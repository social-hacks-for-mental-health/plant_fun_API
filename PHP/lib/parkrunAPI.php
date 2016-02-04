<?php

class parkrunAPI {

	private $api;
	private $token;
	private $curlhandle;
	private $tokenError;
	private $debugging=false;
	private $caching=false;

	private $user_token=false;
	private $username=null;
	private $password=null;

	private $scope;
	private $keypath;
	private $cachepath="/var/run/parkrun/api/.parkrun.token.cache";
	private $expiry_buffer=5;
	private $cache_umask=0077;

	public function __construct( $keypath="/usr/local/keys/api/.parkrunapi.keys",$api="https://test-api.parkrun.com/",$scope="core", $options=array()) {

		if ((isset($options['scope'])) && (!(isset($scope)))) {
			$this->scope=$options['scope'];
		} elseif (!(isset($scope))) {
			$this->scope='core';
		} else {
			$this->scope=$scope;
		}

		if ((isset($options['keypath'])) && (!(isset($keypath)))) {
			$this->keypath=$options['keypath'];
		} elseif (!(isset($keypath))) {
			$this->keypath="/usr/local/keys/api/.parkrunapi.keys";
		} else {
			$this->keypath=$keypath;
		}

		if ((isset($options['api'])) && (!(isset($api)))) {
			$this->api=$options['api'];
		} elseif (!(isset($api))) {
			$this->api="https://test-api.parkrun.com/";
		} else {
			$this->api=$api;
		}

		if ( ((isset($options['user_token']))) && ($options['user_token']==true) ) {
			$this->debug("Is user_token");
			if ( (isset($options['username'])) && (isset($options['password']))) {
				$this->username=$options['username'];
				$this->password=$options['password'];
				$this->user_token=true;
			} else {
				$this->debug("Need username and password options for user_token");
				error_log("Need both username and password options for user_token");
			}
		}

		if (is_array($options)) {
			if ((isset($options['debug'])) && ($options['debug']==true)){
				$this->debugging=$options['debug'];
				error_log("parkrun phpAPI: Debugging enabled");
			}
			if ((isset($options['cachepath'])) && (!(isset($cachepath)))) {
				$this->cachepath=$options['cachepath'];
			}
			if ((isset($options['caching'])) && (!(isset($cachepath)))) {
				$this->caching=$options['caching'];
			}
			if ((isset($options['umask'])) && (!(isset($umask)))) {
				$this->cache_umask=$options['umask'];
			}
		}

		if(substr($this->api, -1) !== '/') {
			$this->debug("Adding trailing / to api [$this->api]");
			# need a suffix
			$this->api.="/";
		}

		umask($this->cache_umask);

		if ($this->caching) {
			$this->loadAccessToken ($api );
		} 
		if (!($this->isValid())) {
			$this->debug("No valid token");
			$this->getAccessToken ();
			if ($this->caching) {
				$this->saveAccessToken();
			}
		}
	}

	private function debug( $msg=null ) {
		if (($this->debugging)&&(isset($msg))) {
			error_log("parkrun phpAPI client: $msg");
		}
	}

	private function saveAccessToken () {
		if (($this->token)&&($this->caching)) {
			if (file_put_contents($this->cachepath, serialize($this->token))) {;
				$this->debug("Saved contents to [$this->cachepath]");
			} else {
				$this->debug("UNABLE to save contents to [$this->cachepath]");
			}
		}
	}
	private function loadAccessToken () {
		if (($this->caching)&&(is_readable($this->cachepath))) {
			$this->debug("Loading from [$this->cachepath]");
			$contents=file_get_contents($this->cachepath);
			if ($contents) {
				$this->token=unserialize($contents);
				if (!($this->tokenExpired())) {
					$this->debug("token is valid");
					$this->curlhandle=curl_init();
					$this->setCurl($this->token->access_token);
				} else {
					$this->debug("token has expired; renewing");
					$this->getAccessToken();
					$this->saveAccessToken();
				}

			} else {
				$this->debug("UNABLE to load contents from [$this->cachepath]");
				$this->getAccessToken ();
				$this->saveAccessToken();
			}
		} else {
			if ($this->caching) {
				$this->debug("Caching enabled, but cache file not readable");
				$this->getAccessToken ();
				$this->saveAccessToken();
			} else {
				$this->getAccessToken ();
			}
		}
	}

	private function getAccessToken ( ) {
                $this->debug("Requesting new token");
		if (file_exists($this->keypath)) {
			$contents=file_get_contents($this->keypath);
			$pattern="/^client_id:=(.*)\n/";
			preg_match_all($pattern,$contents,$match);
			if ($match) {
				$user=trim($match[1][0]);
			}
			$pattern="/(.*)client_secret:=(.*)/";
			preg_match_all($pattern,$contents,$match);
			if ($match) {
				$secret=trim($match[2][0]);
			}
		} else {
                        $this->debug("No such file [$this->keypath]");
                        $this->token=null;
                        $this->curlhandle=null;
                        $this->tokenError=null;
                        return null;
		}

		if ((isset($user))&&(isset($secret))) {
			$this->curlhandle=curl_init();

			# Basic auth first so not using get_headers
			$headers=array(
					"Accept: application/json",
					"Authorization: Basic " . base64_encode("$user:$secret")
					);

			$params=array(
				"scope"=>$this->scope
			);
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$headers);
			if ($this->user_token==true) {
				$this->debug("is user_token: Setting username/password credentials");
				curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."auth/user");
				$params["username"]=$this->username;
				$params["password"]=$this->password;
				$params["grant_type"]='password';
			} else {
				$params["grant_type"]='client_credentials';
				curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."auth/token");
			}
			curl_setopt($this->curlhandle,CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($this->curlhandle,CURLOPT_POST,true);
			curl_setopt($this->curlhandle,CURLOPT_POSTFIELDS, http_build_query($params));

			curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
			$result=curl_exec($this->curlhandle);

			if ($result) {
				$decoded=json_decode($result);
				if ( ($decoded)&&(isset($decoded->access_token))) {
					#var_dump($decoded);
					# set handler to GET with bearer token
					curl_setopt($this->curlhandle,CURLOPT_POST,false);
					$headers=array(
							'Authorization: Bearer '.$decoded->access_token,
							'Content-type: application/json'
							);
					curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$headers);
					$decoded->expires_at=time()+($decoded->expires_in-$this->expiry_buffer);
					$this->token=$decoded;
					# Set the expires_at timestamp, but deduct a few seconds for paranoia
				} else {
					curl_close($this->curlhandle);
					$this->token=null;
					$this->curlhandle=null;
					$this->tokenError=$this->handle_error($result);
					return($this->tokenError);
				}
			} else {
				curl_close($this->curlhandle);
				$this->token=null;
				$this->curlhandle=null;
			}
		} else {
			$this->debug("No user credentials in [$this->keypath]");
			$this->token=null;
		}
	}
	
	private function setCurl($access_token) {
		curl_setopt($this->curlhandle,CURLOPT_POST,false);
		$headers=array(
				'Authorization: Bearer '.$access_token,
				'Content-type: application/json'
				);
		curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($this->curlhandle,CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
	}

	public function isValid() {
		return ( $this->token != null );
	}

	public function tokenExpired() {
		$time=time();
		$this->debug("Token expiry ".$this->token->expires_at." time $time returning [".($this->token->expires_at >= $time)."]");
		return ( $time >= $this->token->expires_at);
	}

	public function tokenError() {
		return ( $this->tokenError );
	}

	public function access_token() {
		return ( $this->token->access_token );
	}

	function  __destruct() {
		$this->api=null;
		$this->token=null;
		if ($this->curlhandle!=null) {
			curl_close($this->curlhandle);
		}
		$this->curlhandle=null;
	}

	private function fetch ($resource) {
		if (isset($this->curlhandle)) {
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

			#### verbose #### $this->debug("Requesting [".$this->api."$resource]");
			
			curl_setopt($this->curlhandle,CURLOPT_HEADER,true);
			curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
			$result=curl_exec($this->curlhandle);
			#### verbose #### $this->debug("Result [$result]");
			$header_size=curl_getinfo($this->curlhandle,CURLINFO_HEADER_SIZE);

			curl_setopt($this->curlhandle,CURLOPT_HEADER,false);
			$header=preg_split('/\n/',substr($result,0,$header_size));
			#### verbose #### $this->debug("Header [$header] Body [".substr($result,$header_size)."]");
			return (array('header'=>$header,'body'=>substr($result,$header_size)));
		} else {
			error_log("parkrun phpAPI client: invalid token");
			return null;
		}
	}

	private function tidyResource($resource) {
		# remove leading / - it's enforced on $this->api
		if (substr($resource,0,1)=='/') {
			$this->debug("Dropping leading /");
			$resource=substr($resource,1);
		}
		# no logging of password resources
		if (!(strpos($resource,'/password'))) {
			$this->debug("resource is [$resource]");
		}
		return $resource;
	}

	public function RequestResource($resource,$depth=0) {
		if (isset($resource)) {
			$resource=$this->tidyResource($resource);

			$resultArr=$this->fetch($resource);
			$result=$resultArr['body'];
			$this->debug("result [$result]");
			if ($result) {
				$obj=json_decode($result);
				if (!($obj)) {
					return($this->handle_error($result));
				} elseif (isset($obj->status)&&($obj->status=='false')) {
					if ((isset($obj->error->code))&&($obj->error->code==401)) {
						if ($depth<2) {
							$this->debug("Token expired on server; refreshing");
							$this->getAccessToken();
							$this->saveAccessToken();
							# try request again
							return($this->RequestResource($resource,$depth+1));
						} else {
							$this->debug("Too many refresh attempts");
							return($this->handle_error($result));
						}
					}
#					if (isset($obj['status']
				}
				$next=$this->parkrunFetchRel($obj,'next');
				if (isset($resultArr['header'])) {
					$match=preg_grep('/^Content-Range:/',$resultArr['header']);
					if ($match) {
						$keys=array_keys($match);
						$basemeta=explode("/",str_replace("Content-Range: ","",$match[$keys[0]]));
						if (isset($basemeta[1])) {
							$meta=array("max"=>$basemeta[1],"range"=>$basemeta[0]);	
						} else {
							$meta=array("max"=>'unknown',"range"=>'unknown');
						}
					} else {
						$meta=null;
					}
				} else {
					$meta=null;
				}
				return(array('object'=>$obj,'next'=>$next,'error'=>null,'meta'=>$meta));
			} else {
				return(array('object'=>null,'next'=>null,'error'=>'No data returned','meta'=>null));
			}
		} else {
			return(array('object'=>null,'next'=>null,'error'=>'No resource provided','meta'=>null));
		}
	}

	private function parkrunFetchRel($object,$name) {
		if (isset($object)&&(isset($object->links))) {
			foreach ( $object->links as $rel ) {
#				var_dump($rel);
#				echo "Have rel ".$rel->rel;
				if ( $rel->rel==$name) {
#					echo "Found [$name] returning ".$rel->href;
					return str_replace('"','',$rel->href);
				}
			}
		}
		return null;
	}

	public function CreateResource( $resource, $fields) {
		if ((isset($resource))&&(is_array($fields))) {
			$resource=$this->tidyResource($resource);

			error_reporting(E_ALL);
			#$this->debug("resource is ".$this->api.$resource);
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

			$header=$this->get_headers('POST');
			$this->debug("headers ".print_r($header,true));
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$header);
			curl_setopt($this->curlhandle,CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($this->curlhandle,CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($this->curlhandle,CURLOPT_POST,true);
			curl_setopt($this->curlhandle,CURLOPT_POSTFIELDS, http_build_query($fields));
			$result=curl_exec($this->curlhandle);

#			$header_size=curl_getinfo($this->curlhandle,CURLINFO_HEADER_SIZE);
#			curl_setopt($this->curlhandle,CURLOPT_HEADER,false);
#			$header=preg_split('/\n/',substr($result,0,$header_size));
			#$this->debug('headers: '.print_r($header,true));
#			$this->debug("result is [".print_r($result,true)."]");
#			$this->debug("header size $header_size is [".print_r($header,true)."]");

#			$http_status= curl_getinfo($this->curlhandle);
#			$this->debug("http_status: [".print_r($http_status,true)."]");
			curl_setopt($this->curlhandle,CURLOPT_POST,false);
			curl_setopt($this->curlhandle,CURLOPT_CUSTOMREQUEST, "GET");
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('GET'));

			return ( $this->handle_error($result) );
		} else {
			$this->debug("resource or fields not set");
			return null;
		}
	}

	public function ModifyResource( $resource, $fields) {
		if ((isset($resource))&&(is_array($fields))) {
			$resource=$this->tidyResource($resource);

			#$this->debug("resource is ".$this->api.$resource);
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('PUT'));

			curl_setopt($this->curlhandle,CURLOPT_POSTFIELDS, http_build_query($fields));
			curl_setopt($this->curlhandle,CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
			$this->debug("fields are ".http_build_query($fields));

			$result=curl_exec($this->curlhandle);
			$curl_error=curl_error($this->curlhandle);

			curl_setopt($this->curlhandle,CURLOPT_POST,false);
			curl_setopt($this->curlhandle,CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('GET'));

			$this->debug("curl_error: [$curl_error]");
			return ( $this->handle_error($result) ) ;
		}
		return null;
	}

	public function DeleteResource($resource, $fields) {
		if (isset($resource) && is_array($fields)) {
			$resource=$this->tidyResource($resource);

			curl_setopt($this->curlhandle, CURLOPT_URL, $this->api . "$resource");
			curl_setopt($this->curlhandle, CURLOPT_HTTPHEADER, $this->get_headers('DELETE'));
			curl_setopt($this->curlhandle, CURLOPT_POST, true);
			curl_setopt($this->curlhandle, CURLOPT_POSTFIELDS, http_build_query($fields));

			$result = curl_exec($this->curlhandle);
			$curl_error = curl_error($this->curlhandle);

			# restore defensive defaults
			curl_setopt($this->curlhandle, CURLOPT_POST, false);
			curl_setopt($this->curlhandle,CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($this->curlhandle, CURLOPT_HTTPHEADER, $this->get_headers('GET'));

			return $this->handle_error($result);
		}
		return null;
	}

	private function get_headers ( $type='GET' ) {
		if ($type=='GET') {
			return array(
				'Authorization: Bearer '.$this->token->access_token,
				'Content-type: application/json'
				);
		} else if ($type=='PUT') {
			return array(
					"Accept: application/json",
					'Authorization: Bearer '.$this->token->access_token,
					'X-HTTP-Method-Override: PUT'
					);

		} else if ($type=='POST') {
			return array(
					"Accept: application/json",
					'Authorization: Bearer '.$this->token->access_token,
				);
		} else if ($type=='DELETE') {
			return array(
					'Authorization: Bearer ' . $this->token->access_token,
					'X-HTTP-Method-Override: DELETE'
				);
		}
	}

	private function handle_error( $result ) {
		$this->debug("handle_error: ".print_r($result,true));
		if (isset($result)) {
			$obj=json_decode($result);
			if (!($obj)) {
				if (strpos($result,'404 Page Not Found')!== false) {
					return(array('object'=>null,'error'=>"Resource not found",'next'=>null,'meta'=>null));
				} elseif (strpos($result,'Named route not found for name')!== false) {
					return(array('object'=>null,'error'=>"Resource not found",'next'=>null,'meta'=>null));
				} else {
					return(array('object'=>null,'error'=>"API has returned an invalid JSON object [$result] - please raise with support and reference api [$this->api] timestamp [".date(DATE_RFC2822)."]",'next'=>null,'meta'=>null));
				}
			}
			$this->debug("handle_error obj: ".print_r($obj,true));
			if (isset($obj->status) && ($obj->status=='false')) {
				$this->debug("error: ".print_r($obj,true));
				return(array('object'=>$obj,'error'=>$obj->error->human_message,'next'=>null,'meta'=>null));
			} elseif (isset($obj->status) && ($obj->status=='true')) {
				$next=$this->parkrunFetchRel($obj,'next');
				return(array('object'=>$obj,'error'=>null,'next'=>$next,'meta'=>null));
			} else {
				if ((isset($this->curlhandle))&&(curl_errno($this->curlhandle))) {
					$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
					return(array('object'=>$obj,'error'=>"error occurred: http status $http_status",'meta'=>null,'next'=>null));
				} else {
					return(array('object'=>$obj,'error'=>'unknown error occurred','meta'=>null,'next'=>null));
				}
			}
		} else {
			return(array('object'=>null,'error'=>$curl_error,'next'=>null));
		}
	}

	public function FetchAll ($resource,$resourceName,$fetchLimit=5) {
		if (isset($resource)) {
			$this->debug("Fetching first resource [".$resource."]");
			$fetches=0;
			$finalresults=array();
			$error=null;
			$returnresults=new stdClass();
			$returnresults->data=new stdClass();

			while ( (isset($resource)) && ($fetches<$fetchLimit) ) {
				$this->debug("Fetching resource #$fetches [$resource]]");
				$item=$this->RequestResource($resource);
				$fetches++;
				if (isset($item['object']) && isset($item['object']->data->$resourceName)) {
					foreach ($item['object']->data->$resourceName as $event) {
						array_push($finalresults,$event);
					}
					$this->debug("Have item[next] ".print_r($item['next'],true));
					$resource=$item['next'];
				} else {
					$resource=null;
					if (isset($item['error'])) {
						$error=$item['error'];
					} else {
						$error='request error';
					}
				}
			}
			if (isset($finalresults)) {
				$returnresults->data->$resourceName=$finalresults;
			} else {
				$returnresults=null;
			}
			return (array('all'=>$returnresults,'fetches'=>$fetches,'fetchlimit'=>$fetchLimit,'error'=>$error,'meta'=>null));
		} else {
			return(array('object'=>null,'next'=>null,'error'=>'No resource provided','meta'=>null));
		}
	}

}


?>

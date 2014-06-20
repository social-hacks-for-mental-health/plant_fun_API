<?php


class parkrunAPI {

	private $api;
	private $token;
	private $curlhandle;
	private $tokenError;
	private $debug=0;

	public function __construct( $path="/usr/local/keys/api/.parkrunapi.keys",$api="https://test-api.parkrun.com",$scope="core",$cachepath="/usr/local/keys/api/.parkrunapi.cached") {
#		$this->loadAccessToken ("/tmp/rl.test",$api);
		$this->getAccessToken ( $path, $api, $scope );

#		$this->saveAccessToken ("/tmp/rl.test");
	}

	private function saveAccessToken ($cachepath) {
		if ((isset($cachepath))&&($this->token)) {
			if (file_put_contents($cachepath, serialize($this->token))) {;
				error_log("Saved contents to [$cachepath]");
			} else {
				error_log("UNABLE to save contents to [$cachepath]");
			}
		}
	}
	private function loadAccessToken ($cachepath,$api) {
		error_log("Loading from [$cachepath]");
		if (isset($cachepath)) {
			$contents=file_get_contents($cachepath);
			if ($contents) {
				$this->token=unserialize($contents);
				print_r($this->token);

				if ($this->token) {
					$this->curlhandle=curl_init();
					$this->setCurl($this->token->access_token);
					$this->setAPI($api);
				}
			} else {
				error_log("UNABLE to load contents from [$cachepath]");
			}
		}
	}


	private function getAccessToken ( $path, $api, $scope ) {
		if (!(isset($path))) {
			$path="/usr/local/keys/api/.parkrunapi.keys";
		}
		if (!(isset($scope))) {
			$scope='core';
		}
		if (file_exists($path)) {
			$contents=file_get_contents($path);
			$pattern="/^client_id:=(.*)\n/";
			preg_match_all($pattern,$contents,$match);
			if ($match) {
				$user=$match[1][0];
			}
			$pattern="/(.*)client_secret:=(.*)\n/";
			preg_match_all($pattern,$contents,$match);
			if ($match) {
				$secret=$match[2][0];
			}
		} else {
			echo "No such file [$path]";
			die;
		}

		if ((isset($user))&&(isset($secret))) {
			$this->setAPI($api);
			$this->curlhandle=curl_init();

			# Basic auth first so not using get_headers
			$headers=array(
					"Accept: application/json",
					"Authorization: Basic " . base64_encode("$user:$secret")
					);

			$params=array(
				"grant_type"=>'client_credentials',
				"scope"=>$scope
			);
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$headers);
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."/token.php");
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

					$this->token=$decoded;
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
			echo "No user credentials in [$path]";
			$this->token=null;
		}
	}

	private function setAPI($api) {
		if (!(isset($api))) {
			$this->api="https://test-api.parkrun.com";
		} else {
			$this->api=$api;
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
		curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

		curl_setopt($this->curlhandle,CURLOPT_HEADER,true);
		curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
		$result=curl_exec($this->curlhandle);
		$header_size=curl_getinfo($this->curlhandle,CURLINFO_HEADER_SIZE);

		curl_setopt($this->curlhandle,CURLOPT_HEADER,false);
		$header=preg_split('/\n/',substr($result,0,$header_size));
		if ($this->debug===1) {
			error_log('headers: '.print_r($header,true));
		}
		return (array('header'=>$header,'body'=>substr($result,$header_size)));
	}

	public function RequestResource($resource) {
		if (isset($resource)) {
			$resultArr=$this->fetch($resource);
			$result=$resultArr['body'];
			if ($this->debug===1) {
				error_log("result [$result]");
			}
			if ($result) {
				$obj=json_decode($result);
				if (!($obj)) {
					return($this->handle_error($result));
				}
				$next=$this->parkrunFetchRel($obj,'next');
				if (isset($resultArr['header'])) {
					$match=preg_grep('/^Content-Range:/',$resultArr['header']);
					if ($match) {
						#error_log("Have content range");
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
			error_reporting(E_ALL);
			#error_log("resource is ".$this->api.$resource);
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

			$header=$this->get_headers('POST');
			if ($this->debug===1) {
				error_log("headers ".print_r($header,true));
			}
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$header);
			curl_setopt($this->curlhandle,CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($this->curlhandle,CURLOPT_POST,true);
			curl_setopt($this->curlhandle,CURLOPT_POSTFIELDS, http_build_query($fields));
			$result=curl_exec($this->curlhandle);

#			$header_size=curl_getinfo($this->curlhandle,CURLINFO_HEADER_SIZE);
#			curl_setopt($this->curlhandle,CURLOPT_HEADER,false);
#			$header=preg_split('/\n/',substr($result,0,$header_size));
			#error_log('headers: '.print_r($header,true));
#			error_log("result is [".print_r($result,true)."]");
#			error_log("header size $header_size is [".print_r($header,true)."]");

#			$http_status= curl_getinfo($this->curlhandle);
#			error_log("http_status: [".print_r($http_status,true)."]");
			curl_setopt($this->curlhandle,CURLOPT_POST,false);
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('GET'));

			return ( $this->handle_error($result) );
		} else {
			error_log("resource or fields not set");
			return null;
		}
	}

	public function ModifyResource( $resource, $fields) {
		if ((isset($resource))&&(is_array($fields))) {

			#error_log("resource is ".$this->api.$resource);
			curl_setopt($this->curlhandle,CURLOPT_URL,$this->api."$resource");

			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('PUT'));

			curl_setopt($this->curlhandle,CURLOPT_POST,true);
			curl_setopt($this->curlhandle,CURLOPT_POSTFIELDS, http_build_query($fields));
			curl_setopt($this->curlhandle,CURLOPT_RETURNTRANSFER,true);
			#error_log("fields are ".http_build_query($fields));

			$result=curl_exec($this->curlhandle);
			$curl_error=curl_error($this->curlhandle);

			curl_setopt($this->curlhandle,CURLOPT_POST,false);
			curl_setopt($this->curlhandle,CURLOPT_HTTPHEADER,$this->get_headers('GET'));

			if ($this->debug===1) {
				error_log("curl_error: [$curl_error]");
			}
			return ( $this->handle_error($result) ) ;
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
		}
	}

	private function handle_error( $result ) {
		if ($this->debug===1) {
			error_log("handle_error: ".print_r($result,true));
		}
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
			if ($this->debug===1) {
				error_log("handle_error obj: ".print_r($obj,true));
			}
			if (isset($obj->status) && ($obj->status=='false')) {
				if ($this->debug===1) {
					error_log("error: ".print_r($obj,true));
				}
				return(array('object'=>$obj,'error'=>$obj->error->human_message,'next'=>null,'meta'=>null));
			} elseif (isset($obj->status) && ($obj->status=='true')) {
				$next=$this->parkrunFetchRel($obj,'next');
				return(array('object'=>$obj,'error'=>null,'next'=>$next,'meta'=>null));
			} else {
				if (curl_errno($this->curlhandle)) {
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
}


?>

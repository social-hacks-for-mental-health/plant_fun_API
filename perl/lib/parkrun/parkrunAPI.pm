package parkrunAPI;

# Copyright (c) 2013-2014 UKTT Limited (operating as parkrun Group)
# Enquiries/Issues/Bugs to api@parkrun.com
# Please do not distribute without permission.

use strict;
use warnings;
use Exporter qw(import);

our $version = 1.0;
our $caching = 1;
our @ISA = qw(Exporter);
our @EXPORT = qw( LoadKeys FetchToken FetchTokenCache RequestResource FetchSingleResource ConstructMessage FetchAthlete FetchCountry languages countryfolders GetEventEmail FetchAthleteResult FetchAll ) ;
#our %EXPORT_TAGS = ( DEFAULT => [qw(&FetchToken &RequestResource)]);
#constructRequest parkrunAPIClient );

use JSON;
use LWP::UserAgent;
use LWP::Simple;
use LWP::ConnCache; 
use MIME::Base64;
use Data::Dumper;
use DateTime;
use Storable qw(dclone); 

my %cache;
my $parkrun_user;
my $parkrun_secret_key;
my $parkrun_api="https://api.parkrun.com";
our $parkrun_api_version=1;
my $ua=LWP::UserAgent->new();

our $debuglevel=1;
our $debugverbose=9;
our $debuginfo=3;
our $debugbasic=1;

my $browser;

our %languages = (
	1 => { language=>'English', folder_name=>'english', code=>'en' },
	2 => { language=>'Danish', folder_name=>'danish', code=>'da' },
	3 => { language=>'Icelandic', folder_name=>'icelandic', code=>'is' },
	4 => { language=>'Polish', folder_name=>'polish', code=>'pl' },
	5 => { language=>'Russian', folder_name=>'russian', code=>'ru' }
);

our %countryfolders = (	
	3 => 'Australia',
	23 => 'Denmark',
	39 => 'Iceland',
	42 => 'Ireland',
	65 => 'NewZealand',
	74 => 'Poland',
	79 => 'Russia',
	85 => 'SouthAfrica',
	97 => 'UK',
	98 => 'USA',
	102 => 'Zimbabwe'
);

sub init_browser {
	my ($url,$user,$secret,$scope,$token)=@_;
	my $headers;
	my $params;
	if (($user)&&($secret)) {
		$headers = HTTP::Headers->new( 
		    'Content-type' => 'application/json',
		    'Accept' => 'application/json',
		    'Authorization' => 'Basic '. encode_base64($user.':'.$secret),
		);
		$params = {
			grant_type=>'client_credentials'
		};

	} else {
		if ($token) {
			$headers = HTTP::Headers->new( 
			    'Content-type' => 'application/json',
			    'Accept' => 'application/json',
			    'Authorization' => "Bearer $token"
			);
		}
	}

	$browser=LWP::UserAgent->new;
	$browser->default_headers($headers);
}

sub FetchTokenCache {
	my ($url,$user,$secret,$scope,$hr_token)=@_;

	my $tokenfile=".parkrunapi.token";
	my $the_token=LoadToken($tokenfile);

	if ($url) {
		$parkrun_api=$url;
	}

	my $new_token;
	if (!($the_token)) {
		# no token returned
		warn("no token; requesting\n") if ($debuglevel>=$debugverbose);
		$new_token=parkrunAPI::FetchToken($url,$user,$secret,$scope,\$hr_token);
	} else {
		# a token returned
		warn("a token exists\n") if ($debuglevel>=$debugverbose);
		%$hr_token=%{$the_token};
	}
		
	my $now=time();
	if ( ($$hr_token{'access_token'}) && ($now>=($$hr_token{'valid_from'}+$$hr_token{'expires_in'}))) {
		warn("token has expired; refreshing\n") if ($debuglevel>=$debugverbose);
		$new_token=parkrunAPI::FetchToken($url,$user,$secret,$scope,\$hr_token);
	} elsif ( $$hr_token{'access_token'} ) {
		warn("token valid and active\n") if ($debuglevel>=$debugverbose);
	}

	SaveToken($hr_token,$tokenfile);

	init_browser(undef,undef,undef,undef,$$hr_token{'access_token'});
	return $$hr_token{'access_token'};
}
sub SaveToken {
	my ($hr_token,$tokenfile)=@_;
	open(TOKEN,">$tokenfile");
	foreach my $key (keys %$hr_token) {
		print(TOKEN "$key:=".$$hr_token{$key}."\n");
	}
	close(TOKEN);
}
sub LoadKeys {
	my ($file)=@_;
	my ($user,$secret);

	my $hr_keys;
	if ( -f $file ) {
		$hr_keys=LoadToken($file);	
	} else {
		return undef;
	}
	if ( ($$hr_keys{'client_id'}) && ($$hr_keys{'client_secret'}) ) {
		return($$hr_keys{'client_id'},$$hr_keys{'client_secret'});
	} else {
		warn("No client_id or client_secret values\n");
		return undef;
	}
}
sub LoadToken {
	my ($tokenfile)=@_;
	my %token;
	if ( (-f $tokenfile) && ( !-z $tokenfile ) ) {
		open(TOKEN,"<$tokenfile");
		while(my $line=<TOKEN>) {
			chomp($line);
			my ($key,$val)=split(/:=/,$line);
			$token{$key}=$val;
		}
		close(TOKEN);
		return \%token;
	} else {
		return undef;
	}
}

sub FetchToken {
	my ($url,$user,$secret,$scope,$sr_hr_token)=@_;

	if ($url) {
		$parkrun_api=$url;
	}
	if ($user) {
		$parkrun_user=$user;
	} else {
		return undef;
	}
		
	if ($secret) {
		$parkrun_secret_key=$secret;
	} else {
		return undef;
	}
		
	my $headers = HTTP::Headers->new( 
	    'Content-type' => 'application/json',
	    'Accept' => 'application/json',
	    'Authorization' => 'Basic '. encode_base64($user.':'.$secret),
	);
	my $params = {
		grant_type=>'client_credentials'
	};

	if ($scope) {
		$$params{'scope'}=$scope;
	}

	$browser=LWP::UserAgent->new;
	$browser->default_headers($headers);
	warn("Posting: [".$parkrun_api."/token.php]\n") if ($main::debuglevel>=$debugverbose);
	my $response=$browser->post($parkrun_api."/token.php",$params);

	my $token;
	if ($response->is_success) {
		warn("Have: [".$response->decoded_content."]\n") if ($main::debuglevel>=$debugverbose);
		my $obj=decode_json($response->decoded_content);
		$token=$$obj{'access_token'};
		warn("Have token [$token]\n") if ($main::debuglevel>=$debugverbose);

		if ($sr_hr_token) {
			$$sr_hr_token=$obj;
			${$$sr_hr_token}{'valid_from'}=time();	
		}

		$headers = HTTP::Headers->new( 
		    'Content-type' => 'application/json',
		    'Authorization' => "Bearer $token"
		);
		$browser->default_headers($headers);

		return($token);
	} else {
		warn("Have: [".$response->decoded_content."]\n") if ($main::debuglevel>=$debugverbose);
		return undef;
	}
}


sub RequestResource {
	my ($request,$lr_arguments)=@_;

	my $url;
	if ($request=~/^\/v$parkrun_api_version\//) {
		# full link provided
		$url=$parkrun_api.$request;
	} else {	
		# construct it
		$url=$parkrun_api."/v".$parkrun_api_version."/".$request;
	}

	if ($lr_arguments) {
		my $count=0;
		foreach my $arg (@$lr_arguments) {
			warn("Adding [arg] to [$url]\n") if ($main::debuglevel>=$debuginfo);
			if ($count==0) {
				$url.="?$arg";
			} else {
				$url.="&$arg";
			}
			$count++;
		}
	}
	warn("Fetching [$url]\n") if ($main::debuglevel>=$debuginfo);

	
	if ($caching) {
		if ($cache{$url}) {
			warn("Cache hit for [$url]\n") if ($main::debuglevel>=$debuginfo);	
			my $lr=$cache{$url};	
			my ($obj,$next)=@{$lr};
		#	my $returnobj=dclone($obj);
		#	return ($returnobj,$next);
			return ($obj,$next);
		}
	}	
	my $response=$browser->get($url);

	if ($response->is_success) { 
		warn("Have: [".$response->decoded_content."]\n") if ($main::debuglevel>=$debugverbose);
		if (length($response->decoded_content)>0) {
			if ($response->decoded_content=~/^INTERNAL/) {
				die("Unable to process: ".$response->decoded_content."\n");
			} 
			my $obj=decode_json($response->decoded_content);
			my $next=parkrunFetchRel($obj,'next');
			if ($caching) {
				my @item=($obj,$next);
				##print("Caching url [$url] with obj [$obj] and next [$next] \n");
				$cache{$url}=\@item;
			} 
			return ($obj,$next);
		} else {
			warn("Invalid data returned\n");
			return undef;
		}
	} else {
		die("Resource request failed: Requested: $url Response: ".$response->status_line."\n");
	}
}

# iterate through the links and return the link requested
sub parkrunFetchRel {
	my ($obj,$name)=@_;

	foreach my $rel (@{$$obj{'links'}}) {
		if ($$rel{'rel'} eq $name) {
			my $href=$$rel{'href'};
			$href=~s/"//g;
			return($href);
		}
	}
	return undef;
}


sub ConstructMessage {
	my ($id,$requestType,$identifier,$data,$hr_additional)=@_;

	if (!($identifier)) {
		$identifier='AthleteID';
	}
	my %message = (
		"$identifier"=>$id,
		requestType=>$requestType,
	);
	if ($data) {
		$message{'data'}{'Athlete'}=$data;
	}

	if ($hr_additional) {
		foreach my $key (keys %$hr_additional) {
			warn("Adding [$key=$$hr_additional{$key}] to JSON\n") if ($debuglevel>=$debugverbose);
			$message{$key}=$$hr_additional{$key};
		}
	}
	my $msg=encode_json(\%message);
	return $msg;
}

my %singlecache;
sub FetchSingleResource {
	my ($resource,$resourceid,$identifier) = @_;

	my $fetch="$resource/$resourceid";
	if (!($identifier)) {
		$identifier=ucfirst($resource);
	}
	while ($fetch) {
		if (!($singlecache{$resource}{$resourceid})) {
			my ($results,$next)=parkrunAPI::RequestResource($fetch);
			if ($next) {
				warn("Multiple results returned on resource [$resource] id [$resourceid] fetch\n");
			}
			$singlecache{$resource}{$identifier}=$$results{'data'}{$identifier}[0];
		} else {
			#warn("[$resource][$identifier] cached\n");
		}
		#return($$results{'data'}{$capresource}[0]);
		return($singlecache{$resource}{$identifier});
	}
}
sub FetchCountry {
	my ($countrycode) = @_;

	my $fetch="countries/$countrycode";
	while ($fetch) {
		my ($results,$next)=parkrunAPI::RequestResource($fetch);
		if ($next) {
			warn("Unexpected results returned on countrycode [$countrycode] fetch\n");
		}
		return($$results{'data'}{'Countries'}[0]);
	}
}

sub FetchAthlete {
	my ($athleteid) = @_;

	if ($athleteid) {
		my $fetch="athletes/$athleteid";
		while ($fetch) {
			my ($results,$next)=parkrunAPI::RequestResource($fetch);
			if ($next) {
				warn("Unexpected results returned on athleteid [$athleteid] fetch\n");
			}
			return($$results{'data'}{'Athletes'}[0]);
		}
	} else {
		return undef;
	}
}

sub FetchAthleteResult {
	my ($athleteid,$raceid,$eventid) = @_;

	my $fetch="athletes/$athleteid/events/$raceid/runs/$eventid/results";
	while ($fetch) {
		my ($results,$next)=parkrunAPI::RequestResource($fetch);
		if ($next) {
			warn("Unexpected results returned on athleteid [$athleteid] fetch\n");
		}
		return($$results{'data'}{'Results'}[0]);
	}
}

sub FetchAll {
	my ($request)=@_;

	my $fetch=$request;
	my ($finalresults,$results,$next);
	while ($fetch) {
		($results,$next)=parkrunAPI::RequestResource($fetch);
		if ($finalresults) {
			foreach my $key (keys(%{$$finalresults{'data'}})) {
				push(@{$$finalresults{'data'}{$key}},@{$$results{'data'}{$key}});
			}
		} else {
			#$$finalresults{'data'}=$$results{'data'};
			# we need to COPY the structure
			# we need to COPY the structure
			# we need to COPY the structure
			#$$finalresults{'data'}={%{$$results{'data'}}};
			$$finalresults{'data'}=dclone($$results{'data'});
		}
		$fetch=parkrunFetchRel($results,'next');
	}
	return ($$finalresults{'data'});
}

1;


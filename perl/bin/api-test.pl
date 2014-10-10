#!/usr/bin/perl -w 

# Copyright (c) 2013-14 UKTT Limited (operating as parkrun Group)
# Enquiries/Issues/Bugs to api@parkrun.com
# Do not distribute without permission.

use strict;

use FindBin;
use lib "$FindBin::Bin/../lib";
use JSON;
use LWP::UserAgent;
use LWP::Simple;
use MIME::Base64;
use Data::Dumper;
use DateTime;
use parkrun::parkrunAPI;
use Getopt::Long;
use WWW::Mechanize;

our $debuglevel=1;
our $debugverbose=9;

my ($parkrun_user,$parkrun_secret_key)=parkrunAPI::LoadKeys(".parkrunapi.keys");

if (!($parkrun_user)) {
	($parkrun_user,$parkrun_secret_key)=parkrunAPI::LoadKeys($ENV{"HOME"}."/.parkrunapi.keys");
} 

if (!($parkrun_user)) {
	warn("Could not load keys - Please add keys to: ./.parkrunapi.keys or ~/.parkrunapi.keys\n");
}

my $lastrun;
my $api;
my $resource;
my $maxfetch=5;
my $scope='ALL';
my $fetchall;
my @arguments;
my $jsononly;
GetOptions("lastrun=i"=>\$lastrun,"api=s"=>\$api,"resources|resource=s"=>\$resource,"fetchall"=>\$fetchall,"maxfetch=i"=>\$maxfetch,"debug=i"=>\$debuglevel,"scope=s"=>\$scope,"client_id=s"=>\$parkrun_user,"client_secret=s"=>\$parkrun_secret_key,"args=s"=>\@arguments,'json|jsononly'=>\$jsononly);

our $parkrun_api_version=1;

# get time 6 hours ago
my $dt;

if ($lastrun) {
	$dt=DateTime->from_epoch( epoch=>$lastrun);
} else {
	$dt=DateTime->now();
	$dt->subtract(minutes => 15);
}
$dt->set_time_zone('Europe/London');
my $timestamp=$dt->ymd('').$dt->hms('');

# format for UTF8
binmode STDOUT, ":utf8";


my $url;
if ($api) {
	$url=$api;
} else {
	$url='https://test-api.parkrun.com';
}
my %hr_token;
my $token=parkrunAPI::FetchTokenCache($url,$parkrun_user,$parkrun_secret_key,$scope,\%hr_token);

if (!($token)) {
	die("No token returned\n");
} else {
	if  ($debuglevel>=$debugverbose) {
		warn("Have token [$token] and timestamp [$hr_token{'valid_from'}] expires [$hr_token{'expires_in'}]\n");
#		print Dumper(%hr_token);
	}
}

my @messages;
my $fetch=$resource;
if ($resource=~/^\//) {
	warn("Dropping leading /\n") unless ($jsononly);
	$fetch=~s/^\///g;
}
my $time=time();
$time-=1;
my $fetchcount=0;

if ($fetchall) {
	my $res=parkrunAPI::FetchAll($fetch);
	if ($jsononly) {
		print encode_json($res);
	} else {
		print Dumper(parkrunAPI::FetchAll($fetch));
	}
} else {
	while ( $fetch ) {
		$fetchcount++;
		my ($results,$next)=parkrunAPI::RequestResource($fetch,\@arguments);
		if ($fetchcount==1) {
			@arguments=();
		}
		if ($jsononly) {
			print encode_json($results);
		} else {
			print Dumper($results);
		}
		if (!($next)) {
			exit;
		} elsif ( ($next) && ($fetchcount<$maxfetch) ){
			print("Fetching next...\n") unless ($jsononly);
			$fetch=$next;
		} elsif ($fetchcount>=$maxfetch) {
			exit;
		}
	}
}


Node.js client library
======================

A simple, promises based client library for the parkrun API.

Usage
-----

<pre>
	var parkrunAPI = require('parkrun-api.js');
	var API = new parkrunAPI("https://test-api.parkrun.com", client_id, client_secret);
	
	API.getEvents().then(function(events) { 
		... do something with events
	}).catch(function(err) {
	    .. handle errors
	);
</pre>



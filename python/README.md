python library
============

Modules required
------------------

* request
* re
* os

Usage Example
-------------

* Create a file ~/.parkrunapi.keys containing access keys in format:
<pre>
	client_id:=your-parkrun-client-id
	client_secret:=your-parkrun-client-secret
</pre>

* `chmod 400 ~/.parkrunapi.keys` like the good unix user you are

* add checked out library directory PYTHONPATH: (other methods are available…)
	export PYTHONPATH=$PYTHONPATH:/Users/ian/parkrun/github/parkrunAPI/python/lib
	(or add to .bashrc/.zshrc)

* Example in src directory


NOTE - this is a proof of concept, there's very little error checking and no token caching yet…


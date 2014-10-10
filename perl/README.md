perl library
============

Libraries required
------------------

* JSON
* LWP
* MIME::Base64
* DateTime
* WWW::Mechanize

Usage Example
-------------

* Create a file ~/.parkrunapi.keys containing access keys in format:

`client_id:=your-parkrun-client-id`
`client_secret:=your-parkrun-client-secret`

* `chmod 400 ~/.parkrunapi.keys` like the good unix user you are

* Example using api-test.pl client: `bin/api-test.pl --resource athletes/yourathleteid/results  --api https://test-api.parkrun.com`


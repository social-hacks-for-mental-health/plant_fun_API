var testCase  = require('nodeunit').testCase;
var parkrunAPI = require('../lib/parkrun-api.js');
var nock = require('nock');

/*var auth = nock('https://test-api.parkrun.com')
                .post('/auth/token')
                .twice().reply(200, {
                  access_token: 'test_token',
                });*/
var auth = nock('https://test-api.parkrun.com:443')
  .post('/auth/token', "grant_type=client_credentials&scope=core")
  .reply(200, { access_token: 'auto_token' })
  .post('/auth/token', "grant_type=client_credentials&scope=core")
  .replyWithError({error: true})
  .post('/auth/token', "grant_type=client_credentials&scope=core")
  .reply(200, { access_token: 'initial_token' });
var expired = nock('https://test-api.parkrun.com:443')
  .post('/auth/token', "grant_type=client_credentials&scope=core")
  .reply(200, { access_token: 'refresh_token' })
  .post('/auth/token', "grant_type=client_credentials&scope=core")
  .replyWithError({error: true});


var API = new parkrunAPI("https://test-api.parkrun.com", "user", "secret");

module.exports.testFilter = testCase({
    "Test filter": function(test) {
    	test.expect(4);

    	var arr = new Array()
    	arr.push({"a": 1, "b": 2, "c": 3, "d": 4});
    	var filtered = API._filter(arr, ["a", "d"]);
    	var props = 0;
    	for(x in filtered[0]) {
    		props++;
    	}
    	test.equals(filtered.length, 1, "array length one")
    	test.equals(props, 2, "has two properties");
        test.ok(filtered[0].hasOwnProperty("a"), "has property a");
        test.ok(filtered[0].hasOwnProperty("d"), "has property d");
        test.done();
    }
})

module.exports.testGetMax = testCase({
    "Test getMax": function(test) {
        test.expect(1);
        test.equals(685, API._getMax({"EventsRange":[{"first":100,"last":200,"max":685}]}), "extract max 685");
        test.done();
    }
})

module.exports.testAuthorise = testCase({
    "Test authorise": function(test) {
        test.expect(3);
        API._authorise().then(function() {
            test.ok(false, "this should be an error");
        }).catch(function() {
            test.ok(true, "test error in authorisation");
            API._authorise().then(function() {
                test.equals("initial_token", API.access_token, "test successful authorisation")
                test.ok(auth.done, "test auth nocks used");
                test.done();
            });
        })
    }
})

module.exports.testExpiry = testCase({
	"Test expiry": function(test) {
        test.expect(3);
        API._checkToken().then(function() {
            test.equals("initial_token", API.access_token, "token initialised");
            API.expiry = new Date().getTime()-(3670*1000)
            API._checkToken().then(function() {
                test.equals("refresh_token", API.access_token, "test token refreshed");
                API.expiry = new Date().getTime()-(3670*1000)
                API._checkToken().catch(function(err) {
                    test.ok(expired.done, "test expired nocks used");
                    test.done();    
                })
            });
        });       
	}
})

/*
module.exports.testAPICallNoPagination = testCase({
    "Test API call no pagination": function(test) {
        test.fail("Not implemented");
        test.done();
    }
})

module.exports.testAPICallWithPagination = testCase({
    "Test API call with pagination": function(test) {
        test.fail("Not implemented");
        test.done();
    }
})
*/

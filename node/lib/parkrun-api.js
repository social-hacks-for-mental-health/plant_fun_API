// Node.js client library for the parkrun API
// Copyright Tim Poultney 2015
//
//    Licensed under the Apache License, Version 2.0 (the "License");
//    you may not use this file except in compliance with the License.
//    You may obtain a copy of the License at
//
//        http://www.apache.org/licenses/LICENSE-2.0
//
//    Unless required by applicable law or agreed to in writing, software
//    distributed under the License is distributed on an "AS IS" BASIS,
//    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//    See the License for the specific language governing permissions and
//    limitations under the License.

function ParkrunAPI(baseURI, id, secret) {
    var Q = require('q');
    var request = require('request');

    this.id = id;
    this.secret = secret;
    this.baseURI = baseURI;

	this.access_token =  '';
    this.expiry = -1;
    this.request = null;
    this.opts = { jsonnumeric: true };

    var self = this;

    // OAuth2 based authentication
	this._authorise = function() {
        var deferred = Q.defer();
        var opts = {
          method: "post",
          uri: self.baseURI+"/auth/token",
          json: true,
          headers: {
            "Authorization": "Basic " + new Buffer(self.id + ":" + self.secret).toString("base64")
          },
          form: { grant_type: "client_credentials", scope: "core" }
        };

        request(opts, function handleTokenRequest(err, res, body) {
            //console.log(err, body);
            if(!err && body.hasOwnProperty("access_token")) {
                self.access_token = body.access_token;
                self.expiry = new Date().getTime()+(3570*1000);
                self.request = request.defaults({
                    baseUrl: self.baseURI,
                    json: true,
                    gzip: true,
                    headers: { 
                        "Authorization": "Bearer "+self.access_token 
                    }
                });
                deferred.resolve(body);
            } 
            else deferred.reject(err);
        });
        return deferred.promise;
	}

    // Ensure our token is fresh and refetch if not
    this._checkToken = function() {
        var deferred = Q.defer();
        if(new Date().getTime() > self.expiry || self.expiry===-1) {
            this._authorise().then(function() {
                deferred.resolve(true)
            }).catch(function(err) {
                deferred.reject("Unable to authorise: "+err);
            })
        } else deferred.resolve(true);
        return deferred.promise;
    }

    // Reduces the attributes of each Object in arr to only those specified in the fields array
    this._filter = function(arr, fields) {
        if (fields.length>0) { 
            return arr.map(function(item) { 
                ret = {};                 
                for(key in item) { 
                    if(this.indexOf(key)!=-1) ret[key] = item[key];
                }
                return ret;
                }, fields);
        } else return arr;
    }

    // Extract the max from the Content-Range object
    this._getMax = function(range) {
        if(Object.keys(range).length==1) {
            for (key in range) {
                dataKey = key.replace("Range","");
                return range[key][0].max;
            }
        } else {
            console.log("multidata");
        }
    }

    this._getDataKey = function(range) {
        for (key in range) {
            dataKey = key.replace("Range","");
            return dataKey;
        }
    }

    // Calculate the percentage and notify
    this._notify = function(promise) {
        promise.notify(((promise.pagesFetched / promise.totalPages)*100).toFixed(2));
    }

    // generic getter for API uris
    // opts Object will be sent as querystring
    // fields Array will be used to limit the fields returned in each record
    this._getURI = function(uri, opts, fields) {
        delete self.opts.offset;
        opts = (opts || self.opts);
        var deferred = Q.defer()
        fields = (fields || []);

        self._checkToken().then(function() {
            // request the first page
            self.request({uri: uri, qs: opts}, function(err, res, body) {
                if(!err && body.status==="true") {
                    console.log(JSON.stringify(body));
                    // Content-Range denotes paged output, need to fetch additional pages
                    if(body.hasOwnProperty("Content-Range")) {
                        contentRange = body["Content-Range"];
                        
                        if(Object.keys(contentRange).length===1) {
                            deferred.pagesFetched = 1;
                            deferred.expectedLength = self._getMax(contentRange);
                            deferred.dataKey = self._getDataKey(contentRange);
                            deferred.totalPages = Math.ceil((deferred.expectedLength-100)/100)+1;
                            self._notify(deferred);
                            self._fetchPages(uri, opts, fields, body, deferred);
                        } else {
                            deferred.resolve(body.data);
                        }
                    } else {
                        if(fields.length>0) body = self._filter(body, fields);
                        deferred.resolve(body);
                    }
                    
                }
                else {
                    if(err) deferred.reject(err);
                    if(body.status==="false") deferred.reject(body["error"]);
                }

            });
        }).catch(function(err) {
            deferred.reject(err);
        });
        return deferred.promise;
    }

    // handles pagination
    this._fetchPages = function(uri, opts, fields, body, deferred) {
        opts = (opts || self.opts);
        var promises = [];
        if(deferred.expectedLength > 100) {
                for(var i = 0; i<deferred.totalPages-1; i++) {
                    promises.push(self._getNext(uri, opts, i, deferred));   
                }

                    Q.allSettled(promises).then(function(results) {
                        data = body.data[deferred.dataKey];
                        data = self._filter(data, fields);
                        for(var i=0; i<results.length; i++) {
                            if(results[i].state != "fulfilled") deferred.reject(result.reason);
                            var pageData = results[i].value.data[deferred.dataKey];
                            pageData = self._filter(pageData, fields);
                            data = data.concat(pageData);
                        }
                        if(data.length!=deferred.expectedLength) deferred.reject("Data did not paginate correctly, expected "+deferred.expectedLength+" but received "+data.length);
                        deferred.resolve(data);
                    }).catch(function(err) {
                        deferred.reject(err);
                    }).fin(function() {
                        //self.fetching=false;
                    });

            } else {
                console.log(deferred.dataKey+" "+uri+" "+opts+" "+fields);
                try {
                body.data[deferred.dataKey] = self._filter(body.data[deferred.dataKey], fields);
                deferred.resolve(body.data[deferred.dataKey]); 
                } catch (err) {
                    deferred.reject(err);
            }
                 
        }
    }

    // getter to generate promises for pagination
    this._getNext = function(uri, opts, idx, promise) {
        var deferred = Q.defer();
        self._checkToken().then(function() {
            opts = (opts || self.opts);
            opts.offset=(idx+1)*100;
            self.request({uri: uri, qs: opts}, function(err, res, body) {
                /*// START workaround https://github.com/parkrun/parkrunAPI/issues/6
                var max = self._getMax(body["Content-Range"]);
                if(max != promise.expectedLength) {
                    console.log("Updating expectedLength from "+promise.expectedLength+" to "+max);
                    promise.expectedLength = max;
                }
                // END workaround*/
                promise.pagesFetched++;
                self._notify(promise);
                
                if(!err && body.status==="true") deferred.resolve(body);
                else deferred.reject(err);
            })
        }).catch(function(err) {
            // catch token failures
            deferred.reject(err);
        });
        return deferred.promise
    }

    // parkrun API wrappers
    this.getAthletesResults = function(athlete, opts, fields) {
        return self._getURI("/v1/athletes/"+athlete+"/results", opts, fields);
    }

    this.getAthletesVolunteering = function(athlete, opts, fields) {
        return self._getURI("/v1/athletes/"+athlete+"/volunteerduties", opts, fields);
    }

    this.getEventStatistics = function(id, opts, fields) {
        return self._getURI("/v1/events/"+id+"/statistics", opts, fields);
    }

    this.getCancellations = function(opts, fields) {
        return self._getURI("/v1/cancellations", opts, fields);
    }

    this.getCountries = function(opts, fields) {
        return self._getURI("/v1/countries", opts, fields);
    }

    this.getRegions = function(opts, fields) {
        return self._getURI("/v1/regions", opts, fields);
    }

    this.getEvents = function (opts, fields) {
        return self._getURI("/v1/events", opts, fields);
    }

    this.getGeoLocations = function(opts, fields) {
        return self._getURI("/v1/geoLocations", opts, fields);
    }

    this.getResultsForEvent = function(event, opts, fields) {
        return self._getURI("/v1/events/"+event+"/results", opts, fields);
    }

    this.getVolunteersForEvent = function(event, opts, fields) {
        return self._getURI("/v1/events/"+event+"/volunteers", opts, fields);
    }

    this._authorise();
 }

module.exports = ParkrunAPI;

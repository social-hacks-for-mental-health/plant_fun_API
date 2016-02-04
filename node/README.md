#Node.js client library

A simple, promises based client library for the parkrun API.

##Usage

### Simple


```javascript
var parkrunAPI = require('parkrun-api.js');
var API = new parkrunAPI("https://test-api.parkrun.com", client_id, client_secret);
	
API.getEvents().then(function(events) { 
	# do something with events
	
}).catch(function(err) {
	# handle errors
	
);
```
### Advanced

```javascript
Q = require("q");

progress = [[],[],[]];
promises = [API.getCountries(), API.getEvents(), API.getGeoLocations()];

Q.spread(promises, function(countries, events, locations) {
	# do stuff
	
}).catch(function(err) {
	# handle errors
	
}).progress(function(update) {
	progress[update.index].push(parseFloat(update.value));
    total = 0;
    progress.forEach(function(row) {
	    for(i=row.length-1;i>-1;i--) {
	        if(i>0) total += row[i]-row[i-1];
	        else total += row[i];
	    }
	})
	console.log("Progress: "+Math.round((total/(100*promises.length)*100))+"%");
})
```



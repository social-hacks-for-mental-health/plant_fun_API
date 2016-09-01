#!/usr/bin/env python

from parkrunAPI import *

print "Getting UK 5k events..."

# setup API access
api = parkrunAPI()
api.getAccessToken()

# get UK 5k events
resource = '/v1/series/1/countries/97/events'
result = api.fetchAll(resource)

# for each event, print long name
for event in result['Events']:
	print event['EventLongName']
import requests
import re
import os

# responses from parkrun API are returned as a list of dictionaries.
# this function takes the list, key to seach and value to search for as input
# return
def find(lst, key, value):
    for i, dic in enumerate(lst):
        if dic[key] == value:
            return dic
    return -1

class parkrunAPI:
    'Python parkrun API interface'

    __keypath       = ""
    __api           = ""
    __expiry_buffer = 5
    __headers       = ""
    __scope         = ""

    def __init__(self, keypath="~/.parkrunapi.keys", api="https://test-api.parkrun.com", scope="core", options=[]):

        self.__keypath = os.path.expanduser(keypath)
        self.__api = api
        self.__scope = scope


    def getAccessToken(self):

        with open(self.__keypath, 'r') as f:
            contents = f.read()
        match = re.match("^client_id:=(.*)\nclient_secret:=(.*)\n", contents)
        if match:
            client_id=match.group(1)
            client_secret=match.group(2)
        else:
            raise Exception("user and secret not found in: "+self.__keypath)

        payload = {'grant_type':'client_credentials', 'scope':self.__scope}
        url = self.__api+'/auth/token'

        r =  requests.post(url, auth=(client_id,client_secret), data=payload)

        self.token=r.json()['access_token']

        self.headers = {'Accept': 'application/json', 'Authorization':'Bearer '+self.token}

    def fetch(self,resource,params=''):

        url = self.__api+resource

        # print url

        r =  requests.get(url,headers=self.headers,params=params)

        # print r.text

        return r.json()


    def fetchAll(self,resource,params=''):

        out = {}

        while resource:

            res = self.fetch(resource,params)

            # print res

            data = (res['data'])

            if len(out)==0:
                out = data
            else:
                k = list(out.keys())[0]
                out[k]+=data[k]

            links = res['links']

            resource = ''

            for link in links:
                if link['rel']=='next':
                    resource = link['href'].replace("\"","")

            # print resource
        return out

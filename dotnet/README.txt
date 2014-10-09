This directory contains:

* parkrunBasicAPIWrapper.zip - The simplest of the two wrappers we’re producing
* parkrunRestSharpAPIWrapper.zip - The more strongly-typed (in terms of the return data) of the two wrappers we're producing
* APIWrapperExampleInVBNet.zip - An example application written in VB.NET


:: Wrapper types

We’re providing you with two wrappers, the parkrunBasicAPIWrapper (provided) which is a simple wrapper around our OAuth2 authentication and REST requests, and parkrunRestSharpAPIWrapper which supports strong typing, and uses a component called RestSharp (see http://www.restsharp.org).

The simpler wrapper treats the API quite generically, but you may find you need to do a bit more work with the results for them to be useful. 

:: Documentation

the parkrunAPI is documented at https://developer.parkrun.com - Please use your credentials to access this and review all available methods. Our basic example simply fetches a set of results, so should be enough to get you started familiarising yourself with the environment at least.

Please note that the parkrun API contains some experimental and as-yet unfinished end-points. We’d be grateful if you would be discrete about the features some of them imply, as we really don’t yet know if, or when, they’ll see the light of day. 

:: Scope

We’ve configured you with access to the ‘core’ scope. We anticipate tightening the specific scope you will have access to 


:: Installation of example

The example

1. unzip the class libraries (parkrunBasicAPIWrapper and parkrunRestSharpAPIWrapper) and put them in some known location - when deployed they should go in some 'code' type directory in <system> but for testing/playing-around-with it doesn't matter

2. unzip the ASP.NET project, put it somewhere, then open the solution in Visual Studio

3. the references to parkrunBasicAPIWrapper and parkrunRestSharpAPIWrapper will be broken so the ASP.NET project won't build - You'll need to go into the references, remove the broken references and then browse to where you put them in 1. above, then select the parkrunBasicAPIWrapper.dll and parkrunRestSharpAPIWrapper.dll (either from \release or \debug as suits - debug may allow you to step through if you wish) as the reference. You may also need to check the permissions on the unzipped parkrunBasicAPIWrapper and parkrunRestSharpAPIWrapper folders - if necessary give "everyone" read and modify folder permission on that folder and try it like that (obviously don’t do that in a live environment).

4. ASP.NET project should now build and run - It contains your test-api credentials (in the web.config of the ASP.NET project) so it should just work, although please don't battle for hours in case we've missed something.

For reference your TEST credentials are:

client_id: <will be provided; contact support@parkrun.com>
client_secret: <will be provided; contact support@parkrun.com>

Under no circumstances should these credentials be used for live purposes.  

Are valid against test-api.parkrun.com, and should also be used to access https://developer.parkrun.com

I expect you’ll have questions, so please email us via support@parkrun.com.

Please don’t battle away for hours though: If you’re stuck we might be able to help, or it might suggest we’ve not bundled things in quite the right way. Just keen you don’t waste hours when a quick ping could set you straight.
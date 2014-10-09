Imports parkrunBasicAPIWrapper
Imports parkrunRestSharpAPIWrapper
Imports parkrunRestSharpAPIWrapper.ResponseEntities
Imports System.Web.Configuration
Imports System.Net

Public Class TestCalls
    Inherits System.Web.UI.Page

#Region "Constants"

    Private Const _PARKRUN_API_OAUTH2_USERID__APPSETTING_KEY As String = "parkrunAPIOAuth2UserID"
    Private Const _PARKRUN_API_OAUTH2_USERSECRET__APPSETTING_KEY As String = "parkrunAPIOAuth2UserSecret"
    Private Const _PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY As String = "parkrunAPIOAuth2Token"
    Private Const _PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY As String = "parkrunAPIOAuth2TokenExpires"
    Private Const _EXPIRYTIME_FORMAT As String = "yyyyMMddHHmm"

#End Region


#Region "Test the Basic Wrapper"

    Protected Sub btn1_Click(sender As Object, e As EventArgs)
        Dim gotAnyToken As Boolean =
            (Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) IsNot Nothing AndAlso
            Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY).ToString().Length > 0)
        Dim expiryTime As DateTime
        Dim gotExpiryTime As Boolean = False
        Dim tokenShouldBeValid As Boolean = False
        Dim apiToken As String = String.Empty
        If gotAnyToken Then
            ' if we've got a token currently in our store (the Application object in
            ' this example), check to see if it will be expired or still valid
            Dim expiry As String = _
                (If(Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) IsNot Nothing, _
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY).ToString(), String.Empty))
            gotExpiryTime = DateTime.TryParseExact(expiry, _EXPIRYTIME_FORMAT, Nothing, _
                System.Globalization.DateTimeStyles.None, expiryTime)
            tokenShouldBeValid = (If(gotExpiryTime, expiryTime > _
                (DateTime.Now.Subtract(New TimeSpan(0, 2, 0))), False))
        End If
        If tokenShouldBeValid Then
            ' well before expiry time so attempt to use this token - can call an
            ' overload that attempts an auto-reauthenticate on invalid tokens anyway
            apiToken = Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY).ToString()
        Else
            ' we either didn't have a token or it was dodgy w.r.t. the expiry time, so
            ' attempt to get a new token now
            Dim tokenFetchSucceeded As Boolean
            Dim err As Exception = Nothing
            Dim tokenDuration As Integer
            Dim userID As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERID__APPSETTING_KEY)
            Dim userSecret As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERSECRET__APPSETTING_KEY)
            Dim fetcher As New parkrunBasicAPIWrapper.AuthTokenFetcher(userID, userSecret)
            Dim token As String = fetcher.GetOAuth2Token("https://test-api.parkrun.com", "/token.php", "core", tokenDuration, tokenFetchSucceeded, err)
            If tokenFetchSucceeded Then
                ' store the new token in the Application object - this is
                ' just one option and is not available across webfarms,
                ' but each node could keep its own token without issue
                ' (could equally use file/db/<etc> storage)
                Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) = token
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) = DateTime.Now.AddSeconds(tokenDuration).ToString(_EXPIRYTIME_FORMAT)
                apiToken = token
            End If
            tokenShouldBeValid = tokenFetchSucceeded
        End If

        If tokenShouldBeValid Then
            Dim userIDForReAuth As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERID__APPSETTING_KEY)
            Dim userSecretForReAuth As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERSECRET__APPSETTING_KEY)
            Dim success As Boolean, tokenWasExpired As Boolean
            Dim callException As Exception = Nothing
            Dim reAuthorizationException As Exception = Nothing
            Dim responses As New List(Of WebResponse)()
            Dim newTokenIfReAuthed As String = String.Empty
            Dim expiryIfReAuthed As Integer
            Dim contentRanges As List(Of ArrayList) = Nothing
            Dim qs As New Dictionary(Of String, String)()
            ' can add a higher limit to number of records per call - CallGetService_GetAll()
            ' gets batches of this number, so if there's 1250 records and the limit is 100
            ' (the default) then internally CallGetService_GetAll() will have to make 13
            ' individual calls, but if you set limit to e.g. 500, it'll make 3; this should
            ' be set as a compromise between call size and number of calls if you know
            ' roughly how many records to expect back
            qs.Add("limit", "500")
            ' make the call - this one gets the first "limit" results (e.g.
            ' 500 above) updated since the yyyyMMddHHmm timestamp passed to
            ' the service
            Dim someAthletes As ArrayList = parkrunBasicAPIWrapper.APICaller.CallGetService_GetAll( _
                "https://test-api.parkrun.com", "/v1/results/20140408120000", _
                apiToken, userIDForReAuth, userSecretForReAuth, "core", _
                qs, success, tokenWasExpired, callException, newTokenIfReAuthed, expiryIfReAuthed, _
                contentRanges, reAuthorizationException, AddressOf Me.ShowProgressBasicWrapper)

            ' if the token was expired and internally the above call auto-reauthenticated,
            ' store the new token
            If newTokenIfReAuthed.Length > 0 Then
                ' store the new token in the Application object - this is
                ' just one option and is not available across webfarms,
                ' but each node could keep its own token without issue
                ' (could equally use file/db/<etc> storage)
                Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) = newTokenIfReAuthed
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) = DateTime.Now.AddSeconds(expiryIfReAuthed).ToString(_EXPIRYTIME_FORMAT)
            End If

            ' do something with the returned athletes object e.g. ...
            Dim l As List(Of Object) = New List(Of Object)
            Dim id As String
            Dim runid As String
            Dim eventnumber As String
            Dim time As String
            For Each dict As Dictionary(Of String, Object) In someAthletes
                id = dict.Item("AthleteID").ToString()
                runid = dict.Item("RunId").ToString()
                eventnumber = dict.Item("EventNumber").ToString()
                time = dict.Item("RunTime").ToString()
                l.Add(New With {Key .AthleteID = id, .RunId = runid, _
                    .EventNumber = eventnumber, .Time = time})
            Next
            grid1.DataSource = l
            grid1.DataBind()
        Else

        End If
    End Sub

    ''' <summary>
    ''' use this to show progress bar etc if using in evironment where
    ''' its possible to update the display asynchronously (not useful
    ''' in a vanilla ASP.NET environment)
    ''' </summary>
    ''' <param name="contentRangeOfLatestSubCall"></param>
    ''' <param name="latestBatchResponse"></param>
    ''' <remarks></remarks>
    Protected Sub ShowProgressBasicWrapper(contentRangeOfLatestSubCall As ArrayList, latestBatchResponse As WebResponse)

    End Sub

#End Region


#Region "Test the RestSharp Wrapper"

    Protected Sub btn2_Click(sender As Object, e As EventArgs)
        Dim gotAnyToken As Boolean = _
            (Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) IsNot Nothing AndAlso _
            Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY).ToString().Length > 0)
        Dim expiryTime As DateTime
        Dim gotExpiryTime As Boolean = False
        Dim tokenShouldBeValid As Boolean = False
        Dim apiToken As String = String.Empty
        If gotAnyToken Then
            ' if we've got a token currently in our store (the Application object in
            ' this example), check to see if it will be expired or still valid
            Dim expiry As String = _
                (If(Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) IsNot Nothing, _
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY).ToString(), String.Empty))
            gotExpiryTime = DateTime.TryParseExact(expiry, _EXPIRYTIME_FORMAT, Nothing, _
                System.Globalization.DateTimeStyles.None, expiryTime)
            tokenShouldBeValid = (If(gotExpiryTime, expiryTime > _
                (DateTime.Now.Subtract(New TimeSpan(0, 2, 0))), False))
        End If
        If tokenShouldBeValid Then
            ' well before expiry time so attempt to use this token - can call an
            ' overload that attempts an auto-reauthenticate on invalid tokens anyway
            apiToken = Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY).ToString()
        Else
            ' we either didn't have a token or it was dodgy w.r.t. the expiry time, so
            ' attempt to get a new token now
            Dim tokenFetchSucceeded As Boolean
            Dim err As Exception = Nothing
            Dim tokenDuration As Integer
            Dim userID As String = WebConfigurationManager.AppSettings( _
                _PARKRUN_API_OAUTH2_USERID__APPSETTING_KEY)
            Dim userSecret As String = WebConfigurationManager.AppSettings( _
                _PARKRUN_API_OAUTH2_USERSECRET__APPSETTING_KEY)
            Dim fetcher As New parkrunRestSharpAPIWrapper.AuthTokenFetcher(userID, userSecret)
            Dim token As String = fetcher.GetOAuth2Token("https://test-api.parkrun.com", _
                "/token.php", "core", tokenDuration, tokenFetchSucceeded, err)
            If tokenFetchSucceeded Then
                ' store the new token in the Application object - this is
                ' just one option and is not available across webfarms,
                ' but each node could keep its own token without issue
                ' (could equally use file/db/<etc> storage)
                Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) = token
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) = _
                    DateTime.Now.AddSeconds(tokenDuration).ToString(_EXPIRYTIME_FORMAT)
                apiToken = token
            End If
            tokenShouldBeValid = tokenFetchSucceeded
        End If

        If tokenShouldBeValid Then
            Dim userIDForReAuth As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERID__APPSETTING_KEY)
            Dim userSecretForReAuth As String = WebConfigurationManager.AppSettings(_PARKRUN_API_OAUTH2_USERSECRET__APPSETTING_KEY)
            Dim success As Boolean, tokenWasExpired As Boolean
            Dim callException As Exception = Nothing
            Dim reAuthorizationException As Exception = Nothing
            Dim newTokenIfReAuthed As String = String.Empty
            Dim expiryIfReAuthed As Integer
            Dim qs As New Dictionary(Of String, String)()
            qs.Add("limit", "500")
            ' this tests the results - gets all results at bushy (event 1) at
            ' their 500th run
            Dim results As List(Of ResultCollection) = _
                parkrunRestSharpAPIWrapper.APICaller.CallGetService_GetAll(Of ResultCollection)( _
                "https://test-api.parkrun.com", "/v1/results/20140409090000", apiToken, _
                userIDForReAuth, userSecretForReAuth, "core", _
                qs, success, tokenWasExpired, callException, newTokenIfReAuthed, expiryIfReAuthed, _
                reAuthorizationException, AddressOf Me.ShowProgressRestSharpWrapper)
            If newTokenIfReAuthed.Length > 0 Then
                ' store the new token in the Application object - this is
                ' just one option and is not available across webfarms,
                ' but each node could keep its own token without issue
                ' (could equally use file/db/<etc> storage)
                Application(_PARKRUN_API_OAUTH2_TOKEN__APPLICATION_KEY) = newTokenIfReAuthed
                Application(_PARKRUN_API_OAUTH2_TOKENEXPIRY__APPLICATION_KEY) = _
                    DateTime.Now.AddSeconds(expiryIfReAuthed).ToString(_EXPIRYTIME_FORMAT)
            End If
            
            ' do something with the returned result objects e.g. ...
            Dim allResults As List(Of Result) = New List(Of Result)()
            ' CallGetService_GetAll returns batches of objects, so concat them
            ' all together for binding
            For Each batchOfResults As ResultCollection In results
                allResults.AddRange(batchOfResults.List)
            Next
            grid1.DataSource = allResults.OrderBy(Function(result) result.Updated)
            grid1.DataBind()

        Else
            ' we couldn't get a valid token

        End If
    End Sub

    ''' <summary>
    ''' use this to show progress bar etc if using in evironment where
    ''' its possible to update the display asynchronously (not useful
    ''' in a vanilla ASP.NET environment)
    ''' </summary>
    ''' <param name="contentRangeOfLatestSubCall"></param>
    ''' <param name="latestBatchResponse"></param>
    ''' <remarks></remarks>
    Protected Sub ShowProgressRestSharpWrapper(contentRangeOfLatestSubCall As ContentRangeValues, latestBatchResponse As Object)

    End Sub

#End Region

End Class
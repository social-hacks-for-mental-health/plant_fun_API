<?php

	require_once("../lib/parkrunAPI.php");

	$token=new parkrunAPI("/home/richard/.parkrunapi.keys","https://test-api.parkrun.com");
	if (!($token->isValid())) {
		error_log(print_r($token->tokenError()));
	} else {
		error_log(" access_token is: ".$token->access_token() );
		if ($token) {
			if (1==2) {
				$fetch="/v1/events/12/athletes";
				while (isset($fetch)) {
					$result=$token->RequestResource($fetch);

					$data=$result['object']->data;
					$athletes=$data->Athletes;
					foreach ($athletes as $athlete) {
						echo "Firstname : ".$athlete->FirstName."\n";
						echo "Lastname  : ".$athlete->LastName."\n";
					}
					$fetch=$result['next'];
				}
			}

			#$result=$token->RequestResource("/v1/athletes/6703/favourites");
			#print_r($result);
			$target=array('AppId'=>1,'TargetTime'=>"19:59",'AthleteId'=>6703,'EventNumber'=>1);
			$result=$token->CreateResource("/v1/favourites",$target);
			error_log("createresource: ".print_r($result,true));
#j			$target=array('AppId'=>1,'PreferenceId'=>"4",'AthleteId'=>6703,'EventNumber'=>14,'Favourite'=>'false');
#j			$result=$token->ModifyResource("/v1/favourites",$target);
#j			error_log('error is: '.$result['error']);
		
	#		$result=$token->RequestResource("/v1/athletes/6703/targets");
	#		print_r($result);
		}
	}

?>

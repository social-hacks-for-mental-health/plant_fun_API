<?php

	require_once("../lib/parkrunAPI.php");

	$token=new parkrunAPI("/home/richard/.parkrunapi.keys","https://rlrest.parkrun.com");
	if (!($token->isValid())) {
		error_log(print_r($token->tokenError()));
	} else {
		$timestamp='20131106120000';
		$fetch="/v1/results/$timestamp/events";
		while (isset($fetch)) {
			$result=$token->RequestResource($fetch);
			foreach ( $result['object']->data->Events as $event ) {
				echo "Event ".$event->EventLongName." has published results since $timestamp\n";
			}
			$fetch=$result['next'];
		}
	}
?>

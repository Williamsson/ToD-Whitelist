<?php 
function getUUID($username=""){
	$echo = false;
	if($username == ""){
		$echo = true;
		$username = $_GET['username'];
	}
	if(!isset($username)){
		header('HTTP/1.0 400 Bad Request', true, 400);
		return false;
		exit;
	}
	$url = "http://api.fishbans.com/uuid/" . $username;
	if($echo){
		echo curlOut($url);
	}else{
		return curlOut($url);
	}
}

function getUsernameFromUUID($uuid=""){
	if($uuid == ""){	
		$uuid = $_GET['uuid'];	
	}		
	if(!isset($uuid)){		
		header('HTTP/1.0 400 Bad Request', true, 400);		
		exit;	
	}	
	$url = "https://api.mojang.com/user/profiles/" . $uuid . "/names";	
	$data = json_decode(curlOut($url));	
	foreach ($data as $obj){		
		$currentName = $obj->name;	
	}	
	return $currentName;
}

function checkBans(){	
	$uuid = $_GET['uuid'];		
	if(!isset($uuid)){		
		header('HTTP/1.0 400 Bad Request', true, 400);		
		exit;	
	}	
	$username = getUsernameFromUUID($uuid);	
	$url = "http://api.fishbans.com/bans/" . $username;
	echo curlOut($url);
}

function curlOut($url){
	$ch = curl_init();
	// Set URL to download
	curl_setopt($ch, CURLOPT_URL, $url);
	// Should cURL return or print out the data? (true = return, false = print)
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// Timeout in seconds
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	// Download the given URL, and return output
	$output = curl_exec($ch);
	return $output;
}
<?php 
require_once("config.php");
require_once("api.functions.php");
if(isset($_GET['auth']) && $_GET['auth'] == $authKey){
	$action = $_GET['request'];
	if(in_array($action, $allowedFunctions)){
		$action();
	}else{
		header('HTTP/1.0 400 Bad Request', true, 400);
	}
	
}else{
	header('HTTP/1.0 403 Forbidden');
}
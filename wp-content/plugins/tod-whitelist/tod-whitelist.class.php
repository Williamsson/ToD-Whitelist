<?php
/**
 * Plugin Name: Tales of Dertinia Whitelist
 * Description: The whitelisting system for Tales of Dertinia.
 * Version: 1.5
 * Author: Simon Williamsson
 * License: GPL2
 */
 
class todWhitelist {
	
	public function __construct(){
		
		require_once("widget.class.php");
		require_once("display.class.php");
		
		$widget = new todWhitelistWidget();
		$display = new display();
		
	}
	
	static function install(){
		global $wpdb;
		require_once("inc/config.php");
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
		$charset_collate = $wpdb->get_charset_collate();
		$table = $pluginConf['dataTable'];
		
		$query = "CREATE TABLE $table (
		id int(11) NOT NULL AUTO_INCREMENT,
		uuid varchar(40) NOT NULL,
		time timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
		email varchar(40) NOT NULL,
		prevExp varchar(60),
		description text,
		state varchar(20) DEFAULT 'whitelisted',
		availableInvites int(1) DEFAULT '1',
		totalInvites int(1) DEFAULT '1',
		UNIQUE KEY id (id),
		UNIQUE KEY uuid (uuid)
		) $charset_collate;";
	
		dbDelta($query);
	
		$table = $pluginConf['verificationTable'];
		$query = "CREATE TABLE $table (
		id int(11) NOT NULL,
		verification_key varchar(40) NOT NULL,
		username_temp varchar(40) NOT NULL
		UNIQUE KEY id (id)
		) $charset_collate;";
	
		dbDelta($query);
	
		$table = $pluginConf['recruitmentTable'];
		$query = "CREATE TABLE $table (
		id int(11) NOT NULL,
		recruitedUUID varchar(40) NOT NULL,
		recruitedGotReward tinyint(1) DEFAULT '0',
		recruiterGotReward tinyint(1) DEFAULT '0',
		dateRecruited timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
		UNIQUE KEY uuid (uuid)
		) $charset_collate;";
	
		dbDelta($query);
	
		$table = $pluginConf['logTable'];
		$query = "CREATE TABLE $table (
		id int(11) NOT NULL AUTO_INCREMENT,
		content text NOT NULL,
		resolved tinyint(1) NOT NULL DEFAULT '0',
		date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
		UNIQUE KEY id (id)
		) $charset_collate;";
	
		dbDelta($query);
	
	}
	
	protected function getConf($val = ""){
		require_once("inc/config.php");
		
		if(empty($val)){
			return $pluginConf;
		}else{
			return $pluginConf[$val];
		}
	}
	
	protected function getLogs(){
		global $wpdb;
		$table = $this->getConf('logTable');
		$data = $wpdb->get_results(
				"SELECT id,content,resolved,date FROM $table"
		);
		if($data){
			$return = array();
			foreach($data as $log){
				$temp = array();
				$temp['id'] = $log->id;
				$temp['content'] = $log->content;
				$temp['resolved'] = $log->resolved;
				$temp['date'] = $log->date;
	
				$return[] = $temp;
			}
			return $return;
		}
		return false;
	}
	
	protected function getAllUsers($state){
		global $wpdb;
		
		$table = $this->getConf('dataTable');
		
		$data = $wpdb->get_results(
				"SELECT id,uuid,time,email,prevExp,description FROM $table WHERE state = '$state'"
		);
		if($data){
			$return = array();
			foreach($data as $user){
				$temp = array();
				$temp['id'] = $user->id;
				$temp['uuid'] = $user->uuid;
				$temp['time'] = $user->time;
				$temp['email'] = $user->email;
				$temp['prevExp'] = $user->prevExp;
				$temp['description'] = $user->description;
	
				$return[] = $temp;
			}
			return $return;
		}
		return false;
	}
	
	protected function setApiAuthCookie(){
		if (!isset($_COOKIE['apiAuthKey'])) {
			require_once("api/config.php");
			setcookie('apiAuthKey', $authKey, time()+3600);
		}
	}
	
	protected function sendEmail($to, $headers, $message, $title){
		if(wp_mail($to, $title, $message, $headers)){
			return true;
		}
		return false;
	}
	
	protected function addLogEntry($msg){
		global $wpdb;
		
		$wpdb->insert(
				$this->errorLogTable,
				array(
						'content'	=> $msg
				)
		);
	}
}
$todWhitelist = new todWhitelist();
register_activation_hook( __FILE__, array( 'todWhitelist', 'install' ) );
<?php 

class user extends todWhitelist{
	
	public function __construct(){
		
	}
	
	public function userRegistration(){
		$email = sanitize_text_field($_POST['email']);
		$username = sanitize_text_field($_POST['username']);
		$completeRegistration = true;
		$doAuthentication = true;
		$errors = array();
		
		if($this->checkEmailExists($email)){
			$completeRegistration = false;
			$errors[] = "We already have this email registered to a player.";
		}
		$uuid = $this->getUserUUID($username);
		
		$uuid = json_decode($uuid);
		$uuid = $uuid->uuid;
		
		$userState = $this->getUuidState($uuid);
		
		if($userState){
			$completeRegistration = false;
			$errors[] = "This username is already registered. Current state: $userState";
		}
		
		if($completeRegistration){
			$doAuthitentication = true;
			global $wpdb;
			
			$prevExp = "";
			if(!empty($_POST['prevExp'])){
				foreach($_POST['prevExp'] as $exp){
					$prevExp = $prevExp . $exp . ", ";
				}
			}
			$prevExp = sanitize_text_field($prevExp);
			$desc = sanitize_text_field($_POST['description']);
			
			$res = $wpdb->insert(
				$this->getConf('dataTable'),
				array(
					'uuid'	=> $uuid,
					'email'	=> $email,
					'prevExp'	=> $prevExp,
					'description'	=> $desc,
					'state'			=> 'pending'
				)
			);
			if(!$res){
				$doAuthentication = false;
				$errors[] = "Critical error: Failed to whitelist. Try again later. Staff has been notified";
				//@TODO: Add log entry, send email to staff
			}
			
			if($doAuthentication){
				$lastid = $wpdb->insert_id;
				$authKey = substr(md5(rand()), 0, 15);
				
				$res = $wpdb->insert(
						$this->getConf('verificationTable'),
						array(
								'id'				=> $lastid,
								'verification_key'	=> $authKey,
								'username_temp'		=> $username
						)
				);
				if($res){
					$mailContent = "<h1>Welcome!</h1>";
					$mailContent .= "<p>In order to become whitelisted you need to activate your account with the link below:</p>";
					$mailContent .= "<a href='http://talesofdertinia.com/activation?id=$authKey'>Activate account with this link</a>";
			
					$mailContent .= "<p>Does the link not work? Copy and paste this: http://talesofdertinia.com/activation?id=$authKey</p>";
			
					$mailContent .= "<p>Best regards,<br/>
									the Tales of Dertinia staff</p>";
					$mailSent = $this->sendEmail($email, "", $mailContent, "Tales of Dertinia Whitelist");
					if($mailContent){
						unset($_POST['username']);
						unset($_POST['email']);
						unset($_POST['description']);
						$this->addLogEntry("Sent auth email to $email");
						return true;
					}
					$this->addLogEntry("Failed to send auth email to $email");
					//This part is completed and works fine.
					// @TODO: URL shouldn't be hardcoded, it should find out what domain it is on
					return false;
				}else{
					$errors[] = "Critical error: Failed to generate authentication. A staff member will send a email to you within 1-5 days.";
					// @TODO: Add log entry, automated email to staff
				}
			}
			
		}	
		
		return $errors;
	}
	
	private function setUserState($id, $state){
		global $wpdb;
		$wpdb->update($this->getConf('dataTable'), array( 'state' => $state),array('id'=>$id));
	}
	
	private function getUserUUID($username){
		require_once("/api/api.functions.php");
		return getUUID($username);
	}
	
	private function forumAccountHandler($action, $username, $pwd, $email){
		global $db, $wpdb, $config;
		
		define('FORUM_ADD',TRUE);
		define('IN_PHPBB',TRUE);
		define('IN_PORTAL',TRUE);
		
		require_once(plugin_dir_path(__FILE__) . '/phpbb/common.php');
		require_once(plugin_dir_path(__FILE__) . '/phpbb/includes/functions.php');
		require_once(plugin_dir_path(__FILE__) . '/phpbb/includes/functions_user.php');
		
		switch($action){
			case 'add':
				if(!validate_phpbb_username($username)){
					
					$group_id = 2;
					$language = 'en';
					$user_type = USER_NORMAL;
					$is_dst = date('I');
					$timezone = '+1';
					
					$user_row = array(
							'username'              => $username,
							'user_password'         => $pwd,
							'user_email'            => $email,
							'group_id'              => (int) $group_id,
							'user_timezone'         => (float) $timezone,
							'user_dst'              => $is_dst,
							'user_lang'             => $language,
							'user_type'             => $user_type,
							'user_regdate'          => time()
					);
	
					// all the information has been compiled, add the user
					// tables affected: users table, profile_fields_data table, groups table, and config table.
					$createdUser = user_add($user_row);
					return $createdUser;
				}else{
					return false;
				}
				break;
					
			case 'delete':
				return false;
				break;
		}
	
	}
	
	private function createAccounts($id, $username){
		global $wpdb;
		$res = $wpdb->get_results("SELECT email FROM " . $this->getConf('dataTable') . " WHERE id = $id", OBJECT);
	
		$email = $res[0]->email;
	
		$pwd = wp_generate_password(9, true);
		if (!username_exists( $username ) && email_exists($email) == false ) {
			if(wp_create_user( $username, $pwd, $email )){
				$this->addLogEntry("Created WP account for $username");
			}else{
				$this->addLogEntry("WP account already exists for $username");
			}
		}
	
		//Start making a welcome mail
		
		$headers[] = 'From: Tales of Dertinia Staff <info@talesofdertinia.com>';
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
	
		$message = "<h1>All done!</h1>";
		$message .= "<p>Now that you're whitelisted we've made you a member of our community!<br/>";
		$message .= "To fully enjoy this, we advice you to regularly visit our website and our forums.<br/>";
		$message .= "To make things easier for you we've automagically registered these for you!</p>";
		$message .= "<br/><h2>Tales of Dertinia Website:</h2>";
	
		if(!$userExists){
			$this->addLogEntry("Creating website user for $username");
			$message .="<p>Username: $username</p>";
			$message .="<p>Password: $pwd (we strongly advice you to change this).</p>";
		}else{
			$this->addLogEntry("Website account already exists for $username");
			$message .="<p>Oh! You already had an account here. In that case you know your credentials better than we do.<br/>
							Should there be any problems just email us and we'll take a look at it.</p>";
		}
	
		$forumAcc = $this->forumAccountHandler("add", $username,md5($pwd),$email);
		$message .="<br/><h2>Tales of Dertinia Forum:</h2>";
	
		if($forumAcc){
			$this->addLogEntry("Created forum acc for $username");
			$message .="<p>Username: $username</p>";
			$message .="<p>Password: $pwd (we strongly advice you to change this).</p>";
		}else{
			$this->addLogEntry("Forum acc already existed for $username");
			$message .="<p>Oh! You already had an account here. In that case you know your credentials better than we do.<br/>
							Should there be any problems just email us and we'll take a look at it.</p>";
		}
	
		$message .="<br/><p>The application process is now completed! We thank you for your interest and hope you'll have a lot of fun on the server.</p>";
		$message .= "<h2>Teamspeak</h2>";
		$message .= "<p>If you'd ever want to talk with the community instead of just chatting, we have a Teamspeak 3 server hosted as well!</p>";
		$message .= "<p>To join you need to download Teamspeak from their official website and join using the same address as when you join the minecraft server.</p>";
	
		$message .= "<p>Best regards,<br/>
						the Tales of Dertinia staff.</p>";
	
		if($this->sendEmail($email, $headers, $message, "Welcome to the Tales of Dertinia community!")){
			$this->addLogEntry("Sent welcome email to $username");
		}else{
			$this->addLogEntry("Failed to send welcome mail to $username");
		}
	}
	
	private function checkEmailExists($email){
		global $wpdb;
		if($wpdb->get_results("SELECT id FROM " . $this->getConf('dataTable') ." WHERE email = '$email'")){
			return true;
		}
		return false;
	}
	
	//Returns false if uuid dosen't exist, returns the state if the uuid exists
	private function getUuidState($uuid){
		global $wpdb;
		$state = $wpdb->get_results("SELECT state FROM " . $this->getConf('dataTable') . " WHERE uuid = '$uuid'");
		if($state){
			return $state;
		}
		return false;
	}
} //End of class
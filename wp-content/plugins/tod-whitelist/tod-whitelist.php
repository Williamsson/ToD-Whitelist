<?php
/**
 * Plugin Name: Tales of Dertinia Whitelist
 * Description: The whitelisting system for Tales of Dertinia.
 * Version: 1.0
 * Author: Simon Williamsson
 * License: GPL2
 
 
 */
 
 // Creating the widget 
class todWhitelist extends WP_Widget {
	
	private $userDataTable, $userVerificationsTable, $userRecruitmentTable, $errorLogTable;
	
	function __construct() {
		global $wpdb;
		$this->userDataTable = $wpdb->prefix . "tod_user_data";
		$this->userVerificationsTable = $wpdb->prefix . "tod_user_verifications";
		$this->userRecruitmentTable = $wpdb->prefix . "tod_user_recruitment";
		$this->errorLogTable = $wpdb->prefix . "tod_error_log";
	
		parent::__construct(
				// Base ID of your widget
				'todWhitelist',
	
				// Widget name will appear in UI
				__('ToD Whitelist', 'todWhitelist_domain'),
	
				// Widget description
				array( 'description' => __( 'Widget that shows the whitelisting-form.', 'todWhitelist_domain' ), )
		);
	}
	
	static function install(){
		global $wpdb;
	
		$table = $wpdb->prefix . "tod_user_data";
		$charset_collate = $wpdb->get_charset_collate();
	
		$query = "CREATE TABLE $table (
			id int(11) NOT NULL AUTO_INCREMENT,
			uuid varchar(40) NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			email varchar(40) NOT NULL,
			prevExp varchar(60),
			description text,
			state varchar(20) DEFAULT 'whitelisted',
			avalibleInvites int(1) DEFAULT '5',
			totalInvitesSent int(1) DEFAULT NULL,
			UNIQUE KEY id (id),
			UNIQUE KEY uuid (uuid)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($query);
		
		$table = $wpdb->prefix . "tod_user_verifications";
		$query = "CREATE TABLE $table (
			id int(11) NOT NULL,
			verification_key varchar(40) NOT NULL,
			username_temp varchar(40) NOT NULL
			UNIQUE KEY id (id)
		) $charset_collate;";
		
		dbDelta($query);
		
		$table = $wpdb->prefix . "tod_user_recruitment";
		$query = "CREATE TABLE $table (
			id int(11) NOT NULL,
			recruitedUUID varchar(40) NOT NULL,
			recruitedGotReward tinyint(1) DEFAULT '0',
			recruiterGotReward tinyint(1) DEFAULT '0',
			dateRecruited datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		) $charset_collate;";
		
		dbDelta($query);
		
		$table = $wpdb->prefix . "tod_error_log";
		$query = "CREATE TABLE $table (
			id int(11) NOT NULL AUTO_INCREMENT,
			content text NOT NULL,
			resolved tinyint(1) NOT NULL DEFAULT '0',
			date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
		
		dbDelta($query);
		
	}
	
	// Creating widget front-end
	// This is where the action happens
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		// before and after widget arguments are defined by themes
		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
	
		if(isset($_POST['whitelistSubmit']) && $_POST['whitelistSubmit'] == "Apply"){
			$errors = array();
			if (!function_exists('curl_init')){
				$errors[] = "ERROR: Due to connection problems we couldn't verify settings. Please notify the site admin.";
			}else{
				$email = strtolower($_POST['email']);
				if($this->checkEmailExists($email)){
					$errors[] = "That email is already registered!";
				}else{
					if(!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)){
						$username = sanitize_text_field($_POST['username']);
						$uuid = $this->getUserUUID($username);
	
						if(!empty($uuid)){
							$state = $this->checkUuidState($uuid);
							$state = $state[0]->state;
							if(!$state){
								$prevExp = "";
								foreach($_POST['prevExp'] as $exp){
									$prevExp = $prevExp . $exp . ", ";
								}
								$prevExp = sanitize_text_field($prevExp);
								$desc = sanitize_text_field($_POST['description']);
									
								global $wpdb;
								$res = $wpdb->insert(
										$this->userDataTable,
										array(
												'uuid'			=> $uuid,
												'time'			=> current_time('mysql'),
												'email'			=> $email,
												'prevExp'		=> $prevExp,
												'description'	=> $desc,
												'state'			=> 'pending'
										)
								);
								if($res){
									$lastid = $wpdb->insert_id;
									$authKey = substr(md5(rand()), 0, 15);
										
									$res = $wpdb->insert(
											$this->userVerificationsTable,
											array(
													'id'				=> $lastid,
													'verification_key'	=> $authKey,
													'username_temp'		=> $username
											)
									);
									if($res){
										$headers[] = 'From: Tales of Dertinia Staff <info@talesofdertinia.com>';
										$headers[] = 'Content-Type: text/html; charset=UTF-8';
										
										$mailContent = "<h1>Welcome!</h1>";
										$mailContent .= "<p>In order to become whitelisted you need to activate your account with the link below:</p>";
										$mailContent .= "<a href='http://dev.talesofdertinia.com/activation?id=$authKey'>Activate account with this link</a>";
										
										$mailContent .= "<p>Does the link not work? Copy and paste this: http://dev.talesofdertinia.com/activation?id=$authKey</p>";
										
										$mailContent .= "<p>Best regards,<br/>
														the Tales of Dertinia staff</p>";
										
										$res = $this->sendEmail($email, $headers, $mailContent, "Tales of Dertinia Whitelist Application");
										
										if($res){
											$message = "Application successful! Check your email and click the activation link to start playing!";
											unset($_POST['username']);
											unset($_POST['email']);
											unset($_POST['description']);
										}else{
											//Everything except sending a email authentication worked..
											$message = "Something went wrong. Your application has been processed, however you haven't gotten the authentication email properly. A notification has been sent to the staff with all information and they will get back to you.";
											$headers[] = 'From: ToD Error Handler <info@talesofdertinia.com>';
											$headers[] = 'Content-Type: text/html; charset=UTF-8';
											$mailMessage = "<h1>Error while sending activation email</h1>";
											$mailMessage .= "User with ID $lastid is fully inserted in database, but hasn't recieved authentication email. Email to user is $email";
											$this->sendEmail("info@talesofdertinia.com", $headers, $mailMessage, "Error in registration");
											$this->addErrorEntry("User with ID $lastid hasn't recieved an authentication email.");
										}
									}else{
										//We've inserted the user as pending in the tod_user_data table - however there's no data in tod_user_verifications
										$message = "Something went wrong. A team of highly trained creepers has been dispatched to fix the errorsss..";
										$headers[] = 'From: ToD Error Handler <info@talesofdertinia.com>';
										$headers[] = 'Content-Type: text/html; charset=UTF-8';
										$mailMessage = "<h1>Error while sending activation email</h1>";
										$mailMessage .= "The first part of the registration failed. I don't know why.";
										$this->sendEmail("info@talesofdertinia.com", $headers, $mailMessage, "Error in registration");
									}
								}else{
									//Couldn't insert data to tod_user_data table. Let's not alarm the user.
									$message = "I'm terribly sorry, but something seems to have gone awfully wrong. Our team, travelling on a Techy-Trolly(TM), has been dispatched and is enroute! Try again at a later time.";
									$headers[] = 'From: ToD Error Handler <info@talesofdertinia.com>';
									$headers[] = 'Content-Type: text/html; charset=UTF-8';
									$mailMessage = "<h1>Error while sending activation email</h1>";
									$mailMessage .= "The first part of the registration failed. I don't know why.";
									$this->sendEmail("info@talesofdertinia.com", $headers, $mailMessage, "Error in registration");
								}
							}else{
								$errors[] = "You're already in our registers! State: $state";
							}
								
						}else{
							$errors[] = "No username with that name could be found.";
						}
					}else{
						$errors[] = "Email entered is not a valid email format!";
					}
						
				}
			}
		}
	
		?>
		<form method="POST" action="">
			<label for="username">Minecraft username*:</label><br/>
				<input type="text" name="username" value="<?= (isset($_POST['username']) ? $_POST['username'] : ''); ?>"><br/>
			<label for="email">Email*:</label><br/>
				<input type="text" name="email" value="<?= (isset($_POST['email']) ? $_POST['email'] : ''); ?>"><br/>
				<a id="whitelist_email_zebra_link" href="#">Why do you want my email?</a><br/>
			<label for="prevExp">Have you ever been a(optional):</label><br/>
				<input type="checkbox" name="prevExp[]" value="Builder"> Builder<br/>
				<input type="checkbox" name="prevExp[]" value="Staff"> Staff member<br/>
				<input type="checkbox" name="prevExp[]" value="Admin"> Admin<br/>
				<a id="whitelist_why_zebra_link" href="#">Why is this important?</a><br/>
			<label for="description">Other information(optional):</label><br/>
				<textarea name="description" rows="6" cols="30"><?= (isset($_POST['description']) ? $_POST['description'] : ''); ?></textarea>
			<input type="submit" name="whitelistSubmit" value="Apply">
		</form>
		<?php 
		if(isset($message)){
			echo "<h2>$message</h2>";
		}
		if(!empty($errors)){
			echo "<h2>Form errors:</h2>
 				<ul>";
			foreach($errors as $err){
				echo "<li>" . $err . "</li>";
			}
			echo "</ul>";
		}
		
		echo $args['after_widget'];
	}
				
	// Widget Backend 
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'todWhitelist_domain' );
		}
		// Widget admin form
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php 
	}
			
	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
	
	private function sendEmail($to, $headers, $message, $title){
		if(wp_mail($to, $title, $message, $headers)){
			return true;
		}
		return false;
	}
	
	private function addErrorEntry($msg){
		global $wpdb;
		
		$wpdb->insert(
			$this->errorLogTable,
			array(
				'content'	=> $msg,
				'time'		=> current_time('mysql')
			)
		);
	}
	
	protected function getUserUUID($username){
		
		$username = sanitize_text_field($username);
		
		$ch = curl_init();
		// Set URL to download
		curl_setopt($ch, CURLOPT_URL, "https://api.mojang.com/users/profiles/minecraft/" . $username);
		// Should cURL return or print out the data? (true = return, false = print)
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Timeout in seconds
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		// Download the given URL, and return output
		$output = json_decode(curl_exec($ch));
		
		return $output->id;
	}
	
	public function activationPage(){
		
		$authKey = sanitize_text_field($_GET['id']);
		if(!empty($authKey)){
			global $wpdb;

			$data = $wpdb->get_results("SELECT id, username_temp FROM " . $this->userVerificationsTable ." WHERE verification_key = '$authKey'");
			if($data){
				$id = $data[0]->id;
				$wpdb->update($this->userDataTable, array( 'state' => 'whitelisted'),array('id'=>$id));
				$wpdb->delete($this->userVerificationsTable,array('id'=>$id));
				
				echo "<h1>Account confirmation completed! <br/>You can now start playing.</h1>";
				echo "<p>We've also sent another email to you with account credentials for this website and the forums.</p>";
				
				$this->createAccounts($id, $data[0]->username_temp);
				
			}else{
				echo "<h1>We have no records of this key existing.</h1><br/>";
				echo "<p>Having problems activating your account?<br/> Use the contact form on the website describing your problem!<br/>
						Please remember to tell us what your minecraft username is so that we can troubleshoot the issue.</p>";
			}
		}else{
			the_content();
		}
	}
	
	private function forumAccountHandler($action="add", $username, $pwd, $email){
		global $db, $wpdb, $phpEx;
		$phpEx = "php";
		
		define('FORUM_ADD',TRUE);
		define('IN_PHPBB',TRUE);
		define('IN_PORTAL',TRUE);
		
		include_once(plugin_dir_path(__FILE__) . '/phpbb/common.php');
		include_once(plugin_dir_path(__FILE__) . '/phpbb/includes/functions.php');
		include_once(plugin_dir_path(__FILE__) . '/phpbb/includes/functions_user.php');
		global $config;
		switch($action){
			case 'add':
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
				break;
			
			case 'delete':
				return false;
				break;
		}
		
	}
	
	private function createAccounts($id, $username){
		global $wpdb;
		$res = $wpdb->get_results("SELECT email FROM " . $this->userDataTable . " WHERE id = $id", OBJECT);
		
		$email = $res[0]->email;
		
		$pwd = wp_generate_password(9, true);
		$userExists = username_exists( $username );
		if (!$userExists and email_exists($email) == false ) {
			wp_create_user( $username, $pwd, $email );
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
			$message .="<p>Username: $username</p>";
			$message .="<p>Password: $pwd (we strongly advice you to change this).</p>";
		}else{
			$message .="<p>Oh! You already had an account here. In that case you know your credentials better than we do.<br/>
							Should there be any problems just email us and we'll take a look at it.</p>";
		}
		
		$forumAcc = $this->forumAccountHandler("add", $username,md5($pwd),$email);
		
		$message .="<br/><h2>Tales of Dertinia Forum:</h2>";
		if($forumAcc){
			$message .="<p>Username: $username</p>";
			$message .="<p>Password: $pwd (we strongly advice you to change this).</p>";
		}else{
			$message .="<p>Oh! You already had an account here. In that case you know your credentials better than we do.<br/>
							Should there be any problems just email us and we'll take a look at it.</p>";
		}
		
		$message .="<br/><p>The application process is now completed! We thank you for your interest and hope you'll have a lot of fun on the server.</p>";
		$message .= "<h2>Teamspeak</h2>";
		$message .= "<p>If you'd ever want to talk with the community instead of just chatting, we have a Teamspeak 3 server hosted as well!</p>";
		$message .= "<p>To join you need to download Teamspeak from their official website and join using the same address as when you join the minecraft server.</p>";
		
		$message .= "<p>Best regards,<br/>
						the Tales of Dertinia staff.</p>";
		
		$this->sendEmail($email, $headers, $message, "Welcome to the Tales of Dertinia community!");
		
		
	}
	
	protected function checkEmailExists($email){
		global $wpdb;
		if($wpdb->get_results("SELECT id FROM " . $this->userDataTable ." WHERE email = '$email'")){
			return true;
		}
		return false;
	}
	
	//Returns false if uuid dosen't exist, returns the state if the uuid exists
	protected function checkUuidState($uuid){
		global $wpdb;
		$state = $wpdb->get_results("SELECT state FROM " . $this->userDataTable." WHERE uuid = '$uuid'");
		if($state){
			return $state;
		}
		return false;
	}
	
} // Class todWhitelist ends here


register_activation_hook( __FILE__, array( 'todWhitelist', 'install' ) );

// Register and load the widget
function wpb_load_widget() {
	register_widget( 'todWhitelist' );
}
add_action( 'widgets_init', 'wpb_load_widget' );
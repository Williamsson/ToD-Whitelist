<?php
/**
 * Plugin Name: Tales of Dertinia Whitelist
 * Description: The whitelisting system for Tales of Dertinia.
 * Version: 1.1
 * Author: Simon Williamsson
 * License: GPL2
 */
 
 // Creating the widget 
class todWhitelist extends WP_Widget {
	
	protected $userDataTable, $userVerificationsTable, $userRecruitmentTable, $errorLogTable;
	
	function __construct() {
		global $wpdb;
		$this->userDataTable = $wpdb->prefix . "tod_user_data";
		$this->userVerificationsTable = $wpdb->prefix . "tod_user_verifications";
		$this->userRecruitmentTable = $wpdb->prefix . "tod_user_recruitment";
		$this->errorLogTable = $wpdb->prefix . "tod_error_log";
		
		add_action( 'admin_enqueue_scripts', array(&$this,'loadScripts'));
		add_action('admin_menu', array(&$this, 'addAdminMenuItem'));
		add_action( 'init', array(&$this,'setApiAuthCookie'));
		
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
			dateRecruited timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
			UNIQUE KEY uuid (uuid)
		) $charset_collate;";
		
		dbDelta($query);
		
		$table = $wpdb->prefix . "tod_error_log";
		$query = "CREATE TABLE $table (
			id int(11) NOT NULL AUTO_INCREMENT,
			content text NOT NULL,
			resolved tinyint(1) NOT NULL DEFAULT '0',
			date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
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
	
		?>
		<form method="POST" action="/activation?activation=1">
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
	
	public function addAdminMenuItem(){
	        add_menu_page('ToD-Whitelist Settings', 'ToD-Whitelist', 'manage_options', 'tod-whitelist', array(&$this, 'adminPageContent'), "dashicons-shield", 25);
	}
	
	public function setApiAuthCookie(){
		if (!isset($_COOKIE['apiAuthKey'])) {
			require_once("api/config.php");
			setcookie('apiAuthKey', $authKey, time()+3600);
		}
	}
	
	public function adminPageContent(){
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		if(isset($_POST['changeUserState'])){
			$this->setUserState($_POST['id'], $_POST['state']);
		}
		
		if(isset($_GET['tab'])){
			$current = $_GET['tab'];
		}else{
			$current = 'whitelist';
		}
		$tabs = array( 'whitelist' => 'Whitelisted folks', 'blacklist' => 'Blacklisted folks', 'pending' => 'Pending folks', 'logs' => 'Logs');
		$page = '<div id="icon-themes" class="icon32"><br></div>';
		$page .= '<h2 class="nav-tab-wrapper">';
		switch ($current){
			case 'whitelist':
				$data = $this->getAllUsers('whitelisted');
				break;
			case 'blacklist':
				$data = $this->getAllUsers('blacklisted');
				break;
			case 'logs':
				$data = $this->getLogs();
				break;
			case 'pending':
				$data = $this->getAllUsers('pending');
				break;
			default:
				$data = $this->getAllUsers('whitelisted');
				break;
		}
		foreach( $tabs as $tab => $name ){
			$class = ( $tab == $current ) ? ' nav-tab-active' : '';
			$page .= "<a class='nav-tab$class' href='?page=tod-whitelist&tab=$tab'>$name</a>";
		
		}
		$page .= '</h2>';
		
		
		if($current != 'logs'){
			$page .= '<h2>Instructions</h2>';
			$page .= '<p>The top left search field is used to get the UUID of a minecraft username,<br/> when you press "Search" the UUID will be inserted in the bottom right search field (provided that the username you enter exists).</p>';
			$page .= '<p>The bottom right search field can be used as well, but you will not find usernames via it if you enter them there.<br/>
						This field searches everything in the table - But Not <u>Usernames</u>.</p>';
			
			$page .= '<p><b>IN SHORT: ENTER USERNAME IN TOP LEFT, ANYTHING ELSE BOTTOM RIGHT!</b></p>';
			
			$page .= '<p><u>Note:</u>The action dropdown and "Go" button ONLY affects the row you\'re clicking the button on!</p>';
			
			$page .= '<label for="tod-whitelist-search">Enter username:</label>';
			$page .= "<input type='search' id='tod-whitelist-search' placeholder>";
			$page .= '<input type="submit" id="tod-whitelist-search-submit" value="Search"> <input type="submit" id="tod-whitelist-clear" value="Clear"><br/><br/>';
			$page .= '<div id="tod-whitelist-content-wrapper">';
				$page .= '<table id="usersTable" class="display nowrap dataTable dtr-inline">';
					$page .= '<thead>';
					$page .= '<tr role="row">';
					$page .= '<th>Whitelisted at</th>';
					$page .= '<th>UUID</th>';
					$page .= '<th>Email</th>';
					$page .= '<th>Staff Experience</th>';
					$page .= '<th>Description</th>';
					$page .= '<th>Action</th>';
					$page .= '</tr>';
					$page .= '</thead>';
					$page .= '<tbody>';
					if($data){
						foreach($data as $row){
							$page .= '<tr>';
								$page .= "<td>" . $row['time'] . "</td>";
								$page .= "<td><a class='uuidProfileLink' href='#". $row['id'] ."'>" . $row['uuid'] . "</a></td>";
								$page .= "<td>" . $row['email'] . "</td>";
								$page .= "<td>" . $row['prevExp'] . "</td>";
								$page .= "<td>" . $row['description'] . "</td>";
								
								$page .= "<td><form action='' method='POST'>
													<input name='id' type='hidden' value='" . $row['id'] . "'>
													<select name='state'>
														<option value='blacklisted'>Blacklist</option>
														<option value='whitelisted'>Whitelist</option>
														<option value='pending'>Add to pending</option>
													</select>
													<input type='submit' name='changeUserState' value='Go'>
												</form></td>";
							$page .= '</tr>';
						}
					}
					$page .= '</tbody>';
					
				$page .= '</table>';
			$page .= '</div>';
		}else{
			$page .= '<div id="tod-whitelist-content-wrapper">';
				$page .= '<table id="logsTable" class="display nowrap dataTable dtr-inline">';
					$page .= '<thead>';
						$page .= '<tr role="row">';
							$page .= '<th>Error ID</th>';
							$page .= '<th>content</th>';
							$page .= '<th>Resolved</th>';
							$page .= '<th>Date of event</th>';
						$page .= '</tr>';
					$page .= '</thead>';
					$page .= '<tbody>';
					if($data){
						foreach($data as $row){
							$page .= '<tr>';
								$page .= "<td>" . $row['id'] . "</td>";
								$page .= "<td>" . $row['content'] . "</td>";
								$page .= "<td>" . $row['resolved'] . "</td>";
								$page .= "<td>" . $row['date'] . "</td>";
							$page .= '</tr>';
						}
					}
					$page .= '</tbody>';
				$page .= '</table>';
			$page .= '</div>';
		}
		
		$page .= "<div class='modal'></div>";
		echo $page;
	}
	
	private function getLogs(){
		global $wpdb;
		$data = $wpdb->get_results(
				"SELECT id,content,resolved,date FROM " . $this->errorLogTable . ""
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
	
	private function setUserState($id, $state){
		global $wpdb;
		$wpdb->update($this->userDataTable, array( 'state' => $state),array('id'=>$id));
	}
	
	private function getAllUsers($state){
		global $wpdb;
		
		$data = $wpdb->get_results(
			"SELECT id,uuid,time,email,prevExp,description FROM " . $this->userDataTable . " WHERE state = '$state'"
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
	
	public function loadScripts($hook_suffix){
		wp_enqueue_script( "dataTables", "//cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js" );
		wp_enqueue_style('datatablesCSS', "//cdn.datatables.net/1.10.5/css/jquery.dataTables.min.css");
		wp_enqueue_style('tod-whitelist-css', "/wp-content/plugins/tod-whitelist/css/style.css");
		wp_enqueue_script( "todScript", "/wp-content/plugins/tod-whitelist/js/todScript.js" );
		wp_enqueue_style( 'custom', get_stylesheet_directory_uri() . '/zebra-dialog/default/zebra_dialog.css' );
		wp_enqueue_script( 'zebra_dialog', get_stylesheet_directory_uri() . '/zebra-dialog/zebra_dialog.js', array(), '1.0.0', true );
	}
	
	private function sendEmail($to, $headers, $message, $title){
		if(wp_mail($to, $title, $message, $headers)){
			return true;
		}
		return false;
	}
	
	private function addLogEntry($msg){
		global $wpdb;
		
		$wpdb->insert(
			$this->errorLogTable,
			array(
				'content'	=> $msg
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
		if(!empty($_GET['id'])){
			global $wpdb;
			$authKey = sanitize_text_field($_GET['id']);
			
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
		}elseif($_GET['activation']){
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
								if(!$state){
									$prevExp = "";
									if(!empty($_POST['prevExp'])){
										foreach($_POST['prevExp'] as $exp){
											$prevExp = $prevExp . $exp . ", ";
										}
									}
									$prevExp = sanitize_text_field($prevExp);
									$desc = sanitize_text_field($_POST['description']);
										
									global $wpdb;
									$res = $wpdb->insert(
											$this->userDataTable,
											array(
													'uuid'			=> $uuid,
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
											$mailContent .= "<a href='http://talesofdertinia.com/activation?id=$authKey'>Activate account with this link</a>";
			
											$mailContent .= "<p>Does the link not work? Copy and paste this: http://talesofdertinia.com/activation?id=$authKey</p>";
			
											$mailContent .= "<p>Best regards,<br/>
																	the Tales of Dertinia staff</p>";
											
											$res = $this->sendEmail($email, $headers, $mailContent, "Tales of Dertinia Whitelist Application");
			
											if($res){
												$message = "Application successful! Check your email and click the activation link to start playing!";
												$this->addLogEntry("Verification email was sent to $email");
												unset($_POST['username']);
												unset($_POST['email']);
												unset($_POST['description']);
											}else{
												//Everything except sending a email authentication worked..
												$message = "Something went wrong. Your application has been processed, however you haven't gotten the authentication email properly. A notification has been sent to the staff with all information and they will get back to you.";
												$this->addLogEntry("User with ID $lastid hasn't recieved an authentication email. Email is: $email");
											}
										}else{
											//We've inserted the user as pending in the tod_user_data table - however there's no data in tod_user_verifications
											$this->addLogEntry("First part of whitelist failed for some unknown reason");
											$message = "Something went wrong. A team of highly trained creepers has been dispatched to fix the errorsss..";
											$headers[] = 'From: ToD Error Handler <info@talesofdertinia.com>';
											$headers[] = 'Content-Type: text/html; charset=UTF-8';
											$mailMessage = "<h1>Error while sending activation email</h1>";
											$mailMessage .= "The first part of the registration failed. I don't know why.";
											$this->sendEmail("info@talesofdertinia.com", $headers, $mailMessage, "Error in registration");
										}
									}else{
										//Couldn't insert data to tod_user_data table. Let's not alarm the user.
										$this->addLogEntry("Second part of whitelist failed for some unknown reason. User ID: $lastid");
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
		}else{
			the_content();
		}
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
		$res = $wpdb->get_results("SELECT email FROM " . $this->userDataTable . " WHERE id = $id", OBJECT);
		
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
		$state = $wpdb->get_results("SELECT state FROM " . $this->userDataTable. " WHERE uuid = '$uuid'");
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
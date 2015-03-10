<?php 
class display extends todWhitelist{
	
	public function __construct(){
		add_action( 'admin_enqueue_scripts', array(&$this,'loadAdminScripts'));
		add_action('admin_menu', array(&$this, 'addAdminMenuItem'));
		add_shortcode( 'todwhitelistregister', array(&$this, 'registerPageShortcode' ) );
		add_shortcode( 'todwhitelistactivate', array(&$this, 'activationPageShortcode' ) );
	}
	
	public function loadAdminScripts($hook_suffix){
		$this->setApiAuthCookie();
		wp_enqueue_script( "dataTables", "//cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js" );
		wp_enqueue_style('datatablesCSS', "//cdn.datatables.net/1.10.5/css/jquery.dataTables.min.css");
		wp_enqueue_style('tod-whitelist-css', "/wp-content/plugins/tod-whitelist/css/style.css");
		wp_enqueue_script( "todScript", "/wp-content/plugins/tod-whitelist/js/todScript.js" );
		wp_enqueue_style( 'custom', get_stylesheet_directory_uri() . '/zebra-dialog/default/zebra_dialog.css' );
		wp_enqueue_script( 'zebra_dialog', get_stylesheet_directory_uri() . '/zebra-dialog/zebra_dialog.js', array(), '1.0.0', true );
	}
	
	public function activationPageShortcode($atts, $content = ""){
		return $this->getActivationPageContent();
	}
	
	public function getActivationPageContent(){
		if(isset($_GET['id'])){
			require_once("user.class.php");
			$user = new user();
			$authId = sanitize_text_field($_GET['id']);
			$auth = $user->authenticateUser($authId);
			
			if($auth){
				return '<h1>All done!</h1>
						<p>Everything is complete and you have receieved a email with credentials for our websites. Welcome to the Tales of Dertinia community!</p>';
			}
			return '<h1>Something went wrong</h1>
						<p>Try again later or try contacting us on the "Contact" page.</p>';
			
		}else{
			return "<h1>No authentication key provided</h1>
					<p>This page is worthless to you. No need to be here. At all. Like, really. It's not as if I'm dropping knowledge on you or anything. This page only exists for those with a proper authentication key. And you don't have one. At least you haven't brought it. I know these things. For I am the almighty.</p>
					<p>In other news, did you know that simplistic passwords contribute to over 80% of all computer password break-ins? Or that peanuts are one of the ingredients of dynamite? The world is filled with curious little facts, if you only broaden your views.</p>";
		}
	}
	
	public function registerPageShortcode( $atts, $content = "" ) {
		return $this->getRegistrationPageContent();
	}
	
	public function getRegistrationPageContent(){
		if(isset($_POST['whitelistSubmit'])){
			require_once("user.class.php");
			$user = new user();
			$registration = $user->userRegistration();
			if(!is_array($registration)){
				return '<h1>First part completed!</h1>
						<p>Check your email to verify and complete the registration.</p>';
			}
			$return = "<ul>";
			foreach($registration as $error){
				$return .= "<li>$error</li>";
			}
			$return .= "</ul>";
			return $return;
		}else{
			return '<p>No data recieved, you need to fill out the form in the right panel.</p>';
		}
	}
	
	public function getLogs(){
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
	
	public function getAllUsers($state){
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
	
	public function addAdminMenuItem(){
		add_menu_page('ToD-Whitelist Settings', 'ToD-Whitelist', 'manage_options', 'tod-whitelist', array(&$this, 'adminPageContent'), "dashicons-shield", 25);
	}
	
	public function adminPageContent(){
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		if(isset($_POST['changeUserState'])){
			require_once("user.class.php");
			$user = new user();
			$user->setUserState($_POST['id'], $_POST['state']);
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
	
}
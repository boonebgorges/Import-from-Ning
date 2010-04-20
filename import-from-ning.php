<?php
/*
Plugin Name: Ning to WordPress member import
Plugin URI: http://teleogistic.net/code/wordpresswordpress-mu/import-from-ning/
Description: Import users from Ning export CSV into WordPress/BuddyPress. Based on Dagon Design Import Users
Author: Boone Gorges
Version: 1.1
Author URI: http://teleogistic.net
*/


function bp_ning_import_add_management_pages() {
	if ( function_exists( 'bp_core_setup_globals' ) )
		$plugin_page = add_submenu_page( 'bp-general-settings', __('Import from Ning','bp-ning-import'), __('Import from Ning','bp-ning-import'), 'manage_options', __FILE__, 'bp_ning_import_management_page' );
	else
		$plugin_page = add_submenu_page( 'users.php', __('Import from Ning','bp-ning-import'), __('Import from Ning','bp-ning-import'), 'manage_options', __FILE__, 'bp_ning_import_management_page' );
	
	add_action( "admin_print_styles-$plugin_page", 'bp_ning_import_style' );
}

 
function bp_ning_import_style() {
	wp_enqueue_style( 'invite-anyone-admin-css', WP_PLUGIN_URL . '/import-from-ning/style.css' );
}



/* Only load BuddyPress functions if BP is active */
function bp_ning_import_bp_init() {
	require( dirname( __FILE__ ) . '/bp-functions.php' );
}
add_action( 'bp_init', 'bp_ning_import_bp_init' );

#can specify how to parse submitted file by editing this function
function fileParseFunction($filename){
	return file($filename);
}

#modify this function to specify how to parse text in field
#could change format or add validation
function fieldParseFunction($text){
	return explode("\n", trim($text));
}



function bp_ning_import_management_page() {

	global $wpdb, $wp_roles, $formatinfo, $ddui_version;

	$result = "";

	if (isset($_POST['step_one'])) {
		
		/* If no file was selected, throw an error and start over */
		if ( $_FILES['ddui_file']['error'] == 4 ) {
			?>
			<div id="message" class="error fade"><p><?php _e( 'You didn\'t select a file!', 'bp-ning-import' ) ?></p></div>
			<?php
			bp_ning_import_step_one();
			die();
		}
		

		/* OK, a file was found. Turn it into the necessary arrays */

		
		$u_temp = array();
		
		/* This stuff is for the text upload box - shouldn't need it */ 
		/*
		if(trim((string)$_POST["ddui_data"]) != ""){
			$u_temp = array_merge($u_temp, fieldParseFunction(((string) ($_POST["ddui_data"]))));
		}
		else{
			$result .= "<p>No names entered in field.</p>";
		}
		*/
		
		if ($_FILES['ddui_file']['error'] != UPLOAD_ERR_NO_FILE){#Earlier versions of PHP may use $HTTP_POST_FILES
			$file = $_FILES['ddui_file'];
			if($file['error']){
				$result .= '<h4 style="color: #FF0000;">Errors!</h4><p>';
				switch ($file['error']){
					case UPLOAD_ERR_INI_SIZE:
						$result .= "File of ".$file['size']."exceeds max size ".upload_max_filesize;
						break;
					case UPLOAD_ERR_FORM_SIZE:
						$result .= "File of ".$file['size']."exceeds max size ".upload_max_filesize;
						break;
					case UPLOAD_ERR_PARTIAL:
						$result .= "File not fully uploaded";
						break;
					default:
				}
				$result.='.</p>';
			}
			elseif(!is_uploaded_file($file['tmp_name'])){
				//$result = "File ".$file['name']." was not uploaded via the form.";
			}
			else{ #should be ok to read the file now
				$u_temp = array_merge($u_temp, fileParseFunction($file['tmp_name']));
			}
		} else{
			$result .= "<p>No file submitted.</p>";
		}
		//print "<pre>"; 
		$u_data = array();
		$i = 0;

		$u_keys = $u_temp[0];
		
		$u_keys = explode( ",", $u_keys );
		
		
		foreach( $u_keys as $key => $u_key ) {
			$u_key = trim( $u_key );
			$u_key = str_replace( '\"', '', $u_key );
			$u_key = str_replace( '"', '', $u_key );
			$u_keys[$key] = $u_key;
		}

		unset( $u_temp[0] );
		$u_temp = array_values( $u_temp );
		
		/* loop through the lines of the csv (each is a user) */
		foreach ($u_temp as $ut) {
			if (trim($ut) != '') {
				/* Ugly trick to account for commas in field data */
				$ut = str_replace( ", ", "%%%", $ut );
				$ut = explode( ',', $ut );
				
				/* loop through each piece of data, strip slashes and quotes, put in associative array */
				foreach ( $ut as $key => $data ) {
					$new_key = $u_keys[$key];
					$data = trim( $data );
					$data = str_replace( '\"', '', $data );
					$data = str_replace( '"', '', $data );
					$data = str_replace( '%%%', ', ', $data);
					$u_data[$i][$new_key] = $data;
				}
				$i++;
			}
		}
		
		bp_ning_import_step_two( $u_keys, $u_data );
	
	} else if ( $_POST['step_two'] ) {
		//print "<pre>"; print_r($_POST); die();
		
		/* Hook to allow BuddyPress functions to manipulate the data */
		/* $u_data is the user data; $u_keys is an array containing the ning profile fields */
		$data = $_POST;
		//$data = apply_filters( 'ning_import_step_two_submit', $data );
		
		$u_keys = $_POST['u_keys'];
		$u_data = $_POST['u_data'];
		
		$errors = array();
		$complete = 0;
		$the_role = 'subscriber';

		$errors = array();
		$new_data = array( 'errors' => $errors );
		
		foreach ($u_data as $ud) {			
			$ud = explode( "|", $ud );
			
			$n_key = array_search( 'Name', $u_keys );
			$name = $ud[$n_key];
			
			$e_key = array_search( 'Email', $u_keys );
			$email = $ud[$e_key];
			
			if ( !is_email( $email ) )
				$ud['error'] = 1;
			
			$username = strtolower( preg_replace( "/\s+/", '', $name ) );

			if ( !validate_username( $username ) )
				$ud['error'] = 2;
			
			/* Autogenerates username by adding an integer to the end of it */
			if ( username_exists( $username ) ) {
				$i = 1;
				while ( username_exists( $username . $i ) )
					$i++;
				$username = $username . $i;
			}

			$email_exists = $wpdb->get_row("SELECT user_email FROM $wpdb->users WHERE user_email = '" . $email . "'");
			
			if ( $email_exists )
				$ud['error'] = 3;

			/* Error codes:
				1 - no email address/poorly formatted email address
				2 - username does not validate
				3 - email address already exists
				4 - tried to create user, but couldn't
			*/

			if ( $ud['error'] ) {
				$new_data['errors'][] = $ud;	
			} else {
				// generate passwords if none were provided in the import
				$password = substr(md5(uniqid(microtime())), 0, 7);
				
				// create user
				$args = array(
					"user_login" => $username,
					"display_name" => $name,
					"nickname" => $name,
					"user_pass" => $password,
					"user_email" => $email
				);
					
				$user_id = wp_insert_user( $args );
				
				if ( !$user_id ) {
					$ud['error'] = 4;
					$new_data['errors'][] = $ud;
				} else {
					/* Account was successfully created. Send u:p to user */
					wp_new_user_notification( $user_id, $password );
					$complete++;

					/* Set the role to Subscriber */
					$ruser = new WP_User( $user_id );
					$ruser->set_role( $the_role );
					
					$new_data[$user_id] = $ud;
				}
			}
		}

		/* $new_data format:
		Array (
			[errors] => Array (
				// $uds of those whose user ids were not created, with error codes
				)
			[user id 1] => Array (
			) // successfully created users, keyed by their new user ids
		)
		*/
		
		bp_ning_import_step_two_results( $u_keys, $new_data );
		
	} else if ( $_POST['step_two_success'] ) {
		do_action( 'ning_import_step_two_submit', $_POST );
		die();

	} else if ( $_POST['step_three'] ) {
		
		/* If the user has selected profile fields to import, process them. Otherwise we're done. */
		if ( $_POST['fields'] ) {
			bp_ning_import_build_profile_fields( $_POST );
		} else {
		?>
			<div class="wrap">
				<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
				<p><?php _e( 'This tools allows you to import users and user data from a Ning network into a WordPress installation. If you have BuddyPress installed, you will also be able to import additional profile information into BuddyPress profiles.', 'bp-ning-import' ) ?></p>
	
				<h3><?php _e( 'Step Three: Import Additional Profile Data to BuddyPress', 'bp-ning-import' ) ?></h3>
				
				<p><?php _e( "You didn't select any fields. That means that you're done importing. Enjoy using WordPress and BuddyPress!", 'bp-ning-import' ) ?></p>
			
			</div>
		<?php
		}	

	} else {
		bp_ning_import_step_one();	
	}
}


add_action('admin_menu', 'bp_ning_import_add_management_pages');


function bp_ning_import_step_one() {
?>
	<div class="wrap">
	
	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
	<p><?php _e( 'This tools allows you to import users and user data from a Ning network into a WordPress installation. If you have BuddyPress installed, you will also be able to import additional profile information into BuddyPress profiles.', 'bp-ning-import' ) ?></p>
	
	<h3><?php _e( 'Step One: Export from Ning', 'bp-ning-import' ) ?></h3>

	<form enctype="multipart/form-data" method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>"  >
		<ol>
			<li><?php _e( 'In your Ning network, navigate to <strong>Manage > Members</strong>.', 'bp-ning-import' ) ?></li>
			<li><?php _e( 'Scroll to the bottom of the member list until you find the Export All Member Data (.CSV) link. Click the link and follow the instructions to download the member data onto your computer.', 'bp-ning-import' ) ?></li>
			<li><?php _e( 'In the <strong>User Data</strong> field below, browse for the .CSV file that you just downloaded.', 'bp-ning-import' ) ?></li>


			<input type="hidden" name="step_one" id="step_one" value="true" />
			<?php print $formatinfo; ?>
			<br />
			<label for="ddui_file"><em>User data</em>: <input type="file" id="ddui_file" name="ddui_file" value="TestInput" /> </label>
			<br /><br />
			<?php /* Removing the textarea since the CSV works <br />
			<textarea name="ddui_data" cols="100" rows="12"></textarea>
			<br /> */ ?>
			
			
			
			<li><?php _e( 'Click <strong>Continue</strong> to move on to the next step.', 'bp-ning-import' ) ?></li>
		</ol>
		
		<div class="submit">
			<input class="button primary-button" type="submit" name="info_update" value="<?php _e( 'Continue', 'bp-ning-import' ); ?> &raquo;" />
		</div>
	</form>
		<?php 
		if ($result != "") { 
			echo '<div style="border: 1px solid #000000; padding: 10px;">';
			echo '<h4>Results</h4>';
			echo trim($result); 
			echo '</div>';
		} 
	?>
	
	</div>
<?php
}

function bp_ning_import_step_two( $u_keys, $u_data ) {
?>
	<div class="wrap">
	
	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
	
	<h3><?php _e( 'Step Two: Create the Users', 'bp-ning-import' ) ?></h3>
	
	<p><?php _e( "The following users were identified in the file you uploaded. If there are any users on the list that you <em>don't</em> want to import, you can uncheck them before hitting <strong>Continue</strong>.", 'bp-ning-import' ) ?></p>
	
	<form action='<?php echo $_SERVER["REQUEST_URI"]; ?>' method="post">
	<ul>
	<?php foreach( $u_data as $u ) : ?>
		<?php
			$u_deets = $u['Name'] . " - " . $u['Email'];		
		?>
		<li><input type="checkbox" name="u_data[]" id="u_data[]" value="<?php echo implode( '|', $u) ?>" checked="checked" /> <?php echo $u_deets ?></li>
		
	<?php endforeach; ?>
	</ul>
	
	<?php foreach( $u_keys as $k ) : ?>
		<input type="hidden" name="u_keys[]" id="u_keys[]" value="<?php echo esc_attr($k) ?>" />
	<?php endforeach; ?>
	
	<input type="hidden" name="step_two" id="step_two" value="1" />
	
	<br />
	
	<div class="bp-ning-import-error-users">
	<p><?php _e( '<strong>Warning</strong>: When you click <strong>Continue</strong>, user accounts will be created for the users above. Email notifications, which include user names and passwords, will be sent <strong>immediately</strong> to the email addresses selected below.', 'bp-ning-import' ) ?></p>
	</div>
	
	<div class="submit">
		<input class="button primary-button" type="submit" name="info_update" value="<?php _e( 'Continue', 'bp-ning-import' ); ?> &raquo;" />
	</div>
	
	</form>
	</div>
<?php 
}

function bp_ning_import_step_two_results( $u_keys, $data ) {

	$error_messages = array(
		1 => __( "Invalid email address", 'bp-ning-import' ),
		2 => __( "Invalid username", 'bp-ning-import' ),
		3 => __( "Email address already in use", 'bp-ning-import' ),
		4 => __( "Couldn't create user", 'bp-ning-import' )
	);
	
?>
	<div class="wrap">
	
	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
	
	<h3><?php _e( 'Step Two: Create the Users', 'bp-ning-import' ) ?></h3>
	
	
	<?php if ( !empty( $data['errors'] ) ) : ?>
		<p><?php _e( "Here is a list of the users that couldn't be created. You'll have to create these users manually at <strong>Dashboard > Users > Add New</strong>.", 'bp-ning-import' ) ?></p>
		
		<div class="bp-ning-import-error-users">
			<ul>
				<?php foreach( $data['errors'] as $error ) : ?>
				<?php
					$n_key = array_search( 'Name', $u_keys );
					$name = $error[$n_key];
			
					$e_key = array_search( 'Email', $u_keys );
					$email = $error[$e_key];
					
					$e = $error['error'];
				?>
				<li><?php echo $name . " - " . $email ?> - <span class="bp-ning-import-error-message"><?php echo $error_messages[$e] ?></span></li>
					
				<?php endforeach; ?>
			</ul>
		</div>		
	<?php endif; ?>
	
	<?php if ( count( $data ) > 1 ) : ?>
	<form action='<?php echo $_SERVER["REQUEST_URI"]; ?>' method="post">
	
		<p><?php _e( "The following users were created successfully. Emails with usernames and passwords have been sent.", 'bp-ning-import' ) ?></p>
	
		<div class="bp-ning-import-success-users">
			<ul>
			<?php foreach( $data as $key => $user ) : ?>
				
				<?php
					if ( $key == 'errors' )
						continue;
					
					$n_key = array_search( 'Name', $u_keys );
					$name = $user[$n_key];
			
					$e_key = array_search( 'Email', $u_keys );
					$email = $user[$e_key];
				?>
				<li><?php echo $name . " - " . $email ?></li>
				
				<?php /* This hidden input will carry all the necessary info to the next page - blech */ ?>
				<input type="hidden" name="u_data[<?php echo $key ?>]" id="u_data[<?php echo $key ?>]" value="<?php echo implode( '|', $user ) ?>" />
				
			<?php endforeach; ?>
			</ul>
		</div>
	
		
		<?php if ( function_exists( 'bp_core_setup_globals' ) ) : ?>
			<?php /* The Continue button is only shown if users were successfully imported and BuddyPress is running... */	?>
			
			<?php foreach( $u_keys as $k ) : ?>
				<input type="hidden" name="u_keys[]" id="u_keys[]" value="<?php echo esc_attr($k) ?>" />
			<?php endforeach; ?>
			
			<input type="hidden" name="step_two_success" id="step_two_success" value="1" />
			
			<p><?php _e( "Since you're running BuddyPress, you'll be able to import additional Ning profile data into BuddyPress profile fields on the next page.", 'bp-ning-import' ) ?></p>
			
			<div class="submit">
				<input class="button primary-button" type="submit" name="info_update" value="<?php _e( 'Continue', 'bp-ning-import' ); ?> &raquo;" />
			</div>
		
		<?php else : ?>
			<?php /* ...otherwise the import is complete. */ ?>
			
			<p><?php _e( "The import is complete! Enjoy using WordPress.", 'bp-ning-import' ) ?></p>
			
		<?php endif; ?>	
	
	</form>
	
	<?php else : ?>
		<?php /* If there are no users successfully created, then we're done */ ?>
		<p><?php _e( "No users successfully imported. Are you sure that your .CSV is correctly formatted? Try downloading it again from Ning, and start the import process over again with the new file. Warning: Don't try to edit the .CSV file in MS Excel, as it's known to cause problems.", 'bp-ning-import' ) ?></p>
		
	<?php endif; ?>
	
	
	</div>
<?php
}

?>
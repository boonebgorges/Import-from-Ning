<?php
/* Import from Ning BP functions */


function echo_memory_usage() {
        $mem_usage = memory_get_usage(true);

        if ($mem_usage < 1024)
            echo $mem_usage." bytes";
        elseif ($mem_usage < 1048576)
            echo round($mem_usage/1024,2)." kilobytes";
        else
            echo round($mem_usage/1048576,2)." megabytes";

        echo "<br/>";
    }


function bp_ning_import_add_management_pages() {
	$plugin_page = add_submenu_page( 'bp-general-settings', __('Import from Ning','bp-ning-import'), __('Import from Ning','bp-ning-import'), 'manage_options', __FILE__, 'bp_ning_import_steps' );

	add_action( "admin_print_styles-$plugin_page", 'bp_ning_import_style' );
}
add_action('admin_menu', 'bp_ning_import_add_management_pages');


function bp_ning_import_style() {
	wp_enqueue_style( 'invite-anyone-admin-css', WP_PLUGIN_URL . '/import-from-ning/style.css' );
}

function bp_ning_import_steps() {

	bp_ning_import_header_markup();

	if ( !isset( $_POST['current_step'] ) )
		bp_ning_import_intro_markup();
	else
		$current_step = $_POST['current_step'];

	switch( $current_step ) {
		case 'members' :
			bp_ning_import_members_markup();
			break;

		case 'profiles' :
			bp_ning_import_profiles_markup();
			break;

		case 'profiles_done' :
			bp_ning_import_profile_two_markup();
			break;

		case 'groups' :
			bp_ning_import_groups_markup();
			break;

		case 'groups_done' :
			bp_ning_import_discussion_groups_markup();
			break;

		case 'discussion_groups_done' :
			bp_ning_import_discussions_markup();
			break;

		case 'discussions_done' :
			bp_ning_import_blogs_markup();
			break;

		case 'blogs_done' :
			bp_ning_import_finished_markup();
			break;

		case 'send_email' :
			bp_ning_import_sent_email_markup();
			break;

		case 'start_over' :
			delete_option( 'bp_ning_left_off' );
			delete_option( 'bp_ning_group_array' );
			delete_option( 'bp_ning_import_users' );
			delete_option( 'bp_ning_user_array' );
			delete_option( 'bp_ning_import_finished' );
			delete_option( 'bp_ning_emails_sent' );
			bp_ning_import_members_markup();
			break;
	}
}


function bp_ning_import_prepare_json( $type ) {
	$json = WP_CONTENT_DIR . '/ning-files/ning-' . $type . '-local.json';
	if ( !file_exists( $json ) )
		return false;

	$data = file_get_contents( $json );
	//echo $data;
	$data = preg_replace( '|^\(|', '', $data );
	$data = preg_replace( '|\)$|', '', $data );
	$data = str_replace( '}{', '},{', $data );
	$parsed = json_decode( $data );



/* switch(json_last_error())
    {
        case JSON_ERROR_DEPTH:
            echo ' - Maximum stack depth exceeded';
        break;
        case JSON_ERROR_CTRL_CHAR:
            echo ' - Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            echo ' - Syntax error, malformed JSON';
        break;
        case JSON_ERROR_NONE:
            echo ' - No errors';
        break;
    } */

	unset( $json );
	unset( $data );

	return $parsed;
}

function bp_ning_import_create_user( $userdata ) {
	global $wpdb;


	$username = strtolower( preg_replace( "/\s+/", '', $userdata->fullName ) );
	$username = str_replace( '@', '', $username );
	$username = str_replace( '.', '', $username );
	$username = str_replace( ')', '', $username );
	$username = str_replace( '(', '', $username );

	$username = preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", $username);
	$table = array(
        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
    );

    $username = strtr($username, $table);

	// Autogenerates username by adding an integer to the end of it
	if ( username_exists( $username ) ) {
		$i = 1;
		while ( username_exists( $username . $i ) )
			$i++;
		$username = $username . $i;
	}

	// Autogenerate password
	$password = substr(md5(uniqid(microtime())), 0, 7);

	$bp_member = array(
		"user_email" => $userdata->email,
		"user_name" => $userdata->fullName,
		"already_exists" => 0
	);


	// Check for existing member
	if ( $user = get_user_by_email( $userdata->email ) ) {

		$bp_member['user_login'] = $user->user_login;
		$bp_member['id'] = $user->ID;
		$bp_member['already_exists'] = 1;
		return $bp_member;

	} else {

		// create user
		$args = array(
			"user_login" => $username,
			"display_name" => $userdata->fullName,
			"nickname" => $userdata->fullName,
			"user_pass" => $password,
			"user_email" => $userdata->email
		);

		$bp_member['id'] = wp_insert_user( $args );
		$bp_member['user_login'] = $username;
		$bp_member['password'] = $password;

		echo "<br />" . $bp_member['id'] . ") $userdata->fullName created";
	}

	$f = explode( "?", $userdata->profilePhoto );
	$g = explode( "members/", $f[0] );
	$oldfilepath = WP_CONTENT_DIR . '/ning-files/' . $f[0];

	if ( !file_exists( $oldfilepath ) )
		return;

	$filename = $g[1];
	if ( strpos( $filename, '/' ) ) {
		$fn = explode( "/", $filename );
		$filename = array_pop( $fn );
	}

	if ( !preg_match( '/png|gif|jpg|jpeg|bmp|PNG|GIF|JPG|JPEG|BMP/', $filename ) )
		return;

	$newfilepath = BP_AVATAR_UPLOAD_PATH . '/' . $filename;

	if ( !file_exists( BP_AVATAR_UPLOAD_PATH . '/avatars/' ) )
		mkdir( BP_AVATAR_UPLOAD_PATH . '/avatars/' );

	if ( !file_exists( BP_AVATAR_UPLOAD_PATH . '/avatars/' . $bp_member['id'] ) )
		mkdir( BP_AVATAR_UPLOAD_PATH . '/avatars/' . $bp_member['id'] );

	copy( $oldfilepath, $newfilepath );

	// Rudimentary squaring algorithm
	$size = getimagesize( $newfilepath );

	$args = array(
		'item_id' => $bp_member['id'],
		'original_file' => '/' . $filename
	);

	if ( $size[0] > $size[1] ) {
		$diff = $size[0] - $size[1];
		$cropx = $diff/2;
		$args['crop_w'] = $size[1];
		$args['crop_h'] = $size[1];
		$args['crop_x'] = $cropx;
	} else {
		$diff = $size[1] - $size[0];
		$cropy = $diff/2;
		$args['crop_w'] = $size[0];
		$args['crop_h'] = $size[0];
		$args['crop_y'] = $cropy;
	}

	bp_core_avatar_handle_crop( $args ); // todo - find a good way to check for avatar import. bp_core_get_avatar()?

	// Store the Ning ID for association with content later on
	// update_user_meta( $bp_member['id'], 'ning_id', $userdata->contributerName );

	return $bp_member;
}


// Step setup functions
function bp_ning_import_get_members() {
	if ( !$left_off = get_option( 'bp_ning_left_off' ) )
		$left_off = -1;

	$members = bp_ning_import_prepare_json( 'members' );
	if ( !$member_id_array = get_option( 'bp_ning_import_users' ) )
		$member_id_array = array();

	if ( !$ning_id_array = get_option( 'bp_ning_user_array' ) )
		$ning_id_array = array();

	echo "Importing users - this may take a while.<br />";
	echo "If you see a Refresh message at the bottom of the page you'll need to refresh the page in order to continue importing.";

	$counter = $left_off;

	foreach ( $members as $member_key => $member ) {

		// echo "<br />$member_key";
		//if ( $member_key < $left_off )
		// 	continue;

		/* if ( $counter % 200 == 0 && $counter != 0 ) {
		 	echo "<h2>Refresh to continue importing users</h2>";
		 	update_option( 'bp_ning_import_users', $member_id_array );
			update_option( 'bp_ning_user_array', $ning_id_array );
		 	die();
		 } */

		 $bp_member = bp_ning_import_create_user( $member );

		 if ( isset( $bp_member['id'] ) )
		 	$member_id_array['success'][] = $bp_member;
		 else
		 	$member_id_array['error'][] = $bp_member;

		 // Create an array of Ning IDs for later reference
		 $ning_id = $member->contributorName;
		 $ning_id_array[$ning_id] = $bp_member['id'];

		 //update_option( 'bp_ning_left_off', $member_key );
		 $counter++;
	}

	update_option( 'bp_ning_import_users', $member_id_array );
	update_option( 'bp_ning_user_array', $ning_id_array );
	delete_option( 'bp_ning_left_off' );

	return $member_id_array;
}

function bp_ning_import_get_profile_fields() {
	$members = bp_ning_import_prepare_json( 'members' );

	$profile_fields = array();
	$forbidden_fields = array(
		'createdDate',
		'fullName',
		'comments',
		'profilePhoto',
		'level',
		'contributorName',
		'state',
		'profileQuestions'
	);


	foreach ( $members as $member ) {
		$member = (array)$member;
		foreach( $member as $key => $value ) {
			if ( $key == 'profileQuestions' ) {
				$questions = (array)$value;
				foreach( $questions as $q => $a ) {
					if ( !in_array( $q, $profile_fields ) && !in_array( $q, $forbidden_fields) )
						$profile_fields[] = $q;
				}
			}

			if ( !in_array( $key, $profile_fields ) && !in_array( $key, $forbidden_fields) )
				$profile_fields[] = $key;
		}
	}

	unset( $members );
	return $profile_fields;

}

function bp_ning_import_process_profiles() {

	$ning_id_array = get_option( 'bp_ning_user_array', $ning_id_array );

	if ( empty( $_POST['pf'] ) )
		return;

	$fields = $_POST['pf'];

	// Keep track of renamed fields
	$field_key = array();
	foreach( $fields as $key => $field ) {

		// Check to see if the user provided an alternative name for the field
		if ( $_POST['pfn'][$key] ) {
			$newfield = $_POST['pfn'][$key];
			$fields[$key] = $newfield;
			$field_key[$field] = $newfield;
		} else {
			$field_key[$field] = $field;
			$newfield = $field;
		}

		// Create the field
		$args = array(
			'field_group_id' => 1,
			'name' => $newfield,
			'type' => 'textbox',
			'is_required' => false
			);

		if ( !xprofile_get_field_id_from_name( $newfield ) ) {
			xprofile_insert_field( $args );
		}

	}

	// Get the field ids for the just-created fields. Todo: patch the core so that xprofile_insert_field() returns the id

	foreach( $fields as $field ) {
		$field_ids[$field] = xprofile_get_field_id_from_name( $field );
	}

	// Populate the new fields
	$members = bp_ning_import_prepare_json( 'members' );

	foreach( $members as $member ) {
		$member = (array)$member;

		$ncommented_id = $member['contributorName'];
		$commented_id = $ning_id_array[$ncommented_id];
		$commented_username = bp_core_get_username( $commented_id );

		// Create @replies for all comments
		if ( isset( $member['comments'] ) && !get_user_meta( $commented_id, 'ning_comments_imported' ) ) {
			global $bp;

			$comments = $member['comments'];

			$ncommented_id = $member['contributorName'];
			$commented_id = $ning_id_array[$ncommented_id];
			$commented_username = bp_core_get_username( $commented_id );

			foreach( $comments as $comment ) {
				$ncommenter_id = $comment->contributorName;
				$commenter_id = $ning_id_array[$ncommenter_id];

				$ndate = strtotime( $comment->createdDate );
				$date_created = date( "Y-m-d H:i:s", $ndate );

				$from_user_link = bp_core_get_userlink( $commenter_id );

				$activity_action = sprintf( __( '%s posted an update:', 'buddypress' ), $from_user_link );

				$activity_content = '@' . $commented_username . ' ' . $comment->description;

				$primary_link = bp_core_get_userlink( $commenter_id, false, true );

				$args = array(
					'user_id' => $commenter_id,
					'action' => $activity_action,
					'content' => bp_activity_at_name_filter( $activity_content ),
					'primary_link' => $primary_link,
					'component' => $bp->activity->id,
					'type' => 'activity_update',
					'recorded_time' => $date_created
				);

				bp_activity_add( $args );
			}

			update_user_meta( $commented_id, 'ning_comments_imported', 1 );
		} else {
			update_user_meta( $commented_id, 'ning_comments_imported', 1 );
		}

		$profile_imported = get_user_meta( $commented_id, 'ning_profile_imported' );

		if ( isset( $member['profileQuestions'] ) && !$profile_imported ) {
			$questions = (array)$member['profileQuestions'];
			foreach ( $questions as $q => $a ) {
				$member[$q] = $a;
			}
			unset( $member['profileQuestions'] );
		}


		if ( !$profile_imported ) {
			xprofile_set_field_data( get_option( 'bp-xprofile-fullname-field-name' ), $commented_id, $member['fullName'] );

			foreach( $member as $key => $value ) {
				if ( !array_key_exists( $key, $field_key ) )
					continue;

				$bp_field_name = $field_key[$key];

				$user = get_user_by_email( $member['email'] );

				if ( !xprofile_get_field_data( $bp_field_name, $user->ID ) ) {
					xprofile_set_field_data( $bp_field_name, $user->ID, $value );
				}
			}
		}

		update_user_meta( $commented_id, 'ning_profile_imported', 1 );

	}

	unset( $members );
}


function bp_ning_import_process_inline_images( $text, $type ) {
	// Only worry about local images
	if ( strpos( $text, 'img src="' . $type ) ) {
		$images = array();
		$b = explode( 'img src="' . $type . '/', $text );
		foreach ( $b as $c ) {
			if ( !preg_match( '/gif|jpg|jpeg|png|bmp/', $c ) )
				continue;

			$d = explode( '"', $c );

			$i = explode( '?', $d[0] );

			if ( strpos( $i[0], ' ' ) )
				continue;

			$images[] = $i[0];
		}

		$uploads = wp_upload_dir();

		// Move the file to the uploads dir

		$current_dir = WP_CONTENT_DIR . '/ning-files/' . $type . '/';

		// $images is an array of file names in import-from-ning/json/discussions. Move 'em
		foreach ( $images as $image ) {
			$new_filename = wp_unique_filename( $uploads['path'], $image, $unique_filename_callback );
			$new_file = $uploads['path'] . "/$new_filename";
			$image_path = $current_dir . $image;

			if ( !file_exists( $new_filename ) )
				continue;

			copy( $image_path, $new_file );

			// Borrowed some of this upload code from WP core
			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );

			// Compute the URL
			$url = $uploads['url'] . "/$new_filename";

			$text = str_replace( $type . '/' . $image, $url, $text );
		}

		unset( $images );
	}

	return $text;
}


function bp_ning_import_get_groups() {
	$ning_id_array = get_option( 'bp_ning_user_array' );

	// Get list of Ning groups for cross reference
	$groups = bp_ning_import_prepare_json( 'groups' );

	if ( !$ning_group_id_array = get_option( 'bp_ning_group_array' ) )
		$ning_group_id_array = array();

	$counter = 0;
	foreach ( $groups as $group_key => $group ) {

		if ( $counter >= 30 ) {
			update_option( 'bp_ning_group_array', $ning_group_id_array );

			echo "<h3>Refresh to continue</h3>";
			die();
		}

		// Create the group
		$ning_group_creator_id = $group->contributorName;
		$creator_id = $ning_id_array[$ning_group_creator_id];

		$status = ( $group->groupPrivacy == 'private' ) ? 'private' : 'public';

		$ndate = strtotime( $group->createdDate );

		$date_created = date( "Y-m-d H:i:s", $ndate );

		$slug = sanitize_title( esc_attr( $group->title ) );

		$args = array(
			'creator_id' => $creator_id,
			'name' => $group->title,
			'description' => $group->description,
			'slug' => $slug,
			'status' => $status,
			'enable_forum' => 1,
			'date_created' => $date_created
		);

		if ( !BP_Groups_Group::group_exists( $slug ) ) {
			if ( $group_id = groups_create_group( $args ) ) {
				groups_update_groupmeta( $group_id, 'last_activity', $date_created );
				groups_update_groupmeta( $group_id, 'total_member_count', 1 );
				groups_new_group_forum( $group_id, $group->title, $group->description );
				echo "$group_key) <strong>Created group: $group->title</strong><br />";
				if ( !$forum_id = groups_get_groupmeta( $group_id, 'forum_id' ) ) {
					print_r($group);
					die();
				}

				$ngroup_id = $group->id;
				$ning_group_id_array[$ngroup_id] = $group_id;

				$counter++;
			}
		} else {
			//echo "<em>Group $group->title already exists</em><br />";
		}

		if ( is_array( $group->members ) ) {
			foreach( $group->members as $member ) {
				$ning_group_member_id = $member->contributorName;
				$member_id = $ning_id_array[$ning_group_member_id];

				groups_join_group( $group_id, $member_id );
			}
		}
	}
	update_option( 'bp_ning_group_array', $ning_group_id_array );

	unset( $groups );
}


function bp_ning_import_get_discussion_groups() {
	global $wpdb;

	$ning_id_array = get_option( 'bp_ning_user_array' );

	// Get list of Ning groups for cross reference
	$groups = bp_ning_import_prepare_json( 'groups' );

	if ( !$ning_group_id_array = get_option( 'bp_ning_group_array' ) )
		$ning_group_id_array = array();

	// Loop through each discussion. If the topic doesn't have a corresponding group, create one. Then insert the forum items.

	$discussions = bp_ning_import_prepare_json( 'discussions' );

	$counter = 0;
	foreach ( $discussions as $discussion_key => $discussion ) {
		if ( !isset( $discussion->category ) )
			continue;

		// todo - what if a topic has no group and no category


		$slug = sanitize_title( esc_attr( $discussion->category ) );

		$ning_group_creator_id = $discussion->contributorName;
		$creator_id = $ning_id_array[$ning_group_creator_id];

		$ndate = strtotime( $discussion->createdDate );
		$date_created = date( "Y-m-d H:i:s", $ndate );

		if ( !$group_id = BP_Groups_Group::group_exists( $slug ) ) {

			$args = array(
				'creator_id' => $creator_id,
				'name' => $discussion->category,
				'description' => $discussion->category,
				'slug' => groups_check_slug( $slug ),
				'status' => 'public',
				'enable_forum' => 1,
				'date_created' => $date_created
			);

			if ( $group_id = groups_create_group( $args ) ) {
				groups_update_groupmeta( $group_id, 'last_activity', $date_created );
				groups_update_groupmeta( $group_id, 'total_member_count', 1 );

				groups_new_group_forum( $group_id, $discussion->category, $discussion->category );
				echo "<strong>Created group: $discussion->category</strong><br />";

				$ning_group_id = $discussion->category;
				$ning_group_id_array[$ning_group_id] = $group_id;
				update_option( 'bp_ning_group_array', $ning_group_id_array );
			}
		}
	}
}



function bp_ning_import_get_discussions() {
	global $wpdb;

	$ning_id_array = get_option( 'bp_ning_user_array' );

	// Get list of Ning groups for cross reference
	$groups = bp_ning_import_prepare_json( 'groups' );

	if ( !$ning_group_id_array = get_option( 'bp_ning_group_array' ) )
		$ning_group_id_array = array();

	$discussions = bp_ning_import_prepare_json( 'discussions' );

	//do_action( 'bbpress_init' );

	$counter = 0;
	$what = 0;
	foreach ( $discussions as $discussion_key => $discussion ) {
		unset( $topic_id );

		if ( $counter >= 4 ) {
			echo "<h3>Refresh to continue</h3>";
			die();
		}

		$slug = sanitize_title( esc_attr( $discussion->category ) );

		$ning_group_creator_id = $discussion->contributorName;
		$creator_id = $ning_id_array[$ning_group_creator_id];

		if ( !$creator_id ) {
			$what++;
			continue;
		}

		$ndate = strtotime( $discussion->createdDate );
		$date_created = date( "Y-m-d H:i:s", $ndate );

		if ( isset( $discussion->category ) ) {
			$ning_group_id = $discussion->category;
			$group_id = $ning_group_id_array[$ning_group_id];
		} else if ( isset( $discussion->groupId ) ) {
			$ngroup_id = $discussion->groupId;
			$group_id = $ning_group_id_array[$ngroup_id];
		} else {
			continue; // todo fix me!
		}

		// Let's handle inline images!!!1!1! ROFLMAO
		$discussion->description = bp_ning_import_process_inline_images( $discussion->description, 'discussions' );

		$args = array(
			'topic_title' => $discussion->title,
			'topic_slug' => groups_check_slug( sanitize_title( esc_attr( $discussion->title ) ) ),
			'topic_text' => $discussion->description,
			'topic_poster' => $creator_id,
			'topic_poster_name' => bp_core_get_user_displayname( $creator_id ),
			'topic_last_poster' => $creator_id,
			'topic_last_poster_name' => bp_core_get_user_displayname( $creator_id ),
			'topic_start_time' => $date_created,
			'topic_time' => $date_created,
			'forum_id' => groups_get_groupmeta( $group_id, 'forum_id' )
		);
		$query = "SELECT `topic_id` FROM wp_bb_topics WHERE topic_title = '%s' AND topic_start_time = '%s' LIMIT 1";

		$q = $wpdb->prepare( $query, $args['topic_title'], $args['topic_start_time'] );

		$topic_exists = $wpdb->get_results( $q );

		if ( isset( $topic_exists[0] ) ) {
			//echo "<em>- Topic $discussion->title already exists</em><br />";
			continue;
		}

		if ( !$args['forum_id'] )
			{ echo "No forum id - skipping"; continue; }

		if ( !$topic_id = bp_forums_new_topic( $args ) ) {
			echo "<h2>Refresh to import more discussions</h2>";
			die();
		} else {
			echo "<strong>- Created topic: $discussion->title</strong><br />";
		}

		$skip_activity = get_option( 'bp_ning_skip_forum_activity' );

		if ( !$skip_activity ) {
			$topic = bp_forums_get_topic_details( $topic_id );

			$group = new BP_Groups_Group( $group_id );

			// Activity item
			$activity_action = sprintf( __( '%s started the forum topic %s in the group %s:', 'buddypress'), bp_core_get_userlink( $creator_id ), '<a href="' . bp_get_group_permalink( $group ) . 'forum/topic/' . $topic->topic_slug .'/">' . attribute_escape( $topic->topic_title ) . '</a>', '<a href="' . bp_get_group_permalink( $group ) . '">' . attribute_escape( $group->name ) . '</a>' );
			$activity_content = bp_create_excerpt( $topic_text );

			groups_record_activity( array(
				'user_id' => $creator_id,
				'action' => apply_filters( 'groups_activity_new_forum_topic_action', $activity_action, $discussion->description, &$topic ),
				'content' => apply_filters( 'groups_activity_new_forum_topic_content', $activity_content, $discussion->description, &$topic ),
				'primary_link' => apply_filters( 'groups_activity_new_forum_topic_primary_link', bp_get_group_permalink( $group ) . 'forum/topic/' . $topic->topic_slug . '/' ),
				'type' => 'new_forum_topic',
				'item_id' => $group_id,
				'secondary_item_id' => $topic->topic_id,
				'recorded_time' => $date_created,
				'hide_sitewide' => 0
			) );

			do_action( 'groups_new_forum_topic', $group_id, &$topic );
		}

		// Now check for comments

		if ( isset( $discussion->comments ) ) {
			foreach ( $discussion->comments as $reply ) {
				$ning_group_creator_id = $reply->contributorName;
				$creator_id = $ning_id_array[$ning_group_creator_id];

				$ndate = strtotime( $reply->createdDate );
				$date_created = date( "Y-m-d H:i:s", $ndate );

				$reply->description = bp_ning_import_process_inline_images( $reply->description, 'discussions' );

				$args = array(
					'topic_id' => $topic_id,
					'post_text' => $reply->description,
					'post_time' => $date_created,
					'poster_id' => $creator_id,
					'poster_ip' => '192.168.1.1'
				);

				$query = "SELECT * FROM wp_bb_posts WHERE topic_id = '%s' AND post_text = '%s'";
				$q = $wpdb->prepare( $query, $args['topic_id'], $args['post_text'] );
				$post_exists = $wpdb->get_results( $q );

				if ( $post_exists )
					continue;

				$post_id = bp_forums_insert_post( $args );

				if ( $post_id )
					echo "<strong>- Imported forum post: $reply->description</strong><br />";


				if ( !groups_is_user_member( $creator_id, $group_id ) ) {
					if ( !$bp->groups->current_group )
						$bp->groups->current_group = new BP_Groups_Group( $group_id );

					$new_member = new BP_Groups_Member;
					$new_member->group_id = $group_id;
					$new_member->user_id = $creator_id;
					$new_member->inviter_id = 0;
					$new_member->is_admin = 0;
					$new_member->user_title = '';
					$new_member->date_modified = $date_created;
					$new_member->is_confirmed = 1;

					$new_member->save();

					groups_update_groupmeta( $group_id, 'total_member_count', (int) groups_get_groupmeta( $group_id, 'total_member_count') + 1 );
					groups_update_groupmeta( $group_id, 'last_activity', $date_created );

					do_action( 'groups_join_group', $group_id, $creator_id );
				}


				if ( $skip_activity )
					continue;

				// Activity item
				$topic = bp_forums_get_topic_details( $topic_id );

				$activity_action = sprintf( __( '%s posted on the forum topic %s in the group %s:', 'buddypress'), bp_core_get_userlink( $creator_id ), '<a href="' . bp_get_group_permalink( $group_id ) . 'forum/topic/' . $topic->topic_slug .'/">' . attribute_escape( $topic->topic_title ) . '</a>', '<a href="' . bp_get_group_permalink( $group ) . '">' . attribute_escape( $group->name ) . '</a>' );
				$activity_content = bp_create_excerpt( $reply->description );
				$primary_link = bp_get_group_permalink( $group ) . 'forum/topic/' . $topic->topic_slug . '/';

				if ( $page )
					$primary_link .= "?topic_page=" . $page;
				//echo $primary_link; die();

				groups_record_activity( array(
					'user_id' => $creator_id,
					'action' => apply_filters( 'groups_activity_new_forum_post_action', $activity_action, $post_id, $reply->description, &$topic ),
					'content' => apply_filters( 'groups_activity_new_forum_post_content', $activity_content, $post_id, $reply->description, &$topic ),
					'primary_link' => apply_filters( 'groups_activity_new_forum_post_primary_link', "{$primary_link}#post-{$post_id}" ),
					'type' => 'new_forum_post',
					'item_id' => $group_id,
					'secondary_item_id' => $post_id,
					'recorded_time' => $date_created,
					'hide_sitewide' => 0
				) );

				do_action( 'groups_new_forum_topic_post', $group_id, $post_id );
			}
		}

		unset( $discussions[$discussion_key] );
	}

	unset( $discussions );


}

function bp_ning_import_get_blogs() {
	global $wpdb;

	$ning_id_array = get_option( 'bp_ning_user_array' );

	$blogs = bp_ning_import_prepare_json( 'blogs' );

	foreach ( $blogs as $blog ) {
		$ning_group_creator_id = $blog->contributorName;
		$creator_id = $ning_id_array[$ning_group_creator_id];

		$post_status = ( $blog->publishStatus == 'publish' ) ? 'publish' : 'draft';

		$ndate = strtotime( $blog->publishTime );
		$date_created = date( "Y-m-d H:i:s", $ndate );

		$blog->description = bp_ning_import_process_inline_images( $blog->description, 'blogs' );

		if ( !$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='post' AND post_date = %s", $blog->title, $date_created ) ) ) {
			$args = array(
				'post_type' => 'post',
				'post_status' => $post_status,
				'post_author' => $creator_id,
				'post_title' => $blog->title,
				'post_content' => $blog->description,
				'post_date' => $date_created
			);

			$post_id = wp_insert_post( $args );
			echo "<strong>Blog post created: $blog->title</strong><br />";

		} else {
			echo "<em>Blog post already exists: $blog->title</em><br />";
		}

		if ( isset( $blog->comments ) ) {
			foreach ( $blog->comments as $reply ) {
				$ning_group_creator_id = $reply->contributorName;
				$creator_id = $ning_id_array[$ning_group_creator_id];

				$ndate = strtotime( $reply->createdDate );
				$date_created = date( "Y-m-d H:i:s", $ndate );

				$reply->description = bp_ning_import_process_inline_images( $reply->description, 'blogs' );

				$commenter_data = get_userdata( $creator_id );

				$args = array(
					'comment_post_ID' => $post_id,
					'comment_author' => $commenter_data->user_nicename,
					'comment_author_email' => $commenter_data->user_email,
					'comment_content' => $reply->description,
					'comment_date' => $date_created,
					'user_id' => $creator_id,
					'comment_approved' => 1,
					'poster_ip' => '127.0.0.7'
				);

				$query = "SELECT * FROM wp_comments WHERE comment_post_ID = '%s' AND comment_content = '%s' AND comment_author = '%s'";
				$q = $wpdb->prepare( $query, $args['comment_post_ID'], $args['comment_content'], $args['comment_author'] );
				$post_exists = $wpdb->get_results( $q );

				if ( $post_exists )
					continue;

				$post_id = wp_insert_comment( $args );

			}
		}

	}

	unset( $blogs );

	$pages = bp_ning_import_prepare_json( 'pages' );

	if ( is_array( $pages ) ) {
		foreach ( $pages as $page ) {

			$ning_group_creator_id = $page->contributorName;
			$creator_id = $ning_id_array[$ning_group_creator_id];

			$ndate = strtotime( $page->createdDate );
			$date_created = date( "Y-m-d H:i:s", $ndate );

			if ( !$page->description )
				continue;

			$page->description = bp_ning_import_process_inline_images( $page->description, 'pages' );
			$page->description = str_replace( "\n", '', $page->description );

			if ( !$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='page' AND post_date = %s", $page->title, $date_created ) ) ) {
				$args = array(
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_author' => $creator_id,
					'post_title' => $page->title,
					'post_content' => $page->description,
					'post_date' => $date_created
				);

				$post_id = wp_insert_post( $args );
				echo "<strong>Page created: $page->title</strong><br />";

			} else {
				echo "<em>Page already exists: $page->title</em><br />";
			}
		}
	}

	unset( $pages );
}


function bp_ning_import_insert_post( $args = '' ) {
	global $bp;

	$defaults = array(
		'topic_title' => '',
		'topic_slug' => '',
		'topic_text' => '',
		'topic_poster' => $bp->loggedin_user->id, // accepts ids
		'topic_poster_name' => $bp->loggedin_user->fullname, // accept names
		'topic_last_poster' => $bp->loggedin_user->id, // accepts ids
		'topic_last_poster_name' => $bp->loggedin_user->fullname, // accept names
		'topic_start_time' => date( 'Y-m-d H:i:s' ),
		'topic_time' => date( 'Y-m-d H:i:s' ),
		'topic_open' => 1,
		'topic_tags' => false, // accepts array or comma delim
		'forum_id' => 0 // accepts ids or slugs
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$topic_title = strip_tags( $topic_title );

	if ( empty( $topic_title ) || !strlen( trim( $topic_title ) ) )
		return false;

	if ( empty( $topic_slug ) )
		$topic_slug = sanitize_title( $topic_title );

	if ( !$topic_id = bb_insert_topic( array( 'topic_title' => stripslashes( $topic_title ), 'topic_slug' => $topic_slug, 'topic_poster' => $topic_poster, 'topic_poster_name' => $topic_poster_name, 'topic_last_poster' => $topic_last_poster, 'topic_last_poster_name' => $topic_last_poster_name, 'topic_start_time' => $topic_start_time, 'topic_time' => $topic_time, 'topic_open' => $topic_open, 'forum_id' => (int)$forum_id, 'tags' => $topic_tags ) ) )
		return false;

	/* Now insert the first post. */
	if ( !bp_forums_insert_post( array( 'topic_id' => $topic_id, 'post_text' => $topic_text, 'post_time' => $topic_time, 'poster_id' => $topic_poster ) ) )
		return false;

	do_action( 'bp_forums_new_topic', $topic_id );

	return $topic_id;
}


// Markup functions
function bp_ning_import_header_markup() {
?>
<div class="wrap">

	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
<?php
}


function bp_ning_import_intro_markup() {

	$already_imported = get_option( 'bp_ning_import_finished' );
	$already_sent = get_option( 'bp_ning_emails_sent' );

	if ( $members = bp_ning_import_prepare_json( 'members' ) ) {
		$json_found = true;
	} else {
		$json_dir = WP_CONTENT_DIR . '/ning-files/';
		$json_found = false;
	}
?>
	<?php if ( !$already_imported && !$already_sent ) : ?>

		<h3><?php _e( 'Welcome', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'This plugin will walk you through the process of importing your Ning network backup into BuddyPress. At this time, the plugin imports the following data:' ) ?></p>

		<ul>
			<li>Members</li>
			<li>Profile data</li>
			<li>Groups</li>
			<li>Group discussions</li>
			<li>Miscellaneous discussions</li>
			<li>Blogs</li>
			<li>Pages</li>
		</ul>

		<p>At this time, the plugin does <em>not</em> import the following:</p>

		<ul>
			<li>Photos</li>
			<li>Videos</li>
			<li>Music</li>
			<li>Notes</li>
			<li>Events</li>
		</ul>

		<p>These latter items are not supported by BuddyPress without the use of plugins. At the end of the import process, you'll see a list of plugins that might help you to manage some of the items that the importer can't handle.</li>

		<p>At various times during the import process, you may be asked to hit the Refresh button. When that happens, if you get a message asking whether you'd like to resubmit your data, make sure you answer <strong>Yes</strong> or <strong>OK</strong>.</p>

		<p><strong>Before continuing,</strong> it's recommended that you do the following:</p>
		<ol>
			<li>Activate BuddyPress/bbPress forums (Dashboard > BuddyPress > Forum Setup)</li>
			<li>Change the permissions on your wp-content/uploads folder to 777. This *shouldn't* be necessary, but it might avert some problems. Be sure to change it back to something more sensible afterward (755 or 744).</li>
		</ol>

		<?php if ( $json_found ) : ?>

			<h3><?php _e( 'Ready for blastoff', 'bp-ning-import' ) ?></h3>

			<p><?php _e( 'The plugin will walk you through each step of the import process. When you click Continue, accounts will be created for members of your Ning network, based on the Ning files you\'ve uploaded. You\'ll have a chance at the end of the import process to send an email to your members informing them of the new site.', 'bp-ning-import' ) ?></p>


			<div class="submit">
				<form method="post" action="">
					<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
					<input type="hidden" id="current_step" name="current_step" value="members">
				</form>
			</div>

		<?php else : ?>

			<h3><?php _e( 'Houston, we have a problem', 'bp-ning-import' ) ?></h3>

			<p>In order to run the importer, you must first use your FTP program to upload the contents of your Ning export into a directory called <code>ning-files</code> in your <code>wp-content</code> directory. The plugin couldn't find a members file at <code><?php echo $json_dir ?>ning-members-local.json</code>, which probably means that you haven't uploaded the files to the right place. Upload your unzipped export to the <code>json</code> directory, and try visiting this page again.</p>

		<?php endif; ?>

	<?php elseif ( $already_imported && !$already_sent ) : ?>

		<h3>Hey!</h3>

		<p>It looks like you've already imported your content, but haven't yet sent out notification emails to your new members.</p>

		<p>If you're ready to send out notifications, click Continue. You'll be taken to a screen where you can customize the content of the notification email before it's sent.</p>

		<p>Or, if you'd like, you can start over with the import process.</p>

		<p>What do you want to do?</p>

		<form method="post" action="">

		<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="blogs_done">
		</div>

		</form>

		<form method="post" action="">

		<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Start Over' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="start_over">
		</div>

		</form>


	<?php else : ?>
		<h3>Hey!</h3>

		<p>It looks like you've already imported your content and sent out notifications to your members.</p>

		<p>If you want to go back to the final screen of the import process, where you can see a list of plugins available for BuddyPress, click Continue.</p>

		<p>Or, if you'd like, you can start over with the import process.</p>

		<p>What do you want to do?</p>

		<form method="post" action="">

		<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="send_email">
		</div>

		</form>

		<form method="post" action="">

		<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Start Over' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="start_over">
		</div>

		</form>


	<?php endif; ?>


<?
}



function bp_ning_import_members_markup() {
	$member_id_array = bp_ning_import_get_members();
?>
	<?php if ( !empty( $member_id_array['success'] ) ) : ?>
		<h3><?php _e( 'Accounts created', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'The following members have either been found in your system, or have had accounts created for them.', 'bp-ning-import' ) ?></p>

		<p><?php _e( 'You will have a chance later on to email new members with their login information.', 'bp-ning-import' ) ?></p>


		<div class="submit">
			<form method="post" action="">
				<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
				<input type="hidden" id="current_step" name="current_step" value="profiles">
			</form>
		</div>

		<table id="ning-import-users">

		<tr>
			<th><?php _e( 'User ID' ) ?></th>
			<th><?php _e( 'Name' ) ?></th>
			<th><?php _e( 'Login' ) ?></th>
			<th><?php _e( 'Email address' ) ?></th>
		</tr>


		<?php foreach( $member_id_array['success'] as $bp_member ) : ?>
			<tr>
				<td><?php echo $bp_member['id'] ?></td>
				<td><?php echo $bp_member['user_name'] ?></td>
				<td><?php echo $bp_member['user_login'] ?></td>
				<td><?php echo $bp_member['user_email'] ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
	<?php else : ?>
		<h3><?php _e( 'No accounts created', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'Sorry, I was unable to create any accounts.', 'bp-ning-import' ) ?></p>

	<?php endif; ?>
<?
}

function bp_ning_import_profiles_markup() {
	$profile_fields = bp_ning_import_get_profile_fields();
?>
	<form method="post" action="">

	<?php if ( !empty( $profile_fields ) ) : ?>
		<h3><?php _e( 'Profile fields', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'The following profile fields were identified in your Ning data. Select the ones you\'d like to keep as BuddyPress profile fields. Your members\' data will be imported automatically.', 'bp-ning-import' ) ?></p>

		<p><?php _e( 'You can also edit or add profile fields later on at Dashboard > BuddyPress > Profile Field Setup.', 'bp-ning-import' ) ?></p>

		<table id="ning-import-profile-fields">

		<tr>
			<th> </th>
			<th><?php _e( 'Original field name' ) ?></th>
			<th><?php _e( 'New field name (optional)' ) ?></th>
		</tr>

		<?php $update = false; ?>
		<?php foreach( $profile_fields as $pf ) : ?>
			<?php if ( xprofile_get_field_id_from_name( $pf ) ) continue; ?>
			<?php $update = true; ?>
			<tr>
				<td> <input type="checkbox" name="pf[]" value="<?php echo $pf ?>" checked> </td>
				<td><?php echo $pf ?></td>
				<td><input type="text" name="pfn[]" /></td>
			</tr>
		<?php endforeach; ?>

		</table>

		<?php if ( !$update ) : ?>
			<p>It looks like all of the profile fields found have already been imported. Click Continue to move on to the next step.</p>
		<?php endif; ?>

	<?php else : ?>
		<h3><?php _e( 'Profile fields', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'No additional profile fields were found.', 'bp-ning-import' ) ?></p>

	<?php endif; ?>

	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="profiles_done" />
	</div>

	</form>

<?
}

function bp_ning_import_profile_two_markup() {
?>
	<h3><?php _e( 'Profile fields', 'bp-ning-import' ) ?></h3>

	<p>Importing your user profile data and profile comments. If the page times out and you don't see a Continue button, hit Refresh.</p>

	<?php bp_ning_import_process_profiles() ?>

	<p><?php _e( 'Profile data successfully imported! Click Continue to continue the import process.', 'bp-ning-import' ) ?></p>

	<div class="submit">
		<form method="post" action="">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="groups" />
		</form>
	</div>

<?
}


function bp_ning_import_groups_markup() {
?>
	<form method="post" action="">

		<h3><?php _e( 'Groups', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'Import from Ning is now importing your Ning groups. Importing groups takes a lot of processing power, so you\'re limited to importing 30 groups at a time. If you\'ve got more than that, you will have to refresh the page in order to get them all.', 'bp-ning-import' ) ?></p>

		<p><?php _e( 'Once you\'ve imported all your groups, click Continue at the bottom of the page to move on to the next step.', 'bp-ning-import' ) ?></p>

	<?php $groups = bp_ning_import_get_groups(); ?>

	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="groups_done" />
	</div>

	</form>

<?
}

function bp_ning_import_discussion_groups_markup() {
?>
	<form method="post" action="">

		<h3><?php _e( 'Discussion groups', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'Import from Ning is now importing your Ning groups. If you\'ve got a lot of groups, you might have to refresh the page in order to get them all. If so, you will see a message near the bottom of the screen.', 'bp-ning-import' ) ?></p>

		<p><?php _e( 'Once you\'ve finished importing groups, click Continue at the bottom of the page to move on to the next step.', 'bp-ning-import' ) ?></p>

	<?php $discussion_groups = bp_ning_import_get_discussion_groups(); ?>

	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="discussion_groups_done" />
	</div>

	</form>

<?
}



function bp_ning_import_discussions_markup() {
?>
	<form method="post" action="">

		<h3><?php _e( 'Discussions', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'Import from Ning is now importing your Ning groups. Importing discussion is hard work. If you\'re importing more than a few, you\'ll probably find that you need to refresh the page several times to get them all. If you see a Refresh message at the bottom of the screen, you\'ll know you need to refresh.', 'bp-ning-import' ) ?></p>



		<p><?php _e( 'When all of your discussions have been imported, a Continue button will appear near the bottom of the screen. Click it to move on to the next step.', 'bp-ning-import' ) ?></p>



		<?php $discussions = bp_ning_import_get_discussions(); ?>

	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="discussions_done" />
	</div>

	</form>

<?
}


function bp_ning_import_blogs_markup() {
?>
	<form method="post" action="">

		<h3><?php _e( 'Blogs and Pages', 'bp-ning-import' ) ?></h3>

		<p><?php _e( 'Import from Ning is looking for blog posts and pages to import.', 'bp-ning-import' ) ?></p>

		<p><?php _e( 'Click Continue at the bottom of the page to wrap up.', 'bp-ning-import' ) ?></p>

	<?php bp_ning_import_get_blogs(); ?>

	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="blogs_done" />
	</div>

	</form>

<?
}


function bp_ning_import_finished_markup() {
	update_option( 'bp_ning_import_finished', 1 );

	$already_sent = get_option( 'bp_ning_emails_sent' );

	$users = get_option( 'bp_ning_import_users' );

	$siteurl = get_option( 'siteurl' );
	$blogname = get_option( 'blogname' );

	$subject = "$blogname has a new home";

	$emailtext = "Dear $blogname member,

We've moved!

The content of our old Ning community has been transferred to $siteurl. The new site is built on cool software called BuddyPress and WordPress, which is free and open source (unlike Ning!).

A user name and password have been automatically created for you:
User name: %USERNAME%
Password: %PASSWORD%

Head over to $siteurl and log in using this information. The first thing you'll want to do after logging in is to change your password to something more memorable. Go to My Account > Settings in the navigation bar at the top of the screen.

Enjoy using the new $blogname!

Sincerely,

The folks at $blogname";

?>

	<?php bp_ning_import_donate_message() ?>

	<h3><?php _e( 'Notify your users', 'bp-ning-import' ) ?></h3>

	<?php if ( $already_sent ) : ?>
		<div id="message" class="updated fade below-h2">
			<p><strong>Hey!</strong> It looks like you've already sent out an email notification to your new members. Click Continue at the bottom of the screen only if you're sure that you want to send it again.</p>
		</div>
	<?php endif; ?>

	<form method="post" action="">

	<p>Your Ning content has been successfully imported into BuddyPress. Now it's time to let your community members know about the new site.</p>

	<p><strong>Don't want to send out an email yet?</strong> Want to take a minute to look around the site first? No problem - just visit the Dashboard > BuddyPress > Import from Ning page at any time in the future. The plugin will remember that you've already imported your content and bring you directly to this screen, so that you can send the notification email to your members.</p>

	<p>In the box below, you'll find some suggested text for the email. Feel free to modify it to suit your needs, but <strong>make sure you include %USERNAME% and %PASSWORD% somewhere in the text</strong>. The plugin will replace <strong>%USERDATA%</strong> and <strong>%PASSWORD%</strong> with the username and password of the recipient. Without this information, new users won't be able to log in.</p>

	<table class="form-table">

	<tr>
		<th scope="row">Subject:</th>
		<td><input type="text" name="email-subject" id="email-subject" size="95" value="<?php echo $subject ?>" /></td>
	</tr>

	<tr>
		<th scope="row">Email text:</th>
		<td><textarea rows=20 cols=60 id="email-text" name="email-text"><?php echo $emailtext ?></textarea></td>
	</tr>

	</table>

	<p>When you click on Continue, emails will be sent to the addresses listed below. <strong>Warning:</strong> Sending thousands of emails at the same time might get you marked as spam by some ISPs. If you've got many hundreds of members, you might consider letting them know manually. They can get their login name and password by using the "Forgot your password?" link on <a href="<?php echo bp_root_domain() ?>/wp-login.php?action=lostpassword">the Lost Password</a> page.</p>


	<div class="submit">
			<input class="button primary-button" type="submit" id='submit' name='submit' value="<?php _e( 'Continue' ) ?>">
			<input type="hidden" id="current_step" name="current_step" value="send_email" />
	</div>

	<ul>
	<?php foreach ( $users['success'] as $user ) : ?>
		<li><?php echo $user['user_name'] . " &middot; " . $user['user_email'] ?></li>
	<?php endforeach; ?>
	</ul>



	</form>


<?php
}


function bp_ning_import_sent_email_markup() {

	if ( !get_option( 'bp_ning_emails_sent' ) ) {

		$users = get_option( 'bp_ning_import_users' );

		$subject = stripslashes( $_POST['email-subject'] );
		$email_text = stripslashes( $_POST['email-text'] );

		foreach ( $users['success'] as $user ) {
			$to = $user['user_email'];
			$message = str_replace( "%USERNAME%", $user['user_login'], $email_text );
			$message = str_replace( "%PASSWORD%", $user['password'], $message );

			wp_mail( $to, $subject, $email_text );
		}

		update_option( 'bp_ning_emails_sent', 1 );

	}

?>
	<h3><?php _e( 'Hooray!', 'bp-ning-import' ) ?></h3>

	<?php bp_ning_import_donate_message() ?>

	<p>Your users have been notified of the new site and their new login info.</p>

	<p>Are you new to BuddyPress? You might want to check out some of the <a href="http://buddypress.org/extend/plugins/">great plugins</a> that can make BuddyPress an even more powerful way to stay connected. A few of the more popular plugins:</p>

	<ul>
		<li><a href="http://wordpress.org/extend/plugins/invite-anyone/">Invite Anyone</a></li>
		<li><a href="http://wordpress.org/extend/plugins/welcome-pack/">Welcome Pack</a></li>
		<li><a href="http://wordpress.org/extend/plugins/bp-groupblog">Group Blogs</a></li>
		<li><a href="http://wordpress.org/extend/plugins/bp-album">Album+</a></li>
		<li><a href="http://wordpress.org/extend/plugins/buddypress-links">BuddyPress Links</a></li>
	</ul>

	<p>And don't forget to stop by the <a href="http://buddypress.org/support">BuddyPress forums</a>, where a community of avid BuddyPress users and developers are always game for a support question or just to chat about what BP can do.</p>

	<p><strong>Enjoy using WordPress and BuddyPress!</strong></p>




<?php
}

function bp_ning_import_donate_message() {
?>

	<div class="donate-nag postbox">

	<h3 class="hndle">Share the love</h3>

	<p><?php _e( "I built this free tool in my spare time, to make the transition to WordPress and BuddyPress easier for you. Did you find it useful? Consider a donation!" ) ?></p>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="10885547">
		<input type="hidden" name="item_name" value="Donation for Import from Ning - Wordpress/BuddyPress plugin" />
		<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
		<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
	</form>

	<p>You can also visit <a href="http://teleogistic.net">my blog</a>, see <a href="http://teleogistic.net/code/buddypress">my BuddyPress plugins</a>, and follow me <a href="http://twitter.com/boonebgorges">on Twitter</a>.</p>

	</div>

<?php
}

?>
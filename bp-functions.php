<?php
/* Import from Ning BP functions */

function bp_ning_import_process_profile_fields( $data ) {
	
	$u_keys = $data['u_keys'];
	$u_data = $data['u_data'];
	
	$is_fields = 0;
	foreach ( $u_keys as $k ) {
		if ( $u_key != 'Name' && $u_key != 'Email' && $u_key != 'Profile Address' && $u_key != 'Date Joined' )
			$is_fields = 1;	
	}
	
	if ( !$is_fields )
		return;
	
?>
	<div class="wrap">
	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
	
	<h3><?php _e( 'Step Three: Import Additional Profile Data to BuddyPress', 'bp-ning-import' ) ?></h3>
	
	<p><?php _e( "Several additional profile fields have been found in the Ning data. If you'd like, these fields can be imported as BuddyPress profile fields.", 'bp-ning-import' ) ?></p>
		
	<form action='<?php echo $_SERVER["REQUEST_URI"]; ?>' method="post">
	<ul>
	<?php foreach ( $u_keys as $k ) : ?>
		<?php if ( $k != 'Name' && $k != 'Profile Address' && $k != 'Date Joined' ) : ?>
			<?php /* Don't need to create a field if it already exists */ ?>
			<?php if ( xprofile_get_field_id_from_name( $u_key ) ) continue; ?>
		
			<li><input type="checkbox" name="fields[]" id="fields[]" value="<?php echo $k ?>"  /> <?php echo $k ?></li>
		
		<?php endif; ?>
	<?php endforeach; ?>
	</ul>
	
	
	<p><?php _e( "Check the fields you'd like to import and click <strong>Continue</strong>. (You can edit these fields later at <strong>Dashboard > BuddyPress > Profile Field Setup</strong>.)", 'bp-ning-import' ) ?></p>
	
	<?php /* Must send the $u_keys as well in order to key data correctly in the final step */ ?>
	<?php foreach( $u_keys as $k ) : ?>
		<input type="hidden" name="u_keys[]" id="u_keys[]" value="<?php echo esc_attr($k) ?>" />
	<?php endforeach; ?>
	
	<?php foreach( $u_data as $user_id => $u ) : ?>
		<input type="hidden" name="u_data[<?php echo $user_id ?>]" id="u_data[<?php echo $user_id ?>]" value="<?php echo $u ?>" />
	<?php endforeach; ?>
	
	<input type="hidden" name="step_three" id="step_three" value="1" />
	
	<div class="submit">
		<input class="button primary-button" type="submit" name="info_update" value="<?php _e( 'Continue', 'bp-ning-import' ); ?> &raquo;" />
	</div>
	
	</form>
	
	</div>
<?php	
}
add_action( 'ning_import_step_two_submit', 'bp_ning_import_process_profile_fields' );

function bp_ning_import_build_profile_fields( $data ) {
	global $bp;
	
	if ( !is_array( $data['fields'] ) )
		return;
	
	//print "<pre>";
	//print_r($data); die();
	
	/* Create the fields */
	$fields = array();
	foreach ( $data['fields'] as $field ) {
		$args = array(
			'field_group_id' => 1,
			'name' => $field,
			'type' => 'textbox',
			'is_required' => false						
			);
		
		if ( !xprofile_get_field_id_from_name( $field ) )
			xprofile_insert_field( $args );
		
		$key = array_search( $field, $data['u_keys'] );
		$fields[$key] = $field;
	}
	
	/* Loop through the user data to populate the fields */
	foreach ( $data['u_data'] as $user_id => $u ) {
		$u = explode( '|', $u );
		
		/* Now loop through each of the fields */
		foreach( $fields as $key => $field ) {
			if ( !xprofile_get_field_data( $field, $user_id ) )
				xprofile_set_field_data( $field, $user_id, $u[$key] );
		}
	}
	
	$name_key = array_search( 'Name', $data['u_keys'] );
	
?>
	<div class="wrap">
	<h2><?php _e( 'Import Users from Ning', 'bp-ning-import' ) ?></h2>
	
	<h3><?php _e( 'Success!', 'bp-ning-import' ) ?></h3>
	
	<p><?php _e( "Success! Your users and their profile data have been successfully imported into BuddyPress.", 'bp-ning-import' ) ?></p>

	<p><?php _e( "Click on the links below to visit the new profiles that have been generated for your users. ", 'bp-ning-import' ) ?></p>
		
	<div class="bp-ning-import-success-users">
		<ul>
		<?php foreach ( $data['u_data'] as $user_id => $u ) : ?>
			<?php $u = explode( '|', $u ) ?>
			<li><a href="<?php echo bp_core_get_user_domain( $user_id ) .  $bp->profile->slug ?>"><?php echo $u[$name_key] ?></a></li>
		
		<?php endforeach; ?>
		</ul>
	</div>
		
	<p><?php _e( "Your users can customize their own profiles by navigating to <strong>My Account > Profile > Edit Profile</strong>. As the administrator, you can manage profile fields at <strong>Dashboard > BuddyPress > Profile Field Setup</strong>", 'bp-ning-import' ) ?></p>
		
	<p><?php _e( "Enjoy using BuddyPress!", 'bp-ning-import' ) ?></p>



<?php
}

?>
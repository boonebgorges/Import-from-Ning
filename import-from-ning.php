<?php
/*
Plugin Name: Import from Ning
Plugin URI: http://github.com/boonebgorges/Import-from-Ning
Description: Import members and content from a Ning network export into BuddyPress
Author: Boone Gorges, Ryan McCue, Bronson Quick
Version: 2.1
Author URI: http://boone.gorg.es
*/

/* Only load BuddyPress functions if BP is active */
function bp_ning_import_bp_init() {
	require( dirname( __FILE__ ) . '/bp-functions.php' );
}
add_action( 'bp_include', 'bp_ning_import_bp_init' );

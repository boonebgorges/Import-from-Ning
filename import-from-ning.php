<?php
/*
Plugin Name: Import from Ning
Plugin URI: http://teleogistic.net
Description: Import members and content from a Ning network export into BuddyPress
Author: Boone Gorges, Ryan McCue
Version: 2.1
Author URI: http://teleogistic.net
*/


/* Only load BuddyPress functions if BP is active */
function bp_ning_import_bp_init() {
	require( dirname( __FILE__ ) . '/bp-functions.php' );
}
add_action( 'bp_include', 'bp_ning_import_bp_init' );

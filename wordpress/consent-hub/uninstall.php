<?php
/**
 * ConsentHub uninstall script.
 * Removes all plugin data from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ch_settings' );
delete_option( 'ch_logging_enabled' );

// Drop consent log table
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';
CH_Database::drop_table();

<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'wbv_changes';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

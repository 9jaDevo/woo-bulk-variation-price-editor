<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WBV_DB {
    public static function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wbv_changes';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_id varchar(36) NOT NULL,
            operation_label varchar(191) DEFAULT NULL,
            variation_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            old_price varchar(64) DEFAULT NULL,
            new_price varchar(64) DEFAULT NULL,
            mode varchar(20) DEFAULT NULL,
            value varchar(64) DEFAULT NULL,
            target varchar(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            reverted tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY operation_id (operation_id),
            KEY variation_id (variation_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

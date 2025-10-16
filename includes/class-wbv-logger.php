<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WBV_Logger {
    /**
     * Log multiple change rows for an operation. Optionally include an operation label.
     *
     * @param string $operation_id
     * @param array  $rows
     * @param string $operation_label
     */
    public static function log_changes( $operation_id, $rows, $operation_label = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';

        foreach ( $rows as $r ) {
            $wpdb->insert( $table, array(
                'operation_id'    => $operation_id,
                'operation_label' => $operation_label,
                'variation_id'    => isset( $r['variation_id'] ) ? (int) $r['variation_id'] : 0,
                'product_id'      => isset( $r['product_id'] ) ? (int) $r['product_id'] : 0,
                'old_price'       => isset( $r['old_price'] ) ? (string) $r['old_price'] : null,
                'new_price'       => isset( $r['new_price'] ) ? (string) $r['new_price'] : null,
                'mode'            => isset( $r['mode'] ) ? (string) $r['mode'] : null,
                'value'           => isset( $r['value'] ) ? (string) $r['value'] : null,
                'target'          => isset( $r['target'] ) ? (string) $r['target'] : null,
                'user_id'         => get_current_user_id(),
            ) );
        }
    }

    /**
     * Get recent operations grouped by operation_id.
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent_operations( $limit = 20 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';

        $sql = $wpdb->prepare(
            "SELECT operation_id, operation_label, user_id, MIN(created_at) as created_at, COUNT(*) as changes, SUM(reverted) as reverted_count FROM {$table} GROUP BY operation_id ORDER BY created_at DESC LIMIT %d",
            $limit
        );

        $rows = $wpdb->get_results( $sql );
        $out = array();
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $out[] = array(
                    'operation_id'    => $r->operation_id,
                    'operation_label' => $r->operation_label,
                    'user_id'         => (int) $r->user_id,
                    'created_at'      => $r->created_at,
                    'changes'         => (int) $r->changes,
                    'is_reverted'     => (int) $r->reverted_count >= (int) $r->changes,
                );
            }
        }

        return $out;
    }

    /**
     * Get all rows for a specific operation id.
     */
    public static function get_operation_rows( $operation_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';

        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE operation_id = %s ORDER BY id ASC", $operation_id );
        return $wpdb->get_results( $sql );
    }

    /**
     * Get a single row for an operation and variation.
     */
    public static function get_operation_row( $operation_id, $variation_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';

        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE operation_id = %s AND variation_id = %d LIMIT 1", $operation_id, $variation_id );
        return $wpdb->get_row( $sql );
    }

    /**
     * Mark a single log row as reverted.
     */
    public static function mark_row_reverted( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';
        return $wpdb->update( $table, array( 'reverted' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
    }

    /**
     * Mark all rows in an operation as reverted.
     */
    public static function mark_operation_reverted( $operation_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wbv_changes';
        return $wpdb->update( $table, array( 'reverted' => 1 ), array( 'operation_id' => $operation_id ), array( '%d' ), array( '%s' ) );
    }
}

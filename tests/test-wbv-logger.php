<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wbv-logger.php';

// Provide a fallback implementation for get_current_user_id used by the logger
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

class WPDB_Stub {
    public $prefix = 'wp_';
    public $insert_calls = array();
    public $get_results_return = array();
    public $get_row_return = null;
    public $update_calls = array();

    public function insert( $table, $data ) {
        $this->insert_calls[] = array( 'table' => $table, 'data' => $data );
        return true;
    }

    public function prepare() {
        $args = func_get_args();
        $format = array_shift( $args );
        // weak prepare: replace %d and %s sequentially
        foreach ( $args as $a ) {
            $format = preg_replace( '/%[ds]/', $a, $format, 1 );
        }
        return $format;
    }

    public function get_results( $sql ) {
        return $this->get_results_return;
    }

    public function get_row( $sql ) {
        return $this->get_row_return;
    }

    public function update( $table, $data, $where ) {
        $this->update_calls[] = compact( 'table', 'data', 'where' );
        return true;
    }
}

class WBV_Logger_Test extends TestCase {
    protected function setUp(): void {
        global $wpdb;
        $wpdb = new WPDB_Stub();
    }

    public function test_log_changes_inserts_rows() {
        global $wpdb;
        $rows = array(
            array( 'variation_id' => 1, 'product_id' => 10, 'old_price' => '5.00', 'new_price' => '6.00', 'mode' => 'amount', 'value' => '1.00', 'target' => 'regular' ),
            array( 'variation_id' => 2, 'product_id' => 11, 'old_price' => '10.00', 'new_price' => '11.00', 'mode' => 'percent', 'value' => '10', 'target' => 'regular' ),
        );

        WBV_Logger::log_changes( 'op_test', $rows, 'Test op' );

        $this->assertCount( 2, $wpdb->insert_calls );
        $first = $wpdb->insert_calls[0]['data'];
        $this->assertEquals( 'op_test', $first['operation_id'] );
        $this->assertEquals( 'Test op', $first['operation_label'] );
        $this->assertEquals( 1, $first['variation_id'] );
    }

    public function test_get_recent_operations_returns_rows() {
        global $wpdb;
        $obj = new stdClass();
        $obj->operation_id = 'op1';
        $obj->operation_label = 'Label';
        $obj->user_id = 1;
        $obj->created_at = '2025-01-01 00:00:00';
        $obj->changes = 2;
        $obj->reverted_count = 0;

        $wpdb->get_results_return = array( $obj );
        $ops = WBV_Logger::get_recent_operations( 10 );
        $this->assertCount( 1, $ops );
        $this->assertEquals( 'op1', $ops[0]['operation_id'] );
    }

    public function test_get_operation_rows_returns_rows() {
        global $wpdb;
        $row = new stdClass();
        $row->id = 123;
        $row->operation_id = 'op1';
        $row->variation_id = 5;
        $row->old_price = '1.00';
        $wpdb->get_results_return = array( $row );

        $rows = WBV_Logger::get_operation_rows( 'op1' );
        $this->assertCount( 1, $rows );
        $this->assertEquals( 5, $rows[0]->variation_id );
    }

    public function test_mark_row_reverted_calls_update() {
        global $wpdb;
        $wpdb->update_calls = array();
        WBV_Logger::mark_row_reverted( 10 );
        $this->assertCount( 1, $wpdb->update_calls );
        $call = $wpdb->update_calls[0];
        $this->assertEquals( 'wp_wbv_changes', $call['table'] );
        $this->assertEquals( array( 'reverted' => 1 ), $call['data'] );
        $this->assertEquals( array( 'id' => 10 ), $call['where'] );
    }
}

<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wbv-processor.php';
require_once __DIR__ . '/../includes/class-wbv-logger.php';

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

if ( ! class_exists( 'WPDB_Stub' ) ) {
    class WPDB_Stub {
        public $prefix = 'wp_';
        public $insert_calls = array();
        public function insert( $table, $data ) {
            $this->insert_calls[] = array( 'table' => $table, 'data' => $data );
            return true;
        }
    }
}

class FakeVariation {
    public $id;
    public $parent_id;
    public $regular_price;
    public $sale_price;

    public function __construct( $id, $parent = 0, $regular = '10.00', $sale = '' ) {
        $this->id = $id;
        $this->parent_id = $parent;
        $this->regular_price = $regular;
        $this->sale_price = $sale;
    }

    public function get_id() { return $this->id; }
    public function get_sku() { return ''; }
    public function get_attributes() { return array(); }
    public function get_parent_id() { return $this->parent_id; }
    public function get_regular_price() { return $this->regular_price; }
    public function get_sale_price() { return $this->sale_price; }
    public function set_regular_price( $p ) { $this->regular_price = $p; }
    public function set_sale_price( $p ) { $this->sale_price = $p; }
    public function save() { return true; }
}

class WBV_Processor_Batch_Test extends TestCase {
    protected function setUp(): void {
        global $wpdb;
        $wpdb = new WPDB_Stub();

        // Provide simple wc_get_product stub
        if ( ! function_exists( 'wc_get_product' ) ) {
            function wc_get_product( $id ) {
                // Provide simple mapping where id 1 => 10.00, id 2 => 20.00
                if ( $id == 1 ) return new FakeVariation( 1, 100, '10.00' );
                if ( $id == 2 ) return new FakeVariation( 2, 100, '20.00' );
                return null;
            }
        }
    }

    public function test_process_batch_updates_and_logs() {
        global $wpdb;

        $args = array(
            'operation_id' => 'op_batch_test',
            'items' => array( 1, 2 ),
            'mode' => 'amount',
            'value' => 2.5,
            'target' => 'regular',
            'label' => 'Batch test',
        );

        $res = WBV_Processor::process_batch( $args );
        $this->assertTrue( $res );

        // Ensure logger recorded two inserts
        $this->assertCount( 2, $wpdb->insert_calls );
        $first = $wpdb->insert_calls[0]['data'];
        $this->assertEquals( 'op_batch_test', $first['operation_id'] );
        $this->assertEquals( '12.50', $first['new_price'] );
    }
}

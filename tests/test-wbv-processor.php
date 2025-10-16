<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wbv-processor.php';

class WBV_Processor_Test extends TestCase {
    public function test_compute_new_price_amount() {
        $res = WBV_Processor::compute_new_price( '10.00', 'amount', 2.50 );
        $this->assertEquals( 12.5, floatval( $res ) );
    }

    public function test_compute_new_price_percent() {
        $res = WBV_Processor::compute_new_price( '10.00', 'percent', 10 );
        $this->assertEquals( 11.0, floatval( $res ) );
    }
}

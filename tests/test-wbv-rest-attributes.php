<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/api/class-wbv-rest-controller.php';

// Provide minimal WP helper function stubs used by the controller helper
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $s ) { return is_string($s) ? $s : $s; }
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
    function taxonomy_exists( $tax ) {
        // simple whitelist for our tests
        return in_array( $tax, array( 'pa_color', 'pa_size', 'pa_material' ), true );
    }
}

if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
    function wc_attribute_taxonomy_name( $name ) {
        // naive mapping (attribute_name -> pa_{name})
        return 'pa_' . $name;
    }
}

// Global test fixtures used by WP_Query stub
global $wbv_test_variation_map, $wbv_test_product_term_map, $wbv_test_post_parents;
$wbv_test_variation_map = array();
$wbv_test_product_term_map = array();
$wbv_test_post_parents = array();

// Provide basic get_term_by and get_terms helpers
if ( ! function_exists( 'get_term_by' ) ) {
    function get_term_by( $field, $value, $taxonomy ) {
        global $wbv_test_product_term_map;
        if ( $field === 'slug' && isset( $wbv_test_product_term_map[ $taxonomy ][ $value ] ) ) {
            $t = new stdClass();
            $t->slug = $value;
            $t->term_id = crc32( $taxonomy . ':' . $value );
            $t->name = ucfirst( $value );
            return $t;
        }
        return false;
    }
}

if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) {
        global $wbv_test_product_term_map;
        $tax = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';
        $search = isset( $args['search'] ) ? $args['search'] : '';
        $out = array();
        if ( isset( $wbv_test_product_term_map[ $tax ] ) && $search ) {
            foreach ( $wbv_test_product_term_map[ $tax ] as $slug => $pids ) {
                if ( stripos( $slug, strtolower( $search ) ) !== false || stripos( $slug, strtolower( $search ) ) !== false ) {
                    $t = new stdClass();
                    $t->slug = $slug;
                    $t->name = ucfirst( $slug );
                    $out[] = $t;
                }
            }
        }
        return $out;
    }
}

// Provide get_post stub
if ( ! function_exists( 'get_post' ) ) {
    function get_post( $id ) {
        global $wbv_test_post_parents;
        if ( isset( $wbv_test_post_parents[ $id ] ) ) {
            $o = new stdClass();
            $o->ID = $id;
            $o->post_parent = $wbv_test_post_parents[ $id ];
            return $o;
        }
        return null;
    }
}

// Simple WP_Query stub to satisfy the helper's expectations
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = array();
        public $found_posts = 0;
        public function __construct( $args = array() ) {
            global $wbv_test_variation_map, $wbv_test_product_term_map;
            if ( isset( $args['post_type'] ) && $args['post_type'] === 'product_variation' && isset( $args['meta_query'][0] ) ) {
                $meta = $args['meta_query'][0];
                $key = isset( $meta['key'] ) ? $meta['key'] : '';
                $tax = str_replace( 'attribute_', '', $key );
                $values = isset( $meta['value'] ) ? (array) $meta['value'] : array();
                foreach ( $values as $v ) {
                    if ( isset( $wbv_test_variation_map[ $tax ][ $v ] ) ) {
                        $this->posts = array_merge( $this->posts, $wbv_test_variation_map[ $tax ][ $v ] );
                    }
                }
                $this->posts = array_values( array_unique( $this->posts ) );
                $this->found_posts = count( $this->posts );
                return;
            }

            if ( isset( $args['post_type'] ) && $args['post_type'] === 'product' && isset( $args['tax_query'][0] ) ) {
                $tax = $args['tax_query'][0]['taxonomy'];
                $terms = (array) $args['tax_query'][0]['terms'];
                foreach ( $terms as $t ) {
                    if ( isset( $wbv_test_product_term_map[ $tax ][ $t ] ) ) {
                        $this->posts = array_merge( $this->posts, $wbv_test_product_term_map[ $tax ][ $t ] );
                    }
                }
                $this->posts = array_values( array_unique( $this->posts ) );
                $this->found_posts = count( $this->posts );
                return;
            }
        }
    }
}

class WBV_REST_Controller_Attributes_Test extends TestCase {
    protected function setUp(): void {
        global $wbv_test_variation_map, $wbv_test_product_term_map, $wbv_test_post_parents;
        // map taxonomy -> slug -> variation ids
        $wbv_test_variation_map = array(
            'pa_color' => array( 'red' => array( 201 ), 'blue' => array( 202 ) ),
            'pa_size'  => array( 'large' => array( 301 ) ),
        );

        // map taxonomy -> slug -> product ids
        $wbv_test_product_term_map = array(
            'pa_color' => array( 'red' => array( 100 ), 'blue' => array( 101 ) ),
            'pa_size'  => array( 'large' => array( 100 ) ),
        );

        // variation -> parent product
        $wbv_test_post_parents = array( 201 => 100, 202 => 101, 301 => 100 );
    }

    public function test_and_operator_intersection() {
        $pairs = array( 'pa_color|red', 'pa_size|large' );
        $out = WBV_REST_Controller::resolve_product_ids_for_attribute_pairs( $pairs, 'and' );
        sort( $out );
        $this->assertEquals( array( 100 ), $out );
    }

    public function test_or_operator_union() {
        $pairs = array( 'pa_color|red', 'pa_color|blue' );
        $out = WBV_REST_Controller::resolve_product_ids_for_attribute_pairs( $pairs, 'or' );
        sort( $out );
        $this->assertEquals( array( 100, 101 ), $out );
    }

    public function test_name_based_matching_resolves_to_slugs() {
        // Provide a value that is a name rather than slug (capitalized)
        $pairs = array( 'pa_color|Red' );
        $out = WBV_REST_Controller::resolve_product_ids_for_attribute_pairs( $pairs, 'or' );
        sort( $out );
        $this->assertEquals( array( 100 ), $out );
    }
}

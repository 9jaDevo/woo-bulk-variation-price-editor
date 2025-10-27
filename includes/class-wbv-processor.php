<?php
if (! defined('ABSPATH')) {
    exit;
}

class WBV_Processor
{
    /**
     * Compute a new price based on mode and value.
     * Modes: 'fixed' (set exact value), 'amount' (add/subtract), or 'percent' (percentage change)
     */
    public static function compute_new_price($old_price, $mode, $value)
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        $old = floatval($old_price);

        if ($mode === 'fixed') {
            // Set exact price - replace old price with the value
            $new = floatval($value);
        } elseif ($mode === 'amount') {
            // Add or subtract amount
            $new = $old + floatval($value);
        } else { // percent
            // Percentage increase/decrease
            $new = $old * (1 + floatval($value) / 100);
        }

        if (function_exists('wc_format_decimal')) {
            return wc_format_decimal($new, $decimals);
        }

        return number_format($new, $decimals, '.', '');
    }

    public static function update_variation_price($variation_id, $target, $new_price)
    {
        if (! function_exists('wc_get_product')) {
            return new WP_Error('no_woocommerce', 'WooCommerce is not available');
        }

        $variation = wc_get_product($variation_id);
        if (! $variation) {
            return new WP_Error('invalid_variation', 'Variation not found', array('variation_id' => $variation_id));
        }

        // Ensure new_price is a valid numeric value
        $new_price = floatval($new_price);

        // Format the price properly
        if (function_exists('wc_format_decimal')) {
            $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
            $new_price = wc_format_decimal($new_price, $decimals);
        }

        if ($target === 'regular') {
            $variation->set_regular_price($new_price);
        } else {
            $variation->set_sale_price($new_price);
        }

        $variation->save();

        return true;
    }

    /**
     * Process a batch of variation ids. Expected $args array with keys:
     * - operation_id, items (array of variation ids), mode, value, target, user_id
     */
    public static function process_batch($args)
    {
        // ActionScheduler may pass args as a flat list or as a single array
        if (func_num_args() > 1) {
            $all = func_get_args();
            $args = is_array($all[0]) ? $all[0] : $args;
        }

        if (! is_array($args)) {
            return false;
        }

        $operation_id = isset($args['operation_id']) ? $args['operation_id'] : (isset($args[0]['operation_id']) ? $args[0]['operation_id'] : '');
        $items = isset($args['items']) ? $args['items'] : (isset($args[0]['items']) ? $args[0]['items'] : array());
        $mode = isset($args['mode']) ? $args['mode'] : 'percent';
        $value = isset($args['value']) ? $args['value'] : 0;
        $target = isset($args['target']) ? $args['target'] : 'regular';

        if (empty($items) || ! is_array($items)) {
            return false;
        }

        $rows_to_log = array();

        foreach ($items as $vid) {
            $vid = absint($vid);
            if (! $vid) {
                continue;
            }

            $variation = wc_get_product($vid);
            if (! $variation) {
                continue;
            }

            $old = $target === 'regular' ? $variation->get_regular_price() : $variation->get_sale_price();
            $new = self::compute_new_price($old, $mode, $value);

            do_action('wbv_before_price_change', $vid, $old, $new, array('operation_id' => $operation_id));

            $res = self::update_variation_price($vid, $target, $new);
            if (is_wp_error($res)) {
                // Log error for debugging
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->error(sprintf('WBV failed update: variation %d, error: %s', $vid, $res->get_error_message()), array('source' => 'wbvpricer'));
                } else {
                    error_log(sprintf('WBV failed update: variation %d, error: %s', $vid, $res->get_error_message()));
                }

                // Collect error but continue
                $rows_to_log[] = array('variation_id' => $vid, 'product_id' => $variation->get_parent_id(), 'old_price' => $old, 'new_price' => null, 'mode' => $mode, 'value' => $value, 'target' => $target);
                continue;
            }

            do_action('wbv_after_price_change', $vid, $old, $new, array('operation_id' => $operation_id));

            $rows_to_log[] = array('variation_id' => $vid, 'product_id' => $variation->get_parent_id(), 'old_price' => $old, 'new_price' => $new, 'mode' => $mode, 'value' => $value, 'target' => $target);
        }

        if (! empty($rows_to_log)) {
            $label = isset($args['label']) ? $args['label'] : '';
            WBV_Logger::log_changes($operation_id, $rows_to_log, $label);
        }

        return true;
    }

    /**
     * Process a revert batch for an existing operation.
     * Expected args: operation_id (original op), operation_id_revert (new op id), items (array of variation ids), label
     */
    public static function process_revert_batch($args)
    {
        if (func_num_args() > 1) {
            $all = func_get_args();
            $args = is_array($all[0]) ? $all[0] : $args;
        }

        if (! is_array($args)) {
            return false;
        }

        $orig_operation_id = isset($args['operation_id']) ? $args['operation_id'] : (isset($args[0]['operation_id']) ? $args[0]['operation_id'] : '');
        $revert_operation_id = isset($args['revert_operation_id']) ? $args['revert_operation_id'] : (isset($args[0]['revert_operation_id']) ? $args[0]['revert_operation_id'] : '');
        $items = isset($args['items']) ? $args['items'] : (isset($args[0]['items']) ? $args[0]['items'] : array());
        $label = isset($args['label']) ? $args['label'] : '';

        if (empty($orig_operation_id) || empty($revert_operation_id)) {
            return false;
        }

        if (empty($items) || ! is_array($items)) {
            // If no items provided, try to load all rows for original operation
            $rows = WBV_Logger::get_operation_rows($orig_operation_id);
        } else {
            // Build rows from original operation for provided items
            $rows = array();
            foreach ($items as $vid) {
                $r = WBV_Logger::get_operation_row($orig_operation_id, $vid);
                if ($r) {
                    $rows[] = $r;
                }
            }
        }

        if (empty($rows)) {
            return false;
        }

        $rows_to_log = array();

        foreach ($rows as $r) {
            $vid = intval($r->variation_id);
            $variation = wc_get_product($vid);
            if (! $variation) {
                continue;
            }

            // Current price before revert
            $current = $r->target === 'regular' ? $variation->get_regular_price() : $variation->get_sale_price();
            $old_price = $r->old_price; // value to revert to

            do_action('wbv_before_price_change', $vid, $current, $old_price, array('operation_id' => $revert_operation_id));

            $res = self::update_variation_price($vid, $r->target, $old_price);
            if (is_wp_error($res)) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->error(sprintf('WBV failed revert: variation %d, error: %s', $vid, $res->get_error_message()), array('source' => 'wbvpricer'));
                } else {
                    error_log(sprintf('WBV failed revert: variation %d, error: %s', $vid, $res->get_error_message()));
                }

                // log failed revert row with new_price = null
                $rows_to_log[] = array('variation_id' => $vid, 'product_id' => $r->product_id, 'old_price' => $current, 'new_price' => null, 'mode' => 'revert', 'value' => 0, 'target' => $r->target);
                continue;
            }

            do_action('wbv_after_price_change', $vid, $current, $old_price, array('operation_id' => $revert_operation_id));

            // Log revert row (old_price = previous current, new_price = restored old_price)
            $rows_to_log[] = array('variation_id' => $vid, 'product_id' => $r->product_id, 'old_price' => $current, 'new_price' => $old_price, 'mode' => 'revert', 'value' => 0, 'target' => $r->target);

            // Mark original row as reverted
            if (isset($r->id)) {
                WBV_Logger::mark_row_reverted($r->id);
            }
        }

        if (! empty($rows_to_log)) {
            WBV_Logger::log_changes($revert_operation_id, $rows_to_log, $label);
        }

        // Optionally mark original operation as reverted if all rows were reverted
        // This is a simple approximation; callers may call WBV_Logger::mark_operation_reverted()
        return true;
    }

    /**
     * Set default attributes for a variable product.
     * 
     * @param int $product_id Product ID
     * @param array $default_attributes Array of attribute_name => value pairs
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function set_default_attributes($product_id, $default_attributes)
    {
        if (! function_exists('wc_get_product')) {
            return new WP_Error('no_woocommerce', 'WooCommerce is not available');
        }

        $product = wc_get_product($product_id);
        if (! $product) {
            return new WP_Error('invalid_product', 'Product not found', array('product_id' => $product_id));
        }

        if ($product->get_type() !== 'variable') {
            return new WP_Error('not_variable', 'Product is not a variable product', array('product_id' => $product_id));
        }

        // Validate that the attributes exist for this product
        $product_attributes = $product->get_attributes();
        $validated_defaults = array();

        foreach ($default_attributes as $attr_key => $attr_value) {
            // Check if this attribute exists on the product
            $found = false;
            foreach ($product_attributes as $attribute) {
                $attr_name = $attribute->get_name();
                if ($attr_name === $attr_key || 'pa_' . $attr_name === $attr_key) {
                    $found = true;

                    // For taxonomy-based attributes, validate the term exists
                    if ($attribute->is_taxonomy()) {
                        $taxonomy = $attr_name;
                        if (strpos($taxonomy, 'pa_') !== 0) {
                            $taxonomy = 'pa_' . $taxonomy;
                        }

                        $term = get_term_by('slug', $attr_value, $taxonomy);
                        if (! $term) {
                            // Try by name
                            $term = get_term_by('name', $attr_value, $taxonomy);
                        }

                        if ($term) {
                            $validated_defaults[$attr_key] = $term->slug;
                        }
                    } else {
                        // Custom attribute - just use the value as-is
                        $validated_defaults[$attr_key] = $attr_value;
                    }
                    break;
                }
            }
        }

        // Set the default attributes
        $product->set_default_attributes($validated_defaults);
        $product->save();

        return true;
    }

    /**
     * Process a batch of default attribute updates.
     * Optimized for 4GB RAM VPS - processes in chunks with memory management.
     * Expected $args array with keys:
     * - operation_id, products (array of product_id => defaults), operation_label, user_id
     */
    public static function process_defaults_batch($args)
    {
        // ActionScheduler may pass args as a flat list or as a single array
        if (func_num_args() > 1) {
            $all = func_get_args();
            $args = is_array($all[0]) ? $all[0] : $args;
        }

        if (! is_array($args)) {
            return false;
        }

        $operation_id = isset($args['operation_id']) ? $args['operation_id'] : '';
        $products = isset($args['products']) ? $args['products'] : array();
        $operation_label = isset($args['operation_label']) ? $args['operation_label'] : '';

        if (empty($products) || ! is_array($products)) {
            return false;
        }

        $processed_count = 0;

        foreach ($products as $pid => $defaults) {
            $pid = absint($pid);
            if (! $pid || empty($defaults)) {
                continue;
            }

            $product = wc_get_product($pid);
            if (! $product) {
                continue;
            }

            $old_defaults = $product->get_default_attributes();

            do_action('wbv_before_default_change', $pid, $old_defaults, $defaults, array('operation_id' => $operation_id));

            $res = self::set_default_attributes($pid, $defaults);

            if (is_wp_error($res)) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->error(sprintf('WBV batch default update failed: product %d, error: %s', $pid, $res->get_error_message()), array('source' => 'wbvpricer'));
                } else {
                    error_log(sprintf('WBV batch default update failed: product %d, error: %s', $pid, $res->get_error_message()));
                }
                continue;
            }

            do_action('wbv_after_default_change', $pid, $old_defaults, $defaults, array('operation_id' => $operation_id));

            // Log the change
            WBV_Logger::log_default_change($operation_id, $pid, $old_defaults, $defaults, $operation_label);

            $processed_count++;

            // Clear object cache every 50 products to prevent memory buildup on 4GB VPS
            if ($processed_count % 50 === 0 && function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }

        return true;
    }
}

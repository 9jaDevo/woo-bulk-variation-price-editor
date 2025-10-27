<?php

/**
 * Plugin Name: Woo Bulk Variation Pricer
 * Plugin URI:  https://example.com/
 * Description: Search products and bulk-increase variation prices (preview, background processing and undo support).
 * Version:     0.1.0
 * Author:      Michael Akinwumi
 * Author URI:  https://michaelakinwumi.com
 * Text Domain: woo-bulk-variation-pricer
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WBVPRICER_VERSION', '0.1.2');
define('WBVPRICER_PLUGIN_FILE', __FILE__);
define('WBVPRICER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WBVPRICER_PLUGIN_URL', plugin_dir_url(__FILE__));

/*
 * Load core classes
 */
require_once WBVPRICER_PLUGIN_DIR . 'includes/class-wbv-db.php';
require_once WBVPRICER_PLUGIN_DIR . 'includes/class-wbv-logger.php';
require_once WBVPRICER_PLUGIN_DIR . 'includes/class-wbv-processor.php';
require_once WBVPRICER_PLUGIN_DIR . 'includes/api/class-wbv-rest-controller.php';
require_once WBVPRICER_PLUGIN_DIR . 'includes/class-wbv-admin.php';

/**
 * Activation: create DB tables
 */
function wbvpricer_activate()
{
    WBV_DB::install();
    // Default settings
    if (get_option('wbv_chunk_size') === false) {
        update_option('wbv_chunk_size', 100);
    }

    if (get_option('wbv_use_action_scheduler') === false) {
        update_option('wbv_use_action_scheduler', true);
    }
}

/**
 * Deactivation: placeholder for cleanup/schedules
 */
function wbvpricer_deactivate()
{
    // unschedule background jobs if ActionScheduler is available
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('wbv_run_batch_update');
        as_unschedule_all_actions('wbv_run_revert_batch');
        as_unschedule_all_actions('wbv_run_defaults_batch');
    }
}

register_activation_hook(WBVPRICER_PLUGIN_FILE, 'wbvpricer_activate');
register_deactivation_hook(WBVPRICER_PLUGIN_FILE, 'wbvpricer_deactivate');

/* Bootstrap on plugins_loaded to allow other plugins (WooCommerce) to load first */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('woo-bulk-variation-pricer', false, dirname(plugin_basename(WBVPRICER_PLUGIN_FILE)) . '/languages');

    // Require WooCommerce for runtime features. If it's not active, show admin notice and do not initialize runtime hooks.
    if (! class_exists('WooCommerce')) {
        if (is_admin()) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Woo Bulk Variation Pricer requires WooCommerce to be active.', 'woo-bulk-variation-pricer') . '</p></div>';
            });
        }
        return;
    }

    if (is_admin()) {
        WBV_Admin::init();
    }

    add_action('rest_api_init', array('WBV_REST_Controller', 'register_routes'));

    // Hook processor to ActionScheduler / custom action so queued batches are handled
    add_action('wbv_run_batch_update', array('WBV_Processor', 'process_batch'));
    add_action('wbv_run_revert_batch', array('WBV_Processor', 'process_revert_batch'));
    add_action('wbv_run_defaults_batch', array('WBV_Processor', 'process_defaults_batch'));
});

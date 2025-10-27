<?php
if (! defined('ABSPATH')) {
    exit;
}

class WBV_Admin
{
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function register_settings()
    {
        register_setting('wbv_pricer', 'wbv_chunk_size', array('sanitize_callback' => 'absint', 'default' => 100));
        register_setting('wbv_pricer', 'wbv_use_action_scheduler', array('sanitize_callback' => 'boolval', 'default' => true));
    }

    public static function register_admin_page()
    {
        // Submenu under Products
        add_submenu_page(
            'edit.php?post_type=product',
            __('Bulk Variation Price Editor', 'woo-bulk-variation-pricer'),
            __('Bulk Variation Price Editor', 'woo-bulk-variation-pricer'),
            'manage_woocommerce',
            'wbv-pricer',
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function render_admin_page()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bulk Variation Price Editor', 'woo-bulk-variation-pricer'); ?></h1>
            <p><?php echo esc_html__('Search products by title or SKU and manage variation prices in bulk.', 'woo-bulk-variation-pricer'); ?></p>
            <?php if (get_option('wbv_use_action_scheduler', true) && ! (function_exists('as_enqueue_async_action') || function_exists('as_schedule_single_action'))) : ?>
                <div class="notice notice-warning inline">
                    <p><?php echo esc_html__('ActionScheduler is not available. Background processing of large jobs will be disabled. Ensure WooCommerce (which includes ActionScheduler) is active or disable background processing in the settings.', 'woo-bulk-variation-pricer'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Mode Tabs -->
            <div id="wbv-mode-tabs" class="nav-tab-wrapper" style="margin-bottom: 1rem;">
                <a href="#" class="nav-tab nav-tab-active" data-mode="prices" id="wbv-tab-prices"><?php echo esc_html__('Price Editor', 'woo-bulk-variation-pricer'); ?></a>
                <a href="#" class="nav-tab" data-mode="defaults" id="wbv-tab-defaults"><?php echo esc_html__('Default Attributes', 'woo-bulk-variation-pricer'); ?></a>
            </div>

            <div id="wbv-app">
                <!-- Instructions for Default Attributes Mode -->
                <div id="wbv-defaults-instructions" style="display:none; margin-bottom:1rem; padding:15px; background:#e7f3ff; border-left:4px solid #2271b1; border-radius:4px;">
                    <h3 style="margin-top:0; color:#2271b1;">
                        <span class="dashicons dashicons-info" style="font-size:20px; vertical-align:middle;"></span>
                        <?php echo esc_html__('How to Set Default Attributes', 'woo-bulk-variation-pricer'); ?>
                    </h3>
                    <ol style="margin:10px 0; padding-left:20px;">
                        <li><strong><?php echo esc_html__('Search for products', 'woo-bulk-variation-pricer'); ?></strong> - <?php echo esc_html__('Use the search box below to find variable products', 'woo-bulk-variation-pricer'); ?></li>
                        <li><strong><?php echo esc_html__('Select products', 'woo-bulk-variation-pricer'); ?></strong> - <?php echo esc_html__('Check the boxes next to products you want to update', 'woo-bulk-variation-pricer'); ?></li>
                        <li><strong><?php echo esc_html__('Choose defaults', 'woo-bulk-variation-pricer'); ?></strong> - <?php echo esc_html__('A panel will appear where you can select default attribute values', 'woo-bulk-variation-pricer'); ?></li>
                        <li><strong><?php echo esc_html__('Preview & Apply', 'woo-bulk-variation-pricer'); ?></strong> - <?php echo esc_html__('Preview changes, then click "Apply Changes" to update all selected products', 'woo-bulk-variation-pricer'); ?></li>
                    </ol>
                    <p style="margin-bottom:0; color:#135e96;">
                        <span class="dashicons dashicons-lightbulb" style="vertical-align:middle;"></span>
                        <strong><?php echo esc_html__('Tip:', 'woo-bulk-variation-pricer'); ?></strong>
                        <?php echo esc_html__('Default attributes control which variation is pre-selected when customers view the product page.', 'woo-bulk-variation-pricer'); ?>
                    </p>
                </div>

                <div id="wbv-toolbar">
                    <label style="margin-right:1rem;"><input id="wbv-select-all-visible" type="checkbox" /> <?php echo esc_html__('Select all visible', 'woo-bulk-variation-pricer'); ?></label>
                    <input id="wbv-search" type="search" placeholder="<?php echo esc_attr__('Search products or SKU', 'woo-bulk-variation-pricer'); ?>" style="width:24%;" />
                    <select id="wbv-attribute" multiple style="margin-left:.5rem; min-width:160px; max-width:220px;" size="3">
                        <option value="" disabled><?php echo esc_html__('All attributes', 'woo-bulk-variation-pricer'); ?></option>
                    </select>
                    <select id="wbv-attribute-value" multiple style="margin-left:.25rem; min-width:220px; max-width:320px;" disabled size="3">
                        <option value="" disabled><?php echo esc_html__('All values', 'woo-bulk-variation-pricer'); ?></option>
                    </select>
                    <input id="wbv-attribute-value-text" type="text" placeholder="<?php echo esc_attr__('Or type value text to match term names (optional)', 'woo-bulk-variation-pricer'); ?>" style="margin-left:.25rem; min-width:220px; max-width:320px;" disabled />
                    <div id="wbv-attribute-error" role="status" aria-live="polite" style="display:inline-block; margin-left:.5rem;"></div>
                    <select id="wbv-attribute-op" style="margin-left:.25rem;">
                        <option value="and"><?php echo esc_html__('Match all attributes (AND)', 'woo-bulk-variation-pricer'); ?></option>
                        <option value="or"><?php echo esc_html__('Match any attribute (OR)', 'woo-bulk-variation-pricer'); ?></option>
                    </select>
                    <div id="wbv-active-filters" style="display:inline-block; margin-left:1rem;"></div>
                    <select id="wbv-per-page">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="9999"><?php echo esc_html__('All', 'woo-bulk-variation-pricer'); ?></option>
                    </select>
                    <button id="wbv-search-btn" class="button button-primary"><?php echo esc_html__('Search', 'woo-bulk-variation-pricer'); ?></button>

                    <div id="wbv-price-controls" style="display:inline-block; margin-left:1rem;">
                        <label for="wbv-mode"><?php echo esc_html__('Mode', 'woo-bulk-variation-pricer'); ?>:</label>
                        <select id="wbv-mode">
                            <option value="percent"><?php echo esc_html__('Percent (+/-)', 'woo-bulk-variation-pricer'); ?></option>
                            <option value="amount"><?php echo esc_html__('Amount (+/-)', 'woo-bulk-variation-pricer'); ?></option>
                            <option value="fixed"><?php echo esc_html__('Fixed Price', 'woo-bulk-variation-pricer'); ?></option>
                        </select>

                        <label for="wbv-value" style="margin-left:.5rem;"><?php echo esc_html__('Value', 'woo-bulk-variation-pricer'); ?>:</label>
                        <input id="wbv-value" type="number" step="0.01" style="width:8rem;" />

                        <label for="wbv-target" style="margin-left:.5rem;"><?php echo esc_html__('Target', 'woo-bulk-variation-pricer'); ?>:</label>
                        <select id="wbv-target">
                            <option value="regular"><?php echo esc_html__('Regular price', 'woo-bulk-variation-pricer'); ?></option>
                            <option value="sale"><?php echo esc_html__('Sale price', 'woo-bulk-variation-pricer'); ?></option>
                        </select>

                        <input id="wbv-operation-label" type="text" placeholder="<?php echo esc_attr__('Operation label (optional)', 'woo-bulk-variation-pricer'); ?>" style="margin-left:.5rem; width:18rem;" />
                        <button id="wbv-preview-btn" class="button"><?php echo esc_html__('Preview', 'woo-bulk-variation-pricer'); ?></button>
                        <button id="wbv-apply-btn" class="button button-primary"><?php echo esc_html__('Apply Changes', 'woo-bulk-variation-pricer'); ?></button>
                        <button id="wbv-export-csv" class="button" style="margin-left:.5rem;"><?php echo esc_html__('Export CSV', 'woo-bulk-variation-pricer'); ?></button>
                    </div>
                </div>

                <!-- Default Attributes Controls (hidden by default) -->
                <div id="wbv-defaults-controls" style="display:none; margin-top:1rem;">
                    <div style="margin-bottom:1rem;">
                        <p><?php echo esc_html__('Select products below and set their default attribute values. This controls which variation is pre-selected when customers view the product page.', 'woo-bulk-variation-pricer'); ?></p>
                    </div>
                    <div id="wbv-defaults-selector" style="display:none; margin-bottom:1rem; padding:1rem; background:#f9f9f9; border:1px solid #ddd;">
                        <h3><?php echo esc_html__('Set Default Attributes', 'woo-bulk-variation-pricer'); ?></h3>
                        <p><?php echo esc_html__('Choose default values for selected products:', 'woo-bulk-variation-pricer'); ?></p>
                        <div id="wbv-defaults-attributes"></div>
                        <div style="margin-top:1rem;">
                            <input id="wbv-defaults-operation-label" type="text" placeholder="<?php echo esc_attr__('Operation label (optional)', 'woo-bulk-variation-pricer'); ?>" style="width:18rem;" />
                            <button id="wbv-defaults-preview-btn" class="button"><?php echo esc_html__('Preview', 'woo-bulk-variation-pricer'); ?></button>
                            <button id="wbv-defaults-apply-btn" class="button button-primary"><?php echo esc_html__('Apply Changes', 'woo-bulk-variation-pricer'); ?></button>
                        </div>
                    </div>
                </div>

                <div id="wbv-results"></div>
                <div id="wbv-preview" style="margin-top:1rem;"></div>

                <h2 style="margin-top:2rem;"><?php echo esc_html__('Settings', 'woo-bulk-variation-pricer'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('wbv_pricer'); ?>
                    <?php do_settings_sections('wbv_pricer'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wbv_chunk_size"><?php echo esc_html__('Chunk size', 'woo-bulk-variation-pricer'); ?></label></th>
                            <td><input name="wbv_chunk_size" id="wbv_chunk_size" type="number" value="<?php echo esc_attr(get_option('wbv_chunk_size', 100)); ?>" class="small-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Use ActionScheduler', 'woo-bulk-variation-pricer'); ?></th>
                            <td><label><input name="wbv_use_action_scheduler" type="checkbox" value="1" <?php checked(get_option('wbv_use_action_scheduler', true)); ?> /> <?php echo esc_html__('Enable background processing (requires ActionScheduler bundled with WooCommerce)', 'woo-bulk-variation-pricer'); ?></label></td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>

                <h2 style="margin-top:2rem;"><?php echo esc_html__('Recent Operations', 'woo-bulk-variation-pricer'); ?></h2>
                <div id="wbv-operations"></div>
                <div id="wbv-operation-rows" style="margin-top:1rem;"></div>
            </div>
        </div>
<?php
    }

    public static function enqueue_assets($hook)
    {
        // Only load assets on our plugin admin page (submenu under Products)
        if ($hook !== 'product_page_wbv-pricer') {
            return;
        }

        wp_enqueue_style('wbv-admin', WBVPRICER_PLUGIN_URL . 'assets/css/admin.css', array(), WBVPRICER_VERSION);
        wp_enqueue_script('wbv-admin', WBVPRICER_PLUGIN_URL . 'assets/js/admin.js', array(), WBVPRICER_VERSION, true);

        wp_localize_script('wbv-admin', 'wbvPricer', array(
            'nonce'     => wp_create_nonce('wp_rest'),
            'rest_root' => esc_url_raw(rest_url('wbvpricer/v1')),
            'i18n' => array(
                'noSelection' => __('No variations selected', 'woo-bulk-variation-pricer'),
                'searching' => __('Searching…', 'woo-bulk-variation-pricer'),
                'updateScheduled' => __('Update scheduled', 'woo-bulk-variation-pricer'),
                'updatedVariations' => __('Updated variations', 'woo-bulk-variation-pricer'),
                'revertScheduled' => __('Revert scheduled', 'woo-bulk-variation-pricer'),
                'revertCompleted' => __('Revert completed', 'woo-bulk-variation-pricer'),
                'noProducts' => __('No products found', 'woo-bulk-variation-pricer'),
                'noPreview' => __('No preview', 'woo-bulk-variation-pricer'),
                'previewTitle' => __('Preview', 'woo-bulk-variation-pricer'),
                'selectAllVisible' => __('Select all visible', 'woo-bulk-variation-pricer'),
                'attributesFetchFailed' => __('Could not load attribute definitions from the server. The UI will fall back to attributes detected in the current search results.', 'woo-bulk-variation-pricer'),
                'attributesFetchForbidden' => __('Attributes cannot be loaded due to insufficient permissions (HTTP 403). Ensure your account has the required capability (manage_woocommerce).', 'woo-bulk-variation-pricer'),
                'attributesFetchServerError' => __('Server error while fetching attributes (HTTP %d). Falling back to product-derived values.', 'woo-bulk-variation-pricer'),
                'attributesFetchRetry' => __('Retry', 'woo-bulk-variation-pricer'),
            ),
        ));
    }
}

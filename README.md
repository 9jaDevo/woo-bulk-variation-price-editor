# Woo Bulk Variation Price Editor

A lightweight WordPress plugin for WooCommerce that lets administrators search products, list all variations in the search results, and increase variation prices in bulk (by fixed amount or by percentage) with preview, dry-run and undo support.

## Key features

- Search products by title, SKU, category and attributes.
- View each product's variations directly in the search results (attributes and current prices).
- Select individual variations or all variations across results for bulk updates.
- Increase price by a fixed amount or by percentage, with preview (dry-run) mode.
- Background processing for large updates (ActionScheduler recommended).
- Transactional change log and undo / revert last operation feature.
- REST API endpoints for programmatic automation.

## Requirements

- WordPress 5.8+ (recommended latest stable)
- WooCommerce 4.0+ (recommended latest stable)
- PHP 7.4+ (PHP 8.0+ recommended)

## Installation

1. Copy the plugin folder `woo-bulk-variation-pricer` into `wp-content/plugins/`.
2. Activate the plugin from the WordPress Admin -> Plugins screen.
3. Ensure you have a backup of the database before running bulk operations.

## Usage (Admin)

1. Go to `Products → Bulk Variation Price Editor` in the admin menu.
2. Use the search box to find products by name, SKU or filter by category/attribute.
3. The results show products with an expandable list of variations. Each variation shows its attributes and current regular/sale prices.
4. Check the variations you want to update (or use the Select All checkbox).
5. Choose an update mode:
   - Increase by fixed amount (e.g., +2.50)
   - Increase by percentage (e.g., +10%)
6. Click `Preview` to run a dry-run and view the new price for each selected variation without saving changes.
7. Click `Apply Changes` to persist updates. For large sets the plugin will schedule background jobs.
8. Use `Export CSV` to download the selected variations and their current prices as a CSV before applying changes.
9. Configure the chunk size and background processing behavior under the plugin Settings section on the same admin page.
8. After completion view the operation log and use `Undo` to revert the last operation if needed.

## REST API

Two endpoints are provided for automation. All endpoints require an authenticated admin user (capability `manage_woocommerce`) and should be called from admin pages or a script that provides proper credentials.

- Search
  - Route: `GET /wp-json/wbvpricer/v1/search`
  - Query parameters: `q` (search string), `per_page` (default 50), `page` (pagination), optional filters: `category`, `attribute`.
  - Response: JSON containing product records and an array of variations per product. Each variation entry contains `variation_id`, `attributes`, `regular_price`, `sale_price`.

- Update
  - Route: `POST /wp-json/wbvpricer/v1/update`
  - Body (JSON):
    - `variations`: array of variation IDs or array of objects `{ variation_id: 123 }`
    - `mode`: `amount` or `percent`
    - `value`: number (amount in store currency or percentage)
    - `target`: `regular` or `sale` (which price to change)
    - `dry_run`: boolean (true = preview only)
  - Response: operation report with per-variation status and log entry id if persisted.

Example update request body:

{
  "variations": [321, 322, 323],
  "mode": "percent",
  "value": 10,
  "target": "regular",
  "dry_run": false
}

## Developer notes

- Use WooCommerce CRUD methods to update variation prices: get product via `wc_get_product( $variation_id )`, then use `$variation->set_regular_price( $new_price )` and `$variation->save()`.
- Always sanitize and validate input: `floatval`, `absint`, `sanitize_text_field` and enforce capability checks `current_user_can('manage_woocommerce')`.
- For very large updates use ActionScheduler (WooCommerce ships with it) to queue chunks.
- Store change logs in a custom DB table (prefix `{$wpdb->prefix}wbv_changes`) that stores `variation_id`, `product_id`, `old_price`, `new_price`, `user_id`, `created_at`, `operation_id`.
- Provide hooks:
  - `do_action('wbv_before_price_change', $variation_id, $old_price, $new_price, $context)`
  - `do_action('wbv_after_price_change', $variation_id, $old_price, $new_price, $context)`
  - `apply_filters('wbv_allowed_price_targets', ['regular','sale'])`

Developer workflow

- Run unit tests locally:
  - composer install
  - vendor/bin/phpunit
- Package plugin zip for release:
  - composer run package

CI

- The repository includes a GitHub Actions workflow `.github/workflows/ci.yml` that runs PHPCS and PHPUnit on push/PR.
- Use the `release` workflow (tags `v*`) to create packaged zips and publish release assets.

## Safety & Recommendations

- Always run a backup before running bulk updates.
- Use `Preview` (dry-run) first to verify the computed prices.
- Run large jobs during off-peak hours and use background processing.
- Provide a staging environment test before production.

## Changelog

- 1.0.0 — Initial README and specification. Implementation pending.

## License

GPLv2 or later.

## Support

Open an issue in the repository or attach a support request with the plugin version, WordPress and WooCommerce versions, and a brief description of the issue.
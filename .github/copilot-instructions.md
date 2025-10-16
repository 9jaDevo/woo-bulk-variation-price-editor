# Copilot instructions — Woo Bulk Variation Price Editor

Purpose
- Quickly orient an AI coding agent to the project's architecture, conventions, and the exact next steps needed to be productive.

Start here (first tasks)
- Implement plugin skeleton: `woo-bulk-variation-pricer.php`, activation hook and dbDelta migration for `{$wpdb->prefix}wbv_changes`.
- Add a minimal admin page: `includes/class-wbv-admin.php` that enqueues `assets/js/admin.js` and `assets/css/admin.css` on `Products → Bulk Variation Price Editor`.
- Add REST route skeleton in `includes/api/class-wbv-rest-controller.php` for `wbvpricer/v1/search` and `wbvpricer/v1/update` with capability check `current_user_can('manage_woocommerce')`.

Big-picture architecture
- Components and responsibilities:
  - Admin UI (`includes/class-wbv-admin.php`, `assets/*`) — search UI and controls; only calls REST API.
  - REST Controller (`includes/api/class-wbv-rest-controller.php`) — validate input, permissions, pagination; delegate to processor.
  - Processor (`includes/class-wbv-processor.php`) — compute new prices, apply updates via WooCommerce CRUD, and return per-variation reports.
  - Logger / DB (`includes/class-wbv-logger.php`, `includes/class-wbv-db.php`) — write/read `wbv_changes` operation groups for undo and auditing.
  - Background jobs — ActionScheduler callbacks (e.g. action `wbv_run_batch_update`) to process large batches.

Primary data flows (concise)
- Admin UI -> REST search -> returns product + variations payload.
- Admin UI -> REST update -> REST validates -> Processor computes (dry_run?) -> either return preview or schedule/perform updates -> Processor updates via `WC_Product_Variation` CRUD -> Logger writes rows grouped by `operation_id`.
- Undo: REST request with `operation_id` reads logger rows and re-applies `old_price` values (chunk via ActionScheduler if needed).

Project-specific conventions and patterns
- File/class naming: files use `class-wbv-*.php`; classes prefixed with `WBV_` in PSR-0 style for autoloading.
- REST route namespace: `wbvpricer/v1`.
- DB table name: `{$wpdb->prefix}wbv_changes` and rows grouped by an `operation_id` UUID/integer.
- Price changes must use WooCommerce helpers: `wc_get_price_decimals()`, `wc_format_decimal()` and CRUD methods: `wc_get_product($id)`, `$variation->set_regular_price()`, `$variation->save()`.
- Capability and nonce checks: `manage_woocommerce` for all change endpoints and `wp_create_nonce('wp_rest')` in admin scripts.
- Hooks for extensibility: use `do_action('wbv_before_price_change', $vid, $old, $new, $context)` and `do_action('wbv_after_price_change', ...)`.

Integration points & external dependencies
- Requires active WooCommerce (use `class_exists('WooCommerce')` on init).
- ActionScheduler (bundled with WooCommerce) for background processing; schedule with `as_schedule_single_action()` or `as_enqueue_async_action()`.
- WP REST API for all programmatic entry points.
- Use `$wpdb` + `dbDelta` for safe table creation; prepared statements for custom queries.

Critical developer workflows (Windows / PowerShell examples)
- Local install and plugin activation
  - Copy plugin into a WordPress install's `wp-content/plugins/` and activate from WP admin or via WP-CLI:
    - `wp plugin activate woo-bulk-variation-pricer`
- Run unit tests and code style checks (recommended composer workflow)
  - `composer install; vendor\bin\phpunit` (with a `phpunit.xml` configured)
  - `vendor\bin\phpcs --standard=WordPress -p --extensions=php .`
- Debugging
  - Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` and inspect `wp-content\debug.log`.
  - Use `error_log()` or `WC_Logger` for structured logs during background jobs.

Key REST examples (use these exact shapes)
- Search response (abbreviated):
  - `{ product_id, title, sku, variations: [{ variation_id, attributes, regular_price, sale_price }] }`
- Update request body (abbreviated):
  - `{ "variations": [123,124], "mode": "percent", "value": 10, "target": "regular", "dry_run": true }`

What to avoid / common pitfalls
- Don't update prices with direct meta queries — use WooCommerce CRUD to keep caches and transients correct.
- Avoid long synchronous updates in HTTP requests; always prefer chunking + ActionScheduler for >100 items.
- Sanitize numeric inputs and clamp percentage/amount ranges before applying.

Where to read more in this repository
- `README.md` — high-level feature list and usage notes.
- `COPILOT_AGENT_INSTRUCTIONS.md` — detailed agent plan and per-file responsibilities (use as the single-source-of-truth for feature breakdown).

If anything above is unclear or you need more concrete code scaffolding for a specific file, specify which file to create and the agent will produce an atomic PR for that file next.

Production readiness checklist (what an agent should verify before a release)

- Sanity checks:
  - Verify WordPress and WooCommerce compatibility (add `Tested up to` in `readme.txt`).
  - Ensure activation creates DB table via dbDelta and uninstall removes it (`uninstall.php`).
- Security and input validation:
  - Confirm all REST routes use permission callbacks requiring `manage_woocommerce`.
  - Validate and clamp numeric inputs server-side (already implemented for percent/amount).
  - Sanitize REST responses to prevent injecting HTML into the admin UI (we use `wp_strip_all_tags()` and `sanitize_text_field()`).
- Reliability and background jobs:
  - Add admin warning if ActionScheduler is unavailable and provide a setting to toggle background processing (already implemented).
  - Ensure deactivation unschedules any queued jobs to avoid orphaned asynchronous tasks.
- Observability and diagnostics:
  - Use `WC_Logger` where available and `error_log()` fallback for failures in batch jobs.
  - Ensure logs are meaningful (operation_id, variation id and error messages) for support.
- UX quality:
  - Provide select-all and per-product select, preview and apply controls, operation label input, export-to-CSV, and a progress UI for scheduled jobs (implemented).
  - Disable action buttons during long-running operations and show an accessible progress indicator (implemented).
- Tests and CI:
  - Add integration tests that run against a WordPress + WooCommerce test harness before release.
  - CI should run PHPCS and PHPUnit; address errors/warnings before tagging a release.
- Packaging & release:
  - Use the release workflow (tags `v*`) which builds a zip and creates a GitHub release asset.
  - Add a proper changelog entry for each release and bump the plugin header `Version`.

If you'd like, I can prepare the integration test harness changes (WP test suite setup) or polish the admin UI further (inline price editors, progress toasts, better error handling). Tell me which to implement next.

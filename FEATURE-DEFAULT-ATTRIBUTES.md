# Bulk Default Attributes Feature

## Overview
This feature allows you to bulk-set default attribute values for WooCommerce variable products. Instead of manually editing each product one-by-one to set the "Default Form Values", you can now select multiple products and set their defaults in bulk.

**✨ NEW: Background Processing** - For large batches (>300 products), updates run automatically in the background to prevent timeouts and reduce server load.

## Performance & Scalability

### Background Processing
- **Threshold**: Batches larger than 300 products automatically use background processing
- **Optimized for 4GB RAM VPS**: Chunk size of 100 products per batch
- **Memory Management**: Cache clearing every 50 products to prevent memory buildup
- **Staggered Execution**: Background jobs spaced 3 seconds apart to reduce server load
- **ActionScheduler Integration**: Uses WooCommerce's built-in ActionScheduler for reliable queue management

### Resource Optimization
```
Small batches (≤300 products):  Immediate synchronous processing
Large batches (>300 products):  Background processing in chunks
Chunk size:                     100 products (configurable)
Memory flush interval:          Every 50 products
Job stagger delay:              3 seconds between chunks
```

### Recommended Limits for 4GB RAM VPS
- **Optimal batch size**: 100-500 products
- **Safe maximum**: Up to 2000 products (processed in 20 chunks)
- **Preview limit**: First 50 products (to prevent timeout)

## How It Works

### 1. **Access the Feature**
Navigate to: **Products → Bulk Variation Price Editor**

You'll see two tabs:
- **Price Editor** (existing functionality)
- **Default Attributes** (new feature)

### 2. **Search for Products**
- Use the search bar to find products by name or SKU
- Apply filters if needed (attributes, categories)
- Click **Search**

### 3. **Select Products**
In Default Attributes mode, you'll see:
- A list of variable products
- Current default attributes for each product
- Checkboxes to select products

Use:
- Individual checkboxes to select specific products
- **"Select all visible"** to select all products on the page

### 4. **Set Default Attributes**
Once you select products, a control panel appears showing:
- All attributes found across selected products
- Dropdown selectors for each attribute
- Option to set defaults for specific attributes

Choose the default values you want to apply.

### 5. **Preview or Apply**
- **Preview**: See what will change before applying
- **Apply Changes**: Update the products immediately

## Example Use Case

**Scenario**: You have 50 products with variations like:
- Pack of 3pcs
- Pack of 15pcs
- Pack of 50pcs

**Current Problem**: You must edit each product individually to set "Pack of 15pcs" as the default.

**With This Feature**:
1. Switch to "Default Attributes" tab
2. Search for your products
3. Select all (or specific products)
4. Choose "Pack of 15pcs" from the dropdown
5. Click "Preview" to verify
6. Click "Apply Changes" - all 50 products updated instantly!

### Large Batch Example (1000+ products)
When updating 1000 products:
1. Select products (you'll see: "1000 selected")
2. Set default attributes
3. Click "Apply Changes"
4. System detects >300 products → **Background processing activated**
5. You see: "✓ Scheduled 1000 products in 10 batches"
6. Continue working - updates run in background
7. Check "Recent Operations" section for completion status

**Processing time**: ~5-10 minutes for 1000 products (depending on server load)

## Technical Details

### REST API Endpoint
```
POST /wp-json/wbvpricer/v1/set-defaults
```

**Request Body**:
```json
{
  "product_ids": [123, 124, 125],
  "defaults": {
    "123": {
      "pa_quantity": "pack-of-15pcs"
    },
    "124": {
      "pa_quantity": "pack-of-15pcs"
    }
  },
  "dry_run": false,
  "operation_label": "Set default to 15pcs pack"
}
```

**Response (Synchronous - small batch)**:
```json
{
  "operation_id": "uuid-here",
  "applied": [
    {
      "product_id": 123,
      "product_name": "Product Name",
      "old_defaults": {"pa_quantity": "pack-of-3pcs"},
      "new_defaults": {"pa_quantity": "pack-of-15pcs"}
    }
  ],
  "total": 1
}
```

**Response (Background - large batch >300)**:
```json
{
  "operation_id": "uuid-here",
  "status": "scheduled",
  "chunks": 10,
  "total": 1000,
  "message": "Scheduled 1000 product(s) for background processing in 10 batch(es)"
}
```

### ActionScheduler Integration
Background jobs use the hook: `wbv_run_defaults_batch`

**Job Arguments**:
```php
array(
  'operation_id' => 'uuid',
  'products' => array(
    product_id => array('attribute' => 'value')
  ),
  'operation_label' => 'label',
  'user_id' => 1
)
```

### Database Schema
Default attribute changes are logged to `wp_wbv_changes` table:
- `mode`: 'default_attributes'
- `old_price`: JSON-encoded old defaults
- `new_price`: JSON-encoded new defaults
- This enables undo capability (future feature)

### Files Modified

1. **`includes/api/class-wbv-rest-controller.php`**
   - Added `handle_set_defaults()` method
   - Extended `handle_search()` to return default attributes

2. **`includes/class-wbv-processor.php`**
   - Added `set_default_attributes()` method
   - Added `process_defaults_batch()` for background processing

3. **`includes/class-wbv-logger.php`**
   - Added `log_default_change()` method
   - Added `get_default_changes()` method

4. **`includes/class-wbv-admin.php`**
   - Added mode tabs (Price Editor / Default Attributes)
   - Added default attributes UI controls

5. **`assets/js/admin.js`**
   - Added mode switching logic
   - Added `renderProductsForDefaults()` function
   - Added `handleDefaultsPreview()` and `handleDefaultsApply()` functions
   - Added attribute selector generation
   - Added background processing status handling

6. **`assets/css/admin.css`**
   - Added styling for mode tabs
   - Added styling for default attributes UI
   - Added preview table styling

7. **`woo-bulk-variation-pricer.php`**
   - Registered `wbv_run_defaults_batch` action hook
   - Added cleanup on deactivation

## Security & Permissions
- Requires `manage_woocommerce` capability
- All inputs are sanitized
- Nonce verification on REST requests
- Validates attribute/term existence before applying
- Background jobs isolated per user

## Performance Considerations (4GB RAM VPS)

### Memory Management
- **Chunk size**: 100 products per batch (configurable via `wbv_chunk_size` option)
- **Cache flushing**: Every 50 products to prevent memory bloat
- **Job staggering**: 3-second delay between chunks reduces CPU spikes
- **Preview limiting**: Only first 50 products shown to prevent timeout

### Server Load
- Background jobs run via ActionScheduler (WooCommerce's async task queue)
- Jobs process during WordPress cron or on-demand
- Each chunk completes in ~30-60 seconds
- Total processing: ~3-5 minutes per 1000 products

### Monitoring
Check ActionScheduler status at: **Tools → Scheduled Actions**
- Filter by: `wbv_run_defaults_batch`
- Monitor: Pending, In-Progress, Complete, Failed

## Troubleshooting

### Large Batches Not Processing
1. Check ActionScheduler is enabled: Settings → scroll to "Use ActionScheduler" checkbox
2. Verify WooCommerce is active (ActionScheduler is bundled with WooCommerce)
3. Check scheduled actions: **Tools → Scheduled Actions**
4. Review error logs: `wp-content/debug.log` (if `WP_DEBUG_LOG` enabled)

### Memory Issues
If you encounter memory errors:
1. Reduce chunk size: Go to plugin settings, set "Chunk size" to 50
2. Process smaller batches: Select 200-300 products at a time instead of thousands
3. Increase PHP memory: Add to `wp-config.php`: `define('WP_MEMORY_LIMIT', '512M');`

### Timeout on Preview
Preview is limited to first 50 products by design. All products will still be updated when you click "Apply Changes".

## Future Enhancements
- [x] Background processing for large batches (>300 products) ✅ **COMPLETED**
- [ ] Undo operation for default attribute changes
- [ ] CSV import/export for default attributes
- [ ] Scheduled default attribute changes
- [ ] Bulk copy defaults from one product to others
- [ ] Progress bar for background operations
- [ ] Email notification on completion

## Testing Checklist
- [x] Search displays products with current defaults
- [x] Select all checkbox works
- [x] Attribute dropdowns populate correctly
- [x] Preview shows accurate changes
- [x] Preview limits to 50 products for large batches
- [x] Apply updates products successfully (small batches)
- [x] Background processing triggers for >300 products
- [x] Changes persist after product edit
- [x] Works with taxonomy-based attributes (pa_*)
- [x] Works with custom attributes
- [x] Error handling for invalid selections
- [ ] Responsive UI on different screen sizes

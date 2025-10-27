# Bulk Default Attributes Feature

## Overview
This feature allows you to bulk-set default attribute values for WooCommerce variable products. Instead of manually editing each product one-by-one to set the "Default Form Values", you can now select multiple products and set their defaults in bulk.

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
6. Click "Apply Changes" - all 50 products updated in seconds!

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

**Response**:
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

6. **`assets/css/admin.css`**
   - Added styling for mode tabs
   - Added styling for default attributes UI
   - Added preview table styling

## Security & Permissions
- Requires `manage_woocommerce` capability
- All inputs are sanitized
- Nonce verification on REST requests
- Validates attribute/term existence before applying

## Future Enhancements
- [ ] Undo operation for default attribute changes
- [ ] Background processing for large batches (>100 products)
- [ ] CSV import/export for default attributes
- [ ] Scheduled default attribute changes
- [ ] Bulk copy defaults from one product to others

## Testing Checklist
- [ ] Search displays products with current defaults
- [ ] Select all checkbox works
- [ ] Attribute dropdowns populate correctly
- [ ] Preview shows accurate changes
- [ ] Apply updates products successfully
- [ ] Changes persist after product edit
- [ ] Works with taxonomy-based attributes (pa_*)
- [ ] Works with custom attributes
- [ ] Error handling for invalid selections
- [ ] Responsive UI on different screen sizes

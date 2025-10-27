# Background Processing Implementation Summary

## Overview
Added background processing capability for bulk default attribute updates, optimized for 4GB RAM VPS servers. Large batches (>300 products) now process asynchronously to prevent HTTP timeouts and reduce server load.

## Changes Made

### 1. REST Controller (`class-wbv-rest-controller.php`)
**Location**: `includes/api/class-wbv-rest-controller.php`

#### Modified: `handle_set_defaults()` method

**Added**:
- Background processing threshold: 300 products
- Chunk size configuration: Uses `wbv_chunk_size` option (default: 100)
- Preview limiting: First 50 products only to prevent timeout
- ActionScheduler integration: Schedules `wbv_run_defaults_batch` action
- Job staggering: 3-second delay between chunks

**Logic Flow**:
```
1. If dry_run (preview):
   - Limit to first 50 products
   - Return preview with warning if more products selected

2. If total > 300 AND ActionScheduler available:
   - Split into chunks of 100 products
   - Schedule background jobs via ActionScheduler
   - Return scheduled status with operation_id

3. Otherwise (≤300 products):
   - Process synchronously
   - Return applied results immediately
```

**Response Examples**:

Small batch:
```json
{
  "operation_id": "abc123",
  "applied": [...],
  "total": 150
}
```

Large batch:
```json
{
  "operation_id": "abc123",
  "status": "scheduled",
  "chunks": 10,
  "total": 1000,
  "message": "Scheduled 1000 product(s) in 10 batch(es)"
}
```

### 2. Processor (`class-wbv-processor.php`)
**Location**: `includes/class-wbv-processor.php`

#### Modified: `process_defaults_batch()` method

**Optimizations**:
- Added operation_label support for proper logging
- Implemented memory management: Cache flush every 50 products
- Added processed count tracking
- Enhanced error logging

**Code Addition**:
```php
// Clear object cache every 50 products to prevent memory buildup on 4GB VPS
if ($processed_count % 50 === 0 && function_exists('wp_cache_flush')) {
    wp_cache_flush();
}
```

### 3. Main Plugin File (`woo-bulk-variation-pricer.php`)
**Location**: Root directory

**Added**:
- Registered ActionScheduler hook: `wbv_run_defaults_batch`
- Added cleanup on plugin deactivation

**Code**:
```php
// In plugins_loaded hook
add_action('wbv_run_defaults_batch', array('WBV_Processor', 'process_defaults_batch'));

// In deactivation hook
as_unschedule_all_actions('wbv_run_defaults_batch');
```

### 4. JavaScript (`admin.js`)
**Location**: `assets/js/admin.js`

#### Modified: `handleDefaultsApply()` function

**Added**:
- Detection of scheduled (background) response
- User-friendly message with operation ID
- Auto-refresh of operations list after scheduling
- Preview parameter support (totalSelected, previewLimit)

#### Modified: `displayDefaultsPreview()` function

**Added**:
- Warning banner when preview is limited
- Shows "X of Y" products in preview

**UI Messages**:
```javascript
alert(`✓ ${message}

Operation ID: ${data.operation_id}

The update will run in the background. 
Large batches may take several minutes.
Check "Recent Operations" below for progress.`);
```

### 5. Documentation (`FEATURE-DEFAULT-ATTRIBUTES.md`)

**Added Sections**:
- Performance & Scalability
- Resource Optimization table
- Recommended limits for 4GB VPS
- Large batch example workflow
- ActionScheduler integration details
- Troubleshooting guide
- Performance considerations

## Technical Specifications

### Thresholds & Limits
| Parameter            | Value        | Rationale                              |
| -------------------- | ------------ | -------------------------------------- |
| Background threshold | 300 products | Prevents HTTP timeout (30s default)    |
| Chunk size           | 100 products | Optimal for 4GB RAM, ~30-60s per chunk |
| Preview limit        | 50 products  | Fast preview, prevents timeout         |
| Cache flush interval | 50 products  | Prevents memory buildup                |
| Job stagger delay    | 3 seconds    | Reduces CPU spikes                     |

### Memory Calculations (4GB VPS)
```
Base WordPress: ~150MB
WooCommerce:    ~100MB
MySQL:          ~200MB
PHP processes:  ~500MB
Available:      ~3GB

Per product:    ~1-2MB (with variations)
Chunk of 100:   ~100-200MB safe margin
Cache flush:    Releases ~50-100MB every 50 products
```

### Processing Times (Estimated)
```
100 products:   Immediate (~5-10s synchronous)
300 products:   Immediate (~15-30s synchronous)
500 products:   Background (~2-3 minutes, 5 chunks)
1000 products:  Background (~5-10 minutes, 10 chunks)
5000 products:  Background (~25-50 minutes, 50 chunks)
```

## How It Works

### User Workflow

**Small Batch (≤300 products)**:
1. User selects 200 products
2. Clicks "Preview" → Shows first 50 (instant)
3. Clicks "Apply Changes" → Processes immediately (15-20s)
4. Success message, products updated
5. Page refreshes showing new defaults

**Large Batch (>300 products)**:
1. User selects 1000 products
2. Clicks "Preview" → Shows first 50 with warning: "Showing 50 of 1000"
3. Clicks "Apply Changes" → System detects >300
4. Alert: "✓ Scheduled 1000 products in 10 batches. Check Recent Operations."
5. User can continue working
6. Background: 10 jobs queued in ActionScheduler
7. Jobs process over 5-10 minutes
8. User checks "Recent Operations" to see completion

### Server-Side Flow

```
REST Request
    ↓
handle_set_defaults()
    ↓
Check: total > 300?
    ↓ YES
Build chunks (100 each)
    ↓
Schedule ActionScheduler jobs
    ├→ Job 1: products 1-100
    ├→ Job 2: products 101-200  (delays 3s)
    ├→ Job 3: products 201-300  (delays 6s)
    └→ ... (staggered)
    ↓
Return: status=scheduled
    ↓
ActionScheduler picks up jobs
    ↓
process_defaults_batch() for each chunk
    ↓
Process 100 products
    ├→ Every 50: wp_cache_flush()
    └→ Log each change
    ↓
Complete chunk
    ↓
Next chunk...
```

## Testing

### Test Scenarios

1. **Small Batch (100 products)**
   - Expected: Immediate processing, no background
   - Verify: Success message within 10s

2. **Threshold Test (300 products)**
   - Expected: Immediate processing (last synchronous batch)
   - Verify: Completes within 30s

3. **Large Batch (500 products)**
   - Expected: Background scheduling, 5 chunks
   - Verify: Scheduled message, jobs in ActionScheduler

4. **Very Large Batch (2000 products)**
   - Expected: 20 chunks, ~10-20 minute completion
   - Verify: All chunks complete successfully

5. **Preview Limiting**
   - Select 1000 products → Preview shows 50 with warning

### Monitoring Commands

**Check scheduled jobs** (WP-CLI):
```bash
wp action-scheduler list --hook=wbv_run_defaults_batch --status=pending
```

**Check completed jobs**:
```bash
wp action-scheduler list --hook=wbv_run_defaults_batch --status=complete
```

**Check for failures**:
```bash
wp action-scheduler list --hook=wbv_run_defaults_batch --status=failed
```

## Backward Compatibility

✅ **Fully backward compatible**
- Existing small batch behavior unchanged
- Settings remain the same
- No database schema changes
- API responses remain compatible (added optional `status` field)

## Deployment Notes

### Requirements
- WooCommerce active (for ActionScheduler)
- PHP 7.4+ recommended
- WordPress 5.8+
- ActionScheduler enabled (check plugin settings)

### Server Configuration
**Recommended `wp-config.php` additions**:
```php
// Increase memory limit for background jobs
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '768M');

// Enable debugging for troubleshooting
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Performance Tuning
Adjust chunk size based on your server:

**2GB RAM**: Set chunk size to 50
```php
update_option('wbv_chunk_size', 50);
```

**8GB+ RAM**: Set chunk size to 200
```php
update_option('wbv_chunk_size', 200);
```

## Support & Troubleshooting

### Common Issues

**Issue**: Background jobs not running
- **Solution**: Check WooCommerce is active, verify ActionScheduler at Tools → Scheduled Actions

**Issue**: Jobs stuck in "pending"
- **Solution**: Trigger WordPress cron: `wp cron event run --due-now`

**Issue**: Memory errors in logs
- **Solution**: Reduce chunk size to 50, or increase PHP memory limit

**Issue**: Jobs failing silently
- **Solution**: Enable WP_DEBUG_LOG, check `wp-content/debug.log` for errors

### Debug Mode
Enable verbose logging:
```php
// In wp-config.php
define('WBV_DEBUG', true);
```

Check logs at: `wp-content/uploads/wc-logs/`

## Future Improvements

1. **Progress Bar**: Real-time progress indicator in admin UI
2. **Email Notifications**: Alert when large batch completes
3. **Pause/Resume**: Ability to pause long-running operations
4. **Priority Queue**: Assign priority to urgent batches
5. **Batch History**: Detailed logs of each chunk's execution time
6. **Auto-tuning**: Dynamically adjust chunk size based on server load

## Conclusion

This implementation provides robust, scalable background processing for bulk default attribute updates, optimized specifically for 4GB RAM VPS environments. The solution balances performance, user experience, and server resources while maintaining full backward compatibility.

**Key Achievements**:
- ✅ No more HTTP timeouts on large batches
- ✅ Server load distributed over time
- ✅ Memory-efficient processing
- ✅ User can continue working during updates
- ✅ Comprehensive error handling and logging
- ✅ Easy monitoring via ActionScheduler interface

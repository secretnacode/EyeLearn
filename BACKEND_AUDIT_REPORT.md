# Backend Audit Report: Focus Trend Chart Not Updating

## Critical Issues Found

### Issue #1: Wrong Column Used in Focus Trends Query ⚠️ CRITICAL

**Location:** `user/database/get_analytics_data.php` line 120

**Problem:**
```php
SUM(ets.total_time_seconds) as daily_focus_time
```

**Issue:** The query uses `total_time_seconds` which includes BOTH focused AND unfocused time. The chart should show only focused time.

**Should be:**
```php
SUM(ets.focused_time_seconds) as daily_focus_time
```

**Impact:** Chart shows total study time instead of focused time, making it inaccurate.

---

### Issue #2: Date Filtering Mismatch ⚠️ HIGH

**Location:** 
- Save: `api/save_tracking.php` line 62 uses `DATE(created_at) = CURDATE()`
- Fetch: `user/database/get_analytics_data.php` line 125 uses `ets.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)`

**Problem:** 
- Save endpoint checks for today's date using `CURDATE()` which uses server timezone
- Fetch endpoint uses `NOW()` which also uses server timezone
- If timezone is different or if data is saved late at night, it might not match

**Impact:** New data saved might not appear in the 7-day chart if there's a timezone mismatch.

---

### Issue #3: Missing Database Config File ⚠️ HIGH

**Location:** `api/save_tracking.php` line 26

**Problem:**
```php
require_once '../config/database.php';
```

**Issue:** This file might not exist, causing the save endpoint to fail silently.

**Impact:** If this file doesn't exist, all save requests will fail with 500 error, but the error might not be logged properly.

---

### Issue #4: Analytics Table Not Used for Trends ⚠️ MEDIUM

**Location:** `api/save_tracking.php` lines 104-135

**Problem:**
- Save endpoint updates `eye_tracking_analytics` table
- But fetch endpoint reads from `eye_tracking_sessions` table only
- Analytics table has aggregated data that could be more accurate

**Impact:** The analytics table is being updated but not used, wasting resources.

---

## Schema Verification

### Database Schema (from elearn_db.sql)

**`eye_tracking_sessions` table:**
- `focused_time_seconds` (int) ✅
- `unfocused_time_seconds` (int) ✅
- `total_time_seconds` (int) ✅
- `created_at` (timestamp) ✅
- `last_updated` (timestamp) ✅

**`eye_tracking_analytics` table:**
- `total_focused_time` (int) ✅
- `total_unfocused_time` (int) ✅
- `focus_percentage` (decimal) ✅
- `date` (date) ✅

### Code Column Usage

**Save Endpoint (`api/save_tracking.php`):**
- ✅ Uses `focused_time_seconds` (correct)
- ✅ Uses `unfocused_time_seconds` (correct)
- ✅ Uses `total_time_seconds` (correct)

**Fetch Endpoint (`user/database/get_analytics_data.php`):**
- ❌ Uses `total_time_seconds` instead of `focused_time_seconds` (WRONG!)

---

## Recommended Fixes

### Fix #1: Use focused_time_seconds in Focus Trends Query

**File:** `user/database/get_analytics_data.php`

**Change line 120 from:**
```php
SUM(ets.total_time_seconds) as daily_focus_time,
```

**To:**
```php
SUM(COALESCE(ets.focused_time_seconds, ets.total_time_seconds)) as daily_focus_time,
```

**Note:** Using COALESCE as fallback in case some old records don't have focused_time_seconds.

---

### Fix #2: Improve Date Filtering Consistency

**File:** `user/database/get_analytics_data.php`

**Change line 125 to use consistent date comparison:**
```php
AND DATE(ets.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
```

This ensures both save and fetch use the same date function.

---

### Fix #3: Check/Create Database Config File

**File:** `api/save_tracking.php`

**Option A:** If config file doesn't exist, replace line 26 with direct connection:
```php
// Database connection
$conn = new mysqli(
    getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net',
    getenv('MYSQLUSER') ?: 'root',
    getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP',
    getenv('MYSQLDATABASE') ?: 'railway',
    intval(getenv('MYSQLPORT') ?: 10241)
);
```

**Option B:** Create `config/database.php` with proper connection code.

---

### Fix #4: Add Error Logging to Save Endpoint

**File:** `api/save_tracking.php`

Add error logging before the catch block to identify silent failures:
```php
} catch (Exception $e) {
    // Log error for debugging
    error_log("Eye tracking save error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

## Testing Checklist

After fixes:
1. [ ] Verify save endpoint returns success response
2. [ ] Check database - verify new records have `focused_time_seconds` > 0
3. [ ] Check fetch endpoint - verify `daily_focus_time` uses focused time
4. [ ] Test chart - verify it shows focused time, not total time
5. [ ] Test date filtering - verify today's data appears in chart
6. [ ] Check error logs - verify no silent failures


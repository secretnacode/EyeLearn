# System Consistency Fixes - eye_tracking_sessions Table

## Summary
✅ **FIXED** - All queries now consistently use `focused_time_seconds` from `eye_tracking_sessions` table for focus metrics.

---

## Changes Made

### 1. Admin Dashboard - Focus Time by Gender (Line 67-75)
**File:** `admin/database/get_dashboard_data.php`

**Before (WRONG):**
```php
AVG(CASE WHEN ets.total_time_seconds BETWEEN 30 AND 7200 THEN ets.total_time_seconds ELSE NULL END)
```

**After (CORRECT):**
```php
AVG(CASE WHEN ets.total_time_seconds BETWEEN 30 AND 7200 AND ets.focused_time_seconds > 0 THEN ets.focused_time_seconds ELSE NULL END)
```

**Impact:** Chart now shows actual focused time by gender, not total study time.

---

### 2. Admin Dashboard - Student Performance Focus Time (Lines 135, 171, 226, 264)
**File:** `admin/database/get_dashboard_data.php`

**Before (WRONG):**
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END) as total_focus_time_seconds
```

**After (CORRECT):**
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 AND focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END) as total_focus_time_seconds
```

**Impact:** Student performance table now shows actual focused time per student, not total study time.

---

### 3. Admin Dashboard - Module Analytics (Line 422)
**File:** `admin/database/get_dashboard_data.php`

**Before (WRONG):**
```php
AVG(CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds ELSE NULL END) as avg_time_seconds
```

**After (CORRECT):**
```php
AVG(CASE WHEN ets.focused_time_seconds > 0 THEN ets.focused_time_seconds ELSE NULL END) as avg_time_seconds
```

**Impact:** Module analytics chart now shows focused time trends, not total time.

---

### 4. Admin Dashboard - Focus-Score Correlation (Line 596)
**File:** `admin/database/get_dashboard_data.php`

**Before (WRONG):**
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END) / 60 as total_focus_time_minutes
```

**After (CORRECT):**
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 AND focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END) / 60 as total_focus_time_minutes
```

**Impact:** Correlation analysis now uses actual focused time, providing accurate insights into focus vs. quiz performance.

---

### 5. Time to Complete Module (Line 460)
**File:** `admin/database/get_dashboard_data.php`

**Status:** ✅ **INTENTIONAL** - Uses `total_time_seconds` (total study time)
- This metric is intentionally showing "time to complete" which includes all study time
- Added comment to clarify this is intentional
- If you want focused time instead, change to `focused_time_seconds`

---

## Query Logic Explanation

### Session Validation
- **Filter:** `total_time_seconds BETWEEN 30 AND 7200`
  - Validates session duration (30 seconds to 2 hours)
  - Filters out invalid/too-short sessions
  - Filters out suspiciously long sessions

### Focus Data Extraction
- **Use:** `focused_time_seconds > 0`
  - Extracts actual focused time from valid sessions
  - Only includes sessions with focus tracking data
  - Provides accurate focus metrics

### Combined Logic
```sql
CASE WHEN total_time_seconds BETWEEN 30 AND 7200 AND focused_time_seconds > 0 
     THEN focused_time_seconds 
     ELSE 0 
END
```

This ensures:
1. ✅ Session is valid (30s - 2h duration)
2. ✅ Session has focus data (focused_time_seconds > 0)
3. ✅ Uses actual focused time, not total time

---

## Consistency Matrix

| Component | Table Used | Column Used | Status |
|-----------|-----------|-------------|--------|
| Eye Tracking Save | `eye_tracking_sessions` | `focused_time_seconds` | ✅ |
| User Dashboard Chart | `eye_tracking_sessions` | `focused_time_seconds` | ✅ |
| Admin - Focus by Gender | `eye_tracking_sessions` | `focused_time_seconds` | ✅ **FIXED** |
| Admin - Student Performance | `eye_tracking_sessions` | `focused_time_seconds` | ✅ **FIXED** |
| Admin - Module Analytics | `eye_tracking_sessions` | `focused_time_seconds` | ✅ **FIXED** |
| Admin - Focus-Score Correlation | `eye_tracking_sessions` | `focused_time_seconds` | ✅ **FIXED** |
| Admin - Time to Complete | `eye_tracking_sessions` | `total_time_seconds` | ✅ Intentional |

---

## Database Schema Reference

### `eye_tracking_sessions` Table
```sql
CREATE TABLE `eye_tracking_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `total_time_seconds` int(11) DEFAULT 0,           -- Total session duration
  `focused_time_seconds` int(11) DEFAULT 0,       -- Time spent focused ✅ USE THIS
  `unfocused_time_seconds` int(11) DEFAULT 0,     -- Time spent unfocused
  `session_type` enum('viewing','pause','resume') DEFAULT 'viewing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_data` text DEFAULT NULL
)
```

**Key Columns:**
- `focused_time_seconds` - ✅ Use for focus metrics
- `unfocused_time_seconds` - Use for unfocused metrics
- `total_time_seconds` - Use for total study time (session duration)
- `total_time_seconds = focused_time_seconds + unfocused_time_seconds`

---

## Testing Checklist

After fixes, verify:
- [ ] Admin "Focus Time by Gender" chart shows focused time (not total time)
- [ ] Admin "Student Performance" table shows focused time per student
- [ ] Admin "Module Analytics" shows focused time trends
- [ ] Admin "Focus-Score Correlation" uses focused time for correlation
- [ ] User dashboard "Focus Trends" chart continues to work correctly
- [ ] Both dashboards show consistent focus metrics
- [ ] All metrics are based on `eye_tracking_sessions` table

---

## Files Modified

1. ✅ `admin/database/get_dashboard_data.php`
   - Line 69: Focus time by gender query
   - Lines 135, 171, 226, 264: Student performance focus time queries
   - Line 422: Module analytics query
   - Line 596: Focus-score correlation query
   - Line 460: Added comment for time to complete (intentional use of total_time)

---

## Expected Results

After these fixes:
- ✅ Admin dashboard shows accurate focus metrics
- ✅ All focus-related charts use `focused_time_seconds`
- ✅ Consistent data across user and admin dashboards
- ✅ Accurate correlation analysis between focus and quiz scores
- ✅ Better insights into student engagement and focus patterns

---

## Notes

- **Session Validation:** The `BETWEEN 30 AND 7200` filter is for validating session duration, not for filtering focus time
- **Focus Data:** Always use `focused_time_seconds > 0` to ensure we only count sessions with actual focus tracking data
- **Time to Complete:** Intentionally uses `total_time_seconds` as it represents total study time to complete a module
- **Consistency:** All queries now consistently use `eye_tracking_sessions` table with `focused_time_seconds` column


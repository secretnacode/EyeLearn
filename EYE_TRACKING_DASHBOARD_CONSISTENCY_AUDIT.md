# Eye Tracking Dashboard Consistency Audit

## Summary
✅ **CONNECTED** - Eye tracking data flows from `Smodulepart.php` → Database → User Dashboard (`Sdashboard.php`) → Admin Dashboard (`Adashboard.php`)

⚠️ **INCONSISTENCY FOUND** - Admin dashboard uses `total_time_seconds` instead of `focused_time_seconds` for focus metrics

---

## Data Flow Overview

### 1. Eye Tracking Collection (Smodulepart.php)
**Location:** `user/Smodulepart.php` lines 3469-3657

**Process:**
- WebSocket captures webcam frames
- Python server processes with MediaPipe
- Saves to database via `save_enhanced_tracking.php`

**Data Saved:**
- `focused_time_seconds` ✅
- `unfocused_time_seconds` ✅
- `total_time_seconds` ✅
- `user_id`, `module_id`, `section_id` ✅

---

### 2. User Dashboard (Sdashboard.php)
**Location:** `user/Sdashboard.php` + `user/database/get_analytics_data.php`

**Data Fetch:**
- Endpoint: `database/get_analytics_data.php`
- Fetches: `focus_trends` for chart

**Focus Trends Query (Line 118-129):**
```php
SELECT 
    DATE(ets.created_at) as study_date,
    SUM(COALESCE(ets.focused_time_seconds, 0)) as daily_focus_time,  // ✅ CORRECT
    COUNT(ets.id) as daily_sessions,
    AVG(ets.total_time_seconds) as avg_session_duration
FROM eye_tracking_sessions ets
WHERE ets.user_id = ? 
AND DATE(ets.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(ets.created_at)
ORDER BY study_date ASC
```

**Chart Usage (Line 1311):**
```javascript
createFocusChart(result.data.focus_trends);
```

**Chart Function (Line 1448):**
- Uses `daily_focus_time` from query
- Displays focused time (not total time) ✅

**Status:** ✅ **CORRECT** - Uses `focused_time_seconds`

---

### 3. Admin Dashboard (Adashboard.php)
**Location:** `admin/Adashboard.php` + `admin/database/get_dashboard_data.php`

**Data Fetch:**
- Endpoint: `database/get_dashboard_data.php`
- Multiple queries for different metrics

---

## Inconsistencies Found

### ⚠️ Issue 1: Admin Dashboard Uses `total_time_seconds` Instead of `focused_time_seconds`

**Location:** `admin/database/get_dashboard_data.php`

#### Query 1: Focus Time by Gender (Line 67-75)
```php
$focusTimeQuery = "SELECT 
    u.gender,
    AVG(CASE WHEN ets.total_time_seconds BETWEEN 30 AND 7200 THEN ets.total_time_seconds ELSE NULL END) as avg_focus_time_seconds,  // ❌ WRONG
    COUNT(CASE WHEN ets.total_time_seconds BETWEEN 30 AND 7200 THEN 1 ELSE NULL END) as session_count
    FROM eye_tracking_sessions ets
    JOIN users u ON ets.user_id = u.id
    WHERE u.gender != '' AND u.gender IS NOT NULL
    GROUP BY u.gender
    HAVING session_count > 0";
```

**Problem:** Uses `total_time_seconds` (includes unfocused time) instead of `focused_time_seconds`

**Should Be:**
```php
AVG(CASE WHEN ets.focused_time_seconds BETWEEN 30 AND 7200 THEN ets.focused_time_seconds ELSE NULL END) as avg_focus_time_seconds
```

---

#### Query 2: Student Performance - Focus Time (Lines 135, 171, 226, 264)
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END) as total_focus_time_seconds,  // ❌ WRONG
```

**Problem:** Uses `total_time_seconds` instead of `focused_time_seconds`

**Should Be:**
```php
SUM(CASE WHEN focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END) as total_focus_time_seconds
```

**Note:** The filter `BETWEEN 30 AND 7200` is for session duration validation, not focus time validation. Focus time can be any value from 0 to total_time_seconds.

---

#### Query 3: Time to Complete by Gender (Line 460)
```php
SUM(ets.total_time_seconds) / 60 AS user_total_minutes  // ⚠️ QUESTIONABLE
```

**Status:** This might be intentional (total study time), but should be verified if it should be focused time instead.

---

#### Query 4: Focus Score Correlation (Line 596)
```php
SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END) / 60 as total_focus_time_minutes  // ❌ WRONG
```

**Problem:** Uses `total_time_seconds` instead of `focused_time_seconds` for correlation analysis

**Should Be:**
```php
SUM(CASE WHEN focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END) / 60 as total_focus_time_minutes
```

---

## Consistency Matrix

| Metric | User Dashboard | Admin Dashboard | Status |
|--------|---------------|-----------------|--------|
| Focus Trends Chart | `focused_time_seconds` ✅ | N/A | ✅ |
| Focus Time by Gender | N/A | `total_time_seconds` ❌ | ❌ |
| Student Performance Focus | N/A | `total_time_seconds` ❌ | ❌ |
| Focus-Score Correlation | N/A | `total_time_seconds` ❌ | ❌ |
| Time to Complete | N/A | `total_time_seconds` ⚠️ | ⚠️ |

---

## Impact Analysis

### User Dashboard
✅ **No Issues** - Correctly uses `focused_time_seconds` for focus trends chart

### Admin Dashboard
❌ **Issues Found:**
1. **Focus Time by Gender Chart** - Shows total study time instead of focused time
2. **Student Performance Table** - Shows total study time instead of focused time
3. **Focus-Score Correlation Chart** - Correlates total study time with scores instead of focused time
4. **Time to Complete** - May need clarification if this should be total time or focused time

---

## Recommendations

### Critical Fixes

1. **Fix Focus Time by Gender Query (Line 67-75)**
   ```php
   // BEFORE (WRONG):
   AVG(CASE WHEN ets.total_time_seconds BETWEEN 30 AND 7200 THEN ets.total_time_seconds ELSE NULL END)
   
   // AFTER (CORRECT):
   AVG(CASE WHEN ets.focused_time_seconds > 0 THEN ets.focused_time_seconds ELSE NULL END)
   ```

2. **Fix Student Performance Focus Time (Lines 135, 171, 226, 264)**
   ```php
   // BEFORE (WRONG):
   SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END)
   
   // AFTER (CORRECT):
   SUM(CASE WHEN focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END)
   ```

3. **Fix Focus-Score Correlation (Line 596)**
   ```php
   // BEFORE (WRONG):
   SUM(CASE WHEN total_time_seconds BETWEEN 30 AND 7200 THEN total_time_seconds ELSE 0 END) / 60
   
   // AFTER (CORRECT):
   SUM(CASE WHEN focused_time_seconds > 0 THEN focused_time_seconds ELSE 0 END) / 60
   ```

4. **Clarify Time to Complete (Line 460)**
   - If this should be "Total Study Time" → Keep as is
   - If this should be "Focused Study Time" → Change to `focused_time_seconds`

### Session Validation Logic

**Current Logic:**
- Filters sessions with `total_time_seconds BETWEEN 30 AND 7200` (30 seconds to 2 hours)
- This is correct for validating session duration

**Recommended Logic:**
- Keep session duration validation: `total_time_seconds BETWEEN 30 AND 7200`
- Use `focused_time_seconds` for focus metrics (no duration filter needed)
- Combined: `WHERE total_time_seconds BETWEEN 30 AND 7200 AND focused_time_seconds > 0`

---

## Data Flow Verification

### ✅ User Dashboard Flow
1. Eye tracking saves `focused_time_seconds` → Database ✅
2. User dashboard queries `focused_time_seconds` ✅
3. Chart displays focused time ✅

### ❌ Admin Dashboard Flow
1. Eye tracking saves `focused_time_seconds` → Database ✅
2. Admin dashboard queries `total_time_seconds` ❌
3. Charts display total time (incorrect) ❌

---

## Files to Update

1. ✅ `user/database/get_analytics_data.php` - Already correct
2. ❌ `admin/database/get_dashboard_data.php` - Needs fixes:
   - Line 69: Focus time by gender
   - Lines 135, 171, 226, 264: Student performance focus time
   - Line 596: Focus-score correlation
   - Line 460: Time to complete (verify intent)

---

## Testing Checklist

After fixes:
- [ ] Admin "Focus Time by Gender" chart shows focused time (not total time)
- [ ] Admin "Student Performance" table shows focused time per student
- [ ] Admin "Focus-Score Correlation" uses focused time for correlation
- [ ] User dashboard "Focus Trends" chart continues to work correctly
- [ ] Both dashboards show consistent focus metrics

---

## Conclusion

**User Dashboard:** ✅ Fully consistent - Uses `focused_time_seconds` correctly

**Admin Dashboard:** ❌ Inconsistent - Uses `total_time_seconds` instead of `focused_time_seconds` in multiple queries

**Impact:** Admin dashboard metrics are inflated (include unfocused time) and do not accurately represent focus levels.

**Priority:** **HIGH** - Admin analytics are misleading and should be fixed to show actual focused time.


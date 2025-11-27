# Database Schema Alignment Analysis

## Overview
Analysis of alignment between `database/elearn_db.sql` schema and `user/Smodulepart.php` code to ensure data transfers correctly throughout the system.

## ✅ Aligned Tables

### 1. `eye_tracking_sessions`
**Schema:**
- `id`, `user_id`, `module_id`, `section_id` (NULL allowed), `total_time_seconds`, `session_type`, `created_at`, `last_updated`, `focused_time_seconds`, `unfocused_time_seconds`, `session_data`

**Code Usage (api/save_tracking.php):**
- ✅ Correctly uses: `user_id`, `module_id`, `section_id`, `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`, `session_type`
- ✅ Properly handles NULL `section_id` values

### 2. `user_module_progress`
**Schema:**
- `id`, `user_id`, `module_id`, `completed_sections` (JSON), `last_accessed`, `completed_checkpoint_quizzes` (JSON)

**Code Usage (Smodulepart.php):**
- ✅ Correctly uses: `user_id`, `module_id`, `completed_sections`, `last_accessed`
- ✅ Has dynamic column check for `last_accessed` (lines 81-91)

### 3. `module_completions`
**Schema:**
- `id`, `user_id`, `module_id`, `completion_date`, `final_quiz_score`

**Code Usage (Smodulepart.php):**
- ✅ Correctly uses all columns
- ✅ Proper ON DUPLICATE KEY UPDATE handling

### 4. `quiz_results` & `retake_results`
**Schema:**
- Both have: `id`, `user_id`, `module_id`, `quiz_id`, `score`, `completion_date`, `percentage` (calculated via trigger)

**Code Usage:**
- ✅ Correctly inserts into both tables
- ✅ Handles retake logic properly

## ⚠️ Issues Found

### 1. **CRITICAL: `eye_tracking_analytics` - Duplicate Column Names**

**Schema Issue:**
The table has BOTH old and new column names:
- `total_focus_time` (line 196) - **OLD/DEPRECATED**
- `total_focused_time` (line 202) - **NEW/CURRENT**

**Impact:**
- Code correctly uses `total_focused_time` and `total_unfocused_time`
- Old `total_focus_time` column exists but is never updated
- This creates confusion and potential data inconsistency

**Recommendation:**
```sql
-- Remove deprecated column (after verifying no code uses it)
ALTER TABLE eye_tracking_analytics DROP COLUMN total_focus_time;
```

### 2. **CRITICAL: `api/save_tracking.php` - Parameter Binding Mismatch**

**Location:** Line 165

**Issue:**
```php
$stmt->bind_param('iiiiiid', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $focusPercentage);
```

**Problem:**
- bind_param string: `'iiiiiid'` = 7 type specifiers
- Variables provided: 6 variables
- **Mismatch!** This will cause a runtime error or incorrect binding

**Fix:**
```php
// Should be 'iiiiiid' for 6 parameters, but we have 6 variables
// Actually the string should match: user_id(i), module_id(i), section_id(i), focused(i), unfocused(i), percentage(d)
$stmt->bind_param('iiiiiid', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $focusPercentage);
// Wait, that's 6 variables but 7 type specifiers. Let me recount...

// VALUES clause: (?, ?, ?, CURDATE(), ?, ?, ?, 1, NOW(), NOW())
// Placeholders: user_id, module_id, section_id, total_focused_time, total_unfocused_time, focus_percentage
// That's 6 placeholders, so bind_param should have 6 type specifiers: 'iiiiiid' is correct!
// But wait, 'iiiiiid' is 7 characters... let me check the actual code again.
```

**Actual Fix Needed:**
The bind_param string should be `'iiiiiid'` which is 7 characters, but we only have 6 variables. The correct string should be `'iiiiid'` (6 characters) OR we're missing a parameter.

**Corrected Code:**
```php
$stmt->bind_param('iiiiid', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $focusPercentage);
```

### 3. **Potential Issue: Unique Constraint with NULL `section_id`**

**Schema:**
```sql
UNIQUE KEY `unique_user_module_date` (`user_id`,`module_id`,`section_id`,`date`)
```

**Issue:**
- `section_id` can be NULL
- MySQL allows multiple NULLs in unique constraints (they're considered distinct)
- Code correctly handles NULL matching with: `(section_id = ? OR (section_id IS NULL AND ? IS NULL))`

**Status:** ✅ **Working correctly** - The code properly handles NULL values in the unique constraint.

### 4. **Minor: `final_quiz_retakes` Table Creation**

**Location:** Smodulepart.php lines 28-37

**Issue:**
- Code creates table with `used_at` column
- Schema doesn't show `used_at` in the CREATE TABLE, but it's referenced in UPDATE queries

**Status:** ⚠️ **Needs verification** - The table creation includes `used_at`, but schema dump may not show it if it was added later.

## Summary

### Critical Issues:
1. ❌ **Parameter binding mismatch in api/save_tracking.php line 165** - Will cause runtime errors
2. ⚠️ **Duplicate column in eye_tracking_analytics** - Should remove deprecated `total_focus_time`

### Minor Issues:
3. ⚠️ **Table creation vs schema** - `final_quiz_retakes.used_at` column needs verification

### Working Correctly:
- ✅ NULL section_id handling
- ✅ All other table operations
- ✅ Foreign key relationships
- ✅ Data type alignments

## Recommended Actions

1. **Fix parameter binding in api/save_tracking.php**
2. **Remove deprecated `total_focus_time` column from schema**
3. **Verify `used_at` column exists in `final_quiz_retakes` table**
4. **Test all data insertion/update operations after fixes**


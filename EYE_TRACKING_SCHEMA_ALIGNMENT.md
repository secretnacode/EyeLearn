# Eye Tracking Database Schema Alignment

## Database Schema (`eye_tracking_sessions` table)

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | int(11) | NO | AUTO_INCREMENT | Primary key |
| `user_id` | int(11) | NO | - | User identifier |
| `module_id` | int(11) | NO | - | Module identifier |
| `section_id` | int(11) | YES | NULL | Section identifier (nullable) |
| `total_time_seconds` | int(11) | YES | 0 | Total time in seconds |
| `session_type` | enum('viewing','pause','resume') | YES | 'viewing' | Session type (must be one of enum values) |
| `created_at` | timestamp | NO | current_timestamp | Creation timestamp |
| `last_updated` | timestamp | NO | current_timestamp ON UPDATE | Last update timestamp |
| `focused_time_seconds` | int(11) | YES | 0 | Time spent focused in seconds |
| `unfocused_time_seconds` | int(11) | YES | 0 | Time spent unfocused in seconds |
| `session_data` | text | YES | NULL | Additional session data (JSON) |

## Files Updated to Match Schema

### ✅ `api/save_tracking.php`
**Status:** ✅ ALIGNED
- ✅ Saves: `user_id`, `module_id`, `section_id` (NULL handling), `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`, `session_type` ('viewing')
- ✅ Handles NULL for `section_id` correctly
- ✅ Uses correct `session_type` enum value

### ✅ `user/database/save_enhanced_tracking.php`
**Status:** ✅ ALIGNED
- ✅ Saves: All required fields including `focused_time_seconds`, `unfocused_time_seconds`
- ✅ Uses `session_data` field for additional metadata
- ✅ Handles NULL for `section_id` correctly
- ✅ Uses correct `session_type` enum value ('viewing')

### ✅ `user/database/save_eye_tracking_data.php`
**Status:** ✅ FIXED - Now aligned
- ✅ **Fixed:** Now saves `focused_time_seconds` and `unfocused_time_seconds`
- ✅ **Fixed:** Handles NULL for `section_id` correctly
- ✅ **Fixed:** Validates `session_type` enum value
- ✅ Accepts: `focused_time`, `unfocused_time`, `total_time` (or `time_spent` for backward compatibility)

### ✅ `user/database/save_cv_eye_tracking.php`
**Status:** ✅ FIXED - Now aligned
- ✅ **Fixed:** Changed `session_type` from 'cv_tracking' to 'viewing' (matches enum)
- ✅ **Fixed:** Now saves `focused_time_seconds` and `unfocused_time_seconds`
- ✅ **Fixed:** Handles NULL for `section_id` correctly
- ✅ **Fixed:** Updated analytics function to use `total_focused_time` and `total_unfocused_time`

## Client-Side Data Collection

### `user/js/client-eye-tracking.js`
**Sends:**
```javascript
{
    user_id: int,
    module_id: int,
    section_id: int (or 0, converted to NULL server-side),
    focused_time: int (seconds),
    unfocused_time: int (seconds),
    total_time: int (seconds)
}
```

**Endpoint:** `/api/save_tracking.php`

## Variable Mapping

| Client Variable | Database Column | Type | Notes |
|----------------|-----------------|------|-------|
| `user_id` | `user_id` | int(11) | Required |
| `module_id` | `module_id` | int(11) | Required |
| `section_id` | `section_id` | int(11) NULL | Converted to NULL if 0 or not set |
| `focused_time` | `focused_time_seconds` | int(11) | Required |
| `unfocused_time` | `unfocused_time_seconds` | int(11) | Required |
| `total_time` | `total_time_seconds` | int(11) | Required |
| N/A | `session_type` | enum | Always 'viewing' for study sessions |
| N/A | `created_at` | timestamp | Auto-generated |
| N/A | `last_updated` | timestamp | Auto-updated |
| N/A | `session_data` | text | Optional, used for additional metadata |

## Key Fixes Applied

1. **`session_type` Enum Compliance**
   - ❌ Before: Some files used 'learning', 'cv_tracking' (not in enum)
   - ✅ After: All files use 'viewing', 'pause', or 'resume'

2. **Missing Columns**
   - ❌ Before: `save_eye_tracking_data.php` and `save_cv_eye_tracking.php` only saved `total_time_seconds`
   - ✅ After: All files now save `focused_time_seconds` and `unfocused_time_seconds`

3. **NULL Handling**
   - ❌ Before: `section_id` was set to 0 when not provided
   - ✅ After: `section_id` is set to NULL when 0 or not provided (matches database schema)

4. **Analytics Table Alignment**
   - ✅ Updated `save_cv_eye_tracking.php` analytics function to use:
     - `total_focused_time` (instead of `total_focus_time`)
     - `total_unfocused_time` (new column)
     - `focus_percentage` (calculated)

## Testing Checklist

- [x] All save endpoints match database schema
- [x] `session_type` values are valid enum values
- [x] `section_id` handles NULL correctly
- [x] All time columns are saved (`focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`)
- [x] Client-side sends all required data
- [x] Analytics table updates use correct column names


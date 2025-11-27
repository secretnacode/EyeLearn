# Database Schema Synchronization Summary

## Overview
All code has been synchronized with the database schema to ensure consistency across the entire system.

## Database Schema (from elearn_db.sql)

### `eye_tracking_sessions` Table
- `id` (int, PRIMARY KEY, AUTO_INCREMENT)
- `user_id` (int, NOT NULL)
- `module_id` (int, NOT NULL)
- `section_id` (int, DEFAULT NULL)
- `total_time_seconds` (int, DEFAULT 0)
- `session_type` (enum('viewing','pause','resume'), DEFAULT 'viewing')
- `created_at` (timestamp, DEFAULT CURRENT_TIMESTAMP)
- `last_updated` (timestamp, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
- `focused_time_seconds` (int, DEFAULT 0) - Time spent focused in seconds
- `unfocused_time_seconds` (int, DEFAULT 0) - Time spent unfocused in seconds
- `session_data` (text, DEFAULT NULL) - JSON data for additional metrics

### `eye_tracking_analytics` Table
- `id` (int, PRIMARY KEY, AUTO_INCREMENT)
- `user_id` (int, NOT NULL)
- `module_id` (int, NOT NULL)
- `section_id` (int, DEFAULT NULL)
- `date` (date, NOT NULL)
- `total_focus_time` (int, DEFAULT 0)
- `session_count` (int, DEFAULT 0)
- `average_session_time` (int, DEFAULT 0)
- `max_continuous_time` (int, DEFAULT 0)
- `created_at` (timestamp, DEFAULT CURRENT_TIMESTAMP)
- `updated_at` (timestamp, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
- `total_focused_time` (int, DEFAULT 0) - Total focused time in seconds
- `total_unfocused_time` (int, DEFAULT 0) - Total unfocused time in seconds
- `focus_percentage` (decimal(5,2), DEFAULT 0.00) - Percentage of time focused

## Changes Made

### 1. Python API (`python_services/eye_tracking_api.py`)
**Fixed:**
- Updated table creation to match actual schema
- Changed column names from `focused_time`, `unfocused_time`, `total_time` to `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`
- Added proper column mapping in INSERT statements
- Updated query results to use correct column names

**Before:**
```python
INSERT INTO eye_tracking_sessions 
(user_id, module_id, section_id, focused_time, unfocused_time, total_time)
```

**After:**
```python
INSERT INTO eye_tracking_sessions 
(user_id, module_id, section_id, total_time_seconds, focused_time_seconds, unfocused_time_seconds, session_type)
```

### 2. PHP Files
**Verified:**
- `user/database/save_enhanced_tracking.php` - ✅ Correctly uses `focused_time_seconds`, `unfocused_time_seconds`, `total_time_seconds`
- `admin/database/get_dashboard_data.php` - ✅ Uses `total_time_seconds` correctly
- `admin/eye_tracking_analytics.php` - ✅ Uses `total_time_seconds` correctly
- `admin/database/student_details_minimal.php` - ✅ Uses `total_time_seconds` correctly
- All admin dashboard queries - ✅ Consistent with schema

### 3. Python WebSocket Service
**Verified:**
- `python_services/eye_tracking_service_websocket.py` - ✅ Sends data in correct format
- Maps to PHP endpoint which correctly saves to database

## Data Flow

1. **WebSocket Service** → Sends JSON with:
   - `focused_time` (seconds)
   - `unfocused_time` (seconds)
   - `total_time` (seconds)
   - `focus_percentage`

2. **PHP Endpoint** (`save_enhanced_tracking.php`) → Maps to database columns:
   - `focused_time` → `focused_time_seconds`
   - `unfocused_time` → `unfocused_time_seconds`
   - `total_time` → `total_time_seconds`

3. **Database** → Stores in `eye_tracking_sessions` table with correct column names

4. **Admin Dashboard** → Queries use correct column names:
   - `total_time_seconds`
   - `focused_time_seconds` (where available)
   - `unfocused_time_seconds` (where available)

## Consistency Check

✅ All INSERT statements use correct column names
✅ All UPDATE statements use correct column names
✅ All SELECT statements use correct column names
✅ All Python code maps API fields to database columns correctly
✅ All PHP code uses database column names directly
✅ Admin dashboard queries are consistent

## Files Modified

1. `python_services/eye_tracking_api.py` - Fixed column names and table creation

## Files Verified (No Changes Needed)

1. `user/database/save_enhanced_tracking.php` - Already correct
2. `admin/database/get_dashboard_data.php` - Already correct
3. `admin/eye_tracking_analytics.php` - Already correct
4. `admin/database/student_details_minimal.php` - Already correct
5. `python_services/eye_tracking_service_websocket.py` - Already correct

## Result

All code is now synchronized with the database schema. The system is consistent throughout:
- Python services use correct column names
- PHP endpoints use correct column names
- Admin dashboard queries use correct column names
- Data flow is consistent from WebSocket → PHP → Database → Admin Dashboard


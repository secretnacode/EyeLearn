-- Railway Database Schema Alignment Migration
-- Generated: 2025-11-27
-- Purpose: Align Railway database with codebase schema

-- ============================================
-- ISSUE 1: Remove deprecated total_focus_time column
-- ============================================
-- The eye_tracking_analytics table has both:
--   - total_focus_time (OLD/DEPRECATED) - never updated by code
--   - total_focused_time (NEW/CURRENT) - used by all code
-- 
-- All code uses total_focused_time, so we can safely remove the old column
ALTER TABLE `eye_tracking_analytics` DROP COLUMN IF EXISTS `total_focus_time`;

-- ============================================
-- ISSUE 2: Add used_at column to final_quiz_retakes
-- ============================================
-- The code (user/Smodulepart.php) uses used_at column in UPDATE queries,
-- but the Railway database doesn't have this column yet.
-- This column tracks when a retake was actually used.
ALTER TABLE `final_quiz_retakes` 
ADD COLUMN IF NOT EXISTS `used_at` TIMESTAMP NULL DEFAULT NULL 
COMMENT 'Timestamp when the retake was used';

-- ============================================
-- VERIFICATION QUERIES (Run these to verify)
-- ============================================

-- Verify total_focus_time is removed
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'eye_tracking_analytics' 
-- AND COLUMN_NAME = 'total_focus_time';
-- Expected: 0 rows

-- Verify used_at column exists
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'final_quiz_retakes' 
-- AND COLUMN_NAME = 'used_at';
-- Expected: 1 row with TIMESTAMP, NULL, NULL

-- Eye Tracking System Database Schema
-- Add this to your existing database

-- Create eye tracking sessions table
CREATE TABLE IF NOT EXISTS `eye_tracking_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `total_time_seconds` int(11) DEFAULT 0,
  `session_type` enum('viewing','pause','resume') DEFAULT 'viewing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_module` (`user_id`, `module_id`),
  KEY `idx_user_section` (`user_id`, `section_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create eye tracking analytics table for aggregated data
CREATE TABLE IF NOT EXISTS `eye_tracking_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `total_focus_time` int(11) DEFAULT 0,
  `session_count` int(11) DEFAULT 0,
  `average_session_time` int(11) DEFAULT 0,
  `max_continuous_time` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_module_date` (`user_id`, `module_id`, `section_id`, `date`),
  KEY `idx_user_date` (`user_id`, `date`),
  KEY `idx_module_date` (`module_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints if the referenced tables exist
-- Note: Uncomment these if you have users and modules tables with proper structure
-- ALTER TABLE `eye_tracking_sessions` 
--   ADD CONSTRAINT `fk_eye_tracking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_eye_tracking_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

-- ALTER TABLE `eye_tracking_analytics`
--   ADD CONSTRAINT `fk_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_analytics_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

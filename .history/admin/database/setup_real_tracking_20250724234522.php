<?php
// Setup real tracking database structure
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'elearn_db');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed']);
    exit();
}

$tables_created = [];
$errors = [];

try {
    // 1. Create user_sessions table for tracking study sessions
    $sql1 = "CREATE TABLE IF NOT EXISTS `user_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `module_id` int(11) DEFAULT NULL,
        `session_start` timestamp NOT NULL,
        `session_end` timestamp NULL,
        `total_duration_seconds` int(11) DEFAULT 0,
        `focused_duration_seconds` int(11) DEFAULT 0,
        `unfocused_duration_seconds` int(11) DEFAULT 0,
        `focus_percentage` decimal(5,2) DEFAULT 0.00,
        `session_type` enum('study', 'quiz', 'review') DEFAULT 'study',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_session_start` (`session_start`),
        KEY `idx_user_date` (`user_id`, `session_start`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        $tables_created[] = 'user_sessions';
    } else {
        $errors[] = 'user_sessions: ' . $conn->error;
    }

    // 2. Create daily_analytics table for aggregated daily stats
    $sql2 = "CREATE TABLE IF NOT EXISTS `daily_analytics` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `date` date NOT NULL,
        `total_study_time_seconds` int(11) DEFAULT 0,
        `total_focused_time_seconds` int(11) DEFAULT 0,
        `total_unfocused_time_seconds` int(11) DEFAULT 0,
        `session_count` int(11) DEFAULT 0,
        `average_focus_percentage` decimal(5,2) DEFAULT 0.00,
        `longest_session_seconds` int(11) DEFAULT 0,
        `modules_studied` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_date` (`user_id`, `date`),
        KEY `idx_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        $tables_created[] = 'daily_analytics';
    } else {
        $errors[] = 'daily_analytics: ' . $conn->error;
    }

    // 3. Create focus_events table for detailed tracking
    $sql3 = "CREATE TABLE IF NOT EXISTS `focus_events` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `session_id` int(11) NOT NULL,
        `event_type` enum('focus_start', 'focus_end', 'unfocus_start', 'unfocus_end') NOT NULL,
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `duration_seconds` int(11) DEFAULT NULL,
        `confidence_score` decimal(3,2) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_session_id` (`session_id`),
        KEY `idx_timestamp` (`timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql3)) {
        $tables_created[] = 'focus_events';
    } else {
        $errors[] = 'focus_events: ' . $conn->error;
    }

    // 4. Insert sample data for demonstration
    $insert_samples = "INSERT IGNORE INTO `user_sessions` 
        (`user_id`, `module_id`, `session_start`, `session_end`, `total_duration_seconds`, `focused_duration_seconds`, `unfocused_duration_seconds`, `focus_percentage`, `session_type`) VALUES
        (4, 1, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 1 HOUR, 3600, 2880, 720, 80.00, 'study'),
        (4, 2, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 2 HOUR, 3600, 2700, 900, 75.00, 'study'),
        (4, 1, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 30 MINUTE, 1800, 1440, 360, 80.00, 'quiz'),
        (4, 3, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 1 HOUR, 3600, 2520, 1080, 70.00, 'study'),
        (4, 2, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 30 MINUTE, 1800, 1350, 450, 75.00, 'review'),
        (4, 1, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 2 HOUR, 3600, 2880, 720, 80.00, 'study'),
        (4, 3, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 1 HOUR, 3600, 2520, 1080, 70.00, 'study'),
        
        (5, 1, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 2 HOUR, 3600, 2700, 900, 75.00, 'study'),
        (5, 2, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 1 HOUR, 3600, 3060, 540, 85.00, 'study'),
        (5, 1, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 30 MINUTE, 1800, 1530, 270, 85.00, 'quiz'),
        (5, 3, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 2 HOUR, 3600, 2880, 720, 80.00, 'study'),
        (5, 2, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 1 HOUR, 3600, 3060, 540, 85.00, 'study'),
        (5, 1, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 30 MINUTE, 1800, 1530, 270, 85.00, 'review'),
        (5, 3, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 2 HOUR, 3600, 2880, 720, 80.00, 'study'),
        
        (6, 1, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 0 DAY) - INTERVAL 1 HOUR, 3600, 2520, 1080, 70.00, 'study'),
        (6, 2, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 1 DAY) - INTERVAL 2 HOUR, 3600, 2520, 1080, 70.00, 'study'),
        (6, 1, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 2 DAY) - INTERVAL 1 HOUR, 3600, 2520, 1080, 70.00, 'quiz'),
        (6, 3, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 3 DAY) - INTERVAL 30 MINUTE, 1800, 1260, 540, 70.00, 'study'),
        (6, 2, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 3 HOUR, DATE_SUB(NOW(), INTERVAL 4 DAY) - INTERVAL 2 HOUR, 3600, 2520, 1080, 70.00, 'study'),
        (6, 1, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 2 HOUR, DATE_SUB(NOW(), INTERVAL 5 DAY) - INTERVAL 1 HOUR, 3600, 2520, 1080, 70.00, 'review'),
        (6, 3, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 1 HOUR, DATE_SUB(NOW(), INTERVAL 6 DAY) - INTERVAL 30 MINUTE, 1800, 1260, 540, 70.00, 'study')";
    
    if ($conn->query($insert_samples)) {
        $tables_created[] = 'sample_data_inserted';
    } else {
        $errors[] = 'sample_data: ' . $conn->error;
    }

    // 5. Create/update daily analytics from session data
    $update_analytics = "INSERT INTO daily_analytics 
        (user_id, date, total_study_time_seconds, total_focused_time_seconds, total_unfocused_time_seconds, 
         session_count, average_focus_percentage, longest_session_seconds, modules_studied)
        SELECT 
            user_id,
            DATE(session_start) as date,
            SUM(total_duration_seconds) as total_study_time_seconds,
            SUM(focused_duration_seconds) as total_focused_time_seconds,
            SUM(unfocused_duration_seconds) as total_unfocused_time_seconds,
            COUNT(*) as session_count,
            AVG(focus_percentage) as average_focus_percentage,
            MAX(total_duration_seconds) as longest_session_seconds,
            COUNT(DISTINCT module_id) as modules_studied
        FROM user_sessions 
        WHERE session_end IS NOT NULL
        GROUP BY user_id, DATE(session_start)
        ON DUPLICATE KEY UPDATE
            total_study_time_seconds = VALUES(total_study_time_seconds),
            total_focused_time_seconds = VALUES(total_focused_time_seconds),
            total_unfocused_time_seconds = VALUES(total_unfocused_time_seconds),
            session_count = VALUES(session_count),
            average_focus_percentage = VALUES(average_focus_percentage),
            longest_session_seconds = VALUES(longest_session_seconds),
            modules_studied = VALUES(modules_studied),
            updated_at = CURRENT_TIMESTAMP";
    
    if ($conn->query($update_analytics)) {
        $tables_created[] = 'analytics_updated';
    } else {
        $errors[] = 'analytics_update: ' . $conn->error;
    }

    echo json_encode([
        'success' => true,
        'tables_created' => $tables_created,
        'errors' => $errors,
        'message' => 'Real tracking system setup completed'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'tables_created' => $tables_created,
        'errors' => $errors
    ]);
}

$conn->close();
?>

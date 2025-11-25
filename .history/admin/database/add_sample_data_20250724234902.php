<?php
// Add more sample session data for realistic testing
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'elearn_db');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed']);
    exit();
}

$sessions_added = 0;

try {
    // Add more varied session data for students 7-10 (who currently have no data)
    $additional_sessions = [
        // Student 7 - moderate performer
        [7, 1, 'NOW() - INTERVAL 0 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 0 DAY - INTERVAL 30 MINUTE', 1800, 1080, 720, 60.00, 'study'],
        [7, 2, 'NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR', 'NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR', 3600, 2160, 1440, 60.00, 'study'],
        [7, 1, 'NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 2 DAY - INTERVAL 30 MINUTE', 1800, 1080, 720, 60.00, 'quiz'],
        [7, 3, 'NOW() - INTERVAL 3 DAY - INTERVAL 3 HOUR', 'NOW() - INTERVAL 3 DAY - INTERVAL 2 HOUR', 3600, 2160, 1440, 60.00, 'study'],
        
        // Student 8 - high performer
        [8, 1, 'NOW() - INTERVAL 0 DAY - INTERVAL 2 HOUR', 'NOW() - INTERVAL 0 DAY - INTERVAL 1 HOUR', 3600, 3240, 360, 90.00, 'study'],
        [8, 2, 'NOW() - INTERVAL 1 DAY - INTERVAL 3 HOUR', 'NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR', 3600, 3240, 360, 90.00, 'study'],
        [8, 1, 'NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR', 'NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR', 3600, 3240, 360, 90.00, 'quiz'],
        [8, 3, 'NOW() - INTERVAL 3 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 3 DAY - INTERVAL 30 MINUTE', 1800, 1620, 180, 90.00, 'study'],
        [8, 2, 'NOW() - INTERVAL 4 DAY - INTERVAL 3 HOUR', 'NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR', 3600, 3240, 360, 90.00, 'study'],
        
        // Student 9 - low performer
        [9, 1, 'NOW() - INTERVAL 0 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 0 DAY - INTERVAL 45 MINUTE', 900, 360, 540, 40.00, 'study'],
        [9, 2, 'NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 1 DAY - INTERVAL 45 MINUTE', 900, 360, 540, 40.00, 'study'],
        [9, 1, 'NOW() - INTERVAL 3 DAY - INTERVAL 1 HOUR', 'NOW() - INTERVAL 3 DAY - INTERVAL 45 MINUTE', 900, 360, 540, 40.00, 'study'],
        
        // Student 10 - inconsistent performer
        [10, 1, 'NOW() - INTERVAL 0 DAY - INTERVAL 2 HOUR', 'NOW() - INTERVAL 0 DAY - INTERVAL 1 HOUR', 3600, 1800, 1800, 50.00, 'study'],
        [10, 2, 'NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR', 'NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR', 3600, 2520, 1080, 70.00, 'study'],
        [10, 3, 'NOW() - INTERVAL 5 DAY - INTERVAL 2 HOUR', 'NOW() - INTERVAL 5 DAY - INTERVAL 1 HOUR', 3600, 2880, 720, 80.00, 'study'],
    ];
    
    foreach ($additional_sessions as $session) {
        $sql = "INSERT INTO `user_sessions` 
            (`user_id`, `module_id`, `session_start`, `session_end`, `total_duration_seconds`, `focused_duration_seconds`, `unfocused_duration_seconds`, `focus_percentage`, `session_type`) 
            VALUES (?, ?, {$session[2]}, {$session[3]}, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiids", 
            $session[0], $session[1], $session[4], $session[5], $session[6], $session[7], $session[8]
        );
        
        if ($stmt->execute()) {
            $sessions_added++;
        }
    }
    
    // Update daily analytics with new data
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
    
    $analytics_updated = $conn->query($update_analytics);
    
    echo json_encode([
        'success' => true,
        'sessions_added' => $sessions_added,
        'analytics_updated' => $analytics_updated ? 'yes' : 'no',
        'message' => 'Additional sample data added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'sessions_added' => $sessions_added
    ]);
}

$conn->close();
?>

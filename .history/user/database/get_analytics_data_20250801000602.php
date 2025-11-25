<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get overall user statistics
    $stats_query = "
        SELECT 
            COUNT(DISTINCT ets.module_id) as modules_studied,
            SUM(ets.total_time_seconds) as total_study_time,
            COUNT(ets.id) as total_sessions,
            AVG(ets.total_time_seconds) as avg_session_time,
            MAX(ets.total_time_seconds) as longest_session,
            MIN(ets.created_at) as first_session_date,
            MAX(ets.last_updated) as last_session_date
        FROM eye_tracking_sessions ets 
        WHERE ets.user_id = ?
    ";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $overall_stats = $stats_result->fetch_assoc();

    // Get recent session data for analytics
    $analytics_query = "
        SELECT 
            eta.date,
            eta.total_focus_time,
            eta.session_count,
            eta.average_session_time,
            eta.max_continuous_time,
            m.title as module_title
        FROM eye_tracking_analytics eta
        JOIN modules m ON eta.module_id = m.id
        WHERE eta.user_id = ?
        ORDER BY eta.date DESC
        LIMIT 30
    ";
    
    $stmt = $conn->prepare($analytics_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $analytics_result = $stmt->get_result();
    
    $analytics_data = [];
    while ($row = $analytics_result->fetch_assoc()) {
        $analytics_data[] = $row;
    }

    // Get module-specific performance
    $module_performance_query = "
        SELECT 
            m.title as module_title,
            m.id as module_id,
            SUM(ets.total_time_seconds) as total_time,
            COUNT(ets.id) as session_count,
            AVG(ets.total_time_seconds) as avg_session_time,
            MAX(ets.total_time_seconds) as best_session,
            MAX(ets.last_updated) as last_studied
        FROM eye_tracking_sessions ets
        JOIN modules m ON ets.module_id = m.id
        WHERE ets.user_id = ?
        GROUP BY ets.module_id, m.title, m.id
        ORDER BY total_time DESC
    ";
    
    $stmt = $conn->prepare($module_performance_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $module_result = $stmt->get_result();
    
    $module_performance = [];
    while ($row = $module_result->fetch_assoc()) {
        $module_performance[] = $row;
    }

    // Get focus trends (last 7 days)
    $focus_trends_query = "
        SELECT 
            DATE(eta.date) as study_date,
            SUM(eta.total_focus_time) as daily_focus_time,
            SUM(eta.session_count) as daily_sessions,
            AVG(eta.average_session_time) as avg_session_duration
        FROM eye_tracking_analytics eta
        WHERE eta.user_id = ? 
        AND eta.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(eta.date)
        ORDER BY study_date ASC
    ";
    
    $stmt = $conn->prepare($focus_trends_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $trends_result = $stmt->get_result();
    
    $focus_trends = [];
    while ($row = $trends_result->fetch_assoc()) {
        $focus_trends[] = $row;
    }

    // Calculate derived metrics
    $total_hours = round(($overall_stats['total_study_time'] ?? 0) / 3600, 1);
    $avg_session_minutes = round(($overall_stats['avg_session_time'] ?? 0) / 60, 1);
    $longest_session_minutes = round(($overall_stats['longest_session'] ?? 0) / 60, 1);
    
    // Calculate focus efficiency (this could be enhanced with actual focus/unfocus data)
    $focus_efficiency = 0;
    if (count($analytics_data) > 0) {
        $total_focus = array_sum(array_column($analytics_data, 'total_focus_time'));
        $total_time = array_sum(array_column($analytics_data, 'average_session_time')) * array_sum(array_column($analytics_data, 'session_count'));
        $focus_efficiency = $total_time > 0 ? round(($total_focus / $total_time) * 100, 1) : 0;
    }

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'overall_stats' => [
                'modules_studied' => $overall_stats['modules_studied'] ?? 0,
                'total_study_time_hours' => $total_hours,
                'total_sessions' => $overall_stats['total_sessions'] ?? 0,
                'avg_session_minutes' => $avg_session_minutes,
                'longest_session_minutes' => $longest_session_minutes,
                'focus_efficiency_percent' => $focus_efficiency,
                'first_session' => $overall_stats['first_session_date'],
                'last_session' => $overall_stats['last_session_date']
            ],
            'recent_analytics' => $analytics_data,
            'module_performance' => $module_performance,
            'focus_trends' => $focus_trends,
            'insights' => [
                'best_study_day' => null, // Will be calculated from trends
                'improvement_suggestion' => null,
                'streak_info' => null
            ]
        ]
    ];

    // Calculate insights
    if (count($focus_trends) > 0) {
        // Find best study day
        $best_day = array_reduce($focus_trends, function($carry, $item) {
            return ($carry === null || $item['daily_focus_time'] > $carry['daily_focus_time']) ? $item : $carry;
        });
        $response['data']['insights']['best_study_day'] = $best_day;
        
        // Simple improvement suggestion
        if ($focus_efficiency < 60) {
            $response['data']['insights']['improvement_suggestion'] = "Try shorter, more focused study sessions to improve concentration.";
        } else if ($focus_efficiency > 80) {
            $response['data']['insights']['improvement_suggestion'] = "Excellent focus! Consider challenging yourself with more complex modules.";
        } else {
            $response['data']['insights']['improvement_suggestion'] = "Good focus levels. Regular breaks might help maintain concentration.";
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch analytics data: ' . $e->getMessage()]);
}

$conn->close();
?>

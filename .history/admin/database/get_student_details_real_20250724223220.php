<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    }

    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    
    if (!$student_id) {
        throw new Exception('Student ID is required');
    }
    
    // Get basic student info
    $studentSql = "SELECT id, first_name, last_name, email, gender, created_at as registration_date 
                   FROM users WHERE id = ? AND role = 'student'";
    $stmt = $conn->prepare($studentSql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    
    if ($studentResult->num_rows === 0) {
        throw new Exception('Student not found');
    }
    
    $student = $studentResult->fetch_assoc();
    $student['name'] = $student['first_name'] . ' ' . $student['last_name'];
    $student['student_id'] = 'ST-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT);
    
    // Get today's statistics
    $todaySql = "SELECT 
                    COALESCE(SUM(CASE WHEN is_focused = 1 THEN duration_seconds ELSE 0 END), 0) as focused_seconds,
                    COALESCE(SUM(CASE WHEN is_focused = 0 THEN duration_seconds ELSE 0 END), 0) as unfocused_seconds,
                    COALESCE(SUM(duration_seconds), 0) as total_seconds,
                    COUNT(DISTINCT session_id) as sessions
                 FROM eye_tracking_sessions 
                 WHERE user_id = ? AND DATE(created_at) = CURDATE()";
    
    $stmt = $conn->prepare($todaySql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $todayResult = $stmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    
    $todayFocusedMinutes = round($todayData['focused_seconds'] / 60);
    $todayUnfocusedMinutes = round($todayData['unfocused_seconds'] / 60);
    $todayTotalMinutes = round($todayData['total_seconds'] / 60);
    $todayFocusPercentage = $todayData['total_seconds'] > 0 ? 
        round(($todayData['focused_seconds'] / $todayData['total_seconds']) * 100, 1) : 0;
    $todayAvgSession = $todayData['sessions'] > 0 ? 
        round($todayTotalMinutes / $todayData['sessions']) : 0;
    
    // Get weekly statistics (last 7 days)
    $weeklySql = "SELECT 
                     COALESCE(SUM(CASE WHEN is_focused = 1 THEN duration_seconds ELSE 0 END), 0) as focused_seconds,
                     COALESCE(SUM(duration_seconds), 0) as total_seconds,
                     COUNT(DISTINCT session_id) as sessions,
                     COUNT(DISTINCT DATE(created_at)) as active_days
                  FROM eye_tracking_sessions 
                  WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    
    $stmt = $conn->prepare($weeklySql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $weeklyResult = $stmt->get_result();
    $weeklyData = $weeklyResult->fetch_assoc();
    
    $weeklyFocusedMinutes = round($weeklyData['focused_seconds'] / 60);
    $weeklyTotalMinutes = round($weeklyData['total_seconds'] / 60);
    
    // Get best study time
    $bestTimeSql = "SELECT HOUR(created_at) as hour, 
                           AVG(CASE WHEN is_focused = 1 THEN 1 ELSE 0 END) as focus_rate
                    FROM eye_tracking_sessions 
                    WHERE user_id = ? 
                    GROUP BY HOUR(created_at)
                    ORDER BY focus_rate DESC, COUNT(*) DESC
                    LIMIT 1";
    
    $stmt = $conn->prepare($bestTimeSql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $bestTimeResult = $stmt->get_result();
    $bestTimeData = $bestTimeResult->fetch_assoc();
    
    $bestStudyTime = 'No data';
    if ($bestTimeData) {
        $hour = $bestTimeData['hour'];
        $bestStudyTime = date('g:i A', mktime($hour, 0, 0)) . ' - ' . date('g:i A', mktime($hour + 1, 0, 0));
    }
    
    // Get module performance
    $modulesSql = "SELECT 
                      m.title as module_name,
                      COALESCE(AVG(up.completion_percentage), 0) as completion_percentage,
                      COALESCE(SUM(CASE WHEN ets.is_focused = 1 THEN ets.duration_seconds ELSE 0 END), 0) as focused_seconds,
                      COUNT(DISTINCT ets.session_id) as study_sessions,
                      CASE 
                          WHEN SUM(ets.duration_seconds) > 0 THEN 
                              ROUND((SUM(CASE WHEN ets.is_focused = 1 THEN ets.duration_seconds ELSE 0 END) / SUM(ets.duration_seconds)) * 100, 1)
                          ELSE 0 
                      END as focus_percentage
                   FROM modules m
                   LEFT JOIN user_progress up ON m.id = up.module_id AND up.user_id = ?
                   LEFT JOIN eye_tracking_sessions ets ON ets.user_id = ? AND ets.module_id = m.id
                   WHERE m.is_published = 1
                   GROUP BY m.id, m.title
                   HAVING completion_percentage > 0 OR study_sessions > 0
                   ORDER BY completion_percentage DESC";
    
    $stmt = $conn->prepare($modulesSql);
    $stmt->bind_param('ii', $student_id, $student_id);
    $stmt->execute();
    $modulesResult = $stmt->get_result();
    
    $modules = [];
    while ($row = $modulesResult->fetch_assoc()) {
        $modules[] = [
            'module_name' => $row['module_name'],
            'completion_percentage' => round($row['completion_percentage']),
            'avg_focused_minutes' => round($row['focused_seconds'] / 60),
            'study_sessions' => $row['study_sessions'],
            'focus_percentage' => $row['focus_percentage']
        ];
    }
    
    // Get recent activity (last 5 sessions)
    $recentSql = "SELECT 
                     m.title as module_name,
                     ets.created_at,
                     SUM(ets.duration_seconds) as total_time,
                     CASE 
                         WHEN SUM(ets.duration_seconds) > 0 THEN 
                             ROUND((SUM(CASE WHEN ets.is_focused = 1 THEN ets.duration_seconds ELSE 0 END) / SUM(ets.duration_seconds)) * 100, 1)
                         ELSE 0 
                     END as focus_percentage
                  FROM eye_tracking_sessions ets
                  LEFT JOIN modules m ON ets.module_id = m.id
                  WHERE ets.user_id = ?
                  GROUP BY ets.session_id, m.title, DATE(ets.created_at)
                  ORDER BY ets.created_at DESC
                  LIMIT 5";
    
    $stmt = $conn->prepare($recentSql);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $recentResult = $stmt->get_result();
    
    $recentActivity = [];
    while ($row = $recentResult->fetch_assoc()) {
        $recentActivity[] = [
            'module_name' => $row['module_name'] ?: 'General Study',
            'date' => $row['created_at'],
            'total_time' => round($row['total_time'] / 60), // Convert to minutes
            'focus_percentage' => $row['focus_percentage']
        ];
    }
    
    // Generate AI insights
    $insights = [];
    
    if ($todayFocusPercentage > 80) {
        $insights[] = "🎯 Excellent focus today! " . $todayFocusPercentage . "% focus rate shows great concentration.";
    } elseif ($todayFocusPercentage > 60) {
        $insights[] = "👍 Good focus today with " . $todayFocusPercentage . "% focus rate. Keep it up!";
    } elseif ($todayFocusPercentage > 0) {
        $insights[] = "💡 Focus could be improved. Consider taking breaks and minimizing distractions.";
    }
    
    if ($weeklyData['active_days'] >= 5) {
        $insights[] = "🔥 Great consistency! Active " . $weeklyData['active_days'] . " days this week.";
    } elseif ($weeklyData['active_days'] >= 3) {
        $insights[] = "📈 Good study routine with " . $weeklyData['active_days'] . " active days this week.";
    }
    
    if (count($modules) > 0) {
        $avgCompletion = array_sum(array_column($modules, 'completion_percentage')) / count($modules);
        if ($avgCompletion > 75) {
            $insights[] = "🌟 Excellent progress across modules with " . round($avgCompletion) . "% average completion.";
        }
    }
    
    if (empty($insights)) {
        $insights[] = "📚 Keep studying regularly to build momentum and improve focus metrics.";
    }
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'today' => [
            'focused_time' => $todayFocusedMinutes,
            'unfocused_time' => $todayUnfocusedMinutes,
            'focus_percentage' => $todayFocusPercentage,
            'total_sessions' => $todayData['sessions'],
            'avg_session_time' => $todayAvgSession
        ],
        'weekly' => [
            'focused_time' => $weeklyFocusedMinutes,
            'total_sessions' => $weeklyData['sessions'],
            'active_days' => $weeklyData['active_days']
        ],
        'best_study_time' => $bestStudyTime,
        'module_performance' => $modules,
        'recent_activity' => $recentActivity,
        'insights' => $insights
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>

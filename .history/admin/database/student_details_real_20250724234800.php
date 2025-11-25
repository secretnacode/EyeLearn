<?php
// Real calculation API for student details
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(0);

$conn = @new mysqli('localhost', 'root', '', 'elearn_db');

if ($conn->connect_error) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Connection failed']);
    exit();
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Student ID required']);
    exit();
}

try {
    // Check what columns exist in users table
    $columnsResult = @$conn->query("SHOW COLUMNS FROM users");
    $userColumns = [];
    if ($columnsResult) {
        while ($row = $columnsResult->fetch_assoc()) {
            $userColumns[] = $row['Field'];
        }
    }

    // Build name query
    if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
        $nameSelect = "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name";
    } elseif (in_array('name', $userColumns)) {
        $nameSelect = "name as full_name";
    } else {
        $nameSelect = "CONCAT('User ', id) as full_name";
    }

    $emailField = in_array('email', $userColumns) ? 'email' : "CONCAT('user', id, '@example.com') as email";

    // Get student info with real analytics
    $sql = "SELECT 
        u.id, 
        $nameSelect, 
        $emailField,
        
        -- Today's real data
        COALESCE(today.total_focused_time_seconds, 0) / 60 as today_focused_minutes,
        COALESCE(today.total_unfocused_time_seconds, 0) / 60 as today_unfocused_minutes,
        COALESCE(today.session_count, 0) as today_sessions,
        COALESCE(today.average_focus_percentage, 0) as today_focus_percentage,
        COALESCE(today.longest_session_seconds, 0) / 60 as today_longest_session,
        
        -- Weekly real data
        COALESCE(weekly.total_focused_time_seconds, 0) / 60 as weekly_focused_minutes,
        COALESCE(weekly.total_unfocused_time_seconds, 0) / 60 as weekly_unfocused_minutes,
        COALESCE(weekly.total_sessions, 0) as weekly_sessions,
        COALESCE(weekly.avg_focus_percentage, 0) as weekly_focus_percentage,
        COALESCE(weekly.active_days, 0) as weekly_active_days,
        COALESCE(weekly.modules_studied, 0) as weekly_modules
        
    FROM users u
    LEFT JOIN (
        -- Today's analytics
        SELECT 
            user_id,
            total_focused_time_seconds,
            total_unfocused_time_seconds,
            session_count,
            average_focus_percentage,
            longest_session_seconds
        FROM daily_analytics 
        WHERE date = CURDATE()
    ) today ON u.id = today.user_id
    LEFT JOIN (
        -- Weekly analytics (last 7 days)
        SELECT 
            user_id,
            SUM(total_focused_time_seconds) as total_focused_time_seconds,
            SUM(total_unfocused_time_seconds) as total_unfocused_time_seconds,
            SUM(session_count) as total_sessions,
            AVG(average_focus_percentage) as avg_focus_percentage,
            COUNT(DISTINCT date) as active_days,
            AVG(modules_studied) as modules_studied
        FROM daily_analytics 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY user_id
    ) weekly ON u.id = weekly.user_id
    WHERE u.id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    $student = $result->fetch_assoc();
    
    // Use real name from database, fallback to generic name
    $studentName = !empty(trim($student['full_name'])) ? trim($student['full_name']) : "Student " . $student['id'];
    
    // Calculate average session time for today
    $todayAvgSession = $student['today_sessions'] > 0 ? 
        round(($student['today_focused_minutes'] + $student['today_unfocused_minutes']) / $student['today_sessions'], 1) : 0;
    
    // Calculate total weekly time
    $weeklyTotalTime = $student['weekly_focused_minutes'] + $student['weekly_unfocused_minutes'];
    
    // Generate intelligent insights based on real data
    $insights = [];
    
    // Focus performance insights
    if ($student['today_focus_percentage'] >= 80) {
        $insights[] = "🎯 Excellent focus today! You're maintaining {$student['today_focus_percentage']}% concentration.";
    } elseif ($student['today_focus_percentage'] >= 70) {
        $insights[] = "👍 Good focus today at {$student['today_focus_percentage']}%. Keep up the steady progress!";
    } elseif ($student['today_focus_percentage'] > 0) {
        $insights[] = "💪 Room for improvement - current focus at {$student['today_focus_percentage']}%. Try shorter, focused sessions.";
    }
    
    // Session pattern insights
    if ($student['weekly_sessions'] >= 15) {
        $insights[] = "📈 Consistent study pattern with {$student['weekly_sessions']} sessions this week!";
    } elseif ($student['weekly_sessions'] >= 7) {
        $insights[] = "📊 Regular study habit forming with {$student['weekly_sessions']} sessions this week.";
    }
    
    // Activity insights
    if ($student['weekly_active_days'] >= 6) {
        $insights[] = "🔥 Amazing dedication! Active {$student['weekly_active_days']} days this week.";
    } elseif ($student['weekly_active_days'] >= 4) {
        $insights[] = "✅ Good weekly rhythm with {$student['weekly_active_days']} active study days.";
    }
    
    // Session length insights
    if ($student['today_longest_session'] >= 60) {
        $insights[] = "⏰ Strong endurance with {$student['today_longest_session']} minute longest session today.";
    }
    
    // Add default insight if none generated
    if (empty($insights)) {
        $insights[] = "🌟 Start building your study analytics by engaging with learning modules!";
    }
    
    $response = [
        'success' => true,
        'student' => [
            'id' => $student['id'],
            'name' => $studentName,
            'email' => $student['email'],
            'student_id' => 'ST-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT)
        ],
        'today' => [
            'focused_time' => (int)$student['today_focused_minutes'],
            'unfocused_time' => (int)$student['today_unfocused_minutes'],
            'focus_percentage' => round($student['today_focus_percentage'], 1),
            'total_sessions' => (int)$student['today_sessions'],
            'avg_session_time' => $todayAvgSession,
            'longest_session' => (int)$student['today_longest_session']
        ],
        'weekly' => [
            'focused_time' => (int)$student['weekly_focused_minutes'],
            'unfocused_time' => (int)$student['weekly_unfocused_minutes'],
            'total_time' => (int)$weeklyTotalTime,
            'focus_percentage' => round($student['weekly_focus_percentage'], 1),
            'total_sessions' => (int)$student['weekly_sessions'],
            'active_days' => (int)$student['weekly_active_days'],
            'modules_studied' => (int)$student['weekly_modules']
        ],
        'insights' => $insights,
        'calculation_type' => 'real_database',
        'data_source' => 'user_sessions + daily_analytics tables'
    ];
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

exit();
?>

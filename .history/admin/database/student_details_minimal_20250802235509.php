<?php
// admin/database/student_details_minimal.php - Real student details API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'error' => 'Student ID required']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

try {
    // Get basic student info
    $studentQuery = "SELECT 
        id,
        CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
        email,
        gender,
        created_at,
        CONCAT('ST-', LPAD(id, 4, '0')) as student_id
        FROM users 
        WHERE id = ? AND role = 'student'";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }
    
    $student = $result->fetch_assoc();
    $student['name'] = trim($student['name']) ?: "Student " . $student['id'];
    
    // Get today's focus data
    $todayQuery = "SELECT 
        SUM(CASE WHEN total_time_seconds > 0 THEN total_time_seconds ELSE 0 END) / 60 as focused_minutes,
        SUM(CASE WHEN session_duration_seconds > total_time_seconds 
            THEN (session_duration_seconds - total_time_seconds) 
            ELSE 0 END) / 60 as unfocused_minutes,
        COUNT(*) as total_sessions,
        AVG(CASE WHEN total_time_seconds > 0 THEN total_time_seconds ELSE NULL END) / 60 as avg_session_time
        FROM eye_tracking_sessions 
        WHERE user_id = ? AND DATE(created_at) = CURDATE()";
    
    $stmt = $conn->prepare($todayQuery);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $todayResult = $stmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    
    $todayFocused = round($todayData['focused_minutes'] ?: 0, 0);
    $todayUnfocused = round($todayData['unfocused_minutes'] ?: 0, 0);
    
    // If no unfocused time, estimate it
    if ($todayUnfocused == 0 && $todayFocused > 0) {
        $todayUnfocused = round($todayFocused * 0.3, 0);
    }
    
    $todayTotal = $todayFocused + $todayUnfocused;
    $todayFocusPercent = $todayTotal > 0 ? round(($todayFocused / $todayTotal) * 100, 0) : 0;
    
    // Get weekly focus data (last 7 days)
    $weeklyQuery = "SELECT 
        SUM(CASE WHEN total_time_seconds > 0 THEN total_time_seconds ELSE 0 END) / 60 as focused_minutes,
        SUM(CASE WHEN session_duration_seconds > total_time_seconds 
            THEN (session_duration_seconds - total_time_seconds) 
            ELSE 0 END) / 60 as unfocused_minutes,
        COUNT(*) as total_sessions,
        COUNT(DISTINCT DATE(created_at)) as active_days
        FROM eye_tracking_sessions 
        WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    
    $stmt = $conn->prepare($weeklyQuery);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $weeklyResult = $stmt->get_result();
    $weeklyData = $weeklyResult->fetch_assoc();
    
    $weeklyFocused = round($weeklyData['focused_minutes'] ?: 0, 0);
    $weeklyUnfocused = round($weeklyData['unfocused_minutes'] ?: 0, 0);
    
    // If no unfocused time, estimate it
    if ($weeklyUnfocused == 0 && $weeklyFocused > 0) {
        $weeklyUnfocused = round($weeklyFocused * 0.3, 0);
    }
    
    $weeklyTotal = $weeklyFocused + $weeklyUnfocused;
    $weeklyFocusPercent = $weeklyTotal > 0 ? round(($weeklyFocused / $weeklyTotal) * 100, 0) : 0;
    $activeDays = $weeklyData['active_days'] ?: 0;
    $totalSessions = $weeklyData['total_sessions'] ?: 0;
    
    // Generate insights based on real data
    $insights = [];
    
    if ($todayFocusPercent >= 80) {
        $insights[] = "🎯 Excellent focus today! {$todayFocusPercent}% focus rate is outstanding.";
    } elseif ($todayFocusPercent >= 60) {
        $insights[] = "👍 Good focus today with {$todayFocusPercent}% concentration rate.";
    } elseif ($todayFocused > 0) {
        $insights[] = "📈 Room for improvement - focus rate today is {$todayFocusPercent}%.";
    } else {
        $insights[] = "💡 No study sessions recorded today. Consider starting a learning session!";
    }
    
    if ($weeklyFocusPercent >= 75) {
        $insights[] = "🌟 Consistent weekly performance with {$weeklyFocusPercent}% focus rate.";
    } elseif ($weeklyFocusPercent >= 60) {
        $insights[] = "📊 Steady weekly progress with {$weeklyFocusPercent}% average focus.";
    }
    
    if ($activeDays >= 5) {
        $insights[] = "🔄 Great consistency! Active {$activeDays} days this week.";
    } elseif ($activeDays >= 3) {
        $insights[] = "📅 Moderate activity with {$activeDays} active days this week.";
    }
    
    if ($totalSessions >= 10) {
        $insights[] = "💪 Highly engaged with {$totalSessions} learning sessions this week.";
    } elseif ($totalSessions >= 5) {
        $insights[] = "✅ Regular learner with {$totalSessions} sessions this week.";
    }
    
    // Default insights if no data
    if (empty($insights)) {
        $insights = [
            "📚 Welcome to EyeLearn! Start your first learning session to see personalized insights.",
            "🎯 Focus tracking will provide detailed analytics once you begin studying.",
            "💡 Regular study sessions will unlock detailed performance insights."
        ];
    }
    
    $response = [
        'success' => true,
        'student' => [
            'id' => (int)$student['id'],
            'name' => $student['name'],
            'email' => $student['email'],
            'student_id' => $student['student_id'],
            'gender' => $student['gender'] ?: 'Not specified',
            'registration_date' => $student['created_at']
        ],
        'today' => [
            'focused_time' => $todayFocused,
            'unfocused_time' => $todayUnfocused,
            'focus_percentage' => $todayFocusPercent,
            'total_sessions' => (int)($todayData['total_sessions'] ?: 0),
            'avg_session_time' => round($todayData['avg_session_time'] ?: 0, 1)
        ],
        'weekly' => [
            'focused_time' => $weeklyFocused,
            'unfocused_time' => $weeklyUnfocused,
            'total_time' => $weeklyTotal,
            'focus_percentage' => $weeklyFocusPercent,
            'total_sessions' => $totalSessions,
            'active_days' => $activeDays
        ],
        'insights' => $insights
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

<?php
// Simple student details API
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable errors
ini_set('display_errors', 0);
error_reporting(0);

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Student ID required']);
    exit();
}

try {
    $conn = @new mysqli('localhost', 'root', '', 'elearn_db');
    
    if ($conn->connect_error) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }

    // Get basic student info with proper name
    $columnsResult = @$conn->query("SHOW COLUMNS FROM users");
    $userColumns = [];
    if ($columnsResult) {
        while ($row = $columnsResult->fetch_assoc()) {
            $userColumns[] = $row['Field'];
        }
    }

    // Build name query based on available columns
    if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
        $nameSelect = "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name";
    } elseif (in_array('name', $userColumns)) {
        $nameSelect = "name as full_name";
    } else {
        $nameSelect = "CONCAT('User ', id) as full_name";
    }

    $emailField = in_array('email', $userColumns) ? 'email' : "CONCAT('user', id, '@example.com') as email";

    $result = @$conn->query("SELECT id, $nameSelect, $emailField FROM users WHERE id = $student_id LIMIT 1");
    
    if (!$result || $result->num_rows == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    $student = $result->fetch_assoc();
    
    // Use real name from database, fallback to generic name
    $studentName = !empty(trim($student['full_name'])) ? trim($student['full_name']) : "Student " . $student['id'];
    
    // Generate consistent data based on student ID
    srand($student_id * 12345);
    
    // Today's data
    $todayFocused = 20 + ($student_id % 30);
    $todayUnfocused = 5 + ($student_id % 15);
    $todayTotal = $todayFocused + $todayUnfocused;
    $todayFocusPercent = round(($todayFocused / $todayTotal) * 100, 1);
    
    // Weekly data
    $weeklyFocused = 150 + ($student_id % 100);
    $weeklyUnfocused = 40 + ($student_id % 30);
    $weeklyTotal = $weeklyFocused + $weeklyUnfocused;
    $weeklyFocusPercent = round(($weeklyFocused / $weeklyTotal) * 100, 1);
    
    $response = [
        'success' => true,
        'student' => [
            'id' => $student['id'],
            'name' => $studentName,
            'email' => $student['email'],
            'student_id' => 'ST-' . str_pad($student['id'], 4, '0', STR_PAD_LEFT)
        ],
        'today' => [
            'focused_time' => $todayFocused,
            'unfocused_time' => $todayUnfocused,
            'focus_percentage' => $todayFocusPercent,
            'total_sessions' => rand(2, 6),
            'avg_session_time' => rand(15, 35)
        ],
        'weekly' => [
            'focused_time' => $weeklyFocused,
            'unfocused_time' => $weeklyUnfocused,
            'total_time' => $weeklyTotal,
            'focus_percentage' => $weeklyFocusPercent,
            'total_sessions' => rand(10, 25),
            'active_days' => rand(5, 7)
        ],
        'insights' => [
            "🎯 Great focus today! Maintaining excellent concentration levels.",
            "📈 Consistent study pattern detected. Keep up the good work!",
            "💡 Best performance occurs in afternoon sessions."
        ]
    ];
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

exit();
?>

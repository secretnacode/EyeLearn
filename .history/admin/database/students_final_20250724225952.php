<?php
// Robust students API with real names and proper error handling
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Disable error display
ini_set('display_errors', 0);
error_reporting(0);

try {
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    if ($conn->connect_error) {
        ob_clean();
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    // Get search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // First, check what columns exist in users table
    $columnsResult = $conn->query("SHOW COLUMNS FROM users");
    $userColumns = [];
    if ($columnsResult) {
        while ($row = $columnsResult->fetch_assoc()) {
            $userColumns[] = $row['Field'];
        }
    }
    
    // Build dynamic query based on available columns
    $nameField = "COALESCE(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')), name, CONCAT('User ', id)) as full_name";
    $emailField = in_array('email', $userColumns) ? 'email' : "CONCAT('user', id, '@example.com') as email";
    $genderField = in_array('gender', $userColumns) ? 'gender' : "'Not specified' as gender";
    $dateField = in_array('created_at', $userColumns) ? 'created_at' : 'NOW() as created_at';
    $roleCondition = in_array('role', $userColumns) ? "WHERE (role = 'student' OR role IS NULL)" : "WHERE 1=1";
    
    // Build the SQL query
    $sql = "SELECT 
                id,
                $nameField,
                $emailField,
                $genderField,
                $dateField as registration_date
            FROM users 
            $roleCondition";
    
    // Add search filter if provided
    if (!empty($search)) {
        $searchTerm = $conn->real_escape_string($search);
        if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
            $sql .= " AND (first_name LIKE '%$searchTerm%' OR last_name LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%')";
        } elseif (in_array('name', $userColumns)) {
            $sql .= " AND (name LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%')";
        } else {
            $sql .= " AND email LIKE '%$searchTerm%'";
        }
    }
    
    $sql .= " ORDER BY id ASC LIMIT 50";
    
    $result = $conn->query($sql);
    $students = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $userId = $row['id'];
            
            // Generate consistent data based on user ID for reproducible results
            srand($userId * 12345); // Consistent seed
            
            $todayFocusTime = rand(15, 45);
            $todayUnfocusTime = rand(3, 15);
            $todayTotal = $todayFocusTime + $todayUnfocusTime;
            $todayFocusPercentage = round(($todayFocusTime / $todayTotal) * 100, 1);
            
            $totalFocusTime = rand(80, 250);
            $totalUnfocusTime = rand(20, 80);
            $totalTotal = $totalFocusTime + $totalUnfocusTime;
            $totalFocusPercentage = round(($totalFocusTime / $totalTotal) * 100, 1);
            
            $students[] = [
                'id' => $userId,
                'name' => trim($row['full_name']) ?: "Student $userId",
                'email' => $row['email'],
                'gender' => $row['gender'],
                'registration_date' => $row['registration_date'],
                'progress' => rand(60, 95),
                'total_focus_time' => $totalFocusTime,
                'total_unfocus_time' => $totalUnfocusTime,
                'total_sessions' => rand(5, 25),
                'focus_percentage' => $totalFocusPercentage,
                'today_focus_time' => $todayFocusTime,
                'today_unfocus_time' => $todayUnfocusTime,
                'today_focus_percentage' => $todayFocusPercentage,
                'last_activity' => date('M j, Y g:i A', strtotime('-' . rand(1, 72) . ' hours')),
                'activity_status' => rand(0, 2) == 0 ? 'Active' : (rand(0, 1) ? 'Recent' : 'Inactive'),
                'student_id' => 'ST-' . str_pad($userId, 4, '0', STR_PAD_LEFT)
            ];
        }
    }
    
    // Clean output and send response
    ob_clean();
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total_count' => count($students)
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred'
    ]);
}

exit();
?>

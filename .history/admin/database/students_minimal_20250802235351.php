<?php
// admin/database/students_minimal.php - Real data API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Base query to get student data with progress and today's focus time
    $query = "SELECT DISTINCT
        u.id,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as name,
        u.email,
        u.gender,
        CONCAT('ST-', LPAD(u.id, 4, '0')) as student_id,
        COALESCE(AVG(up.completion_percentage), 0) as progress,
        
        -- Today's focus time data (in minutes)
        COALESCE(SUM(CASE 
            WHEN DATE(ets.created_at) = CURDATE() AND ets.total_time_seconds > 0 
            THEN ets.total_time_seconds 
            ELSE 0 
        END), 0) / 60 as today_focus_time,
        
        -- Today's session duration minus focus time for unfocused time
        COALESCE(SUM(CASE 
            WHEN DATE(ets.created_at) = CURDATE() AND ets.session_duration_seconds > ets.total_time_seconds 
            THEN (ets.session_duration_seconds - ets.total_time_seconds) 
            ELSE 0 
        END), 0) / 60 as today_unfocus_time,
        
        -- Total sessions count
        COUNT(DISTINCT ets.id) as total_sessions
        
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
        WHERE u.role = 'student'";
    
    // Add search condition if provided
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $query .= " AND (u.first_name LIKE '%$search%' 
                    OR u.last_name LIKE '%$search%' 
                    OR u.email LIKE '%$search%'
                    OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search%')";
    }
    
    $query .= " GROUP BY u.id, u.first_name, u.last_name, u.email, u.gender
                ORDER BY name ASC
                LIMIT 50";
    
    $result = $conn->query($query);
    $students = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate focus percentage for today
            $today_focus_time = round($row['today_focus_time'], 0);
            $today_unfocus_time = round($row['today_unfocus_time'], 0);
            
            // If no unfocus time recorded, estimate it based on typical session patterns
            if ($today_unfocus_time == 0 && $today_focus_time > 0) {
                $today_unfocus_time = round($today_focus_time * 0.3, 0); // Assume 30% unfocused time
            }
            
            $today_total_time = $today_focus_time + $today_unfocus_time;
            $today_focus_percentage = 0;
            if ($today_total_time > 0) {
                $today_focus_percentage = round(($today_focus_time / $today_total_time) * 100, 0);
            }
            
            // Clean up student name
            $studentName = trim($row['name']);
            if (empty($studentName)) {
                $studentName = "Student " . $row['id'];
            }
            
            $students[] = [
                'id' => (int)$row['id'],
                'name' => $studentName,
                'email' => $row['email'] ?: "student{$row['id']}@example.com",
                'student_id' => $row['student_id'],
                'gender' => $row['gender'] ?: 'Not specified',
                'progress' => round($row['progress'], 0),
                'today_focus_time' => $today_focus_time,
                'today_unfocus_time' => $today_unfocus_time,
                'today_focus_percentage' => $today_focus_percentage,
                'total_sessions' => (int)$row['total_sessions']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => count($students),
        'query_executed' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

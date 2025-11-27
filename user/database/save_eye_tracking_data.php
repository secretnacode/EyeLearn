<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Database connection
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$module_id = intval($input['module_id'] ?? 0);
$section_id = intval($input['section_id'] ?? 0);
$time_spent = intval($input['time_spent'] ?? 0); // Time in seconds
$session_type = $input['session_type'] ?? 'viewing'; // 'viewing', 'pause', 'resume'

if ($module_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid module ID']);
    exit();
}

try {
    // Check if record exists for this session
    $check_query = "SELECT id, total_time_seconds, focused_time_seconds, unfocused_time_seconds FROM eye_tracking_sessions 
                   WHERE user_id = ? AND module_id = ? AND section_id = ? 
                   AND DATE(created_at) = CURDATE()
                   ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("iii", $user_id, $module_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Extract focus data if provided
    $focused_time = intval($input['focused_time'] ?? 0);
    $unfocused_time = intval($input['unfocused_time'] ?? 0);
    
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $new_total_time = $row['total_time_seconds'] + $time_spent;
        $new_focused_time = intval($row['focused_time_seconds']) + $focused_time;
        $new_unfocused_time = intval($row['unfocused_time_seconds']) + $unfocused_time;
        
        $update_query = "UPDATE eye_tracking_sessions 
                        SET total_time_seconds = ?,
                            focused_time_seconds = ?,
                            unfocused_time_seconds = ?,
                            last_updated = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("iiii", $new_total_time, $new_focused_time, $new_unfocused_time, $row['id']);
        $update_stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'total_time' => $new_total_time,
            'focused_time' => $new_focused_time,
            'unfocused_time' => $new_unfocused_time
        ]);
    } else {
        // Create new record
        $insert_query = "INSERT INTO eye_tracking_sessions 
                        (user_id, module_id, section_id, total_time_seconds, focused_time_seconds, unfocused_time_seconds, session_type, created_at, last_updated) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iiiiis", $user_id, $module_id, $section_id, $time_spent, $focused_time, $unfocused_time, $session_type);
        $insert_stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'total_time' => $time_spent,
            'focused_time' => $focused_time,
            'unfocused_time' => $unfocused_time
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

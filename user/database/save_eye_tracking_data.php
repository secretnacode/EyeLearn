<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Database connection - consistent pattern across all files
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}
// Set charset for consistent encoding
$conn->set_charset('utf8mb4');

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
    // Always create a new session record to ensure accurate session counting
    // Each study session should be a separate record for proper analytics
    $insert_query = "INSERT INTO eye_tracking_sessions 
                    (user_id, module_id, section_id, total_time_seconds, session_type, created_at, last_updated) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiiis", $user_id, $module_id, $section_id, $time_spent, $session_type);
    $insert_stmt->execute();
    
    echo json_encode(['success' => true, 'total_time' => $time_spent]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

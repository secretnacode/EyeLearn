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
// Handle section_id: use NULL if 0 or not set (database allows NULL)
$section_id = isset($input['section_id']) && $input['section_id'] && intval($input['section_id']) > 0 
    ? intval($input['section_id']) 
    : null;
$total_time = intval($input['total_time'] ?? $input['time_spent'] ?? 0); // Time in seconds
$focused_time = intval($input['focused_time'] ?? 0); // Focused time in seconds
$unfocused_time = intval($input['unfocused_time'] ?? 0); // Unfocused time in seconds
// session_type enum: 'viewing','pause','resume' - use 'viewing' for study sessions
$session_type = $input['session_type'] ?? 'viewing';
// Validate session_type is in enum
if (!in_array($session_type, ['viewing', 'pause', 'resume'])) {
    $session_type = 'viewing';
}

if ($module_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid module ID']);
    exit();
}

try {
    // Always create a new session record to ensure accurate session counting
    // Each study session should be a separate record for proper analytics
    // Match database schema: user_id, module_id, section_id, focused_time_seconds, unfocused_time_seconds, total_time_seconds, session_type
    $insert_query = "INSERT INTO eye_tracking_sessions 
                    (user_id, module_id, section_id, focused_time_seconds, unfocused_time_seconds, total_time_seconds, session_type, created_at, last_updated) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiiiiis", $user_id, $module_id, $section_id, $focused_time, $unfocused_time, $total_time, $session_type);
    $insert_stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'total_time' => $total_time,
        'focused_time' => $focused_time,
        'unfocused_time' => $unfocused_time
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

<?php
session_start();
header('Content-Type: application/json');

// Allow CORS for the Python service
header('Access-Control-Allow-Origin: http://127.0.0.1:5000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// For now, we'll use a test user ID since session may not be available from Python service
// In production, you'd want to implement proper authentication
$user_id = 1; // Default test user

// Check if user is authenticated via session (if available)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

// Database connection - consistent pattern across all files
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}
// Set charset for consistent encoding
$conn->set_charset('utf8mb4');

// Get POST data (JSON from Python service)
$input = json_decode(file_get_contents('php://input'), true);
$module_id = intval($input['module_id'] ?? 0);
// Handle section_id: use NULL if 0 or not set (database allows NULL)
$section_id = isset($input['section_id']) && $input['section_id'] && intval($input['section_id']) > 0 
    ? intval($input['section_id']) 
    : null;
$total_time = intval($input['total_time'] ?? $input['time_spent'] ?? 0); // Time in seconds
$focused_time = intval($input['focused_time'] ?? 0); // Focused time in seconds
$unfocused_time = intval($input['unfocused_time'] ?? 0); // Unfocused time in seconds
// session_type enum: 'viewing','pause','resume' - use 'viewing' for CV tracking sessions
$session_type = 'viewing'; // Must be one of: 'viewing', 'pause', 'resume'

if ($module_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid module ID']);
    exit();
}

if ($total_time <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid time spent']);
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
    
    $session_id = $conn->insert_id;
    
    echo json_encode([
        'success' => true, 
        'session_id' => $session_id,
        'total_time' => $total_time,
        'focused_time' => $focused_time,
        'unfocused_time' => $unfocused_time,
        'tracking_type' => 'cv_tracking'
    ]);
    
    // Update daily analytics
    updateDailyAnalytics($conn, $user_id, $module_id, $section_id, $total_time, $focused_time, $unfocused_time);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function updateDailyAnalytics($conn, $user_id, $module_id, $section_id, $total_time, $focused_time, $unfocused_time) {
    try {
        $today = date('Y-m-d');
        $focus_percentage = $total_time > 0 ? round(($focused_time / $total_time) * 100, 2) : 0;
        
        // Check if analytics record exists for today
        $check_analytics = "SELECT id, total_focused_time, total_unfocused_time, session_count FROM eye_tracking_analytics 
                           WHERE user_id = ? AND module_id = ? AND (section_id = ? OR (section_id IS NULL AND ? IS NULL)) AND date = ?";
        $stmt = $conn->prepare($check_analytics);
        $stmt->bind_param("iiiss", $user_id, $module_id, $section_id, $section_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing analytics
            $row = $result->fetch_assoc();
            $new_focused_time = $row['total_focused_time'] + $focused_time;
            $new_unfocused_time = $row['total_unfocused_time'] + $unfocused_time;
            $new_total_time = $new_focused_time + $new_unfocused_time;
            $new_focus_percentage = $new_total_time > 0 ? round(($new_focused_time / $new_total_time) * 100, 2) : 0;
            $new_session_count = $row['session_count'] + 1;
            $new_average = $new_total_time / $new_session_count;
            
            $update_analytics = "UPDATE eye_tracking_analytics 
                               SET total_focused_time = ?, total_unfocused_time = ?, focus_percentage = ?, session_count = ?, average_session_time = ?, updated_at = NOW()
                               WHERE id = ?";
            $stmt = $conn->prepare($update_analytics);
            $stmt->bind_param("iidiii", $new_focused_time, $new_unfocused_time, $new_focus_percentage, $new_session_count, $new_average, $row['id']);
            $stmt->execute();
        } else {
            // Create new analytics record
            $insert_analytics = "INSERT INTO eye_tracking_analytics 
                               (user_id, module_id, section_id, date, total_focused_time, total_unfocused_time, focus_percentage, session_count, average_session_time, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())";
            $stmt = $conn->prepare($insert_analytics);
            $stmt->bind_param("iiisiiid", $user_id, $module_id, $section_id, $today, $focused_time, $unfocused_time, $focus_percentage, $total_time);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Log error but don't fail the main request
        error_log("Analytics update failed: " . $e->getMessage());
    }
}

$conn->close();
?>

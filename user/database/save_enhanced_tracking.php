<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection - use same pattern as other working files
$host = getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: '10241';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON data'
    ]);
    exit();
}

// Validate required fields
$required_fields = ['user_id', 'module_id', 'focused_time', 'unfocused_time', 'total_time', 'focus_percentage'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit();
    }
}

try {
    // Check if record exists for today to update or create new
    $check_query = "SELECT id, total_time_seconds, focused_time_seconds, unfocused_time_seconds 
                   FROM eye_tracking_sessions 
                   WHERE user_id = ? AND module_id = ? AND (? IS NULL OR section_id = ?)
                   AND DATE(created_at) = CURDATE()
                   ORDER BY created_at DESC LIMIT 1";
    
    $check_stmt = $conn->prepare($check_query);
    $section_id = $data['section_id'] ?? null;
    $check_stmt->execute([$data['user_id'], $data['module_id'], $section_id, $section_id]);
    $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Extract values from data - Python service sends time in seconds
    $total_time_seconds = intval($data['total_time'] ?? 0);
    $focused_time_seconds = intval($data['focused_time'] ?? 0);
    $unfocused_time_seconds = intval($data['unfocused_time'] ?? 0);
    $focus_percentage = floatval($data['focus_percentage'] ?? 0);
    
    // session_type must be one of: 'viewing', 'pause', 'resume' (from enum)
    $session_type = 'viewing'; // Use 'viewing' for CV tracking sessions
    
    // Store detailed analytics in session_data JSON field
    $session_data = json_encode([
        'focused_time' => $focused_time_seconds,
        'unfocused_time' => $unfocused_time_seconds,
        'focus_percentage' => $focus_percentage,
        'focus_sessions' => $data['focus_sessions'] ?? 0,
        'unfocus_sessions' => $data['unfocus_sessions'] ?? 0,
        'session_type' => $data['session_type'] ?? 'enhanced_cv_tracking',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    if ($existing_record) {
        // Update existing record - accumulate values
        $new_total_time = intval($existing_record['total_time_seconds']) + $total_time_seconds;
        $new_focused_time = intval($existing_record['focused_time_seconds']) + $focused_time_seconds;
        $new_unfocused_time = intval($existing_record['unfocused_time_seconds']) + $unfocused_time_seconds;
        
        $update_sql = "UPDATE eye_tracking_sessions 
                      SET total_time_seconds = ?, 
                          focused_time_seconds = ?,
                          unfocused_time_seconds = ?,
                          session_data = ?,
                          last_updated = NOW() 
                      WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            $new_total_time, 
            $new_focused_time, 
            $new_unfocused_time,
            $session_data,
            $existing_record['id']
        ]);
        
        $record_id = $existing_record['id'];
        $total_time_seconds = $new_total_time;
        $focused_time_seconds = $new_focused_time;
        $unfocused_time_seconds = $new_unfocused_time;
    } else {
        // Create new record - use actual table columns matching database schema
        $insert_sql = "
            INSERT INTO eye_tracking_sessions (
                user_id, 
                module_id, 
                section_id, 
                total_time_seconds,
                focused_time_seconds,
                unfocused_time_seconds,
                session_type,
                session_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->execute([
            $data['user_id'],
            $data['module_id'],
            $section_id,
            $total_time_seconds,
            $focused_time_seconds,
            $unfocused_time_seconds,
            $session_type,
            $session_data
        ]);
        
        $record_id = $conn->lastInsertId();
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Eye tracking data saved successfully",
        'record_id' => $record_id,
        'total_time' => $total_time_seconds,
        'data_saved' => [
            'total_time_seconds' => $total_time_seconds,
            'focused_time_seconds' => $focused_time_seconds,
            'unfocused_time_seconds' => $unfocused_time_seconds,
            'focus_percentage' => $focus_percentage
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

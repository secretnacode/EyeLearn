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

// Database connection
require_once '../../config.php';

try {
    $conn = new PDO("mysql:host=localhost;dbname=elearn_db", DB_USER, DB_PASS);
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
    // Check if user_analytics table exists, create if not
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS user_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_id INT,
            section_id INT,
            focused_time DECIMAL(10,2) DEFAULT 0,
            unfocused_time DECIMAL(10,2) DEFAULT 0,
            total_time DECIMAL(10,2) DEFAULT 0,
            focus_percentage DECIMAL(5,2) DEFAULT 0,
            focus_sessions INT DEFAULT 0,
            unfocus_sessions INT DEFAULT 0,
            session_type VARCHAR(50) DEFAULT 'enhanced_cv_tracking',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_module (user_id, module_id),
            INDEX idx_created_at (created_at)
        )
    ";
    $conn->exec($create_table_sql);
    
    // Check if record exists for today
    $today = date('Y-m-d');
    $check_sql = "
        SELECT id, focused_time, unfocused_time, total_time, focus_sessions, unfocus_sessions 
        FROM user_analytics 
        WHERE user_id = ? AND module_id = ? AND DATE(created_at) = ?
        ORDER BY created_at DESC LIMIT 1
    ";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$data['user_id'], $data['module_id'], $today]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing record with accumulated data
        $update_sql = "
            UPDATE user_analytics 
            SET 
                focused_time = ?,
                unfocused_time = ?,
                total_time = ?,
                focus_percentage = ?,
                focus_sessions = ?,
                unfocus_sessions = ?,
                session_type = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            $data['focused_time'],
            $data['unfocused_time'],
            $data['total_time'],
            $data['focus_percentage'],
            $data['focus_sessions'] ?? 0,
            $data['unfocus_sessions'] ?? 0,
            $data['session_type'] ?? 'enhanced_cv_tracking',
            $existing['id']
        ]);
        
        $action = 'updated';
        $record_id = $existing['id'];
        
    } else {
        // Insert new record
        $insert_sql = "
            INSERT INTO user_analytics (
                user_id, module_id, section_id, focused_time, unfocused_time, 
                total_time, focus_percentage, focus_sessions, unfocus_sessions, session_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->execute([
            $data['user_id'],
            $data['module_id'],
            $data['section_id'] ?? null,
            $data['focused_time'],
            $data['unfocused_time'],
            $data['total_time'],
            $data['focus_percentage'],
            $data['focus_sessions'] ?? 0,
            $data['unfocus_sessions'] ?? 0,
            $data['session_type'] ?? 'enhanced_cv_tracking'
        ]);
        
        $action = 'created';
        $record_id = $conn->lastInsertId();
    }
    
    // Also update user progress if module tracking
    if (isset($data['module_id']) && $data['total_time'] > 0) {
        $progress_sql = "
            INSERT INTO user_progress (user_id, module_id, time_spent, last_accessed)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            time_spent = time_spent + VALUES(time_spent),
            last_accessed = VALUES(last_accessed)
        ";
        
        $progress_stmt = $conn->prepare($progress_sql);
        $progress_stmt->execute([
            $data['user_id'],
            $data['module_id'],
            $data['total_time']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Tracking data $action successfully",
        'record_id' => $record_id,
        'action' => $action,
        'data_saved' => [
            'focused_time' => $data['focused_time'],
            'unfocused_time' => $data['unfocused_time'],
            'total_time' => $data['total_time'],
            'focus_percentage' => $data['focus_percentage']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

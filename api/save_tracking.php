<?php
/**
 * API Endpoint: Save Eye Tracking Data
 * Saves eye-tracking session data from client-side TensorFlow.js tracker
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Database connection - use same pattern as other working files
$host = getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: '10241';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP';

try {
    $conn = new mysqli($host, $username, $password, $dbname, intval($port));
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $required = ['user_id', 'module_id', 'focused_time', 'unfocused_time', 'total_time'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Extract data
    $userId = (int)$data['user_id'];
    $moduleId = (int)$data['module_id'];
    $sectionId = isset($data['section_id']) && $data['section_id'] ? (int)$data['section_id'] : null;
    $focusedTime = (int)$data['focused_time'];
    $unfocusedTime = (int)$data['unfocused_time'];
    $totalTime = (int)$data['total_time'];
    
    // Calculate focus percentage
    $focusPercentage = $totalTime > 0 ? round(($focusedTime / $totalTime) * 100, 2) : 0;
    
    // Check if a session already exists for this user/module/section today
    // FIX: Use consistent date comparison with fetch query
    $checkQuery = "SELECT id, focused_time_seconds, unfocused_time_seconds, total_time_seconds 
                   FROM eye_tracking_sessions 
                   WHERE user_id = ? 
                   AND module_id = ? 
                   AND (section_id = ? OR (section_id IS NULL AND ? IS NULL))
                   AND DATE(created_at) = CURDATE()
                   ORDER BY last_updated DESC 
                   LIMIT 1";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param('iiii', $userId, $moduleId, $sectionId, $sectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingSession = $result->fetch_assoc();
    $stmt->close();
    
    if ($existingSession) {
        // Update existing session
        $updateQuery = "UPDATE eye_tracking_sessions 
                        SET focused_time_seconds = ?,
                            unfocused_time_seconds = ?,
                            total_time_seconds = ?,
                            last_updated = NOW()
                        WHERE id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('iiii', $focusedTime, $unfocusedTime, $totalTime, $existingSession['id']);
        $stmt->execute();
        $stmt->close();
        
        $sessionId = $existingSession['id'];
        $action = 'updated';
    } else {
        // Insert new session
        $insertQuery = "INSERT INTO eye_tracking_sessions 
                        (user_id, module_id, section_id, focused_time_seconds, unfocused_time_seconds, total_time_seconds, session_type, created_at, last_updated)
                        VALUES (?, ?, ?, ?, ?, ?, 'learning', NOW(), NOW())";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param('iiiiii', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $totalTime);
        $stmt->execute();
        $sessionId = $conn->insert_id;
        $stmt->close();
        
        $action = 'created';
    }
    
    // Update or create analytics entry
    $analyticsCheckQuery = "SELECT id FROM eye_tracking_analytics 
                             WHERE user_id = ? AND module_id = ? AND DATE(date) = CURDATE()";
    $stmt = $conn->prepare($analyticsCheckQuery);
    $stmt->bind_param('ii', $userId, $moduleId);
    $stmt->execute();
    $analyticsResult = $stmt->get_result();
    $existingAnalytics = $analyticsResult->fetch_assoc();
    $stmt->close();
    
    if ($existingAnalytics) {
        // Update analytics
        $updateAnalyticsQuery = "UPDATE eye_tracking_analytics 
                                  SET total_focused_time = ?,
                                      total_unfocused_time = ?,
                                      focus_percentage = ?,
                                      updated_at = NOW()
                                  WHERE id = ?";
        $stmt = $conn->prepare($updateAnalyticsQuery);
        $stmt->bind_param('iidi', $focusedTime, $unfocusedTime, $focusPercentage, $existingAnalytics['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert analytics
        $insertAnalyticsQuery = "INSERT INTO eye_tracking_analytics 
                                  (user_id, module_id, section_id, date, total_focused_time, total_unfocused_time, focus_percentage, session_count, created_at, updated_at)
                                  VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 1, NOW(), NOW())";
        $stmt = $conn->prepare($insertAnalyticsQuery);
        $stmt->bind_param('iiiiiid', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $focusPercentage);
        $stmt->execute();
        $stmt->close();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Tracking data saved successfully',
        'session_id' => $sessionId,
        'action' => $action,
        'data' => [
            'focused_time' => $focusedTime,
            'unfocused_time' => $unfocusedTime,
            'total_time' => $totalTime,
            'focus_percentage' => $focusPercentage
        ]
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Eye tracking save error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Request data: " . json_encode($_POST ?? json_decode(file_get_contents('php://input'), true) ?? []));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();

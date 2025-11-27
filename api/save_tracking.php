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
    // Get JSON input (handles both regular POST and sendBeacon Blob)
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // If json_decode failed, try to handle Blob from sendBeacon
    if (!$data && !empty($json)) {
        // sendBeacon sends as Blob, but PHP receives it as raw data
        // Try to decode again or handle as string
        $data = json_decode($json, true);
    }
    
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
    // Handle section_id: use NULL if null, 0, or not set (database allows NULL)
    $sectionId = (isset($data['section_id']) && $data['section_id'] !== null && $data['section_id'] !== '' && (int)$data['section_id'] > 0) 
        ? (int)$data['section_id'] 
        : null;
    $focusedTime = (int)$data['focused_time'];
    $unfocusedTime = (int)$data['unfocused_time'];
    $totalTime = (int)$data['total_time'];
    
    // Log for debugging
    error_log("Eye tracking save request: user_id=$userId, module_id=$moduleId, section_id=" . ($sectionId ?? 'NULL') . ", focused=$focusedTime, unfocused=$unfocusedTime, total=$totalTime");
    
    // Calculate focus percentage
    $focusPercentage = $totalTime > 0 ? round(($focusedTime / $totalTime) * 100, 2) : 0;
    
    // Always create a new session record to ensure accurate session counting
    // Each study session should be a separate record for proper analytics
    // session_type enum: 'viewing','pause','resume' - use 'viewing' for study sessions
    $insertQuery = "INSERT INTO eye_tracking_sessions 
                    (user_id, module_id, section_id, focused_time_seconds, unfocused_time_seconds, total_time_seconds, session_type, created_at, last_updated)
                    VALUES (?, ?, ?, ?, ?, ?, 'viewing', NOW(), NOW())";
    
    $stmt = $conn->prepare($insertQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    // Bind parameters: user_id, module_id, section_id (can be NULL), focused_time, unfocused_time, total_time
    // mysqli handles NULL values correctly when the variable is actually NULL
    // We need to ensure the variable reference is correct
    $stmt->bind_param('iiiiii', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $totalTime);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute insert: ' . $stmt->error . ' | SQL: ' . $insertQuery);
    }
    
    $sessionId = $conn->insert_id;
    $stmt->close();
    
    $action = 'created';
    
    // Update or create analytics entry
    // Match on user_id, module_id, section_id (handling NULL), and date
    $analyticsCheckQuery = "SELECT id, total_focused_time, total_unfocused_time, session_count 
                             FROM eye_tracking_analytics 
                             WHERE user_id = ? AND module_id = ? 
                             AND (section_id = ? OR (section_id IS NULL AND ? IS NULL))
                             AND DATE(date) = CURDATE()";
    $stmt = $conn->prepare($analyticsCheckQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare analytics check: ' . $conn->error);
    }
    $stmt->bind_param('iiii', $userId, $moduleId, $sectionId, $sectionId);
    $stmt->execute();
    $analyticsResult = $stmt->get_result();
    $existingAnalytics = $analyticsResult->fetch_assoc();
    $stmt->close();
    
    if ($existingAnalytics) {
        // Update analytics - ACCUMULATE values, don't replace
        $newFocusedTime = $existingAnalytics['total_focused_time'] + $focusedTime;
        $newUnfocusedTime = $existingAnalytics['total_unfocused_time'] + $unfocusedTime;
        $newTotalTime = $newFocusedTime + $newUnfocusedTime;
        $newFocusPercentage = $newTotalTime > 0 ? round(($newFocusedTime / $newTotalTime) * 100, 2) : 0;
        $newSessionCount = $existingAnalytics['session_count'] + 1;
        
        $updateAnalyticsQuery = "UPDATE eye_tracking_analytics 
                                  SET total_focused_time = ?,
                                      total_unfocused_time = ?,
                                      focus_percentage = ?,
                                      session_count = ?,
                                      updated_at = NOW()
                                  WHERE id = ?";
        $stmt = $conn->prepare($updateAnalyticsQuery);
        if (!$stmt) {
            throw new Exception('Failed to prepare analytics update: ' . $conn->error);
        }
        $stmt->bind_param('iidii', $newFocusedTime, $newUnfocusedTime, $newFocusPercentage, $newSessionCount, $existingAnalytics['id']);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute analytics update: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        // Insert analytics
        $insertAnalyticsQuery = "INSERT INTO eye_tracking_analytics 
                                  (user_id, module_id, section_id, date, total_focused_time, total_unfocused_time, focus_percentage, session_count, created_at, updated_at)
                                  VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 1, NOW(), NOW())";
        $stmt = $conn->prepare($insertAnalyticsQuery);
        if (!$stmt) {
            throw new Exception('Failed to prepare analytics insert: ' . $conn->error);
        }
        // Handle NULL section_id properly - mysqli handles NULL correctly
        // 6 parameters: user_id(i), module_id(i), section_id(i), focused_time(i), unfocused_time(i), percentage(d)
        $stmt->bind_param('iiiiid', $userId, $moduleId, $sectionId, $focusedTime, $unfocusedTime, $focusPercentage);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute analytics insert: ' . $stmt->error);
        }
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
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'request_data' => json_decode(file_get_contents('php://input'), true) ?? []
    ];
    
    error_log("Eye tracking save error: " . json_encode($errorDetails, JSON_PRETTY_PRINT));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => $errorDetails // Include details in response for debugging
    ]);
}

$conn->close();

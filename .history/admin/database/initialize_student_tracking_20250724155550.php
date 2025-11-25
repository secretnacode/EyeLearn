<?php
// This file handles automatic linking of new tracking sessions to the admin dashboard
include '../../config.php';

function initializeStudentTracking($userId, $moduleId) {
    global $pdo;
    
    try {
        // Check if user progress record exists for this user/module combination
        $stmt = $pdo->prepare("SELECT id FROM user_progress WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$userId, $moduleId]);
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Create initial progress record
            $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, module_id, completion_percentage, completed, last_accessed) VALUES (?, ?, 0, 0, NOW())");
            $stmt->execute([$userId, $moduleId]);
            
            error_log("Created initial progress record for user $userId, module $moduleId");
        }
        
        // Update last accessed time
        $stmt = $pdo->prepare("UPDATE user_progress SET last_accessed = NOW() WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$userId, $moduleId]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error initializing student tracking: " . $e->getMessage());
        return false;
    }
}

// If called directly (e.g., from eye tracking service)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $moduleId = $input['module_id'] ?? null;
    
    if ($userId && $moduleId) {
        $success = initializeStudentTracking($userId, $moduleId);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    }
    exit;
}

// Function can also be included and called directly
if (isset($user_id) && isset($module_id)) {
    initializeStudentTracking($user_id, $module_id);
}
?>

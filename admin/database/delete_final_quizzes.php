<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1 || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Connect to database
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid quiz ID']);
    exit;
}

$quiz_id = $_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete the quiz (questions will be deleted automatically due to CASCADE)
    $stmt = $conn->prepare("DELETE FROM final_quizzes WHERE id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
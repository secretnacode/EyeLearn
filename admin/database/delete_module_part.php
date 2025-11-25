<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1 || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginpage.php");
    exit;
}

// Database connection
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if a module part ID was provided
if (isset($_POST['module_part_id'])) {
    $module_part_id = $_POST['module_part_id'];
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Delete the module part (cascading delete will remove all related sections and questions)
        $stmt = $conn->prepare("DELETE FROM module_parts WHERE id = ?");
        $stmt->bind_param("i", $module_part_id);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        // Redirect back with success message
        header("Location: ../Amodule.php?tab=module-parts&status=success&message=Module part deleted successfully");
        exit;
        
    } catch (Exception $e) {
        // Roll back the transaction if any query failed
        $conn->rollback();
        
        // Redirect back with error message
        header("Location: ../Amodule.php?tab=module-parts&status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // No module part ID provided
    header("Location: ../Amodule.php?tab=module-parts&status=error&message=No module part selected for deletion");
    exit;
}

$conn->close();
?>
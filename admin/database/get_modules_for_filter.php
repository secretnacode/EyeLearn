<?php
// admin/database/get_modules_for_filter.php - Get modules for filter dropdown
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // Get all published modules
    $query = "SELECT id, title FROM modules WHERE status = 'published' ORDER BY title ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
        exit;
    }
    
    $modules = [];
    
    while ($row = $result->fetch_assoc()) {
        $modules[] = [
            'id' => (int)$row['id'],
            'title' => $row['title']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'modules' => $modules,
        'total' => count($modules)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>


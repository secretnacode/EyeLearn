<?php
// Debug version of students API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    // Test simple query first
    $simpleQuery = "SELECT id, first_name, last_name, email, role FROM users WHERE role = 'student' LIMIT 5";
    $result = $conn->query($simpleQuery);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Simple query failed: ' . $conn->error]);
        exit;
    }
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'id' => $row['id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
            'role' => $row['role']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Simple query worked',
        'students' => $students,
        'count' => count($students)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
?>

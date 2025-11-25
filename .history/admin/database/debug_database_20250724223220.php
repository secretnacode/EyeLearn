<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    }

    // Get all tables in database
    $tablesResult = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $tablesResult->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Get users table structure
    $usersColumns = [];
    if (in_array('users', $tables)) {
        $columnsResult = $conn->query("SHOW COLUMNS FROM users");
        while ($row = $columnsResult->fetch_assoc()) {
            $usersColumns[] = $row['Field'];
        }
        
        // Get sample user data
        $usersData = $conn->query("SELECT * FROM users LIMIT 3");
        $sampleUsers = [];
        while ($row = $usersData->fetch_assoc()) {
            $sampleUsers[] = $row;
        }
    }
    
    // Check each important table
    $tableInfo = [];
    $importantTables = ['users', 'modules', 'user_progress', 'eye_tracking_sessions'];
    
    foreach ($importantTables as $table) {
        if (in_array($table, $tables)) {
            $columnsResult = $conn->query("SHOW COLUMNS FROM $table");
            $columns = [];
            while ($row = $columnsResult->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $countResult->fetch_assoc()['count'];
            
            $tableInfo[$table] = [
                'exists' => true,
                'columns' => $columns,
                'row_count' => $count
            ];
        } else {
            $tableInfo[$table] = [
                'exists' => false,
                'columns' => [],
                'row_count' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'all_tables' => $tables,
        'users_columns' => $usersColumns,
        'sample_users' => $sampleUsers ?? [],
        'table_info' => $tableInfo
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>

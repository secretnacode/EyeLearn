<?php
// Debug version with error reporting enabled
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Students API Debug</h2>";

try {
    echo "<p>Attempting database connection...</p>";
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>";
        exit();
    }
    
    echo "<p style='color: green;'>Database connected successfully!</p>";
    
    // Test basic query
    echo "<p>Testing basic users query...</p>";
    $sql = "SELECT id, 
                   COALESCE(CONCAT(first_name, ' ', last_name), name, 'Unknown') as name,
                   email,
                   COALESCE(gender, 'Not specified') as gender,
                   created_at as registration_date
            FROM users 
            WHERE (role = 'student' OR role IS NULL)
            ORDER BY created_at DESC 
            LIMIT 5";
    
    echo "<p>SQL Query: <code>" . htmlspecialchars($sql) . "</code></p>";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
        exit();
    }
    
    echo "<p style='color: green;'>Query executed successfully!</p>";
    echo "<p>Found " . $result->num_rows . " students</p>";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM users");
    echo "<table border='1'>";
    echo "<tr><th>Column</th><th>Type</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td></tr>";
    }
    echo "</table>";
    
    // Check eye_tracking_sessions table
    echo "<h3>Eye Tracking Sessions Table:</h3>";
    $tableCheck = $conn->query("SHOW TABLES LIKE 'eye_tracking_sessions'");
    if ($tableCheck->num_rows > 0) {
        echo "<p style='color: green;'>Table exists</p>";
        $columns = $conn->query("SHOW COLUMNS FROM eye_tracking_sessions");
        echo "<table border='1'>";
        echo "<tr><th>Column</th><th>Type</th></tr>";
        while ($col = $columns->fetch_assoc()) {
            echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>Table does not exist</p>";
    }
    
    // Test the focus data query
    echo "<h3>Testing Focus Data Query:</h3>";
    $focusQuery = "SELECT 
        SUM(CASE WHEN is_focused = 1 AND DATE(created_at) = CURDATE() THEN duration_seconds ELSE 0 END) / 60 as today_focused,
        SUM(CASE WHEN is_focused = 0 AND DATE(created_at) = CURDATE() THEN duration_seconds ELSE 0 END) / 60 as today_unfocused,
        SUM(CASE WHEN is_focused = 1 THEN duration_seconds ELSE 0 END) / 60 as total_focused,
        SUM(CASE WHEN is_focused = 0 THEN duration_seconds ELSE 0 END) / 60 as total_unfocused,
        COUNT(DISTINCT session_id) as sessions
        FROM eye_tracking_sessions 
        WHERE user_id = 1";
    
    echo "<p>Focus Query: <code>" . htmlspecialchars($focusQuery) . "</code></p>";
    
    $focusResult = $conn->query($focusQuery);
    if ($focusResult) {
        echo "<p style='color: green;'>Focus query executed successfully!</p>";
        $focusData = $focusResult->fetch_assoc();
        echo "<pre>" . print_r($focusData, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>Focus query failed: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}
?>

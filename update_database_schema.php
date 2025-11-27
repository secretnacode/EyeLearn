<?php
// Add session_data column to eye_tracking_sessions table if it doesn't exist
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if session_data column exists
    $check_column = $conn->query("SHOW COLUMNS FROM eye_tracking_sessions LIKE 'session_data'");
    
    if ($check_column->rowCount() == 0) {
        // Add session_data column
        $conn->exec("ALTER TABLE eye_tracking_sessions ADD COLUMN session_data TEXT");
        echo "✅ Added session_data column to eye_tracking_sessions table<br>";
    } else {
        echo "✅ session_data column already exists<br>";
    }
    
    echo "✅ Database structure updated successfully!<br>";
    echo "<br><strong>Next step:</strong> Enable the Python service to use this endpoint.";
    
} catch(PDOException $e) {
    echo "❌ Error updating database: " . $e->getMessage();
}
?>

<?php
// Simple script to add sample progress data
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user ID (you can change this to your actual user ID)
$user_id = 7; // Change this to your actual user ID

// Get some module IDs
$moduleQuery = "SELECT id, title FROM modules WHERE status = 'published' LIMIT 3";
$result = $conn->query($moduleQuery);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $module_id = $row['id'];
        $title = $row['title'];
        
        // Check if progress already exists
        $checkQuery = "SELECT id FROM user_progress WHERE user_id = ? AND module_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('ii', $user_id, $module_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Create sample progress data
            $progress = rand(10, 85); // Random progress between 10% and 85%
            
            $insertQuery = "INSERT INTO user_progress (user_id, module_id, completion_percentage, last_accessed) VALUES (?, ?, ?, NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param('iid', $user_id, $module_id, $progress);
            
            if ($insertStmt->execute()) {
                echo "✅ Added progress for module: $title ($progress%)\n";
            } else {
                echo "❌ Failed to add progress for module: $title\n";
            }
        } else {
            echo "ℹ️ Progress already exists for module: $title\n";
        }
    }
} else {
    echo "❌ No published modules found\n";
}

$conn->close();
echo "\n✅ Sample progress data creation completed!\n";
?>

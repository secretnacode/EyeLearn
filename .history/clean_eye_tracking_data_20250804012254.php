<?php
// Clean Eye Tracking Data
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Delete invalid sessions (less than 30 seconds or more than 2 hours)
$cleanQuery = "DELETE FROM eye_tracking_sessions 
               WHERE total_time_seconds <= 0 
               OR total_time_seconds IS NULL 
               OR total_time_seconds > 7200";

if ($conn->query($cleanQuery)) {
    $deletedRows = $conn->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "Cleaned $deletedRows invalid eye tracking sessions",
        'deleted_rows' => $deletedRows
    ]);
} else {
    echo json_encode([
        'error' => 'Failed to clean data: ' . $conn->error
    ]);
}

$conn->close();
?>

<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

// Query to get the most recent timestamp from key tables
// This indicates when data was last added or updated.
$latestTimestampQuery = "
    SELECT GREATEST(
        (SELECT MAX(created_at) FROM users),
        (SELECT MAX(updated_at) FROM user_progress),
        (SELECT MAX(created_at) FROM quiz_results),
        (SELECT MAX(session_end_time) FROM eye_tracking_sessions)
    ) AS last_update;
";

$result = $conn->query($latestTimestampQuery);

if ($result) {
    $row = $result->fetch_assoc();
    echo json_encode(['last_update' => $row['last_update']]);
} else {
    echo json_encode(['error' => 'Failed to query for updates.']);
}

$conn->close();
?>
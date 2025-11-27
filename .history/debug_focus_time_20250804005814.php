<?php
// Debug focus time calculation
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Eye Tracking Sessions Table Structure:</h2>";
$result = $conn->query('DESCRIBE eye_tracking_sessions');
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No eye_tracking_sessions table found or error: " . $conn->error;
}

echo "<h2>Sample Eye Tracking Data:</h2>";
$result = $conn->query('SELECT id, user_id, module_id, total_time_seconds, session_duration_seconds, focus_time_seconds, created_at FROM eye_tracking_sessions ORDER BY id DESC LIMIT 10');
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>User</th><th>Module</th><th>Total Time</th><th>Session Duration</th><th>Focus Time</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['module_id']}</td>";
        echo "<td>" . ($row['total_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['session_duration_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['focus_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No eye tracking data found or error: " . $conn->error;
}

echo "<h2>Focus Time Statistics:</h2>";
$result = $conn->query('SELECT 
    COUNT(*) as total_sessions,
    AVG(total_time_seconds) as avg_total_time,
    AVG(CASE WHEN session_duration_seconds IS NOT NULL THEN session_duration_seconds END) as avg_session_duration,
    AVG(CASE WHEN focus_time_seconds IS NOT NULL THEN focus_time_seconds END) as avg_focus_time,
    MIN(total_time_seconds) as min_time,
    MAX(total_time_seconds) as max_time,
    COUNT(CASE WHEN total_time_seconds > 0 THEN 1 END) as sessions_with_total_time,
    COUNT(CASE WHEN focus_time_seconds > 0 THEN 1 END) as sessions_with_focus_time
FROM eye_tracking_sessions');

if ($result) {
    $stats = $result->fetch_assoc();
    echo "<table border='1'>";
    foreach ($stats as $key => $value) {
        echo "<tr><td>$key</td><td>" . ($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error getting statistics: " . $conn->error;
}

echo "<h2>Current Dashboard Query Result:</h2>";
$dashboardQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.gender,
    COALESCE(AVG(CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds END), 0) as avg_focus_time_seconds,
    COUNT(DISTINCT ets.id) as total_sessions
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.gender
    ORDER BY avg_focus_time_seconds DESC
    LIMIT 10";

$result = $conn->query($dashboardQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Gender</th><th>Avg Focus (seconds)</th><th>Avg Focus (minutes)</th><th>Sessions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $avgFocusMinutes = round($row['avg_focus_time_seconds'] / 60, 1);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . ($row['gender'] ?? 'NULL') . "</td>";
        echo "<td>" . round($row['avg_focus_time_seconds'], 1) . "</td>";
        echo "<td>$avgFocusMinutes</td>";
        echo "<td>{$row['total_sessions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No student data found or error: " . $conn->error;
}

$conn->close();
?>

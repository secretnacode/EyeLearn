<?php
// Test improved focus time calculation
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Before vs After Focus Time Calculation:</h2>";

echo "<h3>Old Calculation (using only total_time_seconds):</h3>";
$oldQuery = "SELECT 
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

$result = $conn->query($oldQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Gender</th><th>Old Avg Focus (min)</th><th>Sessions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $avgFocusMinutes = round($row['avg_focus_time_seconds'] / 60, 1);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . ($row['gender'] ?? 'NULL') . "</td>";
        echo "<td>$avgFocusMinutes</td>";
        echo "<td>{$row['total_sessions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>New Calculation (using best available time column):</h3>";
$newQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.gender,
    COALESCE(AVG(CASE 
        WHEN ets.focus_time_seconds IS NOT NULL AND ets.focus_time_seconds > 0 THEN ets.focus_time_seconds
        WHEN ets.session_duration_seconds IS NOT NULL AND ets.session_duration_seconds > 0 THEN ets.session_duration_seconds
        WHEN ets.total_time_seconds IS NOT NULL AND ets.total_time_seconds > 0 THEN ets.total_time_seconds
        ELSE NULL
    END), 0) as avg_focus_time_seconds,
    COUNT(DISTINCT ets.id) as total_sessions
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.gender
    ORDER BY avg_focus_time_seconds DESC
    LIMIT 10";

$result = $conn->query($newQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Gender</th><th>New Avg Focus (min)</th><th>Sessions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $avgFocusMinutes = round($row['avg_focus_time_seconds'] / 60, 1);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . ($row['gender'] ?? 'NULL') . "</td>";
        echo "<td>$avgFocusMinutes</td>";
        echo "<td>{$row['total_sessions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Detailed Analysis of Time Columns Used:</h3>";
$analysisQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    ets.total_time_seconds,
    ets.session_duration_seconds,
    ets.focus_time_seconds,
    CASE 
        WHEN ets.focus_time_seconds IS NOT NULL AND ets.focus_time_seconds > 0 THEN 'focus_time_seconds'
        WHEN ets.session_duration_seconds IS NOT NULL AND ets.session_duration_seconds > 0 THEN 'session_duration_seconds'
        WHEN ets.total_time_seconds IS NOT NULL AND ets.total_time_seconds > 0 THEN 'total_time_seconds'
        ELSE 'none'
    END as column_used,
    CASE 
        WHEN ets.focus_time_seconds IS NOT NULL AND ets.focus_time_seconds > 0 THEN ets.focus_time_seconds
        WHEN ets.session_duration_seconds IS NOT NULL AND ets.session_duration_seconds > 0 THEN ets.session_duration_seconds
        WHEN ets.total_time_seconds IS NOT NULL AND ets.total_time_seconds > 0 THEN ets.total_time_seconds
        ELSE NULL
    END as selected_time
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student' AND ets.id IS NOT NULL
    ORDER BY u.id, ets.id
    LIMIT 20";

$result = $conn->query($analysisQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>User</th><th>Name</th><th>Total Time</th><th>Session Duration</th><th>Focus Time</th><th>Column Used</th><th>Selected Time</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . ($row['total_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['session_duration_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['focus_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>{$row['column_used']}</td>";
        echo "<td>" . ($row['selected_time'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

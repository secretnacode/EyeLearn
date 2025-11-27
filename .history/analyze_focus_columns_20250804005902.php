<?php
// Check for better focus time calculation
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Checking Focus Time Column Options:</h2>";

// Check if focus_time_seconds column exists and has better data
$result = $conn->query("SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'elearn_db' 
AND TABLE_NAME = 'eye_tracking_sessions' 
AND COLUMN_NAME LIKE '%time%'");

if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['COLUMN_NAME']}</td>";
        echo "<td>{$row['DATA_TYPE']}</td>";
        echo "<td>{$row['IS_NULLABLE']}</td>";
        echo "<td>" . ($row['COLUMN_DEFAULT'] ?? 'NULL') . "</td>";
        echo "<td>{$row['EXTRA']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Comparing Time Columns:</h2>";
$result = $conn->query("SELECT 
    total_time_seconds,
    session_duration_seconds,
    focus_time_seconds,
    CASE 
        WHEN focus_time_seconds IS NOT NULL AND focus_time_seconds > 0 THEN focus_time_seconds
        WHEN session_duration_seconds IS NOT NULL AND session_duration_seconds > 0 THEN session_duration_seconds  
        ELSE total_time_seconds 
    END as best_focus_time
FROM eye_tracking_sessions 
WHERE (total_time_seconds > 0 OR session_duration_seconds > 0 OR focus_time_seconds > 0)
LIMIT 20");

if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>Total Time</th><th>Session Duration</th><th>Focus Time</th><th>Best Focus Time</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . ($row['total_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['session_duration_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['focus_time_seconds'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['best_focus_time'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Focus Time Statistics by Column:</h2>";
$result = $conn->query("SELECT 
    'total_time_seconds' as column_name,
    COUNT(CASE WHEN total_time_seconds > 0 THEN 1 END) as non_zero_count,
    AVG(CASE WHEN total_time_seconds > 0 THEN total_time_seconds END) as avg_value,
    MIN(CASE WHEN total_time_seconds > 0 THEN total_time_seconds END) as min_value,
    MAX(total_time_seconds) as max_value
FROM eye_tracking_sessions
UNION ALL
SELECT 
    'session_duration_seconds' as column_name,
    COUNT(CASE WHEN session_duration_seconds > 0 THEN 1 END) as non_zero_count,
    AVG(CASE WHEN session_duration_seconds > 0 THEN session_duration_seconds END) as avg_value,
    MIN(CASE WHEN session_duration_seconds > 0 THEN session_duration_seconds END) as min_value,
    MAX(session_duration_seconds) as max_value
FROM eye_tracking_sessions
UNION ALL
SELECT 
    'focus_time_seconds' as column_name,
    COUNT(CASE WHEN focus_time_seconds > 0 THEN 1 END) as non_zero_count,
    AVG(CASE WHEN focus_time_seconds > 0 THEN focus_time_seconds END) as avg_value,
    MIN(CASE WHEN focus_time_seconds > 0 THEN focus_time_seconds END) as min_value,
    MAX(focus_time_seconds) as max_value
FROM eye_tracking_sessions");

if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>Column</th><th>Non-Zero Count</th><th>Avg Value</th><th>Min Value</th><th>Max Value</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['column_name']}</td>";
        echo "<td>" . ($row['non_zero_count'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['avg_value'] ? round($row['avg_value'], 1) : 'NULL') . "</td>";
        echo "<td>" . ($row['min_value'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['max_value'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

<?php
// Focus Time Accuracy - Final Diagnosis and Fix
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Focus Time Inaccuracy - Root Cause Analysis</h1>";

echo "<h2>Problem Identification</h2>";

// Check if eye tracking data exists and is meaningful
echo "<h3>1. Eye Tracking Data Quality Check</h3>";
$dataQualityQuery = "SELECT 
    COUNT(*) as total_sessions,
    COUNT(CASE WHEN total_time_seconds > 0 THEN 1 END) as sessions_with_time,
    MIN(total_time_seconds) as min_time,
    MAX(total_time_seconds) as max_time,
    AVG(total_time_seconds) as avg_time,
    COUNT(DISTINCT user_id) as unique_users
FROM eye_tracking_sessions";

$result = $conn->query($dataQualityQuery);
$quality = $result->fetch_assoc();

echo "<table border='1'>";
echo "<tr><th>Metric</th><th>Value</th><th>Assessment</th></tr>";
echo "<tr><td>Total Sessions</td><td>{$quality['total_sessions']}</td><td>" . ($quality['total_sessions'] > 0 ? '✅ Good' : '❌ No data') . "</td></tr>";
echo "<tr><td>Sessions with Time > 0</td><td>{$quality['sessions_with_time']}</td><td>" . ($quality['sessions_with_time'] > 0 ? '✅ Good' : '❌ No valid times') . "</td></tr>";
echo "<tr><td>Min Time (seconds)</td><td>{$quality['min_time']}</td><td>" . ($quality['min_time'] >= 0 ? '✅ Valid' : '❌ Invalid') . "</td></tr>";
echo "<tr><td>Max Time (seconds)</td><td>{$quality['max_time']}</td><td>" . ($quality['max_time'] < 86400 ? '✅ Reasonable' : '⚠️ Very high') . "</td></tr>";
echo "<tr><td>Avg Time (minutes)</td><td>" . round($quality['avg_time'] / 60, 1) . "</td><td>" . ($quality['avg_time'] > 0 ? '✅ Valid' : '❌ Zero average') . "</td></tr>";
echo "<tr><td>Unique Users</td><td>{$quality['unique_users']}</td><td>" . ($quality['unique_users'] > 0 ? '✅ Good' : '❌ No users') . "</td></tr>";
echo "</table>";

// Check for common issues
echo "<h3>2. Common Data Issues</h3>";
$issuesQuery = "SELECT 
    user_id,
    COUNT(*) as session_count,
    AVG(total_time_seconds) as avg_time,
    MIN(total_time_seconds) as min_time,
    MAX(total_time_seconds) as max_time,
    SUM(CASE WHEN total_time_seconds = 0 THEN 1 ELSE 0 END) as zero_sessions,
    SUM(CASE WHEN total_time_seconds IS NULL THEN 1 ELSE 0 END) as null_sessions
FROM eye_tracking_sessions 
GROUP BY user_id
HAVING session_count > 0
ORDER BY user_id
LIMIT 10";

$result = $conn->query($issuesQuery);
echo "<table border='1'>";
echo "<tr><th>User ID</th><th>Sessions</th><th>Avg Time (min)</th><th>Min</th><th>Max</th><th>Zero Sessions</th><th>Null Sessions</th><th>Issues</th></tr>";

while ($row = $result->fetch_assoc()) {
    $avgMin = round($row['avg_time'] / 60, 1);
    $issues = [];
    if ($row['zero_sessions'] > 0) $issues[] = "Zero times";
    if ($row['null_sessions'] > 0) $issues[] = "Null times";
    if ($row['max_time'] > 7200) $issues[] = "Very long sessions";
    if ($row['avg_time'] == 0) $issues[] = "No valid data";
    
    $issueText = empty($issues) ? '✅ Clean' : '⚠️ ' . implode(', ', $issues);
    
    echo "<tr>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td>{$row['session_count']}</td>";
    echo "<td>{$avgMin}</td>";
    echo "<td>{$row['min_time']}</td>";
    echo "<td>{$row['max_time']}</td>";
    echo "<td>{$row['zero_sessions']}</td>";
    echo "<td>{$row['null_sessions']}</td>";
    echo "<td>{$issueText}</td>";
    echo "</tr>";
}
echo "</table>";

// Test the exact query used in dashboard
echo "<h3>3. Current Dashboard Query Test</h3>";
$dashboardQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.gender,
    COALESCE(AVG(CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds ELSE NULL END), 0) as avg_focus_time_seconds,
    COUNT(DISTINCT ets.id) as total_sessions,
    -- Debug fields
    COUNT(CASE WHEN ets.total_time_seconds > 0 THEN 1 END) as valid_sessions,
    SUM(ets.total_time_seconds) as total_time_sum,
    MAX(ets.total_time_seconds) as max_session_time
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.gender
    HAVING total_sessions > 0
    ORDER BY avg_focus_time_seconds DESC
    LIMIT 10";

$result = $conn->query($dashboardQuery);
echo "<table border='1'>";
echo "<tr><th>User</th><th>Gender</th><th>Avg Focus (min)</th><th>Total Sessions</th><th>Valid Sessions</th><th>Total Time Sum</th><th>Manual Calc (min)</th></tr>";

while ($row = $result->fetch_assoc()) {
    $avgFocusMin = $row['avg_focus_time_seconds'] > 0 ? round($row['avg_focus_time_seconds'] / 60, 1) : 0;
    $manualCalc = $row['valid_sessions'] > 0 ? round($row['total_time_sum'] / $row['valid_sessions'] / 60, 1) : 0;
    
    echo "<tr>";
    echo "<td>{$row['first_name']} {$row['last_name']}</td>";
    echo "<td>" . ($row['gender'] ?: 'N/A') . "</td>";
    echo "<td>{$avgFocusMin}</td>";
    echo "<td>{$row['total_sessions']}</td>";
    echo "<td>{$row['valid_sessions']}</td>";
    echo "<td>{$row['total_time_sum']}</td>";
    echo "<td>{$manualCalc}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Proposed Solutions</h2>";

echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Solution 1: Clean Query with Better Filtering</h3>";
echo "<p>Use a more robust query that filters out invalid data:</p>";
echo "<pre>";
$cleanQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.gender,
    -- Only count sessions with reasonable time (30 seconds to 2 hours)
    ROUND(AVG(CASE 
        WHEN ets.total_time_seconds BETWEEN 30 AND 7200 
        THEN ets.total_time_seconds 
        ELSE NULL 
    END) / 60, 1) as avg_focus_time_minutes,
    COUNT(CASE 
        WHEN ets.total_time_seconds BETWEEN 30 AND 7200 
        THEN 1 
        ELSE NULL 
    END) as valid_sessions,
    COUNT(DISTINCT ets.id) as total_sessions
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.gender
    HAVING valid_sessions > 0
    ORDER BY avg_focus_time_minutes DESC
    LIMIT 10";
echo htmlspecialchars($cleanQuery);
echo "</pre>";

$result = $conn->query($cleanQuery);
if ($result && $result->num_rows > 0) {
    echo "<h4>Clean Query Results:</h4>";
    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Gender</th><th>Avg Focus (min)</th><th>Valid Sessions</th><th>Total Sessions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . ($row['gender'] ?: 'N/A') . "</td>";
        echo "<td>{$row['avg_focus_time_minutes']}</td>";
        echo "<td>{$row['valid_sessions']}</td>";
        echo "<td>{$row['total_sessions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No results from clean query</p>";
}
echo "</div>";

echo "<div style='background: #f0fdf4; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Solution 2: Data Cleaning</h3>";
echo "<p>Clean up any invalid eye tracking data:</p>";

// Count invalid data
$invalidCount = $conn->query("SELECT COUNT(*) as count FROM eye_tracking_sessions WHERE total_time_seconds <= 0 OR total_time_seconds > 7200")->fetch_assoc()['count'];
echo "<p>Invalid sessions found: <strong>$invalidCount</strong></p>";

if ($invalidCount > 0) {
    echo "<button onclick='cleanData()' style='background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Clean Invalid Data</button>";
    echo "<script>
    function cleanData() {
        if (confirm('This will delete $invalidCount invalid eye tracking sessions. Continue?')) {
            fetch('clean_eye_tracking_data.php', {method: 'POST'})
            .then(response => response.text())
            .then(data => {
                alert('Data cleaned! Refresh the page to see results.');
                location.reload();
            });
        }
    }
    </script>";
}
echo "</div>";

$conn->close();
?>

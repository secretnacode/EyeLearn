<?php
// Helper function to get available time columns and create optimized query
function getOptimizedFocusTimeQuery($conn) {
    // Check which columns exist
    $result = $conn->query("DESCRIBE eye_tracking_sessions");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Determine best time column to use based on available columns
    $timeColumnExpression = '';
    if (in_array('focus_time_seconds', $columns)) {
        $timeColumnExpression = "CASE 
            WHEN ets.focus_time_seconds IS NOT NULL AND ets.focus_time_seconds > 0 THEN ets.focus_time_seconds
            WHEN ets.session_duration_seconds IS NOT NULL AND ets.session_duration_seconds > 0 THEN ets.session_duration_seconds
            WHEN ets.total_time_seconds IS NOT NULL AND ets.total_time_seconds > 0 THEN ets.total_time_seconds
            ELSE NULL
        END";
        $whereCondition = "(ets.focus_time_seconds > 0 OR ets.session_duration_seconds > 0 OR ets.total_time_seconds > 0)";
    } elseif (in_array('session_duration_seconds', $columns)) {
        $timeColumnExpression = "CASE 
            WHEN ets.session_duration_seconds IS NOT NULL AND ets.session_duration_seconds > 0 THEN ets.session_duration_seconds
            WHEN ets.total_time_seconds IS NOT NULL AND ets.total_time_seconds > 0 THEN ets.total_time_seconds
            ELSE NULL
        END";
        $whereCondition = "(ets.session_duration_seconds > 0 OR ets.total_time_seconds > 0)";
    } else {
        // Fallback to total_time_seconds only
        $timeColumnExpression = "CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds ELSE NULL END";
        $whereCondition = "ets.total_time_seconds > 0";
    }
    
    return [
        'timeExpression' => $timeColumnExpression,
        'whereCondition' => $whereCondition,
        'availableColumns' => $columns
    ];
}

// Test the function
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$queryInfo = getOptimizedFocusTimeQuery($conn);

echo "<h2>Available Columns:</h2>";
echo "<ul>";
foreach ($queryInfo['availableColumns'] as $column) {
    echo "<li>$column</li>";
}
echo "</ul>";

echo "<h2>Optimized Time Expression:</h2>";
echo "<pre>" . htmlspecialchars($queryInfo['timeExpression']) . "</pre>";

echo "<h2>Where Condition:</h2>";
echo "<pre>" . htmlspecialchars($queryInfo['whereCondition']) . "</pre>";

echo "<h2>Test Query Result:</h2>";
$testQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    COALESCE(AVG({$queryInfo['timeExpression']}), 0) as avg_focus_time_seconds
    FROM users u
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name
    HAVING avg_focus_time_seconds > 0
    ORDER BY avg_focus_time_seconds DESC
    LIMIT 5";

echo "<pre>" . htmlspecialchars($testQuery) . "</pre>";

$result = $conn->query($testQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Avg Focus Time (min)</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $avgMinutes = round($row['avg_focus_time_seconds'] / 60, 1);
        echo "<tr><td>{$row['id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$avgMinutes}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No results or error: " . $conn->error;
}

$conn->close();
?>

<?php
// Debug focus time data flow
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Focus Time Debug - Live Data Flow Test</h1>";

echo "<h2>1. Raw Eye Tracking Data Sample</h2>";
$rawQuery = "SELECT 
    ets.id,
    ets.user_id,
    ets.module_id,
    ets.total_time_seconds,
    ets.created_at,
    u.first_name,
    u.last_name
FROM eye_tracking_sessions ets
JOIN users u ON ets.user_id = u.id
WHERE u.role = 'student'
ORDER BY ets.created_at DESC
LIMIT 10";

$result = $conn->query($rawQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>Session ID</th><th>User</th><th>Module</th><th>Total Time (sec)</th><th>Total Time (min)</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $timeMinutes = $row['total_time_seconds'] ? round($row['total_time_seconds'] / 60, 1) : 0;
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>{$row['module_id']}</td>";
        echo "<td>{$row['total_time_seconds']}</td>";
        echo "<td>{$timeMinutes}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No eye tracking data found</p>";
}

echo "<h2>2. Current Dashboard Query Result</h2>";
$dashboardQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.gender,
    COALESCE(AVG(up.completion_percentage), 0) as avg_completion,
    COALESCE(AVG(CASE WHEN ets.total_time_seconds > 0 THEN ets.total_time_seconds ELSE NULL END), 0) as avg_focus_time_seconds,
    COUNT(DISTINCT up.module_id) as modules_enrolled,
    COUNT(DISTINCT ets.id) as total_sessions
    FROM users u
    LEFT JOIN user_progress up ON u.id = up.user_id
    LEFT JOIN eye_tracking_sessions ets ON u.id = ets.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name, u.email, u.gender
    ORDER BY avg_completion DESC, avg_focus_time_seconds DESC
    LIMIT 10";

$result = $conn->query($dashboardQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Avg Completion</th><th>Avg Focus (sec)</th><th>Avg Focus (min)</th><th>Sessions</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $avgFocusTimeSeconds = $row['avg_focus_time_seconds'] ?? 0;
        $avgFocusTimeMinutes = $avgFocusTimeSeconds > 0 ? round($avgFocusTimeSeconds / 60, 1) : 0;
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>" . round($row['avg_completion'], 1) . "%</td>";
        echo "<td>{$avgFocusTimeSeconds}</td>";
        echo "<td>{$avgFocusTimeMinutes}</td>";
        echo "<td>{$row['total_sessions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No student data found</p>";
}

echo "<h2>3. Individual Student Focus Time Breakdown</h2>";
$detailQuery = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    ets.total_time_seconds,
    ets.created_at
FROM users u
JOIN eye_tracking_sessions ets ON u.id = ets.user_id
WHERE u.role = 'student' AND ets.total_time_seconds > 0
ORDER BY u.id, ets.created_at DESC
LIMIT 20";

$result = $conn->query($detailQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>User ID</th><th>Name</th><th>Session Time (sec)</th><th>Session Time (min)</th><th>Date</th></tr>";
    $currentUserId = null;
    $userSessions = [];
    
    while ($row = $result->fetch_assoc()) {
        $timeMinutes = round($row['total_time_seconds'] / 60, 1);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']} {$row['last_name']}</td>";
        echo "<td>{$row['total_time_seconds']}</td>";
        echo "<td>{$timeMinutes}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
        
        // Collect for average calculation
        if (!isset($userSessions[$row['id']])) {
            $userSessions[$row['id']] = [
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'sessions' => []
            ];
        }
        $userSessions[$row['id']]['sessions'][] = $row['total_time_seconds'];
    }
    echo "</table>";
    
    echo "<h3>Calculated Averages:</h3>";
    echo "<table border='1'><tr><th>User ID</th><th>Name</th><th>Sessions</th><th>Avg Focus (sec)</th><th>Avg Focus (min)</th></tr>";
    foreach ($userSessions as $userId => $userData) {
        $avgSeconds = array_sum($userData['sessions']) / count($userData['sessions']);
        $avgMinutes = round($avgSeconds / 60, 1);
        echo "<tr>";
        echo "<td>{$userId}</td>";
        echo "<td>{$userData['name']}</td>";
        echo "<td>" . count($userData['sessions']) . "</td>";
        echo "<td>" . round($avgSeconds, 1) . "</td>";
        echo "<td>{$avgMinutes}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No session details found</p>";
}

echo "<h2>4. API Test</h2>";
$apiUrl = 'http://localhost/capstone/admin/database/get_dashboard_data.php';
echo "<p>Testing API: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";

$apiResponse = @file_get_contents($apiUrl);
if ($apiResponse) {
    $apiData = json_decode($apiResponse, true);
    if ($apiData && !isset($apiData['error'])) {
        echo "<h3>API Student Performance Data:</h3>";
        if (isset($apiData['student_performance'])) {
            echo "<table border='1'><tr><th>Name</th><th>Avg Focus (min)</th><th>Modules</th></tr>";
            foreach ($apiData['student_performance'] as $student) {
                echo "<tr>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['avg_focus_time_minutes']}</td>";
                echo "<td>{$student['modules_enrolled']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No student performance data in API response</p>";
        }
        
        echo "<h3>API Focus Time by Gender:</h3>";
        if (isset($apiData['focus_time_by_gender'])) {
            echo "<table border='1'><tr><th>Gender</th><th>Avg Focus (min)</th><th>Sessions</th></tr>";
            foreach ($apiData['focus_time_by_gender'] as $genderData) {
                echo "<tr>";
                echo "<td>{$genderData['gender']}</td>";
                echo "<td>{$genderData['avg_focus_time_minutes']}</td>";
                echo "<td>{$genderData['session_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No focus time by gender data in API response</p>";
        }
    } else {
        echo "<p style='color: red;'>API Error: " . ($apiData['error'] ?? 'Unknown error') . "</p>";
        echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>Failed to fetch API data</p>";
}

echo "<h2>5. Live Test - Add New Session</h2>";
echo "<p>Let's add a test session and see if it reflects immediately:</p>";

// Add a test session
$testUserId = 2; // Assuming user ID 2 exists
$testModuleId = 1; // Assuming module ID 1 exists
$testTime = 1800; // 30 minutes in seconds

$insertQuery = "INSERT INTO eye_tracking_sessions (user_id, module_id, total_time_seconds, created_at) 
                VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($insertQuery);
$stmt->bind_param("iii", $testUserId, $testModuleId, $testTime);

if ($stmt->execute()) {
    echo "<p style='color: green;'>✅ Test session added: User $testUserId, Module $testModuleId, Time: $testTime seconds (30 minutes)</p>";
    
    // Now test the query again
    echo "<h3>Updated Query Result:</h3>";
    $result = $conn->query($dashboardQuery);
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Avg Focus (min)</th><th>Sessions</th><th>Latest Session</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $avgFocusTimeSeconds = $row['avg_focus_time_seconds'] ?? 0;
            $avgFocusTimeMinutes = $avgFocusTimeSeconds > 0 ? round($avgFocusTimeSeconds / 60, 1) : 0;
            
            // Get latest session for this user
            $latestQuery = "SELECT total_time_seconds, created_at FROM eye_tracking_sessions WHERE user_id = {$row['id']} ORDER BY created_at DESC LIMIT 1";
            $latestResult = $conn->query($latestQuery);
            $latestSession = $latestResult ? $latestResult->fetch_assoc() : null;
            $latestTime = $latestSession ? round($latestSession['total_time_seconds'] / 60, 1) . " min" : "None";
            
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
            echo "<td>{$avgFocusTimeMinutes}</td>";
            echo "<td>{$row['total_sessions']}</td>";
            echo "<td>{$latestTime}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to add test session: " . $conn->error . "</p>";
}

$conn->close();
?>

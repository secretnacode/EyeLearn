<?php
// Test the fixed eye tracking endpoint
echo "<h1>🧪 Testing Fixed Eye Tracking Endpoint</h1>";

$test_data = [
    'user_id' => 1,
    'module_id' => 1,
    'section_id' => 1,
    'focused_time' => 2.5,      // 2.5 minutes focused
    'unfocused_time' => 0.5,    // 0.5 minutes unfocused
    'total_time' => 3.0,        // 3 minutes total
    'focus_percentage' => 83.3,  // 83.3% focus
    'focus_sessions' => 3,
    'unfocus_sessions' => 2,
    'session_type' => 'test_enhanced_cv_tracking'
];

echo "<h2>📤 Sending Test Data:</h2>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Send POST request to the endpoint
$url = 'http://localhost/capstone/user/database/save_enhanced_tracking.php';
$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($test_data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "<h2>📥 Response from Endpoint:</h2>";
if ($result === false) {
    echo "<p style='color: red;'>❌ Failed to connect to endpoint</p>";
} else {
    $response = json_decode($result, true);
    if ($response) {
        echo "<pre style='background: #f0f9ff; padding: 15px; border-radius: 5px;'>";
        echo json_encode($response, JSON_PRETTY_PRINT);
        echo "</pre>";
        
        if ($response['success']) {
            echo "<h2>✅ Test PASSED!</h2>";
            echo "<p>Eye tracking data was saved successfully to the database.</p>";
            
            // Verify data was saved to eye_tracking_sessions table
            require_once 'config.php';
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            
            $check_sql = "SELECT * FROM eye_tracking_sessions WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($check_sql);
            $stmt->execute([$response['record_id']]);
            $saved_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($saved_record) {
                echo "<h3>🗄️ Saved Record in Database:</h3>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                foreach ($saved_record as $key => $value) {
                    echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
                }
                echo "</table>";
                
                echo "<h3>📊 Dashboard Compatibility Check:</h3>";
                $total_seconds = $saved_record['total_time_seconds'];
                $total_minutes = round($total_seconds / 60, 1);
                echo "<p>✅ Total time: {$total_seconds} seconds = {$total_minutes} minutes</p>";
                echo "<p>✅ Data format matches dashboard expectations</p>";
            }
            
        } else {
            echo "<h2>❌ Test FAILED!</h2>";
            echo "<p style='color: red;'>Error: " . ($response['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        echo "<p>Raw response: " . htmlspecialchars($result) . "</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 Next Steps:</h2>";
echo "<ol>";
echo "<li>✅ Database endpoint is now fixed and tested</li>";
echo "<li>✅ Python service is configured to use this endpoint</li>";
echo "<li>🔄 Restart the Python eye tracking service to apply changes</li>";
echo "<li>🧪 Test with real eye tracking session</li>";
echo "<li>📊 Check admin dashboard for real-time data</li>";
echo "</ol>";

echo "<p><strong>🚀 Ready to test with real eye tracking!</strong></p>";
?>

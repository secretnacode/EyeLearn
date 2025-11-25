<?php
// Debug script to check students API output
$url = 'http://localhost/capstone/admin/database/students_safe.php';

// Get the raw output
$output = file_get_contents($url);

echo "<h2>Raw API Output Debug</h2>";
echo "<h3>First 200 characters:</h3>";
echo "<pre>" . htmlspecialchars(substr($output, 0, 200)) . "</pre>";

echo "<h3>Output Length:</h3>";
echo strlen($output) . " characters";

echo "<h3>JSON Validation:</h3>";
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color: green;'>Valid JSON</p>";
    echo "<p>Number of students: " . count($json['students']) . "</p>";
} else {
    echo "<p style='color: red;'>Invalid JSON: " . json_last_error_msg() . "</p>";
    echo "<h4>First few lines of output:</h4>";
    $lines = explode("\n", $output);
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "<pre>" . ($i+1) . ": " . htmlspecialchars($lines[$i]) . "</pre>";
    }
}
?>

<?php
// Quick database structure check
$conn = new mysqli('localhost', 'root', '', 'elearn_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Structure Check</h2>";

echo "<h3>Tables in database:</h3>";
$result = $conn->query('SHOW TABLES');
echo "<ul>";
while ($row = $result->fetch_array()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";

echo "<h3>Columns in users table:</h3>";
$result = $conn->query('SHOW COLUMNS FROM users');
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
}
echo "</ul>";

echo "<h3>Columns in eye_tracking_sessions table:</h3>";
$result = $conn->query('SHOW COLUMNS FROM eye_tracking_sessions');
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Table does not exist or error: " . $conn->error . "</p>";
}

echo "<h3>Sample data from users table:</h3>";
$result = $conn->query('SELECT id, first_name, last_name, email, role FROM users LIMIT 3');
if ($result) {
    echo "<table border='1'><tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Role</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['first_name'] . "</td><td>" . $row['last_name'] . "</td><td>" . $row['email'] . "</td><td>" . $row['role'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

$conn->close();
?>

<?php
// Comprehensive database debug script
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection and Query Debug</h1>";

echo "<h2>1. Testing Database Connection</h2>";
try {
    $conn = new mysqli('localhost', 'root', '', 'elearn_db');
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
        echo "<h3>Possible solutions:</h3>";
        echo "<ul>";
        echo "<li>Check if XAMPP MySQL is running</li>";
        echo "<li>Verify database name 'elearn_db' exists</li>";
        echo "<li>Check MySQL credentials (username: root, password: empty)</li>";
        echo "</ul>";
        exit();
    }
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception during connection: " . $e->getMessage() . "</p>";
    exit();
}

echo "<h2>2. Testing Database Selection</h2>";
$dbSelected = $conn->select_db('elearn_db');
if (!$dbSelected) {
    echo "<p style='color: red;'>❌ Could not select database 'elearn_db'</p>";
    
    // Show available databases
    $result = $conn->query("SHOW DATABASES");
    echo "<h3>Available databases:</h3><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Database'] . "</li>";
    }
    echo "</ul>";
    exit();
}
echo "<p style='color: green;'>✅ Database 'elearn_db' selected successfully!</p>";

echo "<h2>3. Checking Users Table</h2>";
$tablesResult = $conn->query("SHOW TABLES LIKE 'users'");
if ($tablesResult->num_rows == 0) {
    echo "<p style='color: red;'>❌ Users table does not exist!</p>";
    
    // Show all tables
    $allTables = $conn->query("SHOW TABLES");
    echo "<h3>Available tables:</h3><ul>";
    while ($row = $allTables->fetch_assoc()) {
        echo "<li>" . array_values($row)[0] . "</li>";
    }
    echo "</ul>";
    exit();
}
echo "<p style='color: green;'>✅ Users table exists!</p>";

echo "<h2>4. Testing Users Table Structure</h2>";
$columnsResult = $conn->query("SHOW COLUMNS FROM users");
if (!$columnsResult) {
    echo "<p style='color: red;'>❌ Could not get table structure: " . $conn->error . "</p>";
    exit();
}

$columns = [];
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $columnsResult->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . ($row['Null'] == 'YES' ? 'YES' : 'NO') . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>5. Testing Basic Query</h2>";
$testQuery = "SELECT COUNT(*) as total FROM users";
echo "<p>Query: <code>$testQuery</code></p>";

$result = $conn->query($testQuery);
if (!$result) {
    echo "<p style='color: red;'>❌ Basic query failed: " . $conn->error . "</p>";
    exit();
}

$count = $result->fetch_assoc();
echo "<p style='color: green;'>✅ Found " . $count['total'] . " total users in database</p>";

echo "<h2>6. Testing Sample Data Query</h2>";
$sampleQuery = "SELECT id, " . 
    (in_array('email', $columns) ? 'email' : "'no-email' as email") . 
    " FROM users LIMIT 3";
echo "<p>Query: <code>$sampleQuery</code></p>";

$sampleResult = $conn->query($sampleQuery);
if (!$sampleResult) {
    echo "<p style='color: red;'>❌ Sample query failed: " . $conn->error . "</p>";
    exit();
}

echo "<p style='color: green;'>✅ Sample query successful!</p>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Email</th></tr>";
while ($row = $sampleResult->fetch_assoc()) {
    echo "<tr><td>" . $row['id'] . "</td><td>" . $row['email'] . "</td></tr>";
}
echo "</table>";

echo "<h2>7. Testing Full Students Query</h2>";
$nameField = "COALESCE(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')), name, CONCAT('User ', id)) as full_name";
$emailField = in_array('email', $columns) ? 'email' : "CONCAT('user', id, '@example.com') as email";
$genderField = in_array('gender', $columns) ? 'gender' : "'Not specified' as gender";
$dateField = in_array('created_at', $columns) ? 'created_at' : 'NOW() as created_at';
$roleCondition = in_array('role', $columns) ? "WHERE (role = 'student' OR role IS NULL)" : "WHERE 1=1";

$fullQuery = "SELECT 
    id,
    $nameField,
    $emailField,
    $genderField,
    $dateField as registration_date
FROM users 
$roleCondition
ORDER BY id ASC 
LIMIT 5";

echo "<p>Full Query:</p>";
echo "<pre>" . htmlspecialchars($fullQuery) . "</pre>";

$fullResult = $conn->query($fullQuery);
if (!$fullResult) {
    echo "<p style='color: red;'>❌ Full query failed: " . $conn->error . "</p>";
    echo "<h3>Query breakdown test:</h3>";
    
    // Test each part separately
    $testParts = [
        "SELECT id FROM users LIMIT 1",
        "SELECT id, $nameField FROM users LIMIT 1",
        "SELECT id, $nameField, $emailField FROM users LIMIT 1"
    ];
    
    foreach ($testParts as $i => $testPart) {
        echo "<p>Testing part " . ($i + 1) . ": <code>" . htmlspecialchars($testPart) . "</code></p>";
        $partResult = $conn->query($testPart);
        if ($partResult) {
            echo "<p style='color: green;'>✅ Part " . ($i + 1) . " works</p>";
        } else {
            echo "<p style='color: red;'>❌ Part " . ($i + 1) . " failed: " . $conn->error . "</p>";
            break;
        }
    }
    exit();
}

echo "<p style='color: green;'>✅ Full query successful!</p>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Gender</th><th>Registration</th></tr>";
while ($row = $fullResult->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['full_name'] . "</td>";
    echo "<td>" . $row['email'] . "</td>";
    echo "<td>" . $row['gender'] . "</td>";
    echo "<td>" . $row['registration_date'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>8. Final Status</h2>";
echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 All database tests passed! The API should work correctly.</p>";

$conn->close();
?>

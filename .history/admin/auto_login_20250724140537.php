<?php
// Auto-login as admin for testing
session_start();

include '../config.php';

// Authenticate admin user
$email = 'admin@admin.eyelearn';
$password = 'SecureAdminPassword123!';

$user = authenticateUser($email, $password, $pdo);

if ($user) {
    createUserSession($user);
    echo "<h1>Admin Login Successful!</h1>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Role: " . $_SESSION['role'] . "</p>";
    echo "<p>Email: " . $_SESSION['email'] . "</p>";
    
    echo "<h2>Testing Dashboard API</h2>";
    
    // Test the API
    $api_response = file_get_contents('http://localhost/capstone/admin/database/get_dashboard_data.php', false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Cookie: ' . session_name() . '=' . session_id() . "\r\n"
        ]
    ]));
    
    echo "<h3>API Response:</h3>";
    echo "<pre>" . htmlspecialchars($api_response) . "</pre>";
    
    echo '<p><a href="Adashboard.php">Go to Admin Dashboard</a></p>';
} else {
    echo "<h1>Login Failed</h1>";
    echo "<p>Could not authenticate admin user</p>";
}
?>

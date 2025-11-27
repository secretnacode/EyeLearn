<?php
// Environment-specific configuration for EyeLearn

// Detect environment
$environment = 'production'; // Change to 'development' for local testing

if ($environment === 'development') {
    // Development settings
    $config = [
        'db_host' => 'localhost',
        'db_user' => 'root',
        'db_pass' => '',
        'db_name' => 'elearn_db',
        'debug' => true,
        'error_reporting' => E_ALL,
        'display_errors' => 1,
        'base_url' => 'http://localhost/capstone/',
        'python_service_url' => 'http://localhost:5000'
    ];
} else {
    // Production settings
    $config = [
        'db_host' => 'localhost', // Your production DB host
        'db_user' => 'eyellearn_user', // Your production DB user
        'db_pass' => 'your_secure_password', // Your production DB password
        'db_name' => 'elearn_db',
        'debug' => false,
        'error_reporting' => 0,
        'display_errors' => 0,
        'base_url' => 'https://yourdomain.com/',
        'python_service_url' => 'http://localhost:5000'
    ];
}

// Apply settings
if ($config['debug']) {
    error_reporting($config['error_reporting']);
    ini_set('display_errors', $config['display_errors']);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Database connection
function getDBConnection() {
    global $config;
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );
    
    if ($conn->connect_error) {
        if ($config['debug']) {
            die("Connection failed: " . $conn->connect_error);
        } else {
            die("Database connection error");
        }
    }
    
    return $conn;
}

// Export config for use in other files
return $config;
?>

<?php
/**
 * EyeLearn Configuration Template
 * Copy this file to config.php and update the settings below
 */

// Database Configuration
$servername = "localhost";        // Database server (usually localhost)
$username = "root";               // Database username (default: root for XAMPP)
$password = "";                   // Database password (default: empty for XAMPP)
$dbname = "elearn_db";           // Database name

// Eye Tracking Service Configuration
$eye_tracking_host = "127.0.0.1"; // Eye tracking service host
$eye_tracking_port = 5000;         // Eye tracking service port

// Application Settings
$app_name = "EyeLearn";
$app_version = "1.0.0";
$debug_mode = true;               // Set to false in production

// Session Configuration
$session_timeout = 3600;         // Session timeout in seconds (1 hour)

// File Upload Settings
$max_file_size = 10 * 1024 * 1024; // Maximum file size (10MB)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

// Eye Tracking Settings
$tracking_save_interval = 10;     // Save tracking data every X seconds
$focus_threshold = 0.6;           // Focus detection threshold (0.0-1.0)
$countdown_duration = 3;          // Countdown before tracking starts (seconds)

// Security Settings
$password_min_length = 6;         // Minimum password length
$max_login_attempts = 5;          // Maximum failed login attempts
$lockout_duration = 900;          // Account lockout duration (15 minutes)

// Email Configuration (optional)
$smtp_host = "";                  // SMTP server
$smtp_port = 587;                 // SMTP port
$smtp_username = "";              // SMTP username
$smtp_password = "";              // SMTP password
$from_email = "noreply@eyelearn.local";
$from_name = "EyeLearn Platform";

// Analytics Configuration
$analytics_retention_days = 365;  // Keep analytics data for X days
$real_time_refresh_interval = 30; // Dashboard refresh interval (seconds)

// Path Configuration
$base_path = "/capstone";         // Base application path
$upload_path = "uploads/";        // Upload directory
$log_path = "logs/";             // Log directory

// Development Settings
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Create database connection
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    if ($debug_mode) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact support.");
    }
}

// Legacy MySQL connection (for older code compatibility)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    if ($debug_mode) {
        die("Connection failed: " . $conn->connect_error);
    } else {
        die("Database connection failed. Please contact support.");
    }
}

// Helper functions
function get_eye_tracking_url($endpoint = '') {
    global $eye_tracking_host, $eye_tracking_port;
    return "http://{$eye_tracking_host}:{$eye_tracking_port}" . ($endpoint ? "/{$endpoint}" : "");
}

function is_debug_mode() {
    global $debug_mode;
    return $debug_mode;
}

function get_app_info() {
    global $app_name, $app_version;
    return [
        'name' => $app_name,
        'version' => $app_version
    ];
}
?>

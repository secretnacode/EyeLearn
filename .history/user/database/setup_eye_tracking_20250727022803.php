<?php
// Eye Tracking Database Setup Script
// Run this once to create the necessary tables for eye tracking

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "elearn_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Define SQL statements directly
    $sql_statements = [
        "CREATE TABLE IF NOT EXISTS `eye_tracking_sessions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `module_id` int(11) NOT NULL,
          `section_id` int(11) DEFAULT NULL,
          `total_time_seconds` int(11) DEFAULT 0,
          `session_type` enum('viewing','pause','resume') DEFAULT 'viewing',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_module` (`user_id`, `module_id`),
          KEY `idx_user_section` (`user_id`, `section_id`),
          KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `eye_tracking_analytics` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `module_id` int(11) NOT NULL,
          `section_id` int(11) DEFAULT NULL,
          `date` date NOT NULL,
          `total_focus_time` int(11) DEFAULT 0,
          `session_count` int(11) DEFAULT 0,
          `average_session_time` int(11) DEFAULT 0,
          `max_continuous_time` int(11) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_user_module_date` (`user_id`, `module_id`, `section_id`, `date`),
          KEY `idx_user_date` (`user_id`, `date`),
          KEY `idx_module_date` (`module_id`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($sql_statements as $statement) {
        try {
            $pdo->exec($statement);
            echo "✓ Executed table creation successfully.\n";
        } catch (PDOException $e) {
            echo "✗ Error executing statement: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nEye tracking database setup completed!\n";
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>

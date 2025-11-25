<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eye Tracking System Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-2xl w-full">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">👁️ Eye Tracking System Setup</h1>
        
        <?php
        if (isset($_POST['setup'])) {
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "elearn_db";

            try {
                $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo '<div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">';
                echo "✅ Connected to database successfully!<br>";
                
                // Define SQL statements directly
                $sql_statements = [
                    "eye_tracking_sessions" => "CREATE TABLE IF NOT EXISTS `eye_tracking_sessions` (
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
                    
                    "eye_tracking_analytics" => "CREATE TABLE IF NOT EXISTS `eye_tracking_analytics` (
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
                
                foreach ($sql_statements as $table_name => $statement) {
                    try {
                        $pdo->exec($statement);
                        echo "✅ Created table: $table_name<br>";
                    } catch (PDOException $e) {
                        echo "❌ Error creating $table_name: " . $e->getMessage() . "<br>";
                    }
                }
                
                echo '<br><strong>Eye tracking database setup completed successfully!</strong>';
                echo '</div>';
                
                echo '<div class="mt-4">';
                echo '<a href="user/Smodule.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Go to Modules</a>';
                echo '<a href="admin/eye_tracking_analytics.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 ml-2">View Analytics</a>';
                echo '</div>';
                
            } catch(PDOException $e) {
                echo '<div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">';
                echo "❌ Connection failed: " . $e->getMessage();
                echo '</div>';
            }
        } else {
            // Show setup form
            echo '<div class="mb-6">';
            echo '<p class="text-gray-600 mb-4">This will create the necessary database tables for the eye tracking system:</p>';
            echo '<ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">';
            echo '<li><strong>eye_tracking_sessions</strong> - Stores individual tracking sessions</li>';
            echo '<li><strong>eye_tracking_analytics</strong> - Stores aggregated analytics data</li>';
            echo '</ul>';
            echo '<p class="text-sm text-gray-500 mb-4">⚠️ Make sure your MySQL server is running and the database "elearn_db" exists.</p>';
            echo '</div>';
            
            echo '<form method="POST">';
            echo '<button type="submit" name="setup" class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600 transition-colors">';
            echo '🚀 Setup Eye Tracking System';
            echo '</button>';
            echo '</form>';
        }
        ?>
        
        <div class="mt-6 pt-6 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">📋 What This System Does:</h3>
            <ul class="text-sm text-gray-600 space-y-1">
                <li>• Tracks when users are actively viewing module content</li>
                <li>• Pauses timer when users switch tabs or lose focus</li>
                <li>• Monitors mouse movement and keyboard activity</li>
                <li>• Provides real-time study time statistics</li>
                <li>• Generates analytics for educators</li>
            </ul>
        </div>
    </div>
</body>
</html>

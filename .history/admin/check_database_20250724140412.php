<?php
// Check database tables and structure
include '../config.php';

echo "<h1>Database Structure Check</h1>";

try {
    // Check what tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Existing Tables:</h2><ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check if key tables exist
    $requiredTables = ['users', 'modules', 'eye_tracking_sessions'];
    
    echo "<h2>Required Tables Check:</h2>";
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "<p>✓ $table exists</p>";
            
            // Count records in each table
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "<p>&nbsp;&nbsp;→ Records: $count</p>";
            
            if ($table === 'users') {
                // Check user roles
                $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
                $stmt->execute();
                $roles = $stmt->fetchAll();
                echo "<p>&nbsp;&nbsp;→ User roles:</p><ul>";
                foreach ($roles as $role) {
                    echo "<li>{$role['role']}: {$role['count']}</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p>✗ $table MISSING</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>

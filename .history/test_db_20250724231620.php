<?php
// Simple database test
try {
    $pdo = new PDO("mysql:host=localhost;dbname=elearn_db", "root", "");
    echo "Database connection successful!\n";
    
    // Test if tables exist
    $result = $pdo->query("SHOW TABLES LIKE 'eye_tracking_%'");
    $tables = $result->fetchAll();
    
    if (count($tables) > 0) {
        echo "Eye tracking tables already exist:\n";
        foreach ($tables as $table) {
            echo "- " . $table[0] . "\n";
        }
    } else {
        echo "No eye tracking tables found. Need to create them.\n";
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>

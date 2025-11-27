<?php
// Add camera agreement columns to users table
require_once 'config.php';

try {
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hasAgreementColumn = in_array('camera_agreement_accepted', $columns);
    $hasDateColumn = in_array('camera_agreement_date', $columns);
    
    if (!$hasAgreementColumn) {
        echo "Adding camera_agreement_accepted column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN camera_agreement_accepted TINYINT(1) DEFAULT 0");
        echo "✅ Added camera_agreement_accepted column\n";
    } else {
        echo "camera_agreement_accepted column already exists\n";
    }
    
    if (!$hasDateColumn) {
        echo "Adding camera_agreement_date column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN camera_agreement_date DATETIME NULL");
        echo "✅ Added camera_agreement_date column\n";
    } else {
        echo "camera_agreement_date column already exists\n";
    }
    
    echo "Database schema updated successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

<?php
include '../../config.php';

echo "Ensuring all existing students have proper progress records...\n\n";

// Get all students
$stmt = $pdo->prepare('SELECT id, first_name, last_name FROM users WHERE role = "student"');
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all published modules
$stmt = $pdo->prepare('SELECT id, title FROM modules WHERE status = "published"');
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($students) . " students and " . count($modules) . " published modules\n\n";

$created_records = 0;

foreach ($students as $student) {
    echo "Checking {$student['first_name']} {$student['last_name']} (ID: {$student['id']})...\n";
    
    foreach ($modules as $module) {
        // Check if progress record exists
        $stmt = $pdo->prepare('SELECT id FROM user_progress WHERE user_id = ? AND module_id = ?');
        $stmt->execute([$student['id'], $module['id']]);
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Create progress record with 0% completion
            $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, module_id, completion_percentage, completed, last_accessed) VALUES (?, ?, 0, 0, NOW())");
            $stmt->execute([$student['id'], $module['id']]);
            echo "  Created progress record for module: {$module['title']}\n";
            $created_records++;
        }
    }
}

echo "\nCompleted! Created $created_records new progress records.\n";

// Final verification - show current dashboard stats
echo "\nCurrent dashboard statistics:\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student'");
$stmt->execute();
$total_students = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE status = 'published'");
$stmt->execute();
$active_modules = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM user_progress WHERE completed = 1");
$stmt->execute();
$completed_students = $stmt->fetchColumn();

$completion_rate = $total_students > 0 ? round(($completed_students / $total_students) * 100, 1) : 0;

$stmt = $pdo->prepare("SELECT AVG(completion_percentage) FROM user_progress WHERE completion_percentage > 0");
$stmt->execute();
$avg_score = round($stmt->fetchColumn() ?: 0, 1);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM eye_tracking_sessions");
$stmt->execute();
$tracking_sessions = $stmt->fetchColumn();

echo "Total Students: $total_students\n";
echo "Active Modules: $active_modules\n";
echo "Completion Rate: $completion_rate%\n";
echo "Average Score: $avg_score%\n";
echo "Eye Tracking Sessions: $tracking_sessions\n";
?>

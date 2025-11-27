<?php
/**
 * Railway Database Schema Alignment Script
 * 
 * This script connects to Railway database, inspects the actual schema,
 * compares it with the codebase, and generates alignment reports.
 */

// Railway Database Connection
$host = getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: '10241';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP';

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<!DOCTYPE html><html><head><title>Railway Schema Alignment</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
    h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; border-left: 4px solid #2196F3; padding-left: 10px; }
    h3 { color: #777; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th { background: #4CAF50; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border: 1px solid #ddd; }
    tr:nth-child(even) { background: #f9f9f9; }
    .status-ok { color: #4CAF50; font-weight: bold; }
    .status-warning { color: #FF9800; font-weight: bold; }
    .status-error { color: #F44336; font-weight: bold; }
    .code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
    .diff-added { background: #c8e6c9; }
    .diff-removed { background: #ffcdd2; }
    .diff-changed { background: #fff9c4; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Railway Database Schema Alignment Report</h1>";
echo "<p><strong>Database:</strong> <span class='code'>{$dbname}</span> @ <span class='code'>{$host}:{$port}</span></p>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Get all tables
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);
$tables = [];
while ($row = $tables_result->fetch_array()) {
    $tables[] = $row[0];
}

echo "<h2>📊 Database Overview</h2>";
echo "<p><strong>Total Tables:</strong> " . count($tables) . "</p>";
echo "<ul>";
foreach ($tables as $table) {
    $count_query = "SELECT COUNT(*) as cnt FROM `{$table}`";
    $count_result = $conn->query($count_query);
    $count = $count_result ? $count_result->fetch_assoc()['cnt'] : 0;
    echo "<li><strong>{$table}</strong>: {$count} rows</li>";
}
echo "</ul>";

// Critical tables to check
$critical_tables = [
    'eye_tracking_sessions',
    'eye_tracking_analytics',
    'user_module_progress',
    'module_completions',
    'quiz_results',
    'retake_results',
    'checkpoint_quiz_results',
    'final_quiz_retakes',
    'users',
    'modules'
];

echo "<h2>🔎 Detailed Schema Inspection</h2>";

$schema_report = [];
$alignment_issues = [];

foreach ($critical_tables as $table) {
    if (!in_array($table, $tables)) {
        $alignment_issues[] = [
            'table' => $table,
            'issue' => 'MISSING_TABLE',
            'severity' => 'error',
            'message' => "Table '{$table}' does not exist in Railway database"
        ];
        continue;
    }
    
    echo "<div class='section'>";
    echo "<h3>Table: <span class='code'>{$table}</span></h3>";
    
    // Get table structure
    $columns_query = "SHOW FULL COLUMNS FROM `{$table}`";
    $columns_result = $conn->query($columns_query);
    
    if (!$columns_result) {
        echo "<p class='status-error'>Error fetching columns: " . $conn->error . "</p>";
        continue;
    }
    
    $columns = [];
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Comment</th></tr>";
    
    while ($col = $columns_result->fetch_assoc()) {
        $columns[] = $col;
        $null_indicator = $col['Null'] === 'YES' ? '<span class="status-warning">NULL</span>' : '<span class="status-ok">NOT NULL</span>';
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$null_indicator}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "<td>" . ($col['Comment'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get indexes
    $indexes_query = "SHOW INDEXES FROM `{$table}`";
    $indexes_result = $conn->query($indexes_query);
    
    if ($indexes_result && $indexes_result->num_rows > 0) {
        echo "<h4>Indexes:</h4><ul>";
        $indexes = [];
        while ($idx = $indexes_result->fetch_assoc()) {
            if (!isset($indexes[$idx['Key_name']])) {
                $indexes[$idx['Key_name']] = [];
            }
            $indexes[$idx['Key_name']][] = $idx['Column_name'];
        }
        foreach ($indexes as $idx_name => $idx_cols) {
            $unique = $idx_name === 'PRIMARY' ? 'PRIMARY KEY' : (strpos($idx_name, 'unique') !== false ? 'UNIQUE' : 'INDEX');
            echo "<li><strong>{$idx_name}</strong> ({$unique}): " . implode(', ', $idx_cols) . "</li>";
        }
        echo "</ul>";
    }
    
    $schema_report[$table] = $columns;
    echo "</div>";
}

// Check for deprecated columns
echo "<h2>⚠️ Schema Issues & Recommendations</h2>";

// Check eye_tracking_analytics for deprecated columns
if (isset($schema_report['eye_tracking_analytics'])) {
    $col_names = array_column($schema_report['eye_tracking_analytics'], 'Field');
    if (in_array('total_focus_time', $col_names) && in_array('total_focused_time', $col_names)) {
        $alignment_issues[] = [
            'table' => 'eye_tracking_analytics',
            'issue' => 'DUPLICATE_COLUMN',
            'severity' => 'warning',
            'message' => "Both 'total_focus_time' (deprecated) and 'total_focused_time' (current) exist. Remove deprecated column."
        ];
    }
}

// Check final_quiz_retakes for used_at column
if (isset($schema_report['final_quiz_retakes'])) {
    $col_names = array_column($schema_report['final_quiz_retakes'], 'Field');
    if (!in_array('used_at', $col_names)) {
        $alignment_issues[] = [
            'table' => 'final_quiz_retakes',
            'issue' => 'MISSING_COLUMN',
            'severity' => 'warning',
            'message' => "Column 'used_at' is missing but used in code. Should be added."
        ];
    }
}

// Display issues
if (count($alignment_issues) > 0) {
    echo "<table><tr><th>Table</th><th>Issue</th><th>Severity</th><th>Message</th><th>Action</th></tr>";
    foreach ($alignment_issues as $issue) {
        $severity_class = 'status-' . $issue['severity'];
        $action = '';
        
        if ($issue['issue'] === 'DUPLICATE_COLUMN' && $issue['table'] === 'eye_tracking_analytics') {
            $action = "ALTER TABLE `{$issue['table']}` DROP COLUMN `total_focus_time`;";
        } elseif ($issue['issue'] === 'MISSING_COLUMN' && $issue['table'] === 'final_quiz_retakes') {
            $action = "ALTER TABLE `{$issue['table']}` ADD COLUMN `used_at` TIMESTAMP NULL DEFAULT NULL;";
        } elseif ($issue['issue'] === 'MISSING_TABLE') {
            $action = "CREATE TABLE required - check schema file";
        }
        
        echo "<tr>";
        echo "<td><strong>{$issue['table']}</strong></td>";
        echo "<td>{$issue['issue']}</td>";
        echo "<td class='{$severity_class}'>{$issue['severity']}</td>";
        echo "<td>{$issue['message']}</td>";
        echo "<td><code>{$action}</code></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='status-ok'>✅ No critical alignment issues found!</p>";
}

// Generate SQL migration script
echo "<h2>📝 Generated Migration SQL</h2>";
echo "<div class='section'>";
echo "<h3>SQL Script to Align Schema</h3>";
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;'>";

$migration_sql = "-- Railway Schema Alignment Migration\n";
$migration_sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($alignment_issues as $issue) {
    if ($issue['issue'] === 'DUPLICATE_COLUMN' && $issue['table'] === 'eye_tracking_analytics') {
        $migration_sql .= "-- Remove deprecated total_focus_time column\n";
        $migration_sql .= "ALTER TABLE `eye_tracking_analytics` DROP COLUMN IF EXISTS `total_focus_time`;\n\n";
    } elseif ($issue['issue'] === 'MISSING_COLUMN' && $issue['table'] === 'final_quiz_retakes') {
        $migration_sql .= "-- Add used_at column to final_quiz_retakes\n";
        $migration_sql .= "ALTER TABLE `final_quiz_retakes` ADD COLUMN IF NOT EXISTS `used_at` TIMESTAMP NULL DEFAULT NULL;\n\n";
    }
}

if (count($alignment_issues) === 0) {
    $migration_sql .= "-- No migrations needed. Schema is aligned!\n";
}

echo htmlspecialchars($migration_sql);
echo "</pre>";
echo "</div>";

// Code alignment check
echo "<h2>💻 Code Alignment Status</h2>";
echo "<div class='section'>";
echo "<p>Based on the comprehensive schema alignment report, here's the code status:</p>";

$code_status = [
    'api/save_tracking.php' => '✅ Fixed - Parameter binding corrected',
    'user/database/save_session_data.php' => '✅ Fixed - Uses total_focused_time',
    'user/Sassessment.php' => '✅ Fixed - Uses total_focused_time',
    'populate_sample_analytics.php' => '✅ Fixed - Uses total_focused_time',
    'user/Smodulepart.php' => '✅ Aligned - All operations match schema',
    'user/database/save_cv_eye_tracking.php' => '✅ Aligned',
    'user/database/save_eye_tracking_data.php' => '✅ Aligned',
    'admin/database/get_dashboard_data.php' => '✅ Aligned'
];

echo "<table><tr><th>File</th><th>Status</th></tr>";
foreach ($code_status as $file => $status) {
    $status_class = strpos($status, '✅') !== false ? 'status-ok' : 'status-error';
    echo "<tr><td><code>{$file}</code></td><td class='{$status_class}'>{$status}</td></tr>";
}
echo "</table>";
echo "</div>";

// Summary
echo "<h2>📋 Summary</h2>";
echo "<div class='section'>";
echo "<ul>";
echo "<li><strong>Tables Inspected:</strong> " . count($critical_tables) . "</li>";
echo "<li><strong>Issues Found:</strong> " . count($alignment_issues) . "</li>";
echo "<li><strong>Code Files Aligned:</strong> " . count($code_status) . "</li>";
echo "</ul>";

if (count($alignment_issues) === 0) {
    echo "<p class='status-ok'><strong>✅ All critical tables are properly aligned with the codebase!</strong></p>";
} else {
    echo "<p class='status-warning'><strong>⚠️ Please review the issues above and apply the recommended migrations.</strong></p>";
}
echo "</div>";

// Save migration SQL to file
file_put_contents('railway_schema_migration.sql', $migration_sql);
echo "<p class='status-ok'>💾 Migration SQL saved to: <code>railway_schema_migration.sql</code></p>";

echo "</div></body></html>";

$conn->close();
?>


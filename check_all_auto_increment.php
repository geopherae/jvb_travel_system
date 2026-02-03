<?php
require_once 'actions/db.php';

echo "Checking all tables for missing AUTO_INCREMENT...\n\n";

// Tables that should have AUTO_INCREMENT on their id column
$tablesToCheck = [
    'clients',
    'client_visa_applications',
    'client_visa_companions',
    'admin_accounts',
    'audit_logs',
    'messages',
    'notifications',
    'visa_packages'
];

$needsFix = [];

foreach ($tablesToCheck as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        $createTable = $row['Create Table'];
        
        // Check if id column exists and if it has AUTO_INCREMENT
        if (preg_match('/`id`.*?PRIMARY KEY/', $createTable) && strpos($createTable, 'AUTO_INCREMENT') === false) {
            $needsFix[] = $table;
            echo "✗ $table: Missing AUTO_INCREMENT\n";
        } else {
            echo "✓ $table: OK\n";
        }
    }
}

if (!empty($needsFix)) {
    echo "\n\nTables needing fix: " . implode(', ', $needsFix) . "\n";
} else {
    echo "\n\nAll tables are correct!\n";
}

$conn->close();

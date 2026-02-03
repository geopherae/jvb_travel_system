<?php
/**
 * Migration: Add Access Code Columns for Visa Applications
 * 
 * Adds:
 * - access_code column to client_visa_companions (individual companion access code)
 * - group_access_code column to client_visa_applications (group-level access code)
 * 
 * Run via: php migrations/add_access_codes_visa_tables.php
 */

require_once __DIR__ . '/../actions/db.php';

$migrations = [
    // 1. Add access_code to client_visa_companions
    "ALTER TABLE `client_visa_companions` 
     ADD COLUMN `access_code` VARCHAR(100) DEFAULT NULL AFTER `financial_source`" => "Add access_code to client_visa_companions",
    
    // 2. Add group_access_code to client_visa_applications
    "ALTER TABLE `client_visa_applications` 
     ADD COLUMN `group_access_code` VARCHAR(100) DEFAULT NULL AFTER `applicant_status`" => "Add group_access_code to client_visa_applications"
];

echo "Running migrations...\n";
$success = 0;
$failed = 0;

foreach ($migrations as $sql => $description) {
    echo "▶ " . $description . "\n";
    
    if ($conn->query($sql)) {
        echo "  ✅ Success\n";
        $success++;
    } else {
        $error = $conn->error;
        // Check if column already exists (error code 1060)
        if (strpos($error, '1060') !== false) {
            echo "  ℹ️  Column already exists\n";
        } else {
            echo "  ❌ Error: " . $error . "\n";
            $failed++;
        }
    }
}

echo "\n" . ($success + $failed) . " migration(s) checked\n";
echo "✅ " . $success . " successful\n";
if ($failed > 0) {
    echo "❌ " . $failed . " failed\n";
}

$conn->close();
?>

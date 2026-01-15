<?php
/**
 * Migration: Add 'status_confirmed' to user_survey_status survey_type enum
 * 
 * This migration modifies the user_survey_status table to support the new 
 * 'status_confirmed' survey type triggered when a client's booking is confirmed.
 * 
 * Run this migration once by accessing it in the browser:
 * http://localhost/jvb_travel_system/migrations/add_status_confirmed_survey_type.php
 */

require_once __DIR__ . '/../actions/db.php';

$migrationName = 'add_status_confirmed_survey_type';
$logFile = __DIR__ . '/../logs/migrations.log';

function logMigration($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

try {
    logMigration("Starting migration: {$migrationName}");
    
    // Modify the enum to include 'status_confirmed'
    $alterSQL = "ALTER TABLE `user_survey_status` 
                 MODIFY `survey_type` ENUM('first_login','status_confirmed','trip_complete','admin_weekly_survey') NOT NULL";
    
    if ($conn->query($alterSQL) === TRUE) {
        logMigration("✅ Successfully added 'status_confirmed' to survey_type enum");
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px;'>";
        echo "<h2>✅ Migration Successful</h2>";
        echo "<p>The 'status_confirmed' survey type has been added to the user_survey_status table.</p>";
        echo "</div>";
    } else {
        throw new Exception("Failed to modify survey_type enum: " . $conn->error);
    }

} catch (Exception $e) {
    logMigration("❌ Migration failed: " . $e->getMessage());
    echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 20px;'>";
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

// Also check if the column exists in the table already
echo "<hr style='margin: 20px 0;'>";
echo "<h3>Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE user_survey_status");
echo "<table style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 10px;'>Field</th><th style='border: 1px solid #ddd; padding: 10px;'>Type</th><th style='border: 1px solid #ddd; padding: 10px;'>Null</th><th style='border: 1px solid #ddd; padding: 10px;'>Key</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . $row['Field'] . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . $row['Type'] . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . $row['Null'] . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . ($row['Key'] ?: '—') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

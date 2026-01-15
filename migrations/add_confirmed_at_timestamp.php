<?php
/**
 * Migration: Add confirmed_at timestamp to clients table
 * Purpose: Track when a client's status becomes "Confirmed" for accurate onboarding velocity metrics
 * Date: 2026-01-13
 */

require_once __DIR__ . '/../actions/db.php';

// Check if column already exists
$checkStmt = $conn->query("SHOW COLUMNS FROM clients LIKE 'confirmed_at'");
if ($checkStmt->num_rows > 0) {
    echo "✅ Column 'confirmed_at' already exists.\n";
    exit;
}

// Add the column
$alterSQL = "
    ALTER TABLE clients 
    ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL 
    AFTER created_at
";

if ($conn->query($alterSQL) === TRUE) {
    echo "✅ Successfully added 'confirmed_at' column to clients table.\n";
    
    // Backfill existing Confirmed clients with audit log timestamps (best effort)
    $backfillSQL = "
        UPDATE clients c
        LEFT JOIN (
            SELECT target_id, MIN(timestamp) as first_confirmed
            FROM audit_logs
            WHERE action_type = 'update_booking'
              AND target_type = 'client'
              AND changes LIKE '%Confirmed%'
            GROUP BY target_id
        ) al ON al.target_id = c.id
        SET c.confirmed_at = COALESCE(al.first_confirmed, c.created_at)
        WHERE c.status IN ('Confirmed', 'Trip Ongoing', 'Trip Completed')
          AND c.confirmed_at IS NULL
    ";
    
    if ($conn->query($backfillSQL) === TRUE) {
        $affected = $conn->affected_rows;
        echo "✅ Backfilled confirmed_at for {$affected} existing clients.\n";
    } else {
        echo "⚠️ Backfill query failed: " . $conn->error . "\n";
    }
    
    // Log migration
    $logFile = __DIR__ . '/../logs/migrations.log';
    $logEntry = date('Y-m-d H:i:s') . " - add_confirmed_at_timestamp.php executed successfully\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    echo "✅ Migration completed successfully.\n";
} else {
    echo "❌ Error adding column: " . $conn->error . "\n";
    exit(1);
}

$conn->close();
?>

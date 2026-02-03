<?php
/**
 * Migration: Add access_code to client_visa_companions, Remove group_code from clients
 * Date: 2026-01-27
 * 
 * Changes:
 * - Adds individual access_code column to client_visa_companions table (after full_name)
 * - Removes group_code column from clients table
 * - Adds index on access_code for faster lookups
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    // Allow CLI execution for migrations
    if (php_sapi_name() !== 'cli') {
        exit('Direct access not permitted. Run via CLI: php ' . basename(__FILE__));
    }
}

require_once __DIR__ . '/../actions/db.php';

echo "Starting migration: Add access_code to companions, Remove group_code from clients\n";
echo str_repeat("=", 80) . "\n";

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Step 1: Add access_code column to client_visa_companions
    echo "[1/3] Adding access_code column to client_visa_companions...\n";
    $sql1 = "ALTER TABLE `client_visa_companions`
             ADD COLUMN `access_code` VARCHAR(100) NOT NULL DEFAULT '' AFTER `full_name`";
    
    if (!mysqli_query($conn, $sql1)) {
        throw new Exception("Failed to add access_code column: " . mysqli_error($conn));
    }
    echo "✓ access_code column added successfully\n";

    // Step 2: Add index on access_code
    echo "[2/3] Adding index on access_code...\n";
    $sql2 = "ALTER TABLE `client_visa_companions`
             ADD INDEX `idx_companion_access_code` (`access_code`)";
    
    if (!mysqli_query($conn, $sql2)) {
        throw new Exception("Failed to add index: " . mysqli_error($conn));
    }
    echo "✓ Index created successfully\n";

    // Step 3: Remove group_code from clients table
    echo "[3/3] Removing group_code column from clients...\n";
    $sql3 = "ALTER TABLE `clients`
             DROP COLUMN `group_code`";
    
    if (!mysqli_query($conn, $sql3)) {
        throw new Exception("Failed to remove group_code column: " . mysqli_error($conn));
    }
    echo "✓ group_code column removed successfully\n";

    // Commit transaction
    mysqli_commit($conn);
    
    echo str_repeat("=", 80) . "\n";
    echo "✓ Migration completed successfully!\n\n";
    
    echo "Next steps:\n";
    echo "1. Update process_add_visa_client.php to generate access codes for companions\n";
    echo "2. Remove any references to clients.group_code in the codebase\n";
    echo "3. Update authentication logic to check client_visa_companions.access_code\n";
    echo "4. For existing companion records, run:\n";
    echo "   UPDATE client_visa_companions \n";
    echo "   SET access_code = CONCAT(UPPER(SUBSTRING(full_name, 1, 4)), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'))\n";
    echo "   WHERE access_code = '';\n";
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

mysqli_close($conn);
?>

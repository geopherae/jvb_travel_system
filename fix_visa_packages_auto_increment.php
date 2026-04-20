<?php
/**
 * Fix visa_packages table: Add AUTO_INCREMENT to id column
 * This ensures new visa packages get proper sequential IDs
 */

require_once __DIR__ . '/actions/db.php';

try {
    echo "Fixing visa_packages table...\n";

    // Modify the id column to include AUTO_INCREMENT
    $result = $conn->query("
        ALTER TABLE `visa_packages` 
        MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;
    ");

    if ($result) {
        echo "✓ Successfully modified id column to AUTO_INCREMENT\n";
        
        // Get the current max id
        $maxId = $conn->query("SELECT MAX(id) as max_id FROM visa_packages");
        $row = $maxId->fetch_assoc();
        $currentMaxId = $row['max_id'] ?? 0;
        
        // Set AUTO_INCREMENT for next insert
        $nextId = $currentMaxId + 1;
        $conn->query("ALTER TABLE `visa_packages` AUTO_INCREMENT = $nextId;");
        
        echo "✓ Set AUTO_INCREMENT to start at: $nextId\n";
        echo "✓ Fix complete! New visa packages will now get proper sequential IDs.\n";
    } else {
        echo "✗ Error modifying table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>

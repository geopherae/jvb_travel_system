<?php
/**
 * Apply client_visa_requirements table creation migration
 * Run this to recreate the missing table
 */

require_once __DIR__ . '/actions/db.php';

echo "==============================================\n";
echo "Creating client_visa_requirements table\n";
echo "==============================================\n\n";

// Read migration SQL
$migrationFile = __DIR__ . '/migrations/create_client_visa_requirements_table.sql';
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

// Execute migration (multi_query to handle multiple statements)
if ($conn->multi_query($sql)) {
    echo "✓ Migration executed successfully\n\n";
    
    // Clear any pending results
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
} else {
    die("✗ Migration failed: " . $conn->error . "\n");
}

// Verify table was created
$checkTable = $conn->query("SHOW TABLES LIKE 'client_visa_requirements'");
if ($checkTable && $checkTable->num_rows > 0) {
    echo "✓ Table 'client_visa_requirements' verified\n\n";
    
    // Show structure
    $structure = $conn->query("DESCRIBE client_visa_requirements");
    echo "Table structure:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-25s %-30s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    while($col = $structure->fetch_assoc()) {
        printf(
            "%-25s %-30s %-10s %-10s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Key']
        );
    }
    echo str_repeat("-", 80) . "\n\n";
    
    echo "✓ Migration completed successfully!\n";
    echo "\nYou can now use the table in your application.\n";
} else {
    die("✗ Table verification failed\n");
}

$conn->close();

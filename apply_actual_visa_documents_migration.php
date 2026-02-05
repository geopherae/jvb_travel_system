<?php
/**
 * Apply Migration: Create client_actual_visa_documents table
 * 
 * This table stores actual visa documents (passport stamps, visa stickers, etc.)
 * that are uploaded after the embassy releases the visa.
 * 
 * Run this script once to apply the migration.
 */

require_once __DIR__ . '/actions/db.php';

// Read SQL file
$sqlFile = __DIR__ . '/migrations/create_client_actual_visa_documents.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die("Error: Unable to read migration file: $sqlFile\n");
}

try {
    // Execute the SQL
    if ($conn->multi_query($sql)) {
        // Clear remaining results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        echo "✅ Migration applied successfully!\n";
        echo "Table 'client_actual_visa_documents' created.\n";
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify table creation
$result = $conn->query("SHOW TABLES LIKE 'client_actual_visa_documents'");
if ($result && $result->num_rows > 0) {
    echo "✅ Table verified: client_actual_visa_documents exists\n";
} else {
    echo "⚠️ Warning: Table verification failed\n";
}

$conn->close();

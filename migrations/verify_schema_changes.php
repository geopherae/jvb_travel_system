<?php
/**
 * Verify schema changes
 */

require_once __DIR__ . '/../actions/db.php';

echo "=== client_visa_companions Table Structure ===\n";
$result = mysqli_query($conn, 'DESCRIBE client_visa_companions');
while ($row = mysqli_fetch_assoc($result)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

echo "\n=== Companion Records with Access Codes ===\n";
$result = mysqli_query($conn, 'SELECT id, full_name, access_code FROM client_visa_companions');
while ($row = mysqli_fetch_assoc($result)) {
    echo "  ID: {$row['id']}, Name: {$row['full_name']}, Code: {$row['access_code']}\n";
}

echo "\n=== Checking clients table (group_code should be removed) ===\n";
$result = mysqli_query($conn, 'DESCRIBE clients');
$hasGroupCode = false;
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['Field'] === 'group_code') {
        $hasGroupCode = true;
        break;
    }
}

if ($hasGroupCode) {
    echo "✗ WARNING: group_code column still exists in clients table!\n";
} else {
    echo "✓ group_code column successfully removed from clients table\n";
}

mysqli_close($conn);
?>

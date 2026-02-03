<?php
require_once 'actions/db.php';

echo "Fixing clients table AUTO_INCREMENT...\n\n";

$sql = "ALTER TABLE `clients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT";

if ($conn->query($sql)) {
    echo "✓ Successfully added AUTO_INCREMENT to clients.id\n\n";
    
    // Verify
    $result = $conn->query("SHOW CREATE TABLE clients");
    $row = $result->fetch_assoc();
    if (strpos($row['Create Table'], 'AUTO_INCREMENT') !== false) {
        echo "✓ Verified: clients.id now has AUTO_INCREMENT\n";
    } else {
        echo "✗ Warning: AUTO_INCREMENT may not have been applied\n";
    }
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

$conn->close();

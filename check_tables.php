<?php
require_once 'actions/db.php';

$result = $conn->query('SHOW TABLES');
echo "Current tables in jvb_travel_db:\n";
echo "================================\n";
while($row = $result->fetch_array()) {
    echo $row[0] . "\n";
}

// Check if client_visa_requirements exists
$checkTable = $conn->query("SHOW TABLES LIKE 'client_visa_requirements'");
if ($checkTable->num_rows > 0) {
    echo "\n✓ client_visa_requirements table EXISTS\n";
    
    // Show structure
    $structure = $conn->query("DESCRIBE client_visa_requirements");
    echo "\nTable structure:\n";
    while($col = $structure->fetch_assoc()) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
} else {
    echo "\n✗ client_visa_requirements table DOES NOT EXIST\n";
}

$conn->close();

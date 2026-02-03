<?php
require_once 'actions/db.php';

echo "Checking foreign key column types:\n\n";

// Check clients.id
$r = $conn->query('DESCRIBE clients');
while($c = $r->fetch_assoc()) {
    if($c['Field'] == 'id') {
        echo "clients.id: {$c['Type']} {$c['Extra']}\n";
    }
}

// Check client_visa_companions.id
$r = $conn->query('DESCRIBE client_visa_companions');
while($c = $r->fetch_assoc()) {
    if($c['Field'] == 'id') {
        echo "client_visa_companions.id: {$c['Type']} {$c['Extra']}\n";
    }
}

$conn->close();

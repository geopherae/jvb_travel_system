<?php
require_once 'actions/db.php';

echo "Checking clients table structure:\n";
$result = $conn->query("SHOW CREATE TABLE clients");
if ($result) {
    $row = $result->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo "ERROR: " . $conn->error;
}

$conn->close();

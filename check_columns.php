<?php
require_once __DIR__ . '/actions/db.php';

// Check clients table
$stmt = $conn->prepare("DESCRIBE clients");
$stmt->execute();
$result = $stmt->get_result();

echo "=== CLIENTS TABLE ===\n";
while ($row = $result->fetch_assoc()) {
  echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
$stmt->close();

// Check client_visa_companions table
$stmt = $conn->prepare("DESCRIBE client_visa_companions");
$stmt->execute();
$result = $stmt->get_result();

echo "\n=== CLIENT_VISA_COMPANIONS TABLE ===\n";
while ($row = $result->fetch_assoc()) {
  echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
$stmt->close();

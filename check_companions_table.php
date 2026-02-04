<?php
require_once __DIR__ . '/actions/db.php';

echo "=== client_visa_companions Table Structure ===\n";
$result = $conn->query('DESCRIBE client_visa_companions');
while ($row = $result->fetch_assoc()) {
  echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== CHECK Constraints ===\n";
$constraintsQuery = "SELECT CONSTRAINT_NAME, CHECK_CLAUSE 
FROM information_schema.CHECK_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() 
AND CONSTRAINT_NAME LIKE '%companion%'";
$result = $conn->query($constraintsQuery);
while ($row = $result->fetch_assoc()) {
  echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
}

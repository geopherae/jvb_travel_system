<?php
require_once __DIR__ . '/actions/db.php';

echo "Fixing client_visa_companions table AUTO_INCREMENT...\n\n";

// Check existing keys
echo "Checking existing keys...\n";
$result = $conn->query("SHOW KEYS FROM client_visa_companions WHERE Key_name = 'PRIMARY'");
$hasPrimaryKey = $result->num_rows > 0;
echo "Primary key exists: " . ($hasPrimaryKey ? 'Yes' : 'No') . "\n\n";

// Step 1: Drop the CHECK constraint
echo "Step 1: Dropping CHECK constraint...\n";
$conn->query("ALTER TABLE client_visa_companions DROP CONSTRAINT IF EXISTS check_companion_id_positive");
echo "✓ Constraint dropped\n\n";

// Step 2: Drop existing primary key if it exists
if ($hasPrimaryKey) {
  echo "Step 2: Dropping existing PRIMARY KEY...\n";
  $conn->query("ALTER TABLE client_visa_companions DROP PRIMARY KEY");
  echo "✓ Primary key dropped\n\n";
}

// Step 3: Modify id column to add AUTO_INCREMENT and set as PRIMARY KEY
echo "Step 3: Adding AUTO_INCREMENT and PRIMARY KEY to id column...\n";
$conn->query("ALTER TABLE client_visa_companions MODIFY id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY");
echo "✓ AUTO_INCREMENT and PRIMARY KEY added\n\n";

echo "Note: CHECK constraint not re-added as AUTO_INCREMENT implicitly ensures id > 0\n\n";

// Verify the changes
echo "=== Verification ===\n";
$result = $conn->query("SHOW CREATE TABLE client_visa_companions");
$row = $result->fetch_assoc();
echo $row['Create Table'] . "\n";

echo "\n✅ Fix completed successfully!\n";

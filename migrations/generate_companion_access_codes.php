<?php
/**
 * Generate access codes for existing companion records
 */

require_once __DIR__ . '/../actions/db.php';

echo "Generating access codes for existing companion records...\n";

$sql = "UPDATE client_visa_companions 
        SET access_code = CONCAT(
            UPPER(SUBSTRING(full_name, 1, 4)), 
            '-', 
            LPAD(FLOOR(RAND() * 10000), 4, '0')
        ) 
        WHERE access_code = ''";

if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "✓ Updated {$affected} companion record(s) with access codes.\n";
} else {
    echo "✗ Error: " . mysqli_error($conn) . "\n";
    exit(1);
}

mysqli_close($conn);
?>

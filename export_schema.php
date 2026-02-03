<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'jvb_travel_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$output = "-- JVB Travel System Database Schema\n";
$output .= "-- Exported: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Database: jvb_travel_db\n\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Get all tables
$result = $conn->query("SHOW TABLES");
$tables = [];

while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Export schema for each table
foreach ($tables as $table) {
    $createTable = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $createTable->fetch_array();
    $output .= "\nDROP TABLE IF EXISTS `" . $table . "`;\n";
    $output .= $row[1] . ";\n";
}

$output .= "\n-- Export admin_accounts data\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Export admin_accounts data
$adminResult = $conn->query("SELECT * FROM admin_accounts");
if ($adminResult->num_rows > 0) {
    $output .= "INSERT INTO `admin_accounts` VALUES\n";
    $first = true;
    while ($row = $adminResult->fetch_assoc()) {
        if (!$first) {
            $output .= ",\n";
        }
        $output .= "(";
        $first_col = true;
        foreach ($row as $value) {
            if (!$first_col) $output .= ", ";
            if ($value === null) {
                $output .= "NULL";
            } else {
                $output .= "'" . $conn->real_escape_string($value) . "'";
            }
            $first_col = false;
        }
        $output .= ")";
        $first = false;
    }
    $output .= ";\n";
}

$output .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

// Write to file
$filename = 'c:\\xampp\\htdocs\\jvb_travel_system\\!--DATABASE BACKUP--!\\jvb_travel_db_schema_empty_with_admin_20260202.sql';
file_put_contents($filename, $output);

echo "Schema exported successfully to: " . $filename . "\n";
echo "File size: " . filesize($filename) . " bytes\n";
echo "Tables exported: " . count($tables) . "\n";

$conn->close();
?>

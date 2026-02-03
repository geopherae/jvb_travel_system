<?php
require_once 'actions/db.php';

echo "Full clients table structure:\n";
echo str_repeat("=", 80) . "\n";
$r = $conn->query('SHOW CREATE TABLE clients');
$row = $r->fetch_assoc();
echo $row['Create Table'] . "\n\n";

echo "Full client_visa_companions table structure:\n";
echo str_repeat("=", 80) . "\n";
$r = $conn->query('SHOW CREATE TABLE client_visa_companions');
$row = $r->fetch_assoc();
echo $row['Create Table'] . "\n";

$conn->close();

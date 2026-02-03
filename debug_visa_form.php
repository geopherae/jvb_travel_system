<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Simulate form submission
$_SESSION['admin'] = ['id' => 1];
$_POST = [
    'assigned_admin_id' => '1',
    'processing_type' => 'visa',
    'application_mode' => 'individual',
    'full_name' => 'Test User',
    'email' => 'test@test.com',
    'phone_number' => '09123456789',
    'address' => 'Test Address',
    'access_code' => 'TEST-1234',
    'passport_number' => 'P123456789',
    'passport_expiry' => '2028-02-02',
    'applicant_status' => 'employed',
    'visa_package_id' => '4',
    'visa_type_selected' => 'Tourist',
    'financial_source' => 'self_funded',
    'group_members_json' => '[]'
];

// Check database connection
require_once 'actions/db.php';

echo "Database connection: " . ($conn ? "OK" : "FAILED") . "\n";
echo "Session admin ID: " . ($_SESSION['admin']['id'] ?? 'MISSING') . "\n";
echo "POST assigned_admin_id: " . ($_POST['assigned_admin_id'] ?? 'MISSING') . "\n";

// Check tables exist
$tables = ['clients', 'client_visa_applications', 'visa_packages'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "Table $table: " . ($result->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";
}

// Check visa package
$pkg = $conn->query("SELECT id, country FROM visa_packages WHERE id = 4");
if ($pkg->num_rows > 0) {
    $row = $pkg->fetch_assoc();
    echo "Visa Package 4: " . $row['country'] . "\n";
} else {
    echo "Visa Package 4: NOT FOUND\n";
}

$conn->close();

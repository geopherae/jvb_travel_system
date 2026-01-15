<?php
declare(strict_types=1);

/**
 * Migration Script: Insert 9 Sample Client Records
 * Run once via terminal: php migrations/seed_sample_clients.php
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die("❌ Database connection failed.\n");
}

// Sample client data with access codes
$sampleClients = [
    [
        'full_name' => 'Maria Santos',
        'email' => 'maria.santos@example.com',
        'phone_number' => '09171234567',
        'status' => 'Confirmed',
        'address' => 'Quezon City, Metro Manila',
        'access_code' => 'MS' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'John Reyes',
        'email' => 'john.reyes@example.com',
        'phone_number' => '09261234568',
        'status' => 'Awaiting Docs',
        'address' => 'Makati City, Metro Manila',
        'access_code' => 'JR' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Sofia Cruz',
        'email' => 'sofia.cruz@example.com',
        'phone_number' => '09351234569',
        'status' => 'Trip Ongoing',
        'address' => 'Cebu City, Cebu',
        'access_code' => 'SC' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Carlos Dela Cruz',
        'email' => 'carlos.delacruz@example.com',
        'phone_number' => '09451234570',
        'status' => 'Under Review',
        'address' => 'Davao City, Davao del Sur',
        'access_code' => 'CD' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Isabel Garcia',
        'email' => 'isabel.garcia@example.com',
        'phone_number' => '09551234571',
        'status' => 'Trip Completed',
        'address' => 'Pasig City, Metro Manila',
        'access_code' => 'IG' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Miguel Ramos',
        'email' => 'miguel.ramos@example.com',
        'phone_number' => '09651234572',
        'status' => 'Confirmed',
        'address' => 'Taguig City, Metro Manila',
        'access_code' => 'MR' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Ana Bautista',
        'email' => 'ana.bautista@example.com',
        'phone_number' => '09751234573',
        'status' => 'Awaiting Docs',
        'address' => 'Iloilo City, Iloilo',
        'access_code' => 'AB' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Rafael Torres',
        'email' => 'rafael.torres@example.com',
        'phone_number' => '09851234574',
        'status' => 'Resubmit Files',
        'address' => 'Baguio City, Benguet',
        'access_code' => 'RT' . strtoupper(bin2hex(random_bytes(4)))
    ],
    [
        'full_name' => 'Elena Mendoza',
        'email' => 'elena.mendoza@example.com',
        'phone_number' => '09951234575',
        'status' => 'Confirmed',
        'address' => 'Angeles City, Pampanga',
        'access_code' => 'EM' . strtoupper(bin2hex(random_bytes(4)))
    ]
];

echo "🚀 Starting client seeding process...\n\n";

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO clients (
            full_name, 
            email, 
            phone_number, 
            status, 
            address, 
            access_code,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $successCount = 0;
    $skippedCount = 0;

    foreach ($sampleClients as $client) {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
        $checkStmt->bind_param('s', $client['email']);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            echo "⚠️  Skipped: {$client['full_name']} (email already exists)\n";
            $skippedCount++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Insert new client
        $stmt->bind_param(
            'ssssss',
            $client['full_name'],
            $client['email'],
            $client['phone_number'],
            $client['status'],
            $client['address'],
            $client['access_code']
        );

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            echo "✅ Inserted: {$client['full_name']} (ID: {$insertedId}, Status: {$client['status']}, Access Code: {$client['access_code']})\n";
            $successCount++;
        } else {
            throw new Exception("Insert failed for {$client['full_name']}: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    echo "\n✨ Seeding completed successfully!\n";
    echo "   - Inserted: {$successCount} clients\n";
    echo "   - Skipped: {$skippedCount} clients (duplicates)\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n✅ Database connection closed.\n";

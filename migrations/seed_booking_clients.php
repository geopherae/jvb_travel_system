<?php
declare(strict_types=1);

/**
 * Migration Script: Insert 10 Sample Booking Clients (Visa-Free)
 * Run once via terminal: php migrations/seed_booking_clients.php
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../actions/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die("❌ Database connection failed.\n");
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$safeColumn}'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function bindParams(mysqli_stmt $stmt, string $types, array $values): void {
    $refs = [];
    $refs[] = $types;
    foreach ($values as $key => $value) {
        $refs[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Fetch active admins (for assignment)
$adminIds = [];
$adminResult = $conn->query("SELECT id FROM admin_accounts WHERE is_active = 1 ORDER BY id ASC");
if ($adminResult) {
    while ($row = $adminResult->fetch_assoc()) {
        $adminIds[] = (int) $row['id'];
    }
}
if (empty($adminIds)) {
    $adminIds = [1];
}

// Fetch visa-free packages only (requires_visa = 0)
$packages = [];
$packageResult = $conn->query("SELECT id, package_name, requires_visa FROM tour_packages WHERE is_deleted = 0");
if ($packageResult) {
    while ($row = $packageResult->fetch_assoc()) {
        if ((int) $row['requires_visa'] === 0) {
            $packages[$row['package_name']] = (int) $row['id'];
        }
    }
}

if (empty($packages)) {
    die("❌ No visa-free tour packages found. Run migrations/seed_sample_tour_packages.php first.\n");
}

$packageNames = array_keys($packages);

// Sample booking clients (visa-free only)
$sampleClients = [
    [
        'full_name' => 'Alyssa Villanueva',
        'email' => 'alyssa.villanueva@example.com',
        'phone_number' => '09171230011',
        'address' => 'Bacoor City, Cavite',
        'status' => 'Confirmed',
        'booking_number' => 'JVB-2026-1001',
        'booking_date' => '2026-01-22',
        'trip_date_start' => '2026-02-14',
        'trip_date_end' => '2026-02-18',
        'package_name' => 'Bali Paradise Retreat',
        'hotel' => 'Seminyak Beach Resort',
        'room_type' => 'Deluxe Ocean View',
        'flight_details' => "PR425 MNL–DPS 09:10-13:45\nPR426 DPS–MNL 16:20-20:10",
        'companions' => ['Marco Villanueva (Spouse)'],
        'transfer_tour_hotline' => '+63 917 555 1022'
    ],
    [
        'full_name' => 'Lorenzo Castillo',
        'email' => 'lorenzo.castillo@example.com',
        'phone_number' => '09281230022',
        'address' => 'Quezon City, Metro Manila',
        'status' => 'Under Review',
        'booking_number' => 'JVB-2026-1002',
        'booking_date' => '2026-01-24',
        'trip_date_start' => '2026-02-20',
        'trip_date_end' => '2026-02-22',
        'package_name' => 'Singapore City Experience',
        'hotel' => 'Orchard Road Suites',
        'room_type' => 'Executive Twin',
        'flight_details' => "SQ921 MNL–SIN 07:35-11:20\nSQ922 SIN–MNL 19:10-22:50",
        'companions' => ['Katrina Castillo (Sister)'],
        'transfer_tour_hotline' => '+63 917 555 1043'
    ],
    [
        'full_name' => 'Rhea Santiago',
        'email' => 'rhea.santiago@example.com',
        'phone_number' => '09351230033',
        'address' => 'Iloilo City, Iloilo',
        'status' => 'Awaiting Docs',
        'booking_number' => 'JVB-2026-1003',
        'booking_date' => '2026-01-25',
        'trip_date_start' => '2026-03-03',
        'trip_date_end' => '2026-03-06',
        'package_name' => 'Bangkok & Pattaya Getaway',
        'hotel' => 'Riverside Bangkok Hotel',
        'room_type' => 'Standard Double',
        'flight_details' => "PR731 MNL–BKK 08:40-11:10\nPR732 BKK–MNL 15:20-19:40",
        'companions' => ['Janelle Santiago (Friend)'],
        'transfer_tour_hotline' => '+66 2 555 2211'
    ],
    [
        'full_name' => 'Paolo Navarro',
        'email' => 'paolo.navarro@example.com',
        'phone_number' => '09461230044',
        'address' => 'Davao City, Davao del Sur',
        'status' => 'Confirmed',
        'booking_number' => 'JVB-2026-1004',
        'booking_date' => '2026-01-26',
        'trip_date_start' => '2026-03-10',
        'trip_date_end' => '2026-03-12',
        'package_name' => 'Cebu Island Hopping Adventure',
        'hotel' => 'Mactan Bay Resort',
        'room_type' => 'Garden View',
        'flight_details' => "PR182 MNL–CEB 10:20-11:55\nPR183 CEB–MNL 20:05-21:35",
        'companions' => ['Lea Navarro (Spouse)', 'Evan Navarro (Child)'],
        'transfer_tour_hotline' => '+63 32 555 7788'
    ],
    [
        'full_name' => 'Camille Uy',
        'email' => 'camille.uy@example.com',
        'phone_number' => '09571230055',
        'address' => 'Makati City, Metro Manila',
        'status' => 'Trip Ongoing',
        'booking_number' => 'JVB-2026-1005',
        'booking_date' => '2026-01-18',
        'trip_date_start' => '2026-01-28',
        'trip_date_end' => '2026-02-01',
        'package_name' => 'Seoul K-Culture Adventure',
        'hotel' => 'Myeongdong Central Hotel',
        'room_type' => 'Superior Queen',
        'flight_details' => "7C2311 MNL–ICN 01:05-06:20\n7C2312 ICN–MNL 19:15-22:25",
        'companions' => ['Bianca Uy (Friend)'],
        'transfer_tour_hotline' => '+82 2 555 3344'
    ],
    [
        'full_name' => 'Ethan Lim',
        'email' => 'ethan.lim@example.com',
        'phone_number' => '09681230066',
        'address' => 'Taguig City, Metro Manila',
        'status' => 'Confirmed',
        'booking_number' => 'JVB-2026-1006',
        'booking_date' => '2026-01-27',
        'trip_date_start' => '2026-03-17',
        'trip_date_end' => '2026-03-20',
        'package_name' => 'Bali Paradise Retreat',
        'hotel' => 'Ubud Valley Resort',
        'room_type' => 'Premier King',
        'flight_details' => "PR429 MNL–DPS 12:30-17:05\nPR430 DPS–MNL 20:15-00:45",
        'companions' => ['Mika Lim (Spouse)'],
        'transfer_tour_hotline' => '+62 361 555 9090'
    ],
    [
        'full_name' => 'Hannah Flores',
        'email' => 'hannah.flores@example.com',
        'phone_number' => '09791230077',
        'address' => 'Pasig City, Metro Manila',
        'status' => 'Resubmit Files',
        'booking_number' => 'JVB-2026-1007',
        'booking_date' => '2026-01-21',
        'trip_date_start' => '2026-02-25',
        'trip_date_end' => '2026-02-27',
        'package_name' => 'Singapore City Experience',
        'hotel' => 'Marina Bay Hotel',
        'room_type' => 'Deluxe Twin',
        'flight_details' => "SQ915 MNL–SIN 08:15-12:00\nSQ916 SIN–MNL 18:40-22:25",
        'companions' => ['Jake Flores (Friend)'],
        'transfer_tour_hotline' => '+65 6555 2233'
    ],
    [
        'full_name' => 'Noah Dizon',
        'email' => 'noah.dizon@example.com',
        'phone_number' => '09831230088',
        'address' => 'Cagayan de Oro City, Misamis Oriental',
        'status' => 'Confirmed',
        'booking_number' => 'JVB-2026-1008',
        'booking_date' => '2026-01-23',
        'trip_date_start' => '2026-03-05',
        'trip_date_end' => '2026-03-07',
        'package_name' => 'Cebu Island Hopping Adventure',
        'hotel' => 'Lapu-Lapu Beachside Inn',
        'room_type' => 'Standard Twin',
        'flight_details' => "5J562 MNL–CEB 06:10-07:40\n5J563 CEB–MNL 17:55-19:30",
        'companions' => ['Chloe Dizon (Partner)'],
        'transfer_tour_hotline' => '+63 32 555 1122'
    ],
    [
        'full_name' => 'Samantha Ordoñez',
        'email' => 'samantha.ordonez@example.com',
        'phone_number' => '09911230099',
        'address' => 'Baguio City, Benguet',
        'status' => 'Trip Completed',
        'booking_number' => 'JVB-2026-1009',
        'booking_date' => '2026-01-12',
        'trip_date_start' => '2026-01-16',
        'trip_date_end' => '2026-01-19',
        'package_name' => 'Bangkok & Pattaya Getaway',
        'hotel' => 'Pattaya Seaview Resort',
        'room_type' => 'Sea View Double',
        'flight_details' => "TG621 MNL–BKK 09:30-11:55\nTG622 BKK–MNL 18:15-22:35",
        'companions' => ['Dylan Ordoñez (Spouse)'],
        'transfer_tour_hotline' => '+66 2 555 8899'
    ],
    [
        'full_name' => 'Kevin Miranda',
        'email' => 'kevin.miranda@example.com',
        'phone_number' => '09181230110',
        'address' => 'Cebu City, Cebu',
        'status' => 'Confirmed',
        'booking_number' => 'JVB-2026-1010',
        'booking_date' => '2026-01-29',
        'trip_date_start' => '2026-03-21',
        'trip_date_end' => '2026-03-25',
        'package_name' => 'Seoul K-Culture Adventure',
        'hotel' => 'Gangnam Avenue Hotel',
        'room_type' => 'Superior Twin',
        'flight_details' => "KE622 MNL–ICN 13:05-18:10\nKE621 ICN–MNL 20:00-23:15",
        'companions' => ['Aira Miranda (Friend)'],
        'transfer_tour_hotline' => '+82 2 555 7788'
    ]
];

$hasProcessingType = columnExists($conn, 'clients', 'processing_type');
$hasHotel = columnExists($conn, 'clients', 'hotel');
$hasRoomType = columnExists($conn, 'clients', 'room_type');
$hasFlightDetails = columnExists($conn, 'clients', 'flight_details');
$hasCompanions = columnExists($conn, 'clients', 'companions_json');
$hasTransferHotline = columnExists($conn, 'clients', 'transfer_tour_hotline');

$baseColumns = [
    'assigned_admin_id',
    'full_name',
    'email',
    'phone_number',
    'address',
    'access_code',
    'assigned_package_id',
    'booking_number',
    'trip_date_start',
    'trip_date_end',
    'booking_date',
    'status',
    'created_at'
];

if ($hasProcessingType) $baseColumns[] = 'processing_type';
if ($hasHotel) $baseColumns[] = 'hotel';
if ($hasRoomType) $baseColumns[] = 'room_type';
if ($hasFlightDetails) $baseColumns[] = 'flight_details';
if ($hasCompanions) $baseColumns[] = 'companions_json';
if ($hasTransferHotline) $baseColumns[] = 'transfer_tour_hotline';

$columnsSql = '`' . implode('`, `', $baseColumns) . '`';
$placeholders = implode(', ', array_fill(0, count($baseColumns), '?'));

echo "🚀 Starting booking client seeding process...\n\n";

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO clients ({$columnsSql}) VALUES ({$placeholders})");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $successCount = 0;
    $skippedCount = 0;

    foreach ($sampleClients as $index => $client) {
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

        $packageId = $packages[$client['package_name']] ?? $packages[$packageNames[0]];
        $adminId = $adminIds[$index % count($adminIds)];

        $initials = strtoupper(substr($client['full_name'], 0, 1) . substr(strrchr($client['full_name'], ' '), 1, 1));
        $accessCode = $initials . strtoupper(bin2hex(random_bytes(3)));

        $values = [
            $adminId,
            $client['full_name'],
            $client['email'],
            $client['phone_number'],
            $client['address'],
            $accessCode,
            $packageId,
            $client['booking_number'],
            $client['trip_date_start'],
            $client['trip_date_end'],
            $client['booking_date'],
            $client['status'],
            date('Y-m-d H:i:s')
        ];

        if ($hasProcessingType) $values[] = 'booking';
        if ($hasHotel) $values[] = $client['hotel'];
        if ($hasRoomType) $values[] = $client['room_type'];
        if ($hasFlightDetails) $values[] = $client['flight_details'];
        if ($hasCompanions) $values[] = json_encode($client['companions'], JSON_UNESCAPED_UNICODE);
        if ($hasTransferHotline) $values[] = $client['transfer_tour_hotline'];

        $types = str_repeat('s', count($values));
        $types[0] = 'i'; // assigned_admin_id
        $types[6] = 'i'; // assigned_package_id

        bindParams($stmt, $types, $values);

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            echo "✅ Inserted: {$client['full_name']} (ID: {$insertedId}, Status: {$client['status']}, Package: {$client['package_name']})\n";
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

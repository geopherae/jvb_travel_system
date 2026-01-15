<?php
/**
 * Test Data Generator: Adjust timestamps for Confirmed clients
 * Purpose: Create varied onboarding velocity data for testing charts
 * Date: 2026-01-13
 */

require_once __DIR__ . '/../actions/db.php';

echo "🧪 Generating test data for onboarding velocity...\n\n";

// Get all Confirmed clients
$stmt = $conn->query("
    SELECT id, full_name, created_at, confirmed_at 
    FROM clients 
    WHERE status IN ('Confirmed', 'Trip Ongoing', 'Trip Completed')
    ORDER BY id
");

$clients = $stmt->fetch_all(MYSQLI_ASSOC);

if (count($clients) === 0) {
    echo "❌ No confirmed clients found.\n";
    exit;
}

echo "📋 Found " . count($clients) . " confirmed clients:\n";

// Define test scenarios (hours between creation and confirmation)
$scenarios = [
    2,    // Very fast: 2 hours
    12,   // Fast: 12 hours
    36,   // Medium: 1.5 days
    72,   // Slow: 3 days
    120,  // Very slow: 5 days
    168,  // Week: 7 days
];

foreach ($clients as $index => $client) {
    // Distribute clients across different weeks (last 8 weeks)
    $weeksAgo = 1 + ($index % 8);
    $newCreatedAt = date('Y-m-d H:i:s', strtotime("-{$weeksAgo} weeks"));
    
    // Use different onboarding speeds
    $hoursToConfirm = $scenarios[$index % count($scenarios)];
    $newConfirmedAt = date('Y-m-d H:i:s', strtotime($newCreatedAt . " +{$hoursToConfirm} hours"));
    
    // Update the client
    $updateStmt = $conn->prepare("
        UPDATE clients 
        SET created_at = ?, confirmed_at = ? 
        WHERE id = ?
    ");
    $updateStmt->bind_param("ssi", $newCreatedAt, $newConfirmedAt, $client['id']);
    
    if ($updateStmt->execute()) {
        $daysToConfirm = round($hoursToConfirm / 24, 1);
        echo "✅ {$client['full_name']}: Created {$weeksAgo}w ago, confirmed after {$hoursToConfirm}h ({$daysToConfirm}d)\n";
    } else {
        echo "❌ Failed to update {$client['full_name']}: " . $conn->error . "\n";
    }
    
    $updateStmt->close();
}

echo "\n🎉 Test data generation complete!\n";
echo "📊 View results at: http://localhost/jvb_travel_system/admin/audit.php\n";

$conn->close();

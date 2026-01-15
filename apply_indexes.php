<?php
/**
 * Apply database indexes for messages and threads tables
 */

require_once 'actions/db.php';

echo "<h2>Database Index Migration</h2>";

$indexes = [
    [
        'table' => 'messages',
        'name' => 'idx_thread_id',
        'sql' => 'ALTER TABLE `messages` ADD INDEX `idx_thread_id` (`thread_id`)',
        'type' => 'INDEX'
    ],
    [
        'table' => 'messages',
        'name' => 'idx_sender_id_type',
        'sql' => 'ALTER TABLE `messages` ADD INDEX `idx_sender_id_type` (`sender_id`, `sender_type`)',
        'type' => 'INDEX'
    ],
    [
        'table' => 'messages',
        'name' => 'idx_created_at',
        'sql' => 'ALTER TABLE `messages` ADD INDEX `idx_created_at` (`created_at`)',
        'type' => 'INDEX'
    ],
    [
        'table' => 'messages',
        'name' => 'idx_thread_created',
        'sql' => 'ALTER TABLE `messages` ADD INDEX `idx_thread_created` (`thread_id`, `created_at`)',
        'type' => 'INDEX'
    ],
    [
        'table' => 'threads',
        'name' => 'idx_thread_lookup',
        'sql' => 'ALTER TABLE `threads` ADD UNIQUE INDEX `idx_thread_lookup` (`user_id`, `user_type`, `recipient_id`, `recipient_type`)',
        'type' => 'UNIQUE INDEX'
    ],
    [
        'table' => 'threads',
        'name' => 'idx_recipient_lookup',
        'sql' => 'ALTER TABLE `threads` ADD INDEX `idx_recipient_lookup` (`recipient_id`, `recipient_type`, `user_id`, `user_type`)',
        'type' => 'INDEX'
    ]
];

$applied = 0;
$failed = 0;
$skipped = 0;

echo "<table border='1' cellpadding='10' style='margin-top: 20px; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Table</th>";
echo "<th>Index Name</th>";
echo "<th>Type</th>";
echo "<th>Status</th>";
echo "<th>Message</th>";
echo "</tr>";

foreach ($indexes as $idx) {
    $status = 'PENDING';
    $message = '';
    $bgColor = '#ffffff';
    
    try {
        if ($conn->query($idx['sql'])) {
            $status = 'APPLIED ✓';
            $applied++;
            $message = 'Index created successfully';
            $bgColor = '#e8f5e9';
        } else {
            $error = $conn->error;
            
            // Check if it's a duplicate key error
            if (strpos($error, '1061') !== false || strpos($error, 'Duplicate key name') !== false) {
                $status = 'SKIPPED';
                $skipped++;
                $message = 'Index already exists (skipped)';
                $bgColor = '#fff3e0';
            } else {
                $status = 'FAILED ✗';
                $failed++;
                $message = $error;
                $bgColor = '#ffebee';
            }
        }
    } catch (mysqli_sql_exception $e) {
        $error = $e->getMessage();
        
        // Check if it's a duplicate key error
        if (strpos($error, '1061') !== false || strpos($error, 'Duplicate key name') !== false) {
            $status = 'SKIPPED';
            $skipped++;
            $message = 'Index already exists (skipped)';
            $bgColor = '#fff3e0';
        } else {
            $status = 'FAILED ✗';
            $failed++;
            $message = $error;
            $bgColor = '#ffebee';
        }
    }
    
    echo "<tr style='background-color: $bgColor;'>";
    echo "<td>" . $idx['table'] . "</td>";
    echo "<td>" . $idx['name'] . "</td>";
    echo "<td>" . $idx['type'] . "</td>";
    echo "<td><strong>" . $status . "</strong></td>";
    echo "<td>" . htmlspecialchars($message) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3 style='margin-top: 30px;'>Summary:</h3>";
echo "<p><strong style='color: green;'>Applied:</strong> $applied</p>";
echo "<p><strong style='color: orange;'>Skipped (already exist):</strong> $skipped</p>";
echo "<p><strong style='color: red;'>Failed:</strong> $failed</p>";

if ($failed === 0) {
    echo "<p style='color: green; font-size: 18px;'><strong>✓ All indexes are now in place!</strong></p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>✗ Some indexes failed. Check the errors above.</strong></p>";
}

// Verify indexes were created
echo "<h3 style='margin-top: 30px;'>Verification:</h3>";

echo "<h4>Messages Table Indexes:</h4>";
$result = $conn->query("SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_%'");
if ($result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Key_name'] . " (" . $row['Column_name'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No custom indexes found on messages table.</p>";
}

echo "<h4>Threads Table Indexes:</h4>";
$result = $conn->query("SHOW INDEX FROM threads WHERE Key_name LIKE 'idx_%'");
if ($result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Key_name'] . " (" . $row['Column_name'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No custom indexes found on threads table.</p>";
}

echo "<p style='margin-top: 30px; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #007AFF;'>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Test sending a message between client and admin<br>";
echo "2. Verify message appears within 2 seconds<br>";
echo "3. Check <a href='check_messages.php'>Message System Status</a>";
echo "</p>";

$conn->close();
?>

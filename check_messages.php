<?php
require_once 'actions/db.php';

echo "<h2>Message System Status</h2>";

// Check messages count
$result = $conn->query('SELECT COUNT(*) as count FROM messages');
$row = $result->fetch_assoc();
echo "<p><strong>Total messages:</strong> " . $row['count'] . "</p>";

// Check threads count
$result = $conn->query('SELECT COUNT(*) as count FROM threads');
$row = $result->fetch_assoc();
echo "<p><strong>Total threads:</strong> " . $row['count'] . "</p>";

// Latest 5 messages
echo "<h3>Latest 5 Messages:</h3>";
$result = $conn->query('SELECT m.*, t.user_id, t.user_type, t.recipient_id, t.recipient_type 
                        FROM messages m 
                        LEFT JOIN threads t ON m.thread_id = t.id 
                        ORDER BY m.created_at DESC 
                        LIMIT 5');

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Thread</th><th>Sender</th><th>Type</th><th>Recipient</th><th>Type</th><th>Message</th><th>Created</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['thread_id'] . "</td>";
    echo "<td>" . $row['sender_id'] . "</td>";
    echo "<td>" . $row['sender_type'] . "</td>";
    echo "<td>" . $row['recipient_id'] . "</td>";
    echo "<td>" . $row['recipient_type'] . "</td>";
    echo "<td>" . htmlspecialchars($row['message_text']) . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check threads
echo "<h3>All Threads:</h3>";
$result = $conn->query('SELECT * FROM threads ORDER BY created_at DESC');
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>User ID</th><th>User Type</th><th>Recipient ID</th><th>Recipient Type</th><th>Created</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . $row['user_type'] . "</td>";
    echo "<td>" . $row['recipient_id'] . "</td>";
    echo "<td>" . $row['recipient_type'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>

<?php
/**
 * Cleanup corrupted messages and verify message system integrity
 * This script removes messages with NULL thread_id or missing recipient data
 */

require_once 'actions/db.php';

echo "<h2>Message System Cleanup & Verification</h2>";

// Start transaction
$conn->begin_transaction();

try {
    // Step 1: Find corrupted messages (NULL thread_id)
    echo "<h3>Step 1: Identifying corrupted messages...</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM messages WHERE thread_id IS NULL OR recipient_id IS NULL");
    $row = $result->fetch_assoc();
    $corruptedCount = $row['count'];
    
    if ($corruptedCount > 0) {
        echo "<p style='color: red;'><strong>Found $corruptedCount corrupted messages with NULL thread_id or recipient_id</strong></p>";
        
        // Show the corrupted messages before deletion
        echo "<h4>Corrupted Messages (to be deleted):</h4>";
        $result = $conn->query("SELECT id, sender_id, sender_type, recipient_id, message_text, created_at FROM messages WHERE thread_id IS NULL OR recipient_id IS NULL ORDER BY created_at DESC");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Sender</th><th>Type</th><th>Recipient</th><th>Message</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['sender_id'] . "</td>";
            echo "<td>" . $row['sender_type'] . "</td>";
            echo "<td>" . ($row['recipient_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['message_text'], 0, 50)) . "...</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Delete corrupted messages
        $deleteResult = $conn->query("DELETE FROM messages WHERE thread_id IS NULL OR recipient_id IS NULL");
        echo "<p><strong>Deleted $corruptedCount corrupted messages.</strong></p>";
    } else {
        echo "<p style='color: green;'><strong>No corrupted messages found!</strong></p>";
    }

    // Step 2: Verify all messages have valid threads
    echo "<h3>Step 2: Verifying message-thread integrity...</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM messages m WHERE NOT EXISTS (SELECT 1 FROM threads t WHERE t.id = m.thread_id)");
    $row = $result->fetch_assoc();
    $orphanCount = $row['count'];
    
    if ($orphanCount > 0) {
        echo "<p style='color: orange;'><strong>Found $orphanCount messages with invalid thread references (will delete)</strong></p>";
        $conn->query("DELETE FROM messages WHERE thread_id NOT IN (SELECT id FROM threads)");
    } else {
        echo "<p style='color: green;'><strong>All messages have valid thread references!</strong></p>";
    }

    // Step 3: Verify threads have proper data
    echo "<h3>Step 3: Verifying thread data integrity...</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM threads WHERE user_id IS NULL OR recipient_id IS NULL OR user_type IS NULL OR recipient_type IS NULL");
    $row = $result->fetch_assoc();
    $invalidThreads = $row['count'];
    
    if ($invalidThreads > 0) {
        echo "<p style='color: red;'><strong>Found $invalidThreads threads with NULL fields (will delete)</strong></p>";
        $conn->query("DELETE FROM threads WHERE user_id IS NULL OR recipient_id IS NULL OR user_type IS NULL OR recipient_type IS NULL");
    } else {
        echo "<p style='color: green;'><strong>All threads have valid data!</strong></p>";
    }

    // Step 4: Rebuild unique index on threads if needed
    echo "<h3>Step 4: Checking thread uniqueness...</h3>";
    $result = $conn->query("
        SELECT user_id, user_type, recipient_id, recipient_type, COUNT(*) as cnt
        FROM threads
        GROUP BY user_id, user_type, recipient_id, recipient_type
        HAVING cnt > 1
    ");
    $duplicates = $result->num_rows;
    
    if ($duplicates > 0) {
        echo "<p style='color: orange;'><strong>Found $duplicates duplicate thread combinations</strong></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>User</th><th>Type</th><th>Recipient</th><th>Type</th><th>Count</th></tr>";
        $result = $conn->query("
            SELECT user_id, user_type, recipient_id, recipient_type, COUNT(*) as cnt
            FROM threads
            GROUP BY user_id, user_type, recipient_id, recipient_type
            HAVING cnt > 1
        ");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['user_type'] . "</td>";
            echo "<td>" . $row['recipient_id'] . "</td>";
            echo "<td>" . $row['recipient_type'] . "</td>";
            echo "<td>" . $row['cnt'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Keep only the oldest thread for each combination
        echo "<p>Deleting duplicate threads (keeping oldest)...</p>";
        $conn->query("
            DELETE t FROM threads t
            INNER JOIN (
                SELECT user_id, user_type, recipient_id, recipient_type, MIN(id) as keep_id
                FROM threads
                GROUP BY user_id, user_type, recipient_id, recipient_type
                HAVING COUNT(*) > 1
            ) dup ON (
                t.user_id = dup.user_id AND 
                t.user_type = dup.user_type AND 
                t.recipient_id = dup.recipient_id AND 
                t.recipient_type = dup.recipient_type AND 
                t.id > dup.keep_id
            )
        ");
    } else {
        echo "<p style='color: green;'><strong>No duplicate threads found!</strong></p>";
    }

    // Commit transaction
    $conn->commit();
    
    // Final statistics
    echo "<h3>Final System Status:</h3>";
    $result = $conn->query('SELECT COUNT(*) as count FROM messages');
    $msgCount = $result->fetch_assoc()['count'];
    
    $result = $conn->query('SELECT COUNT(*) as count FROM threads');
    $threadCount = $result->fetch_assoc()['count'];
    
    echo "<p><strong>Total Messages:</strong> $msgCount</p>";
    echo "<p><strong>Total Threads:</strong> $threadCount</p>";
    
    echo "<h4>Latest 5 Messages:</h4>";
    $result = $conn->query('
        SELECT m.id, m.thread_id, m.sender_id, m.sender_type, m.recipient_id, m.recipient_type, 
               m.message_text, m.created_at
        FROM messages m
        ORDER BY m.created_at DESC
        LIMIT 5
    ');
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Thread</th><th>Sender</th><th>Type</th><th>Recipient</th><th>Type</th><th>Message</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['thread_id'] . "</td>";
        echo "<td>" . $row['sender_id'] . "</td>";
        echo "<td>" . $row['sender_type'] . "</td>";
        echo "<td>" . $row['recipient_id'] . "</td>";
        echo "<td>" . $row['recipient_type'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['message_text'], 0, 50)) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'><strong>✓ Cleanup completed successfully!</strong></p>";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<p style='color: red;'><strong>Error during cleanup:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>Transaction rolled back. No changes were made.</p>";
}

$conn->close();
?>

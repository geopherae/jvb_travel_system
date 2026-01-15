<?php
require_once __DIR__ . '/../actions/db.php';

$sql = "
    UPDATE clients c
    LEFT JOIN (
        SELECT target_id, MIN(timestamp) as first_confirmed
        FROM audit_logs
        WHERE action_type = 'update_booking'
          AND target_type = 'client'
          AND changes LIKE '%Confirmed%'
        GROUP BY target_id
    ) al ON al.target_id = c.id
    SET c.confirmed_at = COALESCE(al.first_confirmed, c.created_at)
    WHERE c.status IN ('Confirmed', 'Trip Ongoing', 'Trip Completed')
      AND c.confirmed_at IS NULL
";

if ($conn->query($sql) === TRUE) {
    echo "✅ Backfilled confirmed_at for " . $conn->affected_rows . " clients.\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

$conn->close();

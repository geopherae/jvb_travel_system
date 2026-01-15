<?php
require_once __DIR__ . '/../../actions/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$user_id       = $_GET['user_id'] ?? null;
$user_type     = $_GET['user_type'] ?? null;
$recipient_id  = $_GET['recipient_id'] ?? null;
$recipient_type = $_GET['recipient_type'] ?? null;

if (!$user_id || !$recipient_id) {
  http_response_code(400);
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("
  SELECT m.*, 
    CASE WHEN m.sender_type = 'admin' THEN a.first_name ELSE c.first_name END AS sender_name
  FROM messages m
  LEFT JOIN admin_accounts a ON m.sender_type = 'admin' AND m.sender_id = a.id
  LEFT JOIN clients c ON m.sender_type = 'client' AND m.sender_id = c.id
  WHERE (
    (m.sender_id = ? AND m.sender_type = ?) OR
    (m.recipient_id = ? AND m.recipient_type = ?)
  )
  ORDER BY m.created_at ASC
");

$stmt->bind_param("isis", $user_id, $user_type, $user_id, $user_type);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
  $messages[] = [
    'id'            => $row['id'],
    'sender_id'     => $row['sender_id'],
    'sender_type'   => $row['sender_type'],
    'sender_name'   => $row['sender_name'] ?? 'Unknown',
    'message_text'  => $row['message_text'],
    'created_at'    => date('M j, Y H:i', strtotime($row['created_at']))
  ];
}

echo json_encode($messages);
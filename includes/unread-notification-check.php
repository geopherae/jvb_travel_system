<?php

$recipientType = isset($_SESSION['client_id']) ? 'client' : 'admin';
$recipientId   = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : $_SESSION['admin']['id'];

$stmt = $conn->prepare("
  SELECT COUNT(*) FROM notifications
  WHERE status = 'unread'
    AND dismissed = 0
    AND recipient_type = ?
    AND recipient_id = ?
");

$stmt->bind_param("si", $recipientType, $recipientId);
$stmt->execute();
$stmt->bind_result($unreadCount);
$stmt->fetch();
$stmt->close();

echo json_encode(['unread' => $unreadCount]);
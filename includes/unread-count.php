<?php
require_once __DIR__ . '/../actions/db.php';
session_start();
header('Content-Type: application/json');

$isClient = isset($_SESSION['client_id']);
$isAdmin  = isset($_SESSION['admin']['id']);

if (!$isClient && !$isAdmin) {
  echo json_encode(['unread' => 0]);
  exit;
}

$recipientType = $isClient ? 'client' : 'admin';
$recipientId   = $isClient ? $_SESSION['client_id'] : $_SESSION['admin']['id'];

$stmt = $conn->prepare("
  SELECT COUNT(*) FROM notifications
  WHERE status = 'unread' AND recipient_type = ? AND recipient_id = ?
");
$stmt->bind_param("si", $recipientType, $recipientId);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

echo json_encode(['unread' => $count]);
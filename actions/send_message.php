<?php
session_start();
require '../actions/db.php';

header('Content-Type: text/plain'); // So browser shows all output

echo "--- DEBUG OUTPUT ---\n";

// Role detection
$currentUserRole = $_SESSION['role'] ?? 'client';
echo "Role: $currentUserRole\n";

// Inputs
$clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
$content = trim($_POST['content'] ?? '');

echo "client_id: " . var_export($clientId, true) . "\n";
echo "content: " . var_export($content, true) . "\n";

// Basic validation
if (!$clientId || !$content || strlen($content) > 1000 || !in_array($currentUserRole, ['client', 'admin'])) {
  http_response_code(400);
  echo "Invalid input.\n";
  exit;
}

// Optional: verify ownership
// if ($currentUserRole === 'client' && $_SESSION['client_id'] !== $clientId) {
//   http_response_code(403);
//   echo "Unauthorized.\n";
//   exit;
// }

echo "Preparing to insert into database...\n";

// Insert
$stmt = $conn->prepare("INSERT INTO messages (client_id, sender, content) VALUES (?, ?, ?)");
if (!$stmt) {
  echo "Prepare failed: " . $conn->error . "\n";
  exit;
}

$stmt->bind_param("iss", $clientId, $currentUserRole, $content);
$success = $stmt->execute();

if ($success && $stmt->affected_rows > 0) {
  echo "✅ Message successfully saved.\n";
} else {
  http_response_code(500);
  echo "❌ Failed to insert.\nError: " . $stmt->error . "\n";
}

$stmt->close();
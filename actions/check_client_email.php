<?php
session_start();

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';

header('Content-Type: application/json');

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode([
    'success' => false,
    'exists' => false,
    'message' => 'Invalid email.'
  ]);
  exit();
}

$stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
if (!$stmt) {
  echo json_encode([
    'success' => false,
    'exists' => false,
    'message' => 'Database error.'
  ]);
  exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();

echo json_encode([
  'success' => true,
  'exists' => $exists,
  'message' => $exists ? 'Email already exists in the database.' : 'Email is available.'
]);
exit();

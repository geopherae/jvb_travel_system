<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/db.php';

// Set JSON response header
header('Content-Type: application/json');

// 🔐 Check admin session
if (empty($_SESSION['admin']['id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

// 📥 Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$review_id = (int)($data['review_id'] ?? 0);

// 🔍 Validate
if (!$review_id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
  exit();
}

// 🗑️ Delete the review
// First, fetch the photo_path to delete the file
$stmt_fetch = $conn->prepare("SELECT photo_path FROM client_reviews WHERE review_id = ?");
$stmt_fetch->bind_param("i", $review_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$review = $result->fetch_assoc();
$stmt_fetch->close();

// Delete photo file if it exists
if ($review && $review['photo_path']) {
  $photo_file = __DIR__ . "/../" . $review['photo_path'];
  if (file_exists($photo_file)) {
    unlink($photo_file);
  }
}

// Delete the review from database
$stmt = $conn->prepare("DELETE FROM client_reviews WHERE review_id = ?");
$stmt->bind_param("i", $review_id);

if ($stmt->execute()) {
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Review deleted successfully'
  ]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
}

$stmt->close();
?>

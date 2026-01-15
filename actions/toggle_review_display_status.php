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
$currentStatus = (int)($data['currentStatus'] ?? 0);

// 🔍 Validate
if (!$review_id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
  exit();
}

// 🔄 Toggle the status (0 → 1, 1 → 0)
$newStatus = ($currentStatus === 0) ? 1 : 0;

// 🔗 Update the database
$stmt = $conn->prepare("UPDATE client_reviews SET displayinHomePage = ? WHERE review_id = ?");
$stmt->bind_param("ii", $newStatus, $review_id);

if ($stmt->execute()) {
  // 📸 If approving (0 → 1), move photo from pending to gallery
  if ($newStatus === 1) {
    $stmt_photo = $conn->prepare("SELECT photo_path FROM client_reviews WHERE review_id = ?");
    $stmt_photo->bind_param("i", $review_id);
    $stmt_photo->execute();
    $photo_result = $stmt_photo->get_result();
    $photo_data = $photo_result->fetch_assoc();
    
    if ($photo_data && $photo_data['photo_path']) {
      $old_path = __DIR__ . "/../" . $photo_data['photo_path'];
      $filename = basename($photo_data['photo_path']);
      $new_path = __DIR__ . "/../images/login_gallery_images/" . $filename;
      
      // Ensure directory exists
      if (!is_dir(__DIR__ . "/../images/login_gallery_images/")) {
        mkdir(__DIR__ . "/../images/login_gallery_images/", 0755, true);
      }
      
      // Move photo from pending to public gallery
      if (file_exists($old_path) && rename($old_path, $new_path)) {
        // Update photo path in database
        $new_photo_path = "images/login_gallery_images/" . $filename;
        $stmt_update_path = $conn->prepare("UPDATE client_reviews SET photo_path = ? WHERE review_id = ?");
        $stmt_update_path->bind_param("si", $new_photo_path, $review_id);
        $stmt_update_path->execute();
        $stmt_update_path->close();
      }
    }
    $stmt_photo->close();
  }
  
  // 📸 If hiding (1 → 0), move photo back to pending folder
  if ($newStatus === 0) {
    $stmt_review = $conn->prepare("SELECT photo_path, client_id, assigned_package_id FROM client_reviews WHERE review_id = ?");
    $stmt_review->bind_param("i", $review_id);
    $stmt_review->execute();
    $review_result = $stmt_review->get_result();
    $review_data = $review_result->fetch_assoc();
    
    if ($review_data && $review_data['photo_path']) {
      $old_path = __DIR__ . "/../" . $review_data['photo_path'];
      $filename = basename($review_data['photo_path']);
      $client_id = $review_data['client_id'];
      $package_id = $review_data['assigned_package_id'];
      $new_path = __DIR__ . "/../uploads/pending_reviews/client_{$client_id}/{$package_id}/" . $filename;
      
      // Ensure directory exists
      $pendingDir = __DIR__ . "/../uploads/pending_reviews/client_{$client_id}/{$package_id}/";
      if (!is_dir($pendingDir)) {
        mkdir($pendingDir, 0755, true);
      }
      
      // Move photo from public gallery back to pending
      if (file_exists($old_path) && rename($old_path, $new_path)) {
        // Update photo path in database
        $new_photo_path = "uploads/pending_reviews/client_{$client_id}/{$package_id}/" . $filename;
        $stmt_update_path = $conn->prepare("UPDATE client_reviews SET photo_path = ? WHERE review_id = ?");
        $stmt_update_path->bind_param("si", $new_photo_path, $review_id);
        $stmt_update_path->execute();
        $stmt_update_path->close();
      }
    }
    $stmt_review->close();
  }
  
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'newStatus' => $newStatus,
    'message' => $newStatus === 1 ? 'Review is now public' : 'Review is now hidden'
  ]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to update review status']);
}

$stmt->close();
?>

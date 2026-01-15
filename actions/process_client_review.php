<?php
// process_client_review.php - Process client review submission
session_start();

// Include DB connection
require_once __DIR__ . '/../actions/db.php';

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['modal_status'] = 'error';
    header("Location: ../client/client_dashboard.php");
    exit;
}

// Collect POST data safely
$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;
$return_url = $_POST['return_url'] ?? 'client/client_dashboard.php';
$rating    = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
$review    = isset($_POST['review']) ? trim($_POST['review']) : "";

// Resolve assigned_package_id from clients table
$assigned_package_id = null;
if ($client_id) {
    $client_stmt = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
    $client_stmt->bind_param("i", $client_id);
    $client_stmt->execute();
    $client_result = $client_stmt->get_result();
    $client_data = $client_result->fetch_assoc();
    $assigned_package_id = $client_data['assigned_package_id'] ?? null;
    $client_stmt->close();
}

if (!$assigned_package_id) {
    $_SESSION['modal_status'] = 'error';
    header("Location: ../$return_url");
    exit;
}

// Validate inputs
if (!$client_id || !$assigned_package_id || $rating < 1 || $rating > 5 || empty($review)) {
    $_SESSION['modal_status'] = 'error';
    header("Location: ../$return_url");
    exit;
}

// Handle photo upload if provided
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // Build directory path: /uploads/pending_reviews/client_{client_id}/{assigned_package_id}/
    $uploadDir = __DIR__ . "/../uploads/pending_reviews/client_{$client_id}/{$assigned_package_id}/";

    // Ensure directory exists
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $_SESSION['modal_status'] = 'error';
        header("Location: ../$return_url");
        exit;
    }

    // Generate unique filename
    $file_name   = uniqid() . '_' . basename($_FILES['photo']['name']);
    $target_path = $uploadDir . $file_name;

    // Move uploaded file
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
        // Save relative path to DB
        $photo_path = "uploads/pending_reviews/client_{$client_id}/{$assigned_package_id}/" . $file_name;
    } else {
        $_SESSION['modal_status'] = 'error';
        header("Location: ../$return_url");
        exit;
    }
}

// Insert review into database
$stmt = $conn->prepare("INSERT INTO client_reviews 
    (client_id, assigned_package_id, rating, review, photo_path, displayInHomePage, created_at) 
    VALUES (?, ?, ?, ?, ?, 0, NOW())");
$stmt->bind_param("iiiss", $client_id, $assigned_package_id, $rating, $review, $photo_path);

if ($stmt->execute()) {
    $review_id = $conn->insert_id;
    
    // Update review_id to match id
    $update_review_id = $conn->prepare("UPDATE client_reviews SET review_id = ? WHERE id = ?");
    $update_review_id->bind_param("ii", $review_id, $review_id);
    $update_review_id->execute();
    $update_review_id->close();
    
    // Update client's left_review status
    $update_stmt = $conn->prepare("UPDATE clients SET left_review = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $client_id);
    $update_stmt->execute();
    $update_stmt->close();

    $_SESSION['modal_status'] = 'review_success';
} else {
    $_SESSION['modal_status'] = 'review_failed';
}

header("Location: ../$return_url");
exit();
?>
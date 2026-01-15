<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php'; // Your existing mysqli $conn

// ✅ CSRF Validation
if (!isset($_SESSION['csrf_token_modal'], $_POST['csrf_token_modal']) ||
    $_SESSION['csrf_token_modal'] !== $_POST['csrf_token_modal']) {
  $_SESSION['modal_status'] = 'csrf_error';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Get and validate admin ID
$adminId = (int)($_POST['edit_admin_id'] ?? 0);
if ($adminId <= 0) {
  $_SESSION['modal_status'] = 'invalid_id';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Prevent self-deletion or role downgrade (safety check)
$currentAdminId = $_SESSION['admin']['id'] ?? 0;
if ($adminId === $currentAdminId) {
  $_SESSION['modal_status'] = 'cannot_edit_self';
  header('Location: ../admin/admin_settings.php');
  exit('Cannot edit your own account from here');
}

// ✅ Sanitize & Validate Inputs
$firstName       = trim($_POST['edit_first_name'] ?? '');
$lastName        = trim($_POST['edit_last_name'] ?? '');
$emailRaw        = trim($_POST['edit_email'] ?? '');
$email           = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$username        = strtolower(trim($_POST['edit_username'] ?? ''));
$phoneRaw        = trim($_POST['edit_phone_number'] ?? '');
$phoneNumber     = preg_match('/^09\d{9}$/', $phoneRaw) ? $phoneRaw : null;
$messengerLink   = trim($_POST['edit_messenger_link'] ?? '');
$adminProfileRaw = trim($_POST['edit_admin_profile_json'] ?? '');
$newPassword     = $_POST['edit_new_password'] ?? '';
$confirmPassword = $_POST['edit_confirm_password'] ?? '';

// ✅ Validate Required Fields
if (!$firstName || !$lastName || !$email || !$username) {
  $_SESSION['modal_status'] = 'missing_fields';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Fetch CURRENT user data for duplicate checks and old photo cleanup
$stmt = $conn->prepare("
  SELECT email, username, admin_photo, role 
  FROM admin_accounts 
  WHERE id = ?
");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();
$stmt->close();

if (!$currentUser) {
  $_SESSION['modal_status'] = 'user_not_found';
  header('Location: ../admin/admin_settings.php');
  exit;
}

// ✅ Check for Duplicate Email/Username (exclude current user)
if ($email !== $currentUser['email']) {
  $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin_accounts WHERE email = ? AND id != ?");
  $checkStmt->bind_param("si", $email, $adminId);
  $checkStmt->execute();
  $checkStmt->bind_result($count);
  $checkStmt->fetch();
  $checkStmt->close();
  
  if ($count > 0) {
    $_SESSION['modal_status'] = 'duplicate_email';
    header('Location: ../admin/admin_settings.php');
    exit;
  }
}

if ($username !== $currentUser['username']) {
  $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admin_accounts WHERE username = ? AND id != ?");
  $checkStmt->bind_param("si", $username, $adminId);
  $checkStmt->execute();
  $checkStmt->bind_result($count);
  $checkStmt->fetch();
  $checkStmt->close();
  
  if ($count > 0) {
    $_SESSION['modal_status'] = 'duplicate_username';
    header('Location: ../admin/admin_settings.php');
    exit;
  }
}

// ✅ Handle Optional Password Update
$passwordHash = $currentUser['password_hash'] ?? null; // Keep existing if no new password
if (!empty($newPassword)) {
  if ($newPassword !== $confirmPassword) {
    $_SESSION['modal_status'] = 'password_mismatch';
    header('Location: ../admin/admin_settings.php');
    exit;
  }
  if (strlen($newPassword) < 8) {
    $_SESSION['modal_status'] = 'weak_password';
    header('Location: ../admin/admin_settings.php');
    exit;
  }
  $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
}

// ✅ Encode Admin Profile JSON
$adminProfileArray = json_decode($adminProfileRaw, true);
$adminProfile = json_encode([
  'bio' => $adminProfileArray['bio'] ?? ''
]);

// ✅ Handle Photo Upload/Replacement
$photoFilename = $currentUser['admin_photo']; // Keep existing by default
$oldPhotoPath = !empty($photoFilename) ? __DIR__ . '/../uploads/admin_photo/' . $photoFilename : null;

if (!empty($_FILES['edit_admin_photo']['name'])) {
  $uploadDir = __DIR__ . '/../uploads/admin_photo/';
  $fileTmp   = $_FILES['edit_admin_photo']['tmp_name'];
  $fileName  = basename($_FILES['edit_admin_photo']['name']);
  $fileExt   = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

  if (in_array($fileExt, $allowed) && $_FILES['edit_admin_photo']['error'] === UPLOAD_ERR_OK) {
    $newPhotoName = uniqid('admin_', true) . '.' . $fileExt;
    
    // Delete old photo if exists (but keep default)
    if ($oldPhotoPath && file_exists($oldPhotoPath) && strpos($oldPhotoPath, 'default') === false) {
      unlink($oldPhotoPath);
    }
    
    if (move_uploaded_file($fileTmp, $uploadDir . $newPhotoName)) {
      $photoFilename = $newPhotoName;
    }
  }
}

// ✅ Role (keep existing for now - can be extended later)
$role = $currentUser['role']; // Preserve original role
$sessionTimeout = 30; // Default

// ✅ UPDATE the admin account
$updateStmt = $conn->prepare("
  UPDATE admin_accounts SET 
    first_name = ?, last_name = ?, email = ?, username = ?, password_hash = ?,
    phone_number = ?, messenger_link = ?, admin_profile = ?, admin_photo = ?, 
    role = ?, session_timeout = ?
  WHERE id = ?
");

$updateStmt->bind_param(
  "sssssssssssi",
  $firstName,
  $lastName,
  $email,
  $username,
  $passwordHash,
  $phoneNumber,
  $messengerLink,
  $adminProfile,
  $photoFilename,
  $role,
  $sessionTimeout,
  $adminId
);

if ($updateStmt->execute()) {
  $updateStmt->close();
  
  // ✅ Update survey tracking if password changed (optional)
  if (!empty($newPassword)) {
    $surveyTypes = ['first_login', 'admin_weekly_survey'];
    $updatedAt = date('Y-m-d H:i:s');
    
    foreach ($surveyTypes as $type) {
      $surveyStmt = $conn->prepare("
        INSERT INTO user_survey_status (user_id, user_role, survey_type, is_completed, updated_at) 
        VALUES (?, 'admin', ?, 0, ?) 
        ON DUPLICATE KEY UPDATE updated_at = ?
      ");
      $surveyStmt->bind_param("isss", $adminId, $type, $updatedAt, $updatedAt);
      $surveyStmt->execute();
      $surveyStmt->close();
    }
  }
  
    $_SESSION['modal_status'] = 'edit_admin_success';
    header('Location: ../admin/admin_settings.php');
    exit;
} else {
  $updateStmt->close();
  $_SESSION['modal_status'] = 'update_failed';
  header('Location: ../admin/admin_settings.php');
  exit;
}
?>
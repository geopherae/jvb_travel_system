<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/applicant_status_helper.php';
require_once __DIR__ . '/../actions/notify.php';

use function Auth\guard;
use function Auth\getActorContext;
use function LogHelper\logClientOnboardingAudit;

guard('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Invalid request method.');
}

function toMysqlDate($input) {
  if (!$input) return null;
  $timestamp = strtotime($input);
  return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function handlePhotoUpload($file, $oldPhoto = null) {
  if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return $oldPhoto; // Keep existing photo
  }
  
  if ($file['error'] !== UPLOAD_ERR_OK) {
    return null;
  }
  
  $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!in_array($file['type'], $allowed)) {
    throw new Exception('Only JPG, JPEG, and PNG files are allowed.');
  }
  
  if ($file['size'] > 2 * 1024 * 1024) {
    throw new Exception('File must be under 2MB.');
  }
  
  $uploadDir = __DIR__ . '/../uploads/client_profiles/';
  if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }
  
  $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
  $newFilename = uniqid('client_', true) . '.' . $extension;
  $uploadPath = $uploadDir . $newFilename;
  
  if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Delete old photo if exists
    if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
      unlink($uploadDir . $oldPhoto);
    }
    return $newFilename;
  }
  
  return null;
}

$clientId = (int) ($_POST['client_id'] ?? 0);
$applicantType = trim($_POST['applicant_type'] ?? '');
$applicantId = (int) ($_POST['applicant_id'] ?? 0);

$fullName = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$financialSource = trim($_POST['financial_source'] ?? '');
$sponsorStatus = trim($_POST['sponsor_status'] ?? ''); // NEW: Capture sponsor status
$passportNumber = trim($_POST['passport_number'] ?? '');
$passportExpiry = toMysqlDate($_POST['passport_expiry'] ?? '');

// Process applicant status
$rawApplicantStatus = $_POST['applicant_status'] ?? '';
if (is_array($rawApplicantStatus)) {
  // Remove empty values, trim, and reindex
  $applicantStatusArr = array_values(array_filter(array_map('trim', $rawApplicantStatus), function($v) { return $v !== ''; }));
} elseif (is_string($rawApplicantStatus) && $rawApplicantStatus !== '') {
  $applicantStatusArr = [trim($rawApplicantStatus)];
} else {
  $applicantStatusArr = [];
}

// Convert to JSON format with option and label fields
$applicantStatus = convertApplicantStatusToJson($applicantStatusArr);

$visaType = trim($_POST['visa_type'] ?? '');
$relationship = trim($_POST['relationship'] ?? '');

// Handle photo upload
$photoFilename = null;
$oldPhoto = trim($_POST['existing_photo'] ?? '');
try {
  $photoFilename = handlePhotoUpload($_FILES['client_profile_photo'] ?? null, $oldPhoto);
} catch (Exception $e) {
  $errors[] = $e->getMessage();
}

$errors = [];

if (!$clientId) $errors[] = 'Missing client ID.';
if ($applicantType !== 'lead' && $applicantType !== 'companion') $errors[] = 'Invalid applicant type.';
if ($applicantType === 'companion' && !$applicantId) $errors[] = 'Missing companion ID.';
if ($fullName === '') $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'Phone must start with 09 and have 11 digits.';
if ($address === '') $errors[] = 'Address is required.';
if ($financialSource === '') $errors[] = 'Financial source is required.';
// NEW: Validate sponsor status if financial source is sponsored
if ($financialSource === 'sponsored' && $sponsorStatus === '') {
  $errors[] = 'Sponsor status is required when financial source is Sponsored.';
}
if ($passportNumber === '') $errors[] = 'Passport number is required.';
if (!$passportExpiry) $errors[] = 'Passport expiry is required.';
if (empty($applicantStatusArr)) $errors[] = 'Applicant status is required.';
if ($visaType === '') $errors[] = 'Visa type is required.';
if ($applicantType === 'companion' && $relationship === '') $errors[] = 'Relationship is required.';

if (!empty($errors)) {
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
  }

  $_SESSION['message'] = implode(' ', $errors);
  $_SESSION['message_type'] = 'error';
  echo "<script>";
  echo "window.location.href = '../admin/view_client_visa.php?client_id=" . urlencode((string) $clientId) . "';";
  echo "</script>";
  exit;
}

try {
  $conn->begin_transaction();
  $success = false;
  
  if ($applicantType === 'lead') {
    $stmt = $conn->prepare(
      "UPDATE clients SET
        full_name = ?,
        email = ?,
        phone_number = ?,
        address = ?,
        passport_number = ?,
        passport_expiry = ?,
        visa_lead_applicant_status = ?,
        financial_source = ?,
        client_profile_photo = COALESCE(?, client_profile_photo),
        updated_at = NOW()
      WHERE id = ?"
    );
    $stmt->bind_param(
      'sssssssssi',
      $fullName,
      $email,
      $phone,
      $address,
      $passportNumber,
      $passportExpiry,
      $applicantStatus,
      $financialSource,
      $photoFilename,
      $clientId
    );
    if ($stmt->execute()) {
      $success = true;
      $_SESSION['debug_console'][] = "✅ Lead applicant updated.";
    } else {
      $_SESSION['debug_console'][] = "❌ Lead update failed: " . $stmt->error;
    }
    $stmt->close();

    // NEW: Update sponsor_status in client_visa_applications for lead
    $appStmt = $conn->prepare(
      "UPDATE client_visa_applications 
       SET sponsor_status = ? 
       WHERE client_id = ?"
    );
    $appStmt->bind_param('si', $sponsorStatus, $clientId);
    if ($appStmt->execute()) {
      $_SESSION['debug_console'][] = "✅ Sponsor status updated for lead applicant.";
    } else {
      $_SESSION['debug_console'][] = "⚠️ Sponsor status update skipped or failed: " . $appStmt->error;
    }
    $appStmt->close();

    // Upsert visa requirements for lead
    $reqStmt = $conn->prepare(
      "SELECT id, requirements_json FROM client_visa_requirements
      WHERE client_id = ? AND companion_id IS NULL
      LIMIT 1"
    );
    $reqStmt->bind_param('i', $clientId);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result()->fetch_assoc();
    $reqStmt->close();

    if ($reqResult) {
      $updateReq = $conn->prepare(
        "UPDATE client_visa_requirements
        SET visa_type = ?
        WHERE id = ?"
      );
      $updateReq->bind_param('si', $visaType, $reqResult['id']);
      if ($updateReq->execute()) {
        $_SESSION['debug_console'][] = "✅ Visa requirements updated.";
      } else {
        $_SESSION['debug_console'][] = "❌ Visa requirements update failed: " . $updateReq->error;
      }
      $updateReq->close();
    } else {
      $requirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
      $insertReq = $conn->prepare(
        "INSERT INTO client_visa_requirements (client_id, companion_id, visa_type, requirements_json)
        VALUES (?, NULL, ?, ?)"
      );
      $insertReq->bind_param('iss', $clientId, $visaType, $requirementsJson);
      if ($insertReq->execute()) {
        $_SESSION['debug_console'][] = "✅ Visa requirements inserted.";
      } else {
        $_SESSION['debug_console'][] = "❌ Visa requirements insert failed: " . $insertReq->error;
      }
      $insertReq->close();
    }
  } else {
    // companion applicant
    $stmt = $conn->prepare(
      "UPDATE client_visa_companions SET
        full_name = ?,
        email = ?,
        phone_number = ?,
        address = ?,
        passport_number = ?,
        passport_expiry = ?,
        applicant_status = ?,
        financial_source = ?,
        relationship = ?,
        companions_photo = COALESCE(?, companions_photo)
      WHERE id = ?
        AND (SELECT client_id FROM client_visa_applications WHERE id = visa_application_id LIMIT 1) = ?"
    );
    $stmt->bind_param(
      'ssssssssssii',
      $fullName,
      $email,
      $phone,
      $address,
      $passportNumber,
      $passportExpiry,
      $applicantStatus,
      $financialSource,
      $relationship,
      $photoFilename,
      $applicantId,
      $clientId
    );
    if ($stmt->execute()) {
      $success = true;
      $_SESSION['debug_console'][] = "✅ Companion applicant updated.";
    } else {
      $_SESSION['debug_console'][] = "❌ Companion update failed: " . $stmt->error;
    }
    $stmt->close();

    // NEW: Update sponsor_status in client_visa_applications for companion
    // We need to find the visa_application_id first
    $getAppIdStmt = $conn->prepare(
      "SELECT visa_application_id FROM client_visa_companions WHERE id = ?"
    );
    $getAppIdStmt->bind_param('i', $applicantId);
    $getAppIdStmt->execute();
    $appIdResult = $getAppIdStmt->get_result()->fetch_assoc();
    $getAppIdStmt->close();
    
    if ($appIdResult && isset($appIdResult['visa_application_id'])) {
      $visaAppId = (int) $appIdResult['visa_application_id'];
      $appStmt = $conn->prepare(
        "UPDATE client_visa_applications 
         SET sponsor_status = ? 
         WHERE id = ?"
      );
      $appStmt->bind_param('si', $sponsorStatus, $visaAppId);
      if ($appStmt->execute()) {
        $_SESSION['debug_console'][] = "✅ Sponsor status updated for companion.";
      } else {
        $_SESSION['debug_console'][] = "⚠️ Sponsor status update failed for companion: " . $appStmt->error;
      }
      $appStmt->close();
    }

    // Upsert visa requirements for companion
    $reqStmt = $conn->prepare(
      "SELECT id, requirements_json FROM client_visa_requirements
      WHERE client_id = ? AND companion_id = ?
      LIMIT 1"
    );
    $reqStmt->bind_param('ii', $clientId, $applicantId);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result()->fetch_assoc();
    $reqStmt->close();

    if ($reqResult) {
      $updateReq = $conn->prepare(
        "UPDATE client_visa_requirements
        SET visa_type = ?
        WHERE id = ?"
      );
      $updateReq->bind_param('si', $visaType, $reqResult['id']);
      if ($updateReq->execute()) {
        $_SESSION['debug_console'][] = "✅ Visa requirements updated.";
      } else {
        $_SESSION['debug_console'][] = "❌ Visa requirements update failed: " . $updateReq->error;
      }
      $updateReq->close();
    } else {
      $requirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
      $insertReq = $conn->prepare(
        "INSERT INTO client_visa_requirements (client_id, companion_id, visa_type, requirements_json)
        VALUES (?, ?, ?, ?)"
      );
      $insertReq->bind_param('iiss', $clientId, $applicantId, $visaType, $requirementsJson);
      if ($insertReq->execute()) {
        $_SESSION['debug_console'][] = "✅ Visa requirements inserted.";
      } else {
        $_SESSION['debug_console'][] = "❌ Visa requirements insert failed: " . $insertReq->error;
      }
      $insertReq->close();
    }
  }

  // Notify client
  $adminId = (int) ($_SESSION['admin']['id'] ?? 0);
  $adminName = '';
  if ($adminId) {
    $adminStmt = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE id = ?");
    $adminStmt->bind_param('i', $adminId);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result()->fetch_assoc();
    $adminStmt->close();
    if ($adminResult) {
      $adminName = trim($adminResult['first_name'] . ' ' . $adminResult['last_name']);
    }
  }

  notify([
    'recipient_type' => 'client',
    'recipient_id' => $clientId,
    'event' => 'visa_details_updated',
    'context' => [
      'applicant_name' => $fullName,
      'applicant_type' => $applicantType,
      'admin_id' => $adminId,
      'admin_name' => $adminName
    ]
  ]);

  // Commit transaction if successful
  if ($success) {
    $conn->commit();
  } else {
    $conn->rollback();
  }

  // AJAX or non-AJAX response
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode([
      'success' => $success,
      'message' => $success ? 'Visa applicant updated successfully.' : 'Failed to update visa applicant.',
      'debug' => $_SESSION['debug_console'] ?? [],
      'close_modal' => $success,
      'show_toast' => $success ? [
        'type' => 'success',
        'text' => 'Visa applicant updated successfully.'
      ] : null
    ]);
    exit;
  }

  // Fallback for non-AJAX
  if ($success) {
    $_SESSION['modal_status'] = 'edit_visa_client_success';
    header("Location: ../admin/admin_dashboard.php");
    exit();
  } else {
    $_SESSION['modal_status'] = 'edit_visa_client_failed';
    echo "<script>";
    foreach ($_SESSION['debug_console'] ?? [] as $log) {
      echo "console.log(" . json_encode($log) . ");";
    }
    echo "window.location.href = '../admin/view_client_visa.php?client_id=" . urlencode((string) $clientId) . "';";
    echo "</script>";
    exit;
  }

} catch (Exception $e) {
  $conn->rollback();
  error_log('process_edit_visa_client error: ' . $e->getMessage());
  $_SESSION['modal_status'] = 'edit_visa_client_failed';
  $_SESSION['debug_console'][] = '❌ Error: ' . $e->getMessage();
  redirectWithDebug($clientId);
}

// 🔧 Helpers
function getApplicantPhotoField($applicantType, $row) {
  if ($applicantType === 'lead') {
    return $row['client_profile_photo'] ?? '';
  } else {
    // companions_photo is the correct field
    return $row['companions_photo'] ?? ($row['companion_photo'] ?? '');
  }
}

function redirectWithDebug($clientId) {
  echo "<script>";
  foreach ($_SESSION['debug_console'] ?? [] as $log) {
    echo "console.log(" . json_encode($log) . ");";
  }
  echo "window.location.href = '../admin/view_client_visa.php?client_id=" . urlencode((string) $clientId) . "';";
  echo "</script>";
  exit;
}
?>
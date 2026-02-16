<?php
session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function LogHelper\generateReassignmentSummary;

// ✅ 1. CSRF Token Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
  http_response_code(403);
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ 2. Sanitize & Validate Input
$clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;
$visaPackageId = isset($_POST['visa_package_id']) ? (int) $_POST['visa_package_id'] : null;

if (!$clientId || !$visaPackageId) {
  http_response_code(400);
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ 3. Verify Visa Package Exists
$verify = $conn->prepare("SELECT id, visa_package_name FROM visa_packages WHERE id = ?");
$verify->bind_param("i", $visaPackageId);
$verify->execute();
$verifyResult = $verify->get_result();
if ($verifyResult->num_rows === 0) {
  http_response_code(404);
  $_SESSION['modal_status'] = 'invalid_id';
  header("Location: ../admin/view_client.php");
  exit();
}
$visaPackageData = $verifyResult->fetch_assoc();
$visaPackageName = $visaPackageData['visa_package_name'];

// ✅ 4. Verify Client Exists
$clientCheck = $conn->prepare("SELECT id, full_name FROM clients WHERE id = ?");
$clientCheck->bind_param("i", $clientId);
$clientCheck->execute();
$clientResult = $clientCheck->get_result();
if ($clientResult->num_rows === 0) {
  http_response_code(404);
  $_SESSION['modal_status'] = 'client_not_found';
  header("Location: ../admin/admin_dashboard.php");
  exit();
}
$clientData = $clientResult->fetch_assoc();
$clientName = $clientData['full_name'];

// ✅ 5. Fetch Previous Visa Package for Logging
$prevQuery = $conn->prepare("
  SELECT visa_package_id 
  FROM client_visa_applications 
  WHERE client_id = ? 
  LIMIT 1
");
$prevQuery->bind_param("i", $clientId);
$prevQuery->execute();
$prevResult = $prevQuery->get_result();
$previousVisaPackageId = null;
$previousVisaPackageName = null;

if ($prevResult->num_rows > 0) {
  $prevData = $prevResult->fetch_assoc();
  $previousVisaPackageId = $prevData['visa_package_id'];
  
  // Fetch previous package name
  if ($previousVisaPackageId) {
    $prevNameQuery = $conn->prepare("SELECT visa_package_name FROM visa_packages WHERE id = ?");
    $prevNameQuery->bind_param("i", $previousVisaPackageId);
    $prevNameQuery->execute();
    $prevNameResult = $prevNameQuery->get_result();
    if ($prevNameResult->num_rows > 0) {
      $previousVisaPackageName = $prevNameResult->fetch_assoc()['visa_package_name'];
    }
    $prevNameQuery->close();
  }
}

// ✅ 6. Check if already assigned to this visa package (no update needed)
if ($previousVisaPackageId === $visaPackageId) {
  $_SESSION['modal_status'] = 'no_change';
  $_SESSION['message'] = 'Client is already assigned to this visa package.';
  header("Location: ../admin/view_client.php?client_id=" . $clientId);
  exit();
}

// ✅ 7. Fetch requirements_json from the new visa package

// Fetch requirements_json and visa_types_json from the new visa package
$fetchVisaSql = "SELECT requirements_json, visa_types_json FROM visa_packages WHERE id = ?";
$fetchVisaStmt = $conn->prepare($fetchVisaSql);
if (!$fetchVisaStmt) {
  http_response_code(500);
  $_SESSION['modal_status'] = 'db_error';
  error_log("Fetch visa package prepare failed for visa_package_id=$visaPackageId: " . $conn->error);
  header("Location: ../admin/view_client.php?client_id=" . $clientId);
  exit();
}

$fetchVisaStmt->bind_param("i", $visaPackageId);
if (!$fetchVisaStmt->execute()) {
  http_response_code(500);
  $_SESSION['modal_status'] = 'db_error';
  error_log("Fetch visa package execute failed for visa_package_id=$visaPackageId: " . $fetchVisaStmt->error);
  header("Location: ../admin/view_client.php?client_id=" . $clientId);
  exit();
}

$fetchVisaStmt->bind_result($requirementsJson, $visaTypesJson);
$fetchVisaStmt->fetch();
$fetchVisaStmt->close();

// Validate and use default if requirements_json is empty
$requirementsJson = $requirementsJson ?: json_encode([], JSON_UNESCAPED_UNICODE);
if (json_decode($requirementsJson, true) === null && $requirementsJson !== '[]') {
  error_log("[reassign_visa_package] Warning: Invalid requirements_json from visa_package $visaPackageId, using empty array");
  $requirementsJson = json_encode([], JSON_UNESCAPED_UNICODE);
}

// Decode visa_types_json and select the first type
$visaType = null;
if ($visaTypesJson) {
  $visaTypesArr = json_decode($visaTypesJson, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($visaTypesArr) && count($visaTypesArr) > 0 && isset($visaTypesArr[0]['type'])) {
    $visaType = $visaTypesArr[0]['type'];
  }
}
if (!$visaType) {
  $visaType = null;
}

// ✅ 8. Update or Create Visa Application
if ($previousVisaPackageId !== null) {
  // Update existing visa application, clear visa_type then set new
  $update = $conn->prepare("
    UPDATE client_visa_applications 
    SET visa_package_id = ?, 
        visa_type = ?,
        updated_at = NOW()
    WHERE client_id = ?
  ");
  $update->bind_param("isi", $visaPackageId, $visaType, $clientId);
  $updateSuccess = $update->execute();
  $update->close();
} else {
  // Create new visa application
  $insert = $conn->prepare("
    INSERT INTO client_visa_applications (
      client_id, 
      visa_package_id, 
      visa_type,
      created_at, 
      updated_at
    ) VALUES (?, ?, ?, NOW(), NOW())
  ");
  $insert->bind_param("iis", $clientId, $visaPackageId, $visaType);
  $updateSuccess = $insert->execute();
  $insert->close();
}

if (!$updateSuccess) {
  http_response_code(500);
  $_SESSION['modal_status'] = 'db_error';
  error_log("Visa package reassignment failed for client_id=$clientId: " . $conn->error);
  header("Location: ../admin/view_client.php?client_id=" . $clientId);
  exit();
}


// ✅ 9. Update client_visa_requirements with new requirements_json
// First, delete existing requirements for this client (for lead applicant only)
$deleteReqSql = "DELETE FROM client_visa_requirements WHERE client_id = ? AND companion_id IS NULL";
$deleteReqStmt = $conn->prepare($deleteReqSql);
if ($deleteReqStmt) {
  $deleteReqStmt->bind_param("i", $clientId);
  $deleteReqStmt->execute();
  $deleteReqStmt->close();
}

// Insert new requirements for this client (lead applicant)
$insertReqSql = "INSERT INTO client_visa_requirements 
  (client_id, companion_id, visa_type, requirements_json, created_at, updated_at) 
  VALUES (?, NULL, NULL, ?, NOW(), NOW())";
$insertReqStmt = $conn->prepare($insertReqSql);
if (!$insertReqStmt) {
  error_log("Insert requirements prepare failed for client_id=$clientId: " . $conn->error);
} else {
  $insertReqStmt->bind_param("is", $clientId, $requirementsJson);
  if (!$insertReqStmt->execute()) {
    error_log("Insert requirements execute failed for client_id=$clientId: " . $insertReqStmt->error);
  } else {
    error_log("[reassign_visa_package] Client $clientId: Visa requirements re-copied from package $visaPackageId");
  }
  $insertReqStmt->close();
}

// ✅ 9b. Update all companions' requirements_json to match the lead's
$updateCompanionsSql = "UPDATE client_visa_requirements SET requirements_json = ?, updated_at = NOW() WHERE client_id = ? AND companion_id IS NOT NULL";
$updateCompanionsStmt = $conn->prepare($updateCompanionsSql);
if (!$updateCompanionsStmt) {
  error_log("Update companions requirements prepare failed for client_id=$clientId: " . $conn->error);
} else {
  $updateCompanionsStmt->bind_param("si", $requirementsJson, $clientId);
  if (!$updateCompanionsStmt->execute()) {
    error_log("Update companions requirements execute failed for client_id=$clientId: " . $updateCompanionsStmt->error);
  } else {
    error_log("[reassign_visa_package] Client $clientId: Companions' visa requirements updated to match lead");
  }
  $updateCompanionsStmt->close();
}

// ✅ 10. Log Reassignment
$actor_id = (int) ($_SESSION['admin_id'] ?? 0);
$actor_role = 'admin';
$action_type = 'reassign_visa_package';
$target_type = 'client_visa_application';
$severity = 'normal';
$module = 'visa';
$timestamp = date('Y-m-d H:i:s');
$session_id = session_id();
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$kpi_tag = 'visa_package_reassigned';
$business_impact = 'moderate';

$audit_payload = [
  'client_id' => $clientId,
  'client_name' => $clientName,
  'actor_id' => $actor_id,
  'old_visa_package_id' => $previousVisaPackageId,
  'old_visa_package_name' => $previousVisaPackageName,
  'new_visa_package_id' => $visaPackageId,
  'new_visa_package_name' => $visaPackageName,
  'action' => $previousVisaPackageId ? 'update' : 'create',
  'source' => 'reassign_visa_package.php'
];

$audit_changes = json_encode($audit_payload, JSON_UNESCAPED_UNICODE);

$audit_stmt = $conn->prepare("
  INSERT INTO audit_logs (
    action_type, actor_id, actor_role,
    target_id, target_type, changes,
    severity, module, timestamp,
    session_id, ip_address, user_agent,
    kpi_tag, business_impact
  ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
  )
");

$audit_stmt->bind_param(
  "sissssssssssss",
  $action_type,
  $actor_id,
  $actor_role,
  $clientId,
  $target_type,
  $audit_changes,
  $severity,
  $module,
  $timestamp,
  $session_id,
  $ip_address,
  $user_agent,
  $kpi_tag,
  $business_impact
);

$audit_stmt->execute();
$audit_stmt->close();

// ✅ 11. Send Notification
$eventType = $previousVisaPackageId ? 'visa_package_reassigned' : 'visa_package_assigned';
notify([
  'recipient_type' => 'client',
  'recipient_id'   => $clientId,
  'event'          => $eventType,
  'context'        => [
    'client_id'         => $clientId,
    'visa_package_name' => $visaPackageName
  ]
]);

// ✅ 12. Set Success Message & Redirect
$_SESSION['modal_status'] = 'success';
$_SESSION['message'] = $previousVisaPackageId 
  ? "Visa package reassigned successfully!"
  : "Visa package assigned successfully!";

// Determine return URL (default to view_client_visa.php)
$returnUrl = $_POST['return_url'] ?? "../admin/view_client_visa.php?client_id=" . $clientId;
if (strpos($returnUrl, '?') === false) {
  $returnUrl .= "?client_id=" . $clientId;
}

header("Location: " . $returnUrl);
exit();

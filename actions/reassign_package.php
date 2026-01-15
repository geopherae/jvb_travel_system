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

// ✅ 3. Sanitize & Validate Input
$clientId  = isset($_POST['client_id'])  ? (int) $_POST['client_id'] : null;
$packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : null;

if (!$clientId || !$packageId) {
  http_response_code(400);
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ 4. Verify Package Exists
$verify = $conn->prepare("SELECT id FROM tour_packages WHERE id = ?");
$verify->bind_param("i", $packageId);
$verify->execute();
$result = $verify->get_result();
if ($result->num_rows === 0) {
  http_response_code(404);
  $_SESSION['modal_status'] = 'invalid_id';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ 5. Fetch Previous Package for Logging
$prevQuery = $conn->prepare("SELECT assigned_package_id FROM clients WHERE id = ?");
$prevQuery->bind_param("i", $clientId);
$prevQuery->execute();
$prevResult = $prevQuery->get_result();
$previousPackageId = $prevResult->fetch_assoc()['assigned_package_id'] ?? null;

// ✅ 6. Update Client's Assigned Package
$update = $conn->prepare("
  UPDATE clients 
  SET assigned_package_id = ?, 
      status = 'Awaiting Docs' 
  WHERE id = ?
");
$update->bind_param("ii", $packageId, $clientId);

if ($update->execute()) {
  // ✅ 7. Delete Old Itinerary
  $delete = $conn->prepare("DELETE FROM client_itinerary WHERE client_id = ?");
  $delete->bind_param("i", $clientId);
  $delete->execute();

// ✅ 7b. Copy and Validate New Package's Itinerary JSON
$fetchTourItinerary = $conn->prepare("
  SELECT itinerary_json 
  FROM tour_package_itinerary 
  WHERE package_id = ? 
  ORDER BY updated_at DESC 
  LIMIT 1
");
$fetchTourItinerary->bind_param("i", $packageId);
$fetchTourItinerary->execute();
$tourResult = $fetchTourItinerary->get_result();
$tourItinerary = $tourResult->fetch_assoc()['itinerary_json'] ?? null;

// ✅ Validate JSON Structure Before Insert
if (!empty($tourItinerary) && json_validate($tourItinerary)) {
  $insertClientItinerary = $conn->prepare("
    INSERT INTO client_itinerary (client_id, itinerary_json, updated_at)
    VALUES (?, ?, NOW())
  ");
  $insertClientItinerary->bind_param("is", $clientId, $tourItinerary);
  $insertClientItinerary->execute();
}

  // ✅ 8. Log Reassignment
$actor_id        = (int) ($_SESSION['admin_id'] ?? 0);
$actor_role      = 'admin';
$action_type     = 'reassign_package';
$target_type     = 'client';
$severity        = 'normal';
$module          = 'client';
$timestamp       = date('Y-m-d H:i:s');
$session_id      = session_id();
$ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$kpi_tag         = 'tour_reassigned';
$business_impact = 'moderate';

$audit_payload = [
  'client_id'       => $clientId,
  'actor_id'        => $actor_id,
  'old_package_id'  => $previousPackageId,
  'new_package_id'  => $packageId,
  'summary'         => generateReassignmentSummary($clientId, $previousPackageId, $packageId),
  'source'          => 'process_reassign_package.php'
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
$_SESSION['debug_console'][] = "🧾 Audit log saved.";


$packageQuery = $conn->prepare("SELECT package_name FROM tour_packages WHERE id = ?");
$packageQuery->bind_param("i", $packageId);
$packageQuery->execute();
$packageResult = $packageQuery->get_result();
$packageName = $packageResult->fetch_assoc()['package_name'] ?? 'Unnamed Package';

// Determine if this is initial assignment or reassignment
$eventType = $previousPackageId ? 'package_reassigned' : 'package_assigned';

notify([
  'recipient_type' => 'client',
  'recipient_id'   => $clientId,
  'event'          => $eventType,
  'context'        => [
    'package_id'   => $packageId,
    'package_name' => $packageName,
    'admin_id'     => $actor_id
  ]
]);

  // ✅ 9. Success Status
  $_SESSION['modal_status'] = 'reassigned';
} else {
  // ❌ Failed to update package
  http_response_code(500);
  $_SESSION['modal_status'] = 'reassign_failed';
}

// ✅ Final Redirect
header("Location: ../admin/view_client.php?client_id=$clientId");
exit();
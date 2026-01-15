<?php
session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function LogHelper\generateArchiveSummary;

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
$clientId  = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;

if (!$clientId) {
  http_response_code(400);
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ 4. Archive Client
$update = $conn->prepare("
  UPDATE clients 
  SET is_archived = 1 
  WHERE id = ?
");
$update->bind_param("i", $clientId);

if ($update->execute()) {
  $update->close();

  // ✅ 5. Log Archive Action
  $actor_id        = (int) ($_SESSION['admin_id'] ?? 0);
  $actor_role      = 'admin';
  $action_type     = 'archive_client';
  $target_type     = 'client';
  $severity        = 'normal';
  $module          = 'client';
  $timestamp       = date('Y-m-d H:i:s');
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'client_archived';
  $business_impact = 'moderate';

  $audit_payload = [
    'client_id' => $clientId,
    'actor_id'  => $actor_id,
    'summary'   => "Client ID $clientId archived by Admin ID $actor_id",
    'source'    => 'process_archive_client.php'
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

  // ✅ 6. Notify Client
  notify([
    'recipient_type' => 'client',
    'recipient_id'   => $clientId,
    'event'          => 'client_archived',
    'context'        => [
      'admin_id' => $actor_id
    ]
  ]);

  // ✅ 7. Success Status
  $_SESSION['modal_status'] = 'client_archived_success';

} else {
  // ❌ Failed to update
  http_response_code(500);
  $_SESSION['modal_status'] = 'client_archived_failed';
}

// ✅ Final Redirect
header("Location: ../admin/view_client.php?client_id=$clientId");
exit();
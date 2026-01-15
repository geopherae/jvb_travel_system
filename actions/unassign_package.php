<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../actions/db.php'; // ✅ Centralized DB connection
use function LogHelper\generateUnassignSummary;
use function LogHelper\logAuditAction;

// ✅ Authentication
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/admin_dashboard.php");
  exit();
}

// ✅ CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  $_SESSION['modal_status'] = 'error';
  header("Location: ../admin/admin_dashboard.php");
  exit();
}

// ✅ Input
$clientId = intval($_POST['client_id'] ?? 0);
if ($clientId <= 0) {
  $_SESSION['modal_status'] = 'invalid_id';
  header("Location: ../admin/view_client.php");
  exit();
}

// ✅ Delete itinerary row
$deleteItinerary = $conn->prepare("DELETE FROM client_itinerary WHERE client_id = ?");
$deleteItinerary->bind_param("i", $clientId);
$deleteItinerary->execute();
$deleteItinerary->close();

// ✅ Clear assignment and booking fields
$update = $conn->prepare("
  UPDATE clients 
  SET assigned_package_id = NULL, 
      checklist_template_id = NULL,
      trip_date_start = NULL,
      trip_date_end = NULL,
      booking_date = NULL,
      booking_number = NULL,
      status = 'No Assigned Package'
  WHERE id = ?
");
$update->bind_param("i", $clientId);
$success = $update->execute();
$update->close();

// ✅ Audit log
if ($success) {
  logAuditAction($conn, [
    'actor_id'        => $_SESSION['admin_id'] ?? 0,
    'actor_role'      => 'admin',
    'action_type'     => 'unassign_package',
    'target_id'       => $clientId,
    'target_type'     => 'client',
    'changes'         => [
      'client_id' => $clientId,
      'summary'   => generateUnassignSummary($clientId),
      'source'    => 'unassign_package.php'
    ],
    'severity'        => 'normal',
    'module'          => 'client',
    'kpi_tag'         => 'tour_unassigned',
    'business_impact' => 'moderate'
  ]);
}

// ✅ Redirect with status
$_SESSION['modal_status'] = $success ? 'unassigned' : 'error';
header("Location: ../admin/view_client.php?client_id=" . urlencode($clientId));
exit();
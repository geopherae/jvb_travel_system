<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';
require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\generateBookingSummary;

// 🚦 Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Invalid request method.');
}

// 🔍 Validate booking ID
$clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : null;
$bookingNumber = trim($_POST['booking_number'] ?? '');
if (!$clientId || $bookingNumber === '') {
  http_response_code(400);
  exit('Missing or invalid booking data.');
}

// 🧑‍💼 Retrieve admin info
$adminInfo = $_SESSION['admin'] ?? null;
if (!$adminInfo || !isset($adminInfo['id'])) {
  http_response_code(401);
  exit('Session expired or unauthorized.');
}
$adminId = $adminInfo['id'];

// 📝 Get admin name
$stmt = $conn->prepare("SELECT first_name FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$adminResult = $stmt->get_result();
$admin = $adminResult->fetch_assoc();
$stmt->close();

if (!$admin) {
  http_response_code(404);
  exit('Admin not found.');
}
$adminName = $admin['first_name'];

// 🧼 Extract and validate booking fields
$status        = trim($_POST['status'] ?? '');
$tripStart     = $_POST['trip_date_start'] ?? null;
$tripEnd       = $_POST['trip_date_end'] ?? null;
$bookingDate   = $_POST['booking_date'] ?? null;
$assignedRaw   = $_POST['assigned_admin_id'] ?? null;
$assignedAdmin = is_numeric($assignedRaw) ? (int)$assignedRaw : null;
$updatedAt     = $_POST['updated_at'] ?? date('Y-m-d H:i:s');
$rawJson       = $_POST['itinerary_json'] ?? '[]';
$decodedJson   = json_decode($rawJson, true);

if (!is_array($decodedJson)) {
  http_response_code(422);
  exit('Invalid itinerary JSON.');
}

if ($tripStart && $tripEnd && strtotime($tripEnd) < strtotime($tripStart)) {
  http_response_code(422);
  exit('Return date cannot be earlier than departure date.');
}

if ($bookingDate && $tripStart && strtotime($bookingDate) > strtotime($tripStart)) {
  http_response_code(422);
  exit('Booking date cannot be later than departure date.');
}

// 🆙 Check if status is changing to Confirmed
$stmt = $conn->prepare("SELECT status FROM clients WHERE id = ?");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$result = $stmt->get_result();
$currentClient = $result->fetch_assoc();
$stmt->close();

$isNewlyConfirmed = ($currentClient && $currentClient['status'] !== 'Confirmed' && $status === 'Confirmed');

// 🆙 Update booking (with confirmed_at if status becomes Confirmed)
if ($isNewlyConfirmed) {
  $stmt = $conn->prepare("
    UPDATE clients SET
      booking_number = ?, status = ?,
      trip_date_start = ?, trip_date_end = ?,
      booking_date = ?, assigned_admin_id = ?,
      confirmed_at = NOW()
    WHERE id = ?
  ");
  $stmt->bind_param("ssssssi", $bookingNumber, $status, $tripStart, $tripEnd, $bookingDate, $assignedAdmin, $clientId);
} else {
  $stmt = $conn->prepare("
    UPDATE clients SET
      booking_number = ?, status = ?,
      trip_date_start = ?, trip_date_end = ?,
      booking_date = ?, assigned_admin_id = ?
    WHERE id = ?
  ");
  $stmt->bind_param("ssssssi", $bookingNumber, $status, $tripStart, $tripEnd, $bookingDate, $assignedAdmin, $clientId);
}
$stmt->execute();
$stmt->close();

// 🗺 Upsert itinerary
$stmt = $conn->prepare("SELECT id FROM client_itinerary WHERE client_id = ?");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
  $stmt->close();
  $stmt = $conn->prepare("UPDATE client_itinerary SET itinerary_json = ? WHERE client_id = ?");
  $stmt->bind_param("si", $rawJson, $clientId);
} else {
  $stmt->close();
  $stmt = $conn->prepare("INSERT INTO client_itinerary (client_id, itinerary_json) VALUES (?, ?)");
  $stmt->bind_param("is", $clientId, $rawJson);
}
$stmt->execute();
$stmt->close();

// 🔔 Notify client
notify([
  'recipient_type' => 'client',
  'recipient_id'   => $clientId,
  'event'          => 'booking_updated',
  'context'        => [
    'booking_number' => $bookingNumber,
    //'status'         => $status,
    'admin_id'       => $adminId,
    'admin_name'     => $adminName
  ]
]);

// 🧾 Log audit
$auditPayload = [
  'client_id' => $clientId,
  'actor_id'  => $adminId,
  'fields_changed' => ['booking_number', 'status', 'trip_date_start', 'trip_date_end', 'booking_date', 'assigned_admin_id'],
  'summary'   => generateBookingSummary([
    'booking_number'     => $bookingNumber,
    'status'             => $status,
    'trip_date_start'    => $tripStart,
    'trip_date_end'      => $tripEnd,
    'booking_date'       => $bookingDate,
    'assigned_admin_id'  => $assignedAdmin
  ]),
  'source' => 'update_booking.php'
];

$auditJson = json_encode($auditPayload, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("
  INSERT INTO audit_logs (
    action_type, actor_id, actor_role,
    target_id, target_type, changes,
    severity, module, timestamp,
    session_id, ip_address, user_agent,
    kpi_tag, business_impact
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$actionType     = 'update_booking';
$actorRole      = 'admin';
$targetType     = 'client';
$severity       = 'normal';
$module         = 'booking';
$sessionId      = session_id();
$ipAddress      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$kpiTag         = 'booking_update';
$businessImpact = 'moderate';

$stmt->bind_param(
  "sissssssssssss",
  $actionType,
  $adminId,
  $actorRole,
  $clientId,
  $targetType,
  $auditJson,
  $severity,
  $module,
  $updatedAt,
  $sessionId,
  $ipAddress,
  $userAgent,
  $kpiTag,
  $businessImpact
);
$stmt->execute();
$stmt->close();

// ✅ Toast trigger
$_SESSION['modal_status'] = 'booking_update_success';
http_response_code(200);
echo json_encode([
  'status' => 'success',
  'message' => "Booking updated successfully by $adminName."
]);
?>
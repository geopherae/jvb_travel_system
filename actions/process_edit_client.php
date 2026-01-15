<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
use function Auth\guard;
guard('admin');

require_once __DIR__ . '/../includes/log_helper.php';
use function LogHelper\generateClientSummary;


include '../includes/header.php';

// 🧭 Validate client ID
$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) {
  $_SESSION['modal_status'] = 'invalid_id';
  $_SESSION['debug_console'][] = "⚠️ Missing client_id in POST.";
  return redirectWithDebug();
}

// 🧠 Fetch current client data
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $clientId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
  $_SESSION['modal_status'] = 'error';
  $_SESSION['debug_console'][] = "❌ No client found with ID: $clientId";
  return redirectWithDebug();
}

// 📝 Merge fields
$fields = [
  'full_name'            => trim($_POST['full_name'] ?? $current['full_name']),
  'email'                => trim($_POST['email'] ?? $current['email']),
  'phone_number'         => trim($_POST['phone_number'] ?? $current['phone_number']),
  'address'              => trim($_POST['address'] ?? $current['address']),
  'booking_number'       => array_key_exists('booking_number', $_POST) ? trim($_POST['booking_number']) : $current['booking_number'],
  'assigned_package_id'  => $_POST['assigned_package_id'] ?? $current['assigned_package_id'],
  'trip_date_start'      => $_POST['trip_date_start'] ?? $current['trip_date_start'],
  'trip_date_end'        => $_POST['trip_date_end'] ?? $current['trip_date_end'],
  'booking_date'       => $_POST['booking_date'] ?? $current['booking_date'],
  'client_profile_photo' => $current['client_profile_photo'],
  'created_at'           => date('Y-m-d H:i:s')
];

// 📸 Handle photo upload
handlePhotoUpload($fields);

// 🛠️ Update client
$update = $conn->prepare("
  UPDATE clients SET
    full_name = ?, email = ?, phone_number = ?, address = ?,
    booking_number = ?, assigned_package_id = ?,
    trip_date_start = ?, trip_date_end = ?, booking_date = ?,
    client_profile_photo = ?, created_at = ?
  WHERE id = ?
");

if (!$update) {
  $_SESSION['modal_status'] = 'db_error';
  $_SESSION['debug_console'][] = "❌ Prepare failed: " . $conn->error;
  return redirectWithDebug();
}

$update->bind_param(
  "sssssssssssi",
  $fields['full_name'], $fields['email'], $fields['phone_number'], $fields['address'],
  $fields['booking_number'], $fields['assigned_package_id'],
  $fields['trip_date_start'], $fields['trip_date_end'], $fields['booking_date'],
  $fields['client_profile_photo'], $fields['updated_at'], $clientId
);

if ($update->execute()) {
  $_SESSION['modal_status'] = 'edit_client_success';
  $_SESSION['debug_console'][] = "✅ Client $clientId updated successfully.";
  if ($update->execute()) {
  $_SESSION['modal_status'] = 'edit_client_success';
  $_SESSION['debug_console'][] = "✅ Client $clientId updated successfully.";

  // 🧾 Audit Log
  $actor_id        = (int) ($_SESSION['admin_id'] ?? 0);
  $actor_role      = 'admin';
  $action_type     = 'edit_client';
  $target_type     = 'client';
  $severity        = 'normal';
  $module          = 'client';
  $timestamp       = date('Y-m-d H:i:s');
  $session_id      = session_id();
  $ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $user_agent      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
  $kpi_tag         = 'profile_edit';
  $business_impact = 'moderate';

  $audit_payload = [
    'client_id'       => $clientId,
    'actor_id'        => $actor_id,
    'fields_changed'  => ['full_name', 'email', 'phone_number', 'address'],
    'summary'         => generateClientSummary($fields),
    'source'          => 'process_edit_client.php'
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
}

} else {
  $_SESSION['modal_status'] = 'edit_client_failed';
  $_SESSION['debug_console'][] = "❌ Update failed: " . $update->error;
}
$update->close();

// 📦 Update itinerary
if (isset($_POST['itinerary_json'])) {
  $raw_json = $_POST['itinerary_json'];
  $itinerary = $conn->prepare("
    INSERT INTO client_itinerary (client_id, itinerary_json)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE itinerary_json = VALUES(itinerary_json)
  ");
  $itinerary->bind_param("is", $clientId, $raw_json);
  if ($itinerary->execute()) {
    $_SESSION['debug_console'][] = "📦 Itinerary saved.";
  } else {
    $_SESSION['debug_console'][] = "❌ Itinerary update failed: " . $itinerary->error;
  }
  $itinerary->close();
}

return redirectWithDebug();

// 🔧 Helpers
function handlePhotoUpload(&$fields) {
  if (
    isset($_FILES['client_profile_photo']) &&
    $_FILES['client_profile_photo']['error'] === UPLOAD_ERR_OK &&
    is_uploaded_file($_FILES['client_profile_photo']['tmp_name'])
  ) {
    $uploadDir = __DIR__ . '/../uploads/client_profiles/';
    $ext = strtolower(pathinfo($_FILES['client_profile_photo']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    $maxSize = 3 * 1024 * 1024;

    if (in_array($ext, $allowedExts) && $_FILES['client_profile_photo']['size'] <= $maxSize) {
      $filename = 'client_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
      $targetFile = $uploadDir . $filename;

      if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
      if (move_uploaded_file($_FILES['client_profile_photo']['tmp_name'], $targetFile)) {
        if ($fields['client_profile_photo'] && file_exists($uploadDir . $fields['client_profile_photo'])) {
          unlink($uploadDir . $fields['client_profile_photo']);
        }
        $fields['client_profile_photo'] = $filename;
        $_SESSION['debug_console'][] = "📸 New profile photo saved: $filename";
      } else {
        $_SESSION['debug_console'][] = "❌ Failed to move uploaded file.";
      }
    } else {
      $_SESSION['debug_console'][] = "⚠️ Invalid or oversized photo. Ext: $ext, Size: " . $_FILES['client_profile_photo']['size'];
    }
  }
}

function redirectWithDebug() {
  echo "<script>";
  foreach ($_SESSION['debug_console'] ?? [] as $log) {
    echo "console.log(" . json_encode($log) . ");";
  }
  echo "window.location.href = '../admin/view_client.php?client_id=" . urlencode($_POST['client_id'] ?? '') . "';";
  echo "</script>";
  exit;
}
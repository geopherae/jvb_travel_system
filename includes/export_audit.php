<?php
// export_audit.php

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_export.csv"');

// 🧠 Read filters
$filters = [];

$actionType = $_POST['action_type'] ?? '';
$module     = $_POST['module'] ?? '';
$kpiTag     = $_POST['kpi_tag'] ?? '';
$actorRole  = $_POST['actor_role'] ?? '';
$anonymize  = isset($_POST['anonymize']) && $_POST['anonymize'] === '1';
// Remove unused date variables

if ($actionType) $filters[] = "action_type = '" . mysqli_real_escape_string($conn, $actionType) . "'";
if ($module)     $filters[] = "module = '" . mysqli_real_escape_string($conn, $module) . "'";
if ($kpiTag)     $filters[] = "kpi_tag = '" . mysqli_real_escape_string($conn, $kpiTag) . "'";
if ($actorRole)  $filters[] = "actor_role = '" . mysqli_real_escape_string($conn, $actorRole) . "'";

$whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

// 📥 Fetch logs
$query = "SELECT * FROM audit_logs $whereClause ORDER BY timestamp DESC";
$result = mysqli_query($conn, $query);

// 🧾 CSV headers
$headers = [
  'Timestamp', 'Action', 'Target', 'Payload', 'Severity',
  'Module', 'System', 'Actor', 'KPI', 'Impact', 'Role'
];

$output = fopen('php://output', 'w');
fputcsv($output, $headers);

// 📦 Write rows
while ($row = mysqli_fetch_assoc($result)) {
  $payload = $row['changes'] ?? $row['payload'] ?? null;
  $payloadText = 'No payload';

  if ($payload) {
    $decoded = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      // Flatten payload into key=value pairs
      $flattened = [];
      foreach ($decoded as $key => $value) {
        $flattened[] = "$key=" . (is_scalar($value) ? $value : json_encode($value));
      }
      $payloadText = implode('; ', $flattened);
    } else {
      $payloadText = 'Invalid JSON';
    }
  }

  $actorId = $anonymize ? 'Hidden' : $row['actor_id'];

  fputcsv($output, [
    $row['timestamp'],
    $row['action_type'],
    $row['target_type'] . ' #' . $row['target_id'],
    $payloadText,
    $row['severity'],
    $row['module'],
    isset($row['is_system']) ? ($row['is_system'] ? 'Yes' : 'No') : 'N/A',
    $actorId,
    $row['kpi_tag'],
    $row['business_impact'],
    $row['actor_role'] ?? 'N/A'
  ]);
}

fclose($output);
exit;
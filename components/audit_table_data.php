<?php
// audit_table_data.php

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/survey_response_viewer.php';

header('Content-Type: application/json');

// 🧮 Pagination setup
$limit  = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 5;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// 🔍 Dynamic filters
$filters = [];

if (!empty($_GET['action_type'])) {
  $filters[] = "action_type = '" . mysqli_real_escape_string($conn, $_GET['action_type']) . "'";
}
if (!empty($_GET['module'])) {
  $filters[] = "module = '" . mysqli_real_escape_string($conn, $_GET['module']) . "'";
}
if (!empty($_GET['kpi_tag'])) {
  $filters[] = "kpi_tag = '" . mysqli_real_escape_string($conn, $_GET['kpi_tag']) . "'";
}
if (!empty($_GET['actor_role'])) {
  $filters[] = "actor_role = '" . mysqli_real_escape_string($conn, $_GET['actor_role']) . "'";
}
if (!empty($_GET['start_date'])) {
  $filters[] = "DATE(timestamp) >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
}
if (!empty($_GET['end_date'])) {
  $filters[] = "DATE(timestamp) <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
}

$whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

// 📥 Fetch paginated audit logs
$query = "SELECT * FROM audit_logs $whereClause ORDER BY timestamp DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

// 🧮 Total rows for pagination
$countQuery  = "SELECT COUNT(*) AS total FROM audit_logs $whereClause";
$countResult = mysqli_query($conn, $countQuery);
$totalRows   = mysqli_fetch_assoc($countResult)['total'] ?? 0;
$totalPages  = ceil($totalRows / $limit);

// 🧾 Render table rows
ob_start();

if ($result && mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    $payload = $row['changes'] ?? $row['payload'] ?? null;
    $payloadPreview = 'No payload';
    $payloadTooltip = '';

    if ($payload) {
      $decoded = json_decode($payload, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        // Get first key-value pair for preview
        $firstKey = array_key_first($decoded);
        $firstVal = $decoded[$firstKey] ?? '';
        $payloadPreview = is_array($firstVal) ? $firstKey . ': [...]' : substr($firstKey . ': ' . $firstVal, 0, 40);
        if (strlen($firstKey . ': ' . $firstVal) > 40) {
          $payloadPreview .= '...';
        }
        $payloadTooltip = htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      } else {
        $payloadPreview = 'Invalid JSON';
      }
    }

    // Severity badge
    $severityClass = match($row['severity'] ?? 'info') {
      'info'    => 'bg-blue-100 text-blue-700',
      'warning' => 'bg-yellow-100 text-yellow-700',
      'error'   => 'bg-red-100 text-red-700',
      'critical' => 'bg-rose-100 text-rose-700',
      default   => 'bg-gray-100 text-gray-700'
    };

    // Truncate long fields
    $timestamp = date('M d, H:i', strtotime($row['timestamp']));
    $actionType = strlen($row['action_type']) > 15 ? substr($row['action_type'], 0, 15) . '...' : $row['action_type'];
    $module = strlen($row['module']) > 12 ? substr($row['module'], 0, 12) . '...' : $row['module'];

    ?>
    <tr class="border-b hover:bg-gray-50 transition">
      <td class="px-5 py-3 text-gray-900 font-medium text-xs"><?= htmlspecialchars($timestamp) ?></td>
      <td class="px-5 py-3 text-gray-700 text-xs"><span class="font-mono bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($actionType) ?></span></td>
      <td class="px-5 py-3 text-gray-700 text-xs"><?= htmlspecialchars($row['target_type']) ?> <span class="font-mono text-gray-500">#<?= htmlspecialchars($row['target_id']) ?></span></td>
      <td class="px-5 py-3 text-gray-600 text-xs">
        <span class="cursor-help border-b border-dotted border-gray-400" title="<?= $payloadTooltip ?>">
          <?= htmlspecialchars($payloadPreview) ?>
        </span>
      </td>
      <td class="px-5 py-3 text-gray-700 text-xs"><?= htmlspecialchars($module) ?></td>
      <td class="px-5 py-3 text-gray-700 text-xs font-mono text-gray-600"><?= htmlspecialchars($row['actor_id']) ?></td>
      <td class="px-5 py-3 text-gray-700 text-xs"><span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs"><?= htmlspecialchars($row['kpi_tag']) ?></span></td>
      <td class="px-5 py-3 text-gray-700 text-xs"><?= htmlspecialchars($row['business_impact'] ?? '—') ?></td>
      <td class="px-5 py-3 text-gray-700 text-xs"><span class="bg-sky-100 text-sky-700 px-2 py-1 rounded text-xs"><?= htmlspecialchars($row['actor_role'] ?? 'N/A') ?></span></td>
    </tr>
    <?php
  }
} else {
  echo '<tr><td colspan="9" class="px-5 py-4 text-center text-gray-500">No audit logs found.</td></tr>';
}

$rowsHtml = ob_get_clean();

// 📤 Return JSON response
echo json_encode([
  'rows'       => $rowsHtml,
  'page'       => $page,
  'totalPages' => $totalPages
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
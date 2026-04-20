<?php
require_once __DIR__ . '/../actions/db.php';

// 📈 Onboarding Completion Time: Average time from client creation to "Confirmed" status (≤30 days)
// This measures how long the complete onboarding process takes for active clients, grouped by week
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 12;

// Calculate weekly average time from account creation to confirmed status
$velocityStmt = $conn->prepare("
  SELECT 
    DATE_FORMAT(c.created_at, '%x-%v') AS week,
    ROUND(AVG(TIMESTAMPDIFF(SECOND, c.created_at, c.confirmed_at)) / 3600, 2) AS avg_hours
  FROM clients c
  WHERE c.created_at IS NOT NULL
    AND c.confirmed_at IS NOT NULL
    AND c.confirmed_at >= c.created_at
    AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
    AND TIMESTAMPDIFF(DAY, c.created_at, c.confirmed_at) <= 30
  GROUP BY week
  ORDER BY week DESC
  LIMIT ?
");
$velocityStmt->bind_param("ii", $limit, $limit);
$velocityStmt->execute();
$velocityResult = $velocityStmt->get_result();

$labels = [];
$data   = [];

while ($row = $velocityResult->fetch_assoc()) {
  // Keep the ISO week + year context for clarity (e.g., 2026-13)
  $labels[] = 'Week ' . $row['week'];
  $data[]   = (float) ($row['avg_hours'] ?? 0);
}

// 🧾 Output as JSON (chronological order)
echo json_encode([
  'labels' => array_reverse($labels),
  'data'   => array_reverse($data)
]);
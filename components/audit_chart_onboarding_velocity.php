<?php
require_once __DIR__ . '/../actions/db.php';

// 📈 Onboarding Velocity: Average time from client creation to "Confirmed" status
// This measures how long the complete onboarding process takes, grouped by week
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 12;

// Calculate weekly average time from account creation to confirmed status
$velocityStmt = $conn->prepare("
  SELECT 
    DATE_FORMAT(c.created_at, '%Y-%u') AS week,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, c.confirmed_at)), 1) AS avg_hours
  FROM clients c
  WHERE c.created_at IS NOT NULL
    AND c.confirmed_at IS NOT NULL
    AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
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
  $labels[] = 'Week ' . substr($row['week'], -2); // e.g. "Week 36"
  $data[]   = (float) ($row['avg_hours'] ?? 0);
}

// 🧾 Output as JSON (chronological order)
echo json_encode([
  'labels' => array_reverse($labels),
  'data'   => array_reverse($data)
]);
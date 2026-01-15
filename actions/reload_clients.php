<?php
session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';  // Add this for getStatusBadgeClass()

$isAdmin = $_SESSION['admin'] ?? false;

// 👥 Fetch enriched client data
$clientQuery = "
  SELECT 
    c.id, 
    c.full_name, 
    c.booking_number,
    c.client_profile_photo, 
    CASE
      WHEN c.trip_date_start IS NOT NULL AND c.trip_date_end IS NOT NULL THEN
        CONCAT(DATEDIFF(c.trip_date_end, c.trip_date_start) + 1, ' Days / ',
               DATEDIFF(c.trip_date_end, c.trip_date_start), ' Nights')
      ELSE '—'
    END AS duration,
    CASE
      WHEN c.trip_date_start IS NOT NULL AND c.trip_date_end IS NOT NULL THEN
        CONCAT(
          DATE_FORMAT(c.trip_date_start, '%b %e'), ' to ',
          DATE_FORMAT(c.trip_date_end, '%b %e'), ', ',
          DATE_FORMAT(c.trip_date_end, '%Y')
        )
      ELSE '—'
    END AS trip_date_range,
    IFNULL(c.status, 'Pending') AS status
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  ORDER BY c.full_name
";

$result = $conn->query($clientQuery);
$clients = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $row['status'] = ucfirst(strtolower(trim($row['status'])));
    $clients[] = $row;
  }
} else {
  error_log("⚠️ No clients found or query failed: " . $conn->error);
}

// 🧼 Flag to disable filters and pagination
$isCleanReload = true;

// ✅ Include the table view
include '../components/clients-table.php';
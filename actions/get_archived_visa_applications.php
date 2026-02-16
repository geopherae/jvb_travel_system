<?php
header('Content-Type: application/json');
session_start();

// Check if admin is logged in
if (empty($_SESSION['admin']['id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit;
}

require_once __DIR__ . '/db.php';

try {
  $sql = "
    SELECT 
      va.id, 
      c.full_name AS client_name,
      vp.visa_cover_image,
      vp.visa_package_name,
      vp.visa_package_description,
      vp.country,
      va.status,
      DATE_FORMAT(va.created_at, '%b %e, %Y') AS archived_date
    FROM client_visa_applications va
    LEFT JOIN clients c ON va.client_id = c.id
    LEFT JOIN visa_packages vp ON va.visa_package_id = vp.id
    WHERE va.is_archived = 1
    ORDER BY va.created_at DESC
  ";
  
  $result = $conn->query($sql);
  
  if (!$result) {
    throw new Exception('Database query failed: ' . $conn->error);
  }
  
  $applications = [];
  while ($row = $result->fetch_assoc()) {
    // Handle visa package image
    $image = '../images/default_trip_cover.jpg'; // Default fallback
    if (!empty($row['visa_cover_image']) && $row['visa_cover_image'] !== 'NULL') {
      $image = '../images/visa_packages_banners/' . $row['visa_cover_image'];
    }
    
    // Handle description with default
    $description = $row['visa_package_description'];
    if (empty($description)) {
      $description = 'Visa application package for ' . ($row['country'] ?? 'international') . ' travel';
    }
    
    $applications[] = [
      'id' => (int)$row['id'],
      'client_name' => $row['client_name'] ?? 'Unknown Client',
      'image' => $image,
      'package_name' => $row['visa_package_name'] ?? 'Unknown Package',
      'description' => $description,
      'country' => $row['country'] ?? '—',
      'status' => $row['status'] ?? 'Archived',
      'archived_date' => $row['archived_date'] ?? '—'
    ];
  }
  
  echo json_encode([
    'success' => true,
    'applications' => $applications,
    'count' => count($applications)
  ]);
  
} catch (Exception $e) {
  error_log('Error fetching archived visa applications: ' . $e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => 'Failed to fetch archived applications',
    'error' => $e->getMessage()
  ]);
}
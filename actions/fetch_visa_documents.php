<?php
session_start();

// ✅ Security & Dependencies
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\guard;
guard('admin');

// ✅ Validate client_id
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
if (!$client_id) {
  header('Content-Type: application/json');
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Client ID is required']);
  exit();
}

// ✅ Fetch visa applications for this client
$visa_apps_stmt = $conn->prepare("
  SELECT 
    id,
    client_id,
    visa_package_id,
    application_mode,
    applicant_status,
    is_deleted
  FROM client_visa_applications
  WHERE client_id = ? AND is_deleted = 0
");
$visa_apps_stmt->bind_param("i", $client_id);
$visa_apps_stmt->execute();
$visa_apps = $visa_apps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($visa_apps)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'data' => []]);
  exit();
}

// ✅ Build response data
$response = [];
foreach ($visa_apps as $app) {
  // Fetch visa package requirements
  $package_stmt = $conn->prepare("
    SELECT requirements_json
    FROM visa_packages
    WHERE id = ?
  ");
  $package_stmt->bind_param("i", $app['visa_package_id']);
  $package_stmt->execute();
  $package = $package_stmt->get_result()->fetch_assoc();
  
  $requirements = json_decode($package['requirements_json'] ?? '[]', true) ?? [];
  $requirements = array_filter($requirements, fn($req) => !empty($req['required']));
  
  // Fetch document submissions for this application
  $submissions_stmt = $conn->prepare("
    SELECT 
      id,
      visa_application_id,
      companion_id,
      requirement_id,
      file_name,
      file_path,
      mime_type,
      status,
      admin_comments,
      submitted_at,
      approved_at,
      approved_by_admin_id
    FROM visa_document_submissions
    WHERE visa_application_id = ?
    ORDER BY companion_id ASC, requirement_id ASC
  ");
  $submissions_stmt->bind_param("i", $app['id']);
  $submissions_stmt->execute();
  $submissions = $submissions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  
  // Fetch group members if group mode
  $companions = [];
  if ($app['application_mode'] === 'group') {
    $companions_stmt = $conn->prepare("
      SELECT id, full_name
      FROM client_visa_companions
      WHERE visa_application_id = ?
      ORDER BY id ASC
    ");
    $companions_stmt->bind_param("i", $app['id']);
    $companions_stmt->execute();
    $companions = $companions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  
  // Merge requirements with submissions
  $merged = [];
  foreach ($requirements as $req) {
    // Find submission for this requirement (null companion_id = lead guest)
    $submission = array_values(array_filter($submissions, fn($s) => 
      $s['requirement_id'] == $req['id'] && $s['companion_id'] === null
    ));
    
    $merged[] = [
      'requirement_id' => $req['id'],
      'name' => $req['name'] ?? '',
      'description' => $req['description'] ?? '',
      'status' => $submission ? $submission[0]['status'] : 'Not Submitted',
      'submission' => $submission ? $submission[0] : null
    ];
  }
  
  $response[] = [
    'visa_application_id' => $app['id'],
    'visa_package_id' => $app['visa_package_id'],
    'application_mode' => $app['application_mode'],
    'applicant_status' => $app['applicant_status'],
    'requirements' => $merged,
    'submissions' => $submissions,
    'companions' => $companions
  ];
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $response]);
exit();

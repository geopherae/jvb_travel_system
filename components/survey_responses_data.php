<?php
/**
 * Survey Responses Data Endpoint
 * Fetches survey responses from user_survey_status table
 */

require_once __DIR__ . '/../actions/db.php';

header('Content-Type: application/json');

$sort = $_GET['sort'] ?? 'recent';
$role = $_GET['role'] ?? 'all';
$surveyType = $_GET['survey_type'] ?? 'all';
$orderBy = $sort === 'oldest' ? 'ASC' : 'DESC';

// Build query based on role filter
$baseQuery = "
  SELECT 
    id,
    user_id,
    user_role,
    survey_type,
    response_payload,
    created_at,
    completed_at,
    is_completed
  FROM user_survey_status
";

$whereClauses = [];
$whereClauses[] = "user_role IN ('client', 'admin')";
// Exclude admin ID #1
$whereClauses[] = "NOT (user_role = 'admin' AND user_id = 1)";

if ($role !== 'all') {
  $whereClauses[] = "user_role = '" . mysqli_real_escape_string($conn, $role) . "'";
  
  $allowedTypes = [];
  if ($role === 'client') {
    $allowedTypes = ['first_login', 'status_confirmed', 'trip_complete'];
  } elseif ($role === 'admin') {
    $allowedTypes = ['first_login', 'admin_weekly_survey'];
  }
  
  if (!empty($allowedTypes)) {
    $typesStr = "'" . implode("','", array_map(function($t) use ($conn) { return mysqli_real_escape_string($conn, $t); }, $allowedTypes)) . "'";
    $whereClauses[] = "survey_type IN ($typesStr)";
  }
}

if ($surveyType !== 'all') {
  $whereClauses[] = "survey_type = '" . mysqli_real_escape_string($conn, $surveyType) . "'";
}

$query = $baseQuery . " WHERE " . implode(" AND ", $whereClauses) . " ORDER BY created_at $orderBy";

$result = mysqli_query($conn, $query);
$surveys = [];

if ($result && mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    // Parse response payload
    $responseData = null;
    if ($row['response_payload']) {
      $decoded = json_decode($row['response_payload'], true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $responseData = $decoded;
      }
    }

    $surveys[] = [
      'id' => $row['id'],
      'user_id' => $row['user_id'],
      'user_role' => $row['user_role'],
      'survey_type' => $row['survey_type'],
      'response_data' => $responseData,
      'created_at' => $row['created_at'],
      'completed_at' => $row['completed_at'],
      'is_completed' => (bool)$row['is_completed']
    ];
  }
}

echo json_encode([
  'surveys' => $surveys,
  'total' => count($surveys)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

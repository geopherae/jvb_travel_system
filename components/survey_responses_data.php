<?php
/**
 * Survey Responses Data Endpoint
 * Fetches survey responses from user_survey_status table
 */

require_once __DIR__ . '/../actions/db.php';

header('Content-Type: application/json');

$sort = $_GET['sort'] ?? 'recent';
$orderBy = $sort === 'oldest' ? 'ASC' : 'DESC';

// Fetch all survey responses
$query = "
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
  WHERE user_role = 'client'
  ORDER BY created_at $orderBy
";

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

<?php
session_start();
require_once __DIR__ . '/../actions/db.php';

// Logging helper
function logTripSurveyDebug($message) {
    $logFile = __DIR__ . '/../logs/survey_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] TRIP_COMPLETION: {$message}\n", FILE_APPEND);
}

try {
    // Validate client ID
    $clientId = $_SESSION['client_id'] ?? ($_SESSION['client']['id'] ?? null);
    if (!$clientId || !is_numeric($clientId)) {
        throw new Exception('Missing or invalid client ID');
    }

    $userRole     = 'client';
    $surveyType   = trim($_POST['survey_type'] ?? '');
    if ($surveyType !== 'trip_complete') {
        throw new Exception('Invalid survey type: ' . $surveyType);
    }

    $isCompleted  = 1;
    $completedAt  = date('Y-m-d H:i:s');

    logTripSurveyDebug("Processing submission for client {$clientId}");

// 📦 Build structured response payload (tolerate missing answers)
$rawAnswers = [
  'q1_overall_experience'    => $_POST['q1_overall_experience'] ?? null,
  'q2_access_ease'           => $_POST['q2_access_ease'] ?? null,
  'q3_coordination_impact'   => $_POST['q3_coordination_impact'] ?? null,
  'q4_most_helpful_feature'  => $_POST['q4_most_helpful_feature'] ?? null,
  'q5_improvement_suggestions' => $_POST['q5_improvement_suggestions'] ?? null,
  'q6_trip_review'           => $_POST['q6_trip_review'] ?? null,
];
$norm = [];
$answeredCount = 0;
$unanswered = [];
foreach ($rawAnswers as $k => $v) {
  $val = is_string($v) ? trim($v) : $v;
  if ($val === '' || $val === null) {
    $norm[$k] = null;
    $unanswered[] = $k;
  } else {
    $norm[$k] = $val;
    $answeredCount++;
  }
}
$responsePayload = [
  'survey_type' => $surveyType,
  'responses' => [
    "How would you rate your overall experience using the travel portal during your trip?" => $norm['q1_overall_experience'] ?? null,
    "Was it easy to access your itinerary and travel documents through the portal?" => $norm['q2_access_ease'] ?? null,
    "Did the portal help reduce the need for back-and-forth messaging with your travel coordinator?" => $norm['q3_coordination_impact'] ?? null,
    "Which portal feature did you find most helpful during your trip?" => $norm['q4_most_helpful_feature'] ?? null,
    "Was there anything confusing or missing from the portal that you’d like us to improve?" => $norm['q5_improvement_suggestions'] ?? null,
    "Would you like to share a short review or highlight from your trip?" => $norm['q6_trip_review'] ?? null
  ],
  'meta' => [
    'answered_count' => $answeredCount,
    'unanswered_keys' => $unanswered,
  ]
];

    $jsonPayload = json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to encode survey: ' . json_last_error_msg());
    }

    logTripSurveyDebug("JSON payload encoded: " . strlen($jsonPayload) . " bytes");

    // Update survey record
    $updateQuery = $conn->prepare("
      UPDATE user_survey_status
      SET is_completed = ?, completed_at = ?, response_payload = ?
      WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0
    ");

    if (!$updateQuery) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $updateQuery->bind_param("ississ", $isCompleted, $completedAt, $jsonPayload, $clientId, $userRole, $surveyType);
    
    if (!$updateQuery->execute()) {
        throw new Exception('Update failed: ' . $updateQuery->error);
    }

    $affectedRows = $updateQuery->affected_rows;
    logTripSurveyDebug("Update affected {$affectedRows} rows");
    $updateQuery->close();

    if ($affectedRows === 0) {
        logTripSurveyDebug("No survey record found or already completed for client {$clientId}");
    }

    // Cleanup session
    unset($_SESSION['show_trip_completion_survey']);
    unset($_SESSION['survey_type']);

    // Redirect with success
    $_SESSION['modal_status'] = 'survey_submitted';
    header("Location: ../client/client_dashboard.php");
    exit();

} catch (Exception $e) {
    error_log("Trip completion survey error: " . $e->getMessage());
    logTripSurveyDebug("Error: " . $e->getMessage());

    $_SESSION['modal_status'] = 'survey_failed';
    $_SESSION['error_message'] = $e->getMessage();

    header("Location: ../client/client_dashboard.php");
    exit();
}
?>
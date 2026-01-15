<?php
session_start();
require_once __DIR__ . '/../actions/db.php';

// 🧠 Survey metadata
$adminId     = $_SESSION['admin']['id'] ?? null;
$userRole    = 'admin';
$surveyType  = $_POST['survey_type'] ?? 'admin_weekly_survey';
$skipFlag    = $_POST['skip_survey'] ?? '0';
$completedAt = date('Y-m-d H:i:s');

// 📦 Build response payload
$deferUntil = date('Y-m-d 00:00:00', strtotime('tomorrow'));

$responsePayload = ($skipFlag === '1')
  ? [
      'survey_type' => $surveyType,
      'skipped' => true,
      'skip_until' => $deferUntil,
      'reason' => 'Admin skipped this week\'s survey'
    ]
  : [
      'survey_type' => $surveyType,
      'responses' => [
        "How manageable was your workload this week?" => $_POST['q1_workload_manageability'] ?? null,
        "Did the system help you complete tasks more efficiently?" => $_POST['q2_system_efficiency'] ?? null,
        "Were there any parts of the system that felt confusing or frustrating?" => $_POST['q3_confusing_parts'] ?? null,
        "What’s one improvement or feature you’d love to see next?" => $_POST['q4_feature_request'] ?? null,
        "How are you feeling overall?" => $_POST['q5_emotional_state'] ?? null
      ]
    ];

$jsonPayload = json_encode($responsePayload, JSON_UNESCAPED_UNICODE);

// 🛠 Update the most recent eligible pending survey (exclude future-scheduled ones)
if ($skipFlag === '1') {
  $updateQuery = $conn->prepare("
    UPDATE user_survey_status
    SET is_completed = 0, completed_at = NULL, response_payload = ?, created_at = ?
    WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0 AND created_at <= ?
    ORDER BY created_at ASC LIMIT 1
  ");
  $updateQuery->bind_param("ssisss", $jsonPayload, $deferUntil, $adminId, $userRole, $surveyType, $completedAt);
} else {
  $updateQuery = $conn->prepare("
    UPDATE user_survey_status
    SET is_completed = 1, completed_at = ?, response_payload = ?
    WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0 AND created_at <= ?
    ORDER BY created_at ASC LIMIT 1
  ");
  $updateQuery->bind_param("ssisss", $completedAt, $jsonPayload, $adminId, $userRole, $surveyType, $completedAt);
}

$success = $updateQuery->execute();
$updateQuery->close();

// 🗓 Schedule next weekly survey only if submitted and no future one exists
if ($success && $skipFlag !== '1') {
  $futureCheck = $conn->prepare("
    SELECT COUNT(*) FROM user_survey_status
    WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0 AND created_at > ?
  ");
  $futureCheck->bind_param("isss", $adminId, $userRole, $surveyType, $completedAt);
  $futureCheck->execute();
  $futureCheck->bind_result($futureCount);
  $futureCheck->fetch();
  $futureCheck->close();

  if ($futureCount == 0) {
    $nextSurveyDate = date('Y-m-d H:i:s', strtotime('+7 days'));
    $insertNext = $conn->prepare("
      INSERT INTO user_survey_status (user_id, user_role, survey_type, is_completed, created_at)
      VALUES (?, ?, ?, 0, ?)
    ");
    $insertNext->bind_param("isss", $adminId, $userRole, $surveyType, $nextSurveyDate);
    $insertNext->execute();
    $insertNext->close();
  }
}

// 🧹 Cleanup session
unset($_SESSION['show_weekly_survey_modal']);
unset($_SESSION['survey_type']);

// ✅ Toast feedback
$_SESSION['modal_status'] = $success
  ? ($skipFlag === '1' ? 'survey_skipped' : 'survey_submitted')
  : 'survey_failed';

header("Location: ../admin/admin_dashboard.php");
exit();
?>
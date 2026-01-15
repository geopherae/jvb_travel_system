<?php
session_start();
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../components/status_alert.php';


// 🧠 Survey metadata
$userId     = $_SESSION['admin']['id'] ?? null;
$userRole   = 'admin';
$surveyType = $_POST['survey_type'] ?? 'first_login';
$isCompleted = 1;
$completedAt = date('Y-m-d H:i:s');

// 📦 Build structured response payload
$responsePayload = [
  'survey_type' => $surveyType,
  'responses' => [
    "How useful do you expect this system to be for managing itineraries, documents, and client coordination?" => $_POST['q1_expected_usefulness'] ?? null,
    "From your initial impression, how easy do you think it will be to learn and navigate this system?" => $_POST['q2_expected_ease'] ?? null,
    "What part of your workflow do you hope this system will improve the most?" => $_POST['q3_expected_workflow_focus'] ?? null,
    "How confident are you that this system will reduce reliance on messaging apps and repetitive coordination?" => $_POST['q4_expected_coordination_improvement'] ?? null,
    "What are you hoping this system will help you do better?" => $_POST['q5_admin_expectations'] ?? null
  ]
];

$jsonPayload = json_encode($responsePayload, JSON_UNESCAPED_UNICODE);

// 🛠 Update survey record
$updateQuery = $conn->prepare("
  UPDATE user_survey_status
  SET is_completed = ?, completed_at = ?, response_payload = ?
  WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0
");

$updateQuery->bind_param("ississ", $isCompleted, $completedAt, $jsonPayload, $userId, $userRole, $surveyType);
$success = $updateQuery->execute();
$updateQuery->close();

// 🧹 Cleanup session
unset($_SESSION['show_survey_modal']);
unset($_SESSION['survey_type']);

// ✅ Toast feedback
$_SESSION['modal_status'] = $success ? 'survey_submitted' : 'survey_failed';
header("Location: ../admin/admin_dashboard.php");
exit();
?>
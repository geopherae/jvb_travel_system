<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\guard;

// Detect if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
  header('Content-Type: application/json');
}

// Guard authentication
guard('client');

$client_id = $_SESSION['client_id'] ?? null;
$survey_type = trim($_POST['survey_type'] ?? '');
$skip_survey = (int)($_POST['skip_survey'] ?? 0);
$template_id = (int)($_POST['template_id'] ?? 0);

if (!$client_id) {
  if ($isAjax) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  } else {
    $_SESSION['modal_status'] = 'survey_failed';
    $_SESSION['error_message'] = 'Unauthorized';
    header("Location: ../client/client_dashboard.php");
  }
  exit;
}

if ($survey_type !== 'status_confirmed') {
  if ($isAjax) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid survey type']);
  } else {
    $_SESSION['modal_status'] = 'survey_failed';
    $_SESSION['error_message'] = 'Invalid survey type';
    header("Location: ../client/client_dashboard.php");
  }
  exit;
}

try {
  // If skipped, mark as completed without saving responses
  if ($skip_survey === 1) {
    $stmt = $conn->prepare("
      UPDATE user_survey_status 
      SET is_completed = 1, updated_at = NOW() 
      WHERE user_id = ? 
      AND user_role = 'client' 
      AND survey_type = 'status_confirmed'
      LIMIT 1
    ");
    $stmt->bind_param("i", $client_id);
    
    if (!$stmt->execute()) {
      throw new Exception("Database error: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Clear session flag
    unset($_SESSION['show_confirmed_status_survey_modal']);
    
    if ($isAjax) {
      echo json_encode(['success' => true, 'message' => 'Survey skipped']);
    } else {
      $_SESSION['modal_status'] = 'survey_skipped';
      header("Location: ../client/client_dashboard.php");
    }
    exit;
  }

  // Collect survey responses (tolerate missing answers)
  $rawResponses = [
    'perceived_usefulness' => $_POST['q1_perceived_usefulness'] ?? null,
    'ease_of_use' => $_POST['q2_ease_of_use'] ?? null,
    'trust_security' => $_POST['q3_trust_security'] ?? null,
    'satisfaction_process' => $_POST['q4_satisfaction_process'] ?? null,
    'behavioral_intention' => $_POST['q5_behavioral_intention'] ?? null
  ];

  // Normalize values and compute metadata
  $responses = [];
  $answeredCount = 0;
  $unanswered = [];
  foreach ($rawResponses as $key => $value) {
    $normalized = is_string($value) ? trim($value) : $value;
    if ($normalized === '' || $normalized === null) {
      $responses[$key] = null;
      $unanswered[] = $key;
    } else {
      $responses[$key] = $normalized;
      $answeredCount++;
    }
  }

  // Build survey data as JSON
  $survey_data = [
    'survey_type' => 'status_confirmed',
    'responses' => $responses,
    'meta' => [
      'answered_count' => $answeredCount,
      'unanswered_keys' => $unanswered,
    ],
    'submitted_at' => date('Y-m-d H:i:s')
  ];

  $survey_json = json_encode($survey_data, JSON_UNESCAPED_UNICODE);

  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("JSON encoding error: " . json_last_error_msg());
  }

  // Update survey status
  $stmt = $conn->prepare("
    UPDATE user_survey_status 
    SET is_completed = 1, 
        response_payload = ?,
        completed_at = NOW() 
    WHERE user_id = ? 
    AND user_role = 'client' 
    AND survey_type = 'status_confirmed'
    LIMIT 1
  ");
  
  if (!$stmt) {
    throw new Exception("Prepare error: " . $conn->error);
  }
  
  $stmt->bind_param("si", $survey_json, $client_id);
  
  if (!$stmt->execute()) {
    throw new Exception("Database error: " . $stmt->error);
  }

  if ($stmt->affected_rows === 0) {
    throw new Exception("Survey record not found or already completed");
  }

  $stmt->close();

  // Clear session flag
  unset($_SESSION['show_confirmed_status_survey_modal']);

  if ($isAjax) {
    echo json_encode(['success' => true, 'message' => 'Survey submitted successfully', 'answered_count' => $answeredCount]);
  } else {
    $_SESSION['modal_status'] = 'survey_submitted';
    header("Location: ../client/client_dashboard.php");
  }
  exit;

} catch (Exception $e) {
  error_log("Error submitting confirmed status survey: " . $e->getMessage());
  $logFile = __DIR__ . '/../logs/survey_debug.log';
  file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Error: " . $e->getMessage() . "\n", FILE_APPEND);
  
  if ($isAjax) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while submitting the survey']);
  } else {
    $_SESSION['modal_status'] = 'survey_failed';
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../client/client_dashboard.php");
  }
  exit;
}

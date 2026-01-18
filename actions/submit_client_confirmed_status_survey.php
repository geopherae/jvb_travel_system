<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\guard;

// Logging helper
function logSurveyDebug($message) {
  $logFile = __DIR__ . '/../logs/survey_debug.log';
  $timestamp = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

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
  // Handle skip: defer survey for a day
  if ($skip_survey === 1) {
    $deferUntil = date('Y-m-d 00:00:00', strtotime('tomorrow'));
    $responsePayload = [
      'survey_type' => $survey_type,
      'skipped' => true,
      'skip_until' => $deferUntil,
      'reason' => 'Client deferred survey for a day'
    ];

    $jsonPayload = json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE || empty($jsonPayload)) {
      throw new Exception('Failed to encode survey skip payload: ' . json_last_error_msg());
    }

    $conn->begin_transaction();
    logSurveyDebug("🔒 Transaction started (skip)");

    $updateQuery = $conn->prepare("
      UPDATE user_survey_status
      SET is_completed = 0, completed_at = NULL, response_payload = ?, created_at = ?
      WHERE user_id = ? AND user_role = 'client' AND survey_type = ? AND is_completed = 0
    ");

    if (!$updateQuery) {
      throw new Exception('Database error: ' . $conn->error);
    }

    $updateQuery->bind_param(
      "ssis",
      $jsonPayload,
      $deferUntil,
      $client_id,
      $survey_type
    );

    if (!$updateQuery->execute()) {
      throw new Exception('Failed to defer survey: ' . $updateQuery->error);
    }

    if ($updateQuery->affected_rows === 0) {
      logSurveyDebug('No survey row found to defer; creating one for tomorrow');

      $insertQuery = $conn->prepare("
        INSERT INTO user_survey_status (user_id, user_role, survey_type, is_completed, created_at, completed_at, response_payload)
        VALUES (?, 'client', ?, 0, ?, NULL, ?)
      ");

      if (!$insertQuery) {
        throw new Exception('Insert preparation failed: ' . $conn->error);
      }

      $insertQuery->bind_param(
        "isss",
        $client_id,
        $survey_type,
        $deferUntil,
        $jsonPayload
      );

      if (!$insertQuery->execute()) {
        throw new Exception('Failed to create deferred survey row: ' . $insertQuery->error);
      }

      logSurveyDebug('Deferred survey row inserted');
    } else {
      logSurveyDebug("✅ Survey deferred until {$deferUntil}");
    }

    $updateQuery->close();
    $conn->commit();

    unset($_SESSION['show_confirmed_status_survey_modal']);
    
    if ($isAjax) {
      echo json_encode(['success' => true, 'message' => 'Survey deferred until tomorrow']);
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

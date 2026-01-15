<?php

session_start();
require_once __DIR__ . '/../actions/db.php';

// ✅ Logging helper
function logSurveyDebug($message) {
    $logFile = __DIR__ . '/../logs/survey_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

try {
    // ✅ Validate client ID
    $clientId = $_SESSION['client_id'] ?? null;
    if (!$clientId || !is_numeric($clientId)) {
        throw new Exception('Missing or invalid client ID');
    }

    $userRole   = 'client';
    $surveyType = 'first_login';
    $skipFlag   = $_POST['skip_survey'] ?? '0';

    // 🚪 Allow skipping for a day
    if ($skipFlag === '1') {
        $deferUntil = date('Y-m-d 00:00:00', strtotime('tomorrow'));
        $responsePayload = [
            'survey_type' => $surveyType,
            'skipped'     => true,
            'skip_until'  => $deferUntil,
            'reason'      => 'Client deferred survey for a day'
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
            WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0
        ");

        if (!$updateQuery) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $updateQuery->bind_param(
            "ssiss",
            $jsonPayload,
            $deferUntil,
            $clientId,
            $userRole,
            $surveyType
        );

        if (!$updateQuery->execute()) {
            throw new Exception('Failed to defer survey: ' . $updateQuery->error);
        }

        if ($updateQuery->affected_rows === 0) {
            throw new Exception('No survey found to defer');
        }

        $conn->commit();
        logSurveyDebug("✅ Survey deferred until {$deferUntil}");

        unset($_SESSION['show_client_survey_modal']);
        $_SESSION['modal_status'] = 'survey_skipped';

        header("Location: ../client/client_dashboard.php");
        exit();
    }

    // ✅ Collect survey data (tolerate missing answers; align names with modal)
    $surveyData = [
        'q1_expected_usefulness' => trim($_POST['q1_expected_usefulness'] ?? ''),
        'q2_expected_ease' => trim($_POST['q2_expected_ease'] ?? ''),
        'q3_upload_confidence' => trim($_POST['q3_upload_confidence'] ?? ''),
        'q4_task_simplicity' => trim($_POST['q4_task_simplicity'] ?? ''),
        'q5_feature_interest' => trim($_POST['q5_feature_interest'] ?? ''),
    ];

    $answeredCount = 0;
    $normalized = [];
    $unanswered = [];
    foreach ($surveyData as $k => $v) {
        if ($v === '') {
            $normalized[$k] = null;
            $unanswered[] = $k;
        } else {
            $normalized[$k] = $v;
            $answeredCount++;
        }
    }

    // ✅ Survey metadata
    $isCompleted = 1;
    $completedAt = date('Y-m-d H:i:s');

    // ✅ Build response payload
    $responsePayload = [
        'survey_type' => $surveyType,
        'responses' => [
            'usefulness'         => $normalized['q1_expected_usefulness'],
            'ease_of_use'        => $normalized['q2_expected_ease'],
            'upload_confidence'  => $normalized['q3_upload_confidence'],
            'task_simplicity'    => $normalized['q4_task_simplicity'],
            'feature_interest'   => $normalized['q5_feature_interest']
        ],
        'meta' => [
            'answered_count' => $answeredCount,
            'unanswered_keys' => $unanswered,
        ],
        'submitted_at' => $completedAt
    ];

    // ✅ Encode and validate JSON
    $jsonPayload = json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE || empty($jsonPayload)) {
        throw new Exception('Failed to encode survey responses: ' . json_last_error_msg());
    }

    logSurveyDebug("🧪 Final JSON payload: " . $jsonPayload);
    logSurveyDebug("📦 Payload length: " . strlen($jsonPayload));

    // ✅ Begin transaction
    $conn->begin_transaction();
    logSurveyDebug("🔒 Transaction started");

    // ✅ Prepare and execute update
    $updateQuery = $conn->prepare("
        UPDATE user_survey_status
        SET is_completed = ?, completed_at = ?, response_payload = ?
        WHERE user_id = ? AND user_role = ? AND survey_type = ? AND is_completed = 0
    ");
    if (!$updateQuery) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $updateQuery->bind_param(
        "ississ",
        $isCompleted,
        $completedAt,
        $jsonPayload,
        $clientId,
        $userRole,
        $surveyType
    );

    if (!$updateQuery->execute()) {
        throw new Exception('Failed to update survey: ' . $updateQuery->error);
    }

    if ($updateQuery->affected_rows === 0) {
        logSurveyDebug('ℹ️ No survey row updated; proceeding without error');
    }

    logSurveyDebug("✅ Survey record updated");
    logSurveyDebug("✅ Update affected rows: " . $updateQuery->affected_rows);

    // ✅ Commit transaction
    $conn->commit();
    logSurveyDebug("✅ Transaction committed");

    // ✅ Clear session flags
    unset($_SESSION['show_client_survey_modal']);
    $_SESSION['modal_status'] = 'survey_submitted';

    header("Location: ../client/client_dashboard.php");
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
        logSurveyDebug("⛔ Transaction rolled back");
    }

    error_log("Survey submission failed: " . $e->getMessage());
    logSurveyDebug("❌ Survey submission failed: " . $e->getMessage());

    $_SESSION['modal_status'] = 'survey_failed';
    $_SESSION['error_message'] = $e->getMessage();

    header("Location: ../client/client_dashboard.php");
    exit();

} finally {
    if (isset($updateQuery)) {
        $updateQuery->close();
    }
}
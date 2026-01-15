<?php
require_once __DIR__ . '/../actions/db.php';

if (!headers_sent()) {
    header('Content-Type: application/json');
}


function evaluateChecklistTask($conn, $client_id, $key): bool {
    if (!$client_id || !$key) return false;

    switch ($key) {
        case 'survey_taken':
            $stmt = $conn->prepare("
                SELECT is_completed 
                FROM user_survey_status 
                WHERE user_id = ? 
                AND user_role = 'client' 
                AND survey_type = 'first_login'
            ");
            break;

        case 'id_uploaded':
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM upload_files 
                WHERE client_id = ? 
                AND document_type IN ('passport', 'identification card')
            ");
            break;

        case 'id_approved':
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM upload_files 
                WHERE client_id = ? 
                AND document_type IN ('passport', 'identification card') 
                AND status = 'approved'
            ");
            break;

        case 'itinerary_confirmed':
            $stmt = $conn->prepare("
                SELECT is_confirmed 
                FROM client_itinerary 
                WHERE client_id = ?
            ");
            break;

        case 'photos_uploaded':
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM client_trip_photos 
                WHERE client_id = ? 
                AND file_name IS NOT NULL 
                AND file_name != ''
            ");
            break;

        case 'trip_survey_taken':
            $stmt = $conn->prepare("
                SELECT is_completed 
                FROM user_survey_status 
                WHERE user_id = ? 
                AND survey_type = 'trip_complete'
            ");
            break;

        case 'facebook_visited':
            return !empty($_SESSION["facebook_clicked_{$client_id}"]);

        default:
            return false;
    }

    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_column();
    $stmt->close();

    return match ($key) {
        'survey_taken', 'itinerary_confirmed', 'trip_survey_taken' => (bool) $result,
        default => (int) $result > 0,
    };
}

// ✅ Modular checklist evaluation function
function evaluateChecklistProgress($conn, $client_id, $template_id) {
    // 🔍 Fallback: fetch template_id if missing
    if (!$client_id || !$template_id) {
        if (!$client_id && isset($_SESSION['client_id'])) {
            $client_id = $_SESSION['client_id'];
        }

        if ($client_id && !$template_id) {
            $stmt = $conn->prepare("
                SELECT id 
                FROM user_survey_status 
                WHERE user_id = ? 
                AND user_role = 'client' 
                AND survey_type = 'first_login' 
                LIMIT 1
            ");
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $template_id = $stmt->get_result()->fetch_column();
            $stmt->close();
        }
    }

    if (!$client_id || !$template_id) {
        return ['error' => 'Missing client_id or template_id'];
    }

    // 🔹 Load checklist template
    $stmt = $conn->prepare("SELECT checklist_json FROM checklist_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $template_json = $stmt->get_result()->fetch_column();
    $stmt->close();
    $template = json_decode($template_json, true) ?? [];

    // 🔹 Load existing progress JSON
    $stmt = $conn->prepare("SELECT progress_json FROM client_checklist_progress WHERE client_id = ? AND template_id = ?");
    $stmt->bind_param("ii", $client_id, $template_id);
    $stmt->execute();
    $progress_json = $stmt->get_result()->fetch_column();
    $stmt->close();
    $progress = json_decode($progress_json, true) ?? [];

    // 🔹 Evaluate each task
    $updated = false;

    foreach ($template as &$item) {
        $key = $item['status_key'];
        $item['completed_at'] = $progress[$key] ?? null;
        $item['is_completed'] = isset($progress[$key]);

        if ($item['is_completed']) continue;

        $is_complete = false;

        switch ($key) {
            case 'survey_taken':
                $stmt = $conn->prepare("
                    SELECT is_completed 
                    FROM user_survey_status 
                    WHERE user_id = ? 
                    AND user_role = 'client' 
                    AND survey_type = 'first_login'
                ");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = (bool) $stmt->get_result()->fetch_column();
                $stmt->close();
                break;

            case 'id_uploaded':
                $stmt = $conn->prepare("SELECT COUNT(*) FROM upload_files WHERE client_id = ? AND document_type IN ('passport', 'identification card')");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = $stmt->get_result()->fetch_column() > 0;
                $stmt->close();
                break;

            case 'id_approved':
                $stmt = $conn->prepare("SELECT COUNT(*) FROM upload_files WHERE client_id = ? AND document_type IN ('passport', 'identification card') AND status = 'approved'");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = $stmt->get_result()->fetch_column() > 0;
                $stmt->close();
                break;

            case 'itinerary_confirmed':
                $stmt = $conn->prepare("SELECT is_confirmed FROM client_itinerary WHERE client_id = ?");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = (bool) $stmt->get_result()->fetch_column();
                $stmt->close();
                break;

            case 'photos_uploaded':
                $stmt = $conn->prepare("SELECT COUNT(*) FROM client_trip_photos WHERE client_id = ? AND file_name IS NOT NULL AND file_name != ''");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = $stmt->get_result()->fetch_column() > 0;
                $stmt->close();
                break;

            case 'trip_survey_taken':
                $stmt = $conn->prepare("SELECT is_completed FROM user_survey_status WHERE user_id = ? AND survey_type = 'trip_complete'");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $is_complete = (bool) $stmt->get_result()->fetch_column();
                $stmt->close();
                break;

            case 'facebook_visited':
                $is_complete = !empty($_SESSION["facebook_clicked_{$client_id}"]);
                break;
        }

        if ($is_complete) {
            $timestamp = date('Y-m-d H:i:s');
            $progress[$key] = $timestamp;
            $item['is_completed'] = true;
            $item['completed_at'] = $timestamp;
            $updated = true;
        }
    }

    // 🔄 Save updated progress JSON if needed
    if ($updated) {
        $new_json = json_encode($progress);
        $stmt = $conn->prepare("
            INSERT INTO client_checklist_progress (client_id, template_id, progress_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE progress_json = VALUES(progress_json), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("iis", $client_id, $template_id, $new_json);
        $stmt->execute();
        $stmt->close();
    }

    return $template;
}

// ✅ Optional: expose as endpoint for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $client_id   = $_GET['client_id']   ?? null;
    $template_id = $_GET['template_id'] ?? null;
    echo json_encode(evaluateChecklistProgress($conn, $client_id, $template_id));
}
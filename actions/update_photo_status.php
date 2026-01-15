<?php
ob_start(); // Prevent stray output
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../actions/notify.php';

use function Auth\guard;
guard('admin'); // NEW: Enforce admin authentication

header('Content-Type: application/json');
http_response_code(200); // Ensure frontend sees this as a successful response

$photo_id    = $_GET['id'] ?? null;
$action      = $_GET['action'] ?? null; // NEW: Support 'delete' action
$status      = $_GET['status'] ?? null; // Existing: For status updates
$admin_id    = $_SESSION['admin']['id'] ?? 0; // UPDATED: Align with auth.php
$admin_role  = 'admin';

$allowed_actions = ['delete']; // NEW: Allowed actions
$allowed_statuses = ['Approved', 'Rejected']; // UPDATED: Renamed for clarity
$action_type = $action === 'delete' ? 'PHOTO_DELETION' : 'PHOTO_STATUS_UPDATE'; // NEW: Adjust based on action
$target_type = 'client_trip_photo';
$module      = 'photos';
$session_id  = session_id();
$ip_address  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$kpi_tag     = 'photo_flow';
$impact      = 'medium';

// ✅ Status badge helper
function getStatusClass(string $status): string {
    return match ($status) {
        'Approved' => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
        'Rejected' => 'bg-red-100 text-red-700 border border-red-300',
        default    => 'bg-yellow-100 text-yellow-700 border border-yellow-300'
    };
}

// ✅ Audit logger
function logAudit($conn, $success, $photo_id, $action_or_status, $admin_id, $admin_role, $action_type, $target_type, $module, $session_id, $ip_address, $user_agent, $kpi_tag, $impact) {
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            action_type, actor_role, actor_id,
            target_type, target_id, changes,
            severity, module, timestamp,
            session_id, ip_address, user_agent,
            kpi_tag, business_impact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
    ");

    $severity = $success ? 'info' : 'error';
    $changes = json_encode([
        'photo_id' => $photo_id,
        'action_or_status' => $action_or_status, // UPDATED: Generic for status or delete
        'result' => $success ? 'success' : 'failure'
    ], JSON_UNESCAPED_UNICODE);

    $stmt->bind_param(
        "ssissssssssss",
        $action_type,
        $admin_role,
        $admin_id,
        $target_type,
        $photo_id,
        $changes,
        $severity,
        $module,
        $session_id,
        $ip_address,
        $user_agent,
        $kpi_tag,
        $impact
    );

    $stmt->execute();
    $stmt->close();
}

// ✅ Validate input
if (!$photo_id || ($action !== 'delete' && !in_array($status, $allowed_statuses))) {
    $_SESSION['modal_status'] = 'error';

    logAudit($conn, false, $photo_id, $action ?? $status, $admin_id, $admin_role, $action_type, $target_type, $module, $session_id, $ip_address, $user_agent, $kpi_tag, $impact);

    echo json_encode([
        'success' => false,
        'toast' => 'error',
        'message' => 'Invalid action, status, or photo ID.',
        'data' => [
            'document_status' => $status,
            'action' => $action,
            'status_updated_by' => 'System',
            'id' => $photo_id
        ]
    ]);
    exit;
}

// ✅ Get admin full name
$status_updated_by = 'System';
$adminQuery = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE id = ?");
$adminQuery->bind_param("i", $admin_id);
$adminQuery->execute();
$adminResult = $adminQuery->get_result();
if ($adminRow = $adminResult->fetch_assoc()) {
    $status_updated_by = trim($adminRow['first_name'] . ' ' . $adminRow['last_name']);
}
$adminQuery->close();

// ✅ Get client_id and file_path for deletion or notification
$clientQuery = $conn->prepare("SELECT client_id, file_path FROM client_trip_photos WHERE id = ?");
$clientQuery->bind_param("i", $photo_id);
$clientQuery->execute();
$result = $clientQuery->get_result();
$photo_data = $result->fetch_assoc();
$client_id = $photo_data['client_id'] ?? null;
$file_path = $photo_data['file_path'] ?? null; // NEW: Fetch file path for deletion
$clientQuery->close();

// ✅ Handle deletion
if ($action === 'delete') {
    // Delete the photo record
    $stmt = $conn->prepare("DELETE FROM client_trip_photos WHERE id = ?");
    $stmt->bind_param("i", $photo_id);
    $success = $stmt->execute();
    $stmt->close();

    // Delete the physical file, if it exists
    if ($success && $file_path && file_exists($file_path)) {
        if (!unlink($file_path)) {
            error_log("Failed to delete photo file: $file_path");
            // Continue, as DB deletion succeeded
        }
    }

    if (!$success) {
        logAudit($conn, false, $photo_id, 'delete', $admin_id, $admin_role, $action_type, $target_type, $module, $session_id, $ip_address, $user_agent, $kpi_tag, $impact);
        echo json_encode([
            'success' => false,
            'toast' => 'error',
            'message' => 'Failed to delete photo.',
            'data' => ['id' => $photo_id]
        ]);
        exit;
    }

    // Notify client
    if ($client_id) {
        notify([
            'recipient_type' => 'client',
            'recipient_id'   => $client_id,
            'event'          => 'photo_deleted',
            'context'        => [
                'photo_id'   => $photo_id,
                'admin_id'   => $admin_id,
                'admin_name' => $status_updated_by
            ]
        ]);
    }

    logAudit($conn, true, $photo_id, 'delete', $admin_id, $admin_role, $action_type, $target_type, $module, $session_id, $ip_address, $user_agent, $kpi_tag, $impact);
    $_SESSION['modal_status'] = 'success';

    echo json_encode([
        'success' => true,
        'toast' => 'success',
        'message' => 'Photo deleted successfully.',
        'data' => ['id' => $photo_id, 'client_id' => $client_id]
    ]);
    exit;
}

// ✅ Update photo status (existing logic)
if ($status === 'Approved') {
    $stmt2 = $conn->prepare("UPDATE client_trip_photos SET document_status = ?, status_updated_by = ?, approved_at = NOW() WHERE id = ?");
} else {
    $stmt2 = $conn->prepare("UPDATE client_trip_photos SET document_status = ?, status_updated_by = ? WHERE id = ?");
}
$stmt2->bind_param("ssi", $status, $status_updated_by, $photo_id);
$stmt2->execute();
$stmt2->close();

// ✅ Insert notification (existing logic)
if ($client_id) {
    // Only notify if approved (rejections are less common for photos)
    if ($status === 'Approved') {
        notify([
            'recipient_type' => 'client',
            'recipient_id'   => $client_id,
            'event'          => 'photo_approved',
            'context'        => [
                'photo_id'   => $photo_id,
                'day'        => 1, // Fetch from photo record if needed
                'admin_id'   => $admin_id,
                'admin_name' => $status_updated_by
            ]
        ]);
    } elseif ($status === 'Rejected') {
        notify([
            'recipient_type' => 'client',
            'recipient_id'   => $client_id,
            'event'          => 'photo_rejected',
            'context'        => [
                'photo_id'   => $photo_id,
                'day'        => 1,
                'admin_id'   => $admin_id,
                'admin_name' => $status_updated_by,
                'reason'     => ' Reason: Please check photo quality and guidelines.'
            ]
        ]);
    }
}

// ✅ Log success (existing logic)
logAudit($conn, true, $photo_id, $status, $admin_id, $admin_role, $action_type, $target_type, $module, $session_id, $ip_address, $user_agent, $kpi_tag, $impact);

// ✅ Set toast status
$_SESSION['modal_status'] = 'success';

// ✅ Return updated status and styling
echo json_encode([
    'success' => true,
    'toast' => 'success',
    'message' => "Photo status updated to {$status}.",
    'data' => [
        'document_status' => $status,
        'status_updated_by' => $status_updated_by,
        'status_class' => getStatusClass($status),
        'approved_at' => $status === 'Approved' ? date('M j, Y H:i') : null,
        'id' => $photo_id,
        'client_id' => $client_id
    ]
]);
$conn->close();
ob_end_flush();
exit;
?>
<?php
/**
 * Update Visa Document Status
 * Handles approval/rejection of visa document submissions
 */

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
use function Auth\getActorContext;

guard('admin');

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// Get actor context for audit logging
$actor = getActorContext();
$adminId = $actor['id'] ?? null;
$adminName = $_SESSION['admin']['full_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Sanitize inputs
$submissionId = trim($_POST['submission_id'] ?? '');
$status = trim($_POST['status'] ?? '');
$adminComments = trim($_POST['admin_comments'] ?? '');

// Validate required fields
if (empty($submissionId)) {
    echo json_encode(['success' => false, 'message' => 'Submission ID is required.']);
    exit;
}

if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current submission data
    $stmt = $conn->prepare("
        SELECT id, status, admin_comments 
        FROM visa_document_submissions 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentData = $result->fetch_assoc();
    $stmt->close();

    if (!$currentData) {
        throw new Exception('Document submission not found.');
    }

    // Update the submission
    $approvedAt = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;
    
    $approvedByAdminId = ($status === 'Approved') ? ($adminId ?? null) : null;

    $stmt = $conn->prepare("
        UPDATE visa_document_submissions
        SET 
            status = ?,
            admin_comments = ?,
            approved_at = ?,
            approved_by_admin_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('sssii', $status, $adminComments, $approvedAt, $approvedByAdminId, $submissionId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update document status: ' . $stmt->error);
    }
    $stmt->close();

    // ═══════════════════════════════════════════════════════════════════════════
    // Send Notification to Client
    // ═══════════════════════════════════════════════════════════════════════════
    if ($status === 'Approved' || $status === 'Rejected') {
        require_once __DIR__ . '/notify.php';
        $notifyManager = new NotificationManager($conn);

        // Get client ID and requirement details from submission
        $detailsStmt = $conn->prepare("
            SELECT vds.requirement_name, vds.visa_application_id,
                   cva.client_id, c.full_name as client_name,
                   vp.visa_package_name
            FROM visa_document_submissions vds
            JOIN client_visa_applications cva ON vds.visa_application_id = cva.id
            JOIN clients c ON cva.client_id = c.id
            JOIN visa_packages vp ON cva.visa_package_id = vp.id
            WHERE vds.id = ?
        ");
        $detailsStmt->bind_param('i', $submissionId);
        $detailsStmt->execute();
        $detailsResult = $detailsStmt->get_result();
        
        if ($detailsResult->num_rows > 0) {
            $details = $detailsResult->fetch_assoc();
            
            if ($status === 'Approved') {
                $notifyManager->send([
                    'recipient_type' => 'client',
                    'recipient_id' => $details['client_id'],
                    'event' => 'document_approved',
                    'context' => [
                        'client_id' => $details['client_id'],
                        'document_name' => $details['requirement_name'],
                        'visa_package_name' => $details['visa_package_name'],
                        'approved_by' => $adminName
                    ]
                ]);
            } else if ($status === 'Rejected') {
                $reason = !empty($adminComments) ? ' Reason: ' . $adminComments : '';
                $notifyManager->send([
                    'recipient_type' => 'client',
                    'recipient_id' => $details['client_id'],
                    'event' => 'document_rejected',
                    'context' => [
                        'client_id' => $details['client_id'],
                        'document_name' => $details['requirement_name'],
                        'reason' => $reason,
                        'visa_package_name' => $details['visa_package_name']
                    ]
                ]);
            }
        }
        $detailsStmt->close();
    }

    // Log the action (optional - if you have audit logging)
    if (function_exists('LogHelper\logClientOnboardingAudit')) {
        require_once __DIR__ . '/../includes/log_helper.php';
        \LogHelper\logClientOnboardingAudit(
            $conn,
            null, // client_id if available
            'visa_document_status_updated',
            [
                'submission_id' => $submissionId,
                'old_status' => $currentData['status'],
                'new_status' => $status,
                'admin_comments' => $adminComments
            ],
            $actor
        );
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Document status updated to {$status}.",
        'data' => [
            'status' => $status,
            'approved_at' => $approvedAt,
            'updated_by' => $adminName
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Update visa document status error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => (ENV === 'development') 
            ? $e->getMessage() 
            : 'An error occurred while updating the document status.'
    ]);
}

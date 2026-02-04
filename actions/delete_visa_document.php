<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function LogHelper\logClientOnboardingAudit;

// Set JSON header before any output
header('Content-Type: application/json');

// Allow both admin and client access
if (isset($_SESSION['admin']['id'])) {
  guard('admin');
  $isAdmin = true;
} else {
  guard('client');
  $isAdmin = false;
}

$response = ['success' => false, 'message' => ''];

try {
    // Get submission ID
    $submissionId = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : -1;
    
    if ($submissionId < 0) {
        throw new Exception('Invalid submission ID.');
    }

    // Fetch document details before deletion
    $stmt = $conn->prepare('
        SELECT id, visa_application_id, companion_id, requirement_id, requirement_name, file_path, file_name
        FROM visa_document_submissions
        WHERE id = ?
        LIMIT 1
    ');
    if (!$stmt) throw new Exception('Database error: ' . $conn->error);
    
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Document not found.');
    }
    
    $document = $result->fetch_assoc();
    $stmt->close();

    // Get client ID from visa application
    $visaStmt = $conn->prepare('SELECT client_id FROM client_visa_applications WHERE id = ? LIMIT 1');
    if (!$visaStmt) throw new Exception('Database error: ' . $conn->error);
    
    $visaStmt->bind_param('i', $document['visa_application_id']);
    $visaStmt->execute();
    $visaResult = $visaStmt->get_result();
    
    if ($visaResult->num_rows === 0) {
        throw new Exception('Visa application not found.');
    }
    
    $visaApp = $visaResult->fetch_assoc();
    $clientId = $visaApp['client_id'];
    $visaStmt->close();

    // Verify client can only delete their own documents
    if (!$isAdmin) {
        $sessionClientId = (int)$_SESSION['client_id'];
        if ($clientId != $sessionClientId) {
            throw new Exception('Unauthorized: You can only delete your own documents.');
        }
    }

    // Delete physical file if exists
    $filePath = __DIR__ . '/../' . ltrim($document['file_path'], '/');
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete database record
    $deleteStmt = $conn->prepare('DELETE FROM visa_document_submissions WHERE id = ?');
    if (!$deleteStmt) throw new Exception('Database error: ' . $conn->error);
    
    $deleteStmt->bind_param('i', $submissionId);
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete document record: ' . $deleteStmt->error);
    }
    $deleteStmt->close();

    // Log the action
    $actor = Auth\getActorContext();
    logClientOnboardingAudit($conn, $clientId, 'visa_document_deleted', [
        'visa_application_id' => $document['visa_application_id'],
        'requirement_id' => $document['requirement_id'],
        'requirement_name' => $document['requirement_name'],
        'companion_id' => $document['companion_id'],
        'file_name' => $document['file_name']
    ], $actor);

    // Set session status for toast notification
    $_SESSION['modal_status'] = 'document_deleted';

    $response['success'] = true;
    $response['message'] = 'Document deleted successfully.';

} catch (Exception $e) {
    error_log('Error in delete_visa_document.php: ' . $e->getMessage());
    $response['message'] = ENV === 'development' ? $e->getMessage() : 'An error occurred while deleting the document.';
}

echo json_encode($response);
?>

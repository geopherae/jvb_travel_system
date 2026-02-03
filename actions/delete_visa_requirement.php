<?php
/**
 * Delete Visa Requirement from Client's Application
 * 
 * Removes a specific requirement from the client_visa_requirements record,
 * allowing admins to exclude no-longer-needed documents from visa packages.
 * 
 * POST Parameters:
 *   - requirement_id: The ID of the requirement to remove
 *   - visa_application_id: The visa application ID
 */

// Prevent direct access (allow POST requests via AJAX)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
  exit('Access denied.');
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

use function Auth\guard;
use function Auth\getActorContext;
use function LogHelper\logClientOnboardingAudit;

// Only admins can delete requirements
guard('admin');

header('Content-Type: application/json');

try {
  // Validate input
  $requirementId = trim($_POST['requirement_id'] ?? '');
  $visaAppId = intval($_POST['visa_application_id'] ?? 0);
  $companionId = $_POST['companion_id'] !== '' ? intval($_POST['companion_id']) : null;

  if (!$requirementId || !$visaAppId) {
    throw new Exception('Missing required parameters.');
  }

  // Fetch the visa application to get client_id
  $appStmt = $conn->prepare("
    SELECT client_id FROM client_visa_applications WHERE id = ?
  ");
  $appStmt->bind_param("i", $visaAppId);
  $appStmt->execute();
  $appResult = $appStmt->get_result()->fetch_assoc();
  $appStmt->close();

  if (!$appResult) {
    throw new Exception('Visa application not found.');
  }

  $clientId = $appResult['client_id'];

  // Fetch ONLY the specific applicant's requirements (lead or specific companion)
  // Use IS NULL for lead (no companion_id), or match companion_id for companions
  if ($companionId === null) {
    $reqStmt = $conn->prepare("
      SELECT id, requirements_json FROM client_visa_requirements 
      WHERE client_id = ? AND companion_id IS NULL
      LIMIT 1
    ");
    $reqStmt->bind_param("i", $clientId);
  } else {
    $reqStmt = $conn->prepare("
      SELECT id, requirements_json FROM client_visa_requirements 
      WHERE client_id = ? AND companion_id = ?
      LIMIT 1
    ");
    $reqStmt->bind_param("ii", $clientId, $companionId);
  }

  $reqStmt->execute();
  $reqResult = $reqStmt->get_result();
  
  $updateCount = 0;

  if ($row = $reqResult->fetch_assoc()) {
    $reqId = $row['id'];
    $reqJson = $row['requirements_json'];
    $requirements = json_decode($reqJson, true);

    if (!is_array($requirements)) {
      $requirements = [];
    }

    // Filter out the requirement with matching ID
    $filteredRequirements = array_filter($requirements, function($req) use ($requirementId) {
      return ($req['id'] ?? '') !== $requirementId;
    });

    // Re-index array
    $filteredRequirements = array_values($filteredRequirements);

    // Update only if something changed
    if (count($filteredRequirements) < count($requirements)) {
      $newJson = json_encode($filteredRequirements, JSON_UNESCAPED_UNICODE);
      
      $updateStmt = $conn->prepare("
        UPDATE client_visa_requirements 
        SET requirements_json = ? 
        WHERE id = ?
      ");
      $updateStmt->bind_param("si", $newJson, $reqId);
      $updateStmt->execute();
      $updateStmt->close();

      $updateCount++;
    }
  }

  $reqStmt->close();

  // Log the action via audit trail
  $actor = getActorContext();

  // Try to log - but don't fail if it doesn't work
  try {
    logClientOnboardingAudit(
      $conn,
      $clientId,
      'visa_requirement_removed',
      [
        'requirement_id' => $requirementId,
        'visa_application_id' => $visaAppId,
        'companion_id' => $companionId,
        'records_updated' => $updateCount
      ],
      $actor
    );
  } catch (Exception $logError) {
    error_log('Audit log error: ' . $logError->getMessage());
    // Continue - don't fail if logging fails
  }

  echo json_encode([
    'success' => $updateCount > 0,
    'message' => $updateCount > 0 
      ? 'Requirement removed successfully.' 
      : 'Requirement not found.',
    'records_updated' => $updateCount
  ]);

} catch (Exception $e) {
  error_log('Error in delete_visa_requirement.php: ' . $e->getMessage());
  
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => ENV === 'development' ? $e->getMessage() : 'An error occurred.'
  ]);
}

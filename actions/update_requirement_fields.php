<?php
/**
 * Update requirement name and/or description in client_visa_requirements table
 * Only admins can use this endpoint
 * Called when admin edits requirement fields in the upload modal
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
  exit('Access denied.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\guard;
guard('admin');

header('Content-Type: application/json');

try {
  $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
  $companionId = isset($_POST['companion_id']) ? (int) $_POST['companion_id'] : null;
  $requirementId = isset($_POST['requirement_id']) ? trim($_POST['requirement_id']) : '';
  $newName = isset($_POST['req_name']) ? trim($_POST['req_name']) : '';
  $newDescription = isset($_POST['req_description']) ? trim($_POST['req_description']) : '';

  // Validate inputs
  if (!$clientId || !$requirementId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
  }

  if (empty($newName) && empty($newDescription)) {
    echo json_encode(['success' => false, 'message' => 'At least one field (name or description) is required']);
    exit;
  }

  // Get current requirements JSON
  $query = "SELECT requirements_json FROM client_visa_requirements 
            WHERE client_id = ? AND " . ($companionId ? "companion_id = ?" : "companion_id IS NULL");
  
  $stmt = $conn->prepare($query);
  if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
  }

  if ($companionId) {
    $stmt->bind_param("ii", $clientId, $companionId);
  } else {
    $stmt->bind_param("i", $clientId);
  }

  if (!$stmt->execute()) {
    throw new Exception("Execute failed: " . $stmt->error);
  }

  $result = $stmt->get_result();
  if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Requirement record not found']);
    $stmt->close();
    exit;
  }

  $row = $result->fetch_assoc();
  $stmt->close();

  // Decode JSON
  $requirementsJson = $row['requirements_json'];
  $requirements = json_decode($requirementsJson, true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Invalid JSON in requirements: " . json_last_error_msg());
  }

  if (!is_array($requirements)) {
    throw new Exception("Requirements JSON is not an array");
  }

  // Find and update the specific requirement
  $found = false;
  foreach ($requirements as &$req) {
    if ($req['id'] === $requirementId) {
      if (!empty($newName)) {
        $req['name'] = $newName;
      }
      if (!empty($newDescription)) {
        $req['description'] = $newDescription;
      }
      $found = true;
      break;
    }
  }

  if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Requirement ID not found in this applicant\'s requirements']);
    exit;
  }

  // Encode back to JSON
  $updatedJson = json_encode($requirements, JSON_UNESCAPED_UNICODE);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Failed to encode JSON: " . json_last_error_msg());
  }

  // Update database
  $updateQuery = "UPDATE client_visa_requirements 
                  SET requirements_json = ? 
                  WHERE client_id = ? AND " . ($companionId ? "companion_id = ?" : "companion_id IS NULL");
  
  $updateStmt = $conn->prepare($updateQuery);
  if (!$updateStmt) {
    throw new Exception("Prepare failed: " . $conn->error);
  }

  if ($companionId) {
    $updateStmt->bind_param("sii", $updatedJson, $clientId, $companionId);
  } else {
    $updateStmt->bind_param("si", $updatedJson, $clientId);
  }

  if (!$updateStmt->execute()) {
    throw new Exception("Update failed: " . $updateStmt->error);
  }

  $updateStmt->close();

  echo json_encode(['success' => true, 'message' => 'Requirement updated successfully']);

} catch (Exception $e) {
  error_log("Error in update_requirement_fields.php: " . $e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => ENV === 'development' ? $e->getMessage() : 'Failed to update requirement'
  ]);
}
?>

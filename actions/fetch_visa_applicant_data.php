<?php
/**
 * Fetch Complete Visa Applicant Data for Edit Modal
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\guard;
guard('admin');

header('Content-Type: application/json');

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  exit('Access denied.');
}

try {
  $applicantType = trim($_POST['applicant_type'] ?? '');
  $clientId = (int) ($_POST['client_id'] ?? 0);
  $applicantId = (int) ($_POST['applicant_id'] ?? 0);

  if (!$clientId || !$applicantType) {
    throw new Exception('Missing required parameters.');
  }

  $applicantData = null;

  if ($applicantType === 'lead') {
    // Fetch lead applicant (client) data
    $stmt = $conn->prepare("
        SELECT 
          id,
          full_name as name,
          email,
          phone_number as phone,
          address,
          client_profile_photo,
          passport_number as passport,
          passport_expiry,
          visa_lead_applicant_status as applicant_status,
          financial_source,
          visa_application_id,
          created_at
        FROM clients
        WHERE id = ?
    ");
    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $applicantData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$applicantData) {
      throw new Exception('Applicant not found.');
    }

    // Parse applicant_status JSON to array of option values
    if (!empty($applicantData['applicant_status'])) {
      $decoded = json_decode($applicantData['applicant_status'], true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Extract just the option values from [{"option": "...", "label": "..."}, ...]
        $applicantData['applicant_status'] = array_column($decoded, 'option');
      } else {
        // If not valid JSON, treat as single value and wrap in array
        $applicantData['applicant_status'] = [$applicantData['applicant_status']];
      }
    } else {
      $applicantData['applicant_status'] = [];
    }

    // Alias for frontend compatibility
    $applicantData['client_profile_photo'] = $applicantData['client_profile_photo'] ?? '';

    // Fetch visa_type from visa requirements if available
    $visaStmt = $conn->prepare("
      SELECT visa_type FROM client_visa_requirements
      WHERE client_id = ? AND companion_id IS NULL
      LIMIT 1
    ");
    $visaStmt->bind_param('i', $clientId);
    $visaStmt->execute();
    $visaResult = $visaStmt->get_result()->fetch_assoc();
    $visaStmt->close();

    if ($visaResult) {
      $applicantData['visa_type'] = $visaResult['visa_type'];
    }

    // Fetch visa types AND applicant_status_options from assigned visa package
    $visaTypeOptions = [];
    $applicantStatusOptions = [];
    $packageId = null;
    $visaApplicationId = $applicantData['visa_application_id'] ?? null;

    if (!empty($visaApplicationId)) {
      // Get visa_package_id from client_visa_applications using visa_application_id
      $pkgIdStmt = $conn->prepare("SELECT visa_package_id FROM client_visa_applications WHERE id = ? LIMIT 1");
      $pkgIdStmt->bind_param('i', $visaApplicationId);
      $pkgIdStmt->execute();
      $pkgIdResult = $pkgIdStmt->get_result()->fetch_assoc();
      $pkgIdStmt->close();
      if ($pkgIdResult && !empty($pkgIdResult['visa_package_id'])) {
        $packageId = $pkgIdResult['visa_package_id'];
      }
    }

    // If still no packageId, try to get from latest client_visa_applications for this client
    if (empty($packageId)) {
      $pkgIdStmt = $conn->prepare("SELECT visa_package_id FROM client_visa_applications WHERE client_id = ? ORDER BY id DESC LIMIT 1");
      $pkgIdStmt->bind_param('i', $clientId);
      $pkgIdStmt->execute();
      $pkgIdResult = $pkgIdStmt->get_result()->fetch_assoc();
      $pkgIdStmt->close();
      if ($pkgIdResult && !empty($pkgIdResult['visa_package_id'])) {
        $packageId = $pkgIdResult['visa_package_id'];
      }
    }

    if ($packageId) {
      $pkgStmt = $conn->prepare("SELECT visa_types_json, applicant_status_options FROM visa_packages WHERE id = ? LIMIT 1");
      $pkgStmt->bind_param('i', $packageId);
      $pkgStmt->execute();
      $pkgResult = $pkgStmt->get_result()->fetch_assoc();
      $pkgStmt->close();
      if ($pkgResult) {
        // Parse visa_types_json
        if (!empty($pkgResult['visa_types_json'])) {
          $types = json_decode($pkgResult['visa_types_json'], true);
          if (is_array($types)) {
            foreach ($types as $typeObj) {
              if (is_array($typeObj) && isset($typeObj['type'])) {
                $typeName = trim((string) $typeObj['type']);
              } elseif (is_string($typeObj)) {
                $typeName = trim($typeObj);
              } else {
                continue;
              }
              if ($typeName !== '') {
                $visaTypeOptions[$typeName] = true;
              }
            }
          }
        }
        // Parse applicant_status_options
        if (!empty($pkgResult['applicant_status_options'])) {
          $statusOpts = json_decode($pkgResult['applicant_status_options'], true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($statusOpts)) {
            $applicantStatusOptions = $statusOpts;
          }
        }
      }
    }

    $visaTypeOptions = array_keys($visaTypeOptions);
    sort($visaTypeOptions, SORT_NATURAL | SORT_FLAG_CASE);
    $applicantData['visa_type_options'] = $visaTypeOptions;
    $applicantData['applicant_status_options'] = $applicantStatusOptions;

  } elseif ($applicantType === 'companion') {
    // Fetch companion data
    if (!$applicantId) {
      throw new Exception('Companion ID is required.');
    }

    $stmt = $conn->prepare("
        SELECT 
          id,
          full_name as name,
          email,
          phone_number as phone,
          address,
          companions_photo,
          passport_number as passport,
          passport_expiry,
          applicant_status,
          financial_source,
          relationship,
          visa_application_id,
          created_at
        FROM client_visa_companions
        WHERE id = ? AND (SELECT client_id FROM client_visa_applications WHERE id = visa_application_id LIMIT 1) = ?
    ");
    $stmt->bind_param('ii', $applicantId, $clientId);
    $stmt->execute();
    $applicantData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$applicantData) {
      throw new Exception('Companion not found.');
    }

    // Parse applicant_status JSON to array
    if (!empty($applicantData['applicant_status'])) {
      $decoded = json_decode($applicantData['applicant_status'], true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Check if it's an array of objects (new format) or array of strings (old format)
        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['option'])) {
          // New format: [{"option": "employed", "label": "..."}, ...]
          // Extract just the option values
          $applicantData['applicant_status'] = array_map(function($item) {
            return $item['option'];
          }, $decoded);
        } else {
          // Old format: ["employed", "student"] - use as-is
          $applicantData['applicant_status'] = $decoded;
        }
      } else {
        // If not valid JSON, treat as single value and wrap in array
        $applicantData['applicant_status'] = [$applicantData['applicant_status']];
      }
    } else {
      $applicantData['applicant_status'] = [];
    }

    // Alias for frontend compatibility
    $applicantData['companions_photo'] = $applicantData['companions_photo'] ?? '';

    // Fetch visa_type from visa requirements if available
    $visaStmt = $conn->prepare("
      SELECT visa_type FROM client_visa_requirements
      WHERE companion_id = ?
      LIMIT 1
    ");
    $visaStmt->bind_param('i', $applicantId);
    $visaStmt->execute();
    $visaResult = $visaStmt->get_result()->fetch_assoc();
    $visaStmt->close();

    if ($visaResult) {
      $applicantData['visa_type'] = $visaResult['visa_type'];
    }

    // Fetch visa types AND applicant_status_options from assigned visa package
    $visaTypeOptions = [];
    $applicantStatusOptions = [];
    $packageId = null;
    $companionVisaAppId = $applicantData['visa_application_id'] ?? null;

    if (!empty($companionVisaAppId)) {
      // Get visa_package_id from client_visa_applications
      $pkgIdStmt = $conn->prepare("SELECT visa_package_id FROM client_visa_applications WHERE id = ? LIMIT 1");
      $pkgIdStmt->bind_param('i', $companionVisaAppId);
      $pkgIdStmt->execute();
      $pkgIdResult = $pkgIdStmt->get_result()->fetch_assoc();
      $pkgIdStmt->close();
      if ($pkgIdResult && !empty($pkgIdResult['visa_package_id'])) {
        $packageId = $pkgIdResult['visa_package_id'];
      }
    }

    // If still no packageId, fallback to latest for client
    if (empty($packageId)) {
      $pkgIdStmt = $conn->prepare("SELECT visa_package_id FROM client_visa_applications WHERE client_id = ? ORDER BY id DESC LIMIT 1");
      $pkgIdStmt->bind_param('i', $clientId);
      $pkgIdStmt->execute();
      $pkgIdResult = $pkgIdStmt->get_result()->fetch_assoc();
      $pkgIdStmt->close();
      if ($pkgIdResult && !empty($pkgIdResult['visa_package_id'])) {
        $packageId = $pkgIdResult['visa_package_id'];
      }
    }

    if ($packageId) {
      $pkgStmt = $conn->prepare("SELECT visa_types_json, applicant_status_options FROM visa_packages WHERE id = ? LIMIT 1");
      $pkgStmt->bind_param('i', $packageId);
      $pkgStmt->execute();
      $pkgResult = $pkgStmt->get_result()->fetch_assoc();
      $pkgStmt->close();
      if ($pkgResult) {
        // Parse visa_types_json
        if (!empty($pkgResult['visa_types_json'])) {
          $types = json_decode($pkgResult['visa_types_json'], true);
          if (is_array($types)) {
            foreach ($types as $typeObj) {
              if (is_array($typeObj) && isset($typeObj['type'])) {
                $typeName = trim((string) $typeObj['type']);
              } elseif (is_string($typeObj)) {
                $typeName = trim($typeObj);
              } else {
                continue;
              }
              if ($typeName !== '') {
                $visaTypeOptions[$typeName] = true;
              }
            }
          }
        }
        // Parse applicant_status_options
        if (!empty($pkgResult['applicant_status_options'])) {
          $statusOpts = json_decode($pkgResult['applicant_status_options'], true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($statusOpts)) {
            $applicantStatusOptions = $statusOpts;
          }
        }
      }
    }

    $visaTypeOptions = array_keys($visaTypeOptions);
    sort($visaTypeOptions, SORT_NATURAL | SORT_FLAG_CASE);
    $applicantData['visa_type_options'] = $visaTypeOptions;
    $applicantData['applicant_status_options'] = $applicantStatusOptions;
    
  } else {
    throw new Exception('Invalid applicant type.');
  }

  echo json_encode([
    'success' => true,
    'data' => $applicantData
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
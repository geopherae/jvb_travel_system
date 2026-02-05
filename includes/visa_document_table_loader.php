<?php
/**
 * Visa Document Table Data Loader
 * 
 * Loads and prepares all data needed for the visa-document-table.php component.
 * This keeps the component file clean and focused on presentation.
 * 
 * Required Parameters:
 *   $conn - Database connection (from actions/db.php)
 *   $visa_application_id - Optional, the visa app ID (fetched from GET if not provided)
 *   $application_mode - Optional, application mode (fetched from visa app if not provided)
 * 
 * Returns:
 *   All variables needed by the component (see bottom of file)
 */

// ============================================================================
// ACCESS CONTROL & SESSION DETECTION
// ============================================================================

$isAdmin   = isset($_SESSION['admin']['id']);
$isClient  = !$isAdmin && isset($_SESSION['client_id']);

// Accept visa_application_id and application_mode as parameters
$visaAppId = $visa_application_id ?? ($_GET['visa_application_id'] ?? null);
$appMode   = $application_mode ?? ($_GET['application_mode'] ?? null);

if (!$visaAppId) {
  throw new Exception('No visa application specified.');
}

// ============================================================================
// DATABASE QUERIES (EARLY FETCH FOR ACCESS CONTROL)
// ============================================================================

// Fetch visa application early to determine application_mode
$appStmt = $conn->prepare("
  SELECT id, visa_package_id, application_mode, client_id, status
  FROM client_visa_applications
  WHERE id = ?
");
$appStmt->bind_param("i", $visaAppId);
$appStmt->execute();
$visaApp = $appStmt->get_result()->fetch_assoc();
$appStmt->close();

if (!$visaApp) {
  throw new Exception('Visa application not found.');
}

$visaPackageId = $visaApp['visa_package_id'];
$appMode = $appMode ?? $visaApp['application_mode'];
$clientId = $visaApp['client_id'];
$visaApplicationStatus = $visa_application_status ?? $visaApp['status']; // Get status from parameter or database

// Determine client's access type (individual vs group)
// Group access is only enabled if:
// 1. Application mode is 'Group', AND
// 2. User logged in with the group_access_code (indicated by session variable)
$clientAccessType = 'individual';
$currentClientId = null;
$currentCompanionId = null;

if ($isClient) {
  if (!empty($_SESSION['is_companion']) && !empty($_SESSION['companion_id'])) {
    $currentCompanionId = $_SESSION['companion_id'];
    $currentClientId = $_SESSION['client_id'];
  } else {
    $currentClientId = $_SESSION['client_id'];
  }
  
  // Only grant group access if logged in with group_access_code
  if (!empty($_SESSION['group_access_enabled']) && strtolower($appMode) === 'group') {
    $clientAccessType = 'group';
  }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Construct full file path for visa document
 */
function getVisaDocPath($clientId, $visaAppId, $fileName) {
  return '../uploads/visa_docs/client_' . $clientId . '/application_' . $visaAppId . '/' . $fileName;
}

/**
 * Get person (lead or companion) data by key
 */
function getPersonData($personKey, $leadName, $companions) {
  if ($personKey === 'lead') {
    return ['name' => $leadName, 'id' => null];
  }
  foreach ($companions as $comp) {
    if ($comp['id'] == $personKey) {
      return ['name' => $comp['full_name'], 'id' => $comp['id']];
    }
  }
  return ['name' => 'Unknown', 'id' => null];
}

/**
 * Check if requirement should be visible based on applicant status condition
 */
function isRequirementVisible($req, $applicantStatus) {
  $condition = $req['condition'] ?? null;
  if (!$condition) {
    return true;
  }

  $type = strtolower($condition['type'] ?? '');
  $operator = strtolower($condition['operator'] ?? 'equals');
  $value = strtolower($condition['value'] ?? '');
  $status = strtolower((string) $applicantStatus);

  if ($type === 'applicant_status') {
    if ($value === '') {
      return true;
    }
    if ($operator === 'equals') {
      return $status === $value;
    }
    return true;
  }

  return true;
}

/**
 * Merge requirements with their submissions and determine display status
 */
function mergeRequirementsWithSubmissions($requirements, $submissions, $applicantStatus = null, $isViewingOwnDocuments = true, $isGroupView = false) {
  $merged = [];
  foreach ($requirements as $req) {
    if (!isRequirementVisible($req, $applicantStatus)) {
      continue;
    }
    
    $isConfidential = !empty($req['is_confidential']);
    if ($isGroupView && !$isViewingOwnDocuments && $isConfidential) {
      continue;
    }
    
    $reqId = $req['id'] ?? '';
    $submitted = array_filter($submissions, function($sub) use ($reqId) {
      return ($sub['requirement_id'] ?? $sub['requirement_name'] ?? '') === $reqId;
    });
    $submitted = array_shift($submitted);

    $merged[] = [
      'requirement_id' => $reqId,
      'requirement_name' => $req['name'] ?? 'Unknown Requirement',
      'description' => $req['description'] ?? '',
      'required' => $req['required'] ?? true,
      'category' => $req['category'] ?? null,
      'condition' => $req['condition'] ?? null,
      'is_confidential' => $isConfidential,
      'submission' => $submitted,
      'status' => $submitted ? ($submitted['status'] ?? 'Pending') : 'Not Submitted'
    ];
  }
  return $merged;
}

/**
 * Group requirements by category
 */
function groupRequirementsByCategory($requirements) {
  $groups = [
    'primary' => [],
    'secondary' => [],
    'conditional' => [],
    'other' => []
  ];

  foreach ($requirements as $req) {
    $category = strtolower($req['category'] ?? '');
    if (!isset($groups[$category])) {
      $category = 'other';
    }
    $groups[$category][] = $req;
  }

  return $groups;
}

/**
 * Build display sections from grouped requirements
 */
function buildSectionBlocks($grouped, $templates) {
  $sections = [];
  foreach ($templates as $template) {
    $items = $grouped[$template['key']] ?? [];
    if (empty($items)) {
      continue;
    }
    $sections[] = [
      'title' => $template['title'],
      'accent' => $template['accent'],
      'items' => $items,
    ];
  }
  return $sections;
}

// ============================================================================
// DATABASE QUERIES (CONTINUED)
// ============================================================================

// 🆕 Fetch visa requirements from client_visa_requirements table (instead of visa_packages)
// This ensures we use the client/companion-specific copy of requirements
$leadReqStmt = $conn->prepare("
  SELECT visa_type, requirements_json
  FROM client_visa_requirements
  WHERE client_id = ? AND companion_id IS NULL
  LIMIT 1
");
$leadReqStmt->bind_param("i", $clientId);
$leadReqStmt->execute();
$leadReqData = $leadReqStmt->get_result()->fetch_assoc();
$leadReqStmt->close();

$requirementsJson = $leadReqData['requirements_json'] ?? '[]';
$leadVisaType = $leadReqData['visa_type'] ?? '';
$allRequirements = json_decode($requirementsJson, true) ?? [];
$templateRequirements = $allRequirements;

// Fetch visa document submissions
$submissionsStmt = $conn->prepare("
  SELECT id, companion_id, requirement_id, requirement_name, file_name, file_path,
         mime_type, status, uploaded_at, approved_at, approved_by_admin_id, admin_comments
  FROM visa_document_submissions
  WHERE visa_application_id = ?
  ORDER BY companion_id ASC, requirement_id ASC
");
$submissionsStmt->bind_param("i", $visaAppId);
$submissionsStmt->execute();
$submissionsResult = $submissionsStmt->get_result();
$submissions = [];
while ($sub = $submissionsResult->fetch_assoc()) {
  $submissions[] = $sub;
}
$submissionsStmt->close();

// Decode JSON-encoded file_name if needed
foreach ($submissions as &$sub) {
  if (!empty($sub['file_name'])) {
    $decodedName = json_decode($sub['file_name'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_string($decodedName)) {
      $sub['file_name'] = $decodedName;
    }
  }
}
unset($sub);

// Fetch lead client
$leadStmt = $conn->prepare("
  SELECT full_name, visa_lead_applicant_status FROM clients WHERE id = ?
");
$leadStmt->bind_param("i", $clientId);
$leadStmt->execute();
$lead = $leadStmt->get_result()->fetch_assoc();
$leadStmt->close();

$leadName = $lead['full_name'] ?? 'Lead Guest';
$leadApplicantStatus = $lead['visa_lead_applicant_status'] ?? null;

// Fetch companions
$isGroupApplication = false;
$companions = [];

$companionStmt = $conn->prepare("
  SELECT id, full_name, applicant_status, relationship FROM client_visa_companions
  WHERE visa_application_id = ?
  ORDER BY created_at ASC
");
$companionStmt->bind_param("i", $visaAppId);
$companionStmt->execute();
$companionResult = $companionStmt->get_result();
while ($comp = $companionResult->fetch_assoc()) {
  $companions[] = $comp;
  $isGroupApplication = true;
}
$companionStmt->close();

// Group submissions by companion_id
$groupedSubmissions = [];
foreach ($submissions as $sub) {
  $key = $sub['companion_id'] ?? 'lead';
  if (!isset($groupedSubmissions[$key])) {
    $groupedSubmissions[$key] = [];
  }
  $groupedSubmissions[$key][] = $sub;
}

// ============================================================================
// BUILD APPLICANT BUNDLES
// ============================================================================

$sectionTemplates = [
  ['key' => 'primary', 'title' => 'Primary Requirements', 'accent' => 'from-sky-50 to-blue-50 border-sky-100'],
  ['key' => 'secondary', 'title' => 'Secondary Requirements', 'accent' => 'from-emerald-50 to-green-50 border-emerald-100'],
  ['key' => 'conditional', 'title' => 'Conditional Requirements', 'accent' => 'from-purple-50 to-pink-50 border-purple-100'],
  ['key' => 'other', 'title' => 'Other Requirements', 'accent' => 'from-gray-50 to-slate-50 border-gray-100'],
];

$applicantBundles = [];

// Determine if lead guest should be shown
$showLeadGuest = true;
if ($isClient && $clientAccessType === 'individual') {
  $showLeadGuest = empty($currentCompanionId);
}

if ($showLeadGuest) {
  $isViewingOwnDocs = true;
  
  if ($isClient && $clientAccessType === 'group') {
    $isViewingOwnDocs = empty($currentCompanionId);
  }
  
  $leadMerged = mergeRequirementsWithSubmissions(
    $templateRequirements,
    $groupedSubmissions['lead'] ?? [],
    $leadApplicantStatus,
    $isViewingOwnDocs,
    ($clientAccessType === 'group')
  );
  $leadGrouped = groupRequirementsByCategory($leadMerged);
  $applicantBundles[] = [
    'name' => $leadName,
    'label' => 'Lead Guest',
    'relationship' => 'Lead Guest',
    'client_id' => $clientId,
    'companion_id' => null,
    'visa_type' => $leadVisaType,
    'sections' => buildSectionBlocks($leadGrouped, $sectionTemplates),
  ];
}

// Build companion bundles
foreach ($companions as $idx => $companion) {
  $isOwnCompanion = ($currentCompanionId && $currentCompanionId == $companion['id']);
  
  if ($isClient && $clientAccessType === 'individual' && !$isOwnCompanion) {
    continue;
  }
  
  $isViewingOwnDocs = true;
  
  if ($isClient && $clientAccessType === 'group') {
    $isViewingOwnDocs = $isOwnCompanion;
  }
  
  // 🆕 Fetch companion's visa_type and requirements from client_visa_requirements
  $companionReqStmt = $conn->prepare("
    SELECT visa_type, requirements_json
    FROM client_visa_requirements
    WHERE client_id = ? AND companion_id = ?
    LIMIT 1
  ");
  $companionReqStmt->bind_param("ii", $clientId, $companion['id']);
  $companionReqStmt->execute();
  $companionReqData = $companionReqStmt->get_result()->fetch_assoc();
  $companionReqStmt->close();
  
  $companionRequirementsJson = $companionReqData['requirements_json'] ?? '[]';
  $companionVisaType = $companionReqData['visa_type'] ?? '';
  $companionAllRequirements = json_decode($companionRequirementsJson, true) ?? [];
  
  $companionMerged = mergeRequirementsWithSubmissions(
    $companionAllRequirements,
    $groupedSubmissions[$companion['id']] ?? [],
    $companion['applicant_status'] ?? null,
    $isViewingOwnDocs,
    ($clientAccessType === 'group')
  );
  $companionGrouped = groupRequirementsByCategory($companionMerged);
  $applicantBundles[] = [
    'name' => $companion['full_name'] ?? 'Companion',
    'label' => 'Companion ' . ($idx + 1),
    'relationship' => $companion['relationship'] ?? 'Companion',
    'client_id' => $clientId,
    'companion_id' => (int)$companion['id'],
    'visa_type' => $companionVisaType,
    'sections' => buildSectionBlocks($companionGrouped, $sectionTemplates),
  ];
}

// ============================================================================
// FETCH ACTUAL VISA DOCUMENTS (if status is Complete)
// ============================================================================

$actualVisaDocuments = [];
if ($visaApplicationStatus === 'Complete') {
  $actualVisaStmt = $conn->prepare("
    SELECT 
      cavd.id,
      cavd.file_name,
      cavd.file_path,
      cavd.file_size,
      cavd.mime_type,
      cavd.uploaded_at,
      cavd.notes,
      aa.first_name AS uploaded_by_name
    FROM client_actual_visa_documents cavd
    LEFT JOIN admin_accounts aa ON cavd.uploaded_by = aa.id
    WHERE cavd.visa_application_id = ?
    ORDER BY cavd.uploaded_at DESC
  ");
  $actualVisaStmt->bind_param("i", $visaAppId);
  $actualVisaStmt->execute();
  $actualVisaDocuments = $actualVisaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $actualVisaStmt->close();
}

// ============================================================================
// PREPARE COMPONENT DATA
// ============================================================================

$applicantMeta = array_map(function ($bundle) {
  // Only return companion_id if it's actually set and greater than 0
  $companionId = isset($bundle['companion_id']) && $bundle['companion_id'] > 0 ? (int)$bundle['companion_id'] : null;
  
  return [
    'name' => $bundle['name'],
    'relationship' => $bundle['relationship'] ?? '',
    'client_id' => $bundle['client_id'] ?? null,
    'companion_id' => $companionId,
  ];
}, $applicantBundles);

$applicantMetaJson = htmlspecialchars(
  json_encode($applicantMeta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
  ENT_QUOTES,
  'UTF-8'
);

// 🆕 Build applicant-specific requirements for the upload modal
// Extract requirements for each applicant that will be displayed
// This matches the exact logic used to build applicantBundles
$applicantRequirements = [];

// Add lead guest requirements (if shown)
if ($showLeadGuest) {
  $applicantRequirements[] = $allRequirements;
}

// Add companion requirements in the same order as applicant bundles
foreach ($companions as $idx => $companion) {
  $isOwnCompanion = ($currentCompanionId && $currentCompanionId == $companion['id']);
  
  // Skip if client shouldn't see this companion (same logic as applicant bundle building)
  if ($isClient && $clientAccessType === 'individual' && !$isOwnCompanion) {
    continue;
  }
  
  // Fetch companion's requirements
  $companionReqStmt = $conn->prepare("
    SELECT requirements_json
    FROM client_visa_requirements
    WHERE client_id = ? AND companion_id = ?
    LIMIT 1
  ");
  $companionReqStmt->bind_param("ii", $clientId, $companion['id']);
  $companionReqStmt->execute();
  $companionReqData = $companionReqStmt->get_result()->fetch_assoc();
  $companionReqStmt->close();
  
  $companionReqs = json_decode($companionReqData['requirements_json'] ?? '[]', true) ?? [];
  $applicantRequirements[] = $companionReqs;
}

$applicantRequirementsJson = htmlspecialchars(
  json_encode($applicantRequirements, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
  ENT_QUOTES,
  'UTF-8'
);

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';

// Determine access
$isAdmin   = isset($_SESSION['admin']['id']);
$isClient  = !$isAdmin && isset($_SESSION['client_id']);

// Accept visa_application_id and application_mode as parameters (passed from view_client_visa.php)
// Or fetch from GET/SESSION if not provided
$visaAppId = $visa_application_id ?? ($_GET['visa_application_id'] ?? null);
$appMode   = $application_mode ?? ($_GET['application_mode'] ?? null);

if (!$visaAppId) {
  echo '<p class="text-gray-500 text-sm">No visa application specified.</p>';
  return;
}

// Fetch visa application details (applicant_status is stored on clients/companions, not on this table)
$appStmt = $conn->prepare("
  SELECT id, visa_package_id, application_mode, client_id
  FROM client_visa_applications
  WHERE id = ?
");
$appStmt->bind_param("i", $visaAppId);
$appStmt->execute();
$appResult = $appStmt->get_result();
$visaApp = $appResult->fetch_assoc();
$appStmt->close();

if (!$visaApp) {
  echo '<p class="text-gray-500 text-sm">Visa application not found.</p>';
  return;
}

$visaPackageId = $visaApp['visa_package_id'];
$appMode = $appMode ?? $visaApp['application_mode'];
$clientId = $visaApp['client_id'];

// Helper function to construct full file path
function getVisaDocPath($clientId, $visaAppId, $fileName) {
  return '../uploads/visa_docs/client_' . $clientId . '/application_' . $visaAppId . '/' . $fileName;
}

// Fetch visa package and requirements template
$pkgStmt = $conn->prepare("
  SELECT requirements_json, country
  FROM visa_packages
  WHERE id = ?
");
$pkgStmt->bind_param("i", $visaPackageId);
$pkgStmt->execute();
$pkgResult = $pkgStmt->get_result();
$pkg = $pkgResult->fetch_assoc();
$pkgStmt->close();

$requirementsJson = $pkg['requirements_json'] ?? '[]';
$allRequirements = json_decode($requirementsJson, true) ?? [];

// Show all requirements (primary + conditional); filtering by condition can be added later
$templateRequirements = $allRequirements;

// Fetch visa document submissions grouped by companion_id
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

// Fetch client (lead guest) including applicant_status for conditional requirements
$leadStmt = $conn->prepare("
  SELECT full_name, visa_lead_applicant_status FROM clients WHERE id = ?
");
$leadStmt->bind_param("i", $clientId);
$leadStmt->execute();
$leadResult = $leadStmt->get_result();
$lead = $leadResult->fetch_assoc();
$leadStmt->close();
$leadName = $lead['full_name'] ?? 'Lead Guest';
$leadApplicantStatus = $lead['visa_lead_applicant_status'] ?? null;

// Fetch companions if group application
$isGroupApplication = false;
$companions = [];
$companionSubmissions = [];

$companionStmt = $conn->prepare("
  SELECT id, full_name, applicant_status FROM client_visa_companions
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

// Group submissions by companion_id (null = lead, otherwise companion id)
$groupedSubmissions = [];
foreach ($submissions as $sub) {
  $key = $sub['companion_id'] ?? 'lead';
  if (!isset($groupedSubmissions[$key])) {
    $groupedSubmissions[$key] = [];
  }
  $groupedSubmissions[$key][] = $sub;
}

// Helper to get person name and submissions
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

// Merge requirements with submissions for each person
// Evaluate if a requirement should be shown for the given applicant status
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
      return true; // No value to compare; show by default
    }
    if ($operator === 'equals') {
      return $status === $value;
    }
    // Unknown operators default to visible to avoid accidental hiding
    return true;
  }

  // Unknown condition types default to visible
  return true;
}

function mergeRequirementsWithSubmissions($requirements, $submissions, $applicantStatus = null) {
  $merged = [];
  foreach ($requirements as $req) {
    if (!isRequirementVisible($req, $applicantStatus)) {
      continue; // Skip requirements whose conditions are not met
    }
    $reqId = $req['id'] ?? '';
    $submitted = array_filter($submissions, function($sub) use ($reqId) {
      return ($sub['requirement_id'] ?? $sub['requirement_name'] ?? '') === $reqId;
    });
    $submitted = array_shift($submitted); // Get first match

    $merged[] = [
      'requirement_id' => $reqId,
      'requirement_name' => $req['name'] ?? 'Unknown Requirement',
      'description' => $req['description'] ?? '',
      'required' => $req['required'] ?? true,
      'category' => $req['category'] ?? null,
      'condition' => $req['condition'] ?? null,
      'submission' => $submitted,
      'status' => $submitted ? ($submitted['status'] ?? 'Pending') : 'Not Submitted'
    ];
  }
  return $merged;
}

// Group merged requirements by category for clearer visual separation
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
?>

<!-- ✅ Alpine.js & x-cloak -->
<style>[x-cloak] { display: none !important; }</style>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<section 
  x-cloak
  x-data="visaDocumentTable()"
  class="bg-white p-4 sm:p-6 rounded-lg shadow border border-gray-200">

  <!-- ✅ Success Toast -->
  <div x-show="toast.visible" x-transition x-cloak
       class="fixed inset-0 flex items-start justify-center z-50 bg-black bg-opacity-15 px-4"
       role="alert">
    <div class="mt-10 bg-green-100 border border-green-400 text-green-700 px-4 sm:px-6 py-3 sm:py-4 rounded shadow-lg max-w-md w-full">
      <strong class="font-bold">Success!</strong>
      <p class="block mt-2 text-sm" x-text="toast.message"></p>
    </div>
  </div>

  <!-- 📄 Header -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 mb-4 sm:mb-6">
    <h3 class="text-base sm:text-lg font-semibold text-gray-800 tracking-tight flex items-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      Visa Documents
    </h3>
    <button @click="openUpload('', '')"
            class="w-full sm:w-auto bg-sky-500 text-white px-4 py-2 rounded hover:bg-sky-600 active:bg-sky-700 transition text-sm font-medium touch-manipulation">
      Upload Document
    </button>
  </div>

  <!-- 📋 Table Wrapper -->
  <div id="visa-documents-content" class="bg-white rounded-lg overflow-hidden">

    <!-- Content -->
    <div class="space-y-4" x-data="{ 
      openApplicant: '<?= $isGroupApplication ? 'lead' : 'solo' ?>', 
      openRequirements: {} 
    }">

      <?php if ($isGroupApplication): ?>
        <!-- GROUP APPLICATION LAYOUT -->
        
        <!-- LEAD GUEST SECTION -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <!-- Lead Guest Header (Collapsible) -->
          <button @click="openApplicant = openApplicant === 'lead' ? '' : 'lead'"
                  class="w-full px-4 py-3 bg-gradient-to-r from-sky-50 to-blue-50 hover:from-sky-100 hover:to-blue-100 transition-colors flex items-center justify-between border-b border-gray-200">
            <div class="flex items-center gap-3">
              <div class="text-left">
                <p class="font-bold font-mono text-xs text-sky-600 uppercase tracking-wide">Lead Guest</p>
                <p class="font-semibold text-gray-900"><?= htmlspecialchars($leadName) ?></p>
              </div>
            </div>
                          <svg class="w-5 h-5 text-sky-600 transition-transform" 
                   :class="openApplicant === 'lead' ? 'rotate-90' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
          </button>

<!-- Lead Guest Requirements -->
<div x-show="openApplicant === 'lead'" x-transition class="divide-y divide-gray-200 bg-white rounded-lg overflow-hidden">
  <?php 
    $leadMerged = mergeRequirementsWithSubmissions(
      $templateRequirements,
      $groupedSubmissions['lead'] ?? [],
      $leadApplicantStatus
    );
    $leadGrouped = groupRequirementsByCategory($leadMerged);
    $leadSections = [
      ['key' => 'primary', 'title' => 'Primary Requirements', 'accent' => 'from-sky-50 to-blue-50 border-sky-100'],
      ['key' => 'secondary', 'title' => 'Secondary Requirements', 'accent' => 'from-amber-50 to-yellow-50 border-amber-100'],
      ['key' => 'conditional', 'title' => 'Conditional Requirements', 'accent' => 'from-purple-50 to-pink-50 border-purple-100'],
      ['key' => 'other', 'title' => 'Other Requirements', 'accent' => 'from-gray-50 to-slate-50 border-gray-100'],
    ];
    foreach ($leadSections as $section):
      $items = $leadGrouped[$section['key']] ?? [];
      if (!$items) continue;
  ?>
    <div class="border-y border-gray-200">
      <div class="px-4 py-2 bg-gradient-to-r <?= $section['accent']; ?> text-xs font-semibold uppercase tracking-wide text-gray-700 flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-sky-500"></span>
        <?= htmlspecialchars($section['title']); ?>
      </div>
      <?php foreach ($items as $item): ?>
        <?php $isRejected = ($item['submission']['status'] ?? '') === 'Rejected'; ?>
        <div class="p-6 transition-colors duration-150 ease-in-out <?= $isRejected ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-sky-50' ?>">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-start justify-between gap-3">
                <div class="pr-2">
                  <h3 class="text-base font-semibold text-sky-800"><?= htmlspecialchars($item['requirement_name']) ?></h3>
                  <p class="italic text-xs text-gray-600 mt-2"><?= htmlspecialchars($item['description']) ?></p>
                  <?php if (($item['category'] ?? '') === 'conditional' && isset($item['condition']['value'])): ?>
                    <p class="text-[11px] text-purple-700 font-medium mt-1">Condition: <?= htmlspecialchars($item['condition']['type'] ?? 'applicant_status') ?> <?= htmlspecialchars($item['condition']['operator'] ?? '=') ?> <?= htmlspecialchars($item['condition']['value']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($item['submission']): ?>
                <p class="text-xs text-gray-500 mt-2">Uploaded at: <?= date("M j, Y", strtotime($item['submission']['uploaded_at'])) ?></p>
              <?php endif; ?>
            </div>

            <div class="flex flex-col items-end gap-4 md:w-56">
              <span class="px-2 md:px-3 py-1 text-xs font-semibold rounded-full <?= getStatusClass($item['status']); ?>">
                <?= htmlspecialchars($item['status']) ?>
              </span>
              <div class="flex items-center gap-2">
                <?php if ($item['submission']): ?>
                  <button @click="openViewer(
                    '<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>',
                    '<?= htmlspecialchars($item['submission']['file_name']) ?>',
                    '<?= htmlspecialchars($item['requirement_name']) ?>',
                    '<?= htmlspecialchars($item['submission']['mime_type']) ?>',
                    '<?= htmlspecialchars($item['submission']['status']) ?>',
                    '<?= htmlspecialchars($item['submission']['admin_comments'] ?? '') ?>'
                  )" class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="View Document">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <a href="<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>" download
                     class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-emerald-600 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-200" title="Download Document">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </a>
                <?php else: ?>
                  <button @click="openUpload('<?= htmlspecialchars($item['requirement_id']) ?>', '<?= htmlspecialchars($item['requirement_name']) ?>')"
                          class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="Upload Document">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

        <!-- COMPANIONS SECTIONS -->
        <?php foreach ($companions as $idx => $companion): ?>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <!-- Companion Header (Collapsible) -->
          <button @click="openApplicant = openApplicant === 'companion_<?= $idx ?>' ? '' : 'companion_<?= $idx ?>'"
                  class="w-full px-4 py-3 bg-gradient-to-r from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 transition-colors flex items-center justify-between border-b border-gray-200">
            <div class="flex items-center gap-3">

              <div class="text-left">
                <p class="font-bold font-mono text-xs text-purple-800/70 uppercase tracking-wide">Companion <?= $idx + 1 ?></p>
                <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($companion['full_name']) ?></p>
              </div>
            </div>
            <svg class="w-5 h-5 text-purple-600 transition-transform" 
                   :class="openApplicant === 'companion_<?= $idx ?>' ? 'rotate-90' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
          </button>

          <!-- Companion Requirements -->
          <div x-show="openApplicant === 'companion_<?= $idx ?>'" x-transition class="divide-y divide-gray-200">
            <?php 
              $companionMerged = mergeRequirementsWithSubmissions(
                $templateRequirements,
                $groupedSubmissions[$companion['id']] ?? [],
                $companion['applicant_status'] ?? null
              );
              $companionGrouped = groupRequirementsByCategory($companionMerged);
              $companionSections = [
                ['key' => 'primary', 'title' => 'Primary Requirements', 'accent' => 'from-sky-50 to-blue-50 border-sky-100'],
                ['key' => 'secondary', 'title' => 'Secondary Requirements', 'accent' => 'from-amber-50 to-yellow-50 border-amber-100'],
                ['key' => 'conditional', 'title' => 'Conditional Requirements', 'accent' => 'from-purple-50 to-pink-50 border-purple-100'],
                ['key' => 'other', 'title' => 'Other Requirements', 'accent' => 'from-gray-50 to-slate-50 border-gray-100'],
              ];
              foreach ($companionSections as $section):
                $items = $companionGrouped[$section['key']] ?? [];
                if (!$items) continue;
            ?>
              <div class="border-y border-gray-200">
                <div class="px-4 py-2 bg-gradient-to-r <?= $section['accent']; ?> text-xs font-semibold uppercase tracking-wide text-gray-700 flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-sky-500"></span>
                  <?= htmlspecialchars($section['title']); ?>
                </div>
                <?php foreach ($items as $item): ?>
                  <?php $isRejected = ($item['submission']['status'] ?? '') === 'Rejected'; ?>
                  <div class="p-6 transition-colors duration-150 ease-in-out <?= $isRejected ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-sky-50' ?>">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                      <div class="flex-1">
                        <div class="flex items-start justify-between gap-3">
                          <div class="pr-2">
                            <h3 class="text-base font-semibold text-sky-800"><?= htmlspecialchars($item['requirement_name']) ?></h3>
                            <p class="italic text-xs text-gray-600 mt-2"><?= htmlspecialchars($item['description']) ?></p>
                            <?php if (($item['category'] ?? '') === 'conditional' && isset($item['condition']['value'])): ?>
                              <p class="text-[11px] text-purple-700 font-medium mt-1">Condition: <?= htmlspecialchars($item['condition']['type'] ?? 'applicant_status') ?> <?= htmlspecialchars($item['condition']['operator'] ?? '=') ?> <?= htmlspecialchars($item['condition']['value']) ?></p>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php if ($item['submission']): ?>
                          <p class="text-xs text-gray-500 mt-2"><?= date("M j, Y", strtotime($item['submission']['uploaded_at'])) ?></p>
                        <?php endif; ?>
                      </div>

                      <div class="flex flex-col items-end gap-4 md:w-56">
                        <span class="px-2 md:px-3 py-1 text-xs font-semibold rounded-full <?= getStatusClass($item['status']); ?>">
                          <?= htmlspecialchars($item['status']) ?>
                        </span>
                        <div class="flex items-center gap-2">
                          <?php if ($item['submission']): ?>
                            <button @click="openViewer(
                              '<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>',
                              '<?= htmlspecialchars($item['submission']['file_name']) ?>',
                              '<?= htmlspecialchars($item['requirement_name']) ?>',
                              '<?= htmlspecialchars($item['submission']['mime_type']) ?>',
                              '<?= htmlspecialchars($item['submission']['status']) ?>',
                              '<?= htmlspecialchars($item['submission']['admin_comments'] ?? '') ?>'
                            )" class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="View">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                            </button>
                            <a href="<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>" download
                               class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-emerald-600 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-200" title="Download">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                              </svg>
                            </a>
                          <?php else: ?>
                            <button @click="openUpload('<?= htmlspecialchars($item['requirement_id']) ?>', '<?= htmlspecialchars($item['requirement_name']) ?>')"
                                    class="bg-white inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="Upload Document">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                              </svg>
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

      <?php else: ?>
        <!-- INDIVIDUAL APPLICATION LAYOUT -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <!-- Applicant Header -->
          <div class="px-4 py-3 bg-gradient-to-r from-sky-50 to-blue-50 border-b border-gray-200">
            <p class="font-semibold text-gray-900">👤 Applicant</p>
            <p class="text-sm text-sky-600"><?= htmlspecialchars($leadName) ?></p>
          </div>

          <!-- Requirements -->
          <div class="divide-y divide-gray-200">
            <?php 
              $leadMerged = mergeRequirementsWithSubmissions(
                $templateRequirements,
                $groupedSubmissions['lead'] ?? [],
                $leadApplicantStatus
              );
              $leadGrouped = groupRequirementsByCategory($leadMerged);
              $leadSections = [
                ['key' => 'primary', 'title' => 'Primary Requirements', 'accent' => 'from-sky-50 to-blue-50 border-sky-100'],
                ['key' => 'secondary', 'title' => 'Secondary Requirements', 'accent' => 'from-amber-50 to-yellow-50 border-amber-100'],
                ['key' => 'conditional', 'title' => 'Conditional Requirements', 'accent' => 'from-purple-50 to-pink-50 border-purple-100'],
                ['key' => 'other', 'title' => 'Other Requirements', 'accent' => 'from-gray-50 to-slate-50 border-gray-100'],
              ];
              foreach ($leadSections as $section):
                $items = $leadGrouped[$section['key']] ?? [];
                if (!$items) continue;
            ?>
              <div class="border-y border-gray-200">
                <div class="px-4 py-2 bg-gradient-to-r <?= $section['accent']; ?> text-xs font-semibold uppercase tracking-wide text-gray-700 flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-sky-500"></span>
                  <?= htmlspecialchars($section['title']); ?>
                </div>
                <?php foreach ($items as $item): ?>
                  <?php $isRejected = ($item['submission']['status'] ?? '') === 'Rejected'; ?>
                  <div class="p-6 transition-colors <?= $isRejected ? 'bg-red-100 hover:bg-red-100' : 'hover:bg-sky-50' ?>">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                      <div class="flex-1">
                        <div class="flex items-start justify-between gap-3">
                          <div class="pr-2">
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($item['requirement_name']) ?></p>
                            <p class="italic text-sm text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                            <?php if (($item['category'] ?? '') === 'conditional' && isset($item['condition']['value'])): ?>
                              <p class="text-[11px] text-purple-700 font-medium mt-1">Condition: <?= htmlspecialchars($item['condition']['type'] ?? 'applicant_status') ?> <?= htmlspecialchars($item['condition']['operator'] ?? '=') ?> <?= htmlspecialchars($item['condition']['value']) ?></p>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php if ($item['submission']): ?>
                          <p class="text-xs text-gray-500 mt-2"><?= date("M j, Y", strtotime($item['submission']['uploaded_at'])) ?></p>
                        <?php endif; ?>
                      </div>

                      <div class="flex flex-col items-end gap-4 md:w-56">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= getStatusClass($item['status']); ?>">
                          <?= htmlspecialchars($item['status']) ?>
                        </span>
                        <div class="flex items-center gap-2">
                          <?php if ($item['submission']): ?>
                            <button @click="openViewer(
                              '<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>',
                              '<?= htmlspecialchars($item['submission']['file_name']) ?>',
                              '<?= htmlspecialchars($item['requirement_name']) ?>',
                              '<?= htmlspecialchars($item['submission']['mime_type']) ?>',
                              '<?= htmlspecialchars($item['submission']['status']) ?>',
                              '<?= htmlspecialchars($item['submission']['admin_comments'] ?? '') ?>'
                            )" class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="View">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                            </button>
                            <a href="<?= htmlspecialchars(getVisaDocPath($clientId, $visaAppId, $item['submission']['file_path'])) ?>" download
                               class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-emerald-600 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-200" title="Download">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                              </svg>
                            </a>
                          <?php else: ?>
                            <button @click="openUpload('<?= htmlspecialchars($item['requirement_id']) ?>', '<?= htmlspecialchars($item['requirement_name']) ?>')"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-sky-600 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200" title="Upload Document">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                              </svg>
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- 🔍 File Viewer Modal -->
  <div x-show="modals.viewer"
       x-transition
       x-cloak
       class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4">
    <div class="bg-white w-full max-w-5xl h-[95vh] sm:h-[90vh] rounded-lg shadow-lg flex flex-col sm:flex-row overflow-hidden"
         @keydown.window.escape="closeViewer()"
         @click.outside="closeViewer()">

      <!-- Preview Panel -->
      <div class="w-full sm:w-2/3 bg-gray-100 p-3 sm:p-6 flex items-center justify-center overflow-hidden relative flex-shrink-0 h-1/4 sm:h-full">
        <template x-if="viewer.mimeType === 'application/pdf'">
          <iframe :src="viewer.path"
                  class="w-full h-full border rounded-md"
                  frameborder="0"></iframe>
        </template>

        <template x-if="viewer.mimeType.startsWith('image/')">
          <div class="relative w-full h-full flex items-center justify-center">
            <img :src="viewer.path"
                 :style="`transform: scale(${viewer.zoom})`"
                 class="max-w-full max-h-full object-contain transition-transform duration-200"
                 alt="Preview" />
            <div class="absolute top-2 right-2 bg-white bg-opacity-90 rounded-lg shadow-lg p-1 flex gap-1">
              <button @click="viewer.zoom = Math.min(viewer.zoom + 0.1, 2)"
                      class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation">➕</button>
              <button @click="viewer.zoom = Math.max(viewer.zoom - 0.1, 0.5)"
                      class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation">➖</button>
              <button @click="viewer.zoom = 1"
                      class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation">🔄</button>
            </div>
          </div>
        </template>

        <template x-if="!viewer.mimeType.startsWith('image/') && viewer.mimeType !== 'application/pdf'">
          <p class="text-sm text-gray-500 italic">Unsupported preview</p>
        </template>
      </div>

      <!-- Metadata Panel -->
      <div class="w-full sm:w-1/3 bg-white p-4 sm:p-6 flex flex-col justify-between overflow-y-auto flex-1">
        <div>
          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Requirement:</label>
            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['requirement_name'] ?? 'N/A'); ?></p>
          </div>

          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">File Name:</label>
            <p class="text-sm text-gray-700 truncate" x-text="viewer.fileName"></p>
          </div>

          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Status:</label>
            <span class="px-2 py-1 text-xs font-semibold rounded-full inline-block"
                  :class="viewer.status === 'Approved' ? 'bg-green-100 text-green-700' :
                           viewer.status === 'Rejected' ? 'bg-red-100 text-red-700' :
                           'bg-yellow-100 text-yellow-700'"
                  x-text="viewer.status"></span>
          </div>

          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Admin Comments:</label>
            <p class="text-sm text-gray-700 bg-gray-50 p-2 rounded rounded-sm max-h-20 overflow-y-auto"
               x-text="viewer.adminComments || 'No comments'"></p>
          </div>

          <a :href="viewer.path"
             target="_blank"
             class="text-xs text-sky-600 hover:text-sky-700 hover:underline touch-manipulation inline-block mt-2">
            Open Full Screen
          </a>
        </div>

        <div class="flex gap-2 mt-4 border-t pt-4">
          <button @click="closeViewer()"
                  class="flex-1 text-sm text-gray-600 hover:text-gray-800 py-2 touch-manipulation">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- 📤 Upload Modal (Placeholder) -->
  <div x-show="modals.upload" 
       x-transition 
       x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div @click.away="modals.upload = false"
         class="w-full max-w-lg bg-white rounded-xl shadow-xl p-6 space-y-4 relative">
      <button @click="modals.upload = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <h2 class="text-lg font-semibold text-gray-800">Upload Visa Document</h2>
      <p class="text-sm text-gray-600">Upload a file for this requirement.</p>

      <form class="space-y-4" action="../actions/submit_visa_document.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="visa_application_id" value="<?= htmlspecialchars($visaAppId) ?>">

        <div x-data>
          <label class="block text-sm font-medium text-gray-700 mb-1">Requirement *</label>
          <template x-if="selectedRequirementId">
            <div class="flex items-center justify-between gap-2 p-3 bg-sky-50 border border-sky-100 rounded">
              <div>
                <p class="text-sm font-semibold text-gray-900" x-text="selectedRequirementName || 'Selected Requirement'"></p>
                <p class="text-xs text-gray-600">Auto-selected from the card you clicked.</p>
              </div>
              <button type="button" class="text-xs text-sky-600 hover:text-sky-700" @click="resetRequirementSelection()">Change</button>
            </div>
          </template>

          <template x-if="!selectedRequirementId">
            <select name="requirement_id" id="requirement_id" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
              <option value="">Select requirement...</option>
              <?php foreach ($templateRequirements as $req): ?>
              <option value="<?= htmlspecialchars($req['id'] ?? '') ?>">
                <?= htmlspecialchars($req['name'] ?? '') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </template>

          <input type="hidden" name="requirement_id" x-show="selectedRequirementId" :value="selectedRequirementId">
        </div>

        <div>
          <label for="upload_file" class="block text-sm font-medium text-gray-700 mb-1">File *</label>
          <input type="file" name="document_file" id="upload_file" required
                 accept=".pdf,.jpg,.jpeg,.png"
                 class="w-full text-sm">
        </div>

        <div class="flex gap-2">
          <button type="button" @click="modals.upload = false"
                  class="flex-1 text-sm text-gray-600 hover:text-gray-800 py-2">
            Cancel
          </button>
          <button type="submit"
                  class="flex-1 text-sm bg-sky-600 text-white py-2 rounded hover:bg-sky-700">
            Upload
          </button>
        </div>
      </form>
    </div>
  </div>

</section>

<script>
function visaDocumentTable() {
  return {
    modals: {
      viewer: false,
      upload: false
    },
    selectedRequirementId: '',
    selectedRequirementName: '',
    toast: {
      visible: false,
      message: ''
    },
    viewer: {
      path: '',
      fileName: '',
      requirement: '',
      mimeType: '',
      status: '',
      adminComments: '',
      zoom: 1
    },
    openViewer(path, fileName, requirement, mimeType, status, adminComments) {
      this.viewer = { path, fileName, requirement, mimeType, status, adminComments, zoom: 1 };
      this.modals.viewer = true;
    },
    openUpload(reqId, reqName) {
      this.selectedRequirementId = reqId || '';
      this.selectedRequirementName = reqName || '';
      this.modals.upload = true;
    },
    resetRequirementSelection() {
      this.selectedRequirementId = '';
      this.selectedRequirementName = '';
    },
    closeViewer() {
      this.modals.viewer = false;
    }
  }
}
</script>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';

// Load all data for this component
try {
  require_once __DIR__ . '/../includes/visa_document_table_loader.php';
} catch (Exception $e) {
  echo '<p class="text-gray-500 text-sm">' . htmlspecialchars($e->getMessage()) . '</p>';
  return;
}

/**
 * Helper function to escape strings for safe use in Alpine.js expressions
 * Handles apostrophes, quotes, and other special characters
 */
function escapeForAlpine($str) {
    if ($str === null || $str === '') return '';
    $str = (string) $str;
    $str = str_replace('\\', '\\\\', $str);  // Escape backslashes first
    $str = str_replace("'", "\\'", $str);     // Escape single quotes (apostrophes)
    $str = str_replace('"', '\\"', $str);     // Escape double quotes
    $str = str_replace("\n", '\\n', $str);    // Escape newlines
    $str = str_replace("\r", '\\r', $str);    // Escape carriage returns
    return $str;
}
?>

<!-- ✅ Alpine.js & x-cloak -->
<style>[x-cloak] { display: none !important; }</style>

<!-- Load component function FIRST (synchronously, no defer) -->
<script src="../includes/documents_table_scripts.js"></script>

<script>
  console.log('Testing visaDocumentTable function...');
  try {
    const testEl = document.createElement('div');
    testEl.dataset.applicantsMeta = '[]';
    testEl.dataset.applicantRequirements = '[]';
    testEl.dataset.accessType = 'individual';
    testEl.dataset.isClient = '0';
    testEl.dataset.visaApplicationId = '1';
    
    const result = visaDocumentTable(testEl);
    console.log('✓ Function works, returned:', result);
  } catch (error) {
    console.error('✗ Function error:', error);
  }
</script>

<script>
  // Wait for function to be defined before loading Alpine
  if (typeof visaDocumentTable === 'undefined') {
    console.error('visaDocumentTable not loaded!');
  } else {
    console.log('✓ visaDocumentTable loaded successfully');
  }
</script>

<!-- Load Alpine LAST without defer -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Main content section with Alpine data -->
<div x-data="visaDocumentTable($el)" data-applicants-meta="<?= $applicantMetaJson ?>" data-applicant-requirements="<?= $applicantRequirementsJson ?>" data-access-type="<?= htmlspecialchars($clientAccessType) ?>" data-is-client="<?= $isClient ? '1' : '0' ?>" data-visa-application-id="<?= htmlspecialchars($visaAppId) ?>" id="visa-document-table-root">

<section class="bg-white p-4 sm:p-6 rounded-lg shadow border border-gray-200">

  <!-- ✅ Status Complete Message -->
  <?php if ($visaApplicationStatus === 'Complete'): ?>
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1">
          <?php if ($isAdmin): ?>
            <h4 class="text-sm font-semibold text-green-800">All Documents Approved</h4>
            <p class="text-xs text-green-700 mt-1">All required documents have been approved. Upload the client's Visa Documents once it has been released by the embassy.</p>
          <?php else: ?>
            <h4 class="text-sm font-semibold text-green-800">Congratulations on Your Submission!</h4>
            <p class="text-xs text-green-700 mt-1">Thank you for submitting all required documents. Please wait for updates on your visa application status.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- 📄 Header -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 mb-4 sm:mb-6">
    <div class="flex-1">
      <h3 class="text-base sm:text-lg font-semibold text-gray-800 tracking-tight flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Visa Application Documents
      </h3>
      <!-- Show access level indicator for clients -->
      <?php if ($isClient): ?>
        <p class="text-xs text-gray-500 mt-1">
          Access: 
          <span class="font-semibold <?= $clientAccessType === 'group' ? 'text-emerald-600' : 'text-slate-600' ?>">
            <?= $clientAccessType === 'group' ? 'Group View' : 'Your Documents' ?>
          </span>
        </p>
      <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
      <?php if ($visaApplicationStatus === 'Complete'): ?>
        <!-- Show Upload Visa button when Complete -->
        <button @click="openUploadActualVisa()"
                class="w-full sm:w-auto bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-700 active:bg-emerald-800 transition text-sm font-medium touch-manipulation flex items-center justify-center gap-2"
                title="Upload the client's actual visa document">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          Upload Visa
        </button>
      <?php else: ?>
        <!-- Show Add Requirement button when not Complete -->
        <button @click="openAddRequirement()"
                class="w-full sm:w-auto bg-sky-500 text-white px-4 py-2 rounded hover:bg-sky-600 active:bg-sky-700 transition text-sm font-medium touch-manipulation"
                :title="'Add requirement for: ' + (applicantMeta[currentIdx]?.name || 'Unknown')">
          Add Requirement
        </button>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- 📋 Table Wrapper -->
  <div id="visa-documents-content" class="bg-white rounded-lg overflow-hidden">

    <!-- ✅ Actual Visa Documents Section (shown when uploaded) -->
    <?php if (!empty($actualVisaDocuments)): ?>
      <div class="mb-6 border border-emerald-200 rounded-lg overflow-hidden bg-gradient-to-r from-emerald-50 to-green-50">
        <div class="px-4 py-3 bg-gradient-to-r from-emerald-100 to-green-100 border-b border-emerald-200 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
          </svg>
          <span class="text-sm font-semibold uppercase tracking-wide text-emerald-700"><?= $isAdmin ? 'Visa Documents' : 'Your Visa Documents' ?></span>
        </div>
        <div class="divide-y divide-emerald-200">
          <?php foreach ($actualVisaDocuments as $visaDoc): ?>
            <?php 
              $docPath = !empty($visaDoc['file_path']) ? ('../' . ltrim($visaDoc['file_path'], '/')) : '';
              // Escape all dynamic values for Alpine.js
              $escapedDocPath = escapeForAlpine($docPath);
              $escapedFileName = escapeForAlpine($visaDoc['file_name']);
              $escapedMimeType = escapeForAlpine($visaDoc['mime_type']);
              $escapedNotes = escapeForAlpine($visaDoc['notes'] ?? '');
              $escapedUploadedAt = escapeForAlpine($visaDoc['uploaded_at']);
              $escapedUploadedBy = escapeForAlpine($visaDoc['uploaded_by_name'] ?? '');
              $escapedId = escapeForAlpine($visaDoc['id']);
            ?>
            <div class="p-4 sm:p-6 bg-white hover:bg-emerald-50 transition-colors duration-150 ease-in-out cursor-pointer"
                 @click="openViewer('<?= $escapedDocPath ?>', '<?= $escapedFileName ?>', 'Visa Document', '<?= $escapedMimeType ?>', 'Approved', '<?= $escapedNotes ?>', '<?= $escapedUploadedAt ?>', '', '<?= $escapedUploadedBy ?>', '<?= $escapedId ?>', 'actual_visa')">
              <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 sm:gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-base font-semibold text-emerald-800"><?= htmlspecialchars($visaDoc['file_name']) ?></h3>
                  </div>
                  <?php if (!empty($visaDoc['notes'])): ?>
                    <p class="text-xs text-gray-600 mt-2 italic"><?= htmlspecialchars($visaDoc['notes']) ?></p>
                  <?php endif; ?>
                  <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-500">
                    <span>Uploaded: <?= date("M j, Y", strtotime($visaDoc['uploaded_at'])) ?></span>
                    <?php if (!empty($visaDoc['uploaded_by_name'])): ?>
                      <span>By: <?= htmlspecialchars($visaDoc['uploaded_by_name']) ?></span>
                    <?php endif; ?>
                    <span>Size: <?= number_format($visaDoc['file_size'] / 1024, 2) ?> KB</span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 border border-emerald-300">
                    ✓ <?= $isAdmin ? 'Visa Uploaded' : 'Received' ?>
                  </span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Content -->
    <div class="space-y-4">
<?php foreach ($applicantBundles as $idx => $applicant): ?>
  <!-- BUNDLE <?= $idx ?> START: <?= htmlspecialchars($applicant['name']) ?> -->
  <div x-show="currentIdx === <?= $idx ?>" class="border border-gray-200 rounded-lg overflow-hidden">
          <!-- Applicant Header -->
          <div class="px-3 sm:px-4 py-3 sm:py-4 bg-gradient-to-r from-sky-50 to-blue-50 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <p class="text-sm sm:text-base tracking-wide text-sky-700 font-semibold">Primary Requirements for:</p>
            <template x-if="applicantMeta.length > 1 || (accessType === 'group' && isClient)">
              <select x-model.number="currentIdx"
                      @change="currentIdx = Number($event.target.value)"
                      class="px-3 sm:px-4 py-2.5 border-2 border-sky-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 text-xs sm:text-sm bg-white text-gray-800 font-medium appearance-none cursor-pointer hover:border-sky-400 transition-all w-full md:w-72"
                      style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%230369a1%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3e%3cpolyline points=%226 9 12 15 18 9%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25em 1.25em; padding-right: 2.75rem;">
                <!-- Lead Guest Section -->
                <optgroup label="Lead Guest">
                  <option value="0" x-text="applicantMeta[0]?.name || 'Lead Applicant'"></option>
                </optgroup>
                <!-- Companions Section -->
                <template x-if="applicantMeta.length > 1">
                  <optgroup label="Companions">
                    <template x-for="(applicant, idx) in applicantMeta.slice(1)" :key="idx + 1">
                      <option :value="idx + 1" x-text="applicant.name"></option>
                    </template>
                  </optgroup>
                </template>
              </select>
            </template>
          </div>

          <!-- Requirements -->
          <div class="divide-y divide-gray-200">
            <?php foreach ($applicant['sections'] as $section): ?>
              <div class="border-y border-gray-200">
                <div class="px-4 py-2 bg-gradient-to-r <?= $section['accent']; ?> text-xs font-semibold uppercase tracking-wide text-gray-700 flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-sky-500"></span>
                  <?= htmlspecialchars($section['title']); ?>
                </div>
                <?php
                // If this is the conditional requirements section, group by applicant label
                if (strtolower($section['title']) === 'conditional requirements') {
                  // Build groups: label => [items]
                  $labelGroups = [];
                  foreach ($section['items'] as $item) {
                    $condType = $item['condition']['type'] ?? '';
                    $condValue = $item['condition']['value'] ?? '';
                    $condLabel = '';
if ($condType === 'sponsor_status') {
  if ($condValue === 'employed') {
    $condLabel = 'Sponsor is an Employee';
  } elseif ($condValue === 'self_employed_business_owner') {
    $condLabel = 'Sponsor is Self-Employed/Business Owner';
  } elseif ($condValue === 'company') {
    $condLabel = 'Sponsor is a Company/Corporation';
  } else {
    $condLabel = 'Sponsor status: ' . ucwords(str_replace('_', ' ', $condValue));
  }
} elseif ($condType === 'applicant_status') {
  $applicantLabels = [
    'employed' => 'Applicant is Employed',
    'self_employed' => 'Applicant is Self-Employed',
    'business_owner' => 'Applicant is a Business Owner',
    'self_employed_business_owner' => 'Applicant is Self-Employed/Business Owner',
    'company' => 'Applicant is a Company/Corporation',
    'student' => 'Applicant is a Student',
    'married' => 'Applicant is Married',
    'widow' => 'Applicant is a Widow/Widower',
    'visit_family_friend' => 'Applicant is Visiting Family/Friend',
    'family_friend' => 'Applicant is Visiting Family/Friend',
    'sponsored' => 'Applicant is Sponsored',
    'unemployed' => 'Applicant is Unemployed',
    'senior_citizen_retired' => 'Applicant is a Senior Citizen',
  ];
  $condLabel = $applicantLabels[$condValue] ?? ('Applicant status: ' . ucwords(str_replace('_', ' ', $condValue)));
} else {
  $condLabel = 'Condition: ' . htmlspecialchars($condType) . ' = ' . htmlspecialchars($condValue);
}
                    $labelGroups[$condLabel][] = $item;
                  }
                  // Output each group
                  foreach ($labelGroups as $groupLabel => $groupItems) {
                    echo '<div class="px-4 py-2 bg-purple-50 border-b border-purple-100 text-xs font-bold text-purple-700/80 flex items-center gap-2 mt-2">';
                    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-purple-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2z" /></svg>';
                    echo htmlspecialchars($groupLabel);
                    echo '</div>';
                    foreach ($groupItems as $item) {
                      ?>
                      <div class="overflow-hidden"
                           <?php if ($isAdmin): ?>@mouseenter="hoverRowId = '<?= htmlspecialchars($item['requirement_id']) ?>'"
                           @mouseleave="hoverRowId = null"<?php endif; ?>>
                        <div class="flex transition-transform duration-200 ease-in-out delay-200" 
                             <?php if ($isAdmin): ?>:style="hoverRowId === '<?= htmlspecialchars($item['requirement_id']) ?>' ? 'transform: translateX(48px)' : 'transform: translateX(0)'"<?php endif; ?>>
                          <?php if ($isAdmin): ?>
                            <div class="absolute left-0 top-0 bottom-0 w-12 cursor-pointer z-10" style="position: absolute; width: 3rem;"></div>
                          <?php endif; ?>
                          <?php if ($isAdmin): ?>
                            <button @click.stop="openDeleteRequirementModal('<?= escapeForAlpine($item['requirement_id']) ?>', '<?= escapeForAlpine($item['requirement_name']) ?>')"
                                    class="-ml-12 flex-shrink-0 flex items-center justify-center w-12 h-auto bg-red-100 hover:bg-red-200 text-red-600 transition-colors"
                                    title="Remove requirement">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                            </button>
                          <?php endif; ?>
                          <div class="p-4 sm:p-6 w-full transition-colors duration-150 ease-in-out cursor-pointer relative <?= ($item['submission']['status'] ?? '') === 'Rejected' ? 'bg-red-50' : 'bg-white' ?>"
                            title="<?= !$item['submission'] ? 'Click to upload requirement' : 'Click to view document' ?>"
                            @click="<?php if ($item['submission']): ?>openViewer('<?= escapeForAlpine($docPath) ?>', '<?= escapeForAlpine($item['submission']['file_name']) ?>', '<?= escapeForAlpine($item['requirement_name']) ?>', '<?= escapeForAlpine($item['submission']['mime_type']) ?>', '<?= escapeForAlpine($item['submission']['status']) ?>', '<?= escapeForAlpine($item['submission']['admin_comments'] ?? '') ?>', '<?= escapeForAlpine($item['submission']['uploaded_at'] ?? '') ?>', '<?= escapeForAlpine($item['submission']['approved_at'] ?? '') ?>', '<?= escapeForAlpine($item['submission']['updated_by'] ?? '') ?>', '<?= escapeForAlpine($item['submission']['id'] ?? $item['submission']['submission_id'] ?? '') ?>')<?php else: ?>openUploadDocument('<?= escapeForAlpine($item['requirement_id']) ?>', '<?= escapeForAlpine($item['requirement_name']) ?>', '<?= escapeForAlpine($item['description'] ?? '') ?>')<?php endif; ?>">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 sm:gap-4">
                              <div class="flex-1">
                                <div class="flex items-start justify-between gap-3">
                                  <div class="pr-2">
                                    <div class="flex items-center gap-2">
                                      <h3 class="text-base font-semibold text-sky-800"><?= htmlspecialchars($item['requirement_name']) ?></h3>
                                      <?php if (!empty($item['is_confidential']) && $isAdmin): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-full bg-purple-100 text-purple-700 border border-purple-200" title="Confidential - Hidden from other group members">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                          </svg>
                                          Confidential
                                        </span>
                                      <?php endif; ?>
                                    </div>
                                    <p class="italic text-xs text-gray-600 mt-2"><?= htmlspecialchars($item['description']) ?></p>
                                    
                                  </div>
                                </div>
                                <?php if ($item['submission']): ?>
                                  <p class="text-xs text-gray-500 mt-2">Uploaded at: <?= date("M j, Y", strtotime($item['submission']['uploaded_at'])) ?></p>
                                <?php endif; ?>
                              </div>
                              <div class="flex flex-col items-end gap-2 sm:gap-4 md:w-56">
                                <span class="px-2 md:px-3 py-1 text-xs font-semibold rounded-full <?= getStatusClass($item['status']); ?>">
                                  <?= htmlspecialchars($item['status']) ?>
                                </span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <?php
                    }
                  }
                  // Skip the default foreach below for this section
                  continue;
                }
                ?>
                <?php foreach ($section['items'] as $item): ?>
                  <?php 
                    $isRejected = ($item['submission']['status'] ?? '') === 'Rejected';
                    
                    // Calculate docPath early for use in click handler
                    $docPath = '';
                    if ($item['submission']) {
                      $storedPath = $item['submission']['file_path'] ?? '';
                      $docPath = $storedPath ? ('../' . ltrim($storedPath, '/')) : getVisaDocPath($clientId, $visaAppId, $item['submission']['file_name']);
                    }
                    
                    // Escape all values for Alpine.js
                    $escapedReqId = escapeForAlpine($item['requirement_id']);
                    $escapedReqName = escapeForAlpine($item['requirement_name']);
                    $escapedReqDesc = escapeForAlpine($item['description'] ?? '');
                    
                    // For submissions
                    if ($item['submission']) {
                      $escapedDocPath = escapeForAlpine($docPath);
                      $escapedFileName = escapeForAlpine($item['submission']['file_name']);
                      $escapedMimeType = escapeForAlpine($item['submission']['mime_type']);
                      $escapedStatus = escapeForAlpine($item['submission']['status']);
                      $escapedComments = escapeForAlpine($item['submission']['admin_comments'] ?? '');
                      $escapedUploadedAt = escapeForAlpine($item['submission']['uploaded_at'] ?? '');
                      $escapedApprovedAt = escapeForAlpine($item['submission']['approved_at'] ?? '');
                      $escapedUpdatedBy = escapeForAlpine($item['submission']['updated_by'] ?? '');
                      $escapedSubmissionId = escapeForAlpine($item['submission']['id'] ?? $item['submission']['submission_id'] ?? '');
                    }
                  ?>
                  <!-- Admin hover-reveal wrapper (slide only on left edge hover) -->
                  <div class="overflow-hidden"
                       <?php if ($isAdmin): ?>@mouseenter="hoverRowId = '<?= htmlspecialchars($item['requirement_id']) ?>'"
                       @mouseleave="hoverRowId = null"<?php endif; ?>>
                    <div class="flex transition-transform duration-200 ease-in-out delay-200" 
                         <?php if ($isAdmin): ?>:style="hoverRowId === '<?= htmlspecialchars($item['requirement_id']) ?>' ? 'transform: translateX(48px)' : 'transform: translateX(0)'"<?php endif; ?>>
                      <!-- Left trigger zone (admin only) -->
                      <?php if ($isAdmin): ?>
                        <div class="absolute left-0 top-0 bottom-0 w-12 cursor-pointer z-10"
                             style="position: absolute; width: 3rem;">
                        </div>
                      <?php endif; ?>

                      <!-- Trash icon (admin only, revealed on left edge hover) -->
                      <?php if ($isAdmin): ?>
                        <button @click.stop="openDeleteRequirementModal('<?= $escapedReqId ?>', '<?= $escapedReqName ?>')"
                                class="-ml-12 flex-shrink-0 flex items-center justify-center w-12 h-auto bg-red-100 hover:bg-red-200 text-red-600 transition-colors"
                                title="Remove requirement">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      <?php endif; ?>
                      
                      <!-- Main content -->
                      <div class="p-4 sm:p-6 w-full transition-colors duration-150 ease-in-out cursor-pointer relative <?= $isRejected ? 'bg-red-50' : 'bg-white' ?>"
                        title="<?= !$item['submission'] ? 'Click to upload requirement' : 'Click to view document' ?>"
                        @click="<?php if ($item['submission']): ?>openViewer('<?= $escapedDocPath ?>', '<?= $escapedFileName ?>', '<?= $escapedReqName ?>', '<?= $escapedMimeType ?>', '<?= $escapedStatus ?>', '<?= $escapedComments ?>', '<?= $escapedUploadedAt ?>', '<?= $escapedApprovedAt ?>', '<?= $escapedUpdatedBy ?>', '<?= $escapedSubmissionId ?>')<?php else: ?>openUploadDocument('<?= $escapedReqId ?>', '<?= $escapedReqName ?>', '<?= $escapedReqDesc ?>')<?php endif; ?>">
                        
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 sm:gap-4">
                          <div class="flex-1">
                            <div class="flex items-start justify-between gap-3">
                              <div class="pr-2">
                                <div class="flex items-center gap-2">
                                  <h3 class="text-base font-semibold text-sky-800"><?= htmlspecialchars($item['requirement_name']) ?></h3>
                                  <?php if (!empty($item['is_confidential']) && $isAdmin): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-full bg-purple-100 text-purple-700 border border-purple-200" title="Confidential - Hidden from other group members">
                                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                      </svg>
                                      Confidential
                                    </span>
                                  <?php endif; ?>
                                </div>
                                <p class="italic text-xs text-gray-600 mt-2"><?= htmlspecialchars($item['description']) ?></p>
                                <?php if (strtolower($item['category'] ?? '') === 'conditional' && isset($item['condition']['value'])): ?>
<?php
$condType = $item['condition']['type'] ?? '';
$condValue = $item['condition']['value'] ?? '';
$condLabel = '';
if ($condType === 'sponsor_status') {
  if ($condValue === 'employed') {
    $condLabel = 'Sponsor is an Employee';
  } elseif ($condValue === 'self_employed_business_owner') {
    $condLabel = 'Sponsor is Self-Employed/Business Owner';
  } elseif ($condValue === 'company') {
    $condLabel = 'Sponsor is a Company/Corporation';
  } else {
    $condLabel = 'Sponsor status: ' . ucwords(str_replace('_', ' ', $condValue));
  }
} elseif ($condType === 'applicant_status') {
  $applicantLabels = [
    'employed' => 'Applicant is Employed',
    'self_employed' => 'Applicant is Self-Employed',
    'business_owner' => 'Applicant is a Business Owner',
    'self_employed_business_owner' => 'Applicant is Self-Employed/Business Owner',
    'company' => 'Applicant is a Company/Corporation',
    'student' => 'Applicant is a Student',
    'married' => 'Applicant is Married',
    'widow' => 'Applicant is a Widow/Widower',
    'visit_family_friend' => 'Applicant is Visiting Family/Friend',
    'family_friend' => 'Applicant is Visiting Family/Friend',
    'sponsored' => 'Applicant is Sponsored',
    'unemployed' => 'Applicant is Unemployed',
    'senior_citizen_retired' => 'Applicant is a Senior Citizen',
  ];
  $condLabel = $applicantLabels[$condValue] ?? ('Applicant status: ' . ucwords(str_replace('_', ' ', $condValue)));
} else {
  $condLabel = 'Condition: ' . htmlspecialchars($condType) . ' = ' . htmlspecialchars($condValue);
}
?>
                                  <p class="text-[11px] text-purple-700 font-medium mt-1">Why shown: <?= htmlspecialchars($condLabel) ?></p>
                                <?php endif; ?>
                              </div>
                            </div>
                            <?php if ($item['submission']): ?>
                              <p class="text-xs text-gray-500 mt-2">Uploaded at: <?= date("M j, Y", strtotime($item['submission']['uploaded_at'])) ?></p>
                            <?php endif; ?>
                          </div>

                          <div class="flex flex-col items-end gap-2 sm:gap-4 md:w-56">
                            <span class="px-2 md:px-3 py-1 text-xs font-semibold rounded-full <?= getStatusClass($item['status']); ?>">
                              <?= htmlspecialchars($item['status']) ?>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>

            <?php if (empty($applicant['sections'])): ?>
              <div class="p-4 text-sm text-gray-500">No requirements to display for this applicant.</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</section>

<!-- ✅ Success/Error Toast -->
<div x-show="toast.visible" x-transition x-cloak
     class="fixed inset-0 flex items-start justify-center z-50 px-4"
     :class="toast.type === 'success' ? 'bg-emerald-900 bg-opacity-10' : 'bg-red-900 bg-opacity-10'"
     role="alert">
  <div class="mt-10 px-4 sm:px-6 py-3 sm:py-4 rounded shadow-lg max-w-md w-full"
       :class="toast.type === 'success' 
         ? 'bg-emerald-100 border border-emerald-300 text-emerald-700' 
         : 'bg-red-100 border border-red-300 text-red-700'">
    <strong class="font-bold" x-text="toast.type === 'success' ? 'Success!' : 'Error'"></strong>
    <p class="block mt-2 text-sm" x-text="toast.message"></p>
  </div>
</div>

<!-- ✅ MODALS - Inside Alpine scope but outside section to fix z-index stacking -->
<?php include __DIR__ . '/visa-file-viewer-modal.php'; ?>
<?php include __DIR__ . '/visa-add-requirement-modal.php'; ?>
<?php include __DIR__ . '/visa-upload-document-modal.php'; ?>
<?php include __DIR__ . '/visa-upload-actual-visa-modal.php'; ?>
<?php include __DIR__ . '/visa-confirmation-modal.php'; ?>

</div><!-- End Alpine wrapper -->
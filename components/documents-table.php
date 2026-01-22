<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../actions/db.php';
$tooltips = require __DIR__ . '/../includes/tooltip_map.php';
require_once __DIR__ . '/../includes/tooltip_render.php';
require_once __DIR__ . '/../includes/empty_state_map.php';

// 🧠 Determine access
$isAdmin   = isset($_SESSION['admin']['id']);
$isClient  = !$isAdmin && isset($_SESSION['client_id']);
$client_id = $isAdmin
  ? ($_GET['client_id'] ?? $_SESSION['client_id'] ?? null)
  : ($_SESSION['client_id'] ?? null);

// 🧾 Upload form target
$formAction = $isAdmin
  ? '../admin/upload_document_admin.php'
  : '../client/upload_document_client.php';

// 🗂️ Fetch client documents
$documents = [];
if ($client_id) {
  $stmt = $conn->prepare("
    SELECT id, file_name, mime_type, file_path, document_type, document_status,
           uploaded_at, approved_at, status_updated_by, admin_comments
    FROM uploaded_files
    WHERE client_id = ?
  ");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($doc = $result->fetch_assoc()) {
    $documents[] = $doc;
  }

  $stmt->close();
}
?>

<!-- ✅ Alpine.js & x-cloak -->
<style>[x-cloak] { display: none !important; }</style>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<section 
  x-cloak
  x-data="documentsTable()" 
  class="bg-white p-4 sm:p-6 rounded-md shadow border border-gray-200">

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
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 2a2 2 0 00-2 2v16a2 2 0 002 2h10a2 2 0 002-2V8l-6-6H7z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M14 2v6h6" />
      </svg>
      Client Documents
    </h3>
    <button @click="modals.upload = true"
            class="w-full sm:w-auto bg-sky-500 text-white px-4 py-2 rounded hover:bg-sky-600 active:bg-sky-700 transition text-sm font-medium touch-manipulation">
      Upload Document
    </button>
  </div>

  <!-- 📋 Table Wrapper -->
  <div id="documents-table-content">
    <div class="bg-white rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <?php if (empty($documents)): ?>
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-500 px-4">
          <?= getEmptyStateSvg('no-documents-found'); ?>
          <p class="text-sm sm:text-md italic font-semibold text-sky-700 mb-1 text-center">No documents found.</p>
          <p class="text-sm sm:text-md text-sky-700 italic text-center">Click the <strong>Upload Document</strong> button to start!</p>
        </div>
        <?php else: ?>
        
        <!-- Mobile Card View (visible on mobile only) -->
        <div class="block sm:hidden space-y-3">
          <?php foreach ($documents as $doc): ?>
          <div class="border border-gray-200 rounded-lg p-4 bg-white hover:bg-sky-50 transition-colors">
            <!-- File Name & Status -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <button @click="openFileModal(
                <?= (int) $doc['id'] ?>,
                '<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>',
                '<?= htmlspecialchars($doc['file_name']) ?>',
                '<?= htmlspecialchars($doc['document_type']) ?>',
                '<?= htmlspecialchars($doc['mime_type']) ?>',
                '<?= htmlspecialchars($doc['document_status']) ?>',
                '<?= htmlspecialchars($doc['admin_comments'] ?? 'No admin comments.') ?>',
                '<?= htmlspecialchars($doc['uploaded_at']) ?>',
                '<?= htmlspecialchars($doc['approved_at']) ?>',
                '<?= htmlspecialchars($doc['status_updated_by']) ?>'
              )"
              class="flex-1 text-left font-medium text-gray-900 hover:text-sky-600 transition min-w-0">
                <p class="truncate text-sm" title="<?= htmlspecialchars($doc['file_name'] ?? 'Unnamed File') ?>">
                  <?= htmlspecialchars($doc['file_name'] ?? 'Unnamed File') ?>
                </p>
              </button>
              <span class="px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap flex-shrink-0 <?= getStatusBadgeClass($doc['document_status'] ?? 'Under Review') ?>">
                <?= htmlspecialchars($doc['document_status'] ?? 'Under Review') ?>
              </span>
            </div>

            <!-- Type & Date -->
            <div class="flex flex-wrap gap-3 text-xs text-gray-600 mb-3">
              <div class="flex items-center gap-1">
                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($doc['document_type'] ?? '-') ?></span>
              </div>
              <div class="flex items-center gap-1">
                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                </svg>
                <span><?= date("M j, Y", strtotime($doc['uploaded_at'] ?? 'now')) ?></span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-4 pt-3 border-t border-gray-100">
              <button @click="openFileModal(
                <?= (int) $doc['id'] ?>,
                '<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>',
                '<?= htmlspecialchars($doc['file_name']) ?>',
                '<?= htmlspecialchars($doc['document_type']) ?>',
                '<?= htmlspecialchars($doc['mime_type']) ?>',
                '<?= htmlspecialchars($doc['document_status']) ?>',
                '<?= htmlspecialchars($doc['admin_comments'] ?? 'No admin comments.') ?>',
                '<?= htmlspecialchars($doc['uploaded_at']) ?>',
                '<?= htmlspecialchars($doc['approved_at']) ?>',
                '<?= htmlspecialchars($doc['status_updated_by']) ?>'
              )"
              class="text-sky-600 hover:text-sky-700 font-medium inline-flex items-center gap-1 text-sm touch-manipulation">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                View
              </button>

              <a href="<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>"
                 download="<?= htmlspecialchars($doc['file_name']) ?>"
                 class="text-emerald-500 hover:text-emerald-600 font-medium inline-flex items-center gap-1 text-sm touch-manipulation"
                 title="Download">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download
              </a>

              <button @click="printDocument('<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>')"
                      class="text-blue-500 hover:text-blue-600 font-medium inline-flex items-center gap-1 text-sm touch-manipulation"
                      title="Print">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Desktop Table View (hidden on mobile) -->
        <div class="hidden sm:block rounded-lg border overflow-hidden overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="bg-blue-50 text-gray-500 font-medium">
              <tr>
                <th class="p-3 text-left">File</th>
                <th class="p-3 text-left hidden md:table-cell">Type</th>
                <th class="p-3 text-left hidden lg:table-cell">Date Uploaded</th>
                <th class="p-3 text-left">Status</th>
                <th class="p-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php foreach ($documents as $doc): ?>
              <tr class="text-gray-700 hover:text-sky-600 transition-colors odd:bg-white even:bg-sky-50">

                <!-- 📁 File Name -->
                <td class="p-3 sm:p-4 text-left">
                  <button @click="openFileModal(
                    <?= (int) $doc['id'] ?>,
                    '<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>',
                    '<?= htmlspecialchars($doc['file_name']) ?>',
                    '<?= htmlspecialchars($doc['document_type']) ?>',
                    '<?= htmlspecialchars($doc['mime_type']) ?>',
                    '<?= htmlspecialchars($doc['document_status']) ?>',
                    '<?= htmlspecialchars($doc['admin_comments'] ?? 'No admin comments.') ?>',
                    '<?= htmlspecialchars($doc['uploaded_at']) ?>',
                    '<?= htmlspecialchars($doc['approved_at']) ?>',
                    '<?= htmlspecialchars($doc['status_updated_by']) ?>'
                  )"
                  class="hover:underline font-medium truncate max-w-[175px] inline-block"
                  title="<?= htmlspecialchars($doc['file_name'] ?? 'Unnamed File') ?>">
                    <?= htmlspecialchars($doc['file_name'] ?? 'Unnamed File') ?>
                  </button>
                </td>

                <!-- 📑 Type (Hidden on Tablet and below) -->
                <td class="p-3 sm:p-4 text-left hidden md:table-cell">
                  <?= htmlspecialchars($doc['document_type'] ?? '-') ?>
                </td>

                <!-- 📅 Date (Hidden on Tablet and below) -->
                <td class="p-3 sm:p-4 text-left hidden lg:table-cell">
                  <?= date("F j, Y", strtotime($doc['uploaded_at'] ?? 'now')) ?>
                </td>

                <!-- 🏷️ Status Badge -->
                <td class="p-3 sm:p-4 text-left">
                  <span class="px-3 py-1 text-xs font-semibold rounded-full <?= getStatusBadgeClass($doc['document_status'] ?? 'Under Review') ?>">
                    <?= htmlspecialchars($doc['document_status'] ?? 'Under Review') ?>
                  </span>
                </td>

                <!-- ⚙️ Actions -->
                <td class="p-3 sm:p-4 text-center space-x-1 sm:space-x-2">
                  <button @click="openFileModal(
                    <?= (int) $doc['id'] ?>,
                    '<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>',
                    '<?= htmlspecialchars($doc['file_name']) ?>',
                    '<?= htmlspecialchars($doc['document_type']) ?>',
                    '<?= htmlspecialchars($doc['mime_type']) ?>',
                    '<?= htmlspecialchars($doc['document_status']) ?>',
                    '<?= htmlspecialchars($doc['admin_comments'] ?? 'No admin comments.') ?>',
                    '<?= htmlspecialchars($doc['uploaded_at']) ?>',
                    '<?= htmlspecialchars($doc['approved_at']) ?>',
                    '<?= htmlspecialchars($doc['status_updated_by']) ?>'
                  )"
                  class="text-sky-600 hover:underline font-medium inline-flex items-center gap-1"
                  title="View">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>

                  <!-- Download Button -->
                  <a href="<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>"
                     download="<?= htmlspecialchars($doc['file_name']) ?>"
                     class="text-emerald-500 hover:underline font-medium inline-flex items-center gap-1"
                     title="Download">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </a>

                  <!-- Print Button -->
                  <button @click="printDocument('<?= htmlspecialchars("../" . implode('/', array_map('rawurlencode', explode('/', $doc['file_path'])))) ?>')"
                          class="text-blue-500 hover:underline font-medium inline-flex items-center gap-1"
                          title="Print">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

<!-- 📄 File Viewer Modal -->
<div x-show="modals.viewer"
     x-transition
     x-cloak
     class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4">
  <div class="bg-white w-full max-w-5xl h-[95vh] sm:h-[90vh] rounded-lg shadow-lg flex flex-col sm:flex-row overflow-hidden"
       @keydown.window.escape="closeFileModal()"
       @click.outside="closeFileModal()">

    <!-- 🖼️ Top/Left Panel: File Preview -->
    <div class="w-full sm:w-2/3 bg-gray-100 p-3 sm:p-6 flex items-center justify-center overflow-hidden relative flex-shrink-0 h-1/4 sm:h-full">

      <!-- PDF Viewer -->
      <template x-if="fileViewer.mimeType === 'application/pdf'">
        <iframe :src="fileViewer.path"
                class="w-full h-full border rounded-md"
                frameborder="0"></iframe>
      </template>

      <!-- Image Viewer with Zoom -->
      <template x-if="fileViewer.mimeType.startsWith('image/')">
        <div class="relative w-full h-full flex items-center justify-center">
          <img :src="fileViewer.path"
               :style="`transform: scale(${fileViewer.zoom})`"
               class="max-w-full max-h-full object-contain transition-transform duration-200"
               alt="Preview" />
          <div class="absolute top-2 right-2 bg-white bg-opacity-90 rounded-lg shadow-lg p-1 flex gap-1">
            <button @click="fileViewer.zoom = Math.min(fileViewer.zoom + 0.1, 2)"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Zoom In">➕</button>
            <button @click="fileViewer.zoom = Math.max(fileViewer.zoom - 0.1, 0.5)"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Zoom Out">➖</button>
            <button @click="fileViewer.zoom = 1"
                    class="text-xs px-2 py-1 hover:bg-gray-100 rounded touch-manipulation" title="Reset Zoom">🔄</button>
          </div>
        </div>
      </template>

      <!-- Unsupported Format -->
      <template x-if="!fileViewer.mimeType.startsWith('image/') && fileViewer.mimeType !== 'application/pdf'">
        <p class="text-sm text-gray-500 italic">Unsupported file type preview</p>
      </template>
    </div>

    <!-- 🧾 Bottom/Right Panel: Metadata -->
    <div class="w-full sm:w-1/3 bg-white p-4 sm:p-6 flex flex-col justify-between overflow-y-auto flex-1">
      <div>
        <!-- File Name -->
        <label class="block text-sm font-medium text-gray-700 mb-1">File Name:</label>
        <input x-model="fileViewer.name"
               maxlength="100"
               class="w-full text-sm border border-gray-300 rounded px-3 py-2 truncate focus:ring-sky-500 focus:border-sky-500"
               placeholder="Document Name" />

        <!-- Document Type -->
        <div class="mt-3 sm:mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Document Type:</label>
          <select x-model="fileViewer.type"
                  <?= $isClient ? 'disabled' : '' ?>
                  class="w-full text-sm border rounded px-3 py-2 <?= $isClient ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '' ?>">
            <option>Passport</option>
            <option>Valid ID</option>
            <option>Visa</option>
            <option>Service Voucher</option>
            <option>Airline Ticket</option>
            <option>PH Travel Tax</option>
            <option>Acknowledgement Receipt</option>
            <option>Other</option>
          </select>
        </div>

        <!-- Status -->
        <div class="mt-3 sm:mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
          <select x-model="fileViewer.status"
                  <?= $isClient ? 'disabled' : '' ?>
                  class="w-full text-sm border rounded px-3 py-2 <?= $isClient ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '' ?>">
            <option>Pending</option>
            <option>Approved</option>
            <option>Rejected</option>
          </select>
        </div>

        <!-- Admin Comments -->
        <div class="mt-3 sm:mt-4 relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">Admin Comments:</label>
          <textarea
              x-model="fileViewer.adminComments"
              :class="[
                'w-full h-24 sm:h-28 text-sm border rounded px-3 py-2 resize-none overflow-y-auto focus:ring-sky-500 focus:border-sky-500',
                (fileViewer.status === 'Rejected' && !fileViewer.adminComments.trim()) ? 'border-red-500 ring-2 ring-red-300 animate-pulse' : 'border-gray-300'
              ]"
              placeholder="No admin comments."
              <?= $isClient ? 'readonly disabled' : '' ?>
          ></textarea>
          <template x-if="fileViewer.status === 'Rejected' && !fileViewer.adminComments.trim()">
            <div class="absolute left-0 mt-1 text-xs text-red-600 bg-white bg-opacity-90 px-2 py-1 rounded shadow pointer-events-none z-10">
              Please enter rejection reason
            </div>
          </template>
        </div>

        <a :href="fileViewer.path"
           target="_blank"
           class="mt-3 inline-block text-sm text-sky-600 hover:text-sky-700 hover:underline touch-manipulation">
          Open in Full Screen
        </a>
      </div>

      <!-- 🗑️ Delete File Button -->
      <div class="mt-4">
        <button @click="deleteFile"
                class="text-sm text-red-600 hover:text-red-700 hover:underline touch-manipulation"
                :disabled="deleteFileLoading">
          <template x-if="deleteFileLoading">
            <span>Deleting...</span>
          </template>
          <template x-if="!deleteFileLoading">
            <span>Delete File</span>
          </template>
        </button>
      </div>

      <!-- 🕓 Metadata Footer -->
      <div class="text-xs text-gray-400 mt-4 sm:mt-6 border-t pt-3 sm:pt-4 space-y-1">
        <p class="break-words">
          <span class="font-medium">Uploaded:</span>
          <span x-text="fileViewer.uploadedAt 
            ? new Date(fileViewer.uploadedAt).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) 
            : '—'"></span>
        </p>
        <p class="break-words">
          <span class="font-medium">Approved:</span>
          <span x-text="fileViewer.approvedAt 
            ? new Date(fileViewer.approvedAt).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) 
            : 'Not yet approved'"></span>
        </p>
        <p class="break-words">
          <span class="font-medium">Updated By:</span>
          <span x-text="fileViewer.updatedBy || '—'"></span>
        </p>
      </div>

      <!-- 🧮 Action Buttons -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-2 sm:gap-0">
        <button @click="closeFileModal()"
                class="text-sm text-gray-600 hover:text-gray-800 hover:underline py-2 sm:py-0 touch-manipulation order-2 sm:order-1">
          Close
        </button>

        <button @click="submitDocumentChanges()"
                :disabled="submitDocumentChangesLoading || 
                  (fileViewer.status === 'Rejected' && !fileViewer.adminComments.trim())"
                class="text-sm px-4 py-2 rounded transition font-semibold touch-manipulation order-1 sm:order-2"
                :class="submitDocumentChangesLoading || 
                  (fileViewer.status === 'Rejected' && !fileViewer.adminComments.trim())
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-sky-600 text-white hover:bg-sky-700 active:bg-sky-800'">
          <template x-if="submitDocumentChangesLoading">
            <span>Saving...</span>
          </template>
          <template x-if="!submitDocumentChangesLoading">
            <span>Save Changes</span>
          </template>
        </button>
      </div>
    </div>
  </div>
</div>


<div x-show="confirmAction.visible" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4">
  <div @click.away="confirmAction.visible = false"
       class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden transition-all transform"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    <!-- Colored Header Bar -->
    <div class="h-2 bg-gradient-to-r"
         :class="confirmAction.type === 'approve' ? 'from-emerald-400 to-emerald-600' 
                 : confirmAction.type === 'reject' ? 'from-red-400 to-red-600'
                 : 'from-amber-400 to-amber-600'">
    </div>

    <!-- Content Container -->
    <div class="px-6 py-6 sm:px-8 sm:py-8 space-y-6 relative">
      
      <!-- Close Button -->
      <button @click="confirmAction.visible = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full p-1 transition-all duration-200"
              aria-label="Close modal">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Icon & Header -->
      <div class="flex items-start gap-4">
        <!-- Dynamic Icon -->
        <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
             :class="confirmAction.type === 'approve' ? 'bg-emerald-100' 
                     : confirmAction.type === 'reject' ? 'bg-red-100'
                     : 'bg-amber-100'">
          <!-- Approve Icon -->
          <template x-if="confirmAction.type === 'approve'">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </template>
          <!-- Reject Icon -->
          <template x-if="confirmAction.type === 'reject'">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </template>
          <!-- Delete Icon -->
          <template x-if="confirmAction.type === 'delete'">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </template>
        </div>

        <!-- Text Content -->
        <div class="flex-1 pt-1">
          <h2 class="text-xl font-bold text-gray-900 mb-1"
              x-text="confirmAction.type === 'approve' ? 'Approve Document' 
                      : confirmAction.type === 'reject' ? 'Reject Document'
                      : 'Delete Document'">
            Confirm Action
          </h2>
          <p class="text-sm text-gray-600 leading-relaxed"
             x-text="confirmAction.type === 'approve' 
               ? 'This will mark the document as approved and notify the client.' 
               : confirmAction.type === 'reject'
               ? 'Please provide a reason for rejecting this document.'
               : 'This action cannot be undone. The file will be permanently removed.'">
            Are you sure you want to perform this action?
          </p>
        </div>
      </div>

      <!-- Rejection Reason Input -->
      <template x-if="confirmAction.type === 'reject'">
        <div class="pt-2">
          <label for="rejectionReason" class="block text-sm font-semibold text-gray-700 mb-2">
            Rejection Reason <span class="text-red-500">*</span>
          </label>
          <textarea id="rejectionReason" x-model="confirmAction.reason"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 px-4 py-3 text-sm text-gray-700 transition-all resize-none"
                    rows="3"
                    placeholder="Explain why this document is being rejected..."></textarea>
          <p class="text-xs text-gray-500 mt-1.5 flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            This message will be visible to the client
          </p>
        </div>
      </template>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-4">
        <button @click="confirmAction.visible = false"
                class="flex-1 bg-white border-2 border-gray-300 hover:border-gray-400 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 shadow-sm">
          Cancel
        </button>
        <button @click="confirmAndOpenModal()"
                :disabled="confirmAction.type === 'reject' && !confirmAction.reason.trim()"
                class="flex-1 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all duration-200 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                :class="confirmAction.type === 'approve' 
                        ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 hover:shadow-lg' 
                        : 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 hover:shadow-lg'">
          <span x-text="confirmAction.type === 'approve' ? 'Approve' : confirmAction.type === 'reject' ? 'Reject' : 'Delete'">
            Confirm
          </span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Document Upload Modal -->
<div x-cloak x-transition x-show="modals.upload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
  <div @click.away="modals.upload = false"
       class="w-full max-w-lg bg-white rounded-xl shadow-xl px-6 py-6 sm:px-7 sm:py-7 transition-all space-y-6 relative">

    <!-- Close Button -->
    <button @click="modals.upload = false"
            class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition"
            aria-label="Close modal">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
           stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Header -->
    <div class="text-left">
      <h2 class="text-lg font-semibold text-gray-800">Upload a Document</h2>
      <p class="text-sm text-gray-600 mt-1">Provide the required file and details below.</p>
    </div>

    <!-- Upload Form -->
    <form action="<?= htmlspecialchars($formAction) ?>" method="POST" enctype="multipart/form-data" class="space-y-5">
      <?php if ($isAdmin): ?>
        <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
      <?php endif; ?>

      <!-- Document Type -->
      <div>
        <label for="document_type" class="block text-sm font-medium text-slate-700 mb-1">Document Type</label>
        <select id="document_type" name="document_type" required
            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-slate-700 bg-white transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent">
          <option value="" disabled>Select type</option>
          <option value="Passport">Passport</option>
          <option value="Valid ID">Valid ID</option>
          <option value="Visa">Visa</option>
          <option value="Service Voucher">Service Voucher</option>
          <option value="Airline Ticket">Airline Ticket</option>
          <option value="PH Travel Tax">PH Travel Tax</option>
          <option value="Acknowledgement Receipt">Acknowledgement Receipt</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <!-- Custom Name -->
      <div>
        <label for="custom_name" class="block text-sm font-medium text-slate-700 mb-1">Rename File (Optional)</label>
        <input type="text" name="custom_name" id="custom_name" maxlength="60"
               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-slate-700 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent"
               placeholder="e.g., Justine_Passport">
        <p class="text-xs text-slate-500 mt-1">Max 60 characters. Letters, numbers, underscores only.</p>
      </div>

<!-- File Upload -->
<div>
  <label for="document_file" class="block text-sm font-medium text-slate-700 mb-2">Upload File</label>

  <div class="relative w-full rounded-lg border border-gray-300 border-dashed bg-gray-50 p-4 transition hover:border-gray-400 hover:bg-gray-100 focus-within:ring-2 focus-within:ring-sky-500">
    <input type="file" name="document_file" id="document_file"
           accept=".pdf,.jpg,.jpeg,.png" required
           class="block w-full text-sm text-slate-700
                  file:mr-3 file:py-1.5 file:px-4 file:border-0
                  file:rounded-md file:bg-sky-600 file:text-white file:cursor-pointer
                  hover:file:bg-sky-500 transition">
  </div>

  <p class="text-xs text-slate-500 mt-2">Accepted formats: PDF, JPG, JPEG, PNG.</p>
</div>

      <!-- Actions -->
      <div class="flex justify-end pt-2">
        <button type="submit"
                class="inline-flex items-center justify-center bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium px-4 py-2 rounded-md shadow-sm transition">
          Upload
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Alpine.js Component Script -->
<script>
function documentsTable() {
    return {
        deleteFileLoading: false,
        modals: {
            upload: false,
            viewer: false,
        },
        toast: {
            visible: false,
            message: '',
        },
        confirmAction: {
            visible: false,
            type: '',
            documentId: null,
            reason: '',
        },
        pendingDocumentUpdate: null,
        fileViewer: {
            id: null,
            path: '',
            name: '',
            type: '',
            mimeType: '',
            status: '',
            adminComments: '',
            uploadedAt: '',
            approvedAt: '',
            updatedBy: '',
            zoom: 1,
        },
        submitDocumentChangesLoading: false,
        hasFileChanged: false,

        // Open file viewer modal
        openFileModal(id, path, name, type, mimeType, status, adminComments, uploadedAt, approvedAt, updatedBy) {
            this.fileViewer = {
                id,
                path,
                name,
                type,
                mimeType,
                status,
                adminComments,
                uploadedAt,
                approvedAt,
                updatedBy,
                zoom: 1,
            };
            this.modals.viewer = true;
            this.hasFileChanged = false;
        },

        // Close file viewer modal
        closeFileModal() {
            this.modals.viewer = false;
        },

        // Save changes to document (approve/reject/status/comments)
        submitDocumentChanges() {
            // If status is being set to Rejected and no reason yet, show modal
            if (
                this.fileViewer.status === 'Rejected' &&
                !this.fileViewer.adminComments.trim()
            ) {
                // Store the current update so we can resume after reason is entered
                this.pendingDocumentUpdate = { ...this.fileViewer };
                this.confirmAction = {
                    visible: true,
                    type: 'reject',
                    documentId: this.fileViewer.id,
                    reason: ''
                };
                return;
            }

            this.submitDocumentChangesLoading = true;

            fetch('../actions/update_client_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id: this.fileViewer.id,
                    file_name: this.fileViewer.name,
                    document_type: this.fileViewer.type,
                    document_status: this.fileViewer.status,
                    admin_comments: this.fileViewer.adminComments
                })
            })
            .then(async response => {
                const data = await response.json();
                if (data.success) {
                    this.toast.message = 'Document changes saved!';
                    this.toast.visible = true;
                    this.modals.viewer = false;
                    setTimeout(() => {
                        this.toast.visible = false;
                        fetch('../components/documents-table.php?client_id=<?= htmlspecialchars($client_id) ?>')
                            .then(res => res.text())
                            .then(html => {
                                // Create a temporary DOM to extract the inner table
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = html;
                                const newContent = tempDiv.querySelector('#documents-table-content');
                                if (newContent) {
                                    document.getElementById('documents-table-content').innerHTML = newContent.innerHTML;
                                } else {
                                    document.getElementById('documents-table-content').innerHTML = html;
                                }
                            });
                    }, 1500);
                } else {
                    this.toast.message = data.error || 'Update failed.';
                    this.toast.visible = true;
                    setTimeout(() => this.toast.visible = false, 2000);
                }
            })
            .catch(() => {
                this.toast.message = 'Network error.';
                this.toast.visible = true;
                setTimeout(() => this.toast.visible = false, 2000);
            })
            .finally(() => {
                this.submitDocumentChangesLoading = false;
            });
        },

        // Open confirmation modal for approve/reject/delete
        confirmAndOpenModal() {
            // Handle rejection from File Viewer "Save Changes"
            if (this.confirmAction.type === 'reject' && this.pendingDocumentUpdate) {
                // Set the adminComments to the entered reason
                this.fileViewer.adminComments = this.confirmAction.reason;
                this.confirmAction.visible = false;
                this.confirmAction.reason = '';
                this.pendingDocumentUpdate = null;
                // Now actually submit the changes
                this.submitDocumentChanges();
                return;
            }

            // Handle delete from File Viewer
            if (this.confirmAction.type === 'delete') {
                this.confirmAction.visible = false;
                this.deleteFileConfirmed(this.confirmAction.documentId);
                return;
            }

            // Approve/Reject from table actions
            let endpoint = this.confirmAction.type === 'approve'
                ? '../actions/approve_document.php'
                : '../actions/reject_document.php';

            let body = `id=${encodeURIComponent(this.confirmAction.documentId)}`;
            if (this.confirmAction.type === 'reject') {
                body += `&reason=${encodeURIComponent(this.confirmAction.reason)}`;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(async response => {
                const text = await response.text();
                this.toast.message = text || 'An error occurred.';
                this.toast.visible = true;
                this.confirmAction.visible = false;
                this.confirmAction.reason = '';
                setTimeout(() => {
                    this.toast.visible = false;
                    window.location.reload();
                }, 1800);
            })
            .catch(() => {
                this.toast.message = 'Network error.';
                this.toast.visible = true;
                setTimeout(() => this.toast.visible = false, 1800);
            });
        },

        // Show confirmation modal for delete
        deleteFile() {
            this.confirmAction = {
                visible: true,
                type: 'delete',
                documentId: this.fileViewer.id,
                reason: ''
            };
        },

deleteFileConfirmed(documentId) {
  this.deleteFileLoading = true;

  fetch('../components/delete_document.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: documentId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      this.modals.viewer = false;
      window.location.reload(); // ✅ Toast will appear via status_alert.php
    } else {
      // ✅ Fallback only if backend fails before setting session
      console.warn('Backend failed to set toast status:', data.message);
      window.location.reload(); // Still reload to trigger any session-based fallback
    }
  })
  .catch(err => {
    console.error('Delete request failed:', err);
    window.location.reload(); // ✅ Let backend/session handle the toast
  })
  .finally(() => {
    this.deleteFileLoading = false;
  });
},

        // Print document
        printDocument(filePath) {
            const printWindow = window.open(filePath, '_blank');
            if (printWindow) {
                printWindow.addEventListener('load', () => {
                    printWindow.print();
                });
            } else {
                alert('Please allow pop-ups to print documents.');
            }
        }
    }
}
</script>
    </form>
  </div>
</div>

</section>
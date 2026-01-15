<?php
// admin/pending_docs_modal.php
?>
<div id="pending-modal" class="hidden fixed inset-0 z-[2147483000] flex items-center justify-center" style="position:fixed;">
  <div class="absolute inset-0 bg-black/50" data-modal-dismiss="pending-modal" style="position:fixed;"></div>
  <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4 max-h-[80vh] overflow-hidden z-[2147483001]" style="position:relative;">
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 class="text-sm font-semibold text-slate-800">Pending Documents</h3>
      <button class="text-slate-500 hover:text-slate-700" data-modal-dismiss="pending-modal">✕</button>
    </div>
    <div class="overflow-y-auto max-h-[65vh] divide-y">
      <?php if (empty($pendingDocsDetails)): ?>
        <p class="p-4 text-sm text-slate-500">No pending documents.</p>
      <?php else: ?>
        <?php foreach ($pendingDocsDetails as $item): ?>
          <div class="px-4 py-3 flex items-start justify-between gap-3 text-sm">
            <div>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($item['full_name'] ?? 'Client') ?></p>
              <p class="text-slate-500 text-xs">Document: <?= htmlspecialchars($item['document_type'] ?? $item['file_name'] ?? 'Unknown') ?></p>
            </div>
            <a class="text-sky-600 text-xs font-semibold hover:underline" href="../admin/view_client.php?client_id=<?= urlencode($item['id'] ?? '') ?>">View</a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

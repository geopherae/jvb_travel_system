<?php
// admin/incomplete_clients_modal.php
?>
<div id="incomplete-modal" class="hidden fixed inset-0 z-[2147483000] flex items-center justify-center" style="position:fixed;">
  <div class="absolute inset-0 bg-black/50" data-modal-dismiss="incomplete-modal" style="position:fixed;"></div>
  <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4 max-h-[80vh] overflow-hidden z-[2147483001]" style="position:relative;">
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 class="text-sm font-semibold text-slate-800">Clients Missing Required Items</h3>
      <button class="text-slate-500 hover:text-slate-700" data-modal-dismiss="incomplete-modal">✕</button>
    </div>
    <div class="overflow-y-auto max-h-[65vh] divide-y">
      <?php if (empty($incompleteClients)): ?>
        <p class="p-4 text-sm text-slate-500">All clients have submitted required items.</p>
      <?php else: ?>
        <?php foreach ($incompleteClients as $client): ?>
          <div class="px-4 py-3 flex items-start justify-between gap-3 text-sm">
            <div>
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($client['full_name'] ?? 'Client') ?></p>
              <p class="text-slate-500 text-xs mb-1">Status: <?= htmlspecialchars($client['status'] ?? 'Unknown') ?></p>
              <p class="text-rose-600 text-xs font-semibold">Missing: <?= htmlspecialchars(implode(', ', $client['missing'] ?? [])) ?></p>
            </div>
            <a class="text-sky-600 text-xs font-semibold hover:underline" href="../admin/view_client.php?client_id=<?= urlencode($client['id'] ?? '') ?>">View</a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

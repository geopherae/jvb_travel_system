<?php
if (!isset($conn, $client_id)) {
  echo "<p class='text-red-500 text-sm'>Client ID missing.</p>";
  return;
}

$stmt = $conn->prepare("SELECT assigned_admin_id FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$assigned_admin_id = $stmt->get_result()->fetch_assoc()['assigned_admin_id'] ?? null;

if (!$assigned_admin_id) {
  echo <<<HTML
  <div class="bg-white p-3 rounded-md border border-slate-200 text-center text-sm text-slate-600 italic">
    🧭 No travel agent assigned yet.
  </div>
  HTML;
  return;
}

$stmt = $conn->prepare("SELECT admin_photo, first_name, last_name, phone_number, messenger_link FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $assigned_admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if ($admin):
?>
<div class="bg-white p-3 rounded-md border border-slate-200 flex items-center gap-3">
  <img src="<?= htmlspecialchars('../uploads/admin_photo/' . $admin['admin_photo']) ?>" alt="Agent Photo"
       class="w-10 h-10 rounded-full object-cover border border-sky-200" />

  <div class="flex-1">
    <h4 class="text-sm font-semibold text-slate-800">
      <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
    </h4>
    <div class="flex gap-2 mt-1">
      <?php if (!empty($admin['phone_number'])): ?>
        <a href="tel:<?= htmlspecialchars($admin['phone_number']) ?>"
           class="text-xs text-sky-600 hover:underline">📞 Call</a>
      <?php endif; ?>
      <?php if (!empty($admin['messenger_link'])): ?>
        <a href="<?= htmlspecialchars($admin['messenger_link']) ?>" target="_blank"
           class="text-xs text-sky-600 hover:underline">💬 Message</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
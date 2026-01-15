<?php include_once __DIR__ . '/../includes/status-helpers.php';

$imgSrc = !empty($client['client_profile_photo'])
  ? '../uploads/client_profiles/' . htmlspecialchars($client['client_profile_photo'])
  : '../images/default_client_profile.png';

$status = $client['client_status'] ?? 'Pending';
$badgeClass = getStatusBadgeClass($status);

$fullName = $client['full_name'] ?? 'Unnamed Client';
$accessCode = $client['access_code'] ?? '—';
$email = $client['email'] ?? '';
$phone = $client['phone_number'] ?? '';
?>

<div class="relative bg-white border rounded-xl shadow-md p-6 space-y-4">

  <!-- Status Badge -->
  <div class="absolute top-4 right-4">
    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badgeClass ?>">
      <?= ucfirst($status); ?>
    </span>
  </div>

  <!-- Profile Info -->
  <div class="flex items-center gap-4">
    <div class="w-20 h-20 rounded-full overflow-hidden border shadow">
      <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($fullName) ?> Profile"
           class="w-full h-full object-cover" loading="lazy" />
    </div>
    <div>
      <p class="text-base font-semibold text-gray-800"><?= htmlspecialchars($fullName); ?></p>
      <p class="text-xs text-gray-500">Access Code: <?= htmlspecialchars($accessCode); ?></p>
    </div>
  </div>

  <!-- Contact Info -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
    <div>
      <p><span class="font-medium">Email:</span> <?= htmlspecialchars($email); ?></p>
    </div>
    <div>
      <p><span class="font-medium">Phone:</span> <?= htmlspecialchars($phone); ?></p>
    </div>
  </div>

  <!-- Actions -->
  <div class="flex gap-4 pt-2">
    <?php if ($email): ?>
      <a href="mailto:<?= htmlspecialchars($email); ?>"
         class="text-sm text-sky-600 hover:underline focus:outline-none focus:ring-2 focus:ring-sky-400"
         aria-label="Email <?= htmlspecialchars($fullName) ?>">Email</a>
    <?php endif; ?>
    <?php if ($phone): ?>
      <a href="tel:<?= htmlspecialchars($phone); ?>"
         class="text-sm text-sky-600 hover:underline focus:outline-none focus:ring-2 focus:ring-sky-400"
         aria-label="Call <?= htmlspecialchars($fullName) ?>">Call</a>
    <?php endif; ?>
  </div>
</div>
<?php
require_once __DIR__ . '/../actions/db.php';

// 🧮 Calculate conversion rate
$conversionStmt = $conn->prepare("
  SELECT 
    (SELECT COUNT(*) FROM clients WHERE status = 'Trip Completed') AS completed,
    (SELECT COUNT(*) FROM clients) AS total
");
$conversionStmt->execute();
$conversionResult = $conversionStmt->get_result();
$conversionData = $conversionResult->fetch_assoc();

$completedClients = $conversionData['completed'] ?? 0;
$totalClients     = $conversionData['total'] ?? 0;

if ($totalClients > 0) {
  $conversionRate = round(($completedClients / $totalClients) * 100, 1);
} else {
  $conversionRate = 0; // Or set to null and handle in display
}
?>

<div class="w-full flex items-center justify-between h-auto">
  <div>
    <p class="text-sm font-medium text-white uppercase tracking-wide">
      Client Conversion
    </p>
    <p class="text-3xl font-bold text-white">
      <?php if ($totalClients > 0): ?>
        <?= $conversionRate ?>%
      <?php else: ?>
        N/A
      <?php endif; ?>
    </p>
    <p class="text-xs font-semibold text-white mt-1">
      <?php if ($totalClients > 0): ?>
        <?= number_format($completedClients) ?> of <?= number_format($totalClients) ?> clients completed trips
      <?php else: ?>
        No clients available
      <?php endif; ?>
    </p>
  </div>
  
  <div class="bg-indigo-100 text-indigo-600 rounded-full p-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
      <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 14.5h-2v-2h2v2zm0-4h-2V7h2v5.5z"/>
    </svg>
  </div>
</div>
<?php
require_once __DIR__ . '/../actions/db.php';

// Calculate average time (in hours) from client account creation to first document upload
$adoptionStmt = $conn->prepare("
  SELECT 
    ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, uf.first_upload)), 1) AS avg_hours,
    COUNT(*) AS total_clients,
    MAX(uf.first_upload) AS latest_upload
  FROM (
    SELECT client_id, MIN(uploaded_at) AS first_upload
    FROM uploaded_files
    GROUP BY client_id
  ) AS uf
  JOIN clients c ON c.id = uf.client_id
  WHERE uf.first_upload IS NOT NULL
    AND c.created_at IS NOT NULL
");
$adoptionStmt->execute();
$adoptionResult = $adoptionStmt->get_result();
$adoptionData   = $adoptionResult->fetch_assoc();

$avgHours = $adoptionData['avg_hours'] ?? null;
$totalClients = $adoptionData['total_clients'] ?? 0;
$latestUpload = $adoptionData['latest_upload'] ?? null;

// Format display (h/d units)
function formatAdoptionTime(?float $hours): string {
  if ($hours === null) {
    return '—';
  }
  if ($hours >= 48) {
    return number_format($hours / 24, 1) . 'd';
  }
  return number_format($hours, 1) . 'h';
}

$display = formatAdoptionTime($avgHours);

// Sentiment chip based on engagement speed
$sentiment = 'Good Engagement';
$chipColor = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
if ($avgHours !== null) {
  if ($avgHours <= 6) {
    $sentiment = 'Hot Engagement';
  } elseif ($avgHours <= 24) {
    $sentiment = 'Good Engagement';
  } elseif ($avgHours <= 48) {
    $sentiment = 'Warm Engagement';
    $chipColor = 'bg-amber-100 text-amber-700 border border-amber-200';
  } else {
    $sentiment = 'Cold Engagement';
    $chipColor = 'bg-rose-100 text-rose-700 border border-rose-200';
  }
}

$latestLabel = $latestUpload ? date('M d', strtotime($latestUpload)) : '—';
?>

<div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-br from-sky-50 via-white to-sky-50 shadow-sm h-full">
  <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-sky-100 opacity-50"></div>
  <div class="absolute -left-12 bottom-0 h-24 w-24 rounded-full bg-sky-100 opacity-60"></div>

  <div class="relative p-6 h-full flex flex-col justify-between space-y-4">
    <div>
      <p class="text-sm font-semibold text-sky-700 uppercase tracking-[0.18em]">Adoption Velocity</p>
      <div class="flex items-baseline gap-3 mt-3">
        <p class="text-4xl font-bold text-gray-900 leading-none">
          <?= $display; ?>
        </p>
        <?php if ($avgHours !== null): ?>
          <span class="text-base text-gray-700">account → first upload</span>
        <?php else: ?>
          <span class="text-base text-gray-500">no uploads yet</span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-semibold <?= $chipColor; ?>">
          <span aria-hidden="true">🔥</span>
          <?= htmlspecialchars($sentiment); ?>
        </span>
      </div>
    </div>

    <div class="bg-white/80 border border-gray-200 rounded-lg p-3 shadow-sm text-xs space-y-1.5">
      <div class="flex items-center justify-between">
        <span class="text-gray-600">Clients uploading</span>
        <span class="font-semibold text-gray-900"><?= number_format($totalClients); ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-gray-600">Latest upload</span>
        <span class="font-semibold text-gray-900"><?= $latestLabel; ?></span>
      </div>
      <p class="text-gray-600 leading-relaxed pt-1.5 border-t border-gray-200">
        Lower hours = eager clients. Goal: under 12h.
      </p>
    </div>
  </div>
</div>
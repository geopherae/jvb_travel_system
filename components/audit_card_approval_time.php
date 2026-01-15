<?php
require_once __DIR__ . '/../actions/db.php';

// Calculate average approval time (hours) for approved documents
$approvalStmt = $conn->prepare("
  SELECT 
    ROUND(AVG(TIMESTAMPDIFF(HOUR, uploaded_at, approved_at)), 1) AS avg_approval_hours,
    COUNT(*) AS total_approvals,
    MAX(approved_at) AS latest_approval
  FROM uploaded_files
  WHERE approved_at IS NOT NULL
");
$approvalStmt->execute();
$approvalResult = $approvalStmt->get_result();
$approvalData   = $approvalResult->fetch_assoc();

$avgApprovalHours = $approvalData['avg_approval_hours'] ?? null;
$totalApprovals = $approvalData['total_approvals'] ?? 0;
$latestApproval = $approvalData['latest_approval'] ?? null;

// Friendly formatter for display (h/d units)
function formatApprovalTime(?float $hours): string {
  if ($hours === null) {
    return '—';
  }
  if ($hours >= 48) {
    return number_format($hours / 24, 1) . 'd';
  }
  return number_format($hours, 1) . 'h';
}

$display = formatApprovalTime($avgApprovalHours);
$latestLabel = $latestApproval ? date('M d', strtotime($latestApproval)) : '—';

// Simple sentiment tag based on speed
$sentiment = 'On-Time';
$chipColor = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
if ($avgApprovalHours !== null) {
  if ($avgApprovalHours <= 12) {
    $sentiment = 'Lightning Fast';
  } elseif ($avgApprovalHours <= 24) {
    $sentiment = 'On-Time';
  } elseif ($avgApprovalHours <= 48) {
    $sentiment = 'Needs Attention';
    $chipColor = 'bg-amber-100 text-amber-700 border border-amber-200';
  } else {
    $sentiment = 'Improve SLA';
    $chipColor = 'bg-rose-100 text-rose-700 border border-rose-200';
  }
}
?>

<div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-br from-sky-50 via-white to-indigo-50 shadow-sm h-full">
  <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-sky-100 opacity-60"></div>
  <div class="absolute -left-12 bottom-0 h-24 w-24 rounded-full bg-indigo-100 opacity-50"></div>

  <div class="relative p-6 h-full flex flex-col justify-between space-y-4">
    <div>
      <p class="text-sm font-semibold text-sky-700 uppercase tracking-[0.18em]">Approval Time</p>
      <div class="flex items-baseline gap-3 mt-3">
        <p class="text-4xl font-extrabold text-gray-900 leading-none">
          <?= $display; ?>
        </p>
        <?php if ($avgApprovalHours !== null): ?>
          <span class="text-base text-gray-700">avg from upload → approve</span>
        <?php else: ?>
          <span class="text-base text-gray-500">no approvals yet</span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-semibold <?= $chipColor; ?>">
          <span aria-hidden="true">✈️</span>
          <?= htmlspecialchars($sentiment); ?>
        </span>
      </div>
    </div>

    <div class="bg-white/80 border border-gray-200 rounded-lg p-3 shadow-sm text-xs space-y-1.5">
      <div class="flex items-center justify-between">
        <span class="text-gray-600">Documents approved</span>
        <span class="font-semibold text-gray-900"><?= number_format($totalApprovals); ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-gray-600">Latest approval</span>
        <span class="font-semibold text-gray-900"><?= $latestLabel; ?></span>
      </div>
      <p class="text-gray-600 leading-relaxed pt-1.5 border-t border-gray-200">
        Lower hours = faster approvals. Goal: under 24h.
      </p>
    </div>
  </div>
</div>
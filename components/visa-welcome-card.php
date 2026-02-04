<?php
/**
 * Visa Welcome Card Component
 * Displays personalized greeting and visa application summary for clients
 * 
 * Required variables:
 * - $selectedVisaApp: Array with visa application details (id, country, status, application_mode, processing_days, created_at)
 * - $client: Array with client details (full_name)
 */

$selectedVisaApp = $selectedVisaApp ?? null;
$client = $client ?? null;
$status = $selectedVisaApp['status'] ?? 'draft';

date_default_timezone_set('Asia/Manila');
$hour = (int) date('G');

// Greeting based on time
switch (true) {
  case ($hour >= 5 && $hour < 12):
    $greeting = 'Good morning';
    break;
  case ($hour >= 12 && $hour < 17):
    $greeting = 'Good afternoon';
    break;
  default:
    $greeting = 'Good evening';
    break;
}

// Fixed dark sky blue gradient
$gradient = 'from-[#0c4a6e] via-[#0369a1] to-[#0284c7]';
$greetingGradient = 'from-sky-300 via-cyan-200 to-blue-200';
?>

<style>
@keyframes gradientShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
.animate-gradient {
  background-size: 200% 200%;
  animation: gradientShift 15s ease infinite;
}
</style>

<section
  x-data="{ collapsed: false }"
  x-init="window.addEventListener('scroll', () => collapsed = window.scrollY > 1)"
  :class="collapsed ? 'p-4' : 'p-6 lg:p-8'"
  class="w-full rounded-xl bg-gradient-to-r <?= $gradient ?> text-white shadow-lg mb-6 transition-all duration-300 ease-in-out animate-gradient overflow-hidden relative"
>
  <div class="flex flex-col lg:flex-row justify-between gap-8 items-start">

    <!-- Greeting Section -->
    <div class="w-full lg:w-1/2 flex flex-col gap-4">
      <h1 class="text-3xl md:text-4xl font-bold leading-tight tracking-tight">
        <?= $greeting ?>, <span class="bg-gradient-to-r <?= $greetingGradient ?> bg-clip-text text-transparent"><?= htmlspecialchars($client ? ($client['full_name'] ?? 'Traveler') : ($_SESSION['full_name'] ?? 'Traveler')) ?></span>!
      </h1>
      <p class="text-base md:text-lg mt-1 text-white/90 leading-relaxed">
        <?php if ($selectedVisaApp && in_array(strtolower($status), ['approved', 'approved_for_submission', 'booking'])): ?>
          Great news! Your visa application is progressing well. Keep an eye on your documents.
        <?php elseif ($selectedVisaApp && in_array(strtolower($status), ['awaiting_docs', 'draft'])): ?>
          Let's get your visa application started. Upload your documents to begin the process.
        <?php elseif ($selectedVisaApp && strtolower($status) === 'under_review'): ?>
          Your documents are being reviewed. We'll notify you once there's an update.
        <?php elseif ($selectedVisaApp && in_array(strtolower($status), ['rejected', 'cancelled'])): ?>
          Your application needs attention. Please check the details below.
        <?php else: ?>
          Track your visa application progress and manage your documents here.
        <?php endif; ?>
      </p>
    </div>

    <!-- Visa Processing Summary -->
    <?php if ($selectedVisaApp): ?>
    <div class="relative rounded-xl transition-all duration-300 flex flex-col justify-between h-full ring-2 ring-sky-300/20 cursor-default group">
      <div class="flex-1 bg-white/90 backdrop-blur-sm rounded-lg p-5 space-y-4 text-sm text-gray-800"
           x-show="!collapsed" x-transition>
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
          <div class="w-full sm:max-w-[60%]">
            <h2 class="text-lg font-semibold text-slate-700 leading-snug">Visa Application</h2>
            <p class="text-xs text-slate-500 mt-1">Your visa processing status and details</p>
          </div>
          <span class="px-4 py-1 text-xs font-medium rounded-full <?php
            $statusLower = strtolower($status);
            if (in_array($statusLower, ['draft', 'awaiting_docs'])) {
              echo 'bg-amber-100 text-amber-700';
            } elseif (in_array($statusLower, ['under_review', 'submitted'])) {
              echo 'bg-sky-100 text-sky-700';
            } elseif (in_array($statusLower, ['approved', 'approved_for_submission', 'booking'])) {
              echo 'bg-emerald-100 text-emerald-700';
            } elseif (in_array($statusLower, ['rejected', 'cancelled'])) {
              echo 'bg-red-100 text-red-700';
            } else {
              echo 'bg-gray-100 text-gray-700';
            }
          ?>">
            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?>
          </span>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <p class="font-medium text-gray-500 uppercase">Destination Country</p>
            <p class="text-sm font-medium">
              <?= htmlspecialchars($selectedVisaApp['country'] ?? 'N/A') ?>
            </p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Application Mode</p>
            <p class="text-sm font-medium">
              <?= ucfirst(htmlspecialchars($selectedVisaApp['application_mode'] ?? 'N/A')) ?>
            </p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Processing Time</p>
            <p class="text-sm font-medium">
              <?= htmlspecialchars($selectedVisaApp['processing_days'] ?? '—') ?> days
            </p>
          </div>
          <div>
            <p class="font-medium text-gray-500 uppercase">Application Date</p>
            <p class="text-sm font-medium">
              <?php
                if (!empty($selectedVisaApp['created_at'])) {
                  echo date('M j, Y', strtotime($selectedVisaApp['created_at']));
                } else {
                  echo '—';
                }
              ?>
            </p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
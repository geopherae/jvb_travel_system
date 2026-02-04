<?php
require_once __DIR__ . '/../includes/client_session_check.php';
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client');

require_once __DIR__ . '/../actions/db.php';

// 🚫 Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🧑 Get client ID from session
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id || !is_numeric($client_id)) {
  http_response_code(403);
  exit('Invalid session.');
}

// Check if this client has visa processing enabled
$processingType = $_SESSION['processing_type'] ?? 'booking';
if (!in_array($processingType, ['visa', 'both'])) {
  http_response_code(403);
  exit('Visa processing not enabled for this account.');
}

// 📋 Fetch all visa applications for this client
$visa_apps_stmt = $conn->prepare("
  SELECT 
    cva.id,
    cva.visa_package_id,
    cva.application_mode,
    cva.status,
    cva.created_at,
    vp.country,
    vp.processing_days
  FROM client_visa_applications cva
  JOIN visa_packages vp ON cva.visa_package_id = vp.id
  WHERE cva.client_id = ?
  ORDER BY cva.created_at DESC
");
$visa_apps_stmt->bind_param("i", $client_id);
$visa_apps_stmt->execute();
$visa_applications = $visa_apps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$visa_apps_stmt->close();

// Set default selected visa application (first one, or from GET parameter)
$selectedVisaAppId = null;
$selectedVisaApp = null;
if (!empty($visa_applications)) {
  $selectedVisaAppId = (int)($_GET['visa_application_id'] ?? $visa_applications[0]['id']);
  foreach ($visa_applications as $app) {
    if ($app['id'] == $selectedVisaAppId) {
      $selectedVisaApp = $app;
      break;
    }
  }
  // Fallback to first if not found
  if (!$selectedVisaApp) {
    $selectedVisaAppId = $visa_applications[0]['id'];
    $selectedVisaApp = $visa_applications[0];
  }
}
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Visa Processing</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="Your visa processing dashboard." />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>[x-cloak] { display: none !important; }</style>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.store('applicantSelector', {
        currentIdx: 0,
        applicants: [],
        totalApplicants: 0
      });
    });
  </script>
</head>

<body x-data="{ sidebarOpen: false }" class="bg-gray-50 font-poppins text-gray-800">

  <?php include __DIR__ . '/../components/status_alert.php'; ?>

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-primary text-white rounded">
    ☰
  </button>

  <!-- Sidebar -->
  <?php if (file_exists('../components/sidebar.php')) include '../components/sidebar.php'; ?>

  <!-- Right Sidebar Panel -->
  <?php
    $isAdmin = false;
    if (file_exists('../components/right-panel.php')) include '../components/right-panel.php';
  ?>

  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 flex flex-col relative z-0">

    <!-- Scrollable Content Container -->
    <div class="flex-1 overflow-y-auto space-y-6">

      <h2 class="text-xl font-bold">Visa Processing Dashboard</h2>

<?php
// Fetch client details for welcome card
if ($selectedVisaApp) {
  $client_stmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
  $client_stmt->bind_param("i", $client_id);
  $client_stmt->execute();
  $client = $client_stmt->get_result()->fetch_assoc();
  $client_stmt->close();
  
  // Include visa welcome card
  include __DIR__ . '/../components/visa-welcome-card.php';
}
?>


      <?php if (empty($visa_applications)): ?>
        <!-- No Visa Applications -->
        <div class="bg-white rounded-lg shadow p-8 text-center">
          <div class="flex flex-col items-center justify-center py-12">
            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Visa Applications</h3>
            <p class="text-gray-500 max-w-md">
              You don't have any visa applications yet. Contact your administrator to get started with a visa application.
            </p>
          </div>
        </div>
      <?php else: ?>
        <!-- Visa Application Tabs (if multiple) -->
        <?php if (count($visa_applications) > 1): ?>
          <div class="bg-white rounded-lg shadow overflow-hidden border-b border-gray-200">
            <div class="flex overflow-x-auto">
              <?php foreach ($visa_applications as $idx => $app): ?>
                <a href="?visa_application_id=<?= $app['id'] ?>"
                   class="px-6 py-4 text-sm font-medium border-b-2 transition whitespace-nowrap
                   <?= ($selectedVisaAppId == $app['id']) 
                     ? 'border-sky-500 text-sky-600' 
                     : 'border-transparent text-gray-600 hover:text-gray-900' ?>">
                  <?= htmlspecialchars($app['country']) ?> - <?= ucfirst($app['application_mode']) ?>
                  <span class="text-xs text-gray-500 ml-2">(<?= date('M j, Y', strtotime($app['created_at'])) ?>)</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Visa Document Table Component -->
        <?php if ($selectedVisaApp): ?>
          <div>
            <!-- Applicant Selector (Only for Group Applications or Group Access) -->
            <template x-if="$store.applicantSelector.totalApplicants > 1">
              <div class="flex items-center justify-between gap-4 p-4 bg-sky-100 rounded-lg border border-sky-200 mb-6">
                <label class="text-sm font-semibold text-gray-700">Select Applicant:</label>
                <select 
                  x-model.number="$store.applicantSelector.currentIdx"
                  @change="$store.applicantSelector.currentIdx = Number($event.target.value)"
                  class="px-4 py-2.5 border-2 border-sky-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 text-sm bg-white text-gray-800 font-medium appearance-none cursor-pointer hover:border-sky-400 transition-all w-72"
                  style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%220369a1%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3e%3cpolyline points=%226 9 12 15 18 9%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25em 1.25em; padding-right: 2.75rem;">
                  
                  <!-- Lead Guest Section -->
                  <optgroup label="Lead Guest">
                    <option value="0" x-text="$store.applicantSelector.applicants[0]?.name || 'Lead Applicant'"></option>
                  </optgroup>

                  <!-- Companions Section -->
                  <template x-if="$store.applicantSelector.applicants.length > 1">
                    <optgroup label="Companions">
                      <template x-for="(applicant, idx) in $store.applicantSelector.applicants.slice(1)" :key="idx + 1">
                        <option :value="idx + 1" x-text="applicant.name"></option>
                      </template>
                    </optgroup>
                  </template>
                </select>
              </div>
            </template>

            <?php 
              // Pass visa application ID to the component
              $visa_application_id = (int)$selectedVisaApp['id'];
              $application_mode = $selectedVisaApp['application_mode'];
              
              // Include the visa document table component
              include __DIR__ . '/../components/visa-document-table.php';
            ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

  </main>

</body>

</html>

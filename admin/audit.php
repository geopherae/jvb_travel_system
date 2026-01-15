<?php
// audit.php

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../components/survey_response_viewer.php';

// 🧠 Initialize filters
$actionType = $_GET['action_type'] ?? '';
$module     = $_GET['module'] ?? '';
$kpiTag     = $_GET['kpi_tag'] ?? '';
$actorRole  = $_GET['actor_role'] ?? '';
$anonymize  = isset($_GET['anonymize']);

$filters = [];
if ($actionType) $filters[] = "action_type = '" . mysqli_real_escape_string($conn, $actionType) . "'";
if ($module)     $filters[] = "module = '" . mysqli_real_escape_string($conn, $module) . "'";
if ($kpiTag)     $filters[] = "kpi_tag = '" . mysqli_real_escape_string($conn, $kpiTag) . "'";
if ($actorRole)  $filters[] = "actor_role = '" . mysqli_real_escape_string($conn, $actorRole) . "'";

$whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

// 📊 KPI Distribution
$kpiCounts = [];
$kpiQuery = mysqli_query($conn, "SELECT kpi_tag, COUNT(*) as count FROM audit_logs GROUP BY kpi_tag");
while ($row = mysqli_fetch_assoc($kpiQuery)) {
  $kpiCounts[$row['kpi_tag']] = $row['count'];
}

// 🧩 Dropdown values
$actionTypes = [];
$modules     = [];

$typeQuery = mysqli_query($conn, "SELECT DISTINCT action_type FROM audit_logs WHERE action_type IS NOT NULL AND action_type != '' ORDER BY action_type ASC");
while ($row = mysqli_fetch_assoc($typeQuery)) {
  $actionTypes[] = $row['action_type'];
}

$moduleQuery = mysqli_query($conn, "SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC");
while ($row = mysqli_fetch_assoc($moduleQuery)) {
  $modules[] = $row['module'];
}

// Add empty state checks
$hasKpiData = !empty($kpiCounts);
$hasActionTypes = !empty($actionTypes);
$hasModules = !empty($modules);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    [x-cloak] { display: none !important; }
    .gradient-header {
      background: linear-gradient(135deg, #4695ef 0%, #0a3dab 100%);
    }
    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .tab-active {
      border-b-2 border-blue-600 text-blue-600;
    }
    .tab-inactive {
      border-b-2 border-transparent text-gray-600;
    }
  </style>

  <script>
    window.kpiLabels = <?= json_encode(array_keys($kpiCounts)) ?>;
    window.kpiData   = <?= json_encode(array_values($kpiCounts)) ?>;
    window.hasKpiData = <?= json_encode($hasKpiData) ?>;
  </script>
</head>

<body x-data="{ activeTab: 'audit' }" class="bg-gray-50 text-gray-800">

  <?php include '../components/admin_sidebar.php'; ?>

  <main class="ml-0 lg:ml-64 min-h-screen flex flex-col">
    
    <!-- Header Section -->
    <div class="gradient-header text-white px-6 py-12 md:px-10">
      <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between gap-6 mb-8">
          <div>
            <h1 class="text-4xl md:text-5xl font-bold mb-2">Audit Dashboard</h1>
            <p class="text-blue-100 text-lg">Monitor activities, track surveys, and analyze system metrics</p>
          </div>
          <form method="POST" action="../includes/export_audit.php" class="hidden md:block">
            <input type="hidden" name="action_type" value="<?= htmlspecialchars($actionType) ?>">
            <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
            <input type="hidden" name="kpi_tag" value="<?= htmlspecialchars($kpiTag) ?>">
            <input type="hidden" name="actor_role" value="<?= htmlspecialchars($actorRole) ?>">
            <input type="hidden" name="anonymize" value="<?= $anonymize ? '1' : '' ?>">
            <button type="submit" class="px-6 py-3 bg-white text-blue-600 rounded-lg font-semibold hover:bg-blue-50 transition shadow-lg">
              📥 Export CSV
            </button>
          </form>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-20">
            <?php include '../components/audit_card_total_clients.php'; ?>
          </div>
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-20">
            <?php include '../components/audit_card_trips_completed.php'; ?>
          </div>
          <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-20">
            <?php include '../components/audit_card_conversion_rate.php'; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 px-6 py-8 md:px-10">
      <div class="max-w-7xl mx-auto space-y-8">



        <!-- Velocity Chart -->
        <?php
          $velocitySummaryStmt = $conn->prepare("
            SELECT 
              ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, c.confirmed_at)), 1) AS avg_hours,
              COUNT(*) AS total_clients,
              MAX(c.confirmed_at) AS latest_confirmed
            FROM clients c
            WHERE c.confirmed_at IS NOT NULL
              AND c.created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
          ");
          $velocitySummaryStmt->execute();
          $vsData = $velocitySummaryStmt->get_result()->fetch_assoc();
          $ovAvgHours = $vsData['avg_hours'] ?? null;
          $ovCount = $vsData['total_clients'] ?? 0;
          $latestConfirmed = $vsData['latest_confirmed'] ?? null;

          $ovDisplay = '—';
          if ($ovAvgHours !== null) {
            if ($ovAvgHours >= 48) {
              $ovDisplay = number_format($ovAvgHours / 24, 1) . 'd';
            } else {
              $ovDisplay = number_format($ovAvgHours, 1) . 'h';
            }
          }

          $ovSentiment = 'On-Time';
          $ovChip = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
          if ($ovAvgHours !== null) {
            if ($ovAvgHours <= 24) {
              $ovSentiment = 'Smooth Sailing';
            } elseif ($ovAvgHours <= 48) {
              $ovSentiment = 'Keep an Eye';
              $ovChip = 'bg-amber-100 text-amber-700 border border-amber-200';
            } else {
              $ovSentiment = 'Speed Up Boarding';
              $ovChip = 'bg-rose-100 text-rose-700 border border-rose-200';
            }
          }

          $latestLabel = $latestConfirmed ? date('M d', strtotime($latestConfirmed)) : '—';
        ?>

        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-br from-sky-50 via-white to-sky-50 shadow-sm">
          <div class="absolute -right-12 -top-10 h-36 w-36 rounded-full bg-sky-100 opacity-50"></div>
          <div class="absolute -left-16 bottom-0 h-28 w-28 rounded-full bg-sky-100 opacity-60"></div>

          <div class="relative p-6 space-y-5">
            <div class="flex flex-col lg:flex-row justify-between gap-6">
              <div class="space-y-3">
                <p class="text-sm font-semibold text-sky-700 uppercase tracking-[0.18em]">Onboarding Velocity</p>
                <div class="flex items-baseline gap-3">
                  <p class="text-4xl font-bold text-gray-900 leading-none">
                    <?= $ovDisplay; ?>
                  </p>
                  <?php if ($ovAvgHours !== null): ?>
                    <span class="text-base text-gray-700">avg from account creation → confirmed</span>
                  <?php else: ?>
                    <span class="text-base text-gray-500">no confirmed journeys yet</span>
                  <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-semibold <?= $ovChip; ?>">
                    <span aria-hidden="true">🚀</span>
                    <?= htmlspecialchars($ovSentiment); ?>
                  </span>
                  <span class="text-sm text-gray-600">Lower hours = faster boarding. Goal: under 24h.</span>
                </div>
              </div>

              <div class="shrink-0 bg-white/80 border border-gray-200 rounded-2xl p-4 shadow-sm min-w-[220px]">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Snapshot</p>
                  <span class="text-xs text-gray-500">Past 12 weeks</span>
                </div>
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Latest confirmed</span>
                    <span class="text-sm font-semibold text-gray-900"><?= $latestLabel; ?></span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Clients included</span>
                    <span class="text-sm font-semibold text-gray-900"><?= number_format((int) $ovCount); ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white/70 border border-gray-200/70 rounded-lg p-3 shadow-inner">
              <canvas id="velocityLineChart" class="max-h-[300px]"></canvas>
            </div>
          </div>
        </div>

                <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Chart -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-full">
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h2 class="text-xl font-bold text-gray-900">KPI Distribution</h2>
                  <p class="text-sm text-gray-500 mt-1">System activity breakdown by KPI tags</p>
                </div>
              </div>
              <?php if (!$hasKpiData): ?>
                <div class="h-64 flex items-center justify-center text-gray-400">
                  <p>No KPI data available yet</p>
                </div>
              <?php else: ?>
                <canvas id="kpiChart" class="max-h-[300px]"></canvas>
              <?php endif; ?>
            </div>
          </div>

          <!-- Side Metrics -->
          <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <canvas id="statusPieChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Adoption Velocity & Approval Time Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="h-full">
            <?php include '../components/audit_card_adoption_velocity.php'; ?>
          </div>
          <div class="h-full">
            <?php include '../components/audit_card_approval_time.php'; ?>
          </div>
        </div>

        <!-- Filters & Tabs Section -->
        <div class="space-y-6">
          <!-- Modern Tab Navigation -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 pt-6 pb-0">
              <div class="flex gap-6 border-b pb-2 text-sm font-semibold text-gray-600">
                <button 
                  @click="activeTab = 'audit'" 
                  :class="activeTab === 'audit' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                  class="pb-1 transition">
                  Audit Logs
                </button>
                <button 
                  @click="activeTab = 'surveys'" 
                  :class="activeTab === 'surveys' ? 'text-sky-600 border-b-2 border-sky-600' : 'hover:text-sky-500'"
                  class="pb-1 transition">
                  Survey Responses
                </button>
              </div>
            </div>

            <!-- Audit Logs Tab Content -->
            <div x-show="activeTab === 'audit'" x-transition class="px-6 py-6">
              <!-- Filters -->
              <?php include '../components/audit_kpi_table_filters.php'; ?>

              <!-- Table Controls -->
              <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 mt-6">
                <h3 class="text-lg font-bold text-gray-900">Recent Activity</h3>
                <div class="flex items-center gap-3">
                  <label for="limitSelect" class="text-gray-700 font-medium">Show per page:</label>
                  <select id="limitSelect" class="px-4 py-2 rounded-lg border border-gray-300 bg-white font-medium hover:border-gray-400 transition">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                  </select>
                </div>
              </div>

              <!-- Audit Table -->
              <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <?php include '../components/audit_table.php'; ?>
              </div>

              <!-- Pagination -->
              <div class="flex justify-center items-center gap-2 mt-6">
                <div id="paginationLinks" class="audit-pagination inline-flex items-center gap-1">
                  <!-- Links injected by JavaScript -->
                </div>
              </div>
            </div>

            <!-- Survey Responses Tab Content -->
            <div x-show="activeTab === 'surveys'" x-transition class="px-6 py-6">
              <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                  <h3 class="text-lg font-bold text-gray-900">Client Feedback</h3>
                  <p class="text-sm text-gray-500 mt-1">View responses from clients about their experience</p>
                </div>
                <div class="flex items-center gap-3">
                  <label for="surveySortSelect" class="text-gray-700 font-medium">Sort by:</label>
                  <select id="surveySortSelect" class="px-4 py-2 rounded-lg border border-gray-300 bg-white font-medium hover:border-gray-400 transition">
                    <option value="recent">Recent First</option>
                    <option value="oldest">Oldest First</option>
                  </select>
                </div>
              </div>

              <div id="surveyResponsesContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Survey cards injected by JavaScript -->
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </main>

  <script src="../assets/js/audit_scripts.js"></script>
  <script src="../assets/js/survey_responses.js"></script>

</body>
</html>
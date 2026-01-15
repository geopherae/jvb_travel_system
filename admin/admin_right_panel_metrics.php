<?php
// admin/admin_right_panel_metrics.php

if (!isset($_SESSION['admin'])) return; // 🔒 Only show to admins

require_once __DIR__ . '/../actions/db.php';

// 🧮 Metric 1: Active Clients (status ≠ 'Trip Completed')
$totalClients = 0;
$clientStmt = $conn->prepare("SELECT COUNT(*) AS total FROM clients WHERE status != 'Trip Completed'");
if ($clientStmt) {
  $clientStmt->execute();
  $result = $clientStmt->get_result();
  $data = $result->fetch_assoc();
  $totalClients = $data['total'] ?? 0;
  $clientStmt->close();
}

// 📄 Metric 2: Pending Documents (status = 'Pending')
$pendingDocs = 0;
$docStmt = $conn->prepare("SELECT COUNT(*) AS total FROM uploaded_files WHERE document_status = 'Pending'");
if ($docStmt) {
  $docStmt->execute();
  $result = $docStmt->get_result();
  $data = $result->fetch_assoc();
  $pendingDocs = $data['total'] ?? 0;
  $docStmt->close();
}

// ✈️ Metric 3: Trips This Month
$tripsThisMonth = 0;
date_default_timezone_set('Asia/Manila');
$currentMonth = date('Y-m');
$tripStmt = $conn->prepare("SELECT COUNT(*) AS total FROM clients WHERE trip_date_start LIKE ?");
if ($tripStmt) {
  $searchMonth = $currentMonth . '%';
  $tripStmt->bind_param('s', $searchMonth);
  $tripStmt->execute();
  $result = $tripStmt->get_result();
  $data = $result->fetch_assoc();
  $tripsThisMonth = $data['total'] ?? 0;
  $tripStmt->close();
}

// 📋 Metric 4: Clients Missing Required Items
// Removed - metric no longer displayed

?>

<div class="mt-4 space-y-4">
  <!-- 🎯 Metrics Stack -->
  <div class="space-y-2">
    
    <!-- 👥 Active Clients -->
    <div class="flex items-center justify-between bg-gradient-to-r from-sky-50 to-sky-100 border border-sky-200 rounded-lg px-3 py-2.5 hover:shadow-sm transition-all duration-200">
      <div class="flex items-center gap-2.5">
        <div class="bg-sky-200 text-sky-700 rounded-md p-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
          </svg>
        </div>
        <p class="text-xs font-semibold text-sky-700 uppercase tracking-wide">Active Clients</p>
      </div>
      <p class="text-xl font-bold text-slate-800"><?= number_format($totalClients) ?></p>
    </div>

    <!-- 📄 Pending Documents -->
    <div class="flex items-center justify-between bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 rounded-lg px-3 py-2.5 hover:shadow-sm transition-all duration-200">
      <div class="flex items-center gap-2.5">
        <div class="bg-amber-200 text-amber-700 rounded-md p-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M9 12h6m2 8H7a2 2 0 01-2-2V6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v10a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Pending Docs</p>
      </div>
      <p class="text-xl font-bold text-slate-800"><?= number_format($pendingDocs) ?></p>
    </div>

    <!-- ✈️ Trips This Month -->
    <div class="flex items-center justify-between bg-gradient-to-r from-emerald-50 to-emerald-100 border border-emerald-200 rounded-lg px-3 py-2.5 hover:shadow-sm transition-all duration-200">
      <div class="flex items-center gap-2.5">
        <div class="bg-emerald-200 text-emerald-700 rounded-md p-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
        </div>
        <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Trips This Month</p>
      </div>
      <p class="text-xl font-bold text-slate-800"><?= number_format($tripsThisMonth) ?></p>
    </div>

  </div>

  <!-- 📊 Dashboard Link -->
  <div class="pt-2 text-center">
    <a href="../admin/audit.php" class="text-xs text-sky-600 hover:text-sky-700 font-semibold uppercase tracking-wider transition-colors hover:underline">
      View Detailed Dashboard →
    </a>
  </div>
</div>

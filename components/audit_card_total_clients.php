<?php
require_once __DIR__ . '/../actions/db.php';

// 🧮 Fetch total active clients
$clientCardStmt = $conn->prepare("SELECT COUNT(*) AS total FROM clients WHERE status != 'Trip Completed'");
$clientCardStmt->execute();
$clientCardResult = $clientCardStmt->get_result();
$clientCardData = $clientCardResult->fetch_assoc();
$totalClients = $clientCardData['total'] ?? 0;
?>

<div class="w-full flex items-center justify-between h-auto">
  <div>
    <p class="text-sm font-medium text-white uppercase tracking-wide">
      Active Clients
    </p>
    <p class="text-3xl font-bold text-white">
      <?= number_format($totalClients) ?>
    </p>
        <p class="text-xs font-semibold text-white mt-1">
      Current active clients
    </p>
  </div>
  <div class="bg-blue-100 text-blue-600 rounded-full p-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
      <path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
    </svg>
  </div>
</div>
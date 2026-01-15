<?php
require_once __DIR__ . '/../actions/db.php';

// 🧮 Fetch total trips completed
$completedTripStmt = $conn->prepare("SELECT COUNT(*) AS total FROM clients WHERE status = 'Trip Completed'");
$completedTripStmt->execute();
$completedTripResult = $completedTripStmt->get_result();
$completedTripData = $completedTripResult->fetch_assoc();
$totalTripsCompleted = $completedTripData['total'] ?? 0;

// 📅 Get current month name
$currentMonth = date('F');
?>

<div class="w-full flex items-center justify-between h-auto">
  <div>
    <p class="text-sm font-medium text-white uppercase tracking-wide">
      Trips Completed
    </p>
    <p class="text-3xl font-bold text-white">
      <?= number_format($totalTripsCompleted) ?>
    </p>
    <p class="text-xs font-semibold text-white mt-1">
      Month of <?= $currentMonth ?>
    </p>
  </div>
  <div class="bg-green-100 text-green-600 rounded-full p-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
    </svg>
  </div>
</div>
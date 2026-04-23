<?php
// audit_card_client_last_activity.php

require_once __DIR__ . '/../actions/db.php';

// Query clients with processing_type = 'booking', ordered by last_activity DESC, limit to 30
$stmt = $conn->prepare("
  SELECT id, full_name, last_activity
  FROM clients
  WHERE processing_type = 'booking' AND last_activity IS NOT NULL
  ORDER BY last_activity DESC
  LIMIT 30
");
$stmt->execute();
$result = $stmt->get_result();
$clients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h3 class="text-lg font-bold text-gray-900">Client Last Activity (Travel Booking)</h3>
      <p class="text-sm text-gray-500 mt-1">Recent activity from clients in travel booking</p>
    </div>
  </div>

  <?php if (empty($clients)): ?>
    <div class="text-center py-8 text-gray-400">
      <p>No client activity data available</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($clients as $client): ?>
        <div class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <span class="text-sm font-semibold text-blue-600">
                  <?= strtoupper(substr($client['full_name'], 0, 1)) ?>
                </span>
              </div>
              <div>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($client['full_name']) ?></span>
                <br>
                <small class="text-gray-500">ID: <?= htmlspecialchars($client['id']) ?></small>
              </div>
            </div>
          <span class="text-sm text-gray-600">
            <?= date('Y-m-d', strtotime($client['last_activity'])) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
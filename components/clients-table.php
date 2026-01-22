<?php
require_once '../includes/empty_state_map.php';

$isAdmin = $isAdmin ?? false;
$clients = $clients ?? [];
$tableTitle = $isAdmin ? 'Active Clients — Bookings' : 'Client Bookings';

// 🧠 Detect if this is an AJAX reload or a clean reload
$isAjaxReload = basename($_SERVER['SCRIPT_NAME']) === 'reload_clients_table.php';
$isCleanReload = $isCleanReload ?? false;

// 🔍 Filter and sort setup
$sort = ($isAjaxReload || $isCleanReload) ? null : ($_GET['sort'] ?? null);
$order = ($isAjaxReload || $isCleanReload) ? 'desc' : ($_GET['order'] ?? 'desc');
$search = ($isAjaxReload || $isCleanReload) ? '' : trim($_GET['search'] ?? '');
$page = ($isAjaxReload || $isCleanReload) ? 1 : (isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1);

// 🧮 Status priority map
if (!function_exists('getStatusPriority')) {
  function getStatusPriority($status) {
    $order = [
      'trip completed' => 8,
      'confirmed' => 7,
      'trip ongoing' => 6,
      'awaiting docs' => 5,
      'resubmit files' => 4,
      'under review' => 3,
      'cancelled' => 2,
      'no assigned package' => 1
    ];
    return $order[strtolower(trim($status))] ?? 0;
  }
}

// 🔎 Apply search filter
if ($search !== '') {
  $clients = array_filter($clients, function ($client) use ($search) {
    return stripos($client['full_name'] ?? '', $search) !== false;
  });
}

// 🔃 Apply status sort
if ($sort === 'status') {
  usort($clients, function ($a, $b) use ($order) {
    $priorityA = getStatusPriority($a['status'] ?? '');
    $priorityB = getStatusPriority($b['status'] ?? '');
    return $order === 'asc' ? $priorityA - $priorityB : $priorityB - $priorityA;
  });
}

// 📄 Pagination
$limit = 6;
$offset = ($page - 1) * $limit;
$totalClients = count($clients);
$paginatedClients = array_slice($clients, $offset, $limit);
$totalPages = ceil($totalClients / $limit);

// Debug: Log client data
error_log("DEBUG: clients-table.php - Total clients: $totalClients, Paginated clients: " . count($paginatedClients));
?>


<div id="clients-table-container">

<section class="bg-white p-4 md:p-6 rounded-lg shadow-sm border border-gray-200">
  <!-- Header with Title and Refresh -->
  <div class="flex items-center justify-between gap-2 mb-4">
    <div class="flex items-center gap-2">
      <h3 class="text-base md:text-lg font-semibold text-gray-800"><?= htmlspecialchars($tableTitle) ?></h3>

      <!-- 🔄 Inline Refresh Button -->
      <button
        type="button"
        onclick="manualClientTableReload()"
        class="text-sky-600 hover:text-sky-800 transition p-1"
        title="Refresh Table"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4v6h6M20 20v-6h-6M5.64 5.64a9 9 0 0112.72 0M18.36 18.36a9 9 0 01-12.72 0" />
        </svg>
      </button>
    </div>

    <?php if ($isAdmin): ?>
      <button @click="showAddClientModal = true; step = 1"
              class="backdrop-blur-sm bg-sky-500 text-white px-3 md:px-4 py-2 rounded hover:bg-sky-400 transition text-xs md:text-sm font-medium whitespace-nowrap"
              aria-label="Add New Guest">
        + Add New Guest
      </button>
    <?php endif; ?>
  </div>

  <!-- Search Form (stacks on mobile) -->
  <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2 mb-4">
    <form method="GET" class="flex flex-col md:flex-row items-stretch md:items-center gap-2 flex-1">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search name..."
             class="border border-gray-300 rounded px-3 py-2 md:py-1 text-sm focus:ring-sky-500 focus:border-sky-500 flex-1 md:flex-none" />

      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
      <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">

      <button type="submit" class="bg-sky-600 text-white md:bg-transparent md:text-sky-600 px-3 py-2 md:py-1 rounded md:rounded-none text-sm underline hover:bg-sky-500 md:hover:bg-transparent md:hover:text-sky-500 transition font-medium md:font-normal">
        Search
      </button>

      <a href="?sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>"
         class="text-center text-sm underline py-2 md:py-1 <?= $search ? 'text-gray-500 hover:text-gray-700' : 'text-gray-300 cursor-default' ?>">
        Clear
      </a>
    </form>
  </div>

  <div class="overflow-x-auto -mx-4 md:mx-0 md:overflow-x-auto">
<?php if (empty($clients)): ?>
  <div class="flex flex-col items-center justify-center py-12 text-gray-500">
    <?php echo getEmptyStateSvg('no-clients-found'); ?>
    <h3 class="text-base italic font-semibold text-sky-700 mb-1">No Clients Yet</h3>
    <p class="text-sm italic text-sky-700 text-center max-w-sm">
      You haven’t added any client records yet. Use the <strong>Add Client</strong> button above to get started and begin managing your bookings and profiles.
    </p>
  </div>
<?php else: ?>
      <div class="rounded-lg border overflow-hidden min-w-full">
<table class="w-full text-xs md:text-sm text-left">
  <thead class="bg-blue-50 text-gray-500 font-medium text-center">
    <tr>
      <th scope="col" class="p-2 md:p-3">Client Name</th>
      <th scope="col" class="p-2 md:p-3 hidden sm:table-cell">Booking No.</th>
      <th scope="col" class="p-2 md:p-3 hidden md:table-cell">Date</th>
      <th scope="col" class="p-2 md:p-3">
        <div class="flex items-center justify-center gap-1">
          <span class="hidden sm:inline">Status</span>
          <a href="?sort=status&order=<?= $sort === 'status' && $order === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>"
             class="text-sky-600 hover:text-sky-500 transition" aria-label="Sort by Status">
            <?php if ($sort === 'status' && $order === 'asc'): ?>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            <?php else: ?>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            <?php endif; ?>
          </a>
        </div>
      </th>
      <th scope="col" class="p-2 md:p-3 text-center">Actions</th>
    </tr>
  </thead>
  <tbody class="divide-y divide-gray-200">
    <?php foreach ($paginatedClients as $client): ?>
      <?php
        $fullName = $client['full_name'] ?? '—';
        $photoFile = trim($client['client_profile_photo'] ?? '');
        $avatarSrc = $photoFile && file_exists(__DIR__ . '/../uploads/client_profiles/' . $photoFile)
          ? '../uploads/client_profiles/' . rawurlencode($photoFile)
          : '../images/default_client_profile.png';
        $bookingNumber = $client['booking_number'] ?? '—';
        $dateRange = $client['trip_date_range'] ?? '—';
        $statusText = trim($client['status'] ?? '') ?: 'Pending';
      ?>
      <tr 
        class="text-gray-700 hover:text-sky-600 transition-colors odd:bg-white even:bg-sky-50"
        data-client-id="<?= (int) $client['id'] ?>"
      >
        <td class="p-2 md:p-4">
          <div class="flex items-center gap-2">
            <img src="<?= $avatarSrc ?>" alt="Profile photo of <?= htmlspecialchars($fullName) ?>"
                 class="w-5 h-5 md:w-6 md:h-6 rounded-full object-cover border-2 border-white shadow-md shadow-gray-100" loading="lazy" />
            <span class="font-medium text-xs md:text-sm"><?= htmlspecialchars($fullName) ?></span>
          </div>
        </td>

        <td class="p-2 md:p-4 hidden sm:table-cell text-xs md:text-sm"><?= htmlspecialchars($bookingNumber) ?></td>
        <td class="p-2 md:p-4 hidden md:table-cell text-xs md:text-sm"><?= htmlspecialchars($dateRange) ?></td>
        <td class="p-2 md:p-4 text-center">
          <span class="px-2 md:px-3 py-1 text-xs font-semibold rounded-full <?= getStatusBadgeClass($statusText) ?>">
            <?= htmlspecialchars($statusText) ?>
          </span>
        </td>
        <td class="p-2 md:p-4 text-center">
          <form action="../admin/view_client.php" method="GET">
            <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
            <button type="submit"
                    class="text-sky-500 font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-sky-300"
                    aria-label="View profile for <?= htmlspecialchars($fullName) ?>">
              View
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

      </div>

      <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-6">
          <nav class="inline-flex space-x-1 text-sm font-medium">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
<a href="?page=<?= $i ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>&search=<?= urlencode($search) ?>"
   class="px-3 py-1 rounded border <?= $i === $page ? 'bg-sky-500 text-white border-sky-500' : 'bg-white text-sky-600 border-gray-300 hover:bg-sky-50' ?>">
  <?= $i ?>
</a>
<?php endfor; ?>
</nav>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
</section>
            </div>
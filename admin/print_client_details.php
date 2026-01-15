<?php
session_start();
date_default_timezone_set('Asia/Manila');

// ✅ Load dependencies
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// ✅ Validate client ID
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
if (!$client_id) {
  die("Client not specified.");
}

// ✅ Fetch client and assigned package info
$client_stmt = $conn->prepare("
  SELECT 
    c.*, 
    t.package_name, 
    t.package_description,
    t.price,
    t.day_duration,
    t.night_duration,
    t.tour_cover_image,
    t.inclusions_json
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();

if (!$client) {
  die("Client not found.");
}

// ✅ Build assigned package object
$assignedPackage = null;
if (!empty($client['assigned_package_id'])) {
  $assignedPackage = [
    'id'              => $client['assigned_package_id'],
    'name'            => $client['package_name'] ?? 'Untitled Package',
    'description'     => $client['package_description'] ?? '',
    'price'           => $client['price'] ?? 0,
    'duration_days'   => $client['day_duration'] ?? 0,
    'duration_nights' => $client['night_duration'] ?? 0,
    'inclusions'      => json_decode($client['inclusions_json'] ?? '[]', true) ?? [],
  ];
}

// ✅ Fetch itinerary
$itinerary_stmt = $conn->prepare("
  SELECT itinerary_json
  FROM client_itinerary
  WHERE client_id = ?
  LIMIT 1
");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_row = $itinerary_stmt->get_result()->fetch_assoc();

$parsedItinerary = json_decode($itinerary_row['itinerary_json'] ?? '[]', true) ?? [];
$total_days = count($parsedItinerary);
$total_nights = max(0, $total_days - 1);

// ✅ Format dates
$trip_start = $client['trip_date_start'] ?? null;
$trip_end = $client['trip_date_end'] ?? null;

$start_formatted = $trip_start ? date('F j, Y', strtotime($trip_start)) : 'Not Set';
$end_formatted = $trip_end ? date('F j, Y', strtotime($trip_end)) : 'Not Set';

// ✅ Paginate itinerary items (3-4 items per page)
$itemsPerPage = 3;
$itineraryPages = array_chunk($parsedItinerary, $itemsPerPage);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Details - <?= htmlspecialchars($client['full_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'jvb-blue': '#07528e',
          }
        }
      }
    }
  </script>
  <style>
    .print-only { display: none; }
    * { box-sizing: border-box; }

    @page {
      size: A4;
      margin: 0;
      counter-increment: page;
    }
    
    body {
      counter-reset: page;
      margin: 0;
      padding: 0;
      background: #f3f4f6;
    }
    
    /* Floating print button */
    .floating-print-btn {
      position: fixed;
      bottom: 30px;
      right: 30px;
      z-index: 1000;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
    }
    
    .floating-print-btn:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
    }

    @media screen {
      body {
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        background-color: #f3f4f6;
        padding: 20px;
        gap: 20px;
      }
      
      .print-page-container {
        background: white;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        width: 210mm;
        min-height: 297mm;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
      }
      
      .page-content {
        padding: 20mm;
        min-height: 297mm;
        background: white;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
      }
    }
    
    @media print {
      .no-print { display: none !important; }
      
      html, body { 
        width: 100%;
        margin: 0; 
        padding: 0; 
        background: white;
      }
      
      body {
        background: white;
        display: block;
        padding: 0;
        gap: 0;
      }
      
      .print-footer {
        position: fixed;
        left: 0;
        right: 0;
        background: white;
        z-index: 50;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        display: flex;
        bottom: 0;
        height: 50px;
        padding: 8px 20px;
        border-top: 1px solid #e5e7eb;
        align-items: center;
        justify-content: space-between;
        font-size: 11px;
      }
      
      .page-number::after {
        content: "Page " counter(page);
        font-weight: 600;
        color: #4b5563;
      }
      
      .print-page-container {
        width: 100%;
        margin: 0;
        padding: 0;
        page-break-after: always;
        page-break-inside: avoid;
        min-height: 0;
        box-shadow: none;
        border-radius: 0;
      }
      
      .print-page-container:last-child {
        page-break-after: avoid;
      }
      
      .page-content {
        padding: 20mm;
        margin: 0;
        background: white;
        border-radius: 0;
        min-height: 0;
        display: block;
      }
      
      .print-page-card {
        border: none !important;
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
      }
      
      .max-w-4xl {
        box-shadow: none !important;
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
      }
      
      .shadow-lg { box-shadow: none !important; }
      
      .rounded-lg { border-radius: 0 !important; }
      
      .print-logo {
        width: 80px !important;
        height: auto !important;
        top: 20mm !important;
        right: 20mm !important;
      }
      
      /* Force print colors for itinerary */
      .itinerary-header {
        background-color: #07528e !important;
        color: white !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      .itinerary-body {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      .page-break-inside-avoid {
        page-break-inside: avoid;
        break-inside: avoid;
      }
    }

    @media print {
      .print-only { display: flex !important; }
    }
  </style>
</head>

<body class="margin-0 padding-0">
  <!-- Floating Print Button -->
  <button class="floating-print-btn no-print px-6 py-3 bg-jvb-blue text-white rounded-full font-semibold hover:bg-[#053d6b] transition" onclick="window.print()">
    🖨️ Print / Save as PDF
  </button>

  <!-- Print footer (all pages) -->
  <div class="print-footer print-only">
    <div class="text-jvb-blue font-semibold">JV-B Travel & Tours</div>
    <div class="page-number text-sm"></div>
  </div>

  <!-- PAGE 1: CLIENT INFORMATION & TRIP DETAILS -->
  <div class="print-page-container w-full max-w-4xl mx-auto">
    <div class="page-content bg-white rounded-lg relative print-page-card">
      <!-- Logo -->
      <img src="../images/JVB_Logo.png" alt="JV-B Travel" class="absolute top-2 right-8 w-40 h-40 object-contain print-logo">
      
      <!-- Header -->
      <div class="border-b-4 border-jvb-blue pb-5 mb-8 pr-24">
        <div class="text-jvb-blue text-sm font-semibold uppercase tracking-wide mb-2">Client Details & Trip Information</div>
        <h1 class="text-4xl font-bold text-jvb-blue break-words"><?= htmlspecialchars($client['full_name']) ?></h1>
      </div>
      
      <!-- Two Column Layout: Client Information & Trip Details -->
      <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Section 1: Client Information -->
        <div class="page-break-inside-avoid">
          <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Client Information</h2>
          
          <div class="space-y-3">
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Full Name:</span>
              <span class="text-gray-900 break-words"><?= htmlspecialchars($client['full_name'] ?? 'N/A') ?></span>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Booking Number:</span>
              <span class="text-gray-900 break-words"><?= htmlspecialchars($client['booking_reference'] ?? 'N/A') ?></span>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Email Address:</span>
              <span class="text-gray-900 break-all"><?= htmlspecialchars($client['email'] ?? 'N/A') ?></span>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Phone Number:</span>
              <span class="text-gray-900"><?= htmlspecialchars($client['phone_number'] ?? 'N/A') ?></span>
            </div>
          </div>
        </div>
        
        <!-- Section 2: Trip Details -->
        <?php if ($assignedPackage): ?>
        <div class="page-break-inside-avoid">
          <div class="flex items-center justify-between pb-2 border-b-2 border-gray-200 mb-4">
            <h2 class="text-lg font-bold text-jvb-blue">Trip Details</h2>
            <?php 
            $status = $client['status'] ?? 'pending';
            $statusClasses = [
              'awaiting docs' => 'bg-yellow-100 text-yellow-800',
              'confirmed' => 'bg-green-100 text-green-800',
              'trip ongoing' => 'bg-blue-100 text-jvb-blue',
              'trip completed' => 'bg-green-200 text-green-900'
            ];
            $badgeClass = $statusClasses[strtolower($status)] ?? 'bg-gray-100 text-gray-800';
            ?>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase <?= $badgeClass ?>" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;"><?= htmlspecialchars($status) ?></span>
          </div>
          
          <div class="space-y-3">
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Package Name:</span>
              <span class="font-semibold text-gray-900 break-words"><?= htmlspecialchars($assignedPackage['name']) ?></span>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Trip Dates:</span>
              <span class="text-gray-900"><?= $start_formatted ?> - <?= $end_formatted ?></span>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Package Duration:</span>
              <div class="mt-1">
                <span class="inline-block px-2 py-1 bg-sky-100 text-sky-900 rounded-full font-semibold text-sm" style="-webkit-print-color-adjust: exact; print-color-adjust: exact;"><?= htmlspecialchars($assignedPackage['duration_days']) ?> Days / <?= htmlspecialchars($assignedPackage['duration_nights']) ?> Nights</span>
              </div>
            </div>
            
            <div class="flex flex-col">
              <span class="font-semibold text-gray-700 text-sm">Price*:</span>
              <span class="text-gray-900 font-bold text-lg">₱<?= number_format($assignedPackage['price'], 2) ?></span>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="page-break-inside-avoid">
          <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Trip Details</h2>
          <p class="text-gray-500 italic">No tour package assigned to this client.</p>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Package Inclusions (Full Width) -->
      <?php if ($assignedPackage && !empty($assignedPackage['inclusions'])): ?>
      <div class="mb-8">
        <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Package Inclusions</h2>
        <div class="space-y-3">
            <?php foreach ($assignedPackage['inclusions'] as $inclusion): ?>
              <?php if (is_array($inclusion)): ?>
                <div class="border-b border-gray-100 pb-3 last:border-0">
                  <div class="flex items-start gap-3">
                    <span class="text-xl flex-shrink-0 mt-0.5"><?= htmlspecialchars($inclusion['icon'] ?? '✓') ?></span>
                    <div class="flex-1 min-w-0">
                      <div class="font-semibold text-jvb-blue text-sm break-words"><?= htmlspecialchars($inclusion['title'] ?? '') ?></div>
                      <?php if (!empty($inclusion['desc'])): ?>
                        <div class="text-gray-600 text-sm mt-1 break-words"><?= htmlspecialchars($inclusion['desc']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="border-b border-gray-100 pb-3 last:border-0">
                  <div class="flex items-center gap-3">
                    <span class="text-green-600 font-bold flex-shrink-0">✓</span>
                    <span class="text-gray-900 text-sm flex-1 break-words"><?= htmlspecialchars($inclusion) ?></span>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- Footer - Page 1 -->
      <div class="border-t border-gray-200 pt-4 mt-8 flex flex-col gap-4 text-xs">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
          <div class="flex-1 max-w-xl">
            <p class="text-gray-500 text-xs break-words">*Price shown is indicative and may not reflect the full or final amount paid. This system does not track payments made outside the platform. Please refer to official invoices and receipts for accurate payment records.</p>
          </div>
          <div class="text-right text-gray-600 flex-shrink-0">
            <p class="font-semibold">Generated at:</p>
            <p><?= date('F j, Y', time()) ?></p>
            <p><?= date('g:i A', time()) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- PAGE 2: ITINERARY START -->
  <?php if (!empty($parsedItinerary)): ?>
  <div class="print-page-container w-full max-w-4xl mx-auto">
    <div class="page-content bg-white rounded-lg relative print-page-card">
      <!-- Logo -->
      <img src="../images/JVB_Logo.png" alt="JV-B Travel" class="absolute top-2 right-8 w-40 h-40 object-contain print-logo">
      
      <!-- Header -->
      <div class="border-b-4 border-jvb-blue pb-5 mb-8 pr-24">
        <div class="text-jvb-blue text-sm font-semibold uppercase tracking-wide mb-2">Client Details & Trip Information</div>
        <h1 class="text-4xl font-bold text-jvb-blue break-words"><?= htmlspecialchars($client['full_name']) ?></h1>
      </div>
      
      <!-- Section 3: Itinerary -->
      <div class="mb-8">
        <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Trip Itinerary</h2>
        
        <div class="space-y-4">
          <?php foreach ($itineraryPages[0] ?? [] as $day): ?>
            <div class="page-break-inside-avoid">
              <div class="itinerary-header bg-jvb-blue text-white font-bold px-4 py-2 rounded-t">
                Day <?= htmlspecialchars($day['day_number'] ?? $day['day'] ?? 'N/A') ?> 
                <?php if (!empty($day['day_title'])): ?>
                  - <?= htmlspecialchars($day['day_title']) ?>
                <?php endif; ?>
              </div>
              
              <div class="itinerary-body border border-gray-200 border-t-0 rounded-b p-4 bg-gray-50">
                <?php if (!empty($day['activities'])): ?>
                  <div class="space-y-2">
                    <?php foreach ($day['activities'] as $activity): ?>
                      <div class="flex gap-3 items-start">
                        <?php if (!empty($activity['time'])): ?>
                          <span class="text-jvb-blue font-semibold text-sm w-16 flex-shrink-0"><?= htmlspecialchars($activity['time']) ?></span>
                        <?php else: ?>
                          <span class="text-gray-400 font-semibold text-sm w-16 flex-shrink-0">--:--</span>
                        <?php endif; ?>
                        <span class="text-gray-900 text-sm flex-1 break-words"><?= htmlspecialchars($activity['title'] ?? '') ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php elseif (!empty($day['location']) || !empty($day['description'])): ?>
                  <?php if (!empty($day['location'])): ?>
                    <div class="text-gray-600 text-sm mb-2">📍 <?= htmlspecialchars($day['location']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($day['description'])): ?>
                    <div class="text-gray-900 text-sm"><?= htmlspecialchars($day['description']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="text-gray-500 italic text-sm">No activities scheduled</p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="border-t border-gray-200 pt-4 flex flex-col gap-4 text-xs">
        <div class="flex justify-end">
        </div>
      </div>
    </div>
  </div>
  
  <!-- PAGES 3+: ADDITIONAL ITINERARY PAGES -->
  <?php for ($pageNum = 1; $pageNum < count($itineraryPages); $pageNum++): ?>
  <div class="print-page-container w-full max-w-4xl mx-auto">
    <div class="page-content bg-white rounded-lg relative print-page-card">
      <!-- Logo -->
      <img src="../images/JVB_Logo.png" alt="JV-B Travel" class="absolute top-2 right-8 w-40 h-40 object-contain print-logo">
      
      <!-- Header -->
      <div class="border-b-4 border-jvb-blue pb-5 mb-8 pr-24">
        <div class="text-jvb-blue text-sm font-semibold uppercase tracking-wide mb-2">Client Details & Trip Information</div>
        <h1 class="text-4xl font-bold text-jvb-blue break-words"><?= htmlspecialchars($client['full_name']) ?></h1>
      </div>
      
      <!-- Section: Itinerary Continuation -->
      <div class="mb-8">
        <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Trip Itinerary (continued)</h2>
        
        <div class="space-y-4">
          <?php foreach ($itineraryPages[$pageNum] as $day): ?>
            <div class="page-break-inside-avoid">
              <div class="itinerary-header bg-jvb-blue text-white font-bold px-4 py-2 rounded-t">
                Day <?= htmlspecialchars($day['day_number'] ?? $day['day'] ?? 'N/A') ?> 
                <?php if (!empty($day['day_title'])): ?>
                  - <?= htmlspecialchars($day['day_title']) ?>
                <?php endif; ?>
              </div>
              
              <div class="itinerary-body border border-gray-200 border-t-0 rounded-b p-4 bg-gray-50">
                <?php if (!empty($day['activities'])): ?>
                  <div class="space-y-2">
                    <?php foreach ($day['activities'] as $activity): ?>
                      <div class="flex gap-3 items-start">
                        <?php if (!empty($activity['time'])): ?>
                          <span class="text-jvb-blue font-semibold text-sm w-16 flex-shrink-0"><?= htmlspecialchars($activity['time']) ?></span>
                        <?php else: ?>
                          <span class="text-gray-400 font-semibold text-sm w-16 flex-shrink-0">--:--</span>
                        <?php endif; ?>
                        <span class="text-gray-900 text-sm flex-1 break-words"><?= htmlspecialchars($activity['title'] ?? '') ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php elseif (!empty($day['location']) || !empty($day['description'])): ?>
                  <?php if (!empty($day['location'])): ?>
                    <div class="text-gray-600 text-sm mb-2">📍 <?= htmlspecialchars($day['location']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($day['description'])): ?>
                    <div class="text-gray-900 text-sm"><?= htmlspecialchars($day['description']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="text-gray-500 italic text-sm">No activities scheduled</p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="border-t border-gray-200 pt-4 flex flex-col gap-4 text-xs">
        <div class="flex justify-end">
        </div>
      </div>
    </div>
  </div>
  <?php endfor; ?>
  <?php else: ?>
  <!-- PAGE 2: NO ITINERARY -->
  <div class="print-page-container w-full max-w-4xl mx-auto">
    <div class="page-content bg-white rounded-lg relative print-page-card">
      <!-- Logo -->
      <img src="../images/JVB_Logo.png" alt="JV-B Travel" class="absolute top-2 right-8 w-40 h-40 object-contain print-logo">
      
      <!-- Header -->
      <div class="border-b-4 border-jvb-blue pb-5 mb-8 pr-24">
        <div class="text-jvb-blue text-sm font-semibold uppercase tracking-wide mb-2">Client Details & Trip Information</div>
        <h1 class="text-4xl font-bold text-jvb-blue break-words"><?= htmlspecialchars($client['full_name']) ?></h1>
      </div>
      
      <!-- Section 3: Itinerary -->
      <div class="mb-8">
        <h2 class="text-lg font-bold text-jvb-blue pb-2 border-b-2 border-gray-200 mb-4">Trip Itinerary</h2>
        <p class="text-gray-500 italic">No itinerary has been confirmed for this trip.</p>
      </div>
      
      <!-- Footer -->
      <div class="border-t border-gray-200 pt-4 flex flex-col gap-4 text-xs">
        <div class="flex justify-end">
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</body>
</html>

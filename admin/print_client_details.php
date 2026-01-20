<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Mpdf\Mpdf;

// 1. DATA FETCHING LOGIC
$client_id = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
if (!$client_id) {
    exit('Client not specified.');
}

$client_stmt = $conn->prepare("
  SELECT 
    c.*, 
    c.booking_number,
    t.package_name, 
    t.package_description,
    t.price,
    t.day_duration,
    t.night_duration,
    t.tour_cover_image,
    t.inclusions_json,
    t.exclusions_json
  FROM clients c
  LEFT JOIN tour_packages t ON c.assigned_package_id = t.id
  WHERE c.id = ?
");
$client_stmt->bind_param("i", $client_id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();

if (!$client) {
    exit('Client not found.');
}

$assignedPackage = null;
if (!empty($client['assigned_package_id'])) {

    $assignedPackage = [
        'id'              => $client['assigned_package_id'],
        'name'            => $client['package_name'] ?? 'Untitled Package',
        'description'     => $client['package_description'] ?? '',
        'hotel'           => $client['hotel'] ?? 'N/A',
        'room_type'       => $client['room_type'] ?? 'N/A',
        'flight_details'  => $client['flight_details'] ?? 'N/A',
        'companions'      => json_decode($client['companions_json'] ?? '[]', true) ?? [],
        'price'           => $client['price'] ?? 0,
        'inclusions'      => json_decode($client['inclusions_json'] ?? '[]', true) ?? [],
        'exclusions'      => json_decode($client['exclusions_json'] ?? '[]', true) ?? [],
    ];
}

$itinerary_stmt = $conn->prepare("SELECT itinerary_json FROM client_itinerary WHERE client_id = ? LIMIT 1");
$itinerary_stmt->bind_param("i", $client_id);
$itinerary_stmt->execute();
$itinerary_row = $itinerary_stmt->get_result()->fetch_assoc();
$parsedItinerary = json_decode($itinerary_row['itinerary_json'] ?? '[]', true) ?? [];

$start_formatted = $client['trip_date_start'] ? date('F j, Y', strtotime($client['trip_date_start'])) : 'Not Set';
$end_formatted = $client['trip_date_end'] ? date('F j, Y', strtotime($client['trip_date_end'])) : 'Not Set';

// 2. START OUTPUT BUFFERING

ob_start(); 
?>
<style>
body { font-family: sans-serif; color: #333; font-size: 10pt; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
th, td { padding: 10px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.logo { width: 150px; margin-bottom: 10px; }
.itinerary-time { width: 80px; font-weight: bold; color: #0056b3; font-size: 9pt; }
.label { font-weight: bold; color: #6f6f6f; width: 30%; }
.label_content { padding: 12px; font-weight: bold; color: #4a5568; font-size: 14px; font-weight: bold; }
.header-title { text-transform: uppercase; font-weight: bold; letter-spacing: 1px; margin-bottom: 28px; font-size: 13pt; color: #003e7f; }
.header-client { font-weight: bold; font-size: 24pt; margin: 0 0 32px 0; color: #0056b3; }
.header-thankyou { font-weight: 600; margin-top: 36px; margin-bottom: 10px; }
.footer-contact { margin-bottom: 3px; }
</style>



<table style="border: none;">
    <tr>
        <td style="border: none; width: 70%; vertical-align: top;">
            <div class="header-title">
                Hotel and Booking Confirmation
            </div>
            <br>
            <div class="header-client">
                <?= htmlspecialchars($client['full_name']) ?>
            </div>
            <div class="header-thankyou">
                Thank you so much for booking with us! We hope you have a wonderful trip.
            </div>
        </td>
        <td style="border: none; width: 30%; text-align: right; vertical-align: top;">
            <img src="../images/JVB_Logo.png" class="logo">
        </td>
    </tr>
</table>

<div style="border-bottom: 4px solid #0056b3; margin-bottom: 14px;"></div>

<table>
    <thead>
        <tr>
            <th colspan="2" style="background: #0056b3; color: #fff; text-align: center;">BOOKING CONFIRMATION</th>
        </tr>
    </thead>
    <tbody>
        <!-- Confirmation No spans both columns -->
        <tr style="background-color: #f0f9ff;">
            <td colspan="2" style="text-align: left;">
                <span class="label">CONFIRMATION NO:</span>
                <span style="font-size: 16pt; font-weight: bold; color: #0056b3;">
                    <?= htmlspecialchars($client['booking_number'] ?? 'N/A') ?>
                </span>
            </td>
        </tr>
        <!-- Hotlines side by side -->
        <tr>
            <td class="label">
                Transfer and Tour Hotline:<br>
                <span class="label_content"><?= htmlspecialchars($client['transfer_tour_hotline'] ?? 'N/A') ?></span>
            </td>
            <td class="label">
                Travel Agency Emergency Hotline:<br>
                <span class="label_content">+63 939 347 2015</span>
            </td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr><th colspan="2" style="background: #0056b3; color: #fff; text-align: center;">GUEST AND PACKAGE DETAILS</th></tr>
    </thead>
    <tbody>
        <tr style="background-color: #f0f9ff;">
            <td class="label">Guest/s Names</td>
            <td class="label_content">
                <div>
                    <strong><?= htmlspecialchars($client['full_name'] ?? 'N/A') ?></strong>
                </div>
<?php
$companions = [];

if (!empty($client['companions_json'])) {
    $companions = json_decode($client['companions_json'], true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($companions)) {
        $companions = [];
    }
}

if (!empty($companions)) {
    foreach ($companions as $companion) {
        echo '<div style="margin-left:18px; padding:2px;">' . htmlspecialchars($companion) . '</div>';
    }
}
?>
            </td>
        </tr>
        <tr>
            <td class="label">Travel Date</td>
            <td class="label_content"><?= $start_formatted ?> - <?= $end_formatted ?></td>
        </tr>
        <tr style="background-color: #f0f9ff;">
            <td class="label">Tour Package</td>
            <td class="label_content"><?= htmlspecialchars($assignedPackage['name'] ?? 'N/A') ?></td>
        </tr>
        <tr style="background-color: #f0f9ff;">
            <td class="label">Hotel</td>
            <td class="label_content"><?= htmlspecialchars($assignedPackage['hotel'] ?? 'N/A') ?></td>
        </tr>
        <tr style="background-color: #f0f9ff;">
            <td class="label">Room Type</td>
            <td class="label_content"><?= htmlspecialchars($assignedPackage['room_type'] ?? 'N/A') ?></td>
        </tr>
        <tr style="background-color: #f0f9ff;">
            <td class="label">Flight Details</td>
            <td class="label_content"><?= nl2br(htmlspecialchars($assignedPackage['flight_details'] ?: 'N/A')) ?></td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th style="background: #0056b3; color: #fff; text-align: center; width: 50%;">INCLUSIONS</th>
            <th style="background: #0056b3; color: #fff; text-align: center; width: 50%;">EXCLUSIONS</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $inclusions = $assignedPackage['inclusions'] ?? [];
        $exclusions = $assignedPackage['exclusions'] ?? [];
        $maxRows = max(count($inclusions), count($exclusions));
        for ($i = 0; $i < $maxRows; $i++):
            $rowBg = $i % 2 === 0 ? 'background-color: #f0f9ff;' : '';
        ?>
        <tr style="<?= $rowBg ?>">
            <td style="width: 50%;">
                <?php if (isset($inclusions[$i])): $inc = $inclusions[$i]; ?>
                    <span style="color: #38CB89; font-weight: bold; font-size: 13pt; vertical-align: middle;">&#10003;</span>
                    <strong><?= htmlspecialchars($inc['title'] ?? $inc) ?></strong>
                    <div style="font-size: 8pt; color: #666; margin-left: 20px;"><?= htmlspecialchars($inc['desc'] ?? '') ?></div>
                <?php endif; ?>
            </td>
            <td style="width: 50%;">
                <?php if (isset($exclusions[$i])): $exc = $exclusions[$i]; ?>
                    <span style="color: #FF5630; font-weight: bold; font-size: 13pt; vertical-align: middle;">&#10007;</span>
                    <strong><?= htmlspecialchars($exc['title'] ?? $exc) ?></strong>
                    <div style="font-size: 8pt; color: #666; margin-left: 20px;"><?= htmlspecialchars($exc['desc'] ?? '') ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endfor; ?>
    </tbody>
</table>

<pagebreak />
<table>
    <thead>
        <tr><th colspan="2" style="background: #0056b3; color: #fff; text-align: center;">ITINERARY</th></tr>
    </thead>
    <tbody>
        <?php if (!empty($parsedItinerary)): ?>
            <?php foreach ($parsedItinerary as $idx => $day): 
                $rowBg = $idx % 2 === 0 ? 'background-color: #f0f9ff;' : '';
                $dayLabel = "Day " . ($day['day_number'] ?? ($idx + 1));
            ?>
            <tr style="<?= $rowBg ?> page-break-inside: avoid;">
                <td class="label"><?= $dayLabel ?></td>
                <td>
                    <?php if (!empty($day['day_title'])): ?>
                        <div style="font-weight: bold; margin-bottom: 5px; color: #0056b3;"><?= htmlspecialchars($day['day_title']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($day['activities'])): ?>
                        <?php foreach ($day['activities'] as $act): ?>
                            <div style="margin-bottom: 4px;">
                                <span class="itinerary-time"><?= htmlspecialchars($act['time'] ?? '--:--') ?></span>
                                <span><?= htmlspecialchars($act['title'] ?? '') ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="font-size: 9pt;"><?= htmlspecialchars($day['description'] ?? 'No activities scheduled') ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2" style="text-align: center; font-style: italic;">No itinerary available</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<table style="width:100%; border-collapse:collapse; font-size:10pt; color:#666; margin-top:10px;">
  <thead>
    <tr>
      <th colspan="2" style="background: #0056b3; color: #fff; text-align: center; padding: 6px;">
        REMARKS AND IMPORTANT NOTES
      </th>
    </tr>
  </thead>
  <tbody>
    <?php
    $remarks = [
      "In case of emergency, you may ask the hotel to call the emergency contact number indicated above.",
      "Last-minute changes for the time of pick-up from the hotel will be coordinated but not guaranteed.",
      "All passengers must register at <a href=\"https://etravel.gov.ph/\" style=\"color:#0066cc; text-decoration:underline;\">https://etravel.gov.ph/</a> within 72 hours before their flight. Please save the generated QR code to present at airline check-in and immigration.",
      "Any incidental/security deposit will be on the guests' account.",
      "Be sure to take care of your own personal belongings when you get off the van/bus and during touring. Tour operators will not take any responsibility.",
      "You are responsible for ensuring compliance with the immigration, customs, or other legal requirements of the countries of your destination. In case of failure to comply, there won't be a refund. Kindly ensure that for international destinations, you possess a valid passport with at least six (6) months validity and the applicable valid visas (if applicable).",
      "Any unused tour services are non-refundable.",
      "In case of emergency, you may ask the hotel to call the emergency contact number indicated above."
    ];

    foreach ($remarks as $i => $remark):
      $rowBg = $i % 2 === 0 ? 'background-color:#f0f9ff;' : '';
    ?>
    <tr style="<?= $rowBg ?>">
      <td style="width:20px; text-align:center; vertical-align:top; padding:2px;">
        <span style="color:#4a5568; font-weight:bold; font-size:12pt;">&#8226;</span>
      </td>
      <td class="label_content" style="padding:4px; text-align:left;">
        <?= $remark ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
// 3. CAPTURE COMPLETE HTML
$html = ob_get_clean();

// 4. GENERATE PDF
$mpdf = new Mpdf([
    'format' => 'legal',
    'margin_top' => 15,
    'margin_bottom' => 25, // Space for footer
    'margin_left' => 10,
    'margin_right' => 10,
]);

// Set global Footer (Appears on every page)
$footerHtml = '
<table style="width: 100%; border: none; font-size: 10pt; color: #666;">
    <tr>
        <!-- Left side contact details -->
        <td style="color: #002365; font-weight: 500; border: none; width: 38%; vertical-align: bottom; text-align: left;">
            <div style="line-height: 1.6;">
                <img src="../images/jvb_document_print/icon_phone.png" style="width:13px; vertical-align:middle; margin-right:6px;"> 047 272 0168 | +63 939 347 2015
            </div>
            <div style="line-height: 1.6;">
                <img src="../images/jvb_document_print/icon_pin.png" style="width:13px; vertical-align:top; margin-right:6px; margin-top:2px;"><span style="display:inline-block; vertical-align:top;">&nbsp;15 Basa St, West Tapinac,<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Olongapo City PH 2200</span>
            </div>
            <div style="line-height: 1.6;">
                <img src="../images/jvb_document_print/icon_mail.png" style="width:13px; vertical-align:middle; margin-right:6px;"> reservations.jvandbtravel@gmail.com
            </div>
        </td>

        <!-- Middle accreditation badge -->
        <td style="border: none; width: 20%; text-align: center; vertical-align: bottom;">
            <img src="../images/dot_accreditation_badge.png" style="width: 200px; margin-top: 5px;">
        </td>

        <!-- Right side contact details (mirrored, aligned right, icons on right side) -->
        <td style="color: #002365; font-weight: 500; border: none; width: 38%; vertical-align: bottom; text-align: right;">
            <div style="line-height: 1.6;">
                047 272 0168 | +63 935 205 2449 <img src="../images/jvb_document_print/icon_phone.png" style="width:13px; vertical-align:middle; margin-left:6px;">
            </div>
            <div style="line-height: 1.6;">
                <span style="display:inline-block; vertical-align:top; text-align:right;">81 - 14th St. New Kalalake,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>Olongapo City PH 2200</span> <img src="../images/jvb_document_print/icon_pin.png" style="width:13px; vertical-align:top; margin-left:6px; margin-top:2px;">
            </div>
            <div style="line-height: 1.6;">
                newkalalake.jvandbtravel@gmail.com <img src="../images/jvb_document_print/icon_mail.png" style="width:13px; vertical-align:middle; margin-left:6px;">
            </div>
        </td>
    </tr>
</table>';

$mpdf->SetHTMLFooter($footerHtml);

$mpdf->SetTitle('Booking Confirmation - ' . $client['full_name']);


$mpdf->WriteHTML($html);

$filename = 'JVB_Booking_' . ($client['booking_number'] ?? 'Ref') . '.pdf';
$mpdf->Output($filename, 'I');
exit;
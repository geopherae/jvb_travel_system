<?php
$today           = date('j');
$currentMonth    = date('F');
$currentYear     = date('Y');
$currentMonthNum = date('m');
$currentYearNum  = date('Y');
$daysInMonth     = date('t');
$firstDayOfMonth = date('w', strtotime(date('Y-m-01')));
$tripStartDay    = null;
$tripEndDay      = null;

if (isset($_SESSION['client_id'])) {
  $client_id = $_SESSION['client_id'];
  $tripQuery = $conn->prepare("
    SELECT trip_date_start, trip_date_end
    FROM clients
    WHERE id = ?
    LIMIT 1
  ");
  $tripQuery->bind_param("i", $client_id);
  $tripQuery->execute();
  $result = $tripQuery->get_result();
  if ($row = $result->fetch_assoc()) {
    $startObj = $row['trip_date_start'] ? date_create($row['trip_date_start']) : null;
    $endObj   = $row['trip_date_end'] ? date_create($row['trip_date_end']) : null;

    if ($startObj && $endObj) {
      if (date_format($startObj, 'Y') === $currentYearNum && date_format($startObj, 'm') === $currentMonthNum) {
        $tripStartDay = (int) date_format($startObj, 'j');
      }
      if (date_format($endObj, 'Y') === $currentYearNum && date_format($endObj, 'm') === $currentMonthNum) {
        $tripEndDay = (int) date_format($endObj, 'j');
      }
    }
  }
  $result->free();
  $tripQuery->close();
}
?>

<section x-data class="bg-white border rounded shadow-sm p-4 text-sm">
  <h3 class="text-md font-semibold text-primary mb-2">
    <?= "$currentMonth $currentYear" ?> · Trip Calendar
  </h3>
  <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-600">

    <!-- Weekdays -->
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?>
      <div class="font-semibold"><?= $weekday ?></div>
    <?php endforeach; ?>

    <!-- Padding -->
    <?php for ($i = 0; $i < $firstDayOfMonth; $i++): ?>
      <div></div>
    <?php endfor; ?>

    <!-- Days -->
    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
      <?php
        $isToday = $d == $today;
        $inTrip  = $tripStartDay && $tripEndDay && $d >= $tripStartDay && $d <= $tripEndDay;

        // Shape adjustment
        $rounded = '';
        if ($d == $tripStartDay && $tripStartDay !== $tripEndDay) {
          $rounded = 'rounded-l-full';
        } elseif ($d == $tripEndDay && $tripStartDay !== $tripEndDay) {
          $rounded = 'rounded-r-full';
        } elseif ($inTrip) {
          $rounded = 'rounded-none';
        }

        // Styling
        $classes = 'py-1 px-1 relative group text-center ';
        if ($isToday) {
          $classes .= 'bg-sky-500 text-white font-semibold ring-2 ring-offset-1 ring-sky-600 ' . $rounded;
        } elseif ($inTrip) {
          $classes .= 'bg-emerald-100 text-emerald-900 font-medium ' . $rounded;
        } else {
          $classes .= 'hover:bg-sky-50 hover:text-sky-700';
        }
      ?>

      <div class="<?= $classes ?>">
        <?= $d ?>
        <?php if ($isToday): ?>
          <span class="absolute -top-5 left-1/2 -translate-x-1/2 scale-0 group-hover:scale-100 transition text-[10px] text-white bg-sky-600 px-2 py-0.5 rounded shadow-lg z-10">
            Today
          </span>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</section>
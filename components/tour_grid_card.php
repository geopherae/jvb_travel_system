<?php
// ✅ Ensure we have a valid normalized tour array and ID
$tourId = (int) ($tour['id'] ?? 0);
?>

<div class="group h-full">
  <!-- Each card gets its own Alpine scope from tour_card.php -->
  <?php include 'tour_card.php'; ?>
</div>
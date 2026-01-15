<?php
// includes/testimonial-card.php

// Load database connection (MySQLi)
require_once __DIR__ . '/../actions/db.php';

// Fetch the most recent review with joined client name and package name
$review = null;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $query = "
        SELECT 
            r.rating,
            r.review,
            r.created_at,
            c.full_name AS client_name,
            p.package_name
        FROM client_reviews r
        INNER JOIN clients c ON r.client_id = c.id
        INNER JOIN tour_packages p ON r.assigned_package_id = p.id
        WHERE r.rating IS NOT NULL
        AND r.displayinHomePage = 1
        ORDER BY r.created_at DESC
        LIMIT 1
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $review = $result->fetch_assoc();
    }
}
?>

<div class="mt-12 bg-white/20 backdrop-blur-lg border border-white/30 rounded-2xl p-6 md:p-8 shadow-xl max-w-xl mx-auto lg:mx-0">
  <?php if ($review): ?>
    <div class="flex items-center mb-4">
      <!-- Dynamic stars rating -->
      <div class="flex text-yellow-400">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <svg class="w-6 h-6 <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-400' ?>" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        <?php endfor; ?>
      </div>
      <span class="ml-2 text-gray-200 font-medium"><?= htmlspecialchars($review['rating']) ?></span>
    </div>

    <!-- Review text -->
    <blockquote class="text-gray-100 italic mb-6">
      "<?= htmlspecialchars($review['review']) ?>"
    </blockquote>

    <!-- Client info -->
    <div class="flex items-center justify-between text-sm">
      <div>
        <p class="font-semibold text-white"><?= htmlspecialchars($review['client_name']) ?></p>
        <p class="text-gray-300"><?= htmlspecialchars($review['package_name']) ?></p>
      </div>
      <p class="text-gray-300">
        <?= date('F j, Y', strtotime($review['created_at'])) ?>
      </p>
    </div>

  <?php else: ?>
    <!-- Fallback if no reviews yet or query failed -->
    <div class="text-center text-gray-200 italic">
      "No reviews yet — be the first to share your amazing journey!"
    </div>
  <?php endif; ?>

  <?php if (isset($result)) $result->free(); ?>
</div>
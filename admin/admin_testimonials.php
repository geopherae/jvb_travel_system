<?php
include_once __DIR__ . '/../admin/admin_session_check.php';

// 🔐 Auth check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../actions/db.php';

// 🚫 Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 👤 Admin info
$isAdmin = true;
$adminName = $_SESSION['first_name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-50">
<head>
  <style>[x-cloak] { display: none !important; }</style>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Client Reviews & Testimonials</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  
  <!-- 🍞 Check for Pending Toast on Page Load -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const pendingToast = sessionStorage.getItem('pendingToast');
      if (pendingToast) {
        sessionStorage.removeItem('pendingToast');
        window.dispatchEvent(new CustomEvent('toast', {
          detail: { status: pendingToast }
        }));
      }
    });
  </script>
</head>

<body class="font-poppins text-gray-800 overflow-hidden"
      x-data="{ sidebarOpen: false, selectedTestimonial: null }"
      style="background: linear-gradient(to bottom, #e0f7ff 0%, white 10%, white 100%);">

  <!-- Status Alert Toast -->
  <?php include '../components/status_alert.php'; ?>

  <!-- Mobile Toggle -->
  <button @click="sidebarOpen = !sidebarOpen" class="p-3 md:hidden absolute top-4 left-4 z-30 bg-sky-600 text-white rounded">
    ☰
  </button>

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Panel -->
  <?php include '../components/right-panel.php'; ?>

  <!-- Main Content -->
  <main class="ml-0 lg:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-6 relative z-0">

    <h2 class="text-xl font-bold">Client Reviews & Testimonials</h2>

    <?php
    // Fetch testimonials from database
    $reviewsQuery = "
      SELECT 
        cr.review_id, 
        cr.client_id, 
        cr.assigned_package_id, 
        cr.rating, 
        cr.review, 
        cr.photo_path,
        cr.created_at,
        cr.displayinHomePage,
        c.full_name, 
        c.trip_date_start, 
        c.trip_date_end, 
        c.client_profile_photo,
        tp.package_name
      FROM client_reviews cr
      LEFT JOIN clients c ON cr.client_id = c.id
      LEFT JOIN tour_packages tp ON cr.assigned_package_id = tp.id
      ORDER BY cr.created_at DESC
    ";
    $reviewsResult = $conn->query($reviewsQuery);
    $reviews = $reviewsResult ? $reviewsResult->fetch_all(MYSQLI_ASSOC) : [];

    // Process testimonials data
    $testimonials = [];
    foreach ($reviews as $review) {
      $avatarInitial = strtoupper(substr($review['full_name'], 0, 1));
      $dates = '—';
      if ($review['trip_date_start'] && $review['trip_date_end']) {
        $start = date('M j', strtotime($review['trip_date_start']));
        $end = date('M j, Y', strtotime($review['trip_date_end']));
        $dates = $start . ' - ' . $end;
      }
      $testimonials[] = [
        'review_id' => (int)$review['review_id'],
        'avatar_initial' => $avatarInitial,
        'stars' => (int)$review['rating'],
        'name' => $review['full_name'],
        'package' => $review['package_name'] ?? 'Unknown Package',
        'date' => $review['created_at'],
        'dates' => $dates,
        'quote' => $review['review'],
        'client_profile_photo' => $review['client_profile_photo'],
        'photo_path' => $review['photo_path'],
        'displayinHomePage' => (int)$review['displayinHomePage']
      ];
    }

    // Get unique packages for filter
    $packages = array_unique(array_column($testimonials, 'package'));
    sort($packages);

    // Handle filter and sort
    $filter_package = $_GET['filter_package'] ?? '';
    $filter_display = $_GET['filter_display'] ?? 'all'; // all, public, hidden
    $sort_by = $_GET['sort_by'] ?? 'date_desc';

    // Filter by display status
    if ($filter_display === 'public') {
        $testimonials = array_filter($testimonials, function($t) {
            return $t['displayinHomePage'] === 1;
        });
    } elseif ($filter_display === 'hidden') {
        $testimonials = array_filter($testimonials, function($t) {
            return $t['displayinHomePage'] === 0;
        });
    }

    // Filter by package
    if ($filter_package) {
        $testimonials = array_filter($testimonials, function($t) use ($filter_package) {
            return $t['package'] === $filter_package;
        });
    }

    // Sort
    usort($testimonials, function($a, $b) use ($sort_by) {
        switch ($sort_by) {
            case 'date_asc':
                return strtotime($a['date']) <=> strtotime($b['date']);
            case 'date_desc':
                return strtotime($b['date']) <=> strtotime($a['date']);
            case 'rating_asc':
                return $a['stars'] <=> $b['stars'];
            case 'rating_desc':
                return $b['stars'] <=> $a['stars'];
            default:
                return 0;
        }
    });

    // Pagination
    $perPage = 6;
    $totalTestimonials = count($testimonials);
    $totalPages = ceil($totalTestimonials / $perPage);
    $currentPage = isset($_GET['page']) ? max(1, min($totalPages, (int)$_GET['page'])) : 1;
    $start = ($currentPage - 1) * $perPage;
    $paginatedTestimonials = array_slice($testimonials, $start, $perPage);

    // Build query string for pagination
    $queryParams = [];
    if ($filter_package) $queryParams['filter_package'] = $filter_package;
    if ($filter_display !== 'all') $queryParams['filter_display'] = $filter_display;
    if ($sort_by !== 'date_desc') $queryParams['sort_by'] = $sort_by;
    $queryString = http_build_query($queryParams);
    $queryString = $queryString ? '&' . $queryString : '';
    ?>

    <!-- Filter and Sort Form -->
    <div class="bg-white rounded-lg p-4">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
          <label for="filter_display" class="block text-sm font-medium text-gray-700">Visibility</label>
          <select name="filter_display" id="filter_display" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm rounded-md">
            <option value="all" <?= $filter_display === 'all' ? 'selected' : '' ?>>All Reviews</option>
            <option value="public" <?= $filter_display === 'public' ? 'selected' : '' ?>>Public Only</option>
            <option value="hidden" <?= $filter_display === 'hidden' ? 'selected' : '' ?>>Hidden Only</option>
          </select>
        </div>
        <div>
          <label for="filter_package" class="block text-sm font-medium text-gray-700">Filter by Package</label>
          <select name="filter_package" id="filter_package" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm rounded-md">
            <option value="">All Packages</option>
            <?php foreach ($packages as $package): ?>
              <option value="<?= htmlspecialchars($package) ?>" <?= $filter_package === $package ? 'selected' : '' ?>><?= htmlspecialchars($package) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="sort_by" class="block text-sm font-medium text-gray-700">Sort by</label>
          <select name="sort_by" id="sort_by" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm rounded-md">
            <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date (Newest First)</option>
            <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date (Oldest First)</option>
            <option value="rating_desc" <?= $sort_by === 'rating_desc' ? 'selected' : '' ?>>Rating (Highest First)</option>
            <option value="rating_asc" <?= $sort_by === 'rating_asc' ? 'selected' : '' ?>>Rating (Lowest First)</option>
          </select>
        </div>
        <button type="submit" class="bg-sky-600 text-white px-4 py-2 rounded-md hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">Apply</button>
      </form>
      <hr class="mt-4 border-t border-gray-200" />
    </div>

    <!-- Testimonials Grid or Empty State -->
    <?php if (empty($paginatedTestimonials)): ?>
      <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
        <!-- Empty State Icon -->
        <div class="mb-6">
          <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 10h6m-6 4h6"></path>
          </svg>
        </div>

        <!-- Message -->
        <h3 class="text-2xl font-semibold text-gray-700 mb-3">
          <?php if ($totalTestimonials === 0): ?>
            No testimonials yet
          <?php else: ?>
            No testimonials match your filters
          <?php endif; ?>
        </h3>

        <p class="text-gray-500 max-w-md mb-8">
          <?php if ($totalTestimonials === 0): ?>
            Client reviews will appear here once they submit feedback after their trips. Encourage your clients to share their experiences!
          <?php else: ?>
            Try adjusting the package filter or sort options to see more testimonials.
          <?php endif; ?>
        </p>

        <!-- Optional: Clear filters button (only if filters are active) -->
        <?php if ($filter_package || $sort_by !== 'date_desc'): ?>
          <a href="?" class="inline-flex items-center px-5 py-3 bg-sky-600 text-white font-medium rounded-lg hover:bg-sky-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Clear Filters
          </a>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- Testimonials Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-6">
        <?php foreach ($paginatedTestimonials as $testimonial): ?>
          <?php include '../components/testimonial_card.php'; ?>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-10">
          <nav class="flex items-center space-x-1">
            <?php if ($currentPage > 1): ?>
              <a href="?page=<?= $currentPage - 1 ?><?= $queryString ?>" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?page=<?= $i ?><?= $queryString ?>" class="px-4 py-2 text-sm font-medium <?= $i == $currentPage ? 'text-sky-600 bg-sky-50 border border-sky-500' : 'text-gray-500 bg-white border border-gray-300' ?> hover:bg-gray-50">
                <?= $i ?>
              </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
              <a href="?page=<?= $currentPage + 1 ?><?= $queryString ?>" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">Next</a>
            <?php endif; ?>
          </nav>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- 📋 Testimonial Details Modal -->
    <?php include __DIR__ . '/../components/testimonial-details-modal.php'; ?>

  </main>

  <!-- 🎯 Toggle Display Status Script -->
  <script>
    async function toggleDisplayStatus(reviewId, currentStatus) {
      try {
        const response = await fetch('../actions/toggle_review_display_status.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            review_id: reviewId,
            currentStatus: currentStatus
          })
        });

        const result = await response.json();

        if (result.success) {
          // Close the modal
          const rootElement = document.querySelector('html[x-data]');
          if (rootElement && rootElement.__x) {
            rootElement.__x.data.selectedTestimonial = null;
          }

          // Store the toast message in sessionStorage before reload
          sessionStorage.setItem('pendingToast', result.newStatus === 1 ? 'review_public' : 'review_hidden');

          // Soft refresh the page after a short delay
          setTimeout(() => {
            location.reload();
          }, 500);
        } else {
          window.dispatchEvent(new CustomEvent('toast', {
            detail: {
              status: 'review_toggle_failed'
            }
          }));
        }
      } catch (error) {
        console.error('Error toggling review status:', error);
        window.dispatchEvent(new CustomEvent('toast', {
          detail: {
            status: 'review_toggle_failed'
          }
        }));
      }
    }

    async function deleteReview(reviewId) {
      // Confirm before deleting
      if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
        return;
      }

      try {
        const response = await fetch('../actions/delete_review.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            review_id: reviewId
          })
        });

        const result = await response.json();

        if (result.success) {
          // Close the modal
          const rootElement = document.querySelector('html[x-data]');
          if (rootElement && rootElement.__x) {
            rootElement.__x.data.selectedTestimonial = null;
          }

          // Store the toast message in sessionStorage before reload
          sessionStorage.setItem('pendingToast', 'review_deleted');

          // Soft refresh the page after a short delay
          setTimeout(() => {
            location.reload();
          }, 500);
        } else {
          window.dispatchEvent(new CustomEvent('toast', {
            detail: {
              status: 'review_delete_failed'
            }
          }));
        }
      } catch (error) {
        console.error('Error deleting review:', error);
        window.dispatchEvent(new CustomEvent('toast', {
          detail: {
            status: 'review_delete_failed'
          }
        }));
      }
    }
  </script>

</body>
</html>
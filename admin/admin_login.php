<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$imageDir = __DIR__ . "/../images/admin_login_gallery_images";
$baseImages = [];
if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $baseImages[] = "../images/admin_login_gallery_images/" . $file;
        }
    }
}

// Prepare up to 8 images, cycle if fewer
$galleryImages = [];
$imgCount = count($baseImages);
if ($imgCount > 0) {
    for ($i = 0; $i < 8; $i++) {
        $galleryImages[] = $baseImages[$i % $imgCount];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Travel Agent Portal | JV-B Travel System</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    @keyframes gradient-shift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .gradient-animated {
      background: linear-gradient(135deg, #174f85 0%, #2596be 25%, #1e7ca8 50%, #174f85 75%, #2596be 100%);
      background-size: 400% 400%;
      animation: gradient-shift 20s ease infinite;
    }

    .photo-card {
      opacity: 0;
      transform: scale(0.85);
      transition: all 1.3s ease-in-out;
      pointer-events: none;
    }

    .photo-card.visible {
      opacity: 1;
      transform: scale(1);
      pointer-events: auto;
    }

    .photo-card:hover {
      transform: scale(1.08) translateY(-12px);
      z-index: 30;
    }
  </style>
</head>
<body class="min-h-screen text-white overflow-x-hidden lg:overflow-hidden relative"
  x-data="{}"
  @load="document.dispatchEvent(new CustomEvent('page-loaded'))">

  <!-- Hero Background with Glassmorphism Blur - Animated Gradient -->
  <div class="absolute inset-0 z-0 gradient-animated"></div>
  
  <div class="absolute inset-0 bg-[#174f85]/40 backdrop-blur-sm z-0"></div>
  
  <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/40 pointer-events-none z-0"></div>
  <div class="absolute inset-0 bg-gradient-to-r from-black/30 via-transparent to-black/20 pointer-events-none z-0"></div>

  <!-- Floating Gallery Photos – RIGHT HALF ONLY, 8 smaller spread photos -->
  <?php if (!empty($galleryImages)): ?>
  <div class="absolute top-0 right-0 w-full lg:w-1/2 h-full pointer-events-none overflow-hidden z-5 hidden lg:block">
    <?php foreach ($galleryImages as $index => $img): ?>
      <div class="photo-card absolute w-36 sm:w-40 md:w-44 aspect-[4/5] rounded-2xl overflow-hidden shadow-2xl border-4 border-white/70 bg-white"
           id="photo-<?= $index ?>"
           style="
             left: <?= [5, 18, 75, 88, 32, 65, 12, 82][$index % 8] ?>%;
             top: <?= [5, 32, 12, 68, 75, 50, 40, 58][$index % 8] ?>%;
           ">
        <img src="<?= htmlspecialchars($img) ?>" 
             alt="Travel operations memory" 
             class="w-full h-full object-cover"
             loading="lazy">
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Main Content -->
  <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row max-w-[1920px] mx-auto px-4 lg:px-6">

    <!-- Hero Text Content (left side – professional tone) -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 lg:px-16 py-8 lg:py-0">
      <div class="max-w-xl">
        <div class="inline-block mb-4 px-4 py-2 bg-white/15 backdrop-blur-lg rounded-full text-xs font-medium tracking-wide border border-white/20">
          JV-B Travel and Tours
        </div>
        
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-semibold sm:font-extrabold leading-tight mb-4 drop-shadow-lg">
          Itinerary and Document<br>
          <span class="text-[#2596be] drop-shadow-xl">Management System</span>
        </h1>
        
        <p class="md:text-2xl sm:text-xl text-gray-100 mb-6 drop-shadow-md">
          Streamline tour management, track client progress, and deliver exceptional travel experiences.
        </p>

        <!-- Features -->
        <div class="space-y-3 text-lg text-gray-100">
          <div class="flex items-start gap-2">
            <svg class="w-6 h-6 text-[#2596be] flex-shrink-0 mt-0.5" fill="white" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span>Real-time client tracking and communication</span>
          </div>
          <div class="flex items-start gap-2">
            <svg class="w-6 h-6 text-[#2596be] flex-shrink-0 mt-0.5" fill="white" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span>Tour Packages and Itinerary Builder</span>
          </div>
          <div class="flex items-start gap-2">
            <svg class="w-6 h-6 text-[#2596be] flex-shrink-0 mt-0.5" fill="white" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span>Faster Document Approvals</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-8 lg:py-0 lg:px-8 relative">
      <div class="w-full max-w-sm">
        <div class="bg-[#ffffff]/100 backdrop-blur-lg border border-sky-700/25 rounded-3xl shadow-2xl p-6 md:p-8">
          <div class="text-center mb-6">
            <img 
              src="../images/JVB_Logo.jpg" 
              alt="JVB Travel Logo" 
              class="mx-auto w-24 h-24 object-contain rounded-full mb-3 shadow-lg ring-2 ring-[#2596be]/30"
            />
            <h2 class="text-2xl font-bold text-sky-800">Agent Portal</h2>
            <p class="text-gray-700 mt-1 text-md">Access your client management system</p>
          </div>

          <?php if (isset($_SESSION['login_error'])): ?>
            <div class="bg-red-500/30 border border-red-400/50 text-white px-4 py-3 rounded-xl mb-6 text-center backdrop-blur-sm text-xs">
              <?= htmlspecialchars($_SESSION['login_error']) ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
          <?php endif; ?>

          <?php if (isset($_SESSION['session_expired'])): ?>
            <div class="bg-yellow-500/30 border border-yellow-400/50 text-white px-4 py-3 rounded-xl mb-6 text-center backdrop-blur-sm text-xs">
              Your session has expired. Please log in again.
            </div>
            <?php unset($_SESSION['session_expired']); ?>
          <?php endif; ?>

          <form action="process_admin_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />

            <div class="mb-4">
              <label for="username" class="block text-sm font-medium text-slate-500 mb-1.5">
                Username
              </label>
              <input 
                type="text" 
                id="username" 
                name="username" 
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/30 rounded-lg text-slate-700 placeholder-gray-300 focus:outline-none focus:border-[#2596be] focus:ring-2 focus:ring-[#2596be]/40 transition text-sm"
                placeholder="Enter your username"
              />
            </div>

            <div class="mb-6">
              <label for="password" class="block text-sm font-medium text-slate-500 mb-1.5">
                Password
              </label>
              <input 
                type="password" 
                id="password" 
                name="password" 
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/30 rounded-lg text-slate-700 placeholder-gray-300 focus:outline-none focus:border-[#2596be] focus:ring-2 focus:ring-[#2596be]/40 transition text-sm"
                placeholder="Enter your password"
              />
            </div>

            <button 
              type="submit"
              class="w-full bg-[#2596be] hover:bg-[#1e7ca8] text-white font-semibold py-3 rounded-lg transition-all duration-300 transform hover:scale-[1.02] shadow-lg text-sm"
            >
              Sign In
            </button>
          </form>

          <p class="font-medium mt-5 text-center text-sm text-slate-500">
            Are you a client? 
            <a href="../client/login.php" class="text-[#2596be] hover:text-[#1e7ca8] font-semibold hover:underline">
              Client Login
            </a>
          </p>

          <div class="mt-5 pt-4 border-t border-slate-500/20">
            <p class="text-center text-slate-400 text-xs">
              Your login is secure and encrypted
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript: Staggered pop-in + slow cycle -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const cards = document.querySelectorAll('.photo-card');

      // Initial staggered reveal
      cards.forEach((card, index) => {
        setTimeout(() => {
          card.classList.add('visible');
        }, 1000 + index * 450);
      });

      // Optional: slow pop-out / pop-in cycle
      function cyclePhotos() {
        cards.forEach((card, index) => {
          setTimeout(() => {
            card.classList.remove('visible');
            setTimeout(() => {
              card.classList.add('visible');
            }, 1800); // time hidden
          }, index * 6000 + 12000); // staggered start
        });
      }

      setTimeout(cyclePhotos, 10000); // start after initial reveal
    });
  </script>

</body>
</html>
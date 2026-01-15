<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$imageDir = __DIR__ . "/../images/login_gallery_images";
$baseImages = [];
if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $baseImages[] = "../images/login_gallery_images/" . $file;
        }
    }
}

// Prepare up to 6 images, cycle if fewer
$galleryImages = [];
$imgCount = count($baseImages);
if ($imgCount > 0) {
    for ($i = 0; $i < 6; $i++) {
        $galleryImages[] = $baseImages[$i % $imgCount];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JV-B Travel & Tours | Your Journey Begins Here</title>
  <?php include __DIR__ . '/../components/favicon_links.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Pop-in / pop-out + floating animation */
    .photo-card {
      opacity: 0;
      transform: scale(0.85);
      transition: all 1.3s ease-in-out;
      pointer-events: none;
    }

    .photo-card.visible {
      opacity: 1;
      transform: scale(1);
    }

    .photo-card:hover {
      transform: scale(1.12) translateY(-18px) !important;
      z-index: 30;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(var(--rotate, 0deg)); }
      50%      { transform: translateY(-45px) rotate(var(--rotate, 0deg)); }
    }

    .float-slow   { animation: float 32s infinite ease-in-out; }
    .float-medium { animation: float 40s infinite ease-in-out; }
    .float-fast   { animation: float 28s infinite ease-in-out; }
  </style>
</head>
<body class="min-h-screen text-white overflow-x-hidden relative">

  <!-- Hero Background with Glassmorphism Blur -->
  <div class="absolute inset-0 z-0">
    <img 
      src="../images/image_login_3.jpg" 
      alt="Dream travel destination" 
      class="w-full h-full object-cover brightness-[1.18] contrast-[1.08] saturate-[1.25]"
      loading="eager"
    />
    
    <div class="absolute inset-0 bg-[#174f85]/60 backdrop-blur-sm"></div>
    
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/40 pointer-events-none"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-black/30 via-transparent to-black/20 pointer-events-none"></div>
  </div>

  <!-- Floating Gallery Photos – RIGHT HALF ONLY, spread to edges -->
  <?php if (!empty($galleryImages)): ?>
  <div class="absolute top-0 right-0 w-full lg:w-1/2 h-full pointer-events-none overflow-hidden z-5 hidden lg:block">
    <?php foreach ($galleryImages as $index => $img): ?>
      <div class="photo-card absolute w-44 sm:w-52 md:w-56 lg:w-52 aspect-[4/5] rounded-2xl overflow-hidden shadow-2xl border-8 border-white/70 bg-white"
           id="photo-<?= $index ?>"
           style="
             --rotate: <?= [-10, 9, -8, 10, -7, 8][$index % 6] ?>deg;
             left: <?= [4, 12, 82, 90, 28, 70][$index % 6] ?>%;
             top: <?= [8, 28, 15, 65, 78, 45][$index % 6] ?>%;
           "
           class="float-<?= ['slow', 'medium', 'fast', 'slow', 'medium', 'fast'][$index % 6] ?>">
        <img src="<?= htmlspecialchars($img) ?>" 
             alt="Travel memory" 
             class="w-full h-full object-cover"
             loading="lazy">
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Main Content -->
  <div class="relative z-10 min-h-screen flex flex-col lg:flex-row">

    <!-- Hero Text Content (left side – clean & readable) -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 py-16 lg:px-16 lg:py-0">
      <div class="max-w-2xl mx-auto lg:mx-0">
        <div class="inline-block mb-6 px-5 py-2.5 bg-white/15 backdrop-blur-lg rounded-full text-sm font-medium tracking-wide border border-white/20">
          Welcome to JV-B Travel & Tours
        </div>
        
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6 drop-shadow-lg">
          Let's Make Your<br>
          <span class="text-[#2596be] drop-shadow-xl">Best Trip Ever</span>
        </h1>
        
        <p class="text-lg sm:text-xl text-gray-100 mb-10 max-w-xl drop-shadow-md">
          Whether you're seeking adventure, relaxation, or cultural immersion, we are here to bring the perfect journey just for you.
        </p>

        <div class="flex flex-wrap gap-8 text-sm">
            <div class="flex items-center gap-2">
            <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer">
              <svg class="w-6 h-6 text-[#2596be] hover:text-[#1e7ca8] transition" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
            </a>
            <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer"><span class="font-medium">Follow us on Facebook</span></a>
            </div>
            <div class="flex items-center gap-2">
            <svg class="w-6 h-6 text-[#2596be]" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812z" clip-rule="evenodd" />
            </svg>
            <a href="mailto:reservations.jvandbtravel@gmail.com" class="font-medium hover:text-[#ffffff] transition">Send us an email</a>
            </div>
        </div>
      <!-- Testimonial card -->
      <?php include '../includes/testimonial-card.php'; ?>
      </div>
    </div>

    <!-- Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12 lg:py-0 lg:px-12 relative">
      <div class="w-full max-w-md">
        <div class="bg-[#174f85]/60 backdrop-blur-lg border border-white/25 rounded-3xl shadow-2xl p-8 md:p-10">
          <div class="text-center mb-8">
            <img 
              src="../images/JVB_Logo.jpg" 
              alt="JVB Travel Logo" 
              class="mx-auto w-24 h-24 object-contain rounded-full mb-5 shadow-xl ring-4 ring-[#2596be]/30"
            />
            <h2 class="text-3xl font-bold text-white drop-shadow-md">Client Portal</h2>
            <p class="text-gray-200 mt-2">Access your personalized itinerary</p>
          </div>

          <?php if (isset($_SESSION['login_error'])): ?>
            <div class="bg-red-500/30 border border-red-400/50 text-white px-6 py-4 rounded-2xl mb-8 text-center backdrop-blur-sm">
              <?= htmlspecialchars($_SESSION['login_error']) ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
          <?php endif; ?>

          <form action="process_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />

            <div class="mb-6">
              <label for="access_code" class="block text-sm font-medium text-gray-200 mb-2">
                Enter your Access Code
              </label>
              <input 
                type="password" 
                id="access_code" 
                name="access_code" 
                required
                class="w-full px-6 py-4 bg-white/10 border border-white/30 rounded-xl text-white placeholder-gray-300 focus:outline-none focus:border-[#2596be] focus:ring-2 focus:ring-[#2596be]/40 transition text-lg"
                placeholder="JVBT-0000"
              />
            </div>

            <button 
              type="submit"
              class="w-full bg-[#2596be] hover:bg-[#1e7ca8] text-white font-semibold py-4 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg"
            >
              
            Login
            </button>
          </form>

          <p class="font-semibold mt-8 text-center text-sm text-gray-300">
            Are you an administrator? 
            <a href="../admin/admin_login.php" class="text-[#2596be] hover:text-[#1e7ca8] font-semibold hover:underline">
              Admin Login
            </a>
          </p>
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
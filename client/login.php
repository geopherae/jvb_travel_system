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

    /* Hide scrollbar but keep functionality */
    .scrollbar-hide {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    
    .scrollbar-hide::-webkit-scrollbar {
      display: none;
    }

    /* Mobile-specific optimizations */
    @media (max-width: 768px) {
      /* Ensure text is readable on mobile */
      body {
        font-size: 16px;
        -webkit-text-size-adjust: 100%;
      }

      /* Prevent horizontal scroll on body */
      html, body {
        overflow-x: hidden;
        max-width: 100vw;
      }

      /* Touch-friendly button sizes */
      button, a.inline-block {
        min-height: 44px;
        min-width: 44px;
      }

      /* Carousel mobile optimization */
      .tour-carousel {
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
      }

      .tour-card {
        scroll-snap-align: start;
        scroll-snap-stop: always;
      }
    }
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
    
    <!-- Main blue overlay with subtle blur -->
    <div class="absolute inset-0 bg-sky-700/50 backdrop-blur-[2px]"></div>
    
    <!-- Gradient overlays for depth using sky colors -->
    <div class="absolute inset-0 bg-gradient-to-b from-sky-700/60 via-transparent to-sky-800/40 pointer-events-none"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-sky-900/90 via-transparent to-sky-600/15 pointer-events-none"></div>
  </div>

  <!-- Floating Gallery Photos – RIGHT HALF ONLY on desktop, hidden on mobile -->
  <?php if (!empty($galleryImages)): ?>
  <div class="absolute top-0 right-0 w-full lg:w-1/2 h-screen pointer-events-none overflow-hidden z-[5] hidden lg:block">
    <?php foreach ($galleryImages as $index => $img): ?>
      <div class="photo-card absolute w-44 sm:w-52 md:w-56 lg:w-52 aspect-[4/5] rounded-2xl overflow-hidden shadow-2xl border-8 border-white/70 bg-white"
           id="photo-<?= $index ?>"
           style="
             --rotate: <?= [-10, 9, -8, 10, -7, 8][$index % 6] ?>deg;
             left: <?= [8, 18, 70, 82, 35, 58][$index % 6] ?>%;
             top: <?= [5, 25, 12, 60, 75, 40][$index % 6] ?>%;
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
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 sm:px-8 py-16 sm:py-20 lg:px-20 lg:py-28">
      <div class="max-w-2xl mx-auto lg:mx-0">
        <div class="inline-block mb-4 sm:mb-6 px-4 sm:px-5 py-2 sm:py-2.5 bg-white/15 backdrop-blur-lg rounded-full text-xs sm:text-sm font-medium tracking-wide border border-white/20">
          Welcome to JV-B Travel & Tours
        </div>
        
        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-extrabold leading-tight mb-4 sm:mb-6 drop-shadow-lg">
          Let's Make Your<br>
          <span class="text-white drop-shadow-xl">Best Trip Ever</span>
        </h1>
        
        <p class="text-base sm:text-lg md:text-xl text-gray-100 mb-6 sm:mb-10 max-w-xl drop-shadow-md">
          Whether you're seeking adventure, relaxation, or cultural immersion, we are here to bring the perfect journey just for you.
        </p>

        <!-- CTA Section -->
        <div class="pb-4 text-left">
          <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer" class="inline-block px-6 sm:px-8 py-3 sm:py-4 bg-[#2596be] hover:bg-[#1e7ca8] text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg text-sm sm:text-base">
            Visit our Facebook Page
          </a>
        </div>

        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-4 sm:gap-8 text-xs sm:text-sm">
          <div class="flex items-center gap-2">
            <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer">
              <svg class="w-5 h-5 sm:w-6 sm:h-6 text-[#2596be] hover:text-[#1e7ca8] transition" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
            </a>
            <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer"><span class="font-medium">Follow us on Facebook</span></a>
          </div>
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-[#2596be]" fill="currentColor" viewBox="0 0 20 20">
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
    <div class="w-full lg:w-1/2 flex items-center justify-center px-4 sm:px-6 py-8 sm:py-12 lg:py-0 lg:px-12 relative">
      <div class="w-full max-w-md">
        <div class="bg-[#174f85]/60 backdrop-blur-lg border border-white/25 rounded-2xl sm:rounded-3xl shadow-2xl p-6 sm:p-8 md:p-10">
          <div class="text-center mb-6 sm:mb-8">
            <img 
              src="../images/JVB_Logo.jpg" 
              alt="JVB Travel Logo" 
              class="mx-auto w-20 h-20 sm:w-24 sm:h-24 object-contain rounded-full mb-4 sm:mb-5 shadow-xl ring-4 ring-[#2596be]/30"
            />
            <h2 class="text-2xl sm:text-3xl font-bold text-white drop-shadow-md">Client Portal</h2>
            <p class="text-gray-200 mt-2 text-sm sm:text-base">Access your personalized itinerary</p>
          </div>

          <?php if (isset($_SESSION['login_error'])): ?>
            <div class="bg-red-500/30 border border-red-400/50 text-white px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl mb-6 sm:mb-8 text-center backdrop-blur-sm text-sm sm:text-base">
              <?= htmlspecialchars($_SESSION['login_error']) ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
          <?php endif; ?>

          <form action="process_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />

            <div class="mb-5 sm:mb-6">
              <label for="access_code" class="block text-sm font-medium text-gray-200 mb-2">
                Enter your Access Code
              </label>
              <input 
                id="access_code" 
                name="access_code" 
                required
                class="w-full px-4 sm:px-6 py-3 sm:py-4 bg-white/10 border border-white/30 rounded-xl text-white placeholder-gray-300 focus:outline-none focus:border-[#2596be] focus:ring-2 focus:ring-[#2596be]/40 transition text-base sm:text-lg"
                placeholder="JVBT-0000"
              />
            </div>

            <button 
              type="submit"
              class="w-full bg-[#2596be] hover:bg-[#1e7ca8] text-white font-semibold py-3 sm:py-4 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg text-sm sm:text-base"
            >
              Login
            </button>
          </form>

          <p class="italic font-semibold mt-6 sm:mt-8 text-center text-xs sm:text-sm text-gray-300">
            Don't have an access code? Book now to get started!
          </p>
          <p class="font-semibold mt-2 text-center text-xs sm:text-sm text-gray-300">
            Are you an administrator? 
            <a href="../admin/admin_login.php" class="text-[#2596be] hover:text-[#1e7ca8] font-semibold hover:underline">
              Admin Login
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- DOT Illustration Divider - Full Width -->
  <div class="relative z-10 w-full h-6 sm:h-8 overflow-hidden bg-gray-100">
    <img src="../images/landing_page_assets/dot_illustrations_icons.svg" 
         alt="Decorative divider with dot illustrations" 
         class="w-full h-full object-cover">
  </div>

  <!-- Tour Packages Carousel Section -->
  <div class="relative z-10 py-12 sm:py-16 md:py-20 px-4 sm:px-6 bg-gradient-to-b from-white to-gray-50">
    <div class="max-w-7xl mx-auto">
      <!-- Section Header -->
      <div class="text-center mb-8 sm:mb-10 md:mb-12">
        <div class="inline-block mb-3 sm:mb-4 px-4 sm:px-5 py-2 sm:py-2.5 bg-sky-700 rounded-full text-xs sm:text-sm font-medium tracking-wide border border-[#2596be]/30">
          Curated Experiences
        </div>
        <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold text-sky-800 mb-4 sm:mb-6 px-4">
          Explore Our Featured Tour Packages
        </h2>
        <p class="text-sm sm:text-base md:text-lg text-slate-700 max-w-2xl mx-auto px-4">
          Handpicked destinations and experiences crafted for unforgettable memories.
        </p>
      </div>

      <!-- Carousel Container -->
      <div class="relative px-0 sm:px-8 md:px-12">
        <!-- Carousel Wrapper -->
        <div class="p-2 sm:p-4 overflow-x-auto md:overflow-hidden scrollbar-hide snap-x snap-mandatory">
          <div class="tour-carousel flex transition-transform duration-500 ease-out md:transition-transform" id="tourCarousel" style="width: 100%;">
            
            <!-- Tour Package Card 1 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/memorable_japan.png" 
                       alt="Tour Package 1" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Memorable Japan</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4">Your dream Japan getaway starts here! Explore Mt. Fuji, Asakusa, Kyoto's Bamboo Grove, Nara Deer Park, and more!</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Mount Fuji</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Kyoto Temples</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Asakusa District</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">As low as $1,088 per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 2 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/betcha_by_bali.png" 
                       alt="Tour Package 2" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Betcha by Bali</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Enjoy Uluwatu's rich cultural heritage. Visit Tampaksiring, Mengwi Royal Temple, and more!</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>English-speaking guide</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Private coach and fully loaded tours</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Filipino tour escort</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">As low as ₱32,888 per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 3 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/manila_shanghai.png" 
                       alt="Tour Package 3" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Manila Shanghai</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Discover the perfect mix of modern city, views, culture, shopping, theme park fun, and more!</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>English-speaking guide</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Compulsory shopping visits</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Roundtrip international airfare via Cebu Pacific</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">$559 All-in per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 4 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/beijing.png" 
                       alt="Tour Package 4" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Wo Ai Ni Beijing</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Visit Heavenly Temple, Forbidden City, Universal Studios Beijing, Summer Palace, and more!</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Private Coach</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>4 Nights Hotel Accommodation (5-star)</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>English-Speaking Guide</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">From $899 per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 5 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/coron.jpg" 
                       alt="Tour Package 5" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Dream Island Coron</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Your dream island escape just got more exciting! Visit Coron and explore its stunning islands and lagoons.</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Island Hopping Tour A</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>3 Days Hotel Accommodation (5-star)</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Coron Town Tour</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">From P5,199 per pax</span>
                </div>
              </div>
            </div>

            <!-- Remaining tour cards 6-8 follow same pattern... -->
            <!-- I'll include them all for completeness -->
            
            <!-- Tour Package Card 6 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/sagada.jpg" 
                       alt="Tour Package 6" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Benguet, Sagada</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Experience the beauty of Banague, Sagada, and Baguio City. It's a perfect mountain escape with stunning views and cool breezes!</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Roundtrip van transfer</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Tourism fees & permits</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Fuel, toll, parking fees</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">As low as P3,899 per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 7 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/cebu.jpg" 
                       alt="Tour Package 7" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Cebu City</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">A nature lover's dream with its breathtaking waterfalls and some of the top diving spots in the Philippines.</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Island Hopping</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Visit to Sirao Garden</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Must-try famous Cebu Lechon</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">From $3,499 per pax</span>
                </div>
              </div>
            </div>

            <!-- Tour Package Card 8 -->
            <div class="tour-card flex-shrink-0 w-full sm:w-1/2 lg:w-1/4 px-2 sm:px-3 flex flex-col h-full">
              <div class="group relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-lg hover:-translate-y-2 h-full flex flex-col">
                <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="w-full aspect-[4/3] overflow-hidden bg-gray-100 flex-shrink-0">
                  <img src="../images/landing_page_assets/tour_packages_banners/siargao.jpg" 
                       alt="Tour Package 8" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="relative z-10 p-4 sm:p-6 flex flex-col flex-grow">
                  <h3 class="text-lg sm:text-xl font-bold text-sky-800 mb-2 sm:mb-3">Surfers' Paradise Siargao</h3>
                  <p class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-2">Chase sunsets and salty hair in Siargao, the surfing capital of the Philippines.</p>
                  <ul class="text-slate-700 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 space-y-1 flex-grow">
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>2 Nights Hotel Accommodation</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Roundtrip Airport Transfer</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>Daily Breakfast</span>
                    </li>
                    <li class="flex items-start gap-2">
                      <span class="text-[#2596be] font-bold">•</span>
                      <span>And more!</span>
                    </li>
                  </ul>
                  <span class="inline-block px-3 sm:px-4 py-1.5 sm:py-2 bg-[#2596be]/10 text-[#2596be] rounded-lg text-xs sm:text-sm font-semibold mt-auto">As low as P4,999 per pax</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Arrows - Hidden on mobile, shown on tablet+ -->
        <button onclick="scrollCarousel(-1)" class="absolute left-0 top-1/3 -translate-y-1/2 z-20 bg-[#2596be] hover:bg-[#1e7ca8] text-white p-3 sm:p-4 rounded-full shadow-lg transition-all duration-300 transform hover:scale-110 hidden md:flex items-center justify-center">
          <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <button onclick="scrollCarousel(1)" class="absolute right-0 top-1/3 -translate-y-1/2 z-20 bg-[#2596be] hover:bg-[#1e7ca8] text-white p-3 sm:p-4 rounded-full shadow-lg transition-all duration-300 transform hover:scale-110 hidden md:flex items-center justify-center">
          <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>

      <!-- Mobile swipe indicator -->
      <div class="flex justify-center gap-2 mt-6 md:hidden">
        <div class="w-2 h-2 rounded-full bg-[#2596be]"></div>
        <div class="w-2 h-2 rounded-full bg-gray-300"></div>
        <div class="w-2 h-2 rounded-full bg-gray-300"></div>
        <div class="w-2 h-2 rounded-full bg-gray-300"></div>
      </div>
    </div>
  </div>

  <!-- DOT Illustration Divider - Full Width -->
  <div class="relative z-10 w-full h-8 overflow-hidden bg-gray-100">
    <img src="../images/landing_page_assets/dot_illustrations_icons.svg" 
         alt="Decorative divider with dot illustrations" 
         class="w-full h-full object-cover">
  </div>

  <!-- Services Section -->
  <div class="relative z-10 py-20 px-6 bg-white">
    <div class="max-w-7xl mx-auto">
      <!-- Section Header -->
      <div class="text-center mb-16">
        <div class="inline-block mb-4 px-5 py-2.5 bg-sky-700 rounded-full text-sm font-medium tracking-wide border border-[#2596be]/30">
          Our Expertise
        </div>
        <h2 class="text-4xl sm:text-5xl font-extrabold text-sky-800 mb-6">
          Everything You Need for Your Journey
        </h2>
        <p class="text-lg text-slate-700 max-w-2xl mx-auto">
          From flights to visas, hotels to tours, we've got every aspect of your travel covered with professional service and competitive rates.
        </p>
      </div>

      <!-- Services Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        
        <!-- Service 1 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Airline Ticketing</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Local & international flights at the best rates. We handle all your booking needs with instant confirmations.</p>
        </div>

        <!-- Service 2 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Tour Packages</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Curated local & international packages. Unforgettable experiences designed just for you.</p>
        </div>

        <!-- Service 3 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Cruise & Ferry</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Seamless cruise ship and ferry bookings. Make your maritime adventure worry-free.</p>
        </div>

        <!-- Service 4 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Hotel Reservations</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Premium accommodations worldwide. From luxury resorts to boutique stays, we have options.</p>
        </div>

        <!-- Service 5 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Sightseeing Tours</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Guided activities & attractions. Explore destinations like a true insider with expert local guides.</p>
        </div>

        <!-- Service 6 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Visa Processing</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Tourist visa assistance for any destination. Complete documentation support from start to finish.</p>
        </div>

        <!-- Service 7 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Travel Insurance</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Comprehensive coverage for peace of mind. Protect your investment with our insurance packages.</p>
        </div>

        <!-- Service 8 -->
        <div class="group text-center p-6 rounded-xl hover:bg-gray-50 transition-all duration-300">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#2596be]/10 mb-5 group-hover:bg-[#2596be]/20 transition-colors duration-300">
            <svg class="w-10 h-10 text-[#2596be]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-sky-800 mb-3">Transfer Services</h3>
          <p class="text-slate-600 text-sm leading-relaxed">Airport pickups & ground transportation. Arrive refreshed with our reliable transfer services.</p>
        </div>

      </div>

      <!-- CTA Section -->
      <div class="mt-16 text-center">
        <p class="text-gray-700 text-lg mb-6">Ready to start your journey?</p>
        <a href="mailto:reservations.jvandbtravel@gmail.com" class="inline-block px-8 py-4 bg-[#2596be] hover:bg-[#1e7ca8] text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
          Send us an email
        </a>
      </div>
    </div>
  </div>

  <!-- Passport Assistance Section -->
  <div class="relative z-10 py-20 px-6 bg-gradient-to-br from-gray-50 to-white">
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <!-- Left Column: Text Content -->
        <div class="flex flex-col justify-center">
          <h2 class="text-4xl sm:text-5xl font-extrabold text-sky-800 mb-6 leading-tight">
            Need a Passport?<br>We've Got You Covered!
          </h2>
          <p class="text-lg text-slate-700 mb-8 leading-relaxed">
            JV-B Travel and Tours offers DFA Passport Assistance to make your application smooth and hassle-free!
          </p>
          
          <!-- Requirements Section -->
          <div class="mb-8">
            <h3 class="text-xl font-bold text-sky-800 mb-4">Requirements for Adults:</h3>
            <ul class="space-y-3 text-slate-700">
              <li class="flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#2596be]/20 flex-shrink-0 mt-0.5">
                  <span class="text-[#2596be] font-bold text-sm">✓</span>
                </span>
                <span>Original PSA Birth Certificate</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#2596be]/20 flex-shrink-0 mt-0.5">
                  <span class="text-[#2596be] font-bold text-sm">✓</span>
                </span>
                <span>1 Valid Government-Issued ID</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#2596be]/20 flex-shrink-0 mt-0.5">
                  <span class="text-[#2596be] font-bold text-sm">✓</span>
                </span>
                <span>Marriage Certificate (if applicable)</span>
              </li>
            </ul>
          </div>

          <!-- DFA Office Info -->
          <div class="bg-blue-50 border-l-4 border-[#2596be] p-5 rounded-r-lg mb-8">
            <p class="text-slate-700 font-semibold mb-2">Closest DFA Office: SM Olongapo Central</p>
            <p class="text-slate-600 text-sm">If your PSA Birth Certificate is unreadable or late registered, please bring a Local Civil Registry (LCR) copy.</p>
          </div>

          <p class="text-lg text-slate-700 font-semibold">
            Don't wait — secure your passport today and get ready for your next adventure!
          </p>
        </div>

        <!-- Right Column: Square Image -->
        <div class="flex justify-center lg:justify-end">
          <div class="w-full max-w-md rounded-2xl overflow-hidden shadow-2xl border border-gray-200 hover:shadow-3xl transition-shadow duration-300">
            <img src="../images/landing_page_assets/passport-assistance.jpg" 
                 alt="DFA Passport Assistance" 
                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tourist Visa Processing Section -->
  <div class="relative z-10 py-20 px-6 bg-cover bg-center" style="background-image: url('../images/landing_page_assets/dark_blue_gradient.png');">
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <!-- Left Column: Square Image -->
        <div class="flex justify-center lg:justify-start">
          <div class="w-full max-w-md rounded-2xl overflow-hidden shadow-2xl border border-white/20 hover:shadow-3xl transition-shadow duration-300">
            <img src="../images/landing_page_assets/visa-processing.jpg" 
                 alt="Tourist Visa Processing" 
                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
          </div>
        </div>

        <!-- Right Column: Text Content -->
        <div class="flex flex-col justify-center">
          <h2 class="text-4xl sm:text-5xl font-extrabold text-white mb-6 leading-tight">
            Plan Your Next International Adventure with Ease!
          </h2>
          <p class="text-lg text-white/90 mb-6 leading-relaxed">
            JV-B Travel and Tours offers Tourist Visa Processing for top travel destinations around the world — including Japan, Korea, USA, UK, Canada, Europe, China, Turkey, UAE, Australia, and New Zealand!
          </p>
          
          <p class="text-lg text-white/90 mb-6 leading-relaxed">
            Let us handle your visa requirements while you focus on planning your dream getaway.
          </p>

          <p class="text-xl text-white font-semibold">
            Your hassle-free journey starts here!
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript: Staggered pop-in + slow cycle -->
  <script>
    // Carousel state
    let carouselIndex = 0;

    // Carousel scroll function
    function scrollCarousel(direction) {
      const carousel = document.getElementById('tourCarousel');
      const totalCards = document.querySelectorAll('.tour-card').length;
      
      // Get cards per view based on screen size
      let cardsPerView = 4; // Default for large screens
      if (window.innerWidth < 1024) {
        cardsPerView = window.innerWidth < 640 ? 1 : 2;
      }
      
      const maxIndex = Math.ceil(totalCards / cardsPerView) - 1;
      
      carouselIndex += direction;
      
      // Loop back to start or end
      if (carouselIndex > maxIndex) {
        carouselIndex = 0;
      } else if (carouselIndex < 0) {
        carouselIndex = maxIndex;
      }
      
      // Calculate offset based on cards per view (100% scroll per click)
      const offset = -carouselIndex * 100;
      carousel.style.transform = `translateX(${offset}%)`;
    }

    // Gallery animation on load
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

  <!-- Testimonial Section -->
  <section class="relative z-10 py-20 px-6 bg-gradient-to-b from-white to-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <div class="inline-block mb-4 px-5 py-2.5 bg-sky-700 rounded-full text-sm font-medium tracking-wide border border-[#2596be]/30">
          Client Testimonials
        </div>
        <h2 class="text-4xl sm:text-5xl font-extrabold text-sky-800 mb-6">What Our Clients Say</h2>
        <p class="text-lg text-slate-700 max-w-2xl mx-auto">
          Real stories from travelers who experienced unforgettable journeys with JV-B Travel & Tours.
        </p>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
          include 'reviews.php';
          $count = 0;
          foreach ($reviews as $review) {
            if ($count >= 10) break;
            $shortReview = strlen($review['review']) > 100 ? substr($review['review'], 0, 100) . '...' : $review['review'];
            $reviewId = 'review-' . $count;
            echo '<div class="group relative bg-white border border-gray-200 rounded-2xl overflow-hidden hover:border-[#2596be] transition-all duration-300 hover:shadow-xl hover:-translate-y-1 h-full flex flex-col">
                    <div class="absolute inset-0 bg-gradient-to-br from-[#2596be]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative z-10 p-6 flex flex-col flex-grow">
                      <!-- Stars -->
                      <div class="flex text-yellow-400 mb-3">
                        ' . str_repeat('<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>', $review['stars']) . '
                      </div>
                      
                      <!-- Review text with hover expansion -->
                      <div id="' . $reviewId . '-container" class="relative min-h-24 mb-4 flex-grow">
                        <p class="text-slate-700 italic review-short" id="' . $reviewId . '-short" style="display: block;">
                          "' . htmlspecialchars($shortReview) . '"
                        </p>
                        <p class="text-slate-700 italic review-full hidden" id="' . $reviewId . '-full" style="display: none;">
                          "' . htmlspecialchars($review['review']) . '"
                        </p>
                      </div>
                      
                      <!-- Client name -->
                      <p class="font-semibold text-sky-800 text-sm">
                        ' . htmlspecialchars($review['name']) . '
                      </p>
                    </div>
                  </div>';
            $count++;
          }
        ?>
      </div>
    </div>
  </section>

  <script>
    // Testimonial hover effect to show full reviews
    document.querySelectorAll("[id$='-container']").forEach(container => {
      const reviewId = container.id.replace('-container', '');
      const shortReview = document.getElementById(reviewId + '-short');
      const fullReview = document.getElementById(reviewId + '-full');
      const card = container.closest('[class*="border-gray-200"]');
      
      if (shortReview && fullReview) {
        card.addEventListener('mouseenter', () => {
          shortReview.classList.add('hidden');
          fullReview.classList.remove('hidden');
        });
        
        card.addEventListener('mouseleave', () => {
          shortReview.classList.remove('hidden');
          fullReview.classList.add('hidden');
        });
      }
    });
  </script>

  <!-- Footer -->
  <footer class="relative z-10 bg-gradient-to-br from-sky-900 to-sky-950 text-white py-12 px-6">
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        
        <!-- Company Info -->
        <div class="space-y-4">
          <img src="../images/JVB_Logo.jpg" alt="JVB Travel Logo" class="w-20 h-20 object-contain rounded-full ring-4 ring-white/20">
          <h3 class="text-xl font-bold">JV-B Travel & Tours</h3>
          <p class="text-gray-300 text-sm">Your trusted partner in creating unforgettable travel experiences.</p>
          <div class="pt-2">
            <p class="text-xs text-gray-400 italic">DOT Accreditation No.</p>
            <p class="text-sm text-[#2596be] font-semibold">DOT-R03-TRA-00839-2022</p>
          </div>
          <!-- Social Media -->
          <div class="pt-2">
            <h5 class="text-sm font-semibold mb-3">Follow Us</h5>
            <a href="https://www.facebook.com/jvandbtravel" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-gray-300 hover:text-[#2596be] transition text-sm">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              <span>JV-B Travel and Tours</span>
            </a>
          </div>
        </div>

        <!-- West Tapinac Office -->
        <div class="space-y-3">
          <h4 class="text-lg font-semibold mb-4 border-b border-white/20 pb-2">West Tapinac Office</h4>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span>15 Basa St. West Tapinac,<br>Olongapo City 2200 PH</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Mon-Fri 8:00AM - 7:00PM<br>Saturday 8:00AM - 12:00NN</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span>(047) 272 0168</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span>+63 939 347 2015</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <a href="mailto:reservations.jvandbtravel@gmail.com" class="hover:text-[#2596be] transition break-all">reservations.jvandbtravel@gmail.com</a>
          </div>
        </div>

        <!-- New Kalalake Office -->
        <div class="space-y-3">
          <h4 class="text-lg font-semibold mb-4 border-b border-white/20 pb-2">New Kalalake Office</h4>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span>81 - 14th St. New Kalalake,<br>Olongapo City 2200 PH</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Mon-Fri 8:00AM - 7:00PM<br>Saturday 8:00AM - 12:00NN</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span>(047) 272 0168</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span>+63 935 205 2449</span>
          </div>
          <div class="flex items-start gap-3 text-gray-300 text-sm">
            <svg class="w-5 h-5 text-[#2596be] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <a href="mailto:newkalalake.jvandbtravel@gmail.com" class="hover:text-[#2596be] transition break-all">newkalalake.jvandbtravel@gmail.com</a>
          </div>
        </div>

      </div>

      <!-- Copyright -->
      <div class="border-t border-white/10 mt-8 pt-6 text-center text-gray-400 text-sm">
        <p>&copy;2017 JV-B Travel and Tours. All rights reserved.</p>
        <p>Website design by <a href="https://www.facebook.com/CahillMultimediaServices" target="_blank" rel="noopener noreferrer" class="text-[#2596be] hover:text-white transition">Cahill Multimedia Services</a></p>
      </div>
    </div>
  </footer>

  // Access Code Input Formatting Script
  <script>
document.addEventListener('DOMContentLoaded', function() {
  var accessCodeInput = document.getElementById('access_code');
  if (!accessCodeInput) return;

  // On input: auto-insert dash after 4 chars
  accessCodeInput.addEventListener('input', function(e) {
    let val = accessCodeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    if (val.length > 4) {
      val = val.slice(0, 4) + '-' + val.slice(4, 8);
    }
    else if (val.length === 4) {
      val = val + '-';
    }
    accessCodeInput.value = val;
  });

  // On paste: format pasted value
  accessCodeInput.addEventListener('paste', function(e) {
    e.preventDefault();
    let paste = (e.clipboardData || window.clipboardData).getData('text');
    paste = paste.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    let formatted = paste;
    if (paste.length > 4) {
      formatted = paste.slice(0, 4) + '-' + paste.slice(4, 8);
    } else if (paste.length === 4) {
      formatted = paste + '-';
    }
    accessCodeInput.value = formatted;
  });
});
</script>
</body>
</html>
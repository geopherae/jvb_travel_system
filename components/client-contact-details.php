<?php if (!isset($client)) return; ?>

<?php
$imgSrc = !empty($client['client_profile_photo'])
  ? '../uploads/client_profiles/' . htmlspecialchars($client['client_profile_photo'])
  : '../images/default_client_profile.png';

$fullName = $client['full_name'] ?? 'Unnamed Client';
$accessCode = $client['access_code'] ?? '—';
$email = $client['email'] ?? '';
$phone = $client['phone_number'] ?? '';
$passportNumber = $client['passport_number'] ?? '';
$passportExpiry = !empty($client['passport_expiry'])
  ? date('M d, Y', strtotime($client['passport_expiry']))
  : '';
$hasPassport = !empty($passportNumber) || !empty($passportExpiry);
?>

<!-- 👤 Client Contact Details Card-->
<div class="relative overflow-hidden rounded-xl sm:rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 sm:hover:scale-[1.02] h-full flex flex-col">
  <!-- Background: Blurred Avatar -->
  <img 
    src="<?= !empty($client['client_profile_photo']) ? '../uploads/client_profiles/' . htmlspecialchars($client['client_profile_photo']) : '../images/default_client_profile.png' ?>" 
    alt="Background"
    class="absolute inset-0 w-full h-full object-cover blur-sm"
    loading="lazy"
  />

  <!-- Background overlay with semi-transparent dark gradient -->
  <div class="absolute inset-0 bg-gradient-to-br from-sky-900/85 via-sky-700/80 to-sky-900/85 backdrop-blur-sm"></div>
  
  <!-- Decorative elements -->
  <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/5 rounded-full -mr-8 -mt-8 sm:-mr-12 sm:-mt-12"></div>
  <div class="absolute bottom-0 left-0 w-20 h-20 sm:w-32 sm:h-32 bg-white/5 rounded-full -ml-10 -mb-10 sm:-ml-16 sm:-mb-16"></div>

  <!-- Content -->
  <div class="relative z-10 p-4 sm:p-6 space-y-4 flex-1">
    <!-- Client Info: Avatar, Name & Status -->
    <div class="flex flex-col sm:flex-row items-center sm:items-center gap-3 sm:gap-4 pb-4 border-b border-white/30">
      <!-- Avatar -->
      <img 
        src="<?= !empty($client['client_profile_photo']) ? '../uploads/client_profiles/' . htmlspecialchars($client['client_profile_photo']) : '../images/default_client_profile.png' ?>" 
        alt="<?= htmlspecialchars($fullName) ?>"
        class="bg-white w-20 h-20 sm:w-16 sm:h-16 rounded-full object-cover border-4 border-white/100 shadow-lg flex-shrink-0"
        loading="lazy"
      />
      <!-- Name & Status -->
      <div class="flex-1 min-w-0 text-center sm:text-left w-full sm:w-auto">
        <h3 class="pb-1 sm:pb-1 text-xl sm:text-2xl font-bold text-white break-words"><?= htmlspecialchars($fullName) ?></h3>
        <?php 
        $status = $client['status'] ?? 'Pending';
        $statusColors = [
          'Awaiting Docs' => 'bg-amber-500/90 text-white',
          'Under Review' => 'bg-blue-500/90 text-white',
          'Resubmit Files' => 'bg-orange-500/90 text-white',
          'Confirmed' => 'bg-green-500/90 text-white',
          'Trip Ongoing' => 'bg-sky-500/90 text-white',
          'Trip Completed' => 'bg-purple-500/90 text-white',
          'Cancelled' => 'bg-red-500/90 text-white',
          'Archived' => 'bg-gray-500/90 text-white',
        ];
        $badgeClass = $statusColors[$status] ?? 'bg-gray-500/90 text-white';
        ?>
        <span class="inline-block px-3 py-1 sm:py-1 rounded-full bg-white/10 border border-white/20 text-white text-xs sm:text-xs font-semibold <?= $badgeClass ?> shadow-md">
          <?= htmlspecialchars($status) ?>
        </span>
      </div>
    </div>

    <!-- Dropdown Menu -->
    <div class="absolute top-3 right-3 sm:top-4 sm:right-4 z-50" x-data="{ open: false }" @click.outside="open = false">
      <button 
        @click="open = !open"
        class="p-2 bg-white/90 hover:bg-white active:bg-white rounded-full shadow-lg transition backdrop-blur-sm border border-gray-200 touch-manipulation"
        aria-label="Toggle client actions"
      >
        <svg class="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
        </svg>
      </button>

      <div x-show="open" x-transition x-cloak
           class="absolute right-0 mt-2 w-52 sm:w-56 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">
        <button 
          @click="$store.modals.editClient = true; open = false"
          class="w-full text-left px-4 py-3 text-sm font-medium text-gray-800 hover:bg-sky-50 active:bg-sky-100 transition touch-manipulation"
        >
          Update Guest Info
        </button>
        <button 
          @click="$store.modals.archiveClient = true; open = false"
          class="w-full text-left px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 active:bg-red-100 transition touch-manipulation"
        >
          Archive Guest
        </button>
      </div>
    </div>

    <!-- Contact Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-2">
      <!-- Email -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
          </svg>
          <span class="text-xs font-medium">Email</span>
        </div>
        <?php if ($email): ?>
          <a href="mailto:<?= htmlspecialchars($email) ?>"
             class="text-sm sm:text-base font-bold text-white hover:text-sky-200 active:text-sky-300 transition block break-all" title="<?= htmlspecialchars($email) ?>">
            <?= htmlspecialchars($email) ?>
          </a>
        <?php else: ?>
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        <?php endif; ?>
      </div>

      <!-- Phone -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
          </svg>
          <span class="text-xs font-medium">Phone</span>
        </div>
        <?php if ($phone): ?>
          <a href="tel:<?= htmlspecialchars($phone) ?>"
             class="text-sm sm:text-base font-bold text-white hover:text-sky-200 active:text-sky-300 transition block break-all">
            <?= htmlspecialchars($phone) ?>
          </a>
        <?php else: ?>
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        <?php endif; ?>
      </div>

      <!-- Access Code -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Access Code</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white font-mono tracking-wide"><?= htmlspecialchars($accessCode) ?></p>
      </div>

      <!-- Passport -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zM4 4h3a3 3 0 006 0h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45 4a2.5 2.5 0 10-4.9 0h4.9zM12 9a1 1 0 100 2h3a1 1 0 100-2h-3zm-1 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Passport</span>
        </div>
        <?php if ($hasPassport): ?>
          <div class="space-y-1 min-w-0">
            <?php if ($passportNumber): ?>
              <p class="text-sm sm:text-base font-bold text-white break-words"><?= htmlspecialchars($passportNumber) ?></p>
            <?php endif; ?>
            <?php if ($passportExpiry): ?>
              <p class="text-xs text-amber-300 font-medium">Expires <?= $passportExpiry ?></p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
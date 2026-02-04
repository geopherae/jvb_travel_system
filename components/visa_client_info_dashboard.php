<?php 
/**
 * Visa Client Info Dashboard Component - Compact Card Swiper
 * 
 * Modern, compact client information display for visa applications
 * Supports individual and group applications with card navigation
 * 
 * Parameters:
 *   $client           - Lead client details array (from clients table)
 *   $visa_application - Visa application details (from client_visa_applications table)
 *   $companions       - Array of companions (from client_visa_companions table)
 *   $isAdmin          - Boolean, whether viewing as admin
 */

if (!isset($client)) {
  return;
}

// Prepare lead client data
$clientId = $client['id'];
$fullName = $client['full_name'] ?? 'Unnamed Client';
$email = $client['email'] ?? '';
$phone = $client['phone_number'] ?? '';
$profilePhoto = !empty($client['client_profile_photo'])
  ? '../uploads/client_profiles/' . rawurlencode($client['client_profile_photo'])
  : '../images/default_client_profile.png';

// Visa-critical information ONLY
$passportNumber = $client['passport_number'] ?? '';
$passportExpiry = !empty($client['passport_expiry']) 
  ? strtotime($client['passport_expiry']) 
  : null;
$tripStart = !empty($client['trip_date_start']) 
  ? date('M d, Y', strtotime($client['trip_date_start'])) 
  : '';
$tripEnd = !empty($client['trip_date_end']) 
  ? date('M d, Y', strtotime($client['trip_date_end'])) 
  : '';

// Visa application details
$visaStatus = $visa_application['applicant_status'] ?? 'draft';
$visaMode = $visa_application['application_mode'] ?? 'individual';
$countryName = $visa_application['country'] ?? 'Unknown';
$processingDays = $visa_application['processing_days'] ?? 0;

// Status badge colors
$visaStatusColors = [
  'draft' => 'bg-gray-100 text-gray-700',
  'awaiting_docs' => 'bg-yellow-100 text-yellow-700',
  'under_review' => 'bg-blue-100 text-blue-700',
  'approved_for_submission' => 'bg-green-100 text-green-700',
  'booking' => 'bg-purple-100 text-purple-700',
];
$visaStatusColor = $visaStatusColors[$visaStatus] ?? 'bg-gray-100 text-gray-700';

// Passport expiry status
$isPassportExpired = $passportExpiry && $passportExpiry < time();
$passportExpiryDays = $passportExpiry ? ceil(($passportExpiry - time()) / 86400) : null;
$passportStatusClass = !$passportExpiry 
  ? 'bg-gray-100 text-gray-700' 
  : ($isPassportExpired 
    ? 'bg-red-100 text-red-700' 
    : ($passportExpiryDays <= 90 
      ? 'bg-orange-100 text-orange-700' 
      : 'bg-green-100 text-green-700'));
$passportStatusText = !$passportExpiry 
  ? 'Not Provided' 
  : ($isPassportExpired 
    ? 'EXPIRED' 
    : ($passportExpiryDays <= 90 
      ? "Exp: {$passportExpiryDays}d" 
      : "Valid"));

// Companions data
$companions = $companions ?? [];
$isGroupApplication = $visaMode === 'group' && !empty($companions);
$totalApplicants = 1 + count($companions);

// Build applicant array for navigation
$applicants = [];
$applicants[] = [
  'type' => 'lead',
  'name' => $fullName,
  'relationship' => 'Lead Applicant',
  'email' => $email,
  'phone' => $phone,
  'passport' => $passportNumber,
  'passport_expiry' => $passportExpiry,
  'passport_status' => $passportStatusText,
  'passport_status_class' => $passportStatusClass,
  'avatar' => $profilePhoto,
  'access_code' => $client['access_code'] ?? '—',
];
foreach ($companions as $comp) {
  $compPassportExpiry = !empty($comp['passport_expiry']) ? strtotime($comp['passport_expiry']) : null;
  $compIsExpired = $compPassportExpiry && $compPassportExpiry < time();
  $compExpiryDays = $compPassportExpiry ? ceil(($compPassportExpiry - time()) / 86400) : null;
  $compStatusClass = !$compPassportExpiry 
    ? 'bg-gray-100 text-gray-700' 
    : ($compIsExpired 
      ? 'bg-red-100 text-red-700' 
      : ($compExpiryDays <= 90 
        ? 'bg-orange-100 text-orange-700' 
        : 'bg-green-100 text-green-700'));
  $compStatusText = !$compPassportExpiry 
    ? 'Not Provided' 
    : ($compIsExpired 
      ? 'EXPIRED' 
      : ($compExpiryDays <= 90 
        ? "Exp: {$compExpiryDays}d" 
        : "Valid"));
  
  $applicants[] = [
    'type' => 'companion',
    'id' => $comp['id'],
    'name' => $comp['full_name'] ?? 'Unnamed',
    'relationship' => $comp['relationship'] ?? 'Companion',
    'applicant_status' => $comp['applicant_status'] ?? 'Not Specified',
    'email' => $comp['email'] ?? '',
    'phone' => $comp['phone_number'] ?? '',
    'passport' => $comp['passport_number'] ?? '',
    'passport_expiry' => $compPassportExpiry,
    'passport_status' => $compStatusText,
    'passport_status_class' => $compStatusClass,
    'avatar' => '../images/default_client_profile.png',
    'access_code' => $comp['access_code'] ?? '—',
  ];
}

$applicantsJson = htmlspecialchars(
  json_encode($applicants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
  ENT_QUOTES,
  'UTF-8'
);
?>

<script>
window.visaClientCard = window.visaClientCard || function(el) {
  const applicants = JSON.parse(el.dataset.applicants || '[]');
  
  // Helper function to convert strings to Sentence Case
  const toSentenceCase = (str) => {
    if (!str) return '';
    return str
      .toLowerCase()
      .replace(/_/g, ' ')
      .replace(/\b\w/g, char => char.toUpperCase())
      .replace(/\b([A-Z])\w*/g, (match, first) => {
        return first + match.slice(1).toLowerCase();
      })
      .trim();
  };
  
  return {
    currentIdx: 0,
    totalApplicants: applicants.length,
    applicants,
    get current() {
      return this.applicants[this.currentIdx] || this.applicants[0] || {};
    },
    toSentenceCase,
    preloadAvatars() {
      this.applicants.forEach(applicant => {
        if (applicant?.avatar) {
          const img = new Image();
          img.src = applicant.avatar;
        }
      });
    },
    syncWithStore() {
      const store = window.Alpine?.store('applicantSelector');
      if (!store) {
        return;
      }

      store.totalApplicants = this.totalApplicants;
      if (!Array.isArray(store.applicants) || store.applicants.length === 0) {
        store.applicants = this.applicants.map(applicant => ({ name: applicant.name, relationship: applicant.relationship }));
      }

      this.currentIdx = Number(store.currentIdx) || 0;

      this.$watch(() => store.currentIdx, value => {
        if (value !== undefined) {
          this.currentIdx = Number(value) || 0;
        }
      });

      this.$watch('currentIdx', value => {
        store.currentIdx = Number(value) || 0;
      });
    },
    init() {
      this.preloadAvatars();
      this.syncWithStore();
    }
  };
};
</script>

<!-- 🎯 Compact Client Card Swiper -->
<div x-data="visaClientCard($el)" x-init="init()" data-applicants="<?= $applicantsJson ?>" class="relative overflow-hidden rounded-xl sm:rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 sm:hover:scale-[1.02] h-full">
  
  <!-- Background: Blurred Avatar -->
  <div class="absolute inset-0">
    <div class="absolute inset-0 bg-cover bg-center blur-sm" :style="`background-image: url('${current.avatar || ''}')`"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900/85 via-slate-700/80 to-slate-900/85 backdrop-blur-sm"></div>
  </div>

  <!-- Decorative elements -->
  <div class="absolute top-0 right-0 w-16 h-16 sm:w-24 sm:h-24 bg-white/5 rounded-full -mr-8 -mt-8 sm:-mr-12 sm:-mt-12"></div>
  <div class="absolute bottom-0 left-0 w-20 h-20 sm:w-32 sm:h-32 bg-white/5 rounded-full -ml-10 -mb-10 sm:-ml-16 sm:-mb-16"></div>

  <!-- Content -->
  <div class="relative z-10 p-4 sm:p-6 space-y-4 flex-1 h-full flex flex-col">
    <!-- Client Info: Avatar, Name & Status -->
    <div class="flex flex-col sm:flex-row items-center sm:items-center gap-3 sm:gap-4 pb-4 border-b border-white/30">
      <!-- Avatar -->
      <img 
        :src="current.avatar"
        :alt="current.name"
        class="bg-white w-20 h-20 sm:w-16 sm:h-16 rounded-full object-cover border-4 border-white/100 shadow-lg flex-shrink-0"
        loading="lazy"
      />
      <!-- Name & Status -->
      <div class="flex-1 min-w-0 text-center sm:text-left w-full max-w-[90%] sm:w-auto">
        <h3 class="pb-1 sm:pb-1 text-lg sm:text-xl font-bold text-white line-clamp-1 break-words">
          <span x-text="current.name"></span>
        </h3>
        <div class="flex flex-wrap gap-2 justify-center sm:justify-start items-center">
          <span class="inline-block px-3 py-1 rounded-full bg-white/10 border border-white/20 text-white text-xs font-semibold shadow-md">
            <span x-text="toSentenceCase(current.relationship)"></span>
          </span>
          
          <!-- Status Badge (only if companion with applicant_status) -->
          <template x-if="current.applicant_status">
            <span class="inline-block px-3 py-1 rounded-full bg-indigo-500/90 text-white text-xs font-semibold shadow-md border border-white/20">
              <span x-text="toSentenceCase(current.applicant_status)"></span>
            </span>
          </template>
        </div>
      </div>
    </div>

    <!-- Contact Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-2">
      
      <!-- Passport -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zM4 4h3a3 3 0 006 0h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45 4a2.5 2.5 0 10-4.9 0h4.9zM12 9a1 1 0 100 2h3a1 1 0 100-2h-3zm-1 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Passport</span>
        </div>
        <template x-if="current.passport">
          <div class="space-y-1 min-w-0">
            <p class="text-sm sm:text-base font-bold text-white break-words">
              <span x-text="current.passport"></span>
            </p>
            <template x-if="current.passport_expiry">
              <p class="text-xs text-amber-300 font-medium">
                Expires <span x-text="new Date(current.passport_expiry * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"></span>
              </p>
            </template>
          </div>
        </template>
        <template x-if="!current.passport">
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        </template>
      </div>

      <!-- Email -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
          </svg>
          <span class="text-xs font-medium">Email</span>
        </div>
        <template x-if="current.email">
          <a :href="'mailto:' + current.email" 
             class="text-sm sm:text-base font-bold text-white hover:text-sky-200 active:text-sky-300 transition block break-all"
             :title="current.email">
            <span x-text="current.email"></span>
          </a>
        </template>
        <template x-if="!current.email">
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        </template>
      </div>

      <!-- Contact Number -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
          </svg>
          <span class="text-xs font-medium">Phone</span>
        </div>
        <template x-if="current.phone">
          <a :href="'tel:' + current.phone" 
             class="text-sm sm:text-base font-bold text-white hover:text-sky-200 active:text-sky-300 transition block break-all">
            <span x-text="current.phone"></span>
          </a>
        </template>
        <template x-if="!current.phone">
          <p class="text-sm sm:text-base font-bold text-white/50 italic">Not provided</p>
        </template>
      </div>

      <!-- Access Code -->
      <div class="space-y-2 min-w-0">
        <div class="flex items-center gap-2 text-white/80">
          <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
          </svg>
          <span class="text-xs font-medium">Access Code</span>
        </div>
        <p class="text-sm sm:text-base font-bold text-white font-mono tracking-wide">
          <span x-text="current.access_code"></span>
        </p>
      </div>
    </div>
    
    </div>
  </div>
</div>

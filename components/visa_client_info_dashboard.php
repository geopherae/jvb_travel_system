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
  ];
}
?>

<!-- 🎯 Compact Client Card Swiper -->
<div x-data="<?= htmlspecialchars(json_encode([
  'currentIdx' => 0,
  'totalApplicants' => count($applicants),
  'applicants' => $applicants
], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>" class="relative rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition-shadow">
  

  <!-- Applicant Selector Select -->
  <div class="absolute top-3 right-3 sm:top-4 sm:right-4 z-50 w-56">
    <select 
      x-model.number="currentIdx"
      class="w-full px-4 py-2 pr-10 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm bg-white text-slate-700 font-medium appearance-none cursor-pointer"
      style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3e%3cpolyline points=%226 9 12 15 18 9%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1.25em 1.25em; padding-right: 2.5rem;"
    >
      <!-- Lead Guest Section -->
      <optgroup label="Lead Guest" style="font-family: monospace; font-size: 0.75rem; color: #64748b;">
        <template x-if="applicants[0]">
          <option :value="0" x-text="applicants[0].name"></option>
        </template>
      </optgroup>

      <!-- Companions Section -->
      <template x-if="totalApplicants > 1">
        <optgroup label="Companions" style="font-family: monospace; font-size: 0.75rem; color: #64748b;">
          <template x-for="(applicant, idx) in applicants.slice(1)" :key="idx + 1">
            <option :value="idx + 1" x-text="applicant.name"></option>
          </template>
        </optgroup>
      </template>
    </select>
  </div>

  <!-- Applicant Card (Scrollable Content) -->
  <template x-for="(applicant, idx) in applicants" :key="idx">
    <div x-show="currentIdx === idx" 
         class="p-5 sm:p-6 space-y-4"
         x-transition>
      
      <!-- Top Row: Avatar + Name + Type -->
      <div class="flex items-start gap-4">
        <!-- Avatar -->
        <img :src="applicant.avatar" 
             :alt="applicant.name"
             class="w-16 h-16 rounded-xl object-cover border-2 border-gray-100 flex-shrink-0 shadow-sm">
        
        <!-- Name & Type -->
        <div class="flex-1 min-w-0">
          <h3 class="text-base sm:text-lg font-bold text-slate-900 break-words">
            <span x-text="applicant.name"></span>
          </h3>
          <p class="text-xs sm:text-sm text-slate-500 font-medium">
            <span x-text="applicant.relationship"></span>
          </p>
          
          <!-- Status Badge (only if companion with applicant_status) -->
          <template x-if="applicant.applicant_status">
            <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded-md bg-indigo-100 text-indigo-700">
              <span x-text="applicant.applicant_status.replace(/_/g, ' ')"></span>
            </span>
          </template>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-gray-200"></div>

      <!-- Visa Info Grid (Compact) -->
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        
        <!-- Visa Destination -->
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">Destination</label>
          <p class="text-sm sm:text-base font-bold text-slate-900"><?= htmlspecialchars($countryName) ?></p>
          <p class="text-xs text-slate-500">~<?= intval($processingDays) ?> days</p>
        </div>

        <!-- Passport Status -->
        <template x-if="applicant.passport">
          <div>
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">Passport</label>
            <p class="text-xs sm:text-sm font-mono font-bold text-slate-900 break-all">
              <span x-text="applicant.passport.substring(0, 8) + '...'"></span>
            </p>
            <span :class="applicant.passport_status_class" class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded">
              <span x-text="applicant.passport_status"></span>
            </span>
          </div>
        </template>

        <!-- Trip Dates -->
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">Trip</label>
          <p class="text-sm sm:text-base font-bold text-slate-900"><?= $tripStart ? date('M d', strtotime($client['trip_date_start'])) : 'TBD' ?></p>
          <p class="text-xs text-slate-500"><?= $tripEnd ? '→ ' . date('M d, Y', strtotime($client['trip_date_end'])) : '' ?></p>
        </div>

        <!-- Contact -->
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide block mb-1">Contact</label>
          <a :href="'mailto:' + applicant.email" 
             class="text-xs sm:text-sm text-blue-600 hover:text-blue-700 font-medium truncate block"
             x-show="applicant.email"
             :title="applicant.email">
            <span x-text="applicant.email"></span>
          </a>
          <a :href="'tel:' + applicant.phone" 
             class="text-xs sm:text-sm text-blue-600 hover:text-blue-700 font-medium block"
             x-show="applicant.phone">
            <span x-text="applicant.phone"></span>
          </a>
          <p class="text-xs text-slate-500" x-show="!applicant.email && !applicant.phone">Not provided</p>
        </div>
      </div>

      <!-- Visa Status Badge (Bottom) -->
      <div class="flex items-center gap-2 pt-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-semibold <?= $visaStatusColor ?> shadow-sm">
          <span class="w-1.5 h-1.5 rounded-full <?= str_replace('text-', 'bg-', $visaStatusColor) ?>"></span>
          <?= ucfirst(str_replace('_', ' ', $visaStatus)) ?>
        </span>
        
        <!-- Pagination Indicator -->
        <template x-if="totalApplicants > 1">
          <span class="ml-auto text-xs text-slate-500 font-medium">
            <span x-text="currentIdx + 1"></span> / <span x-text="totalApplicants"></span>
          </span>
        </template>
      </div>
    </div>
  </template>
</div>

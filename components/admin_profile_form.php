<?php
  $photoFilename = $admin['admin_photo'] ?? '';
  $photoPath = !empty($photoFilename)
    ? "../uploads/admin_photo/" . $photoFilename
    : '../images/default_client_profile.png';

  $photoUrl = $photoPath . '?v=' . time();
?>

<script>
  function adminPhotoPreview(initialUrl) {
    return {
      previewUrl: initialUrl || '../images/default_client_profile.png',
      fileName: '',
      handleFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.fileName = file.name;

        const reader = new FileReader();
        reader.onload = e => this.previewUrl = e.target.result;
        reader.readAsDataURL(file);
      }
    };
  }
</script>

<div class="bg-white border rounded-lg p-6 space-y-6 shadow-sm">
  <div class="border-b pb-2 px-2 flex items-center justify-between">
    <h3 class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Profile Information</h3>
    <p class="text-xs text-gray-400 uppercase">Section 1 of 2</p>
  </div>

  <!-- Top Row: Photo + Name -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Profile Photo -->
    <div x-data="adminPhotoPreview('<?= $photoUrl ?>')" class="flex flex-col items-center gap-2 border-2 border-dashed rounded-lg py-2 px-3 bg-gray-50 hover:border-blue-400 hover:bg-blue-50 transition">
      <img :src="previewUrl" alt="Admin Avatar"
           class="w-20 h-20 rounded-full object-cover border-2 border-blue-500 shadow"
           loading="lazy" />

      <label for="current-admin-photo" class="text-xs font-medium text-blue-700 hover:underline cursor-pointer">
        Click to upload a new photo
        <input id="current-admin-photo" name="current_admin_photo" type="file"
               accept=".jpg,.jpeg,.png,.webp"
               class="hidden"
               @change="handleFile">
      </label>

      <p class="text-[10px] text-gray-400" x-text="fileName || 'Accepted: JPG, PNG, WEBP. Max 2MB.'"></p>

    </div>

    <!-- First & Last Name -->
    <div class="grid grid-cols-1 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
        <input type="text" name="first_name" value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>"
               class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
        <input type="text" name="last_name" value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>"
               class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" required>
      </div>
    </div>
  </div>

  <!-- Middle Row: Email & Phone -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
      <input type="email" name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400" required>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
      <input type="tel" name="phone_number" value="<?= htmlspecialchars($admin['phone_number'] ?? '') ?>"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             pattern="^09\d{9}$" placeholder="09XXXXXXXXX">
    </div>
  </div>

  <!-- Bottom Row: Messenger & Profile -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Messenger Link</label>
      <input type="text" name="messenger_link"
             value="<?= isset($admin['messenger_link']) && $admin['messenger_link'] !== '' ? htmlspecialchars($admin['messenger_link']) : '' ?>"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             placeholder="https://m.me/yourname">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Admin Profile</label>
      <input type="text" name="admin_profile" value="<?= htmlspecialchars($bio) ?>"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             placeholder="Short description (1 line)">
    </div>
  </div>
</div>
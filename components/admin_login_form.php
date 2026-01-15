<div class="bg-white border rounded-lg p-6 space-y-6 shadow-sm"
     x-data="{
       showPasswordQuality: false,
       passwordStrength: 'Weak',
       validatePassword(password) {
         if (typeof password !== 'string') {
           this.passwordStrength = 'Weak';
           return;
         }

         if (password.length >= 12 && /[A-Z]/.test(password) && /\W/.test(password)) {
           this.passwordStrength = 'Strong';
         } else if (password.length >= 8) {
           this.passwordStrength = 'Medium';
         } else {
           this.passwordStrength = 'Weak';
         }
       }
     }">

  <div class="border-b pb-2 px-2 flex items-center justify-between">
    <h3 class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Login Information</h3>
    <p class="text-xs text-gray-400 uppercase">Section 2 of 2</p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Username -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
      <input type="text" name="username" value="<?= htmlspecialchars($admin['username'] ?? '') ?>"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             pattern="^[a-zA-Z0-9_.\-]+$" required>
    </div>

    <!-- Current Password -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
      <input type="password" name="current_password"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             placeholder="Current Password">
    </div>

    <!-- New Password -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
      <input type="password" name="new_password"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             @input="showPasswordQuality = true; validatePassword($event.target.value)"
             placeholder="New Password">
    </div>

    <!-- Confirm New Password -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
      <input type="password" name="confirm_new_password"
             class="w-full border rounded px-3 py-2 text-sm placeholder:text-gray-400"
             placeholder="Confirm New Password">
    </div>
  </div>

  <!-- Password Strength Indicator -->
  <div x-show="showPasswordQuality" class="text-sm text-gray-600">
    Password strength:
    <span x-text="passwordStrength"
          :class="{
            'text-red-500': passwordStrength === 'Weak',
            'text-yellow-500': passwordStrength === 'Medium',
            'text-green-600': passwordStrength === 'Strong'
          }"></span>
  </div>
</div>
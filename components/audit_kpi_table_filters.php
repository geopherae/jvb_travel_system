<!-- Filters -->
<div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 via-white to-blue-50 shadow-sm">
  <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-blue-100 opacity-40"></div>
  <div class="absolute -left-12 bottom-0 h-28 w-28 rounded-full bg-slate-100 opacity-50"></div>

  <div class="relative p-6 space-y-5">
    <div class="flex items-center gap-2 mb-6">
      <h2 class="text-lg font-bold text-gray-900">🔍 Filter Activity Log</h2>
      <span class="text-xs text-gray-500 font-medium">Narrow down results</span>
    </div>

    <form id="auditFilters" class="space-y-5">
      <!-- Row 1: Type, Module, KPI Tag -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Action Type Dropdown -->
        <div>
          <label for="action_type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Action Type</label>
          <select id="action_type" name="action_type" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            <option value="">All Types</option>
            <?php foreach ($actionTypes as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $actionType === $type ? 'selected' : '' ?>>
                <?= htmlspecialchars($type) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Module Dropdown -->
        <div>
          <label for="module" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Module</label>
          <select id="module" name="module" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            <option value="">All Modules</option>
            <?php foreach ($modules as $mod): ?>
              <option value="<?= htmlspecialchars($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>>
                <?= htmlspecialchars($mod) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- KPI Tag Dropdown -->
        <div>
          <label for="kpi_tag" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">KPI Tag</label>
          <select id="kpi_tag" name="kpi_tag" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            <option value="">All KPI Tags</option>
            <option value="booking_flow" <?= $kpiTag === 'booking_flow' ? 'selected' : '' ?>>Booking Flow</option>
            <option value="profile_edit" <?= $kpiTag === 'profile_edit' ? 'selected' : '' ?>>Profile Edit</option>
            <option value="payment_ops" <?= $kpiTag === 'payment_ops' ? 'selected' : '' ?>>Payment Ops</option>
          </select>
        </div>
      </div>

      <!-- Row 2: Role, Date Range -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Actor Role Dropdown -->
        <div>
          <label for="actor_role" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Actor Role</label>
          <select id="actor_role" name="actor_role" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            <option value="">All Roles</option>
            <option value="admin" <?= $actorRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="client" <?= $actorRole === 'client' ? 'selected' : '' ?>>Client</option>
            <option value="staff" <?= $actorRole === 'staff' ? 'selected' : '' ?>>Staff</option>
          </select>
        </div>

        <!-- Date Range -->
        <div>
          <label for="start_date" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Start Date</label>
          <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
        </div>
        <div>
          <label for="end_date" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">End Date</label>
          <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white text-sm font-medium text-gray-700 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
        </div>

        <!-- Anonymize Checkbox -->
        <div class="flex items-end">
          <label for="anonymize" class="flex items-center gap-2.5 cursor-pointer">
            <input type="checkbox" id="anonymize" name="anonymize" class="w-5 h-5 accent-sky-600 rounded border-gray-300 cursor-pointer" <?= $anonymize ? 'checked' : '' ?>>
            <span class="text-xs font-medium text-gray-700">Anonymize IDs</span>
          </label>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 pt-3 border-t border-gray-200">
        <button type="button" id="clearFiltersBtn" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50 hover:border-gray-400 transition">
          ↺ Clear Filters
        </button>
        <p class="text-xs text-gray-500 flex items-center">Filters update automatically as you change them</p>
      </div>
    </form>
  </div>
</div>
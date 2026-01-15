document.addEventListener('alpine:init', () => {
  // 🔧 Dropdown store
  Alpine.store('dropdown', { active: null });

  // ✅ Dropdown component
  window.dropdownData = function(rowId) {
    return {
      rowId,
      get menuOpen() {
        return $store.dropdown.active === this.rowId;
      },
      toggleMenu() {
        $store.dropdown.active = this.menuOpen ? null : this.rowId;
      },
      closeMenu() {
        $store.dropdown.active = null;
      }
    };
  };

  // ✅ Tour row component (minimal)
  window.tourRowData = function(tour, rowId) {
    return { tour, rowId };
  };
});
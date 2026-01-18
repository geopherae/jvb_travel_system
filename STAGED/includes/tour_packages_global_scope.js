document.addEventListener('alpine:init', () => {
  console.log('Alpine initializing stores...');

  /* --------------------
     Modal Store for Tour Cards/List Rows
  -------------------- */
  Alpine.store('tourModal', {
    isOpen: false,
    activeTour: {},   // always an object
    tab: 'itinerary',
    openModal(tourId) {
      if (!Array.isArray(window.allTours) || !window.allTours.length) {
        console.warn('No tours loaded into window.allTours');
        return;
      }
      const found = window.allTours.find(t => String(t.id) === String(tourId));
      if (found) {
        this.activeTour = found;
        this.tab = 'itinerary';
        this.isOpen = true;
      } else {
        console.warn(`Tour not found for ID: ${tourId}`, {
          availableIds: window.allTours.map(t => t.id)
        });
      }
    },
    closeModal() {
      this.isOpen = false;
      this.activeTour = {};
    }
  });

  /* --------------------
     Edit Modal Store
  -------------------- */
  Alpine.store('editTourModal', {
    isOpen: false,
    tourId: null,
    tourData: {},
    open(tourOrId) {
      const isObject = typeof tourOrId === 'object' && tourOrId !== null;
      const id = isObject ? tourOrId.id : tourOrId;
      const found = isObject
        ? tourOrId
        : window.allTours?.find(t => String(t.id) === String(id));
      if (!found) {
        console.warn('[editTourModal] No tour found for:', tourOrId);
        return;
      }
      this.tourId = found.id;
      this.tourData = found;
      this.isOpen = true;
      console.log('[editTourModal] isOpen set to true');
      const tourModal = Alpine.store('tourModal');
      if (tourModal?.isOpen) {
        tourModal.closeModal();
        console.log('[editTourModal] Closed tourModal to prevent overlap');
      }
    },
    close() {
      this.isOpen = false;
      this.tourId = null;
      this.tourData = {};
      console.log('[editTourModal] close() called');
      const tourModal = Alpine.store('tourModal');
      if (tourModal?.isOpen) {
        tourModal.closeModal();
        console.log('[editTourModal] Also closed tourModal');
      }
    }
  });


  /* --------------------
     Dropdown Store
  -------------------- */
  Alpine.store('dropdown', {
    active: null,
    open(id) { this.active = id; },
    close() { this.active = null; },
    isOpen(id) { return this.active === id; }
  });

  /* --------------------
     Modal Store for General Modal Confirmation
  -------------------- */
  Alpine.store('modals', {
    unassign: false,
    data: {
      clientId: null
    },
    openUnassign(clientId) {
      this.data.clientId = clientId;
      this.unassign = true;
    },
    closeUnassign() {
      this.unassign = false;
      this.data.clientId = null;
    }
  });

Alpine.data('tourFormData', (tour = {}) => ({
  // Base Properties
  packageName: tour.name || '',
  description: tour.description || '',
  price: tour.price || 0,
  formattedPrice: tour.price ? Number(tour.price).toLocaleString('en-PH') : '',
  days: tour.days || 0,
  nights: tour.nights || 0,
  origin: tour.origin || '',
  destination: tour.destination || '',
  isFavorite: tour.is_favorite || false,
  requiresVisa: tour.requires_visa || false,
  
  // Image Related
  image: tour.image || '../images/default_trip_cover.jpg',
  previewUrl: tour.image || '../images/default_trip_cover.jpg',
  hasNewImageUpload: false,
  filename: '',
  imageError: '',
  
  // Configuration
  checklistTemplateId: tour.checklistTemplateId || 0,
  tab: 'details',
  max: 10,

  isFormValid() {
  return (
    this.packageName.trim() !== '' &&
    this.description.trim() !== '' &&
    this.origin.trim() !== '' &&
    this.destination.trim() !== '' &&
    this.price > 0 &&
    this.days >= 1
    // Optional: && this.itinerary.length > 0 && this.itinerary[0].day_title.trim() !== ''
  );
},
  
  // Complex Data
  inclusions: tour.inclusions ? JSON.parse(JSON.stringify(tour.inclusions)) : [],
  exclusions: tour.exclusions ? JSON.parse(JSON.stringify(tour.exclusions)) : [],
  itinerary: tour.itinerary ? JSON.parse(JSON.stringify(tour.itinerary)) : [{ day_title: '', activities: [] }],
  
  // Airport Related
  airports: window.AIRPORTS || {},
  originQuery: tour.origin || '',
  destinationQuery: tour.destination || '',
  activeDropdown: null,
  originMatches: [],
  destinationMatches: [],
  
  // Nights Auto-calculation
  updateNights() {
    if (this.days >= 2) {
      this.nights = this.days - 1;
    } else {
      this.nights = 0;
    }
  },
  
  // Airport Methods
  filterAirports(type) {
    this.activeDropdown = type;
    const query = type === 'origin' ? this.originQuery : this.destinationQuery;
    const matches = [];

    for (const [country, codes] of Object.entries(this.airports)) {
      for (const [code, info] of Object.entries(codes)) {
        // Handle both string (just name) and object (with name + city)
        const name = typeof info === 'object' ? info.name : info;
        const city = typeof info === 'object' ? (info.city || '') : '';

        if (code.toLowerCase().includes(query.toLowerCase()) ||
            name.toLowerCase().includes(query.toLowerCase()) ||
            (city && city.toLowerCase().includes(query.toLowerCase()))) {
          matches.push({ code, name, city, country });
        }
      }
    }

    // Limit results for performance and cleaner dropdown
    const limitedMatches = matches.slice(0, 15);

    if (type === 'origin') {
      this.originMatches = limitedMatches;
      this.destinationMatches = [];
    } else {
      this.destinationMatches = limitedMatches;
      this.originMatches = [];
    }
  },

  // Accepts full airport object and properly stores as string code
  selectAirport(type, airport) {
    // Extract code from airport object
    const code = (typeof airport === 'string') ? airport : (airport.code || '');
    
    if (type === 'origin') {
      this.origin = String(code);              // Ensure it's a string for database storage
      this.originQuery = String(code);         // Shows code in input field
      this.originMatches = [];                 // Close dropdown
    } else if (type === 'destination') {
      this.destination = String(code);         // Ensure it's a string for database storage
      this.destinationQuery = String(code);    // Shows code in input field
      this.destinationMatches = [];            // Close dropdown
    }
    this.activeDropdown = null;
  },

// Image Handling (updated with validation)
handleCoverUpload(event) {
  const file = event.target.files[0];
  
  // Reset error/state first
  this.imageError = '';
  
  if (!file) {
    this.filename = '';
    this.hasNewImageUpload = false;
    this.previewUrl = '../images/default_trip_cover.jpg';
    return;
  }

  // Validation: File type
  if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
    this.imageError = 'Please upload only JPG or PNG images';
    this.filename = '';
    this.hasNewImageUpload = false;
    return;
  }

  // Validation: File size (3MB max)
  if (file.size > 3 * 1024 * 1024) {
    this.imageError = 'File size must be less than 3MB';
    this.filename = '';
    this.hasNewImageUpload = false;
    return;
  }

  // Success: Update state
  this.filename = file.name;
  this.hasNewImageUpload = true;

  const reader = new FileReader();
  reader.onload = (e) => {
    this.previewUrl = e.target.result;
  };
  reader.readAsDataURL(file);
},

  // Price Formatting
  formatPriceInput(event) {
    const raw = event.target.value.replace(/[^\d]/g, '');
    const num = parseInt(raw) || 0;
    this.price = num;
    this.formattedPrice = num ? num.toLocaleString('en-PH') : '';
  },

  // Itinerary Methods
  resetItinerary() {
    this.itinerary = [{ day_title: '', activities: [] }];
  },
  
  addDay() {
    if (this.itinerary.length >= 7) return;
    this.itinerary.push({ day_title: '', activities: [] });
  },
  
  removeDay(dayIndex) {
    this.itinerary.splice(dayIndex, 1);
  },
  
  addActivity(dayIndex) {
    this.itinerary[dayIndex].activities.push({
      hasTime: false,
      time: '',
      title: ''
    });
  },
  
  removeActivity(dayIndex, activityIndex) {
    this.itinerary[dayIndex].activities.splice(activityIndex, 1);
  },

  // Inclusion Methods
  add() {
    if (this.inclusions.length >= this.max) return;
    this.inclusions.push({ icon: '', title: '', desc: '' });
  },
  
  remove(index) {
    if (index >= 0 && index < this.inclusions.length) {
      this.inclusions.splice(index, 1);
    }
  },

  // Exclusion Methods
  addExclusion() {
    if (this.exclusions.length >= this.max) return;
    this.exclusions.push({ icon: '', title: '', desc: '' });
  },
  
  removeExclusion(index) {
    if (index >= 0 && index < this.exclusions.length) {
      this.exclusions.splice(index, 1);
    }
  },

  // Form Reset
  resetForm() {
    Object.assign(this, {
      packageName: '',
      description: '',
      price: 0,
      formattedPrice: '',    
      days: 0,
      nights: 0,
      origin: '',
      destination: '',
      originQuery: '',
      destinationQuery: '',
      originMatches: [],
      destinationMatches: [],
      checklistTemplateId: 0,
      isFavorite: false,
      requiresVisa: false,
      filename: '',
      previewUrl: '../images/default_trip_cover.jpg',
      hasNewImageUpload: false,
      tab: 'details'
    });
    this.resetItinerary();
    this.inclusions = [];
  },

  // Initialization
  init() {
    // Sync displayed queries with existing values (important for edit mode)
    this.originQuery = this.origin || '';
    this.destinationQuery = this.destination || '';

    // Auto-format price on load
    this.formattedPrice = this.price ? Number(this.price).toLocaleString('en-PH') : '';

    // Watch days to auto-update nights
    this.$watch('days', (value) => {
      this.updateNights();
    });

    // Close dropdown on Escape
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        this.activeDropdown = null;
        this.originMatches = [];
        this.destinationMatches = [];
      }
    });
  }
}));
})

/* --------------------
   Shared helper for all tour/list rows and cards
-------------------- */
function tourRowData(tourId) {
  return {
    rowId: tourId,
    toggleMenu() {
      if (Alpine.store('dropdown').isOpen(this.rowId)) {
        Alpine.store('dropdown').close();
      } else {
        Alpine.store('dropdown').open(this.rowId);
      }
    }
  };
}

/* --------------------
   Modal Open/Close Logic for General Modal Confirmation
-------------------- */

  document.addEventListener('alpine:init', () => {
    Alpine.store('modals', {
      unassign: false,
      data: {
        clientId: null
      },
      openUnassign(clientId) {
        this.data.clientId = clientId;
        this.unassign = true;
      },
      closeUnassign() {
        this.unassign = false;
        this.data.clientId = null;
      }
    });
  });

/* --------------------
   Modal Open/Close Logic for Add Modal
-------------------- */
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('openAddModal');
  const modal = document.getElementById('addModal');

  if (!openBtn) {
    console.warn('[AddModal] #openAddModal not found in DOM.');
    return;
  }

  if (!modal) {
    console.warn('[AddModal] #addModal not found in DOM.');
    return;
  }

  openBtn.addEventListener('click', () => {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    setTimeout(() => {
      if (typeof initFormLogic === 'function') {
        initFormLogic();
      } else {
        console.warn('[AddModal] initFormLogic() is not defined.');
      }
    }, 0);
  });

  ['closeAddModal', 'cancelAddModal'].forEach(id => {
    const btn = document.getElementById(id);
    if (!btn) {
      console.warn(`[AddModal] Button #${id} not found.`);
      return;
    }

    btn.addEventListener('click', () => {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');

      const alpineRoot = document.querySelector('#addModal [x-data]');
      if (alpineRoot && alpineRoot.__x && typeof alpineRoot.__x.$data.resetForm === 'function') {
        alpineRoot.__x.$data.resetForm();
      } else {
        console.warn('[AddModal] Alpine root or resetForm() not available.');
      }
    });
  });
});

/* --------------------
   Form Logic
-------------------- */
function initFormLogic() {
  const form = document.querySelector('#addModal form');
  if (!form) return;

  const packageName = form.querySelector('[name="package_name"]');
  const description = form.querySelector('[name="package_description"]');
  const priceInput = form.querySelector('[name="price"]');
  const dayInput = form.querySelector('[name="day_duration"]');
  const nightInput = form.querySelector('[name="night_duration"]');
  const saveBtn = form.querySelector('button[type="submit"]');

  if (!packageName || !description || !priceInput || !dayInput || !nightInput || !saveBtn) {
    console.warn('Some form elements are missing in #addModal');
    return;
  }

  packageName.placeholder = "Enter package name";
  description.placeholder = "Write a brief description";
  dayInput.placeholder = "e.g. 3";
  nightInput.placeholder = "Auto-calculated";

  dayInput.addEventListener('input', () => {
    const days = parseInt(dayInput.value) || 0;
    nightInput.value = Math.max(0, days - 1);
  });

  priceInput.addEventListener('input', () => {
    let raw = priceInput.value.replace(/[^\d]/g, '');
    let num = parseInt(raw) || '';
    priceInput.value = num !== '' ? num.toLocaleString('en-PH') : '';
  });

  [dayInput, nightInput].forEach(input => {
    input.addEventListener('blur', () => {
      if (!input.value.trim()) input.value = '0';
    });
  });

  const nameError = document.createElement('small');
  nameError.className = 'text-xs text-rose-600 hidden';
  nameError.textContent = '❗ This field is required';
  packageName.parentNode.appendChild(nameError);

  const descError = document.createElement('small');
  descError.className = 'text-xs text-rose-600 hidden';
  descError.textContent = '❗ This field is required';
  description.parentNode.appendChild(descError);

  const touched = { name: false, description: false };

  const validateForm = () => {
    const nameFilled = packageName.value.trim().length > 0;
    const descFilled = description.value.trim().length > 0;

    nameError.classList.toggle('hidden', nameFilled || !touched.name);
    descError.classList.toggle('hidden', descFilled || !touched.description);

    const isValid = nameFilled && descFilled;
    saveBtn.disabled = !isValid;
    saveBtn.classList.toggle('bg-sky-600', isValid);
    saveBtn.classList.toggle('hover:bg-sky-700', isValid);
    saveBtn.classList.toggle('bg-slate-300', !isValid);
    saveBtn.classList.toggle('cursor-not-allowed', !isValid);
  };

  packageName.addEventListener('blur', () => { touched.name = true; validateForm(); });
  description.addEventListener('blur', () => { touched.description = true; validateForm(); });
  packageName.addEventListener('input', validateForm);
  description.addEventListener('input', validateForm);
  validateForm();
}

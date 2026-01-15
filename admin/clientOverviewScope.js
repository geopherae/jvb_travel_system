function clientOverviewScope() {
  return {
    // 🔹 UI State
    tab: 'info',
    modals: createModals(),

    // 🔹 Itinerary Data
    itinerary: [],
    maxDays: 10,

    // 🔹 Itinerary Methods
    addDay() {
      if (this.itinerary.length >= this.maxDays) return;
      this.itinerary.push(createEmptyDay(this.itinerary.length + 1));
    },

    removeDay(index) {
      this.itinerary.splice(index, 1);
      this.reindexDays();
    },

    reindexDays() {
      this.itinerary.forEach((day, i) => day.day_number = i + 1);
    }
  };
}

// 🔧 Modal State Factory
function createModals() {
  return {
    unassign: false,
    editBooking: false,
    editClient: false,
    clientId: null
  };
}

// 🔧 Day Factory
function createEmptyDay(dayNumber) {
  return {
    day_number: dayNumber,
    day_title: '',
    activities: []
  };
}

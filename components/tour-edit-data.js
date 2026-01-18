function tourEditData(tour) {
  let inclusions = [];
  let itinerary = [];

  // 🌐 Safely parse inclusions_json
  try {
    const rawIncl = tour.inclusions_json ?? '[]';
    const parsedIncl = JSON.parse(rawIncl);
    inclusions = Array.isArray(parsedIncl)
      ? parsedIncl.map(i => ({
          icon: i.icon || '',
          title: i.title || '',
          desc: i.desc || ''
        }))
      : [];
  } catch (e) {
    console.warn('⚠️ Failed to parse inclusions_json:', e);
    inclusions = [];
  }

  // 📆 Safely parse package_itinerary_json
  try {
    const rawItin = tour.package_itinerary_json ?? '[]';
    const parsedItin = JSON.parse(rawItin);
    itinerary = Array.isArray(parsedItin)
      ? parsedItin.map((day, index) => ({
          day_number: index + 1,
          day_title: day.day_title || '',
          activities: Array.isArray(day.activities)
            ? day.activities.map(act => ({
                hasTime: act.hasTime ?? !!act.time,
                time: act.time || '',
                title: act.title || ''
              }))
            : []
        }))
      : [];
  } catch (e) {
    console.warn('⚠️ Failed to parse package_itinerary_json:', e);
    itinerary = [];
  }

  return {
    tour,

    previewUrl: tour.tour_cover_image?.trim()
      ? `../images/tour_packages_banners/${tour.tour_cover_image.replace(/^\/+/, '')}`
      : `../images/default_trip_cover.jpg`,

    days: Number(tour.day_duration || 0),
    nights: Number(tour.night_duration || 0),

    tab: 'itinerary',

    inclusions,
    max: 10,

    // 🧳 Travel Inclusions Logic
    add() {
      if (this.inclusions.length < this.max) {
        this.inclusions.push({ icon: '', title: '', desc: '' });
      }
    },
    remove(index) {
      if (index >= 0 && index < this.inclusions.length) {
        this.inclusions.splice(index, 1);
      }
    },
    resetInclusions() {
      try {
        const parsed = JSON.parse(tour.inclusions_json ?? '[]');
        this.inclusions = Array.isArray(parsed)
          ? parsed.map(i => ({
              icon: i.icon || '',
              title: i.title || '',
              desc: i.desc || ''
            }))
          : [];
      } catch {
        this.inclusions = [];
      }
    },

    // 🗺️ Travel Itinerary Logic
    itinerary,

    addDay() {
      this.itinerary.push({
        day_number: this.itinerary.length + 1,
        day_title: '',
        activities: []
      });
    },

    removeDay(index) {
      if (index >= 0 && index < this.itinerary.length) {
        this.itinerary.splice(index, 1);
        this.itinerary.forEach((day, i) => {
          day.day_number = i + 1;
        });
      }
    },

    addActivity(dayIndex) {
      if (this.itinerary[dayIndex]) {
        this.itinerary[dayIndex].activities.push({
          hasTime: false,
          time: '',
          title: ''
        });
      }
    },

    removeActivity(dayIndex, activityIndex) {
      if (this.itinerary[dayIndex]?.activities) {
        this.itinerary[dayIndex].activities.splice(activityIndex, 1);
      }
    },

    resetItinerary() {
      try {
        const parsed = JSON.parse(tour.package_itinerary_json ?? '[]');
        this.itinerary = Array.isArray(parsed)
          ? parsed.map((day, index) => ({
              day_number: index + 1,
              day_title: day.day_title || '',
              activities: Array.isArray(day.activities)
                ? day.activities.map(act => ({
                    hasTime: act.hasTime ?? !!act.time,
                    time: act.time || '',
                    title: act.title || ''
                  }))
                : []
            }))
          : [];
      } catch {
        this.itinerary = [];
      }
    }
  };
}
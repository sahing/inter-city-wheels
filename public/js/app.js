// ==========================================
// Intercity Wheels - Interactive Platform Engine
// ==========================================

// ------------------------------------------------------------------
// CSRF protection for every state-changing request.
//
// Laravel now enforces CSRF on /api/* and the auth endpoints (see
// bootstrap/app.php). Rather than threading a token through ~70 fetch() calls,
// wrap fetch once: unsafe methods get the token from the <meta> tag that
// layouts/app.blade.php renders into every page.
// ------------------------------------------------------------------
(function installCsrfFetchWrapper() {
  if (typeof window.fetch !== 'function') return;

  const originalFetch = window.fetch.bind(window);
  const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function isSameOriginRequest(input) {
    const rawUrl = typeof input === 'string'
      ? input
      : (input instanceof URL
        ? input.href
        : (input && typeof input.url === 'string' ? input.url : ''));

    if (!rawUrl) return false;

    try {
      const resolved = new URL(rawUrl, window.location.origin);
      return resolved.origin === window.location.origin;
    } catch (_) {
      return false;
    }
  }

  window.fetch = function (input, init) {
    const method = String(
      (init && init.method) || (typeof input === 'object' && input && input.method) || 'GET'
    ).toUpperCase();

    // Only attach Laravel CSRF headers on same-origin unsafe requests; sending
    // them to external origins (e.g. Firebase/Google APIs) leaks the token and
    // triggers CORS preflight 403 rejections.
    if (SAFE_METHODS.includes(method) || !isSameOriginRequest(input)) {
      return originalFetch(input, init);
    }

    const token = csrfToken();
    if (!token) {
      return originalFetch(input, init);
    }

    const options = Object.assign({}, init || {});
    const headers = new Headers(
      options.headers || (typeof input === 'object' && input ? input.headers : undefined)
    );

    if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', token);
    if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');

    options.headers = headers;

    return originalFetch(input, options);
  };
})();

// Pre-defined Cities & Route Matrix (mutable — synced from DB at runtime)
let CITY_DATABASE = {
  "pune": { name: "Pune", state: "Maharashtra", lat: 18.5204, lng: 73.8567 },
  "goa": { name: "Goa (Panjim)", state: "Goa", lat: 15.4909, lng: 73.8278 },
  "mumbai": { name: "Mumbai", state: "Maharashtra", lat: 19.0760, lng: 72.8777 },
  "bengaluru": { name: "Bengaluru", state: "Karnataka", lat: 12.9716, lng: 77.5946 },
  "hyderabad": { name: "Hyderabad", state: "Telangana", lat: 17.3850, lng: 78.4867 },
  "delhi": { name: "New Delhi", state: "Delhi NCR", lat: 28.6139, lng: 77.2090 },
  "jaipur": { name: "Jaipur", state: "Rajasthan", lat: 26.9124, lng: 75.7873 },
  "chennai": { name: "Chennai", state: "Tamil Nadu", lat: 13.0827, lng: 80.2707 },
  "baharampur": { name: "Baharampur", state: "West Bengal (Murshidabad)", lat: 24.0988, lng: 88.2514 },
  "berhampore": { name: "Berhampore", state: "West Bengal (Murshidabad)", lat: 24.0988, lng: 88.2514 },
  "digha": { name: "Digha", state: "West Bengal (Purba Medinipur)", lat: 21.6266, lng: 87.5074 },
  "newdigha": { name: "New Digha", state: "West Bengal (Purba Medinipur)", lat: 21.6200, lng: 87.5000 },
  "olddigha": { name: "Old Digha", state: "West Bengal (Purba Medinipur)", lat: 21.6300, lng: 87.5150 },
  "mandarmani": { name: "Mandarmani", state: "West Bengal (Purba Medinipur)", lat: 21.6667, lng: 87.7000 },
  "tajpur": { name: "Tajpur", state: "West Bengal (Purba Medinipur)", lat: 21.6500, lng: 87.6500 },
  "shankarpur": { name: "Shankarpur", state: "West Bengal (Purba Medinipur)", lat: 21.6360, lng: 87.5700 },
  "contai": { name: "Contai (Kanthi)", state: "West Bengal (Purba Medinipur)", lat: 21.7780, lng: 87.7510 },
  "haldia": { name: "Haldia", state: "West Bengal (Purba Medinipur)", lat: 22.0667, lng: 88.0667 },
  "kharagpur": { name: "Kharagpur", state: "West Bengal (Paschim Medinipur)", lat: 22.3460, lng: 87.2320 },
  "midnapore": { name: "Midnapore", state: "West Bengal (Paschim Medinipur)", lat: 22.4257, lng: 87.3199 },
  "murshidabad": { name: "Murshidabad", state: "West Bengal (Heritage City)", lat: 24.1750, lng: 88.2800 },
  "jangipur": { name: "Jangipur", state: "West Bengal (Murshidabad)", lat: 24.4686, lng: 88.0700 },
  "farakka": { name: "Farakka", state: "West Bengal (Murshidabad)", lat: 24.8167, lng: 87.9000 },
  "kandi": { name: "Kandi", state: "West Bengal (Murshidabad)", lat: 23.9556, lng: 88.0378 },
  "kolkata": { name: "Kolkata", state: "West Bengal", lat: 22.5726, lng: 88.3639 },
  "mayapur": { name: "Mayapur", state: "West Bengal (Nadia)", lat: 23.4232, lng: 88.3900 },
  "krishnanagar": { name: "Krishnanagar", state: "West Bengal (Nadia)", lat: 23.4000, lng: 88.5000 },
  "siliguri": { name: "Siliguri", state: "West Bengal", lat: 26.7271, lng: 88.3953 },
  "darjeeling": { name: "Darjeeling", state: "West Bengal (Queen of Hills)", lat: 27.0410, lng: 88.2663 },
  "durgapur": { name: "Durgapur", state: "West Bengal", lat: 23.5204, lng: 87.3119 },
  "asansol": { name: "Asansol", state: "West Bengal", lat: 23.6889, lng: 86.9661 },
  "bardhaman": { name: "Bardhaman (Burdwan)", state: "West Bengal", lat: 23.2324, lng: 87.8615 },
  "bolpur": { name: "Bolpur (Santiniketan)", state: "West Bengal (Birbhum)", lat: 23.6700, lng: 87.7200 },
  "tarapith": { name: "Tarapith", state: "West Bengal (Birbhum)", lat: 24.1167, lng: 87.8000 },
  "bakkhali": { name: "Bakkhali", state: "West Bengal (South 24 Parganas)", lat: 21.5630, lng: 88.2580 },
  "malda": { name: "Malda", state: "West Bengal", lat: 25.0108, lng: 88.1411 },
  "puri": { name: "Puri", state: "Odisha", lat: 19.8135, lng: 85.8312 },
  "bhubaneswar": { name: "Bhubaneswar", state: "Odisha", lat: 20.2961, lng: 85.8245 }
};

// Route Distances (km) and base highway times (mutable — synced from DB at runtime)
let ROUTE_MATRIX = {
  "berhampore-digha": { dist: 345, time: "7h 30m", toll: 320 },
  "digha-berhampore": { dist: 345, time: "7h 30m", toll: 320 },
  "baharampur-digha": { dist: 345, time: "7h 30m", toll: 320 },
  "digha-baharampur": { dist: 345, time: "7h 30m", toll: 320 },
  "kolkata-digha": { dist: 185, time: "4h 15m", toll: 180 },
  "digha-kolkata": { dist: 185, time: "4h 15m", toll: 180 },
  "kolkata-mayapur": { dist: 132, time: "3h 30m", toll: 120 },
  "mayapur-kolkata": { dist: 132, time: "3h 30m", toll: 120 },
  "pune-goa": { dist: 450, time: "9h 15m", toll: 480 },
  "goa-pune": { dist: 450, time: "9h 15m", toll: 480 },
  "mumbai-pune": { dist: 152, time: "2h 45m", toll: 320 },
  "pune-mumbai": { dist: 152, time: "2h 45m", toll: 320 },
  "mumbai-goa": { dist: 590, time: "11h 20m", toll: 650 },
  "goa-mumbai": { dist: 590, time: "11h 20m", toll: 650 },
  "bengaluru-hyderabad": { dist: 570, time: "9h 00m", toll: 620 },
  "hyderabad-bengaluru": { dist: 570, time: "9h 00m", toll: 620 },
  "bengaluru-chennai": { dist: 346, time: "6h 15m", toll: 390 },
  "chennai-bengaluru": { dist: 346, time: "6h 15m", toll: 390 },
  "delhi-jaipur": { dist: 280, time: "4h 45m", toll: 340 },
  "jaipur-delhi": { dist: 280, time: "4h 45m", toll: 340 },
  "baharampur-kolkata": { dist: 205, time: "5h 15m", toll: 180 },
  "kolkata-baharampur": { dist: 205, time: "5h 15m", toll: 180 },
  "berhampore-kolkata": { dist: 205, time: "5h 15m", toll: 180 },
  "kolkata-berhampore": { dist: 205, time: "5h 15m", toll: 180 },
  "murshidabad-kolkata": { dist: 218, time: "5h 30m", toll: 180 },
  "kolkata-murshidabad": { dist: 218, time: "5h 30m", toll: 180 },
  "baharampur-farakka": { dist: 102, time: "2h 30m", toll: 90 },
  "farakka-baharampur": { dist: 102, time: "2h 30m", toll: 90 },
  "baharampur-jangipur": { dist: 65, time: "1h 35m", toll: 50 },
  "jangipur-baharampur": { dist: 65, time: "1h 35m", toll: 50 },
  "baharampur-kandi": { dist: 32, time: "55m", toll: 0 },
  "kandi-baharampur": { dist: 32, time: "55m", toll: 0 }
};

// Global App State
const state = {
  activeTab: (window.CURRENT_USER && window.CURRENT_USER.role === 'admin') ? 'admin' : ((window.CURRENT_USER && window.CURRENT_USER.role === 'driver') ? 'bidding' : 'schedules'),

  // Cabs Booking State
  cab: {
    tripType: 'oneway', // oneway, roundtrip, airport
    fromCity: 'kolkata',
    toCity: 'mayapur',
    distance: 132,
    travelTime: '3h 30m',
    selectedFleet: 'suv', // sedan, prime, suv, luxury
    includeTolls: true,
    petFriendly: false,
    nightAllowance: false,
    luggageCarrier: false,
    appliedCoupon: null,
    discountAmount: 0
  },

  // Schedules / Berth Selection State (From screen.png & code.html)
  schedules: {
    route: 'Pune → Goa',
    operator: 'INTERCITY EXPRESS #8092',
    busType: 'Volvo Multi-Axle AC Sleeper',
    activeDeck: 'lower',
    selectedSeats: [
      { id: 'L4', price: 850, type: 'lower', window: true },
      { id: 'L5', price: 850, type: 'lower', window: false }
    ],
    boardingPoint: 'Swargate Bus Terminal, Bay 4 (09:30 PM)',
    droppingPoint: 'Panjim KTC Bus Stand, Platform 1 (06:45 AM)',
    passengers: [
      { name: 'Rahul Sharma', age: 29, gender: 'male', berth: 'L4' },
      { name: 'Sneha Verma', age: 27, gender: 'female', berth: 'L5' }
    ]
  },

  // Driver Bidding State (live auctions from /api/driver/auctions)
  bidding: {
    availableTrips: [],
    recentAllotted: [],
    filter: 'all',
    viewMode: (typeof window !== 'undefined' && window.innerWidth < 768) ? 'cards' : 'table',
    expandedId: null,
    canBid: true,
    isAdminViewer: false
  },

  // Dynamic Form Builder & Workflow Config (gemini.md requirements)
  admin: {
    customFields: [
      { id: 'f_flight_num', label: 'Flight Number (For Airport Pickup)', type: 'text', condition: 'Airport Trips Only', required: false },
      { id: 'f_pet_info', label: 'Pet Breed & Weight', type: 'text', condition: 'Pet-friendly Selected', required: false },
      { id: 'f_gstin', label: 'Company GSTIN for Tax Invoice', type: 'text', condition: 'Optional Corporate', required: false }
    ],
    workflowStages: [
      { id: 'stg-1', name: 'Trip Requested', desc: 'User submits route & requirements' },
      { id: 'stg-2', name: 'Driver Auction & Bidding', desc: 'Registered drivers submit live counter bids' },
      { id: 'stg-3', name: 'Bid Accepted & Escrow Locked', desc: 'Fare locked, ride confirmation emitted' },
      { id: 'stg-4', name: 'OTP Start Validation', desc: 'Driver enters 4-digit customer ride OTP' },
      { id: 'stg-5', name: 'Trip Completed & Payout', desc: 'Tolls reconciled & wallet credited' }
    ],
    pricingRules: {
      sedanPerKm: 12.5,
      suvPerKm: 18.0,
      luxuryPerKm: 28.0,
      petCharge: Number(window.PLATFORM_SETTINGS?.addon_pet_friendly_rate ?? 350),
      nightCharge: Number(window.PLATFORM_SETTINGS?.addon_night_allowance_rate ?? 400),
      luggageCharge: Number(window.PLATFORM_SETTINGS?.addon_luggage_carrier_rate ?? 200)
    }
  },

  // Bookings list
  bookings: [
    {
      id: 'ICW-90412',
      type: 'Bus Berth',
      route: 'Pune → Goa',
      date: 'Tonight, 09:30 PM',
      operator: 'Intercity Express #8092',
      berths: ['L4', 'L5'],
      passengers: ['Rahul Sharma (29/M)', 'Sneha Verma (27/F)'],
      amount: 1700,
      status: 'Confirmed',
      otp: '8492'
    }
  ]
};

// Fleet specs & rates (seeded from defaults, hydrated from CarType DB records)
const DEFAULT_FLEET_TYPES = {
  hatchback: {
    category: 'hatchback',
    name: 'Hatchback Economy',
    models: 'WagonR, Tata Tiago',
    seats: '4 Seats',
    luggage: '2 Bags',
    ratePerKm: 11.0,
    badge: 'Budget Value',
    extraKm: 11,
    image: 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?auto=format&fit=crop&w=640&q=80'
  },
  sedan: {
    category: 'sedan',
    name: 'Comfort Sedan',
    models: 'Maruti Dzire, Toyota Etios, Amaze',
    seats: '4 Seats',
    luggage: '3 Bags',
    ratePerKm: 13.0,
    badge: 'Most Popular',
    extraKm: 13,
    image: 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=640&q=80'
  },
  prime: {
    category: 'prime',
    name: 'Prime Executive',
    models: 'Honda City, Hyundai Verna, Ciaz',
    seats: '4 Seats',
    luggage: '3 Bags',
    ratePerKm: 15.0,
    badge: 'Executive Comfort',
    extraKm: 15,
    image: 'https://images.unsplash.com/photo-1590362891988-f77804702088?auto=format&fit=crop&w=640&q=80'
  },
  suv: {
    category: 'suv',
    name: 'Family SUV',
    models: 'Maruti Ertiga, Mahindra Marazzo',
    seats: '6 Seats',
    luggage: '4 Bags',
    ratePerKm: 17.5,
    badge: 'Family Favorite',
    extraKm: 17,
    image: 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=640&q=80'
  },
  innova: {
    category: 'innova',
    name: 'Innova Crysta Premium',
    models: 'Toyota Innova Crysta (Dual AC)',
    seats: '7 Seats',
    luggage: '5 Bags',
    ratePerKm: 21.0,
    badge: 'Highway Champion',
    extraKm: 21,
    image: 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=640&q=80'
  },
  tempo: {
    category: 'tempo',
    name: 'Tempo Traveller',
    models: 'Force Urbania 12-17 Pushback',
    seats: '12-17 Seats',
    luggage: '10 Bags',
    ratePerKm: 26.0,
    badge: 'Group Travel',
    extraKm: 26,
    image: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=640&q=80'
  },
  luxury: {
    category: 'luxury',
    name: 'Luxury Business Fleet',
    models: 'Toyota Camry, Mercedes E-Class',
    seats: '4 Seats',
    luggage: '3 Bags',
    ratePerKm: 38.0,
    badge: 'VIP Experience',
    extraKm: 38,
    image: 'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=640&q=80'
  }
};
let FLEET_TYPES = { ...DEFAULT_FLEET_TYPES };

let mapInstance = null;
let mapRouteLine = null;
let mapMarkers = [];

// ==========================================
// Initialization
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
  // Seed locations from server-rendered window variable
  if (window.INITIAL_SERVICE_LOCATIONS && Array.isArray(window.INITIAL_SERVICE_LOCATIONS)) {
    window.syncAllLocationDropdowns(window.INITIAL_SERVICE_LOCATIONS);
  }

  // Seed car types from server-rendered window variable
  if (window.INITIAL_CAR_TYPES && Array.isArray(window.INITIAL_CAR_TYPES)) {
    window.syncAllCarTypes(window.INITIAL_CAR_TYPES);
  }

  // Merge server-side route fares into ROUTE_MATRIX
  if (window.INITIAL_CAB_ROUTES && Array.isArray(window.INITIAL_CAB_ROUTES)) {
    window.INITIAL_CAB_ROUTES.forEach(r => {
      if (!r.from_city || !r.to_city) return;
      const fKey = r.from_city.toLowerCase().replace(/[^a-z0-9]/g, '');
      const tKey = r.to_city.toLowerCase().replace(/[^a-z0-9]/g, '');
      const entry = {
        dist: parseFloat(r.distance_km) || 0,
        time: r.estimated_time || '',
        toll: parseFloat(r.base_toll) || 0
      };
      ROUTE_MATRIX[`${fKey}-${tKey}`] = entry;
      ROUTE_MATRIX[`${tKey}-${fKey}`] = entry;
    });
  }

  initAuth();
  initNavigation();
  initCabsModule();
  initSchedulesModule();
  initBiddingModule();
  initAdminModule();
  initMap();
  updateTotalCabFare();
  if (typeof updateDynamicFieldsVisibility === 'function') updateDynamicFieldsVisibility();
  startBiddingCountdown();

  // Background refresh: keep dropdowns up-to-date with any admin changes
  fetch('/api/locations')
    .then(r => r.json())
    .then(data => {
      const list = Array.isArray(data) ? data : (data && data.locations ? data.locations : null);
      if (list && Array.isArray(list)) {
        window.syncAllLocationDropdowns(list);
      }
    })
    .catch(() => { }); // silent — offline / server error is not critical
});

// ==========================================
// Navigation & Shell Controls
// ==========================================
function initNavigation() {
  const navTabs = document.querySelectorAll('.nav-tab-btn, .mobile-dock-btn');
  navTabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      const target = tab.dataset.target;
      switchTab(target);
    });
  });

  // Automatically adapt Leaflet maps on viewport resize / rotation
  window.addEventListener('resize', () => {
    if (mapInstance) {
      mapInstance.invalidateSize();
    }
  });
}

function switchTab(tabId) {
  // Role-based navigation guard & Feature Flag guard
  const userRole = window.CURRENT_USER?.role;
  const isSchedulesEnabled = window.PLATFORM_SETTINGS?.enable_schedules_and_berths == '1' || window.PLATFORM_SETTINGS?.enable_schedules_and_berths === true;

  if (tabId === 'schedules' && !isSchedulesEnabled && userRole !== 'admin') {
    tabId = 'cabs';
    if (typeof showToast === 'function') {
      showToast('Schedules & Berths option is currently disabled.', 'info');
    }
  }

  if (userRole === 'customer') {
    const customerTabs = isSchedulesEnabled ? ['cabs', 'schedules', 'my-bookings'] : ['cabs', 'my-bookings'];
    if (!customerTabs.includes(tabId)) {
      tabId = 'cabs';
    }
  } else if (userRole === 'driver') {
    const driverTabs = ['bidding', 'driver-trips', 'driver-wallet', 'driver-profile'];
    if (!driverTabs.includes(tabId)) {
      tabId = 'driver-trips';
    }
  } else if (!userRole) {
    const guestTabs = isSchedulesEnabled ? ['cabs', 'schedules', 'my-bookings'] : ['cabs', 'my-bookings'];
    if (!guestTabs.includes(tabId)) {
      tabId = 'cabs';
    }
  }

  state.activeTab = tabId;

  // Full-width desktop admin panel layout toggle
  if (tabId === 'admin') {
    document.body.classList.add('admin-active-view');
  } else {
    document.body.classList.remove('admin-active-view');
  }

  // Update header buttons
  document.querySelectorAll('.nav-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.target === tabId);
  });

  // Update mobile bottom dock
  document.querySelectorAll('.mobile-dock-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.target === tabId);
  });

  // Synchronize driver console sub-navigation bar buttons
  document.querySelectorAll('.driver-subnav-btn').forEach(btn => {
    const isMatched = (btn.getAttribute('onclick') || '').includes(`'${tabId}'`);
    btn.classList.toggle('active', isMatched);
  });

  // Show active tab pane
  document.querySelectorAll('.tab-pane').forEach(pane => {
    pane.classList.toggle('active', pane.id === `tab-${tabId}`);
  });

  // Re-size map if cab tab is shown
  if (tabId === 'cabs' && mapInstance) {
    setTimeout(() => {
      mapInstance.invalidateSize();
    }, 200);
  }

  // Refresh live auctions whenever the driver switches to the bidding tab
  if (tabId === 'bidding' && typeof renderBiddingList === 'function') {
    renderBiddingList();
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}
window.switchTab = switchTab;

// ==========================================
// 1. Cabs Module Logic & OpenStreetMap
// ==========================================
function initCabsModule() {
  const fromSelect = document.getElementById('cab-from-city');
  const toSelect = document.getElementById('cab-to-city');
  const swapBtn = document.getElementById('cab-swap-cities-btn');
  const pickupAddr = document.getElementById('cab-pickup-address');
  const dropAddr = document.getElementById('cab-drop-address');

  if (fromSelect && toSelect) {
    fromSelect.addEventListener('change', handleCityChange);
    toSelect.addEventListener('change', handleCityChange);
  }

  if (swapBtn) {
    swapBtn.addEventListener('click', () => {
      const tmp = fromSelect.value;
      fromSelect.value = toSelect.value;
      toSelect.value = tmp;

      if (pickupAddr && dropAddr) {
        const tmpAddr = pickupAddr.value;
        pickupAddr.value = dropAddr.value;
        dropAddr.value = tmpAddr;
      }
      handleCityChange();
    });
  }

  // Initialize Date to Tomorrow by default
  const dateInput = document.getElementById('cab-pickup-date');
  if (dateInput && !dateInput.value) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.value = tomorrow.toISOString().split('T')[0];
  }

  if (dateInput) {
    dateInput.addEventListener('change', () => {
      updateRouteRecapTexts();
    });
  }

  const timeInput = document.getElementById('cab-pickup-time');
  if (timeInput) {
    timeInput.addEventListener('change', () => {
      updateRouteRecapTexts();
    });
  }

  // Trip Type Selector
  document.querySelectorAll('.trip-type-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('.trip-type-pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      state.cab.tripType = pill.dataset.type;

      const returnDateGroup = document.getElementById('return-date-group');
      if (returnDateGroup) {
        returnDateGroup.style.display = state.cab.tripType === 'roundtrip' ? 'flex' : 'none';
      }
      updateTotalCabFare();
      updateRouteRecapTexts();
    });
  });

  // Fleet selection
  document.querySelectorAll('.fleet-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.fleet-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      state.cab.selectedFleet = card.dataset.fleet;
      updateTotalCabFare();
      updateRouteRecapTexts();
    });
  });

  // Add-ons toggles
  const tollCheck = document.getElementById('addon-tolls');
  const petCheck = document.getElementById('addon-pet');
  const nightCheck = document.getElementById('addon-night');
  const luggageCheck = document.getElementById('addon-luggage');

  // Every option below changes what the server charges, so re-quote rather
  // than recomputing locally.
  if (tollCheck) tollCheck.addEventListener('change', (e) => { state.cab.includeTolls = e.target.checked; refreshCabQuote(); });
  if (petCheck) petCheck.addEventListener('change', (e) => {
    state.cab.petFriendly = e.target.checked;
    if (typeof updateDynamicFieldsVisibility === 'function') updateDynamicFieldsVisibility();
    refreshCabQuote();
  });
  if (nightCheck) nightCheck.addEventListener('change', (e) => { state.cab.nightAllowance = e.target.checked; refreshCabQuote(); });
  if (luggageCheck) luggageCheck.addEventListener('change', (e) => { state.cab.luggageCarrier = e.target.checked; refreshCabQuote(); });

  // Coupon Application
  const couponBtn = document.getElementById('apply-coupon-btn');
  if (couponBtn) {
    couponBtn.addEventListener('click', applyCouponCode);
  }

  // Book cab CTA (Direct Backend Integration with Real OTP & Ticket)
  const bookCabBtn = document.getElementById('book-cab-confirm-btn');
  if (bookCabBtn) {
    bookCabBtn.addEventListener('click', handleConfirmCabBooking);
  }

  // Initial text sync
  handleCityChange();
}

// ==========================================
// CAR RENTAL BOOKING FLOW REDESIGN ENGINE
// ==========================================
let activeRentalFilter = 'all';
let activeRentalSort = 'recommended';

window.switchRentalTab = function (type) {
  state.cab.tripType = type;

  // Toggle Tab button active state
  document.querySelectorAll('.rental-type-tab').forEach(tab => {
    tab.classList.toggle('active', tab.dataset.type === type);
  });

  // Toggle panels
  const panels = ['oneway', 'roundtrip', 'local', 'airport'];
  panels.forEach(p => {
    const el = document.getElementById(`rental-form-${p}`);
    if (el) el.style.display = (p === type) ? 'block' : 'none';
  });

  if (type === 'roundtrip') {
    calculateTripDuration();
  }

  if (typeof updateDynamicFieldsVisibility === 'function') {
    updateDynamicFieldsVisibility();
  }

  updateRentalRouteStats();
};

window.syncRentalFromCity = function (val) {
  if (!val) return;
  const cityName = val.split(',')[0].trim();
  const clean = cityName.toLowerCase();
  state.cab.fromCity = clean;
  const legacySelect = document.getElementById('cab-from-city');
  if (legacySelect) {
    let opt = Array.from(legacySelect.options).find(o => o.value === clean);
    if (!opt) {
      opt = document.createElement('option');
      opt.value = clean;
      opt.textContent = cityName;
      legacySelect.appendChild(opt);
    }
    legacySelect.value = clean;
  }
  updateRentalRouteStats();
};

window.syncRentalToCity = function (val) {
  if (!val) return;
  const cityName = val.split(',')[0].trim();
  const clean = cityName.toLowerCase();
  state.cab.toCity = clean;
  const legacySelect = document.getElementById('cab-to-city');
  if (legacySelect) {
    let opt = Array.from(legacySelect.options).find(o => o.value === clean);
    if (!opt) {
      opt = document.createElement('option');
      opt.value = clean;
      opt.textContent = cityName;
      legacySelect.appendChild(opt);
    }
    legacySelect.value = clean;
  }
  updateRentalRouteStats();
};

// ========================================================
// INTERACTIVE CITY PICKER & AUTOCOMPLETE ENGINE
// ========================================================
window.SERVICE_LOCATIONS = window.INITIAL_SERVICE_LOCATIONS || [];
let citySearchDebounceTimer = null;
let currentPickerInputId = null;

function getActiveLocations() {
  if (window.SERVICE_LOCATIONS && window.SERVICE_LOCATIONS.length) {
    return window.SERVICE_LOCATIONS.filter(l => (l.is_active == 1 || l.is_active === true || l.is_active === '1'));
  }
  return Object.entries(CITY_DATABASE).map(([k, c]) => ({
    name: c.name,
    state: c.state,
    latitude: c.lat,
    longitude: c.lng,
    is_active: true
  }));
}

window.openCityPicker = function (inputId) {
  closeAllCityDropdowns();
  currentPickerInputId = inputId;

  // Show the full catalog when opening via focus/click/chevron instead of
  // filtering down to the pre-filled city name (typing still filters live).
  renderCityPickerDropdown(inputId, '');

  // Pre-fetch server suggestions for fast experience
  fetchLocationsFromServer(inputId, '');
};

window.handleCityPickerInput = function (inputId, val) {
  currentPickerInputId = inputId;

  // Typing invalidates coordinates captured from an earlier suggestion, so a
  // stale pair can never price a different city.
  const typedInput = document.getElementById(inputId);
  if (typedInput) {
    delete typedInput.dataset.lat;
    delete typedInput.dataset.lng;
  }

  renderCityPickerDropdown(inputId, val);

  // Debounced server search: long enough that the geocoder is asked once per
  // pause rather than once per keystroke.
  clearTimeout(citySearchDebounceTimer);
  citySearchDebounceTimer = setTimeout(() => {
    fetchLocationsFromServer(inputId, val);
  }, 400);
};

window.DISCOVERED_CITY_CACHE = window.DISCOVERED_CITY_CACHE || [];
const locationSearchCache = new Map();

function rememberClientLocations(locations) {
  if (!Array.isArray(locations)) return;
  locations.forEach(item => {
    if (!item || !item.name || item.needs_geocode) return;
    const lat = Number(item.latitude);
    const lng = Number(item.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const key = `${String(item.name).toLowerCase().trim()}|${String(item.state || '').toLowerCase().trim()}`;
    const existingIdx = window.DISCOVERED_CITY_CACHE.findIndex(
      c => `${String(c.name).toLowerCase().trim()}|${String(c.state || '').toLowerCase().trim()}` === key
    );
    const normalized = {
      name: item.name,
      state: item.state || '',
      latitude: lat,
      longitude: lng,
      airport_code: item.airport_code || null,
      is_registered: Boolean(item.is_registered),
      badge: item.badge || (item.is_registered ? 'Registered Hub' : 'OpenStreetMap'),
      type: item.type || 'geocoded',
      cached: Boolean(item.cached),
      beyond_region: Boolean(item.beyond_region),
    };
    if (existingIdx >= 0) {
      window.DISCOVERED_CITY_CACHE[existingIdx] = normalized;
    } else {
      window.DISCOVERED_CITY_CACHE.push(normalized);
    }
  });
}

function fetchLocationsFromServer(inputId, val) {
  const clean = (val || '').split(',')[0].trim();
  const cacheKey = clean.toLowerCase();

  if (cacheKey.length >= 2 && locationSearchCache.has(cacheKey)) {
    const cachedList = locationSearchCache.get(cacheKey);
    if (currentPickerInputId === inputId) {
      renderCityPickerDropdownItems(inputId, val, cachedList);
    }
    return;
  }

  fetch(`/api/locations/search?q=${encodeURIComponent(clean)}`)
    .then(r => r.json())
    .then(data => {
      if (data && data.success && Array.isArray(data.locations)) {
        const hasActionableResult = data.locations.some(loc => !loc.needs_geocode);
        if (cacheKey.length >= 2 && hasActionableResult) {
          locationSearchCache.set(cacheKey, data.locations);
        }
        rememberClientLocations(data.locations);
        if (currentPickerInputId === inputId) {
          renderCityPickerDropdownItems(inputId, val, data.locations);
        }
      }
    })
    .catch(err => {
      console.warn('Live location search error:', err);
    });
}

window.renderCityPickerDropdown = function (inputId, filterText = '') {
  renderCityPickerDropdownItems(inputId, filterText, null);
};

function renderCityPickerDropdownItems(inputId, filterText = '', serverLocations = null) {
  const dropdown = document.getElementById(`dropdown-${inputId}`);
  if (!dropdown) return;

  const cleanFilter = (filterText || '').split(',')[0].toLowerCase().trim();
  let list = [];

  if (serverLocations && Array.isArray(serverLocations) && serverLocations.length > 0) {
    list = serverLocations;
  } else {
    const activeLocs = getActiveLocations().sort((a, b) => a.name.localeCompare(b.name));
    const combined = [...activeLocs, ...window.DISCOVERED_CITY_CACHE];
    list = cleanFilter
      ? combined.filter(l => (l.name && l.name.toLowerCase().includes(cleanFilter)) || (l.state && l.state.toLowerCase().includes(cleanFilter)))
      : activeLocs;
  }

  // Deduplicate items by lowercase name
  const seen = new Set();
  const uniqueList = [];
  list.forEach(item => {
    const key = (item.name || '').toLowerCase().trim();
    if (!seen.has(key)) {
      seen.add(key);
      uniqueList.push(item);
    }
  });

  let html = '';

  if (PICKUP_PICKER_INPUTS.includes(inputId)) {
    html += `
      <div class="city-picker-detect-row">
        <button type="button" class="city-picker-detect-btn" onmousedown="event.preventDefault(); detectInitialLocation('${inputId}', 'gps', event)">
          <span class="material-symbols-outlined" style="font-size:15px; color:#20A9D2;">my_location</span>
          <span>Use GPS Location</span>
        </button>
        <button type="button" class="city-picker-detect-btn" onmousedown="event.preventDefault(); detectInitialLocation('${inputId}', 'network', event)">
          <span class="material-symbols-outlined" style="font-size:15px; color:#0284c7;">wifi</span>
          <span>Detect via Network</span>
        </button>
      </div>
    `;
  }

  // Render locations list
  if (uniqueList.length > 0) {
    const activeInputVal = (document.getElementById(inputId)?.value || '').split(',')[0].toLowerCase().trim();
    html += uniqueList.map(l => {
      const safeName = (l.name || '').replace(/'/g, "\\'");
      const safeState = (l.state || '').replace(/'/g, "\\'");
      const isSelected = activeInputVal !== '' && (l.name || '').toLowerCase().trim() === activeInputVal;
      const numericLat = Number(l.latitude);
      const numericLng = Number(l.longitude);
      const hasCoords = l.latitude !== null && l.latitude !== undefined &&
        l.longitude !== null && l.longitude !== undefined &&
        Number.isFinite(numericLat) && Number.isFinite(numericLng);

      let badgeHtml = '';
      if (l.needs_geocode) {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#fee2e2; color:#b91c1c; font-size:10px;">No coordinates</span>`;
      } else if (l.airport_code) {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#e0f2fe; color:#0284c7;">${l.airport_code}</span>`;
      } else if (l.is_registered || l.badge === 'Registered Hub') {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#dcfce7; color:#15803d; font-size:10px;">Registered Hub</span>`;
      } else if (l.badge === 'OpenStreetMap') {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#e0f2fe; color:#0369a1; font-size:10px;">OpenStreetMap</span>`;
      } else if (l.is_custom || l.badge === 'Desired Location') {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#fef3c7; color:#b45309; font-size:10px;">Desired Location</span>`;
      } else {
        badgeHtml = `<span class="city-picker-item-badge" style="background:#f1f5f9; color:#475569; font-size:10px;">Regional Route</span>`;
      }

      const icon = l.airport_code ? 'flight' : (l.is_custom ? 'pin_drop' : 'location_city');

      return `
        <div class="city-picker-item${isSelected ? ' highlighted' : ''}" ${hasCoords
          ? `onmousedown="event.preventDefault(); selectCityFromPicker('${inputId}', '${safeName}', '${safeState}', ${numericLat}, ${numericLng}, { fromPointer: true })"`
          : 'style="opacity:.55; cursor:not-allowed;" title="No coordinates for this place — type a nearby town or city"'}>
          <div class="city-picker-item-main">
            <span class="material-symbols-outlined" style="font-size:16px; color:${l.is_custom ? '#f59e0b' : '#64748b'};">${icon}</span>
            <div>
              <span class="city-picker-item-name">${l.name}</span>
              <span class="city-picker-item-state">${l.state ? `(${l.state})` : ''}</span>
            </div>
          </div>
          ${badgeHtml}
        </div>
      `;
    }).join('');
  }

  // The server already returns the typed text as a "custom" entry, with real
  // coordinates when it can ground the name. A client-side extra pinned to a
  // default city would price an unknown place as that city, so it is gone.

  if (!html) {
    html = `<div style="padding:10px 12px; font-size:12px; color:#94a3b8; text-align:center;">No locations found for "${filterText}"</div>`;
  }

  dropdown.innerHTML = html;
  dropdown.style.display = 'block';
}

const NEXT_BOOKING_FIELD_MAP = {
  'rental-from-city': 'rental-to-city',
  'rental-to-city': 'rental-pickup-date',
  'rental-rt-from': 'rental-rt-to',
  'rental-rt-to': 'rental-rt-pickup-date',
  'rental-local-city': 'rental-local-date',
  'rental-airport-name': 'rental-airport-address',
  'rental-airport-address': 'rental-airport-date',
};

let suppressCityDropdownCloseUntil = 0;

window.advanceBookingFieldFocus = function (currentInputId) {
  const nextId = NEXT_BOOKING_FIELD_MAP[currentInputId];
  if (!nextId) return;

  const nextEl = document.getElementById(nextId);
  if (!nextEl || nextEl.offsetParent === null) return;

  nextEl.focus({ preventScroll: true });
  if (typeof nextEl.select === 'function' && nextEl.type === 'text') {
    nextEl.select();
  }
};

window.selectCityFromPicker = function (inputId, cityName, stateName, lat, lng, options = {}) {
  const input = document.getElementById(inputId);
  if (!input) return;

  if (options && options.fromPointer) {
    suppressCityDropdownCloseUntil = Date.now() + 350;
  }

  const numericLat = Number(lat);
  const numericLng = Number(lng);
  const hasCoords = Number.isFinite(numericLat) && Number.isFinite(numericLng) &&
    Math.abs(numericLat) <= 90 && Math.abs(numericLng) <= 180 &&
    !(numericLat === 0 && numericLng === 0);

  const formatted = stateName && !stateName.includes('Desired') ? `${cityName}, ${stateName}` : cityName;
  input.value = formatted;

  // Remember the exact coordinates of the suggestion so the quote can be
  // priced from them instead of re-geocoding the name. Any typing clears them.
  if (hasCoords) {
    input.dataset.lat = String(numericLat);
    input.dataset.lng = String(numericLng);
  } else {
    delete input.dataset.lat;
    delete input.dataset.lng;
  }

  // Cache coordinates in client CITY_DATABASE
  const cleanKey = cityName.toLowerCase().trim();
  const slugKey = cityName.toLowerCase().replace(/[^a-z0-9]/g, '');
  if (hasCoords) {
    const entry = {
      name: cityName,
      state: stateName || '',
      lat: numericLat,
      lng: numericLng
    };
    CITY_DATABASE[cleanKey] = entry;
    CITY_DATABASE[slugKey] = entry;
  }

  const dropdown = document.getElementById(`dropdown-${inputId}`);
  if (dropdown) dropdown.style.display = 'none';

  if (inputId === 'rental-from-city') {
    syncRentalFromCity(formatted);
  } else if (inputId === 'rental-to-city') {
    syncRentalToCity(formatted);
  } else if (inputId === 'rental-rt-from' || inputId === 'rental-rt-to' || inputId === 'rental-local-city') {
    updateRentalRouteStats();
  }

  if (options.autoAdvance !== false && NEXT_BOOKING_FIELD_MAP[inputId]) {
    requestAnimationFrame(() => {
      window.advanceBookingFieldFocus(inputId);
    });
  }
};

window.toggleCityDropdown = function (inputId, e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
  }
  const dropdown = document.getElementById(`dropdown-${inputId}`);
  if (!dropdown) return;
  if (dropdown.style.display === 'block') {
    dropdown.style.display = 'none';
  } else {
    closeAllCityDropdowns();
    openCityPicker(inputId);
  }
};

window.closeAllCityDropdowns = function () {
  document.querySelectorAll('.city-picker-dropdown').forEach(dd => {
    dd.style.display = 'none';
  });
};

// ========================================================
// INITIAL PICKUP LOCATION DETECTION (GPS & NETWORK)
// ========================================================
const PICKUP_PICKER_INPUTS = ['rental-from-city', 'rental-rt-from', 'rental-local-city'];

function activePickupInputId() {
  const tripType = state.cab.tripType || 'oneway';
  if (tripType === 'roundtrip') return 'rental-rt-from';
  if (tripType === 'local') return 'rental-local-city';
  if (tripType === 'airport') return 'rental-airport-address';
  return 'rental-from-city';
}

function setGeoDetectionStatus(text, isError = false) {
  const statusEl = document.getElementById('rental-geo-status');
  if (!statusEl) return;
  statusEl.textContent = text || '';
  statusEl.style.color = isError ? '#b91c1c' : '#0369a1';
}

function setGeoButtonsDisabled(disabled) {
  ['btn-detect-pickup-gps', 'btn-detect-pickup-network'].forEach(id => {
    const btn = document.getElementById(id);
    if (btn) btn.disabled = disabled;
  });
}

function getBrowserCoordinates() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('GPS geolocation is not supported in this browser.'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        lat: pos.coords.latitude,
        lng: pos.coords.longitude
      }),
      (err) => {
        if (err && err.code === 1) {
          reject(new Error('Location permission denied. Allow browser location or click "Use Network".'));
        } else if (err && err.code === 3) {
          reject(new Error('GPS timed out. Try again or click "Use Network".'));
        } else {
          reject(new Error('GPS location unavailable. Try "Use Network" instead.'));
        }
      },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
    );
  });
}

function applyDetectedLocation(inputId, loc, source) {
  if (!loc || !loc.name) return;

  const formatted = loc.state ? `${loc.name}, ${loc.state}` : loc.name;
  const sourceLabel = source === 'gps' ? 'GPS' : 'Network';

  if (inputId === 'cab-pickup-address') {
    const addrInput = document.getElementById('cab-pickup-address');
    if (addrInput) {
      addrInput.value = loc.display_name || formatted;
    }
    const activeEl = document.getElementById(activePickupInputId());
    if (activeEl && Number.isFinite(Number(loc.latitude)) && Number.isFinite(Number(loc.longitude))) {
      activeEl.dataset.lat = String(loc.latitude);
      activeEl.dataset.lng = String(loc.longitude);
    }
    showToast(`Pickup address updated via ${sourceLabel}: ${loc.display_name || formatted}`, 'success');
    return;
  }

  if (inputId === 'rental-airport-address') {
    const aptAddr = document.getElementById('rental-airport-address');
    if (aptAddr) {
      aptAddr.value = loc.display_name || formatted;
      if (Number.isFinite(Number(loc.latitude)) && Number.isFinite(Number(loc.longitude))) {
        aptAddr.dataset.lat = String(loc.latitude);
        aptAddr.dataset.lng = String(loc.longitude);
      }
    }
    state.cab.detectedPickup = {
      cityFormatted: formatted,
      displayName: loc.display_name || formatted,
      source: source
    };
    updateRentalRouteStats();
    setGeoDetectionStatus(`${sourceLabel}: ${formatted}`);
    showToast(`Detected via ${sourceLabel}: ${formatted}`, 'success');
    requestAnimationFrame(() => {
      window.advanceBookingFieldFocus('rental-airport-address');
    });
    return;
  }

  selectCityFromPicker(inputId, loc.name, loc.state || '', loc.latitude, loc.longitude);
  state.cab.detectedPickup = {
    cityFormatted: formatted,
    displayName: loc.display_name || formatted,
    source: source
  };

  setGeoDetectionStatus(`${sourceLabel}: ${formatted}`);
  showToast(`Initial pickup set via ${sourceLabel}: ${formatted}`, 'success');
}

window.detectActiveBookingLocation = function (mode = 'auto') {
  return window.detectInitialLocation(activePickupInputId(), mode);
};

window.detectInitialLocation = async function (inputId, mode = 'auto', event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  closeAllCityDropdowns();
  setGeoButtonsDisabled(true);
  setGeoDetectionStatus(mode === 'network' ? 'Detecting via network...' : 'Detecting location...');

  try {
    let url = '/api/locations/detect';

    if (mode === 'gps' || mode === 'auto') {
      try {
        const coords = await getBrowserCoordinates();
        url += `?mode=gps&lat=${encodeURIComponent(coords.lat)}&lng=${encodeURIComponent(coords.lng)}`;
      } catch (gpsErr) {
        if (mode === 'gps') {
          setGeoDetectionStatus(gpsErr.message, true);
          showToast(gpsErr.message, 'warning');
          return;
        }
        // Auto mode seamlessly falls back to approximate network detection.
        url += '?mode=network';
      }
    } else {
      url += '?mode=network';
    }

    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    if (!res.ok || !data.success || !data.location) {
      const msg = (data && data.message) || 'Could not detect initial pickup location.';
      setGeoDetectionStatus(msg, true);
      showToast(msg, 'warning');
      return;
    }

    applyDetectedLocation(inputId, data.location, data.source || mode);
  } catch (err) {
    console.warn('Location detection error:', err);
    const msg = 'Unable to detect location right now. Please type your pickup city.';
    setGeoDetectionStatus(msg, true);
    showToast(msg, 'warning');
  } finally {
    setGeoButtonsDisabled(false);
  }
};

document.addEventListener('click', function (e) {
  if (Date.now() < suppressCityDropdownCloseUntil) {
    suppressCityDropdownCloseUntil = 0;
    return;
  }
  if (!e.target.closest('.rental-input-wrap')) {
    closeAllCityDropdowns();
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeAllCityDropdowns();
    return;
  }
  if (e.key === 'Enter' && e.target && e.target.id && NEXT_BOOKING_FIELD_MAP[e.target.id]) {
    e.preventDefault();
    const inputId = e.target.id;
    const dropdown = document.getElementById(`dropdown-${inputId}`);
    if (dropdown && dropdown.style.display === 'block' && !e.target.dataset.lat) {
      const firstItem = dropdown.querySelector('.city-picker-item[onmousedown]');
      if (firstItem) {
        firstItem.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
        return;
      }
    }
    closeAllCityDropdowns();
    window.advanceBookingFieldFocus(inputId);
  }
});

document.addEventListener('change', function (e) {
  if (e.target && e.target.id === 'rental-airport-name' && String(e.target.value || '').trim() !== '') {
    requestAnimationFrame(() => {
      window.advanceBookingFieldFocus('rental-airport-name');
    });
  }
});

// Sync Car Types across Cab Booking, Filter Pills, and Manual/Edit Booking Dropdowns
window.syncAllCarTypes = async function (carTypesList) {
  let carTypes = carTypesList;
  if (carTypes && !Array.isArray(carTypes) && Array.isArray(carTypes.car_types)) {
    carTypes = carTypes.car_types;
  }
  if (!carTypes) {
    try {
      const res = await fetch('/api/car-types');
      const data = await res.json();
      if (data.success && Array.isArray(data.car_types)) {
        carTypes = data.car_types;
      }
    } catch (e) {
      return;
    }
  }
  if (!Array.isArray(carTypes)) return;

  const activeTypes = carTypes.filter(ct => ct.is_active !== false && ct.is_active !== 0 && ct.is_active !== '0');
  if (activeTypes.length > 0) {
    const nextFleets = {};
    activeTypes.forEach(ct => {
      const key = String(ct.category_key || '').toLowerCase().trim();
      if (!key) return;
      nextFleets[key] = {
        category: key,
        name: ct.name,
        models: ct.models || '',
        seats: String(ct.seats || '4 Seats'),
        luggage: String(ct.luggage || '2 Bags'),
        ratePerKm: Number(ct.rate_per_km) || 12,
        badge: ct.badge || 'Popular',
        extraKm: Number(ct.extra_km_rate || ct.rate_per_km) || 12,
        image: ct.image_url || 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80'
      };
    });
    if (Object.keys(nextFleets).length > 0) {
      FLEET_TYPES = nextFleets;
      if (!FLEET_TYPES[state.cab.selectedFleet]) {
        state.cab.selectedFleet = Object.keys(FLEET_TYPES)[0];
      }
    }
  }

  // Update Cab Booking filter pills
  const pillsContainer = document.getElementById('rental-filter-pills');
  if (pillsContainer) {
    const activeCat = activeRentalFilter || 'all';
    const pillsHtml = [
      `<button type="button" class="rental-filter-pill ${activeCat === 'all' ? 'active' : ''}" data-category="all" onclick="filterRentalCategory('all', this)">All Cabs</button>`,
      ...activeTypes.map(ct => {
        const key = String(ct.category_key || '').toLowerCase().trim();
        return `<button type="button" class="rental-filter-pill ${activeCat === key ? 'active' : ''}" data-category="${key}" onclick="filterRentalCategory('${key}', this)">${ct.name}</button>`;
      })
    ].join('');
    pillsContainer.innerHTML = pillsHtml;
  }

  // Update Manual, Edit Booking, and Booking Type Car Type dropdowns
  ['add-cab-type', 'edit-cab-type'].forEach(selectId => {
    const select = document.getElementById(selectId);
    if (!select) return;
    const prevVal = select.value;
    select.innerHTML = '<option value="">-- Auto / Unspecified --</option>' +
      activeTypes.map(ct => {
        const key = String(ct.category_key || '').toLowerCase().trim();
        return `<option value="${key}">${ct.name}</option>`;
      }).join('');
    if (prevVal) {
      if (![...select.options].some(o => o.value === prevVal)) {
        const extraOpt = document.createElement('option');
        extraOpt.value = prevVal;
        extraOpt.textContent = prevVal.toUpperCase();
        select.appendChild(extraOpt);
      }
      select.value = prevVal;
    }
  });

  document.querySelectorAll('.booking-type-car-select').forEach(select => {
    const prevVal = select.value;
    select.innerHTML = '<option value="">-- Any / All Car Types --</option>' +
      activeTypes.map(ct => {
        const key = String(ct.category_key || '').toLowerCase().trim();
        return `<option value="${key}">${ct.name}</option>`;
      }).join('');
    if (prevVal) {
      if (![...select.options].some(o => o.value === prevVal)) {
        const extraOpt = document.createElement('option');
        extraOpt.value = prevVal;
        extraOpt.textContent = prevVal.toUpperCase();
        select.appendChild(extraOpt);
      }
      select.value = prevVal;
    }
  });

  if (typeof renderRentalVehicles === 'function' && document.getElementById('rental-vehicles-grid')) {
    renderRentalVehicles(activeRentalFilter, activeRentalSort);
  }

  if (typeof window.refreshAdminRouteFareDisplays === 'function') {
    window.refreshAdminRouteFareDisplays();
  }
};

// ========================================================
// REAL-TIME LOCATION & ROUTE SYNCHRONIZATION
// ========================================================
window.syncAllLocationDropdowns = async function (locationsList) {
  let locs = locationsList;
  if (locs && !Array.isArray(locs) && Array.isArray(locs.locations)) {
    locs = locs.locations;
  }
  if (!locs) {
    try {
      const res = await fetch('/api/locations');
      const data = await res.json();
      if (data && data.success && Array.isArray(data.locations)) {
        locs = data.locations;
      }
    } catch (err) {
      console.warn('Sync /api/locations error:', err);
    }
  }

  if (!Array.isArray(locs)) return;
  window.SERVICE_LOCATIONS = locs;

  // 1. Update mutable CITY_DATABASE
  locs.forEach(loc => {
    if (!loc || !loc.name) return;
    const cleanKey = loc.name.toLowerCase().trim();
    const slugKey = loc.name.toLowerCase().replace(/[^a-z0-9]/g, '');
    const entry = {
      name: loc.name,
      state: loc.state || '',
      lat: parseFloat(loc.latitude) || 22.5726,
      lng: parseFloat(loc.longitude) || 88.3639
    };
    CITY_DATABASE[cleanKey] = entry;
    CITY_DATABASE[slugKey] = entry;
  });

  // 2. Update #cities-datalist
  const datalist = document.getElementById('cities-datalist');
  if (datalist) {
    datalist.innerHTML = '';
    locs.filter(l => (l.is_active == 1 || l.is_active === true || l.is_active === '1')).forEach(l => {
      const opt1 = document.createElement('option');
      opt1.value = `${l.name}, ${l.state}`;
      opt1.textContent = l.name;
      datalist.appendChild(opt1);

      const opt2 = document.createElement('option');
      opt2.value = l.name;
      opt2.textContent = l.state;
      datalist.appendChild(opt2);
    });
  }

  // 3. Update #route-cities-list
  const routeDatalist = document.getElementById('route-cities-list');
  if (routeDatalist) {
    routeDatalist.innerHTML = '';
    locs.forEach(l => {
      const opt = document.createElement('option');
      opt.value = l.name;
      routeDatalist.appendChild(opt);
    });
  }

  // 4. Update legacy hidden selects (#cab-from-city, #cab-to-city)
  ['cab-from-city', 'cab-to-city'].forEach(id => {
    const sel = document.getElementById(id);
    if (sel) {
      const currentVal = sel.value;
      sel.innerHTML = '';
      locs.filter(l => (l.is_active == 1 || l.is_active === true || l.is_active === '1')).forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.name.toLowerCase().trim();
        opt.textContent = l.name;
        if (opt.value === currentVal) opt.selected = true;
        sel.appendChild(opt);
      });
    }
  });

  // 5. Update Admin Manual Booking selects (#add-from-city, #add-to-city)
  ['add-from-city', 'add-to-city'].forEach(id => {
    const sel = document.getElementById(id);
    if (sel) {
      const currentVal = sel.value;
      sel.innerHTML = '<option value="">Select City</option>';
      locs.filter(l => (l.is_active == 1 || l.is_active === true || l.is_active === '1')).forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.name;
        opt.textContent = `${l.name}, ${l.state}`;
        if (opt.value === currentVal) opt.selected = true;
        sel.appendChild(opt);
      });
    }
  });

  // Re-render any visible dropdowns
  document.querySelectorAll('.city-picker-dropdown').forEach(dd => {
    if (dd.style.display === 'block') {
      const inputId = dd.id.replace('dropdown-', '');
      renderCityPickerDropdown(inputId, '');
    }
  });
};

window.swapRentalCities = function () {
  const fromInput = document.getElementById('rental-from-city');
  const toInput = document.getElementById('rental-to-city');
  if (fromInput && toInput) {
    const tmp = fromInput.value;
    fromInput.value = toInput.value;
    toInput.value = tmp;
    syncRentalFromCity(fromInput.value);
    syncRentalToCity(toInput.value);
  }
};

window.syncRentalDate = function (val) {
  const hiddenDate = document.getElementById('cab-pickup-date');
  if (hiddenDate) hiddenDate.value = val;
  updateRouteRecapTexts();
};

window.syncRentalTime = function (val) {
  const hiddenTime = document.getElementById('cab-pickup-time');
  if (hiddenTime) hiddenTime.value = val;
  updateRouteRecapTexts();
};

window.calculateTripDuration = function () {
  const pDateVal = document.getElementById('rental-rt-pickup-date')?.value;
  const rDateVal = document.getElementById('rental-rt-return-date')?.value;
  const pill = document.getElementById('rental-rt-duration-pill');
  if (pDateVal && rDateVal && pill) {
    const d1 = new Date(pDateVal);
    const d2 = new Date(rDateVal);
    const diffTime = d2 - d1;
    const diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
    pill.textContent = `Trip Duration: ${diffDays} Day${diffDays > 1 ? 's' : ''}`;
  }
};

window.toggleAirportDirection = function (dir) {
  const l1 = document.getElementById('rental-airport-label-1');
  const l2 = document.getElementById('rental-airport-label-2');
  if (dir === 'pickup') {
    if (l1) l1.textContent = 'AIRPORT / TERMINAL';
    if (l2) l2.textContent = 'DESTINATION ADDRESS';
  } else {
    if (l1) l1.textContent = 'PICKUP ADDRESS';
    if (l2) l2.textContent = 'AIRPORT / TERMINAL';
  }
};

function updateRentalRouteStats() {
  let from = state.cab.fromCity || 'kolkata';
  let to = state.cab.toCity || 'mayapur';

  if (state.cab.tripType === 'oneway') {
    const rawFrom = document.getElementById('rental-from-city')?.value?.split(',')[0].trim().toLowerCase().replace(/[^a-z0-9]/g, '') || from;
    const rawTo = document.getElementById('rental-to-city')?.value?.split(',')[0].trim().toLowerCase().replace(/[^a-z0-9]/g, '') || to;
    from = rawFrom;
    to = rawTo;
    state.cab.fromCity = from;
    state.cab.toCity = to;
  } else if (state.cab.tripType === 'roundtrip') {
    from = document.getElementById('rental-rt-from')?.value?.split(',')[0].trim().toLowerCase() || from;
    to = document.getElementById('rental-rt-to')?.value?.split(',')[0].trim().toLowerCase() || to;
    state.cab.fromCity = from;
    state.cab.toCity = to;
  } else if (state.cab.tripType === 'local') {
    from = document.getElementById('rental-local-city')?.value?.split(',')[0].trim().toLowerCase() || from;
    to = from;
  } else if (state.cab.tripType === 'airport') {
    from = 'kolkata';
    to = 'kolkata';
  }

  const ALIAS_MAP = {
    'berhampore': 'baharampur',
    'berhampur': 'baharampur',
    'baharampur': 'berhampore',
    'calcutta': 'kolkata',
    'dighabeach': 'digha',
    'newdigha': 'digha',
    'olddigha': 'digha',
    'burdwan': 'bardhaman',
    'santiniketan': 'bolpur',
    'bangalore': 'bengaluru',
    'bombay': 'mumbai',
    'madras': 'chennai',
    'panaji': 'goa',
    'panjim': 'goa'
  };

  const routeKey = `${from}-${to}`;
  const aliasKey = `${ALIAS_MAP[from] || from}-${ALIAS_MAP[to] || to}`;
  const reverseKey = `${to}-${from}`;
  const reverseAliasKey = `${ALIAS_MAP[to] || to}-${ALIAS_MAP[from] || from}`;

  let route = ROUTE_MATRIX[routeKey] || ROUTE_MATRIX[aliasKey] || ROUTE_MATRIX[reverseKey] || ROUTE_MATRIX[reverseAliasKey];

  if (!route) {
    // Dynamic distance calculation based on coordinates if available
    const c1 = CITY_DATABASE[from] || CITY_DATABASE[ALIAS_MAP[from]];
    const c2 = CITY_DATABASE[to] || CITY_DATABASE[ALIAS_MAP[to]];
    if (c1 && c2) {
      const rad = Math.PI / 180;
      const dLat = (c2.lat - c1.lat) * rad;
      const dLng = (c2.lng - c1.lng) * rad;
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(c1.lat * rad) * Math.cos(c2.lat * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      const straightKm = Math.round(6371 * c);
      const roadKm = Math.round(straightKm * 1.25);
      const roadDist = Math.max(roadKm, 25);
      const totalMins = Math.max(20, Math.round((roadDist / 45) * 60));
      const hours = Math.floor(totalMins / 60);
      const mins = totalMins % 60;
      const timeStr = hours > 0 ? (mins > 0 ? `${hours}h ${mins}m` : `${hours}h`) : `${mins}m`;
      route = { dist: roadDist, time: timeStr, toll: Math.round(roadDist * 0.9) };
    } else {
      route = { dist: 345, time: "7h 30m", toll: 320 };
    }
  }

  state.cab.distance = route.dist;
  state.cab.travelTime = route.time;
  state.cab.tollEstimate = route.toll;

  updateRouteRecapTexts();
  updateTotalCabFare();

  // Server-side route + price calculation (single source of truth, P1-2)
  refreshCabQuote();
}

// ------------------------------------------------------------------
// Server-quoted pricing (P1-2).
//
// The browser no longer decides what a trip costs. It asks
// /api/routes/calculate for the same itemisation the booking endpoint will
// charge (base, tolls, add-ons, coupon) and renders those numbers. If the
// server cannot measure the route it says so, and no fare is shown.
// ------------------------------------------------------------------
function readInputCoords(el) {
  if (!el) return null;
  const lat = parseFloat(el.dataset.lat);
  const lng = parseFloat(el.dataset.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  return { lat, lng };
}

function cabQuoteParams() {
  const tripType = state.cab.tripType || 'oneway';
  const params = new URLSearchParams();

  let from = '';
  let to = '';

  if (tripType === 'local') {
    from = document.getElementById('rental-local-city')?.value.trim() || 'Kolkata';
    to = from;
    params.set('local_package', document.getElementById('rental-local-package')?.value || '8_80');
  } else if (tripType === 'roundtrip') {
    from = document.getElementById('rental-rt-from')?.value.trim() || '';
    to = document.getElementById('rental-rt-to')?.value.trim() || '';
  } else if (tripType === 'airport') {
    const dir = document.querySelector('input[name="airport_direction"]:checked')?.value || 'pickup';
    const apt = document.getElementById('rental-airport-name')?.value || 'Airport';
    const city = document.getElementById('rental-airport-address')?.value || 'City Area';
    from = dir === 'pickup' ? city : apt;
    to = dir === 'pickup' ? apt : city;
    params.set('airport_type', dir);
  } else {
    from = document.getElementById('rental-from-city')?.value.trim() || '';
    to = document.getElementById('rental-to-city')?.value.trim() || '';
  }

  if (!from || !to) return null;

  params.set('from', from);
  params.set('to', to);
  params.set('trip_type', tripType);

  // When both ends came from a suggestion we know their exact coordinates, so
  // the server can price the real route without geocoding the names again.
  if (tripType === 'oneway' || tripType === 'roundtrip') {
    const fromEl = document.getElementById(tripType === 'roundtrip' ? 'rental-rt-from' : 'rental-from-city');
    const toEl = document.getElementById(tripType === 'roundtrip' ? 'rental-rt-to' : 'rental-to-city');
    const fromCoords = readInputCoords(fromEl);
    const toCoords = readInputCoords(toEl);

    if (fromCoords) {
      params.set('from_lat', fromCoords.lat);
      params.set('from_lng', fromCoords.lng);
    }
    if (toCoords) {
      params.set('to_lat', toCoords.lat);
      params.set('to_lng', toCoords.lng);
    }
  }

  params.set('fleet', state.cab.selectedFleet || 'suv');
  params.set('include_tolls', state.cab.includeTolls ? '1' : '0');
  if (state.cab.petFriendly) params.set('pet_friendly', '1');
  if (state.cab.nightAllowance) params.set('night_allowance', '1');
  if (state.cab.luggageCarrier) params.set('luggage_carrier', '1');
  if (state.cab.appliedCoupon) params.set('coupon', state.cab.appliedCoupon);

  return params;
}

let cabQuoteRequestId = 0;

async function refreshCabQuote() {
  const params = cabQuoteParams();

  if (!params) {
    state.cab.serverQuote = null;
    state.cab.quoteError = null;
    return;
  }

  const requestId = ++cabQuoteRequestId;

  try {
    const res = await fetch('/api/routes/calculate?' + params.toString());
    const data = await res.json();

    // A later request supersedes this one (fast typing must not flicker).
    if (requestId !== cabQuoteRequestId) return;

    if (data && data.success) {
      state.cab.serverQuote = data;
      state.cab.quoteError = null;

      if (data.route) {
        state.cab.distance = data.route.distance_km;
        state.cab.travelTime = data.route.estimated_time;
        state.cab.tollEstimate = data.route.base_toll;
        updateRouteRecapTexts();
      }
      if (typeof syncBeyondRegionNoticeUi === 'function') syncBeyondRegionNoticeUi();

      // The coupon may have been rejected by the server: reflect that.
      if (state.cab.appliedCoupon && data.quote && data.quote.coupon && !data.quote.coupon.applied) {
        state.cab.appliedCoupon = null;
        const msgEl = document.getElementById('coupon-msg');
        if (msgEl) {
          msgEl.textContent = '❌ ' + (data.quote.coupon.message || 'Coupon could not be applied.');
          msgEl.style.color = '#dc2626';
        }
      }
    } else {
      state.cab.serverQuote = null;
      state.cab.quoteError = (data && data.message) || 'We could not price this trip.';
      const msgEl = document.getElementById('coupon-msg');
      if (msgEl && state.cab.appliedCoupon) {
        msgEl.textContent = '❌ ' + state.cab.quoteError;
        msgEl.style.color = '#dc2626';
      }
    }
  } catch (err) {
    if (requestId === cabQuoteRequestId) {
      state.cab.serverQuote = null;
    }
  }

  if (state.cab.currentStep === 2) {
    renderRentalVehicles(activeRentalFilter, activeRentalSort);
  } else if (state.cab.currentStep === 3) {
    updateTotalCabFare();
  }
}

function serverFleetTotal(fleetKey) {
  const breakdown = state.cab.serverQuote && state.cab.serverQuote.itemized_breakdown;

  if (breakdown && breakdown[fleetKey] && typeof breakdown[fleetKey].total === 'number') {
    return breakdown[fleetKey].total;
  }

  return null;
}

// Resolve optional preferred/specific car type configured under a Route, Local Package, Airport Hub, or Booking Type rule
function resolvePreferredCarTypeForBooking(overrides = {}) {
  const tripType = (overrides.tripType || state.cab.tripType || 'oneway').toLowerCase();
  let preferred = '';

  if (tripType === 'oneway' || tripType === 'roundtrip') {
    const fromVal = (overrides.fromCity ?? (tripType === 'roundtrip'
      ? document.getElementById('rental-rt-from')?.value
      : document.getElementById('rental-from-city')?.value) ?? '').split(',')[0].trim().toLowerCase();
    const toVal = (overrides.toCity ?? (tripType === 'roundtrip'
      ? document.getElementById('rental-rt-to')?.value
      : document.getElementById('rental-to-city')?.value) ?? '').split(',')[0].trim().toLowerCase();

    if (fromVal && toVal && Array.isArray(window.INITIAL_CAB_ROUTES)) {
      const route = window.INITIAL_CAB_ROUTES.find(r =>
        r && r.is_active !== false && r.is_active !== 0 &&
        String(r.from_city || '').trim().toLowerCase() === fromVal &&
        String(r.to_city || '').trim().toLowerCase() === toVal
      );
      if (route && route.default_car_type) {
        preferred = String(route.default_car_type).trim().toLowerCase();
      }
    }
  } else if (tripType === 'local') {
    const pkgCode = (overrides.localPackage ?? document.getElementById('rental-local-package')?.value ?? '').trim();
    if (pkgCode && Array.isArray(window.INITIAL_LOCAL_PACKAGES)) {
      const pkg = window.INITIAL_LOCAL_PACKAGES.find(p =>
        p && p.is_active !== false && p.is_active !== 0 &&
        String(p.package_code || '').trim() === pkgCode
      );
      if (pkg && pkg.default_car_type) {
        preferred = String(pkg.default_car_type).trim().toLowerCase();
      }
    }
  } else if (tripType === 'airport') {
    const airportText = (overrides.airportText ?? document.getElementById('rental-airport-name')?.value ?? '').trim().toLowerCase();
    if (airportText && Array.isArray(window.INITIAL_AIRPORT_HUBS)) {
      const hub = window.INITIAL_AIRPORT_HUBS.find(a => {
        if (!a || a.is_active === false || a.is_active === 0) return false;
        const code = String(a.iata_code || '').trim().toLowerCase();
        const name = String(a.airport_name || '').trim().toLowerCase();
        const city = String(a.city || '').trim().toLowerCase();
        return (code && airportText.includes(code)) ||
          (name && airportText.includes(name)) ||
          (city && airportText.includes(city));
      });
      if (hub && hub.default_car_type) {
        preferred = String(hub.default_car_type).trim().toLowerCase();
      }
    }
  }

  if (!preferred && window.PLATFORM_SETTINGS) {
    const ruleVal = window.PLATFORM_SETTINGS[`default_car_type_${tripType}`];
    if (ruleVal) preferred = String(ruleVal).trim().toLowerCase();
  }

  if (preferred && (overrides.allowUnknown || FLEET_TYPES[preferred])) {
    return preferred;
  }
  return '';
}

// Explore Cabs Validation & Results Navigation
window.exploreCabs = function () {
  const tripType = state.cab.tripType || 'oneway';

  if (tripType === 'oneway') {
    const fromVal = document.getElementById('rental-from-city')?.value.trim();
    const toVal = document.getElementById('rental-to-city')?.value.trim();
    if (!fromVal) {
      showToast('Please enter a Pickup City / Location.', 'warning');
      document.getElementById('rental-from-city')?.focus();
      return;
    }
    if (!toVal) {
      showToast('Please enter a Destination City / Location.', 'warning');
      document.getElementById('rental-to-city')?.focus();
      return;
    }
    if (fromVal.toLowerCase() === toVal.toLowerCase()) {
      showToast('Pickup and Destination cannot be identical for One Way trips.', 'warning');
      return;
    }
    syncRentalFromCity(fromVal);
    syncRentalToCity(toVal);
  } else if (tripType === 'roundtrip') {
    const rtFrom = document.getElementById('rental-rt-from')?.value.trim();
    const rtTo = document.getElementById('rental-rt-to')?.value.trim();
    if (!rtFrom || !rtTo) {
      showToast('Please specify both Pickup and Destination for Round Trip.', 'warning');
      return;
    }
  } else if (tripType === 'local') {
    const localCity = document.getElementById('rental-local-city')?.value.trim();
    if (!localCity) {
      showToast('Please select a City for Local Rental.', 'warning');
      return;
    }
  }

  // Apply optional specific Car Type configured for this booking type/route/package/hub
  const preferredCarType = resolvePreferredCarTypeForBooking();
  if (preferredCarType) {
    state.cab.selectedFleet = preferredCarType;
    activeRentalFilter = preferredCarType;
  } else {
    activeRentalFilter = 'all';
  }
  document.querySelectorAll('#rental-filter-pills .rental-filter-pill').forEach(p => {
    p.classList.toggle('active', (p.dataset.category || '') === activeRentalFilter);
  });

  // Update stats & render vehicles
  updateRentalRouteStats();
  renderRentalVehicles(activeRentalFilter, activeRentalSort);

  // Advance to Step 2
  goToCabStep(2);

  // Smooth scroll down to comparison grid
  const s2 = document.getElementById('cab-step-2');
  if (s2) {
    s2.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

// Render Vehicles Comparison Cards
window.renderRentalVehicles = function (filterCat = 'all', sortBy = 'recommended') {
  const grid = document.getElementById('rental-vehicles-grid');
  if (!grid) return;

  activeRentalFilter = filterCat;
  activeRentalSort = sortBy;

  let dist = state.cab.distance || 132;
  if (state.cab.tripType === 'roundtrip') dist = dist * 2;
  if (state.cab.tripType === 'local') {
    const pkg = document.getElementById('rental-local-package')?.value || '8_80';
    dist = pkg === '4_40' ? 40 : (pkg === '8_80' ? 80 : (pkg === '10_100' ? 100 : 120));
  }

  let fleets = Object.entries(FLEET_TYPES).map(([key, f]) => {
    // Prefer the server's itemised quote for this fleet; fall back to a local
    // estimate only when the quote is unavailable (offline / mid-flight).
    const quotedTotal = serverFleetTotal(key);

    let baseFare = Math.round(dist * f.ratePerKm);
    let tollFare = state.cab.includeTolls ? (state.cab.tollEstimate || 120) : 0;
    if (state.cab.tripType === 'roundtrip') tollFare = Math.round(tollFare * 1.8);
    if (state.cab.tripType === 'local') tollFare = 0;
    let totalFare = quotedTotal !== null ? quotedTotal : (baseFare + tollFare);

    return {
      key,
      ...f,
      calculatedBase: baseFare,
      calculatedToll: tollFare,
      totalFare
    };
  });

  // Filter by Category
  if (filterCat !== 'all') {
    fleets = fleets.filter(f => f.category === filterCat);
  }

  // Sort
  if (sortBy === 'price_low') {
    fleets.sort((a, b) => a.totalFare - b.totalFare);
  } else if (sortBy === 'capacity_high') {
    fleets.sort((a, b) => parseInt(b.seats) - parseInt(a.seats));
  }

  // Render Cards HTML
  grid.innerHTML = fleets.map(f => `
      <article class="rental-vehicle-card">
        <div class="rental-vehicle-media">
          <img src="${f.image}" alt="${f.name}" class="rental-vehicle-img" loading="lazy" decoding="async">
          <span class="rental-category-badge rental-category-badge--overlay">${f.badge}</span>
        </div>
        <div class="rental-vehicle-body">
          <div class="rental-vehicle-head">
            <h3 class="rental-vehicle-name">${f.name}</h3>
          </div>
          <p class="rental-vehicle-models">${f.models} or similar</p>
          <div class="rental-spec-chips">
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">group</span> ${f.seats}</span>
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">luggage</span> ${f.luggage}</span>
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">ac_unit</span> AC</span>
          </div>
          <div class="rental-policy-strip">
            <span>✓ Tolls &amp; taxes included</span>
            <span>✓ Free cancellation</span>
          </div>
        </div>
        <div class="rental-vehicle-footer">
          <div class="rental-fare-block">
            <div class="rental-fare-total">₹${f.totalFare.toLocaleString('en-IN')}</div>
            <div class="rental-fare-sub">up to ${dist} km • ₹${f.extraKm}/km after</div>
          </div>
          <button type="button" class="btn-book-vehicle" onclick="selectRentalVehicle('${f.key}')">Book now</button>
        </div>
      </article>
    `).join('');
};

window.filterRentalCategory = function (cat, btn) {
  document.querySelectorAll('.rental-filter-pill').forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const sortVal = document.getElementById('rental-sort-by')?.value || 'recommended';
  renderRentalVehicles(cat, sortVal);
};

window.sortRentalResults = function (by) {
  renderRentalVehicles(activeRentalFilter, by);
};

window.selectRentalVehicle = function (fleetKey) {
  state.cab.selectedFleet = fleetKey;
  const fData = FLEET_TYPES[fleetKey] || FLEET_TYPES.suv;

  // Update Step 3 Summary
  const imgEl = document.getElementById('summary-vehicle-img');
  if (imgEl && fData.image) imgEl.src = fData.image;

  const fleetTitle = document.getElementById('recap-fleet-title');
  if (fleetTitle) fleetTitle.textContent = `${fData.name} (${fData.models.split(',')[0]})`;

  // Sync pickup / drop into Step 3 inputs based on trip type
  let fromVal = 'Kolkata';
  let toVal = 'Mayapur, West Bengal';
  if (state.cab.tripType === 'roundtrip') {
    fromVal = document.getElementById('rental-rt-from')?.value || 'Kolkata';
    toVal = document.getElementById('rental-rt-to')?.value || 'Mayapur, West Bengal';
  } else if (state.cab.tripType === 'local') {
    fromVal = document.getElementById('rental-local-city')?.value || 'Kolkata';
    toVal = `${fromVal} Local Sightseeing`;
  } else if (state.cab.tripType === 'airport') {
    const dir = document.querySelector('input[name="airport_direction"]:checked')?.value || 'pickup';
    const apt = document.getElementById('rental-airport-name')?.value || 'Airport';
    const city = document.getElementById('rental-airport-address')?.value || 'City Area';
    fromVal = dir === 'pickup' ? apt : city;
    toVal = dir === 'pickup' ? city : apt;
  } else {
    fromVal = document.getElementById('rental-from-city')?.value || 'Kolkata';
    toVal = document.getElementById('rental-to-city')?.value || 'Mayapur, West Bengal';
  }

  const pInput = document.getElementById('cab-pickup-address');
  const dInput = document.getElementById('cab-drop-address');
  if (pInput) {
    const detected = state.cab.detectedPickup;
    if (detected && detected.cityFormatted === fromVal && detected.displayName) {
      pInput.value = detected.displayName;
    } else {
      pInput.value = fromVal.includes('Sector') || fromVal.includes('Bay') || fromVal.includes('Terminal') ? fromVal : `${fromVal} Area`;
    }
  }
  if (dInput) dInput.value = toVal.includes('Sector') || toVal.includes('Bay') || toVal.includes('Terminal') ? toVal : `${toVal} Landmark`;

  // The selected fleet is part of the quote request, so refresh it before the
  // summary is shown.
  refreshCabQuote();
  goToCabStep(3);

  const s3 = document.getElementById('cab-step-3');
  if (s3) {
    s3.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

window.updateDynamicFieldsVisibility = function () {
  const isAirport = state.cab.tripType === 'airport';
  const isPetChecked = Boolean(document.getElementById('addon-pet')?.checked);

  document.querySelectorAll('.dynamic-field-item').forEach(el => {
    const key = el.dataset.fieldKey;
    const appliesTo = el.dataset.appliesTo;
    const cond = el.dataset.conditional;

    let show = true;
    if (key === 'flight_number' || appliesTo === 'airport' || cond === 'Airport Trips Only') {
      show = isAirport;
    } else if (key === 'pet_breed' || cond === 'Pet-friendly Selected') {
      show = isPetChecked;
    }

    el.style.display = show ? 'block' : 'none';
  });

  const wrapper = document.getElementById('cab-dynamic-fields-wrapper');
  if (wrapper) {
    const items = Array.from(wrapper.querySelectorAll('.dynamic-field-item'));
    const hasVisible = items.length === 0 || items.some(i => i.style.display !== 'none');
    wrapper.style.display = hasVisible ? 'block' : 'none';
  }
};

window.applyRentalCoupon = function () {
  applyCouponCode();
};

// Coupons are validated and priced by the server. The browser used to hardcode
// FIRSTWHEELS / INTERCITY100 and invent the discount (P1-2) — a coupon added in
// the admin panel never worked, and an expired one still "applied".
async function applyCouponCode() {
  const input = document.getElementById('cab-coupon-input');
  const val = input ? input.value.trim().toUpperCase() : '';
  const msgEl = document.getElementById('coupon-msg');

  if (msgEl) msgEl.style.display = 'block';

  if (!val) {
    state.cab.appliedCoupon = null;
    if (msgEl) {
      msgEl.textContent = 'Enter a promo code to apply.';
      msgEl.style.color = '#64748b';
    }
    refreshCabQuote();
    return;
  }

  state.cab.appliedCoupon = val;
  await refreshCabQuote();

  const applied = state.cab.serverQuote && state.cab.serverQuote.quote && state.cab.serverQuote.quote.coupon;

  if (applied && applied.applied) {
    if (msgEl) {
      msgEl.textContent = `🎉 Coupon ${val} applied — ₹${Number(applied.discount).toLocaleString('en-IN')} off.`;
      msgEl.style.color = '#16a34a';
    }
    showToast(`Promo code ${val} applied successfully!`, 'success');
  } else {
    state.cab.appliedCoupon = null;
    const reason = (applied && applied.message) || state.cab.quoteError || 'Coupon could not be applied.';
    if (msgEl) {
      msgEl.textContent = '❌ ' + reason;
      msgEl.style.color = '#dc2626';
    }
    showToast(reason, 'warning');
    refreshCabQuote();
  }
}

// ==========================================
// Stepper Flow Navigation Engine
// ==========================================
window.goToCabStep = function (step) {
  // Update pills & indicators
  for (let i = 1; i <= 3; i++) {
    const pill = document.getElementById(`pill-step-${i}`);
    const num = document.getElementById(`step-num-${i}`);
    if (pill) {
      pill.classList.remove('active', 'completed');
      if (i < step) {
        pill.classList.add('completed');
        if (num) num.innerHTML = '✓';
      } else if (i === step) {
        pill.classList.add('active');
        if (num) num.textContent = String(i);
      } else {
        if (num) num.textContent = String(i);
      }
    }

    const panel = document.getElementById(`cab-step-${i}`);
    if (panel) {
      if (i === 1) {
        // Hero Booking Panel remains visible as top hero section
        panel.style.display = 'block';
      } else {
        panel.style.display = (i === step) ? 'block' : 'none';
      }
    }
  }

  state.cab.currentStep = step;
  updateRouteRecapTexts();
  updateTotalCabFare();

  if (step === 2) {
    renderRentalVehicles(activeRentalFilter, activeRentalSort);
  }

  // On Step 3: sync contact details from input & update dynamic fields
  if (step === 3) {
    if (typeof updateDynamicFieldsVisibility === 'function') updateDynamicFieldsVisibility();
    const pickupInput = document.getElementById('cab-pickup-address');
    if (pickupInput && pickupInput.value) {
      state.cab.pickupAddress = pickupInput.value;
    }
  }
};

window.setQuickCabDate = function (preset) {
  const dateInput = document.getElementById('cab-pickup-date');
  if (!dateInput) return;
  const now = new Date();
  if (preset === 'today') {
    dateInput.value = now.toISOString().split('T')[0];
  } else if (preset === 'tomorrow') {
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.value = tomorrow.toISOString().split('T')[0];
  } else if (preset === 'weekend') {
    const sat = new Date(now);
    const day = now.getDay();
    const diff = (6 - day + 7) % 7 || 7;
    sat.setDate(sat.getDate() + diff);
    dateInput.value = sat.toISOString().split('T')[0];
  }
  updateRouteRecapTexts();
};

function updateRouteRecapTexts() {
  let fromName = 'Kolkata';
  let toName = 'Mayapur, West Bengal';

  if (state.cab.tripType === 'oneway') {
    fromName = document.getElementById('rental-from-city')?.value || 'Kolkata';
    toName = document.getElementById('rental-to-city')?.value || 'Mayapur, West Bengal';
  } else if (state.cab.tripType === 'roundtrip') {
    fromName = document.getElementById('rental-rt-from')?.value || 'Kolkata';
    toName = document.getElementById('rental-rt-to')?.value || 'Mayapur, West Bengal';
  } else if (state.cab.tripType === 'local') {
    fromName = document.getElementById('rental-local-city')?.value || 'Kolkata';
    toName = 'Local Package';
  } else if (state.cab.tripType === 'airport') {
    fromName = document.getElementById('rental-airport-name')?.value || 'Airport';
    toName = document.getElementById('rental-airport-address')?.value || 'City Address';
  }

  const dist = state.cab.distance || 132;
  const time = state.cab.travelTime || '3h 30m';
  const toll = state.cab.tollEstimate || 120;

  const dateVal = document.getElementById('rental-pickup-date')?.value || document.getElementById('cab-pickup-date')?.value;
  const timeVal = document.getElementById('rental-pickup-time')?.value || document.getElementById('cab-pickup-time')?.value || '12:15 AM';

  let dateDisplay = '11 Nov 2026, 12:15 AM';
  if (dateVal) {
    const parts = dateVal.split('-');
    if (parts.length === 3) {
      const d = new Date(parts[0], parts[1] - 1, parts[2]);
      dateDisplay = `${d.toLocaleDateString('en-IN', { month: 'short', day: 'numeric', year: 'numeric' })}, ${timeVal}`;
    }
  }

  // Step 1 strip
  const s1Route = document.getElementById('step1-route-name');
  if (s1Route) s1Route.textContent = `${fromName} → ${toName}`;
  const s1Dist = document.getElementById('step1-dist');
  if (s1Dist) s1Dist.textContent = `${dist} km`;
  const s1Time = document.getElementById('step1-time');
  if (s1Time) s1Time.textContent = time;
  const s1Toll = document.getElementById('step1-toll');
  if (s1Toll) s1Toll.textContent = `₹${toll} Included`;

  // Step 2 banner
  const recapRoute = document.getElementById('recap-route');
  if (recapRoute) recapRoute.textContent = `${fromName} → ${toName}`;
  const recapDatetime = document.getElementById('recap-datetime');
  if (recapDatetime) recapDatetime.textContent = dateDisplay;
  const recapTripType = document.getElementById('recap-trip-type');
  if (recapTripType) recapTripType.textContent = state.cab.tripType || 'One Way';
  const recapDistance = document.getElementById('recap-distance');
  if (recapDistance) recapDistance.textContent = `${dist} km (~${time})`;

  // Step 3 banner
  const fleetData = FLEET_TYPES[state.cab.selectedFleet] || FLEET_TYPES.suv;
  const recapFleet = document.getElementById('recap-fleet-title');
  if (recapFleet) recapFleet.textContent = `${fleetData.name} (${fleetData.models.split(',')[0]})`;
  const recapRouteMini = document.getElementById('recap-route-mini');
  if (recapRouteMini) recapRouteMini.textContent = `${fromName} → ${toName}`;
  if (typeof syncBeyondRegionNoticeUi === 'function') syncBeyondRegionNoticeUi();
}

function syncBeyondRegionNoticeUi() {
  const route = state.cab.serverQuote && state.cab.serverQuote.route;
  const isBeyond = Boolean(route && route.beyond_region);
  const note = (route && route.region_note)
    || 'This route is outside our core regional service area. Distance and estimated fare were calculated via API fallback; ride confirmation and vehicle availability are not guaranteed.';

  const b2 = document.getElementById('step2-beyond-region-banner');
  const b2Text = document.getElementById('step2-beyond-region-text');
  if (b2) b2.style.display = isBeyond ? 'block' : 'none';
  if (b2Text && isBeyond) b2Text.textContent = note;

  const b3 = document.getElementById('step3-beyond-region-banner');
  const b3Text = document.getElementById('step3-beyond-region-text');
  if (b3) b3.style.display = isBeyond ? 'block' : 'none';
  if (b3Text && isBeyond) b3Text.textContent = note;

  const btnLabel = document.getElementById('book-cab-confirm-btn-label');
  if (btnLabel) {
    btnLabel.textContent = isBeyond ? 'SUBMIT UNCONFIRMED REQUEST (OUT OF REGION)' : 'CONFIRM & BOOK CAB';
  }
}

// ==========================================
// Real Backend API Cab Booking Submission
// ==========================================
async function handleConfirmCabBooking() {
  const btn = document.getElementById('book-cab-confirm-btn');
  const nameInput = document.getElementById('cab-rider-name');
  const phoneInput = document.getElementById('cab-rider-phone');
  const emailInput = document.getElementById('cab-rider-email');
  const pickupInput = document.getElementById('cab-pickup-address');
  const dropInput = document.getElementById('cab-drop-address');
  const termsCheck = document.getElementById('rental-terms-agree');

  if (termsCheck && !termsCheck.checked) {
    showToast('Please agree to the Privacy Policy and T&Cs to continue.', 'warning');
    termsCheck.focus();
    return;
  }

  const customerName = nameInput ? nameInput.value.trim() : (window.CURRENT_USER?.name || 'Aditya Sharma');
  const customerPhone = phoneInput ? phoneInput.value.trim() : '+91 98220 11920';
  const customerEmail = emailInput ? emailInput.value.trim() : 'traveller@intercitywheels.com';

  if (!customerName) {
    showToast('Please enter passenger full name.', 'warning');
    if (nameInput) nameInput.focus();
    return;
  }
  if (!customerPhone) {
    showToast('Please enter passenger mobile number.', 'warning');
    if (phoneInput) phoneInput.focus();
    return;
  }

  let fromCityName = 'Kolkata';
  let toCityName = 'Mayapur';
  let pickupLoc = '';
  let dropLoc = '';

  if (state.cab.tripType === 'oneway') {
    fromCityName = document.getElementById('rental-from-city')?.value.split(',')[0].trim() || 'Kolkata';
    toCityName = document.getElementById('rental-to-city')?.value.split(',')[0].trim() || 'Mayapur';
    pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : `${fromCityName} Central Area`;
    dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : `${toCityName} Center`;
  } else if (state.cab.tripType === 'roundtrip') {
    fromCityName = document.getElementById('rental-rt-from')?.value.split(',')[0].trim() || 'Kolkata';
    toCityName = document.getElementById('rental-rt-to')?.value.split(',')[0].trim() || 'Mayapur';
    pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : `${fromCityName} Central Area`;
    dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : `${toCityName} Center`;
  } else if (state.cab.tripType === 'local') {
    fromCityName = document.getElementById('rental-local-city')?.value.split(',')[0].trim() || 'Kolkata';
    toCityName = `${fromCityName} Local`;
    pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : `${fromCityName} Central`;
    dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : `${fromCityName} Sightseeing`;
  } else if (state.cab.tripType === 'airport') {
    const airportDirection = document.querySelector('input[name="airport_direction"]:checked')?.value || 'pickup';
    const airportName = document.getElementById('rental-airport-name')?.value.trim() || 'Netaji Subhash Chandra Bose Intl (CCU), Kolkata';
    const cityAddress = document.getElementById('rental-airport-address')?.value.trim() || 'Salt Lake Sector V, Kolkata';
    const cityExtracted = airportName.split(',').pop()?.trim() || 'Kolkata';
    fromCityName = cityExtracted;
    toCityName = airportName.includes('(') ? airportName.split('(')[0].trim() : 'Airport Hub';
    if (airportDirection === 'pickup') {
      pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : airportName;
      dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : cityAddress;
    } else {
      pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : cityAddress;
      dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : airportName;
    }
  }

  if (!pickupLoc) pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : `${fromCityName} Central Area`;
  if (!dropLoc) dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : `${toCityName} Center`;

  // Extract departure and return times
  let departureTimeStr = '';
  let returnTimeStr = '';
  if (state.cab.tripType === 'oneway') {
    const d = document.getElementById('rental-pickup-date')?.value || document.getElementById('cab-pickup-date')?.value;
    const t = document.getElementById('rental-pickup-time')?.value || document.getElementById('cab-pickup-time')?.value || '12:15';
    if (d) departureTimeStr = `${d} ${t}`;
  } else if (state.cab.tripType === 'roundtrip') {
    const d = document.getElementById('rental-rt-pickup-date')?.value || document.getElementById('rental-rt-start-date')?.value || document.getElementById('cab-pickup-date')?.value;
    const t = document.getElementById('rental-rt-pickup-time')?.value || document.getElementById('rental-rt-start-time')?.value || '06:00 AM';
    if (d) departureTimeStr = `${d} ${t}`;

    const rd = document.getElementById('rental-rt-return-date')?.value || document.getElementById('cab-return-date')?.value;
    const rt = document.getElementById('rental-rt-return-time')?.value || '08:00 PM';
    if (rd) returnTimeStr = `${rd} ${rt}`;
  } else if (state.cab.tripType === 'local') {
    const d = document.getElementById('rental-local-date')?.value;
    const t = document.getElementById('rental-local-time')?.value || '10:00';
    if (d) departureTimeStr = `${d} ${t}`;
  } else if (state.cab.tripType === 'airport') {
    const d = document.getElementById('rental-airport-date')?.value;
    const t = document.getElementById('rental-airport-time')?.value || '11:30';
    if (d) departureTimeStr = `${d} ${t}`;
  }

  // Dynamic fields extraction
  const dynamicFieldsData = {};
  document.querySelectorAll('.cab-dynamic-field').forEach(input => {
    const key = input.dataset.key;
    if (key && input.value.trim()) {
      dynamicFieldsData[key] = input.value.trim();
    }
  });

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

  const fromEl = state.cab.tripType === 'roundtrip'
    ? document.getElementById('rental-rt-from')
    : (state.cab.tripType === 'local' ? document.getElementById('rental-local-city') : document.getElementById('rental-from-city'));
  const toEl = state.cab.tripType === 'roundtrip'
    ? document.getElementById('rental-rt-to')
    : document.getElementById('rental-to-city');
  const fromCoords = readInputCoords(fromEl);
  const toCoords = (state.cab.tripType === 'oneway' || state.cab.tripType === 'roundtrip') ? readInputCoords(toEl) : null;

  const payload = {
    service_type: 'cab',
    trip_type: state.cab.tripType || 'oneway',
    from_city: fromCityName,
    to_city: toCityName,
    pickup_location: pickupLoc,
    drop_location: dropLoc,
    from_lat: fromCoords ? fromCoords.lat : undefined,
    from_lng: fromCoords ? fromCoords.lng : undefined,
    to_lat: toCoords ? toCoords.lat : undefined,
    to_lng: toCoords ? toCoords.lng : undefined,
    departure_time: departureTimeStr || undefined,
    return_time: returnTimeStr || undefined,
    customer_name: customerName,
    customer_phone: customerPhone,
    customer_email: customerEmail,
    fleet_category: state.cab.selectedFleet || 'suv',
    local_package: document.getElementById('rental-local-package')?.value || '8_80',
    airport_type: document.querySelector('input[name="airport_direction"]:checked')?.value || 'pickup',
    flight_number: document.getElementById('rental-airport-flight')?.value || '',
    include_tolls: state.cab.includeTolls !== false,
    pet_friendly: Boolean(state.cab.petFriendly),
    night_allowance: Boolean(state.cab.nightAllowance),
    luggage_carrier: Boolean(state.cab.luggageCarrier),
    coupon_code: state.cab.appliedCoupon || '',
    payment_mode: document.querySelector('input[name="rental_pay_mode"]:checked')?.value || (window.PLATFORM_SETTINGS?.payment_default_option || 'full_online'),
    dynamic_fields: dynamicFieldsData
  };

  try {
    const res = await fetch('/api/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    // Confirming a booking requires a signed-in account. Guests are handed to
    // the one-tap login gate, and the payload is restored after sign-in.
    if (res.status === 401) {
      beginBookingLoginGate(payload);
      return;
    }

    if (data.success && data.booking) {
      const b = data.booking;

      if (data.requires_razorpay && data.razorpay) {
        try {
          const verifiedData = await launchRazorpayCheckout(b.booking_reference, data.razorpay);
          if (verifiedData && verifiedData.booking) {
            finalizeCabBookingUi(verifiedData.booking, fromCityName, toCityName, customerName);
            return;
          }
        } catch (rzpErr) {
          showToast(rzpErr.message || 'Razorpay payment was not completed. You can retry from My Trips.', 'warning');
          goToCabStep(1);
          switchTab('my-bookings');
          window.showBookingInHistory(b);
          return;
        }
      }

      finalizeCabBookingUi(b, fromCityName, toCityName, customerName);
    } else {
      showToast(data.message || 'Booking validation error. Please verify input.', 'danger');
    }
  } catch (err) {
    // Previously this opened the payment sheet and then minted a ticket with a
    // random reference, so a failed request still looked like a successful
    // booking. A booking only exists once the server says so.
    console.warn('Booking submission error:', err);
    showToast('We could not reach the booking service. Please try again — no booking has been created.', 'danger');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origBtnContent;
    }
  }
}

function handleCityChange() {
  const fromSelect = document.getElementById('cab-from-city');
  const toSelect = document.getElementById('cab-to-city');
  if (!fromSelect || !toSelect) return;

  const from = fromSelect.value;
  const to = toSelect.value;

  state.cab.fromCity = from;
  state.cab.toCity = to;

  const routeKey = `${from}-${to}`;
  const route = ROUTE_MATRIX[routeKey] || { dist: 380, time: "7h 00m", toll: 400 };

  state.cab.distance = route.dist;
  state.cab.travelTime = route.time;
  state.cab.tollEstimate = route.toll;

  // Update DOM stats (safe — element may not exist on all tabs)
  const distEl = document.getElementById('cab-stat-distance');
  if (distEl) distEl.textContent = `${route.dist} km`;
  const timeEl = document.getElementById('cab-stat-time');
  if (timeEl) timeEl.textContent = route.time;
  const tollEl = document.getElementById('cab-stat-toll');
  if (tollEl) tollEl.textContent = `₹${route.toll}`;

  updateMapRoute(from, to);
  updateRouteRecapTexts();
  updateTotalCabFare();
}

function finalizeCabBookingUi(b, fromCityName, toCityName, customerName) {
  const localBooking = {
    id: b.booking_reference,
    type: 'Outstation Cab',
    route: `${fromCityName} → ${toCityName}`,
    date: 'Scheduled',
    operator: `${FLEET_TYPES[state.cab.selectedFleet]?.name || 'Executive SUV'} (Intercity Wheels)`,
    berths: ['Private Car Rental'],
    passengers: [customerName],
    amount: Number(b.total_amount),
    status: b.status || 'Requested',
    otp: String(b.start_otp)
  };

  state.bookings.unshift(localBooking);
  if (b.is_beyond_region) {
    showToast(`⚠️ Booking request saved (Beyond Service Region — NOT Confirmed / Availability Not Guaranteed). Ref: ${b.booking_reference}`, 'warning');
  } else {
    showToast(`🎉 Booking confirmed! Reference: ${b.booking_reference}`, 'success');
  }

  const badge = document.querySelector('.nav-tab-badge.badge-primary');
  if (badge) {
    badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1);
  }

  goToCabStep(1);
  switchTab('my-bookings');
  window.showBookingInHistory(b);
  if (typeof window.refreshAppNotifications === 'function') window.refreshAppNotifications();
}

function loadRazorpaySdk() {
  if (typeof window.Razorpay !== 'undefined') return Promise.resolve();
  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[src*="checkout.razorpay.com/v1/checkout.js"]');
    if (existing) {
      existing.addEventListener('load', resolve);
      existing.addEventListener('error', () => reject(new Error('Failed to load Razorpay Checkout SDK.')));
      return;
    }
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.onload = resolve;
    script.onerror = () => reject(new Error('Failed to load Razorpay Checkout SDK.'));
    document.head.appendChild(script);
  });
}

async function launchRazorpayCheckout(bookingReference, rzpConfig) {
  await loadRazorpaySdk();
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  return new Promise((resolve, reject) => {
    const options = {
      key: rzpConfig.key,
      amount: rzpConfig.amount,
      currency: rzpConfig.currency || 'INR',
      name: rzpConfig.name || 'Intercity Wheels',
      description: rzpConfig.description || `Booking ${bookingReference}`,
      order_id: rzpConfig.order_id,
      prefill: rzpConfig.prefill || {},
      theme: { color: '#d49b4c' },
      modal: {
        ondismiss: () => reject(new Error('Payment popup closed before completion.')),
      },
      handler: async function (response) {
        try {
          const verifyRes = await fetch(`/api/bookings/${encodeURIComponent(bookingReference)}/razorpay/verify`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
              razorpay_order_id: response.razorpay_order_id,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature,
            }),
          });
          const verifyData = await verifyRes.json();
          if (verifyRes.ok && verifyData.success) {
            resolve(verifyData);
          } else {
            reject(new Error(verifyData.message || 'Razorpay signature verification failed.'));
          }
        } catch (err) {
          reject(new Error('Network error while verifying Razorpay payment.'));
        }
      },
    };

    const rzp = new window.Razorpay(options);
    rzp.on('payment.failed', (resp) => {
      reject(new Error(resp?.error?.description || 'Razorpay payment failed.'));
    });
    rzp.open();
  });
}

window.retryRazorpayForBooking = async function (reference) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try {
    const res = await fetch(`/api/bookings/${encodeURIComponent(reference)}/razorpay/order`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
    });
    const data = await res.json();
    if (!res.ok || !data.success || !data.razorpay) {
      showToast(data.message || 'Could not start Razorpay checkout.', 'danger');
      return;
    }
    const verified = await launchRazorpayCheckout(reference, data.razorpay);
    if (verified && verified.booking) {
      showToast(verified.message || 'Payment verified! Booking confirmed.', 'success');
      window.showBookingInHistory(verified.booking);
      if (typeof window.refreshAppNotifications === 'function') window.refreshAppNotifications();
    }
  } catch (err) {
    showToast(err.message || 'Payment not completed.', 'warning');
  }
};

function updateTotalCabFare() {
  const fleet = FLEET_TYPES[state.cab.selectedFleet] || FLEET_TYPES.suv;
  const serverQuote = state.cab.serverQuote && state.cab.serverQuote.quote;

  let dist = state.cab.distance;
  if (state.cab.tripType === 'roundtrip') dist = dist * 2;

  let baseFare;
  let tolls;
  let addons;
  let discount;
  let total;

  if (serverQuote) {
    // Authoritative numbers straight from FareCalculatorService.
    baseFare = Number(serverQuote.base_fare) || 0;
    tolls = Number(serverQuote.toll_fare) || 0;
    addons = Number(serverQuote.addon_fare) || 0;
    discount = Number(serverQuote.discount_fare) || 0;
    total = Number(serverQuote.total_amount) || 0;
  } else {
    baseFare = dist * fleet.ratePerKm;
    tolls = state.cab.includeTolls ? (ROUTE_MATRIX[`${state.cab.fromCity}-${state.cab.toCity}`]?.toll || 400) : 0;
    if (state.cab.tripType === 'roundtrip') tolls = tolls * 1.8;

    addons = 0;
    if (state.cab.petFriendly) addons += state.admin.pricingRules.petCharge;
    if (state.cab.nightAllowance) addons += state.admin.pricingRules.nightCharge;
    if (state.cab.luggageCarrier) addons += state.admin.pricingRules.luggageCharge;

    const subtotal = Math.round(baseFare + tolls + addons);
    discount = 0;
    total = Math.max(subtotal - discount, 0);
  }

  state.cab.discountAmount = discount;

  // Update UI breakdown
  const elBase = document.getElementById('fare-base');
  const elTolls = document.getElementById('fare-tolls');
  const elAddons = document.getElementById('fare-addons');
  const elDiscount = document.getElementById('fare-discount');
  const elTotal = document.getElementById('fare-grand-total');

  if (elBase) elBase.textContent = `₹${Math.round(baseFare).toLocaleString('en-IN')}`;
  if (elTolls) elTolls.textContent = `₹${Math.round(tolls).toLocaleString('en-IN')}`;
  if (elAddons) elAddons.textContent = `₹${Math.round(addons).toLocaleString('en-IN')}`;
  if (elDiscount) elDiscount.textContent = `-₹${Math.round(discount).toLocaleString('en-IN')}`;
  if (elTotal) elTotal.textContent = `₹${Math.round(total).toLocaleString('en-IN')}`;

  if (typeof updateCheckoutPaymentDisplay === 'function') {
    updateCheckoutPaymentDisplay(total);
  }

  // Update fleet prices on the comparison cards from the same source.
  for (const key of Object.keys(FLEET_TYPES)) {
    const fPriceEl = document.getElementById(`fleet-price-${key}`);
    if (fPriceEl) {
      const quoted = serverFleetTotal(key);
      const fTotal = quoted !== null ? quoted : Math.round(dist * FLEET_TYPES[key].ratePerKm + (state.cab.includeTolls ? 400 : 0));
      fPriceEl.textContent = `₹${fTotal.toLocaleString('en-IN')}`;
    }
  }
}

// Legacy alias kept because older markup binds applyCouponCode directly.
// The implementation above validates the code server-side.

// Leaflet OpenStreetMap Route Display
function initMap() {
  const mapEl = document.getElementById('live-leaflet-map');
  if (!mapEl || typeof L === 'undefined') return;

  const pune = CITY_DATABASE.pune;
  mapInstance = L.map('live-leaflet-map', {
    zoomControl: true,
    attributionControl: false
  }).setView([pune.lat, pune.lng], 7);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
  }).addTo(mapInstance);

  updateMapRoute(state.cab.fromCity, state.cab.toCity);
}

function updateMapRoute(fromKey, toKey) {
  if (!mapInstance || typeof L === 'undefined') return;

  const fromCity = CITY_DATABASE[fromKey] || CITY_DATABASE.pune;
  const toCity = CITY_DATABASE[toKey] || CITY_DATABASE.goa;

  // Clear existing markers & route
  mapMarkers.forEach(m => mapInstance.removeLayer(m));
  mapMarkers = [];
  if (mapRouteLine) mapInstance.removeLayer(mapRouteLine);

  // Custom marker icons
  const originIcon = L.divIcon({
    className: 'custom-map-pin origin-pin',
    html: `<div style="background:#0b0f19;color:#fdfaf5;font-weight:700;font-size:11px;padding:4px 8px;border-radius:20px;border:2px solid #d49b4c;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:nowrap;">📍 ${fromCity.name}</div>`,
    iconSize: [80, 30]
  });

  const destIcon = L.divIcon({
    className: 'custom-map-pin dest-pin',
    html: `<div style="background:linear-gradient(135deg, #ebcaa5 0%, #d49b4c 100%);color:#0b0f19;font-weight:800;font-size:11px;padding:4px 8px;border-radius:20px;border:2px solid #0b0f19;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:nowrap;">🏁 ${toCity.name}</div>`,
    iconSize: [80, 30]
  });

  const marker1 = L.marker([fromCity.lat, fromCity.lng], { icon: originIcon }).addTo(mapInstance);
  const marker2 = L.marker([toCity.lat, toCity.lng], { icon: destIcon }).addTo(mapInstance);
  mapMarkers.push(marker1, marker2);

  // Curved intermediate route point for realistic highway path
  const midLat = (fromCity.lat + toCity.lat) / 2 + 0.08;
  const midLng = (fromCity.lng + toCity.lng) / 2 - 0.05;

  const latlngs = [
    [fromCity.lat, fromCity.lng],
    [midLat, midLng],
    [toCity.lat, toCity.lng]
  ];

  mapRouteLine = L.polyline(latlngs, {
    color: '#d49b4c',
    weight: 4,
    opacity: 0.9,
    dashArray: '8, 8',
    lineJoin: 'round'
  }).addTo(mapInstance);

  const bounds = L.latLngBounds([
    [fromCity.lat, fromCity.lng],
    [toCity.lat, toCity.lng]
  ]);
  mapInstance.fitBounds(bounds, { padding: [40, 40] });
}

// ==========================================
// 2. Schedules & Berth Reservation (From Prototype)
// ==========================================
function initSchedulesModule() {
  // Deck Switching
  const btnLower = document.getElementById('deck-switch-lower');
  const btnUpper = document.getElementById('deck-switch-upper');
  const lowerGrid = document.getElementById('deck-lower-grid');
  const upperGrid = document.getElementById('deck-upper-grid');

  if (btnLower && btnUpper) {
    btnLower.addEventListener('click', () => {
      btnLower.classList.add('active');
      btnUpper.classList.remove('active');
      lowerGrid.style.display = 'flex';
      upperGrid.style.display = 'none';
      state.schedules.activeDeck = 'lower';
    });
    btnUpper.addEventListener('click', () => {
      btnUpper.classList.add('active');
      btnLower.classList.remove('active');
      upperGrid.style.display = 'flex';
      lowerGrid.style.display = 'none';
      state.schedules.activeDeck = 'upper';
    });
  }

  // Setup Berth Clicks
  document.querySelectorAll('.berth-cell').forEach(cell => {
    cell.addEventListener('click', () => {
      if (cell.classList.contains('booked')) {
        showToast('This berth is already reserved.', 'warning');
        return;
      }
      if (cell.classList.contains('ladies-only')) {
        showToast('Berth reserved under Ladies Quota.', 'warning');
        // allow selection if passenger is female
      }

      const seatCode = cell.dataset.seat;
      const price = parseInt(cell.dataset.price, 10);
      toggleBerthSelection(cell, seatCode, price);
    });
  });

  // Proceed to Pay
  const proceedPayBtn = document.getElementById('schedules-pay-btn');
  if (proceedPayBtn) {
    proceedPayBtn.addEventListener('click', () => {
      if (state.schedules.selectedSeats.length === 0) {
        showToast('Please select at least 1 berth to proceed.', 'warning');
        return;
      }
      openPaymentModal('bus');
    });
  }

  renderPassengerForms();
  updateSchedulesTotal();
}

function toggleBerthSelection(cellElement, seatCode, price) {
  const index = state.schedules.selectedSeats.findIndex(s => s.id === seatCode);
  const icon = cellElement.querySelector('.material-symbols-outlined');

  if (index >= 0) {
    // Deselect
    state.schedules.selectedSeats.splice(index, 1);
    cellElement.classList.remove('selected');
    if (icon) {
      icon.textContent = 'bed';
      icon.style.fontVariationSettings = "'FILL' 0";
    }
    showToast(`Berth ${seatCode} removed.`, 'info');
  } else {
    // Select
    if (state.schedules.selectedSeats.length >= 6) {
      showToast('Maximum 6 berths can be selected per booking.', 'warning');
      return;
    }
    state.schedules.selectedSeats.push({
      id: seatCode,
      coach: cellElement.dataset.coach || null,
      price: price,
      type: state.schedules.activeDeck,
      window: ['L1', 'L4', 'L7', 'U1', 'U4'].includes(seatCode)
    });
    cellElement.classList.add('selected');
    if (icon) {
      icon.textContent = 'check_circle';
      icon.style.fontVariationSettings = "'FILL' 1";
    }
    showToast(`Berth ${seatCode} selected (₹${price})`, 'success');
  }

  renderPassengerForms();
  updateSchedulesTotal();
}

function updateSchedulesTotal() {
  const count = state.schedules.selectedSeats.length;
  const total = state.schedules.selectedSeats.reduce((sum, s) => sum + s.price, 0);

  const seatCodes = state.schedules.selectedSeats.map(s => s.id).join(', ');

  const dockLabel = document.getElementById('dock-seat-count-label');
  const dockTotal = document.getElementById('dock-total-price');
  const passengerBadge = document.getElementById('passengers-count-badge');

  if (dockLabel) {
    dockLabel.textContent = count > 0 ? `${count} Berth${count > 1 ? 's' : ''}: ${seatCodes}` : 'No berths selected';
  }
  if (dockTotal) {
    dockTotal.innerHTML = `₹${total.toLocaleString('en-IN')} <small>all incl.</small>`;
  }
  if (passengerBadge) {
    passengerBadge.textContent = `${count} Traveller${count > 1 ? 's' : ''}`;
  }
}

function renderPassengerForms() {
  const container = document.getElementById('passenger-cards-container');
  if (!container) return;

  const seats = state.schedules.selectedSeats;
  if (seats.length === 0) {
    container.innerHTML = `
      <div style="padding: 20px; text-align: center; color: var(--text-muted); border: 1.5px dashed var(--outline); border-radius: var(--radius-md);">
        <span class="material-symbols-outlined" style="font-size: 28px; color: var(--text-light); margin-bottom: 6px;">airline_seat_recline_extra</span>
        <p style="font-size: 13px; font-weight: 700; color: var(--text-main);">No Berths Selected</p>
        <p style="font-size: 11.5px;">Choose seats from the coach layout above.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = seats.map((seat, i) => {
    const existing = state.schedules.passengers[i] || {
      name: i === 0 ? 'Rahul Sharma' : (i === 1 ? 'Sneha Verma' : ''),
      age: i === 0 ? 29 : (i === 1 ? 27 : 25),
      gender: i === 1 ? 'female' : 'male'
    };

    return `
      <div class="card" style="padding: 14px; margin-bottom: 10px; border-radius: var(--radius-md);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <span style="width: 20px; height: 20px; border-radius: 50%; background: #0f172a; color: #ffffff; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">${i + 1}</span>
            <span style="font-weight: 700; font-size: 13.5px;">Passenger ${i + 1}</span>
          </div>
          <span style="font-size: 11px; font-weight: 700; background: var(--primary-light); color: var(--primary); padding: 2px 7px; border-radius: 4px;">
            ${seat.id} • ${seat.window ? 'Window' : 'Aisle'}
          </span>
        </div>
        <input class="form-control" type="text" placeholder="Full Name" value="${existing.name}" style="margin-bottom: 8px; height: 40px;" onchange="updatePassengerData(${i}, 'name', this.value)">
        <div style="display: flex; gap: 8px; align-items: center;">
          <div style="width: 80px;">
            <input class="form-control" type="number" placeholder="Age" value="${existing.age}" style="height: 40px; text-align: center;" onchange="updatePassengerData(${i}, 'age', this.value)">
          </div>
          <div style="flex: 1; display: flex; gap: 6px;">
            <button type="button" class="btn btn-sm ${existing.gender === 'male' ? 'btn-primary' : 'btn-secondary'}" style="flex: 1; height: 40px; font-size: 12px;" onclick="setPassengerGender(${i}, 'male')">Male</button>
            <button type="button" class="btn btn-sm ${existing.gender === 'female' ? 'btn-primary' : 'btn-secondary'}" style="flex: 1; height: 40px; font-size: 12px;" onclick="setPassengerGender(${i}, 'female')">Female</button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

window.updatePassengerData = function (idx, field, value) {
  if (!state.schedules.passengers[idx]) {
    state.schedules.passengers[idx] = {};
  }
  state.schedules.passengers[idx][field] = value;
};

window.setPassengerGender = function (idx, gender) {
  if (!state.schedules.passengers[idx]) {
    state.schedules.passengers[idx] = {};
  }
  state.schedules.passengers[idx].gender = gender;
  renderPassengerForms();
};

// ==========================================
// 3. Driver Bidding Marketplace
// ==========================================
// ==========================================
// 3. Driver Bidding Marketplace (live auctions)
// ==========================================
// The portal renders the auctions the operations team has actually published
// (`GET /api/driver/auctions`, masked payload). Bidding itself happens on the
// auction's own page, so there is exactly one bid flow and one reveal rule.
function initBiddingModule() {
  renderBiddingList();
}

// Legacy call site in the app bootstrap. Each auction card shows its own
// deadline, so there is no page-level countdown to run any more.
function startBiddingCountdown() {
  /* no-op */
}

window.refreshDriverAuctions = function () {
  return renderBiddingList();
};

window.setDriverAuctionFilter = function (filterKey, btn) {
  state.bidding.filter = filterKey || 'all';
  document.querySelectorAll('#driver-bidding-status-filters [data-bid-filter]').forEach(function (el) {
    el.classList.toggle('active', el.dataset.bidFilter === state.bidding.filter);
  });
  renderFilteredBiddingCards();
};

window.setDriverAuctionView = function (mode) {
  state.bidding.viewMode = mode === 'cards' ? 'cards' : 'table';
  document.querySelectorAll('#driver-bidding-status-filters [data-bid-view]').forEach(function (el) {
    el.classList.toggle('active', el.dataset.bidView === state.bidding.viewMode);
  });
  renderFilteredBiddingCards();
};

window.toggleAuctionBidDrawer = function (auctionId) {
  const id = Number(auctionId || 0);
  state.bidding.expandedId = (state.bidding.expandedId === id) ? null : id;
  renderFilteredBiddingCards();
  if (state.bidding.expandedId === id) {
    const input = document.getElementById('console-bid-input-' + id);
    if (input) input.focus();
  }
};

function auctionEscape(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function isAvatarUrl(value) {
  if (typeof value !== 'string') return false;
  const trimmed = value.trim();
  if (!trimmed.startsWith('https://') && !trimmed.startsWith('http://')) return false;
  try {
    const parsed = new URL(trimmed);
    return parsed.protocol === 'https:' || parsed.protocol === 'http:';
  } catch (_) {
    return false;
  }
}

function renderAvatarHtml(avatar, fallback = '👤', alt = 'Profile avatar') {
  const val = typeof avatar === 'string' ? avatar.trim() : '';
  const fb = fallback || '👤';
  if (isAvatarUrl(val)) {
    return `<img src="${auctionEscape(val)}" alt="${auctionEscape(alt)}" class="avatar-img" referrerpolicy="no-referrer" data-fallback="${auctionEscape(fb)}" onerror="this.replaceWith(document.createTextNode(this.dataset.fallback || '👤'))">`;
  }
  return auctionEscape(val || fb);
}

function setAvatarContent(el, avatar, fallback = '👤', alt = 'Profile avatar') {
  if (!el) return;
  const val = typeof avatar === 'string' ? avatar.trim() : '';
  const fb = fallback || '👤';
  if (isAvatarUrl(val)) {
    const img = document.createElement('img');
    img.src = val;
    img.alt = alt;
    img.className = 'avatar-img';
    img.referrerPolicy = 'no-referrer';
    img.addEventListener('error', () => {
      img.replaceWith(document.createTextNode(fb));
    }, { once: true });
    el.replaceChildren(img);
  } else {
    el.textContent = val || fb;
  }
}

function auctionMoney(value) {
  const amount = Number(value || 0);
  return '₹' + amount.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function auctionDateTime(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (isNaN(date.getTime())) return '';
  return date.toLocaleString('en-IN', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
}

function auctionTimeLeft(iso) {
  if (!iso) return 'open until driver allotted';
  const ms = new Date(iso).getTime() - Date.now();
  if (isNaN(ms)) return 'open until driver allotted';
  if (ms <= 0) return 'closed';
  const mins = Math.floor(ms / 60000);
  const hours = Math.floor(mins / 60);
  const days = Math.floor(hours / 24);
  if (days > 0) return 'closes in ' + days + 'd ' + (hours % 24) + 'h';
  if (hours > 0) return 'closes in ' + hours + 'h ' + (mins % 60) + 'm';
  return 'closes in ' + Math.max(1, mins) + 'm';
}

async function renderBiddingList() {
  const container = document.getElementById('bidding-cards-stream');
  if (!container) return;

  container.innerHTML = '<div style="text-align:center; padding:26px; color:#94a3b8; font-size:12.5px;">Loading live auctions…</div>';

  try {
    const res = await fetch('/api/driver/auctions', { headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      container.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;">Live auctions are available to driver partners only.</div>';
      return;
    }

    const auctions = Array.isArray(data.auctions) ? data.auctions : [];
    const recentAllotted = Array.isArray(data.recent_allotted) ? data.recent_allotted : [];
    const viewerRole = window.CURRENT_USER ? window.CURRENT_USER.role : null;
    const isAdminViewer = viewerRole === 'admin' || data.is_admin === true;

    state.bidding.availableTrips = auctions;
    state.bidding.recentAllotted = recentAllotted;
    state.bidding.canBid = data.can_bid !== false;
    state.bidding.isAdminViewer = isAdminViewer;

    const freshCount = auctions.filter(a => a.current_lowest === null || a.current_lowest === undefined).length;
    const activeBidsCount = auctions.filter(a => a.current_lowest !== null && a.current_lowest !== undefined).length;
    const myBidsCount = auctions.filter(a => Number(a.my_bids || 0) > 0).length
      + recentAllotted.filter(item => item.allotted_to_you).length;
    const allottedCount = recentAllotted.length;
    const allCount = auctions.length + allottedCount;

    const setCount = (id, count) => {
      const el = document.getElementById(id);
      if (el) el.textContent = '(' + count + ')';
    };
    setCount('bidding-count-all', allCount);
    setCount('bidding-count-fresh', freshCount);
    setCount('bidding-count-active', activeBidsCount);
    setCount('bidding-count-mine', myBidsCount);
    setCount('bidding-count-allotted', allottedCount);

    renderFilteredBiddingCards();
  } catch (err) {
    console.error('renderBiddingList', err);
    container.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;">Could not load live auctions. Check your connection, then press Refresh.</div>';
  }
}

function getAuctionPricingBounds(auction) {
  const hasLowest = auction.current_lowest !== null && auction.current_lowest !== undefined;
  const ceiling = hasLowest ? Number(auction.current_lowest) : Number(auction.start_amount || 0);
  const maxAllowed = hasLowest ? Math.max(1, Math.floor(ceiling - 1)) : Math.max(1, Math.floor(ceiling));
  const minFloor = Math.max(1, Math.floor(Number(auction.min_driver_price || 1)));
  const effectiveMax = Math.max(minFloor, maxAllowed);
  const suggestedBid = effectiveMax > minFloor + 100 ? Math.floor(effectiveMax - 100) : minFloor;
  return { hasLowest, ceiling, minFloor, effectiveMax, suggestedBid };
}

function getAuctionStatusBadgeHtml(auction) {
  const myBids = Number(auction.my_bids || 0);
  const bidCount = Number(auction.bid_count || 0);
  const hasLowest = auction.current_lowest !== null && auction.current_lowest !== undefined;
  if (auction.you_are_lowest) {
    return '<span class="admin-badge badge-success" style="font-size:10px;">Lowest Bid</span>';
  }
  if (myBids > 0) {
    return '<span class="admin-badge badge-danger" style="font-size:10px;">Outbid</span>';
  }
  if (hasLowest) {
    return '<span class="admin-badge badge-gold" style="font-size:10px;">Bid Placed (' + bidCount + ')</span>';
  }
  return '<span class="admin-badge badge-info" style="font-size:10px;">Fresh</span>';
}

function buildAuctionBidBoxHtml(auction, canBidNow, canBidRole, isAdminViewer) {
  const auctionId = Number(auction.id || 0);
  const { minFloor, effectiveMax, suggestedBid } = getAuctionPricingBounds(auction);
  const termsUrl = (window.PLATFORM_SETTINGS && window.PLATFORM_SETTINGS.terms_conditions_url) || '/terms-conditions';
  const myBids = Number(auction.my_bids || 0);
  const remainingBids = Number(auction.remaining_bids ?? 0);
  const action = myBids > 0 ? 'Confirm New Bid' : 'Submit Bid';

  if (canBidNow && remainingBids > 0 && auction.is_open !== false) {
    return '<div class="auction-bid-box">'
      + '<div class="auction-bid-box-head">'
      + '<span>I will accept this booking at price (<strong style="color:var(--text-main);">' + auctionMoney(minFloor) + ' – ' + auctionMoney(effectiveMax) + '</strong> &bull; <span style="color:var(--success); font-weight:600;">' + auctionMoney(minFloor) + ' wins immediately</span>)</span>'
      + '<span style="color:var(--text-muted);">' + remainingBids + ' bid' + (remainingBids === 1 ? '' : 's') + ' left</span>'
      + '</div>'
      + '<div class="auction-bid-input-row">'
      + '<input type="number" id="console-bid-input-' + auctionId + '" class="form-control" inputmode="numeric" min="' + minFloor + '" max="' + effectiveMax + '" step="1" placeholder="e.g. ' + suggestedBid + '" style="flex:1; min-width:140px; height:34px; font-size:13px; font-weight:700;" oninput="updateConsoleBidPreview(' + auctionId + ',\'' + auctionEscape(auction.pickup_city) + '\',\'' + auctionEscape(auction.drop_city) + '\')" onkeydown="if(event.key===\'Enter\'){event.preventDefault();submitConsoleAuctionBid(\'' + auctionEscape(auction.token) + '\',' + auctionId + ');}">'
      + '<button type="button" id="console-bid-btn-' + auctionId + '" class="btn btn-sm btn-primary" onclick="submitConsoleAuctionBid(\'' + auctionEscape(auction.token) + '\',' + auctionId + ')">'
      + '<span class="material-symbols-outlined" style="font-size:15px;">gavel</span>'
      + '<span>' + action + '</span>'
      + '</button>'
      + '</div>'
      + '<div id="console-bid-preview-' + auctionId + '" style="font-size:11.5px; color:var(--text-main); font-weight:600; background:var(--surface-card); border:1px dashed var(--outline); border-radius:6px; padding:6px 9px;">Enter your amount above: <em>"I will accept this booking (' + auctionEscape(auction.pickup_city) + ' &rarr; ' + auctionEscape(auction.drop_city) + ') at price ₹___."</em></div>'
      + '<label class="auction-bid-terms">'
      + '<input type="checkbox" id="console-bid-terms-' + auctionId + '" style="margin-top:2px; accent-color:var(--primary);">'
      + '<span>I agree to the <a href="' + auctionEscape(termsUrl) + '" target="_blank" rel="noopener" style="color:var(--primary); font-weight:600; text-decoration:underline;">Terms &amp; Conditions</a> and confirm I will accept and complete this booking at my quoted price if allotted.</span>'
      + '</label>'
      + '<div id="console-bid-msg-' + auctionId + '" class="auction-bid-feedback"></div>'
      + '</div>';
  }
  if (canBidRole && remainingBids === 0) {
    return '<div style="width:100%; font-size:11.5px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 10px;">You have reached the bid limit for this auction.</div>';
  }
  if (isAdminViewer && !state.bidding.canBid) {
    return '<div style="width:100%; font-size:11.5px; color:#475569; background:var(--surface-base); border:1px solid var(--outline); border-radius:8px; padding:8px 10px;">Register at least one driver profile in the fleet to submit bids from Admin access.</div>';
  }
  return '';
}

function renderFilteredBiddingCards() {
  const container = document.getElementById('bidding-cards-stream');
  if (!container) return;

  const auctionsAll = Array.isArray(state.bidding.availableTrips) ? state.bidding.availableTrips : [];
  const recentAllottedAll = Array.isArray(state.bidding.recentAllotted) ? state.bidding.recentAllotted : [];
  const activeFilter = state.bidding.filter || 'all';
  const viewMode = state.bidding.viewMode || 'table';
  const expandedId = state.bidding.expandedId;
  const viewerRole = window.CURRENT_USER ? window.CURRENT_USER.role : null;
  const isAdminViewer = Boolean(state.bidding.isAdminViewer);
  const canBidRole = viewerRole === 'driver' || isAdminViewer;
  const canBidNow = state.bidding.canBid !== false && canBidRole;

  document.querySelectorAll('#driver-bidding-status-filters [data-bid-view]').forEach(function (el) {
    el.classList.toggle('active', el.dataset.bidView === viewMode);
  });

  const verificationBanner = (!state.bidding.canBid && !isAdminViewer)
    ? '<div style="text-align:center; padding:14px 16px; margin-bottom:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:12.5px;">Your driver profile is pending verification. Auctions are visible now; bidding unlocks once operations approves your profile.</div>'
    : '';

  let filteredAuctions = auctionsAll;
  let filteredAllotted = recentAllottedAll;

  if (activeFilter === 'fresh') {
    filteredAuctions = auctionsAll.filter(a => a.current_lowest === null || a.current_lowest === undefined);
    filteredAllotted = [];
  } else if (activeFilter === 'active_bids') {
    filteredAuctions = auctionsAll.filter(a => a.current_lowest !== null && a.current_lowest !== undefined);
    filteredAllotted = [];
  } else if (activeFilter === 'my_bids') {
    filteredAuctions = auctionsAll.filter(a => Number(a.my_bids || 0) > 0);
    filteredAllotted = recentAllottedAll.filter(item => item.allotted_to_you);
  } else if (activeFilter === 'allotted') {
    filteredAuctions = [];
    filteredAllotted = recentAllottedAll;
  }

  if (!filteredAuctions.length && !filteredAllotted.length) {
    const emptyTitles = {
      all: 'No live outstation auctions are open right now',
      fresh: 'No fresh auctions without bids right now',
      active_bids: 'No open auctions with bids placed right now',
      my_bids: 'You have not placed any bids yet',
      allotted: 'No allotted auctions yet'
    };
    const emptyTitle = emptyTitles[activeFilter] || emptyTitles.all;
    const resetBtn = activeFilter !== 'all'
      ? '<button type="button" class="btn btn-sm btn-primary" onclick="setDriverAuctionFilter(\'all\')"><span>Show All Auctions</span></button>'
      : (isAdminViewer
        ? '<button type="button" class="btn btn-sm btn-primary" onclick="switchTab(\'admin\'); switchAdminSection(\'bookings\');"><span class="material-symbols-outlined" style="font-size:15px;">gavel</span><span>Open Auction in Admin Panel</span></button>'
        : '');

    container.innerHTML = verificationBanner
      + '<div style="text-align:center; padding:28px 18px; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">'
      + '<span class="material-symbols-outlined" style="font-size:34px; color:#cbd5e1; display:block; margin-bottom:6px;">gavel</span>'
      + '<div style="font-weight:700; font-size:14px; color:#334155;">' + auctionEscape(emptyTitle) + '</div>'
      + '<p style="font-size:12px; margin:6px auto 14px; max-width:440px; color:#64748b; line-height:1.5;">Open trips appear here with an inline acceptance box so you can quote within the allowed range.</p>'
      + '<div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">'
      + resetBtn
      + '<button type="button" class="btn btn-sm btn-outline" onclick="renderBiddingList()">'
      + '<span class="material-symbols-outlined" style="font-size:15px;">refresh</span>'
      + '<span>Refresh Auctions</span>'
      + '</button>'
      + '</div>'
      + '</div>';
    return;
  }

  if (viewMode === 'table') {
    const openRowsHtml = filteredAuctions.map(function (auction) {
      const auctionId = Number(auction.id || 0);
      const { hasLowest, minFloor, effectiveMax } = getAuctionPricingBounds(auction);
      const lowest = hasLowest ? auctionMoney(auction.current_lowest) : '—';
      const badge = getAuctionStatusBadgeHtml(auction);
      const distanceText = auction.distance_km ? (auction.distance_km + ' km') : 'On req';
      const departure = auction.departure_at ? auctionDateTime(auction.departure_at) : 'TBD';
      const myBids = Number(auction.my_bids || 0);
      const myLatestVal = auction.my_latest_bid !== null && auction.my_latest_bid !== undefined
        ? auctionMoney(auction.my_latest_bid)
        : null;
      const isExpanded = expandedId === auctionId;
      const actionLabel = isExpanded ? 'Close' : (myBids > 0 ? 'Improve Bid' : 'Place Bid');

      const mainRow = '<tr>'
        + '<td>'
        + '<a href="' + auctionEscape(auction.bid_url) + '" class="booking-ref-link">' + auctionEscape(auction.booking_reference) + '</a>'
        + '<div class="cell-subtext">' + auctionEscape(auctionTimeLeft(auction.closes_at)) + '</div>'
        + '</td>'
        + '<td>'
        + '<div class="cell-primary-text">' + auctionEscape(auction.pickup_city) + ' &rarr; ' + auctionEscape(auction.drop_city) + '</div>'
        + '<div class="cell-subtext">' + auctionEscape(distanceText) + ' &bull; ' + auctionEscape(departure) + '</div>'
        + '</td>'
        + '<td>'
        + badge
        + (myLatestVal ? '<div class="cell-subtext">Your Bid: <strong>' + myLatestVal + '</strong></div>' : '')
        + '</td>'
        + '<td>'
        + '<div class="cell-primary-text text-tabular" style="color:var(--success);">' + (hasLowest ? lowest : ('Cap ' + auctionMoney(auction.start_amount))) + '</div>'
        + '<div class="cell-subtext">Range ' + auctionMoney(minFloor) + ' – ' + auctionMoney(effectiveMax) + '</div>'
        + '</td>'
        + '<td style="text-align:right; white-space:nowrap;">'
        + '<div class="admin-actions-compact">'
        + '<button type="button" class="btn btn-sm ' + (isExpanded ? 'btn-outline' : 'btn-primary') + '" style="height:28px; padding:0 10px; font-size:11px;" onclick="toggleAuctionBidDrawer(' + auctionId + ')">'
        + '<span class="material-symbols-outlined" style="font-size:14px;">' + (isExpanded ? 'expand_less' : 'gavel') + '</span>'
        + '<span>' + actionLabel + '</span>'
        + '</button>'
        + '<button type="button" class="btn-action-icon btn-action-view" onclick="openDriverAuctionDetails(' + auctionId + ')" title="Trip Details">'
        + '<span class="material-symbols-outlined">visibility</span>'
        + '</button>'
        + '<a class="btn-action-icon" style="background:#f8fafc; border-color:#e2e8f0; color:#475569; text-decoration:none;" href="' + auctionEscape(auction.bid_url) + '" title="Open Bid Page">'
        + '<span class="material-symbols-outlined">open_in_new</span>'
        + '</a>'
        + '</div>'
        + '</td>'
        + '</tr>';

      const drawerRow = isExpanded
        ? '<tr class="auction-bid-drawer-row"><td colspan="5">' + buildAuctionBidBoxHtml(auction, canBidNow, canBidRole, isAdminViewer) + '</td></tr>'
        : '';

      return mainRow + drawerRow;
    }).join('');

    const allottedRowsHtml = filteredAllotted.map(function (item) {
      const allottedBadge = item.allotted_to_you
        ? '<span class="admin-badge badge-success" style="font-size:10px;">Allotted to You</span>'
        : '<span class="admin-badge badge-gold" style="font-size:10px;">Allotted</span>';
      const dist = item.distance_km ? (item.distance_km + ' km') : 'On req';
      const dep = item.departure_at ? auctionDateTime(item.departure_at) : 'Scheduled';
      const payout = (item.allotted_to_you && item.winning_amount !== null && item.winning_amount !== undefined)
        ? auctionMoney(item.winning_amount)
        : '—';

      return '<tr style="background:#f8fafc;">'
        + '<td><span class="booking-ref-link">' + auctionEscape(item.booking_reference) + '</span><div class="cell-subtext">Driver allotted</div></td>'
        + '<td><div class="cell-primary-text">' + auctionEscape(item.pickup_city) + ' &rarr; ' + auctionEscape(item.drop_city) + '</div><div class="cell-subtext">' + auctionEscape(dist) + ' &bull; ' + auctionEscape(dep) + '</div></td>'
        + '<td>' + allottedBadge + '</td>'
        + '<td><div class="cell-primary-text text-tabular" style="color:' + (item.allotted_to_you ? 'var(--success)' : '#64748b') + ';">' + payout + '</div><div class="cell-subtext">' + (item.allotted_to_you ? 'Your payout' : 'Closed') + '</div></td>'
        + '<td style="text-align:right;"><span style="font-size:11px; color:var(--text-muted); font-weight:600;">Allotted</span></td>'
        + '</tr>';
    }).join('');

    container.innerHTML = verificationBanner
      + '<div class="auction-table-wrap admin-table-responsive">'
      + '<table class="admin-data-table admin-table-compact">'
      + '<thead><tr>'
      + '<th>BOOKING</th>'
      + '<th>ROUTE &amp; SCHEDULE</th>'
      + '<th>STATUS</th>'
      + '<th>LOWEST / RANGE</th>'
      + '<th style="text-align:right;">BID ACTION</th>'
      + '</tr></thead>'
      + '<tbody>' + openRowsHtml + allottedRowsHtml + '</tbody>'
      + '</table>'
      + '</div>';
    return;
  }

  // Compact Card View (default on mobile < 768px or when user toggles "Cards")
  const openCardsHtml = filteredAuctions.map(function (auction) {
    const auctionId = Number(auction.id || 0);
    const { hasLowest, minFloor, effectiveMax } = getAuctionPricingBounds(auction);
    const lowest = hasLowest ? auctionMoney(auction.current_lowest) : '—';
    const badge = getAuctionStatusBadgeHtml(auction);
    const distanceText = auction.distance_km ? (auction.distance_km + ' KM') : 'On request';
    const departure = auction.departure_at ? auctionDateTime(auction.departure_at) : 'TBD';
    const myBids = Number(auction.my_bids || 0);
    const myLatestVal = auction.my_latest_bid !== null && auction.my_latest_bid !== undefined
      ? auctionMoney(auction.my_latest_bid)
      : null;
    const isExpanded = expandedId === auctionId;
    const actionLabel = isExpanded ? 'Close' : (myBids > 0 ? 'Improve Bid' : 'Place Bid');

    return '<div class="auction-card auction-card-compact">'
      + '<div class="auction-card-top">'
      + '<div class="auction-meta-badges">'
      + '<span class="admin-badge badge-primary" style="font-size:10px; font-family:monospace;">' + auctionEscape(auction.booking_reference) + '</span>'
      + badge
      + '</div>'
      + '<span class="auction-deadline-pill"><span class="material-symbols-outlined" style="font-size:13px;">schedule</span><span>' + auctionEscape(auctionTimeLeft(auction.closes_at)) + '</span></span>'
      + '</div>'
      + '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">'
      + '<div>'
      + '<div class="cell-primary-text" style="font-size:13.5px;">' + auctionEscape(auction.pickup_city) + ' &rarr; ' + auctionEscape(auction.drop_city) + '</div>'
      + '<div class="cell-subtext">' + auctionEscape(distanceText) + ' &bull; ' + auctionEscape(departure) + '</div>'
      + '</div>'
      + '<div style="text-align:right;">'
      + '<div class="cell-primary-text text-tabular" style="color:var(--success);">' + (hasLowest ? ('Lowest ' + lowest) : ('Cap ' + auctionMoney(auction.start_amount))) + '</div>'
      + '<div class="cell-subtext">Range ' + auctionMoney(minFloor) + ' – ' + auctionMoney(effectiveMax) + (myLatestVal ? (' &bull; You: <strong>' + myLatestVal + '</strong>') : '') + '</div>'
      + '</div>'
      + '</div>'
      + (isExpanded ? buildAuctionBidBoxHtml(auction, canBidNow, canBidRole, isAdminViewer) : '')
      + '<div class="auction-bid-footer" style="padding-top:8px;">'
      + '<button type="button" class="btn btn-sm ' + (isExpanded ? 'btn-outline' : 'btn-primary') + '" style="height:30px; padding:0 12px; font-size:11.5px;" onclick="toggleAuctionBidDrawer(' + auctionId + ')">'
      + '<span class="material-symbols-outlined" style="font-size:14px;">' + (isExpanded ? 'expand_less' : 'gavel') + '</span>'
      + '<span>' + actionLabel + '</span>'
      + '</button>'
      + '<div style="display:flex; align-items:center; gap:6px;">'
      + '<button type="button" class="btn btn-sm btn-outline driver-item-details-btn" onclick="openDriverAuctionDetails(' + auctionId + ')">Details</button>'
      + '<a class="btn btn-sm btn-outline" style="height:34px; text-decoration:none;" href="' + auctionEscape(auction.bid_url) + '">'
      + '<span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span>'
      + '<span>Bid Page</span>'
      + '</a>'
      + '</div>'
      + '</div>'
      + '</div>';
  }).join('');

  const allottedCardsHtml = filteredAllotted.length
    ? '<div style="margin-top:' + (filteredAuctions.length ? '14px' : '0') + ';">'
    + '<div class="text-micro" style="color:var(--text-muted); margin-bottom:8px;">Allotted Trips</div>'
    + filteredAllotted.map(function (item) {
      const allottedBadge = item.allotted_to_you
        ? '<span class="admin-badge badge-success" style="font-size:10px;">Allotted to You</span>'
        : '<span class="admin-badge badge-gold" style="font-size:10px;">Allotted</span>';
      const dist = item.distance_km ? item.distance_km + ' KM' : 'On request';
      const payout = (item.allotted_to_you && item.winning_amount !== null && item.winning_amount !== undefined)
        ? ' &bull; Your payout <strong style="color:var(--success);">' + auctionMoney(item.winning_amount) + '</strong>'
        : '';
      return '<div class="auction-card auction-card--allotted auction-card-compact">'
        + '<div class="auction-card-top">'
        + '<div class="auction-meta-badges">'
        + '<span class="admin-badge badge-primary" style="font-size:10px; font-family:monospace;">' + auctionEscape(item.booking_reference) + '</span>'
        + allottedBadge
        + '</div>'
        + '<span class="auction-deadline-pill"><span class="material-symbols-outlined" style="font-size:13px;">verified</span><span>Allotted</span></span>'
        + '</div>'
        + '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:12.5px; color:var(--text-main);">'
        + '<div><strong>' + auctionEscape(item.pickup_city) + '</strong> &rarr; <strong>' + auctionEscape(item.drop_city) + '</strong> <span style="color:var(--text-muted); font-size:11.5px;">(' + auctionEscape(dist) + ')</span></div>'
        + '<div style="font-size:11.5px; color:#475569;">Status: <strong style="color:var(--gold-dark);">Allotted</strong>' + payout + '</div>'
        + '</div>'
        + '</div>';
    }).join('')
    + '</div>'
    : '';

  container.innerHTML = verificationBanner + openCardsHtml + allottedCardsHtml;
}

window.updateConsoleBidPreview = function (auctionId, pickupCity, dropCity) {
  const input = document.getElementById('console-bid-input-' + auctionId);
  const preview = document.getElementById('console-bid-preview-' + auctionId);
  if (!preview) return;
  const val = Number(input ? input.value : 0);
  if (val > 0) {
    preview.innerHTML = 'I will accept this booking (<strong>' + auctionEscape(pickupCity) + ' &rarr; ' + auctionEscape(dropCity) + '</strong>) at price <strong style="color:var(--gold-dark);">' + auctionMoney(val) + '</strong>.';
  } else {
    preview.innerHTML = 'Enter your amount above: <em>"I will accept this booking (' + auctionEscape(pickupCity) + ' &rarr; ' + auctionEscape(dropCity) + ') at price ₹___."</em>';
  }
};

window.submitConsoleAuctionBid = async function (token, auctionId) {
  const input = document.getElementById('console-bid-input-' + auctionId);
  const terms = document.getElementById('console-bid-terms-' + auctionId);
  const btn = document.getElementById('console-bid-btn-' + auctionId);
  const msg = document.getElementById('console-bid-msg-' + auctionId);

  const showMsg = (text, ok) => {
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'auction-bid-feedback ' + (ok ? 'ok' : 'error');
  };

  const amount = Number(input ? input.value : 0);
  if (!amount || amount < 1) {
    showMsg('Enter your bid amount in rupees.', false);
    if (input) input.focus();
    return;
  }

  if (terms && !terms.checked) {
    showMsg('Please agree to the Terms & Conditions before placing your bid.', false);
    terms.focus();
    return;
  }

  const origHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Submitting…';
  }

  try {
    const res = await fetch('/api/bidding/' + encodeURIComponent(token) + '/bids', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ amount: amount, agreed_terms: true })
    });
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      const errText = data.message || 'Could not place bid. Check the amount and try again.';
      showMsg(errText, false);
      if (typeof showToast === 'function') showToast(errText, 'warning');
      return;
    }

    if (typeof showToast === 'function') {
      showToast(data.message || 'Bid placed! You are currently the lowest bidder.', 'success');
    }
    await renderBiddingList();
  } catch (err) {
    console.error('submitConsoleAuctionBid', err);
    showMsg('Network error while placing bid. Please try again.', false);
  } finally {
    if (btn && document.body.contains(btn)) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }
};

function showDriverDetailsScreen(title, icon, rows, note) {
  const modal = document.getElementById('driver-item-details-modal');
  const titleEl = document.getElementById('driver-details-title');
  const iconEl = document.getElementById('driver-details-icon');
  const bodyEl = document.getElementById('driver-details-body');
  if (!modal || !bodyEl) return;
  if (titleEl) titleEl.textContent = title || 'Details';
  if (iconEl) iconEl.textContent = icon || 'info';
  const rowsHtml = (rows || []).map(r =>
    '<div class="driver-details-row">'
    + '<span class="driver-details-label">' + auctionEscape(r.label) + '</span>'
    + '<span class="driver-details-value">' + auctionEscape(r.value) + '</span>'
    + '</div>'
  ).join('');
  const noteHtml = note
    ? '<div style="margin-top:10px; padding:10px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:11.5px; color:#92400e;">' + auctionEscape(note) + '</div>'
    : '';
  bodyEl.innerHTML = '<div class="driver-details-grid">' + rowsHtml + '</div>' + noteHtml
    + '<div style="display:flex; justify-content:flex-end; margin-top:14px;">'
    + '<button type="button" class="btn btn-sm btn-secondary" onclick="closeModal(\'driver-item-details-modal\')">Close</button>'
    + '</div>';
  modal.classList.add('active');
}

function openDriverVehicleDetails(v) {
  if (!v) return;
  const berths = Number(v.berth_capacity || 0) > 0 ? ', ' + v.berth_capacity + ' Berths' : '';
  showDriverDetailsScreen(v.vehicle_model || 'Vehicle Details', 'directions_car', [
    { label: 'Registration Plate', value: v.vehicle_plate || '—' },
    { label: 'Approval Status', value: v.status || 'Pending' },
    { label: 'Fleet Class', value: (v.fleet_category || '—').toUpperCase() },
    { label: 'Capacity', value: (v.seating_capacity || 0) + ' Seats' + berths },
    { label: 'Fuel Type', value: v.fuel_type || 'Diesel' },
    { label: 'Year', value: v.manufacturing_year || '—' },
    { label: 'RC Number', value: v.rc_number || '—' }
  ], v.admin_notes ? 'Admin Note: ' + v.admin_notes : '');
}

function openDriverTripDetails(dt) {
  if (!dt) return;
  showDriverDetailsScreen('Trip ' + (dt.booking_reference || ''), 'local_taxi', [
    { label: 'Status', value: dt.status || '—' },
    { label: 'Route', value: dt.route_summary || '—' },
    { label: 'Passenger', value: (dt.customer_name || '—') + (dt.customer_phone ? ' (' + dt.customer_phone + ')' : '') },
    { label: 'Pickup Location', value: dt.pickup_location || '—' },
    { label: 'Drop Location', value: dt.drop_location || '—' },
    { label: 'Total Fare', value: '₹' + Number(dt.total_amount || 0).toLocaleString('en-IN') + ' (Tolls & taxes included)' }
  ], '');
}

function openDriverAuctionDetails(auctionId) {
  const list = (state && state.bidding && Array.isArray(state.bidding.availableTrips)) ? state.bidding.availableTrips : [];
  const a = list.find(item => Number(item.id) === Number(auctionId));
  if (!a) return;
  showDriverDetailsScreen('Auction ' + (a.booking_reference || ''), 'gavel', [
    { label: 'Route', value: (a.pickup_city || '—') + ' → ' + (a.drop_city || '—') },
    { label: 'Distance', value: a.distance_km ? a.distance_km + ' KM' : 'On request' },
    { label: 'Departure', value: a.departure_at ? auctionDateTime(a.departure_at) : 'TBD' },
    { label: 'Start Price', value: auctionMoney(a.start_amount) },
    { label: 'Current Lowest', value: a.current_lowest != null ? auctionMoney(a.current_lowest) : 'No bids yet' },
    { label: 'Closes', value: auctionTimeLeft(a.closes_at) }
  ], 'Passenger name, mobile and exact addresses unlock only if you win.');
}

// ==========================================
// 4. AdminLTE 3 Module & Dynamic Form Builder
// ==========================================
function initAdminModule() {
  const adminApp = document.getElementById('admin-lte-app');
  if (!adminApp) return;

  renderFormBuilderFields();
  renderWorkflowStages();
}

// AdminLTE 3 Responsive Side Panel Toggle (Mobile Drawer & Desktop Collapse)
window.toggleAdminSidebar = function (forceState) {
  const app = document.getElementById('admin-lte-app');
  if (!app) return;
  if (window.innerWidth < 992) {
    if (typeof forceState === 'boolean') {
      app.classList.toggle('sidebar-open', forceState);
    } else {
      app.classList.toggle('sidebar-open');
    }
  } else {
    if (typeof forceState === 'boolean') {
      app.classList.toggle('sidebar-collapsed', !forceState);
    } else {
      app.classList.toggle('sidebar-collapsed');
    }
  }
};

// AdminLTE 3 Section Switcher
window.switchAdminSection = function (sectionId) {
  // 1. Update active nav link
  document.querySelectorAll('.admin-nav-link').forEach(link => {
    link.classList.toggle('active', link.dataset.section === sectionId);
  });

  // 2. Update active content section
  document.querySelectorAll('.admin-section').forEach(sec => {
    sec.classList.remove('active');
  });
  const target = document.getElementById(`admin-sec-${sectionId}`);
  if (target) {
    target.classList.add('active');
  }

  // 3. Update breadcrumb title
  const titles = {
    'dashboard': 'Dashboard',
    'form-builder': 'Dynamic Form Builder',
    'workflow': 'Trip Pipeline Stages',
    'pricing': 'Fares & Surcharges',
    'fleet': 'Drivers & Bidding Fleet',
    'car-types': 'Car Types & Fleet Setup',
    'bookings': 'Outstation Bookings & Dispatch Management',
    'users': 'Registered Users & Drivers Directory',
    'locations': 'Service Locations & Network Hubs',
    'cab-routes': 'Outstation Cab Routes & Highway Matrix',
    'booking-types': 'Booking Types & Packages Management',
    'bidding': 'Driver Bidding Marketplace',
    'berth-routes': 'Berth Routes & Sleeper Schedules',
    'berth-layouts': 'Vehicle Berth & Seat Customizer',
    'vehicles': 'Driver Vehicles & Fleet Approvals',
    'system': 'System & Platform Health',
    'coupons': 'Coupons & Promotions'
  };
  const crumbTitle = document.getElementById('admin-crumb-title');
  if (crumbTitle && titles[sectionId]) {
    crumbTitle.textContent = titles[sectionId];
  }

  // Lazily refresh the auction board the first time it is opened.
  if (sectionId === 'bidding' && typeof window.renderAdminBiddings === 'function') {
    window.renderAdminBiddings();
  }

  // Load the coupon register the first time it is opened.
  if (sectionId === 'coupons' && typeof window.loadAdminCoupons === 'function') {
    window.loadAdminCoupons();
  }

  // 4. On mobile, automatically close sidebar after choosing item
  if (window.innerWidth < 992) {
    toggleAdminSidebar(false);
  }
};

// AdminLTE 3 Sidebar Navigation Live Search Filter
window.filterAdminMenu = function (query) {
  const q = (query || '').toLowerCase().trim();
  document.querySelectorAll('#admin-nav-list .admin-nav-item').forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? 'block' : 'none';
  });
  // Dynamically show/hide group headers based on visible items
  document.querySelectorAll('#admin-nav-list .admin-nav-header').forEach(header => {
    if (!q) {
      header.style.display = '';
      return;
    }
    let next = header.nextElementSibling;
    let hasVisible = false;
    while (next && !next.classList.contains('admin-nav-header')) {
      if (next.style.display !== 'none') {
        hasVisible = true;
        break;
      }
      next = next.nextElementSibling;
    }
    header.style.display = hasVisible ? '' : 'none';
  });
};

// Dynamic Field Modal Handlers
window.openAddDynamicFieldModal = function () {
  const modal = document.getElementById('add-dynamic-field-modal');
  if (modal) {
    modal.classList.add('active');
    setTimeout(() => {
      const input = document.getElementById('new-field-label');
      if (input) input.focus();
    }, 100);
  }
};

window.closeAddDynamicFieldModal = function () {
  const modal = document.getElementById('add-dynamic-field-modal');
  if (modal) modal.classList.remove('active');
};

window.submitAddDynamicField = function (e) {
  if (e) e.preventDefault();
  const labelInput = document.getElementById('new-field-label');
  const label = labelInput ? labelInput.value.trim() : '';
  if (!label) return;

  const type = document.getElementById('new-field-type')?.value || 'text';
  const applies = document.getElementById('new-field-applies')?.value || 'all';
  const condition = document.getElementById('new-field-condition')?.value || 'All Bookings';
  const required = document.getElementById('new-field-required')?.checked || false;

  const newField = {
    id: `f_${Date.now()}`,
    label: label,
    type: type,
    applies: applies,
    condition: condition,
    required: required
  };

  state.admin.customFields.push(newField);
  renderFormBuilderFields();
  closeAddDynamicFieldModal();
  document.getElementById('add-dynamic-field-form')?.reset();
  showToast(`Dynamic field "${label}" added to booking system!`, 'success');

  // Attempt async persistence to backend API
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  fetch('/api/admin/dynamic-fields', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken || ''
    },
    body: JSON.stringify({
      label: label,
      field_type: type,
      applies_to: applies,
      conditional_logic: condition,
      is_required: required
    })
  }).then(res => res.json()).then(data => {
    if (data && data.field) {
      newField.dbId = data.field.id;
    }
  }).catch(() => { });
};

function renderFormBuilderFields() {
  const container = document.getElementById('admin-fields-list');
  const previewContainer = document.getElementById('admin-preview-fields-container');
  const badgeCount = document.getElementById('admin-badge-fields-count');
  const statCount = document.getElementById('admin-stat-custom-fields');

  const count = state.admin.customFields.length;
  if (badgeCount) badgeCount.textContent = count;
  if (statCount) statCount.textContent = `${count} Fields`;

  if (container) {
    if (count === 0) {
      container.innerHTML = `<div style="text-align:center; padding:24px; color:#94a3b8;">No custom dynamic fields configured. Click "Add Custom Field" to inject one.</div>`;
    } else {
      container.innerHTML = state.admin.customFields.map((field, idx) => `
        <div class="builder-field-card">
          <div class="builder-field-meta">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="builder-field-title">${field.label}</span>
              ${field.required ? '<span class="admin-badge badge-primary">Required</span>' : ''}
            </div>
            <div style="display:flex; gap:8px; margin-top:4px; align-items:center; flex-wrap:wrap;">
              <span class="builder-field-type">Type: <strong>${field.type}</strong></span>
              <span class="builder-field-condition">Rule: ${field.condition}</span>
              <span style="font-size:10.5px; color:#64748b;">Scope: ${field.applies || 'all'}</span>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline" style="color:#dc2626; border-color:#fecaca;" onclick="deleteAdminField(${idx})" title="Remove field">
            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
          </button>
        </div>
      `).join('');
    }
  }

  if (previewContainer) {
    previewContainer.innerHTML = state.admin.customFields.map(field => `
      <div style="background:#ffffff; border:1px solid var(--outline); border-radius:var(--radius-xs); padding:10px;">
        <div style="font-size:11.5px; font-weight:700; color:#334155; margin-bottom:4px;">
          ${field.label} ${field.required ? '<span style="color:#ef4444;">*</span>' : ''}
        </div>
        ${field.type === 'checkbox' ? `
          <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; cursor:pointer;">
            <input type="checkbox" style="accent-color:var(--primary);">
            <span>Yes, include this requirement</span>
          </label>
        ` : `
          <input type="${field.type === 'number' ? 'number' : 'text'}" class="form-control" placeholder="Passenger enters ${field.label.toLowerCase()}..." style="height:34px; font-size:12px;" disabled>
        `}
        <div style="font-size:9.5px; color:#94a3b8; margin-top:4px;">Rule: ${field.condition}</div>
      </div>
    `).join('');
  }
}

window.deleteAdminField = function (idx) {
  const field = state.admin.customFields[idx];
  state.admin.customFields.splice(idx, 1);
  renderFormBuilderFields();
  showToast('Dynamic field removed.', 'info');

  if (field && field.dbId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    fetch(`/api/admin/dynamic-fields/${field.dbId}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || ''
      }
    }).catch(() => { });
  }
};

function renderWorkflowStages() {
  const track = document.getElementById('admin-workflow-track');
  if (!track) return;

  track.innerHTML = state.admin.workflowStages.map((stage, idx) => `
    <div class="workflow-stage-pill ${idx === 1 ? 'active' : ''}">
      <span style="font-size: 18px; color: var(--primary);">0${idx + 1}</span>
      <div>
        <div style="font-size: 13px; font-weight: 700;">${stage.name}</div>
        <div style="font-size: 11px; color: #64748b; font-weight: 500;">${stage.desc}</div>
      </div>
    </div>
    ${idx < state.admin.workflowStages.length - 1 ? '<span class="material-symbols-outlined workflow-stage-arrow">arrow_forward</span>' : ''}
  `).join('');
}

// ==========================================
// 5. Payment Simulation & Boarding Pass Modal
// ==========================================
function openPaymentModal(sourceType) {
  const modal = document.getElementById('payment-modal');
  let amount = 0;
  let summary = '';

  if (sourceType === 'bus') {
    amount = state.schedules.selectedSeats.reduce((sum, s) => sum + s.price, 0);
    summary = `${state.schedules.selectedSeats.length} Bus Berths (${state.schedules.route})`;
  } else {
    // Charge exactly what the server quoted (P1-2). No local arithmetic.
    const serverQuote = state.cab.serverQuote && state.cab.serverQuote.quote;

    if (serverQuote) {
      amount = Number(serverQuote.total_amount) || 0;
    } else {
      const fleet = FLEET_TYPES[state.cab.selectedFleet];
      amount = Math.round(state.cab.distance * fleet.ratePerKm + (state.cab.includeTolls ? 400 : 0) - state.cab.discountAmount);
    }

    const fromName = CITY_DATABASE[state.cab.fromCity]?.name || state.cab.fromCity || 'Origin';
    const toName = CITY_DATABASE[state.cab.toCity]?.name || state.cab.toCity || 'Destination';
    summary = `Outstation Cab: ${fromName} → ${toName}`;
  }

  document.getElementById('payment-modal-amount').textContent = `₹${amount.toLocaleString('en-IN')}`;
  document.getElementById('payment-modal-desc').textContent = summary;
  modal.dataset.source = sourceType;
  modal.dataset.amount = amount;
  modal.classList.add('active');
}

window.confirmMockPayment = async function (method) {
  const modal = document.getElementById('payment-modal');
  modal.classList.remove('active');

  const source = modal.dataset.source;

  // The ticket must always reflect a real server-side booking (P0-7 / honesty).
  // Nothing is fabricated any more: no random reference, no random OTP.
  if (source === 'bus') {
    const route = state.schedules.route || '';
    const [routeFrom, routeTo] = route.split('→').map(part => (part || '').trim());

    const busPayload = {
      service_type: 'bus',
      from_city: routeFrom || 'Pune',
      to_city: routeTo || 'Goa',
      pickup_location: state.schedules.pickup || `${routeFrom || 'Pune'} Bus Terminal`,
      drop_location: state.schedules.dropoff || `${routeTo || 'Goa'} Bus Stand`,
      customer_name: (state.schedules.passengers[0]?.name) || window.CURRENT_USER?.name || 'Passenger',
      customer_phone: window.CURRENT_USER?.phone || '+91 98220 11920',
      customer_email: window.CURRENT_USER?.email || 'traveller@intercitywheels.com',
      selected_units: state.schedules.selectedSeats.map(s => s.id),
      coach_code: state.schedules.selectedSeats[0]?.coach || state.schedules.coachCode || null,
      passengers: state.schedules.passengers
    };

    let booked = null;

    try {
      const res = await fetch('/api/bookings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(busPayload)
      });

      const data = await res.json();

      if (res.status === 401) {
        beginBookingLoginGate(busPayload);
        return;
      }

      if (res.ok && data.success && data.booking) {
        booked = data.booking;
      } else {
        showToast(data.message || 'Those berths could not be booked. Please pick again.', 'danger');
        return;
      }
    } catch (err) {
      console.warn('Bus booking API error:', err);
      showToast('We could not reach the booking service. No berths were booked.', 'danger');
      return;
    }

    const booking = {
      id: booked.booking_reference,
      type: 'Volvo AC Sleeper',
      route: booked.route_summary,
      date: 'Today, Scheduled',
      operator: state.schedules.operator || 'Intercity Sleeper',
      berths: (booked.selected_units || []).map(String),
      passengers: state.schedules.passengers.map(p => p.name || 'Passenger'),
      amount: Number(booked.total_amount),
      status: 'Requested',
      otp: String(booked.start_otp)
    };

    state.bookings.unshift(booking);
    showToast(`Payment of ₹${booking.amount.toLocaleString('en-IN')} via ${method} recorded. Reference ${booking.id}.`, 'success');

    // Same destination as cab bookings: the trip history, with this trip on top.
    switchTab('my-bookings');
    window.showBookingInHistory(booked);

    return;
  }

  // Cab bookings are created by the booking form; there is nothing to create
  // here, so never invent one.
  showToast('No pending booking to pay for. Please complete the booking form first.', 'warning');
};

function showTicketModal(booking) {
  const modal = document.getElementById('ticket-modal');
  document.getElementById('ticket-display-id').textContent = booking.id;
  document.getElementById('ticket-display-route').textContent = booking.route;
  document.getElementById('ticket-display-operator').textContent = booking.operator;
  document.getElementById('ticket-display-berths').textContent = booking.berths.join(', ');
  document.getElementById('ticket-display-amount').textContent = `₹${booking.amount.toLocaleString('en-IN')}`;
  document.getElementById('ticket-display-otp').textContent = booking.otp;

  // Generate dynamic QR code pattern (SVG zero-dependency)
  const qrSvg = generateSvgQrCode(booking.id, booking.otp);
  document.getElementById('ticket-qr-box').innerHTML = qrSvg;

  modal.classList.add('active');
}

// Zero-dependency SVG QR code generator
function generateSvgQrCode(id, otp) {
  return `
    <svg width="140" height="140" viewBox="0 0 100 100" fill="#0f172a" xmlns="http://www.w3.org/2000/svg">
      <!-- Outer positioning boxes -->
      <rect x="5" y="5" width="26" height="26" fill="none" stroke="#d49b4c" stroke-width="5" rx="3"/>
      <rect x="11" y="11" width="14" height="14" fill="#d49b4c"/>
      <rect x="69" y="5" width="26" height="26" fill="none" stroke="#d49b4c" stroke-width="5" rx="3"/>
      <rect x="75" y="11" width="14" height="14" fill="#d49b4c"/>
      <rect x="5" y="69" width="26" height="26" fill="none" stroke="#d49b4c" stroke-width="5" rx="3"/>
      <rect x="11" y="75" width="14" height="14" fill="#d49b4c"/>
      
      <!-- Data modules -->
      <rect x="36" y="8" width="8" height="8" fill="#d49b4c"/>
      <rect x="48" y="12" width="6" height="6" fill="#0f172a"/>
      <rect x="36" y="24" width="6" height="6" fill="#0f172a"/>
      <rect x="46" y="32" width="8" height="8" fill="#d49b4c"/>
      <rect x="10" y="42" width="6" height="6" fill="#0f172a"/>
      <rect x="22" y="48" width="8" height="8" fill="#0f172a"/>
      <rect x="36" y="46" width="12" height="12" fill="#d49b4c"/>
      <rect x="58" y="44" width="8" height="8" fill="#0f172a"/>
      <rect x="72" y="38" width="6" height="6" fill="#0f172a"/>
      <rect x="84" y="48" width="6" height="6" fill="#0f172a"/>
      <rect x="42" y="64" width="8" height="8" fill="#0f172a"/>
      <rect x="54" y="60" width="8" height="8" fill="#8c5d1e"/>
      <rect x="68" y="68" width="10" height="10" fill="#d49b4c"/>
      <rect x="44" y="78" width="6" height="6" fill="#0f172a"/>
      <rect x="56" y="76" width="6" height="6" fill="#0f172a"/>
      <rect x="80" y="80" width="8" height="8" fill="#0f172a"/>
    </svg>
    <span style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 6px;">BOARDING PASS SCANNER</span>
  `;
}

// Modal Dismissers
window.closeModal = function (id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
};

// Close active modal on backdrop click
document.addEventListener('click', function (e) {
  if (e.target && e.target.classList && e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Close active modal on Escape key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
      modal.classList.remove('active');
    });
  }
});

// Toast Notifications Helper
function showToast(message, type = 'info') {
  const shelf = document.getElementById('toast-shelf');
  if (!shelf) return;

  const toast = document.createElement('div');
  toast.className = `toast-pill toast-${type}`;

  let icon = 'info';
  if (type === 'success') icon = 'check_circle';
  if (type === 'warning') icon = 'warning';
  if (type === 'gold') icon = 'stars';

  toast.innerHTML = `
    <span class="material-symbols-outlined" style="font-size: 18px; color: ${type === 'gold' ? '#f5d061' : 'inherit'};">${icon}</span>
    <span>${message}</span>
  `;

  shelf.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}
window.showToast = showToast;

// ==========================================
// Role-Based Authentication System
// ==========================================
const ROLE_CONFIGS = {
  customer: {
    title: 'Customer',
    email: 'customer@intercitywheels.com',
    icon: 'directions_car',
    desc: 'Customer Portal: Book sanitised outstation cabs and luxury sleeper bus berths.',
    btnLabel: 'Sign In as Customer',
    defaultTab: 'cabs'
  },
  driver: {
    title: 'Chauffeur Partner',
    email: 'driver@intercitywheels.com',
    icon: 'local_taxi',
    desc: 'Driver Partner Console: Review and bid on live intercity trip requests & track wallet payouts.',
    btnLabel: 'Sign In as Driver Partner',
    defaultTab: 'bidding'
  },
  admin: {
    title: 'Platform Admin',
    email: 'admin@intercitywheels.com',
    icon: 'shield_person',
    desc: 'Platform Administrator: Control pricing, fleet allot, live dispatch, and manage drivers.',
    btnLabel: 'Sign In as Administrator',
    defaultTab: 'admin'
  }
};

function initAuth() {
  // Auto-route based on active role (Desktop full admin panel by default for admin)
  if (window.CURRENT_USER) {
    if (window.CURRENT_USER.role === 'admin') {
      document.body.classList.add('role-admin-mode', 'admin-active-view');
      switchTab('admin');
      const secParam = new URLSearchParams(window.location.search).get('section');
      if (secParam && typeof switchAdminSection === 'function') {
        switchAdminSection(secParam);
      }
    } else if (window.CURRENT_USER.role === 'driver') {
      switchTab('bidding');
    }
  }

  // Dismiss user profile dropdown when clicking outside
  window.addEventListener('click', (e) => {
    const menu = document.getElementById('user-profile-menu');
    const shelf = document.getElementById('user-dropdown-shelf');
    if (shelf && menu && !menu.contains(e.target)) {
      shelf.style.display = 'none';
    }
  });
}

window.toggleUserMenu = function () {
  const shelf = document.getElementById('user-dropdown-shelf');
  if (shelf) {
    shelf.style.display = (shelf.style.display === 'none' || !shelf.style.display) ? 'block' : 'none';
  }
};

window.openLoginModal = function (role = 'customer', mode = 'signin') {
  selectLoginRole(role);
  if (typeof switchAuthMode === 'function') {
    switchAuthMode(mode);
  }
  // Mobile OTP is retired in favor of Firebase ID & Credentials
  if (typeof switchSignInMethod === 'function') {
    switchSignInMethod('password');
  }
  const modal = document.getElementById('login-modal');
  if (modal) modal.classList.add('active');
};

window.switchAuthMode = function (mode) {
  const isSignIn = (mode === 'signin');
  const signinContainer = document.getElementById('auth-signin-container');
  const signupContainer = document.getElementById('auth-signup-container');
  const tabSignIn = document.getElementById('tab-btn-signin');
  const tabSignUp = document.getElementById('tab-btn-signup');
  const title = document.getElementById('auth-modal-title');
  const subtitle = document.getElementById('auth-modal-subtitle');

  if (signinContainer) signinContainer.style.display = isSignIn ? 'block' : 'none';
  if (signupContainer) signupContainer.style.display = isSignIn ? 'none' : 'block';

  if (tabSignIn) tabSignIn.classList.toggle('active', isSignIn);
  if (tabSignUp) tabSignUp.classList.toggle('active', !isSignIn);

  if (title) title.textContent = isSignIn ? 'Portal Sign In' : 'Create Account';
  if (subtitle) subtitle.textContent = isSignIn ? 'Select role & authenticate securely' : 'Sign up for Intercity Wheels cabs & outstation services';
};

window.switchSignInMethod = function (method) {
  const isOtp = (method === 'otp');
  const otpView = document.getElementById('signin-view-otp');
  const passView = document.getElementById('signin-view-password');
  const btnOtp = document.getElementById('method-btn-otp');
  const btnPass = document.getElementById('method-btn-password');

  if (otpView) otpView.style.display = isOtp ? 'block' : 'none';
  if (passView) passView.style.display = isOtp ? 'none' : 'block';

  if (btnOtp) btnOtp.classList.toggle('active', isOtp);
  if (btnPass) btnPass.classList.toggle('active', !isOtp);
};

window.lastReceivedOtp = '1234';

window.handleSendOtp = async function (event) {
  if (event) event.preventDefault();
  const phoneInput = document.getElementById('login-otp-phone');
  const roleInput = document.getElementById('login-role-input');
  const sendBtn = document.getElementById('btn-send-otp');
  const sendBtnText = document.getElementById('btn-send-otp-text');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const phone = phoneInput ? phoneInput.value.trim() : '';
  const role = roleInput ? roleInput.value : 'customer';

  if (!phone || phone.length < 10) {
    showToast('Please enter a valid 10-digit mobile number.', 'warning');
    return;
  }

  if (sendBtn) {
    sendBtn.disabled = true;
    if (sendBtnText) sendBtnText.textContent = 'Sending OTP via Fast2SMS...';
  }

  try {
    const res = await fetch('/auth/send-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ phone, role })
    });

    const data = await res.json();

    if (res.ok && data.success) {
      window.lastReceivedOtp = data.otp || '1234';

      // Update live preview OTP display on screen
      const otpDisplay = document.getElementById('otp-display-number');
      if (otpDisplay) otpDisplay.textContent = window.lastReceivedOtp;

      const gatewayLabel = document.getElementById('otp-status-gateway-label');
      if (gatewayLabel) {
        gatewayLabel.textContent = data.sms_sent
          ? 'Fast2SMS Gateway Active (SMS Dispatched)'
          : 'Fast2SMS Gateway (Demo Mode - Live OTP Shown Below)';
      }

      // Show toast with OTP notice
      showToast(data.message, 'info');

      // Reveal step 2 (verify OTP form)
      const reqForm = document.getElementById('otp-request-form');
      const verForm = document.getElementById('otp-verify-form');
      if (reqForm) reqForm.style.display = 'none';
      if (verForm) verForm.style.display = 'block';

      const otpInput = document.getElementById('login-otp-input');
      if (otpInput) {
        otpInput.value = '';
        otpInput.focus();
      }
    } else {
      showToast(data.message || 'Failed to send OTP.', 'warning');
    }
  } catch (err) {
    console.error('Send OTP error:', err);
    showToast('Network error while requesting OTP.', 'warning');
  } finally {
    if (sendBtn) {
      sendBtn.disabled = false;
      if (sendBtnText) sendBtnText.textContent = 'Send Verification OTP';
    }
  }
};

window.autoFillReceivedOtp = function () {
  const otpInput = document.getElementById('login-otp-input');
  const otpDisplay = document.getElementById('otp-display-number');
  const otpToUse = window.lastReceivedOtp || (otpDisplay ? otpDisplay.textContent.trim() : '1234');
  if (otpInput) {
    otpInput.value = otpToUse;
    showToast(`Auto-filled OTP: ${otpToUse}`, 'success');
    otpInput.focus();
  }
};

window.resetOtpStep = function () {
  const reqForm = document.getElementById('otp-request-form');
  const verForm = document.getElementById('otp-verify-form');
  if (reqForm) reqForm.style.display = 'block';
  if (verForm) verForm.style.display = 'none';
};

window.resendOtp = function () {
  showToast('Requesting fresh OTP...', 'info');
  handleSendOtp();
};

window.handleVerifyOtp = async function (event) {
  if (event) event.preventDefault();
  const phoneInput = document.getElementById('login-otp-phone');
  const otpInput = document.getElementById('login-otp-input');
  const roleInput = document.getElementById('login-role-input');
  const verifyBtn = document.getElementById('btn-verify-otp');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const phone = phoneInput ? phoneInput.value.trim() : '';
  const otp = otpInput ? otpInput.value.trim() : '';
  const role = roleInput ? roleInput.value : 'customer';

  if (!otp) {
    showToast('Please enter the verification OTP.', 'warning');
    return;
  }

  const origBtnHtml = verifyBtn ? verifyBtn.innerHTML : '';
  if (verifyBtn) {
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = `<span class="material-symbols-outlined" style="animation:spin 1s linear infinite; font-size:18px;">sync</span> <span>Verifying OTP...</span>`;
  }

  try {
    const res = await fetch('/auth/verify-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ phone, otp, role })
    });

    const data = await res.json();

    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('login-modal');
      const targetUrl = data.redirect_url || '/';
      const sep = targetUrl.includes('?') ? '&' : '?';
      window.location.replace(targetUrl + sep + 'auth=' + Date.now());
    } else {
      showToast(data.message || 'OTP verification failed.', 'danger');
      if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = origBtnHtml;
      }
    }
  } catch (err) {
    console.error('Verify OTP error:', err);
    showToast('Network error during OTP verification.', 'warning');
    if (verifyBtn) {
      verifyBtn.disabled = false;
      verifyBtn.innerHTML = origBtnHtml;
    }
  }
};

window.selectSignupRole = function (role) {
  const isDriver = (role === 'driver');
  const roleInput = document.getElementById('signup-role-input');
  const btnCustomer = document.getElementById('signup-role-btn-customer');
  const btnDriver = document.getElementById('signup-role-btn-driver');
  const driverFields = document.getElementById('signup-driver-fields');
  const submitLabel = document.getElementById('signup-submit-label');

  if (roleInput) roleInput.value = role;
  if (btnCustomer) btnCustomer.classList.toggle('active', !isDriver);
  if (btnDriver) btnDriver.classList.toggle('active', isDriver);

  if (driverFields) driverFields.style.display = isDriver ? 'block' : 'none';
  if (submitLabel) submitLabel.textContent = isDriver ? 'Register as Driver Partner' : 'Create Customer Account';
};

window.handleFormRegister = async function (event) {
  if (event) event.preventDefault();
  const nameEl = document.getElementById('signup-name');
  const phoneEl = document.getElementById('signup-phone');
  const emailEl = document.getElementById('signup-email');
  const passwordEl = document.getElementById('signup-password');
  const roleEl = document.getElementById('signup-role-input');
  const vehModelEl = document.getElementById('signup-veh-model');
  const vehPlateEl = document.getElementById('signup-veh-plate');
  const submitBtn = document.getElementById('signup-submit-btn');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const name = nameEl ? nameEl.value.trim() : '';
  const phone = phoneEl ? phoneEl.value.trim() : '';
  const email = emailEl ? emailEl.value.trim() : '';
  const password = passwordEl ? passwordEl.value : '';
  const role = roleEl ? roleEl.value : 'customer';
  const vehicle_model = vehModelEl ? vehModelEl.value.trim() : '';
  const vehicle_plate = vehPlateEl ? vehPlateEl.value.trim() : '';

  if (!name || !phone || !email || !password) {
    showToast('Please fill in all required registration fields.', 'warning');
    return;
  }

  const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="material-symbols-outlined" style="animation:spin 1s linear infinite; font-size:18px;">sync</span> <span>Registering Account...</span>`;
  }

  try {
    const registerEndpoint = role === 'driver' ? '/driver/register' : '/register';
    const res = await fetch(registerEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({
        name,
        phone,
        email,
        password,
        role,
        vehicle_model,
        vehicle_plate
      })
    });

    const data = await res.json();

    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('login-modal');
      const targetUrl = data.redirect_url || '/';
      const sep = targetUrl.includes('?') ? '&' : '?';
      window.location.replace(targetUrl + sep + 'auth=' + Date.now());
    } else {
      const errorMsg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Registration failed.');
      showToast(errorMsg, 'warning');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      }
    }
  } catch (err) {
    console.error('Registration error:', err);
    showToast('Network error during registration.', 'warning');
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origBtnHtml;
    }
  }
};

window.selectLoginRole = function (role) {
  const cfg = ROLE_CONFIGS[role] || ROLE_CONFIGS.customer;

  // Update role pill active state
  document.querySelectorAll('.role-pill').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.role === role);
  });

  // Update hidden input
  const roleInput = document.getElementById('login-role-input');
  if (roleInput) roleInput.value = role;

  // Update scope notice
  const scopeText = document.getElementById('role-scope-text');
  const scopeIcon = document.getElementById('role-scope-icon');
  if (scopeText) scopeText.textContent = cfg.desc;
  if (scopeIcon) scopeIcon.textContent = cfg.icon;

  // Preset demo credentials for that role only when demo mode is enabled
  const emailInput = document.getElementById('login-email');
  const passInput = document.getElementById('login-password');
  if (window.INTERCITY_CONFIG && window.INTERCITY_CONFIG.demo) {
    if (emailInput) emailInput.value = cfg.email;
    if (passInput) passInput.value = 'password123';
  } else {
    if (emailInput) emailInput.value = '';
    if (passInput) passInput.value = '';
  }

  // Update button label
  const btnLabel = document.getElementById('login-submit-label');
  if (btnLabel) btnLabel.textContent = cfg.btnLabel;
};

window.handleFormLogin = async function (event) {
  if (event) event.preventDefault();
  const emailEl = document.getElementById('login-email');
  const passwordEl = document.getElementById('login-password');
  const roleEl = document.getElementById('login-role-input');
  const rememberEl = document.getElementById('login-remember');
  const submitBtn = document.getElementById('login-submit-btn');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const email = emailEl ? emailEl.value.trim() : '';
  const password = passwordEl ? passwordEl.value : '';
  const role = roleEl ? roleEl.value : 'customer';
  const remember = rememberEl ? rememberEl.checked : false;

  if (!email || !password) {
    showToast('Please enter both email and password.', 'warning');
    return;
  }

  const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="material-symbols-outlined" style="animation:spin 1s linear infinite; font-size:18px;">sync</span> <span>Signing In & Loading...</span>`;
  }

  try {
    const res = await fetch('/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ email, password, role, remember })
    });

    const data = await res.json();

    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('login-modal');
      const targetUrl = data.redirect_url || '/';
      const sep = targetUrl.includes('?') ? '&' : '?';
      window.location.replace(targetUrl + sep + 'auth=' + Date.now());
    } else {
      showToast(data.message || 'Authentication failed. Please verify credentials.', 'warning');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      }
    }
  } catch (err) {
    console.error('Login error:', err);
    // Fallback: submit standard HTML form to ensure login completes regardless
    const form = document.getElementById('role-login-form');
    if (form) {
      form.submit();
    } else {
      showToast('Network error during authentication. Please retry.', 'warning');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      }
    }
  }
};

window.quickDemoLogin = async function (role) {
  // Demo login does not exist in production; send the user to the real form.
  if (!(window.INTERCITY_CONFIG && window.INTERCITY_CONFIG.demo)) {
    openLoginModal('customer', 'signin');
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  showToast(`Authenticating as ${role.toUpperCase()}...`, 'info');

  try {
    const res = await fetch('/login/quick-demo', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ role })
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('login-modal');
      const targetUrl = data.redirect_url || '/';
      const sep = targetUrl.includes('?') ? '&' : '?';
      window.location.replace(targetUrl + sep + 'auth=' + Date.now());
    } else {
      showToast(data.message || 'Demo login failed.', 'warning');
    }
  } catch (err) {
    console.error('Demo login error:', err);
    window.location.replace('/?auth=' + Date.now());
  }
};

/* ======================================================== */
/* BOOKING LOGIN GATE — sign-in required before confirming   */
/* ======================================================== */
const PENDING_BOOKING_KEY = 'intercityPendingBooking';

window.beginBookingLoginGate = function (payload) {
  // Persist the trip so it survives the one-tap login page reload.
  try {
    sessionStorage.setItem(PENDING_BOOKING_KEY, JSON.stringify(payload));
  } catch (err) {
    console.warn('Could not persist pending booking:', err);
  }

  const modal = document.getElementById('booking-login-gate-modal');
  if (modal) {
    modal.classList.add('active');
    return;
  }

  showToast('Please sign in to confirm your booking.', 'warning');
};

window.oneTapBookingLogin = function () {
  // One tap only exists in demo mode. In production, use the real sign-in form.
  const demoEnabled = Boolean(window.INTERCITY_CONFIG && window.INTERCITY_CONFIG.demo);

  if (demoEnabled && typeof window.quickDemoLogin === 'function') {
    window.quickDemoLogin('customer');
    return;
  }

  closeModal('booking-login-gate-modal');
  openLoginModal('customer', 'signin');
};

window.resumePendingBooking = async function () {
  let raw = null;
  try {
    raw = sessionStorage.getItem(PENDING_BOOKING_KEY);
  } catch (err) {
    return;
  }

  if (!raw) { return; }
  if (!(window.INTERCITY_AUTH && window.INTERCITY_AUTH.authenticated)) { return; }

  let payload;
  try {
    payload = JSON.parse(raw);
  } catch (err) {
    try { sessionStorage.removeItem(PENDING_BOOKING_KEY); } catch (e) { }
    return;
  }

  try { sessionStorage.removeItem(PENDING_BOOKING_KEY); } catch (e) { }

  showToast('Finishing your booking…', 'info');

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/api/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (res.ok && data.success && data.booking) {
      showToast(
        `Booking confirmed. Reference ${data.booking.booking_reference} (Ride OTP: ${data.booking.start_otp})`,
        'success'
      );
      setTimeout(function () {
        window.location.replace('/?booked=' + encodeURIComponent(data.booking.booking_reference));
      }, 1000);
    } else {
      showToast(data.message || 'Could not complete your pending booking.', 'danger');
    }
  } catch (err) {
    showToast('We could not reach the booking service. Your trip details are saved.', 'danger');
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () { window.resumePendingBooking(); });
} else {
  window.resumePendingBooking();
}

/* ======================================================== */
/* ALTERNATE AUTHENTICATION: FIREBASE & CLERK               */
/* ======================================================== */
window.quickFillDemo = function (role) {
  if (typeof selectLoginRole === 'function') {
    selectLoginRole(role);
  }
};

window.getRoleFirebaseProfile = function (role, customEmail = '', customName = '') {
  if (customEmail && customEmail.includes('@') && !customEmail.includes('customer@intercitywheels.com') && !customEmail.includes('driver@intercitywheels.com') && !customEmail.includes('admin@intercitywheels.com')) {
    const rawName = customName || customEmail.split('@')[0];
    const cleanId = 'fb_' + rawName.toLowerCase().replace(/[^a-z0-9]/g, '_') + '_' + Math.floor(1000 + Math.random() * 9000);
    return {
      name: rawName.charAt(0).toUpperCase() + rawName.slice(1),
      email: customEmail,
      firebase_id: cleanId
    };
  }

  if (role === 'driver') {
    return {
      name: 'Rajesh Chauffeur',
      email: 'rajesh.driver@gmail.com',
      firebase_id: 'fb_driver_rajesh_9922'
    };
  } else if (role === 'admin') {
    return {
      name: 'Admin Supervisor',
      email: 'admin@intercitywheels.com',
      firebase_id: 'fb_admin_supervisor_1001'
    };
  }
  return {
    name: 'Priya Sharma',
    email: 'priya.customer@gmail.com',
    firebase_id: 'fb_cust_priya_sharma_9876'
  };
};

window.handleAlternateLogin = async function (provider = 'firebase') {
  let role = 'customer';
  let email = '';
  let name = '';
  let vehicleModel = '';
  let vehiclePlate = '';
  const isSignIn = document.getElementById('auth-signin-container')?.style.display !== 'none';
  if (isSignIn) {
    const roleInput = document.getElementById('login-role-input');
    if (roleInput && roleInput.value) role = roleInput.value;
    const emailInput = document.getElementById('login-email');
    if (emailInput && emailInput.value) email = emailInput.value.trim();
  } else {
    const signupRoleInput = document.getElementById('signup-role-input');
    if (signupRoleInput && signupRoleInput.value) role = signupRoleInput.value;
    const signupEmailInput = document.getElementById('signup-email');
    if (signupEmailInput && signupEmailInput.value) email = signupEmailInput.value.trim();
    const signupNameInput = document.getElementById('signup-name');
    if (signupNameInput && signupNameInput.value) name = signupNameInput.value.trim();
    const vehModelInput = document.getElementById('signup-veh-model');
    if (vehModelInput && vehModelInput.value) vehicleModel = vehModelInput.value.trim();
    const vehPlateInput = document.getElementById('signup-veh-plate');
    if (vehPlateInput && vehPlateInput.value) vehiclePlate = vehPlateInput.value.trim();
  }

  if (!window.ALTERNATE_AUTH_CONFIG) {
    try {
      const res = await fetch('/auth/alternate-config');
      const data = await res.json();
      if (data.success) {
        window.ALTERNATE_AUTH_CONFIG = data.config;
      }
    } catch (e) {
      console.warn('Could not load alternate auth config:', e);
    }
  }

  const altConfig = window.ALTERNATE_AUTH_CONFIG || {};

  if (provider === 'firebase') {
    if (altConfig.firebase && altConfig.firebase.is_configured) {
      return window.loginWithFirebaseLive(role);
    }
    if (!(window.INTERCITY_CONFIG && window.INTERCITY_CONFIG.demo)) {
      showToast('Google / Firebase sign-in is not configured.', 'warning');
      return;
    }
    // Seamless 1-click Firebase ID sign-in in offline/local mode:
    const profile = window.getRoleFirebaseProfile(role, email, name);
    showToast(`Signing in via Firebase ID as ${role.toUpperCase()}...`, 'info');
    return window.submitAlternateAuth({
      provider: 'firebase',
      role: role,
      name: profile.name,
      email: profile.email,
      firebase_id: profile.firebase_id,
      password: 'password123',
      vehicle_model: vehicleModel || (role === 'driver' ? 'Maruti Suzuki Ertiga' : null),
      vehicle_plate: vehiclePlate || (role === 'driver' ? 'WB 02 AB 9988' : null),
      avatar: role === 'driver' ? '👨🏽‍✈️' : (role === 'admin' ? '🛡️' : '👤')
    });
  } else if (provider === 'clerk') {
    if (altConfig.clerk && altConfig.clerk.is_configured) {
      return window.loginWithClerkLive(role);
    }
    const profile = window.getRoleFirebaseProfile(role, email, name);
    showToast(`Signing in via Clerk Auth as ${role.toUpperCase()}...`, 'info');
    return window.submitAlternateAuth({
      provider: 'clerk',
      role: role,
      name: profile.name,
      email: profile.email,
      clerk_user_id: 'user_clerk_' + (profile.firebase_id ? profile.firebase_id.replace('fb_', '') : Date.now()),
      password: 'password123',
      vehicle_model: vehicleModel || (role === 'driver' ? 'Maruti Suzuki Ertiga' : null),
      vehicle_plate: vehiclePlate || (role === 'driver' ? 'WB 02 AB 9988' : null),
      avatar: role === 'driver' ? '👨🏽‍✈️' : '👤'
    });
  }
};

window.openAlternateAuthModal = function (provider = 'firebase', role = 'customer') {
  const modal = document.getElementById('alternate-auth-modal');
  const title = document.getElementById('alt-modal-title');
  const subtitle = document.getElementById('alt-modal-subtitle');
  const brandIcon = document.getElementById('alt-modal-brand-icon');
  const providerName = document.getElementById('alt-provider-name');
  const roleName = document.getElementById('alt-role-name');
  const submitLabel = document.getElementById('alt-submit-label');
  const inputProvider = document.getElementById('alt-input-provider');
  const inputRole = document.getElementById('alt-input-role');
  const envTip = document.getElementById('alt-env-tip');

  if (inputProvider) inputProvider.value = provider;
  if (inputRole) inputRole.value = role;
  if (roleName) roleName.textContent = role.toUpperCase();

  const roleTitle = role.charAt(0).toUpperCase() + role.slice(1);
  const fbGroup = document.getElementById('alt-group-firebase-id');
  const fbIdInput = document.getElementById('alt-input-firebase-id');

  if (provider === 'firebase') {
    if (brandIcon) brandIcon.textContent = '🔥';
    if (title) title.textContent = 'Firebase ID & Google Sign-In';
    if (subtitle) subtitle.textContent = 'Authenticate with your Firebase User ID or Google account';
    if (providerName) providerName.textContent = 'Firebase ID / Google';
    if (submitLabel) submitLabel.textContent = `Sign In as ${roleTitle} via Firebase ID`;
    if (envTip) envTip.innerHTML = '💡 <strong>Live Firebase Setup:</strong> Set <code>FIREBASE_API_KEY</code> & <code>FIREBASE_PROJECT_ID</code> in <code>.env</code> to activate native Google popup.';
    if (fbGroup) fbGroup.style.display = 'block';
  } else {
    if (brandIcon) brandIcon.textContent = '⚡';
    if (title) title.textContent = 'Clerk Authentication';
    if (subtitle) subtitle.textContent = 'Fast Single Sign-On and multi-factor auth with Clerk';
    if (providerName) providerName.textContent = 'Clerk Auth';
    if (submitLabel) submitLabel.textContent = `Sign In as ${roleTitle} with Clerk`;
    if (envTip) envTip.innerHTML = '💡 <strong>Live Clerk Setup:</strong> Set <code>CLERK_PUBLISHABLE_KEY</code> & <code>CLERK_SECRET_KEY</code> in <code>.env</code> to activate live Clerk SSO.';
    if (fbGroup) fbGroup.style.display = 'none';
  }

  const nameInput = document.getElementById('alt-input-name');
  const emailInput = document.getElementById('alt-input-email');
  if (window.INTERCITY_CONFIG && window.INTERCITY_CONFIG.demo) {
    if (role === 'driver') {
      if (nameInput) nameInput.value = 'Rajesh Chauffeur';
      if (emailInput) emailInput.value = 'rajesh.driver@gmail.com';
      if (fbIdInput) fbIdInput.value = 'fb_driver_rajesh_9922';
    } else if (role === 'admin') {
      if (nameInput) nameInput.value = 'Admin Supervisor';
      if (emailInput) emailInput.value = 'admin@intercitywheels.com';
      if (fbIdInput) fbIdInput.value = 'fb_admin_supervisor_1001';
    } else {
      if (nameInput) nameInput.value = 'Priya Sharma';
      if (emailInput) emailInput.value = 'priya.customer@gmail.com';
      if (fbIdInput) fbIdInput.value = 'fb_cust_priya_sharma_9876';
    }
  } else {
    if (nameInput) nameInput.value = '';
    if (emailInput) emailInput.value = '';
    if (fbIdInput) fbIdInput.value = '';
  }

  closeModal('login-modal');

  if (modal) modal.classList.add('active');
};

window.selectAltDemoProfile = function (profileRole) {
  const nameInput = document.getElementById('alt-input-name');
  const emailInput = document.getElementById('alt-input-email');
  const fbIdInput = document.getElementById('alt-input-firebase-id');
  const roleInput = document.getElementById('alt-input-role');
  const roleName = document.getElementById('alt-role-name');

  if (profileRole === 'driver') {
    if (nameInput) nameInput.value = 'Rajesh Chauffeur';
    if (emailInput) emailInput.value = 'rajesh.driver@gmail.com';
    if (fbIdInput) fbIdInput.value = 'fb_driver_rajesh_9922';
    if (roleInput) roleInput.value = 'driver';
    if (roleName) roleName.textContent = 'DRIVER';
  } else {
    if (nameInput) nameInput.value = 'Priya Sharma';
    if (emailInput) emailInput.value = 'priya.customer@gmail.com';
    if (fbIdInput) fbIdInput.value = 'fb_cust_priya_sharma_9876';
    if (roleInput) roleInput.value = 'customer';
    if (roleName) roleName.textContent = 'CUSTOMER';
  }

  window.submitAlternateAuthForm();
};

window.submitAlternateAuthForm = async function (event) {
  if (event) event.preventDefault();

  const provider = document.getElementById('alt-input-provider')?.value || 'firebase';
  const role = document.getElementById('alt-input-role')?.value || 'customer';
  const name = document.getElementById('alt-input-name')?.value?.trim();
  const email = document.getElementById('alt-input-email')?.value?.trim();
  const firebaseId = document.getElementById('alt-input-firebase-id')?.value?.trim();
  const password = document.getElementById('alt-input-password')?.value?.trim() || 'password123';

  if (!email && !firebaseId) {
    showToast('Please provide an email address or Firebase ID.', 'warning');
    return;
  }

  await window.submitAlternateAuth({
    provider,
    role,
    name,
    email,
    firebase_id: firebaseId,
    password: password,
    avatar: role === 'driver' ? '👨🏽‍✈️' : '👤'
  });
};

window.submitAlternateAuth = async function (payload) {
  const submitBtn = document.getElementById('alt-submit-btn');
  const fbBtn = document.getElementById('btn-auth-firebase-google');
  const clerkBtn = document.getElementById('btn-auth-clerk');
  const signupFbBtn = document.getElementById('btn-signup-firebase-google');
  const signupClerkBtn = document.getElementById('btn-signup-clerk');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const activeBtns = [submitBtn, fbBtn, clerkBtn, signupFbBtn, signupClerkBtn].filter(Boolean);
  const origContents = new Map();
  activeBtns.forEach(btn => {
    origContents.set(btn, btn.innerHTML);
    btn.disabled = true;
  });

  if (submitBtn) {
    submitBtn.innerHTML = '<span class="material-symbols-outlined spin" style="font-size:18px;">autorenew</span> Authenticating...';
  }
  if (fbBtn && payload.provider === 'firebase') {
    fbBtn.innerHTML = '<span class="material-symbols-outlined spin" style="font-size:16px;">autorenew</span> Authenticating Firebase ID...';
  }
  if (clerkBtn && payload.provider === 'clerk') {
    clerkBtn.innerHTML = '<span class="material-symbols-outlined spin" style="font-size:16px;">autorenew</span> Authenticating Clerk...';
  }

  try {
    const endpoint = payload.provider === 'firebase' ? '/auth/firebase-login' : '/auth/alternate-login';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (res.ok && data.success) {
      showToast(data.message || 'Signed in successfully!', 'success');
      closeModal('alternate-auth-modal');
      closeModal('login-modal');
      const targetUrl = data.redirect_url || '/';
      const sep = targetUrl.includes('?') ? '&' : '?';
      window.location.replace(targetUrl + sep + 'auth=' + Date.now());
    } else {
      showToast(data.message || 'Alternate authentication failed.', 'warning');
      activeBtns.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = origContents.get(btn) || 'Try Again';
      });
    }
  } catch (err) {
    console.error('Alternate login error:', err);
    showToast('An error occurred during authentication. Please retry.', 'error');
    activeBtns.forEach(btn => {
      btn.disabled = false;
      btn.innerHTML = origContents.get(btn) || 'Try Again';
    });
  }
};

const FIREBASE_PENDING_ROLE_KEY = 'icw_firebase_pending_role';

function firebaseConfig() {
  return window.ALTERNATE_AUTH_CONFIG?.firebase || {};
}

/** Turn a Firebase SDK error into the console step that fixes it. */
function describeFirebaseAuthError(err) {
  const code = (err && err.code) || '';

  const known = {
    'auth/unauthorized-domain':
      'This domain is not authorised in the Firebase project. Open the app at http://localhost:8004 (localhost is pre-authorised), or add this host under Firebase → Authentication → Settings → Authorized domains.',
    'auth/operation-not-allowed':
      'Google sign-in is turned off for this Firebase project. Enable it under Authentication → Sign-in method → Google.',
    'auth/invalid-api-key':
      'The Firebase API key is missing or invalid. Check FIREBASE_API_KEY in .env.',
    'auth/app-not-authorized':
      'These Firebase keys are not authorised for this domain. Add the domain to the Firebase project.',
    'auth/unauthorized-continue-uri':
      'The return domain is not authorised for this Firebase project.',
    'auth/popup-blocked':
      'The browser blocked the sign-in window. Allow popups for this site and try again.',
    'auth/popup-closed-by-user':
      'The sign-in window was closed before Google finished signing you in.',
    'auth/cancelled-popup-request':
      'Another sign-in window is already open. Close it and try again.',
    'auth/network-request-failed':
      'Could not reach Google. Check the connection and try again.',
    'auth/account-exists-with-different-credential':
      'This email already uses a different sign-in method.',
    'auth/internal-error':
      'Google rejected the sign-in request. If its page mentioned redirect_uri_mismatch, the project is using an OAuth client that does not list https://<your-auth-domain>/__/auth/handler — add it in Google Cloud → Credentials, or clear the custom client in Firebase → Authentication → Google.'
  };

  if (known[code]) return known[code];

  if (code === 'auth/unknown' || code === '') {
    return 'Firebase sign-in failed. If the Google page mentioned redirect_uri_mismatch or access_denied, fix the Firebase project settings — see docs/FIREBASE.md.';
  }

  return (err && err.message) || 'Firebase sign-in failed. Please try again.';
}

/** Load the compat SDK and initialise the app once (deduplicated across concurrent callers). */
let firebaseReadyPromise = null;

async function ensureFirebase() {
  if (!firebaseReadyPromise) {
    firebaseReadyPromise = (async () => {
      if (typeof firebase === 'undefined') {
        await loadScript('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
        await loadScript('https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js');
      }

      const cfg = firebaseConfig();

      if (!cfg.is_configured) {
        throw new Error('Firebase is not configured on the server.');
      }

      if (!firebase.apps.length) {
        firebase.initializeApp(cfg);
      }

      return firebase;
    })().catch((err) => {
      firebaseReadyPromise = null;
      throw err;
    });
  }

  return firebaseReadyPromise;
}

/** Fetch the SDK while the page is idle so the popup opens inside the click. */
function warmFirebaseSdk() {
  const cfg = firebaseConfig();

  if (!cfg.is_configured || typeof firebase !== 'undefined') return;

  ensureFirebase().catch(() => {
    // The first click retries; nothing to report before the user acts.
  });
}

async function finishFirebaseSignIn(user, role) {
  const idToken = await user.getIdToken();

  await window.submitAlternateAuth({
    provider: 'firebase',
    id_token: idToken,
    uid: user.uid,
    email: user.email,
    name: user.displayName || (user.email || '').split('@')[0],
    photo_url: user.photoURL,
    role: role
  });
}

window.loginWithFirebaseLive = async function (role = 'customer') {
  showToast('Connecting with Google / Firebase...', 'info');

  try {
    await ensureFirebase();

    const provider = new firebase.auth.GoogleAuthProvider();
    provider.setCustomParameters({ prompt: 'select_account' });

    let result;

    try {
      result = await firebase.auth().signInWithPopup(provider);
    } catch (popupError) {
      // A blocked popup is recoverable: remember the role and redirect instead.
      if (popupError && popupError.code === 'auth/popup-blocked') {
        sessionStorage.setItem(FIREBASE_PENDING_ROLE_KEY, role);
        showToast('Popup blocked — redirecting to Google...', 'info');
        await firebase.auth().signInWithRedirect(provider);

        return;
      }

      throw popupError;
    }

    await finishFirebaseSignIn(result.user, role);
  } catch (err) {
    console.error('Firebase live login error:', err);
    showToast(describeFirebaseAuthError(err), 'warning');
  }
};

/** Completes a sign-in that came back through signInWithRedirect. */
async function completeFirebaseRedirect() {
  if (!firebaseConfig().is_configured) return;

  try {
    await ensureFirebase();

    const result = await firebase.auth().getRedirectResult();

    if (!result || !result.user) return;

    const role = sessionStorage.getItem(FIREBASE_PENDING_ROLE_KEY) || 'customer';
    sessionStorage.removeItem(FIREBASE_PENDING_ROLE_KEY);

    await finishFirebaseSignIn(result.user, role);
  } catch (err) {
    console.error('Firebase redirect completion failed:', err);
    showToast(describeFirebaseAuthError(err), 'warning');
  }
}

// Warm the SDK when the page is idle: early enough that the first click still
// opens the popup inside the gesture, late enough not to compete with the
// first paint. The redirect return path must also run on every page load.
function bootstrapFirebase() {
  warmFirebaseSdk();
  completeFirebaseRedirect();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof requestIdleCallback === 'function') {
      requestIdleCallback(bootstrapFirebase, { timeout: 3000 });
    } else {
      setTimeout(bootstrapFirebase, 1200);
    }
  });
} else if (typeof requestIdleCallback === 'function') {
  requestIdleCallback(bootstrapFirebase, { timeout: 3000 });
} else {
  bootstrapFirebase();
}

window.loginWithClerkLive = async function (role = 'customer') {
  showToast('Connecting with Clerk Auth...', 'info');
  try {
    const clerkConfig = window.ALTERNATE_AUTH_CONFIG?.clerk || {};
    if (typeof Clerk === 'undefined') {
      await loadScript('https://cdn.jsdelivr.net/npm/@clerk/clerk-js@latest/dist/clerk.browser.js');
    }

    const clerk = new window.Clerk(clerkConfig.publishableKey);
    await clerk.load();

    if (clerk.user) {
      await window.submitAlternateAuth({
        provider: 'clerk',
        clerk_user_id: clerk.user.id,
        email: clerk.user.primaryEmailAddress?.emailAddress,
        name: clerk.user.fullName || clerk.user.firstName,
        avatar: clerk.user.imageUrl,
        role: role
      });
    } else {
      clerk.openSignIn();
    }
  } catch (err) {
    console.error('Clerk live login error:', err);
    showToast(err.message || 'Clerk sign-in encountered an error.', 'warning');
  }
};

const pendingScriptLoads = new Map();

function loadScript(src) {
  if (pendingScriptLoads.has(src)) {
    return pendingScriptLoads.get(src);
  }

  const existing = document.querySelector(`script[src="${src}"]`);
  if (existing && existing.dataset.loaded === '1') {
    return Promise.resolve();
  }

  const promise = new Promise((resolve, reject) => {
    const script = existing || document.createElement('script');
    const onLoad = () => {
      script.dataset.loaded = '1';
      resolve();
    };
    const onError = (err) => {
      pendingScriptLoads.delete(src);
      reject(err);
    };

    script.addEventListener('load', onLoad, { once: true });
    script.addEventListener('error', onError, { once: true });

    if (!existing) {
      script.src = src;
      document.head.appendChild(script);
    }
  });

  pendingScriptLoads.set(src, promise);
  return promise;
}

window.performLogout = function () {
  // 1. Preferred Native Form Submission (100% reliable across all browsers & cache engines)
  const logoutForm = document.getElementById('logout-form');
  if (logoutForm) {
    showToast('Signing out...', 'info');
    logoutForm.submit();
    return;
  }

  // 2. Fetch API Fallback
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  fetch('/logout', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    }
  }).finally(() => {
    window.location.replace('/?logout=' + Date.now());
  });
};

window.handleAdminOpsClick = function () {
  if (window.CURRENT_USER && window.CURRENT_USER.role === 'admin') {
    switchTab('admin');
  } else if (window.CURRENT_USER) {
    showToast(`Current account is ${window.CURRENT_USER.role.toUpperCase()}. Admin privileges required.`, 'warning');
    openLoginModal('admin');
  } else {
    showToast('Please sign in with your Administrator account.', 'info');
    openLoginModal('admin');
  }
};

/* ======================================================== */
/* ADMIN: COMPACT BOOKING MANAGEMENT & DISPATCH MODULE       */
/* ======================================================== */
window.currentViewingBookingId = null;

// Convert integer amount to Indian Rupees in words
function numberToWordsINR(num) {
  num = Math.round(Number(num) || 0);
  if (num === 0) return 'Rupees Zero Only';

  const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
  const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

  function inWords(n) {
    if (n < 20) return a[n];
    let digit = n % 10;
    return b[Math.floor(n / 10)] + (digit ? ' ' + a[digit] : ' ');
  }

  let str = '';
  let crore = Math.floor(num / 10000000);
  num %= 10000000;
  let lakh = Math.floor(num / 100000);
  num %= 100000;
  let thousand = Math.floor(num / 1000);
  num %= 1000;
  let hundred = Math.floor(num / 100);
  let rest = num % 100;

  if (crore) str += inWords(crore) + 'Crore ';
  if (lakh) str += inWords(lakh) + 'Lakh ';
  if (thousand) str += inWords(thousand) + 'Thousand ';
  if (hundred) str += inWords(hundred) + 'Hundred ';
  if (rest) str += (str ? 'and ' : '') + inWords(rest);

  return 'Rupees ' + str.trim() + ' Only';
}

// Client-side search & status filtering for compact bookings table
window.setBookingTripTypeFilter = function (tripType, btn) {
  const container = document.getElementById('admin-booking-trip-type-filters');
  if (container) {
    container.querySelectorAll('.admin-trip-type-btn').forEach(b => b.classList.remove('active'));
  }
  if (btn) {
    btn.classList.add('active');
  }
  handleBookingFilterChange();
};

window.handleBookingFilterChange = function () {
  const query = (document.getElementById('admin-booking-search-input')?.value || '').toLowerCase().trim();
  const statusFilter = document.getElementById('admin-booking-filter-status')?.value || 'all';
  const serviceFilter = document.getElementById('admin-booking-filter-service')?.value || 'all';
  const activeTripBtn = document.querySelector('#admin-booking-trip-type-filters .admin-trip-type-btn.active');
  const tripTypeFilter = (activeTripBtn?.dataset.tripType || 'all').toLowerCase();

  const rows = document.querySelectorAll('#admin-bookings-table-tbody tr[data-search]');
  let visibleCount = 0;

  rows.forEach(row => {
    const searchData = row.dataset.search || '';
    const rowStatus = row.dataset.status || '';
    const rowService = row.dataset.service || '';
    const rowTripType = (row.dataset.tripType || 'oneway').toLowerCase();

    const matchQuery = !query || searchData.includes(query);
    const matchStatus = (statusFilter === 'all') || (rowStatus.toLowerCase() === statusFilter.toLowerCase());
    const matchService = (serviceFilter === 'all') || (rowService.toLowerCase() === serviceFilter.toLowerCase());
    const matchTripType = (tripTypeFilter === 'all') || (rowTripType === tripTypeFilter);

    if (matchQuery && matchStatus && matchService && matchTripType) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countBadge = document.getElementById('admin-bookings-filter-count');
  if (countBadge) {
    countBadge.textContent = `${visibleCount} Bookings`;
  }

  const emptyRow = document.getElementById('admin-bookings-empty-row');
  if (emptyRow) {
    emptyRow.style.display = visibleCount === 0 ? '' : 'none';
  }

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-bookings', true);
  }
};

// Extract normalized cab type for a booking
function getBookingCabType(b) {
  if (!b || b.service_type !== 'cab') return '';
  if (b.cab_type) return String(b.cab_type).toUpperCase();
  const unit = Array.isArray(b.selected_units) && b.selected_units[0] ? String(b.selected_units[0]).trim() : '';
  if (unit && unit.toLowerCase() !== 'admin assigned') {
    if (b.driver && unit === b.driver.vehicle_plate && b.driver.fleet_category) {
      return String(b.driver.fleet_category).toUpperCase();
    }
    return unit.toUpperCase();
  }
  if (b.driver && b.driver.fleet_category) {
    return String(b.driver.fleet_category).toUpperCase();
  }
  return '';
}

// Reload / Fetch all bookings from backend API
window.reloadAdminBookings = async function () {
  try {
    const res = await fetch('/api/admin/bookings');
    const data = await res.json();
    if (data.success && Array.isArray(data.bookings)) {
      renderAdminBookingsTable(data.bookings);
      showToast(`Updated ${data.bookings.length} bookings.`, 'info');
    }
  } catch (err) {
    console.error('Failed to reload bookings:', err);
    showToast('Failed to refresh bookings.', 'warning');
  }
};

// Dynamically render bookings table rows
function renderAdminBookingsTable(bookings) {
  const tbody = document.getElementById('admin-bookings-table-tbody');
  if (!tbody) return;

  const countBadge = document.getElementById('admin-badge-bookings-count');
  if (countBadge) countBadge.textContent = bookings.length;
  const filterCount = document.getElementById('admin-bookings-filter-count');
  if (filterCount) filterCount.textContent = `${bookings.length} Bookings`;

  if (bookings.length === 0) {
    tbody.innerHTML = `
      <tr id="admin-bookings-empty-row">
        <td colspan="8" style="text-align:center; padding:35px 20px; color:#94a3b8;">
          <span class="material-symbols-outlined" style="font-size:32px; color:#cbd5e1; display:block; margin-bottom:6px;">inbox</span>
          <span>No customer bookings found. Click "New Booking" to manually log an outstation trip.</span>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = bookings.map(b => {
    const createdDate = b.created_at ? new Date(b.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'Recent';
    const statusClass = b.status === 'Confirmed' ? 'badge-success' : (b.status === 'Driver Assigned' ? 'badge-gold' : (b.status === 'Started' ? 'badge-info' : (b.status === 'Completed' ? 'badge-primary' : 'badge-secondary')));
    const driverHtml = b.driver ? `
      <div class="driver-allotted-card">
        <span class="cell-primary-text">${b.driver.name}</span>
        <span class="driver-plate-pill">${b.driver.vehicle_plate}</span>
        <button type="button" class="btn-link-action" onclick="openAllotDriverModal(${b.id})" title="Change Driver">Change</button>
      </div>
    ` : `
      <button type="button" class="btn btn-xs btn-outline-gold" onclick="openAllotDriverModal(${b.id})">
        <span class="material-symbols-outlined" style="font-size:13px;">person_add</span>
        <span>Allot Driver</span>
      </button>
    `;

    const cabType = getBookingCabType(b);
    const cabTypeBadge = cabType
      ? `<span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Cab Type">${cabType}</span>`
      : '';
    const outOfRegionBadge = b.is_beyond_region
      ? `<span class="admin-badge badge-warning" style="font-size:9px; padding:1px 5px;" title="Beyond service region — availability not guaranteed">OUT OF REGION</span>`
      : '';
    const payLabel = b.payment_status === 'pending_availability'
      ? 'Unconfirmed / Out of Region'
      : (b.payment_status === 'pay_on_pickup' ? 'Pay on Pickup' : 'Prepaid (Escrow)');
    const payDotClass = b.status === 'Cancelled'
      ? 'bg-danger'
      : (b.payment_status === 'pending_availability' ? 'bg-warning' : 'bg-success');
    const searchStr = `${b.booking_reference} ${b.customer_name} ${b.customer_phone} ${b.route_summary} ${b.pickup_location} ${b.drop_location} ${cabType}`.toLowerCase();

    return `
      <tr id="booking-row-${b.id}" data-id="${b.id}" data-ref="${b.booking_reference}" data-status="${b.status}" data-service="${b.service_type}" data-trip-type="${(b.trip_type || 'oneway').toLowerCase()}" data-search="${searchStr}">
        <td>
          <a href="javascript:void(0)" onclick="openViewBookingModal(${b.id})" class="booking-ref-link" title="Click to inspect booking">
            ${b.booking_reference}
          </a>
          <div class="cell-subtext">${createdDate}</div>
        </td>
        <td>
          <div class="cell-primary-text">${b.customer_name}</div>
          <div class="cell-subtext">
            <a href="tel:${b.customer_phone}" style="color:#64748b; text-decoration:none;">${b.customer_phone}</a>
          </div>
        </td>
        <td>
          <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
            <span class="admin-badge ${b.service_type === 'cab' ? 'badge-primary' : 'badge-gold'}" style="font-size:9.5px; padding:1px 5px;">
              ${(b.service_type || 'CAB').toUpperCase()}
            </span>
            ${cabTypeBadge}
            ${outOfRegionBadge}
            <span class="cell-primary-text">${b.route_summary || ''}</span>
          </div>
          <div class="cell-subtext truncate-subtext" title="${b.pickup_location || ''} → ${b.drop_location || ''}">
            ${b.pickup_location || ''} → ${b.drop_location || ''}
          </div>
        </td>
        <td>
          <div class="cell-primary-text text-tabular" style="font-weight:800; color:#0f172a;">
            ₹${Number(b.total_amount).toLocaleString('en-IN')}
          </div>
          <div class="cell-subtext">
            <span class="status-indicator-dot ${payDotClass}"></span>
            <span>${payLabel}</span>
          </div>
        </td>
        <td id="booking-driver-cell-${b.id}">
          ${driverHtml}
        </td>
        <td>
          <span class="admin-otp-pill">${b.start_otp || '----'}</span>
        </td>
        <td>
          <span class="admin-badge ${statusClass}" id="booking-status-badge-${b.id}">
            ${b.status}
          </span>
        </td>
        <td style="text-align:right;">
          <div class="admin-actions-compact">
            <button type="button" class="btn-action-icon btn-action-driver" onclick="openAllotDriverModal(${b.id})" title="Allot / Change Chauffeur">
              <span class="material-symbols-outlined">local_taxi</span>
            </button>
            <button type="button" class="btn-action-icon btn-action-view" onclick="openViewBookingModal(${b.id})" title="View Details & Payment Breakdown">
              <span class="material-symbols-outlined">visibility</span>
            </button>
            <button type="button" class="btn-action-icon btn-action-edit" onclick="openEditBookingModal(${b.id})" title="Edit Booking">
              <span class="material-symbols-outlined">edit</span>
            </button>
            <button type="button" class="btn-action-icon btn-action-invoice" onclick="openInvoiceModal(${b.id})" title="Generate Tax Invoice">
              <span class="material-symbols-outlined">receipt</span>
            </button>
            <button type="button" class="btn-action-icon" style="background:#f0f7ff; color:#1d4ed8; border:1px solid #bfdbfe;" onclick="openBiddingForBooking(${b.id})" title="Send to driver bidding (Configure & Preview)">
              <span class="material-symbols-outlined">gavel</span>
            </button>
            ${b.service_type === 'cab' && b.status !== 'Completed' && b.status !== 'Cancelled' ? `
            <button type="button" class="btn-action-icon" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;" onclick="copyBookingBidInvitation(${b.id}, this)" title="Copy Formatted WhatsApp Bid Invitation">
              <span class="material-symbols-outlined">content_copy</span>
            </button>
            <button type="button" class="btn-action-icon" style="background:#ecfdf5; color:#047857; border:1px solid #6ee7b7;" onclick="shareBookingBidOnWhatsApp(${b.id}, this)" title="Share Bid Invitation on WhatsApp">
              <span class="material-symbols-outlined">share</span>
            </button>` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
  handleBookingFilterChange();
}

// 1. ALLOT CHAUFFEUR MODAL
window.openAllotDriverModal = async function (bookingId) {
  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}`);
    const data = await res.json();
    if (!data.success || !data.booking) return;

    const b = data.booking;
    document.getElementById('allot-booking-id').value = b.id;
    document.getElementById('allot-info-ref').textContent = b.booking_reference;
    document.getElementById('allot-info-fare').textContent = `₹${Number(b.total_amount).toLocaleString('en-IN')}`;
    document.getElementById('allot-info-customer').textContent = `${b.customer_name} (${b.customer_phone})`;
    const cabType = getBookingCabType(b);
    document.getElementById('allot-info-route').textContent = `${(b.service_type || 'Cab').toUpperCase()}${cabType ? ` (${cabType})` : ''} • ${b.route_summary || ''}`;

    const select = document.getElementById('allot-driver-select');
    if (select) {
      select.value = b.assigned_driver_id || '';
      window.onAllotDriverSelectChange(select);
    }

    const modal = document.getElementById('admin-allot-driver-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Failed to open allot driver modal:', err);
    showToast('Failed to load booking details.', 'warning');
  }
};

window.onAllotDriverSelectChange = function (selectElem) {
  const preview = document.getElementById('allot-driver-preview');
  if (!preview) return;

  const selectedOpt = selectElem.options[selectElem.selectedIndex];
  if (!selectedOpt || !selectedOpt.value) {
    preview.style.display = 'none';
    return;
  }

  document.getElementById('allot-preview-name').textContent = selectedOpt.text.split('—')[0]?.trim() || '';
  document.getElementById('allot-preview-rating').textContent = `★${selectedOpt.dataset.rating || '4.90'}`;
  document.getElementById('allot-preview-vehicle').textContent = selectedOpt.dataset.vehicle || '';
  document.getElementById('allot-preview-plate').textContent = selectedOpt.dataset.plate || '';
  document.getElementById('allot-preview-phone').textContent = selectedOpt.dataset.phone || '';
  preview.style.display = 'block';
};

window.submitAllotDriver = async function (e) {
  if (e) e.preventDefault();
  const bookingId = document.getElementById('allot-booking-id').value;
  const driverId = document.getElementById('allot-driver-select').value;
  const status = document.getElementById('allot-status-select').value;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}/allot-driver`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ driver_id: driverId, status: status })
    });

    const data = await res.json();
    if (data.success && data.booking) {
      showToast(data.message || 'Driver allotted successfully!', 'success');
      closeModal('admin-allot-driver-modal');
      reloadAdminBookings();
    } else {
      showToast(data.message || 'Failed to allot driver.', 'warning');
    }
  } catch (err) {
    console.error('Allot driver error:', err);
    showToast('Network error while assigning driver.', 'warning');
  }
};

window.openAllotDriverFromView = function () {
  if (window.currentViewingBookingId) {
    closeModal('admin-view-booking-modal');
    openAllotDriverModal(window.currentViewingBookingId);
  }
};

// 2. VIEW DETAILS & PAYMENT MODAL
window.openViewBookingModal = async function (bookingId) {
  window.currentViewingBookingId = bookingId;
  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}`);
    const data = await res.json();
    if (!data.success || !data.booking) return;

    const b = data.booking;
    document.getElementById('view-booking-ref-badge').textContent = b.booking_reference;
    document.getElementById('view-booking-route').textContent = b.route_summary || `${b.pickup_location} → ${b.drop_location}`;
    document.getElementById('view-booking-service-badge').textContent = (b.service_type || 'CAB').toUpperCase();
    const cabType = getBookingCabType(b);
    const cabTypeBadgeEl = document.getElementById('view-booking-cab-type');
    if (cabTypeBadgeEl) {
      if (cabType) {
        cabTypeBadgeEl.textContent = cabType;
        cabTypeBadgeEl.style.display = 'inline-block';
      } else {
        cabTypeBadgeEl.style.display = 'none';
      }
    }
    document.getElementById('view-booking-trip-type').textContent = (b.trip_type || 'ONE-WAY').toUpperCase();
    document.getElementById('view-booking-status-badge').textContent = (b.status || 'CONFIRMED').toUpperCase();
    document.getElementById('view-booking-otp').textContent = b.start_otp || '----';

    const travelDate = b.departure_time ? new Date(b.departure_time).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : (b.created_at ? new Date(b.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Scheduled');
    document.getElementById('view-booking-date').textContent = travelDate;

    // Customer
    document.getElementById('view-booking-customer-name').textContent = b.customer_name;
    document.getElementById('view-booking-customer-phone').textContent = b.customer_phone;
    document.getElementById('view-booking-customer-phone').href = `tel:${b.customer_phone}`;
    document.getElementById('view-booking-customer-email').textContent = b.customer_email || 'Not provided';
    document.getElementById('view-booking-pickup').textContent = b.pickup_location;
    document.getElementById('view-booking-drop').textContent = b.drop_location;

    // Chauffeur box
    const driverBox = document.getElementById('view-booking-driver-content');
    if (b.driver) {
      driverBox.innerHTML = `
        <div style="font-size:13px; font-weight:700; color:#0f172a;">${b.driver.name}</div>
        <div style="font-size:12px; color:#15803d; margin-top:2px; font-weight:600;">
          ${b.driver.vehicle_model} • <span class="driver-plate-pill">${b.driver.vehicle_plate}</span>
        </div>
        <div style="font-size:11.5px; color:#475569; margin-top:2px;">
          Phone: <a href="tel:${b.driver.phone}" style="color:inherit; font-weight:600;">${b.driver.phone}</a> • ★${b.driver.rating || '4.90'}
        </div>
      `;
    } else {
      driverBox.innerHTML = `
        <div style="color:#d97706; font-size:12px; font-weight:600; padding:4px 0;">
          ⚠️ No chauffeur allotted yet.
        </div>
        <button type="button" class="btn btn-xs btn-outline-gold" style="margin-top:4px;" onclick="openAllotDriverFromView()">
          <span class="material-symbols-outlined" style="font-size:13px;">person_add</span>
          <span>Allot Chauffeur Now</span>
        </button>
      `;
    }

    // Financial breakdown
    document.getElementById('view-booking-base-fare').textContent = `₹${Number(b.base_fare || 0).toLocaleString('en-IN')}`;
    document.getElementById('view-booking-toll-fare').textContent = `₹${Number(b.toll_fare || 0).toLocaleString('en-IN')}`;
    document.getElementById('view-booking-addon-fare').textContent = `₹${Number(b.addon_fare || 0).toLocaleString('en-IN')}`;
    const discRow = document.getElementById('view-booking-discount-row');
    if (Number(b.discount_fare) > 0) {
      discRow.style.display = 'flex';
      document.getElementById('view-booking-discount-fare').textContent = `-₹${Number(b.discount_fare).toLocaleString('en-IN')}`;
    } else {
      discRow.style.display = 'none';
    }
    document.getElementById('view-booking-total-fare').textContent = `₹${Number(b.total_amount || 0).toLocaleString('en-IN')}`;
    document.getElementById('view-booking-txnid').textContent = `TXN-${(b.booking_reference || '').replace('ICW-', 'ESC')}`;

    const modal = document.getElementById('admin-view-booking-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Failed to view booking:', err);
    showToast('Failed to load booking details.', 'warning');
  }
};

// 3. EDIT BOOKING MODAL
window.openEditBookingModal = async function (bookingId) {
  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}`);
    const data = await res.json();
    if (!data.success || !data.booking) return;

    const b = data.booking;
    document.getElementById('edit-booking-id').value = b.id;
    document.getElementById('edit-booking-ref-badge').textContent = b.booking_reference;

    document.getElementById('edit-customer-name').value = b.customer_name || '';
    document.getElementById('edit-customer-phone').value = b.customer_phone || '';
    document.getElementById('edit-customer-email').value = b.customer_email || '';
    document.getElementById('edit-service-type').value = b.service_type || 'cab';
    const editCabTypeEl = document.getElementById('edit-cab-type');
    if (editCabTypeEl) {
      editCabTypeEl.value = getBookingCabType(b).toLowerCase();
    }
    document.getElementById('edit-trip-type').value = b.trip_type || 'oneway';
    document.getElementById('edit-route-summary').value = b.route_summary || '';
    document.getElementById('edit-pickup-location').value = b.pickup_location || '';
    document.getElementById('edit-drop-location').value = b.drop_location || '';

    document.getElementById('edit-base-fare').value = Math.round(b.base_fare || 0);
    document.getElementById('edit-toll-fare').value = Math.round(b.toll_fare || 0);
    document.getElementById('edit-addon-fare').value = Math.round(b.addon_fare || 0);
    document.getElementById('edit-discount-fare').value = Math.round(b.discount_fare || 0);
    recalcEditTotalFare();

    document.getElementById('edit-status').value = b.status || 'Confirmed';
    document.getElementById('edit-start-otp').value = b.start_otp || '';
    document.getElementById('edit-driver-id').value = b.assigned_driver_id || '';

    const modal = document.getElementById('admin-edit-booking-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Failed to open edit modal:', err);
    showToast('Failed to load booking for editing.', 'warning');
  }
};

window.openEditBookingFromView = function () {
  if (window.currentViewingBookingId) {
    closeModal('admin-view-booking-modal');
    openEditBookingModal(window.currentViewingBookingId);
  }
};

window.recalcEditTotalFare = function () {
  const base = Number(document.getElementById('edit-base-fare')?.value) || 0;
  const toll = Number(document.getElementById('edit-toll-fare')?.value) || 0;
  const addon = Number(document.getElementById('edit-addon-fare')?.value) || 0;
  const discount = Number(document.getElementById('edit-discount-fare')?.value) || 0;
  const total = Math.max(0, Math.round(base + toll + addon - discount));

  const totalElem = document.getElementById('edit-computed-total');
  if (totalElem) totalElem.textContent = `₹${total.toLocaleString('en-IN')}`;
};

window.submitEditBooking = async function (e) {
  if (e) e.preventDefault();
  const id = document.getElementById('edit-booking-id').value;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const payload = {
    customer_name: document.getElementById('edit-customer-name').value.trim(),
    customer_phone: document.getElementById('edit-customer-phone').value.trim(),
    customer_email: document.getElementById('edit-customer-email').value.trim() || null,
    service_type: document.getElementById('edit-service-type').value,
    cab_type: document.getElementById('edit-cab-type')?.value || null,
    trip_type: document.getElementById('edit-trip-type').value,
    route_summary: document.getElementById('edit-route-summary').value.trim(),
    pickup_location: document.getElementById('edit-pickup-location').value.trim(),
    drop_location: document.getElementById('edit-drop-location').value.trim(),
    base_fare: Number(document.getElementById('edit-base-fare').value) || 0,
    toll_fare: Number(document.getElementById('edit-toll-fare').value) || 0,
    addon_fare: Number(document.getElementById('edit-addon-fare').value) || 0,
    discount_fare: Number(document.getElementById('edit-discount-fare').value) || 0,
    status: document.getElementById('edit-status').value,
    start_otp: document.getElementById('edit-start-otp').value.trim(),
    assigned_driver_id: document.getElementById('edit-driver-id').value || null
  };

  try {
    const res = await fetch(`/api/admin/bookings/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (data.success) {
      showToast('Booking updated successfully!', 'success');
      closeModal('admin-edit-booking-modal');
      reloadAdminBookings();
    } else {
      showToast(data.message || 'Failed to update booking.', 'warning');
    }
  } catch (err) {
    console.error('Failed to submit booking update:', err);
    showToast('Network error while saving changes.', 'warning');
  }
};

// 4. ADD MANUAL BOOKING MODAL
window.openAddBookingModal = function () {
  const form = document.getElementById('admin-add-booking-form');
  if (form) form.reset();
  autoSuggestAddFares();
  recalcAddTotalFare();
  const modal = document.getElementById('admin-add-booking-modal');
  if (modal) modal.classList.add('active');
};

window.autoSuggestAddFares = function () {
  const from = document.getElementById('add-from-city')?.value || 'Pune';
  const to = document.getElementById('add-to-city')?.value || 'Goa';

  let base = 6500;
  let toll = 480;

  if ((from === 'Pune' && to === 'Mumbai') || (from === 'Mumbai' && to === 'Pune')) {
    base = 2800;
    toll = 320;
  } else if ((from === 'Mumbai' && to === 'Goa') || (from === 'Goa' && to === 'Mumbai')) {
    base = 8500;
    toll = 650;
  } else if (to === 'Shirdi' || from === 'Shirdi') {
    base = 3800;
    toll = 280;
  } else if (to === 'Nashik' || from === 'Nashik') {
    base = 3400;
    toll = 240;
  }

  const baseInput = document.getElementById('add-base-fare');
  const tollInput = document.getElementById('add-toll-fare');
  if (baseInput) baseInput.value = base;
  if (tollInput) tollInput.value = toll;

  const cabTypeSelect = document.getElementById('add-cab-type');
  if (cabTypeSelect) {
    const tripType = document.getElementById('add-trip-type')?.value || 'oneway';
    const preferred = resolvePreferredCarTypeForBooking({
      tripType,
      fromCity: from,
      toCity: to,
      allowUnknown: true
    });
    if (preferred && [...cabTypeSelect.options].some(o => o.value === preferred)) {
      cabTypeSelect.value = preferred;
    } else if (!preferred) {
      cabTypeSelect.value = '';
    }
  }

  recalcAddTotalFare();
};

window.recalcAddTotalFare = function () {
  const base = Number(document.getElementById('add-base-fare')?.value) || 0;
  const toll = Number(document.getElementById('add-toll-fare')?.value) || 0;
  const addon = Number(document.getElementById('add-addon-fare')?.value) || 0;
  const discount = Number(document.getElementById('add-discount-fare')?.value) || 0;
  const total = Math.max(0, Math.round(base + toll + addon - discount));

  const totalElem = document.getElementById('add-computed-total');
  if (totalElem) totalElem.textContent = `₹${total.toLocaleString('en-IN')}`;
};

window.submitAddBooking = async function (e) {
  if (e) e.preventDefault();
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const payload = {
    customer_name: document.getElementById('add-customer-name').value.trim(),
    customer_phone: document.getElementById('add-customer-phone').value.trim(),
    customer_email: document.getElementById('add-customer-email').value.trim() || null,
    service_type: document.getElementById('add-service-type').value,
    cab_type: document.getElementById('add-cab-type')?.value || null,
    trip_type: document.getElementById('add-trip-type').value,
    from_city: document.getElementById('add-from-city').value,
    to_city: document.getElementById('add-to-city').value,
    pickup_location: document.getElementById('add-pickup-location').value.trim(),
    drop_location: document.getElementById('add-drop-location').value.trim(),
    base_fare: Number(document.getElementById('add-base-fare').value) || 0,
    toll_fare: Number(document.getElementById('add-toll-fare').value) || 0,
    addon_fare: Number(document.getElementById('add-addon-fare').value) || 0,
    discount_fare: Number(document.getElementById('add-discount-fare').value) || 0,
    assigned_driver_id: document.getElementById('add-driver-id').value || null
  };

  try {
    const res = await fetch('/api/admin/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (data.success && data.booking) {
      showToast(`Booking ${data.booking.booking_reference} created!`, 'success');
      closeModal('admin-add-booking-modal');
      reloadAdminBookings();
    } else {
      showToast(data.message || 'Failed to create booking.', 'warning');
    }
  } catch (err) {
    console.error('Create booking error:', err);
    showToast('Network error while creating booking.', 'warning');
  }
};

// 5. PROFESSIONAL GST TAX INVOICE
function applyInvoiceTemplateToDom(inv) {
  const tpl = inv.template || {};
  const comp = inv.company || {};
  const paper = document.getElementById('invoice-printable-area');
  if (paper) {
    paper.setAttribute('data-invoice-style', tpl.style || window.PLATFORM_SETTINGS?.invoice_template_style || 'classic_gst');
    paper.style.setProperty('--inv-accent', tpl.accent_color || window.PLATFORM_SETTINGS?.invoice_accent_color || '#d49b4c');
  }

  const setTxt = (id, val) => {
    const el = document.getElementById(id);
    if (el && val !== undefined && val !== null) el.textContent = val;
  };

  setTxt('inv-brand-name', comp.brand_name || tpl.brand_name || 'INTERCITY WHEELS');
  setTxt('inv-company-legal', comp.name || 'IntercityWheels Mobility Services Pvt. Ltd.');
  setTxt('inv-company-address', comp.address ? `Regd. Office: ${comp.address}` : '');
  setTxt('inv-gstin', comp.gstin || '—');
  setTxt('inv-sac-code', comp.hsn_sac || '—');
  setTxt('inv-support-phone', comp.support_phone || '—');
  setTxt('inv-support-email', comp.support_email || '—');
  setTxt('inv-badge-title', tpl.title || 'TAX INVOICE');
  setTxt('inv-place-of-supply', tpl.place_of_supply || comp.place_of_supply || 'Maharashtra (27)');
  setTxt('inv-cgst-label', `CGST (${tpl.cgst_pct ?? 2.5}%):`);
  setTxt('inv-sgst-label', `SGST (${tpl.sgst_pct ?? 2.5}%):`);
  setTxt('inv-signatory-name', tpl.signatory_name || comp.name || 'IntercityWheels Mobility Services');
  setTxt('inv-signatory-label', tpl.signatory_label || 'Authorized Signatory');

  const stampBox = document.getElementById('inv-paid-stamp-box');
  if (stampBox) stampBox.style.display = (tpl.show_stamp === false) ? 'none' : 'inline-flex';
  const otpLine = document.getElementById('inv-otp-line');
  if (otpLine) otpLine.style.display = (tpl.show_otp === false) ? 'none' : '';

  const termsList = document.getElementById('inv-terms-list');
  if (termsList && Array.isArray(tpl.terms)) {
    termsList.innerHTML = tpl.terms.map(t => {
      const div = document.createElement('div');
      div.textContent = t;
      return div.outerHTML;
    }).join('');
  }
}

window.openInvoiceModal = async function (bookingId) {
  window.currentViewingBookingId = bookingId;
  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}/invoice`);
    const data = await res.json();
    if (!data.success || !data.invoice) return;

    const inv = data.invoice;
    applyInvoiceTemplateToDom(inv);
    document.getElementById('inv-doc-number').textContent = inv.invoice_number;
    document.getElementById('inv-doc-date').textContent = inv.invoice_date;
    document.getElementById('inv-booking-ref').textContent = data.booking.booking_reference;

    // Customer
    document.getElementById('inv-customer-name').textContent = inv.customer.name;
    document.getElementById('inv-customer-phone').textContent = inv.customer.phone;
    document.getElementById('inv-customer-email').textContent = inv.customer.email;
    document.getElementById('inv-pickup-address').textContent = inv.customer.pickup_location;
    document.getElementById('inv-drop-address').textContent = inv.customer.drop_location;

    // Trip & Driver
    document.getElementById('inv-service-type').textContent = `${inv.customer.service_type} (${inv.customer.trip_type})`;
    document.getElementById('inv-route').textContent = inv.customer.route;
    document.getElementById('inv-travel-date').textContent = inv.customer.travel_date;

    if (inv.driver) {
      document.getElementById('inv-driver-name').textContent = `${inv.driver.name} (Ph: ${inv.driver.phone})`;
      document.getElementById('inv-vehicle-details').textContent = `${inv.driver.vehicle_model} (${inv.driver.vehicle_plate})`;
    } else {
      document.getElementById('inv-driver-name').textContent = 'Chauffeur Dispatched On Demand';
      document.getElementById('inv-vehicle-details').textContent = 'Commercial Transport Vehicle';
    }

    // Charges Table
    document.getElementById('inv-row-base').textContent = `₹${Number(inv.charges.base_fare).toFixed(2)}`;
    document.getElementById('inv-row-toll').textContent = `₹${Number(inv.charges.toll_fare).toFixed(2)}`;

    const addonRow = document.getElementById('inv-addon-row');
    if (Number(inv.charges.addon_fare) > 0) {
      addonRow.style.display = '';
      document.getElementById('inv-row-addon').textContent = `₹${Number(inv.charges.addon_fare).toFixed(2)}`;
    } else {
      addonRow.style.display = 'none';
    }

    const discountRow = document.getElementById('inv-discount-row');
    if (Number(inv.charges.discount_fare) > 0) {
      discountRow.style.display = '';
      document.getElementById('inv-row-discount').textContent = `-₹${Number(inv.charges.discount_fare).toFixed(2)}`;
    } else {
      discountRow.style.display = 'none';
    }

    // Tax Totals
    document.getElementById('inv-subtotal').textContent = `₹${Number(inv.charges.taxable_amount).toFixed(2)}`;
    document.getElementById('inv-cgst').textContent = `₹${Number(inv.charges.cgst).toFixed(2)}`;
    document.getElementById('inv-sgst').textContent = `₹${Number(inv.charges.sgst).toFixed(2)}`;
    document.getElementById('inv-grand-total').textContent = `₹${Number(inv.charges.total_amount).toLocaleString('en-IN')}.00`;
    document.getElementById('inv-amount-in-words').textContent = `(${numberToWordsINR(inv.charges.total_amount)})`;

    // Payment details
    document.getElementById('inv-paid-stamp-text').textContent = inv.payment.status || 'PAID IN FULL';
    document.getElementById('inv-payment-mode-text').textContent = inv.payment.method || 'Online Escrow (Prepaid)';
    document.getElementById('inv-txn-ref').textContent = inv.payment.transaction_ref;
    document.getElementById('inv-start-otp').textContent = inv.payment.start_otp || '----';

    const modal = document.getElementById('admin-invoice-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Invoice load error:', err);
    showToast('Failed to generate tax invoice.', 'warning');
  }
};

window.openInvoiceFromView = function () {
  if (window.currentViewingBookingId) {
    closeModal('admin-view-booking-modal');
    openInvoiceModal(window.currentViewingBookingId);
  }
};

window.printAdminInvoice = function () {
  window.print();
};

/* ======================================================== */
/* DRIVER / CHAUFFEUR TRIP VERIFICATION & WALLET LOGIC       */
/* ======================================================== */
window.verifyDriverStartOtp = async function (bookingId) {
  const input = document.getElementById(`driver-otp-input-${bookingId}`);
  const otp = input ? input.value.trim() : '';

  if (!otp || otp.length !== 4) {
    showToast('Please enter the 4-digit ride start OTP provided by the passenger.', 'warning');
    if (input) input.focus();
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/driver/trips/${bookingId}/verify-otp`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ otp })
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'OTP verified! Trip has commenced.', 'success');
      const badge = document.getElementById(`driver-trip-status-badge-${bookingId}`);
      if (badge) {
        badge.className = 'admin-badge badge-info';
        badge.textContent = 'Started';
      }
      const actionsBox = document.getElementById(`driver-trip-actions-${bookingId}`);
      if (actionsBox) {
        actionsBox.innerHTML = `
            <div style="display:flex; align-items:center; gap:6px; color:#0284c7; font-size:12px; font-weight:700;">
                <span class="material-symbols-outlined" style="font-size:18px;">local_shipping</span>
                <span>Ride in progress. Drive safely.</span>
            </div>
            <button type="button" class="btn btn-sm btn-accent" onclick="completeDriverTrip(${bookingId})">
                <span class="material-symbols-outlined" style="font-size:15px;">flag</span>
                <span>Complete Trip & Settle Payout</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline" onclick="openInvoiceModal(${bookingId})">
                <span class="material-symbols-outlined" style="font-size:14px;">receipt</span>
                <span>View Invoice</span>
            </button>
          `;
      }
    } else {
      showToast(data.message || 'Invalid OTP. Please verify with passenger.', 'warning');
    }
  } catch (err) {
    console.error('Verify OTP error:', err);
    showToast('Network error while verifying OTP.', 'warning');
  }
};

window.completeDriverTrip = async function (bookingId) {
  if (!confirm('Confirm passenger drop-off and complete trip? Fare will be credited to your driver wallet.')) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/driver/trips/${bookingId}/complete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      }
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Trip completed! Payout credited to your wallet.', 'success');
      const badge = document.getElementById(`driver-trip-status-badge-${bookingId}`);
      if (badge) {
        badge.className = 'admin-badge badge-primary';
        badge.textContent = 'Completed';
      }
      const actionsBox = document.getElementById(`driver-trip-actions-${bookingId}`);
      if (actionsBox) {
        actionsBox.innerHTML = `
            <div style="color:#16a34a; font-size:12px; font-weight:700; display:flex; align-items:center; gap:4px;">
                <span class="material-symbols-outlined" style="font-size:16px;">verified</span>
                <span>Trip completed & payout credited.</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline" onclick="openInvoiceModal(${bookingId})">
                <span class="material-symbols-outlined" style="font-size:14px;">receipt</span>
                <span>View Invoice</span>
            </button>
          `;
      }
      if (data.booking && data.booking.total_amount) {
        const payout = Math.round(data.booking.total_amount * 0.9);
        const walletEl = document.getElementById('driver-header-wallet-bal');
        const walletPanelEl = document.getElementById('driver-wallet-bal-display');
        const currentRaw = (walletEl || walletPanelEl)?.textContent || '0';
        const curVal = parseInt(currentRaw.replace(/[^0-9]/g, ''), 10) || 0;
        const nextFormatted = `₹${(curVal + payout).toLocaleString('en-IN')}`;
        if (walletEl) walletEl.textContent = nextFormatted;
        if (walletPanelEl) walletPanelEl.textContent = nextFormatted;
      }
    } else {
      showToast(data.message || 'Failed to complete trip.', 'warning');
    }
  } catch (err) {
    console.error('Complete trip error:', err);
    showToast('Network error while completing trip.', 'warning');
  }
};

window.requestDriverPayout = async function (balance) {
  if (!balance || balance <= 0) {
    showToast('Insufficient wallet balance for withdrawal.', 'warning');
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch('/api/driver/wallet/withdraw', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ amount: balance })
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
    } else {
      showToast(data.message || 'Payout request failed.', 'warning');
    }
  } catch (err) {
    console.error('Payout request error:', err);
    showToast('Network error while requesting payout.', 'warning');
  }
};

window.setDriverTripFilter = function (filterKey, btn) {
  const key = filterKey || 'all';
  document.querySelectorAll('#driver-trips-status-filters [data-trip-filter]').forEach(function (el) {
    el.classList.toggle('active', el.dataset.tripFilter === key);
  });

  const rows = document.querySelectorAll('#driver-trips-table-body tr.driver-trip-row');
  let visible = 0;
  rows.forEach(function (row) {
    const bucket = row.dataset.tripBucket || '';
    const match = (key === 'all') || (bucket === key);
    row.style.display = match ? '' : 'none';
    if (match) visible += 1;
  });

  const emptyFilterRow = document.getElementById('driver-trips-filter-empty');
  if (emptyFilterRow) {
    emptyFilterRow.style.display = (rows.length > 0 && visible === 0) ? '' : 'none';
  }
};

window.setDriverTripView = function (mode) {
  const isCards = mode === 'cards';
  document.querySelectorAll('#driver-trips-status-filters [data-trip-view]').forEach(function (el) {
    el.classList.toggle('active', el.dataset.tripView === (isCards ? 'cards' : 'table'));
  });
  const wrap = document.getElementById('driver-trips-view-wrap');
  if (wrap) {
    wrap.classList.toggle('is-cards-mode', isCards);
  }
};

/* ======================================================== */
/* ADMIN: REGISTERED USERS & CHAUFFEUR DIRECTORY MODULE     */
/* ======================================================== */
window.currentViewingUserId = null;

// Filter users table by search query and role
window.handleUserFilterChange = function () {
  const query = (document.getElementById('admin-user-search-input')?.value || '').toLowerCase().trim();
  const roleFilter = document.getElementById('admin-user-filter-role')?.value || 'all';

  const rows = document.querySelectorAll('#admin-users-table-tbody tr[data-search]');
  let visibleCount = 0;

  rows.forEach(row => {
    const searchData = row.dataset.search || '';
    const rowRole = row.dataset.role || '';

    const matchQuery = !query || searchData.includes(query);
    const matchRole = (roleFilter === 'all') || (rowRole.toLowerCase() === roleFilter.toLowerCase());

    if (matchQuery && matchRole) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countBadge = document.getElementById('admin-users-filter-count');
  if (countBadge) {
    countBadge.textContent = `${visibleCount} Accounts`;
  }

  const emptyRow = document.getElementById('admin-users-empty-row');
  if (emptyRow) {
    emptyRow.style.display = visibleCount === 0 ? '' : 'none';
  }

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-users', true);
  }
};

window.handleAdminFleetFilterChange = function () {
  const rawQuery = (document.getElementById('admin-fleet-search-input')?.value || '').toLowerCase().trim();
  const compactQuery = rawQuery.replace(/\s+/g, '');
  const catFilter = (document.getElementById('admin-fleet-filter-category')?.value || 'all').toLowerCase();

  const rows = document.querySelectorAll('#admin-fleet-directory-tbody tr[data-search]');
  let visibleCount = 0;

  rows.forEach(row => {
    const searchData = row.dataset.search || '';
    const rowCat = (row.dataset.category || '').toLowerCase();

    const matchQuery = !rawQuery
      || searchData.includes(rawQuery)
      || (compactQuery !== '' && searchData.includes(compactQuery));
    const matchCat = (catFilter === 'all') || (rowCat === catFilter);

    if (matchQuery && matchCat) {
      row.style.display = '';
      visibleCount += 1;
    } else {
      row.style.display = 'none';
    }
  });

  const countEl = document.getElementById('admin-fleet-filter-count');
  if (countEl) countEl.textContent = `${visibleCount} Chauffeurs`;

  const emptyRow = document.getElementById('admin-fleet-empty-row');
  if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-fleet', true);
  }
};

// Reload all users via API
window.reloadAdminUsers = async function () {
  try {
    const res = await fetch('/api/admin/users');
    const data = await res.json();
    if (data.success && Array.isArray(data.users)) {
      renderAdminUsersTable(data.users);
      showToast(`Updated ${data.users.length} registered accounts.`, 'info');
    }
  } catch (err) {
    console.error('Failed to reload users:', err);
    showToast('Failed to refresh users directory.', 'warning');
  }
};

// Render users table rows
function renderAdminUsersTable(users) {
  const tbody = document.getElementById('admin-users-table-tbody');
  if (!tbody) return;

  const countBadge = document.getElementById('admin-badge-users-count');
  if (countBadge) countBadge.textContent = users.length;
  const filterCount = document.getElementById('admin-users-filter-count');
  if (filterCount) filterCount.textContent = `${users.length} Accounts`;

  if (users.length === 0) {
    tbody.innerHTML = `
      <tr id="admin-users-empty-row">
        <td colspan="7" style="text-align:center; padding:35px 20px; color:#94a3b8;">
          <span class="material-symbols-outlined" style="font-size:32px; color:#cbd5e1; display:block; margin-bottom:6px;">group</span>
          <span>No registered users found. Click "New User / Driver" to register an account.</span>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = users.map(u => {
    const roleClass = u.role === 'admin' ? 'badge-gold' : (u.role === 'driver' ? 'badge-success' : 'badge-primary');
    const driverHtml = u.driver ? `
      <div class="driver-allotted-card">
        <span class="cell-primary-text">${u.driver.vehicle_model}</span>
        <div style="display:flex; gap:4px; align-items:center; margin-top:2px;">
          <span class="driver-plate-pill">${u.driver.vehicle_plate}</span>
          <span style="color:#f59e0b; font-weight:700; font-size:10px;">★${u.driver.rating || '4.90'}</span>
          <span style="color:#15803d; font-weight:700; font-size:10px;">₹${Number(u.driver.wallet_balance || 0).toLocaleString('en-IN')}</span>
        </div>
      </div>
    ` : (u.role === 'driver' ? '<span style="color:#d97706; font-size:11px;">Vehicle Pending</span>' : '<span style="color:#94a3b8; font-size:11px;">Standard Passenger</span>');

    const currentUserId = window.CURRENT_USER ? Number(window.CURRENT_USER.id) : null;
    const isSelfAdmin = currentUserId === Number(u.id) && u.role === 'admin';
    const searchStr = `${u.name} ${u.email} ${u.phone} ${u.role} ${u.driver ? u.driver.vehicle_plate + ' ' + u.driver.vehicle_model : ''}`.toLowerCase();

    return `
      <tr id="user-row-${u.id}" data-id="${u.id}" data-role="${u.role}" data-search="${auctionEscape(searchStr)}">
        <td>
          <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:20px; width:30px; height:30px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
              ${renderAvatarHtml(u.avatar, u.role === 'admin' ? '🛡️' : (u.role === 'driver' ? '👨🏽‍✈️' : '👤'), u.name || 'User')}
            </span>
            <div>
              <div class="cell-primary-text">${auctionEscape(u.name)}</div>
              <div class="cell-subtext">ID: #USR-${String(u.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td>
          <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <span class="admin-badge ${roleClass}">
              ${auctionEscape(String(u.role || '').toUpperCase())}
            </span>
            <select class="form-control" style="width:auto; min-width:110px; height:28px; padding:2px 6px; font-size:11px; font-weight:600;"
              aria-label="Change access level for ${auctionEscape(u.name)}"
              data-prev-role="${auctionEscape(u.role)}"
              onchange="changeUserRole(${Number(u.id)}, this.value, this)"
              ${isSelfAdmin ? 'disabled title="Cannot change your own active admin role"' : ''}>
              <option value="customer" ${u.role === 'customer' ? 'selected' : ''}>Customer</option>
              <option value="driver" ${u.role === 'driver' ? 'selected' : ''}>Driver</option>
              <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
            </select>
          </div>
        </td>
        <td>
          <div class="cell-primary-text" style="font-size:11.5px;">${u.email}</div>
          <div class="cell-subtext">
            <a href="tel:${u.phone}" style="color:#64748b; text-decoration:none;">${u.phone}</a>
          </div>
        </td>
        <td>
          ${driverHtml}
        </td>
        <td>
          <div class="cell-primary-text text-tabular">${u.trips_count || 0} Trips</div>
          <div class="cell-subtext">${u.role === 'driver' ? 'Completed Rides' : 'Bookings Logged'}</div>
        </td>
        <td>
          <div class="cell-subtext">${u.created_at || 'Recent'}</div>
        </td>
        <td style="text-align:right;">
          <div class="admin-actions-compact">
            <button type="button" class="btn-action-icon btn-action-view" onclick="openViewUserModal(${u.id})" title="View Profile & Trips">
              <span class="material-symbols-outlined">visibility</span>
            </button>
            <button type="button" class="btn-action-icon btn-action-edit" onclick="openEditUserModal(${u.id})" title="Edit Account">
              <span class="material-symbols-outlined">edit</span>
            </button>
            <button type="button" class="btn-action-icon btn-action-invoice" onclick="quickDemoLogin('${u.role}')" title="Switch to this Role Perspective">
              <span class="material-symbols-outlined">login</span>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// 1b. QUICK ROLE / PERMISSION SWITCHER
window.changeUserRole = async function (userId, newRole, selectEl) {
  const prevRole = selectEl ? (selectEl.dataset.prevRole || '') : '';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  if (selectEl) selectEl.disabled = true;

  try {
    const res = await fetch(`/api/admin/users/${userId}/role`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ role: newRole })
    });

    const data = await res.json().catch(() => ({}));
    if (res.ok && data.success) {
      if (selectEl) selectEl.dataset.prevRole = newRole;
      showToast(data.message || 'Access level updated.', 'success');
      reloadAdminUsers();
    } else {
      if (selectEl && prevRole) selectEl.value = prevRole;
      showToast(data.message || 'Could not update access level.', 'warning');
    }
  } catch (err) {
    console.error('Change user role error:', err);
    if (selectEl && prevRole) selectEl.value = prevRole;
    showToast('Network error while updating access level.', 'warning');
  } finally {
    if (selectEl) {
      const currentUserId = window.CURRENT_USER ? Number(window.CURRENT_USER.id) : null;
      const isSelfAdmin = currentUserId === Number(userId) && (selectEl.value === 'admin');
      selectEl.disabled = isSelfAdmin;
    }
  }
};

// 1. ADD USER / DRIVER MODAL
window.openAddUserModal = function () {
  const form = document.getElementById('admin-add-user-form');
  if (form) form.reset();
  toggleAddDriverFields('customer');
  const modal = document.getElementById('admin-add-user-modal');
  if (modal) modal.classList.add('active');
};

window.toggleAddDriverFields = function (role) {
  const group = document.getElementById('add-user-driver-fleet-group');
  if (group) {
    group.style.display = (role === 'driver') ? 'block' : 'none';
  }
};

window.submitAddUser = async function (e) {
  if (e) e.preventDefault();
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const payload = {
    name: document.getElementById('add-user-name').value.trim(),
    email: document.getElementById('add-user-email').value.trim(),
    phone: document.getElementById('add-user-phone').value.trim(),
    role: document.getElementById('add-user-role').value,
    password: document.getElementById('add-user-password').value || 'password123',
    vehicle_model: document.getElementById('add-driver-vehicle-model')?.value.trim() || null,
    vehicle_plate: document.getElementById('add-driver-vehicle-plate')?.value.trim() || null,
    fleet_category: document.getElementById('add-driver-category')?.value || null
  };

  try {
    const res = await fetch('/api/admin/users', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-add-user-modal');
      reloadAdminUsers();
    } else {
      showToast(data.message || 'Failed to register account.', 'warning');
    }
  } catch (err) {
    console.error('Add user error:', err);
    showToast('Network error while registering account.', 'warning');
  }
};

// 2. EDIT USER MODAL
window.openEditUserModal = async function (userId) {
  window.currentViewingUserId = userId;
  try {
    const res = await fetch(`/api/admin/users/${userId}`);
    const data = await res.json();
    if (!data.success || !data.user) return;

    const u = data.user;
    document.getElementById('edit-user-id').value = u.id;
    document.getElementById('edit-user-name').value = u.name;
    document.getElementById('edit-user-email').value = u.email;
    document.getElementById('edit-user-phone').value = u.phone;
    document.getElementById('edit-user-role').value = u.role;
    document.getElementById('edit-user-password').value = '';

    const modal = document.getElementById('admin-edit-user-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Failed to open edit user modal:', err);
    showToast('Failed to load user details.', 'warning');
  }
};

window.submitEditUser = async function (e) {
  if (e) e.preventDefault();
  const userId = document.getElementById('edit-user-id').value;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const payload = {
    name: document.getElementById('edit-user-name').value.trim(),
    email: document.getElementById('edit-user-email').value.trim(),
    phone: document.getElementById('edit-user-phone').value.trim(),
    role: document.getElementById('edit-user-role').value
  };

  const pass = document.getElementById('edit-user-password').value;
  if (pass) payload.password = pass;

  try {
    const res = await fetch(`/api/admin/users/${userId}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-edit-user-modal');
      reloadAdminUsers();
    } else {
      showToast(data.message || 'Failed to update account.', 'warning');
    }
  } catch (err) {
    console.error('Edit user error:', err);
    showToast('Network error while updating account.', 'warning');
  }
};

window.deleteUserAccount = async function () {
  const userId = document.getElementById('edit-user-id').value;
  if (!confirm('Are you sure you want to permanently delete this account?')) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/admin/users/${userId}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      }
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'info');
      closeModal('admin-edit-user-modal');
      reloadAdminUsers();
    } else {
      showToast(data.message || 'Failed to delete account.', 'warning');
    }
  } catch (err) {
    console.error('Delete user error:', err);
    showToast('Network error while removing account.', 'warning');
  }
};

// 3. VIEW USER PROFILE MODAL
window.openViewUserModal = async function (userId) {
  window.currentViewingUserId = userId;
  try {
    const res = await fetch(`/api/admin/users/${userId}`);
    const data = await res.json();
    if (!data.success || !data.user) return;

    const u = data.user;
    setAvatarContent(
      document.getElementById('view-user-avatar'),
      u.avatar,
      u.role === 'admin' ? '🛡️' : (u.role === 'driver' ? '👨🏽‍✈️' : '👤'),
      u.name || 'User'
    );
    document.getElementById('view-user-name').textContent = u.name;
    document.getElementById('view-user-role-badge').textContent = u.role.toUpperCase();
    document.getElementById('view-user-role-badge').className = `admin-badge ${u.role === 'admin' ? 'badge-gold' : (u.role === 'driver' ? 'badge-success' : 'badge-primary')}`;
    document.getElementById('view-user-email').textContent = u.email;
    document.getElementById('view-user-phone').textContent = u.phone;
    document.getElementById('view-user-phone').href = `tel:${u.phone}`;

    // Driver card
    const driverCard = document.getElementById('view-user-driver-card');
    if (data.driver) {
      driverCard.style.display = 'block';
      document.getElementById('view-user-driver-vehicle').textContent = data.driver.vehicle_model;
      document.getElementById('view-user-driver-plate').textContent = data.driver.vehicle_plate;
      document.getElementById('view-user-driver-wallet').textContent = `₹${Number(data.driver.wallet_balance || 0).toLocaleString('en-IN')}`;
      document.getElementById('view-user-driver-rating').textContent = `★${data.driver.rating || '4.90'}`;
    } else {
      driverCard.style.display = 'none';
    }

    // Recent activity
    const activityBox = document.getElementById('view-user-activity-content');
    if (Array.isArray(data.bookings) && data.bookings.length > 0) {
      activityBox.innerHTML = data.bookings.map(b => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f1f5f9;">
          <div>
            <strong style="color:var(--primary); font-family:monospace;">${b.booking_reference}</strong> &bull; ${b.route_summary}
          </div>
          <div>
            <span class="admin-badge badge-secondary" style="font-size:9.5px;">${b.status}</span> &bull; <strong>₹${Number(b.total_amount).toLocaleString('en-IN')}</strong>
          </div>
        </div>
      `).join('');
    } else {
      activityBox.innerHTML = `<em>No recent trip bookings logged yet.</em>`;
    }

    const modal = document.getElementById('admin-view-user-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Failed to view user details:', err);
    showToast('Failed to load user profile.', 'warning');
  }
};

window.openEditUserFromView = function () {
  if (window.currentViewingUserId) {
    closeModal('admin-view-user-modal');
    openEditUserModal(window.currentViewingUserId);
  }
};

// ========================================================
// 1. SERVICE LOCATIONS MANAGEMENT (Admin)
// ========================================================
const getAdminCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

window.openAddLocationModal = function () {
  document.getElementById('admin-location-modal-title').textContent = 'Add Service Location';
  document.getElementById('admin-loc-id').value = '';
  document.getElementById('admin-loc-name').value = '';
  document.getElementById('admin-loc-state').value = '';
  document.getElementById('admin-loc-lat').value = '18.5204';
  document.getElementById('admin-loc-lng').value = '73.8567';
  document.getElementById('admin-loc-airport').value = '';
  document.getElementById('admin-loc-has-airport').checked = true;
  document.getElementById('admin-loc-active').checked = true;

  const modal = document.getElementById('admin-location-modal');
  if (modal) modal.classList.add('active');
};

window.openEditLocationModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/locations/${id}`);
    const data = await res.json();
    if (!data.success || !data.location) return;

    const loc = data.location;
    document.getElementById('admin-location-modal-title').textContent = `Edit Location: ${loc.name}`;
    document.getElementById('admin-loc-id').value = loc.id;
    document.getElementById('admin-loc-name').value = loc.name;
    document.getElementById('admin-loc-state').value = loc.state;
    document.getElementById('admin-loc-lat').value = loc.latitude;
    document.getElementById('admin-loc-lng').value = loc.longitude;
    document.getElementById('admin-loc-airport').value = loc.airport_code || '';
    document.getElementById('admin-loc-has-airport').checked = Boolean(loc.has_airport_service);
    document.getElementById('admin-loc-active').checked = Boolean(loc.is_active);

    const modal = document.getElementById('admin-location-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Fetch location error:', err);
    showToast('Failed to load location details.', 'warning');
  }
};

window.saveAdminLocation = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-loc-id').value;
  const isEdit = Boolean(id);

  const payload = {
    name: document.getElementById('admin-loc-name').value.trim(),
    state: document.getElementById('admin-loc-state').value.trim(),
    latitude: parseFloat(document.getElementById('admin-loc-lat').value),
    longitude: parseFloat(document.getElementById('admin-loc-lng').value),
    airport_code: document.getElementById('admin-loc-airport').value.trim().toUpperCase() || null,
    has_airport_service: document.getElementById('admin-loc-has-airport').checked,
    is_active: document.getElementById('admin-loc-active').checked,
  };

  const url = isEdit ? `/api/admin/locations/${id}` : '/api/admin/locations';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-location-modal');
      const loc = data.location;
      if (loc) {
        if (isEdit) {
          const row = document.getElementById(`admin-loc-row-${loc.id}`);
          if (row) {
            row.dataset.search = `${loc.name} ${loc.state} ${loc.airport_code || ''}`.toLowerCase();
            row.cells[0].innerHTML = `<strong>${loc.name}</strong>`;
            row.cells[1].textContent = loc.state;
            row.cells[2].textContent = `${loc.latitude}, ${loc.longitude}`;
            row.cells[3].innerHTML = loc.airport_code ? `<span class="admin-badge badge-primary">${loc.airport_code}</span>` : `<span style="color:#94a3b8; font-size:11px;">Standard</span>`;
            row.cells[4].innerHTML = loc.is_active ? `<span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleLocationStatus(${loc.id})" id="loc-status-badge-${loc.id}" title="Click to toggle">Active</span>` : `<span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleLocationStatus(${loc.id})" id="loc-status-badge-${loc.id}" title="Click to toggle">Inactive</span>`;
          }
        } else {
          const tbody = document.getElementById('admin-locations-tbody');
          if (tbody) {
            const tr = document.createElement('tr');
            tr.id = `admin-loc-row-${loc.id}`;
            tr.dataset.search = `${loc.name} ${loc.state} ${loc.airport_code || ''}`.toLowerCase();
            tr.innerHTML = `
                <td><strong>${loc.name}</strong></td>
                <td>${loc.state}</td>
                <td style="font-family:monospace; font-size:11.5px; color:#475569;">${loc.latitude}, ${loc.longitude}</td>
                <td>${loc.airport_code ? `<span class="admin-badge badge-primary">${loc.airport_code}</span>` : `<span style="color:#94a3b8; font-size:11px;">Standard</span>`}</td>
                <td><span class="admin-badge ${loc.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleLocationStatus(${loc.id})" id="loc-status-badge-${loc.id}" title="Click to toggle">${loc.is_active ? 'Active' : 'Inactive'}</span></td>
                <td style="text-align:right;">
                  <div class="admin-action-btn-group" style="justify-content:flex-end;">
                    <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditLocationModal(${loc.id})" title="Edit Location">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteLocation(${loc.id})" title="Delete Location">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              `;
            tbody.prepend(tr);
          }
        }

        const totalLocs = document.querySelectorAll('#admin-locations-tbody tr[id^="admin-loc-row-"]').length;
        const locBadge = document.getElementById('admin-badge-locations-count');
        if (locBadge) locBadge.textContent = totalLocs;

        // Sync portal dropdowns and memory with updated/new locations
        await window.syncAllLocationDropdowns();
      }
    } else {
      showToast(data.message || 'Validation error while saving location.', 'danger');
    }
  } catch (err) {
    console.error('Save location error:', err);
    showToast('Network error while saving location.', 'warning');
  }
};

window.toggleLocationStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/locations/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      const badge = document.getElementById(`loc-status-badge-${id}`) || document.querySelector(`#admin-loc-row-${id} td:nth-child(5) .admin-badge`);
      if (badge && data.location) {
        const isActive = data.location.is_active;
        badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
        badge.textContent = isActive ? 'Active' : 'Inactive';
      }
      await window.syncAllLocationDropdowns();
    }
  } catch (err) {
    showToast('Failed to toggle location status.', 'warning');
  }
};

window.deleteLocation = async function (id) {
  if (!confirm('Are you sure you want to delete this service location?')) return;
  try {
    const res = await fetch(`/api/admin/locations/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-loc-row-${id}`);
      if (row) row.remove();
      const totalLocs = document.querySelectorAll('#admin-locations-tbody tr[id^="admin-loc-row-"]').length;
      const locBadge = document.getElementById('admin-badge-locations-count');
      if (locBadge) locBadge.textContent = totalLocs;
      await window.syncAllLocationDropdowns();
    }
  } catch (err) {
    showToast('Failed to delete location.', 'warning');
  }
};

function escapeDiscoveredHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function updateDiscoveredPendingBadge(items) {
  const pendingBadge = document.getElementById('admin-badge-discovered-pending');
  if (!pendingBadge) return;
  const count = Array.isArray(items)
    ? items.filter(item => item.approval_status === 'pending').length
    : document.querySelectorAll('#admin-discovered-cities-tbody .admin-badge[data-status="pending"]').length;
  pendingBadge.textContent = count;
  pendingBadge.style.display = count > 0 ? 'inline-block' : 'none';
}

function renderDiscoveredCitiesTable(items) {
  const tbody = document.getElementById('admin-discovered-cities-tbody');
  if (!tbody) return;

  if (!Array.isArray(items) || items.length === 0) {
    tbody.innerHTML = '<tr id="admin-discovered-empty-row"><td colspan="6" style="text-align:center; padding:18px; color:#94a3b8;">No OpenStreetMap or out-of-region cities cached yet.</td></tr>';
    updateDiscoveredPendingBadge([]);
    return;
  }

  tbody.innerHTML = items.map(disc => {
    const status = disc.approval_status || 'cached';
    let badgeStyle = 'background:#e0f2fe; color:#0369a1;';
    let statusLabel = disc.is_beyond_region ? 'Beyond Region · Cached' : 'OSM Cached';
    if (status === 'pending') {
      badgeStyle = 'background:#fef3c7; color:#b45309;';
      statusLabel = 'Beyond Region · Pending Approval';
    } else if (status === 'approved') {
      badgeStyle = 'background:#dcfce7; color:#15803d;';
      statusLabel = 'Approved · In City List';
    } else if (status === 'dismissed') {
      badgeStyle = 'background:#f1f5f9; color:#64748b;';
      statusLabel = 'Dismissed';
    }

    const latStr = Number(disc.latitude || 0).toFixed(4);
    const lngStr = Number(disc.longitude || 0).toFixed(4);
    const hitsNum = Number(disc.hits || 0);
    const refHtml = disc.last_booking_reference
      ? `<span style="font-weight:600; color:#0f172a;">${escapeDiscoveredHtml(disc.last_booking_reference)}</span> · `
      : '';
    const subDisplay = disc.display_name && disc.display_name !== disc.name
      ? `<div style="font-size:11px; color:#64748b; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeDiscoveredHtml(disc.display_name)}">${escapeDiscoveredHtml(disc.display_name)}</div>`
      : '';
    const actionsHtml = status === 'approved'
      ? '<span style="font-size:11.5px; color:#15803d; font-weight:600;">Added to City List</span>'
      : `<div class="admin-action-btn-group" style="justify-content:flex-end; gap:6px;">
          <button type="button" class="btn btn-sm btn-primary" onclick="approveDiscoveredLocation(${disc.id}, this)" title="Approve & add to active Service Locations">
            <span class="material-symbols-outlined" style="font-size:15px;">add_location_alt</span>
            <span>Approve &amp; Add</span>
          </button>
          ${status === 'pending'
            ? `<button type="button" class="btn btn-sm btn-outline" onclick="dismissDiscoveredLocation(${disc.id}, this)" title="Dismiss out-of-region notification"><span>Dismiss</span></button>`
            : ''}
        </div>`;

    return `
      <tr id="admin-discovered-row-${disc.id}">
        <td><strong>${escapeDiscoveredHtml(disc.name)}</strong>${subDisplay}</td>
        <td>${escapeDiscoveredHtml(disc.state || '—')}</td>
        <td style="font-family:monospace; font-size:11.5px; color:#475569;">${latStr}, ${lngStr}</td>
        <td><span class="admin-badge" id="discovered-status-badge-${disc.id}" data-status="${escapeDiscoveredHtml(status)}" style="${badgeStyle}">${statusLabel}</span></td>
        <td style="font-size:12px; color:#475569;">${refHtml}<span>${hitsNum} ${hitsNum === 1 ? 'hit' : 'hits'}</span></td>
        <td style="text-align:right;">${actionsHtml}</td>
      </tr>
    `;
  }).join('');

  updateDiscoveredPendingBadge(items);
}

window.refreshDiscoveredLocations = async function () {
  try {
    const res = await fetch('/api/admin/discovered-locations', {
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) return;
    const data = await res.json();
    if (data && data.success) {
      renderDiscoveredCitiesTable(data.discovered_locations || []);
    }
  } catch (err) {
    console.warn('Failed to refresh discovered cities:', err);
  }
};

window.approveDiscoveredLocation = async function (id, btn) {
  if (btn) btn.disabled = true;
  try {
    const res = await fetch(`/api/admin/discovered-locations/${id}/approve`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({ is_active: true }),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      showToast(data?.message || 'Could not approve city.', 'danger');
      if (btn) btn.disabled = false;
      return;
    }

    showToast(data.message || 'City approved and added to active city list.', 'success');

    const loc = data.location;
    if (loc && !document.getElementById(`admin-loc-row-${loc.id}`)) {
      const tbody = document.getElementById('admin-locations-tbody');
      if (tbody) {
        const emptyRow = tbody.querySelector('td[colspan="6"]')?.closest('tr');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.id = `admin-loc-row-${loc.id}`;
        tr.dataset.search = `${loc.name} ${loc.state || ''} ${loc.airport_code || ''}`.toLowerCase();
        tr.innerHTML = `
          <td><strong>${escapeDiscoveredHtml(loc.name)}</strong></td>
          <td>${escapeDiscoveredHtml(loc.state || 'India')}</td>
          <td style="font-family:monospace; font-size:11.5px; color:#475569;">${loc.latitude}, ${loc.longitude}</td>
          <td>${loc.airport_code ? `<span class="admin-badge badge-primary">${escapeDiscoveredHtml(loc.airport_code)}</span>` : `<span style="color:#94a3b8; font-size:11px;">Standard</span>`}</td>
          <td><span class="admin-badge ${loc.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleLocationStatus(${loc.id})" id="loc-status-badge-${loc.id}" title="Click to toggle">${loc.is_active ? 'Active' : 'Inactive'}</span></td>
          <td style="text-align:right;">
            <div class="admin-action-btn-group" style="justify-content:flex-end;">
              <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditLocationModal(${loc.id})" title="Edit Location"><span class="material-symbols-outlined">edit</span></button>
              <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteLocation(${loc.id})" title="Delete Location"><span class="material-symbols-outlined">delete</span></button>
            </div>
          </td>
        `;
        tbody.prepend(tr);
      }
    }

    const totalLocs = document.querySelectorAll('#admin-locations-tbody tr[id^="admin-loc-row-"]').length;
    const locBadge = document.getElementById('admin-badge-locations-count');
    if (locBadge) locBadge.textContent = totalLocs;

    locationSearchCache.clear();
    await window.syncAllLocationDropdowns();
    await window.refreshDiscoveredLocations();
  } catch (err) {
    console.error('Approve discovered city error:', err);
    showToast('Network error while approving city.', 'warning');
    if (btn) btn.disabled = false;
  }
};

window.dismissDiscoveredLocation = async function (id, btn) {
  if (btn) btn.disabled = true;
  try {
    const res = await fetch(`/api/admin/discovered-locations/${id}/dismiss`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      showToast(data?.message || 'Could not dismiss city.', 'danger');
      if (btn) btn.disabled = false;
      return;
    }
    showToast(data.message || 'Out-of-region city alert dismissed.', 'info');
    await window.refreshDiscoveredLocations();
  } catch (err) {
    console.error('Dismiss discovered city error:', err);
    showToast('Network error while dismissing city.', 'warning');
    if (btn) btn.disabled = false;
  }
};

// ========================================================
// 1B. OUTSTATION CAB ROUTES MANAGEMENT (Admin)
// ========================================================
window.setCabRouteCalcMode = function (mode, labelText) {
  const modeInput = document.getElementById('admin-cab-route-calc-mode');
  const statusEl = document.getElementById('admin-cab-route-calc-status');
  const apiBtn = document.getElementById('admin-cab-route-mode-api-btn');
  const manualBtn = document.getElementById('admin-cab-route-mode-manual-btn');

  if (modeInput) modeInput.value = mode;
  if (apiBtn && manualBtn) {
    if (mode === 'api') {
      apiBtn.classList.remove('btn-secondary');
      apiBtn.classList.add('btn-primary');
      manualBtn.classList.remove('btn-primary');
      manualBtn.classList.add('btn-secondary');
    } else {
      manualBtn.classList.remove('btn-secondary');
      manualBtn.classList.add('btn-primary');
      apiBtn.classList.remove('btn-primary');
      apiBtn.classList.add('btn-secondary');
    }
  }
  if (statusEl) {
    if (labelText) {
      statusEl.textContent = labelText;
    } else {
      statusEl.textContent = mode === 'api' ? 'Mode: Calculated via API' : 'Mode: Manual Entry';
    }
    statusEl.style.color = mode === 'api' ? '#0f766e' : '#64748b';
  }
};

window.onCabRouteEndpointInput = function () {
  ['admin-cab-route-from-lat', 'admin-cab-route-from-lng', 'admin-cab-route-to-lat', 'admin-cab-route-to-lng'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
};

window.onCabRouteManualMetricEdit = function () {
  const modeInput = document.getElementById('admin-cab-route-calc-mode');
  if (modeInput && modeInput.value === 'api') {
    window.setCabRouteCalcMode('manual', 'Mode: Manual Override (Edited)');
  }
};

window.calculateAdminCabRouteDistance = async function () {
  const fromCity = document.getElementById('admin-cab-route-from')?.value.trim() || '';
  const toCity = document.getElementById('admin-cab-route-to')?.value.trim() || '';
  if (!fromCity || !toCity) {
    showToast('Enter both From City and To City before calculating KM via API.', 'warning');
    return;
  }

  const btn = document.getElementById('admin-cab-route-mode-api-btn');
  const statusEl = document.getElementById('admin-cab-route-calc-status');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">hourglass_top</span><span>Calculating...</span>';
  }
  if (statusEl) {
    statusEl.textContent = 'Querying route API...';
    statusEl.style.color = '#0284c7';
  }

  try {
    const res = await fetch('/api/admin/cab-routes/calculate-distance', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({
        from_city: fromCity,
        to_city: toCity,
      }),
    });
    const payload = await res.json();
    if (!res.ok || !payload.success || !payload.data) {
      window.setCabRouteCalcMode('manual', 'API unavailable — Enter KM manually');
      showToast(payload.message || 'Could not resolve distance via API. Enter KM manually.', 'warning');
      return;
    }

    const d = payload.data;
    document.getElementById('admin-cab-route-dist').value = d.distance_km;
    document.getElementById('admin-cab-route-time').value = d.estimated_time;
    document.getElementById('admin-cab-route-toll').value = Math.round(Number(d.base_toll || 0));

    if (d.from_lat != null) document.getElementById('admin-cab-route-from-lat').value = d.from_lat;
    if (d.from_lng != null) document.getElementById('admin-cab-route-from-lng').value = d.from_lng;
    if (d.to_lat != null) document.getElementById('admin-cab-route-to-lat').value = d.to_lat;
    if (d.to_lng != null) document.getElementById('admin-cab-route-to-lng').value = d.to_lng;

    const sourceLabel = String(d.source || '').includes('osrm_route')
      ? 'OSRM Road API'
      : (String(d.source || '').includes('osm_geocode') ? 'OSM Geocode + Estimate' : 'Location Matrix API');
    window.setCabRouteCalcMode('api', `Calculated via ${sourceLabel} (${d.distance_km} KM)`);
    showToast(`Distance calculated via ${sourceLabel}: ${d.distance_km} KM (${d.estimated_time})`, 'success');
  } catch (err) {
    console.error('Calculate cab route distance error:', err);
    window.setCabRouteCalcMode('manual', 'API error — Enter KM manually');
    showToast('Failed to reach distance API. You can enter KM manually.', 'warning');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
};

window.openAddCabRouteModal = function () {
  document.getElementById('admin-cab-route-modal-title').textContent = 'Add Outstation Cab Route';
  document.getElementById('admin-cab-route-id').value = '';
  document.getElementById('admin-cab-route-from').value = '';
  document.getElementById('admin-cab-route-to').value = '';
  ['admin-cab-route-from-lat', 'admin-cab-route-from-lng', 'admin-cab-route-to-lat', 'admin-cab-route-to-lng'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('admin-cab-route-dist').value = '';
  document.getElementById('admin-cab-route-time').value = '4h 30m';
  document.getElementById('admin-cab-route-toll').value = '0';
  window.setCabRouteCalcMode('manual', 'Mode: Manual or Calculate via API');
  if (document.getElementById('admin-cab-route-schedule')) document.getElementById('admin-cab-route-schedule').value = '06:00 AM, 11:30 AM, 04:00 PM, 09:30 PM';
  if (document.getElementById('admin-cab-route-freq')) document.getElementById('admin-cab-route-freq').value = 'Daily Express';
  if (document.getElementById('admin-cab-route-hatchback-fare')) document.getElementById('admin-cab-route-hatchback-fare').value = '';
  if (document.getElementById('admin-cab-route-sedan-fare')) document.getElementById('admin-cab-route-sedan-fare').value = '';
  if (document.getElementById('admin-cab-route-suv-fare')) document.getElementById('admin-cab-route-suv-fare').value = '';
  if (document.getElementById('admin-cab-route-innova-fare')) document.getElementById('admin-cab-route-innova-fare').value = '';
  if (document.getElementById('admin-cab-route-tempo-fare')) document.getElementById('admin-cab-route-tempo-fare').value = '';
  if (document.getElementById('admin-cab-route-luxury-fare')) document.getElementById('admin-cab-route-luxury-fare').value = '';
  if (document.getElementById('admin-cab-route-roundtrip-discount')) document.getElementById('admin-cab-route-roundtrip-discount').value = '0';
  if (document.getElementById('admin-cab-route-default-car-type')) document.getElementById('admin-cab-route-default-car-type').value = '';
  document.getElementById('admin-cab-route-active').checked = true;

  const delBtn = document.getElementById('admin-cab-route-delete-btn');
  if (delBtn) delBtn.style.display = 'none';

  const modal = document.getElementById('admin-cab-route-modal');
  if (modal) modal.classList.add('active');
};

window.openEditCabRouteModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/cab-routes/${id}`);
    const data = await res.json();
    if (!data.success || !data.route) return;

    const route = data.route;
    document.getElementById('admin-cab-route-modal-title').textContent = `Edit Cab Route: ${route.from_city} ➔ ${route.to_city}`;
    document.getElementById('admin-cab-route-id').value = route.id;
    document.getElementById('admin-cab-route-from').value = route.from_city;
    document.getElementById('admin-cab-route-to').value = route.to_city;
    if (document.getElementById('admin-cab-route-from-lat')) document.getElementById('admin-cab-route-from-lat').value = route.from_lat ?? '';
    if (document.getElementById('admin-cab-route-from-lng')) document.getElementById('admin-cab-route-from-lng').value = route.from_lng ?? '';
    if (document.getElementById('admin-cab-route-to-lat')) document.getElementById('admin-cab-route-to-lat').value = route.to_lat ?? '';
    if (document.getElementById('admin-cab-route-to-lng')) document.getElementById('admin-cab-route-to-lng').value = route.to_lng ?? '';
    document.getElementById('admin-cab-route-dist').value = route.distance_km;
    document.getElementById('admin-cab-route-time').value = route.estimated_time;
    document.getElementById('admin-cab-route-toll').value = route.base_toll || 0;
    window.setCabRouteCalcMode('manual', 'Mode: Saved / Manual (or Recalculate via API)');
    if (document.getElementById('admin-cab-route-schedule')) document.getElementById('admin-cab-route-schedule').value = route.departure_schedule || '';
    if (document.getElementById('admin-cab-route-freq')) document.getElementById('admin-cab-route-freq').value = route.schedule_frequency || 'Daily Express';
    if (document.getElementById('admin-cab-route-hatchback-fare')) document.getElementById('admin-cab-route-hatchback-fare').value = route.hatchback_fare || '';
    if (document.getElementById('admin-cab-route-sedan-fare')) document.getElementById('admin-cab-route-sedan-fare').value = route.sedan_fare || '';
    if (document.getElementById('admin-cab-route-suv-fare')) document.getElementById('admin-cab-route-suv-fare').value = route.suv_fare || '';
    if (document.getElementById('admin-cab-route-innova-fare')) document.getElementById('admin-cab-route-innova-fare').value = route.innova_fare || '';
    if (document.getElementById('admin-cab-route-tempo-fare')) document.getElementById('admin-cab-route-tempo-fare').value = route.tempo_fare || '';
    if (document.getElementById('admin-cab-route-luxury-fare')) document.getElementById('admin-cab-route-luxury-fare').value = route.luxury_fare || '';
    if (document.getElementById('admin-cab-route-roundtrip-discount')) document.getElementById('admin-cab-route-roundtrip-discount').value = route.roundtrip_discount_pct || 0;
    if (document.getElementById('admin-cab-route-default-car-type')) document.getElementById('admin-cab-route-default-car-type').value = (route.default_car_type || '').toLowerCase();
    document.getElementById('admin-cab-route-active').checked = Boolean(route.is_active);

    const delBtn = document.getElementById('admin-cab-route-delete-btn');
    if (delBtn) delBtn.style.display = 'inline-flex';

    const modal = document.getElementById('admin-cab-route-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Fetch cab route error:', err);
    showToast('Failed to load cab route details.', 'warning');
  }
};

window.saveCabRoute = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-cab-route-id').value;
  const isEdit = Boolean(id);

  const fromLatVal = document.getElementById('admin-cab-route-from-lat')?.value;
  const fromLngVal = document.getElementById('admin-cab-route-from-lng')?.value;
  const toLatVal = document.getElementById('admin-cab-route-to-lat')?.value;
  const toLngVal = document.getElementById('admin-cab-route-to-lng')?.value;

  const payload = {
    from_city: document.getElementById('admin-cab-route-from').value.trim(),
    to_city: document.getElementById('admin-cab-route-to').value.trim(),
    distance_km: parseFloat(document.getElementById('admin-cab-route-dist').value),
    estimated_time: document.getElementById('admin-cab-route-time').value.trim(),
    base_toll: parseFloat(document.getElementById('admin-cab-route-toll').value) || 0,
    ...(fromLatVal !== '' && fromLngVal !== '' ? { from_lat: parseFloat(fromLatVal), from_lng: parseFloat(fromLngVal) } : {}),
    ...(toLatVal !== '' && toLngVal !== '' ? { to_lat: parseFloat(toLatVal), to_lng: parseFloat(toLngVal) } : {}),
    departure_schedule: document.getElementById('admin-cab-route-schedule')?.value.trim() || null,
    schedule_frequency: document.getElementById('admin-cab-route-freq')?.value || 'Daily Express',
    hatchback_fare: document.getElementById('admin-cab-route-hatchback-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-hatchback-fare').value) : null,
    sedan_fare: document.getElementById('admin-cab-route-sedan-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-sedan-fare').value) : null,
    suv_fare: document.getElementById('admin-cab-route-suv-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-suv-fare').value) : null,
    innova_fare: document.getElementById('admin-cab-route-innova-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-innova-fare').value) : null,
    tempo_fare: document.getElementById('admin-cab-route-tempo-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-tempo-fare').value) : null,
    luxury_fare: document.getElementById('admin-cab-route-luxury-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-luxury-fare').value) : null,
    roundtrip_discount_pct: document.getElementById('admin-cab-route-roundtrip-discount')?.value ? parseFloat(document.getElementById('admin-cab-route-roundtrip-discount').value) : 0,
    default_car_type: document.getElementById('admin-cab-route-default-car-type')?.value || null,
    is_active: document.getElementById('admin-cab-route-active').checked,
  };

  const url = isEdit ? `/api/admin/cab-routes/${id}` : '/api/admin/cab-routes';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-cab-route-modal');
      const r = data.route;
      if (r) {
        if (Array.isArray(window.INITIAL_CAB_ROUTES)) {
          const idx = window.INITIAL_CAB_ROUTES.findIndex(item => item.id === r.id);
          if (idx >= 0) window.INITIAL_CAB_ROUTES[idx] = r;
          else window.INITIAL_CAB_ROUTES.unshift(r);
        }
        const nightRate = Number(window.PLATFORM_SETTINGS?.addon_night_allowance_rate ?? 400);
        const minKmPerDay = Number(window.PLATFORM_SETTINGS?.roundtrip_min_km_per_day ?? 250);
        const rtDisc = r.roundtrip_discount_pct > 0 ? `${r.roundtrip_discount_pct}% Return Disc.` : `${minKmPerDay} KM/Day Min`;
        const routeCarBadge = r.default_car_type ? `<div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: ${String(r.default_car_type).toUpperCase()}</span></div>` : '';
        const rtCarCellHtml = r.default_car_type
          ? `<span class="admin-badge badge-secondary" style="font-size:10px; padding:2px 6px;">Car: ${String(r.default_car_type).toUpperCase()}</span>`
          : `<span style="font-size:11px; color:#94a3b8;">Any Car Type</span>`;
        const scheduleHtml = r.departure_schedule ? `<div style="font-size:11px; color:#0284c7; font-weight:600; margin-top:2px;">⏱️ ${r.departure_schedule} <span class="admin-badge badge-info" style="font-size:9px; padding:1px 5px;">${r.schedule_frequency || 'Daily Express'}</span></div>` : '<div style="font-size:11px; color:#64748b;">Daily Corridor &bull; On-Demand 24x7</div>';
        const fareScheduleHtml = window.buildRouteFareScheduleHtml(r, false);
        const rtFareScheduleHtml = window.buildRouteFareScheduleHtml(r, true);

        // Update helper for route row
        const updateRow = (row, isBt = false) => {
          if (!row) return;
          row.dataset.status = r.is_active ? 'active' : 'inactive';
          row.dataset.search = `${r.from_city} ${r.to_city}`.toLowerCase();
          row.cells[0].innerHTML = `
              <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <span>${r.from_city}</span>
                <span style="color:var(--primary);">➔</span>
                <span>${r.to_city}</span>
              </div>
              ${scheduleHtml}
            `;
          row.cells[1].innerHTML = `<span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">🛣️ ${r.distance_km} KM</span>`;
          row.cells[2].innerHTML = `<span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${r.estimated_time}</span>`;
          row.cells[3].innerHTML = `<strong style="color:#0f172a;">₹${Number(r.base_toll || 0).toLocaleString('en-IN')}</strong>`;
          row.cells[4].innerHTML = fareScheduleHtml;
          if (isBt) {
            row.cells[5].innerHTML = `<span style="font-size:11px; color:#16a34a; font-weight:600;">${rtDisc}</span>${routeCarBadge}`;
            row.cells[6].innerHTML = `<span class="admin-badge ${r.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCabRouteStatus(${r.id})" id="bt-cab-route-status-badge-${r.id}" title="Click to toggle status">${r.is_active ? 'Active' : 'Inactive'}</span>`;
          } else {
            row.cells[5].innerHTML = `<span class="admin-badge ${r.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCabRouteStatus(${r.id})" id="cab-route-status-badge-${r.id}" title="Click to toggle status">${r.is_active ? 'Active' : 'Inactive'}</span>`;
          }
        };

        const createRow = (tableId, prefix, isBt = false) => {
          const tbody = document.getElementById(tableId);
          if (!tbody) return;
          const tr = document.createElement('tr');
          tr.id = `${prefix}-${r.id}`;
          tr.dataset.status = r.is_active ? 'active' : 'inactive';
          tr.dataset.search = `${r.from_city} ${r.to_city}`.toLowerCase();
          tr.innerHTML = `
              <td>
                <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                  <span>${r.from_city}</span>
                  <span style="color:var(--primary);">➔</span>
                  <span>${r.to_city}</span>
                </div>
                ${scheduleHtml}
              </td>
              <td>
                <span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">🛣️ ${r.distance_km} KM</span>
              </td>
              <td>
                <span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${r.estimated_time}</span>
              </td>
              <td>
                <strong style="color:#0f172a;">₹${Number(r.base_toll || 0).toLocaleString('en-IN')}</strong>
              </td>
              <td>
                ${fareScheduleHtml}
              </td>
              ${isBt ? `<td><span style="font-size:11px; color:#16a34a; font-weight:600;">${rtDisc}</span>${routeCarBadge}</td>` : ''}
              <td>
                <span class="admin-badge ${r.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCabRouteStatus(${r.id})" id="${isBt ? 'bt-' : ''}cab-route-status-badge-${r.id}" title="Click to toggle status">${r.is_active ? 'Active' : 'Inactive'}</span>
              </td>
              <td style="text-align:right;">
                <div class="admin-action-btn-group" style="justify-content:flex-end;">
                  <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCabRouteModal(${r.id})" title="Edit Route">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCabRoute(${r.id})" title="Delete Route">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            `;
          tbody.prepend(tr);
        };

        if (isEdit) {
          updateRow(document.getElementById(`admin-cab-route-row-${r.id}`), false);
          updateRow(document.getElementById(`admin-bt-cab-route-row-${r.id}`), true);
          const rtRow = document.getElementById(`admin-bt-rt-route-row-${r.id}`);
          if (rtRow) {
            rtRow.dataset.status = r.is_active ? 'active' : 'inactive';
            rtRow.dataset.search = `${r.from_city} ${r.to_city}`.toLowerCase();
            rtRow.cells[0].innerHTML = `<div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;"><span>${r.from_city}</span><span style="color:#16a34a;">⇄</span><span>${r.to_city}</span></div><div style="font-size:11px; color:#64748b;">Round-Trip &bull; Night Halt ₹${nightRate.toLocaleString('en-IN')}/night</div>`;
            rtRow.cells[1].innerHTML = `<span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">🔁 ${r.distance_km * 2} KM</span><div style="font-size:10.5px; color:#64748b; margin-top:2px;">${minKmPerDay} KM/Day Min</div>`;
            rtRow.cells[2].innerHTML = `<span style="font-size:11.5px; color:#16a34a; font-weight:700;">${r.roundtrip_discount_pct > 0 ? `${r.roundtrip_discount_pct}% Return Disc.` : 'Standard (0% Disc.)'}</span>`;
            rtRow.cells[3].innerHTML = rtFareScheduleHtml;
            rtRow.cells[4].innerHTML = rtCarCellHtml;
            rtRow.cells[5].innerHTML = `<span class="admin-badge ${r.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCabRouteStatus(${r.id})" id="bt-rt-route-status-badge-${r.id}" title="Click to toggle status">${r.is_active ? 'Active' : 'Inactive'}</span>`;
          }
        } else {
          createRow('admin-cab-routes-tbody', 'admin-cab-route-row', false);
          createRow('admin-bt-routes-tbody', 'admin-bt-cab-route-row', true);
          const rtTbody = document.getElementById('admin-bt-roundtrip-routes-tbody');
          if (rtTbody) {
            const rtTr = document.createElement('tr');
            rtTr.id = `admin-bt-rt-route-row-${r.id}`;
            rtTr.dataset.routeId = r.id;
            rtTr.dataset.status = r.is_active ? 'active' : 'inactive';
            rtTr.dataset.search = `${r.from_city} ${r.to_city}`.toLowerCase();
            rtTr.innerHTML = `
              <td>
                <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                  <span>${r.from_city}</span><span style="color:#16a34a;">⇄</span><span>${r.to_city}</span>
                </div>
                <div style="font-size:11px; color:#64748b;">Round-Trip &bull; Night Halt ₹${nightRate.toLocaleString('en-IN')}/night</div>
              </td>
              <td>
                <span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">🔁 ${r.distance_km * 2} KM</span>
                <div style="font-size:10.5px; color:#64748b; margin-top:2px;">${minKmPerDay} KM/Day Min</div>
              </td>
              <td><span style="font-size:11.5px; color:#16a34a; font-weight:700;">${r.roundtrip_discount_pct > 0 ? `${r.roundtrip_discount_pct}% Return Disc.` : 'Standard (0% Disc.)'}</span></td>
              <td>${rtFareScheduleHtml}</td>
              <td>${rtCarCellHtml}</td>
              <td><span class="admin-badge ${r.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCabRouteStatus(${r.id})" id="bt-rt-route-status-badge-${r.id}" title="Click to toggle status">${r.is_active ? 'Active' : 'Inactive'}</span></td>
              <td style="text-align:right;">
                <div class="admin-action-btn-group" style="justify-content:flex-end;">
                  <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCabRouteModal(${r.id})" title="Edit Route & Return Discount"><span class="material-symbols-outlined">edit</span></button>
                  <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCabRoute(${r.id})" title="Delete Route"><span class="material-symbols-outlined">delete</span></button>
                </div>
              </td>`;
            rtTbody.prepend(rtTr);
          }
        }

        const totalRoutes = document.querySelectorAll('#admin-cab-routes-tbody tr[id^="admin-cab-route-row-"]').length || document.querySelectorAll('#admin-bt-routes-tbody tr[id^="admin-bt-cab-route-row-"]').length;
        const filterCountEl = document.getElementById('admin-cab-routes-filter-count');
        if (filterCountEl) filterCountEl.textContent = `${totalRoutes} Routes`;
        const btFilterCountEl = document.getElementById('admin-bt-routes-filter-count');
        if (btFilterCountEl) btFilterCountEl.textContent = `${totalRoutes} Routes`;
        const rtFilterCountEl = document.getElementById('admin-bt-rt-routes-filter-count');
        if (rtFilterCountEl) rtFilterCountEl.textContent = `${totalRoutes} Routes`;
        const navBadge = document.getElementById('admin-badge-cab-routes-count');
        if (navBadge) navBadge.textContent = totalRoutes;
        const tabBadge = document.getElementById('admin-tab-count-outstation');
        if (tabBadge) tabBadge.textContent = totalRoutes;
        const rtTabBadge = document.getElementById('admin-tab-count-roundtrip');
        if (rtTabBadge) rtTabBadge.textContent = totalRoutes;
        const topPill = document.getElementById('admin-bt-total-routes-pill');
        if (topPill) topPill.textContent = `${totalRoutes} One-Way`;
        const rtTopPill = document.getElementById('admin-bt-total-roundtrip-pill');
        if (rtTopPill) rtTopPill.textContent = `${totalRoutes} Round-Trip`;

        // Update in-memory ROUTE_MATRIX for instant portal sync
        if (r && r.from_city && r.to_city) {
          const fKey = r.from_city.toLowerCase().trim();
          const tKey = r.to_city.toLowerCase().trim();
          ROUTE_MATRIX[`${fKey}-${tKey}`] = { dist: r.distance_km, time: r.estimated_time, toll: r.base_toll || 0 };
          ROUTE_MATRIX[`${tKey}-${fKey}`] = { dist: r.distance_km, time: r.estimated_time, toll: r.base_toll || 0 };
          updateRentalRouteStats();
        }
      }
    } else {
      showToast(data.message || 'Validation error while saving cab route.', 'danger');
    }
  } catch (err) {
    console.error('Save cab route error:', err);
    showToast('Network error while saving cab route.', 'warning');
  }
};

// ========================================================
// 1D. CAR TYPES & FLEET CATEGORIES MANAGEMENT (Admin)
// ========================================================
window.openAddCarTypeModal = function () {
  document.getElementById('admin-car-type-modal-title').textContent = 'Add Car Type & Fleet Category';
  document.getElementById('admin-car-type-id').value = '';
  document.getElementById('admin-car-type-name').value = '';
  document.getElementById('admin-car-type-key').value = '';
  document.getElementById('admin-car-type-key').disabled = false;
  document.getElementById('admin-car-type-badge').value = 'Popular';
  document.getElementById('admin-car-type-models').value = '';
  document.getElementById('admin-car-type-seats').value = '4 Seats';
  document.getElementById('admin-car-type-luggage').value = '2 Bags';
  document.getElementById('admin-car-type-rate-km').value = '12.5';
  document.getElementById('admin-car-type-extra-km').value = '12.5';
  document.getElementById('admin-car-type-min-fare').value = '800';
  document.getElementById('admin-car-type-image').value = 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=640&q=80';
  document.getElementById('admin-car-type-order').value = '0';
  document.getElementById('admin-car-type-active').checked = true;

  const delBtn = document.getElementById('admin-car-type-delete-btn');
  if (delBtn) delBtn.style.display = 'none';

  const modal = document.getElementById('admin-car-type-modal');
  if (modal) modal.classList.add('active');
};

window.openEditCarTypeModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/car-types/${id}`);
    const data = await res.json();
    if (!data.success || !data.car_type) return;

    const ct = data.car_type;
    document.getElementById('admin-car-type-modal-title').textContent = `Edit Car Type: ${ct.name}`;
    document.getElementById('admin-car-type-id').value = ct.id;
    document.getElementById('admin-car-type-name').value = ct.name;
    document.getElementById('admin-car-type-key').value = ct.category_key;
    document.getElementById('admin-car-type-badge').value = ct.badge || 'Popular';
    document.getElementById('admin-car-type-models').value = ct.models;
    document.getElementById('admin-car-type-seats').value = ct.seats;
    document.getElementById('admin-car-type-luggage').value = ct.luggage;
    document.getElementById('admin-car-type-rate-km').value = ct.rate_per_km;
    document.getElementById('admin-car-type-extra-km').value = ct.extra_km_rate || ct.rate_per_km;
    document.getElementById('admin-car-type-min-fare').value = ct.base_fare_min || 800;
    document.getElementById('admin-car-type-image').value = ct.image_url || '';
    document.getElementById('admin-car-type-order').value = ct.order_index || 0;
    document.getElementById('admin-car-type-active').checked = Boolean(ct.is_active);

    const delBtn = document.getElementById('admin-car-type-delete-btn');
    if (delBtn) delBtn.style.display = 'inline-flex';

    const modal = document.getElementById('admin-car-type-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Fetch car type error:', err);
    showToast('Failed to load car type details.', 'warning');
  }
};

window.saveCarType = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-car-type-id').value;
  const isEdit = Boolean(id);

  const payload = {
    name: document.getElementById('admin-car-type-name').value.trim(),
    category_key: document.getElementById('admin-car-type-key').value.trim().toLowerCase(),
    badge: document.getElementById('admin-car-type-badge').value.trim() || 'Popular',
    models: document.getElementById('admin-car-type-models').value.trim(),
    seats: document.getElementById('admin-car-type-seats').value.trim(),
    luggage: document.getElementById('admin-car-type-luggage').value.trim(),
    rate_per_km: parseFloat(document.getElementById('admin-car-type-rate-km').value),
    extra_km_rate: parseFloat(document.getElementById('admin-car-type-extra-km').value) || parseFloat(document.getElementById('admin-car-type-rate-km').value),
    base_fare_min: parseFloat(document.getElementById('admin-car-type-min-fare').value) || 800,
    image_url: document.getElementById('admin-car-type-image').value.trim(),
    order_index: parseInt(document.getElementById('admin-car-type-order').value, 10) || 0,
    is_active: document.getElementById('admin-car-type-active').checked,
  };

  const url = isEdit ? `/api/admin/car-types/${id}` : '/api/admin/car-types';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-car-type-modal');
      const ct = data.car_type;
      if (ct) {
        const updateRow = (row, isBt = false) => {
          if (!row) return;
          row.dataset.status = ct.is_active ? 'active' : 'inactive';
          row.dataset.search = `${ct.name} ${ct.category_key} ${ct.models}`.toLowerCase();
          row.cells[0].innerHTML = `
              <div style="display:flex; align-items:center; gap:10px;">
                <img src="${ct.image_url}" alt="${ct.name}" style="width:${isBt ? '48px' : '52px'}; height:${isBt ? '32px' : '34px'}; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;">
                <div>
                  <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span>${ct.name}</span>
                    <span class="rental-category-badge" style="font-size:9.5px; padding:2px 6px;">${ct.badge}</span>
                  </div>
                  <span style="font-family:monospace; font-size:11px; color:var(--primary); font-weight:700;">key: ${ct.category_key}</span>
                </div>
              </div>
            `;
          row.cells[1].innerHTML = `<span style="font-size:12px; color:#334155; font-weight:600;">${ct.models}</span>`;
          row.cells[2].innerHTML = `<div style="font-size:11.5px; color:#475569;"><span>💺 ${ct.seats}</span> &bull; <span>🧳 ${ct.luggage}</span></div>`;
          row.cells[3].innerHTML = `<strong style="color:#0f172a; font-size:13px;">₹${Number(ct.rate_per_km).toFixed(2)}</strong><span style="font-size:10.5px; color:#64748b;">/km</span>`;
          row.cells[4].innerHTML = `<span style="color:#334155; font-size:12px; font-weight:600;">₹${Number(ct.extra_km_rate || ct.rate_per_km).toFixed(2)}<span style="font-size:10.5px; color:#64748b;">/km</span></span>`;
          row.cells[5].innerHTML = `<span style="color:#0f172a; font-weight:700; font-size:12.5px;">₹${Number(ct.base_fare_min).toLocaleString('en-IN')}</span>`;
          const badgeId = isBt ? `bt-car-type-status-badge-${ct.id}` : `car-type-status-badge-${ct.id}`;
          row.cells[6].innerHTML = `<span class="admin-badge ${ct.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCarTypeStatus(${ct.id})" id="${badgeId}" title="Click to toggle status">${ct.is_active ? 'Active' : 'Inactive'}</span>`;
        };

        const createRow = (tableId, prefix, isBt = false) => {
          const tbody = document.getElementById(tableId);
          if (!tbody) return;
          const tr = document.createElement('tr');
          tr.id = `${prefix}-${ct.id}`;
          tr.dataset.status = ct.is_active ? 'active' : 'inactive';
          tr.dataset.search = `${ct.name} ${ct.category_key} ${ct.models}`.toLowerCase();
          tr.innerHTML = `
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <img src="${ct.image_url}" alt="${ct.name}" style="width:${isBt ? '48px' : '52px'}; height:${isBt ? '32px' : '34px'}; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;">
                  <div>
                    <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                      <span>${ct.name}</span>
                      <span class="rental-category-badge" style="font-size:9.5px; padding:2px 6px;">${ct.badge}</span>
                    </div>
                    <span style="font-family:monospace; font-size:11px; color:var(--primary); font-weight:700;">key: ${ct.category_key}</span>
                  </div>
                </div>
              </td>
              <td><span style="font-size:12px; color:#334155; font-weight:600;">${ct.models}</span></td>
              <td><div style="font-size:11.5px; color:#475569;"><span>💺 ${ct.seats}</span> &bull; <span>🧳 ${ct.luggage}</span></div></td>
              <td><strong style="color:#0f172a; font-size:13px;">₹${Number(ct.rate_per_km).toFixed(2)}</strong><span style="font-size:10.5px; color:#64748b;">/km</span></td>
              <td><span style="color:#334155; font-size:12px; font-weight:600;">₹${Number(ct.extra_km_rate || ct.rate_per_km).toFixed(2)}<span style="font-size:10.5px; color:#64748b;">/km</span></span></td>
              <td><span style="color:#0f172a; font-weight:700; font-size:12.5px;">₹${Number(ct.base_fare_min).toLocaleString('en-IN')}</span></td>
              <td>
                <span class="admin-badge ${ct.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleCarTypeStatus(${ct.id})" id="${isBt ? 'bt-' : ''}car-type-status-badge-${ct.id}" title="Click to toggle status">
                  ${ct.is_active ? 'Active' : 'Inactive'}
                </span>
              </td>
              <td style="text-align:right;">
                <div class="admin-action-btn-group" style="justify-content:flex-end;">
                  <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCarTypeModal(${ct.id})" title="Edit Car Type">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCarType(${ct.id})" title="Delete Car Type">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            `;
          tbody.prepend(tr);
        };

        if (isEdit) {
          updateRow(document.getElementById(`admin-car-type-row-${ct.id}`), false);
        } else {
          createRow('admin-car-types-tbody', 'admin-car-type-row', false);
        }

        const count = document.querySelectorAll('#admin-car-types-tbody tr[id^="admin-car-type-row-"]').length;
        const badge1 = document.getElementById('admin-badge-car-types-count');
        if (badge1) badge1.textContent = `${count} Types`;
        await window.syncAllCarTypes();
      }
    } else {
      showToast(data.message || 'Validation failed. Please verify car type inputs.', 'warning');
    }
  } catch (err) {
    console.error('Save car type error:', err);
    showToast('Error saving car type.', 'danger');
  }
};

window.toggleCarTypeStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/car-types/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success && data.car_type) {
      const ct = data.car_type;
      const b1 = document.getElementById(`car-type-status-badge-${ct.id}`);
      const b2 = document.getElementById(`bt-car-type-status-badge-${ct.id}`);
      [b1, b2].forEach(b => {
        if (b) {
          b.className = `admin-badge ${ct.is_active ? 'badge-success' : 'badge-danger'}`;
          b.textContent = ct.is_active ? 'Active' : 'Inactive';
        }
      });
      const r1 = document.getElementById(`admin-car-type-row-${ct.id}`);
      const r2 = document.getElementById(`admin-bt-car-type-row-${ct.id}`);
      [r1, r2].forEach(r => { if (r) r.dataset.status = ct.is_active ? 'active' : 'inactive'; });
      showToast(data.message, 'success');
      await window.syncAllCarTypes();
    }
  } catch (err) {
    showToast('Failed to toggle car type status.', 'warning');
  }
};

window.deleteCarType = async function (id) {
  if (!confirm('Are you sure you want to delete this car type?')) return;
  try {
    const res = await fetch(`/api/admin/car-types/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      document.getElementById(`admin-car-type-row-${id}`)?.remove();
      const count = document.querySelectorAll('#admin-car-types-tbody tr[id^="admin-car-type-row-"]').length;
      const badge1 = document.getElementById('admin-badge-car-types-count');
      if (badge1) badge1.textContent = `${count} Types`;
      await window.syncAllCarTypes();
    }
  } catch (err) {
    showToast('Failed to delete car type.', 'warning');
  }
};

window.deleteCarTypeFromModal = function () {
  const id = document.getElementById('admin-car-type-id').value;
  if (id) {
    closeModal('admin-car-type-modal');
    deleteCarType(id);
  }
};

window.handleCarTypeSearch = function (q) {
  const s = (q || '').toLowerCase().trim();
  document.querySelectorAll('#admin-car-types-tbody tr').forEach(r => {
    const text = r.dataset.search || '';
    r.style.display = (!s || text.includes(s)) ? '' : 'none';
  });
};

window.handleCarTypeFilter = function () {
  const status = document.getElementById('admin-car-type-filter-status')?.value || 'all';
  document.querySelectorAll('#admin-car-types-tbody tr').forEach(r => {
    const rStatus = r.dataset.status;
    r.style.display = (status === 'all' || rStatus === status) ? '' : 'none';
  });
};

window.toggleCabRouteStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/cab-routes/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      const isActive = data.route.is_active;

      ['cab-route-status-badge-', 'bt-cab-route-status-badge-', 'bt-rt-route-status-badge-'].forEach(prefix => {
        const badge = document.getElementById(`${prefix}${id}`);
        if (badge) {
          badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
          badge.textContent = isActive ? 'Active' : 'Inactive';
        }
      });

      ['admin-cab-route-row-', 'admin-bt-cab-route-row-', 'admin-bt-rt-route-row-'].forEach(prefix => {
        const row = document.getElementById(`${prefix}${id}`);
        if (row) row.dataset.status = isActive ? 'active' : 'inactive';
      });
    }
  } catch (err) {
    showToast('Failed to toggle cab route status.', 'warning');
  }
};

window.deleteCabRoute = async function (id) {
  if (!confirm('Are you sure you want to delete this outstation cab route?')) return;
  try {
    const res = await fetch(`/api/admin/cab-routes/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const r1 = document.getElementById(`admin-cab-route-row-${id}`);
      if (r1) r1.remove();
      const r2 = document.getElementById(`admin-bt-cab-route-row-${id}`);
      if (r2) r2.remove();
      const r3 = document.getElementById(`admin-bt-rt-route-row-${id}`);
      if (r3) r3.remove();

      const totalRoutes = document.querySelectorAll('#admin-cab-routes-tbody tr[id^="admin-cab-route-row-"]').length || document.querySelectorAll('#admin-bt-routes-tbody tr[id^="admin-bt-cab-route-row-"]').length;
      const filterCountEl = document.getElementById('admin-cab-routes-filter-count');
      if (filterCountEl) filterCountEl.textContent = `${totalRoutes} Routes`;
      const btFilterCountEl = document.getElementById('admin-bt-routes-filter-count');
      if (btFilterCountEl) btFilterCountEl.textContent = `${totalRoutes} Routes`;
      const rtFilterCountEl = document.getElementById('admin-bt-rt-routes-filter-count');
      if (rtFilterCountEl) rtFilterCountEl.textContent = `${totalRoutes} Routes`;
      const navBadge = document.getElementById('admin-badge-cab-routes-count');
      if (navBadge) navBadge.textContent = totalRoutes;
      const tabBadge = document.getElementById('admin-tab-count-outstation');
      if (tabBadge) tabBadge.textContent = totalRoutes;
      const rtTabBadge = document.getElementById('admin-tab-count-roundtrip');
      if (rtTabBadge) rtTabBadge.textContent = totalRoutes;
      const topPill = document.getElementById('admin-bt-total-routes-pill');
      if (topPill) topPill.textContent = `${totalRoutes} One-Way`;
      const rtTopPill = document.getElementById('admin-bt-total-roundtrip-pill');
      if (rtTopPill) rtTopPill.textContent = `${totalRoutes} Round-Trip`;
    }
  } catch (err) {
    showToast('Failed to delete cab route.', 'warning');
  }
};

window.deleteCabRouteFromModal = function () {
  const id = document.getElementById('admin-cab-route-id').value;
  if (id) {
    closeModal('admin-cab-route-modal');
    deleteCabRoute(id);
  }
};

window.handleCabRouteSearch = function (q) {
  const query = (q || '').toLowerCase().trim();
  const configs = [
    { selector: '#admin-cab-routes-tbody tr', statusId: 'admin-cab-route-filter-status', countId: 'admin-cab-routes-filter-count' },
    { selector: '#admin-bt-routes-tbody tr', statusId: 'admin-bt-route-filter-status', countId: 'admin-bt-routes-filter-count' },
    { selector: '#admin-bt-roundtrip-routes-tbody tr', statusId: 'admin-bt-rt-route-filter-status', countId: 'admin-bt-rt-routes-filter-count' },
  ];
  configs.forEach(cfg => {
    const rows = document.querySelectorAll(cfg.selector);
    const statusFilter = document.getElementById(cfg.statusId)?.value || 'all';
    let visibleCount = 0;
    rows.forEach(r => {
      const text = (r.dataset.search || '').toLowerCase();
      const status = r.dataset.status;
      const matchesSearch = !query || text.includes(query);
      const matchesStatus = statusFilter === 'all' || status === statusFilter;

      if (matchesSearch && matchesStatus) {
        r.style.display = '';
        visibleCount++;
      } else {
        r.style.display = 'none';
      }
    });
    const countEl = document.getElementById(cfg.countId);
    if (countEl) countEl.textContent = `${visibleCount} Routes`;
  });

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-cab-routes', true);
  }
};

window.handleCabRouteFilter = function () {
  const q = document.getElementById('admin-cab-route-search-input')?.value
    || document.getElementById('admin-bt-route-search-input')?.value
    || document.getElementById('admin-bt-rt-route-search-input')?.value
    || '';
  handleCabRouteSearch(q);
};

// ========================================================
// 1B. MODULAR BOOKING TYPES SUB-NAV & MANAGERS
// ========================================================
window.switchBookingTypeTab = function (tabKey) {
  const normalizedKey = tabKey === 'outstation' ? 'oneway' : tabKey;
  document.querySelectorAll('.admin-bt-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === normalizedKey);
  });
  document.querySelectorAll('.admin-bt-tab-panel').forEach(panel => {
    panel.style.display = (panel.id === `admin-bt-panel-${normalizedKey}`) ? 'block' : 'none';
  });
};

// Local Hourly Rental Packages Handlers
window.openAddLocalPackageModal = function () {
  document.getElementById('admin-local-pkg-modal-title').textContent = 'Add Local Rental Package';
  document.getElementById('admin-local-pkg-id').value = '';
  document.getElementById('admin-local-pkg-code').value = '';
  document.getElementById('admin-local-pkg-city').value = 'All Cities';
  document.getElementById('admin-local-pkg-name').value = '';
  document.getElementById('admin-local-pkg-hours').value = '8';
  document.getElementById('admin-local-pkg-km').value = '80';
  document.getElementById('admin-local-pkg-sedan').value = '2400';
  document.getElementById('admin-local-pkg-suv').value = '3200';
  document.getElementById('admin-local-pkg-extra-km').value = '14';
  document.getElementById('admin-local-pkg-extra-hr').value = '150';
  if (document.getElementById('admin-local-pkg-default-car-type')) document.getElementById('admin-local-pkg-default-car-type').value = '';
  document.getElementById('admin-local-pkg-active').checked = true;

  const delBtn = document.getElementById('admin-local-pkg-delete-btn');
  if (delBtn) delBtn.style.display = 'none';

  const modal = document.getElementById('admin-local-package-modal');
  if (modal) modal.classList.add('active');
};

window.openEditLocalPackageModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/local-packages/${id}`);
    const data = await res.json();
    if (!data.success || !data.package) return;

    const p = data.package;
    document.getElementById('admin-local-pkg-modal-title').textContent = `Edit Local Package: ${p.package_code}`;
    document.getElementById('admin-local-pkg-id').value = p.id;
    document.getElementById('admin-local-pkg-code').value = p.package_code;
    document.getElementById('admin-local-pkg-city').value = p.city || 'All Cities';
    document.getElementById('admin-local-pkg-name').value = p.name;
    document.getElementById('admin-local-pkg-hours').value = p.duration_hours;
    document.getElementById('admin-local-pkg-km').value = p.included_km;
    document.getElementById('admin-local-pkg-sedan').value = p.sedan_fare;
    document.getElementById('admin-local-pkg-suv').value = p.suv_fare;
    document.getElementById('admin-local-pkg-extra-km').value = p.extra_km_rate || 14;
    document.getElementById('admin-local-pkg-extra-hr').value = p.extra_hour_rate || 150;
    if (document.getElementById('admin-local-pkg-default-car-type')) document.getElementById('admin-local-pkg-default-car-type').value = (p.default_car_type || '').toLowerCase();
    document.getElementById('admin-local-pkg-active').checked = Boolean(p.is_active);

    const delBtn = document.getElementById('admin-local-pkg-delete-btn');
    if (delBtn) delBtn.style.display = 'inline-flex';

    const modal = document.getElementById('admin-local-package-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Fetch local package error:', err);
    showToast('Failed to load local package details.', 'warning');
  }
};

window.saveLocalPackage = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-local-pkg-id').value;
  const isEdit = Boolean(id);

  const payload = {
    package_code: document.getElementById('admin-local-pkg-code').value.trim(),
    city: document.getElementById('admin-local-pkg-city').value.trim() || 'All Cities',
    name: document.getElementById('admin-local-pkg-name').value.trim(),
    duration_hours: parseInt(document.getElementById('admin-local-pkg-hours').value),
    included_km: parseInt(document.getElementById('admin-local-pkg-km').value),
    sedan_fare: parseFloat(document.getElementById('admin-local-pkg-sedan').value),
    suv_fare: parseFloat(document.getElementById('admin-local-pkg-suv').value),
    extra_km_rate: parseFloat(document.getElementById('admin-local-pkg-extra-km').value) || 14,
    extra_hour_rate: parseFloat(document.getElementById('admin-local-pkg-extra-hr').value) || 150,
    default_car_type: document.getElementById('admin-local-pkg-default-car-type')?.value || null,
    is_active: document.getElementById('admin-local-pkg-active').checked,
  };

  const url = isEdit ? `/api/admin/local-packages/${id}` : '/api/admin/local-packages';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-local-package-modal');
      const p = data.package;
      if (p) {
        if (Array.isArray(window.INITIAL_LOCAL_PACKAGES)) {
          const idx = window.INITIAL_LOCAL_PACKAGES.findIndex(item => item.id === p.id);
          if (idx >= 0) window.INITIAL_LOCAL_PACKAGES[idx] = p;
          else window.INITIAL_LOCAL_PACKAGES.push(p);
        }
        const pkgCarBadge = p.default_car_type ? `<div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: ${String(p.default_car_type).toUpperCase()}</span></div>` : '';
        if (isEdit) {
          const row = document.getElementById(`admin-local-pkg-row-${p.id}`);
          if (row) {
            row.dataset.status = p.is_active ? 'active' : 'inactive';
            row.dataset.search = `${p.package_code} ${p.name} ${p.city}`.toLowerCase();
            row.cells[0].innerHTML = `<div style="font-weight:700; color:#0f172a;">${p.name}</div><div style="font-size:11px; font-family:monospace; color:#64748b;">Code: <strong>${p.package_code}</strong></div>`;
            row.cells[1].innerHTML = `<span class="admin-badge badge-info" style="font-size:11px;">📍 ${p.city}</span>`;
            row.cells[2].innerHTML = `<span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${p.duration_hours} hrs &bull; 🛣️ ${p.included_km} km</span>`;
            row.cells[3].innerHTML = `<strong style="color:var(--primary); font-size:13px;">₹${Number(p.sedan_fare).toLocaleString('en-IN')}</strong>`;
            row.cells[4].innerHTML = `<strong style="color:var(--primary); font-size:13px;">₹${Number(p.suv_fare).toLocaleString('en-IN')}</strong>`;
            row.cells[5].innerHTML = `<span style="font-size:11px; color:#64748b;">+₹${Number(p.extra_km_rate).toLocaleString('en-IN')}/km &bull; +₹${Number(p.extra_hour_rate).toLocaleString('en-IN')}/hr</span>${pkgCarBadge}`;
            row.cells[6].innerHTML = `<span class="admin-badge ${p.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleLocalPackageStatus(${p.id})" id="local-pkg-status-badge-${p.id}" title="Click to toggle status">${p.is_active ? 'Active' : 'Inactive'}</span>`;
          }
        } else {
          const tbody = document.getElementById('admin-local-packages-tbody');
          if (tbody) {
            const tr = document.createElement('tr');
            tr.id = `admin-local-pkg-row-${p.id}`;
            tr.dataset.status = p.is_active ? 'active' : 'inactive';
            tr.dataset.search = `${p.package_code} ${p.name} ${p.city}`.toLowerCase();
            tr.innerHTML = `
                <td>
                  <div style="font-weight:700; color:#0f172a;">${p.name}</div>
                  <div style="font-size:11px; font-family:monospace; color:#64748b;">Code: <strong>${p.package_code}</strong></div>
                </td>
                <td>
                  <span class="admin-badge badge-info" style="font-size:11px;">📍 ${p.city}</span>
                </td>
                <td>
                  <span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${p.duration_hours} hrs &bull; 🛣️ ${p.included_km} km</span>
                </td>
                <td>
                  <strong style="color:var(--primary); font-size:13px;">₹${Number(p.sedan_fare).toLocaleString('en-IN')}</strong>
                </td>
                <td>
                  <strong style="color:var(--primary); font-size:13px;">₹${Number(p.suv_fare).toLocaleString('en-IN')}</strong>
                </td>
                <td>
                  <span style="font-size:11px; color:#64748b;">+₹${Number(p.extra_km_rate).toLocaleString('en-IN')}/km &bull; +₹${Number(p.extra_hour_rate).toLocaleString('en-IN')}/hr</span>${pkgCarBadge}
                </td>
                <td>
                  <span class="admin-badge ${p.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleLocalPackageStatus(${p.id})" id="local-pkg-status-badge-${p.id}" title="Click to toggle status">${p.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td style="text-align:right;">
                  <div class="admin-action-btn-group" style="justify-content:flex-end;">
                    <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditLocalPackageModal(${p.id})" title="Edit Package">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteLocalPackage(${p.id})" title="Delete Package">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              `;
            tbody.prepend(tr);
          }
        }
        const totalPkgs = document.querySelectorAll('#admin-local-packages-tbody tr[id^="admin-local-pkg-row-"]').length;
        const countPill = document.getElementById('admin-local-packages-filter-count');
        if (countPill) countPill.textContent = `${totalPkgs} Packages`;
        const tabCount = document.getElementById('admin-tab-count-local');
        if (tabCount) tabCount.textContent = totalPkgs;
        const topPill = document.getElementById('admin-bt-total-packages-pill');
        if (topPill) topPill.textContent = `${totalPkgs} Local Pkgs`;
      }
    } else {
      showToast(data.message || 'Error saving local package.', 'danger');
    }
  } catch (err) {
    console.error('Save local package error:', err);
    showToast('Network error while saving local package.', 'warning');
  }
};

window.toggleLocalPackageStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/local-packages/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      const badge = document.getElementById(`local-pkg-status-badge-${id}`);
      const row = document.getElementById(`admin-local-pkg-row-${id}`);
      if (badge && row) {
        const isActive = data.is_active;
        badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
        badge.textContent = isActive ? 'Active' : 'Inactive';
        row.dataset.status = isActive ? 'active' : 'inactive';
      }
    }
  } catch (err) {
    showToast('Failed to toggle package status.', 'warning');
  }
};

window.deleteLocalPackage = async function (id) {
  if (!confirm('Are you sure you want to delete this local package?')) return;
  try {
    const res = await fetch(`/api/admin/local-packages/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-local-pkg-row-${id}`);
      if (row) row.remove();
      const totalPkgs = document.querySelectorAll('#admin-local-packages-tbody tr[id^="admin-local-pkg-row-"]').length;
      const countPill = document.getElementById('admin-local-packages-filter-count');
      if (countPill) countPill.textContent = `${totalPkgs} Packages`;
      const tabCount = document.getElementById('admin-tab-count-local');
      if (tabCount) tabCount.textContent = totalPkgs;
      const topPill = document.getElementById('admin-bt-total-packages-pill');
      if (topPill) topPill.textContent = `${totalPkgs} Local Pkgs`;
    }
  } catch (err) {
    showToast('Failed to delete local package.', 'warning');
  }
};

window.deleteLocalPackageFromModal = function () {
  const id = document.getElementById('admin-local-pkg-id').value;
  if (id) {
    closeModal('admin-local-package-modal');
    deleteLocalPackage(id);
  }
};

window.handleLocalPackageSearch = function (q) {
  const query = (q || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#admin-local-packages-tbody tr');
  let visibleCount = 0;
  rows.forEach(r => {
    const text = (r.dataset.search || '').toLowerCase();
    const statusFilter = document.getElementById('admin-local-pkg-filter-status')?.value || 'all';
    const status = r.dataset.status;
    const matchesSearch = !query || text.includes(query);
    const matchesStatus = statusFilter === 'all' || status === statusFilter;

    if (matchesSearch && matchesStatus) {
      r.style.display = '';
      visibleCount++;
    } else {
      r.style.display = 'none';
    }
  });
  const countEl = document.getElementById('admin-local-packages-filter-count');
  if (countEl) countEl.textContent = `${visibleCount} Packages`;
};

window.handleLocalPackageFilter = function () {
  const q = document.getElementById('admin-local-pkg-search-input')?.value || '';
  handleLocalPackageSearch(q);
};

// Airport Transit Hubs Handlers
window.openAddAirportHubModal = function () {
  document.getElementById('admin-airport-modal-title').textContent = 'Add Airport Transit Hub';
  document.getElementById('admin-airport-id').value = '';
  document.getElementById('admin-airport-name').value = '';
  document.getElementById('admin-airport-code').value = '';
  document.getElementById('admin-airport-city').value = '';
  document.getElementById('admin-airport-terminals').value = 'Terminal 1 & Terminal 2';
  document.getElementById('admin-airport-pickup-surcharge').value = '250';
  document.getElementById('admin-airport-drop-surcharge').value = '150';
  document.getElementById('admin-airport-sedan').value = '950';
  document.getElementById('admin-airport-suv').value = '1450';
  if (document.getElementById('admin-airport-default-car-type')) document.getElementById('admin-airport-default-car-type').value = '';
  document.getElementById('admin-airport-active').checked = true;

  const delBtn = document.getElementById('admin-airport-delete-btn');
  if (delBtn) delBtn.style.display = 'none';

  const modal = document.getElementById('admin-airport-hub-modal');
  if (modal) modal.classList.add('active');
};

window.openEditAirportHubModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/airport-hubs/${id}`);
    const data = await res.json();
    if (!data.success || !data.airport) return;

    const a = data.airport;
    document.getElementById('admin-airport-modal-title').textContent = `Edit Airport Hub: ${a.airport_name} (${a.iata_code})`;
    document.getElementById('admin-airport-id').value = a.id;
    document.getElementById('admin-airport-name').value = a.airport_name;
    document.getElementById('admin-airport-code').value = a.iata_code;
    document.getElementById('admin-airport-city').value = a.city;
    document.getElementById('admin-airport-terminals').value = a.terminals || '';
    document.getElementById('admin-airport-pickup-surcharge').value = a.pickup_surcharge || 250;
    document.getElementById('admin-airport-drop-surcharge').value = a.drop_surcharge || 150;
    document.getElementById('admin-airport-sedan').value = a.sedan_base_fare || 950;
    document.getElementById('admin-airport-suv').value = a.suv_base_fare || 1450;
    if (document.getElementById('admin-airport-default-car-type')) document.getElementById('admin-airport-default-car-type').value = (a.default_car_type || '').toLowerCase();
    document.getElementById('admin-airport-active').checked = Boolean(a.is_active);

    const delBtn = document.getElementById('admin-airport-delete-btn');
    if (delBtn) delBtn.style.display = 'inline-flex';

    const modal = document.getElementById('admin-airport-hub-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('Fetch airport hub error:', err);
    showToast('Failed to load airport hub details.', 'warning');
  }
};

window.saveAirportHub = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-airport-id').value;
  const isEdit = Boolean(id);

  const payload = {
    airport_name: document.getElementById('admin-airport-name').value.trim(),
    iata_code: document.getElementById('admin-airport-code').value.trim().toUpperCase(),
    city: document.getElementById('admin-airport-city').value.trim(),
    terminals: document.getElementById('admin-airport-terminals').value.trim() || 'Main Terminal',
    pickup_surcharge: parseFloat(document.getElementById('admin-airport-pickup-surcharge').value) || 250,
    drop_surcharge: parseFloat(document.getElementById('admin-airport-drop-surcharge').value) || 150,
    sedan_base_fare: parseFloat(document.getElementById('admin-airport-sedan').value) || 950,
    suv_base_fare: parseFloat(document.getElementById('admin-airport-suv').value) || 1450,
    default_car_type: document.getElementById('admin-airport-default-car-type')?.value || null,
    is_active: document.getElementById('admin-airport-active').checked,
  };

  const url = isEdit ? `/api/admin/airport-hubs/${id}` : '/api/admin/airport-hubs';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-airport-hub-modal');
      const a = data.airport;
      if (a) {
        if (Array.isArray(window.INITIAL_AIRPORT_HUBS)) {
          const idx = window.INITIAL_AIRPORT_HUBS.findIndex(item => item.id === a.id);
          if (idx >= 0) window.INITIAL_AIRPORT_HUBS[idx] = a;
          else window.INITIAL_AIRPORT_HUBS.push(a);
        }
        const hubCarBadge = a.default_car_type ? `<div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: ${String(a.default_car_type).toUpperCase()}</span></div>` : '';
        if (isEdit) {
          const row = document.getElementById(`admin-airport-row-${a.id}`);
          if (row) {
            row.dataset.status = a.is_active ? 'active' : 'inactive';
            row.dataset.search = `${a.airport_name} ${a.iata_code} ${a.city}`.toLowerCase();
            row.cells[0].innerHTML = `
                <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                  <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">flight</span>
                  <span>${a.airport_name}</span>
                </div>
                <div style="font-size:11px; font-family:monospace; color:#64748b;">IATA: <strong style="color:var(--primary);">${a.iata_code}</strong></div>
              `;
            row.cells[1].innerHTML = `<div style="font-weight:600; color:#1e293b;">📍 ${a.city}</div><div style="font-size:11px; color:#64748b;">${a.terminals}</div>`;
            row.cells[2].innerHTML = `<strong style="color:#0f172a;">₹${Number(a.pickup_surcharge).toLocaleString('en-IN')}</strong><div style="font-size:10px; color:#64748b;">Parking + Entry</div>`;
            row.cells[3].innerHTML = `<strong style="color:#0f172a;">₹${Number(a.drop_surcharge).toLocaleString('en-IN')}</strong><div style="font-size:10px; color:#64748b;">Departure Toll</div>`;
            row.cells[4].innerHTML = `<span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${Number(a.sedan_base_fare).toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${Number(a.suv_base_fare).toLocaleString('en-IN')}</strong></span>${hubCarBadge}`;
            row.cells[5].innerHTML = `<span class="admin-badge ${a.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleAirportHubStatus(${a.id})" id="airport-status-badge-${a.id}" title="Click to toggle status">${a.is_active ? 'Active' : 'Inactive'}</span>`;
          }
        } else {
          const tbody = document.getElementById('admin-airports-tbody');
          if (tbody) {
            const tr = document.createElement('tr');
            tr.id = `admin-airport-row-${a.id}`;
            tr.dataset.status = a.is_active ? 'active' : 'inactive';
            tr.dataset.search = `${a.airport_name} ${a.iata_code} ${a.city}`.toLowerCase();
            tr.innerHTML = `
                <td>
                  <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">flight</span>
                    <span>${a.airport_name}</span>
                  </div>
                  <div style="font-size:11px; font-family:monospace; color:#64748b;">IATA: <strong style="color:var(--primary);">${a.iata_code}</strong></div>
                </td>
                <td>
                  <div style="font-weight:600; color:#1e293b;">📍 ${a.city}</div>
                  <div style="font-size:11px; color:#64748b;">${a.terminals}</div>
                </td>
                <td>
                  <strong style="color:#0f172a;">₹${Number(a.pickup_surcharge).toLocaleString('en-IN')}</strong>
                  <div style="font-size:10px; color:#64748b;">Parking + Entry</div>
                </td>
                <td>
                  <strong style="color:#0f172a;">₹${Number(a.drop_surcharge).toLocaleString('en-IN')}</strong>
                  <div style="font-size:10px; color:#64748b;">Departure Toll</div>
                </td>
                <td>
                  <span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${Number(a.sedan_base_fare).toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${Number(a.suv_base_fare).toLocaleString('en-IN')}</strong></span>${hubCarBadge}
                </td>
                <td>
                  <span class="admin-badge ${a.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleAirportHubStatus(${a.id})" id="airport-status-badge-${a.id}" title="Click to toggle status">${a.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td style="text-align:right;">
                  <div class="admin-action-btn-group" style="justify-content:flex-end;">
                    <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditAirportHubModal(${a.id})" title="Edit Airport">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteAirportHub(${a.id})" title="Delete Airport">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              `;
            tbody.prepend(tr);
          }
        }
        const totalAirports = document.querySelectorAll('#admin-airports-tbody tr[id^="admin-airport-row-"]').length;
        const countPill = document.getElementById('admin-airports-filter-count');
        if (countPill) countPill.textContent = `${totalAirports} Airports`;
        const tabCount = document.getElementById('admin-tab-count-airport');
        if (tabCount) tabCount.textContent = totalAirports;
        const topPill = document.getElementById('admin-bt-total-airports-pill');
        if (topPill) topPill.textContent = `${totalAirports} Airports`;
      }
    } else {
      showToast(data.message || 'Error saving airport hub.', 'danger');
    }
  } catch (err) {
    console.error('Save airport hub error:', err);
    showToast('Network error while saving airport hub.', 'warning');
  }
};

window.toggleAirportHubStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/airport-hubs/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      const badge = document.getElementById(`airport-status-badge-${id}`);
      const row = document.getElementById(`admin-airport-row-${id}`);
      if (badge && row) {
        const isActive = data.is_active;
        badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
        badge.textContent = isActive ? 'Active' : 'Inactive';
        row.dataset.status = isActive ? 'active' : 'inactive';
      }
    }
  } catch (err) {
    showToast('Failed to toggle airport status.', 'warning');
  }
};

window.saveBookingTypeCarTypeRule = async function (tripType, carType) {
  try {
    const res = await fetch('/api/admin/settings/booking-type-car-types', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify({ [tripType]: carType || '' })
    });
    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      const val = (carType || '').toLowerCase();
      const syncIds = {
        oneway: ['rule-car-type-oneway', 'bt-oneway-default-car-select'],
        roundtrip: ['rule-car-type-roundtrip', 'bt-rt-default-car-select'],
        local: ['rule-car-type-local'],
        airport: ['rule-car-type-airport'],
      };
      (syncIds[tripType] || []).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = val;
      });
      showToast(data.message || 'Car type preference saved.', 'success');
    } else {
      showToast(data.message || 'Failed to save car type preference.', 'warning');
    }
  } catch (err) {
    showToast('Network error while saving car type preference.', 'warning');
  }
};

window.deleteAirportHub = async function (id) {
  if (!confirm('Are you sure you want to delete this airport hub?')) return;
  try {
    const res = await fetch(`/api/admin/airport-hubs/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-airport-row-${id}`);
      if (row) row.remove();
      const totalAirports = document.querySelectorAll('#admin-airports-tbody tr[id^="admin-airport-row-"]').length;
      const countPill = document.getElementById('admin-airports-filter-count');
      if (countPill) countPill.textContent = `${totalAirports} Airports`;
      const tabCount = document.getElementById('admin-tab-count-airport');
      if (tabCount) tabCount.textContent = totalAirports;
      const topPill = document.getElementById('admin-bt-total-airports-pill');
      if (topPill) topPill.textContent = `${totalAirports} Airports`;
    }
  } catch (err) {
    showToast('Failed to delete airport hub.', 'warning');
  }
};

window.deleteAirportHubFromModal = function () {
  const id = document.getElementById('admin-airport-id').value;
  if (id) {
    closeModal('admin-airport-hub-modal');
    deleteAirportHub(id);
  }
};

window.handleAirportHubSearch = function (q) {
  const query = (q || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#admin-airports-tbody tr');
  let visibleCount = 0;
  rows.forEach(r => {
    const text = (r.dataset.search || '').toLowerCase();
    const statusFilter = document.getElementById('admin-airport-filter-status')?.value || 'all';
    const status = r.dataset.status;
    const matchesSearch = !query || text.includes(query);
    const matchesStatus = statusFilter === 'all' || status === statusFilter;

    if (matchesSearch && matchesStatus) {
      r.style.display = '';
      visibleCount++;
    } else {
      r.style.display = 'none';
    }
  });
  const countEl = document.getElementById('admin-airports-filter-count');
  if (countEl) countEl.textContent = `${visibleCount} Airports`;
};

window.handleAirportHubFilter = function () {
  const q = document.getElementById('admin-airport-search-input')?.value || '';
  handleAirportHubSearch(q);
};

// ========================================================
// 2. BERTH ROUTES MANAGEMENT (Admin)
// ========================================================
window.openAddBerthRouteModal = function () {
  document.getElementById('admin-broute-modal-title').textContent = 'Add Berth Route';
  document.getElementById('admin-broute-id').value = '';
  document.getElementById('admin-broute-from').value = '';
  document.getElementById('admin-broute-to').value = '';
  document.getElementById('admin-broute-operator').value = 'Intercity Express';
  document.getElementById('admin-broute-coach').value = '';
  document.getElementById('admin-broute-vehicle').value = 'Volvo Multi-Axle AC Sleeper';
  document.getElementById('admin-broute-dept').value = '09:30 PM';
  document.getElementById('admin-broute-arr').value = '06:45 AM';
  document.getElementById('admin-broute-price').value = '850';
  document.getElementById('admin-broute-total-berths').value = '30';
  document.getElementById('admin-broute-active').checked = true;

  const modal = document.getElementById('admin-berth-route-modal');
  if (modal) modal.classList.add('active');
};

window.openEditBerthRouteModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/berth-routes/${id}`);
    const data = await res.json();
    if (!data.success || !data.route) return;

    const r = data.route;
    document.getElementById('admin-broute-modal-title').textContent = `Edit Berth Route: ${r.from_city} → ${r.to_city}`;
    document.getElementById('admin-broute-id').value = r.id;
    document.getElementById('admin-broute-from').value = r.from_city;
    document.getElementById('admin-broute-to').value = r.to_city;
    document.getElementById('admin-broute-operator').value = r.operator_name;
    document.getElementById('admin-broute-coach').value = r.coach_code;
    document.getElementById('admin-broute-vehicle').value = r.vehicle_type;
    document.getElementById('admin-broute-dept').value = r.departure_time;
    document.getElementById('admin-broute-arr').value = r.arrival_time;
    document.getElementById('admin-broute-price').value = r.base_price;
    document.getElementById('admin-broute-total-berths').value = r.total_berths;
    document.getElementById('admin-broute-active').checked = Boolean(r.is_active);

    const modal = document.getElementById('admin-berth-route-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    showToast('Failed to load berth route.', 'warning');
  }
};

window.saveAdminBerthRoute = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-broute-id').value;
  const isEdit = Boolean(id);

  const payload = {
    from_city: document.getElementById('admin-broute-from').value.trim(),
    to_city: document.getElementById('admin-broute-to').value.trim(),
    operator_name: document.getElementById('admin-broute-operator').value.trim(),
    coach_code: document.getElementById('admin-broute-coach').value.trim(),
    vehicle_type: document.getElementById('admin-broute-vehicle').value.trim(),
    departure_time: document.getElementById('admin-broute-dept').value.trim(),
    arrival_time: document.getElementById('admin-broute-arr').value.trim(),
    base_price: parseFloat(document.getElementById('admin-broute-price').value),
    total_berths: parseInt(document.getElementById('admin-broute-total-berths').value, 10),
    is_active: document.getElementById('admin-broute-active').checked,
  };

  const url = isEdit ? `/api/admin/berth-routes/${id}` : '/api/admin/berth-routes';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-berth-route-modal');
      const br = data.route;
      if (br) {
        if (isEdit) {
          const row = document.getElementById(`admin-broute-row-${br.id}`);
          if (row) {
            row.dataset.search = `${br.from_city} ${br.to_city} ${br.operator_name} ${br.coach_code || ''}`.toLowerCase();
            row.cells[0].innerHTML = `<strong>${br.from_city} → ${br.to_city}</strong><div style="font-size:11px; color:#64748b;">${br.vehicle_type}</div>`;
            row.cells[1].innerHTML = `<span class="admin-badge badge-primary">${br.coach_code || 'N/A'}</span>`;
            row.cells[2].textContent = br.operator_name;
            row.cells[3].textContent = `${br.departure_time} - ${br.arrival_time}`;
            row.cells[4].innerHTML = `<strong>₹${Number(br.base_price).toLocaleString('en-IN')}</strong>`;
            row.cells[5].innerHTML = br.is_active ? `<span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleBerthRouteStatus(${br.id})" id="broute-status-badge-${br.id}" title="Click to toggle">Active</span>` : `<span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleBerthRouteStatus(${br.id})" id="broute-status-badge-${br.id}" title="Click to toggle">Inactive</span>`;
          }
        } else {
          const tbody = document.querySelector('#admin-berth-routes-table tbody');
          if (tbody) {
            const tr = document.createElement('tr');
            tr.id = `admin-broute-row-${br.id}`;
            tr.dataset.search = `${br.from_city} ${br.to_city} ${br.operator_name} ${br.coach_code || ''}`.toLowerCase();
            tr.innerHTML = `
                <td><strong>${br.from_city} → ${br.to_city}</strong><div style="font-size:11px; color:#64748b;">${br.vehicle_type}</div></td>
                <td><span class="admin-badge badge-primary">${br.coach_code || 'N/A'}</span></td>
                <td>${br.operator_name}</td>
                <td>${br.departure_time} - ${br.arrival_time}</td>
                <td><strong>₹${Number(br.base_price).toLocaleString('en-IN')}</strong></td>
                <td><span class="admin-badge ${br.is_active ? 'badge-success' : 'badge-danger'}" style="cursor:pointer;" onclick="toggleBerthRouteStatus(${br.id})" id="broute-status-badge-${br.id}" title="Click to toggle">${br.is_active ? 'Active' : 'Inactive'}</span></td>
                <td style="text-align:right;">
                  <div class="admin-action-btn-group" style="justify-content:flex-end;">
                    <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditBerthRouteModal(${br.id})" title="Edit Route">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteBerthRoute(${br.id})" title="Delete Route">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              `;
            tbody.prepend(tr);
          }
        }
        const totalBroutes = document.querySelectorAll('#admin-berth-routes-table tbody tr[id^="admin-broute-row-"]').length;
        const navBadge = document.getElementById('admin-badge-berth-routes-count');
        if (navBadge) navBadge.textContent = totalBroutes;
      }
    } else {
      showToast(data.message || 'Validation error while saving route.', 'danger');
    }
  } catch (err) {
    showToast('Network error while saving berth route.', 'warning');
  }
};

window.toggleBerthRouteStatus = async function (id) {
  try {
    const res = await fetch(`/api/admin/berth-routes/${id}/toggle`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      const badge = document.getElementById(`broute-status-badge-${id}`) || document.querySelector(`#admin-broute-row-${id} td:nth-child(6) .admin-badge`);
      if (badge && data.route) {
        const isActive = data.route.is_active;
        badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
        badge.textContent = isActive ? 'Active' : 'Inactive';
      }
    }
  } catch (err) {
    showToast('Failed to toggle berth route.', 'warning');
  }
};

window.deleteBerthRoute = async function (id) {
  if (!confirm('Are you sure you want to delete this berth route?')) return;
  try {
    const res = await fetch(`/api/admin/berth-routes/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-broute-row-${id}`);
      if (row) row.remove();
      const totalBroutes = document.querySelectorAll('#admin-berth-routes-table tbody tr[id^="admin-broute-row-"]').length;
      const navBadge = document.getElementById('admin-badge-berth-routes-count');
      if (navBadge) navBadge.textContent = totalBroutes;
    }
  } catch (err) {
    showToast('Failed to delete berth route.', 'warning');
  }
};

window.jumpToBerthLayout = function (coachCode) {
  switchAdminSection('berth-layouts');
  const filter = document.getElementById('admin-berth-coach-filter');
  if (filter) {
    filter.value = coachCode;
    filterBerthsByCoach(coachCode);
  }
};

// ========================================================
// 3. VEHICLE BERTH & SEAT CUSTOMIZER (Admin)
// ========================================================
window.filterBerthsByCoach = function (coachCode) {
  const rows = document.querySelectorAll('.admin-berth-row');
  rows.forEach(r => {
    if (coachCode === 'all' || r.dataset.coach === coachCode) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-berths', true);
  }
};

window.openAddBerthModal = function () {
  document.getElementById('admin-berth-modal-title').textContent = 'Add Custom Berth to Vehicle';
  document.getElementById('admin-berth-id').value = '';
  const activeFilter = document.getElementById('admin-berth-coach-filter')?.value;
  document.getElementById('admin-berth-coach').value = (activeFilter && activeFilter !== 'all') ? activeFilter : '8092';
  document.getElementById('admin-berth-operator').value = 'Intercity Express';
  document.getElementById('admin-berth-num').value = '';
  document.getElementById('admin-berth-deck').value = 'lower';
  document.getElementById('admin-berth-type').value = 'window';
  document.getElementById('admin-berth-price').value = '850';
  document.getElementById('admin-berth-ladies').checked = false;
  document.getElementById('admin-berth-booked').checked = false;

  const modal = document.getElementById('admin-berth-modal');
  if (modal) modal.classList.add('active');
};

window.openEditBerthModal = async function (id) {
  try {
    const res = await fetch(`/api/admin/berths/${id}`);
    const data = await res.json();
    if (!data.success || !data.berth) return;

    const b = data.berth;
    document.getElementById('admin-berth-modal-title').textContent = `Edit Berth: ${b.berth_number} (Vehicle ${b.coach_code})`;
    document.getElementById('admin-berth-id').value = b.id;
    document.getElementById('admin-berth-coach').value = b.coach_code;
    document.getElementById('admin-berth-operator').value = b.operator_name;
    document.getElementById('admin-berth-num').value = b.berth_number;
    document.getElementById('admin-berth-deck').value = b.deck;
    document.getElementById('admin-berth-type').value = b.berth_type;
    document.getElementById('admin-berth-price').value = b.price;
    document.getElementById('admin-berth-ladies').checked = Boolean(b.is_ladies_quota);
    document.getElementById('admin-berth-booked').checked = Boolean(b.is_booked);

    const modal = document.getElementById('admin-berth-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    showToast('Failed to load berth details.', 'warning');
  }
};

window.saveAdminBerth = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-berth-id').value;
  const isEdit = Boolean(id);

  const payload = {
    coach_code: document.getElementById('admin-berth-coach').value.trim(),
    operator_name: document.getElementById('admin-berth-operator').value.trim(),
    deck: document.getElementById('admin-berth-deck').value,
    berth_number: document.getElementById('admin-berth-num').value.trim().toUpperCase(),
    berth_type: document.getElementById('admin-berth-type').value,
    price: parseFloat(document.getElementById('admin-berth-price').value),
    is_ladies_quota: document.getElementById('admin-berth-ladies').checked,
    is_booked: document.getElementById('admin-berth-booked').checked,
  };

  const url = isEdit ? `/api/admin/berths/${id}` : '/api/admin/berths';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-berth-modal');
      const b = data.berth;
      if (b) {
        if (isEdit) {
          const row = document.getElementById(`admin-berth-row-${b.id}`);
          if (row) {
            row.dataset.coach = b.coach_code;
            row.cells[0].textContent = b.berth_number;
            row.cells[1].innerHTML = `<span class="admin-badge badge-primary">${b.coach_code}</span>`;
            row.cells[2].textContent = b.deck.toUpperCase();
            row.cells[3].textContent = b.berth_type;
            row.cells[4].innerHTML = `<strong>₹${Number(b.price).toLocaleString('en-IN')}</strong>`;
            row.cells[5].innerHTML = b.is_booked ? `<span class="admin-badge badge-danger">Booked</span>` : `<span class="admin-badge badge-success">Available</span>`;
          }
        } else {
          const tbody = document.querySelector('#admin-berths-table tbody');
          if (tbody) {
            const tr = document.createElement('tr');
            tr.id = `admin-berth-row-${b.id}`;
            tr.className = 'admin-berth-row';
            tr.dataset.coach = b.coach_code;
            tr.innerHTML = `
                <td><strong>${b.berth_number}</strong></td>
                <td><span class="admin-badge badge-primary">${b.coach_code}</span></td>
                <td>${b.deck.toUpperCase()}</td>
                <td>${b.berth_type}</td>
                <td><strong>₹${Number(b.price).toLocaleString('en-IN')}</strong></td>
                <td>${b.is_ladies_quota ? '<span class="admin-badge badge-warning">Ladies</span>' : '<span style="color:#94a3b8; font-size:11px;">General</span>'}</td>
                <td>${b.is_booked ? '<span class="admin-badge badge-danger">Booked</span>' : '<span class="admin-badge badge-success">Available</span>'}</td>
                <td style="text-align:right;">
                  <div class="admin-action-btn-group" style="justify-content:flex-end;">
                    <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditBerthModal(${b.id})" title="Edit Berth">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteBerth(${b.id})" title="Delete Berth">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              `;
            tbody.prepend(tr);
          }
        }
        const totalBerths = document.querySelectorAll('#admin-berths-table tbody tr[id^="admin-berth-row-"]').length;
        const navBadge = document.getElementById('admin-badge-berths-count');
        if (navBadge) navBadge.textContent = totalBerths;
      }
    } else {
      showToast(data.message || 'Error saving berth configuration.', 'danger');
    }
  } catch (err) {
    showToast('Network error while saving berth.', 'warning');
  }
};

window.deleteBerth = async function (id) {
  if (!confirm('Are you sure you want to remove this berth from the vehicle?')) return;
  try {
    const res = await fetch(`/api/admin/berths/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-berth-row-${id}`);
      if (row) row.remove();
      const totalBerths = document.querySelectorAll('#admin-berths-table tbody tr[id^="admin-berth-row-"]').length;
      const navBadge = document.getElementById('admin-badge-berths-count');
      if (navBadge) navBadge.textContent = totalBerths;
    }
  } catch (err) {
    showToast('Failed to delete berth.', 'warning');
  }
};

// ========================================================
// 4. DRIVER VEHICLE LISTING & FLEET APPROVALS (Admin + Driver)
// ========================================================
function updateVehiclePendingBadgeInDOM() {
  const pendingCount = document.querySelectorAll('.admin-veh-row[data-status="Pending"]').length;
  const badge = document.getElementById('admin-badge-pending-vehicles');
  if (badge) {
    if (pendingCount > 0) {
      badge.className = 'admin-badge badge-danger';
      badge.textContent = `${pendingCount} Pending`;
    } else {
      const totalCount = document.querySelectorAll('.admin-veh-row').length;
      badge.className = 'admin-badge badge-success';
      badge.textContent = `${totalCount} Cars`;
    }
  }
}

function updateVehicleRowStatusInDOM(id, status, notes) {
  const row = document.getElementById(`admin-veh-row-${id}`);
  if (row) {
    row.dataset.status = status;
    const statusCell = document.getElementById(`admin-veh-status-cell-${id}`);
    if (statusCell) {
      if (status === 'Approved') {
        statusCell.innerHTML = `<span class="admin-badge badge-success">Approved</span>`;
      } else if (status === 'Pending') {
        statusCell.innerHTML = `<span class="admin-badge badge-warning">Pending Approval</span>`;
      } else {
        statusCell.innerHTML = `<span class="admin-badge badge-danger">Rejected</span>`;
      }
    }

    // Remove approve & reject buttons smoothly
    const approveBtn = row.querySelector(`.btn-veh-approve-${id}`);
    const rejectBtn = row.querySelector(`.btn-veh-reject-${id}`);
    if (approveBtn) approveBtn.remove();
    if (rejectBtn) rejectBtn.remove();
  }

  // Update driver dashboard card if present
  const driverBadge = document.getElementById(`driver-my-veh-badge-${id}`);
  if (driverBadge) {
    if (status === 'Approved') {
      driverBadge.innerHTML = `<span class="admin-badge badge-success" style="font-size:10.5px; padding:4px 8px;">✅ Approved</span>`;
    } else if (status === 'Pending') {
      driverBadge.innerHTML = `<span class="admin-badge badge-warning" style="font-size:10.5px; padding:4px 8px;">⏳ Pending Approval</span>`;
    } else {
      driverBadge.innerHTML = `<span class="admin-badge badge-danger" style="font-size:10.5px; padding:4px 8px;">❌ Rejected</span>`;
    }
  }
  const driverNotes = document.getElementById(`driver-veh-notes-${id}`);
  if (driverNotes && notes) {
    driverNotes.textContent = `Admin Note: ${notes}`;
    driverNotes.style.display = 'block';
  }

  updateVehiclePendingBadgeInDOM();
}

// Renders the amenity chips stored on a vehicle (shared by the fleet cards
// and the admin table row appended after a driver lists a new car).
window.vehicleFeatureChips = function (features, limit = 3) {
  const list = Array.isArray(features) ? features.filter((f) => typeof f === 'string' && f.trim() !== '') : [];
  if (!list.length) return '';

  const escape = (value) => String(value).replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[ch]));

  const chips = list.slice(0, limit).map((feature) =>
    `<span class="admin-badge badge-secondary" style="font-size:9.5px; padding:2px 5px; white-space:nowrap;">${escape(feature)}</span>`
  );

  if (list.length > limit) {
    chips.push(`<span class="admin-badge badge-secondary" style="font-size:9.5px; padding:2px 5px;">+${list.length - limit}</span>`);
  }

  return `<div style="display:flex; flex-wrap:wrap; gap:3px; margin-top:4px; max-width:200px;">${chips.join('')}</div>`;
};

window.openDriverListVehicleModal = function () {
  const modal = document.getElementById('driver-list-vehicle-modal');
  if (modal) modal.classList.add('active');
};

window.submitDriverNewVehicle = async function (e) {
  e.preventDefault();
  const payload = {
    vehicle_model: document.getElementById('driver-new-veh-model').value.trim(),
    vehicle_plate: document.getElementById('driver-new-veh-plate').value.trim().toUpperCase(),
    fleet_category: document.getElementById('driver-new-veh-category').value,
    seating_capacity: parseInt(document.getElementById('driver-new-veh-seats').value, 10),
    berth_capacity: parseInt(document.getElementById('driver-new-veh-berths').value || 0, 10),
    fuel_type: document.getElementById('driver-new-veh-fuel').value,
    model_year: document.getElementById('driver-new-veh-year').value.trim(),
    rc_number: document.getElementById('driver-new-veh-rc').value.trim(),
    features: Array.from(document.querySelectorAll('#driver-new-veh-features input[type="checkbox"]:checked')).map((cb) => cb.value),
  };

  try {
    const res = await fetch('/api/driver/vehicles', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('driver-list-vehicle-modal');
      document.getElementById('driver-list-vehicle-form')?.reset();

      const v = data.vehicle;
      if (v) {
        // Prepend card to driver vehicle grid
        const grid = document.getElementById('driver-my-vehicles-grid');
        if (grid) {
          const emptyEl = grid.querySelector('div[style*="grid-column: 1 / -1"]');
          if (emptyEl) emptyEl.remove();

          const card = document.createElement('div');
          card.id = `driver-my-veh-card-${v.id}`;
          card.style.cssText = 'border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;';
          card.innerHTML = `
              <div>
                <div style="font-weight:700; font-size:13px; color:#0f172a;" id="driver-veh-title-${v.id}">${v.vehicle_model}</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                  <span style="font-family:monospace; font-weight:700; color:#8c5d1e;">${v.vehicle_plate}</span> &bull; 
                  <span>${v.fleet_category.toUpperCase()} (${v.seating_capacity} Seats${v.berth_capacity > 0 ? ', ' + v.berth_capacity + ' Berths' : ''})</span>
                </div>
                ${window.vehicleFeatureChips(v.features, 4)}
              </div>
              <div id="driver-my-veh-badge-${v.id}">
                <span class="admin-badge badge-warning" style="font-size:10.5px; padding:4px 8px;">⏳ Pending Approval</span>
              </div>
            `;
          grid.prepend(card);
        }

        // Prepend card to driver profile fleet grid as well
        const profileGrid = document.getElementById('driver-profile-vehicles-grid');
        if (profileGrid) {
          const emptyEl = profileGrid.querySelector('div[style*="grid-column:1 / -1"]');
          if (emptyEl) emptyEl.remove();

          const pcard = document.createElement('div');
          pcard.id = `driver-profile-veh-card-${v.id}`;
          pcard.style.cssText = 'border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; display:flex; justify-content:space-between; align-items:flex-start;';
          pcard.innerHTML = `
              <div>
                <div style="font-weight:700; font-size:13.5px; color:#0f172a;">${v.vehicle_model}</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:3px;">
                  <span style="font-family:monospace; font-weight:700; color:var(--primary);">${v.vehicle_plate}</span> &bull; 
                  <span>${v.fleet_category.toUpperCase()}</span>
                </div>
                <div style="font-size:11px; color:#64748b; margin-top:2px;">
                  <span>${v.seating_capacity} Seats${v.berth_capacity > 0 ? ', ' + v.berth_capacity + ' Berths' : ''}</span> &bull; 
                  <span>${v.fuel_type || 'Diesel'}</span>
                </div>
                ${window.vehicleFeatureChips(v.features, 4)}
              </div>
              <div>
                <span class="admin-badge badge-warning" style="font-size:10px; padding:3px 7px;">⏳ Pending</span>
              </div>
            `;
          profileGrid.prepend(pcard);
        }

        // Also prepend to admin table if visible
        const tbody = document.getElementById('admin-vehicles-tbody');
        if (tbody) {
          const tr = document.createElement('tr');
          tr.className = 'admin-veh-row';
          tr.dataset.status = 'Pending';
          tr.id = `admin-veh-row-${v.id}`;
          tr.innerHTML = `
              <td>
                <div style="font-weight:700; color:#0f172a;">${v.driver_name || 'Partner Chauffeur'}</div>
                <div style="font-size:11px; color:#64748b;">${v.driver_phone || '+91 Verified'}</div>
              </td>
              <td>
                <div style="font-weight:600; color:#0f172a;">${v.vehicle_model}</div>
                <div style="font-size:11px; color:#64748b;">Year: ${v.model_year} &bull; Fuel: ${v.fuel_type}</div>
              </td>
              <td>
                <span class="admin-badge badge-gold" style="font-family:monospace; font-size:11.5px;">${v.vehicle_plate}</span>
              </td>
              <td>
                <span class="admin-badge badge-primary">${v.fleet_category.toUpperCase()}</span>
              </td>
              <td>
                <div>${v.seating_capacity} Seats</div>
                ${v.berth_capacity > 0 ? `<div style="font-size:10.5px; color:#16a34a; font-weight:700;">${v.berth_capacity} Berths Fitted</div>` : ''}
              </td>
              <td>
                ${window.vehicleFeatureChips(v.features, 3) || '<span style="font-size:10.5px; color:#94a3b8;">Not listed</span>'}
              </td>
              <td style="font-family:monospace; font-size:11px;" id="admin-veh-rc-${v.id}">${v.rc_number || 'Pending Upload'}</td>
              <td id="admin-veh-status-cell-${v.id}">
                <span class="admin-badge badge-warning">Pending Approval</span>
              </td>
              <td style="text-align:right;">
                <div class="admin-action-btn-group" style="justify-content:flex-end;" id="admin-veh-actions-${v.id}">
                  <button type="button" class="btn btn-xs btn-primary btn-veh-approve-${v.id}" onclick="approveDriverVehicle(${v.id})" title="Approve Vehicle">
                    <span class="material-symbols-outlined" style="font-size:13px;">check</span>
                    <span>Approve</span>
                  </button>
                  <button type="button" class="btn btn-xs btn-outline btn-veh-reject-${v.id}" style="color:#ef4444; border-color:#fca5a5;" onclick="rejectDriverVehicle(${v.id})" title="Reject Vehicle">
                    <span class="material-symbols-outlined" style="font-size:13px;">close</span>
                    <span>Reject</span>
                  </button>
                  <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditVehicleModal(${v.id})" title="Edit Vehicle">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteVehicle(${v.id})" title="Delete Vehicle">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            `;
          tbody.prepend(tr);
          updateVehiclePendingBadgeInDOM();
        }
      }
    } else {
      showToast(data.message || 'Failed to submit vehicle.', 'danger');
    }
  } catch (err) {
    showToast('Network error while registering vehicle.', 'warning');
  }
};

window.filterAdminVehicles = function (status) {
  const rows = document.querySelectorAll('.admin-veh-row');
  rows.forEach(r => {
    if (status === 'all' || r.dataset.status === status) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-vehicles', true);
  }
};

window.reloadAdminVehicles = async function () {
  try {
    const res = await fetch('/api/admin/vehicles');
    const data = await res.json();
    if (data.success) {
      showToast('Vehicle fleet data refreshed.', 'info');
    }
  } catch (err) {
    showToast('Vehicles reloaded.', 'info');
  }
};

window.openEditVehicleModal = async function (id) {
  window.currentEditingVehicleId = id;
  try {
    const res = await fetch(`/api/admin/vehicles/${id}`);
    const data = await res.json();
    if (!data.success || !data.vehicle) return;

    const v = data.vehicle;
    document.getElementById('admin-veh-id').value = v.id;
    document.getElementById('admin-veh-driver-name').value = v.driver_name;
    document.getElementById('admin-veh-driver-phone').value = v.driver_phone;
    document.getElementById('admin-veh-model').value = v.vehicle_model;
    document.getElementById('admin-veh-plate').value = v.vehicle_plate;
    document.getElementById('admin-veh-category').value = v.fleet_category;
    document.getElementById('admin-veh-seats').value = v.seating_capacity;
    document.getElementById('admin-veh-berths').value = v.berth_capacity;
    document.getElementById('admin-veh-coach').value = v.coach_code || '';
    document.getElementById('admin-veh-fuel').value = v.fuel_type || 'Diesel';
    document.getElementById('admin-veh-year').value = v.model_year || '2023';
    document.getElementById('admin-veh-rc').value = v.rc_number || '';
    document.getElementById('admin-veh-status').value = v.status;
    document.getElementById('admin-veh-notes').value = v.admin_notes || '';
    document.getElementById('admin-veh-active').checked = Boolean(v.is_active);

    const features = Array.isArray(v.features) ? v.features : [];
    document.querySelectorAll('#admin-veh-features input[type="checkbox"]').forEach((cb) => {
      cb.checked = features.includes(cb.value);
    });

    const modal = document.getElementById('admin-vehicle-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    showToast('Failed to load vehicle details.', 'warning');
  }
};

window.saveAdminVehicle = async function (e) {
  e.preventDefault();
  const id = document.getElementById('admin-veh-id').value;
  const payload = {
    driver_name: document.getElementById('admin-veh-driver-name').value.trim(),
    driver_phone: document.getElementById('admin-veh-driver-phone').value.trim(),
    vehicle_model: document.getElementById('admin-veh-model').value.trim(),
    vehicle_plate: document.getElementById('admin-veh-plate').value.trim().toUpperCase(),
    fleet_category: document.getElementById('admin-veh-category').value,
    seating_capacity: parseInt(document.getElementById('admin-veh-seats').value, 10),
    berth_capacity: parseInt(document.getElementById('admin-veh-berths').value || 0, 10),
    coach_code: document.getElementById('admin-veh-coach').value.trim() || null,
    fuel_type: document.getElementById('admin-veh-fuel').value,
    model_year: document.getElementById('admin-veh-year').value.trim(),
    rc_number: document.getElementById('admin-veh-rc').value.trim() || null,
    features: Array.from(document.querySelectorAll('#admin-veh-features input[type="checkbox"]:checked')).map((cb) => cb.value),
    status: document.getElementById('admin-veh-status').value,
    admin_notes: document.getElementById('admin-veh-notes').value.trim() || null,
    is_active: document.getElementById('admin-veh-active').checked,
  };

  try {
    const res = await fetch(`/api/admin/vehicles/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message, 'success');
      closeModal('admin-vehicle-modal');
      const v = data.vehicle;
      if (v) {
        updateVehicleRowStatusInDOM(v.id, v.status, v.admin_notes || '');
        const row = document.getElementById(`admin-veh-row-${v.id}`);
        if (row) {
          row.cells[0].innerHTML = `<div style="font-weight:700; color:#0f172a;">${v.driver_name}</div><div style="font-size:11px; color:#64748b;">${v.driver_phone}</div>`;
          row.cells[1].innerHTML = `<div style="font-weight:600; color:#0f172a;">${v.vehicle_model}</div><div style="font-size:11px; color:#64748b;">Year: ${v.model_year} &bull; Fuel: ${v.fuel_type}</div>`;
          row.cells[2].innerHTML = `<span class="admin-badge badge-gold" style="font-family:monospace; font-size:11.5px;">${v.vehicle_plate}</span>`;
          row.cells[3].innerHTML = `<span class="admin-badge badge-primary">${v.fleet_category.toUpperCase()}</span>`;
          row.cells[4].innerHTML = `<div>${v.seating_capacity} Seats</div>${v.berth_capacity > 0 ? `<div style="font-size:10.5px; color:#16a34a; font-weight:700;">${v.berth_capacity} Berths Fitted</div>` : ''}`;
          const rcCell = document.getElementById(`admin-veh-rc-${v.id}`);
          if (rcCell) rcCell.textContent = v.rc_number || 'Pending Upload';
        }
      }
    } else {
      showToast(data.message || 'Failed to update vehicle.', 'danger');
    }
  } catch (err) {
    showToast('Network error while saving vehicle.', 'warning');
  }
};

window.approveDriverVehicle = async function (id) {
  try {
    const res = await fetch(`/api/admin/vehicles/${id}/approve`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      updateVehicleRowStatusInDOM(id, 'Approved');
    } else {
      showToast(data.message || 'Failed to approve vehicle.', 'danger');
    }
  } catch (err) {
    showToast('Failed to approve vehicle.', 'warning');
  }
};

window.rejectDriverVehicle = async function (id) {
  const reason = prompt('Please specify the reason for rejection (e.g. Invalid RC or expired fitness certificate):', 'Expired commercial taxi fitness certificate');
  if (!reason) return;

  try {
    const res = await fetch(`/api/admin/vehicles/${id}/reject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify({ admin_notes: reason })
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'info');
      updateVehicleRowStatusInDOM(id, 'Rejected', reason);
    } else {
      showToast(data.message || 'Failed to reject vehicle.', 'danger');
    }
  } catch (err) {
    showToast('Failed to reject vehicle.', 'warning');
  }
};

window.deleteVehicle = async function (id) {
  if (!confirm('Are you sure you want to permanently delete this vehicle record?')) return;
  try {
    const res = await fetch(`/api/admin/vehicles/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`admin-veh-row-${id}`);
      if (row) row.remove();
      updateVehiclePendingBadgeInDOM();
    } else {
      showToast(data.message || 'Failed to delete vehicle.', 'danger');
    }
  } catch (err) {
    showToast('Failed to delete vehicle.', 'warning');
  }
};

window.deleteVehicleFromModal = function () {
  if (window.currentEditingVehicleId) {
    closeModal('admin-vehicle-modal');
    deleteVehicle(window.currentEditingVehicleId);
  }
};

window.handleLocationSearch = function (query) {
  const q = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#admin-locations-tbody tr');
  rows.forEach(row => {
    const searchData = row.getAttribute('data-search') || row.innerText || '';
    if (!q || searchData.toLowerCase().includes(q)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });

  if (typeof window.renderAdminTablePagination === 'function') {
    window.renderAdminTablePagination('admin-locations', true);
  }
};

window.openTicketModal = async function (reference) {
  let b = (state.bookings || []).find(item => item.id === reference || item.booking_reference === reference);
  if (!b) {
    try {
      // Owner access works for signed-in customers; older bookings that are
      // only linked by phone need the number that was used to book.
      const phone = window.CURRENT_USER && window.CURRENT_USER.phone ? window.CURRENT_USER.phone : '';
      const withPhone = phone ? `?phone=${encodeURIComponent(phone)}` : '';

      let res = await fetch(`/api/bookings/${reference}${withPhone}`);
      let data = await res.json();

      if ((!res.ok || !data.success) && phone) {
        res = await fetch(`/api/bookings/${reference}`);
        data = await res.json();
      }

      if (data && data.success && data.booking) {
        const dbBooking = data.booking;
        b = {
          id: dbBooking.booking_reference,
          route: dbBooking.route_summary || '',
          operator: dbBooking.driver
            ? `${dbBooking.driver.name} (${dbBooking.driver.vehicle_model || 'Cab'})`
            : (dbBooking.service_type === 'bus' ? 'Intercity Sleeper Bus' : 'Intercity Verified Cab'),
          berths: Array.isArray(dbBooking.selected_units) && dbBooking.selected_units.length
            ? dbBooking.selected_units
            : ['Private Cab'],
          amount: Number(dbBooking.total_amount || 0),
          otp: dbBooking.start_otp || '----'
        };
      }
    } catch (err) {
      console.error('Failed to fetch ticket info:', err);
    }
  }
  if (b) {
    showTicketModal(b);
  } else {
    showToast('Booking ticket details not found.', 'warning');
  }
};

window.updatePolicyPreview = function () {
  const privUrl = document.getElementById('setting-privacy-url')?.value.trim() || '/privacy-policy';
  const termsUrl = document.getElementById('setting-terms-url')?.value.trim() || '/terms-conditions';

  const privPreview = document.getElementById('preview-link-privacy');
  const termsPreview = document.getElementById('preview-link-terms');
  if (privPreview) privPreview.href = privUrl;
  if (termsPreview) termsPreview.href = termsUrl;

  const loginPriv = document.getElementById('login-link-privacy');
  const loginTerms = document.getElementById('login-link-terms');
  if (loginPriv) loginPriv.href = privUrl;
  if (loginTerms) loginTerms.href = termsUrl;
};

window.saveAdminPolicyUrls = async function (e) {
  if (e && e.preventDefault) e.preventDefault();

  const privUrl = document.getElementById('setting-privacy-url')?.value.trim() || '/privacy-policy';
  const termsUrl = document.getElementById('setting-terms-url')?.value.trim() || '/terms-conditions';

  try {
    const res = await fetch('/api/admin/settings/policy-urls', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify({
        privacy_policy_url: privUrl,
        terms_conditions_url: termsUrl
      })
    });

    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Legal & Policy URLs updated successfully!', 'success');
      updatePolicyPreview();
    } else {
      showToast(data.message || 'Failed to update policy URLs.', 'danger');
    }
  } catch (err) {
    console.error('Save policy error:', err);
    showToast('Network error while saving policy URLs.', 'warning');
  }
};

window.saveAdminLegalPages = async function (e) {
  if (e && e.preventDefault) e.preventDefault();

  const payload = {
    privacy_policy_title: document.getElementById('setting-privacy-title')?.value.trim() || '',
    privacy_policy_updated_at: document.getElementById('setting-privacy-updated')?.value.trim() || '',
    privacy_policy_body: document.getElementById('setting-privacy-body')?.value.trim() || '',
    terms_conditions_title: document.getElementById('setting-terms-title')?.value.trim() || '',
    terms_conditions_updated_at: document.getElementById('setting-terms-updated')?.value.trim() || '',
    terms_conditions_body: document.getElementById('setting-terms-body')?.value.trim() || '',
    legal_contact_helpline: document.getElementById('setting-legal-helpline')?.value.trim() || '',
    legal_contact_email: document.getElementById('setting-legal-email')?.value.trim() || '',
  };

  try {
    const res = await fetch('/api/admin/settings/legal-pages', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      showToast(data.message || 'Privacy Policy & Terms content updated!', 'success');
    } else {
      showToast(data.message || 'Failed to save legal pages.', 'danger');
    }
  } catch (err) {
    console.error('saveAdminLegalPages', err);
    showToast('Network error while saving legal pages.', 'warning');
  }
};

window.toggleClerkAdminBadge = function () {
  const checked = Boolean(document.getElementById('setting-clerk-enabled')?.checked);
  const badge = document.getElementById('setting-clerk-status-badge');
  if (badge) {
    badge.className = `badge ${checked ? 'badge-success' : 'badge-secondary'}`;
    badge.textContent = checked ? 'CLERK ENABLED' : 'HIDDEN FROM LOGIN';
  }
};

window.saveAdminClerkAuthSettings = async function (e) {
  if (e && e.preventDefault) e.preventDefault();

  const payload = {
    clerk_login_enabled: Boolean(document.getElementById('setting-clerk-enabled')?.checked),
    clerk_publishable_key: document.getElementById('setting-clerk-publishable-key')?.value.trim() || '',
    clerk_secret_key: document.getElementById('setting-clerk-secret-key')?.value.trim() || '',
  };

  try {
    const res = await fetch('/api/admin/settings/clerk-auth', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      const secretInput = document.getElementById('setting-clerk-secret-key');
      if (secretInput) secretInput.value = '';
      const hint = document.getElementById('setting-clerk-secret-hint');
      if (hint && data.settings?.clerk_secret_configured === '1') {
        hint.textContent = '(Saved — leave blank to keep)';
        hint.style.color = '#16a34a';
      }
      if (window.ALTERNATE_AUTH_CONFIG && window.ALTERNATE_AUTH_CONFIG.clerk) {
        window.ALTERNATE_AUTH_CONFIG.clerk.enabled = payload.clerk_login_enabled;
        window.ALTERNATE_AUTH_CONFIG.clerk.publishableKey = payload.clerk_login_enabled ? payload.clerk_publishable_key : '';
        window.ALTERNATE_AUTH_CONFIG.clerk.is_configured = Boolean(payload.clerk_login_enabled && payload.clerk_publishable_key);
      }
      window.toggleClerkAdminBadge();
      showToast(data.message || 'Clerk auth settings saved!', 'success');
    } else {
      showToast(data.message || 'Failed to save Clerk auth settings.', 'warning');
    }
  } catch (err) {
    console.error('saveAdminClerkAuthSettings', err);
    showToast('Network error while saving Clerk settings.', 'warning');
  }
};

// ===== ADMIN DASHBOARD & TABLE PAGINATION =====
(function () {
  const ADMIN_TABLES_CONFIG = {
    'dashboard-fleet': {
      tbodyId: 'admin-dashboard-fleet-tbody',
      barId: 'pag-bar-dashboard-fleet',
      pageSize: 5,
      sizes: [5, 10, 25],
      page: 1,
    },
    'dashboard-bookings': {
      tbodyId: 'admin-recent-bookings-tbody',
      barId: 'pag-bar-dashboard-bookings',
      pageSize: 5,
      sizes: [5, 10, 25],
      page: 1,
    },
    'admin-bookings': {
      tbodyId: 'admin-bookings-table-tbody',
      barId: 'pag-bar-admin-bookings',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-biddings': {
      tbodyId: 'admin-biddings-tbody',
      barId: 'pag-bar-admin-biddings',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-users': {
      tbodyId: 'admin-users-table-tbody',
      barId: 'pag-bar-admin-users',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-fleet': {
      tbodyId: 'admin-fleet-directory-tbody',
      barId: 'pag-bar-admin-fleet',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-locations': {
      tbodyId: 'admin-locations-tbody',
      barId: 'pag-bar-admin-locations',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-cab-routes': {
      tbodyId: 'admin-cab-routes-tbody',
      barId: 'pag-bar-admin-cab-routes',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-berth-routes': {
      tbodyId: 'admin-berth-routes-tbody',
      barId: 'pag-bar-admin-berth-routes',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-berths': {
      tbodyId: 'admin-berths-tbody',
      barId: 'pag-bar-admin-berths',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
    'admin-vehicles': {
      tbodyId: 'admin-vehicles-tbody',
      barId: 'pag-bar-admin-vehicles',
      pageSize: 10,
      sizes: [10, 25, 50],
      page: 1,
    },
  };

  function getPaginatableRows(tbody) {
    if (!tbody) return [];
    return Array.from(tbody.querySelectorAll('tr')).filter(row => {
      if (row.dataset.emptyRow === '1') return false;
      if (row.id === 'admin-bookings-empty-row' || row.id === 'admin-bookings-no-results-row' || row.id === 'admin-users-empty-row' || row.id === 'admin-fleet-empty-row') return false;
      if (row.querySelector('td[colspan]') && tbody.querySelectorAll('tr').length === 1) return false;
      if (row.style.display === 'none') return false;
      return true;
    });
  }

  function buildPageList(current, total) {
    if (total <= 7) {
      return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages = [1];
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let p = start; p <= end; p++) pages.push(p);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
  }

  window.renderAdminTablePagination = function (key, resetPage = false) {
    const cfg = ADMIN_TABLES_CONFIG[key];
    if (!cfg) return;

    const tbody = document.getElementById(cfg.tbodyId);
    const bar = document.getElementById(cfg.barId);
    if (!tbody || !bar) return;

    if (resetPage) cfg.page = 1;

    // Clear previous pagination hiding before measuring active (filter-matching) rows
    Array.from(tbody.querySelectorAll('tr')).forEach(r => r.classList.remove('admin-page-hidden'));

    const rows = getPaginatableRows(tbody);
    const total = rows.length;

    if (total === 0) {
      bar.style.display = 'none';
      return;
    }

    bar.style.display = 'flex';
    const totalPages = Math.max(1, Math.ceil(total / cfg.pageSize));
    if (cfg.page > totalPages) cfg.page = totalPages;
    if (cfg.page < 1) cfg.page = 1;

    const startIdx = (cfg.page - 1) * cfg.pageSize;
    const endIdx = Math.min(total, startIdx + cfg.pageSize);

    rows.forEach((row, idx) => {
      if (idx < startIdx || idx >= endIdx) {
        row.classList.add('admin-page-hidden');
      }
    });

    const pagesHtml = buildPageList(cfg.page, totalPages).map(item => {
      if (item === '...') {
        return '<span class="admin-pag-ellipsis">…</span>';
      }
      return `<button type="button" class="admin-pag-btn ${item === cfg.page ? 'active' : ''}" onclick="setAdminTablePage('${key}', ${item})">${item}</button>`;
    }).join('');

    const sizeOptions = cfg.sizes.map(sz =>
      `<option value="${sz}" ${sz === cfg.pageSize ? 'selected' : ''}>${sz} / page</option>`
    ).join('');

    bar.innerHTML = `
      <div class="admin-pag-left">
        <span class="admin-pag-info">Showing ${startIdx + 1}–${endIdx} of ${total}</span>
        <select class="admin-pag-size-select" aria-label="Rows per page" onchange="setAdminTablePageSize('${key}', this.value)">
          ${sizeOptions}
        </select>
      </div>
      <div class="admin-pag-controls">
        <button type="button" class="admin-pag-btn" ${cfg.page <= 1 ? 'disabled' : ''} onclick="setAdminTablePage('${key}', ${cfg.page - 1})">Prev</button>
        ${pagesHtml}
        <button type="button" class="admin-pag-btn" ${cfg.page >= totalPages ? 'disabled' : ''} onclick="setAdminTablePage('${key}', ${cfg.page + 1})">Next</button>
      </div>
    `;
  };

  window.setAdminTablePage = function (key, page) {
    const cfg = ADMIN_TABLES_CONFIG[key];
    if (!cfg) return;
    cfg.page = Number(page) || 1;
    window.renderAdminTablePagination(key, false);
  };

  window.setAdminTablePageSize = function (key, size) {
    const cfg = ADMIN_TABLES_CONFIG[key];
    if (!cfg) return;
    cfg.pageSize = Math.max(1, parseInt(size, 10) || cfg.sizes[0]);
    cfg.page = 1;
    window.renderAdminTablePagination(key, false);
  };

  function initAllAdminPaginations() {
    Object.keys(ADMIN_TABLES_CONFIG).forEach(key => {
      window.renderAdminTablePagination(key, true);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllAdminPaginations);
  } else {
    initAllAdminPaginations();
  }
})();

window.toggleAdminSchedulesAndBerths = async function (enable) {
  const toggle = document.getElementById('setting-schedules-berths-toggle');
  const badge = document.getElementById('setting-schedules-berths-badge');
  const btn = document.getElementById('setting-schedules-berths-btn');
  const previewBadge = document.getElementById('admin-preview-berths-badge');
  const berthAlert = document.getElementById('berth-routes-feature-alert');
  const schedulesContainer = document.getElementById('schedules-booking-container');
  const disabledCard = document.getElementById('schedules-disabled-notice-card');

  try {
    const res = await fetch('/api/admin/settings/toggle-feature', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify({
        feature: 'schedules_and_berths',
        enabled: enable
      })
    });

    const data = await res.json();
    if (res.ok && data.success) {
      const isNowOn = data.enabled;
      if (toggle) toggle.checked = isNowOn;
      if (badge) {
        badge.className = 'badge ' + (isNowOn ? 'badge-success' : 'badge-danger');
        badge.textContent = isNowOn ? 'ON (Active)' : 'OFF (Disabled)';
      }
      if (btn) {
        btn.className = 'btn btn-sm ' + (isNowOn ? 'btn-outline-danger' : 'btn-primary');
        btn.innerHTML = `<span class="material-symbols-outlined" style="font-size:15px;">${isNowOn ? 'power_settings_new' : 'check_circle'}</span><span>${isNowOn ? 'Turn OFF' : 'Turn ON'}</span>`;
      }
      if (previewBadge) {
        previewBadge.className = 'badge ' + (isNowOn ? 'badge-success' : 'badge-gold');
        previewBadge.textContent = isNowOn ? 'LIVE' : 'OFF';
      }
      if (berthAlert) {
        berthAlert.style.display = isNowOn ? 'none' : 'flex';
      }
      // Update slider visually
      const slider = toggle?.nextElementSibling;
      if (slider) {
        slider.style.backgroundColor = isNowOn ? '#10b981' : '#cbd5e1';
        const knob = slider.querySelector('span');
        if (knob) knob.style.left = isNowOn ? '28px' : '4px';
      }

      // Update global platform settings in memory
      if (!window.PLATFORM_SETTINGS) window.PLATFORM_SETTINGS = {};
      window.PLATFORM_SETTINGS.enable_schedules_and_berths = isNowOn ? '1' : '0';

      // Toggle visibility of navigation buttons if present
      const navBtn = document.getElementById('nav-tab-btn-schedules');
      if (navBtn) navBtn.style.display = isNowOn ? '' : 'none';
      const dockBtn = document.getElementById('mobile-dock-btn-schedules');
      if (dockBtn) dockBtn.style.display = isNowOn ? '' : 'none';
      const dropdownItem = document.getElementById('dropdown-item-schedules');
      if (dropdownItem) dropdownItem.style.display = isNowOn ? '' : 'none';

      if (schedulesContainer && (!window.CURRENT_USER || window.CURRENT_USER.role === 'customer')) {
        schedulesContainer.style.display = isNowOn ? '' : 'none';
      }
      if (disabledCard) {
        disabledCard.style.display = isNowOn ? 'none' : 'block';
      }

      showToast(data.message || (isNowOn ? 'Schedules & Berths option enabled!' : 'Schedules & Berths option disabled!'), isNowOn ? 'success' : 'warning');
    } else {
      if (toggle) toggle.checked = !enable;
      showToast(data.message || 'Failed to update feature setting.', 'danger');
    }
  } catch (err) {
    console.error('Toggle feature error:', err);
    if (toggle) toggle.checked = !enable;
    showToast('Network error while updating feature setting.', 'warning');
  }
};

// ==========================================
// DRIVER PROFILE & DUTY STATUS CONTROLS
// ==========================================
window.openDriverEditProfileModal = function () {
  const modal = document.getElementById('driver-edit-profile-modal');
  if (modal) {
    modal.classList.add('active');
  }
};

window.selectDriverAvatar = function (avatar) {
  const hiddenInput = document.getElementById('driver-edit-avatar');
  if (hiddenInput) hiddenInput.value = avatar;
  document.querySelectorAll('.avatar-picker-grid .avatar-option').forEach(el => {
    el.classList.toggle('active', el.textContent.trim() === avatar);
  });
};

window.saveDriverProfile = async function (e) {
  if (e && e.preventDefault) e.preventDefault();
  const btn = document.getElementById('driver-save-profile-btn');
  if (btn) btn.disabled = true;

  const payload = {
    name: document.getElementById('driver-edit-name')?.value.trim(),
    phone: document.getElementById('driver-edit-phone')?.value.trim(),
    avatar: document.getElementById('driver-edit-avatar')?.value.trim() || '👨🏽‍✈️',
    vehicle_model: document.getElementById('driver-edit-model')?.value.trim(),
    vehicle_plate: document.getElementById('driver-edit-plate')?.value.trim().toUpperCase(),
    fleet_category: document.getElementById('driver-edit-category')?.value,
    city: document.getElementById('driver-edit-city')?.value.trim(),
  };

  try {
    const res = await fetch('/api/driver/profile', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Chauffeur profile updated successfully!', 'success');
      closeModal('driver-edit-profile-modal');

      const p = data.profile;
      if (p) {
        document.querySelectorAll('.driver-name-display').forEach(el => el.textContent = p.name);
        document.querySelectorAll('.driver-avatar-display').forEach(el => setAvatarContent(el, p.avatar, '👨🏽‍✈️', p.name || 'Driver'));
        document.querySelectorAll('.driver-vehicle-display').forEach(el => el.textContent = p.vehicle_model);
        document.querySelectorAll('.driver-plate-display').forEach(el => el.textContent = p.vehicle_plate);
        document.querySelectorAll('.driver-category-display').forEach(el => el.textContent = (p.fleet_category || 'SUV').toUpperCase());
        document.querySelectorAll('.driver-phone-display').forEach(el => el.textContent = p.phone);
        document.querySelectorAll('.driver-city-display').forEach(el => el.textContent = p.city || 'Hub Base');
      }
    } else {
      showToast(data.message || 'Failed to update chauffeur profile.', 'danger');
    }
  } catch (err) {
    console.error('Driver profile save error:', err);
    showToast('Network error while saving profile.', 'warning');
  } finally {
    if (btn) btn.disabled = false;
  }
};

window.toggleDriverDutyStatus = async function () {
  try {
    const res = await fetch('/api/driver/toggle-online', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken()
      }
    });
    const data = await res.json();
    if (res.ok && data.success) {
      const isOnline = data.is_online;
      showToast(data.message, isOnline ? 'success' : 'info');

      document.querySelectorAll('.driver-duty-pill-toggle').forEach(el => {
        el.classList.toggle('online', isOnline);
        el.classList.toggle('offline', !isOnline);
      });
      document.querySelectorAll('.driver-duty-dot').forEach(el => {
        el.classList.toggle('bg-success', isOnline);
        el.classList.toggle('bg-secondary', !isOnline);
      });
      document.querySelectorAll('.driver-duty-text').forEach(el => {
        el.textContent = isOnline ? 'Duty Online' : 'Off Duty';
      });
    }
  } catch (err) {
    showToast('Network error while toggling duty status.', 'warning');
  }
};

// ==========================================
// DRIVER BIDDING MARKETPLACE (Admin)
// ==========================================
window.biddingState = {
  bookingId: null,
  fare: 0,
  session: null,
  detail: null,
};

function biddingEscape(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function biddingMoney(value) {
  const n = Number(value);
  return Number.isFinite(n) ? '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '—';
}

function biddingStatusBadge(status) {
  const map = {
    open: ['badge-success', 'OPEN'],
    closed: ['badge-secondary', 'CLOSED'],
    awarded: ['badge-gold', 'ALLOTTED'],
    cancelled: ['badge-danger', 'CANCELLED'],
  };
  const entry = map[status] || ['badge-secondary', String(status || '').toUpperCase()];
  return `<span class="admin-badge ${entry[0]}">${entry[1]}</span>`;
}

function biddingShowError(id, message) {
  const el = document.getElementById(id);
  if (!el) return;
  if (!message) { el.style.display = 'none'; el.textContent = ''; return; }
  el.style.display = 'block';
  el.textContent = message;
}

window.openBiddingForBooking = async function (bookingId) {
  window.biddingState.bookingId = bookingId;
  window.biddingState.session = null;

  document.getElementById('bidding-open-form').style.display = 'block';
  document.getElementById('bidding-share-panel').style.display = 'none';
  biddingShowError('bidding-open-error', '');

  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}`);
    const data = await res.json();
    if (!data.success || !data.booking) {
      showToast('Could not load that booking.', 'danger');
      return;
    }

    const b = data.booking;
    window.biddingState.fare = Number(b.total_amount) || 0;

    document.getElementById('bidding-info-ref').textContent = b.booking_reference;
    document.getElementById('bidding-info-fare').textContent = biddingMoney(b.total_amount);
    document.getElementById('bidding-info-route').textContent = b.route_summary || '';
    document.getElementById('bidding-info-customer').textContent =
      `${b.customer_name || ''} · ${b.customer_phone || ''} · ${(b.status || '').toUpperCase()}`;

    const closesInput = document.getElementById('bidding-closes-at');
    if (closesInput) {
      closesInput.value = '';
    }

    document.getElementById('bidding-notes').value = '';
    window.recalculateBiddingCeiling();

    const modal = document.getElementById('admin-bidding-open-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('openBiddingForBooking', err);
    showToast('Could not load that booking.', 'danger');
  }
};

window.recalculateBiddingCeiling = function () {
  const fare = Number(window.biddingState.fare || 0);
  const pct = Number(document.getElementById('bidding-fee-pct')?.value || 0);
  const max = Math.max(0, Math.floor(fare * (1 - pct / 100)));

  const hint = document.getElementById('bidding-ceiling-hint');
  if (hint) {
    hint.textContent = `Max allowed: ${biddingMoney(max)} (fare ${biddingMoney(fare)} − ${pct}% margin)`;
  }

  const start = document.getElementById('bidding-start-amount');
  if (start) {
    start.max = max;
    if (!start.value || Number(start.value) > max) {
      start.value = max;
    }
  }
};

window.submitOpenBidding = async function () {
  const btn = document.getElementById('bidding-open-submit');
  const bookingId = window.biddingState.bookingId;

  if (!bookingId) return;

  const payload = {
    start_amount: Number(document.getElementById('bidding-start-amount').value),
    admin_fee_pct: Number(document.getElementById('bidding-fee-pct').value),
    closes_at: document.getElementById('bidding-closes-at').value || null,
    admin_notes: document.getElementById('bidding-notes').value || null,
  };

  if (!payload.start_amount || payload.start_amount < 1) {
    biddingShowError('bidding-open-error', 'Enter a start price greater than zero.');
    return;
  }

  const original = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Opening…';
  biddingShowError('bidding-open-error', '');

  try {
    const res = await fetch(`/api/admin/bookings/${bookingId}/bidding`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      biddingShowError('bidding-open-error', data.message || 'Could not open bidding.');
      return;
    }

    window.biddingState.session = data.session;

    document.getElementById('bidding-share-url').value = data.share.url;
    document.getElementById('bidding-share-text').textContent = data.share.text;
    document.getElementById('bidding-whatsapp-link').href = data.share.whatsapp_url;

    document.getElementById('bidding-open-form').style.display = 'none';
    document.getElementById('bidding-share-panel').style.display = 'block';

    showToast('Bidding opened. Share the link with your drivers.', 'success');
  } catch (err) {
    console.error('submitOpenBidding', err);
    biddingShowError('bidding-open-error', 'Network error while opening bidding.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
};

window.copyBiddingShare = async function () {
  const input = document.getElementById('bidding-share-url');
  if (!input) return;
  try {
    await navigator.clipboard.writeText(input.value);
    showToast('Bidding link copied.', 'success');
  } catch (err) {
    input.select();
    showToast('Press Ctrl/Cmd+C to copy the link.', 'info');
  }
};

async function copyTextToClipboardSafe(text) {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (_) {
      // Fall through to execCommand fallback
    }
  }
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  let ok = false;
  try {
    ok = document.execCommand('copy');
  } catch (_) {
    ok = false;
  }
  document.body.removeChild(ta);
  return ok;
}

async function fetchBookingSharePackage(bookingId) {
  const res = await fetch(`/api/admin/bookings/${bookingId}/bidding/share`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': getAdminCsrfToken(),
    },
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data.success || !data.share) {
    throw new Error(data.message || 'Could not generate bid invitation for this booking.');
  }
  return data;
}

window.copyBiddingInvitationText = async function () {
  const preview = document.getElementById('bidding-share-text');
  const text = preview ? preview.textContent.trim() : '';
  if (!text) return;
  const copied = await copyTextToClipboardSafe(text);
  showToast(copied ? '✅ Formatted WhatsApp bid invitation copied!' : 'Select and copy the invitation message below.', copied ? 'success' : 'info');
};

window.copyBookingBidInvitation = async function (bookingId, btn) {
  const origHtml = btn ? btn.innerHTML : '';
  if (btn) btn.disabled = true;
  try {
    const data = await fetchBookingSharePackage(bookingId);
    const copied = await copyTextToClipboardSafe(data.share.text);
    if (copied) {
      if (btn) {
        btn.innerHTML = '<span class="material-symbols-outlined">check</span>';
        window.setTimeout(() => { btn.innerHTML = origHtml; }, 1800);
      }
      showToast('✅ Bid invitation copied! Ready to paste into WhatsApp.', 'success');
    } else {
      // Fallback: open share modal with message ready
      window.biddingState.bookingId = bookingId;
      window.biddingState.session = data.session;
      document.getElementById('bidding-share-url').value = data.share.url;
      document.getElementById('bidding-share-text').textContent = data.share.text;
      document.getElementById('bidding-whatsapp-link').href = data.share.whatsapp_url;
      document.getElementById('bidding-open-form').style.display = 'none';
      document.getElementById('bidding-share-panel').style.display = 'block';
      document.getElementById('admin-bidding-open-modal')?.classList.add('active');
      showToast('Invitation generated — copy from the preview box.', 'info');
    }
  } catch (err) {
    showToast(err.message || 'Could not copy bid invitation.', 'warning');
  } finally {
    if (btn) btn.disabled = false;
  }
};

window.shareBookingBidOnWhatsApp = async function (bookingId, btn) {
  if (btn) btn.disabled = true;
  try {
    const data = await fetchBookingSharePackage(bookingId);
    await copyTextToClipboardSafe(data.share.text);
    const opened = window.open(data.share.whatsapp_url, '_blank', 'noopener');
    if (!opened) {
      // Popup blocked fallback: open the share panel with direct WhatsApp button
      window.biddingState.bookingId = bookingId;
      window.biddingState.session = data.session;
      document.getElementById('bidding-share-url').value = data.share.url;
      document.getElementById('bidding-share-text').textContent = data.share.text;
      document.getElementById('bidding-whatsapp-link').href = data.share.whatsapp_url;
      document.getElementById('bidding-open-form').style.display = 'none';
      document.getElementById('bidding-share-panel').style.display = 'block';
      document.getElementById('admin-bidding-open-modal')?.classList.add('active');
      showToast('Copied invitation! Click "Share on WhatsApp" in the modal.', 'info');
    } else {
      showToast('Opening WhatsApp with formatted bid invitation…', 'success');
    }
  } catch (err) {
    showToast(err.message || 'Could not open WhatsApp share.', 'warning');
  } finally {
    if (btn) btn.disabled = false;
  }
};

window.copyBiddingDetailLink = async function () {
  const detail = window.biddingState.detail;
  if (!detail) return;
  const url = `${window.location.origin}/bid/${detail.token}`;
  try {
    await navigator.clipboard.writeText(url);
    showToast('Driver link copied.', 'success');
  } catch (err) {
    showToast(url, 'info');
  }
};

window.renderAdminBiddings = async function () {
  const tbody = document.getElementById('admin-biddings-tbody');
  if (!tbody) return;

  const status = document.getElementById('admin-bidding-filter-status')?.value || 'all';

  try {
    const res = await fetch(`/api/admin/biddings?status=${encodeURIComponent(status)}`);
    const data = await res.json();

    if (!data.success) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px; color:#b91c1c;">Could not load auctions.</td></tr>';
      return;
    }

    if (!data.sessions.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:28px; color:#94a3b8;">No auctions yet. Open one from the gavel icon in Bookings Management.</td></tr>';
      return;
    }

    tbody.innerHTML = data.sessions.map((s) => {
      const profit = s.admin_profit_at_lowest === null ? '—' : biddingMoney(s.admin_profit_at_lowest);
      const closes = s.closes_at ? new Date(s.closes_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

      return `
        <tr>
          <td>
            <a href="javascript:void(0)" onclick="openViewBookingModal(${s.booking_id})" class="booking-ref-link">${biddingEscape(s.booking_reference || '')}</a>
            <div style="font-size:11px; color:#64748b;">${biddingEscape(s.customer_name || '')}</div>
          </td>
          <td>
            <div style="font-size:12px; font-weight:600;">${biddingEscape(s.pickup_city)} → ${biddingEscape(s.drop_city)}</div>
            <div style="font-size:11px; color:#64748b;">${s.distance_km ? s.distance_km + ' km' : ''}</div>
          </td>
          <td>${biddingStatusBadge(s.status)}</td>
          <td style="font-weight:700;">${biddingMoney(s.start_amount)}</td>
          <td style="font-weight:700; color:${s.lowest_bid !== null ? '#16a34a' : '#94a3b8'};">${s.lowest_bid !== null ? biddingMoney(s.lowest_bid) : '—'}</td>
          <td style="font-weight:700; color:#0f172a;">${profit}</td>
          <td>${s.bid_count}</td>
          <td style="font-size:11.5px; color:#475569;">${biddingEscape(closes)}</td>
          <td style="text-align:right; white-space:nowrap;">
            <button type="button" class="btn-action-icon btn-action-view" title="View bids & approve winner" onclick="openBiddingDetail(${s.id})">
              <span class="material-symbols-outlined">format_list_numbered</span>
            </button>
            ${s.status === 'open' ? `
              <button type="button" class="btn-action-icon" title="Close bidding" onclick="closeBiddingSession(${s.id})">
                <span class="material-symbols-outlined">lock</span>
              </button>` : ''}
          </td>
        </tr>`;
    }).join('');

    if (typeof window.renderAdminTablePagination === 'function') {
      window.renderAdminTablePagination('admin-biddings', true);
    }
  } catch (err) {
    console.error('renderAdminBiddings', err);
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px; color:#b91c1c;">Network error while loading auctions.</td></tr>';
  }
};

window.openBiddingDetail = async function (sessionId) {
  try {
    const res = await fetch(`/api/admin/biddings/${sessionId}`);
    const data = await res.json();

    if (!data.success) {
      showToast('Could not load that auction.', 'danger');
      return;
    }

    const session = data.session;
    window.biddingState.detail = session;
    biddingShowError('bidding-detail-error', '');

    const booking = session.booking;

    document.getElementById('bidding-detail-summary').innerHTML = `
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:12.5px; line-height:1.8;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <strong style="font-family:monospace; color:var(--primary);">${biddingEscape(booking.booking_reference)}</strong>
          ${biddingStatusBadge(session.status)}
        </div>
        <div><strong>${biddingEscape(session.pickup_city)} → ${biddingEscape(session.drop_city)}</strong>${session.distance_km ? ' · ' + session.distance_km + ' km' : ''}</div>
        <div style="color:#475569;">Customer: ${biddingEscape(booking.customer_name)} · ${biddingEscape(booking.customer_phone)}</div>
        <div style="color:#475569;">${biddingEscape(booking.pickup_location)} → ${biddingEscape(booking.drop_location)}</div>
        <div style="margin-top:6px;">
          Fare <strong>${biddingMoney(booking.total_amount)}</strong> ·
          Start price <strong>${biddingMoney(session.start_amount)}</strong> ·
          Max driver price <strong>${biddingMoney(session.max_driver_price)}</strong> ·
          Margin <strong>${session.admin_fee_pct}%</strong>
        </div>
        <div>
          Lowest bid <strong style="color:#16a34a;">${session.lowest_bid !== null ? biddingMoney(session.lowest_bid) : '—'}</strong> ·
          Admin profit <strong>${session.admin_profit_at_lowest !== null ? biddingMoney(session.admin_profit_at_lowest) : '—'}</strong>
        </div>
      </div>`;

    const tbody = document.getElementById('bidding-detail-bids');

    if (!session.bids.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:#94a3b8;">No bids yet — share the link with your drivers.</td></tr>';
    } else {
      tbody.innerHTML = session.bids.map((bid) => `
        <tr style="${bid.is_lowest ? 'background:#f0fdf4;' : ''}">
          <td>
            <strong>${biddingEscape(bid.driver_name || 'Driver')}</strong>
            ${bid.is_lowest ? '<span class="admin-badge badge-success" style="margin-left:6px; font-size:9px;">LOWEST</span>' : ''}
            ${bid.flagged ? '<span class="admin-badge badge-gold" style="margin-left:6px; font-size:9px;" title="' + biddingEscape(bid.flag_reason || '') + '">FLAGGED</span>' : ''}
            <div style="font-size:11px; color:#64748b;">${biddingEscape(bid.driver_phone || '')} · ★${bid.driver_rating || '—'}</div>
          </td>
          <td style="font-size:11.5px;">${biddingEscape(bid.vehicle_model || '')}<br><span style="color:#64748b;">${biddingEscape(bid.vehicle_plate || '')}</span></td>
          <td style="font-weight:800;">${biddingMoney(bid.bid_amount)}</td>
          <td style="font-size:11.5px; color:#475569;">${bid.placed_at ? new Date(bid.placed_at).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td>
          <td style="font-size:11px; color:#64748b;">${biddingEscape(bid.ip_address || '—')}</td>
          <td style="text-align:right;">
            ${(session.status === 'open' || session.status === 'closed')
          ? `<button type="button" class="btn btn-xs btn-outline-gold" onclick="awardWinningBid(${bid.id})">Assign</button>`
          : ''}
          </td>
        </tr>`).join('');
    }

    const awardBtn = document.getElementById('bidding-detail-award');
    if (awardBtn) {
      awardBtn.disabled = session.bids.length === 0 || session.status === 'awarded' || session.status === 'cancelled';
      awardBtn.dataset.bidId = session.lowest_bid !== null ? (session.bids.find((b) => b.is_lowest) || {}).id || '' : '';
    }

    const reassignWrap = document.getElementById('bidding-detail-reassign-wrap');
    const reassignSelect = document.getElementById('bidding-detail-reassign-driver');
    if (reassignWrap) {
      reassignWrap.style.display = session.status === 'awarded' ? 'block' : 'none';
    }
    if (reassignSelect && booking.assigned_driver_id) {
      reassignSelect.value = String(booking.assigned_driver_id);
    }

    const modal = document.getElementById('admin-bidding-detail-modal');
    if (modal) modal.classList.add('active');
  } catch (err) {
    console.error('openBiddingDetail', err);
    showToast('Network error while loading the auction.', 'danger');
  }
};

window.reassignAllottedDriverFromBidding = async function () {
  const detail = window.biddingState.detail;
  if (!detail || !detail.booking) return;

  const driverId = document.getElementById('bidding-detail-reassign-driver')?.value;
  if (!driverId) return;

  biddingShowError('bidding-detail-error', '');

  try {
    const res = await fetch(`/api/admin/bookings/${detail.booking.id}/allot-driver`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({ driver_id: Number(driverId) }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      biddingShowError('bidding-detail-error', data.message || 'Could not change allotted driver.');
      return;
    }

    showToast(data.message || 'Allotted driver updated.', 'success');
    await window.openBiddingDetail(detail.id);
    window.renderAdminBiddings();
    if (typeof reloadAdminBookings === 'function') reloadAdminBookings();
  } catch (err) {
    console.error('reassignAllottedDriverFromBidding', err);
    biddingShowError('bidding-detail-error', 'Network error while updating driver.');
  }
};

window.saveBiddingServiceChargeSettings = async function () {
  const minPct = parseFloat(document.getElementById('bidding-setting-min-pct')?.value ?? 15);
  const maxPct = parseFloat(document.getElementById('bidding-setting-max-pct')?.value ?? 75);

  if (Number.isNaN(minPct) || Number.isNaN(maxPct) || minPct < 0 || maxPct < minPct) {
    showToast('Max service charge % must be greater than or equal to Min service charge %.', 'warning');
    return;
  }

  try {
    const res = await fetch('/api/admin/settings/bidding', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({
        bidding_min_service_charge_pct: minPct,
        bidding_max_service_charge_pct: maxPct,
      }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      showToast(data.message || 'Could not update bidding limits.', 'warning');
      return;
    }

    window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
    const ruleMin = document.getElementById('rule-bidding-min-pct');
    const ruleMax = document.getElementById('rule-bidding-max-pct');
    if (ruleMin) ruleMin.value = String(minPct);
    if (ruleMax) ruleMax.value = String(maxPct);
    showToast(data.message || 'Bidding service charge limits updated.', 'success');
  } catch (err) {
    console.error('saveBiddingServiceChargeSettings', err);
    showToast('Network error while saving bidding limits.', 'danger');
  }
};

window.awardWinningBid = async function (bidId) {
  const detail = window.biddingState.detail;
  if (!detail) return;

  const explicitId = bidId || Number(document.getElementById('bidding-detail-award')?.dataset.bidId) || null;

  const body = explicitId ? { bid_id: explicitId } : {};

  biddingShowError('bidding-detail-error', '');

  try {
    const res = await fetch(`/api/admin/biddings/${detail.id}/award`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      biddingShowError('bidding-detail-error', data.message || 'Could not award this bid.');
      return;
    }

    showToast(`Approved. ${data.driver ? data.driver.name : 'Driver'} assigned — ₹${Number(data.booking.driver_payout_amount).toLocaleString('en-IN')} payout, ₹${Number(data.booking.admin_profit).toLocaleString('en-IN')} margin.`, 'success');

    await window.openBiddingDetail(detail.id);
    window.renderAdminBiddings();

    if (typeof window.renderAdminBookings === 'function') {
      window.renderAdminBookings();
    }
  } catch (err) {
    console.error('awardWinningBid', err);
    biddingShowError('bidding-detail-error', 'Network error while awarding the bid.');
  }
};

window.closeBiddingSession = async function (sessionId) {
  const id = sessionId || window.biddingState.detail?.id;
  if (!id) return;

  try {
    const res = await fetch(`/api/admin/biddings/${id}/close`, { method: 'POST', headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      const target = sessionId ? 'bidding-detail-error' : 'bidding-open-error';
      biddingShowError(target, data.message || 'Could not close bidding.');
      return;
    }

    showToast('Bidding closed.', 'success');

    if (window.biddingState.detail && window.biddingState.detail.id === id) {
      await window.openBiddingDetail(id);
    }

    window.renderAdminBiddings();
  } catch (err) {
    console.error('closeBiddingSession', err);
    showToast('Network error while closing bidding.', 'danger');
  }
};

window.cancelBiddingSession = async function () {
  const detail = window.biddingState.detail;
  if (!detail) return;

  if (!window.confirm('Cancel this auction? The booking returns to the confirmed pool and the driver link stops accepting bids.')) {
    return;
  }

  try {
    const res = await fetch(`/api/admin/biddings/${detail.id}/cancel`, { method: 'POST', headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      biddingShowError('bidding-detail-error', data.message || 'Could not cancel this auction.');
      return;
    }

    showToast('Auction cancelled.', 'success');
    await window.openBiddingDetail(detail.id);
    window.renderAdminBiddings();
  } catch (err) {
    console.error('cancelBiddingSession', err);
    biddingShowError('bidding-detail-error', 'Network error while cancelling the auction.');
  }
};
// ==========================================
// MY TRIPS — booking history, filters, guest tracking
// ==========================================

let activeTripFilter = 'all';

function tripsEscape(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function tripsMoney(value) {
  const n = Number(value);
  return Number.isFinite(n) ? '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '₹0';
}

function tripBucket(status) {
  if (status === 'Completed') return 'completed';
  if (status === 'Cancelled') return 'cancelled';
  return 'active';
}

function tripStatusBadgeClass(status) {
  return {
    Confirmed: 'badge-success',
    'Driver Assigned': 'badge-gold',
    Started: 'badge-info',
    Bidding: 'badge-info',
    Completed: 'badge-primary',
  }[status] || 'badge-secondary';
}

/**
 * Recompute the filter counters from the DOM so a freshly injected booking is
 * reflected everywhere at once.
 */
function refreshTripCounters() {
  const cards = Array.from(document.querySelectorAll('#my-trips-list .customer-trip-card'));
  const counts = { all: cards.length, active: 0, completed: 0 };

  cards.forEach((card) => {
    const bucket = card.dataset.bucket || 'active';
    if (bucket === 'active') counts.active += 1;
    if (bucket === 'completed') counts.completed += 1;
  });

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value);
  };

  setText('trips-count-all', counts.all);
  setText('trips-count-active', counts.active);
  setText('trips-count-completed', counts.completed);

  const summary = document.getElementById('trips-summary');
  if (summary) {
    summary.textContent = counts.all === 0
      ? 'No trips booked yet'
      : `${counts.active} active · ${counts.completed} completed`;
  }

  return counts;
}

/**
 * Show only the requested bucket. "All" also reveals cancelled bookings, which
 * have no dedicated filter of their own.
 */
window.filterMyTrips = function (filter) {
  activeTripFilter = filter || 'all';

  document.querySelectorAll('.trip-filter-pill').forEach((pill) => {
    const isActive = pill.dataset.filter === activeTripFilter;
    pill.classList.toggle('active', isActive);
    pill.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  const list = document.getElementById('my-trips-list');
  if (!list) return;

  const cards = Array.from(list.querySelectorAll('.customer-trip-card'));
  let visible = 0;

  cards.forEach((card) => {
    const bucket = card.dataset.bucket || 'active';
    const show = activeTripFilter === 'all' || bucket === activeTripFilter;
    card.style.display = show ? '' : 'none';
    if (show) visible += 1;
  });

  const empty = document.getElementById('trips-empty');
  if (empty) {
    empty.style.display = visible === 0 ? 'block' : 'none';

    if (visible === 0 && cards.length > 0) {
      const title = document.getElementById('trips-empty-title');
      const copy = document.getElementById('trips-empty-copy');
      if (title) title.textContent = activeTripFilter === 'completed' ? 'No completed trips yet' : 'No upcoming trips right now';
      if (copy) {
        copy.textContent = activeTripFilter === 'completed'
          ? 'Finished rides will appear here once a trip is completed.'
          : 'Your upcoming & active bookings will appear here. Book a trip to get started.';
      }
    }
  }

  refreshTripCounters();
};

window.copyTripReference = async function (reference) {
  try {
    await navigator.clipboard.writeText(reference);
    showToast(`Reference ${reference} copied.`, 'success');
  } catch (err) {
    showToast(`Reference: ${reference}`, 'info');
  }
};

/**
 * Render a booking as a compact, high-contrast trip card. Used for the instant
 * post-booking redirect and for guest lookups.
 */
function buildTripCardHtml(booking) {
  const status = booking.status || 'Requested';
  const bucket = tripBucket(status);
  const ref = booking.booking_reference || '';
  const departure = booking.departure_time
    ? new Date(booking.departure_time).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
    : 'Scheduled Departure';
  const driver = booking.driver;
  const passengers = Array.isArray(booking.passengers) ? booking.passengers.length : 0;
  const statusSlug = String(status).toLowerCase().replace(/\s+/g, '-');

  const payTag = booking.payment_mode === 'part_pay'
    ? `${Number(booking.advance_percentage || 20)}% Adv Paid`
    : (booking.payment_mode === 'cash' ? 'Pay Driver' : (booking.payment_mode === 'full_upi' ? 'Paid UPI' : 'Prepaid'));
  const isBeyondUnconfirmed = Boolean(booking.is_beyond_region) && (status === 'Requested' || status === 'Bidding');

  const driverBlock = driver
    ? `<div class="trip-tile-line" style="font-weight:700; color:#0f172a;">${tripsEscape(driver.name)} (★${tripsEscape(driver.rating)})</div>
       <div class="trip-tile-sub">${tripsEscape(driver.vehicle_model)} · <strong>${tripsEscape(driver.vehicle_plate)}</strong></div>`
    : (isBeyondUnconfirmed
      ? `<div class="trip-tile-line" style="color:#b45309; font-weight:700;">Availability Not Guaranteed</div>
         <div class="trip-tile-sub">Beyond service region — ride not confirmed until chauffeur accepts</div>`
      : `<div class="trip-tile-line" style="color:${bucket === 'completed' ? '#475569' : '#92400e'}; font-weight:700;">${bucket === 'completed' ? 'Trip closed' : 'Allotment in progress'}</div>
         <div class="trip-tile-sub">${bucket === 'completed' ? 'Completed journey' : 'Verified chauffeur assigned prior to pickup'}</div>`);

  const finalBlock = bucket === 'active'
    ? `<div class="trip-otp-tile">
         <div class="trip-otp-label">Ride Start OTP</div>
         <div class="trip-otp-code">${tripsEscape(booking.start_otp)}</div>
         <div class="trip-otp-hint">Share with chauffeur</div>
       </div>`
    : `<div class="trip-state-tile ${bucket === 'completed' ? 'state-completed' : 'state-cancelled'}">
         <div class="trip-state-title">${bucket === 'completed' ? '✓ Trip Completed' : 'Cancelled'}</div>
         <div class="trip-state-sub">${bucket === 'completed' ? 'Thanks for riding with us' : 'No active OTP'}</div>
       </div>`;

  return `
    <div class="trip-card-top">
      <div class="trip-card-title-group">
        <div class="trip-card-badges">
          <span class="trip-ref-badge">${tripsEscape(ref)}</span>
          <span class="trip-status-pill status-${tripsEscape(statusSlug)}">${tripsEscape(status)}</span>
          ${isBeyondUnconfirmed ? '<span class="trip-status-pill" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;">Out of Region · Unconfirmed</span>' : ''}
          <span class="trip-service-tag">${tripsEscape(String(booking.service_type || '').toUpperCase())}</span>
          <span class="trip-new-flag">Just booked</span>
        </div>
        <div class="trip-card-route">${tripsEscape(booking.route_summary)}</div>
        <div class="trip-card-date">
          <span class="material-symbols-outlined" style="font-size:13px;">schedule</span>
          <span>${tripsEscape(departure)}</span>
        </div>
      </div>
      <div class="trip-card-fare-col">
        <div class="trip-card-fare">${tripsMoney(booking.total_amount)}</div>
        <div class="trip-card-pay-tag">${tripsEscape(payTag)}</div>
      </div>
    </div>

    <div class="trip-card-grid">
      <div class="trip-info-tile">
        <div class="trip-tile-label">Pickup &amp; Drop</div>
        <div class="trip-tile-line"><strong>From:</strong> ${tripsEscape(booking.pickup_location)}</div>
        <div class="trip-tile-line"><strong>To:</strong> ${tripsEscape(booking.drop_location)}</div>
      </div>
      <div class="trip-info-tile">
        <div class="trip-tile-label">Chauffeur &amp; Vehicle</div>
        ${driverBlock}
      </div>
      ${finalBlock}
    </div>

    <div class="trip-card-footer">
      <span class="trip-footer-meta">${isBeyondUnconfirmed ? 'Pending Availability Check (Beyond Region)' : (passengers ? passengers + ' passenger(s)' : 'Escrow Verified')}</span>
      <div class="trip-card-actions">
        <button type="button" class="btn btn-xs trip-btn-secondary" onclick="copyTripReference('${tripsEscape(ref)}')">
          <span class="material-symbols-outlined" style="font-size:14px;">content_copy</span>
          <span>Reference</span>
        </button>
        <button type="button" class="btn btn-xs trip-btn-primary" onclick="openTicketModal('${tripsEscape(ref)}')">
          <span class="material-symbols-outlined" style="font-size:14px;">confirmation_number</span>
          <span>${bucket === 'active' ? 'Digital Boarding Pass' : 'View Trip Ticket'}</span>
        </button>
      </div>
    </div>`;
}

/**
 * Put a booking at the top of the history list (signed-in users) or into the
 * guest tracking panel, then mark it so the customer sees what just happened.
 */
window.showBookingInHistory = function (booking) {
  if (!booking) return;

  const list = document.getElementById('my-trips-list');
  const ref = booking.booking_reference || 'new';

  if (list) {
    const existing = document.getElementById(`trip-card-${ref}`);
    if (existing) existing.remove();

    const card = document.createElement('div');
    card.className = 'customer-trip-card is-new';
    card.id = `trip-card-${ref}`;
    card.dataset.bucket = tripBucket(booking.status);
    card.dataset.status = booking.status || 'Requested';
    card.innerHTML = buildTripCardHtml(booking);

    list.insertBefore(card, list.firstElementChild);

    window.filterMyTrips('all');

    const listTop = document.getElementById('tab-my-bookings');
    if (listTop) listTop.scrollIntoView({ behavior: 'smooth', block: 'start' });

    window.setTimeout(() => card.classList.remove('is-new'), 6000);
    return;
  }

  // Guest: no history list, so preview the booking in the tracking panel.
  const result = document.getElementById('trips-track-result');
  const refInput = document.getElementById('trips-track-reference');
  const phoneInput = document.getElementById('trips-track-phone');

  if (refInput) refInput.value = ref;
  if (phoneInput && booking.customer_phone) phoneInput.value = booking.customer_phone;

  if (result) {
    result.innerHTML = `<div class="customer-trip-card" style="margin-top:6px;">${buildTripCardHtml(booking)}</div>`;
  }
};

/**
 * Guest lookup: reference + the mobile number the booking was made with.
 */
window.trackGuestBooking = async function () {
  const reference = (document.getElementById('trips-track-reference')?.value || '').trim().toUpperCase();
  const phone = (document.getElementById('trips-track-phone')?.value || '').trim();
  const result = document.getElementById('trips-track-result');

  if (!result) return;

  if (!reference || !phone) {
    result.innerHTML = '<div style="font-size:12.5px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px;">Enter both the booking reference and the mobile number used to book.</div>';
    return;
  }

  result.innerHTML = '<div style="font-size:12.5px; color:#64748b; padding:10px 0;">Looking up your booking…</div>';

  try {
    const res = await fetch('/api/bookings/lookup', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ reference: reference, phone: phone })
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success || !data.booking) {
      result.innerHTML = `<div style="font-size:12.5px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px;">${tripsEscape(data.message || 'No booking matches that reference and mobile number.')}</div>`;
      return;
    }

    result.innerHTML = `<div class="customer-trip-card">${buildTripCardHtml(data.booking)}</div>`;
  } catch (err) {
    console.error('trackGuestBooking', err);
    result.innerHTML = '<div style="font-size:12.5px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px;">Network error. Please try again.</div>';
  }
};

// ==========================================
// BULK RATES, NIGHT STAY, HOURS & CONDITIONS
// ==========================================
window.switchPricingSubTab = function (tabId) {
  document.querySelectorAll('.admin-pricing-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  document.querySelectorAll('.admin-pricing-tab-panel').forEach(panel => {
    panel.style.display = (panel.id === `admin-pricing-panel-${tabId}`) ? 'block' : 'none';
  });
};

window.applyCarTypeBulkPercent = function (pct) {
  const mult = 1 + (Number(pct) / 100);
  document.querySelectorAll('#admin-bulk-car-rates-tbody tr[data-car-type-id]').forEach(row => {
    const rateEl = row.querySelector('.bulk-ct-rate-km');
    const extraEl = row.querySelector('.bulk-ct-extra-km');
    if (rateEl) {
      const val = Math.max(1, Math.round((parseFloat(rateEl.value || 0) * mult) * 2) / 2);
      rateEl.value = val;
    }
    if (extraEl) {
      const val = Math.max(0, Math.round((parseFloat(extraEl.value || 0) * mult) * 2) / 2);
      extraEl.value = val;
    }
  });
  showToast(`Preview adjusted by ${pct > 0 ? '+' : ''}${pct}%. Click "Save All Car Type Rates" to apply.`, 'info');
};

window.applyCarTypeFlatDelta = function (delta) {
  document.querySelectorAll('#admin-bulk-car-rates-tbody tr[data-car-type-id]').forEach(row => {
    const rateEl = row.querySelector('.bulk-ct-rate-km');
    const extraEl = row.querySelector('.bulk-ct-extra-km');
    if (rateEl) rateEl.value = Math.max(1, (parseFloat(rateEl.value || 0) + delta));
    if (extraEl) extraEl.value = Math.max(0, (parseFloat(extraEl.value || 0) + delta));
  });
  showToast(`Adjusted preview by ${delta >= 0 ? '+' : ''}₹${delta}/km. Click "Save All Car Type Rates" to apply.`, 'info');
};

window.syncCarTypeExtraKm = function () {
  document.querySelectorAll('#admin-bulk-car-rates-tbody tr[data-car-type-id]').forEach(row => {
    const rateEl = row.querySelector('.bulk-ct-rate-km');
    const extraEl = row.querySelector('.bulk-ct-extra-km');
    if (rateEl && extraEl) extraEl.value = rateEl.value;
  });
  showToast('Matched Extra KM rate to Rate/KM in preview.', 'info');
};

window.bulkSaveCarTypeRates = async function () {
  const rates = [];
  document.querySelectorAll('#admin-bulk-car-rates-tbody tr[data-car-type-id]').forEach(row => {
    const id = parseInt(row.dataset.carTypeId, 10);
    const ratePerKm = parseFloat(row.querySelector('.bulk-ct-rate-km')?.value || 0);
    const extraKmRate = parseFloat(row.querySelector('.bulk-ct-extra-km')?.value || ratePerKm);
    const baseFareMin = parseFloat(row.querySelector('.bulk-ct-min-fare')?.value || 0);
    const isActive = Boolean(row.querySelector('.bulk-ct-active')?.checked);
    if (id && ratePerKm >= 1) {
      rates.push({
        id,
        rate_per_km: ratePerKm,
        extra_km_rate: isNaN(extraKmRate) ? ratePerKm : extraKmRate,
        base_fare_min: isNaN(baseFareMin) ? 0 : baseFareMin,
        is_active: isActive,
      });
    }
  });

  if (rates.length === 0) {
    showToast('No valid car type rates found to save.', 'warning');
    return;
  }

  try {
    const res = await fetch('/api/admin/car-types/bulk-rates', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({ rates }),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Car type rates updated!', 'success');
      if (Array.isArray(data.car_types)) {
        data.car_types.forEach(ct => {
          const row = document.getElementById(`admin-car-type-row-${ct.id}`);
          if (row) {
            row.dataset.status = ct.is_active ? 'active' : 'inactive';
            row.cells[3].innerHTML = `<strong style="color:#0f172a; font-size:13px;">₹${Number(ct.rate_per_km).toFixed(2)}</strong><span style="font-size:10.5px; color:#64748b;">/km</span>`;
            row.cells[4].innerHTML = `<span style="color:#334155; font-size:12px; font-weight:600;">₹${Number(ct.extra_km_rate || ct.rate_per_km).toFixed(2)}<span style="font-size:10.5px; color:#64748b;">/km</span></span>`;
            row.cells[5].innerHTML = `<span style="color:#0f172a; font-weight:700; font-size:12.5px;">₹${Number(ct.base_fare_min).toLocaleString('en-IN')}</span>`;
            const badge = document.getElementById(`car-type-status-badge-${ct.id}`);
            if (badge) {
              badge.className = `admin-badge ${ct.is_active ? 'badge-success' : 'badge-danger'}`;
              badge.textContent = ct.is_active ? 'Active' : 'Inactive';
            }
          }
        });
        await window.syncAllCarTypes(data.car_types);
      }
      if (typeof refreshCabQuote === 'function') refreshCabQuote();
    } else {
      showToast(data.message || 'Failed to save bulk car type rates.', 'warning');
    }
  } catch (err) {
    console.error('bulkSaveCarTypeRates', err);
    showToast('Network error while saving bulk car type rates.', 'danger');
  }
};

function formatTime12Hour(hhmm) {
  if (!hhmm || !hhmm.includes(':')) return hhmm || '';
  const [hStr, mStr] = hhmm.split(':');
  const h = parseInt(hStr, 10);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const h12 = (h % 12) || 12;
  return `${h12}:${mStr || '00'} ${ampm}`;
}

window.savePricingConditions = async function () {
  const payload = {
    addon_night_allowance_rate: parseFloat(document.getElementById('rule-night-charge')?.value ?? 400) || 0,
    night_stay_start_hour: document.getElementById('rule-night-start')?.value || '23:00',
    night_stay_end_hour: document.getElementById('rule-night-end')?.value || '06:00',
    addon_pet_friendly_rate: parseFloat(document.getElementById('rule-pet-charge')?.value ?? 350) || 0,
    addon_luggage_carrier_rate: parseFloat(document.getElementById('rule-luggage-charge')?.value ?? 200) || 0,
    roundtrip_min_km_per_day: parseInt(document.getElementById('rule-rt-min-km')?.value ?? 250, 10) || 0,
    driver_day_allowance: parseFloat(document.getElementById('rule-driver-allowance')?.value ?? 0) || 0,
    free_waiting_minutes: parseInt(document.getElementById('rule-waiting-grace')?.value ?? 45, 10) || 0,
    waiting_charge_per_hour: parseFloat(document.getElementById('rule-waiting-hour-rate')?.value ?? 150) || 0,
    free_cancellation_hours: parseInt(document.getElementById('rule-free-cancel-hours')?.value ?? 1, 10) || 0,
    bidding_min_service_charge_pct: parseFloat(document.getElementById('rule-bidding-min-pct')?.value ?? 15) || 0,
    bidding_max_service_charge_pct: parseFloat(document.getElementById('rule-bidding-max-pct')?.value ?? 75) || 0,
    other_booking_conditions: document.getElementById('rule-other-conditions')?.value || '',
  };

  try {
    const res = await fetch('/api/admin/settings/pricing-conditions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      const bMin = document.getElementById('bidding-setting-min-pct');
      const bMax = document.getElementById('bidding-setting-max-pct');
      if (bMin) bMin.value = String(payload.bidding_min_service_charge_pct);
      if (bMax) bMax.value = String(payload.bidding_max_service_charge_pct);
      state.admin.pricingRules.petCharge = payload.addon_pet_friendly_rate;
      state.admin.pricingRules.nightCharge = payload.addon_night_allowance_rate;
      state.admin.pricingRules.luggageCharge = payload.addon_luggage_carrier_rate;

      const windowLabel = `${formatTime12Hour(payload.night_stay_start_hour)} – ${formatTime12Hour(payload.night_stay_end_hour)}`;
      const pill = document.getElementById('pricing-night-summary-pill');
      if (pill) pill.textContent = `Night: ₹${payload.addon_night_allowance_rate.toLocaleString('en-IN')} (${windowLabel})`;

      const petPriceEl = document.getElementById('addon-price-pet');
      if (petPriceEl) petPriceEl.textContent = `+₹${payload.addon_pet_friendly_rate.toLocaleString('en-IN')}`;
      const nightPriceEl = document.getElementById('addon-price-night');
      if (nightPriceEl) nightPriceEl.textContent = `+₹${payload.addon_night_allowance_rate.toLocaleString('en-IN')}`;
      const nightHoursEl = document.getElementById('addon-night-hours-label');
      if (nightHoursEl) nightHoursEl.textContent = `(${windowLabel})`;
      const lugPriceEl = document.getElementById('addon-price-luggage');
      if (lugPriceEl) lugPriceEl.textContent = `+₹${payload.addon_luggage_carrier_rate.toLocaleString('en-IN')}`;

      if (typeof refreshCabQuote === 'function') refreshCabQuote();
      if (typeof updateTotalCabFare === 'function') updateTotalCabFare();
      showToast(data.message || 'Night stay, hours & conditions saved!', 'success');
    } else {
      showToast(data.message || 'Failed to save pricing conditions.', 'warning');
    }
  } catch (err) {
    console.error('savePricingConditions', err);
    showToast('Network error while saving pricing conditions.', 'danger');
  }
};

window.bulkSaveLocalPackages = async function () {
  const packages = [];
  document.querySelectorAll('#admin-bulk-local-packages-tbody tr[data-package-id]').forEach(row => {
    const id = parseInt(row.dataset.packageId, 10);
    const hours = parseInt(row.querySelector('.bulk-lp-hours')?.value || 0, 10);
    const km = parseInt(row.querySelector('.bulk-lp-km')?.value || 0, 10);
    const sedan = parseFloat(row.querySelector('.bulk-lp-sedan')?.value || 0);
    const suv = parseFloat(row.querySelector('.bulk-lp-suv')?.value || 0);
    const extraKm = parseFloat(row.querySelector('.bulk-lp-extra-km')?.value || 0);
    const extraHr = parseFloat(row.querySelector('.bulk-lp-extra-hr')?.value || 0);
    const isActive = Boolean(row.querySelector('.bulk-lp-active')?.checked);
    if (id && hours >= 1 && km >= 1) {
      packages.push({
        id,
        duration_hours: hours,
        included_km: km,
        sedan_fare: isNaN(sedan) ? 0 : sedan,
        suv_fare: isNaN(suv) ? 0 : suv,
        extra_km_rate: isNaN(extraKm) ? 0 : extraKm,
        extra_hour_rate: isNaN(extraHr) ? 0 : extraHr,
        is_active: isActive,
      });
    }
  });

  if (packages.length === 0) {
    showToast('No local packages found to save.', 'warning');
    return;
  }

  try {
    const res = await fetch('/api/admin/local-packages/bulk-rates', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({ packages }),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Local packages updated!', 'success');
      if (Array.isArray(data.packages)) {
        data.packages.forEach(lp => {
          const row = document.getElementById(`admin-local-pkg-row-${lp.id}`);
          if (row) {
            row.dataset.status = lp.is_active ? 'active' : 'inactive';
            row.cells[2].innerHTML = `<span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${lp.duration_hours} hrs &bull; 🛣️ ${lp.included_km} km</span>`;
            row.cells[3].innerHTML = `<strong style="color:var(--primary); font-size:13px;">₹${Number(lp.sedan_fare).toLocaleString('en-IN')}</strong>`;
            row.cells[4].innerHTML = `<strong style="color:var(--primary); font-size:13px;">₹${Number(lp.suv_fare).toLocaleString('en-IN')}</strong>`;
            row.cells[5].innerHTML = `<span style="font-size:11px; color:#64748b;">+₹${Number(lp.extra_km_rate).toLocaleString('en-IN')}/km &bull; +₹${Number(lp.extra_hour_rate).toLocaleString('en-IN')}/hr</span>`;
          }
        });
        if (typeof refreshLocalPackageOptions === 'function') refreshLocalPackageOptions(data.packages);
      }
      if (typeof refreshCabQuote === 'function') refreshCabQuote();
    } else {
      showToast(data.message || 'Failed to save local packages.', 'warning');
    }
  } catch (err) {
    console.error('bulkSaveLocalPackages', err);
    showToast('Network error while saving local packages.', 'danger');
  }
};

window.bulkAdjustCabRoutes = async function (action) {
  const payload = { action };

  if (action === 'set_roundtrip_discount') {
    payload.roundtrip_discount_pct = parseFloat(document.getElementById('bulk-route-rt-discount')?.value ?? 0) || 0;
  } else if (action === 'percent_adjust') {
    payload.percent = parseFloat(document.getElementById('bulk-route-percent')?.value ?? 0) || 0;
  } else if (action === 'inline_save') {
    const routes = [];
    document.querySelectorAll('#admin-bulk-routes-tbody tr[data-route-id]').forEach(row => {
      const id = parseInt(row.dataset.routeId, 10);
      const sedanRaw = row.querySelector('.bulk-rt-sedan')?.value;
      const suvRaw = row.querySelector('.bulk-rt-suv')?.value;
      routes.push({
        id,
        base_toll: parseFloat(row.querySelector('.bulk-rt-toll')?.value ?? 0) || 0,
        roundtrip_discount_pct: parseFloat(row.querySelector('.bulk-rt-disc')?.value ?? 0) || 0,
        sedan_fare: sedanRaw !== '' && sedanRaw !== undefined ? parseFloat(sedanRaw) : null,
        suv_fare: suvRaw !== '' && suvRaw !== undefined ? parseFloat(suvRaw) : null,
      });
    });
    payload.routes = routes;
  }

  try {
    const res = await fetch('/api/admin/cab-routes/bulk-adjust', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast(data.message || 'Routes updated!', 'success');
      if (Array.isArray(data.routes)) {
        window.INITIAL_CAB_ROUTES = data.routes;
        data.routes.forEach(r => {
          const bulkRow = document.querySelector(`#admin-bulk-routes-tbody tr[data-route-id="${r.id}"]`);
          if (bulkRow) {
            const tollEl = bulkRow.querySelector('.bulk-rt-toll');
            const discEl = bulkRow.querySelector('.bulk-rt-disc');
            const sedanEl = bulkRow.querySelector('.bulk-rt-sedan');
            const suvEl = bulkRow.querySelector('.bulk-rt-suv');
            if (tollEl) tollEl.value = r.base_toll ?? 0;
            if (discEl) discEl.value = r.roundtrip_discount_pct ?? 0;
            if (sedanEl) sedanEl.value = r.sedan_fare ?? '';
            if (suvEl) suvEl.value = r.suv_fare ?? '';
          }
        });
        if (typeof window.refreshAdminRouteFareDisplays === 'function') {
          window.refreshAdminRouteFareDisplays();
        }
      }
      if (typeof refreshCabQuote === 'function') refreshCabQuote();
    } else {
      showToast(data.message || 'Failed to bulk adjust routes.', 'warning');
    }
  } catch (err) {
    console.error('bulkAdjustCabRoutes', err);
    showToast('Network error while updating routes.', 'danger');
  }
};

window.buildRouteFareScheduleHtml = function (route, isRoundTrip = false) {
  const discFactor = Math.max(0, 1 - ((Number(route.roundtrip_discount_pct) || 0) / 100));
  const entries = Object.entries(FLEET_TYPES || {});
  if (entries.length === 0) return '<span style="color:#94a3b8;">No active car types</span>';

  const items = entries.map(([key, fleet]) => {
    const fixedCol = route[`${key}_fare`];
    const customMap = route.car_fares && route.car_fares[key];
    const baseOneWay = (fixedCol !== null && fixedCol !== undefined && Number(fixedCol) > 0)
      ? Number(fixedCol)
      : (customMap !== undefined && customMap !== null && Number(customMap) > 0
        ? Number(customMap)
        : Math.round(Number(route.distance_km || 0) * Number(fleet.ratePerKm || 12.5) + Number(route.base_toll || 0)));
    const fare = isRoundTrip ? Math.round((baseOneWay * 2) * discFactor) : Math.round(baseOneWay);
    return `<span>${fleet.name}: <strong>₹${fare.toLocaleString('en-IN')}</strong></span>`;
  }).join('');

  return `<div class="admin-route-fare-schedule" data-route-id="${route.id}" data-roundtrip="${isRoundTrip ? '1' : '0'}" style="font-size:11px; line-height:1.5; color:#334155; display:flex; flex-wrap:wrap; gap:4px 10px;">${items}</div>`;
};

window.refreshAdminRouteFareDisplays = function () {
  if (!Array.isArray(window.INITIAL_CAB_ROUTES)) return;
  const routeMap = new Map(window.INITIAL_CAB_ROUTES.map(r => [String(r.id), r]));
  document.querySelectorAll('.admin-route-fare-schedule[data-route-id]').forEach(el => {
    const route = routeMap.get(String(el.dataset.routeId));
    if (!route) return;
    const isRt = el.dataset.roundtrip === '1';
    el.outerHTML = window.buildRouteFareScheduleHtml(route, isRt);
  });
};

// ===== IN-APP NOTIFICATIONS =====
(function () {
  let latestNotifIds = new Set();
  let initialNotifLoadDone = false;

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function escapeNotifHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function categoryIcon(category) {
    switch (category) {
      case 'bidding': return 'local_offer';
      case 'trip': return 'local_taxi';
      case 'wallet': return 'account_balance_wallet';
      case 'fleet': return 'directions_car';
      default: return 'confirmation_number';
    }
  }

  function renderNotifications(data) {
    const badge = document.getElementById('app-notif-badge');
    const list = document.getElementById('app-notif-list');
    if (!badge || !list) return;

    const unread = Number(data.unread_count || 0);
    badge.textContent = unread > 99 ? '99+' : String(unread);
    badge.style.display = unread > 0 ? 'inline-block' : 'none';

    const items = Array.isArray(data.notifications) ? data.notifications : [];
    if (items.length === 0) {
      list.innerHTML = '<div class="app-notif-empty">No notifications yet. Booking, bidding & trip alerts will appear here.</div>';
      return;
    }

    list.innerHTML = items.map(item => `
      <div class="app-notif-item ${item.is_read ? '' : 'unread'}" data-notif-id="${escapeNotifHtml(item.id)}" data-tab="${escapeNotifHtml(item.meta?.tab || '')}" data-section="${escapeNotifHtml(item.meta?.section || '')}" data-url="${escapeNotifHtml(item.action_url || '')}" onclick="handleNotificationClick(this)">
        <div class="app-notif-icon">
          <span class="material-symbols-outlined">${categoryIcon(item.category)}</span>
        </div>
        <div class="app-notif-body">
          <div class="app-notif-item-title">
            <span>${escapeNotifHtml(item.title)}</span>
            ${item.is_read ? '' : '<span class="app-notif-dot" title="Unread"></span>'}
          </div>
          <div class="app-notif-item-msg">${escapeNotifHtml(item.message)}</div>
          <div class="app-notif-item-time">${escapeNotifHtml(item.time_ago)}</div>
        </div>
      </div>
    `).join('');
  }

  window.fetchAppNotifications = async function (showNewToasts = false) {
    if (!window.INTERCITY_AUTH?.authenticated && !window.CURRENT_USER) return;
    if (!document.getElementById('app-notif-menu')) return;

    try {
      const res = await fetch('/api/notifications', {
        headers: { 'Accept': 'application/json' },
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;

      const items = Array.isArray(data.notifications) ? data.notifications : [];
      if (initialNotifLoadDone && showNewToasts && typeof showToast === 'function') {
        const freshUnread = items.filter(i => !i.is_read && !latestNotifIds.has(i.id));
        if (freshUnread.length > 0) {
          showToast(freshUnread[0].title, 'info');
        }
      }

      latestNotifIds = new Set(items.map(i => i.id));
      initialNotifLoadDone = true;
      renderNotifications(data);
    } catch (_) {
      // Ignore transient network errors during background poll
    }
  };

  window.refreshAppNotifications = function () {
    return window.fetchAppNotifications(false);
  };

  window.toggleAppNotifications = function () {
    const dropdown = document.getElementById('app-notif-dropdown');
    if (!dropdown) return;
    const isHidden = dropdown.style.display === 'none' || !dropdown.style.display;
    dropdown.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
      window.fetchAppNotifications(false);
    }
  };

  window.handleNotificationClick = async function (el) {
    const id = el?.dataset?.notifId;
    const tab = el?.dataset?.tab;
    const section = el?.dataset?.section;
    const url = el?.dataset?.url;

    if (id && el.classList.contains('unread')) {
      el.classList.remove('unread');
      el.querySelector('.app-notif-dot')?.remove();
      try {
        const res = await fetch(`/api/notifications/${encodeURIComponent(id)}/read`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
        });
        if (res.ok) {
          const data = await res.json();
          const badge = document.getElementById('app-notif-badge');
          if (badge) {
            const unread = Number(data.unread_count || 0);
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.style.display = unread > 0 ? 'inline-block' : 'none';
          }
        }
      } catch (_) { }
    }

    const dropdown = document.getElementById('app-notif-dropdown');
    if (dropdown) dropdown.style.display = 'none';

    if (tab && typeof switchTab === 'function' && document.getElementById(`tab-${tab}`)) {
      switchTab(tab);
      if (tab === 'admin' && section && typeof switchAdminSection === 'function') {
        switchAdminSection(section);
        if (section === 'locations' && typeof window.refreshDiscoveredLocations === 'function') {
          window.refreshDiscoveredLocations();
        }
      }
    } else if (url && url.startsWith('http') && !url.includes('/#')) {
      window.location.href = url;
    }
  };

  window.markAllNotificationsRead = async function () {
    try {
      const res = await fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
      });
      if (res.ok) {
        window.fetchAppNotifications(false);
      }
    } catch (_) { }
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (window.INTERCITY_AUTH?.authenticated || window.CURRENT_USER) {
      window.fetchAppNotifications(false);
      setInterval(() => window.fetchAppNotifications(true), 25000);
    }

    window.addEventListener('click', (e) => {
      const menu = document.getElementById('app-notif-menu');
      const dropdown = document.getElementById('app-notif-dropdown');
      if (dropdown && menu && !menu.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  });
})();

// ===== CHECKOUT PAYMENT OPTIONS & ADVANCE PERCENTAGE =====
window.updateCheckoutPaymentDisplay = function (totalAmount) {
  const settings = window.PLATFORM_SETTINGS || {};
  const advPct = Math.max(1, Math.min(99, parseFloat(settings.payment_advance_pct ?? 20) || 20));
  const roundedTotal = Math.max(0, Math.round(Number(totalAmount) || 0));
  const advAmount = Math.round(roundedTotal * (advPct / 100));

  document.querySelectorAll('.checkout-pay-amt').forEach(el => {
    const mode = el.dataset.amtFor;
    if (mode === 'full_online' || mode === 'full_upi') {
      el.textContent = `₹${roundedTotal.toLocaleString('en-IN')}`;
    } else if (mode === 'part_pay') {
      el.textContent = `₹${advAmount.toLocaleString('en-IN')} now`;
    } else if (mode === 'cash') {
      el.textContent = '₹0 now';
    }
  });

  const checkedMode = document.querySelector('input[name="rental_pay_mode"]:checked')?.value || 'full_online';
  const payableNowEl = document.getElementById('checkout-payable-now-amount');
  if (payableNowEl) {
    if (checkedMode === 'part_pay') {
      const bal = Math.max(0, roundedTotal - advAmount);
      payableNowEl.textContent = `₹${advAmount.toLocaleString('en-IN')} (+ ₹${bal.toLocaleString('en-IN')} to Driver)`;
    } else if (checkedMode === 'cash') {
      payableNowEl.textContent = `₹0 now (₹${roundedTotal.toLocaleString('en-IN')} to Driver)`;
    } else {
      payableNowEl.textContent = `₹${roundedTotal.toLocaleString('en-IN')}`;
    }
  }
};

window.renderCheckoutPaymentOptionsFromSettings = function () {
  const listEl = document.getElementById('checkout-payment-options-list');
  if (!listEl) return;

  const settings = window.PLATFORM_SETTINGS || {};
  const advPctRaw = parseFloat(settings.payment_advance_pct ?? 20) || 20;
  const advLabel = Number.isInteger(advPctRaw) ? String(advPctRaw) : String(advPctRaw);

  const showFull = String(settings.payment_show_full_online ?? '1') === '1';
  const showPart = String(settings.payment_show_part_pay ?? '1') === '1';
  const showUpi = String(settings.payment_show_full_upi ?? '1') === '1';
  const showCash = String(settings.payment_show_cash ?? '1') === '1';

  const opts = [];
  if (showFull) opts.push({ mode: 'full_online', label: 'Pay Full Online (Card / NetBanking / UPI)', color: '#0f172a' });
  if (showPart) opts.push({ mode: 'part_pay', label: `Part Pay (${advLabel}% Advance, Balance to Driver)`, color: '#0284c7' });
  if (showUpi) opts.push({ mode: 'full_upi', label: 'Full UPI (Instant Zero-Fee UPI)', color: '#0f172a' });
  if (showCash) opts.push({ mode: 'cash', label: 'Pay Later to Chauffeur (Cash / UPI)', color: '#16a34a' });
  if (opts.length === 0) {
    opts.push({ mode: 'full_online', label: 'Pay Full Online (Card / NetBanking / UPI)', color: '#0f172a' });
  }

  const def = opts.some(o => o.mode === settings.payment_default_option) ? settings.payment_default_option : opts[0].mode;
  listEl.innerHTML = opts.map(o => `
    <label class="checkout-pay-opt" data-mode="${o.mode}" style="display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12.5px; cursor:pointer;">
      <span style="display:flex; align-items:center; gap:8px;">
        <input type="radio" name="rental_pay_mode" value="${o.mode}" ${o.mode === def ? 'checked' : ''} onchange="updateTotalCabFare()">
        <span>${o.label}</span>
      </span>
      <strong class="checkout-pay-amt" data-amt-for="${o.mode}" style="color:${o.color}; font-size:12px;"></strong>
    </label>
  `).join('');

  const promoCopy = document.getElementById('promo-advance-copy');
  if (promoCopy) {
    if (showPart && showCash) {
      promoCopy.textContent = `No booking charges. Pay ${advLabel}% advance or pay later to driver.`;
    } else if (showPart) {
      promoCopy.textContent = `No booking charges. Pay just ${advLabel}% advance to lock your ride.`;
    } else if (showCash) {
      promoCopy.textContent = 'No booking charges. Pay directly to driver on pickup.';
    } else {
      promoCopy.textContent = 'Instant online booking confirmation with transparent pricing.';
    }
  }

  if (typeof updateTotalCabFare === 'function') {
    updateTotalCabFare();
  }
};

window.toggleRazorpayAdminBadge = function () {
  const enabled = Boolean(document.getElementById('setting-razorpay-enabled')?.checked);
  const badge = document.getElementById('setting-razorpay-status-badge');
  if (badge) {
    badge.textContent = enabled ? 'RAZORPAY ACTIVE' : 'MANUAL / ESCROW';
    badge.className = `badge ${enabled ? 'badge-success' : 'badge-secondary'}`;
  }
};

window.saveAdminPaymentOptions = async function (e) {
  if (e) e.preventDefault();

  const advPct = parseFloat(document.getElementById('setting-payment-advance-pct')?.value ?? 20);
  const showPart = Boolean(document.getElementById('setting-pay-show-part')?.checked);
  const showFull = Boolean(document.getElementById('setting-pay-show-full')?.checked);
  const showUpi = Boolean(document.getElementById('setting-pay-show-upi')?.checked);
  const showCash = Boolean(document.getElementById('setting-pay-show-cash')?.checked);
  const defaultOpt = document.getElementById('setting-payment-default-option')?.value || 'full_online';
  const rzpEnabled = Boolean(document.getElementById('setting-razorpay-enabled')?.checked);
  const rzpMode = document.getElementById('setting-razorpay-mode')?.value || 'test';
  const rzpKeyId = document.getElementById('setting-razorpay-key-id')?.value?.trim() || '';
  const rzpKeySecret = document.getElementById('setting-razorpay-key-secret')?.value?.trim() || '';
  const rzpDisplayName = document.getElementById('setting-razorpay-display-name')?.value?.trim() || 'Intercity Wheels';

  if (!showPart && !showFull && !showUpi && !showCash) {
    showToast('Enable at least one checkout payment option.', 'warning');
    return;
  }

  if (Number.isNaN(advPct) || advPct < 1 || advPct > 99) {
    showToast('Advance percentage must be between 1% and 99%.', 'warning');
    return;
  }

  try {
    const res = await fetch('/api/admin/settings/payment-options', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify({
        payment_advance_pct: advPct,
        payment_show_full_online: showFull,
        payment_show_part_pay: showPart,
        payment_show_full_upi: showUpi,
        payment_show_cash: showCash,
        payment_default_option: defaultOpt,
        payment_gateway: rzpEnabled ? 'razorpay' : 'manual',
        razorpay_mode: rzpMode,
        razorpay_key_id: rzpKeyId,
        razorpay_key_secret: rzpKeySecret || undefined,
        razorpay_display_name: rzpDisplayName,
      }),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      const selDefault = document.getElementById('setting-payment-default-option');
      if (selDefault && data.settings?.payment_default_option) {
        selDefault.value = data.settings.payment_default_option;
      }
      const secretInput = document.getElementById('setting-razorpay-key-secret');
      if (secretInput) secretInput.value = '';
      const secretHint = document.getElementById('setting-razorpay-secret-hint');
      if (secretHint && data.settings?.razorpay_secret_configured === '1') {
        secretHint.textContent = '(Saved — leave blank to keep)';
        secretHint.style.color = '#16a34a';
      }
      window.toggleRazorpayAdminBadge();
      window.renderCheckoutPaymentOptionsFromSettings();
      showToast(data.message || 'Checkout payment options updated!', 'success');
    } else {
      showToast(data.message || 'Failed to update payment options.', 'danger');
    }
  } catch (err) {
    console.error('saveAdminPaymentOptions', err);
    showToast('Network error while saving payment options.', 'danger');
  }
};

// ===== ADMIN INVOICE TEMPLATE CUSTOMIZER =====
function collectInvoiceTemplateForm() {
  const style = document.querySelector('input[name="invoice_template_style"]:checked')?.value || 'classic_gst';
  return {
    invoice_template_style: style,
    invoice_accent_color: document.getElementById('setting-inv-accent')?.value || '#d49b4c',
    invoice_brand_name: document.getElementById('setting-inv-brand-name')?.value?.trim() || 'INTERCITY WHEELS',
    invoice_company_legal: document.getElementById('setting-inv-legal-name')?.value?.trim() || 'IntercityWheels Mobility Services Pvt. Ltd.',
    invoice_title: document.getElementById('setting-inv-title')?.value?.trim() || 'TAX INVOICE',
    invoice_prefix: document.getElementById('setting-inv-prefix')?.value?.trim() || 'INV',
    invoice_gstin: document.getElementById('setting-inv-gstin')?.value?.trim() || '',
    invoice_sac_code: document.getElementById('setting-inv-sac')?.value?.trim() || '',
    invoice_place_of_supply: document.getElementById('setting-inv-pos')?.value?.trim() || '',
    invoice_support_phone: document.getElementById('setting-inv-phone')?.value?.trim() || '',
    invoice_support_email: document.getElementById('setting-inv-email')?.value?.trim() || '',
    invoice_company_address: document.getElementById('setting-inv-address')?.value?.trim() || '',
    invoice_cgst_pct: parseFloat(document.getElementById('setting-inv-cgst')?.value ?? 2.5) || 0,
    invoice_sgst_pct: parseFloat(document.getElementById('setting-inv-sgst')?.value ?? 2.5) || 0,
    invoice_signatory_name: document.getElementById('setting-inv-signatory-name')?.value?.trim() || '',
    invoice_signatory_label: document.getElementById('setting-inv-signatory-label')?.value?.trim() || '',
    invoice_terms: document.getElementById('setting-inv-terms')?.value || '',
    invoice_show_stamp: Boolean(document.getElementById('setting-inv-show-stamp')?.checked),
    invoice_show_otp: Boolean(document.getElementById('setting-inv-show-otp')?.checked),
  };
}

window.openAdminInvoiceTemplateEditor = function () {
  if (typeof switchTab === 'function') switchTab('admin');
  if (typeof switchAdminSection === 'function') switchAdminSection('system');
  window.setTimeout(() => {
    const card = document.getElementById('admin-invoice-template-card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 60);
};

window.selectInvoiceTemplateStyle = function (style) {
  document.querySelectorAll('#inv-style-picker-grid .inv-style-card').forEach(card => {
    const isSel = card.dataset.styleOption === style;
    card.classList.toggle('selected', isSel);
    const radio = card.querySelector('input[type="radio"]');
    if (radio) radio.checked = isSel;
  });
};

window.previewInvoiceTemplate = function () {
  const form = collectInvoiceTemplateForm();
  const terms = form.invoice_terms.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
  const taxable = 3400;
  const cgst = Number((taxable * (form.invoice_cgst_pct / 100)).toFixed(2));
  const sgst = Number((taxable * (form.invoice_sgst_pct / 100)).toFixed(2));
  const toll = 250;
  const total = Math.round(taxable + cgst + sgst + toll);

  applyInvoiceTemplateToDom({
    template: {
      style: form.invoice_template_style,
      accent_color: form.invoice_accent_color,
      title: form.invoice_title,
      brand_name: form.invoice_brand_name,
      place_of_supply: form.invoice_place_of_supply,
      cgst_pct: form.invoice_cgst_pct,
      sgst_pct: form.invoice_sgst_pct,
      show_stamp: form.invoice_show_stamp,
      show_otp: form.invoice_show_otp,
      terms,
      signatory_name: form.invoice_signatory_name,
      signatory_label: form.invoice_signatory_label,
    },
    company: {
      brand_name: form.invoice_brand_name,
      name: form.invoice_company_legal,
      address: form.invoice_company_address,
      gstin: form.invoice_gstin,
      hsn_sac: form.invoice_sac_code,
      support_phone: form.invoice_support_phone,
      support_email: form.invoice_support_email,
    },
  });

  document.getElementById('inv-doc-number').textContent = `${form.invoice_prefix}-2026-ICW-SAMPLE`;
  document.getElementById('inv-doc-date').textContent = 'Sample Preview';
  document.getElementById('inv-booking-ref').textContent = 'ICW-SAMPLE';
  document.getElementById('inv-customer-name').textContent = 'Aarav Sen (Sample)';
  document.getElementById('inv-customer-phone').textContent = '+91 98765 43210';
  document.getElementById('inv-customer-email').textContent = 'rider@example.com';
  document.getElementById('inv-pickup-address').textContent = 'Salt Lake Sector V, Kolkata';
  document.getElementById('inv-drop-address').textContent = 'New Digha Sea Beach';
  document.getElementById('inv-service-type').textContent = 'CAB (ONEWAY)';
  document.getElementById('inv-route').textContent = 'Kolkata → Digha';
  document.getElementById('inv-travel-date').textContent = 'Tomorrow, 08:00 AM';
  document.getElementById('inv-driver-name').textContent = 'Rajesh Kumar (Ph: +91 98220 11920)';
  document.getElementById('inv-vehicle-details').textContent = 'Toyota Innova (MH12AB1234)';
  document.getElementById('inv-row-base').textContent = '₹3400.00';
  document.getElementById('inv-row-toll').textContent = '₹250.00';
  document.getElementById('inv-addon-row').style.display = 'none';
  document.getElementById('inv-discount-row').style.display = 'none';
  document.getElementById('inv-subtotal').textContent = `₹${taxable.toFixed(2)}`;
  document.getElementById('inv-cgst').textContent = `₹${cgst.toFixed(2)}`;
  document.getElementById('inv-sgst').textContent = `₹${sgst.toFixed(2)}`;
  document.getElementById('inv-grand-total').textContent = `₹${total.toLocaleString('en-IN')}.00`;
  document.getElementById('inv-amount-in-words').textContent = `(${numberToWordsINR(total)})`;
  document.getElementById('inv-paid-stamp-text').textContent = 'PAID IN FULL';
  document.getElementById('inv-payment-mode-text').textContent = 'Online Escrow (Prepaid)';
  document.getElementById('inv-txn-ref').textContent = 'TXN-SAMPLE01';
  document.getElementById('inv-start-otp').textContent = '4829';

  const modal = document.getElementById('admin-invoice-modal');
  if (modal) modal.classList.add('active');
};

window.saveAdminInvoiceTemplate = async function (e) {
  if (e) e.preventDefault();
  const payload = collectInvoiceTemplateForm();

  try {
    const res = await fetch('/api/admin/settings/invoice-template', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getAdminCsrfToken(),
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      window.PLATFORM_SETTINGS = { ...(window.PLATFORM_SETTINGS || {}), ...(data.settings || {}) };
      showToast(data.message || 'Invoice template settings saved!', 'success');
    } else {
      showToast(data.message || 'Failed to save invoice template settings.', 'danger');
    }
  } catch (err) {
    console.error('saveAdminInvoiceTemplate', err);
    showToast('Network error while saving invoice template.', 'danger');
  }
};

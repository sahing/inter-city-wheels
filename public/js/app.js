// ==========================================
// Intercity Wheels - Interactive Platform Engine
// ==========================================

// Pre-defined Cities & Route Matrix
const CITY_DATABASE = {
  "pune": { name: "Pune", state: "Maharashtra", lat: 18.5204, lng: 73.8567 },
  "goa": { name: "Goa (Panjim)", state: "Goa", lat: 15.4909, lng: 73.8278 },
  "mumbai": { name: "Mumbai", state: "Maharashtra", lat: 19.0760, lng: 72.8777 },
  "bengaluru": { name: "Bengaluru", state: "Karnataka", lat: 12.9716, lng: 77.5946 },
  "hyderabad": { name: "Hyderabad", state: "Telangana", lat: 17.3850, lng: 78.4867 },
  "delhi": { name: "New Delhi", state: "Delhi NCR", lat: 28.6139, lng: 77.2090 },
  "jaipur": { name: "Jaipur", state: "Rajasthan", lat: 26.9124, lng: 75.7873 },
  "chennai": { name: "Chennai", state: "Tamil Nadu", lat: 13.0827, lng: 80.2707 },
  "baharampur": { name: "Baharampur", state: "West Bengal (Murshidabad)", lat: 24.0988, lng: 88.2514 },
  "murshidabad": { name: "Murshidabad", state: "West Bengal (Heritage City)", lat: 24.1750, lng: 88.2800 },
  "jangipur": { name: "Jangipur", state: "West Bengal (Murshidabad)", lat: 24.4686, lng: 88.0700 },
  "farakka": { name: "Farakka", state: "West Bengal (Murshidabad)", lat: 24.8167, lng: 87.9000 },
  "kandi": { name: "Kandi", state: "West Bengal (Murshidabad)", lat: 23.9556, lng: 88.0378 },
  "kolkata": { name: "Kolkata", state: "West Bengal", lat: 22.6548, lng: 88.4467 },
  "mayapur": { name: "Mayapur", state: "West Bengal (Nadia)", lat: 23.4232, lng: 88.3900 },
  "siliguri": { name: "Siliguri", state: "West Bengal", lat: 26.7271, lng: 88.3953 },
  "durgapur": { name: "Durgapur", state: "West Bengal", lat: 23.5204, lng: 87.3119 }
};

// Route Distances (km) and base highway times
const ROUTE_MATRIX = {
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
    fromCity: 'pune',
    toCity: 'goa',
    distance: 450,
    travelTime: '9h 15m',
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

  // Driver Bidding State
  bidding: {
    activeTripId: null,
    availableTrips: [
      {
        id: 'trip-wb-101',
        tripType: 'Outstation Cab',
        fromLocation: 'Baharampur (Mohona Central Bus Stand)',
        fromCity: 'Baharampur',
        toLocation: 'Kolkata (Netaji Subhash Chandra Bose Intl Airport, CCU)',
        toCity: 'Kolkata',
        distanceKm: 205,
        estimatedTime: '5h 15m',
        fleetCategory: 'Prime Sedan / Ertiga',
        customerName: 'Anirban Banerjee',
        customerPhone: '+91 98301 22334',
        customerBudget: 3800,
        departureTime: 'Tomorrow, 06:30 AM',
        tollEstimate: '₹180 (Included)',
        notes: 'Airport transit drop by 01:00 PM. 2 standard luggage bags.',
        status: 'Open for Bids',
        bidPlaced: null
      },
      {
        id: 'trip-mh-202',
        tripType: 'Outstation Direct',
        fromLocation: 'Swargate Bus Terminal, Pune',
        fromCity: 'Pune',
        toLocation: 'Panjim KTC Bus Stand / Calangute, Goa',
        toCity: 'Goa',
        distanceKm: 450,
        estimatedTime: '9h 15m',
        fleetCategory: 'Toyota Innova Crysta / SUV',
        customerName: 'Rahul Sharma',
        customerPhone: '+91 98765 43210',
        customerBudget: 4800,
        departureTime: 'Tonight, 09:30 PM',
        tollEstimate: '₹480 (Included)',
        notes: 'Family holiday trip. Smooth highway driving required.',
        status: 'Open for Bids',
        bidPlaced: null
      },
      {
        id: 'trip-wb-303',
        tripType: 'Heritage Intercity',
        fromLocation: 'Murshidabad (Hazarduari Palace Heritage Gate)',
        fromCity: 'Murshidabad',
        toLocation: 'Kolkata (Howrah Railway Station / Central)',
        toCity: 'Kolkata',
        distanceKm: 218,
        estimatedTime: '5h 30m',
        fleetCategory: 'AC Sedan (Dzire / Etios)',
        customerName: 'Poulomi Mukherjee',
        customerPhone: '+91 98310 99887',
        customerBudget: 3950,
        departureTime: 'Tomorrow, 04:00 PM',
        tollEstimate: '₹180 (Included)',
        notes: 'Pickup from hotel lobby. Doorstep drop at Howrah Station.',
        status: 'Open for Bids',
        bidPlaced: null
      },
      {
        id: 'trip-mh-404',
        tripType: 'Expressway Transit',
        fromLocation: 'Koregaon Park, Pune',
        toLocation: 'Chhatrapati Shivaji Maharaj Intl Airport (T2), Mumbai',
        distanceKm: 152,
        estimatedTime: '2h 45m',
        fleetCategory: 'Prime SUV',
        customerName: 'Dr. Vikram Singhania',
        customerPhone: '+91 99000 00001',
        customerBudget: 2900,
        departureTime: 'Today, 11:00 PM',
        tollEstimate: '₹320 (Included)',
        notes: 'Fastag expressway lane. Flight departure 04:30 AM.',
        status: 'Open for Bids',
        bidPlaced: null
      }
    ],
    activeTrip: {
      route: 'Pune to Goa (Outstation Cab)',
      date: 'Tomorrow, 07:00 AM',
      requestedBudget: '₹4,800',
      status: 'Collecting Bids (Active)'
    },
    drivers: [
      {
        id: 'drv-1',
        name: 'Ramesh Patil',
        avatar: '👨🏽‍✈️',
        rating: 4.9,
        trips: 840,
        car: 'Toyota Innova Crysta (White)',
        plate: 'MH 12 QX 4920',
        bid: 5200,
        eta: '10 mins away',
        status: 'pending'
      },
      {
        id: 'drv-2',
        name: 'Ajay Solanki',
        avatar: '🧔🏻‍♂️',
        rating: 4.8,
        trips: 512,
        car: 'Maruti Ertiga Hybrid (Silver)',
        plate: 'MH 14 BT 8812',
        bid: 4750,
        eta: '15 mins away',
        status: 'pending'
      },
      {
        id: 'drv-3',
        name: 'Vikram Deshmukh',
        avatar: '👨🏻‍🦱',
        rating: 4.95,
        trips: 1240,
        car: 'Honda City ZX (Golden Sand)',
        plate: 'MH 02 CK 1009',
        bid: 4900,
        eta: '8 mins away',
        status: 'pending'
      }
    ],
    countdownSec: 290
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
      petCharge: 350,
      nightCharge: 400,
      luggageCharge: 200
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

// Fleet specs & rates
const FLEET_TYPES = {
  hatchback: {
    category: 'hatchback',
    name: 'Hatchback Economy',
    models: 'WagonR, Tata Tiago',
    seats: '4 Seats',
    luggage: '2 Bags',
    ratePerKm: 11.0,
    badge: 'Budget Value',
    extraKm: 11,
    image: 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=400&q=80'
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
    image: 'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=400&q=80'
  }
};

let mapInstance = null;
let mapRouteLine = null;
let mapMarkers = [];

// ==========================================
// Initialization
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
  initAuth();
  initNavigation();
  initCabsModule();
  initSchedulesModule();
  initBiddingModule();
  initAdminModule();
  initMap();
  updateTotalCabFare();
  startBiddingCountdown();
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

  if (tollCheck) tollCheck.addEventListener('change', (e) => { state.cab.includeTolls = e.target.checked; updateTotalCabFare(); });
  if (petCheck) petCheck.addEventListener('change', (e) => { state.cab.petFriendly = e.target.checked; updateTotalCabFare(); });
  if (nightCheck) nightCheck.addEventListener('change', (e) => { state.cab.nightAllowance = e.target.checked; updateTotalCabFare(); });
  if (luggageCheck) luggageCheck.addEventListener('change', (e) => { state.cab.luggageCarrier = e.target.checked; updateTotalCabFare(); });

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

    updateRentalRouteStats();
  };

  window.syncRentalFromCity = function (val) {
    if (!val) return;
    const clean = val.split(',')[0].trim().toLowerCase();
    state.cab.fromCity = clean;
    const legacySelect = document.getElementById('cab-from-city');
    if (legacySelect) legacySelect.value = clean;
    updateRentalRouteStats();
  };

  window.syncRentalToCity = function (val) {
    if (!val) return;
    const clean = val.split(',')[0].trim().toLowerCase();
    state.cab.toCity = clean;
    const legacySelect = document.getElementById('cab-to-city');
    if (legacySelect) legacySelect.value = clean;
    updateRentalRouteStats();
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

    if (state.cab.tripType === 'roundtrip') {
      from = document.getElementById('rental-rt-from')?.value?.split(',')[0].trim().toLowerCase() || from;
      to = document.getElementById('rental-rt-to')?.value?.split(',')[0].trim().toLowerCase() || to;
    } else if (state.cab.tripType === 'local') {
      from = document.getElementById('rental-local-city')?.value?.split(',')[0].trim().toLowerCase() || from;
      to = from;
    } else if (state.cab.tripType === 'airport') {
      from = 'kolkata';
      to = 'kolkata';
    }

    const routeKey = `${from}-${to}`;
    let route = ROUTE_MATRIX[routeKey];
    if (!route) {
      // Dynamic distance calculation based on coordinates if available
      const c1 = CITY_DATABASE[from];
      const c2 = CITY_DATABASE[to];
      if (c1 && c2) {
        const rad = Math.PI / 180;
        const dLat = (c2.lat - c1.lat) * rad;
        const dLng = (c2.lng - c1.lng) * rad;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(c1.lat * rad) * Math.cos(c2.lat * rad) * Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const straightKm = Math.round(6371 * c);
        const roadKm = Math.round(straightKm * 1.25);
        const hours = Math.floor(roadKm / 45);
        const mins = Math.round((roadKm % 45) * 1.3);
        route = { dist: Math.max(roadKm, 25), time: `${hours}h ${mins}m`, toll: Math.round(roadKm * 0.9) };
      } else {
        route = { dist: 132, time: "3h 30m", toll: 120 };
      }
    }

    state.cab.distance = route.dist;
    state.cab.travelTime = route.time;
    state.cab.tollEstimate = route.toll;

    updateRouteRecapTexts();
    updateTotalCabFare();
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
      let baseFare = Math.round(dist * f.ratePerKm);
      let tollFare = state.cab.includeTolls ? (state.cab.tollEstimate || 120) : 0;
      if (state.cab.tripType === 'roundtrip') tollFare = Math.round(tollFare * 1.8);
      if (state.cab.tripType === 'local') tollFare = 0;
      let totalFare = baseFare + tollFare;

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
      <div class="rental-vehicle-card">
        <div class="rental-vehicle-img-wrap">
          <img src="${f.image}" alt="${f.name}" class="rental-vehicle-img" loading="lazy">
        </div>
        <div class="rental-vehicle-info">
          <div class="rental-vehicle-head">
            <span class="rental-vehicle-name">${f.name}</span>
            <span class="rental-category-badge">${f.badge}</span>
          </div>
          <div class="rental-vehicle-models">${f.models} or similar</div>
          <div class="rental-spec-chips">
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">group</span> ${f.seats}</span>
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">luggage</span> ${f.luggage}</span>
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">ac_unit</span> AC Sanitized</span>
            <span class="rental-spec-chip"><span class="material-symbols-outlined" style="font-size:14px;">person</span> Chauffeur Included</span>
          </div>
          <div class="rental-policy-strip">
            <span>✓ Fastag Tolls & Taxes Included</span>
            <span>✓ ₹${f.extraKm}/km after ${dist} km</span>
            <span>✓ Free Cancellation upto 1 hr prior</span>
          </div>
        </div>
        <div class="rental-vehicle-pricing">
          <div class="rental-fare-total">₹${f.totalFare.toLocaleString('en-IN')}</div>
          <div class="rental-fare-sub">up to ${dist} km • all inclusive</div>
          <button type="button" class="btn-book-vehicle" onclick="selectRentalVehicle('${f.key}')">
            <span>BOOK NOW</span>
          </button>
        </div>
      </div>
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

    // Sync pickup / drop into Step 3 inputs
    const fromVal = document.getElementById('rental-from-city')?.value || 'Kolkata';
    const toVal = document.getElementById('rental-to-city')?.value || 'Mayapur, West Bengal';
    const pInput = document.getElementById('cab-pickup-address');
    const dInput = document.getElementById('cab-drop-address');
    if (pInput && !pInput.value) pInput.value = `${fromVal} Central`;
    if (dInput && !dInput.value) dInput.value = `${toVal} Landmark`;

    updateTotalCabFare();
    goToCabStep(3);

    const s3 = document.getElementById('cab-step-3');
    if (s3) {
      s3.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  window.applyRentalCoupon = function () {
    const input = document.getElementById('cab-coupon-input');
    const val = input ? input.value.trim().toUpperCase() : '';
    const msg = document.getElementById('coupon-msg');

    if (val === 'FIRSTWHEELS') {
      state.cab.appliedCoupon = 'FIRSTWHEELS';
      if (msg) {
        msg.textContent = '🎉 Coupon FIRSTWHEELS applied! 15% discount saved.';
        msg.style.color = '#16a34a';
      }
      showToast('Promo code FIRSTWHEELS applied successfully!', 'success');
    } else if (val === 'INTERCITY100') {
      state.cab.appliedCoupon = 'INTERCITY100';
      if (msg) {
        msg.textContent = '🎉 Coupon INTERCITY100 applied! Flat ₹100 off.';
        msg.style.color = '#16a34a';
      }
      showToast('Promo code INTERCITY100 applied successfully!', 'success');
    } else {
      state.cab.appliedCoupon = null;
      if (msg) {
        msg.textContent = '❌ Invalid coupon. Try FIRSTWHEELS or INTERCITY100';
        msg.style.color = '#dc2626';
      }
      showToast('Invalid coupon code.', 'warning');
    }
    updateTotalCabFare();
  };

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

    // On Step 3: sync contact details from input
    if (step === 3) {
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

    if (state.cab.tripType === 'oneway') {
      fromCityName = document.getElementById('rental-from-city')?.value.split(',')[0].trim() || 'Kolkata';
      toCityName = document.getElementById('rental-to-city')?.value.split(',')[0].trim() || 'Mayapur';
    } else if (state.cab.tripType === 'roundtrip') {
      fromCityName = document.getElementById('rental-rt-from')?.value.split(',')[0].trim() || 'Kolkata';
      toCityName = document.getElementById('rental-rt-to')?.value.split(',')[0].trim() || 'Mayapur';
    } else if (state.cab.tripType === 'local') {
      fromCityName = document.getElementById('rental-local-city')?.value.split(',')[0].trim() || 'Kolkata';
      toCityName = fromCityName;
    } else if (state.cab.tripType === 'airport') {
      fromCityName = 'Kolkata';
      toCityName = 'Kolkata Airport';
    }

    const pickupLoc = (pickupInput && pickupInput.value.trim()) ? pickupInput.value.trim() : `${fromCityName} Central Area`;
    const dropLoc = (dropInput && dropInput.value.trim()) ? dropInput.value.trim() : `${toCityName} Center`;

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    const payload = {
      service_type: 'cab',
      trip_type: state.cab.tripType || 'oneway',
      from_city: fromCityName,
      to_city: toCityName,
      pickup_location: pickupLoc,
      drop_location: dropLoc,
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
      coupon_code: state.cab.appliedCoupon || ''
    };

    const origBtnContent = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = `<span class="material-symbols-outlined" style="animation:spin 1s linear infinite; font-size:18px;">sync</span> <span>Locking Fare & Confirming...</span>`;
    }

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
      if (data.success && data.booking) {
        const b = data.booking;
        const localBooking = {
          id: b.booking_reference,
          type: 'Outstation Cab',
          route: `${fromCityName} → ${toCityName}`,
          date: 'Scheduled, Confirmed',
          operator: `${FLEET_TYPES[state.cab.selectedFleet]?.name || 'Executive SUV'} (Intercity Wheels)`,
          berths: ['Private Car Rental'],
          passengers: [customerName],
          amount: b.total_amount,
          status: 'Confirmed & Reserved',
          otp: String(b.start_otp)
        };

        state.bookings.unshift(localBooking);
        showToast(`🎉 Cab Booked Successfully! Reference: ${b.booking_reference} (Ride OTP: ${b.start_otp})`, 'success');

        // Update My Trips badge counter in header if present
        const badge = document.querySelector('.nav-tab-badge.badge-primary');
        if (badge) {
          badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1);
        }

        showTicketModal(localBooking);
        goToCabStep(1);
      } else {
        showToast(data.message || 'Booking validation error. Please verify input.', 'danger');
      }
    } catch (err) {
      console.warn('Booking submission error:', err);
      openPaymentModal('cab');
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

    // Update DOM stats
    document.getElementById('cab-stat-distance').textContent = `${route.dist} km`;
    document.getElementById('cab-stat-time').textContent = route.time;
    document.getElementById('cab-stat-toll').textContent = `₹${route.toll}`;
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

  function updateTotalCabFare() {
    const fleet = FLEET_TYPES[state.cab.selectedFleet] || FLEET_TYPES.suv;
    let dist = state.cab.distance;
    if (state.cab.tripType === 'roundtrip') dist = dist * 2;

    let baseFare = dist * fleet.ratePerKm;
    let tolls = state.cab.includeTolls ? (ROUTE_MATRIX[`${state.cab.fromCity}-${state.cab.toCity}`]?.toll || 400) : 0;
    if (state.cab.tripType === 'roundtrip') tolls = tolls * 1.8;

    let addons = 0;
    if (state.cab.petFriendly) addons += state.admin.pricingRules.petCharge;
    if (state.cab.nightAllowance) addons += state.admin.pricingRules.nightCharge;
    if (state.cab.luggageCarrier) addons += state.admin.pricingRules.luggageCharge;

    let subtotal = Math.round(baseFare + tolls + addons);
    let discount = 0;

    if (state.cab.appliedCoupon === 'FIRSTWHEELS') {
      discount = Math.min(Math.round(subtotal * 0.15), 500);
    } else if (state.cab.appliedCoupon === 'INTERCITY100') {
      discount = 100;
    }
    state.cab.discountAmount = discount;

    const total = Math.max(subtotal - discount, 0);

    // Update UI breakdown
    const elBase = document.getElementById('fare-base');
    const elTolls = document.getElementById('fare-tolls');
    const elAddons = document.getElementById('fare-addons');
    const elDiscount = document.getElementById('fare-discount');
    const elTotal = document.getElementById('fare-grand-total');

    if (elBase) elBase.textContent = `₹${Math.round(baseFare)}`;
    if (elTolls) elTolls.textContent = `₹${Math.round(tolls)}`;
    if (elAddons) elAddons.textContent = `₹${addons}`;
    if (elDiscount) elDiscount.textContent = `-₹${discount}`;
    if (elTotal) elTotal.textContent = `₹${total.toLocaleString('en-IN')}`;

    // Update fleet prices
    for (const [key, fData] of Object.entries(FLEET_TYPES)) {
      const fPriceEl = document.getElementById(`fleet-price-${key}`);
      if (fPriceEl) {
        let fTotal = Math.round(dist * fData.ratePerKm + (state.cab.includeTolls ? 400 : 0));
        fPriceEl.textContent = `₹${fTotal.toLocaleString('en-IN')}`;
      }
    }
  }

  function applyCouponCode() {
    const input = document.getElementById('cab-coupon-input');
    const val = input.value.trim().toUpperCase();
    const msgEl = document.getElementById('coupon-msg');

    if (val === 'FIRSTWHEELS') {
      state.cab.appliedCoupon = 'FIRSTWHEELS';
      msgEl.textContent = '🎉 Coupon FIRSTWHEELS applied! 15% discount saved.';
      msgEl.className = 'text-xs font-semibold text-emerald-600 mt-1';
      showToast('Promo code FIRSTWHEELS applied successfully!', 'success');
    } else if (val === 'INTERCITY100') {
      state.cab.appliedCoupon = 'INTERCITY100';
      msgEl.textContent = '🎉 Coupon INTERCITY100 applied! Flat ₹100 off.';
      msgEl.className = 'text-xs font-semibold text-emerald-600 mt-1';
      showToast('Promo code INTERCITY100 applied successfully!', 'success');
    } else {
      msgEl.textContent = '❌ Invalid coupon code. Try FIRSTWHEELS or INTERCITY100';
      msgEl.className = 'text-xs font-semibold text-rose-600 mt-1';
      state.cab.appliedCoupon = null;
      showToast('Invalid coupon code entered.', 'warning');
    }
    updateTotalCabFare();
  }

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
  function initBiddingModule() {
    renderBiddingList();

    const postTripBtn = document.getElementById('post-custom-trip-btn');
    if (postTripBtn) {
      postTripBtn.addEventListener('click', () => {
        const budget = prompt('Enter your target budget for this trip (e.g., ₹4500):', '₹4,500');
        if (budget) {
          state.bidding.activeTrip.requestedBudget = budget;
          document.getElementById('bidding-target-budget').textContent = budget;
          showToast(`Trip request updated with target budget ${budget}!`, 'success');

          // Simulate new incoming driver bid after 3 seconds
          setTimeout(() => {
            simulateIncomingBid();
          }, 3000);
        }
      });
    }
  }

  function renderBiddingList() {
    const container = document.getElementById('bidding-cards-stream');
    if (!container) return;

    if (!state.bidding.availableTrips || !state.bidding.availableTrips.length) {
      container.innerHTML = `<div style="text-align:center; padding:30px; color:#94a3b8;">No active trip requests available for bidding at the moment.</div>`;
      return;
    }

    container.innerHTML = state.bidding.availableTrips.map(trip => {
      const isBidPlaced = Boolean(trip.bidPlaced);
      return `
      <div class="bid-card" id="card-${trip.id}" style="display:flex; flex-direction:column; gap:12px; padding:16px 18px; border:1px solid ${isBidPlaced ? '#86efac' : '#e2e8f0'}; background:${isBidPlaced ? '#f0fdf4' : '#ffffff'}; border-radius:10px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <!-- Top Metadata Row -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <span class="admin-badge badge-primary" style="font-size:10.5px; font-weight:700;">${trip.tripType}</span>
            <span style="font-size:11.5px; color:#64748b; font-weight:600;">📅 ${trip.departureTime}</span>
          </div>
          <div>
            ${isBidPlaced 
              ? `<span class="admin-badge badge-success" style="font-size:11px; padding:4px 8px;">✓ Your Bid Placed: ₹${trip.bidPlaced.amount.toLocaleString('en-IN')} (Accepted)</span>`
              : `<span class="admin-badge badge-gold" style="font-size:10.5px;">⚡ Bidding Live</span>`
            }
          </div>
        </div>

        <!-- From -> To & KM Display Row -->
        <div style="display:grid; grid-template-columns: 1fr auto 1fr; gap:12px; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;">
          <!-- FROM LOCATION -->
          <div>
            <div style="font-size:10px; font-weight:800; color:#0284c7; text-transform:uppercase; display:flex; align-items:center; gap:4px; letter-spacing:0.04em;">
              <span class="material-symbols-outlined" style="font-size:14px;">trip_origin</span>
              <span>Pickup (From)</span>
            </div>
            <div style="font-size:13.5px; font-weight:700; color:#0f172a; margin-top:2px;">
              ${trip.fromLocation}
            </div>
          </div>

          <!-- DISTANCE IN KM PILL -->
          <div style="text-align:center; padding:0 8px;">
            <div style="background:#0b0f19; color:#f8fafc; border:1px solid rgba(212,155,76,0.4); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:800; font-family:monospace; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
              <span>🛣️</span>
              <span style="color:#d49b4c;">${trip.distanceKm} KM</span>
            </div>
            <div style="font-size:10.5px; color:#64748b; margin-top:3px; font-weight:600;">⏱️ ${trip.estimatedTime}</div>
          </div>

          <!-- TO LOCATION -->
          <div>
            <div style="font-size:10px; font-weight:800; color:#16a34a; text-transform:uppercase; display:flex; align-items:center; gap:4px; letter-spacing:0.04em;">
              <span class="material-symbols-outlined" style="font-size:14px;">location_on</span>
              <span>Drop (To)</span>
            </div>
            <div style="font-size:13.5px; font-weight:700; color:#0f172a; margin-top:2px;">
              ${trip.toLocation}
            </div>
          </div>
        </div>

        <!-- Trip Details & Actions Row -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px dashed #e2e8f0; padding-top:10px;">
          <div style="font-size:12px; color:#475569;">
            <span>Passenger: <strong style="color:#0284c7;">Verified Traveler 🔒 (Masked until Bid Won)</strong></span> &bull; 
            <span>Vehicle: <strong>${trip.fleetCategory}</strong></span> &bull; 
            <span style="color:#64748b; font-style:italic;">"${trip.notes}"</span>
            <div style="font-size:10.5px; color:#64748b; margin-top:3px; display:flex; align-items:center; gap:4px;">
              <span class="material-symbols-outlined" style="font-size:13px; color:#10b981;">lock</span>
              <span>Passenger phone & exact pickup address remain masked until you win the bid.</span>
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:12px;">
            <div style="text-align:right;">
              <div style="font-size:10px; text-transform:uppercase; color:#64748b; font-weight:700;">Customer Budget</div>
              <div style="font-size:18px; font-weight:800; color:#16a34a;">₹${trip.customerBudget.toLocaleString('en-IN')}</div>
            </div>

            <div style="display:flex; gap:8px;">
              <button type="button" class="btn btn-sm btn-outline-gold" onclick="openDriverBidModal('${trip.id}')" title="Place your binding bid">
                <span class="material-symbols-outlined" style="font-size:15px;">gavel</span>
                <span>${isBidPlaced ? 'Modify Bid' : 'Bid on This Ride'}</span>
              </button>
              <button type="button" class="btn btn-sm btn-primary" onclick="quickAcceptTrip('${trip.id}')" title="Conclusively accept at passenger budget">
                <span class="material-symbols-outlined" style="font-size:15px;">check_circle</span>
                <span>Accept at ₹${trip.customerBudget.toLocaleString('en-IN')}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      `;
    }).join('');
  }

  window.openDriverBidModal = function (tripId) {
    const trip = state.bidding.availableTrips.find(t => t.id === tripId);
    if (!trip) return;

    state.bidding.activeTripId = tripId;

    document.getElementById('driver-bid-trip-type').textContent = trip.tripType;
    document.getElementById('driver-bid-trip-schedule').textContent = `📅 ${trip.departureTime}`;
    document.getElementById('driver-bid-from-loc').textContent = trip.fromLocation;
    document.getElementById('driver-bid-to-loc').textContent = trip.toLocation;
    document.getElementById('driver-bid-distance-km').textContent = `${trip.distanceKm} km`;
    document.getElementById('driver-bid-est-time').textContent = trip.estimatedTime;
    document.getElementById('driver-bid-customer-budget').textContent = `₹${trip.customerBudget.toLocaleString('en-IN')}`;

    const input = document.getElementById('driver-bid-amount-input');
    input.value = trip.bidPlaced ? trip.bidPlaced.amount : trip.customerBudget;

    const chk = document.getElementById('driver-bid-accept-checkbox');
    if (chk) chk.checked = true;
    toggleBidSubmitButton(true);

    updateConclusiveWriteup();

    const modal = document.getElementById('driver-bid-modal');
    if (modal) modal.classList.add('active');
  };

  window.quickAcceptTrip = function (tripId) {
    const trip = state.bidding.availableTrips.find(t => t.id === tripId);
    if (!trip) return;

    openDriverBidModal(tripId);
    const input = document.getElementById('driver-bid-amount-input');
    if (input) input.value = trip.customerBudget;
    updateConclusiveWriteup();
  };

  window.updateConclusiveWriteup = function () {
    const tripId = state.bidding.activeTripId;
    const trip = state.bidding.availableTrips.find(t => t.id === tripId);
    if (!trip) return;

    const input = document.getElementById('driver-bid-amount-input');
    const amount = parseInt(input.value, 10) || trip.customerBudget;

    document.getElementById('writeup-from').textContent = trip.fromLocation;
    document.getElementById('writeup-to').textContent = trip.toLocation;
    document.getElementById('writeup-km').textContent = `${trip.distanceKm} km`;
    document.getElementById('writeup-price').textContent = `₹${amount.toLocaleString('en-IN')}`;
    document.getElementById('btn-bid-amount-preview').textContent = `₹${amount.toLocaleString('en-IN')}`;
  };

  window.adjustDriverBidPrice = function (delta) {
    const input = document.getElementById('driver-bid-amount-input');
    let val = parseInt(input.value, 10) || 1000;
    val = Math.max(100, val + delta);
    input.value = val;
    updateConclusiveWriteup();
  };

  window.resetDriverBidToTarget = function () {
    const tripId = state.bidding.activeTripId;
    const trip = state.bidding.availableTrips.find(t => t.id === tripId);
    if (!trip) return;

    const input = document.getElementById('driver-bid-amount-input');
    input.value = trip.customerBudget;
    updateConclusiveWriteup();
  };

  window.toggleBidSubmitButton = function (checked) {
    const btn = document.getElementById('btn-submit-conclusive-bid');
    if (btn) {
      btn.disabled = !checked;
      btn.style.opacity = checked ? '1' : '0.5';
    }
  };

  window.submitConclusiveDriverBid = async function () {
    const tripId = state.bidding.activeTripId;
    const trip = state.bidding.availableTrips.find(t => t.id === tripId);
    if (!trip) return;

    const chk = document.getElementById('driver-bid-accept-checkbox');
    if (chk && !chk.checked) {
      showToast('Please check the acceptance declaration to confirm this bid.', 'warning');
      return;
    }

    const input = document.getElementById('driver-bid-amount-input');
    const amount = parseInt(input.value, 10);
    if (!amount || amount < 100) {
      showToast('Please enter a valid fare proposal.', 'warning');
      return;
    }

    try {
      const res = await fetch('/api/bids/submit', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getAdminCsrfToken()
        },
        body: JSON.stringify({
          trip_id: trip.id,
          from_location: trip.fromLocation,
          to_location: trip.toLocation,
          distance_km: trip.distanceKm,
          bid_amount: amount
        })
      });

      const data = await res.json();
      if (res.ok && data.success) {
        trip.bidPlaced = {
          amount: amount,
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          statement: data.bid?.conclusive_statement || `I will take and do this ride from ${trip.fromLocation} to ${trip.toLocation} (${trip.distanceKm} km) at this price: ₹${amount.toLocaleString('en-IN')}. I accept this.`
        };

        closeModal('driver-bid-modal');
        renderBiddingList();

        showToast(`🤝 Bid Confirmed: "I will take and do this ride from ${trip.fromLocation} to ${trip.toLocation} (${trip.distanceKm} km) at ₹${amount.toLocaleString('en-IN')}. I accept this."`, 'success');
      } else {
        showToast(data.message || 'Failed to submit bid.', 'warning');
      }
    } catch (err) {
      console.error('Bid submit error:', err);
      // Fallback local update if offline or network glitch
      trip.bidPlaced = {
        amount: amount,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      };
      closeModal('driver-bid-modal');
      renderBiddingList();
      showToast(`🤝 Bid of ₹${amount.toLocaleString('en-IN')} confirmed for ${trip.fromLocation} → ${trip.toLocation}!`, 'success');
    }
  };

  function startBiddingCountdown() {
    const timerEl = document.getElementById('bidding-timer-text');
    if (!timerEl) return;

    setInterval(() => {
      if (state.bidding.countdownSec > 0) {
        state.bidding.countdownSec--;
        const mins = Math.floor(state.bidding.countdownSec / 60);
        const secs = state.bidding.countdownSec % 60;
        timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
      }
    }, 1000);
  }

  function simulateIncomingBid() {
    const newDriver = {
      id: `drv-${Date.now()}`,
      name: 'Santosh Ghadge',
      avatar: '👨🏻‍🦰',
      rating: 4.88,
      trips: 690,
      car: 'Maruti Suzuki Dzire (Prime)',
      plate: 'MH 12 PK 2201',
      bid: 4400,
      eta: '5 mins away',
      status: 'pending'
    };

    state.bidding.drivers.unshift(newDriver);
    renderBiddingList();
    showToast('⚡ New Driver Bid received: Santosh offered ₹4,400!', 'gold');
  }

  window.openCounterOfferModal = function (driverId, currentBid) {
    const modal = document.getElementById('counter-modal');
    const targetDriver = state.bidding.drivers.find(d => d.id === driverId);
    if (!targetDriver) return;

    document.getElementById('counter-driver-name').textContent = targetDriver.name;
    document.getElementById('counter-current-bid').textContent = `₹${currentBid}`;
    document.getElementById('counter-target-input').value = currentBid - 300;

    modal.dataset.driverId = driverId;
    modal.classList.add('active');
  };

  window.submitCounterOffer = function () {
    const modal = document.getElementById('counter-modal');
    const driverId = modal.dataset.driverId;
    const counterVal = parseInt(document.getElementById('counter-target-input').value, 10);

    modal.classList.remove('active');
    showToast(`Counter offer of ₹${counterVal} sent to driver!`, 'info');

    // Driver response simulation
    setTimeout(() => {
      const drv = state.bidding.drivers.find(d => d.id === driverId);
      if (drv) {
        drv.bid = counterVal;
        renderBiddingList();
        showToast(`🤝 Driver ${drv.name} ACCEPTED your counter-bid of ₹${counterVal}!`, 'success');
      }
    }, 2000);
  };

  window.acceptDriverBid = function (driverId) {
    const driver = state.bidding.drivers.find(d => d.id === driverId);
    if (!driver) return;

    const otp = Math.floor(1000 + Math.random() * 9000);
    const newBooking = {
      id: `ICW-${Math.floor(10000 + Math.random() * 90000)}`,
      type: 'Outstation Bidding Cab',
      route: state.bidding.activeTrip.route,
      date: state.bidding.activeTrip.date,
      operator: `${driver.name} (${driver.car})`,
      berths: [driver.plate],
      passengers: ['Customer (Confirmed)'],
      amount: driver.bid,
      status: 'Driver Assigned',
      otp: String(otp)
    };

    state.bookings.unshift(newBooking);
    showTicketModal(newBooking);
  };

  // ==========================================
  // 4. AdminLTE 3 Module & Dynamic Form Builder
  // ==========================================
  function initAdminModule() {
    renderFormBuilderFields();
    renderWorkflowStages();

    // Surcharges update
    const savePricingBtn = document.getElementById('save-pricing-rules-btn');
    if (savePricingBtn) {
      savePricingBtn.addEventListener('click', () => {
        state.admin.pricingRules.petCharge = parseInt(document.getElementById('rule-pet-charge').value, 10) || 350;
        state.admin.pricingRules.nightCharge = parseInt(document.getElementById('rule-night-charge').value, 10) || 400;
        state.admin.pricingRules.luggageCharge = parseInt(document.getElementById('rule-luggage-charge').value, 10) || 200;
        updateTotalCabFare();
        showToast('Pricing & Surcharge rule matrix saved!', 'success');
      });
    }
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
      'bookings': 'Outstation Bookings & Dispatch Management',
      'users': 'Registered Users & Drivers Directory',
      'locations': 'Service Locations & Network Hubs',
      'cab-routes': 'Outstation Cab Routes & Highway Matrix',
      'booking-types': 'Booking Types & Packages Management',
      'berth-routes': 'Berth Routes & Sleeper Schedules',
      'berth-layouts': 'Vehicle Berth & Seat Customizer',
      'vehicles': 'Driver Vehicles & Fleet Approvals',
      'system': 'System & Platform Health'
    };
    const crumbTitle = document.getElementById('admin-crumb-title');
    if (crumbTitle && titles[sectionId]) {
      crumbTitle.textContent = titles[sectionId];
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
      const fleet = FLEET_TYPES[state.cab.selectedFleet];
      amount = Math.round(state.cab.distance * fleet.ratePerKm + (state.cab.includeTolls ? 400 : 0) - state.cab.discountAmount);
      summary = `Outstation Cab: ${CITY_DATABASE[state.cab.fromCity].name} → ${CITY_DATABASE[state.cab.toCity].name}`;
    }

    document.getElementById('payment-modal-amount').textContent = `₹${amount.toLocaleString('en-IN')}`;
    document.getElementById('payment-modal-desc').textContent = summary;
    modal.dataset.source = sourceType;
    modal.dataset.amount = amount;
    modal.classList.add('active');
  }

  window.confirmMockPayment = function (method) {
    const modal = document.getElementById('payment-modal');
    modal.classList.remove('active');

    const source = modal.dataset.source;
    const amount = parseInt(modal.dataset.amount, 10);
    const otp = Math.floor(1000 + Math.random() * 9000);

    const booking = {
      id: `ICW-${Math.floor(10000 + Math.random() * 90000)}`,
      type: source === 'bus' ? 'Volvo AC Sleeper' : 'Outstation Cab',
      route: source === 'bus' ? state.schedules.route : `${CITY_DATABASE[state.cab.fromCity].name} → ${CITY_DATABASE[state.cab.toCity].name}`,
      date: 'Today, Scheduled',
      operator: source === 'bus' ? state.schedules.operator : `${FLEET_TYPES[state.cab.selectedFleet].name} (Intercity Fleet)`,
      berths: source === 'bus' ? state.schedules.selectedSeats.map(s => s.id) : ['Private Cab'],
      passengers: source === 'bus' ? state.schedules.passengers.map(p => p.name || 'Passenger') : ['Verified Traveller'],
      amount: amount,
      status: 'Confirmed & Paid',
      otp: String(otp)
    };

    state.bookings.unshift(booking);
    showToast(`Payment of ₹${amount} via ${method} Successful!`, 'success');

    setTimeout(() => {
      showTicketModal(booking);
    }, 400);
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

  window.openLoginModal = function (role = 'customer') {
    selectLoginRole(role);
    const modal = document.getElementById('login-modal');
    if (modal) modal.classList.add('active');
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

    // Preset demo credentials for that role
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    if (emailInput) emailInput.value = cfg.email;
    if (passInput) passInput.value = 'password123';

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
  window.handleBookingFilterChange = function () {
    const query = (document.getElementById('admin-booking-search-input')?.value || '').toLowerCase().trim();
    const statusFilter = document.getElementById('admin-booking-filter-status')?.value || 'all';
    const serviceFilter = document.getElementById('admin-booking-filter-service')?.value || 'all';

    const rows = document.querySelectorAll('#admin-bookings-table-tbody tr[data-search]');
    let visibleCount = 0;

    rows.forEach(row => {
      const searchData = row.dataset.search || '';
      const rowStatus = row.dataset.status || '';
      const rowService = row.dataset.service || '';

      const matchQuery = !query || searchData.includes(query);
      const matchStatus = (statusFilter === 'all') || (rowStatus.toLowerCase() === statusFilter.toLowerCase());
      const matchService = (serviceFilter === 'all') || (rowService.toLowerCase() === serviceFilter.toLowerCase());

      if (matchQuery && matchStatus && matchService) {
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
  };

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

      const searchStr = `${b.booking_reference} ${b.customer_name} ${b.customer_phone} ${b.route_summary} ${b.pickup_location} ${b.drop_location}`.toLowerCase();

      return `
      <tr id="booking-row-${b.id}" data-id="${b.id}" data-ref="${b.booking_reference}" data-status="${b.status}" data-service="${b.service_type}" data-search="${searchStr}">
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
          <div style="display:flex; align-items:center; gap:4px;">
            <span class="admin-badge ${b.service_type === 'cab' ? 'badge-primary' : 'badge-gold'}" style="font-size:9.5px; padding:1px 5px;">
              ${(b.service_type || 'CAB').toUpperCase()}
            </span>
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
            <span class="status-indicator-dot ${b.status === 'Cancelled' ? 'bg-danger' : 'bg-success'}"></span>
            <span>Prepaid (Escrow)</span>
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
          </div>
        </td>
      </tr>
    `;
    }).join('');
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
      document.getElementById('allot-info-route').textContent = `${(b.service_type || 'Cab').toUpperCase()} • ${b.route_summary || ''}`;

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
  window.openInvoiceModal = async function (bookingId) {
    window.currentViewingBookingId = bookingId;
    try {
      const res = await fetch(`/api/admin/bookings/${bookingId}/invoice`);
      const data = await res.json();
      if (!data.success || !data.invoice) return;

      const inv = data.invoice;
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
          if (walletEl) {
            const curVal = parseInt(walletEl.textContent.replace(/[^0-9]/g, ''), 10) || 0;
            walletEl.textContent = `₹${(curVal + payout).toLocaleString('en-IN')}`;
          }
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

      const searchStr = `${u.name} ${u.email} ${u.phone} ${u.role} ${u.driver ? u.driver.vehicle_plate + ' ' + u.driver.vehicle_model : ''}`.toLowerCase();

      return `
      <tr id="user-row-${u.id}" data-id="${u.id}" data-role="${u.role}" data-search="${searchStr}">
        <td>
          <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:20px; width:30px; height:30px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center;">
              ${u.avatar || (u.role === 'admin' ? '🛡️' : (u.role === 'driver' ? '👨🏽‍✈️' : '👤'))}
            </span>
            <div>
              <div class="cell-primary-text">${u.name}</div>
              <div class="cell-subtext">ID: #USR-${String(u.id).padStart(4, '0')}</div>
            </div>
          </div>
        </td>
        <td>
          <span class="admin-badge ${roleClass}">
            ${u.role.toUpperCase()}
          </span>
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
      document.getElementById('view-user-avatar').textContent = u.avatar || (u.role === 'admin' ? '🛡️' : (u.role === 'driver' ? '👨🏽‍✈️' : '👤'));
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
      }
    } catch (err) {
      showToast('Failed to delete location.', 'warning');
    }
  };

  // ========================================================
  // 1B. OUTSTATION CAB ROUTES MANAGEMENT (Admin)
  // ========================================================
  window.openAddCabRouteModal = function () {
    document.getElementById('admin-cab-route-modal-title').textContent = 'Add Outstation Cab Route';
    document.getElementById('admin-cab-route-id').value = '';
    document.getElementById('admin-cab-route-from').value = '';
    document.getElementById('admin-cab-route-to').value = '';
    document.getElementById('admin-cab-route-dist').value = '';
    document.getElementById('admin-cab-route-time').value = '4h 30m';
    document.getElementById('admin-cab-route-toll').value = '0';
    if (document.getElementById('admin-cab-route-sedan-fare')) document.getElementById('admin-cab-route-sedan-fare').value = '';
    if (document.getElementById('admin-cab-route-suv-fare')) document.getElementById('admin-cab-route-suv-fare').value = '';
    if (document.getElementById('admin-cab-route-roundtrip-discount')) document.getElementById('admin-cab-route-roundtrip-discount').value = '0';
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
      document.getElementById('admin-cab-route-dist').value = route.distance_km;
      document.getElementById('admin-cab-route-time').value = route.estimated_time;
      document.getElementById('admin-cab-route-toll').value = route.base_toll || 0;
      if (document.getElementById('admin-cab-route-sedan-fare')) document.getElementById('admin-cab-route-sedan-fare').value = route.sedan_fare || '';
      if (document.getElementById('admin-cab-route-suv-fare')) document.getElementById('admin-cab-route-suv-fare').value = route.suv_fare || '';
      if (document.getElementById('admin-cab-route-roundtrip-discount')) document.getElementById('admin-cab-route-roundtrip-discount').value = route.roundtrip_discount_pct || 0;
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

    const payload = {
      from_city: document.getElementById('admin-cab-route-from').value.trim(),
      to_city: document.getElementById('admin-cab-route-to').value.trim(),
      distance_km: parseFloat(document.getElementById('admin-cab-route-dist').value),
      estimated_time: document.getElementById('admin-cab-route-time').value.trim(),
      base_toll: parseFloat(document.getElementById('admin-cab-route-toll').value) || 0,
      sedan_fare: document.getElementById('admin-cab-route-sedan-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-sedan-fare').value) : null,
      suv_fare: document.getElementById('admin-cab-route-suv-fare')?.value ? parseFloat(document.getElementById('admin-cab-route-suv-fare').value) : null,
      roundtrip_discount_pct: document.getElementById('admin-cab-route-roundtrip-discount')?.value ? parseFloat(document.getElementById('admin-cab-route-roundtrip-discount').value) : 0,
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
          const sedanFare = r.sedan_fare ? Number(r.sedan_fare) : Math.round(r.distance_km * 12.5 + (r.base_toll || 0));
          const suvFare = r.suv_fare ? Number(r.suv_fare) : Math.round(r.distance_km * 18.0 + (r.base_toll || 0));
          const rtDisc = r.roundtrip_discount_pct > 0 ? `${r.roundtrip_discount_pct}% Return Disc.` : '250 KM/Day Min';

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
              <div style="font-size:11px; color:#64748b;">Point-to-Point Outstation Highway Corridor</div>
            `;
            row.cells[1].innerHTML = `<span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">🛣️ ${r.distance_km} KM</span>`;
            row.cells[2].innerHTML = `<span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ ${r.estimated_time}</span>`;
            row.cells[3].innerHTML = `<strong style="color:#0f172a;">₹${Number(r.base_toll || 0).toLocaleString('en-IN')}</strong>`;
            row.cells[4].innerHTML = `<span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${sedanFare.toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${suvFare.toLocaleString('en-IN')}</strong></span>`;
            if (isBt) {
              row.cells[5].innerHTML = `<span style="font-size:11px; color:#16a34a; font-weight:600;">${rtDisc}</span>`;
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
                <div style="font-size:11px; color:#64748b;">Point-to-Point Outstation Highway Corridor</div>
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
                <span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${sedanFare.toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${suvFare.toLocaleString('en-IN')}</strong></span>
              </td>
              ${isBt ? `<td><span style="font-size:11px; color:#16a34a; font-weight:600;">${rtDisc}</span></td>` : ''}
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
          } else {
            createRow('admin-cab-routes-tbody', 'admin-cab-route-row', false);
            createRow('admin-bt-routes-tbody', 'admin-bt-cab-route-row', true);
          }

          const totalRoutes = document.querySelectorAll('#admin-cab-routes-tbody tr[id^="admin-cab-route-row-"]').length || document.querySelectorAll('#admin-bt-routes-tbody tr[id^="admin-bt-cab-route-row-"]').length;
          const filterCountEl = document.getElementById('admin-cab-routes-filter-count');
          if (filterCountEl) filterCountEl.textContent = `${totalRoutes} Routes`;
          const btFilterCountEl = document.getElementById('admin-bt-routes-filter-count');
          if (btFilterCountEl) btFilterCountEl.textContent = `${totalRoutes} Routes`;
          const navBadge = document.getElementById('admin-badge-cab-routes-count');
          if (navBadge) navBadge.textContent = totalRoutes;
          const tabBadge = document.getElementById('admin-tab-count-outstation');
          if (tabBadge) tabBadge.textContent = totalRoutes;
          const topPill = document.getElementById('admin-bt-total-routes-pill');
          if (topPill) topPill.textContent = `${totalRoutes} Routes`;
        }
      } else {
        showToast(data.message || 'Validation error while saving cab route.', 'danger');
      }
    } catch (err) {
      console.error('Save cab route error:', err);
      showToast('Network error while saving cab route.', 'warning');
    }
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

        ['cab-route-status-badge-', 'bt-cab-route-status-badge-'].forEach(prefix => {
          const badge = document.getElementById(`${prefix}${id}`);
          if (badge) {
            badge.className = `admin-badge ${isActive ? 'badge-success' : 'badge-danger'}`;
            badge.textContent = isActive ? 'Active' : 'Inactive';
          }
        });

        ['admin-cab-route-row-', 'admin-bt-cab-route-row-'].forEach(prefix => {
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

        const totalRoutes = document.querySelectorAll('#admin-cab-routes-tbody tr[id^="admin-cab-route-row-"]').length || document.querySelectorAll('#admin-bt-routes-tbody tr[id^="admin-bt-cab-route-row-"]').length;
        const filterCountEl = document.getElementById('admin-cab-routes-filter-count');
        if (filterCountEl) filterCountEl.textContent = `${totalRoutes} Routes`;
        const btFilterCountEl = document.getElementById('admin-bt-routes-filter-count');
        if (btFilterCountEl) btFilterCountEl.textContent = `${totalRoutes} Routes`;
        const navBadge = document.getElementById('admin-badge-cab-routes-count');
        if (navBadge) navBadge.textContent = totalRoutes;
        const tabBadge = document.getElementById('admin-tab-count-outstation');
        if (tabBadge) tabBadge.textContent = totalRoutes;
        const topPill = document.getElementById('admin-bt-total-routes-pill');
        if (topPill) topPill.textContent = `${totalRoutes} Routes`;
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
    ['#admin-cab-routes-tbody tr', '#admin-bt-routes-tbody tr'].forEach(selector => {
      const rows = document.querySelectorAll(selector);
      let visibleCount = 0;
      rows.forEach(r => {
        const text = (r.dataset.search || '').toLowerCase();
        const statusFilter = document.getElementById('admin-cab-route-filter-status')?.value || document.getElementById('admin-bt-route-filter-status')?.value || 'all';
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
      const countEl = document.getElementById('admin-cab-routes-filter-count');
      if (countEl) countEl.textContent = `${visibleCount} Routes`;
      const btCountEl = document.getElementById('admin-bt-routes-filter-count');
      if (btCountEl) btCountEl.textContent = `${visibleCount} Routes`;
    });
  };

  window.handleCabRouteFilter = function () {
    const q = document.getElementById('admin-cab-route-search-input')?.value || document.getElementById('admin-bt-route-search-input')?.value || '';
    handleCabRouteSearch(q);
  };

  // ========================================================
  // 1B. MODULAR BOOKING TYPES SUB-NAV & MANAGERS
  // ========================================================
  window.switchBookingTypeTab = function (tabKey) {
    document.querySelectorAll('.admin-bt-tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabKey);
    });
    document.querySelectorAll('.admin-bt-tab-panel').forEach(panel => {
      panel.style.display = (panel.id === `admin-bt-panel-${tabKey}`) ? 'block' : 'none';
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
              row.cells[5].innerHTML = `<span style="font-size:11px; color:#64748b;">+₹${Number(p.extra_km_rate).toLocaleString('en-IN')}/km &bull; +₹${Number(p.extra_hour_rate).toLocaleString('en-IN')}/hr</span>`;
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
                  <span style="font-size:11px; color:#64748b;">+₹${Number(p.extra_km_rate).toLocaleString('en-IN')}/km &bull; +₹${Number(p.extra_hour_rate).toLocaleString('en-IN')}/hr</span>
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
              row.cells[4].innerHTML = `<span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${Number(a.sedan_base_fare).toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${Number(a.suv_base_fare).toLocaleString('en-IN')}</strong></span>`;
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
                  <span style="font-size:11.5px; color:#475569;">Sedan: <strong>₹${Number(a.sedan_base_fare).toLocaleString('en-IN')}</strong> &bull; SUV: <strong>₹${Number(a.suv_base_fare).toLocaleString('en-IN')}</strong></span>
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
  };

  window.openTicketModal = async function (reference) {
    let b = (state.bookings || []).find(item => item.id === reference || item.booking_reference === reference);
    if (!b) {
      try {
        const res = await fetch(`/api/bookings/${reference}`);
        const data = await res.json();
        if (data && data.success && data.booking) {
          const dbBooking = data.booking;
          b = {
            id: dbBooking.booking_reference,
            route: `${dbBooking.pickup_city || ''} → ${dbBooking.drop_city || ''}`,
            operator: dbBooking.driver ? `${dbBooking.driver.name} (${dbBooking.driver.vehicle_model || 'Cab'})` : (dbBooking.service_type === 'berth' ? 'Intercity Sleeper Bus' : 'Intercity Verified Cab'),
            berths: (dbBooking.seat_numbers && Array.isArray(dbBooking.seat_numbers) && dbBooking.seat_numbers.length) ? dbBooking.seat_numbers : [dbBooking.fleet_category ? dbBooking.fleet_category.toUpperCase() : 'Cab'],
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
          document.querySelectorAll('.driver-avatar-display').forEach(el => el.textContent = p.avatar || '👨🏽‍✈️');
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

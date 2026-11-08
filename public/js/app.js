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
  "chennai": { name: "Chennai", state: "Tamil Nadu", lat: 13.0827, lng: 80.2707 }
};

// Route Distances (km) and base highway times
const ROUTE_MATRIX = {
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
  "jaipur-delhi": { dist: 280, time: "4h 45m", toll: 340 }
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
  sedan: { name: 'Comfort Sedan', models: 'Dzire, Etios', seats: '4 Seats', luggage: '2 Bags', ratePerKm: 12.5, badge: 'Popular Value' },
  prime: { name: 'Prime Sedan', models: 'Verna, Ciaz, City', seats: '4 Seats', luggage: '3 Bags', ratePerKm: 15.0, badge: 'High Comfort' },
  suv: { name: 'Executive SUV', models: 'Innova Crysta, Ertiga', seats: '6-7 Seats', luggage: '4 Bags', ratePerKm: 18.0, badge: 'Family Favorite' },
  luxury: { name: 'Luxury Business', models: 'Camry, Mercedes C-Class', seats: '4 Seats', luggage: '3 Bags', ratePerKm: 32.0, badge: 'VIP Experience' }
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
  // Simplified 3-Step Flow Navigation Engine
  // ==========================================
  window.goToCabStep = function (step) {
    const fromCity = document.getElementById('cab-from-city')?.value || 'pune';
    const toCity = document.getElementById('cab-to-city')?.value || 'goa';

    if (step > 1 && fromCity === toCity) {
      showToast('Pickup City and Destination City cannot be the same.', 'warning');
      return;
    }

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
        panel.classList.toggle('active', i === step);
      }
    }

    state.cab.currentStep = step;
    updateRouteRecapTexts();
    updateTotalCabFare();

    // On Step 3: sync contact details from input
    if (step === 3) {
      const pickupInput = document.getElementById('cab-pickup-address');
      if (pickupInput && pickupInput.value) {
        state.cab.pickupAddress = pickupInput.value;
      }
    }

    // Smooth scroll into view on small screens
    if (window.innerWidth < 992) {
      const header = document.querySelector('.cab-stepper-header');
      if (header) {
        header.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

    document.querySelectorAll('.quick-date-chip').forEach(btn => btn.classList.remove('active'));
    if (event && event.target) {
      event.target.classList.add('active');
    }
    updateRouteRecapTexts();
  };

  function updateRouteRecapTexts() {
    const fromCityName = CITY_DATABASE[state.cab.fromCity]?.name || 'Pune';
    const toCityName = CITY_DATABASE[state.cab.toCity]?.name || 'Goa';
    const dist = state.cab.distance;
    const time = state.cab.travelTime;
    const toll = state.cab.tollEstimate;

    const dateVal = document.getElementById('cab-pickup-date')?.value;
    const timeVal = document.getElementById('cab-pickup-time')?.value || '06:30';

    let dateDisplay = 'Tomorrow at 06:30 AM';
    if (dateVal) {
      const parts = dateVal.split('-');
      if (parts.length === 3) {
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        dateDisplay = `${d.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' })}, ${timeVal}`;
      }
    }

    // Step 1 strip
    const s1Route = document.getElementById('step1-route-name');
    if (s1Route) s1Route.textContent = `${fromCityName} → ${toCityName}`;
    const s1Dist = document.getElementById('step1-dist');
    if (s1Dist) s1Dist.textContent = `${dist} km`;
    const s1Time = document.getElementById('step1-time');
    if (s1Time) s1Time.textContent = time;
    const s1Toll = document.getElementById('step1-toll');
    if (s1Toll) s1Toll.textContent = `₹${toll} Included`;

    // Step 2 banner
    const recapRoute = document.getElementById('recap-route');
    if (recapRoute) recapRoute.textContent = `${fromCityName} → ${toCityName} (${dist} km)`;
    const recapDatetime = document.getElementById('recap-datetime');
    if (recapDatetime) recapDatetime.textContent = dateDisplay;

    // Step 3 banner
    const fleetData = FLEET_TYPES[state.cab.selectedFleet] || FLEET_TYPES.suv;
    const recapFleet = document.getElementById('recap-fleet-title');
    if (recapFleet) recapFleet.textContent = `${fleetData.name} (${fleetData.models.split(',')[0]})`;
    const recapRouteMini = document.getElementById('recap-route-mini');
    if (recapRouteMini) recapRouteMini.textContent = `${fromCityName} → ${toCityName}`;
  }

  // ==========================================
  // Real Backend API Cab Booking Submission
  // ==========================================
  async function handleConfirmCabBooking() {
    const btn = document.getElementById('book-cab-confirm-btn');
    const nameInput = document.getElementById('cab-rider-name');
    const phoneInput = document.getElementById('cab-rider-phone');
    const pickupInput = document.getElementById('cab-pickup-address');
    const dropInput = document.getElementById('cab-drop-address');

    const customerName = nameInput ? nameInput.value.trim() : (window.CURRENT_USER?.name || 'Verified Traveller');
    const customerPhone = phoneInput ? phoneInput.value.trim() : '+91 98220 11920';

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

    const fromCityName = CITY_DATABASE[state.cab.fromCity]?.name || 'Pune';
    const toCityName = CITY_DATABASE[state.cab.toCity]?.name || 'Goa';
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
      fleet_category: state.cab.selectedFleet || 'suv',
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
          date: 'Today, Scheduled',
          operator: `${FLEET_TYPES[state.cab.selectedFleet]?.name || 'Executive SUV'} (Intercity Fleet)`,
          berths: ['Private Cab'],
          passengers: [customerName],
          amount: b.total_amount,
          status: 'Confirmed & Paid',
          otp: String(b.start_otp)
        };

        state.bookings.unshift(localBooking);
        showToast(`🎉 Outstation Cab Booked! Reference: ${b.booking_reference} (Ride OTP: ${b.start_otp})`, 'success');

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
      console.warn('Booking fallback simulation:', err);
      openPaymentModal('cab');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = origBtnContent;
      }
    }
  }

  function handleCityChange() {
    const from = document.getElementById('cab-from-city').value;
    const to = document.getElementById('cab-to-city').value;
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

    container.innerHTML = state.bidding.drivers.map(drv => `
    <div class="bid-card" id="card-${drv.id}">
      <div class="bid-driver-info">
        <div class="driver-avatar-circle">
          <span style="font-size: 28px;">${drv.avatar}</span>
        </div>
        <div class="driver-details-col">
          <div class="driver-name">
            ${drv.name}
            <span class="material-symbols-outlined driver-verified-check">verified</span>
          </div>
          <div class="driver-vehicle-tag">${drv.car} • <strong style="color:#0f172a;">${drv.plate}</strong></div>
          <div class="driver-ratings-row">
            <span class="rating-pill">★ ${drv.rating}</span>
            <span>${drv.trips} trips</span>
            <span style="color:#16a34a; font-weight:600;">• ${drv.eta}</span>
          </div>
        </div>
      </div>
      <div class="bid-actions-col">
        <div class="bid-price-display">₹${drv.bid.toLocaleString('en-IN')}</div>
        <div class="bid-buttons-row">
          <button class="btn btn-sm btn-outline" onclick="openCounterOfferModal('${drv.id}', ${drv.bid})">
            Counter Bid
          </button>
          <button class="btn btn-sm btn-accent" onclick="acceptDriverBid('${drv.id}')">
            Accept Ride
          </button>
        </div>
      </div>
    </div>
  `).join('');
  }

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
      defaultTab: 'schedules'
    },
    driver: {
      title: 'Driver Partner',
      email: 'driver@intercitywheels.com',
      icon: 'local_taxi',
      desc: 'Partner Chauffeur Portal: Place live trip bids, accept passengers, verify OTP & track wallet earnings.',
      btnLabel: 'Sign In as Driver Partner',
      defaultTab: 'bidding'
    },
    admin: {
      title: 'Operations Admin',
      email: 'admin@intercitywheels.com',
      icon: 'admin_panel_settings',
      desc: 'Platform Operations Portal: Configure dynamic forms, set route pricing rules & manage workflow approval stages.',
      btnLabel: 'Sign In as Platform Admin',
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
    event.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const role = document.getElementById('login-role-input').value;
    const remember = document.getElementById('login-remember')?.checked;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const submitBtn = document.getElementById('login-submit-btn');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.7';
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
        setTimeout(() => {
          window.location.reload();
        }, 500);
      } else {
        showToast(data.message || 'Authentication failed. Please verify credentials.', 'warning');
      }
    } catch (err) {
      console.error('Login error:', err);
      showToast('Network error during authentication. Please retry.', 'warning');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
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
        setTimeout(() => {
          window.location.reload();
        }, 400);
      } else {
        showToast(data.message || 'Demo login failed.', 'warning');
      }
    } catch (err) {
      console.error('Demo login error:', err);
      showToast('Failed to connect to authentication server.', 'warning');
    }
  };

  window.performLogout = async function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
      const res = await fetch('/logout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        }
      });

      if (res.ok) {
        showToast('Signed out successfully.', 'info');
        setTimeout(() => {
          window.location.reload();
        }, 400);
      }
    } catch (err) {
      console.error('Logout error:', err);
      window.location.reload();
    }
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
        setTimeout(() => {
          window.location.reload();
        }, 700);
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
        setTimeout(() => {
          window.location.reload();
        }, 700);
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

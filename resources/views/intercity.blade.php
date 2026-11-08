@extends('layouts.app')

@section('content')
@php
    $userRole = Auth::check() ? Auth::user()->role : null;
    $defaultTab = match($userRole) {
        'admin' => 'admin',
        'driver' => 'bidding',
        default => 'schedules'
    };
@endphp
<main id="app-viewport" class="app-viewport" style="width:100%; max-width:100%; margin:0; padding:0;">

        <!-- ======================================================== -->
        <!-- TAB 1: OUTSTATION CABS (SIMPLIFIED 3-STEP SCREEN FLOW)   -->
        <!-- ======================================================== -->
        <section id="tab-cabs" class="tab-pane {{ $defaultTab === 'cabs' ? 'active' : '' }}">
            <div class="hero-banner animate-load stagger-1">
                <h1 class="hero-title">Outstation Mobility, <span class="gold-text">Simplified</span></h1>
                <p class="hero-subtitle">Sanitized cabs, top-tier chauffeurs, zero hidden cancellation fees, and toll-inclusive highway pricing.</p>
                <div class="hero-chips">
                    <span class="hero-chip"><span class="material-symbols-outlined">verified</span> Verified Chauffeurs</span>
                    <span class="hero-chip"><span class="material-symbols-outlined">toll</span> Tolls Included</span>
                    <span class="hero-chip"><span class="material-symbols-outlined">pets</span> Pet-Friendly</span>
                    <span class="hero-chip"><span class="material-symbols-outlined">lock</span> 4-Digit Ride OTP</span>
                </div>
            </div>

            <div class="content-grid-split">
                <!-- Left Column: Simplified Progressive Booking Flow -->
                <div class="card animate-load stagger-2" style="padding: 20px 22px;">
                    <!-- Step Progress Navigation Bar -->
                    <div class="cab-stepper-header">
                        <button type="button" class="cab-step-pill active" id="pill-step-1" onclick="goToCabStep(1)">
                            <span class="step-num" id="step-num-1">1</span>
                            <span class="step-label">Route & Time</span>
                        </button>
                        <div class="step-connector"></div>
                        <button type="button" class="cab-step-pill" id="pill-step-2" onclick="goToCabStep(2)">
                            <span class="step-num" id="step-num-2">2</span>
                            <span class="step-label">Choose Cab</span>
                        </button>
                        <div class="step-connector"></div>
                        <button type="button" class="cab-step-pill" id="pill-step-3" onclick="goToCabStep(3)">
                            <span class="step-num" id="step-num-3">3</span>
                            <span class="step-label">Confirm & Ride</span>
                        </button>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 1: ROUTE & SCHEDULE                                 -->
                    <!-- ======================================================== -->
                    <div id="cab-step-1" class="cab-step-content active">
                        <!-- Trip Type Pills -->
                        <div class="trip-type-pill-group">
                            <button type="button" class="trip-type-pill active" data-type="oneway">One-Way</button>
                            <button type="button" class="trip-type-pill" data-type="roundtrip">Round Trip</button>
                            <button type="button" class="trip-type-pill" data-type="airport">Airport Transfer</button>
                        </div>

                        <!-- Route Selection Grid -->
                        <div class="form-row" style="align-items: flex-start;">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label" for="cab-from-city">
                                    <span>Pickup City</span>
                                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">trip_origin</span>
                                </label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">my_location</span>
                                    <select id="cab-from-city" class="form-control">
                                        <option value="pune" selected>Pune, MH</option>
                                        <option value="mumbai">Mumbai, MH</option>
                                        <option value="goa">Goa (Panjim / Airport)</option>
                                        <option value="bengaluru">Bengaluru, KA</option>
                                        <option value="hyderabad">Hyderabad, TS</option>
                                        <option value="delhi">New Delhi, NCR</option>
                                        <option value="jaipur">Jaipur, RJ</option>
                                        <option value="chennai">Chennai, TN</option>
                                    </select>
                                </div>
                                <div style="margin-top: 6px;">
                                    <input type="text" id="cab-pickup-address" class="form-control" placeholder="Exact Pickup Area / Landmark (e.g. Baner, Airport T2)" value="Baner, Pune" style="font-size:12px; height:38px;">
                                </div>
                            </div>

                            <button type="button" id="cab-swap-cities-btn" class="route-swap-btn" title="Swap Route Direction">
                                <span class="material-symbols-outlined" style="font-size:18px;">swap_horiz</span>
                            </button>

                            <div class="form-group" style="flex: 1;">
                                <label class="form-label" for="cab-to-city">
                                    <span>Destination</span>
                                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--secondary);">location_on</span>
                                </label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined" style="color:var(--secondary);">location_on</span>
                                    <select id="cab-to-city" class="form-control">
                                        <option value="goa" selected>Goa (Panjim / Calangute)</option>
                                        <option value="pune">Pune, MH</option>
                                        <option value="mumbai">Mumbai, MH</option>
                                        <option value="bengaluru">Bengaluru, KA</option>
                                        <option value="hyderabad">Hyderabad, TS</option>
                                        <option value="delhi">New Delhi, NCR</option>
                                        <option value="jaipur">Jaipur, RJ</option>
                                        <option value="chennai">Chennai, TN</option>
                                    </select>
                                </div>
                                <div style="margin-top: 6px;">
                                    <input type="text" id="cab-drop-address" class="form-control" placeholder="Exact Drop Location (e.g. Panjim / Hotel Resort)" value="Panjim, Goa" style="font-size:12px; height:38px;">
                                </div>
                            </div>
                        </div>

                        <!-- Schedule Row -->
                        <div class="form-row" style="margin-top: 6px;">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    <span>Departure Date & Time</span>
                                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">schedule</span>
                                </label>
                                <div style="display:flex; gap:8px;">
                                    <div class="input-with-icon" style="flex:1.4;">
                                        <span class="material-symbols-outlined">calendar_today</span>
                                        <input type="date" id="cab-pickup-date" class="form-control" style="font-size:12.5px;">
                                    </div>
                                    <div class="input-with-icon" style="flex:1;">
                                        <span class="material-symbols-outlined">access_time</span>
                                        <input type="time" id="cab-pickup-time" class="form-control" value="06:30" style="font-size:12.5px;">
                                    </div>
                                </div>
                                <div class="quick-date-chips">
                                    <button type="button" class="quick-date-chip active" onclick="setQuickCabDate('tomorrow')">Tomorrow</button>
                                    <button type="button" class="quick-date-chip" onclick="setQuickCabDate('today')">Today</button>
                                    <button type="button" class="quick-date-chip" onclick="setQuickCabDate('weekend')">Weekend</button>
                                </div>
                            </div>

                            <div class="form-group" id="return-date-group" style="flex: 1; display:none;">
                                <label class="form-label">
                                    <span>Return Schedule</span>
                                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--secondary);">event_repeat</span>
                                </label>
                                <div style="display:flex; gap:8px;">
                                    <div class="input-with-icon" style="flex:1.4;">
                                        <span class="material-symbols-outlined">calendar_today</span>
                                        <input type="date" id="cab-return-date" class="form-control" style="font-size:12.5px;">
                                    </div>
                                    <div class="input-with-icon" style="flex:1;">
                                        <span class="material-symbols-outlined">access_time</span>
                                        <input type="time" id="cab-return-time" class="form-control" value="17:00" style="font-size:12.5px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instant Highway Route Insight Strip -->
                        <div class="cab-route-insight-strip">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">alt_route</span>
                                <span><strong id="step1-route-name">Pune → Goa</strong></span>
                            </div>
                            <div style="display:flex; align-items:center; gap:16px; font-size:12px;">
                                <span><strong id="step1-dist" style="color:#f8fafc;">450 km</strong> (<span id="step1-time">9h 15m</span>)</span>
                                <span>Fastag Tolls: <strong id="step1-toll" style="color:var(--gold);">₹480 Included</strong></span>
                            </div>
                        </div>

                        <!-- Step 1 Navigation -->
                        <div class="cab-step-actions">
                            <div style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-outlined" style="font-size:15px; color:#16a34a;">check_circle</span>
                                <span>Zero cancellation fee on upfront booking</span>
                            </div>
                            <button type="button" class="btn btn-accent" onclick="goToCabStep(2)">
                                <span>Select Cab & View Fares</span>
                                <span class="material-symbols-outlined" style="font-size:17px;">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 2: CHOOSE CAB FLEET                                 -->
                    <!-- ======================================================== -->
                    <div id="cab-step-2" class="cab-step-content">
                        <!-- Route Recap Banner -->
                        <div class="cab-recap-banner">
                            <div class="cab-recap-info">
                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">navigation</span>
                                <span id="recap-route">Pune → Goa (450 km)</span>
                                <span style="color:#cbd5e1;">•</span>
                                <span id="recap-datetime" style="color:#64748b;">Tomorrow at 06:30 AM</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="goToCabStep(1)">
                                <span class="material-symbols-outlined" style="font-size:13px;">edit</span>
                                <span>Change Route</span>
                            </button>
                        </div>

                        <label class="form-label" style="margin-bottom:8px;">Select Outstation Vehicle Class</label>
                        <!-- Fleet Selection Grid -->
                        <div class="fleet-grid">
                            <div class="fleet-card" data-fleet="sedan">
                                <div>
                                    <div class="fleet-top">
                                        <span class="fleet-name">Comfort Sedan</span>
                                        <span class="fleet-badge">Value</span>
                                    </div>
                                    <div style="font-size:11.5px; color:#475569;">Dzire, Etios</div>
                                    <div class="fleet-specs">
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">group</span> 4 Seats</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">luggage</span> 2 Bags</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">ac_unit</span> AC</span>
                                    </div>
                                </div>
                                <div class="fleet-pricing">
                                    <div class="fleet-price" id="fleet-price-sedan">₹5,625</div>
                                    <div class="fleet-rate">₹12.5/km</div>
                                </div>
                            </div>

                            <div class="fleet-card" data-fleet="prime">
                                <div>
                                    <div class="fleet-top">
                                        <span class="fleet-name">Prime Sedan</span>
                                        <span class="fleet-badge">Comfort</span>
                                    </div>
                                    <div style="font-size:11.5px; color:#475569;">City, Verna, Ciaz</div>
                                    <div class="fleet-specs">
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">group</span> 4 Seats</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">luggage</span> 3 Bags</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">ac_unit</span> Climate</span>
                                    </div>
                                </div>
                                <div class="fleet-pricing">
                                    <div class="fleet-price" id="fleet-price-prime">₹6,750</div>
                                    <div class="fleet-rate">₹15.0/km</div>
                                </div>
                            </div>

                            <div class="fleet-card selected" data-fleet="suv">
                                <div>
                                    <div class="fleet-top">
                                        <span class="fleet-name">Executive SUV</span>
                                        <span class="fleet-badge" style="background:rgba(212, 155, 76, 0.18); color:#8c5d1e;">Most Popular</span>
                                    </div>
                                    <div style="font-size:11.5px; color:#475569;">Innova Crysta, Ertiga</div>
                                    <div class="fleet-specs">
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">group</span> 6-7 Seats</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">luggage</span> 4 Bags</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">airline_seat_recline_extra</span> Captain</span>
                                    </div>
                                </div>
                                <div class="fleet-pricing">
                                    <div class="fleet-price" id="fleet-price-suv">₹8,100</div>
                                    <div class="fleet-rate">₹18.0/km</div>
                                </div>
                            </div>

                            <div class="fleet-card" data-fleet="luxury">
                                <div>
                                    <div class="fleet-top">
                                        <span class="fleet-name">Luxury Class</span>
                                        <span class="fleet-badge" style="background:#fef3c7; color:#92400e;">VIP</span>
                                    </div>
                                    <div style="font-size:11.5px; color:#475569;">Camry, C-Class</div>
                                    <div class="fleet-specs">
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">group</span> 4 Seats</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">luggage</span> 3 Bags</span>
                                        <span><span class="material-symbols-outlined" style="font-size:13px;">stars</span> Chauffeur</span>
                                    </div>
                                </div>
                                <div class="fleet-pricing">
                                    <div class="fleet-price" id="fleet-price-luxury">₹14,400</div>
                                    <div class="fleet-rate">₹32.0/km</div>
                                </div>
                            </div>
                        </div>

                        <!-- Trust Inclusions Highlight -->
                        <div class="trust-inclusions-strip">
                            <div class="trust-item">
                                <span class="material-symbols-outlined">toll</span>
                                <span>Tolls Included</span>
                            </div>
                            <div class="trust-item">
                                <span class="material-symbols-outlined">verified</span>
                                <span>Top Chauffeur</span>
                            </div>
                            <div class="trust-item">
                                <span class="material-symbols-outlined">ac_unit</span>
                                <span>AC Sanitized</span>
                            </div>
                            <div class="trust-item">
                                <span class="material-symbols-outlined">lock</span>
                                <span>4-Digit Ride OTP</span>
                            </div>
                        </div>

                        <!-- Step 2 Navigation -->
                        <div class="cab-step-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToCabStep(1)">
                                <span class="material-symbols-outlined" style="font-size:17px;">arrow_back</span>
                                <span>Back</span>
                            </button>
                            <button type="button" class="btn btn-accent" onclick="goToCabStep(3)">
                                <span>Continue to Rider Details</span>
                                <span class="material-symbols-outlined" style="font-size:17px;">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- STEP 3: PASSENGER, FARE REVIEW & INSTANT CONFIRMATION     -->
                    <!-- ======================================================== -->
                    <div id="cab-step-3" class="cab-step-content">
                        <!-- Summary Recap Strip -->
                        <div class="cab-recap-banner">
                            <div class="cab-recap-info">
                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">local_taxi</span>
                                <span><strong id="recap-fleet-title">Executive SUV</strong> (<span id="recap-route-mini">Pune → Goa</span>)</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="goToCabStep(2)">
                                <span class="material-symbols-outlined" style="font-size:13px;">tune</span>
                                <span>Change Vehicle</span>
                            </button>
                        </div>

                        <!-- Passenger Contact Information -->
                        <div style="margin-bottom: 14px;">
                            <label class="form-label">Passenger Contact (For Driver Coordination & Ride OTP)</label>
                            <div class="form-row">
                                <div class="form-group" style="flex:1;">
                                    <div class="input-with-icon">
                                        <span class="material-symbols-outlined">person</span>
                                        <input type="text" id="cab-rider-name" class="form-control" placeholder="Full Name" value="{{ Auth::check() ? Auth::user()->name : 'Aditya Sharma' }}" required>
                                    </div>
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <div class="input-with-icon">
                                        <span class="material-symbols-outlined">call</span>
                                        <input type="tel" id="cab-rider-phone" class="form-control" placeholder="Mobile Number (+91)" value="{{ Auth::check() ? (Auth::user()->phone ?? '+91 98220 11920') : '+91 98220 11920' }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add-ons & Travel Preferences -->
                        <div style="margin-top: 10px;">
                            <label class="form-label">Trip Add-ons & Highway Preferences</label>
                            <div class="addons-group">
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-tolls" checked>
                                        <span>Highway Tolls</span>
                                    </div>
                                    <span class="addon-price">₹480</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-pet">
                                        <span>Pet Sanitized</span>
                                    </div>
                                    <span class="addon-price">+₹350</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-night">
                                        <span>Night Halt</span>
                                    </div>
                                    <span class="addon-price">+₹400</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-luggage">
                                        <span>Luggage Carrier</span>
                                    </div>
                                    <span class="addon-price">+₹200</span>
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic Form Fields from Database -->
                        @if($dynamicFields->count() > 0)
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--outline);">
                            <label class="form-label">Special Custom Specifications</label>
                            @foreach($dynamicFields as $field)
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label style="font-size: 11.5px; font-weight: 600; color: #475569;">{{ $field->label }}</label>
                                <input type="{{ $field->field_type }}" class="form-control" placeholder="Enter {{ $field->label }}" style="height: 38px; font-size:12px;">
                            </div>
                            @endforeach
                        </div>
                        @endif

                        <!-- Promo Code Apply -->
                        <div style="margin-top: 10px;">
                            <label class="form-label">Coupon & Promo Discount</label>
                            <div style="display:flex; gap:8px;">
                                <input type="text" id="cab-coupon-input" class="form-control" placeholder="Promo code (e.g. FIRSTWHEELS)" style="text-transform:uppercase; height:42px;" value="FIRSTWHEELS">
                                <button type="button" id="apply-coupon-btn" class="btn btn-secondary" style="height:42px; padding:0 16px;">Apply</button>
                            </div>
                            <div id="coupon-msg" class="text-xs font-semibold text-emerald-600 mt-1">🎉 FIRSTWHEELS applied (15% discount)</div>
                        </div>

                        <!-- Complete Transparent Fare Breakdown -->
                        <div style="margin-top: 16px; background:#f8fafc; border: 1.5px solid var(--outline); border-radius: var(--radius-sm); padding: 14px 16px;">
                            <div style="font-family:var(--font-display); font-weight:700; font-size:13.5px; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:17px;">receipt_long</span>
                                <span>Transparent Fare Summary</span>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:6px; font-size:12.5px;">
                                <div style="display:flex; justify-content:space-between; color:#475569;">
                                    <span>Base Highway Distance Fare</span>
                                    <span id="fare-base" style="font-weight:700; color:#0f172a;" class="text-tabular">₹8,100</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; color:#475569;">
                                    <span>Fastag Highway Tolls</span>
                                    <span id="fare-tolls" style="font-weight:700; color:#0f172a;" class="text-tabular">₹480</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; color:#475569;">
                                    <span>Selected Add-ons</span>
                                    <span id="fare-addons" style="font-weight:700; color:#0f172a;" class="text-tabular">₹0</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; color:#16a34a; font-weight:600;">
                                    <span>Promo Discount</span>
                                    <span id="fare-discount" class="text-tabular">-₹500</span>
                                </div>
                                <div style="border-top:1px dashed var(--outline); padding-top:10px; margin-top:4px; display:flex; justify-content:space-between; align-items:baseline;">
                                    <div>
                                        <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Total Payable</div>
                                        <div style="font-size:10.5px; color:#16a34a;">All taxes, tolls & driver allowance included</div>
                                    </div>
                                    <span id="fare-grand-total" style="font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--text-main);" class="text-tabular">₹8,080</span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 Action Navigation -->
                        <div class="cab-step-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToCabStep(2)">
                                <span class="material-symbols-outlined" style="font-size:17px;">arrow_back</span>
                                <span>Change Cab</span>
                            </button>
                            <button type="button" id="book-cab-confirm-btn" class="btn btn-accent" style="height:48px; font-size:14.5px; padding:0 24px;">
                                <span class="material-symbols-outlined" style="font-size:19px;">verified_user</span>
                                <span>Confirm & Book Outstation Cab</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Map & Highway Trip Companion -->
                <div class="animate-load stagger-3">
                    <div class="card" style="padding: 14px; margin-bottom: 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span class="card-title" style="font-size:14px;">
                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">map</span>
                                <span>Live Highway Route</span>
                            </span>
                            <span style="font-size:11px; color:#16a34a; font-weight:700; display:flex; align-items:center; gap:4px;">
                                <span class="live-gps-dot"></span> Live GPS
                            </span>
                        </div>

                        <div class="route-map-container">
                            <div id="live-leaflet-map"></div>
                        </div>

                        <div class="route-summary-bar">
                            <div class="route-stat">
                                <span class="route-stat-label">Distance</span>
                                <span class="route-stat-val" id="cab-stat-distance">450 km</span>
                            </div>
                            <div class="route-stat">
                                <span class="route-stat-label">Duration</span>
                                <span class="route-stat-val" id="cab-stat-time">9h 15m</span>
                            </div>
                            <div class="route-stat">
                                <span class="route-stat-label">Tolls</span>
                                <span class="route-stat-val" id="cab-stat-toll">₹480</span>
                            </div>
                        </div>
                    </div>

                    <!-- Safety & Peace of Mind Assurance Card -->
                    <div class="card" style="padding: 16px; border: 1.5px solid var(--outline); background: #ffffff;">
                        <div style="font-family:var(--font-display); font-size:13.5px; font-weight:700; color:#0f172a; margin-bottom:10px; display:flex; align-items:center; gap:7px;">
                            <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">security</span>
                            <span>Intercity Wheels Assurance</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:8px; font-size:12px; color:#475569;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#16a34a;">pin_drop</span>
                                <span>Live GPS highway tracking shared via SMS</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#16a34a;">phone_in_talk</span>
                                <span>24/7 Highway Control Room: <strong>1800-209-WHEELS</strong></span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:#16a34a;">receipt</span>
                                <span>Automated GST tax invoice delivered instantly</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 2: SCHEDULED BUS & BERTH SELECTOR (From Design)      -->
        <!-- ======================================================== -->
        <section id="tab-schedules" class="tab-pane {{ $defaultTab === 'schedules' ? 'active' : '' }}">
            <!-- Route Card -->
            <div class="bus-route-header-card animate-load stagger-1">
                <div class="bus-tag-row">
                    <span class="bus-title-code">INTERCITY EXPRESS #8092</span>
                    <span style="color:#64748b; font-weight:600;">Volvo Multi-Axle AC Sleeper</span>
                </div>

                <div class="bus-journey-timeline">
                    <div class="timeline-station">
                        <span class="station-name">Pune</span>
                        <span class="station-time">09:30 PM</span>
                        <span class="station-bay">Swargate Bay 4</span>
                    </div>

                    <div class="timeline-duration-path">
                        <span class="duration-label">9h 15m</span>
                        <div class="path-line-container">
                            <div class="path-node"></div>
                            <div class="path-line-dashed"></div>
                            <span class="material-symbols-outlined path-icon">navigation</span>
                            <div class="path-line-dashed"></div>
                            <div class="path-node" style="background:var(--secondary);"></div>
                        </div>
                        <span class="live-gps-pill">
                            <span class="live-gps-dot"></span> Live GPS
                        </span>
                    </div>

                    <div class="timeline-station" style="text-align:right;">
                        <span class="station-name">Goa</span>
                        <span class="station-time">06:45 AM</span>
                        <span class="station-bay">Panjim KTC</span>
                    </div>
                </div>
            </div>

            <!-- Berth Layout Section -->
            <div style="margin-bottom: 20px;" class="animate-load stagger-2">
                <div class="deck-switcher-wrapper">
                    <div>
                        <h2 style="font-family:var(--font-display); font-size:17px; font-weight:700;">Choose Berths</h2>
                        <p style="font-size:11.5px; color:#64748b;">2+1 Executive Sleeper</p>
                    </div>

                    <div class="deck-switcher">
                        <button type="button" class="deck-btn active" id="deck-switch-lower">Lower Deck</button>
                        <button type="button" class="deck-btn" id="deck-switch-upper">Upper Deck</button>
                    </div>
                </div>

                <div class="bus-coach-card">
                    <div class="cabin-front-header">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">airline_seat_recline_extra</span>
                            <span style="font-weight:600; color:#475569;">Driver Cabin</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px; font-weight:600;">
                            <span style="text-transform:uppercase; font-size:10.5px; letter-spacing:0.05em;">Entry</span>
                            <span class="material-symbols-outlined" style="font-size:16px;">logout</span>
                        </div>
                    </div>

                    <!-- Lower Deck Grid -->
                    <div class="deck-grid-container" id="deck-lower-grid">
                        <div class="deck-grid-row">
                            <button type="button" class="berth-cell" data-seat="L1" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L1</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                            <div class="aisle-indicator"><span class="material-symbols-outlined">straight</span></div>
                            <div class="berth-cell ladies-only" data-seat="L2" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L2</span>
                                    <span class="material-symbols-outlined">female</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </div>
                            <div class="berth-cell booked" data-seat="L3" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L3</span>
                                    <span class="material-symbols-outlined">lock</span>
                                </div>
                                <span class="berth-price">Booked</span>
                            </div>
                        </div>

                        <div class="deck-grid-row">
                            <button type="button" class="berth-cell selected" data-seat="L4" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L4</span>
                                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                            <div class="aisle-indicator"><span class="material-symbols-outlined">straight</span></div>
                            <button type="button" class="berth-cell selected" data-seat="L5" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L5</span>
                                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                            <button type="button" class="berth-cell" data-seat="L6" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L6</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                        </div>

                        <div class="deck-grid-row">
                            <button type="button" class="berth-cell" data-seat="L7" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L7</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                            <div class="aisle-indicator"><span class="material-symbols-outlined">straight</span></div>
                            <button type="button" class="berth-cell" data-seat="L8" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L8</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                            <button type="button" class="berth-cell" data-seat="L9" data-price="850">
                                <div class="berth-cell-top">
                                    <span class="berth-code">L9</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹850</span>
                            </button>
                        </div>
                    </div>

                    <!-- Upper Deck Grid -->
                    <div class="deck-grid-container" id="deck-upper-grid" style="display:none;">
                        <div class="deck-grid-row">
                            <button type="button" class="berth-cell" data-seat="U1" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U1</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹950</span>
                            </button>
                            <div class="aisle-indicator"><span class="material-symbols-outlined">straight</span></div>
                            <button type="button" class="berth-cell" data-seat="U2" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U2</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹950</span>
                            </button>
                            <div class="berth-cell booked" data-seat="U3" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U3</span>
                                    <span class="material-symbols-outlined">lock</span>
                                </div>
                                <span class="berth-price">Booked</span>
                            </div>
                        </div>

                        <div class="deck-grid-row">
                            <button type="button" class="berth-cell" data-seat="U4" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U4</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹950</span>
                            </button>
                            <div class="aisle-indicator"><span class="material-symbols-outlined">straight</span></div>
                            <button type="button" class="berth-cell" data-seat="U5" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U5</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹950</span>
                            </button>
                            <button type="button" class="berth-cell" data-seat="U6" data-price="950">
                                <div class="berth-cell-top">
                                    <span class="berth-code">U6</span>
                                    <span class="material-symbols-outlined">bed</span>
                                </div>
                                <span class="berth-price">₹950</span>
                            </button>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="seat-legend">
                        <div class="legend-item"><div class="legend-box available"></div><span>Available</span></div>
                        <div class="legend-item"><div class="legend-box selected"></div><span>Selected</span></div>
                        <div class="legend-item"><div class="legend-box ladies"></div><span>Ladies</span></div>
                        <div class="legend-item"><div class="legend-box booked"></div><span>Booked</span></div>
                    </div>
                </div>
            </div>

            <!-- Boarding & Dropping Points -->
            <div class="card animate-load stagger-3" style="margin-bottom: 20px;">
                <h3 class="card-title" style="font-size:15px; margin-bottom:12px;">Boarding & Dropping</h3>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="color:var(--primary);">
                            <span>● Boarding Terminal</span>
                            <span style="font-weight:400; color:#64748b;">09:30 PM Departure</span>
                        </label>
                        <select class="form-control" style="background:#ffffff;">
                            <option selected>Swargate Bay 4 • 09:30 PM</option>
                            <option>Katraj Bypass • 09:55 PM</option>
                            <option>Khed Shivapur Toll • 10:30 PM</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label" style="color:var(--secondary);">
                            <span>● Dropping Terminal</span>
                            <span style="font-weight:400; color:#64748b;">06:45 AM Arrival</span>
                        </label>
                        <select class="form-control" style="background:#ffffff;">
                            <option selected>Panjim KTC Platform 1 • 06:45 AM</option>
                            <option>Mapusa Court Circle • 06:15 AM</option>
                            <option>Margao KTC Station • 07:45 AM</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Passenger Details -->
            <div style="margin-bottom: 20px;" class="animate-load stagger-4">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div>
                        <h3 style="font-family:var(--font-display); font-size:15px; font-weight:700;">Passenger Details</h3>
                        <p style="font-size:11.5px; color:#64748b;">Govt. Photo ID required at boarding</p>
                    </div>
                    <span id="passengers-count-badge" style="font-size:11.5px; font-weight:700; background:var(--primary-light); color:var(--primary); padding:3px 9px; border-radius:var(--radius-full);">
                        2 Travellers
                    </span>
                </div>

                <div id="passenger-cards-container"></div>
            </div>

            <!-- Sticky Bottom Checkout Dock -->
            <div class="booking-checkout-dock">
                <div class="dock-price-meta">
                    <span class="dock-seats-label" id="dock-seat-count-label">2 Berths: L4, L5</span>
                    <div class="dock-amount" id="dock-total-price">₹1,700 <small>all incl.</small></div>
                </div>
                <button type="button" id="schedules-pay-btn" class="btn btn-accent" style="min-width:180px;">
                    <span>Proceed to Pay</span>
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                </button>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: CUSTOMER MY BOOKINGS & PASSENGER MANIFEST          -->
        <!-- ======================================================== -->
        <section id="tab-my-bookings" class="tab-pane {{ $defaultTab === 'my-bookings' ? 'active' : '' }}">
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                    <div>
                        <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px;">confirmation_number</span>
                            <span>My Outstation Trips & Digital Tickets</span>
                        </h2>
                        <p class="card-subtitle">Manage upcoming rides, verify chauffeur assignments, view OTP and access GST tax invoices</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-gold" onclick="switchTab('cabs')">
                        <span class="material-symbols-outlined" style="font-size:15px;">add</span>
                        <span>Book Another Trip</span>
                    </button>
                </div>

                <div class="customer-bookings-grid" style="display:flex; flex-direction:column; gap:14px; padding:16px 0;">
                    @forelse($customerBookings ?? [] as $cb)
                    <div class="customer-trip-card" style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#ffffff; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:12px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-family:monospace; font-weight:800; color:var(--primary); font-size:14px;">{{ $cb->booking_reference }}</span>
                                    <span class="admin-badge @if($cb->status === 'Confirmed') badge-success @elseif($cb->status === 'Driver Assigned') badge-gold @elseif($cb->status === 'Started') badge-info @elseif($cb->status === 'Completed') badge-primary @else badge-secondary @endif">
                                        {{ $cb->status }}
                                    </span>
                                    <span class="admin-badge {{ $cb->service_type === 'cab' ? 'badge-primary' : 'badge-gold' }}" style="font-size:9.5px;">
                                        {{ strtoupper($cb->service_type) }}
                                    </span>
                                </div>
                                <div style="font-size:16px; font-weight:800; color:#0f172a; margin-top:4px;">{{ $cb->route_summary }}</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                                    <span>Departure: <strong>{{ $cb->departure_time ? $cb->departure_time->format('D, d M Y, h:i A') : 'Scheduled' }}</strong></span>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:18px; font-weight:800; color:#0f172a;">₹{{ number_format($cb->total_amount) }}</div>
                                <div style="font-size:10.5px; color:#16a34a; font-weight:600;">✓ Prepaid (Escrow Protected)</div>
                            </div>
                        </div>

                        <!-- Trip Manifest Grid -->
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:14px; font-size:12px;">
                            <div style="background:#f8fafc; padding:10px 12px; border-radius:6px;">
                                <div style="font-weight:700; color:#64748b; font-size:10.5px; text-transform:uppercase;">Pickup & Drop Details</div>
                                <div style="margin-top:4px; color:#1e293b;"><strong>Pickup:</strong> {{ $cb->pickup_location }}</div>
                                <div style="margin-top:2px; color:#1e293b;"><strong>Drop:</strong> {{ $cb->drop_location }}</div>
                            </div>

                            <div style="background:#f8fafc; padding:10px 12px; border-radius:6px;">
                                <div style="font-weight:700; color:#64748b; font-size:10.5px; text-transform:uppercase;">Chauffeur & Vehicle</div>
                                @if($cb->driver)
                                <div style="margin-top:4px; font-weight:700; color:#0f172a;">{{ $cb->driver->name }} (★{{ $cb->driver->rating }})</div>
                                <div style="color:#15803d; font-size:11px; margin-top:2px;">{{ $cb->driver->vehicle_model }} • <strong>{{ $cb->driver->vehicle_plate }}</strong></div>
                                <div style="margin-top:2px;"><a href="tel:{{ $cb->driver->phone }}" style="color:#0284c7; text-decoration:none;">📞 {{ $cb->driver->phone }}</a></div>
                                @else
                                <div style="margin-top:4px; color:#d97706; font-weight:600;">Allotment In Progress</div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px;">A verified chauffeur will be allotted 2 hours before pickup.</div>
                                @endif
                            </div>

                            <div style="background:#fffbeb; border:1px solid #fef3c7; padding:10px 12px; border-radius:6px; text-align:center;">
                                <div style="font-weight:700; color:#92400e; font-size:10.5px; text-transform:uppercase;">Ride Start Validation OTP</div>
                                <div style="font-family:monospace; font-size:22px; font-weight:800; color:var(--primary); letter-spacing:0.1em; margin:4px 0;">
                                    {{ $cb->start_otp }}
                                </div>
                                <div style="font-size:10px; color:#78350f;">Show this 4-digit OTP to your chauffeur before boarding.</div>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="openTicketModal('{{ $cb->booking_reference }}')">
                                <span class="material-symbols-outlined" style="font-size:15px;">confirmation_number</span>
                                <span>Digital Boarding Pass</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-gold" onclick="openInvoiceModal({{ $cb->id }})">
                                <span class="material-symbols-outlined" style="font-size:15px;">receipt</span>
                                <span>GST Tax Invoice</span>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8; background:#f8fafc; border-radius:8px;">
                        <span class="material-symbols-outlined" style="font-size:40px; color:#cbd5e1; display:block; margin-bottom:8px;">receipt_long</span>
                        <div style="font-weight:700; font-size:14px; color:#475569;">No active bookings found under your account</div>
                        <p style="font-size:12px; margin:4px 0 14px;">Book an outstation cab or luxury sleeper berth to view your live tickets and chauffeur dispatch.</p>
                        <button type="button" class="btn btn-sm btn-primary" onclick="switchTab('cabs')">
                            <span>Book Outstation Cab</span>
                        </button>
                    </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 3: DRIVER BIDDING MARKETPLACE & AUCTION              -->
        <!-- ======================================================== -->
        <section id="tab-bidding" class="tab-pane {{ $defaultTab === 'bidding' ? 'active' : '' }}">
            <!-- Driver Partner Header Banner -->
            <div class="driver-console-banner" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:10px; padding:16px 20px; margin-bottom:16px; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="font-size:32px; width:48px; height:48px; border-radius:50%; background:rgba(212,155,76,0.2); border:1px solid var(--primary); display:flex; align-items:center; justify-content:center;">
                        👨🏽‍✈️
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:16px; font-weight:800; color:#ffffff;">{{ $driverProfile->name ?? 'Ramesh Patil' }}</span>
                            <span class="admin-badge badge-success" style="font-size:9.5px;">VERIFIED CHAUFFEUR</span>
                        </div>
                        <div style="font-size:11.5px; color:#94a3b8; margin-top:2px;">
                            <span>{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}</span> &bull; 
                            <span style="font-family:monospace; color:var(--primary); font-weight:700;">{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}</span> &bull; 
                            <span style="color:#f59e0b; font-weight:700;">★{{ $driverProfile->rating ?? '4.90' }}</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="text-align:right;">
                        <div style="font-size:10.5px; text-transform:uppercase; color:#94a3b8; font-weight:700;">Wallet Balance</div>
                        <div style="font-size:20px; font-weight:800; color:#38bdf8;" id="driver-header-wallet-bal">₹{{ number_format($driverProfile->wallet_balance ?? 14500) }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-gold" onclick="switchTab('driver-trips')">
                        <span class="material-symbols-outlined" style="font-size:15px;">local_taxi</span>
                        <span>My Assigned Trips</span>
                    </button>
                </div>
            </div>

            <div class="bidding-auction-header animate-load stagger-1">
                <div>
                    <span class="text-micro" style="color:#fb923c;">Live Chauffeur Auction</span>
                    <h2 style="font-family:var(--font-display); font-size:20px; font-weight:800; margin:3px 0;">Pune → Goa Direct</h2>
                    <div style="font-size:12.5px; color:#cbd5e1;">Target Fare: <strong id="bidding-target-budget" style="color:#ffffff;">₹4,800</strong> • AC Sedan / SUV</div>
                </div>
                <div class="countdown-clock">
                    <span class="material-symbols-outlined" style="font-size:16px;">timer</span>
                    <span>Ends in <strong id="bidding-timer-text">04:50</strong></span>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <div>
                    <h3 style="font-family:var(--font-display); font-size:15px; font-weight:700;">Incoming Proposals & Active Bids</h3>
                    <p style="font-size:11.5px; color:#64748b;">Place counter-offers or accept incoming passenger trip requests</p>
                </div>
                <button type="button" id="post-custom-trip-btn" class="btn btn-sm btn-outline-gold">
                    <span class="material-symbols-outlined" style="font-size:15px;">edit</span>
                    <span>Edit Target Budget</span>
                </button>
            </div>

            <div class="bidding-cards-list animate-load stagger-2" id="bidding-cards-stream"></div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: DRIVER MY ASSIGNED TRIPS & OTP VERIFICATION        -->
        <!-- ======================================================== -->
        <section id="tab-driver-trips" class="tab-pane {{ $defaultTab === 'driver-trips' ? 'active' : '' }}">
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                    <div>
                        <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="color:#16a34a; font-size:22px;">local_taxi</span>
                            <span>My Assigned Outstation Rides</span>
                        </h2>
                        <p class="card-subtitle">Active dispatch queue: verify passenger ride start OTP, start trip & complete payouts</p>
                    </div>
                    <span class="admin-badge badge-success" style="font-size:11px;">
                        {{ count($driverTrips ?? []) }} Active Rides
                    </span>
                </div>

                <div class="driver-trips-list" style="display:flex; flex-direction:column; gap:14px; padding:16px 0;">
                    @forelse($driverTrips ?? [] as $dt)
                    <div class="driver-trip-card" id="driver-trip-box-{{ $dt->id }}" style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#ffffff; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:12px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-family:monospace; font-weight:800; color:var(--primary); font-size:14px;">{{ $dt->booking_reference }}</span>
                                    <span class="admin-badge @if($dt->status === 'Confirmed') badge-success @elseif($dt->status === 'Driver Assigned') badge-gold @elseif($dt->status === 'Started') badge-info @elseif($dt->status === 'Completed') badge-primary @else badge-secondary @endif" id="driver-trip-status-badge-{{ $dt->id }}">
                                        {{ $dt->status }}
                                    </span>
                                </div>
                                <div style="font-size:16px; font-weight:800; color:#0f172a; margin-top:4px;">{{ $dt->route_summary }}</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                                    Passenger: <strong style="color:#0f172a;">{{ $dt->customer_name }}</strong> &bull; 
                                    <a href="tel:{{ $dt->customer_phone }}" style="color:#0284c7; text-decoration:none; font-weight:600;">📞 {{ $dt->customer_phone }}</a>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:18px; font-weight:800; color:#16a34a;">₹{{ number_format($dt->total_amount) }}</div>
                                <div style="font-size:10.5px; color:#64748b;">Tolls & Taxes Included</div>
                            </div>
                        </div>

                        <!-- Route & Addresses -->
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px; font-size:12px; background:#f8fafc; padding:10px 12px; border-radius:6px;">
                            <div>
                                <div style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase;">📍 Passenger Pickup Location</div>
                                <div style="color:#0f172a; font-weight:600; margin-top:2px;">{{ $dt->pickup_location }}</div>
                            </div>
                            <div>
                                <div style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase;">🏁 Destination Drop Location</div>
                                <div style="color:#0f172a; font-weight:600; margin-top:2px;">{{ $dt->drop_location }}</div>
                            </div>
                        </div>

                        <!-- OTP Start Verification & Actions -->
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-top:1px dashed #e2e8f0; padding-top:12px;">
                            @if($dt->status === 'Driver Assigned' || $dt->status === 'Confirmed')
                            <div style="display:flex; align-items:center; gap:8px;">
                                <label style="font-size:11.5px; font-weight:700; color:#475569;">Enter Customer OTP:</label>
                                <input type="text" id="driver-otp-input-{{ $dt->id }}" placeholder="4-digit OTP" maxlength="4" style="width:110px; padding:5px 8px; font-family:monospace; font-size:13px; font-weight:700; text-align:center; border:1px solid #cbd5e1; border-radius:4px;">
                                <button type="button" class="btn btn-sm btn-primary" onclick="verifyDriverStartOtp({{ $dt->id }})">
                                    <span class="material-symbols-outlined" style="font-size:15px;">check_circle</span>
                                    <span>Verify & Start Ride</span>
                                </button>
                            </div>
                            @elseif($dt->status === 'Started')
                            <div style="display:flex; align-items:center; gap:6px; color:#0284c7; font-size:12px; font-weight:700;">
                                <span class="material-symbols-outlined" style="font-size:18px;">local_shipping</span>
                                <span>Ride in progress. Drive safely.</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-accent" onclick="completeDriverTrip({{ $dt->id }})">
                                <span class="material-symbols-outlined" style="font-size:15px;">flag</span>
                                <span>Complete Trip & Settle Payout</span>
                            </button>
                            @else
                            <div style="color:#16a34a; font-size:12px; font-weight:700; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-outlined" style="font-size:16px;">verified</span>
                                <span>Trip completed & payout credited.</span>
                            </div>
                            @endif

                            <button type="button" class="btn btn-sm btn-outline" onclick="openInvoiceModal({{ $dt->id }})">
                                <span class="material-symbols-outlined" style="font-size:14px;">receipt</span>
                                <span>View Invoice</span>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8; background:#f8fafc; border-radius:8px;">
                        <span class="material-symbols-outlined" style="font-size:40px; color:#cbd5e1; display:block; margin-bottom:8px;">local_taxi</span>
                        <div style="font-weight:700; font-size:14px; color:#475569;">No assigned rides in your queue currently</div>
                        <p style="font-size:12px; margin:4px 0 14px;">Switch to the "Live Bids & Auctions" tab to bid on upcoming passenger outstation trips.</p>
                        <button type="button" class="btn btn-sm btn-primary" onclick="switchTab('bidding')">
                            <span>Open Bidding Marketplace</span>
                        </button>
                    </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: DRIVER WALLET & PAYOUT CONSOLE                     -->
        <!-- ======================================================== -->
        <section id="tab-driver-wallet" class="tab-pane {{ $defaultTab === 'driver-wallet' ? 'active' : '' }}">
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <div>
                        <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px;">account_balance_wallet</span>
                            <span>Chauffeur Wallet & Instant Payouts</span>
                        </h2>
                        <p class="card-subtitle">Real-time trip fare credits, toll reimbursements, and bank IMPS withdrawals</p>
                    </div>
                </div>

                <!-- Wallet KPI Grid -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; padding:16px 0;">
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px;">
                        <div style="font-size:11px; text-transform:uppercase; color:#166534; font-weight:700;">Available Balance</div>
                        <div style="font-size:24px; font-weight:800; color:#15803d; margin-top:4px;" id="driver-wallet-bal-display">₹{{ number_format($driverProfile->wallet_balance ?? 14500) }}</div>
                        <div style="font-size:11px; color:#166534; margin-top:2px;">Available for instant withdrawal</div>
                    </div>
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px;">
                        <div style="font-size:11px; text-transform:uppercase; color:#1e40af; font-weight:700;">Total Trips Completed</div>
                        <div style="font-size:24px; font-weight:800; color:#1d4ed8; margin-top:4px;">{{ $driverProfile->trips_count ?? 840 }} Trips</div>
                        <div style="font-size:11px; color:#1e40af; margin-top:2px;">Outstation completed rides</div>
                    </div>
                    <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:16px;">
                        <div style="font-size:11px; text-transform:uppercase; color:#92400e; font-weight:700;">Chauffeur Star Rating</div>
                        <div style="font-size:24px; font-weight:800; color:#b45309; margin-top:4px;">★{{ $driverProfile->rating ?? '4.90' }}</div>
                        <div style="font-size:11px; color:#92400e; margin-top:2px;">Top-rated partner badge</div>
                    </div>
                </div>

                <!-- Instant Payout Action Box -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <div style="font-weight:700; font-size:13px; color:#0f172a;">Registered Bank Account for Payouts</div>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                            HDFC Bank &bull; A/C: ••••••••• 4912 &bull; IFSC: HDFC0001029 (Instant 24/7 IMPS Settlement)
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="requestDriverPayout({{ $driverProfile->wallet_balance ?? 14500 }})">
                        <span class="material-symbols-outlined" style="font-size:15px;">payments</span>
                        <span>Instant Bank Transfer</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 4: BOOTSTRAP ADMIN 3 / ADMINLTE 3 SIDE PANEL & PORTAL -->
        <!-- ======================================================== -->
        <section id="tab-admin" class="tab-pane {{ $defaultTab === 'admin' ? 'active' : '' }}" style="width:100%; max-width:100%; margin:0; padding:0;">
            <div class="adminlte-wrapper" id="admin-lte-app" style="width:100%; max-width:100%; margin:0; border-radius:0; border:none; border-top:1px solid rgba(255,255,255,0.08);">
                <!-- Mobile Backdrop Overlay (Tap to close sidebar on mobile/tablet) -->
                <div class="admin-sidebar-overlay" id="admin-sidebar-overlay" onclick="toggleAdminSidebar(false)"></div>

                <!-- AdminLTE 3 Side Panel -->
                <aside class="admin-sidebar" id="admin-sidebar">
                    <!-- Brand Link / Logo -->
                    <div class="admin-sidebar-brand">
                        <div class="admin-brand-content">
                            <img src="{{ asset('assets/logo.jpg') }}" alt="Intercity Wheels Logo" class="admin-brand-img">
                            <div class="admin-brand-text">
                                <span class="admin-brand-title">Intercity</span>
                                <span class="admin-brand-sub">AdminLTE 3</span>
                            </div>
                        </div>
                        <button type="button" class="admin-sidebar-close-btn" onclick="toggleAdminSidebar(false)" aria-label="Close Sidebar">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- User Panel -->
                    <div class="admin-user-panel">
                        <div class="admin-user-avatar">
                            <span>👨🏻‍💼</span>
                            <span class="admin-online-badge"></span>
                        </div>
                        <div class="admin-user-info">
                            <span class="admin-user-name" id="admin-panel-user-name">{{ $currentUser ? $currentUser->name : 'Platform Admin' }}</span>
                            <span class="admin-user-role">Super Admin • Online</span>
                        </div>
                    </div>

                    <!-- Sidebar Search -->
                    <div class="admin-sidebar-search">
                        <div class="admin-search-wrapper">
                            <span class="material-symbols-outlined admin-search-icon">search</span>
                            <input type="text" placeholder="Search navigation..." id="admin-menu-search" oninput="filterAdminMenu(this.value)">
                        </div>
                    </div>

                    <!-- Sidebar Navigation Menu -->
                    <nav class="admin-sidebar-nav">
                        <ul class="admin-nav-list" id="admin-nav-list">
                            <li class="admin-nav-header">MAIN NAVIGATION</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link active" data-section="dashboard" onclick="switchAdminSection('dashboard')">
                                    <span class="material-symbols-outlined admin-nav-icon">dashboard</span>
                                    <span class="admin-nav-text">Dashboard</span>
                                    <span class="admin-nav-badge badge-gold">KPI</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="bookings" onclick="switchAdminSection('bookings')">
                                    <span class="material-symbols-outlined admin-nav-icon">receipt_long</span>
                                    <span class="admin-nav-text">Bookings Management</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-bookings-count">{{ count($recentBookings) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="users" onclick="switchAdminSection('users')">
                                    <span class="material-symbols-outlined admin-nav-icon">manage_accounts</span>
                                    <span class="admin-nav-text">Users & Drivers</span>
                                    <span class="admin-nav-badge badge-info" id="admin-badge-users-count">{{ count($allUsers) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="form-builder" onclick="switchAdminSection('form-builder')">
                                    <span class="material-symbols-outlined admin-nav-icon">dynamic_form</span>
                                    <span class="admin-nav-text">Dynamic Form Builder</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-fields-count">{{ count($dynamicFields) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="workflow" onclick="switchAdminSection('workflow')">
                                    <span class="material-symbols-outlined admin-nav-icon">linear_scale</span>
                                    <span class="admin-nav-text">Trip Pipeline</span>
                                    <span class="admin-nav-badge badge-info">{{ count($workflowStages) }} Stages</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="pricing" onclick="switchAdminSection('pricing')">
                                    <span class="material-symbols-outlined admin-nav-icon">currency_rupee</span>
                                    <span class="admin-nav-text">Fares & Surcharges</span>
                                </a>
                            </li>

                            <li class="admin-nav-header">FLEET & TRIPS</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="fleet" onclick="switchAdminSection('fleet')">
                                    <span class="material-symbols-outlined admin-nav-icon">local_taxi</span>
                                    <span class="admin-nav-text">Drivers & Bidding</span>
                                    <span class="admin-nav-badge badge-success">{{ count($drivers) }} Online</span>
                                </a>
                            </li>

                            <li class="admin-nav-header">SYSTEM & PLATFORM</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="system" onclick="switchAdminSection('system')">
                                    <span class="material-symbols-outlined admin-nav-icon">admin_panel_settings</span>
                                    <span class="admin-nav-text">System & Health</span>
                                    <span class="admin-nav-badge badge-success">OK</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Sidebar Footer -->
                    <div class="admin-sidebar-footer">
                        <div class="admin-footer-status">
                            <span class="status-pulse-dot"></span>
                            <span>Laravel 12 (PHP)</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline" style="padding:4px 8px; font-size:11px;" onclick="switchTab('cabs')" title="Exit Admin to Booking Portal">
                            <span class="material-symbols-outlined" style="font-size:14px;">logout</span>
                        </button>
                    </div>
                </aside>

                <!-- Main Content Wrapper -->
                <main class="admin-main-wrapper">
                    <!-- Top Navbar -->
                    <header class="admin-top-navbar">
                        <div class="admin-top-left">
                            <button type="button" class="admin-menu-toggle-btn" id="admin-sidebar-toggle-btn" onclick="toggleAdminSidebar()" title="Toggle Sidebar">
                                <span class="material-symbols-outlined">menu</span>
                            </button>
                            <div class="admin-breadcrumbs">
                                <span class="crumb-home">Admin</span>
                                <span class="crumb-separator">/</span>
                                <span class="crumb-active" id="admin-crumb-title">Dashboard</span>
                            </div>
                        </div>

                        <div class="admin-top-right">
                            <span class="admin-live-chip">
                                <span class="admin-live-dot"></span>
                                <span class="admin-live-text">Engine Live</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-primary" id="admin-quick-add-field-btn" onclick="openAddDynamicFieldModal()">
                                <span class="material-symbols-outlined" style="font-size:15px;">add_circle</span>
                                <span>Add Field</span>
                            </button>
                        </div>
                    </header>

                    <!-- Page Body & Modular Tab Sections -->
                    <div class="admin-page-content">
                        <!-- 1. DASHBOARD OVERVIEW -->
                        <div class="admin-section active" id="admin-sec-dashboard">
                            <!-- Small-Boxes KPI Row -->
                            <div class="admin-kpi-grid">
                                <div class="admin-small-box box-gold">
                                    <div class="box-inner">
                                        <div class="box-stat" id="admin-stat-bookings">1,284</div>
                                        <div class="box-label">Outstation Trips Completed</div>
                                    </div>
                                    <span class="material-symbols-outlined box-bg-icon">receipt_long</span>
                                    <a href="javascript:void(0)" class="box-footer" onclick="switchAdminSection('bookings')">
                                        <span>View live audit</span>
                                        <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
                                    </a>
                                </div>

                                <div class="admin-small-box box-emerald">
                                    <div class="box-inner">
                                        <div class="box-stat">{{ count($drivers) }} Drivers</div>
                                        <div class="box-label">Verified Chauffeurs Active</div>
                                    </div>
                                    <span class="material-symbols-outlined box-bg-icon">local_taxi</span>
                                    <a href="javascript:void(0)" class="box-footer" onclick="switchAdminSection('fleet')">
                                        <span>Driver auction monitor</span>
                                        <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
                                    </a>
                                </div>

                                <div class="admin-small-box box-amber">
                                    <div class="box-inner">
                                        <div class="box-stat" id="admin-stat-custom-fields">{{ count($dynamicFields) }} Fields</div>
                                        <div class="box-label">Dynamic Form Custom Inputs</div>
                                    </div>
                                    <span class="material-symbols-outlined box-bg-icon">dynamic_form</span>
                                    <a href="javascript:void(0)" class="box-footer" onclick="switchAdminSection('form-builder')">
                                        <span>Customize form fields</span>
                                        <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
                                    </a>
                                </div>

                                <div class="admin-small-box box-obsidian">
                                    <div class="box-inner">
                                        <div class="box-stat">{{ count($workflowStages) }} Stages</div>
                                        <div class="box-label">Lifecycle Pipeline Stages</div>
                                    </div>
                                    <span class="material-symbols-outlined box-bg-icon">linear_scale</span>
                                    <a href="javascript:void(0)" class="box-footer" onclick="switchAdminSection('workflow')">
                                        <span>Inspect pipeline</span>
                                        <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Split Grid: Live Bidding & Recent Bookings -->
                            <div class="admin-card-grid-split">
                                <!-- Active Drivers & Fleet -->
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">local_taxi</span>
                                                <span>Active Fleet & Bidding Status</span>
                                            </h3>
                                            <p class="card-subtitle">Verified chauffeurs ready for outstation rides</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="switchAdminSection('fleet')">View All</button>
                                    </div>
                                    <div class="admin-table-responsive">
                                        <table class="admin-data-table">
                                            <thead>
                                                <tr>
                                                    <th>Driver</th>
                                                    <th>Vehicle</th>
                                                    <th>Rating</th>
                                                    <th>Wallet</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($drivers as $driver)
                                                <tr>
                                                    <td>
                                                        <div style="display:flex; align-items:center; gap:8px;">
                                                            <span style="font-size:18px;">{{ $driver->avatar }}</span>
                                                            <div>
                                                                <div style="font-weight:700; font-size:12.5px;">{{ $driver->name }}</div>
                                                                <div style="font-size:10.5px; color:#64748b;">{{ $driver->phone }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="font-size:12px; font-weight:600;">{{ $driver->vehicle_model }}</div>
                                                        <div style="font-size:10px; color:#64748b; font-family:monospace;">{{ $driver->vehicle_plate }}</div>
                                                    </td>
                                                    <td>
                                                        <span class="rating-pill">⭐ {{ number_format($driver->rating, 2) }}</span>
                                                    </td>
                                                    <td style="font-weight:700; font-variant-numeric:tabular-nums; color:#16a34a;">
                                                        ₹{{ number_format($driver->wallet_balance) }}
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge badge-success">Online</span>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Recent Bookings Log -->
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">receipt_long</span>
                                                <span>Recent Booking Transactions</span>
                                            </h3>
                                            <p class="card-subtitle">Real-time audit log of customer bookings</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="switchAdminSection('bookings')">Audit All</button>
                                    </div>
                                    <div class="admin-table-responsive">
                                        <table class="admin-data-table">
                                            <thead>
                                                <tr>
                                                    <th>Reference</th>
                                                    <th>Route</th>
                                                    <th>Amount</th>
                                                    <th>OTP</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-recent-bookings-tbody">
                                                @forelse($recentBookings as $b)
                                                <tr>
                                                    <td>
                                                        <span style="font-family:monospace; font-weight:700; color:var(--primary);">{{ $b->booking_reference }}</span>
                                                        <div style="font-size:10px; color:#64748b;">{{ $b->customer_name }}</div>
                                                    </td>
                                                    <td>
                                                        <div style="font-size:12px; font-weight:600;">{{ $b->route_summary }}</div>
                                                        <div style="font-size:10px; color:#64748b;">{{ ucfirst($b->service_type) }}</div>
                                                    </td>
                                                    <td style="font-weight:800; color:#0f172a;">₹{{ number_format($b->total_amount) }}</td>
                                                    <td>
                                                        <span class="admin-otp-pill">{{ $b->start_otp }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge badge-gold">{{ $b->status }}</span>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">
                                                        No bookings recorded yet. Create one in Cabs or Schedules!
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. BOOKINGS MANAGEMENT (DIRECTLY AFTER DASHBOARD) -->
                        <div class="admin-section" id="admin-sec-bookings">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">receipt_long</span>
                                            <span>Outstation Bookings & Dispatch Management</span>
                                        </h3>
                                        <p class="card-subtitle">Live passenger dispatch, chauffeur allotment, fare breakdown & GST tax invoices</p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <button type="button" class="btn btn-sm btn-outline" onclick="reloadAdminBookings()" title="Reload Bookings">
                                            <span class="material-symbols-outlined" style="font-size:15px;">refresh</span>
                                            <span>Refresh</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddBookingModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>New Booking</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Filter & Search Toolbar -->
                                <div class="admin-bookings-toolbar">
                                    <div class="admin-search-box">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-booking-search-input" placeholder="Search reference, customer, phone, city..." oninput="handleBookingFilterChange()">
                                    </div>
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label>Status:</label>
                                            <select id="admin-booking-filter-status" onchange="handleBookingFilterChange()">
                                                <option value="all">All Statuses</option>
                                                <option value="Confirmed">Confirmed</option>
                                                <option value="Driver Assigned">Driver Assigned</option>
                                                <option value="Started">Trip Started</option>
                                                <option value="Completed">Completed</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="admin-filter-item">
                                            <label>Service:</label>
                                            <select id="admin-booking-filter-service" onchange="handleBookingFilterChange()">
                                                <option value="all">All Services</option>
                                                <option value="cab">Cabs</option>
                                                <option value="bus">Sleeper Berths</option>
                                            </select>
                                        </div>
                                        <span class="admin-count-pill" id="admin-bookings-filter-count">{{ count($recentBookings) }} Bookings</span>
                                    </div>
                                </div>

                                <div class="admin-table-responsive">
                                    <table class="admin-data-table admin-table-compact" id="admin-bookings-table">
                                        <thead>
                                            <tr>
                                                <th>REF & DATE</th>
                                                <th>CUSTOMER & CONTACT</th>
                                                <th>SERVICE & ROUTE</th>
                                                <th>FARE & PAYMENT</th>
                                                <th>ALLOTTED CHAUFFEUR</th>
                                                <th>START OTP</th>
                                                <th>STATUS</th>
                                                <th style="text-align:right;">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-bookings-table-tbody">
                                            @forelse($recentBookings as $b)
                                            <tr id="booking-row-{{ $b->id }}" data-id="{{ $b->id }}" data-ref="{{ $b->booking_reference }}" data-status="{{ $b->status }}" data-service="{{ $b->service_type }}" data-search="{{ strtolower($b->booking_reference . ' ' . $b->customer_name . ' ' . $b->customer_phone . ' ' . $b->route_summary . ' ' . $b->pickup_location . ' ' . $b->drop_location) }}">
                                                <td>
                                                    <a href="javascript:void(0)" onclick="openViewBookingModal({{ $b->id }})" class="booking-ref-link" title="Click to inspect booking">
                                                        {{ $b->booking_reference }}
                                                    </a>
                                                    <div class="cell-subtext">{{ $b->created_at ? $b->created_at->format('d M, h:i A') : 'Recent' }}</div>
                                                </td>
                                                <td>
                                                    <div class="cell-primary-text">{{ $b->customer_name }}</div>
                                                    <div class="cell-subtext">
                                                        <a href="tel:{{ $b->customer_phone }}" style="color:#64748b; text-decoration:none;">{{ $b->customer_phone }}</a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:4px;">
                                                        <span class="admin-badge {{ $b->service_type === 'cab' ? 'badge-primary' : 'badge-gold' }}" style="font-size:9.5px; padding:1px 5px;">
                                                            {{ strtoupper($b->service_type) }}
                                                        </span>
                                                        <span class="cell-primary-text">{{ $b->route_summary }}</span>
                                                    </div>
                                                    <div class="cell-subtext truncate-subtext" title="{{ $b->pickup_location }} → {{ $b->drop_location }}">
                                                        {{ $b->pickup_location }} → {{ $b->drop_location }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="cell-primary-text text-tabular" style="font-weight:800; color:#0f172a;">
                                                        ₹{{ number_format($b->total_amount) }}
                                                    </div>
                                                    <div class="cell-subtext">
                                                        <span class="status-indicator-dot {{ $b->status === 'Cancelled' ? 'bg-danger' : 'bg-success' }}"></span>
                                                        <span>Prepaid (Escrow)</span>
                                                    </div>
                                                </td>
                                                <td id="booking-driver-cell-{{ $b->id }}">
                                                    @if($b->driver)
                                                    <div class="driver-allotted-card">
                                                        <span class="cell-primary-text">{{ $b->driver->name }}</span>
                                                        <span class="driver-plate-pill">{{ $b->driver->vehicle_plate }}</span>
                                                        <button type="button" class="btn-link-action" onclick="openAllotDriverModal({{ $b->id }})" title="Change Driver">Change</button>
                                                    </div>
                                                    @else
                                                    <button type="button" class="btn btn-xs btn-outline-gold" onclick="openAllotDriverModal({{ $b->id }})">
                                                        <span class="material-symbols-outlined" style="font-size:13px;">person_add</span>
                                                        <span>Allot Driver</span>
                                                    </button>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="admin-otp-pill">{{ $b->start_otp }}</span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge @if($b->status === 'Confirmed') badge-success @elseif($b->status === 'Driver Assigned') badge-gold @elseif($b->status === 'Started') badge-info @elseif($b->status === 'Completed') badge-primary @else badge-secondary @endif" id="booking-status-badge-{{ $b->id }}">
                                                        {{ $b->status }}
                                                    </span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-actions-compact">
                                                        <button type="button" class="btn-action-icon btn-action-driver" onclick="openAllotDriverModal({{ $b->id }})" title="Allot / Change Chauffeur">
                                                            <span class="material-symbols-outlined">local_taxi</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon btn-action-view" onclick="openViewBookingModal({{ $b->id }})" title="View Details & Payment Breakdown">
                                                            <span class="material-symbols-outlined">visibility</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon btn-action-edit" onclick="openEditBookingModal({{ $b->id }})" title="Edit Booking">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon btn-action-invoice" onclick="openInvoiceModal({{ $b->id }})" title="Generate Tax Invoice">
                                                            <span class="material-symbols-outlined">receipt</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr id="admin-bookings-empty-row">
                                                <td colspan="8" style="text-align:center; padding:35px 20px; color:#94a3b8;">
                                                    <span class="material-symbols-outlined" style="font-size:32px; color:#cbd5e1; display:block; margin-bottom:6px;">inbox</span>
                                                    <span>No customer bookings found. Click "New Booking" to manually log an outstation trip.</span>
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 3. USERS & DRIVERS DIRECTORY (AFTER BOOKINGS) -->
                        <div class="admin-section" id="admin-sec-users">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">manage_accounts</span>
                                            <span>Registered Users & Chauffeur Directory</span>
                                        </h3>
                                        <p class="card-subtitle">Comprehensive account registry: traveler customers, verified chauffeurs & platform administrators</p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <button type="button" class="btn btn-sm btn-outline" onclick="reloadAdminUsers()" title="Reload Directory">
                                            <span class="material-symbols-outlined" style="font-size:15px;">refresh</span>
                                            <span>Refresh</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddUserModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">person_add</span>
                                            <span>New User / Driver</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Filter & Search Toolbar -->
                                <div class="admin-bookings-toolbar">
                                    <div class="admin-search-box">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-user-search-input" placeholder="Search by name, email, phone, role..." oninput="handleUserFilterChange()">
                                    </div>
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label>Role:</label>
                                            <select id="admin-user-filter-role" onchange="handleUserFilterChange()">
                                                <option value="all">All Roles</option>
                                                <option value="customer">Customers (Travelers)</option>
                                                <option value="driver">Chauffeur Partners</option>
                                                <option value="admin">Platform Admins</option>
                                            </select>
                                        </div>
                                        <span class="admin-count-pill" id="admin-users-filter-count">{{ count($allUsers ?? []) }} Accounts</span>
                                    </div>
                                </div>

                                <div class="admin-table-responsive">
                                    <table class="admin-data-table admin-table-compact" id="admin-users-table">
                                        <thead>
                                            <tr>
                                                <th>USER & IDENTITY</th>
                                                <th>ROLE</th>
                                                <th>CONTACT DETAILS</th>
                                                <th>VEHICLE / FLEET (DRIVERS)</th>
                                                <th>TRIPS / ACTIVITY</th>
                                                <th>REGISTRATION DATE</th>
                                                <th style="text-align:right;">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-users-table-tbody">
                                            @forelse($allUsers ?? [] as $u)
                                            @php
                                                $driver = null;
                                                if ($u->role === 'driver') {
                                                    $driver = \App\Models\Driver::where('phone', $u->phone)->orWhere('name', 'like', '%' . explode(' ', $u->name)[0] . '%')->first();
                                                }
                                                $trips = 0;
                                                if ($u->role === 'customer') {
                                                    $trips = \App\Models\Booking::where('customer_phone', $u->phone)->orWhere('customer_email', $u->email)->count();
                                                } elseif ($driver) {
                                                    $trips = $driver->trips_count;
                                                }
                                            @endphp
                                            <tr id="user-row-{{ $u->id }}" data-id="{{ $u->id }}" data-role="{{ $u->role }}" data-search="{{ strtolower($u->name . ' ' . $u->email . ' ' . $u->phone . ' ' . $u->role . ' ' . ($driver ? $driver->vehicle_plate . ' ' . $driver->vehicle_model : '')) }}">
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span style="font-size:20px; width:30px; height:30px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center;">
                                                            {{ $u->avatar ?? ($u->role === 'admin' ? '🛡️' : ($u->role === 'driver' ? '👨🏽‍✈️' : '👤')) }}
                                                        </span>
                                                        <div>
                                                            <div class="cell-primary-text">{{ $u->name }}</div>
                                                            <div class="cell-subtext">ID: #USR-{{ str_pad($u->id, 4, '0', STR_PAD_LEFT) }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="admin-badge @if($u->role === 'admin') badge-gold @elseif($u->role === 'driver') badge-success @else badge-primary @endif">
                                                        {{ strtoupper($u->role) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="cell-primary-text" style="font-size:11.5px;">{{ $u->email }}</div>
                                                    <div class="cell-subtext">
                                                        <a href="tel:{{ $u->phone }}" style="color:#64748b; text-decoration:none;">{{ $u->phone }}</a>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($driver)
                                                    <div class="driver-allotted-card">
                                                        <span class="cell-primary-text">{{ $driver->vehicle_model }}</span>
                                                        <div style="display:flex; gap:4px; align-items:center; margin-top:2px;">
                                                            <span class="driver-plate-pill">{{ $driver->vehicle_plate }}</span>
                                                            <span style="color:#f59e0b; font-weight:700; font-size:10px;">★{{ $driver->rating }}</span>
                                                            <span style="color:#15803d; font-weight:700; font-size:10px;">₹{{ number_format($driver->wallet_balance) }}</span>
                                                        </div>
                                                    </div>
                                                    @elseif($u->role === 'driver')
                                                    <span style="color:#d97706; font-size:11px;">Vehicle Pending</span>
                                                    @else
                                                    <span style="color:#94a3b8; font-size:11px;">Standard Passenger</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="cell-primary-text text-tabular">{{ $trips }} Trips</div>
                                                    <div class="cell-subtext">{{ $u->role === 'driver' ? 'Completed Rides' : 'Bookings Logged' }}</div>
                                                </td>
                                                <td>
                                                    <div class="cell-subtext">{{ $u->created_at ? $u->created_at->format('d M Y') : 'Recent' }}</div>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-actions-compact">
                                                        <button type="button" class="btn-action-icon btn-action-view" onclick="openViewUserModal({{ $u->id }})" title="View Profile & Trips">
                                                            <span class="material-symbols-outlined">visibility</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon btn-action-edit" onclick="openEditUserModal({{ $u->id }})" title="Edit Account">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon btn-action-invoice" onclick="quickDemoLogin('{{ $u->role }}')" title="Switch to this Role Perspective">
                                                            <span class="material-symbols-outlined">login</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr id="admin-users-empty-row">
                                                <td colspan="7" style="text-align:center; padding:35px 20px; color:#94a3b8;">
                                                    <span class="material-symbols-outlined" style="font-size:32px; color:#cbd5e1; display:block; margin-bottom:6px;">group</span>
                                                    <span>No registered users found. Click "New User / Driver" to register an account.</span>
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 4. DYNAMIC FORM BUILDER -->
                        <div class="admin-section" id="admin-sec-form-builder">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">dynamic_form</span>
                                            <span>Dynamic Booking Form Fields Manager</span>
                                        </h3>
                                        <p class="card-subtitle">Custom fields rendered directly on the passenger checkout modal based on rules</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" id="admin-add-field-btn" onclick="openAddDynamicFieldModal()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        <span>Add Custom Field</span>
                                    </button>
                                </div>

                                <div id="admin-fields-list"></div>
                            </div>

                            <!-- Live Form Preview -->
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:#6366f1; font-size:18px;">visibility</span>
                                            <span>Live Customer Checkout Form Preview</span>
                                        </h3>
                                        <p class="card-subtitle">Simulated view of injected dynamic fields during ride reservation</p>
                                    </div>
                                    <span class="admin-badge badge-primary">Client Preview</span>
                                </div>
                                <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:var(--radius-sm); padding:16px;">
                                    <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:12px;">Active Dynamic Controls</div>
                                    <div id="admin-preview-fields-container" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                                        <!-- Dynamically synced via JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. TRIP PIPELINE WORKFLOW -->
                        <div class="admin-section" id="admin-sec-workflow">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">linear_scale</span>
                                            <span>Trip Lifecycle State Machine Pipeline</span>
                                        </h3>
                                        <p class="card-subtitle">Configurable booking stages executed by the backend workflow engine</p>
                                    </div>
                                    <span class="notice-tag">Automated Engine</span>
                                </div>

                                <div class="workflow-stages-track" id="admin-workflow-track">
                                    @foreach($workflowStages as $idx => $stage)
                                    <div class="workflow-stage-pill {{ $idx === 1 ? 'active' : '' }}">
                                        <span style="font-size: 16px; color: var(--primary);">0{{ $idx + 1 }}</span>
                                        <div>
                                            <div style="font-size: 12.5px; font-weight: 700;">{{ $stage->stage_name }}</div>
                                            <div style="font-size: 10.5px; color: #64748b; font-weight: 500;">{{ $stage->description }}</div>
                                        </div>
                                    </div>
                                    @if(!$loop->last)
                                    <span class="material-symbols-outlined workflow-stage-arrow">arrow_forward</span>
                                    @endif
                                    @endforeach
                                </div>
                            </div>

                            <!-- Workflow Stage Details Grid -->
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                                @foreach($workflowStages as $idx => $stage)
                                <div class="card" style="padding:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <span style="font-size:11px; font-weight:800; color:var(--primary); letter-spacing:0.05em;">STAGE 0{{ $idx + 1 }}</span>
                                        <span class="admin-badge badge-success">Active</span>
                                    </div>
                                    <div style="font-family:var(--font-display); font-size:15px; font-weight:700; color:#0f172a;">{{ $stage->stage_name }}</div>
                                    <div style="font-size:11.5px; color:#64748b; margin:6px 0 12px;">{{ $stage->description }}</div>
                                    <div style="border-top:1px solid #f1f5f9; padding-top:8px; font-size:10.5px; color:#94a3b8; font-family:monospace;">
                                        Key: {{ $stage->stage_key }}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- 4. FARES & SURCHARGES -->
                        <div class="admin-section" id="admin-sec-pricing">
                            <div class="admin-card-grid-split">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">currency_rupee</span>
                                                <span>Fee & Surcharge Configuration</span>
                                            </h3>
                                            <p class="card-subtitle">Live addon rates added during checkout</p>
                                        </div>
                                    </div>

                                    <div style="display:flex; flex-direction:column; gap:14px;">
                                        <div class="form-group" style="margin:0;">
                                            <label class="form-label" for="rule-pet-charge">Pet Sanitization & Carrier Fee (₹)</label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">pets</span>
                                                <input type="number" id="rule-pet-charge" class="form-control" value="350">
                                            </div>
                                        </div>

                                        <div class="form-group" style="margin:0;">
                                            <label class="form-label" for="rule-night-charge">Night Halt Allowance (11 PM - 6 AM) (₹)</label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">bedtime</span>
                                                <input type="number" id="rule-night-charge" class="form-control" value="400">
                                            </div>
                                        </div>

                                        <div class="form-group" style="margin:0;">
                                            <label class="form-label" for="rule-luggage-charge">Top Roof Luggage Carrier Fee (₹)</label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">luggage</span>
                                                <input type="number" id="rule-luggage-charge" class="form-control" value="200">
                                            </div>
                                        </div>

                                        <button type="button" id="save-pricing-rules-btn" class="btn btn-gold btn-block" style="margin-top:8px;">
                                            <span class="material-symbols-outlined">save</span>
                                            <span>Save Surcharge Rules</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Base Distance Rates -->
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">local_shipping</span>
                                                <span>Fleet Base Distance Rates</span>
                                            </h3>
                                            <p class="card-subtitle">Per kilometer rate rules by fleet category</p>
                                        </div>
                                    </div>

                                    <div class="admin-table-responsive">
                                        <table class="admin-data-table">
                                            <thead>
                                                <tr>
                                                    <th>Fleet Tier</th>
                                                    <th>Rate / Km</th>
                                                    <th>Min Fare</th>
                                                    <th>Toll Policy</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>Sedan</strong> (Dzire / Etios)</td>
                                                    <td style="font-weight:700; color:var(--primary);">₹12.50</td>
                                                    <td>₹1,500</td>
                                                    <td><span class="admin-badge badge-primary">Included</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Prime</strong> (Honda City / Ciaz)</td>
                                                    <td style="font-weight:700; color:var(--primary);">₹15.00</td>
                                                    <td>₹2,000</td>
                                                    <td><span class="admin-badge badge-primary">Included</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Executive SUV</strong> (Innova / Ertiga)</td>
                                                    <td style="font-weight:700; color:var(--primary);">₹18.00</td>
                                                    <td>₹2,500</td>
                                                    <td><span class="admin-badge badge-gold">Fastag Reconciled</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Luxury Class</strong> (Fortuner / BMW)</td>
                                                    <td style="font-weight:700; color:var(--primary);">₹32.00</td>
                                                    <td>₹5,000</td>
                                                    <td><span class="admin-badge badge-gold">VIP Escort</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. DRIVERS & FLEET MANAGEMENT -->
                        <div class="admin-section" id="admin-sec-fleet">
                            <div class="card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">badge</span>
                                            <span>Registered Fleet & Driver Verification Directory</span>
                                        </h3>
                                        <p class="card-subtitle">Chauffeurs verified with Police Clearances & Commercial Permits</p>
                                    </div>
                                </div>

                                <div class="admin-table-responsive">
                                    <table class="admin-data-table">
                                        <thead>
                                            <tr>
                                                <th>Chauffeur</th>
                                                <th>Vehicle Model & Plate</th>
                                                <th>Category</th>
                                                <th>Rating & Trips</th>
                                                <th>Wallet Escrow</th>
                                                <th>Compliance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($drivers as $driver)
                                            <tr>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <span style="font-size:22px;">{{ $driver->avatar }}</span>
                                                        <div>
                                                            <div style="font-weight:700; font-size:13px;">{{ $driver->name }}</div>
                                                            <div style="font-size:11px; color:#64748b;">{{ $driver->phone }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600; font-size:12.5px;">{{ $driver->vehicle_model }}</div>
                                                    <div style="font-size:11px; font-family:monospace; color:#475569;">{{ $driver->vehicle_plate }}</div>
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-secondary">{{ strtoupper($driver->fleet_category) }}</span>
                                                </td>
                                                <td>
                                                    <div style="font-weight:700; font-size:12px;">⭐ {{ number_format($driver->rating, 2) }}</div>
                                                    <div style="font-size:10.5px; color:#64748b;">{{ $driver->trips_count }} Trips Completed</div>
                                                </td>
                                                <td>
                                                    <span style="font-weight:800; font-size:13px; color:#16a34a;">₹{{ number_format($driver->wallet_balance) }}</span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-success">✓ Verified</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 7. SYSTEM & HEALTH -->
                        <div class="admin-section" id="admin-sec-system">
                            <div class="admin-card-grid-split">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">dns</span>
                                                <span>Server & Framework Environment</span>
                                            </h3>
                                            <p class="card-subtitle">Active runtime and persistence stack</p>
                                        </div>
                                    </div>

                                    <div style="display:flex; flex-direction:column; gap:10px; font-size:12.5px;">
                                        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                                            <span style="color:#64748b;">Backend Framework</span>
                                            <strong>Laravel 12.69.3</strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                                            <span style="color:#64748b;">PHP Engine</span>
                                            <strong>PHP {{ PHP_VERSION }}</strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                                            <span style="color:#64748b;">Database Connection</span>
                                            <strong style="color:#16a34a;">SQLite (Online)</strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                                            <span style="color:#64748b;">Map Provider</span>
                                            <strong>OpenStreetMap / Leaflet 1.9.4</strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; padding:8px 0;">
                                            <span style="color:#64748b;">Design System</span>
                                            <strong>AdminLTE 3 • Logo Gold Theme</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">
                                                <span class="material-symbols-outlined" style="color:#0284c7; font-size:18px;">switch_account</span>
                                                <span>Instant Demo Role Switch</span>
                                            </h3>
                                            <p class="card-subtitle">Quickly test platform permissions across roles</p>
                                        </div>
                                    </div>

                                    <div style="display:flex; flex-direction:column; gap:10px;">
                                        <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start;" onclick="quickDemoLogin('admin')">
                                            <span>👨🏻‍💼</span> <span>Login as System Admin</span>
                                        </button>
                                        <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start;" onclick="quickDemoLogin('driver')">
                                            <span>👨🏽‍✈️</span> <span>Login as Verified Driver</span>
                                        </button>
                                        <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start;" onclick="quickDemoLogin('customer')">
                                            <span>👤</span> <span>Login as Customer</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </section>

        <!-- Clean Mobile Bottom Tab Navigation (Role Filtered) -->
        <nav class="fixed-bottom-bar" id="mobile-bottom-dock">
            @if(!$userRole || $userRole === 'customer')
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'cabs' ? 'active' : '' }}" data-target="cabs">
                    <span class="material-symbols-outlined">directions_car</span>
                    <span>Cabs</span>
                </button>
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'schedules' ? 'active' : '' }}" data-target="schedules">
                    <span class="material-symbols-outlined">directions_bus</span>
                    <span>Schedules</span>
                </button>
                @auth
                    <button type="button" class="mobile-dock-btn {{ $defaultTab === 'my-bookings' ? 'active' : '' }}" data-target="my-bookings">
                        <span class="material-symbols-outlined">confirmation_number</span>
                        <span>My Trips</span>
                    </button>
                @endauth
            @elseif($userRole === 'driver')
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'bidding' ? 'active' : '' }}" data-target="bidding">
                    <span class="material-symbols-outlined">local_offer</span>
                    <span>Auctions</span>
                </button>
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'driver-trips' ? 'active' : '' }}" data-target="driver-trips">
                    <span class="material-symbols-outlined">local_taxi</span>
                    <span>My Trips</span>
                </button>
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'driver-wallet' ? 'active' : '' }}" data-target="driver-wallet">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                    <span>Wallet</span>
                </button>
            @elseif($userRole === 'admin')
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'admin' ? 'active' : '' }}" data-target="admin">
                    <span class="material-symbols-outlined">tune</span>
                    <span>Admin</span>
                </button>
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'cabs' ? 'active' : '' }}" data-target="cabs">
                    <span class="material-symbols-outlined">directions_car</span>
                    <span>Cabs</span>
                </button>
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'bidding' ? 'active' : '' }}" data-target="bidding">
                    <span class="material-symbols-outlined">local_offer</span>
                    <span>Driver</span>
                </button>
            @endif
        </nav>
</main>

<!-- ======================================================== -->
<!-- MODAL: PAYMENT GATEWAY                                   -->
<!-- ======================================================== -->
<div class="modal-overlay" id="payment-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Secure Payment</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('payment-modal')">×</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center; padding:8px 0 16px;">
                <div class="text-micro" style="color:var(--text-muted);" id="payment-modal-desc">Trip Checkout</div>
                <div style="font-family:var(--font-display); font-size:30px; font-weight:800; color:var(--primary); margin-top:2px;" id="payment-modal-amount" class="text-tabular">₹1,700</div>
                <span style="font-size:11px; color:#16a34a; font-weight:600;">🔒 256-Bit SSL Escrow Protected</span>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start; height:48px;" onclick="confirmMockPayment('Instant UPI (GPay/PhonePe)')">
                    <span class="material-symbols-outlined" style="color:#0284c7;">qr_code_2</span>
                    <span>UPI Instant (GPay, PhonePe, Paytm)</span>
                </button>
                <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start; height:48px;" onclick="confirmMockPayment('Credit / Debit Card')">
                    <span class="material-symbols-outlined" style="color:var(--secondary);">credit_card</span>
                    <span>Cards (Visa, MasterCard, RuPay)</span>
                </button>
                <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start; height:48px;" onclick="confirmMockPayment('NetBanking')">
                    <span class="material-symbols-outlined" style="color:#6366f1;">account_balance</span>
                    <span>NetBanking (HDFC, ICICI, SBI)</span>
                </button>
                <button type="button" class="btn btn-outline btn-block" style="justify-content:flex-start; height:48px;" onclick="confirmMockPayment('Cash to Chauffeur')">
                    <span class="material-symbols-outlined" style="color:#16a34a;">payments</span>
                    <span>Pay Later (Cash or UPI at Pickup)</span>
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('payment-modal')">Cancel</button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DRIVER COUNTER OFFER                              -->
<!-- ======================================================== -->
<div class="modal-overlay" id="counter-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Counter Offer</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('counter-modal')">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12.5px; color:#64748b; margin-bottom:12px;">
                Propose your target fare to <strong id="counter-driver-name" style="color:#0f172a;">Driver</strong>. Current bid: <span id="counter-current-bid" style="font-weight:700; color:var(--secondary);" class="text-tabular">₹5,200</span>.
            </p>
            <div class="form-group">
                <label class="form-label">Target Offer (₹)</label>
                <div class="input-with-icon">
                    <span class="material-symbols-outlined">currency_rupee</span>
                    <input type="number" id="counter-target-input" class="form-control" value="4800">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('counter-modal')">Cancel</button>
            <button type="button" class="btn btn-accent btn-sm" onclick="submitCounterOffer()">Send Counter Bid</button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DIGITAL BOARDING PASS / TRIP TICKET               -->
<!-- ======================================================== -->
<div class="modal-overlay" id="ticket-modal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="ticket-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <img src="{{ asset('assets/logo.jpg') }}" alt="Logo" style="height:34px; border-radius:6px;">
                <div>
                    <div style="font-family:var(--font-display); font-size:16px; font-weight:800; color:#f8fafc;">INTERCITY WHEELS</div>
                    <div style="font-size:9.5px; color:#d4af37; letter-spacing:0.12em; text-transform:uppercase;">Confirmed Trip Ticket</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" style="color:#cbd5e1;" onclick="closeModal('ticket-modal')">×</button>
        </div>

        <div class="ticket-body">
            <div class="ticket-otp-banner">
                <div>
                    <div class="text-micro" style="color:#8c5d1e;">Ride Start OTP (Show to Chauffeur)</div>
                    <div style="font-size:11.5px; color:#475569;">Share only upon vehicle arrival</div>
                </div>
                <div class="ticket-otp-code" id="ticket-display-otp">8492</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; font-size:12.5px;">
                <div>
                    <span class="text-micro" style="color:#64748b;">Booking Ref</span>
                    <div style="font-weight:700; font-size:14px; color:#0f172a;" id="ticket-display-id">ICW-90412</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Route</span>
                    <div style="font-weight:700; font-size:14px; color:#0f172a;" id="ticket-display-route">Pune → Goa</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Operator</span>
                    <div style="font-weight:600; color:#0f172a;" id="ticket-display-operator">Intercity Express #8092</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Assigned Units</span>
                    <div style="font-weight:700; color:var(--primary);" id="ticket-display-berths">L4, L5</div>
                </div>
            </div>

            <div class="qr-container">
                <div id="ticket-qr-box"></div>
            </div>

            <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center; border-top:1px dashed var(--outline); padding-top:12px;">
                <div>
                    <div style="font-size:11.5px; color:#64748b;">Fare Paid (Tolls Included)</div>
                    <div style="font-size:11px; color:#16a34a; font-weight:600;">✓ Dispatched via SMS & WhatsApp</div>
                </div>
                <div style="font-family:var(--font-display); font-size:22px; font-weight:800; color:#0f172a;" id="ticket-display-amount" class="text-tabular">₹1,700</div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content:space-between;">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                <span class="material-symbols-outlined" style="font-size:15px;">print</span> Print
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('ticket-modal')">
                <span>Done</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD DYNAMIC CUSTOM FORM FIELD (AdminLTE 3)       -->
<!-- ======================================================== -->
<div class="modal-overlay" id="add-dynamic-field-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">add_circle</span>
                <span>Add Dynamic Form Field</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeAddDynamicFieldModal()">×</button>
        </div>
        <div class="modal-body">
            <form id="add-dynamic-field-form" onsubmit="submitAddDynamicField(event)">
                <div class="form-group">
                    <label class="form-label" for="new-field-label">Field Display Label *</label>
                    <div class="input-with-icon">
                        <span class="material-symbols-outlined">edit_note</span>
                        <input type="text" id="new-field-label" class="form-control" placeholder="e.g. Return Flight PNR, Child Safety Seat" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="new-field-type">Input Type</label>
                        <select id="new-field-type" class="form-control">
                            <option value="text">Text Input</option>
                            <option value="number">Numeric (Count / Age)</option>
                            <option value="select">Dropdown Select</option>
                            <option value="checkbox">Toggle / Checkbox</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new-field-applies">Applies To Scope</label>
                        <select id="new-field-applies" class="form-control">
                            <option value="all">All Bookings</option>
                            <option value="cab">Outstation Cabs Only</option>
                            <option value="bus">Volvo Sleeper Buses Only</option>
                            <option value="airport">Airport Transfers Only</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new-field-condition">Conditional Trigger Rule</label>
                    <select id="new-field-condition" class="form-control">
                        <option value="All Bookings">Always Visible</option>
                        <option value="Airport Trips Only">Only when Airport Trip selected</option>
                        <option value="Pet-friendly Selected">Only when Pet-friendly addon checked</option>
                        <option value="Corporate Invoice">Only for Corporate GST</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:12.5px; font-weight:600;">
                        <input type="checkbox" id="new-field-required" style="width:16px; height:16px; accent-color:var(--primary);">
                        <span>Mark this field as mandatory for checkout</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeAddDynamicFieldModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:16px;">check</span>
                        <span>Create Dynamic Field</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ALLOT CHAUFFEUR / DRIVER (AdminLTE 3)             -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-allot-driver-modal">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">local_taxi</span>
                <span>Allot Outstation Chauffeur</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-allot-driver-modal')">×</button>
        </div>
        <div class="modal-body">
            <div id="allot-modal-booking-info" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <span id="allot-info-ref" style="font-family:monospace; font-weight:700; color:var(--primary); font-size:13px;">ICW-00000</span>
                    <span id="allot-info-fare" style="font-weight:800; color:#0f172a;">₹0</span>
                </div>
                <div id="allot-info-customer" style="font-weight:600; color:#334155;">Customer Name</div>
                <div id="allot-info-route" style="color:#64748b; font-size:11px;">Pune → Goa</div>
            </div>

            <form id="admin-allot-form" onsubmit="submitAllotDriver(event)">
                <input type="hidden" id="allot-booking-id" value="">
                
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="allot-driver-select">Select Verified Chauffeur & Vehicle</label>
                    <select class="form-control" id="allot-driver-select" required onchange="onAllotDriverSelectChange(this)">
                        <option value="">-- Choose Chauffeur --</option>
                        @foreach($allDrivers as $d)
                        <option value="{{ $d->id }}" data-phone="{{ $d->phone }}" data-vehicle="{{ $d->vehicle_model }}" data-plate="{{ $d->vehicle_plate }}" data-rating="{{ $d->rating }}" data-category="{{ $d->fleet_category }}">
                            {{ $d->name }} — {{ $d->vehicle_model }} ({{ $d->vehicle_plate }}) • {{ strtoupper($d->fleet_category) }} • ★{{ $d->rating }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div id="allot-driver-preview" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:12px;">
                    <div style="font-weight:700; color:#166534; display:flex; justify-content:space-between;">
                        <span id="allot-preview-name">Driver Name</span>
                        <span id="allot-preview-rating">★4.90</span>
                    </div>
                    <div style="color:#15803d; font-size:11px; margin-top:2px;">
                        <span id="allot-preview-vehicle">Toyota Innova</span> • Plate: <strong id="allot-preview-plate">MH 12 QX 4920</strong>
                    </div>
                    <div style="color:#166534; font-size:11px; margin-top:2px;">
                        Phone: <span id="allot-preview-phone">+91 98220 11920</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="allot-status-select">Updated Booking Status</label>
                    <select class="form-control" id="allot-status-select">
                        <option value="Driver Assigned" selected>Driver Assigned (Chauffeur dispatched)</option>
                        <option value="Confirmed">Confirmed (Chauffeur queued)</option>
                        <option value="Started">Trip In Progress</option>
                    </select>
                </div>

                <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:8px 10px; margin-bottom:16px; font-size:11px; color:#92400e; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">notifications_active</span>
                    <span>An automated SMS & WhatsApp notification with driver contact and vehicle plate will be sent to the passenger.</span>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-allot-driver-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">check_circle</span>
                        <span>Confirm Allotment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW BOOKING & PAYMENT DETAILS (AdminLTE 3)       -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-view-booking-modal">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">visibility</span>
                    <span>Booking & Payment Details</span>
                    <span class="admin-badge badge-primary" id="view-booking-ref-badge">ICW-00000</span>
                </h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-view-booking-modal')">×</button>
        </div>
        <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y:auto; padding:16px 20px;">
            <!-- Trip Header Banner -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:16px;">
                <div>
                    <div style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; letter-spacing:0.04em;">Route & Service</div>
                    <div id="view-booking-route" style="font-size:16px; font-weight:800; color:#0f172a; margin-top:2px;">Pune → Goa</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <span class="admin-badge badge-gold" id="view-booking-service-badge">CAB</span>
                        <span class="admin-badge badge-info" id="view-booking-trip-type">ONE-WAY</span>
                        <span class="admin-badge badge-success" id="view-booking-status-badge">CONFIRMED</span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; color:#64748b; font-weight:600;">Ride Start OTP</div>
                    <div class="admin-otp-pill" id="view-booking-otp" style="font-size:16px; font-weight:800; padding:4px 10px; margin-top:3px; display:inline-block;">8492</div>
                    <div style="font-size:10px; color:#94a3b8; margin-top:4px;" id="view-booking-date">08 Nov 2026, 01:25 PM</div>
                </div>
            </div>

            <!-- Two-Column Information Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <!-- Passenger Info Card -->
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">person</span>
                        <span>Passenger Manifest</span>
                    </div>
                    <div style="font-size:13px; font-weight:700; color:#0f172a;" id="view-booking-customer-name">Rahul Sharma</div>
                    <div style="font-size:12px; color:#475569; margin-top:3px;">
                        📞 <a href="tel:" id="view-booking-customer-phone" style="color:inherit; text-decoration:none;">+91 98220 11920</a>
                    </div>
                    <div style="font-size:12px; color:#475569; margin-top:2px;">
                        ✉️ <span id="view-booking-customer-email">customer@intercitywheels.com</span>
                    </div>
                    <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; font-size:11.5px; color:#64748b;">
                        <div><strong>Pickup:</strong> <span id="view-booking-pickup">Kothrud, Pune</span></div>
                        <div style="margin-top:2px;"><strong>Drop:</strong> <span id="view-booking-drop">Candolim, Goa</span></div>
                    </div>
                </div>

                <!-- Chauffeur / Driver Card -->
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;" id="view-booking-driver-box">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:#16a34a;">local_taxi</span>
                        <span>Assigned Chauffeur</span>
                    </div>
                    <div id="view-booking-driver-content">
                        <!-- dynamic chauffeur or unassigned state -->
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown Card -->
            <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px; background:#fafafa;">
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:10px; display:flex; justify-content:space-between;">
                    <span style="display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">payments</span>
                        <span>Payment Breakdown & Financial Audit</span>
                    </span>
                    <span class="admin-badge badge-success" style="font-size:10px;">ESCROW VERIFIED</span>
                </div>

                <div style="font-size:12px; display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Base Intercity Route Fare:</span>
                        <span style="font-weight:600;" id="view-booking-base-fare">₹0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>NHAI National Highway Toll Charges:</span>
                        <span style="font-weight:600;" id="view-booking-toll-fare">₹0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Add-on Fees (Pet / Night Stay):</span>
                        <span style="font-weight:600;" id="view-booking-addon-fare">₹0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#16a34a;" id="view-booking-discount-row">
                        <span>Promotional Coupon Discount:</span>
                        <span style="font-weight:700;" id="view-booking-discount-fare">-₹0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:800; color:#0f172a; border-top:1px solid #e2e8f0; padding-top:8px; margin-top:2px;">
                        <span>Total Paid by Passenger:</span>
                        <span style="color:var(--primary);" id="view-booking-total-fare">₹0</span>
                    </div>
                </div>

                <div style="margin-top:10px; padding:8px 10px; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; font-size:11px; color:#64748b; display:flex; justify-content:space-between; align-items:center;">
                    <span><strong>Payment Mode:</strong> Online Escrow (Prepaid)</span>
                    <span><strong>Transaction ID:</strong> <span id="view-booking-txnid" style="font-family:monospace; font-weight:700; color:#0f172a;">TXN-1029384</span></span>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content:space-between;">
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-outline btn-sm" id="view-modal-allot-btn" onclick="openAllotDriverFromView()">
                    <span class="material-symbols-outlined" style="font-size:14px;">local_taxi</span>
                    <span>Allot Chauffeur</span>
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="view-modal-edit-btn" onclick="openEditBookingFromView()">
                    <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                    <span>Edit Booking</span>
                </button>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-primary btn-sm" id="view-modal-invoice-btn" onclick="openInvoiceFromView()">
                    <span class="material-symbols-outlined" style="font-size:14px;">receipt</span>
                    <span>Generate Tax Invoice</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-view-booking-modal')">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT / UPDATE BOOKING (AdminLTE 3)                -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-edit-booking-modal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">edit_note</span>
                <span>Edit Outstation Booking</span>
                <span class="admin-badge badge-primary" id="edit-booking-ref-badge">ICW-00000</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-edit-booking-modal')">×</button>
        </div>
        <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y:auto; padding:16px 20px;">
            <form id="admin-edit-booking-form" onsubmit="submitEditBooking(event)">
                <input type="hidden" id="edit-booking-id" value="">

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Passenger Contact Information</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-customer-name">Passenger Name</label>
                        <input type="text" id="edit-customer-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-customer-phone">Mobile Phone</label>
                        <input type="text" id="edit-customer-phone" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="edit-customer-email">Email Address</label>
                    <input type="email" id="edit-customer-email" class="form-control">
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Trip Route & Addresses</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-service-type">Service Type</label>
                        <select id="edit-service-type" class="form-control">
                            <option value="cab">Outstation Cab</option>
                            <option value="bus">Volvo Sleeper Berth</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-trip-type">Trip Direction</label>
                        <select id="edit-trip-type" class="form-control">
                            <option value="oneway">One-Way</option>
                            <option value="roundtrip">Round-Trip</option>
                            <option value="airport">Airport Transfer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="edit-route-summary">Route Summary</label>
                    <input type="text" id="edit-route-summary" class="form-control" placeholder="e.g. Pune → Goa" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-pickup-location">Pickup Address</label>
                        <input type="text" id="edit-pickup-location" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-drop-location">Drop Address</label>
                        <input type="text" id="edit-drop-location" class="form-control" required>
                    </div>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Financial Charges & Reconciliation (₹)</div>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-base-fare">Base Fare</label>
                        <input type="number" id="edit-base-fare" class="form-control" step="1" oninput="recalcEditTotalFare()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-toll-fare">Toll Fare</label>
                        <input type="number" id="edit-toll-fare" class="form-control" step="1" oninput="recalcEditTotalFare()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-addon-fare">Addons</label>
                        <input type="number" id="edit-addon-fare" class="form-control" step="1" oninput="recalcEditTotalFare()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-discount-fare">Discount</label>
                        <input type="number" id="edit-discount-fare" class="form-control" step="1" oninput="recalcEditTotalFare()">
                    </div>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; font-weight:600; color:#475569;">Computed Total Amount:</span>
                    <span style="font-size:16px; font-weight:800; color:var(--primary);" id="edit-computed-total">₹0</span>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Operational Dispatch Status</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-status">Booking Status</label>
                        <select id="edit-status" class="form-control">
                            <option value="Confirmed">Confirmed</option>
                            <option value="Driver Assigned">Driver Assigned</option>
                            <option value="Started">Trip Started</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-start-otp">Start Ride OTP</label>
                        <input type="text" id="edit-start-otp" class="form-control" maxlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-driver-id">Assigned Chauffeur</label>
                        <select id="edit-driver-id" class="form-control">
                            <option value="">-- None / Unassigned --</option>
                            @foreach($allDrivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->vehicle_plate }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-edit-booking-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                        <span>Save Booking Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD MANUAL OUTSTATION BOOKING (AdminLTE 3)        -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-add-booking-modal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">add_circle</span>
                <span>Create Manual Outstation Booking</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-add-booking-modal')">×</button>
        </div>
        <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y:auto; padding:16px 20px;">
            <form id="admin-add-booking-form" onsubmit="submitAddBooking(event)">
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px;">Passenger Details</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-customer-name">Passenger Full Name *</label>
                        <input type="text" id="add-customer-name" class="form-control" placeholder="e.g. Anand Mahindra" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-customer-phone">Mobile Phone Number *</label>
                        <input type="text" id="add-customer-phone" class="form-control" placeholder="+91 98XXX XXXXX" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="add-customer-email">Email Address</label>
                    <input type="email" id="add-customer-email" class="form-control" placeholder="passenger@example.com">
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Route & Fleet Selection</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-service-type">Service Type</label>
                        <select id="add-service-type" class="form-control">
                            <option value="cab" selected>Outstation Cab</option>
                            <option value="bus">Luxury Sleeper Berth</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-trip-type">Trip Direction</label>
                        <select id="add-trip-type" class="form-control">
                            <option value="oneway" selected>One-Way Outstation</option>
                            <option value="roundtrip">Round-Trip Outstation</option>
                            <option value="airport">Airport Transfer</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-from-city">From City *</label>
                        <select id="add-from-city" class="form-control" required onchange="autoSuggestAddFares()">
                            <option value="Pune" selected>Pune</option>
                            <option value="Mumbai">Mumbai</option>
                            <option value="Goa">Goa</option>
                            <option value="Nashik">Nashik</option>
                            <option value="Shirdi">Shirdi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-to-city">To City *</label>
                        <select id="add-to-city" class="form-control" required onchange="autoSuggestAddFares()">
                            <option value="Goa" selected>Goa</option>
                            <option value="Mumbai">Mumbai</option>
                            <option value="Pune">Pune</option>
                            <option value="Nashik">Nashik</option>
                            <option value="Shirdi">Shirdi</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-pickup-location">Detailed Pickup Location *</label>
                        <input type="text" id="add-pickup-location" class="form-control" placeholder="e.g. Pune Airport Terminal 1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-drop-location">Detailed Drop Location *</label>
                        <input type="text" id="add-drop-location" class="form-control" placeholder="e.g. Calangute Beach, Goa" required>
                    </div>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Pricing & Surcharges (₹)</div>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-base-fare">Base Fare *</label>
                        <input type="number" id="add-base-fare" class="form-control" value="6500" step="1" oninput="recalcAddTotalFare()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-toll-fare">Toll Fare</label>
                        <input type="number" id="add-toll-fare" class="form-control" value="480" step="1" oninput="recalcAddTotalFare()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-addon-fare">Addon Fee</label>
                        <input type="number" id="add-addon-fare" class="form-control" value="0" step="1" oninput="recalcAddTotalFare()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-discount-fare">Discount</label>
                        <input type="number" id="add-discount-fare" class="form-control" value="0" step="1" oninput="recalcAddTotalFare()">
                    </div>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; font-weight:600; color:#475569;">Calculated Total Fare:</span>
                    <span style="font-size:16px; font-weight:800; color:var(--primary);" id="add-computed-total">₹6,980</span>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px;">Chauffeur Allotment (Optional)</div>
                <div class="form-group" style="margin-bottom:16px;">
                    <select id="add-driver-id" class="form-control">
                        <option value="">-- Allot Later / Unassigned --</option>
                        @foreach($allDrivers as $d)
                        <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->vehicle_model }} ({{ $d->vehicle_plate }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-add-booking-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">add_circle</span>
                        <span>Create & Dispatch Booking</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: FINAL GST TAX INVOICE (Printable / PDF)          -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-invoice-modal">
    <div class="modal-dialog invoice-modal-dialog" style="max-width: 760px; width: 95%;">
        <div class="modal-header invoice-no-print">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">receipt</span>
                <span>GST Tax Invoice Preview</span>
            </h3>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="btn btn-sm btn-primary" onclick="printAdminInvoice()">
                    <span class="material-symbols-outlined" style="font-size:16px;">print</span>
                    <span>Print / Save PDF</span>
                </button>
                <button type="button" class="modal-close-btn" onclick="closeModal('admin-invoice-modal')">×</button>
            </div>
        </div>
        <div class="modal-body" style="max-height: calc(88vh - 120px); overflow-y:auto; padding:20px; background:#f1f5f9;">
            <!-- Official Invoice Canvas (Printable Area) -->
            <div class="invoice-paper-card" id="invoice-printable-area">
                <!-- Header: Company & Tax Identity -->
                <div class="invoice-header-row">
                    <div class="invoice-brand">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <span style="font-size:22px;">🚗</span>
                            <span class="invoice-company-name">INTERCITY WHEELS</span>
                        </div>
                        <div class="invoice-company-legal">IntercityWheels Mobility Services Pvt. Ltd.</div>
                        <div class="invoice-company-sub">Regd. Office: Floor 4, Tech Vista Towers, Senapati Bapat Road, Pune - 411016</div>
                        <div class="invoice-company-sub"><strong>GSTIN:</strong> 27AAACI9912K1Z8 &nbsp;|&nbsp; <strong>SAC Code:</strong> 996412 (Passenger Transport)</div>
                        <div class="invoice-company-sub">Helpline: 1800-209-WHEELS &nbsp;|&nbsp; billing@intercitywheels.com</div>
                    </div>
                    <div class="invoice-meta-box">
                        <div class="invoice-badge-title">TAX INVOICE</div>
                        <div class="invoice-meta-item"><strong>Invoice No:</strong> <span id="inv-doc-number">INV-2026-ICW-00000</span></div>
                        <div class="invoice-meta-item"><strong>Invoice Date:</strong> <span id="inv-doc-date">08 Nov 2026</span></div>
                        <div class="invoice-meta-item"><strong>Booking Ref:</strong> <span id="inv-booking-ref" style="font-family:monospace; font-weight:700;">ICW-00000</span></div>
                        <div class="invoice-meta-item"><strong>Place of Supply:</strong> Maharashtra (27)</div>
                    </div>
                </div>

                <hr class="invoice-divider">

                <!-- Billed To & Chauffeur Details Grid -->
                <div class="invoice-parties-grid">
                    <div class="invoice-party-col">
                        <div class="invoice-section-heading">Billed To (Passenger)</div>
                        <div class="invoice-party-name" id="inv-customer-name">Rahul Sharma</div>
                        <div class="invoice-party-detail">Phone: <span id="inv-customer-phone">+91 98220 11920</span></div>
                        <div class="invoice-party-detail">Email: <span id="inv-customer-email">customer@intercitywheels.com</span></div>
                        <div class="invoice-party-detail" style="margin-top:4px;">
                            <strong>Pickup:</strong> <span id="inv-pickup-address">Baner, Pune</span>
                        </div>
                        <div class="invoice-party-detail">
                            <strong>Drop:</strong> <span id="inv-drop-address">Panjim, Goa</span>
                        </div>
                    </div>
                    <div class="invoice-party-col">
                        <div class="invoice-section-heading">Trip & Chauffeur Manifest</div>
                        <div class="invoice-party-detail"><strong>Service:</strong> <span id="inv-service-type">Outstation Cab (One-Way)</span></div>
                        <div class="invoice-party-detail"><strong>Route:</strong> <span id="inv-route">Pune → Goa</span></div>
                        <div class="invoice-party-detail"><strong>Trip Date:</strong> <span id="inv-travel-date">Scheduled Departure</span></div>
                        <div class="invoice-party-detail" style="margin-top:4px;">
                            <strong>Chauffeur:</strong> <span id="inv-driver-name">Ramesh Patil</span>
                        </div>
                        <div class="invoice-party-detail">
                            <strong>Vehicle:</strong> <span id="inv-vehicle-details">Toyota Innova Crysta (MH 12 QX 4920)</span>
                        </div>
                    </div>
                </div>

                <!-- Itemized Invoice Table -->
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Description of Service</th>
                            <th style="text-align:center;">HSN / SAC</th>
                            <th style="text-align:right;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody id="inv-items-tbody">
                        <tr>
                            <td>1</td>
                            <td>
                                <strong>Intercity Passenger Transport Service</strong>
                                <div style="font-size:10.5px; color:#64748b;">Outstation travel route fare</div>
                            </td>
                            <td style="text-align:center; font-family:monospace;">996412</td>
                            <td style="text-align:right; font-weight:600;" id="inv-row-base">₹0.00</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>
                                <strong>National Highway Fastag Toll Charges</strong>
                                <div style="font-size:10.5px; color:#64748b;">NHAI electronic toll plaza transit charges</div>
                            </td>
                            <td style="text-align:center; font-family:monospace;">996412</td>
                            <td style="text-align:right; font-weight:600;" id="inv-row-toll">₹0.00</td>
                        </tr>
                        <tr id="inv-addon-row">
                            <td>3</td>
                            <td>
                                <strong>Special Surcharges / Add-on Allowance</strong>
                                <div style="font-size:10.5px; color:#64748b;">Pet sanitization & driver night stay allowance</div>
                            </td>
                            <td style="text-align:center; font-family:monospace;">996412</td>
                            <td style="text-align:right; font-weight:600;" id="inv-row-addon">₹0.00</td>
                        </tr>
                        <tr id="inv-discount-row">
                            <td>4</td>
                            <td>
                                <strong style="color:#16a34a;">Less: Promotional Discount Coupon</strong>
                                <div style="font-size:10.5px; color:#16a34a;">Platform promotional deduction applied</div>
                            </td>
                            <td style="text-align:center; font-family:monospace;">—</td>
                            <td style="text-align:right; font-weight:700; color:#16a34a;" id="inv-row-discount">-₹0.00</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary & Tax Totals -->
                <div class="invoice-summary-row">
                    <div class="invoice-stamp-col">
                        <div class="invoice-paid-stamp">
                            <span class="material-symbols-outlined" style="font-size:18px;">verified</span>
                            <span>PAID IN FULL</span>
                        </div>
                        <div style="font-size:10.5px; color:#64748b; margin-top:6px;">
                            <strong>Payment Mode:</strong> Online Escrow (Prepaid)<br>
                            <strong>Ref:</strong> <span id="inv-txn-ref">TXN-0000000</span><br>
                            <strong>Start OTP:</strong> <span id="inv-start-otp" class="admin-otp-pill">0000</span>
                        </div>
                    </div>

                    <div class="invoice-calc-col">
                        <div class="inv-calc-line">
                            <span>Subtotal (Taxable Value):</span>
                            <span id="inv-subtotal">₹0.00</span>
                        </div>
                        <div class="inv-calc-line">
                            <span>CGST (2.5%):</span>
                            <span id="inv-cgst">₹0.00</span>
                        </div>
                        <div class="inv-calc-line">
                            <span>SGST (2.5%):</span>
                            <span id="inv-sgst">₹0.00</span>
                        </div>
                        <div class="inv-calc-line inv-grand-total">
                            <span>Net Total Invoice Value:</span>
                            <span id="inv-grand-total">₹0.00</span>
                        </div>
                        <div class="inv-amount-words" id="inv-amount-in-words">
                            (Rupees Zero Only)
                        </div>
                    </div>
                </div>

                <!-- Footer Signatory & Compliance Note -->
                <div class="invoice-footer-row">
                    <div class="invoice-notes">
                        <div style="font-weight:700; color:#334155;">Terms & Conditions:</div>
                        <div>1. This is a computer-generated tax invoice and complies with Section 31 of CGST Act 2017.</div>
                        <div>2. All tolls and state border permits are included per the scheduled booking agreement.</div>
                        <div>3. Disputes are subject to Pune, Maharashtra jurisdiction.</div>
                    </div>
                    <div class="invoice-signatory">
                        <div class="signatory-stamp">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:28px;">verified_user</span>
                        </div>
                        <div style="font-weight:700; font-size:11.5px; color:#0f172a;">IntercityWheels Mobility Services</div>
                        <div style="font-size:10px; color:#64748b;">Authorized Signatory</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer invoice-no-print" style="justify-content:space-between;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('admin-invoice-modal')">Close Preview</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="printAdminInvoice()">
                <span class="material-symbols-outlined" style="font-size:15px;">print</span>
                <span>Print / Download Tax Invoice</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD REGISTERED USER / DRIVER (AdminLTE 3)        -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-add-user-modal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">person_add</span>
                <span>Register New User or Chauffeur Partner</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-add-user-modal')">×</button>
        </div>
        <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y:auto; padding:16px 20px;">
            <form id="admin-add-user-form" onsubmit="submitAddUser(event)">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="add-user-role">Account Role *</label>
                    <select id="add-user-role" class="form-control" onchange="toggleAddDriverFields(this.value)">
                        <option value="customer" selected>Customer (Traveler / Passenger)</option>
                        <option value="driver">Driver Partner (Commercial Chauffeur)</option>
                        <option value="admin">Platform Administrator (Operations Super Admin)</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-user-name">Full Name *</label>
                        <input type="text" id="add-user-name" class="form-control" placeholder="e.g. Sunil Gavaskar" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-user-phone">Mobile Phone *</label>
                        <input type="text" id="add-user-phone" class="form-control" placeholder="+91 98XXX XXXXX" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-user-email">Email Address *</label>
                        <input type="email" id="add-user-email" class="form-control" placeholder="user@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-user-password">Initial Password</label>
                        <input type="password" id="add-user-password" class="form-control" placeholder="Default: password123">
                    </div>
                </div>

                <!-- Driver-Specific Fleet Fields (Visible only when role is Driver) -->
                <div id="add-user-driver-fleet-group" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:700; color:#15803d; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:15px;">local_taxi</span>
                        <span>Partner Fleet & Vehicle Details</span>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label class="form-label" for="add-driver-vehicle-model">Vehicle Model</label>
                            <input type="text" id="add-driver-vehicle-model" class="form-control" placeholder="e.g. Maruti Suzuki Ertiga">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add-driver-vehicle-plate">Registration Plate #</label>
                            <input type="text" id="add-driver-vehicle-plate" class="form-control" placeholder="e.g. MH 12 AB 1234">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-driver-category">Fleet Category</label>
                        <select id="add-driver-category" class="form-control">
                            <option value="sedan">Sedan (Dzire / Etios)</option>
                            <option value="suv" selected>SUV (Innova / Ertiga)</option>
                            <option value="prime">Prime Sedan (City / Ciaz)</option>
                            <option value="luxury">Luxury (BMW / Mercedes)</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-add-user-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">check</span>
                        <span>Register Account</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT REGISTERED USER (AdminLTE 3)                 -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-edit-user-modal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">edit</span>
                <span>Edit User Account</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-edit-user-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-edit-user-form" onsubmit="submitEditUser(event)">
                <input type="hidden" id="edit-user-id" value="">

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="edit-user-role">Account Role *</label>
                    <select id="edit-user-role" class="form-control">
                        <option value="customer">Customer (Traveler / Passenger)</option>
                        <option value="driver">Driver Partner (Commercial Chauffeur)</option>
                        <option value="admin">Platform Administrator (Operations Super Admin)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="edit-user-name">Full Name *</label>
                    <input type="text" id="edit-user-name" class="form-control" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-user-email">Email Address *</label>
                        <input type="email" id="edit-user-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-user-phone">Mobile Phone *</label>
                        <input type="text" id="edit-user-phone" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="edit-user-password">Reset Password (Optional)</label>
                    <input type="password" id="edit-user-password" class="form-control" placeholder="Leave blank to keep existing password">
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteUserAccount()" style="color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Account</span>
                    </button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-edit-user-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW USER DETAILS & ACTIVITY (AdminLTE 3)         -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-view-user-modal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">badge</span>
                <span>User Profile & Activity Overview</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-view-user-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <div style="display:flex; align-items:center; gap:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:16px;">
                <div style="font-size:36px; width:52px; height:52px; border-radius:50%; background:#ffffff; border:1px solid #cbd5e1; display:flex; align-items:center; justify-content:center;" id="view-user-avatar">
                    👤
                </div>
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:16px; font-weight:800; color:#0f172a;" id="view-user-name">User Name</span>
                        <span class="admin-badge badge-primary" id="view-user-role-badge">CUSTOMER</span>
                    </div>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        <span id="view-user-email">email@example.com</span> &bull; 
                        <a href="tel:" id="view-user-phone" style="color:inherit; text-decoration:none;">+91 98XXX XXXXX</a>
                    </div>
                </div>
            </div>

            <!-- Driver Card (if applicable) -->
            <div id="view-user-driver-card" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:12px;">
                <div style="font-weight:700; color:#166534; font-size:11px; text-transform:uppercase; margin-bottom:6px;">Chauffeur Fleet Profile</div>
                <div style="color:#0f172a; font-weight:700;" id="view-user-driver-vehicle">Toyota Innova Crysta</div>
                <div style="color:#15803d; margin-top:2px;">
                    Plate: <strong id="view-user-driver-plate">MH 12 QX 4920</strong> &bull; 
                    Wallet: <strong id="view-user-driver-wallet">₹14,500</strong> &bull; 
                    Rating: <strong id="view-user-driver-rating">★4.90</strong>
                </div>
            </div>

            <!-- Activity Summary -->
            <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; font-size:12px; margin-bottom:12px;">
                <div style="font-weight:700; color:#64748b; font-size:10.5px; text-transform:uppercase; margin-bottom:8px;">Recent Trips & Bookings Activity</div>
                <div id="view-user-activity-content" style="color:#475569;">
                    Loading activity...
                </div>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
            <button type="button" class="btn btn-outline btn-sm" id="view-user-edit-btn" onclick="openEditUserFromView()">
                <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                <span>Edit User</span>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-view-user-modal')">Close</button>
        </div>
    </div>
</div>
@endsection

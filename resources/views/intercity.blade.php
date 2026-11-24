@extends('layouts.app')

@section('content')
@php
    $userRole = Auth::check() ? Auth::user()->role : null;
    $defaultTab = match($userRole) {
        'admin' => 'admin',
        'driver' => 'bidding',
        default => 'cabs'
    };
@endphp
<main id="app-viewport" class="app-viewport" style="width:100%; max-width:100%; margin:0; padding:0;">

        <!-- ======================================================== -->
        <!-- TAB 1: OUTSTATION CABS (REDESIGNED LUXURY CAR RENTAL)    -->
        <!-- ======================================================== -->
        <section id="tab-cabs" class="tab-pane {{ $defaultTab === 'cabs' ? 'active' : '' }}" style="width:100%; max-width:100%; margin:0; padding:0;">
            <!-- Autocomplete Suggestions Datalist -->
            <datalist id="cities-datalist">
                @foreach($serviceLocations as $loc)
                    @if($loc->is_active)
                        <option value="{{ $loc->name }}, {{ $loc->state }}">{{ $loc->name }}</option>
                        <option value="{{ $loc->name }}">{{ $loc->state }}</option>
                    @endif
                @endforeach
            </datalist>

            <!-- ======================================================== -->
            <!-- 1. HOMEPAGE HERO SECTION                                 -->
            <!-- ======================================================== -->
            <div class="rental-hero-container">
                <!-- Main Bold Uppercase Heading -->
                <h1 class="rental-hero-title">SERVICES ACROSS 2000+ CITIES</h1>
                <p class="rental-hero-subtitle">India's Trusted Car Rental & Outstation Travel Partner.</p>

                <!-- Centered White Booking Panel -->
                <div class="rental-booking-card" id="cab-step-1">
                    <!-- Booking Type Tabs at Top Center -->
                    <div class="rental-tabs-wrapper">
                        <div class="rental-type-tabs">
                            <button type="button" class="rental-type-tab active" data-type="oneway" onclick="switchRentalTab('oneway')">ONE WAY</button>
                            <button type="button" class="rental-type-tab" data-type="roundtrip" onclick="switchRentalTab('roundtrip')">ROUND TRIP</button>
                            <button type="button" class="rental-type-tab" data-type="local" onclick="switchRentalTab('local')">LOCAL</button>
                            <button type="button" class="rental-type-tab" data-type="airport" onclick="switchRentalTab('airport')">AIRPORT</button>
                        </div>
                    </div>

                    <!-- Hidden legacy inputs for backward compatibility -->
                    <select id="cab-from-city" style="display:none;">
                        @foreach($serviceLocations as $loc)
                            @if($loc->is_active)
                                <option value="{{ strtolower($loc->name) }}" {{ strtolower($loc->name) === 'kolkata' ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <select id="cab-to-city" style="display:none;">
                        @foreach($serviceLocations as $loc)
                            @if($loc->is_active)
                                <option value="{{ strtolower($loc->name) }}" {{ strtolower($loc->name) === 'mayapur' ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endif
                        @endforeach
                    </select>

                    <!-- TAB A: ONE WAY -->
                    <div id="rental-form-oneway" class="rental-tab-panel">
                        <div class="rental-form-grid">
                            <!-- FROM -->
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-from-city">FROM</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-from-city" class="rental-input" value="Kolkata" placeholder="Pickup City / Area" autocomplete="off" onfocus="this.select(); openCityPicker('rental-from-city')" onclick="openCityPicker('rental-from-city')" oninput="handleCityPickerInput('rental-from-city', this.value); syncRentalFromCity(this.value)">
                                    <button type="button" class="rental-dropdown-toggle-btn" title="View available cities" onclick="toggleCityDropdown('rental-from-city', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                                    </button>
                                    <div id="dropdown-rental-from-city" class="city-picker-dropdown" style="display:none;"></div>
                                </div>
                            </div>

                            <!-- Swap Button -->
                            <button type="button" id="cab-swap-cities-btn" class="rental-swap-btn" title="Swap Route Direction" onclick="swapRentalCities()">
                                <span class="material-symbols-outlined" style="font-size:20px;">swap_horiz</span>
                            </button>

                            <!-- TO -->
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-to-city">TO</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-to-city" class="rental-input" value="Mayapur, West Bengal" placeholder="Destination City / Area" autocomplete="off" onfocus="this.select(); openCityPicker('rental-to-city')" onclick="openCityPicker('rental-to-city')" oninput="handleCityPickerInput('rental-to-city', this.value); syncRentalToCity(this.value)">
                                    <button type="button" class="rental-dropdown-toggle-btn" title="View available cities" onclick="toggleCityDropdown('rental-to-city', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                                    </button>
                                    <button type="button" class="rental-add-stop-btn" title="Add Stop / Multi-City" onclick="showToast('Multi-city stopovers supported for round trips and customized routes.', 'info')">
                                        <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                                    </button>
                                    <div id="dropdown-rental-to-city" class="city-picker-dropdown" style="display:none;"></div>
                                </div>
                            </div>

                            <!-- PICK UP DATE -->
                            <div class="rental-field-box" style="max-width:220px;">
                                <label class="rental-field-label" for="rental-pickup-date">PICK UP DATE</label>
                                <div class="rental-input-wrap">
                                    <input type="date" id="rental-pickup-date" class="rental-input" value="{{ date('Y-m-d', strtotime('+1 day')) }}" onchange="syncRentalDate(this.value)">
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>

                            <!-- PICK UP TIME -->
                            <div class="rental-field-box" style="max-width:200px;">
                                <label class="rental-field-label" for="rental-pickup-time">PICK UP TIME</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-pickup-time" class="rental-select" onchange="syncRentalTime(this.value)">
                                        <option value="12:15 AM" selected>12:15 AM</option>
                                        <option value="04:00 AM">04:00 AM</option>
                                        <option value="06:00 AM">06:00 AM</option>
                                        <option value="08:30 AM">08:30 AM</option>
                                        <option value="10:00 AM">10:00 AM</option>
                                        <option value="12:00 PM">12:00 PM</option>
                                        <option value="02:30 PM">02:30 PM</option>
                                        <option value="05:00 PM">05:00 PM</option>
                                        <option value="07:30 PM">07:30 PM</option>
                                        <option value="10:00 PM">10:00 PM</option>
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB B: ROUND TRIP -->
                    <div id="rental-form-roundtrip" class="rental-tab-panel" style="display:none;">
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-rt-from">FROM</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-rt-from" class="rental-input" value="Kolkata" placeholder="Pickup City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-rt-from')" onclick="openCityPicker('rental-rt-from')" oninput="handleCityPickerInput('rental-rt-from', this.value); updateRentalRouteStats()">
                                    <button type="button" class="rental-dropdown-toggle-btn" title="View available cities" onclick="toggleCityDropdown('rental-rt-from', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                                    </button>
                                    <div id="dropdown-rental-rt-from" class="city-picker-dropdown" style="display:none;"></div>
                                </div>
                            </div>
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-rt-to">TO</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-rt-to" class="rental-input" value="Mayapur, West Bengal" placeholder="Destination City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-rt-to')" onclick="openCityPicker('rental-rt-to')" oninput="handleCityPickerInput('rental-rt-to', this.value); updateRentalRouteStats()">
                                    <button type="button" class="rental-dropdown-toggle-btn" title="View available cities" onclick="toggleCityDropdown('rental-rt-to', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                                    </button>
                                    <div id="dropdown-rental-rt-to" class="city-picker-dropdown" style="display:none;"></div>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:180px;">
                                <label class="rental-field-label">PICK UP DATE</label>
                                <div class="rental-input-wrap">
                                    <input type="date" id="rental-rt-pickup-date" class="rental-input" value="{{ date('Y-m-d', strtotime('+1 day')) }}" onchange="calculateTripDuration()">
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:150px;">
                                <label class="rental-field-label">PICK UP TIME</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-rt-pickup-time" class="rental-select">
                                        <option value="06:00 AM" selected>06:00 AM</option>
                                        <option value="09:00 AM">09:00 AM</option>
                                        <option value="12:00 PM">12:00 PM</option>
                                        <option value="03:00 PM">03:00 PM</option>
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:180px;">
                                <label class="rental-field-label">RETURN DATE</label>
                                <div class="rental-input-wrap">
                                    <input type="date" id="rental-rt-return-date" class="rental-input" value="{{ date('Y-m-d', strtotime('+3 days')) }}" onchange="calculateTripDuration()">
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:150px;">
                                <label class="rental-field-label">RETURN TIME</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-rt-return-time" class="rental-select">
                                        <option value="08:00 PM" selected>08:00 PM</option>
                                        <option value="05:00 PM">05:00 PM</option>
                                        <option value="10:00 PM">10:00 PM</option>
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                            <span id="rental-rt-duration-pill" style="font-size:12px; font-weight:700; color:#0284c7; background:#e0f2fe; padding:3px 10px; border-radius:12px;">Trip Duration: 2 Days</span>
                        </div>
                    </div>

                    <!-- TAB C: LOCAL HOURLY RENTAL -->
                    <div id="rental-form-local" class="rental-tab-panel" style="display:none;">
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-local-city">CITY</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">location_city</span>
                                    <input type="text" id="rental-local-city" class="rental-input" value="Kolkata" placeholder="Select City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-local-city')" onclick="openCityPicker('rental-local-city')" oninput="handleCityPickerInput('rental-local-city', this.value); updateRentalRouteStats()">
                                    <button type="button" class="rental-dropdown-toggle-btn" title="View available cities" onclick="toggleCityDropdown('rental-local-city', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                                    </button>
                                    <div id="dropdown-rental-local-city" class="city-picker-dropdown" style="display:none;"></div>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:200px;">
                                <label class="rental-field-label">PICK UP DATE</label>
                                <div class="rental-input-wrap">
                                    <input type="date" id="rental-local-date" class="rental-input" value="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:180px;">
                                <label class="rental-field-label">PICK UP TIME</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-local-time" class="rental-select">
                                        <option value="09:00 AM" selected>09:00 AM</option>
                                        <option value="10:00 AM">10:00 AM</option>
                                        <option value="01:00 PM">01:00 PM</option>
                                        <option value="04:00 PM">04:00 PM</option>
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box">
                                <label class="rental-field-label">DURATION / PACKAGE</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-local-package" class="rental-select">
                                        @forelse($localPackages->where('is_active', true) as $lp)
                                            <option value="{{ $lp->package_code }}" {{ $lp->package_code === '8_80' ? 'selected' : '' }}>
                                                {{ $lp->name }} &bull; ₹{{ number_format($lp->sedan_fare) }}
                                            </option>
                                        @empty
                                            <option value="4_40">4 Hours / 40 KM</option>
                                            <option value="8_80" selected>8 Hours / 80 KM (Most Popular)</option>
                                            <option value="10_100">10 Hours / 100 KM (Full Day)</option>
                                            <option value="12_120">12 Hours / 120 KM (Extended)</option>
                                        @endforelse
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB D: AIRPORT TRANSFER -->
                    <div id="rental-form-airport" class="rental-tab-panel" style="display:none;">
                        <div style="display:flex; gap:16px; margin-bottom:14px;">
                            <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer;">
                                <input type="radio" name="airport_direction" value="pickup" checked onchange="toggleAirportDirection('pickup')">
                                <span>Pickup from Airport</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer;">
                                <input type="radio" name="airport_direction" value="drop" onchange="toggleAirportDirection('drop')">
                                <span>Drop to Airport</span>
                            </label>
                        </div>
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" id="rental-airport-label-1">AIRPORT / TERMINAL</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">flight</span>
                                    <input type="text" id="rental-airport-name" class="rental-input" value="Netaji Subhash Chandra Bose Intl (CCU), Kolkata" placeholder="Enter Airport / Terminal" list="airport-suggestions-list">
                                </div>
                            </div>
                            <datalist id="airport-suggestions-list">
                                @foreach($airportHubs as $hub)
                                    <option value="{{ $hub->airport_name }} ({{ $hub->iata_code }}), {{ $hub->city }}">{{ $hub->terminals }}</option>
                                @endforeach
                            </datalist>
                            <div class="rental-field-box">
                                <label class="rental-field-label" id="rental-airport-label-2">DESTINATION ADDRESS</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">pin_drop</span>
                                    <input type="text" id="rental-airport-address" class="rental-input" value="Salt Lake Sector V, Kolkata" placeholder="Enter City Area / Hotel / Landmark">
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:180px;">
                                <label class="rental-field-label">DATE</label>
                                <div class="rental-input-wrap">
                                    <input type="date" id="rental-airport-date" class="rental-input" value="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:150px;">
                                <label class="rental-field-label">TIME</label>
                                <div class="rental-input-wrap">
                                    <select id="rental-airport-time" class="rental-select">
                                        <option value="12:15 AM">12:15 AM</option>
                                        <option value="06:00 AM">06:00 AM</option>
                                        <option value="11:30 AM" selected>11:30 AM</option>
                                        <option value="04:00 PM">04:00 PM</option>
                                        <option value="08:45 PM">08:45 PM</option>
                                    </select>
                                    <span class="material-symbols-outlined rental-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="rental-field-box" style="max-width:160px;">
                                <label class="rental-field-label">FLIGHT NO. (OPTIONAL)</label>
                                <div class="rental-input-wrap">
                                    <input type="text" id="rental-airport-flight" class="rental-input" placeholder="e.g. 6E-204" style="text-transform:uppercase;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Date & Time inputs for test backward compatibility -->
                    <input type="hidden" id="cab-pickup-date" value="{{ date('Y-m-d', strtotime('+1 day')) }}">
                    <input type="hidden" id="cab-pickup-time" value="12:15">
                    <input type="hidden" id="cab-return-date" value="{{ date('Y-m-d', strtotime('+3 days')) }}">
                    <input type="hidden" id="cab-return-time" value="17:00">

                    <!-- Instant Highway Route Insight Strip (Required by Automated Tests) -->
                    <div class="cab-route-insight-strip" style="margin-top:14px; background:#0f172a; border-radius:8px; padding:10px 16px; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="color:#20A9D2; font-size:18px;">alt_route</span>
                            <span><strong id="step1-route-name">Kolkata → Mayapur, West Bengal</strong></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:16px; font-size:12px;">
                            <span><strong id="step1-dist" style="color:#f8fafc;">132 km</strong> (<span id="step1-time">3h 30m</span>)</span>
                            <span>Highway Tolls: <strong id="step1-toll" style="color:#20A9D2;">₹120 Included</strong></span>
                        </div>
                    </div>

                    <!-- CTA Explore Cabs Button -->
                    <div class="rental-cta-container">
                        <button type="button" id="btn-explore-cabs" class="btn-explore-cabs" onclick="exploreCabs()">
                            <span class="material-symbols-outlined" style="font-size:20px;">directions_car</span>
                            <span>EXPLORE CABS</span>
                        </button>
                    </div>
                </div>

                <!-- Laurel Wreath Badge -->
                <div class="rental-laurel-badge">
                    <span style="font-size:16px;">🌿</span>
                    <span>India's Top Rated Car Rental Service</span>
                    <span style="font-size:16px;">🌿</span>
                </div>

                <!-- Floating Trust & Social Proof Pill -->
                <div class="rental-trust-pill-container">
                    <div class="rental-trust-pill">
                        <!-- App Store -->
                        <div class="rental-trust-item">
                            <div class="rental-trust-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="#0f172a"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.62-.75 1.04-1.8 1.01-2.87-.96.04-2.13.64-2.82 1.44-.6.69-1.13 1.76-.99 2.81 1.07.08 2.18-.63 2.8-1.38z"/></svg>
                            </div>
                            <div class="rental-trust-meta">
                                <span class="rental-trust-source">App Store</span>
                                <span class="rental-trust-stars">★★★★★</span>
                                <span class="rental-trust-count">(4.2K+ Reviews)</span>
                            </div>
                        </div>

                        <!-- Google -->
                        <div class="rental-trust-item">
                            <div class="rental-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                            </div>
                            <div class="rental-trust-meta">
                                <span class="rental-trust-source">Google</span>
                                <span class="rental-trust-stars">★★★★★</span>
                                <span class="rental-trust-count">(6.1K+ Reviews)</span>
                            </div>
                        </div>

                        <!-- Play Store -->
                        <div class="rental-trust-item">
                            <div class="rental-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24"><path fill="#4285F4" d="M3.6 1.8L14.2 12.4 3.6 23z"/><path fill="#34A853" d="M17.4 9.2L3.6 1.8l10.6 10.6z"/><path fill="#EA4335" d="M3.6 23l13.8-7.4-3.2-3.2z"/><path fill="#FBBC05" d="M21.2 11.2l-3.8-2-3.2 3.2 3.2 3.2 3.8-2c.8-.5.8-1.9 0-2.4z"/></svg>
                            </div>
                            <div class="rental-trust-meta">
                                <span class="rental-trust-source">Play Store</span>
                                <span class="rental-trust-stars">★★★★★</span>
                                <span class="rental-trust-count">(15.5K+ Reviews)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 2. PROMOTIONAL BANNER SECTION                            -->
            <!-- ======================================================== -->
            <div class="rental-promo-section">
                <div class="rental-promo-grid">
                    <!-- Card 1 -->
                    <div class="rental-promo-card card-zero-cost" onclick="exploreCabs()">
                        <span class="rental-promo-badge">PROMO ASSURED</span>
                        <h3 class="rental-promo-title">Book Now at zero cost</h3>
                        <p class="rental-promo-sub">No booking charges. Pay 20% advance or pay later to driver.</p>
                    </div>

                    <!-- Card 2 -->
                    <div class="rental-promo-card card-chardham" onclick="exploreCabs()">
                        <span class="rental-promo-badge">POPULAR CIRCUITS</span>
                        <h3 class="rental-promo-title">EXCLUSIVE OUTSTATION OFFERS</h3>
                        <p class="rental-promo-sub">Discover special pilgrimage, weekend getaways & custom tours.</p>
                    </div>

                    <!-- Card 3 -->
                    <div class="rental-promo-card card-free-cancel" onclick="exploreCabs()">
                        <span class="rental-promo-badge">PEACE OF MIND</span>
                        <h3 class="rental-promo-title">Free Cancellations</h3>
                        <p class="rental-promo-sub">Zero cancellation fee up to 1 hour before scheduled pickup.</p>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- STEPPER HEADER (Used for seamless multi-step navigation)  -->
            <!-- ======================================================== -->
            <div class="rental-results-section" style="margin-top:10px; margin-bottom:10px;">
                <div class="cab-stepper-header" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:12px 20px;">
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
            </div>

            <!-- ======================================================== -->
            <!-- STEP 2: VEHICLE SEARCH RESULTS COMPARISON                -->
            <!-- ======================================================== -->
            <div id="cab-step-2" class="rental-results-section cab-step-content" style="display:none;">
                <!-- Search Summary Bar with Edit Search CTA -->
                <div class="rental-summary-bar">
                    <div class="rental-summary-info">
                        <span class="material-symbols-outlined" style="color:#20A9D2; font-size:22px;">navigation</span>
                        <span style="font-weight:700; font-size:15px; color:#0f172a;" id="recap-route">Kolkata → Mayapur, West Bengal</span>
                        <span style="color:#cbd5e1;">•</span>
                        <span id="recap-datetime" style="color:#475569; font-weight:600;">11 Nov 2026, 12:15 AM</span>
                        <span style="color:#cbd5e1;">•</span>
                        <span id="recap-trip-type" style="background:#e0f2fe; color:#0284c7; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11px; text-transform:uppercase;">One Way</span>
                        <span style="color:#cbd5e1;">•</span>
                        <span id="recap-distance" style="font-weight:600; color:#475569;">132 km (~3.5 hrs)</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="goToCabStep(1)" style="display:flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:15px;">edit</span>
                        <span>Edit Search</span>
                    </button>
                </div>

                <!-- Filters & Sorting Toolbar -->
                <div class="rental-filter-bar">
                    <div class="rental-filter-pills">
                        <button type="button" class="rental-filter-pill active" onclick="filterRentalCategory('all', this)">All Cabs</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('hatchback', this)">Hatchback</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('sedan', this)">Sedan</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('suv', this)">SUV</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('innova', this)">Innova Crysta</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('tempo', this)">Tempo Traveller</button>
                        <button type="button" class="rental-filter-pill" onclick="filterRentalCategory('luxury', this)">Luxury</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:12.5px; font-weight:600; color:#64748b;">Sort By:</span>
                        <select id="rental-sort-by" onchange="sortRentalResults(this.value)" style="border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px; font-size:12.5px; font-weight:600; color:#0f172a; background:#ffffff;">
                            <option value="recommended">Recommended</option>
                            <option value="price_low">Lowest Price</option>
                            <option value="capacity_high">Highest Capacity</option>
                        </select>
                    </div>
                </div>

                <!-- Vehicle Comparison Cards Grid -->
                <div class="rental-vehicle-grid" id="rental-vehicles-grid">
                    <!-- Cards will be dynamically calculated & refreshed by JS -->
                </div>

                <!-- Trust Inclusions Highlight Strip -->
                <div class="trust-inclusions-strip" style="margin-top:24px; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:16px 20px; display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div class="trust-item" style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:#0f172a;">
                        <span class="material-symbols-outlined" style="color:#16a34a;">toll</span>
                        <span>Tolls & Taxes Included</span>
                    </div>
                    <div class="trust-item" style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:#0f172a;">
                        <span class="material-symbols-outlined" style="color:#16a34a;">verified</span>
                        <span>Verified Chauffeur</span>
                    </div>
                    <div class="trust-item" style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:#0f172a;">
                        <span class="material-symbols-outlined" style="color:#16a34a;">ac_unit</span>
                        <span>Clean & AC Sanitized</span>
                    </div>
                    <div class="trust-item" style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:#0f172a;">
                        <span class="material-symbols-outlined" style="color:#16a34a;">lock</span>
                        <span>4-Digit Ride OTP</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- STEP 3: 2-COLUMN PASSENGER DETAILS & CHECKOUT            -->
            <!-- ======================================================== -->
            <div id="cab-step-3" class="rental-results-section cab-step-content" style="display:none;">
                <div class="rental-checkout-grid">
                    <!-- LEFT COLUMN: Passenger & Trip Details Form -->
                    <div class="card" style="padding:24px; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px;">
                        <div style="font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; font-weight:800; color:#0f172a; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-outlined" style="color:#20A9D2;">badge</span>
                            <span>Passenger & Pickup Coordinates</span>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Full Name *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">person</span>
                                    <input type="text" id="cab-rider-name" class="form-control" placeholder="Full Name" value="{{ Auth::check() ? Auth::user()->name : 'Aditya Sharma' }}" required>
                                </div>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Mobile Number *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">call</span>
                                    <input type="tel" id="cab-rider-phone" class="form-control" placeholder="Mobile Number (+91)" value="{{ Auth::check() ? (Auth::user()->phone ?? '+91 98220 11920') : '+91 98220 11920' }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top:12px;">
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Email Address (For Tax Invoice & Receipt)</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">mail</span>
                                    <input type="email" id="cab-rider-email" class="form-control" placeholder="Email Address" value="{{ Auth::check() ? Auth::user()->email : 'traveller@intercitywheels.com' }}">
                                </div>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Number of Passengers</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">group</span>
                                    <select id="cab-rider-passengers" class="form-control">
                                        <option value="1">1 Passenger</option>
                                        <option value="2">2 Passengers</option>
                                        <option value="3">3 Passengers</option>
                                        <option value="4" selected>4 Passengers</option>
                                        <option value="5">5 Passengers</option>
                                        <option value="6">6 Passengers</option>
                                        <option value="7">7 Passengers</option>
                                        <option value="12">12+ Passengers (Tempo)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top:12px;">
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Exact Pickup Address / Landmark *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">my_location</span>
                                    <input type="text" id="cab-pickup-address" class="form-control" placeholder="Building, Street, Landmark" value="Salt Lake Sector V, Kolkata">
                                </div>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Exact Drop Address *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined" style="color:#0284c7;">pin_drop</span>
                                    <input type="text" id="cab-drop-address" class="form-control" placeholder="Drop Hotel / Landmark" value="Mayapur ISKCON Temple Main Gate">
                                </div>
                            </div>
                        </div>

                        <!-- Special Instructions -->
                        <div class="form-group" style="margin-top:12px;">
                            <label class="rental-field-label">Special Chauffeur Instructions / Flight No.</label>
                            <input type="text" id="cab-special-instructions" class="form-control" placeholder="e.g. Please carry booster seat, flight arriving at Gate 3">
                        </div>

                        <!-- Add-ons -->
                        <div style="margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9;">
                            <label class="rental-field-label" style="margin-bottom:8px;">Trip Preferences & Add-ons</label>
                            <div class="addons-group">
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-tolls" checked onchange="updateTotalCabFare()">
                                        <span>Highway Fastag Tolls</span>
                                    </div>
                                    <span class="addon-price">Included</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-pet" onchange="updateTotalCabFare()">
                                        <span>Pet Sanitized Cab</span>
                                    </div>
                                    <span class="addon-price">+₹350</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-night" onchange="updateTotalCabFare()">
                                        <span>Chauffeur Night Stay</span>
                                    </div>
                                    <span class="addon-price">+₹400</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-luggage" onchange="updateTotalCabFare()">
                                        <span>Rooftop Luggage Carrier</span>
                                    </div>
                                    <span class="addon-price">+₹200</span>
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic Form Fields from Database -->
                        @if($dynamicFields->count() > 0)
                        <div style="margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0;" id="cab-dynamic-fields-wrapper">
                            <label class="rental-field-label">Custom Specifications</label>
                            @foreach($dynamicFields as $field)
                            <div class="form-group dynamic-field-item" id="dynamic-field-{{ $field->field_key }}" data-field-key="{{ $field->field_key }}" data-applies-to="{{ $field->applies_to }}" data-conditional="{{ $field->conditional_logic }}" style="margin-bottom:8px;">
                                <label style="font-size:12px; font-weight:600; color:#475569;">{{ $field->label }}</label>
                                <input type="{{ $field->field_type }}" class="form-control cab-dynamic-field" data-key="{{ $field->field_key }}" name="dynamic_{{ $field->field_key }}" placeholder="Enter {{ $field->label }}">
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    <!-- RIGHT COLUMN: Sticky Booking Summary -->
                    <div class="card" style="padding:22px; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; position:sticky; top:80px; box-shadow:0 4px 18px rgba(0,0,0,0.06);">
                        <div style="font-family:'Plus Jakarta Sans',sans-serif; font-size:16px; font-weight:800; color:#0f172a; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                            <span>Booking Summary</span>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="goToCabStep(2)" style="font-size:11px; padding:3px 8px;">Change Cab</button>
                        </div>

                        <!-- Selected Vehicle Snapshot -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:14px; display:flex; align-items:center; gap:12px;">
                            <img id="summary-vehicle-img" src="https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80" alt="Selected Cab" style="width:70px; height:45px; object-fit:cover; border-radius:6px;">
                            <div>
                                <div style="font-weight:800; font-size:14px; color:#0f172a;" id="recap-fleet-title">Executive SUV (Innova Crysta)</div>
                                <div style="font-size:11.5px; color:#64748b;" id="recap-route-mini">Kolkata → Mayapur, West Bengal</div>
                            </div>
                        </div>

                        <!-- Fare Breakdown Table -->
                        <div style="display:flex; flex-direction:column; gap:8px; font-size:13px; color:#475569;">
                            <div style="display:flex; justify-content:space-between;">
                                <span>Base Route Fare</span>
                                <span id="fare-base" style="font-weight:700; color:#0f172a;" class="text-tabular">₹2,850</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span>Fastag Tolls & Taxes</span>
                                <span id="fare-tolls" style="font-weight:700; color:#0f172a;" class="text-tabular">₹120</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span>Selected Add-ons</span>
                                <span id="fare-addons" style="font-weight:700; color:#0f172a;" class="text-tabular">₹0</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; color:#16a34a; font-weight:600;">
                                <span>Promo Discount</span>
                                <span id="fare-discount" class="text-tabular">-₹0</span>
                            </div>

                            <!-- Coupon Code Input -->
                            <div style="margin-top:6px; margin-bottom:6px;">
                                <div style="display:flex; gap:6px;">
                                    <input type="text" id="cab-coupon-input" class="form-control" placeholder="COUPON (e.g. FIRSTWHEELS)" style="text-transform:uppercase; font-size:12px; height:36px;" value="">
                                    <button type="button" id="apply-coupon-btn" class="btn btn-secondary" onclick="applyRentalCoupon()" style="height:36px; padding:0 12px; font-size:12px;">Apply</button>
                                </div>
                                <div id="coupon-msg" style="font-size:11px; font-weight:600; color:#16a34a; margin-top:3px; display:none;"></div>
                            </div>

                            <div style="border-top:1px dashed #cbd5e1; padding-top:10px; margin-top:4px; display:flex; justify-content:space-between; align-items:baseline;">
                                <div>
                                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Total Payable</div>
                                    <div style="font-size:10.5px; color:#16a34a;">GST, Tolls & Driver Included</div>
                                </div>
                                <span id="fare-grand-total" style="font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; font-weight:800; color:#0f172a;" class="text-tabular">₹2,550</span>
                            </div>
                        </div>

                        <!-- Payment Method Radios -->
                        <div style="margin-top:14px; background:#f8fafc; border-radius:8px; padding:10px 12px;">
                            <div style="font-size:11.5px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:6px;">Select Payment Mode</div>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; margin-bottom:4px; cursor:pointer;">
                                <input type="radio" name="rental_pay_mode" value="full" checked>
                                <span>Pay Full Online (UPI / Card / NetBanking)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; margin-bottom:4px; cursor:pointer;">
                                <input type="radio" name="rental_pay_mode" value="advance">
                                <span>Pay 20% Advance (Balance to Driver)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer;">
                                <input type="radio" name="rental_pay_mode" value="cash">
                                <span>Pay Later to Chauffeur (Cash / UPI)</span>
                            </label>
                        </div>

                        <!-- Terms & Conditions Checkbox -->
                        <div style="margin-top:12px; font-size:11.5px; color:#475569; display:flex; align-items:flex-start; gap:6px;">
                            <input type="checkbox" id="rental-terms-agree" checked style="margin-top:2px;">
                            <label for="rental-terms-agree">
                                By proceeding, you agree to <a href="/legal/privacy" target="_blank" style="color:#0284c7; text-decoration:underline;">Privacy Policy</a> and <a href="/legal/terms" target="_blank" style="color:#0284c7; text-decoration:underline;">T&Cs</a>.
                            </label>
                        </div>

                        <!-- Confirm & Book Button -->
                        <div style="margin-top:16px;">
                            <button type="button" id="book-cab-confirm-btn" class="btn-explore-cabs" onclick="handleConfirmCabBooking()" style="width:100%; justify-content:center; padding:14px 20px; font-size:15px;">
                                <span class="material-symbols-outlined" style="font-size:20px;">verified_user</span>
                                <span>CONFIRM & BOOK CAB</span>
                            </button>
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

        @if($userRole === 'driver' || $userRole === 'admin')
        <!-- ======================================================== -->
        <!-- TAB 3: DRIVER BIDDING MARKETPLACE & AUCTION              -->
        <!-- ======================================================== -->
        <section id="tab-bidding" class="tab-pane {{ $defaultTab === 'bidding' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'bidding'])

            <!-- Driver Registered Vehicles & Approval Status Card -->
            <div class="card driver-panel-card animate-load stagger-2" style="margin-bottom:16px; padding:14px 18px; border:1px solid rgba(212,155,76,0.3); background:#ffffff;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">garage</span>
                        <span style="font-family:var(--font-display); font-weight:700; font-size:14px; color:#0f172a;">My Registered Cars & Fleet Status</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="openDriverListVehicleModal()">
                        <span class="material-symbols-outlined" style="font-size:14px;">add</span>
                        <span>List Another Car</span>
                    </button>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px;" id="driver-my-vehicles-grid">
                    @forelse($driverMyVehicles as $v)
                        <div id="driver-my-veh-card-{{ $v->id }}" style="border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:700; font-size:13px; color:#0f172a;" id="driver-veh-title-{{ $v->id }}">{{ $v->vehicle_model }}</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                                    <span style="font-family:monospace; font-weight:700; color:#8c5d1e;">{{ $v->vehicle_plate }}</span> &bull; 
                                    <span>{{ ucfirst($v->fleet_category) }} ({{ $v->seating_capacity }} Seats{{ $v->berth_capacity > 0 ? ', ' . $v->berth_capacity . ' Berths' : '' }})</span>
                                </div>
                                <div id="driver-veh-notes-{{ $v->id }}" style="font-size:10.5px; color:#64748b; margin-top:4px; font-style:italic; {{ $v->admin_notes ? '' : 'display:none;' }}">Admin Note: {{ $v->admin_notes }}</div>
                            </div>
                            <div id="driver-my-veh-badge-{{ $v->id }}">
                                @if($v->status === 'Approved')
                                    <span class="admin-badge badge-success" style="font-size:10.5px; padding:4px 8px;">✅ Approved</span>
                                @elseif($v->status === 'Pending')
                                    <span class="admin-badge badge-warning" style="font-size:10.5px; padding:4px 8px;">⏳ Pending Approval</span>
                                @else
                                    <span class="admin-badge badge-danger" style="font-size:10.5px; padding:4px 8px;">❌ Rejected</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1; text-align:center; padding:16px; color:#94a3b8; font-size:12px;">
                            No cars registered yet. Click <strong>"Register Car"</strong> to submit your vehicle for admin approval.
                        </div>
                    @endforelse
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="font-family:var(--font-display); font-size:16px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px;">hail</span>
                        <span>Available Outstation Trips for Driver Bidding</span>
                    </h3>
                    <p style="font-size:11.5px; color:#64748b; margin:2px 0 0;">Review passenger pickup (From), destination (To), highway distance (KM), and submit your conclusive ride commitment bid.</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="admin-badge badge-success" style="font-size:11px;">🟢 Live Trip Requests</span>
                </div>
            </div>

            <div class="bidding-cards-list animate-load stagger-3" id="bidding-cards-stream"></div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: DRIVER MY ASSIGNED TRIPS & OTP VERIFICATION        -->
        <!-- ======================================================== -->
        <section id="tab-driver-trips" class="tab-pane {{ $defaultTab === 'driver-trips' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'driver-trips'])

            <div class="card driver-panel-card animate-load stagger-2" style="margin-bottom:20px;">
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
                        <div id="driver-trip-actions-{{ $dt->id }}" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-top:1px dashed #e2e8f0; padding-top:12px;">
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
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'driver-wallet'])

            <div class="card driver-panel-card animate-load stagger-2" style="margin-bottom:20px;">
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
        <!-- TAB: DRIVER PROFILE & FLEET CONFIGURATION               -->
        <!-- ======================================================== -->
        <section id="tab-driver-profile" class="tab-pane {{ $defaultTab === 'driver-profile' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'driver-profile'])

            <div style="display:grid; grid-template-columns: 1fr 1.6fr; gap:18px; margin-bottom:20px;" class="driver-profile-grid">
                <!-- Chauffeur Identity & Details Card -->
                <div class="card driver-panel-card animate-load stagger-2">
                    <div class="card-header" style="padding-bottom:12px; margin-bottom:14px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <h3 class="card-title" style="font-size:15px; display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">account_circle</span>
                                <span>Chauffeur Identity & Profile</span>
                            </h3>
                            <p class="card-subtitle">Official details shared with passengers upon booking confirmation</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-gold" onclick="openDriverEditProfileModal()">
                            <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                            <span>Edit Profile</span>
                        </button>
                    </div>

                    <div style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:12px 0 18px; border-bottom:1px solid #f1f5f9;">
                        <div class="driver-avatar-display" style="font-size:46px; width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg, rgba(212,155,76,0.2) 0%, rgba(212,155,76,0.05) 100%); border:2px solid var(--primary); display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(212,155,76,0.2); cursor:pointer;" onclick="openDriverEditProfileModal()" title="Click to Change Avatar">
                            {{ $driverProfile->avatar ?? '👨🏽‍✈️' }}
                        </div>
                        <h4 class="driver-name-display" style="font-size:17px; font-weight:800; color:#0f172a; margin:10px 0 2px;">
                            {{ $driverProfile->name ?? 'Ramesh Patil' }}
                        </h4>
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">
                            <span class="admin-badge badge-success" style="font-size:10px;">VERIFIED PARTNER</span>
                            <span class="admin-badge badge-primary" style="font-size:10px;">EXP: 7+ YRS</span>
                        </div>
                        <div style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:4px;">
                            <span class="material-symbols-outlined" style="font-size:15px; color:#94a3b8;">call</span>
                            <span class="driver-phone-display" style="font-weight:600; color:#0f172a;">{{ $driverProfile->phone ?? '+91 98765 43210' }}</span>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; padding-top:14px; font-size:12.5px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #f1f5f9;">
                            <span style="color:#64748b;">Primary Vehicle</span>
                            <span style="font-weight:700; color:#0f172a;" class="driver-vehicle-display">{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #f1f5f9;">
                            <span style="color:#64748b;">Registration Plate</span>
                            <span style="font-family:monospace; font-weight:800; color:var(--primary); background:#fdf8f0; padding:2px 6px; border-radius:4px; border:1px solid rgba(212,155,76,0.3);" class="driver-plate-display">{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #f1f5f9;">
                            <span style="color:#64748b;">Fleet Category</span>
                            <span style="font-weight:700; color:#0f172a; text-transform:uppercase;" class="driver-category-display">{{ $driverProfile->fleet_category ?? 'SUV' }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #f1f5f9;">
                            <span style="color:#64748b;">Operating Hub / City</span>
                            <span style="font-weight:700; color:#0f172a;" class="driver-city-display">{{ $driverProfile->city ?? 'Pune & Mumbai Hub' }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #f1f5f9;">
                            <span style="color:#64748b;">Rating & Trips</span>
                            <span style="font-weight:700; color:#f59e0b;">★{{ $driverProfile->rating ?? '4.90' }} <span style="color:#64748b; font-weight:normal;">({{ $driverProfile->trips_count ?? 840 }} Trips)</span></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
                            <span style="color:#64748b;">Duty Status</span>
                            <button type="button" class="driver-duty-toggle-pill {{ ($driverProfile->is_online ?? true) ? 'online' : 'offline' }} driver-duty-pill-toggle" onclick="toggleDriverDutyStatus()" style="cursor:pointer; border:none;">
                                <span class="status-indicator-dot {{ ($driverProfile->is_online ?? true) ? 'bg-success' : 'bg-secondary' }} driver-duty-dot"></span>
                                <span class="driver-duty-text">{{ ($driverProfile->is_online ?? true) ? 'Duty Online' : 'Off Duty' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Registered Fleet & Vehicle Management -->
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <!-- Registered Vehicles Card -->
                    <div class="card driver-panel-card animate-load stagger-3">
                        <div class="card-header" style="flex-wrap:wrap; gap:8px;">
                            <div>
                                <h3 class="card-title" style="font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">garage</span>
                                    <span>Registered Fleet & Vehicles</span>
                                </h3>
                                <p class="card-subtitle">Manage approved cabs and list additional vehicles for verification</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openDriverListVehicleModal()">
                                <span class="material-symbols-outlined" style="font-size:14px;">add</span>
                                <span>Add New Vehicle</span>
                            </button>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:12px;" id="driver-profile-vehicles-grid">
                            @forelse($driverMyVehicles as $v)
                                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div>
                                        <div style="font-weight:700; font-size:13.5px; color:#0f172a;">{{ $v->vehicle_model }}</div>
                                        <div style="font-size:11.5px; color:#64748b; margin-top:3px;">
                                            <span style="font-family:monospace; font-weight:700; color:var(--primary);">{{ $v->vehicle_plate }}</span> &bull; 
                                            <span>{{ ucfirst($v->fleet_category) }}</span>
                                        </div>
                                        <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                            <span>{{ $v->seating_capacity }} Seats{{ $v->berth_capacity > 0 ? ', ' . $v->berth_capacity . ' Berths' : '' }}</span> &bull; 
                                            <span>{{ $v->fuel_type ?? 'Diesel' }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        @if($v->status === 'Approved')
                                            <span class="admin-badge badge-success" style="font-size:10px; padding:3px 7px;">✅ Approved</span>
                                        @elseif($v->status === 'Pending')
                                            <span class="admin-badge badge-warning" style="font-size:10px; padding:3px 7px;">⏳ Pending</span>
                                        @else
                                            <span class="admin-badge badge-danger" style="font-size:10px; padding:3px 7px;">❌ Rejected</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div style="grid-column:1 / -1; text-align:center; padding:24px 16px; color:#94a3b8; font-size:12.5px;">
                                    No cars listed yet. Click <strong>"Add New Vehicle"</strong> to register your first vehicle.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Chauffeur Documents & Guidelines Card -->
                    <div class="card driver-panel-card animate-load stagger-4">
                        <h4 style="font-size:13.5px; font-weight:800; color:#0f172a; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">verified_user</span>
                            <span>Chauffeur Standards & Compliance</span>
                        </h4>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; font-size:12px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
                                <div style="font-weight:700; color:#0f172a;">Commercial Driving License</div>
                                <div style="color:#16a34a; font-weight:600; margin-top:2px;">✓ Verified Active</div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
                                <div style="font-weight:700; color:#0f172a;">Commercial Vehicle Permit</div>
                                <div style="color:#16a34a; font-weight:600; margin-top:2px;">✓ All India Tourist Permit</div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
                                <div style="font-weight:700; color:#0f172a;">Safety & Punctuality Score</div>
                                <div style="color:var(--primary); font-weight:700; margin-top:2px;">★ 99.4% On-time Arrival</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if($userRole === 'admin')
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
                            <!-- 1. MAIN OVERVIEW -->
                            <li class="admin-nav-header">MAIN OVERVIEW</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link active" data-section="dashboard" onclick="switchAdminSection('dashboard')">
                                    <span class="material-symbols-outlined admin-nav-icon">dashboard</span>
                                    <span class="admin-nav-text">Dashboard</span>
                                    <span class="admin-nav-badge badge-gold">KPI</span>
                                </a>
                            </li>

                            <!-- 2. BOOKINGS & DISPATCH -->
                            <li class="admin-nav-header">BOOKINGS & DISPATCH</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="bookings" onclick="switchAdminSection('bookings')">
                                    <span class="material-symbols-outlined admin-nav-icon">receipt_long</span>
                                    <span class="admin-nav-text">Bookings Management</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-bookings-count">{{ count($recentBookings) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="booking-types" onclick="switchAdminSection('booking-types')">
                                    <span class="material-symbols-outlined admin-nav-icon">category</span>
                                    <span class="admin-nav-text">Booking Types & Packages</span>
                                    <span class="admin-nav-badge badge-gold">4 Types</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="workflow" onclick="switchAdminSection('workflow')">
                                    <span class="material-symbols-outlined admin-nav-icon">linear_scale</span>
                                    <span class="admin-nav-text">Trip Pipeline Stages</span>
                                    <span class="admin-nav-badge badge-info">{{ count($workflowStages) }} Stages</span>
                                </a>
                            </li>

                            <!-- 3. FLEET & CAR TYPES -->
                            <li class="admin-nav-header">FLEET & CAR TYPES</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="car-types" onclick="switchAdminSection('car-types')">
                                    <span class="material-symbols-outlined admin-nav-icon">directions_car</span>
                                    <span class="admin-nav-text">Car Types & Fleet Setup</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-car-types-count">{{ count($allCarTypes ?? []) }} Types</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="fleet" onclick="switchAdminSection('fleet')">
                                    <span class="material-symbols-outlined admin-nav-icon">local_taxi</span>
                                    <span class="admin-nav-text">Drivers & Bidding Marketplace</span>
                                    <span class="admin-nav-badge badge-success">{{ count($drivers) }} Online</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="vehicles" onclick="switchAdminSection('vehicles')">
                                    <span class="material-symbols-outlined admin-nav-icon">no_crash</span>
                                    <span class="admin-nav-text">Driver Vehicles & Approvals</span>
                                    @php $pendingVehiclesCount = $driverVehicles->where('status', 'Pending')->count(); @endphp
                                    @if($pendingVehiclesCount > 0)
                                        <span class="admin-nav-badge badge-danger" id="admin-badge-pending-vehicles">{{ $pendingVehiclesCount }} Pending</span>
                                    @else
                                        <span class="admin-nav-badge badge-success" id="admin-badge-pending-vehicles">{{ count($driverVehicles) }} Cars</span>
                                    @endif
                                </a>
                            </li>

                            <!-- 4. NETWORK & HIGHWAY ROUTES -->
                            <li class="admin-nav-header">NETWORK & ROUTES</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="cab-routes" onclick="switchAdminSection('cab-routes')">
                                    <span class="material-symbols-outlined admin-nav-icon">alt_route</span>
                                    <span class="admin-nav-text">Outstation Cab Routes</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-cab-routes-count">{{ count($allCabRoutes) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="locations" onclick="switchAdminSection('locations')">
                                    <span class="material-symbols-outlined admin-nav-icon">pin_drop</span>
                                    <span class="admin-nav-text">Service Locations & Hubs</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-locations-count">{{ count($serviceLocations) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="berth-routes" onclick="switchAdminSection('berth-routes')">
                                    <span class="material-symbols-outlined admin-nav-icon">directions_bus</span>
                                    <span class="admin-nav-text">Berth Routes & Schedules</span>
                                    <span class="admin-nav-badge badge-info" id="admin-badge-berth-routes-count">{{ count($berthRoutes) }}</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="berth-layouts" onclick="switchAdminSection('berth-layouts')">
                                    <span class="material-symbols-outlined admin-nav-icon">airline_seat_recline_extra</span>
                                    <span class="admin-nav-text">Berth & Seat Layouts</span>
                                    <span class="admin-nav-badge badge-gold" id="admin-badge-berths-count">{{ count($allSeatLayouts) }}</span>
                                </a>
                            </li>

                            <!-- 5. PRICING & FINANCIALS -->
                            <li class="admin-nav-header">PRICING & FINANCIALS</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="pricing" onclick="switchAdminSection('pricing')">
                                    <span class="material-symbols-outlined admin-nav-icon">currency_rupee</span>
                                    <span class="admin-nav-text">Fares & Surcharges</span>
                                </a>
                            </li>

                            <!-- 6. USERS & ACCESS CONTROL -->
                            <li class="admin-nav-header">USERS & ACCESS</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="users" onclick="switchAdminSection('users')">
                                    <span class="material-symbols-outlined admin-nav-icon">manage_accounts</span>
                                    <span class="admin-nav-text">Users & Drivers</span>
                                    <span class="admin-nav-badge badge-info" id="admin-badge-users-count">{{ count($allUsers) }}</span>
                                </a>
                            </li>

                            <!-- 7. SETTINGS & SYSTEM -->
                            <li class="admin-nav-header">SETTINGS & PLATFORM</li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="form-builder" onclick="switchAdminSection('form-builder')">
                                    <span class="material-symbols-outlined admin-nav-icon">dynamic_form</span>
                                    <span class="admin-nav-text">Dynamic Form Builder</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-fields-count">{{ count($dynamicFields) }}</span>
                                </a>
                            </li>
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

                        <!-- ======================================================== -->
                        <!-- 4B. BOOKING TYPES & PACKAGES MANAGEMENT (Admin Master)   -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-booking-types">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">category</span>
                                            <span>Master Booking Types, Routes & Fares Manager</span>
                                        </h3>
                                        <p class="card-subtitle">Uniform modular management for Outstation Highway Routes (One Way & Round Trip), Local Hourly Packages, Airport Transit Hubs, and City Fare Rules</p>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <span class="admin-badge badge-primary" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-routes-pill">{{ count($allCabRoutes) }} Routes</span>
                                        <span class="admin-badge badge-warning" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-packages-pill">{{ count($localPackages) }} Local Pkgs</span>
                                        <span class="admin-badge badge-gold" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-airports-pill">{{ count($airportHubs) }} Airports</span>
                                        <span class="admin-badge badge-info" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-cars-pill">{{ count($allCarTypes ?? []) }} Car Types</span>
                                    </div>
                                </div>

                                <!-- Modular Sub-Nav Tabs -->
                                <div class="admin-sub-tabs-bar">
                                    <button type="button" class="admin-sub-tab-btn active admin-bt-tab-btn" data-tab="outstation" onclick="switchBookingTypeTab('outstation')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">alt_route</span>
                                        <span>Outstation Highway Routes</span>
                                        <span class="badge-pill" id="admin-tab-count-outstation">{{ count($allCabRoutes) }}</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-bt-tab-btn" data-tab="car-types" onclick="switchBookingTypeTab('car-types')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">directions_car</span>
                                        <span>Car Types & Fleet</span>
                                        <span class="badge-pill" id="admin-tab-count-car-types">{{ count($allCarTypes ?? []) }}</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-bt-tab-btn" data-tab="local" onclick="switchBookingTypeTab('local')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">schedule</span>
                                        <span>Local Hourly Packages</span>
                                        <span class="badge-pill" id="admin-tab-count-local">{{ count($localPackages) }}</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-bt-tab-btn" data-tab="airport" onclick="switchBookingTypeTab('airport')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">flight</span>
                                        <span>Airport Transit Hubs</span>
                                        <span class="badge-pill" id="admin-tab-count-airport">{{ count($airportHubs) }}</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-bt-tab-btn" data-tab="rules" onclick="switchBookingTypeTab('rules')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">rule</span>
                                        <span>Fare Rules & Policy Matrix</span>
                                    </button>
                                </div>

                                <!-- SUB-PANEL 1: OUTSTATION CAB ROUTES (One Way & Round Trip) -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-outstation" style="display:block;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; max-width:550px;">
                                            <div class="admin-search-box" style="flex:1;">
                                                <span class="material-symbols-outlined">search</span>
                                                <input type="text" id="admin-bt-route-search-input" placeholder="Search from or to city..." oninput="handleCabRouteSearch(this.value)">
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Status:</label>
                                                <select id="admin-bt-route-filter-status" onchange="handleCabRouteFilter()">
                                                    <option value="all">All Routes</option>
                                                    <option value="active">Active Only</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-bt-routes-filter-count">{{ count($allCabRoutes) }} Routes</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddCabRouteModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Outstation Route</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact">
                                            <thead>
                                                <tr>
                                                    <th>Highway Route & Schedule</th>
                                                    <th>Distance</th>
                                                    <th>Est. Time</th>
                                                    <th>Highway Toll</th>
                                                    <th>Fare Schedule as per Car Type</th>
                                                    <th>Roundtrip Policy</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bt-routes-tbody">
                                                @forelse($allCabRoutes as $cr)
                                                <tr id="admin-bt-cab-route-row-{{ $cr->id }}" data-status="{{ $cr->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($cr->from_city . ' ' . $cr->to_city) }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                            <span>{{ $cr->from_city }}</span>
                                                            <span style="color:var(--primary);">➔</span>
                                                            <span>{{ $cr->to_city }}</span>
                                                        </div>
                                                        @if(!empty($cr->departure_schedule))
                                                            <div style="font-size:11px; color:#0284c7; font-weight:600; margin-top:2px;">
                                                                ⏱️ {{ $cr->departure_schedule }}
                                                                <span class="admin-badge badge-info" style="font-size:9px; padding:1px 5px;">{{ $cr->schedule_frequency ?: 'Daily Express' }}</span>
                                                            </div>
                                                        @else
                                                            <div style="font-size:11px; color:#64748b;">Daily Corridor &bull; On-Demand 24x7</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">
                                                            🛣️ {{ $cr->distance_km }} KM
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ {{ $cr->estimated_time }}</span>
                                                    </td>
                                                    <td>
                                                        <strong style="color:#0f172a;">₹{{ number_format($cr->base_toll) }}</strong>
                                                    </td>
                                                    <td>
                                                        @php
                                                            $hatch = $cr->hatchback_fare ?: round($cr->distance_km * 11.0 + $cr->base_toll);
                                                            $sedan = $cr->sedan_fare ?: round($cr->distance_km * 12.5 + $cr->base_toll);
                                                            $suv = $cr->suv_fare ?: round($cr->distance_km * 18.0 + $cr->base_toll);
                                                            $innova = $cr->innova_fare ?: round($cr->distance_km * 22.0 + $cr->base_toll);
                                                            $tempo = $cr->tempo_fare ?: round($cr->distance_km * 28.0 + $cr->base_toll);
                                                            $luxury = $cr->luxury_fare ?: round($cr->distance_km * 32.0 + $cr->base_toll);
                                                        @endphp
                                                        <div style="font-size:11px; line-height:1.4; color:#334155;">
                                                            <div>Hatch: <strong>₹{{ number_format($hatch) }}</strong> &bull; Sedan: <strong>₹{{ number_format($sedan) }}</strong> &bull; SUV: <strong>₹{{ number_format($suv) }}</strong></div>
                                                            <div style="color:#64748b; font-size:10.5px;">Crysta: ₹{{ number_format($innova) }} &bull; Tempo: ₹{{ number_format($tempo) }} &bull; Lux: ₹{{ number_format($luxury) }}</div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11px; color:#16a34a; font-weight:600;">
                                                            {{ $cr->roundtrip_discount_pct > 0 ? $cr->roundtrip_discount_pct . '% Return Disc.' : '250 KM/Day Min' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($cr->is_active)
                                                            <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="bt-cab-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Active</span>
                                                        @else
                                                            <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="bt-cab-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                            <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCabRouteModal({{ $cr->id }})" title="Edit Route">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                            <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCabRoute({{ $cr->id }})" title="Delete Route">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No outstation routes configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SUB-PANEL: CAR TYPES & FLEET SETUP -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-car-types" style="display:none;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; max-width:550px;">
                                            <div class="admin-search-box" style="flex:1;">
                                                <span class="material-symbols-outlined">search</span>
                                                <input type="text" id="admin-bt-car-type-search-input" placeholder="Search car type, key or models..." oninput="handleCarTypeSearch(this.value)">
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Status:</label>
                                                <select id="admin-bt-car-type-filter-status" onchange="handleCarTypeFilter()">
                                                    <option value="all">All Fleet Types</option>
                                                    <option value="active">Active Only</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-bt-car-types-filter-count">{{ count($allCarTypes ?? []) }} Car Types</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddCarTypeModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Car Type</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact">
                                            <thead>
                                                <tr>
                                                    <th>Vehicle Type & Category</th>
                                                    <th>Example Models</th>
                                                    <th>Capacity Specs</th>
                                                    <th>Rate / KM</th>
                                                    <th>Extra KM Rate</th>
                                                    <th>Min Base Fare</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bt-car-types-tbody">
                                                @forelse($allCarTypes ?? [] as $ct)
                                                <tr id="admin-bt-car-type-row-{{ $ct->id }}" data-status="{{ $ct->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($ct->name . ' ' . $ct->category_key . ' ' . $ct->models) }}">
                                                    <td>
                                                        <div style="display:flex; align-items:center; gap:10px;">
                                                            <img src="{{ $ct->image_url }}" alt="{{ $ct->name }}" style="width:48px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0;">
                                                            <div>
                                                                <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                                    <span>{{ $ct->name }}</span>
                                                                    <span class="rental-category-badge" style="font-size:9.5px; padding:2px 6px;">{{ $ct->badge }}</span>
                                                                </div>
                                                                <span style="font-family:monospace; font-size:10.5px; color:var(--primary); font-weight:700;">key: {{ $ct->category_key }}</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:12px; color:#334155; font-weight:600;">{{ $ct->models }}</span>
                                                    </td>
                                                    <td>
                                                        <div style="font-size:11.5px; color:#475569;">
                                                            <span>💺 {{ $ct->seats }}</span> &bull; <span>🧳 {{ $ct->luggage }}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong style="color:#0f172a; font-size:13px;">₹{{ number_format($ct->rate_per_km, 2) }}</strong><span style="font-size:10.5px; color:#64748b;">/km</span>
                                                    </td>
                                                    <td>
                                                        <span style="color:#334155; font-size:12px; font-weight:600;">₹{{ number_format($ct->extra_km_rate, 2) }}<span style="font-size:10.5px; color:#64748b;">/km</span></span>
                                                    </td>
                                                    <td>
                                                        <span style="color:#0f172a; font-weight:700; font-size:12px;">₹{{ number_format($ct->base_fare_min) }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge {{ $ct->is_active ? 'badge-success' : 'badge-danger' }}" style="cursor:pointer;" onclick="toggleCarTypeStatus({{ $ct->id }})" id="bt-car-type-status-badge-{{ $ct->id }}" title="Click to toggle status">
                                                            {{ $ct->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                            <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCarTypeModal({{ $ct->id }})" title="Edit Car Type">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                            <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCarType({{ $ct->id }})" title="Delete Car Type">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No car types configured yet. Click "Add Car Type" to set one up.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SUB-PANEL 2: LOCAL HOURLY RENTAL PACKAGES -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-local" style="display:none;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; max-width:550px;">
                                            <div class="admin-search-box" style="flex:1;">
                                                <span class="material-symbols-outlined">search</span>
                                                <input type="text" id="admin-local-pkg-search-input" placeholder="Search package code, name or city..." oninput="handleLocalPackageSearch(this.value)">
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Status:</label>
                                                <select id="admin-local-pkg-filter-status" onchange="handleLocalPackageFilter()">
                                                    <option value="all">All Packages</option>
                                                    <option value="active">Active Only</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-local-packages-filter-count">{{ count($localPackages) }} Packages</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddLocalPackageModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Local Package</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact">
                                            <thead>
                                                <tr>
                                                    <th>Package Code & Name</th>
                                                    <th>Applicable City</th>
                                                    <th>Duration & Distance</th>
                                                    <th>Sedan Package Rate</th>
                                                    <th>SUV Package Rate</th>
                                                    <th>Overrun Extra Charges</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-local-packages-tbody">
                                                @forelse($localPackages as $lp)
                                                <tr id="admin-local-pkg-row-{{ $lp->id }}" data-status="{{ $lp->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($lp->package_code . ' ' . $lp->name . ' ' . $lp->city) }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a;">{{ $lp->name }}</div>
                                                        <div style="font-size:11px; font-family:monospace; color:#64748b;">Code: <strong>{{ $lp->package_code }}</strong></div>
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge badge-info" style="font-size:11px;">📍 {{ $lp->city }}</span>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11.5px; font-weight:600; color:#334155;">⏱️ {{ $lp->duration_hours }} hrs &bull; 🛣️ {{ $lp->included_km }} km</span>
                                                    </td>
                                                    <td>
                                                        <strong style="color:var(--primary); font-size:13px;">₹{{ number_format($lp->sedan_fare) }}</strong>
                                                    </td>
                                                    <td>
                                                        <strong style="color:var(--primary); font-size:13px;">₹{{ number_format($lp->suv_fare) }}</strong>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11px; color:#64748b;">+₹{{ number_format($lp->extra_km_rate) }}/km &bull; +₹{{ number_format($lp->extra_hour_rate) }}/hr</span>
                                                    </td>
                                                    <td>
                                                        @if($lp->is_active)
                                                            <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleLocalPackageStatus({{ $lp->id }})" id="local-pkg-status-badge-{{ $lp->id }}" title="Click to toggle status">Active</span>
                                                        @else
                                                            <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleLocalPackageStatus({{ $lp->id }})" id="local-pkg-status-badge-{{ $lp->id }}" title="Click to toggle status">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                            <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditLocalPackageModal({{ $lp->id }})" title="Edit Package">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                            <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteLocalPackage({{ $lp->id }})" title="Delete Package">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No local rental packages configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SUB-PANEL 3: AIRPORT TRANSIT HUBS & TERMINALS -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-airport" style="display:none;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; max-width:550px;">
                                            <div class="admin-search-box" style="flex:1;">
                                                <span class="material-symbols-outlined">search</span>
                                                <input type="text" id="admin-airport-search-input" placeholder="Search airport name, IATA code, or city..." oninput="handleAirportHubSearch(this.value)">
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Status:</label>
                                                <select id="admin-airport-filter-status" onchange="handleAirportHubFilter()">
                                                    <option value="all">All Airports</option>
                                                    <option value="active">Active Only</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-airports-filter-count">{{ count($airportHubs) }} Airports</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddAirportHubModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Airport Hub</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact">
                                            <thead>
                                                <tr>
                                                    <th>Airport Name & Code</th>
                                                    <th>City & Operating Terminals</th>
                                                    <th>Pickup Surcharge</th>
                                                    <th>Drop Surcharge</th>
                                                    <th>Base City Fares (Sedan / SUV)</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-airports-tbody">
                                                @forelse($airportHubs as $ah)
                                                <tr id="admin-airport-row-{{ $ah->id }}" data-status="{{ $ah->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($ah->airport_name . ' ' . $ah->iata_code . ' ' . $ah->city) }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                            <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">flight</span>
                                                            <span>{{ $ah->airport_name }}</span>
                                                        </div>
                                                        <div style="font-size:11px; font-family:monospace; color:#64748b;">IATA: <strong style="color:var(--primary);">{{ $ah->iata_code }}</strong></div>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight:600; color:#1e293b;">📍 {{ $ah->city }}</div>
                                                        <div style="font-size:11px; color:#64748b;">{{ $ah->terminals }}</div>
                                                    </td>
                                                    <td>
                                                        <strong style="color:#0f172a;">₹{{ number_format($ah->pickup_surcharge) }}</strong>
                                                        <div style="font-size:10px; color:#64748b;">Parking + Entry</div>
                                                    </td>
                                                    <td>
                                                        <strong style="color:#0f172a;">₹{{ number_format($ah->drop_surcharge) }}</strong>
                                                        <div style="font-size:10px; color:#64748b;">Departure Toll</div>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11.5px; color:#475569;">
                                                            Sedan: <strong>₹{{ number_format($ah->sedan_base_fare) }}</strong> &bull; SUV: <strong>₹{{ number_format($ah->suv_base_fare) }}</strong>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($ah->is_active)
                                                            <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleAirportHubStatus({{ $ah->id }})" id="airport-status-badge-{{ $ah->id }}" title="Click to toggle status">Active</span>
                                                        @else
                                                            <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleAirportHubStatus({{ $ah->id }})" id="airport-status-badge-{{ $ah->id }}" title="Click to toggle status">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                            <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditAirportHubModal({{ $ah->id }})" title="Edit Airport">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                            <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteAirportHub({{ $ah->id }})" title="Delete Airport">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">No airport hubs configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SUB-PANEL 4: FARE RULES & POLICY MATRIX -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-rules" style="display:none; padding:18px 20px;">
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                                        <!-- 1. ONE WAY -->
                                        <div style="background:#ffffff; border:1px solid #cbd5e1; border-top:4px solid #0284c7; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                                <div>
                                                    <span class="admin-badge badge-primary" style="font-size:10px;">TYPE 1</span>
                                                    <h4 style="font-size:15px; font-weight:800; color:#0f172a; margin:6px 0 2px;">One Way Outstation</h4>
                                                </div>
                                                <span class="status-indicator-dot bg-success" title="Active"></span>
                                            </div>
                                            <p style="font-size:11.5px; color:#64748b; margin:6px 0 10px;">Point-to-point outstation drop. Pay only for one side travel.</p>
                                            <div style="font-size:11.5px; display:flex; flex-direction:column; gap:6px; background:#f1f5f9; padding:8px 10px; border-radius:6px;">
                                                <div><strong>Formula:</strong> Distance (KM) &times; Fleet Rate + Highway Toll</div>
                                                <div><strong>Bidding:</strong> Enabled (Driver Auction)</div>
                                                <div><strong>Min Billing:</strong> Point-to-point route matrix distance</div>
                                            </div>
                                        </div>

                                        <!-- 2. ROUND TRIP -->
                                        <div style="background:#ffffff; border:1px solid #cbd5e1; border-top:4px solid #16a34a; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                                <div>
                                                    <span class="admin-badge badge-success" style="font-size:10px;">TYPE 2</span>
                                                    <h4 style="font-size:15px; font-weight:800; color:#0f172a; margin:6px 0 2px;">Round Trip Outstation</h4>
                                                </div>
                                                <span class="status-indicator-dot bg-success" title="Active"></span>
                                            </div>
                                            <p style="font-size:11.5px; color:#64748b; margin:6px 0 10px;">Multi-day return trips with chauffeur dedicated for duration.</p>
                                            <div style="font-size:11.5px; display:flex; flex-direction:column; gap:6px; background:#f1f5f9; padding:8px 10px; border-radius:6px;">
                                                <div><strong>Min Guarantee:</strong> 250 KM / Calendar Day</div>
                                                <div><strong>Night Halt Fee:</strong> ₹400 / night (11 PM - 6 AM)</div>
                                                <div><strong>Return Date:</strong> Enforced post-pickup</div>
                                            </div>
                                        </div>

                                        <!-- 3. LOCAL RENTAL -->
                                        <div style="background:#ffffff; border:1px solid #cbd5e1; border-top:4px solid #d97706; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                                <div>
                                                    <span class="admin-badge badge-warning" style="font-size:10px; background:#fef3c7; color:#92400e;">TYPE 3</span>
                                                    <h4 style="font-size:15px; font-weight:800; color:#0f172a; margin:6px 0 2px;">Local Hourly Rentals</h4>
                                                </div>
                                                <span class="status-indicator-dot bg-success" title="Active"></span>
                                            </div>
                                            <p style="font-size:11.5px; color:#64748b; margin:6px 0 10px;">Intra-city hourly rental packages for multi-stop city travel.</p>
                                            <div style="font-size:11.5px; display:flex; flex-direction:column; gap:6px; background:#f1f5f9; padding:8px 10px; border-radius:6px;">
                                                <div><strong>Packages:</strong> 4h/40km, 8h/80km, 10h/100km, 12h/120km</div>
                                                <div><strong>Extra KM Rate:</strong> ₹14.00 / km</div>
                                                <div><strong>Extra Hour Rate:</strong> ₹150.00 / hour</div>
                                            </div>
                                        </div>

                                        <!-- 4. AIRPORT TRANSFERS -->
                                        <div style="background:#ffffff; border:1px solid #cbd5e1; border-top:4px solid #8b5cf6; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                                <div>
                                                    <span class="admin-badge badge-gold" style="font-size:10px;">TYPE 4</span>
                                                    <h4 style="font-size:15px; font-weight:800; color:#0f172a; margin:6px 0 2px;">Airport Transit</h4>
                                                </div>
                                                <span class="status-indicator-dot bg-success" title="Active"></span>
                                            </div>
                                            <p style="font-size:11.5px; color:#64748b; margin:6px 0 10px;">Airport Pickup & Drop with flight number tracking option.</p>
                                            <div style="font-size:11.5px; display:flex; flex-direction:column; gap:6px; background:#f1f5f9; padding:8px 10px; border-radius:6px;">
                                                <div><strong>Modes:</strong> Pickup from Airport / Drop to Airport</div>
                                                <div><strong>Terminal Surcharge:</strong> ₹250 flat (parking/entry)</div>
                                                <div><strong>Flight Delay Buffer:</strong> 45 minutes free wait</div>
                                            </div>
                                        </div>
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

                            <!-- Platform Legal & Policy URLs Settings -->
                            <div class="card" style="margin-top:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">policy</span>
                                            <span>Legal Agreements & Policy URLs Configuration</span>
                                        </h3>
                                        <p class="card-subtitle">Set the destination URLs for the Login Pop-up notice: "By proceeding, you agree to Privacy Policy and T&Cs"</p>
                                    </div>
                                    <span class="admin-badge badge-gold">Auth Compliance</span>
                                </div>

                                <form id="admin-policy-urls-form" onsubmit="saveAdminPolicyUrls(event)" style="padding:16px 20px;">
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-privacy-url">
                                                <span style="font-weight:700;">Privacy Policy URL</span>
                                                <span style="font-size:11px; color:#64748b;">(Internal route or external link)</span>
                                            </label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">verified_user</span>
                                                <input type="text" id="setting-privacy-url" class="form-control" value="{{ $platformSettings['privacy_policy_url'] ?? '/privacy-policy' }}" required placeholder="/privacy-policy or https://..." oninput="updatePolicyPreview()">
                                            </div>
                                        </div>

                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-terms-url">
                                                <span style="font-weight:700;">Terms & Conditions (T&Cs) URL</span>
                                                <span style="font-size:11px; color:#64748b;">(Internal route or external link)</span>
                                            </label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">description</span>
                                                <input type="text" id="setting-terms-url" class="form-control" value="{{ $platformSettings['terms_conditions_url'] ?? '/terms-conditions' }}" required placeholder="/terms-conditions or https://..." oninput="updatePolicyPreview()">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Live Preview & Save Row -->
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                        <div>
                                            <div style="font-size:10.5px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">Login Pop-up Notice Preview:</div>
                                            <div style="font-size:12.5px; color:#334155; margin-top:2px;">
                                                By proceeding, you agree to 
                                                <a href="{{ $platformSettings['privacy_policy_url'] ?? '/privacy-policy' }}" id="preview-link-privacy" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">Privacy Policy</a> 
                                                and 
                                                <a href="{{ $platformSettings['terms_conditions_url'] ?? '/terms-conditions' }}" id="preview-link-terms" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">T&Cs</a>.
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Policy URLs</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 5. SERVICE LOCATIONS SETUP (Admin)                       -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-locations">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">pin_drop</span>
                                            <span>Service Locations & Network Hubs</span>
                                        </h3>
                                        <p class="card-subtitle">Setup operational cities, GPS latitude/longitude, and airport transit service coverage</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddLocationModal()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        <span>Add Location</span>
                                    </button>
                                </div>

                                <div class="admin-bookings-toolbar" style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center;">
                                    <div class="admin-search-box" style="flex:1; max-width:320px;">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-location-search-input" placeholder="Search city, state, airport code..." oninput="handleLocationSearch(this.value)">
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-locations-table">
                                        <thead>
                                            <tr>
                                                <th>City / Hub</th>
                                                <th>State</th>
                                                <th>GPS Coordinates</th>
                                                <th>Airport Tag</th>
                                                <th>Coverage Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-locations-tbody">
                                            @forelse($serviceLocations as $loc)
                                            <tr id="admin-loc-row-{{ $loc->id }}" data-search="{{ strtolower($loc->name . ' ' . $loc->state . ' ' . ($loc->airport_code ?: '')) }}">
                                                <td>
                                                    <strong>{{ $loc->name }}</strong>
                                                </td>
                                                <td>{{ $loc->state }}</td>
                                                <td style="font-family:monospace; font-size:11.5px; color:#475569;">
                                                    {{ $loc->latitude }}, {{ $loc->longitude }}
                                                </td>
                                                <td>
                                                    @if($loc->airport_code)
                                                        <span class="admin-badge badge-primary">{{ $loc->airport_code }}</span>
                                                    @else
                                                        <span style="color:#94a3b8; font-size:11px;">Standard</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($loc->is_active)
                                                        <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleLocationStatus({{ $loc->id }})" title="Click to toggle">Active</span>
                                                    @else
                                                        <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleLocationStatus({{ $loc->id }})" title="Click to toggle">Inactive</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditLocationModal({{ $loc->id }})" title="Edit Location">
                                                             <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteLocation({{ $loc->id }})" title="Delete Location">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#94a3b8;">No service locations configured yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 5B. OUTSTATION CAB ROUTES SETUP (Admin)                   -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-cab-routes">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">alt_route</span>
                                            <span>Outstation Cab Routes & Highway Matrix</span>
                                        </h3>
                                        <p class="card-subtitle">Manage inter-city highway distance (KM), travel duration estimates, base highway tolls, and active booking availability</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddCabRouteModal()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        <span>Add Cab Route</span>
                                    </button>
                                </div>

                                <div class="admin-bookings-toolbar" style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                    <div class="admin-search-box" style="flex:1; max-width:320px;">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-cab-route-search-input" placeholder="Search from or to city..." oninput="handleCabRouteSearch(this.value)">
                                    </div>
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label>Status:</label>
                                            <select id="admin-cab-route-filter-status" onchange="handleCabRouteFilter()">
                                                <option value="all">All Routes</option>
                                                <option value="active">Active Only</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <span class="admin-count-pill" id="admin-cab-routes-filter-count">{{ count($allCabRoutes) }} Routes</span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-cab-routes-table">
                                        <thead>
                                            <tr>
                                                <th>Route & Schedule</th>
                                                <th>Highway Distance</th>
                                                <th>Est. Travel Time</th>
                                                <th>Base Highway Toll</th>
                                                <th>Car Type Fare Schedule</th>
                                                <th>Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-cab-routes-tbody">
                                            @forelse($allCabRoutes as $cr)
                                            <tr id="admin-cab-route-row-{{ $cr->id }}" data-status="{{ $cr->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($cr->from_city . ' ' . $cr->to_city) }}">
                                                <td>
                                                    <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                        <span>{{ $cr->from_city }}</span>
                                                        <span style="color:var(--primary);">➔</span>
                                                        <span>{{ $cr->to_city }}</span>
                                                    </div>
                                                    @if(!empty($cr->departure_schedule))
                                                        <div style="font-size:11px; color:#0284c7; font-weight:600; margin-top:2px;">
                                                            ⏱️ {{ $cr->departure_schedule }}
                                                            <span class="admin-badge badge-info" style="font-size:9px; padding:1px 5px;">{{ $cr->schedule_frequency ?: 'Daily Express' }}</span>
                                                        </div>
                                                    @else
                                                        <div style="font-size:11px; color:#64748b;">Daily Corridor &bull; On-Demand 24x7</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-primary" style="font-family:monospace; font-size:12px;">
                                                        🛣️ {{ $cr->distance_km }} KM
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style="font-size:12px; font-weight:600; color:#334155;">⏱️ {{ $cr->estimated_time }}</span>
                                                </td>
                                                <td>
                                                    <strong style="color:#0f172a;">₹{{ number_format($cr->base_toll) }}</strong>
                                                    @if($cr->roundtrip_discount_pct > 0)
                                                        <div style="font-size:10.5px; color:#16a34a; font-weight:600;">{{ $cr->roundtrip_discount_pct }}% RT Disc.</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $hatch = $cr->hatchback_fare ?: round($cr->distance_km * 11.0 + $cr->base_toll);
                                                        $sedan = $cr->sedan_fare ?: round($cr->distance_km * 12.5 + $cr->base_toll);
                                                        $suv = $cr->suv_fare ?: round($cr->distance_km * 18.0 + $cr->base_toll);
                                                        $innova = $cr->innova_fare ?: round($cr->distance_km * 22.0 + $cr->base_toll);
                                                        $tempo = $cr->tempo_fare ?: round($cr->distance_km * 28.0 + $cr->base_toll);
                                                        $luxury = $cr->luxury_fare ?: round($cr->distance_km * 32.0 + $cr->base_toll);
                                                    @endphp
                                                    <div style="font-size:11px; line-height:1.4; color:#334155;">
                                                        <div>Hatch: <strong>₹{{ number_format($hatch) }}</strong> &bull; Sedan: <strong>₹{{ number_format($sedan) }}</strong> &bull; SUV: <strong>₹{{ number_format($suv) }}</strong></div>
                                                        <div style="color:#64748b; font-size:10.5px;">Crysta: ₹{{ number_format($innova) }} &bull; Tempo: ₹{{ number_format($tempo) }} &bull; Lux: ₹{{ number_format($luxury) }}</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($cr->is_active)
                                                        <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="cab-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Active</span>
                                                    @else
                                                        <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="cab-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Inactive</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCabRouteModal({{ $cr->id }})" title="Edit Route">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCabRoute({{ $cr->id }})" title="Delete Route">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">No outstation cab routes configured yet. Click "Add Cab Route" to set one up.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 6. BERTH ROUTES SETUP (Admin)                            -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-berth-routes">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">directions_bus</span>
                                            <span>Berth Routes & Sleeper Schedules</span>
                                        </h3>
                                        <p class="card-subtitle">Setup routes for sleeper buses and berth-fitted passenger vehicles with timings and base fares</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddBerthRouteModal()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        <span>Add Berth Route</span>
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-berth-routes-table">
                                        <thead>
                                            <tr>
                                                <th>Route</th>
                                                <th>Coach Code</th>
                                                <th>Operator & Vehicle Type</th>
                                                <th>Departure & Arrival</th>
                                                <th>Base Fare</th>
                                                <th>Berths</th>
                                                <th>Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-berth-routes-tbody">
                                            @forelse($berthRoutes as $br)
                                            <tr id="admin-broute-row-{{ $br->id }}">
                                                <td>
                                                    <strong>{{ $br->from_city }} → {{ $br->to_city }}</strong>
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-gold" style="font-family:monospace;">{{ $br->coach_code }}</span>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600; color:#0f172a;">{{ $br->operator_name }}</div>
                                                    <div style="font-size:11px; color:#64748b;">{{ $br->vehicle_type }}</div>
                                                </td>
                                                <td>
                                                    <div style="font-size:12px; font-weight:600; color:#0f172a;">{{ $br->departure_time }}</div>
                                                    <div style="font-size:11px; color:#64748b;">Arr: {{ $br->arrival_time }}</div>
                                                </td>
                                                <td style="font-weight:700; color:#16a34a;">₹{{ number_format($br->base_price) }}</td>
                                                <td>{{ $br->total_berths }} Berths</td>
                                                <td>
                                                    @if($br->is_active)
                                                        <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleBerthRouteStatus({{ $br->id }})" title="Click to toggle">Active</span>
                                                    @else
                                                        <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleBerthRouteStatus({{ $br->id }})" title="Click to toggle">Inactive</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                        <button type="button" class="btn btn-xs btn-outline-gold" onclick="jumpToBerthLayout('{{ $br->coach_code }}')" title="Customise Berths">
                                                            <span class="material-symbols-outlined" style="font-size:13px;">airline_seat_recline_extra</span>
                                                            <span>Berths</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditBerthRouteModal({{ $br->id }})" title="Edit Route">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteBerthRoute({{ $br->id }})" title="Delete Route">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No berth routes configured yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 7. VEHICLE BERTH & SEAT LAYOUT CUSTOMIZER (Admin)        -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-berth-layouts">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">airline_seat_recline_extra</span>
                                            <span>Vehicle Berth & Seat Customizer</span>
                                        </h3>
                                        <p class="card-subtitle">Each vehicle or coach can have completely distinct berth mappings, lower/upper decks, and pricing</p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddBerthModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Custom Berth</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Vehicle / Coach Code Selector Toolbar -->
                                <div class="admin-bookings-toolbar" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:12px 16px;">
                                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                        <label style="font-size:12.5px; font-weight:700; color:#334155;">Select Vehicle / Coach Layout:</label>
                                        <select id="admin-berth-coach-filter" class="form-control" style="width:260px; height:38px; font-size:12.5px;" onchange="filterBerthsByCoach(this.value)">
                                            <option value="all">All Vehicles & Coaches</option>
                                            @php
                                                $coaches = $allSeatLayouts->pluck('coach_code')->unique();
                                            @endphp
                                            @foreach($coaches as $cc)
                                                <option value="{{ $cc }}">Coach / Vehicle #{{ $cc }}</option>
                                            @endforeach
                                        </select>
                                        <span style="font-size:11.5px; color:#64748b;">Showing distinct berths & deck configurations</span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-berths-table">
                                        <thead>
                                            <tr>
                                                <th>Coach / Car</th>
                                                <th>Berth No.</th>
                                                <th>Deck Level</th>
                                                <th>Berth Type</th>
                                                <th>Price</th>
                                                <th>Ladies Quota</th>
                                                <th>Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-berths-tbody">
                                            @forelse($allSeatLayouts as $sb)
                                            <tr class="admin-berth-row" data-coach="{{ $sb->coach_code }}" id="admin-berth-row-{{ $sb->id }}">
                                                <td>
                                                    <span class="admin-badge badge-gold" style="font-family:monospace;">{{ $sb->coach_code }}</span>
                                                    <span style="font-size:11px; color:#64748b; margin-left:4px;">{{ $sb->operator_name }}</span>
                                                </td>
                                                <td>
                                                    <strong style="font-size:13px; color:#0f172a;">{{ $sb->berth_number }}</strong>
                                                </td>
                                                <td>
                                                    @if($sb->deck === 'lower')
                                                        <span class="admin-badge badge-primary">Lower Deck</span>
                                                    @elseif($sb->deck === 'upper')
                                                        <span class="admin-badge badge-info">Upper Deck</span>
                                                    @else
                                                        <span class="admin-badge badge-gold">Single Cabin</span>
                                                    @endif
                                                </td>
                                                <td>{{ ucfirst($sb->berth_type) }}</td>
                                                <td style="font-weight:700; color:#16a34a;">₹{{ number_format($sb->price) }}</td>
                                                <td>
                                                    @if($sb->is_ladies_quota)
                                                        <span class="admin-badge" style="background:#fdf2f8; color:#db2777; border:1px solid #fbcfe8;">Ladies Quota</span>
                                                    @else
                                                        <span style="color:#94a3b8; font-size:11.5px;">General</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($sb->is_booked)
                                                        <span class="admin-badge badge-danger">Booked</span>
                                                    @else
                                                        <span class="admin-badge badge-success">Available</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditBerthModal({{ $sb->id }})" title="Edit Berth">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteBerth({{ $sb->id }})" title="Delete Berth">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No vehicle berths configured yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 7B. CAR TYPES & FLEET CATEGORIES SETUP (Admin Master)     -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-car-types">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px; vertical-align:middle;">directions_car</span>
                                            <span>Car Types & Fleet Categories Setup</span>
                                        </h3>
                                        <p class="card-subtitle">Add and configure vehicle classes, per-KM fares, extra KM rates, seating & luggage capacities, and bookability across all trip modules</p>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <span class="admin-badge badge-primary" style="font-size:11px; padding:4px 8px;">{{ count($allCarTypes ?? []) }} Total Fleet Types</span>
                                        <span class="admin-badge badge-success" style="font-size:11px; padding:4px 8px;">{{ ($allCarTypes ?? collect())->where('is_active', true)->count() }} Active</span>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddCarTypeModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Car Type</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="admin-bookings-toolbar" style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                    <div class="admin-search-box" style="flex:1; max-width:320px;">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-car-type-search-input" placeholder="Search car type, category key, models..." oninput="handleCarTypeSearch(this.value)">
                                    </div>
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label>Status:</label>
                                            <select id="admin-car-type-filter-status" onchange="handleCarTypeFilter()">
                                                <option value="all">All Statuses</option>
                                                <option value="active">Active Only</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <span class="admin-count-pill" id="admin-car-types-filter-count">{{ count($allCarTypes ?? []) }} Car Types</span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-car-types-table">
                                        <thead>
                                            <tr>
                                                <th>Vehicle Preview & Category</th>
                                                <th>Example Car Models</th>
                                                <th>Capacity (Seats & Luggage)</th>
                                                <th>Rate / KM</th>
                                                <th>Extra KM Rate</th>
                                                <th>Minimum Base Fare</th>
                                                <th>Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-car-types-tbody">
                                            @forelse($allCarTypes ?? [] as $ct)
                                            <tr id="admin-car-type-row-{{ $ct->id }}" data-status="{{ $ct->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($ct->name . ' ' . $ct->category_key . ' ' . $ct->models) }}">
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <img src="{{ $ct->image_url }}" alt="{{ $ct->name }}" style="width:52px; height:34px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;">
                                                        <div>
                                                            <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                                <span>{{ $ct->name }}</span>
                                                                <span class="rental-category-badge" style="font-size:9.5px; padding:2px 6px;">{{ $ct->badge }}</span>
                                                            </div>
                                                            <span style="font-family:monospace; font-size:11px; color:var(--primary); font-weight:700;">key: {{ $ct->category_key }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-size:12px; color:#334155; font-weight:600;">{{ $ct->models }}</span>
                                                </td>
                                                <td>
                                                    <div style="font-size:11.5px; color:#475569;">
                                                        <span>💺 {{ $ct->seats }}</span> &bull; <span>🧳 {{ $ct->luggage }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong style="color:#0f172a; font-size:13px;">₹{{ number_format($ct->rate_per_km, 2) }}</strong><span style="font-size:10.5px; color:#64748b;">/km</span>
                                                </td>
                                                <td>
                                                    <span style="color:#334155; font-size:12px; font-weight:600;">₹{{ number_format($ct->extra_km_rate, 2) }}<span style="font-size:10.5px; color:#64748b;">/km</span></span>
                                                </td>
                                                <td>
                                                    <span style="color:#0f172a; font-weight:700; font-size:12.5px;">₹{{ number_format($ct->base_fare_min) }}</span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge {{ $ct->is_active ? 'badge-success' : 'badge-danger' }}" style="cursor:pointer;" onclick="toggleCarTypeStatus({{ $ct->id }})" id="car-type-status-badge-{{ $ct->id }}" title="Click to toggle status">
                                                        {{ $ct->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCarTypeModal({{ $ct->id }})" title="Edit Car Type">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCarType({{ $ct->id }})" title="Delete Car Type">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No car types configured yet. Click "Add Car Type" to set one up.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 8. DRIVER VEHICLES & FLEET APPROVALS (Admin)             -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-vehicles">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">no_crash</span>
                                            <span>Driver Vehicles & Fleet Approvals</span>
                                        </h3>
                                        <p class="card-subtitle">Review driver vehicle submissions, verify RC permits, approve/reject fleet cars, and edit details</p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <button type="button" class="btn btn-sm btn-outline" onclick="reloadAdminVehicles()">
                                            <span class="material-symbols-outlined" style="font-size:14px;">refresh</span>
                                            <span>Refresh</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="admin-bookings-toolbar">
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label>Filter Status:</label>
                                            <select id="admin-vehicle-filter-status" onchange="filterAdminVehicles(this.value)">
                                                <option value="all">All Vehicles ({{ count($driverVehicles) }})</option>
                                                <option value="Pending">Pending Approval ({{ $driverVehicles->where('status', 'Pending')->count() }})</option>
                                                <option value="Approved">Approved & Active ({{ $driverVehicles->where('status', 'Approved')->count() }})</option>
                                                <option value="Rejected">Rejected ({{ $driverVehicles->where('status', 'Rejected')->count() }})</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="admin-table-compact" id="admin-vehicles-table">
                                        <thead>
                                            <tr>
                                                <th>Driver Details</th>
                                                <th>Vehicle Model & Year</th>
                                                <th>Plate Number</th>
                                                <th>Category</th>
                                                <th>Capacity</th>
                                                <th>RC Number</th>
                                                <th>Approval Status</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-vehicles-tbody">
                                            @forelse($driverVehicles as $veh)
                                            <tr class="admin-veh-row" data-status="{{ $veh->status }}" id="admin-veh-row-{{ $veh->id }}">
                                                <td>
                                                    <div style="font-weight:700; color:#0f172a;">{{ $veh->driver_name }}</div>
                                                    <div style="font-size:11px; color:#64748b;">{{ $veh->driver_phone }}</div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600; color:#0f172a;">{{ $veh->vehicle_model }}</div>
                                                    <div style="font-size:11px; color:#64748b;">Year: {{ $veh->model_year }} &bull; Fuel: {{ $veh->fuel_type }}</div>
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-gold" style="font-family:monospace; font-size:11.5px;">{{ $veh->vehicle_plate }}</span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge badge-primary">{{ strtoupper($veh->fleet_category) }}</span>
                                                </td>
                                                <td>
                                                    <div>{{ $veh->seating_capacity }} Seats</div>
                                                    @if($veh->berth_capacity > 0)
                                                        <div style="font-size:10.5px; color:#16a34a; font-weight:700;">{{ $veh->berth_capacity }} Berths Fitted</div>
                                                    @endif
                                                </td>
                                                <td style="font-family:monospace; font-size:11px;" id="admin-veh-rc-{{ $veh->id }}">{{ $veh->rc_number ?: 'Pending Upload' }}</td>
                                                <td id="admin-veh-status-cell-{{ $veh->id }}">
                                                    @if($veh->status === 'Approved')
                                                        <span class="admin-badge badge-success">Approved</span>
                                                    @elseif($veh->status === 'Pending')
                                                        <span class="admin-badge badge-warning">Pending Approval</span>
                                                    @else
                                                        <span class="admin-badge badge-danger">Rejected</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end;" id="admin-veh-actions-{{ $veh->id }}">
                                                        @if($veh->status === 'Pending')
                                                        <button type="button" class="btn btn-xs btn-primary btn-veh-approve-{{ $veh->id }}" onclick="approveDriverVehicle({{ $veh->id }})" title="Approve Vehicle">
                                                            <span class="material-symbols-outlined" style="font-size:13px;">check</span>
                                                            <span>Approve</span>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline btn-veh-reject-{{ $veh->id }}" style="color:#ef4444; border-color:#fca5a5;" onclick="rejectDriverVehicle({{ $veh->id }})" title="Reject Vehicle">
                                                            <span class="material-symbols-outlined" style="font-size:13px;">close</span>
                                                            <span>Reject</span>
                                                        </button>
                                                        @endif
                                                        <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditVehicleModal({{ $veh->id }})" title="Edit Vehicle">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteVehicle({{ $veh->id }})" title="Delete Vehicle">
                                                            <span class="material-symbols-outlined">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No driver vehicles listed yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </section>
        @endif

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

@if($userRole === 'driver' || $userRole === 'admin')
<!-- ======================================================== -->
<!-- MODAL: CONCLUSIVE DRIVER BID & COMMITMENT POPUP          -->
<!-- ======================================================== -->
<div class="modal-overlay" id="driver-bid-modal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header" style="background:#0b0f19; color:#f8fafc; border-bottom:1px solid #1e293b;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:8px; background:rgba(212,155,76,0.15); border:1px solid #d49b4c; display:flex; align-items:center; justify-content:center; color:#d49b4c;">
                    <span class="material-symbols-outlined" style="font-size:22px;">gavel</span>
                </div>
                <div>
                    <h3 class="modal-title" style="color:#ffffff; font-size:16px;">Chauffeur Ride Commitment & Bidding</h3>
                    <p style="font-size:11px; color:#94a3b8; margin:0;">Conclusive trip agreement & binding fare proposal</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" style="color:#cbd5e1;" onclick="closeModal('driver-bid-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:18px 20px;">
            <!-- Trip Route & Distance Details Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span class="admin-badge badge-primary" id="driver-bid-trip-type" style="font-size:10.5px;">Outstation Trip</span>
                    <span style="font-size:11.5px; color:#64748b; font-weight:600;" id="driver-bid-trip-schedule">Scheduled Departure</span>
                </div>
                
                <!-- From & To Route Display -->
                <div style="display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center; margin-bottom:12px;">
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                        <div style="font-size:10px; font-weight:700; color:#0284c7; text-transform:uppercase; letter-spacing:0.04em;">From (Pickup Location)</div>
                        <div style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;" id="driver-bid-from-loc">Pickup Location</div>
                    </div>
                    <div style="text-align:center; color:var(--primary); font-weight:800; font-size:20px;">➔</div>
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                        <div style="font-size:10px; font-weight:700; color:#16a34a; text-transform:uppercase; letter-spacing:0.04em;">To (Drop Location)</div>
                        <div style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;" id="driver-bid-to-loc">Drop Location</div>
                    </div>
                </div>

                <!-- Distance, Time & Passenger Budget Row -->
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:12.5px; border-top:1px dashed #cbd5e1; padding-top:10px;">
                    <div>
                        <span style="color:#64748b;">Distance:</span>
                        <strong style="color:#0f172a; margin-left:4px; font-family:monospace; font-size:14px;" id="driver-bid-distance-km">450 km</strong>
                        <span style="color:#94a3b8; margin:0 6px;">&bull;</span>
                        <span style="color:#64748b;">Est. Time:</span>
                        <strong style="color:#0f172a; margin-left:4px;" id="driver-bid-est-time">9h 15m</strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Passenger Budget:</span>
                        <strong style="color:#16a34a; margin-left:4px; font-size:14px;" id="driver-bid-customer-budget">₹4,800</strong>
                    </div>
                </div>
            </div>

            <!-- Driver Fare Input -->
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Your Proposed Ride Price (₹)</span>
                    <span style="font-size:11px; color:#64748b;">Enter final all-inclusive trip fare</span>
                </label>
                <div class="input-with-icon">
                    <span class="material-symbols-outlined" style="color:var(--primary);">currency_rupee</span>
                    <input type="number" id="driver-bid-amount-input" class="form-control" style="font-size:20px; font-weight:800; color:#0f172a; height:46px;" oninput="updateConclusiveWriteup()" min="100" step="50">
                </div>
                <!-- Quick Amount Adjusters -->
                <div style="display:flex; gap:6px; margin-top:8px;">
                    <button type="button" class="btn btn-xs btn-outline" onclick="adjustDriverBidPrice(-100)">- ₹100</button>
                    <button type="button" class="btn btn-xs btn-outline-gold" onclick="resetDriverBidToTarget()">Match Budget</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="adjustDriverBidPrice(200)">+ ₹200</button>
                    <button type="button" class="btn btn-xs btn-outline" onclick="adjustDriverBidPrice(500)">+ ₹500</button>
                </div>
            </div>

            <!-- Mandatory Conclusive Write-up & Acceptance Box -->
            <div style="background:#fffbeb; border:1.5px solid #fde68a; border-radius:8px; padding:14px; margin-top:14px;">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                    <span class="material-symbols-outlined" style="color:#d97706; font-size:18px;">verified</span>
                    <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#92400e; letter-spacing:0.05em;">Conclusive Ride Commitment Statement</span>
                </div>
                <div id="driver-bid-conclusive-statement" style="font-size:13.5px; font-weight:600; color:#1e293b; line-height:1.55; padding:6px 0;">
                    "I will take and do this ride from <span id="writeup-from" style="color:#0284c7;">Pickup</span> to <span id="writeup-to" style="color:#16a34a;">Drop</span> (<span id="writeup-km" style="font-family:monospace;">0 km</span>) at this price: <span id="writeup-price" style="color:#b45309; font-weight:800; font-size:15px;">₹0</span>. I accept this."
                </div>
                <div style="margin-top:10px; display:flex; align-items:flex-start; gap:8px; border-top:1px dashed #fcd34d; padding-top:8px;">
                    <input type="checkbox" id="driver-bid-accept-checkbox" checked style="width:17px; height:17px; margin-top:2px; accent-color:#d97706; cursor:pointer;" onchange="toggleBidSubmitButton(this.checked)">
                    <label for="driver-bid-accept-checkbox" style="font-size:11.5px; color:#475569; font-weight:600; cursor:pointer; line-height:1.4;">
                        I accept this trip and agree to report on schedule with my verified vehicle at the agreed fare.
                    </label>
                </div>
            </div>

            <!-- Privacy Protection Notice -->
            <div style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; margin-top:10px; font-size:11px; color:#475569; display:flex; align-items:center; gap:6px;">
                <span class="material-symbols-outlined" style="font-size:16px; color:#64748b;">lock</span>
                <span><strong>Security & Privacy:</strong> Passenger phone number and exact doorstep pickup address remain masked until your bid is accepted and the ride is allotted to you.</span>
            </div>
        </div>
        <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('driver-bid-modal')">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-submit-conclusive-bid" onclick="submitConclusiveDriverBid()">
                <span>✓ I Accept & Confirm This Bid (<span id="btn-bid-amount-preview">₹0</span>)</span>
            </button>
        </div>
    </div>
</div>
@endif

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

@if($userRole === 'admin')
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
                            <option value="">Select City</option>
                            @foreach($serviceLocations as $loc)
                                @if($loc->is_active)
                                    <option value="{{ $loc->name }}">{{ $loc->name }}, {{ $loc->state }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-to-city">To City *</label>
                        <select id="add-to-city" class="form-control" required onchange="autoSuggestAddFares()">
                            <option value="">Select City</option>
                            @foreach($serviceLocations as $loc)
                                @if($loc->is_active)
                                    <option value="{{ $loc->name }}">{{ $loc->name }}, {{ $loc->state }}</option>
                                @endif
                            @endforeach
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
@endif

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

@if($userRole === 'admin')
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

<!-- ======================================================== -->
<!-- MODAL: ADD / EDIT SERVICE LOCATION (Admin)               -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-location-modal">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">pin_drop</span>
                <span id="admin-location-modal-title">Service Location Setup</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-location-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-location-form" onsubmit="saveAdminLocation(event)">
                <input type="hidden" id="admin-loc-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-loc-name">City / Hub Name *</label>
                        <input type="text" id="admin-loc-name" class="form-control" placeholder="e.g. Pune, Goa, Mumbai" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-loc-state">State / Region *</label>
                        <input type="text" id="admin-loc-state" class="form-control" placeholder="e.g. Maharashtra" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-loc-lat">Latitude *</label>
                        <input type="number" step="0.0000001" id="admin-loc-lat" class="form-control" placeholder="18.5204" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-loc-lng">Longitude *</label>
                        <input type="number" step="0.0000001" id="admin-loc-lng" class="form-control" placeholder="73.8567" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="admin-loc-airport">Airport Code (Optional)</label>
                    <input type="text" id="admin-loc-airport" class="form-control" placeholder="e.g. PNQ, BOM, GOI" style="text-transform:uppercase;">
                </div>

                <div style="display:flex; gap:18px; margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-loc-has-airport" checked>
                        <span>Airport Taxi Drop Available</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-loc-active" checked>
                        <span>Active for Customer Booking</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-location-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                        <span>Save Location</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD / EDIT BERTH ROUTE (Admin)                    -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-berth-route-modal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">directions_bus</span>
                <span id="admin-broute-modal-title">Berth Route & Sleeper Schedule</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-berth-route-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-berth-route-form" onsubmit="saveAdminBerthRoute(event)">
                <input type="hidden" id="admin-broute-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-from">Origin City *</label>
                        <input type="text" id="admin-broute-from" class="form-control" placeholder="e.g. Pune" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-to">Destination City *</label>
                        <input type="text" id="admin-broute-to" class="form-control" placeholder="e.g. Goa" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-operator">Transit Operator *</label>
                        <input type="text" id="admin-broute-operator" class="form-control" placeholder="e.g. Intercity Express" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-coach">Coach / Car Code *</label>
                        <input type="text" id="admin-broute-coach" class="form-control" placeholder="e.g. 8092, 5510" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-broute-vehicle">Vehicle / Coach Model *</label>
                    <input type="text" id="admin-broute-vehicle" class="form-control" placeholder="e.g. Volvo Multi-Axle AC Sleeper, Force Urbania 7-Berth Van" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-dept">Departure Time *</label>
                        <input type="text" id="admin-broute-dept" class="form-control" placeholder="e.g. 09:30 PM" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-arr">Arrival Time *</label>
                        <input type="text" id="admin-broute-arr" class="form-control" placeholder="e.g. 06:45 AM" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-price">Base Berth Fare (₹) *</label>
                        <input type="number" id="admin-broute-price" class="form-control" placeholder="850" required min="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-broute-total-berths">Total Berths *</label>
                        <input type="number" id="admin-broute-total-berths" class="form-control" placeholder="30" required min="1">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-broute-active" checked>
                        <span>Active for Passenger Booking</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-berth-route-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                        <span>Save Berth Route</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD / EDIT VEHICLE BERTH (Admin)                  -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-berth-modal">
    <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">airline_seat_recline_extra</span>
                <span id="admin-berth-modal-title">Customise Vehicle Berth</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-berth-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-berth-form" onsubmit="saveAdminBerth(event)">
                <input type="hidden" id="admin-berth-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-coach">Vehicle / Coach Code *</label>
                        <input type="text" id="admin-berth-coach" class="form-control" placeholder="e.g. 8092, 5510, 9104" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-operator">Operator Name *</label>
                        <input type="text" id="admin-berth-operator" class="form-control" placeholder="e.g. Intercity Express" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-num">Berth Number / Code *</label>
                        <input type="text" id="admin-berth-num" class="form-control" placeholder="e.g. L1, U2, B3, S4" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-deck">Deck Level *</label>
                        <select id="admin-berth-deck" class="form-control">
                            <option value="lower">Lower Deck</option>
                            <option value="upper">Upper Deck</option>
                            <option value="single">Single Cabin / Van</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-type">Berth Type *</label>
                        <select id="admin-berth-type" class="form-control">
                            <option value="window">Window Berth</option>
                            <option value="aisle">Aisle Berth</option>
                            <option value="sleeper">Single Sleeper</option>
                            <option value="double_sleeper">Double Sleeper</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-berth-price">Fare (₹) *</label>
                        <input type="number" id="admin-berth-price" class="form-control" placeholder="850" required min="50">
                    </div>
                </div>

                <div style="display:flex; gap:18px; margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-berth-ladies">
                        <span>Ladies Reserved Quota</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-berth-booked">
                        <span>Marked as Booked</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-berth-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                        <span>Save Berth</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT DRIVER VEHICLE & APPROVAL STATUS (Admin)     -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-vehicle-modal">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">no_crash</span>
                <span>Edit Vehicle & Approval Status</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-vehicle-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-vehicle-form" onsubmit="saveAdminVehicle(event)">
                <input type="hidden" id="admin-veh-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-driver-name">Driver Partner Name *</label>
                        <input type="text" id="admin-veh-driver-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-driver-phone">Driver Phone *</label>
                        <input type="text" id="admin-veh-driver-phone" class="form-control" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-model">Vehicle Make & Model *</label>
                        <input type="text" id="admin-veh-model" class="form-control" placeholder="e.g. Toyota Innova Crysta" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-plate">License Plate Number *</label>
                        <input type="text" id="admin-veh-plate" class="form-control" placeholder="e.g. MH 12 QX 4920" style="text-transform:uppercase;" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-category">Fleet Class *</label>
                        <select id="admin-veh-category" class="form-control">
                            <option value="sedan">Comfort Sedan</option>
                            <option value="prime">Prime Sedan</option>
                            <option value="suv">Executive SUV</option>
                            <option value="luxury">Luxury Class</option>
                            <option value="sleeper_van">Sleeper Berth Van</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-seats">Seats *</label>
                        <input type="number" id="admin-veh-seats" class="form-control" min="2" max="60" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-berths">Berths Fitted</label>
                        <input type="number" id="admin-veh-berths" class="form-control" min="0" max="40" value="0">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-coach">Coach Code (If Berth Fitted)</label>
                        <input type="text" id="admin-veh-coach" class="form-control" placeholder="e.g. 5510">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-year">Model Year</label>
                        <input type="text" id="admin-veh-year" class="form-control" placeholder="2023">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-fuel">Fuel Type</label>
                        <select id="admin-veh-fuel" class="form-control">
                            <option value="Diesel">Diesel</option>
                            <option value="Petrol">Petrol</option>
                            <option value="EV">Electric (EV)</option>
                            <option value="CNG">CNG</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-veh-rc">Registration Certificate (RC) Number</label>
                    <input type="text" id="admin-veh-rc" class="form-control" placeholder="e.g. RC-MH12-984129">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-veh-status">Admin Approval Status *</label>
                        <select id="admin-veh-status" class="form-control" style="font-weight:700;">
                            <option value="Pending">⏳ Pending Approval</option>
                            <option value="Approved">✅ Approved & Active</option>
                            <option value="Rejected">❌ Rejected</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end; padding-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                            <input type="checkbox" id="admin-veh-active">
                            <span>Active in Fleet System</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="admin-veh-notes">Admin Verification Notes / Rejection Reason</label>
                    <textarea id="admin-veh-notes" class="form-control" rows="2" placeholder="e.g. Verified commercial permit and insurance"></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteVehicleFromModal()" style="color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Vehicle</span>
                    </button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-vehicle-modal')">Cancel</button>
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
@endif

@if($userRole === 'driver' || $userRole === 'admin')
<!-- ======================================================== -->
<!-- MODAL: DRIVER LIST / REGISTER NEW VEHICLE               -->
<!-- ======================================================== -->
<div class="modal-overlay" id="driver-list-vehicle-modal">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">garage</span>
                <span>Register Vehicle for Outstation Service</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('driver-list-vehicle-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:12px; color:#92400e; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:18px;">info</span>
                <span>New vehicle listings will be set to <strong>Pending Approval</strong> until verified by platform operations.</span>
            </div>

            <form id="driver-list-vehicle-form" onsubmit="submitDriverNewVehicle(event)">
                <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-model">Vehicle Make & Model *</label>
                        <input type="text" id="driver-new-veh-model" class="form-control" placeholder="e.g. Toyota Innova Crysta, Maruti Ertiga" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-plate">License Plate Number *</label>
                        <input type="text" id="driver-new-veh-plate" class="form-control" placeholder="MH 12 AB 1234" style="text-transform:uppercase;" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-category">Fleet Class *</label>
                        <select id="driver-new-veh-category" class="form-control">
                            <option value="suv" selected>Executive SUV</option>
                            <option value="sedan">Comfort Sedan</option>
                            <option value="prime">Prime Sedan</option>
                            <option value="luxury">Luxury Class</option>
                            <option value="sleeper_van">Sleeper Berth Van</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-seats">Seats *</label>
                        <input type="number" id="driver-new-veh-seats" class="form-control" value="6" min="2" max="60" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-berths">Berths (If Fitted)</label>
                        <input type="number" id="driver-new-veh-berths" class="form-control" value="0" min="0" max="40">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-fuel">Fuel Type *</label>
                        <select id="driver-new-veh-fuel" class="form-control">
                            <option value="Diesel" selected>Diesel</option>
                            <option value="Petrol">Petrol</option>
                            <option value="EV">Electric (EV)</option>
                            <option value="CNG">CNG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-year">Manufacturing Year *</label>
                        <input type="text" id="driver-new-veh-year" class="form-control" value="2023" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="driver-new-veh-rc">Registration Certificate (RC Number) *</label>
                    <input type="text" id="driver-new-veh-rc" class="form-control" placeholder="e.g. RC-MH12-123456" required>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('driver-list-vehicle-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-outlined" style="font-size:15px;">send</span>
                        <span>Submit for Admin Approval</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DRIVER EDIT PROFILE                               -->
<!-- ======================================================== -->
<div class="modal-overlay" id="driver-edit-profile-modal">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">badge</span>
                <span>Edit Chauffeur Profile & Fleet</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('driver-edit-profile-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:18px 20px;">
            <form id="driver-edit-profile-form" onsubmit="saveDriverProfile(event)">
                <!-- Avatar Emoji Selector -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Profile Avatar / Chauffeur Icon</label>
                    <input type="hidden" id="driver-edit-avatar" value="{{ $driverProfile->avatar ?? '👨🏽‍✈️' }}">
                    <div class="avatar-picker-grid">
                        @php
                            $avatars = ['👨🏽‍✈️', '👨🏻‍✈️', '👩🏽‍✈️', '👩🏻‍✈️', '🚗', '🚘', '🚙', '⭐'];
                        @endphp
                        @foreach($avatars as $av)
                            <div class="avatar-option {{ ($driverProfile->avatar ?? '👨🏽‍✈️') === $av ? 'active' : '' }}" onclick="selectDriverAvatar('{{ $av }}')">
                                {{ $av }}
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-name">Full Name *</label>
                        <input type="text" id="driver-edit-name" class="form-control" value="{{ $driverProfile->name ?? 'Ramesh Patil' }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-phone">Phone Number *</label>
                        <input type="text" id="driver-edit-phone" class="form-control" value="{{ $driverProfile->phone ?? '+91 98765 43210' }}" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-model">Primary Vehicle Model *</label>
                        <input type="text" id="driver-edit-model" class="form-control" value="{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-plate">License Plate Number *</label>
                        <input type="text" id="driver-edit-plate" class="form-control" value="{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}" style="text-transform:uppercase;" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-category">Fleet Class *</label>
                        <select id="driver-edit-category" class="form-control">
                            <option value="suv" {{ strtolower($driverProfile->fleet_category ?? '') === 'suv' ? 'selected' : '' }}>Executive SUV</option>
                            <option value="sedan" {{ strtolower($driverProfile->fleet_category ?? '') === 'sedan' ? 'selected' : '' }}>Comfort Sedan</option>
                            <option value="prime" {{ strtolower($driverProfile->fleet_category ?? '') === 'prime' ? 'selected' : '' }}>Prime Sedan</option>
                            <option value="luxury" {{ strtolower($driverProfile->fleet_category ?? '') === 'luxury' ? 'selected' : '' }}>Luxury Class</option>
                            <option value="sleeper_van" {{ strtolower($driverProfile->fleet_category ?? '') === 'sleeper_van' ? 'selected' : '' }}>Sleeper Berth Van</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-city">Base Hub / City</label>
                        <input type="text" id="driver-edit-city" class="form-control" value="{{ $driverProfile->city ?? 'Pune & Mumbai Hub' }}">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--outline); padding-top:14px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('driver-edit-profile-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="driver-save-profile-btn">
                        <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                        <span>Save Profile Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($userRole === 'admin')
<!-- ======================================================== -->
<!-- MODAL: ADMIN SETUP / EDIT OUTSTATION CAB ROUTE          -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-cab-route-modal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">alt_route</span>
                <span id="admin-cab-route-modal-title">Add Outstation Cab Route</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-cab-route-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-cab-route-form" onsubmit="saveCabRoute(event)">
                <input type="hidden" id="admin-cab-route-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-from">From City (Origin) *</label>
                        <input type="text" id="admin-cab-route-from" class="form-control" placeholder="e.g. Pune, Kolkata" required list="route-cities-list">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-to">To City (Destination) *</label>
                        <input type="text" id="admin-cab-route-to" class="form-control" placeholder="e.g. Goa, Murshidabad" required list="route-cities-list">
                    </div>
                </div>

                <datalist id="route-cities-list">
                    @foreach($serviceLocations as $loc)
                        <option value="{{ $loc->name }}">
                    @endforeach
                </datalist>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-dist">Highway Distance (KM) *</label>
                        <input type="number" id="admin-cab-route-dist" class="form-control" placeholder="e.g. 450" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-time">Est. Travel Time *</label>
                        <input type="text" id="admin-cab-route-time" class="form-control" placeholder="e.g. 9h 15m, 4h 30m" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-toll">Highway Base Toll (₹)</label>
                        <input type="number" id="admin-cab-route-toll" class="form-control" placeholder="e.g. 480" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-roundtrip-discount">Roundtrip Discount %</label>
                        <input type="number" id="admin-cab-route-roundtrip-discount" class="form-control" placeholder="e.g. 5 or 10" min="0" max="100" value="0">
                    </div>
                </div>

                <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin:10px 0 6px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">schedule</span>
                    <span>Route Departure Schedules & Frequency</span>
                </div>
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-schedule">Departure Timings</label>
                        <input type="text" id="admin-cab-route-schedule" class="form-control" placeholder="e.g. 06:00 AM, 10:30 AM, 03:00 PM, 08:30 PM">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-freq">Schedule Frequency</label>
                        <select id="admin-cab-route-freq" class="form-control">
                            <option value="Daily Express" selected>Daily Express</option>
                            <option value="Hourly Shuttle">Hourly Shuttle</option>
                            <option value="On-Demand 24x7">On-Demand 24x7</option>
                            <option value="Morning & Evening">Morning & Evening</option>
                            <option value="Weekend Special">Weekend Special</option>
                        </select>
                    </div>
                </div>

                <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin:10px 0 6px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">directions_car</span>
                    <span>Custom Fare Schedule as per Car Type (Optional - blank uses KM rate)</span>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-hatchback-fare">Hatchback Fare (₹)</label>
                        <input type="number" id="admin-cab-route-hatchback-fare" class="form-control" placeholder="Auto (₹11/km)" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-sedan-fare">Comfort Sedan Fare (₹)</label>
                        <input type="number" id="admin-cab-route-sedan-fare" class="form-control" placeholder="Auto (₹12.5/km)" min="0">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-suv-fare">Family SUV Fare (₹)</label>
                        <input type="number" id="admin-cab-route-suv-fare" class="form-control" placeholder="Auto (₹18/km)" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-innova-fare">Innova Crysta Fare (₹)</label>
                        <input type="number" id="admin-cab-route-innova-fare" class="form-control" placeholder="Auto (₹22/km)" min="0">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-tempo-fare">Tempo Traveller Fare (₹)</label>
                        <input type="number" id="admin-cab-route-tempo-fare" class="form-control" placeholder="Auto (₹28/km)" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-luxury-fare">Luxury Limousine Fare (₹)</label>
                        <input type="number" id="admin-cab-route-luxury-fare" class="form-control" placeholder="Auto (₹32/km)" min="0">
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:6px; margin-bottom:14px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-cab-route-active" checked>
                        <span>Active & Bookable for Customers</span>
                    </label>
                </div>

                <div style="background:#f1f5f9; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:11.5px; color:#475569;">
                    💡 <strong>Automated Pricing Formula:</strong> The distance (KM) and toll will dynamically calculate instant passenger fares across all fleet tiers (Sedan, Prime, SUV, Crysta, Luxury). Custom fares override the formula.
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="admin-cab-route-delete-btn" onclick="deleteCabRouteFromModal()" style="display:none; color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Route</span>
                    </button>
                    <div style="display:flex; gap:8px; margin-left:auto;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-cab-route-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                            <span>Save Route</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADMIN SETUP / EDIT CAR TYPE & FLEET CATEGORY     -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-car-type-modal">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px;">directions_car</span>
                <span id="admin-car-type-modal-title">Add Car Type & Fleet Category</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-car-type-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-car-type-form" onsubmit="saveCarType(event)">
                <input type="hidden" id="admin-car-type-id" value="">

                <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-name">Car Type Display Name *</label>
                        <input type="text" id="admin-car-type-name" class="form-control" placeholder="e.g. Comfort Sedan, Executive SUV" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-key">Category Key (Slug) *</label>
                        <input type="text" id="admin-car-type-key" class="form-control" placeholder="e.g. sedan, suv, ev" required style="font-family:monospace;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1.2fr 1.8fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-badge">Badge Label</label>
                        <input type="text" id="admin-car-type-badge" class="form-control" placeholder="e.g. Most Popular, VIP Comfort" value="Popular">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-models">Example Car Models *</label>
                        <input type="text" id="admin-car-type-models" class="form-control" placeholder="e.g. Dzire, Etios, Aura, Tigor" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-seats">Seating Capacity *</label>
                        <input type="text" id="admin-car-type-seats" class="form-control" placeholder="e.g. 4 Seats, 7 Seats" value="4 Seats" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-luggage">Luggage Capacity *</label>
                        <input type="text" id="admin-car-type-luggage" class="form-control" placeholder="e.g. 2 Bags, 4 Bags" value="2 Bags" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-rate-km">Rate per KM (₹) *</label>
                        <input type="number" step="0.5" id="admin-car-type-rate-km" class="form-control" placeholder="e.g. 12.5" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-extra-km">Extra KM Rate (₹)</label>
                        <input type="number" step="0.5" id="admin-car-type-extra-km" class="form-control" placeholder="e.g. 12.5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-min-fare">Min. Base Fare (₹)</label>
                        <input type="number" id="admin-car-type-min-fare" class="form-control" placeholder="e.g. 800" min="0" value="800">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2.2fr 0.8fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-image">Vehicle Photo URL</label>
                        <input type="text" id="admin-car-type-image" class="form-control" placeholder="https://..." value="https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-car-type-order">Order Index</label>
                        <input type="number" id="admin-car-type-order" class="form-control" value="0" min="0">
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:6px; margin-bottom:14px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-car-type-active" checked>
                        <span>Active & Available across Booking Modules</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="admin-car-type-delete-btn" onclick="deleteCarTypeFromModal()" style="display:none; color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Car Type</span>
                    </button>
                    <div style="display:flex; gap:8px; margin-left:auto;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-car-type-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                            <span>Save Car Type</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADMIN SETUP / EDIT LOCAL RENTAL PACKAGE           -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-local-package-modal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">schedule</span>
                <span id="admin-local-pkg-modal-title">Add Local Rental Package</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-local-package-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-local-package-form" onsubmit="saveLocalPackage(event)">
                <input type="hidden" id="admin-local-pkg-id" value="">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-code">Package Code *</label>
                        <input type="text" id="admin-local-pkg-code" class="form-control" placeholder="e.g. 4_40, 8_80, 10_100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-city">Applicable City *</label>
                        <input type="text" id="admin-local-pkg-city" class="form-control" placeholder="e.g. All Cities, Kolkata, Pune" value="All Cities" required list="route-cities-list">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-local-pkg-name">Package Display Name *</label>
                    <input type="text" id="admin-local-pkg-name" class="form-control" placeholder="e.g. 8 Hours / 80 KM (Half Day)" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-hours">Duration (Hours) *</label>
                        <input type="number" id="admin-local-pkg-hours" class="form-control" placeholder="e.g. 8" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-km">Included Distance (KM) *</label>
                        <input type="number" id="admin-local-pkg-km" class="form-control" placeholder="e.g. 80" min="1" required>
                    </div>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:8px 0 6px;">Package Base Fares (₹)</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-sedan">Sedan Package Rate (₹) *</label>
                        <input type="number" id="admin-local-pkg-sedan" class="form-control" placeholder="e.g. 2400" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-suv">SUV Package Rate (₹) *</label>
                        <input type="number" id="admin-local-pkg-suv" class="form-control" placeholder="e.g. 3200" min="0" required>
                    </div>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:8px 0 6px;">Overrun Extra Charges</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-extra-km">Extra KM Rate (₹/KM)</label>
                        <input type="number" id="admin-local-pkg-extra-km" class="form-control" placeholder="e.g. 14" value="14" min="0" step="0.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-local-pkg-extra-hr">Extra Hour Rate (₹/Hour)</label>
                        <input type="number" id="admin-local-pkg-extra-hr" class="form-control" placeholder="e.g. 150" value="150" min="0" step="1">
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:6px; margin-bottom:14px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-local-pkg-active" checked>
                        <span>Active & Available for Booking</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="admin-local-pkg-delete-btn" onclick="deleteLocalPackageFromModal()" style="display:none; color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Package</span>
                    </button>
                    <div style="display:flex; gap:8px; margin-left:auto;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-local-package-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                            <span>Save Package</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADMIN SETUP / EDIT AIRPORT TRANSIT HUB            -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-airport-hub-modal">
    <div class="modal-dialog" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">flight</span>
                <span id="admin-airport-modal-title">Add Airport Transit Hub</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-airport-hub-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <form id="admin-airport-form" onsubmit="saveAirportHub(event)">
                <input type="hidden" id="admin-airport-id" value="">

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-airport-name">Airport Full Name *</label>
                    <input type="text" id="admin-airport-name" class="form-control" placeholder="e.g. Netaji Subhash Chandra Bose International Airport" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-code">IATA Airport Code *</label>
                        <input type="text" id="admin-airport-code" class="form-control" placeholder="e.g. CCU, DEL, BOM, PNQ" style="text-transform:uppercase;" maxlength="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-city">Operating City *</label>
                        <input type="text" id="admin-airport-city" class="form-control" placeholder="e.g. Kolkata, New Delhi, Pune" required list="route-cities-list">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-airport-terminals">Operating Terminals</label>
                    <input type="text" id="admin-airport-terminals" class="form-control" placeholder="e.g. Terminal 1 (Domestic & International), T2" value="Main Passenger Terminal">
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:8px 0 6px;">Airport Surcharges (₹)</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-pickup-surcharge">Pickup Surcharge (₹) *</label>
                        <input type="number" id="admin-airport-pickup-surcharge" class="form-control" placeholder="e.g. 250" value="250" min="0" required>
                        <small style="font-size:10.5px; color:#64748b;">Parking + Commercial entry permit</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-drop-surcharge">Drop Surcharge (₹) *</label>
                        <input type="number" id="admin-airport-drop-surcharge" class="form-control" placeholder="e.g. 150" value="150" min="0" required>
                        <small style="font-size:10.5px; color:#64748b;">Departure flyover / access toll</small>
                    </div>
                </div>

                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:8px 0 6px;">Base Transit Fares (₹)</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-sedan">Base Sedan Rate (₹)</label>
                        <input type="number" id="admin-airport-sedan" class="form-control" placeholder="e.g. 950" value="950" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-airport-suv">Base SUV Rate (₹)</label>
                        <input type="number" id="admin-airport-suv" class="form-control" placeholder="e.g. 1450" value="1450" min="0">
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:6px; margin-bottom:14px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px;">
                        <input type="checkbox" id="admin-airport-active" checked>
                        <span>Active for Airport Transit Bookings</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--outline); padding-top:12px;">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="admin-airport-delete-btn" onclick="deleteAirportHubFromModal()" style="display:none; color:#ef4444; border-color:#fca5a5;">
                        <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                        <span>Delete Airport</span>
                    </button>
                    <div style="display:flex; gap:8px; margin-left:auto;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('admin-airport-hub-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                            <span>Save Airport Hub</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

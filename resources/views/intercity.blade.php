@extends('layouts.app')

@section('content')
@php
    $userRole = Auth::check() ? Auth::user()->role : null;
    $ps = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
    $isSchedulesOn = ($ps['enable_schedules_and_berths'] ?? '0') === '1';
    $requestedTab = (string) request()->query('tab', '');
    $bookedParam = trim((string) request()->query('booked', ''));
    $bookingParam = trim((string) request()->query('booking', ''));
    $hasValidBookedParam = $bookedParam !== '' && preg_match('/^ICW-[A-Z0-9-]{3,24}$/i', $bookedParam) === 1;
    $hasValidBookingParam = $bookingParam !== '' && preg_match('/^ICW-[A-Z0-9-]{3,24}$/i', $bookingParam) === 1;

    // Search query prefill support (?pickup=...&drop=...&type=...)
    $rawQueryPickup = trim((string) request()->query('pickup', request()->query('from', '')));
    $rawQueryDrop = trim((string) request()->query('drop', request()->query('to', '')));
    $rawQueryType = trim((string) request()->query('type', request()->query('trip_type', '')));
    $formatQueryPlace = static fn (string $v): string => $v === '' ? '' : ($v === strtolower($v) ? ucwords($v) : $v);
    $queryPickup = $formatQueryPlace(mb_substr($rawQueryPickup, 0, 120));
    $queryDrop = $formatQueryPlace(mb_substr($rawQueryDrop, 0, 120));
    $hasCabSearchQuery = ($queryPickup !== '' || $queryDrop !== '' || $rawQueryType !== '');

    $lowerQueryType = strtolower($rawQueryType);
    $initialRentalType = 'oneway';
    if ($lowerQueryType !== '') {
        if (str_contains($lowerQueryType, 'round')) {
            $initialRentalType = 'roundtrip';
        } elseif (str_contains($lowerQueryType, 'local') || str_contains($lowerQueryType, 'city ride') || str_contains($lowerQueryType, 'hourly')) {
            $initialRentalType = 'local';
        } elseif (str_contains($lowerQueryType, 'intercity') || str_contains($lowerQueryType, 'outstation') || str_contains($lowerQueryType, 'one')) {
            $initialRentalType = 'oneway';
        } elseif (str_contains($lowerQueryType, 'airport') || preg_match('/\bstation\b/i', $lowerQueryType) === 1 || str_contains($lowerQueryType, 'transfer')) {
            $initialRentalType = 'airport';
        }
    }

    $allowedTabsForRole = match($userRole) {
        'admin' => ['admin', 'cabs', 'schedules', 'bidding'],
        'driver' => ['bidding', 'driver-trips', 'driver-wallet', 'driver-profile'],
        default => $isSchedulesOn ? ['cabs', 'schedules', 'my-bookings'] : ['cabs', 'my-bookings'],
    };
    $defaultTab = match($userRole) {
        'admin' => 'admin',
        'driver' => 'bidding',
        default => 'cabs'
    };
    if (in_array($requestedTab, $allowedTabsForRole, true)) {
        $defaultTab = $requestedTab;
    } elseif (($hasValidBookedParam || $hasValidBookingParam) && in_array('my-bookings', $allowedTabsForRole, true)) {
        $defaultTab = 'my-bookings';
    } elseif ($hasCabSearchQuery && in_array('cabs', $allowedTabsForRole, true)) {
        $defaultTab = 'cabs';
    }

    // Resolve Airport / Station Transfer prefill values
    $airportDirection = 'pickup';
    $initialAirportName = 'Netaji Subhash Chandra Bose Intl (CCU), Kolkata';
    $initialAirportAddress = 'Salt Lake Sector V, Kolkata';
    if ($initialRentalType === 'airport' && ($queryPickup !== '' || $queryDrop !== '')) {
        $matchHubLabel = static function (string $q) use ($airportHubs): ?string {
            if ($q === '') {
                return null;
            }
            $needle = strtolower(trim(explode(',', $q)[0]));
            foreach ($airportHubs as $hub) {
                if (! ($hub->is_active ?? true)) {
                    continue;
                }
                if (
                    strtolower((string) $hub->city) === $needle
                    || strtoupper((string) $hub->iata_code) === strtoupper($needle)
                    || str_contains(strtolower((string) $hub->airport_name), $needle)
                ) {
                    return "{$hub->airport_name} ({$hub->iata_code}), {$hub->city}";
                }
            }
            return null;
        };
        $looksLikeTerminal = static fn (string $q): bool => preg_match('/\b(airport|station|terminal|junction|railway|jn|ccu|del|bom|blr|maa|hyd)\b/i', $q) === 1;

        $pickupHubMatch = $matchHubLabel($queryPickup);
        $dropHubMatch = $matchHubLabel($queryDrop);

        if ($queryPickup !== '' && $queryDrop !== '') {
            if ($looksLikeTerminal($queryPickup) || ($pickupHubMatch && ! $dropHubMatch && ! $looksLikeTerminal($queryDrop))) {
                $airportDirection = 'pickup';
                $initialAirportName = $pickupHubMatch ?: $queryPickup;
                $initialAirportAddress = $queryDrop;
            } else {
                $airportDirection = 'drop';
                $initialAirportName = $dropHubMatch ?: $queryDrop;
                $initialAirportAddress = $queryPickup;
            }
        } elseif ($queryPickup !== '') {
            if ($looksLikeTerminal($queryPickup) || $pickupHubMatch) {
                $airportDirection = 'pickup';
                $initialAirportName = $pickupHubMatch ?: $queryPickup;
                $initialAirportAddress = '';
            } else {
                $airportDirection = 'drop';
                $initialAirportAddress = $queryPickup;
            }
        } elseif ($queryDrop !== '') {
            $airportDirection = 'drop';
            $initialAirportName = $dropHubMatch ?: $queryDrop;
            $initialAirportAddress = '';
        }
    }

    $initialFromCity = $queryPickup !== '' ? $queryPickup : 'Kolkata';
    $initialToCity = $queryDrop !== '' ? $queryDrop : 'Mayapur, West Bengal';
    $initialLocalCity = $queryPickup !== '' ? $queryPickup : ($queryDrop !== '' ? $queryDrop : 'Kolkata');
    $petRate = (float) ($ps['addon_pet_friendly_rate'] ?? 350);
    $nightRate = (float) ($ps['addon_night_allowance_rate'] ?? 400);
    $luggageRate = (float) ($ps['addon_luggage_carrier_rate'] ?? 200);
    $nightStartRaw = $ps['night_stay_start_hour'] ?? '23:00';
    $nightEndRaw = $ps['night_stay_end_hour'] ?? '06:00';
    $nightWindowLabel = date('g:i A', strtotime($nightStartRaw)) . ' – ' . date('g:i A', strtotime($nightEndRaw));
    $rtMinKm = (int) ($ps['roundtrip_min_km_per_day'] ?? 250);
    $driverAllowanceRate = (float) ($ps['driver_day_allowance'] ?? 0);
    $freeWaitMins = (int) ($ps['free_waiting_minutes'] ?? 45);
    $waitHrRate = (float) ($ps['waiting_charge_per_hour'] ?? 150);
    $freeCancelHrs = (int) ($ps['free_cancellation_hours'] ?? 1);
    $otherCondText = $ps['other_booking_conditions'] ?? '';
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
                            <button type="button" class="rental-type-tab {{ $initialRentalType === 'oneway' ? 'active' : '' }}" data-type="oneway" onclick="switchRentalTab('oneway')">ONE WAY</button>
                            <button type="button" class="rental-type-tab {{ $initialRentalType === 'roundtrip' ? 'active' : '' }}" data-type="roundtrip" onclick="switchRentalTab('roundtrip')">ROUND TRIP</button>
                            <button type="button" class="rental-type-tab {{ $initialRentalType === 'local' ? 'active' : '' }}" data-type="local" onclick="switchRentalTab('local')">LOCAL</button>
                            <button type="button" class="rental-type-tab {{ $initialRentalType === 'airport' ? 'active' : '' }}" data-type="airport" onclick="switchRentalTab('airport')">AIRPORT</button>
                        </div>
                    </div>

                    <!-- Quick Initial Pickup Detection (Placed above inputs so city dropdowns never cover or collide with it) -->
                    <div class="rental-geo-bar" id="rental-geo-bar">
                        <span class="rental-geo-label">
                            <span class="material-symbols-outlined" style="font-size:15px; color:#20A9D2;">explore</span>
                            <span>Detect initial pickup:</span>
                        </span>
                        <div class="rental-geo-actions">
                            <button type="button" class="rental-geo-pill" id="btn-detect-pickup-gps" onclick="detectActiveBookingLocation('gps')">
                                <span class="material-symbols-outlined" style="font-size:14px;">my_location</span>
                                <span>Use GPS</span>
                            </button>
                            <button type="button" class="rental-geo-pill" id="btn-detect-pickup-network" onclick="detectActiveBookingLocation('network')">
                                <span class="material-symbols-outlined" style="font-size:14px;">wifi</span>
                                <span>Use Network</span>
                            </button>
                        </div>
                        <span id="rental-geo-status" class="rental-geo-status" aria-live="polite"></span>
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
                    <div id="rental-form-oneway" class="rental-tab-panel" style="display:{{ $initialRentalType === 'oneway' ? 'block' : 'none' }};">
                        <div class="rental-form-grid">
                            <!-- FROM -->
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-from-city">FROM</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-from-city" class="rental-input" value="{{ $initialFromCity }}" placeholder="Pickup City / Area" autocomplete="off" onfocus="this.select(); openCityPicker('rental-from-city')" onclick="openCityPicker('rental-from-city')" oninput="handleCityPickerInput('rental-from-city', this.value); syncRentalFromCity(this.value)">
                                    <button type="button" class="rental-locate-btn" title="Detect current pickup location (GPS or Network)" onclick="detectInitialLocation('rental-from-city', 'auto', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px;">my_location</span>
                                    </button>
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
                                    <input type="text" id="rental-to-city" class="rental-input" value="{{ $initialToCity }}" placeholder="Destination City / Area" autocomplete="off" onfocus="this.select(); openCityPicker('rental-to-city')" onclick="openCityPicker('rental-to-city')" oninput="handleCityPickerInput('rental-to-city', this.value); syncRentalToCity(this.value)">
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
                    <div id="rental-form-roundtrip" class="rental-tab-panel" style="display:{{ $initialRentalType === 'roundtrip' ? 'block' : 'none' }};">
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-rt-from">FROM</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">search</span>
                                    <input type="text" id="rental-rt-from" class="rental-input" value="{{ $initialFromCity }}" placeholder="Pickup City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-rt-from')" onclick="openCityPicker('rental-rt-from')" oninput="handleCityPickerInput('rental-rt-from', this.value); updateRentalRouteStats()">
                                    <button type="button" class="rental-locate-btn" title="Detect current pickup location (GPS or Network)" onclick="detectInitialLocation('rental-rt-from', 'auto', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px;">my_location</span>
                                    </button>
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
                                    <input type="text" id="rental-rt-to" class="rental-input" value="{{ $initialToCity }}" placeholder="Destination City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-rt-to')" onclick="openCityPicker('rental-rt-to')" oninput="handleCityPickerInput('rental-rt-to', this.value); updateRentalRouteStats()">
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
                    <div id="rental-form-local" class="rental-tab-panel" style="display:{{ $initialRentalType === 'local' ? 'block' : 'none' }};">
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" for="rental-local-city">CITY</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">location_city</span>
                                    <input type="text" id="rental-local-city" class="rental-input" value="{{ $initialLocalCity }}" placeholder="Select City" autocomplete="off" onfocus="this.select(); openCityPicker('rental-local-city')" onclick="openCityPicker('rental-local-city')" oninput="handleCityPickerInput('rental-local-city', this.value); updateRentalRouteStats()">
                                    <button type="button" class="rental-locate-btn" title="Detect current city (GPS or Network)" onclick="detectInitialLocation('rental-local-city', 'auto', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px;">my_location</span>
                                    </button>
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
                    <div id="rental-form-airport" class="rental-tab-panel" style="display:{{ $initialRentalType === 'airport' ? 'block' : 'none' }};">
                        <div style="display:flex; gap:16px; margin-bottom:14px;">
                            <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer;">
                                <input type="radio" name="airport_direction" value="pickup" {{ $airportDirection === 'pickup' ? 'checked' : '' }} onchange="toggleAirportDirection('pickup')">
                                <span>Pickup from Airport / Station</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer;">
                                <input type="radio" name="airport_direction" value="drop" {{ $airportDirection === 'drop' ? 'checked' : '' }} onchange="toggleAirportDirection('drop')">
                                <span>Drop to Airport / Station</span>
                            </label>
                        </div>
                        <div class="rental-form-grid">
                            <div class="rental-field-box">
                                <label class="rental-field-label" id="rental-airport-label-1">{{ $airportDirection === 'drop' ? 'AIRPORT / STATION / TERMINAL' : 'AIRPORT / TERMINAL' }}</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">flight</span>
                                    <input type="text" id="rental-airport-name" class="rental-input" value="{{ $initialAirportName }}" placeholder="Enter Airport / Station / Terminal" list="airport-suggestions-list" oninput="updateRentalRouteStats()">
                                </div>
                            </div>
                            <datalist id="airport-suggestions-list">
                                @foreach($airportHubs as $hub)
                                    <option value="{{ $hub->airport_name }} ({{ $hub->iata_code }}), {{ $hub->city }}">{{ $hub->terminals }}</option>
                                @endforeach
                            </datalist>
                            <div class="rental-field-box">
                                <label class="rental-field-label" id="rental-airport-label-2">{{ $airportDirection === 'drop' ? 'PICKUP CITY / ADDRESS' : 'DESTINATION ADDRESS' }}</label>
                                <div class="rental-input-wrap">
                                    <span class="material-symbols-outlined rental-input-icon">pin_drop</span>
                                    <input type="text" id="rental-airport-address" class="rental-input" value="{{ $initialAirportAddress }}" placeholder="Enter City Area / Hotel / Landmark" oninput="updateRentalRouteStats()">
                                    <button type="button" class="rental-locate-btn" title="Detect current address (GPS or Network)" onclick="detectInitialLocation('rental-airport-address', 'auto', event)">
                                        <span class="material-symbols-outlined" style="font-size:18px;">my_location</span>
                                    </button>
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
                            <span><strong id="step1-route-name">{{ ($queryPickup !== '' && $queryDrop !== '') ? ($queryPickup . ' → ' . $queryDrop) : ($queryPickup !== '' ? ($queryPickup . ' → ' . $initialToCity) : 'Kolkata → Mayapur, West Bengal') }}</strong></span>
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
                    @php
                        $promoPaySettings = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
                        $promoAdvPct = $promoPaySettings['payment_advance_pct'] ?? '20';
                        $promoPartPayOn = ($promoPaySettings['payment_show_part_pay'] ?? '1') === '1';
                        $promoCashOn = ($promoPaySettings['payment_show_cash'] ?? '1') === '1';
                    @endphp
                    <!-- Card 1 -->
                    <div class="rental-promo-card card-zero-cost" onclick="exploreCabs()">
                        <span class="rental-promo-badge">PROMO ASSURED</span>
                        <h3 class="rental-promo-title">Book Now at zero cost</h3>
                        <p class="rental-promo-sub" id="promo-advance-copy">
                            @if($promoPartPayOn && $promoCashOn)
                                No booking charges. Pay {{ $promoAdvPct }}% advance or pay later to driver.
                            @elseif($promoPartPayOn)
                                No booking charges. Pay just {{ $promoAdvPct }}% advance to lock your ride.
                            @elseif($promoCashOn)
                                No booking charges. Pay directly to driver on pickup.
                            @else
                                Instant online booking confirmation with transparent pricing.
                            @endif
                        </p>
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

                <div id="step2-beyond-region-banner" style="display:none; background:#fffbeb; border:1px solid #f59e0b; border-left:4px solid #d97706; border-radius:10px; padding:12px 14px; margin-bottom:14px; color:#92400e; font-size:12.5px; line-height:1.45;">
                    <div style="display:flex; align-items:flex-start; gap:10px;">
                        <span class="material-symbols-outlined" style="color:#d97706; font-size:20px; flex-shrink:0;">warning</span>
                        <div>
                            <div style="font-weight:800; color:#78350f; margin-bottom:2px;">Beyond Regional Service Area — Ride Not Confirmed or Guaranteed</div>
                            <div id="step2-beyond-region-text">This route is outside our core service region. Distance and estimated fare are calculated via route API fallback. You can still submit a booking request, but chauffeur availability is not guaranteed.</div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Sorting Toolbar -->
                <div class="rental-filter-bar">
                    <div class="rental-filter-pills" id="rental-filter-pills">
                        <button type="button" class="rental-filter-pill active" data-category="all" onclick="filterRentalCategory('all', this)">All Cabs</button>
                        @forelse(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                            <button type="button" class="rental-filter-pill" data-category="{{ strtolower($ct->category_key) }}" onclick="filterRentalCategory('{{ strtolower($ct->category_key) }}', this)">{{ $ct->name }}</button>
                        @empty
                            <button type="button" class="rental-filter-pill" data-category="hatchback" onclick="filterRentalCategory('hatchback', this)">Hatchback</button>
                            <button type="button" class="rental-filter-pill" data-category="sedan" onclick="filterRentalCategory('sedan', this)">Sedan</button>
                            <button type="button" class="rental-filter-pill" data-category="suv" onclick="filterRentalCategory('suv', this)">SUV</button>
                            <button type="button" class="rental-filter-pill" data-category="innova" onclick="filterRentalCategory('innova', this)">Innova Crysta</button>
                            <button type="button" class="rental-filter-pill" data-category="tempo" onclick="filterRentalCategory('tempo', this)">Tempo Traveller</button>
                            <button type="button" class="rental-filter-pill" data-category="luxury" onclick="filterRentalCategory('luxury', this)">Luxury</button>
                        @endforelse
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
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px;">
                                    <label class="rental-field-label" style="margin-bottom:0;">Exact Pickup Address / Landmark *</label>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <button type="button" class="rental-geo-pill rental-geo-pill-sm" onclick="detectInitialLocation('cab-pickup-address', 'gps', event)">
                                            <span class="material-symbols-outlined" style="font-size:13px;">my_location</span>
                                            <span>GPS</span>
                                        </button>
                                        <button type="button" class="rental-geo-pill rental-geo-pill-sm" onclick="detectInitialLocation('cab-pickup-address', 'network', event)">
                                            <span class="material-symbols-outlined" style="font-size:13px;">wifi</span>
                                            <span>Network</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">my_location</span>
                                    <input type="text" id="cab-pickup-address" class="form-control" placeholder="Building, Street, Landmark" value="{{ $queryPickup !== '' ? $queryPickup : 'Salt Lake Sector V, Kolkata' }}">
                                </div>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="rental-field-label">Exact Drop Address *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined" style="color:#0284c7;">pin_drop</span>
                                    <input type="text" id="cab-drop-address" class="form-control" placeholder="Drop Hotel / Landmark" value="{{ $queryDrop !== '' ? $queryDrop : 'Mayapur ISKCON Temple Main Gate' }}">
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
                                    <span class="addon-price" id="addon-price-pet">+₹{{ number_format($petRate) }}</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-night" onchange="updateTotalCabFare()">
                                        <span>Chauffeur Night Stay <small id="addon-night-hours-label" style="color:#64748b; font-weight:500;">({{ $nightWindowLabel }})</small></span>
                                    </div>
                                    <span class="addon-price" id="addon-price-night">+₹{{ number_format($nightRate) }}</span>
                                </label>
                                <label class="addon-item">
                                    <div class="addon-label">
                                        <input type="checkbox" id="addon-luggage" onchange="updateTotalCabFare()">
                                        <span>Rooftop Luggage Carrier</span>
                                    </div>
                                    <span class="addon-price" id="addon-price-luggage">+₹{{ number_format($luggageRate) }}</span>
                                </label>
                            </div>
                            <div id="cab-fare-conditions-note" style="margin-top:10px; padding:8px 10px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0; font-size:11.5px; color:#475569; line-height:1.45;">
                                <strong>Fare Conditions:</strong>
                                <span>Round-trip min {{ $rtMinKm }} km/day</span> &bull;
                                <span>{{ $freeWaitMins }} min free wait (₹{{ number_format($waitHrRate) }}/hr extra)</span> &bull;
                                <span>Free cancel {{ $freeCancelHrs }}h prior</span>
                                @if(!empty($otherCondText))
                                    <div style="margin-top:2px; color:#64748b;">{{ $otherCondText }}</div>
                                @endif
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

                        @php
                            $paySettings = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
                            $payAdvPct = (float) ($paySettings['payment_advance_pct'] ?? 20);
                            $payAdvLabel = fmod($payAdvPct, 1.0) === 0.0 ? (string) (int) $payAdvPct : rtrim(rtrim(number_format($payAdvPct, 2, '.', ''), '0'), '.');
                            $showFullOnline = ($paySettings['payment_show_full_online'] ?? '1') === '1';
                            $showPartPay = ($paySettings['payment_show_part_pay'] ?? '1') === '1';
                            $showFullUpi = ($paySettings['payment_show_full_upi'] ?? '1') === '1';
                            $showCash = ($paySettings['payment_show_cash'] ?? '1') === '1';
                            if (! $showFullOnline && ! $showPartPay) {
                                $showFullOnline = true;
                            }
                            $defaultPayMode = (($paySettings['payment_default_option'] ?? '') === 'part_pay' && $showPartPay)
                                ? 'part_pay'
                                : ($showFullOnline ? 'full_online' : 'part_pay');
                            $isRazorpayActive = ($paySettings['razorpay_active'] ?? '0') === '1';
                        @endphp
                        <!-- First Screen: Simple Two-Option Payment Selection (Full Payment vs Partial Payment) -->
                        <div id="checkout-payment-modes-box" style="margin-top:14px; background:#f8fafc; border-radius:8px; padding:12px; border:1px solid #e2e8f0; color-scheme:light; accent-color:#0f172a;">
                            <fieldset style="border:0; padding:0; margin:0;">
                                <legend style="font-size:11.5px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:8px; padding:0;">Select Payment Option</legend>
                                <div id="checkout-payment-options-list" style="display:flex; flex-direction:column; gap:8px;">
                                    @if($showFullOnline)
                                    <label class="checkout-pay-opt" data-mode="full_online" for="rental-pay-mode-full" style="display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:44px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; background:#ffffff; font-size:13px; cursor:pointer;">
                                        <span style="display:flex; align-items:center; gap:8px;">
                                            <input type="radio" id="rental-pay-mode-full" name="rental_pay_mode" value="full_online" {{ $defaultPayMode === 'full_online' ? 'checked' : '' }} onchange="onCheckoutPayModeChange(this.value)" style="width:18px; height:18px; flex-shrink:0;">
                                            <span>Full Payment (100% Trip Fare)</span>
                                        </span>
                                        <strong class="checkout-pay-amt" data-amt-for="full_online" style="color:#0f172a; font-size:12.5px;"></strong>
                                    </label>
                                    @endif
                                    @if($showPartPay)
                                    <label class="checkout-pay-opt" data-mode="part_pay" for="rental-pay-mode-part" style="display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:44px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; background:#ffffff; font-size:13px; cursor:pointer;">
                                        <span style="display:flex; align-items:center; gap:8px;">
                                            <input type="radio" id="rental-pay-mode-part" name="rental_pay_mode" value="part_pay" {{ $defaultPayMode === 'part_pay' ? 'checked' : '' }} onchange="onCheckoutPayModeChange(this.value)" style="width:18px; height:18px; flex-shrink:0;">
                                            <span>Partial Payment ({{ $payAdvLabel }}% Advance, Balance to Driver)</span>
                                        </span>
                                        <strong class="checkout-pay-amt" data-amt-for="part_pay" style="color:#0284c7; font-size:12.5px;"></strong>
                                    </label>
                                    <div id="checkout-part-pay-agreed-badge" style="display:none; font-size:11px; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:4px 8px; margin-left:4px;">
                                        ✓ Agreed to pay remaining balance &amp; additional charges (if any) on trip completion.
                                        <a href="javascript:void(0)" onclick="openCheckoutPaymentStepModal(2)" style="color:#0284c7; font-weight:700; margin-left:4px;">Review</a>
                                    </div>
                                    @endif
                                </div>
                            </fieldset>

                            <!-- Hidden inline mirror kept only for settings sync; customer pays cleanly via the step modal -->
                            <div id="checkout-upi-details-box" style="display:none;" aria-hidden="true">
                                <code id="checkout-upi-id-text">{{ $paySettings['upi_id'] ?? 'intercitywheels@upi' }}</code>
                                <span id="checkout-upi-payee-text">{{ $paySettings['upi_payee_name'] ?? 'Intercity Wheels' }}</span>
                                <strong id="checkout-upi-amount-text">₹2,550</strong>
                                <a id="checkout-upi-app-link" href="#">Open UPI App</a>
                                <div id="checkout-upi-instructions-text">{{ $paySettings['upi_instructions'] ?? '' }}</div>
                                <input type="radio" name="upi_settlement_choice" value="submit_utr" checked>
                                <input type="text" id="checkout-upi-utr" value="">
                                <label id="checkout-driver-collect-opt" style="display:{{ $showCash ? 'flex' : 'none' }};">
                                    <input type="radio" name="upi_settlement_choice" value="driver_collect">
                                </label>
                            </div>

                            <div id="checkout-payable-now-bar" style="margin-top:10px; padding-top:8px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; gap:8px; font-size:12px; color:#334155;">
                                <span>Payable Now to Confirm:</span>
                                <strong id="checkout-payable-now-amount" style="color:#0f172a; text-align:right;">₹2,550</strong>
                            </div>
                        </div>

                        <!-- Terms & Conditions Checkbox -->
                        <div style="margin-top:12px; font-size:11.5px; color:#475569; display:flex; align-items:flex-start; gap:6px; accent-color:#0f172a;">
                            <input type="checkbox" id="rental-terms-agree" checked style="margin-top:2px;">
                            <label for="rental-terms-agree">
                                By proceeding, you agree to <a href="/legal/privacy" target="_blank" style="color:#0284c7; text-decoration:underline;">Privacy Policy</a> and <a href="/legal/terms" target="_blank" style="color:#0284c7; text-decoration:underline;">T&Cs</a>.
                            </label>
                        </div>

                        <div id="step3-beyond-region-banner" style="display:none; margin-top:12px; background:#fffbeb; border:1px solid #f59e0b; border-left:4px solid #d97706; border-radius:8px; padding:10px 12px; font-size:11.5px; color:#92400e; line-height:1.45;">
                            <strong style="display:block; color:#78350f; margin-bottom:2px;">⚠️ Beyond Service Region (Unconfirmed Request)</strong>
                            <span id="step3-beyond-region-text">Your booking will be accepted as a request, not a confirmed/guaranteed ride. Availability depends on chauffeur acceptance.</span>
                        </div>

                        <!-- Confirm & Book Button -->
                        <div style="margin-top:16px;">
                            <button type="button" id="book-cab-confirm-btn" class="btn-explore-cabs" style="width:100%; justify-content:center; padding:14px 20px; font-size:15px;">
                                <span class="material-symbols-outlined" style="font-size:20px;">verified_user</span>
                                <span id="book-cab-confirm-btn-label">CONFIRM & BOOK CAB</span>
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
            @if(!$isSchedulesEnabled && (!$userRole || $userRole === 'customer'))
                <div class="card" id="schedules-disabled-notice-card" style="max-width:600px; margin:40px auto; text-align:center; padding:32px 24px;">
                    <div style="width:56px; height:56px; border-radius:50%; background:#fff7ed; color:#ea580c; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                        <span class="material-symbols-outlined" style="font-size:32px;">directions_bus</span>
                    </div>
                    <h3 style="font-size:18px; font-weight:700; color:#1e293b; margin-bottom:8px;">Schedules & Berths Offline</h3>
                    <p style="font-size:14px; color:#64748b; line-height:1.5; margin-bottom:20px;">
                        Scheduled sleeper coach and berth bookings are currently unavailable. Please book a comfortable chauffeur-driven cab for your trip!
                    </p>
                    <button type="button" class="btn btn-primary" onclick="switchTab('cabs')">
                        <span class="material-symbols-outlined">directions_car</span>
                        <span>Book Outstation Cab</span>
                    </button>
                </div>
            @endif

            <div id="schedules-booking-container" style="{{ (!$isSchedulesEnabled && (!$userRole || $userRole === 'customer')) ? 'display:none;' : '' }}">
                @if(!$isSchedulesEnabled && $userRole === 'admin')
                    <div style="background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="material-symbols-outlined" style="color:#d97706; font-size:22px;">visibility_off</span>
                            <div>
                                <strong style="color:#92400e; font-size:13px;">Preview Mode (Feature Disabled for Customers)</strong>
                                <p style="color:#b45309; font-size:12px; margin:2px 0 0;">The Schedules & Berths option is currently turned OFF in Admin Settings. Only admins can see this preview.</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline" style="white-space:nowrap;" onclick="switchTab('admin'); switchAdminSection('system');">
                            <span>Manage in Settings</span>
                        </button>
                    </div>
                @endif
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
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: CUSTOMER MY BOOKINGS & PASSENGER MANIFEST          -->
        <!-- ======================================================== -->
        @php
            $customerTripsList = collect($customerBookings ?? []);
            $activeTripCount = $customerTripsList->filter(fn ($t) => ! in_array($t->status, ['Completed', 'Cancelled'], true))->count();
            $completedTripCount = $customerTripsList->where('status', 'Completed')->count();
            $allTripCount = $customerTripsList->count();
            $bookedQuery = trim((string) request()->query('booked', ''));
            $bookedHighlight = ($bookedQuery !== '' && preg_match('/^ICW-[A-Z0-9-]{3,24}$/i', $bookedQuery) === 1) ? strtoupper($bookedQuery) : null;
            $custSupportCfg = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
            $custSupportPhone = trim((string) ($custSupportCfg['legal_contact_helpline'] ?? $custSupportCfg['invoice_support_phone'] ?? '700 3434 088')) ?: '700 3434 088';
            $custSupportTel = 'tel:' . (preg_replace('/[^0-9+]/', '', $custSupportPhone) ?: '7003434088');
            $custSupportEmail = trim((string) ($custSupportCfg['legal_contact_email'] ?? 'support@intercitywheels.com')) ?: 'support@intercitywheels.com';
        @endphp
        <section id="tab-my-bookings" class="tab-pane {{ $defaultTab === 'my-bookings' ? 'active' : '' }}">
            <div class="card my-trips-shell">
                <div class="card-header my-trips-header">
                    <div>
                        <h2 class="card-title my-trips-heading">
                            <span class="material-symbols-outlined" style="color:var(--gold-dark); font-size:21px;">confirmation_number</span>
                            <span>My Trips &amp; Booking History</span>
                        </h2>
                        <p class="card-subtitle">Track upcoming rides, chauffeur contact, ride OTPs &amp; 24/7 support at every stage</p>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <a href="{{ $custSupportTel }}" class="btn btn-sm btn-outline" style="text-decoration:none;" title="Call 24/7 Support">
                            <span class="material-symbols-outlined" style="font-size:15px;">support_agent</span>
                            <span>Support: {{ $custSupportPhone }}</span>
                        </a>
                        <a href="mailto:{{ $custSupportEmail }}" class="btn btn-sm btn-outline" style="text-decoration:none;" title="Email Support">
                            <span class="material-symbols-outlined" style="font-size:15px;">mail</span>
                            <span>Email Support</span>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="switchTab('cabs')">
                            <span class="material-symbols-outlined" style="font-size:15px;">add</span>
                            <span>Book a Trip</span>
                        </button>
                    </div>
                </div>

                @auth
                    @if($bookedHighlight)
                    <div class="trips-booked-banner" role="status">
                        <span class="material-symbols-outlined" style="font-size:18px; color:#15803d;">check_circle</span>
                        <span><strong>Booking {{ $bookedHighlight }} confirmed.</strong> Your trip details and boarding OTP are ready below.</span>
                    </div>
                    @endif

                    <div class="trips-toolbar">
                        <div class="trips-filters" role="tablist" aria-label="Filter trips by status">
                            <button type="button" class="trip-filter-pill" data-filter="active" role="tab" aria-selected="false" onclick="filterMyTrips('active')">
                                <span>Upcoming Trips</span>
                                <span class="trip-filter-count" id="trips-count-active">{{ $activeTripCount }}</span>
                            </button>
                            <button type="button" class="trip-filter-pill" data-filter="completed" role="tab" aria-selected="false" onclick="filterMyTrips('completed')">
                                <span>Completed Trips</span>
                                <span class="trip-filter-count" id="trips-count-completed">{{ $completedTripCount }}</span>
                            </button>
                            <button type="button" class="trip-filter-pill active" data-filter="all" role="tab" aria-selected="true" onclick="filterMyTrips('all')">
                                <span>All Trips</span>
                                <span class="trip-filter-count" id="trips-count-all">{{ $allTripCount }}</span>
                            </button>
                        </div>
                        <div class="trips-summary" id="trips-summary">
                            @if($allTripCount === 0)
                                No trips booked yet
                            @else
                                {{ $activeTripCount }} active · {{ $completedTripCount }} completed
                            @endif
                        </div>
                    </div>

                    <div class="customer-bookings-grid" id="my-trips-list">
                        @foreach($customerTripsList as $cb)
                        @php
                            $bucket = $cb->status === 'Completed'
                                ? 'completed'
                                : ($cb->status === 'Cancelled' ? 'cancelled' : 'active');
                            $isHighlighted = $bookedHighlight !== null && strtoupper((string) $cb->booking_reference) === $bookedHighlight;
                            $payBadgeLabel = match($cb->payment_status) {
                                'pending_upi_verification' => $cb->payment_mode === 'part_pay' ? 'Adv UTR Pending Admin' : 'UTR Pending Admin',
                                'awaiting_upi_utr' => 'Awaiting UPI UTR',
                                'driver_collect' => 'Driver Collects',
                                'pay_on_pickup' => 'Pay Driver',
                                'upi_rejected' => 'UTR Rejected · Pay Driver',
                                'pending' => 'Payment Pending',
                                'partial_paid' => $cb->driver_collected_at ? 'Paid in Full' : (((float) $cb->advance_percentage).'% Adv Paid'),
                                default => match($cb->payment_mode) {
                                    'part_pay' => $cb->driver_collected_at ? 'Paid in Full' : (((float) $cb->advance_percentage).'% Adv Paid'),
                                    'cash' => $cb->driver_collected_at ? 'Paid to Driver' : 'Pay Driver',
                                    'full_upi' => 'UPI Confirmed',
                                    default => $cb->payment_gateway === 'upi_manual' ? 'UPI Confirmed' : 'Prepaid',
                                },
                            };
                            $showCustomerUpiPanel = ($cb->payment_mode === 'full_upi' || $cb->payment_gateway === 'upi_manual' || in_array($cb->payment_status, ['pending_upi_verification', 'awaiting_upi_utr', 'upi_rejected'], true))
                                && in_array($cb->payment_status, ['pending_upi_verification', 'awaiting_upi_utr', 'upi_rejected', 'driver_collect'], true)
                                && ! in_array($cb->status, ['Completed', 'Cancelled'], true);
                            $upiPayableNow = ((float) $cb->advance_amount > 0) ? (float) $cb->advance_amount : (float) $cb->total_amount;
                            $custPaySettings = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
@endphp
                        <div class="customer-trip-card{{ $isHighlighted ? ' is-new' : '' }}" id="trip-card-{{ $cb->booking_reference }}" data-booking-ref="{{ $cb->booking_reference }}" data-bucket="{{ $bucket }}" data-status="{{ $cb->status }}">
                            <div class="trip-card-top">
                                <div class="trip-card-title-group" data-booking-part="status summary">
                                    <div class="trip-card-badges">
                                        <span class="trip-ref-badge">{{ $cb->booking_reference }}</span>
                                        <span class="trip-status-pill status-{{ strtolower(str_replace(' ', '-', $cb->status)) }}">{{ $cb->status }}</span>
                                        @if($cb->is_beyond_region && in_array($cb->status, ['Requested', 'Bidding'], true))
                                            <span class="trip-status-pill" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;">Out of Region · Unconfirmed</span>
                                        @endif
                                        <span class="trip-service-tag">{{ strtoupper($cb->service_type) }}</span>
                                        @if($isHighlighted)
                                            <span class="trip-new-flag">Just booked</span>
                                        @endif
                                    </div>
                                    <div class="trip-card-route">{{ $cb->route_summary }}</div>
                                    <div class="trip-card-date">
                                        <span class="material-symbols-outlined" style="font-size:13px;">schedule</span>
                                        <span>{{ $cb->departure_time ? $cb->departure_time->format('D, d M Y, h:i A') : 'Scheduled Departure' }}</span>
                                    </div>
                                </div>
                                <div class="trip-card-fare-col" data-booking-part="payment receipt">
                                    <div class="trip-card-fare">₹{{ number_format($cb->total_amount) }}</div>
                                    <div class="trip-card-pay-tag">{{ $payBadgeLabel }}</div>
                                </div>
                            </div>

                            <div class="trip-card-grid">
                                <div class="trip-info-tile" data-booking-part="route summary">
                                    <div class="trip-tile-label">Pickup &amp; Drop</div>
                                    <div class="trip-tile-line"><strong>From:</strong> {{ $cb->pickup_location }}</div>
                                    <div class="trip-tile-line"><strong>To:</strong> {{ $cb->drop_location }}</div>
                                    @if($cb->customer_phone)
                                        <div class="trip-tile-sub" style="margin-top:4px;">Passenger: <strong>{{ $cb->customer_name }}</strong> · <a href="tel:{{ $cb->customer_phone }}" class="trip-driver-tel">{{ $cb->customer_phone }}</a></div>
                                    @endif
                                </div>

                                <div class="trip-info-tile" data-booking-part="driver">
                                    <div class="trip-tile-label">Chauffeur &amp; Vehicle</div>
                                    @if($cb->driver)
                                        <div class="trip-tile-line" style="font-weight:700; color:#0f172a;">{{ $cb->driver->name }} (★{{ $cb->driver->rating }})</div>
                                        <div class="trip-tile-sub">{{ $cb->driver->vehicle_model }} · <strong>{{ $cb->driver->vehicle_plate }}</strong> · <a href="tel:{{ $cb->driver->phone }}" class="trip-driver-tel">{{ $cb->driver->phone }}</a></div>
                                    @elseif($bucket === 'completed')
                                        <div class="trip-tile-line" style="color:#475569; font-weight:600;">Trip closed</div>
                                        <div class="trip-tile-sub">Completed journey</div>
                                    @elseif($cb->is_beyond_region && in_array($cb->status, ['Requested', 'Bidding'], true))
                                        <div class="trip-tile-line" style="color:#b45309; font-weight:700;">Availability Not Guaranteed</div>
                                        <div class="trip-tile-sub">Beyond service region — ride not confirmed until chauffeur accepts</div>
                                    @else
                                        <div class="trip-tile-line" style="color:#92400e; font-weight:700;">Allotment in progress</div>
                                        <div class="trip-tile-sub">Verified chauffeur assigned prior to pickup</div>
                                    @endif
                                </div>

                                @if($bucket === 'active')
                                <div class="trip-otp-tile" data-booking-part="otp">
                                    <div class="trip-otp-label">Ride Start OTP</div>
                                    <div class="trip-otp-code">{{ $cb->start_otp }}</div>
                                    <div class="trip-otp-hint">Share with chauffeur</div>
                                </div>
                                @elseif($bucket === 'completed')
                                <div class="trip-state-tile state-completed" data-booking-part="receipt status">
                                    <div class="trip-state-title">✓ Trip Completed</div>
                                    <div class="trip-state-sub">Thanks for riding with us</div>
                                </div>
                                @else
                                <div class="trip-state-tile state-cancelled" data-booking-part="status">
                                    <div class="trip-state-title">Cancelled</div>
                                    <div class="trip-state-sub">No active OTP</div>
                                </div>
                                @endif
                            </div>

                            <div class="trip-card-footer" style="flex-direction:column; align-items:stretch; gap:8px;">
                                @if($showCustomerUpiPanel)
                                <div data-booking-part="payment" style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 12px; font-size:12px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                                        <span style="font-weight:800; color:#92400e;">
                                            @if($cb->payment_status === 'driver_collect' || $cb->payment_collection_by === 'driver')
                                                Driver will collect ₹{{ number_format((float) $cb->balance_amount > 0 ? (float) $cb->balance_amount : (float) $cb->total_amount) }} on trip completion
                                            @elseif($cb->payment_status === 'pending_upi_verification')
                                                UPI UTR <code style="font-weight:800;">{{ $cb->upi_utr }}</code> — Waiting Admin Confirmation
                                            @else
                                                Pay ₹{{ number_format($upiPayableNow) }} to UPI ID: <code>{{ $custPaySettings['upi_id'] ?? 'intercitywheels@upi' }}</code>
                                            @endif
                                        </span>
                                        <span style="font-size:11px; color:#78350f;">UPI ID: <strong>{{ $custPaySettings['upi_id'] ?? 'intercitywheels@upi' }}</strong></span>
                                    </div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                        <input type="text" id="trip-utr-input-{{ $cb->booking_reference }}" class="form-control" value="{{ $cb->upi_utr }}" placeholder="Enter 12-digit UTR / Ref No." style="flex:1; min-width:170px; height:32px; font-size:12px; font-family:monospace; text-transform:uppercase;">
                                        <button type="button" class="btn btn-xs btn-primary" onclick="submitTripUpiUtr('{{ $cb->booking_reference }}')">
                                            <span>{{ $cb->upi_utr ? 'Update UTR' : 'Submit UTR' }}</span>
                                        </button>
                                        @if($cb->payment_collection_by !== 'driver' && ($custPaySettings['payment_show_cash'] ?? '1') === '1')
                                        <button type="button" class="btn btn-xs btn-outline" onclick="switchTripToDriverCollect('{{ $cb->booking_reference }}')">
                                            <span>Driver Collects on Completion</span>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                @endif
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                <span class="trip-footer-meta" data-booking-part="payment">
                                    @if($cb->is_beyond_region && in_array($cb->status, ['Requested', 'Bidding'], true))
                                        Pending Availability Check (Beyond Region)
                                    @elseif($cb->payment_status === 'pending_upi_verification')
                                        UTR {{ $cb->upi_utr }} pending Admin confirmation
                                    @elseif($cb->payment_mode === 'part_pay' && $cb->balance_amount > 0 && !$cb->driver_collected_at)
                                        Balance to driver: <strong>₹{{ number_format($cb->balance_amount) }}</strong>
                                    @elseif($cb->payment_collection_by === 'driver' && !$cb->driver_collected_at)
                                        Driver collects on completion: <strong>₹{{ number_format($cb->balance_amount > 0 ? $cb->balance_amount : $cb->total_amount) }}</strong>
                                    @elseif(is_array($cb->passengers) && count($cb->passengers) > 0)
                                        {{ count($cb->passengers) }} passenger(s)
                                    @else
                                        {{ $cb->payment_status === 'paid' && $cb->upi_utr ? 'UPI Verified ('.$cb->upi_utr.')' : 'Escrow Verified' }}
                                    @endif
                                </span>
                                <div class="trip-card-actions" data-booking-part="receipt">
                                    @if($cb->driver && $cb->driver->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $cb->driver->phone) ?: $cb->driver->phone }}" class="btn btn-xs btn-primary trip-call-driver-btn" style="text-decoration:none;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">call</span>
                                        <span>Call Driver ({{ $cb->driver->phone }})</span>
                                    </a>
                                    @endif
                                    <a href="{{ $custSupportTel }}" class="btn btn-xs trip-btn-secondary trip-call-support-btn" style="text-decoration:none;" title="24/7 Customer Support">
                                        <span class="material-symbols-outlined" style="font-size:14px;">support_agent</span>
                                        <span>Call Support</span>
                                    </a>
                                    <a href="mailto:{{ $custSupportEmail }}?subject={{ rawurlencode('Support for Booking '.$cb->booking_reference) }}" class="btn btn-xs trip-btn-secondary trip-email-support-btn" style="text-decoration:none;" title="Email Customer Support">
                                        <span class="material-symbols-outlined" style="font-size:14px;">mail</span>
                                        <span>Email Support</span>
                                    </a>
                                    @if($cb->payment_status === 'pending' && $cb->payment_gateway === 'razorpay')
                                    <button type="button" class="btn btn-xs btn-primary" onclick="retryRazorpayForBooking('{{ $cb->booking_reference }}')">
                                        <span class="material-symbols-outlined" style="font-size:14px;">payments</span>
                                        <span>Pay Now</span>
                                    </button>
                                    @endif
                                    <button type="button" class="btn btn-xs trip-btn-secondary" onclick="copyTripReference('{{ $cb->booking_reference }}')">
                                        <span class="material-symbols-outlined" style="font-size:14px;">content_copy</span>
                                        <span>Reference</span>
                                    </button>
                                    <button type="button" class="btn btn-xs trip-btn-primary" onclick="openTicketModal('{{ $cb->booking_reference }}')">
                                        <span class="material-symbols-outlined" style="font-size:14px;">confirmation_number</span>
                                        <span>{{ $bucket === 'active' ? 'Digital Boarding Pass' : 'View Trip Ticket' }}</span>
                                    </button>
                                </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="trips-empty" id="trips-empty" style="display:{{ $allTripCount === 0 ? 'block' : 'none' }};">
                        <span class="material-symbols-outlined" style="font-size:34px; color:#64748b; display:block; margin-bottom:6px;">directions_car</span>
                        <div id="trips-empty-title" style="font-weight:700; font-size:14px; color:#0f172a;">No trips booked yet</div>
                        <p id="trips-empty-copy" style="font-size:12px; color:#475569; margin:4px 0 12px;">Book an outstation cab or sleeper berth to see your upcoming and completed trips here.</p>
                        <button type="button" class="btn btn-sm btn-primary" onclick="switchTab('cabs')">Book Outstation Cab</button>
                    </div>
                @else
                    <div class="trips-guest-card" style="padding:18px 0;">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                            <div style="background:#0b0f19; color:#f8fafc; border-radius:10px; padding:18px; display:flex; flex-direction:column; justify-content:space-between; gap:12px;">
                                <div>
                                    <span style="display:inline-block; background:rgba(212,155,76,0.18); color:#f5d061; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:999px; letter-spacing:0.05em;">CUSTOMER PORTAL</span>
                                    <h3 style="font-size:16px; font-weight:800; color:#ffffff; margin:10px 0 4px;">Sign in to see your trip history</h3>
                                    <p style="font-size:12px; color:#cbd5e1; margin:0; line-height:1.45;">View upcoming rides, completed history, live chauffeur contact and boarding OTPs in one place.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-gold" onclick="openLoginModal('customer', 'signin')" style="align-self:flex-start;">
                                    <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                                    <span>Sign In / Register</span>
                                </button>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px;">
                                <h4 style="font-size:14px; font-weight:800; color:#0f172a; margin:0 0 4px;">Track a Booking Without Signing In</h4>
                                <p style="font-size:12px; color:#475569; margin:0 0 12px;">Enter your booking reference and mobile number used at checkout.</p>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                                    <input type="text" id="trips-track-reference" class="form-control" placeholder="Ref (e.g. ICW-AB12CD)" style="font-size:12.5px;">
                                    <input type="text" id="trips-track-phone" class="form-control" placeholder="Mobile Number" style="font-size:12.5px;">
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" onclick="trackGuestBooking()">
                                    <span class="material-symbols-outlined" style="font-size:15px;">search</span>
                                    <span>Track Booking</span>
                                </button>
                                <div id="trips-track-result" style="margin-top:10px;"></div>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </section>

        @if($userRole === 'driver' || $userRole === 'admin')
        <!-- ======================================================== -->
        <!-- TAB 3: DRIVER BIDDING MARKETPLACE & AUCTION              -->
        <!-- ======================================================== -->
        <section id="tab-bidding" class="tab-pane {{ $defaultTab === 'bidding' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'bidding'])

            <div class="driver-panel-card animate-load stagger-2">
                <div class="driver-panel-card-header">
                    <div>
                        <h3 class="driver-panel-card-title">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:21px;">hail</span>
                            <span>Available Outstation Trips for Driver Bidding</span>
                        </h3>
                        <p class="driver-panel-card-subtitle">Review route, distance &amp; submit a lower bid directly below. Customer contact &amp; exact addresses unlock only if you win the auction.</p>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="admin-badge badge-success" style="font-size:11px;">Live</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="refreshDriverAuctions()" title="Refresh live auctions">
                            <span class="material-symbols-outlined" style="font-size:15px;">refresh</span>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>
                <div class="driver-panel-card-body">
                    <div class="bidding-howto-grid">
                        <div class="bidding-howto-step">
                            <span class="admin-badge badge-primary" style="font-size:10px; padding:2px 7px; flex-shrink:0;">1</span>
                            <div><strong>Pick an Open Trip</strong><span class="step-sub">Trips stay open below until a driver is allotted.</span></div>
                        </div>
                        <div class="bidding-howto-step">
                            <span class="admin-badge badge-gold" style="font-size:10px; padding:2px 7px; flex-shrink:0;">2</span>
                            <div><strong>Bid Lower to Lead</strong><span class="step-sub">Agree to T&amp;C and bid within the allowed floor &amp; cap.</span></div>
                        </div>
                        <div class="bidding-howto-step">
                            <span class="admin-badge badge-success" style="font-size:10px; padding:2px 7px; flex-shrink:0;">3</span>
                            <div><strong>Win &amp; Unlock</strong><span class="step-sub">Allotted trips move to My Assigned Trips with details.</span></div>
                        </div>
                    </div>

                    <div class="admin-trip-type-filters" id="driver-bidding-status-filters" role="group" aria-label="Filter auctions by bid status" style="border:1px solid var(--outline); border-radius:10px; margin-bottom:14px; padding:8px 12px; justify-content:space-between;">
                        <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                            <span class="admin-trip-type-label">Status:</span>
                            <button type="button" class="admin-trip-type-btn active" data-bid-filter="all" onclick="setDriverAuctionFilter('all', this)">
                                All <span id="bidding-count-all">(0)</span>
                            </button>
                            <button type="button" class="admin-trip-type-btn" data-bid-filter="fresh" onclick="setDriverAuctionFilter('fresh', this)">
                                Fresh (No Bids) <span id="bidding-count-fresh">(0)</span>
                            </button>
                            <button type="button" class="admin-trip-type-btn" data-bid-filter="active_bids" onclick="setDriverAuctionFilter('active_bids', this)">
                                Bid Placed <span id="bidding-count-active">(0)</span>
                            </button>
                            <button type="button" class="admin-trip-type-btn" data-bid-filter="my_bids" onclick="setDriverAuctionFilter('my_bids', this)">
                                My Bids <span id="bidding-count-mine">(0)</span>
                            </button>
                            <button type="button" class="admin-trip-type-btn" data-bid-filter="allotted" onclick="setDriverAuctionFilter('allotted', this)">
                                Allotted <span id="bidding-count-allotted">(0)</span>
                            </button>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">
                            <button type="button" class="admin-trip-type-btn active" data-bid-view="table" onclick="setDriverAuctionView('table', this)" title="Compact Table View">Table</button>
                            <button type="button" class="admin-trip-type-btn" data-bid-view="cards" onclick="setDriverAuctionView('cards', this)" title="Compact Card View">Cards</button>
                        </div>
                    </div>

                    <div class="bidding-cards-list" id="bidding-cards-stream"></div>
                </div>
            </div>

            <!-- Driver Registered Vehicles & Approval Status -->
            <div class="card driver-panel-card animate-load stagger-3" style="margin-top:16px; margin-bottom:16px; padding:14px 16px; border:1px solid #e2e8f0; background:#ffffff;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">garage</span>
                        <span style="font-family:var(--font-display); font-weight:800; font-size:14px; color:#0f172a;">My Registered Cars ({{ count($driverMyVehicles) }})</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="openDriverListVehicleModal()">
                        <span class="material-symbols-outlined" style="font-size:14px;">add</span>
                        <span>Add Car</span>
                    </button>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:10px;" id="driver-my-vehicles-grid">
                    @forelse($driverMyVehicles as $v)
                        <div id="driver-my-veh-card-{{ $v->id }}" class="driver-compact-item-card">
                            <div style="min-width:0; flex:1;">
                                <div style="font-weight:700; font-size:13px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="driver-veh-title-{{ $v->id }}">{{ $v->vehicle_model }}</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <span style="font-family:monospace; font-weight:700; color:#8c5d1e;">{{ $v->vehicle_plate }}</span>
                                    @if($v->status === 'Approved')
                                        <span class="admin-badge badge-success" style="font-size:10px; padding:2px 7px;">Approved</span>
                                    @elseif($v->status === 'Pending')
                                        <span class="admin-badge badge-warning" style="font-size:10px; padding:2px 7px;">Pending</span>
                                    @else
                                        <span class="admin-badge badge-danger" style="font-size:10px; padding:2px 7px;">Rejected</span>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline driver-item-details-btn"
                                onclick='openDriverVehicleDetails(@json($v))'>
                                <span>Details</span>
                            </button>
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1; text-align:center; padding:16px; color:#94a3b8; font-size:12px;">
                            No cars registered yet. Click <strong>"Add Car"</strong> to submit your vehicle for admin approval.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: DRIVER MY ASSIGNED TRIPS & OTP VERIFICATION        -->
        <!-- ======================================================== -->
        <section id="tab-driver-trips" class="tab-pane {{ $defaultTab === 'driver-trips' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'driver-trips'])

            @php
                $assignedTripList = collect($driverTrips ?? []);
                $pendingStartCount = $assignedTripList->whereIn('status', ['Driver Assigned', 'Confirmed'])->count();
                $inProgressCount = $assignedTripList->where('status', 'Started')->count();
                $completedTripCount = $assignedTripList->where('status', 'Completed')->count();
            @endphp

            <div class="driver-panel-card animate-load stagger-2">
                <div class="driver-panel-card-header">
                    <div>
                        <h2 class="driver-panel-card-title">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:21px;">local_taxi</span>
                            <span>My Assigned Outstation Rides</span>
                        </h2>
                        <p class="driver-panel-card-subtitle">Active dispatch queue: verify passenger ride start OTP, track active rides &amp; settle wallet payouts.</p>
                    </div>
                    <span id="driver-trips-active-count-badge" class="admin-badge badge-success" style="font-size:11px;">
                        {{ $assignedTripList->count() }} Active Rides
                    </span>
                </div>

                <div class="driver-panel-card-body">
                    <div id="driver-trips-queue-screen">
                        <div class="admin-trip-type-filters" id="driver-trips-status-filters" role="group" aria-label="Filter assigned rides by status" style="border:1px solid var(--outline); border-radius:10px; margin-bottom:14px; padding:8px 12px; justify-content:space-between;">
                            <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                <span class="admin-trip-type-label">Status:</span>
                                <button type="button" class="admin-trip-type-btn active" data-trip-filter="all" onclick="setDriverTripFilter('all', this)">
                                    All ({{ $assignedTripList->count() }})
                                </button>
                                <button type="button" class="admin-trip-type-btn" data-trip-filter="assigned" onclick="setDriverTripFilter('assigned', this)">
                                    Pending Start ({{ $pendingStartCount }})
                                </button>
                                <button type="button" class="admin-trip-type-btn" data-trip-filter="started" onclick="setDriverTripFilter('started', this)">
                                    In Progress ({{ $inProgressCount }})
                                </button>
                                <button type="button" class="admin-trip-type-btn" data-trip-filter="completed" onclick="setDriverTripFilter('completed', this)">
                                    Completed ({{ $completedTripCount }})
                                </button>
                            </div>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <button type="button" class="admin-trip-type-btn active" data-trip-view="table" onclick="setDriverTripView('table', this)" title="Compact Table View">Table</button>
                                <button type="button" class="admin-trip-type-btn" data-trip-view="cards" onclick="setDriverTripView('cards', this)" title="Compact Card View">Cards</button>
                            </div>
                        </div>

                        <div id="driver-trips-empty-state" style="display:{{ $assignedTripList->isEmpty() ? 'block' : 'none' }}; text-align:center; padding:32px 20px; color:var(--text-muted); background:var(--surface-base); border:1px solid var(--outline); border-radius:10px;">
                            <span class="material-symbols-outlined" style="font-size:38px; color:#cbd5e1; display:block; margin-bottom:8px;">local_taxi</span>
                            <div style="font-weight:700; font-size:14px; color:var(--text-main);">No assigned rides in your queue currently</div>
                            <p style="font-size:12px; margin:4px 0 14px;">Switch to the &ldquo;Live Bids &amp; Auctions&rdquo; tab to bid on upcoming passenger outstation trips.</p>
                            <button type="button" class="btn btn-sm btn-primary" onclick="switchTab('bidding')">
                                <span>Open Bidding Marketplace</span>
                            </button>
                        </div>

                        <div class="auction-table-wrap driver-trips-view" id="driver-trips-view-wrap" style="display:{{ $assignedTripList->isEmpty() ? 'none' : 'block' }};">
                            <table class="admin-data-table admin-table-compact">
                                <thead>
                                    <tr>
                                        <th>When &amp; Booking</th>
                                        <th>Where to Pick Up &amp; Whom</th>
                                        <th>Status</th>
                                        <th>Bid / Payout</th>
                                        <th>Dispatch &amp; Start OTP</th>
                                        <th style="text-align:right;">Screen View</th>
                                    </tr>
                                </thead>
                                <tbody id="driver-trips-table-body">
                                    @foreach($assignedTripList as $dt)
                                        @php
                                            $tripBucket = $dt->status === 'Started'
                                                ? 'started'
                                                : ($dt->status === 'Completed' ? 'completed' : 'assigned');
                                            $driverCollectDue = 0.0;
                                            if ($dt->driver_collected_at === null) {
                                                if ($dt->payment_mode === 'part_pay' && (float) $dt->balance_amount > 0) {
                                                    $driverCollectDue = (float) $dt->balance_amount;
                                                } elseif (
                                                    $dt->payment_collection_by === 'driver'
                                                    || in_array($dt->payment_status, ['pay_on_pickup', 'driver_collect', 'upi_rejected', 'pending_upi_verification', 'awaiting_upi_utr'], true)
                                                ) {
                                                    $driverCollectDue = (float) ($dt->balance_amount > 0 ? $dt->balance_amount : $dt->total_amount);
                                                }
                                            }
                                            $isUpiPendingAdmin = in_array($dt->payment_status, ['pending_upi_verification', 'awaiting_upi_utr'], true);
                                            $whenText = $dt->departure_time
                                                ? $dt->departure_time->format('D, d M Y · h:i A')
                                                : ($dt->trip_date ? \Carbon\Carbon::parse($dt->trip_date)->format('d M Y').($dt->trip_time ? ' · '.$dt->trip_time : '') : 'Scheduled');
                                            $custTelClean = preg_replace('/[^0-9+]/', '', (string) $dt->customer_phone) ?: (string) $dt->customer_phone;
                                        @endphp
                                        <tr class="driver-trip-row" id="driver-trip-box-{{ $dt->id }}" data-booking-ref="{{ $dt->booking_reference }}" data-trip-bucket="{{ $tripBucket }}">
                                            <td>
                                                <span class="booking-ref-link">{{ $dt->booking_reference }}</span>
                                                <div class="cell-primary-text" style="font-size:11.5px; margin-top:2px;">{{ $whenText }}</div>
                                            </td>
                                            <td data-booking-part="trip">
                                                <div class="cell-primary-text">{{ $dt->route_summary }}</div>
                                                @if($dt->assigned_driver_id && $dt->status !== 'Cancelled')
                                                    <div class="cell-subtext" style="font-size:11.5px; color:#0f172a; margin-top:2px;">
                                                        <strong>Pickup:</strong> {{ $dt->pickup_location }} &rarr; <strong>Drop:</strong> {{ $dt->drop_location }}
                                                    </div>
                                                    <div class="cell-subtext" style="margin-top:3px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                        <strong style="color:var(--text-main);">{{ $dt->customer_name }}</strong>
                                                        <a href="tel:{{ $custTelClean }}" class="btn btn-xs btn-outline" style="min-height:24px; padding:0 8px; text-decoration:none; color:#0284c7; border-color:#bae6fd; font-weight:700;">
                                                            <span class="material-symbols-outlined" style="font-size:13px;">call</span>
                                                            <span>{{ $dt->customer_phone }}</span>
                                                        </a>
                                                        @if($dt->driver_payout_amount !== null)
                                                            <span class="admin-badge badge-success" style="font-size:9.5px; padding:1px 6px;">Won via driver bidding</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="cell-subtext">Customer name &amp; contact unlock once allotted.</div>
                                                @endif
                                            </td>
                                            <td data-booking-part="status">
                                                <span class="admin-badge @if($dt->status === 'Confirmed') badge-success @elseif($dt->status === 'Driver Assigned') badge-gold @elseif($dt->status === 'Started') badge-info @elseif($dt->status === 'Completed') badge-primary @else badge-secondary @endif" id="driver-trip-status-badge-{{ $dt->id }}">
                                                    {{ $dt->status }}
                                                </span>
                                            </td>
                                            <td data-booking-part="payment">
                                                @if($dt->driver_payout_amount !== null)
                                                    <div class="cell-primary-text text-tabular" style="color:var(--success);">₹{{ number_format((float) $dt->driver_payout_amount) }}</div>
                                                    <div class="cell-subtext">Your winning bid payout</div>
                                                @else
                                                    <div class="cell-primary-text text-tabular" style="color:var(--success);">₹{{ number_format($dt->total_amount) }}</div>
                                                    <div class="cell-subtext">All-inclusive</div>
                                                @endif
                                                @if($dt->driver_collected_at !== null)
                                                    <div class="cell-subtext" style="color:#16a34a; font-weight:700;">Collected ₹{{ number_format((float) $dt->driver_collected_amount) }}</div>
                                                @elseif($driverCollectDue > 0)
                                                    <div class="cell-subtext" style="color:{{ $isUpiPendingAdmin ? '#b45309' : '#d97706' }}; font-weight:700;">
                                                        {{ $isUpiPendingAdmin ? 'UPI Unconfirmed · Collect ₹'.number_format($driverCollectDue).' if unpaid' : 'Collect ₹'.number_format($driverCollectDue).' on Completion' }}
                                                    </div>
                                                @else
                                                    <div class="cell-subtext" style="color:#15803d; font-weight:700;">Prepaid (Confirmed)</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div id="driver-trip-actions-{{ $dt->id }}" style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                                    @if($dt->status === 'Driver Assigned' || $dt->status === 'Confirmed')
                                                        <button type="button" class="btn btn-sm btn-primary" style="min-height:32px; padding:0 12px; font-size:11.5px;" onclick='openDriverTripScreen(@json($dt))'>
                                                            <span class="material-symbols-outlined" style="font-size:15px;">pin</span>
                                                            <span>Pickup &amp; Enter OTP</span>
                                                        </button>
                                                    @elseif($dt->status === 'Started')
                                                        <button type="button" class="btn btn-sm btn-accent" style="min-height:32px; padding:0 12px; font-size:11.5px;" onclick='openDriverTripScreen(@json($dt))'>
                                                            <span class="material-symbols-outlined" style="font-size:15px;">flag</span>
                                                            <span>{{ $driverCollectDue > 0 ? 'Collect ₹'.number_format($driverCollectDue).' & Complete' : 'Complete Trip & Settle' }}</span>
                                                        </button>
                                                    @else
                                                        <span style="color:var(--success); font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                                            <span class="material-symbols-outlined" style="font-size:15px;">verified</span>
                                                            <span>Trip completed &amp; settled.</span>
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <button type="button" class="btn btn-sm btn-outline driver-item-details-btn" style="min-height:30px; padding:0 10px !important;" onclick='openDriverTripScreen(@json($dt))'>
                                                    <span class="material-symbols-outlined" style="font-size:14px;">open_in_new</span>
                                                    <span>Open Screen</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr id="driver-trips-filter-empty" style="display:none;">
                                        <td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted); font-size:12.5px;">
                                            No rides match this status filter.
                                            <button type="button" class="btn btn-sm btn-outline" style="margin-left:8px;" onclick="setDriverTripFilter('all')">Show All</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SCREEN 2: DEDICATED TRIP DISPATCH, CALL CUSTOMER & PROMINENT OTP ENTRY -->
                    <div id="driver-trip-dispatch-screen" hidden style="display:none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding-bottom:12px; margin-bottom:14px; border-bottom:1px solid var(--outline);">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="closeDriverTripScreen()" style="min-height:38px;">
                                    <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span>
                                    <span>Back to All Trips</span>
                                </button>
                                <div>
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <strong id="dispatch-trip-ref" style="font-family:monospace; font-size:14px; color:#0f172a;">ICW-0000</strong>
                                        <span id="dispatch-trip-status-badge" class="admin-badge badge-gold">Driver Assigned</span>
                                    </div>
                                    <div id="dispatch-trip-route" style="font-size:12.5px; color:#475569; font-weight:600;">Route</div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Your Bid / Payout</div>
                                <div id="dispatch-trip-payout" style="font-size:18px; font-weight:800; color:#15803d;">₹0</div>
                            </div>
                        </div>

                        <!-- Step-wise Dispatch Progress -->
                        <nav aria-label="Trip Dispatch Steps" style="margin-bottom:16px;">
                            <ol style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; list-style:none; padding:0; margin:0;">
                                <li id="dispatch-step-1" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-size:11.5px; font-weight:700; color:#334155;">1. Pickup &amp; Call Customer</li>
                                <li id="dispatch-step-2" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-size:11.5px; font-weight:700; color:#334155;">2. Verify Start OTP</li>
                                <li id="dispatch-step-3" style="padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-size:11.5px; font-weight:700; color:#334155;">3. Collect &amp; Complete</li>
                            </ol>
                        </nav>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; margin-bottom:16px;">
                            <!-- WHEN & WHERE TO PICK UP -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px;">
                                <div style="font-size:11px; font-weight:800; color:#b45309; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">When &amp; Where to Pick Up</div>
                                <div style="margin-bottom:10px;">
                                    <div style="font-size:11px; color:#64748b;">Pickup Date &amp; Time</div>
                                    <div id="dispatch-trip-when" style="font-size:15px; font-weight:800; color:#0f172a;">Scheduled</div>
                                </div>
                                <div style="margin-bottom:8px;">
                                    <div style="font-size:11px; color:#64748b;">Exact Pickup Location</div>
                                    <div id="dispatch-trip-pickup" style="font-size:13.5px; font-weight:700; color:#0f172a; line-height:1.4;">—</div>
                                </div>
                                <div>
                                    <div style="font-size:11px; color:#64748b;">Drop Destination</div>
                                    <div id="dispatch-trip-drop" style="font-size:13px; font-weight:600; color:#334155; line-height:1.4;">—</div>
                                </div>
                            </div>

                            <!-- WHOM TO PICK UP & CALL CUSTOMER -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; flex-direction:column; justify-content:space-between; gap:10px;">
                                <div>
                                    <div style="font-size:11px; font-weight:800; color:#0284c7; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Passenger Contact &amp; Collection Duty</div>
                                    <div id="dispatch-trip-customer-name" style="font-size:15px; font-weight:800; color:#0f172a;">Passenger</div>
                                    <div id="dispatch-trip-customer-phone" style="font-size:13px; font-weight:700; color:#475569; margin-top:2px;">—</div>
                                    <div id="dispatch-trip-collection-summary" style="margin-top:10px; padding:8px 10px; border-radius:8px; background:#fffbeb; border:1px solid #fde68a; font-size:12px; font-weight:700; color:#92400e;">—</div>
                                </div>
                                <a id="dispatch-call-customer-btn" href="#" class="btn btn-primary" style="width:100%; min-height:48px; justify-content:center; text-decoration:none; font-size:14px; font-weight:800;">
                                    <span class="material-symbols-outlined" style="font-size:19px;">call</span>
                                    <span>Call Customer for Exact Location</span>
                                </a>
                            </div>
                        </div>

                        <!-- PROMINENT OTP ENTRY PANEL (Step 2: Driver Assigned / Confirmed) -->
                        <div id="dispatch-otp-card" style="display:none; background:linear-gradient(135deg, #fffbeb 0%, #ffffff 100%); border:2px solid #d49b4c; border-radius:14px; padding:18px; text-align:center;">
                            <div style="font-size:11.5px; font-weight:800; color:#b45309; text-transform:uppercase; letter-spacing:0.06em;">Step 2 • Start Ride Verification</div>
                            <h3 style="font-size:17px; font-weight:800; color:#0f172a; margin:4px 0 6px;">Enter Passenger 4-Digit Ride Start OTP</h3>
                            <p id="driver-dispatch-otp-hint" style="font-size:12.5px; color:#475569; margin:0 auto 14px; max-width:420px;">
                                Confirm you have met the passenger at pickup and ask for their 4-digit Ride Start OTP to begin the trip.
                            </p>
                            <form id="driver-dispatch-otp-form" method="POST" onsubmit="submitDispatchStartOtp(event)" style="max-width:340px; margin:0 auto;">
                                <label for="driver-dispatch-otp-input" style="position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%);">4-Digit Ride Start OTP</label>
                                <input type="text" id="driver-dispatch-otp-input" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="\d{4}" maxlength="4" aria-describedby="driver-dispatch-otp-hint" placeholder="••••" required style="width:100%; min-height:56px; font-family:ui-monospace, monospace; font-size:24px; font-weight:800; letter-spacing:0.45em; text-align:center; border:2px solid #0f172a; border-radius:12px; background:#ffffff; color:#0f172a; margin-bottom:12px; box-sizing:border-box;" oninput="this.removeAttribute('aria-invalid')">
                                <button type="submit" id="driver-dispatch-otp-submit" class="btn btn-primary" style="width:100%; min-height:48px; justify-content:center; font-size:14.5px; font-weight:800;">
                                    <span class="material-symbols-outlined" style="font-size:19px;">check_circle</span>
                                    <span>Verify OTP &amp; Start Trip</span>
                                </button>
                            </form>
                        </div>

                        <!-- ACTIVE TRIP COMPLETION PANEL (Step 3: Started) -->
                        <div id="dispatch-active-card" style="display:none; background:#eff6ff; border:2px solid #3b82f6; border-radius:14px; padding:18px; text-align:center;">
                            <div style="font-size:11.5px; font-weight:800; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.06em;">Step 3 • Ride In Progress</div>
                            <h3 style="font-size:17px; font-weight:800; color:#0f172a; margin:4px 0 8px;">Trip Started — Ready for Drop-Off Settlement</h3>
                            <p id="dispatch-active-collect-note" style="font-size:13px; color:#1e3a8a; margin:0 auto 14px; max-width:460px; font-weight:600;">—</p>
                            <button type="button" id="dispatch-complete-trip-btn" class="btn btn-accent" style="min-height:48px; padding:0 24px; font-size:14.5px; font-weight:800;">
                                <span class="material-symbols-outlined" style="font-size:19px;">flag</span>
                                <span id="dispatch-complete-trip-label">Complete Trip &amp; Settle</span>
                            </button>
                        </div>

                        <!-- COMPLETED TRIP SUMMARY PANEL -->
                        <div id="dispatch-completed-card" style="display:none; background:#f0fdf4; border:1.5px solid #86efac; border-radius:14px; padding:18px; text-align:center;">
                            <div style="font-size:12px; font-weight:800; color:#15803d; text-transform:uppercase;">✓ Trip Completed &amp; Settled</div>
                            <p id="dispatch-completed-note" style="font-size:13px; color:#166534; margin:6px 0 14px;">Payout and collection details are recorded in your Payment History.</p>
                            <button type="button" class="btn btn-primary" style="min-height:44px;" onclick="switchTab('driver-wallet')">
                                <span class="material-symbols-outlined" style="font-size:17px;">account_balance_wallet</span>
                                <span>View in Wallet &amp; Payment History</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB: DRIVER WALLET & PAYMENT HISTORY CONSOLE             -->
        <!-- ======================================================== -->
        @php
            $payHistoryData = $driverPaymentHistory ?? app(\App\Services\DriverPaymentHistoryService::class)->buildForDriver($driverProfile ?? null);
            $paySummary = $payHistoryData['summary'] ?? [];
            $tripPayRecords = $payHistoryData['trip_records'] ?? [];
            $ledgerRecords = $payHistoryData['transactions'] ?? [];
        @endphp
        <section id="tab-driver-wallet" class="tab-pane {{ $defaultTab === 'driver-wallet' ? 'active' : '' }}">
            <!-- Chauffeur Master Header & Navigation -->
            @include('partials.driver_header', ['activeDriverTab' => 'driver-wallet'])

            <div class="driver-panel-card animate-load stagger-2">
                <div class="driver-panel-card-header">
                    <div>
                        <h2 class="driver-panel-card-title">
                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:21px;">account_balance_wallet</span>
                            <span>Chauffeur Wallet &amp; Payment History</span>
                        </h2>
                        <p class="driver-panel-card-subtitle">Trip-wise payment parts, winning bid payouts, customer collections &amp; verified ledger settlements.</p>
                    </div>
                    <span class="admin-badge badge-success" style="font-size:11px;">IMPS Active</span>
                </div>

                <div class="driver-panel-card-body">
                    <!-- Uniform Metric Cells matching Auction Grid -->
                    <div class="auction-metrics-grid" style="margin-bottom:14px;">
                        <div class="auction-metric-cell">
                            <div class="auction-metric-label">Available Wallet Balance</div>
                            <div class="auction-metric-value success" id="driver-wallet-bal-display">₹{{ number_format((float) ($paySummary['wallet_balance'] ?? ($driverProfile->wallet_balance ?? 0))) }}</div>
                            <div class="cell-subtext">Available for instant withdrawal</div>
                        </div>
                        <div class="auction-metric-cell">
                            <div class="auction-metric-label">Collected from Customers</div>
                            <div class="auction-metric-value" id="driver-wallet-collected-display" style="color:#0284c7;">₹{{ number_format((float) ($paySummary['total_driver_collected'] ?? 0)) }}</div>
                            <div class="cell-subtext">Cash / UPI received directly</div>
                        </div>
                        <div class="auction-metric-cell">
                            <div class="auction-metric-label">Total Bid / Trip Earnings</div>
                            <div class="auction-metric-value gold" id="driver-wallet-earnings-display">₹{{ number_format((float) ($paySummary['total_driver_earnings'] ?? 0)) }}</div>
                            <div class="cell-subtext">Earned across completed rides</div>
                        </div>
                        <div class="auction-metric-cell">
                            <div class="auction-metric-label">Completed Rides &amp; Rating</div>
                            <div class="auction-metric-value" id="driver-wallet-trips-display">{{ (int) ($paySummary['trips_count'] ?? ($driverProfile->trips_count ?? 0)) }} Trips</div>
                            <div class="cell-subtext">★{{ $paySummary['rating'] ?? ($driverProfile->rating ?? '4.90') }} top-rated partner</div>
                        </div>
                    </div>

                    @php
                        $payoutProfile = $payHistoryData['payout_profile'] ?? [];
                        $payoutReqs = $payHistoryData['payout_requests'] ?? [];
                        $prefMethod = $payoutProfile['preferred_payout_method'] ?? ($driverProfile->preferred_payout_method ?? 'upi');
                        $hasUpiCfg = ! empty($payoutProfile['has_upi']);
                        $hasBankCfg = ! empty($payoutProfile['has_bank']);
                        $availWalletBal = (float) ($paySummary['wallet_balance'] ?? ($driverProfile->wallet_balance ?? 0));
                    @endphp
                    <!-- Request Payout Card (Deducts Wallet & Sends for Admin Approval) -->
                    <div style="background:#f8fafc; border:1px solid var(--outline); border-radius:12px; padding:14px 16px; margin-bottom:18px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                            <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:22px; flex-shrink:0;">account_balance</span>
                                <div>
                                    <div style="font-weight:800; font-size:13.5px; color:var(--text-main);">Request Wallet Payout (Admin Approval Required)</div>
                                    <div id="driver-wallet-payout-dest-summary" class="cell-subtext">{{ $payoutProfile['summary_label'] ?? ($driverProfile ? $driverProfile->payoutSummaryLabel() : 'Configure UPI or Bank in Driver Profile') }}</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline" onclick="switchTab('driver-profile')" style="min-height:32px;">
                                <span class="material-symbols-outlined" style="font-size:14px;">settings</span>
                                <span>Manage UPI / Bank in Profile</span>
                            </button>
                        </div>

                        <form id="driver-wallet-withdraw-form" onsubmit="submitDriverPayoutRequest(event)" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:10px; align-items:end; accent-color:#0f172a;">
                            <fieldset style="border:0; padding:0; margin:0;">
                                <legend style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px; padding:0;">Payout Method</legend>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <label for="withdraw-method-upi" style="display:flex; align-items:center; gap:6px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#ffffff; cursor:pointer; font-size:12.5px; min-height:42px; flex:1;">
                                        <input type="radio" id="withdraw-method-upi" name="driver_withdraw_method" value="upi" {{ $prefMethod === 'upi' ? 'checked' : '' }} style="width:16px; height:16px;">
                                        <span>UPI {{ $hasUpiCfg ? '('.($payoutProfile['payout_upi_id'] ?? '').')' : '(Not set)' }}</span>
                                    </label>
                                    <label for="withdraw-method-bank" style="display:flex; align-items:center; gap:6px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#ffffff; cursor:pointer; font-size:12.5px; min-height:42px; flex:1;">
                                        <input type="radio" id="withdraw-method-bank" name="driver_withdraw_method" value="bank" {{ $prefMethod === 'bank' ? 'checked' : '' }} style="width:16px; height:16px;">
                                        <span>Bank {{ $hasBankCfg ? '('.($payoutProfile['bank_name'] ?? 'Bank').')' : '(Not set)' }}</span>
                                    </label>
                                </div>
                            </fieldset>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="driver-withdraw-amount" style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase;">Amount (Min ₹100)</label>
                                <input type="number" id="driver-withdraw-amount" class="form-control" min="100" step="1" value="{{ $availWalletBal >= 100 ? (int) floor($availWalletBal) : '' }}" placeholder="Enter ₹ amount" style="min-height:42px; font-weight:700;" required>
                            </div>
                            <button type="submit" id="driver-payout-withdraw-btn" class="btn btn-primary" data-wallet-balance="{{ $availWalletBal }}" style="min-height:42px; justify-content:center; font-weight:800;">
                                <span class="material-symbols-outlined" style="font-size:16px;">payments</span>
                                <span>Submit Payout Request</span>
                            </button>
                        </form>
                    </div>

                    <!-- Payout Requests & Admin Approval Status -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <div>
                            <span id="driver-payout-requests-caption" style="font-family:var(--font-display); font-weight:800; font-size:13.5px; color:var(--text-main);">Payout Requests &amp; Approval Status</span>
                            <div class="cell-subtext">Tracks wallet deduction, Admin approval, and Manual or Gateway API transfer reference</div>
                        </div>
                        <span class="cell-subtext" id="driver-payout-requests-count">{{ count($payoutReqs) }} requests</span>
                    </div>
                    <div tabindex="0" role="region" aria-labelledby="driver-payout-requests-caption" class="driver-responsive-table-wrap" style="margin-bottom:18px;">
                        <table class="admin-data-table admin-table-compact driver-responsive-table">
                            <caption>Driver payout requests and Admin processing status</caption>
                            <thead>
                                <tr>
                                    <th>Request Ref</th>
                                    <th>Destination</th>
                                    <th>Amount</th>
                                    <th>Status &amp; Mode</th>
                                    <th>Transaction Ref &amp; Notes</th>
                                </tr>
                            </thead>
                            <tbody id="driver-payout-requests-body">
                                @forelse($payoutReqs as $pr)
                                    <tr>
                                        <th>
                                            <div class="booking-ref-link">{{ $pr['payout_reference'] }}</div>
                                            <div class="cell-subtext">{{ $pr['requested_at_label'] ?? '—' }}</div>
                                        </th>
                                        <td data-col-label="Destination">
                                            <div class="cell-primary-text">{{ strtoupper($pr['payout_method'] ?? 'bank') }}</div>
                                            <div class="cell-subtext">{{ $pr['destination_summary'] }}</div>
                                        </td>
                                        <td data-col-label="Amount" class="text-tabular">
                                            <strong>₹{{ number_format((float) $pr['amount']) }}</strong>
                                        </td>
                                        <td data-col-label="Status & Mode">
                                            @if($pr['status'] === 'paid')
                                                <span class="admin-badge badge-success" style="font-size:10px;">PAID ({{ strtoupper($pr['payout_mode'] ?? 'manual') }})</span>
                                            @elseif($pr['status'] === 'rejected')
                                                <span class="admin-badge badge-danger" style="font-size:10px;">REJECTED &amp; REFUNDED</span>
                                            @else
                                                <span class="admin-badge badge-warning" style="font-size:10px;">PENDING ADMIN APPROVAL</span>
                                            @endif
                                        </td>
                                        <td data-col-label="Transaction Ref & Notes">
                                            @if(!empty($pr['transaction_reference']))
                                                <div class="cell-primary-text" style="font-family:monospace;">Ref: {{ $pr['transaction_reference'] }}</div>
                                            @endif
                                            <div class="cell-subtext">{{ $pr['admin_notes'] ?? ($pr['status'] === 'pending' ? 'Deducted from wallet; awaiting Admin review.' : 'Processed') }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:18px; color:var(--text-muted); font-size:12px;">
                                            No payout requests submitted yet. Save your UPI or Bank details in Profile and request a withdrawal above.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Trip-Wise Payment Parts & Bidding Breakdown -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <div>
                            <span id="driver-trip-pay-caption" style="font-family:var(--font-display); font-weight:800; font-size:13.5px; color:var(--text-main);">Trip-Wise Payment Part Record</span>
                            <div class="cell-subtext">Shows your bidding amount, customer collections, online parts paid, and wallet reconciliation per trip</div>
                        </div>
                        <span class="cell-subtext" id="driver-trip-pay-count">{{ count($tripPayRecords) }} trips</span>
                    </div>
                    <div tabindex="0" role="region" aria-labelledby="driver-trip-pay-caption" class="driver-responsive-table-wrap" id="driver-trip-payment-history-wrap" style="margin-bottom:18px;">
                        <table class="admin-data-table admin-table-compact driver-responsive-table">
                            <caption>Trip-wise payment part records, bidding amount, and collections</caption>
                            <thead>
                                <tr>
                                    <th>Trip &amp; Mode</th>
                                    <th>Fare &amp; Bid Amount</th>
                                    <th>Payment Parts Received</th>
                                    <th>Clarification</th>
                                </tr>
                            </thead>
                            <tbody id="driver-trip-payment-history-body">
                                @forelse($tripPayRecords as $tr)
                                    <tr>
                                        <th>
                                            <div class="booking-ref-link">{{ $tr['booking_reference'] }}</div>
                                            <div class="cell-primary-text" style="font-size:12px;">{{ $tr['route_summary'] }}</div>
                                            <div class="cell-subtext">{{ $tr['payment_mode_label'] }} &bull; {{ $tr['status'] }}</div>
                                        </th>
                                        <td data-col-label="Fare & Bid Amount" class="text-tabular">
                                            <div class="cell-primary-text">Fare: ₹{{ number_format((float) $tr['total_fare']) }}</div>
                                            @if($tr['is_bid_won'] && $tr['bidding_amount'] !== null)
                                                <div style="margin-top:3px;">
                                                    <span class="admin-badge badge-success" style="font-size:9.5px; padding:1px 6px;">Winning Bid: ₹{{ number_format((float) $tr['bidding_amount']) }}</span>
                                                </div>
                                            @else
                                                <div class="cell-subtext">Standard Payout (90%): ₹{{ number_format((float) $tr['driver_payout']) }}</div>
                                            @endif
                                        </td>
                                        <td data-col-label="Payment Parts Received">
                                            <div class="driver-part-pills">
                                                <span class="driver-part-chip">
                                                    <span>Online to Platform</span>
                                                    <strong>₹{{ number_format((float) $tr['platform_received_amount']) }}</strong>
                                                </span>
                                                @if((float) $tr['driver_collected_amount'] > 0)
                                                    <span class="driver-part-chip is-collected">
                                                        <span>Collected by You</span>
                                                        <strong>₹{{ number_format((float) $tr['driver_collected_amount']) }}</strong>
                                                    </span>
                                                @elseif((float) $tr['pending_driver_collection'] > 0)
                                                    <span class="driver-part-chip is-debit">
                                                        <span>Due from Customer</span>
                                                        <strong>₹{{ number_format((float) $tr['pending_driver_collection']) }}</strong>
                                                    </span>
                                                @endif
                                                @if($tr['status'] === 'Completed')
                                                    @if((float) $tr['wallet_credited_part'] > 0)
                                                        <span class="driver-part-chip is-credit">
                                                            <span>Wallet Credited</span>
                                                            <strong>+₹{{ number_format((float) $tr['wallet_credited_part']) }}</strong>
                                                        </span>
                                                    @elseif((float) $tr['wallet_debited_part'] > 0)
                                                        <span class="driver-part-chip is-debit">
                                                            <span>Platform Margin Settled</span>
                                                            <strong>-₹{{ number_format((float) $tr['wallet_debited_part']) }}</strong>
                                                        </span>
                                                    @else
                                                        <span class="driver-part-chip">
                                                            <span>Wallet Adjustment</span>
                                                            <strong>₹0 (Exact Match)</strong>
                                                        </span>
                                                    @endif
                                                    <span class="driver-part-chip is-collected">
                                                        <span>Total Part Received</span>
                                                        <strong>₹{{ number_format((float) $tr['total_received_by_driver']) }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td data-col-label="Clarification" style="max-width:290px;">
                                            <div style="font-size:11.5px; color:#334155; line-height:1.45;">{{ $tr['clarification'] }}</div>
                                            @if(!empty($tr['driver_collected_at_label']))
                                                <div class="cell-subtext" style="margin-top:4px; color:#15803d;">Collected on {{ $tr['driver_collected_at_label'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding:22px; color:var(--text-muted); font-size:12px;">
                                            Trip-wise payment parts, bid amounts, and customer collections will appear here once trips are assigned or completed.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Wallet Ledger Table -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <div>
                            <span id="driver-ledger-caption" style="font-family:var(--font-display); font-weight:800; font-size:13.5px; color:var(--text-main);">Transaction Ledger &amp; Settlement Record</span>
                            <div class="cell-subtext">Audit log of every credit, platform margin settlement, and bank withdrawal</div>
                        </div>
                        <span class="cell-subtext" id="driver-wallet-ledger-count">{{ count($ledgerRecords) }} latest entries</span>
                    </div>
                    <div tabindex="0" role="region" aria-labelledby="driver-ledger-caption" class="driver-responsive-table-wrap">
                        <table class="admin-data-table admin-table-compact driver-responsive-table">
                            <caption>Wallet transaction ledger with trip clarification and running balance</caption>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transaction Record &amp; Clarification</th>
                                    <th>Type</th>
                                    <th style="text-align:right;">Amount</th>
                                    <th style="text-align:right;">Balance After</th>
                                </tr>
                            </thead>
                            <tbody id="driver-wallet-ledger-body">
                                @forelse($ledgerRecords as $wt)
                                    @php
                                        $wtType = $wt['type'] ?? 'credit';
                                        $wtAmount = (float) ($wt['amount'] ?? 0);
                                        $tb = $wt['trip_breakdown'] ?? null;
                                    @endphp
                                    <tr>
                                        <th>
                                            <div class="cell-primary-text">{{ $wt['date_label'] ?? '—' }}</div>
                                            <div class="cell-subtext">{{ $wt['time_label'] ?? '' }}</div>
                                        </th>
                                        <td data-col-label="Transaction Record & Clarification">
                                            <div class="cell-primary-text">
                                                {{ $wt['reason_label'] ?? 'Transaction' }}
                                                @if(!empty($wt['booking_reference']))
                                                    &bull; <span class="booking-ref-link">{{ $wt['booking_reference'] }}</span>
                                                @endif
                                            </div>
                                            <div class="cell-subtext" style="color:#334155; margin-top:2px; line-height:1.4;">{{ $wt['description'] ?? 'Wallet settlement' }}</div>
                                            @if($tb)
                                                <div class="cell-subtext" style="margin-top:3px; font-size:10.5px; color:#64748b;">
                                                    {{ $tb['route_summary'] }} &bull;
                                                    Fare: ₹{{ number_format((float) $tb['total_fare']) }} &bull;
                                                    {{ $tb['is_bid_won'] ? 'Your Bid' : 'Payout' }}: ₹{{ number_format((float) $tb['driver_payout']) }}
                                                    @if((float) $tb['driver_collected_amount'] > 0)
                                                        &bull; Collected: ₹{{ number_format((float) $tb['driver_collected_amount']) }}
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td data-col-label="Type">
                                            <span class="admin-badge {{ $wtAmount == 0.0 ? 'badge-info' : ($wtType === 'credit' ? 'badge-success' : 'badge-warning') }}" style="font-size:10px;">
                                                {{ $wtAmount == 0.0 ? 'SETTLED' : strtoupper($wtType) }}
                                            </span>
                                        </td>
                                        <td data-col-label="Amount" style="text-align:right;" class="text-tabular">
                                            <strong style="color:{{ $wtAmount == 0.0 ? '#0284c7' : ($wtType === 'credit' ? 'var(--success)' : 'var(--danger)') }};">
                                                {{ $wtAmount == 0.0 ? '₹0' : (($wtType === 'credit' ? '+' : '-').'₹'.number_format($wtAmount)) }}
                                            </strong>
                                        </td>
                                        <td data-col-label="Balance After" style="text-align:right;" class="text-tabular">
                                            <span class="cell-primary-text">₹{{ number_format((float) ($wt['balance_after'] ?? 0)) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:22px; color:var(--text-muted); font-size:12px;">
                                            Completed trip payouts, customer collection settlements, and bank withdrawals will appear here.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
                <div class="driver-panel-card animate-load stagger-2">
                    <div class="driver-panel-card-header">
                        <div>
                            <h3 class="driver-panel-card-title">
                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">account_circle</span>
                                <span>Chauffeur Identity & Profile</span>
                            </h3>
                            <p class="driver-panel-card-subtitle">Official details shared with passengers upon confirmation</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-gold" onclick="openDriverEditProfileModal()">
                            <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                            <span>Edit Profile</span>
                        </button>
                    </div>

                    <div class="driver-panel-card-body">
                        <div style="display:flex; align-items:center; gap:14px; padding-bottom:14px; margin-bottom:14px; border-bottom:1px solid var(--outline-light);">
                            <div class="driver-avatar-display" style="font-size:36px; width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg, rgba(212,155,76,0.2) 0%, rgba(212,155,76,0.05) 100%); border:2px solid var(--primary); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; cursor:pointer;" onclick="openDriverEditProfileModal()" title="Click to Change Avatar">
                                @include('partials.avatar', ['avatar' => $driverProfile->avatar ?? null, 'fallback' => '👨🏽‍✈️', 'alt' => $driverProfile->name ?? 'Driver'])
                            </div>
                            <div style="min-width:0;">
                                <h4 class="driver-name-display" style="font-size:16px; font-weight:800; color:var(--text-main); margin:0 0 4px;">
                                    {{ $driverProfile->name ?? 'Ramesh Patil' }}
                                </h4>
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:5px;">
                                    <span class="admin-badge badge-success" style="font-size:10px;">VERIFIED PARTNER</span>
                                    <span class="admin-badge badge-primary" style="font-size:10px;">EXP: 7+ YRS</span>
                                </div>
                                <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
                                    <span class="material-symbols-outlined" style="font-size:15px; color:#94a3b8;">call</span>
                                    <span class="driver-phone-display" style="font-weight:600; color:var(--text-main);">{{ $driverProfile->phone ?? '+91 98765 43210' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="driver-details-grid">
                            <div class="driver-details-row">
                                <span class="driver-details-label">Primary Vehicle</span>
                                <span class="driver-details-value driver-vehicle-display">{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}</span>
                            </div>
                            <div class="driver-details-row">
                                <span class="driver-details-label">Registration Plate</span>
                                <span class="driver-details-value driver-plate-display" style="font-family:monospace; color:var(--gold-dark);">{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}</span>
                            </div>
                            <div class="driver-details-row">
                                <span class="driver-details-label">Fleet Category</span>
                                <span class="driver-details-value driver-category-display" style="text-transform:uppercase;">{{ $driverProfile->fleet_category ?? 'SUV' }}</span>
                            </div>
                            <div class="driver-details-row">
                                <span class="driver-details-label">Operating Hub / City</span>
                                <span class="driver-details-value driver-city-display">{{ $driverProfile->city ?? 'Pune & Mumbai Hub' }}</span>
                            </div>
                            <div class="driver-details-row">
                                <span class="driver-details-label">Rating &amp; Trips</span>
                                <span class="driver-details-value" style="color:var(--gold-dark);">★{{ $driverProfile->rating ?? '4.90' }} <span style="color:var(--text-muted); font-weight:500;">({{ $driverProfile->trips_count ?? 840 }} Trips)</span></span>
                            </div>
                            <div class="driver-details-row">
                                <span class="driver-details-label">Payout Destination</span>
                                <span class="driver-details-value" id="driver-profile-payout-summary">{{ $driverProfile ? $driverProfile->payoutSummaryLabel() : 'Not configured' }}</span>
                            </div>
                            <div class="driver-details-row" style="align-items:center;">
                                <span class="driver-details-label">Duty Status</span>
                                <button type="button" class="driver-duty-toggle-pill {{ ($driverProfile->is_online ?? true) ? 'online' : 'offline' }} driver-duty-pill-toggle" onclick="toggleDriverDutyStatus()" style="cursor:pointer; border:none;">
                                    <span class="status-indicator-dot {{ ($driverProfile->is_online ?? true) ? 'bg-success' : 'bg-secondary' }} driver-duty-dot"></span>
                                    <span class="driver-duty-text">{{ ($driverProfile->is_online ?? true) ? 'Duty Online' : 'Off Duty' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Registered Fleet & Vehicle Management -->
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <!-- Registered Vehicles Card -->
                    <div class="driver-panel-card animate-load stagger-3" style="margin-bottom:0;">
                        <div class="driver-panel-card-header">
                            <div>
                                <h3 class="driver-panel-card-title">
                                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">garage</span>
                                    <span>Registered Fleet &amp; Vehicles</span>
                                </h3>
                                <p class="driver-panel-card-subtitle">Manage approved cabs and submit additional vehicles for verification</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openDriverListVehicleModal()">
                                <span class="material-symbols-outlined" style="font-size:14px;">add</span>
                                <span>Add New Vehicle</span>
                            </button>
                        </div>

                        <div class="driver-panel-card-body">
                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:10px;" id="driver-profile-vehicles-grid">
                                @forelse($driverMyVehicles as $v)
                                    <div class="driver-compact-item-card">
                                        <div style="min-width:0; flex:1;">
                                            <div style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $v->vehicle_model }}</div>
                                            <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                <span style="font-family:monospace; font-weight:700; color:var(--gold-dark);">{{ $v->vehicle_plate }}</span>
                                                @if($v->status === 'Approved')
                                                    <span class="admin-badge badge-success" style="font-size:10px; padding:2px 7px;">Approved</span>
                                                @elseif($v->status === 'Pending')
                                                    <span class="admin-badge badge-warning" style="font-size:10px; padding:2px 7px;">Pending</span>
                                                @else
                                                    <span class="admin-badge badge-danger" style="font-size:10px; padding:2px 7px;">Rejected</span>
                                                @endif
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline driver-item-details-btn"
                                            onclick='openDriverVehicleDetails(@json($v))'>
                                            <span>Details</span>
                                        </button>
                                    </div>
                                @empty
                                    <div style="grid-column:1 / -1; text-align:center; padding:24px 16px; color:var(--text-muted); font-size:12.5px;">
                                        No cars listed yet. Click <strong>&ldquo;Add New Vehicle&rdquo;</strong> to register your first vehicle.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Driver Payout Collection Methods (UPI & Bank Account) -->
                    <div class="driver-panel-card animate-load stagger-3" style="margin-bottom:0;" id="driver-payout-profile-card">
                        <div class="driver-panel-card-header">
                            <div>
                                <h3 class="driver-panel-card-title">
                                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">account_balance</span>
                                    <span>Payout Collection Details (UPI &amp; Bank Account)</span>
                                </h3>
                                <p class="driver-panel-card-subtitle">Configure how you receive wallet payouts via UPI or Banking System</p>
                            </div>
                            <span class="admin-badge badge-info" style="font-size:10.5px;">Required for Withdrawals</span>
                        </div>

                        <div class="driver-panel-card-body">
                            <form id="driver-payout-settings-form" onsubmit="saveDriverPayoutDetails(event)" style="accent-color:#0f172a;">
                                @php $drvPrefPay = $driverProfile->preferred_payout_method ?? 'upi'; @endphp
                                <fieldset style="border:0; padding:0; margin:0 0 12px 0;">
                                    <legend style="font-size:11.5px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:6px; padding:0;">Preferred Payout Method</legend>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <label for="driver-pref-upi" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px; min-height:42px;">
                                            <input type="radio" id="driver-pref-upi" name="driver_pref_payout_method" value="upi" {{ $drvPrefPay === 'upi' ? 'checked' : '' }} style="width:17px; height:17px;">
                                            <span><strong>UPI Transfer</strong> (Instant VPA)</span>
                                        </label>
                                        <label for="driver-pref-bank" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px; min-height:42px;">
                                            <input type="radio" id="driver-pref-bank" name="driver_pref_payout_method" value="bank" {{ $drvPrefPay === 'bank' ? 'checked' : '' }} style="width:17px; height:17px;">
                                            <span><strong>Bank Account</strong> (IMPS / NEFT)</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-payout-upi-id">Driver UPI ID (VPA)</label>
                                        <input type="text" id="driver-payout-upi-id" class="form-control" value="{{ $driverProfile->payout_upi_id ?? '' }}" placeholder="e.g. ramesh@okicici" autocomplete="off" autocapitalize="none" spellcheck="false">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-payout-upi-name">UPI Registered Name</label>
                                        <input type="text" id="driver-payout-upi-name" class="form-control" value="{{ $driverProfile->payout_upi_name ?? '' }}" placeholder="Name shown in UPI app">
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:14px;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-bank-holder">Bank Account Holder Name</label>
                                        <input type="text" id="driver-bank-holder" class="form-control" value="{{ $driverProfile->bank_account_holder ?? '' }}" placeholder="Full name as in bank">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-bank-name">Bank Name</label>
                                        <input type="text" id="driver-bank-name" class="form-control" value="{{ $driverProfile->bank_name ?? '' }}" placeholder="e.g. HDFC Bank / SBI">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-bank-account">Account Number</label>
                                        <input type="text" id="driver-bank-account" class="form-control" value="{{ $driverProfile->bank_account_number ?? '' }}" placeholder="Bank account number" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="driver-bank-ifsc">IFSC Code</label>
                                        <input type="text" id="driver-bank-ifsc" class="form-control" maxlength="11" value="{{ $driverProfile->bank_ifsc ?? '' }}" placeholder="e.g. HDFC0001029" style="text-transform:uppercase; font-family:monospace;" autocapitalize="characters" spellcheck="false">
                                    </div>
                                </div>

                                <div style="display:flex; justify-content:flex-end;">
                                    <button type="submit" id="driver-save-payout-btn" class="btn btn-primary btn-sm" style="min-height:40px;">
                                        <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                        <span>Save Payout Settings</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Chauffeur Documents & Guidelines Card -->
                    <div class="driver-panel-card animate-load stagger-4" style="margin-bottom:0;">
                        <div class="driver-panel-card-header">
                            <div>
                                <h4 class="driver-panel-card-title" style="font-size:14.5px;">
                                    <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">verified_user</span>
                                    <span>Chauffeur Standards &amp; Compliance</span>
                                </h4>
                                <p class="driver-panel-card-subtitle">Active license, permit verification &amp; punctuality benchmarks</p>
                            </div>
                            <span class="admin-badge badge-success" style="font-size:10.5px;">Compliant</span>
                        </div>
                        <div class="driver-panel-card-body">
                            <div class="auction-metrics-grid">
                                <div class="auction-metric-cell">
                                    <div class="auction-metric-label">Commercial License</div>
                                    <div class="auction-metric-value success" style="font-size:14px;">✓ Verified Active</div>
                                </div>
                                <div class="auction-metric-cell">
                                    <div class="auction-metric-label">Vehicle Permit</div>
                                    <div class="auction-metric-value success" style="font-size:14px;">✓ All India Tourist Permit</div>
                                </div>
                                <div class="auction-metric-cell">
                                    <div class="auction-metric-label">Punctuality Score</div>
                                    <div class="auction-metric-value gold" style="font-size:14px;">★ 99.4% On-time</div>
                                </div>
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
                        <a href="{{ url('/') }}" class="admin-brand-content" style="text-decoration:none; color:inherit;">
                            <img src="{{ asset('assets/logo.jpg') }}" alt="Intercity Wheels Logo" class="admin-brand-img">
                            <div class="admin-brand-text">
                                <span class="admin-brand-title">Intercity</span>                                
                            </div>
                        </a>
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
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="bidding" onclick="switchAdminSection('bidding')">
                                    <span class="material-symbols-outlined admin-nav-icon">gavel</span>
                                    <span class="admin-nav-text">Driver Bidding</span>
                                    <span class="admin-nav-badge badge-gold">Auction</span>
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
                            @php
                                $pendingDiscoveredCount = ($discoveredLocations ?? collect())->where('approval_status', 'pending')->count();
    @endphp
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="locations" onclick="switchAdminSection('locations')">
                                    <span class="material-symbols-outlined admin-nav-icon">pin_drop</span>
                                    <span class="admin-nav-text">Service Locations & Hubs</span>
                                    <span class="admin-nav-badge badge-primary" id="admin-badge-locations-count">{{ count($serviceLocations) }}</span>
                                    <span class="admin-nav-badge" id="admin-badge-discovered-pending" style="background:#f59e0b; color:#fff; margin-left:4px; {{ $pendingDiscoveredCount > 0 ? '' : 'display:none;' }}" title="Beyond-region cities pending approval">{{ $pendingDiscoveredCount }}</span>
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
                            @php $pendingPayoutsCount = ($adminPayoutRequests ?? collect())->where('status', 'pending')->count(); @endphp
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="payouts" onclick="switchAdminSection('payouts'); loadAdminPayoutRequests();">
                                    <span class="material-symbols-outlined admin-nav-icon">account_balance_wallet</span>
                                    <span class="admin-nav-text">Driver Payout Approvals</span>
                                    <span class="admin-nav-badge {{ $pendingPayoutsCount > 0 ? 'badge-danger' : 'badge-success' }}" id="admin-badge-pending-payouts">{{ $pendingPayoutsCount }} Pending</span>
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
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="system" onclick="openAdminInvoiceTemplateEditor()">
                                    <span class="material-symbols-outlined admin-nav-icon">receipt_long</span>
                                    <span class="admin-nav-text">Invoice Template &amp; Pay</span>
                                    <span class="admin-nav-badge badge-gold">4 Styles</span>
                                </a>
                            </li>
                            <li class="admin-nav-item">
                                <a href="javascript:void(0)" class="admin-nav-link" data-section="system" onclick="switchAdminSection('system')">
                                    <span class="material-symbols-outlined admin-nav-icon">admin_panel_settings</span>
                                    <span class="admin-nav-text">System Settings &amp; Health</span>
                                    <span class="admin-nav-badge badge-success">OK</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Sidebar Footer -->
                    <div class="admin-sidebar-footer">
                        <div class="admin-footer-status">
                            <span class="status-pulse-dot"></span>
                            <span>Developed by <a href="https://mabia.in">Mabia.in</a></span>
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
                                            <tbody id="admin-dashboard-fleet-tbody">
                                                @forelse($drivers as $driver)
                                                <tr>
                                                    <td>
                                                        <div style="display:flex; align-items:center; gap:8px;">
                                                            <span style="font-size:18px; width:28px; height:28px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">@include('partials.avatar', ['avatar' => $driver->avatar ?? null, 'fallback' => '👨🏽‍✈️', 'alt' => $driver->name])</span>
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
                                                @empty
                                                <tr data-empty-row="1">
                                                    <td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">No active drivers online.</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="admin-pagination-bar" id="pag-bar-dashboard-fleet"></div>
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
                                                <tr data-empty-row="1">
                                                    <td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">
                                                        No bookings recorded yet. Create one in Cabs or Schedules!
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="admin-pagination-bar" id="pag-bar-dashboard-bookings"></div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. DRIVER BIDDING MARKETPLACE -->
                        <div class="admin-section" id="admin-sec-bidding">
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">gavel</span>
                                            <span>Driver Bidding Marketplace</span>
                                        </h3>
                                        <p class="card-subtitle">
                                            Publish a booking to verified drivers (and the Driver Console). Drivers bid downwards; the lowest bid wins once you approve it.
                                            Customer identity and exact addresses stay hidden until approval.
                                        </p>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <select id="admin-bidding-filter-status" class="form-control" style="height:36px; font-size:12.5px; width:150px;" onchange="renderAdminBiddings()">
                                            <option value="all" selected>All auctions</option>
                                            <option value="open">Open</option>
                                            <option value="closed">Closed</option>
                                            <option value="awarded">Allotted</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <button type="button" class="btn btn-outline" onclick="renderAdminBiddings()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">refresh</span> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:12px 18px; display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                    <div style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
                                        <div class="form-group" style="margin:0;">
                                            <label class="form-label" for="bidding-setting-min-pct" style="font-size:11px;">Min Service Charge % (Max Bid Cap)</label>
                                            <input type="number" id="bidding-setting-min-pct" class="form-control" min="0" max="90" step="0.5" value="{{ $ps['bidding_min_service_charge_pct'] ?? 15 }}" style="width:140px; height:34px; font-weight:700;">
                                        </div>
                                        <div class="form-group" style="margin:0;">
                                            <label class="form-label" for="bidding-setting-max-pct" style="font-size:11px;">Max Service Charge % (Instant-Win Floor)</label>
                                            <input type="number" id="bidding-setting-max-pct" class="form-control" min="0" max="95" step="0.5" value="{{ $ps['bidding_max_service_charge_pct'] ?? 75 }}" style="width:155px; height:34px; font-weight:700;">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="saveBiddingServiceChargeSettings()">
                                            <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                                            <span>Save Bid Limits</span>
                                        </button>
                                    </div>
                                    <div style="font-size:11.5px; color:#64748b; max-width:340px; line-height:1.4;">
                                        Example: On ₹5,000, 10% min caps bids at ₹4,500; 50% max sets ₹2,500 floor where the first driver to quote it wins immediately.
                                    </div>
                                </div>
                                <div class="card-body" style="padding:0;">
                                    <table class="admin-data-table admin-table-compact" id="admin-biddings-table">
                                        <thead>
                                            <tr>
                                                <th>Booking</th>
                                                <th>Route</th>
                                                <th>Status</th>
                                                <th>Start price</th>
                                                <th>Lowest bid</th>
                                                <th>Admin profit</th>
                                                <th>Bids</th>
                                                <th>Closes</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-biddings-tbody">
                                            <tr>
                                                <td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">
                                                    Loading auctions…
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="admin-pagination-bar" id="pag-bar-admin-biddings"></div>
                            </div>

                            <div class="card" style="padding:16px 18px;">
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <span class="material-symbols-outlined" style="color:var(--primary);">info</span>
                                    <div style="font-size:12.5px; color:#475569; line-height:1.7;">
                                        <strong>How it works:</strong> open an auction from a booking's row (gavel icon in Bookings Management),
                                        set the start price and the minimum admin margin, then share the link or let drivers bid directly in the Driver Console.
                                        Every new bid must undercut the current lowest bid by at least ₹1.
                                        When you approve the winner, the booking becomes <em>Driver Assigned</em>, the driver receives the customer's
                                        name, mobile and full addresses, and their payout is fixed at their winning bid.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. BOOKINGS MANAGEMENT (DIRECTLY AFTER DASHBOARD) -->
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
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <button type="button" class="btn btn-sm btn-outline-gold" onclick="openAdminInvoiceTemplateEditor()" title="Customize Tax Invoice Template & Styles">
                                            <span class="material-symbols-outlined" style="font-size:15px;">receipt_long</span>
                                            <span>Invoice Template</span>
                                        </button>
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

                                <!-- Compact Booking Type Filter Pills -->
                                <div class="admin-trip-type-filters" id="admin-booking-trip-type-filters" role="group" aria-label="Filter bookings by trip type">
                                    <span class="admin-trip-type-label">Booking Type:</span>
                                    <button type="button" class="admin-trip-type-btn active" data-trip-type="all" onclick="setBookingTripTypeFilter('all', this)">All</button>
                                    <button type="button" class="admin-trip-type-btn" data-trip-type="oneway" onclick="setBookingTripTypeFilter('oneway', this)">One-Way</button>
                                    <button type="button" class="admin-trip-type-btn" data-trip-type="roundtrip" onclick="setBookingTripTypeFilter('roundtrip', this)">Round-Trip</button>
                                    <button type="button" class="admin-trip-type-btn" data-trip-type="local" onclick="setBookingTripTypeFilter('local', this)">Local</button>
                                    <button type="button" class="admin-trip-type-btn" data-trip-type="airport" onclick="setBookingTripTypeFilter('airport', this)">Airport</button>
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
                                            <tr id="booking-row-{{ $b->id }}" data-id="{{ $b->id }}" data-ref="{{ $b->booking_reference }}" data-status="{{ $b->status }}" data-service="{{ $b->service_type }}" data-trip-type="{{ strtolower($b->trip_type ?? 'oneway') }}" data-search="{{ strtolower($b->booking_reference . ' ' . $b->customer_name . ' ' . $b->customer_phone . ' ' . $b->route_summary . ' ' . $b->pickup_location . ' ' . $b->drop_location . ' ' . ($b->cab_type ?? '')) }}">
                                                <td>
                                                    <a href="javascript:void(0)" onclick="openViewBookingModal({{ $b->id }})" class="booking-ref-link" title="Click to inspect booking">
                                                        {{ $b->booking_reference }}
                                                    </a>
                                                    <div class="cell-subtext">{{ $b->created_at ? $b->created_at->format('d M, h:i A') : 'Recent' }}</div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0)" onclick="openAdminBookingCustomerProfile({{ $b->id }})" class="cell-primary-text" style="color:#0f172a; text-decoration:underline; text-decoration-color:#cbd5e1;" title="View customer profile & bookings">
                                                        {{ $b->customer_name }}
                                                    </a>
                                                    <div class="cell-subtext">
                                                        <a href="tel:{{ $b->customer_phone }}" style="color:#64748b; text-decoration:none;">{{ $b->customer_phone }}</a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                                        <span class="admin-badge {{ $b->service_type === 'cab' ? 'badge-primary' : 'badge-gold' }}" style="font-size:9.5px; padding:1px 5px;">
                                                            {{ strtoupper($b->service_type) }}
                                                        </span>
                                                        @if($b->service_type === 'cab' && $b->cab_type)
                                                            <span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Cab Type">
                                                                {{ $b->cab_type }}
                                                            </span>
                                                        @endif
                                                        @if($b->is_beyond_region)
                                                            <span class="admin-badge badge-warning" style="font-size:9px; padding:1px 5px;" title="Beyond service region — availability not guaranteed">
                                                                OUT OF REGION
                                                            </span>
                                                        @endif
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
                                                    @php
                                                        $adminPayDot = $b->status === 'Cancelled'
                                                            ? 'bg-danger'
                                                            : (in_array($b->payment_status, ['pending_availability', 'pending_upi_verification', 'awaiting_upi_utr', 'pending', 'driver_collect', 'pay_on_pickup', 'upi_rejected'], true)
                                                                ? 'bg-warning'
                                                                : 'bg-success');
                                                        $adminPayLabel = match($b->payment_status) {
                                                            'pending_availability' => 'Unconfirmed / Out of Region',
                                                            'pending_upi_verification' => 'UPI Pending Admin'.($b->upi_utr ? ' (UTR: '.$b->upi_utr.')' : ''),
                                                            'awaiting_upi_utr' => 'Awaiting UPI UTR',
                                                            'driver_collect' => 'Driver Collects ₹'.number_format($b->balance_amount > 0 ? $b->balance_amount : $b->total_amount),
                                                            'upi_rejected' => 'UTR Rejected · Driver Collects',
                                                            'pay_on_pickup' => 'Pay Driver ₹'.number_format($b->balance_amount > 0 ? $b->balance_amount : $b->total_amount),
                                                            'partial_paid' => 'Part Paid (Bal ₹'.number_format($b->balance_amount).' Driver)',
                                                            'pending' => 'Online Payment Pending',
                                                            default => $b->payment_mode === 'full_upi'
                                                                ? ($b->driver_collected_at ? 'Collected by Driver' : 'UPI Confirmed'.($b->upi_utr ? ' ('.$b->upi_utr.')' : ''))
                                                                : ($b->driver_collected_at ? 'Driver Collected ₹'.number_format((float) $b->driver_collected_amount) : 'Prepaid (Escrow)'),
                                                        };
                            @endphp
                                                    <div class="cell-subtext">
                                                        <span class="status-indicator-dot {{ $adminPayDot }}"></span>
                                                        <span>{{ $adminPayLabel }}</span>
                                                    </div>
                                                </td>
                                                <td id="booking-driver-cell-{{ $b->id }}">
                                                    @if($b->driver)
                                                    <div class="driver-allotted-card">
                                                        <a href="javascript:void(0)" onclick="openAdminDriverProfileModal({{ $b->driver->id }})" class="cell-primary-text" style="color:var(--primary); text-decoration:underline;" title="View driver profile & trips">{{ $b->driver->name }}</a>
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
                                                        <button type="button" class="btn-action-icon" style="background:#f0f7ff; color:#1d4ed8; border:1px solid #bfdbfe;" onclick="openBiddingForBooking({{ $b->id }})" title="Send to driver bidding (Configure & Preview)">
                                                            <span class="material-symbols-outlined">gavel</span>
                                                        </button>
                                                        @if($b->service_type === 'cab' && ! in_array($b->status, ['Completed', 'Cancelled'], true))
                                                        <button type="button" class="btn-action-icon" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;" onclick="copyBookingBidInvitation({{ $b->id }}, this)" title="Copy Formatted WhatsApp Bid Invitation">
                                                            <span class="material-symbols-outlined">content_copy</span>
                                                        </button>
                                                        <button type="button" class="btn-action-icon" style="background:#ecfdf5; color:#047857; border:1px solid #6ee7b7;" onclick="shareBookingBidOnWhatsApp({{ $b->id }}, this)" title="Share Bid Invitation on WhatsApp">
                                                            <span class="material-symbols-outlined">share</span>
                                                        </button>
                                                        @endif
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-bookings"></div>
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
                                                <th>ROLE &amp; ACCESS LEVEL</th>
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
                                                $driver = $u->role === 'driver' ? $u->driverProfile() : null;
                                                $trips = 0;
                                                if ($u->role === 'customer') {
                                                    $trips = \App\Models\Booking::where('customer_phone', $u->phone)->orWhere('customer_email', $u->email)->count();
                                                } elseif ($driver) {
                                                    $trips = $driver->trips_count;
                                                }
                                                $isSelfAdmin = Auth::id() === $u->id && $u->role === 'admin';
                    @endphp
                                            <tr id="user-row-{{ $u->id }}" data-id="{{ $u->id }}" data-role="{{ $u->role }}" data-search="{{ strtolower($u->name . ' ' . $u->email . ' ' . $u->phone . ' ' . $u->role . ' ' . ($driver ? $driver->vehicle_plate . ' ' . $driver->vehicle_model : '')) }}">
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span style="font-size:20px; width:30px; height:30px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
                                                            @include('partials.avatar', ['avatar' => $u->avatar ?? null, 'fallback' => $u->role === 'admin' ? '🛡️' : ($u->role === 'driver' ? '👨🏽‍✈️' : '👤'), 'alt' => $u->name])
                                                        </span>
                                                        <div>
                                                            <div class="cell-primary-text">{{ $u->name }}</div>
                                                            <div class="cell-subtext">ID: #USR-{{ str_pad($u->id, 4, '0', STR_PAD_LEFT) }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                        <span class="admin-badge @if($u->role === 'admin') badge-gold @elseif($u->role === 'driver') badge-success @else badge-primary @endif">
                                                            {{ strtoupper($u->role) }}
                                                        </span>
                                                        <select class="form-control" style="width:auto; min-width:110px; height:28px; padding:2px 6px; font-size:11px; font-weight:600;"
                                                            aria-label="Change access level for {{ $u->name }}"
                                                            data-prev-role="{{ $u->role }}"
                                                            onchange="changeUserRole({{ $u->id }}, this.value, this)"
                                                            @if($isSelfAdmin) disabled title="Cannot change your own active admin role" @endif>
                                                            <option value="customer" {{ $u->role === 'customer' ? 'selected' : '' }}>Customer</option>
                                                            <option value="driver" {{ $u->role === 'driver' ? 'selected' : '' }}>Driver</option>
                                                            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                                        </select>
                                                    </div>
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-users"></div>
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
                            <div class="card" style="margin-bottom:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">tune</span>
                                            <span>Bulk Rates, Night Stay & Fare Conditions Manager</span>
                                        </h3>
                                        <p class="card-subtitle">Set per-km car type rates, night stay rates & hours, local package hours, and fare conditions in one clean view</p>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <span class="admin-badge badge-primary" style="font-size:11px; padding:4px 8px;">{{ count($allCarTypes ?? []) }} Car Types</span>
                                        <span class="admin-badge badge-gold" style="font-size:11px; padding:4px 8px;" id="pricing-night-summary-pill">Night: ₹{{ number_format($nightRate) }} ({{ $nightWindowLabel }})</span>
                                        <span class="admin-badge badge-success" style="font-size:11px; padding:4px 8px;">{{ count($localPackages ?? []) }} Local Pkgs</span>
                                    </div>
                                </div>

                                <!-- Sub-Nav Tabs -->
                                <div class="admin-sub-tabs-bar">
                                    <button type="button" class="admin-sub-tab-btn active admin-pricing-tab-btn" data-tab="car-rates" onclick="switchPricingSubTab('car-rates')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">directions_car</span>
                                        <span>Car Type Per-KM Rates (Bulk)</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-pricing-tab-btn" data-tab="conditions" onclick="switchPricingSubTab('conditions')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">bedtime</span>
                                        <span>Night Stay, Hours & Conditions</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-pricing-tab-btn" data-tab="local-rates" onclick="switchPricingSubTab('local-rates')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">schedule</span>
                                        <span>Local Packages (Hours & Rates)</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-pricing-tab-btn" data-tab="route-rates" onclick="switchPricingSubTab('route-rates')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">alt_route</span>
                                        <span>Outstation Route Bulk Adjust</span>
                                    </button>
                                </div>

                                <!-- PANEL 1: BULK CAR TYPE PER-KM RATES -->
                                <div class="admin-pricing-tab-panel" id="admin-pricing-panel-car-rates" style="display:block;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                            <span style="font-size:12px; font-weight:700; color:#334155;">Quick Adjust All:</span>
                                            <button type="button" class="btn btn-xs btn-outline" onclick="applyCarTypeBulkPercent(5)" title="Increase all per-km rates by 5%">+5%</button>
                                            <button type="button" class="btn btn-xs btn-outline" onclick="applyCarTypeBulkPercent(10)" title="Increase all per-km rates by 10%">+10%</button>
                                            <button type="button" class="btn btn-xs btn-outline" onclick="applyCarTypeBulkPercent(-5)" title="Decrease all per-km rates by 5%">-5%</button>
                                            <button type="button" class="btn btn-xs btn-outline" onclick="applyCarTypeFlatDelta(1)" title="Add ₹1/km to all">+₹1/km</button>
                                            <button type="button" class="btn btn-xs btn-outline" onclick="syncCarTypeExtraKm()" title="Match Extra KM rate to Rate/KM">Sync Extra KM = Rate/KM</button>
                                        </div>
                                        <button type="button" id="bulk-save-car-rates-btn" class="btn btn-sm btn-primary" onclick="bulkSaveCarTypeRates()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save All Car Type Rates</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact" id="admin-bulk-car-rates-table">
                                            <thead>
                                                <tr>
                                                    <th>Car Type & Key</th>
                                                    <th>Rate / KM (₹)</th>
                                                    <th>Extra KM Rate (₹)</th>
                                                    <th>Min Base Fare (₹)</th>
                                                    <th style="text-align:center;">Active</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bulk-car-rates-tbody">
                                                @forelse($allCarTypes ?? [] as $ct)
                                                <tr data-car-type-id="{{ $ct->id }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a;">{{ $ct->name }}</div>
                                                        <div style="font-size:11px; color:#64748b;">key: <code>{{ $ct->category_key }}</code> &bull; {{ $ct->seats }}</div>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.5" min="1" class="form-control bulk-ct-rate-km" value="{{ $ct->rate_per_km }}" style="max-width:120px; height:34px; font-weight:700;">
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.5" min="0" class="form-control bulk-ct-extra-km" value="{{ $ct->extra_km_rate }}" style="max-width:120px; height:34px;">
                                                    </td>
                                                    <td>
                                                        <input type="number" step="50" min="0" class="form-control bulk-ct-min-fare" value="{{ $ct->base_fare_min }}" style="max-width:130px; height:34px;">
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <input type="checkbox" class="bulk-ct-active" {{ $ct->is_active ? 'checked' : '' }}>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">No car types configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- PANEL 2: NIGHT STAY, HOURS & BOOKING CONDITIONS -->
                                <div class="admin-pricing-tab-panel" id="admin-pricing-panel-conditions" style="display:none; padding:18px 20px;">
                                    <div class="admin-card-grid-split">
                                        <!-- Left: Night Stay & Surcharges -->
                                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                                            <h4 style="font-size:14.5px; font-weight:800; color:#0f172a; margin:0 0 4px; display:flex; align-items:center; gap:6px;">
                                                <span class="material-symbols-outlined" style="color:var(--gold); font-size:18px;">bedtime</span>
                                                <span>Night Stay Rate, Hours & Add-ons</span>
                                            </h4>
                                            <p style="font-size:12px; color:#64748b; margin:0 0 14px;">Saved directly to the server and applied to every live quote & booking.</p>

                                            <div style="display:flex; flex-direction:column; gap:12px;">
                                                <div class="form-group" style="margin:0;">
                                                    <label class="form-label" for="rule-night-charge">Night Stay / Halt Allowance Rate (₹)</label>
                                                    <div class="input-with-icon">
                                                        <span class="material-symbols-outlined">bedtime</span>
                                                        <input type="number" id="rule-night-charge" class="form-control" min="0" step="10" value="{{ $nightRate }}">
                                                    </div>
                                                </div>

                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-night-start">Night Hours Start</label>
                                                        <input type="time" id="rule-night-start" class="form-control" value="{{ $nightStartRaw }}">
                                                    </div>
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-night-end">Night Hours End</label>
                                                        <input type="time" id="rule-night-end" class="form-control" value="{{ $nightEndRaw }}">
                                                    </div>
                                                </div>

                                                <div class="form-group" style="margin:0;">
                                                    <label class="form-label" for="rule-pet-charge">Pet Sanitization & Carrier Fee (₹)</label>
                                                    <div class="input-with-icon">
                                                        <span class="material-symbols-outlined">pets</span>
                                                        <input type="number" id="rule-pet-charge" class="form-control" min="0" step="10" value="{{ $petRate }}">
                                                    </div>
                                                </div>

                                                <div class="form-group" style="margin:0;">
                                                    <label class="form-label" for="rule-luggage-charge">Top Roof Luggage Carrier Fee (₹)</label>
                                                    <div class="input-with-icon">
                                                        <span class="material-symbols-outlined">luggage</span>
                                                        <input type="number" id="rule-luggage-charge" class="form-control" min="0" step="10" value="{{ $luggageRate }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right: Hours, Min KM & Other Conditions -->
                                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                                            <h4 style="font-size:14.5px; font-weight:800; color:#0f172a; margin:0 0 4px; display:flex; align-items:center; gap:6px;">
                                                <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">gavel</span>
                                                <span>Hours, Minimum Billing & Other Conditions</span>
                                            </h4>
                                            <p style="font-size:12px; color:#64748b; margin:0 0 14px;">Standard rules shown on trip summary and policy matrix.</p>

                                            <div style="display:flex; flex-direction:column; gap:12px;">
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-rt-min-km">Round-Trip Min KM / Day</label>
                                                        <input type="number" id="rule-rt-min-km" class="form-control" min="0" max="2000" value="{{ $rtMinKm }}">
                                                    </div>
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-driver-allowance">Driver Day Allowance (₹)</label>
                                                        <input type="number" id="rule-driver-allowance" class="form-control" min="0" step="10" value="{{ $driverAllowanceRate }}" title="0 = Included in per-km rate">
                                                    </div>
                                                </div>

                                                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-waiting-grace">Free Wait (Mins)</label>
                                                        <input type="number" id="rule-waiting-grace" class="form-control" min="0" max="480" value="{{ $freeWaitMins }}">
                                                    </div>
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-waiting-hour-rate">Extra Hr Wait (₹)</label>
                                                        <input type="number" id="rule-waiting-hour-rate" class="form-control" min="0" step="10" value="{{ $waitHrRate }}">
                                                    </div>
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-free-cancel-hours">Free Cancel (Hrs)</label>
                                                        <input type="number" id="rule-free-cancel-hours" class="form-control" min="0" max="168" value="{{ $freeCancelHrs }}">
                                                    </div>
                                                </div>

                                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-bidding-min-pct">Min Bidding Service Charge (%)</label>
                                                        <input type="number" id="rule-bidding-min-pct" class="form-control" min="0" max="90" step="0.5" value="{{ $ps['bidding_min_service_charge_pct'] ?? 15 }}" title="Drivers cannot bid higher than Fare × (1 - Min%)">
                                                    </div>
                                                    <div class="form-group" style="margin:0;">
                                                        <label class="form-label" for="rule-bidding-max-pct">Max Bidding Service Charge / Auto-Win (%)</label>
                                                        <input type="number" id="rule-bidding-max-pct" class="form-control" min="0" max="95" step="0.5" value="{{ $ps['bidding_max_service_charge_pct'] ?? 75 }}" title="Driver reaching Fare × (1 - Max%) becomes instant winner">
                                                    </div>
                                                </div>

                                                <div class="form-group" style="margin:0;">
                                                    <label class="form-label" for="rule-other-conditions">Other Booking Conditions / Notes</label>
                                                    <textarea id="rule-other-conditions" class="form-control" rows="2" placeholder="e.g. Tolls, state taxes & parking as applicable.">{{ $otherCondText }}</textarea>
                                                </div>

                                                <button type="button" id="save-pricing-rules-btn" class="btn btn-gold btn-block" onclick="savePricingConditions()" style="margin-top:4px;">
                                                    <span class="material-symbols-outlined">save</span>
                                                    <span>Save Night Stay, Hours & Conditions</span>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-block" onclick="switchAdminSection('system')" style="margin-top:6px;">
                                                    <span class="material-symbols-outlined" style="font-size:16px;">payments</span>
                                                    <span>Configure Checkout Payment Modes & Advance %</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PANEL 3: LOCAL PACKAGES BULK HOURS & RATES -->
                                <div class="admin-pricing-tab-panel" id="admin-pricing-panel-local-rates" style="display:none;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <span style="font-size:12px; color:#475569;">Edit local rental hours, included KMs, package fares, and extra hour/km overrun rates together.</span>
                                        <button type="button" id="bulk-save-local-packages-btn" class="btn btn-sm btn-primary" onclick="bulkSaveLocalPackages()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save All Local Packages</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact" id="admin-bulk-local-packages-table">
                                            <thead>
                                                <tr>
                                                    <th>Package</th>
                                                    <th>Hours</th>
                                                    <th>Included KM</th>
                                                    <th>Sedan Fare (₹)</th>
                                                    <th>SUV Fare (₹)</th>
                                                    <th>Extra KM (₹/km)</th>
                                                    <th>Extra Hour (₹/hr)</th>
                                                    <th style="text-align:center;">Active</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bulk-local-packages-tbody">
                                                @forelse($localPackages ?? [] as $lp)
                                                <tr data-package-id="{{ $lp->id }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a;">{{ $lp->name }}</div>
                                                        <div style="font-size:11px; color:#64748b;"><code>{{ $lp->package_code }}</code> &bull; {{ $lp->city }}</div>
                                                    </td>
                                                    <td><input type="number" min="1" class="form-control bulk-lp-hours" value="{{ $lp->duration_hours }}" style="max-width:80px; height:34px;"></td>
                                                    <td><input type="number" min="1" class="form-control bulk-lp-km" value="{{ $lp->included_km }}" style="max-width:90px; height:34px;"></td>
                                                    <td><input type="number" min="0" step="50" class="form-control bulk-lp-sedan" value="{{ $lp->sedan_fare }}" style="max-width:105px; height:34px; font-weight:700;"></td>
                                                    <td><input type="number" min="0" step="50" class="form-control bulk-lp-suv" value="{{ $lp->suv_fare }}" style="max-width:105px; height:34px; font-weight:700;"></td>
                                                    <td><input type="number" min="0" step="0.5" class="form-control bulk-lp-extra-km" value="{{ $lp->extra_km_rate }}" style="max-width:95px; height:34px;"></td>
                                                    <td><input type="number" min="0" step="10" class="form-control bulk-lp-extra-hr" value="{{ $lp->extra_hour_rate }}" style="max-width:95px; height:34px;"></td>
                                                    <td style="text-align:center;"><input type="checkbox" class="bulk-lp-active" {{ $lp->is_active ? 'checked' : '' }}></td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No local packages configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- PANEL 4: OUTSTATION ROUTE BULK ADJUST -->
                                <div class="admin-pricing-tab-panel" id="admin-pricing-panel-route-rates" style="display:none; padding:18px 20px;">
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; margin-bottom:16px;">
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">1. Sync All Routes to Car Type Per-KM Rates</div>
                                            <p style="font-size:11.5px; color:#64748b; margin:0 0 10px;">Clears custom fixed route overrides so every outstation route calculates dynamically from your Car Type ₹/km rates + route toll.</p>
                                            <button type="button" class="btn btn-sm btn-outline" onclick="bulkAdjustCabRoutes('use_car_type_rates')">Use Car Type Rates Everywhere</button>
                                        </div>

                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">2. Bulk Set Round-Trip Return Discount %</div>
                                            <p style="font-size:11.5px; color:#64748b; margin:0 0 10px;">Apply a uniform return discount percentage across all outstation routes.</p>
                                            <div style="display:flex; gap:8px; align-items:center;">
                                                <input type="number" id="bulk-route-rt-discount" class="form-control" min="0" max="100" step="1" value="5" style="max-width:95px; height:34px;">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="bulkAdjustCabRoutes('set_roundtrip_discount')">Apply %</button>
                                            </div>
                                        </div>

                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">3. Bulk Adjust Custom Route Fares (%)</div>
                                            <p style="font-size:11.5px; color:#64748b; margin:0 0 10px;">Scale any existing fixed route overrides by a percentage (e.g. 10 or -5).</p>
                                            <div style="display:flex; gap:8px; align-items:center;">
                                                <input type="number" id="bulk-route-percent" class="form-control" min="-90" max="500" step="1" value="10" style="max-width:95px; height:34px;">
                                                <button type="button" class="btn btn-sm btn-gold" onclick="bulkAdjustCabRoutes('percent_adjust')">Scale Fares</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <span style="font-size:12px; font-weight:700; color:#334155;">Inline Route Tolls & Return Discount ({{ count($allCabRoutes ?? []) }} routes)</span>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="bulkAdjustCabRoutes('inline_save')">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Inline Route Edits</span>
                                        </button>
                                    </div>
                                    <div class="table-responsive" style="max-height:360px; overflow-y:auto;">
                                        <table class="admin-table-compact" id="admin-bulk-routes-table">
                                            <thead>
                                                <tr>
                                                    <th>Route</th>
                                                    <th>Distance</th>
                                                    <th>Base Toll (₹)</th>
                                                    <th>Return Disc (%)</th>
                                                    <th>Sedan Override (₹)</th>
                                                    <th>SUV Override (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bulk-routes-tbody">
                                                @forelse($allCabRoutes ?? [] as $cr)
                                                <tr data-route-id="{{ $cr->id }}">
                                                    <td style="font-weight:700; color:#0f172a;">{{ $cr->from_city }} → {{ $cr->to_city }}</td>
                                                    <td>{{ $cr->distance_km }} km</td>
                                                    <td><input type="number" min="0" class="form-control bulk-rt-toll" value="{{ $cr->base_toll }}" style="max-width:95px; height:32px;"></td>
                                                    <td><input type="number" min="0" max="100" step="0.5" class="form-control bulk-rt-disc" value="{{ $cr->roundtrip_discount_pct ?? 0 }}" style="max-width:85px; height:32px;"></td>
                                                    <td><input type="number" min="0" class="form-control bulk-rt-sedan" value="{{ $cr->sedan_fare }}" placeholder="Auto" style="max-width:100px; height:32px;"></td>
                                                    <td><input type="number" min="0" class="form-control bulk-rt-suv" value="{{ $cr->suv_fare }}" placeholder="Auto" style="max-width:100px; height:32px;"></td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="6" style="text-align:center; padding:20px; color:#94a3b8;">No routes configured yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 4B. BOOKING TYPES & PACKAGES MANAGEMENT (Admin Master)   -->
                        <!-- ======================================================== -->
                        @php
                            $activeRouteCarTypes = ($allCarTypes ?? collect())->where('is_active', true)->values();
                            if ($activeRouteCarTypes->isEmpty()) {
                                $activeRouteCarTypes = ($allCarTypes ?? collect())->values();
                            }
@endphp
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
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <span class="admin-badge badge-primary" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-routes-pill">{{ count($allCabRoutes) }} One-Way</span>
                                        <span class="admin-badge badge-success" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-roundtrip-pill">{{ count($allCabRoutes) }} Round-Trip</span>
                                        <span class="admin-badge badge-warning" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-packages-pill">{{ count($localPackages) }} Local Pkgs</span>
                                        <span class="admin-badge badge-gold" style="font-size:11px; padding:4px 8px;" id="admin-bt-total-airports-pill">{{ count($airportHubs) }} Airports</span>
                                    </div>
                                </div>

                                <!-- Modular Sub-Nav Tabs -->
                                <div class="admin-sub-tabs-bar">
                                    <button type="button" class="admin-sub-tab-btn active admin-bt-tab-btn" data-tab="oneway" onclick="switchBookingTypeTab('oneway')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">arrow_forward</span>
                                        <span>One-Way (Outstation Highway Routes)</span>
                                        <span class="badge-pill" id="admin-tab-count-outstation">{{ count($allCabRoutes) }}</span>
                                    </button>
                                    <button type="button" class="admin-sub-tab-btn admin-bt-tab-btn" data-tab="roundtrip" onclick="switchBookingTypeTab('roundtrip')">
                                        <span class="material-symbols-outlined" style="font-size:16px;">sync_alt</span>
                                        <span>Round-Trip Outstation</span>
                                        <span class="badge-pill" id="admin-tab-count-roundtrip">{{ count($allCabRoutes) }}</span>
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

                                <!-- SUB-PANEL 1: ONE-WAY OUTSTATION HIGHWAY ROUTES -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-oneway" style="display:block;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; flex-wrap:wrap;">
                                            <div class="admin-search-box" style="flex:1; min-width:190px; max-width:260px;">
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
                                            <div class="admin-filter-item">
                                                <label>Car Type:</label>
                                                <select id="bt-oneway-default-car-select" class="booking-type-car-select" onchange="saveBookingTypeCarTypeRule('oneway', this.value)" title="Optional default Car Type for One-Way bookings">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_oneway'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-bt-routes-filter-count">{{ count($allCabRoutes) }} Routes</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddCabRouteModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add One-Way / Highway Route</span>
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
                                                <tr id="admin-bt-cab-route-row-{{ $cr->id }}" data-route-id="{{ $cr->id }}" data-status="{{ $cr->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($cr->from_city . ' ' . $cr->to_city) }}">
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
                                                        <div class="admin-route-fare-schedule" data-route-id="{{ $cr->id }}" data-roundtrip="0" style="font-size:11px; line-height:1.5; color:#334155; display:flex; flex-wrap:wrap; gap:4px 10px;">
                                                            @foreach($activeRouteCarTypes as $ct)
                                                                @php $ctFare = round($cr->getFareForCarType($ct->category_key, (float) $ct->rate_per_km)); @endphp
                                                                <span>{{ $ct->name }}: <strong>₹{{ number_format($ctFare) }}</strong></span>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11px; color:#16a34a; font-weight:600;">
                                                            {{ $cr->roundtrip_discount_pct > 0 ? $cr->roundtrip_discount_pct . '% Return Disc.' : $rtMinKm . ' KM/Day Min' }}
                                                        </span>
                                                        @if(!empty($cr->default_car_type))
                                                            <div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: {{ strtoupper($cr->default_car_type) }}</span></div>
                                                        @endif
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

                                <!-- SUB-PANEL 2: ROUND-TRIP OUTSTATION ROUTES & DISCOUNTS -->
                                <div class="admin-bt-tab-panel" id="admin-bt-panel-roundtrip" style="display:none;">
                                    <div class="admin-bookings-toolbar" style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between;">
                                        <div style="display:flex; gap:12px; align-items:center; flex:1; flex-wrap:wrap;">
                                            <div class="admin-search-box" style="flex:1; min-width:190px; max-width:260px;">
                                                <span class="material-symbols-outlined">search</span>
                                                <input type="text" id="admin-bt-rt-route-search-input" placeholder="Search round-trip corridor..." oninput="handleCabRouteSearch(this.value)">
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Status:</label>
                                                <select id="admin-bt-rt-route-filter-status" onchange="handleCabRouteFilter()">
                                                    <option value="all">All Routes</option>
                                                    <option value="active">Active Only</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <div class="admin-filter-item">
                                                <label>Car Type:</label>
                                                <select id="bt-rt-default-car-select" class="booking-type-car-select" onchange="saveBookingTypeCarTypeRule('roundtrip', this.value)" title="Optional default Car Type for Round-Trip bookings">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_roundtrip'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <span class="admin-count-pill" id="admin-bt-rt-routes-filter-count">{{ count($allCabRoutes) }} Routes</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openAddCabRouteModal()">
                                            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                            <span>Add Round-Trip Route</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="admin-table-compact">
                                            <thead>
                                                <tr>
                                                    <th>Round-Trip Corridor</th>
                                                    <th>Return Distance</th>
                                                    <th>Return Discount & Policy</th>
                                                    <th>Round-Trip Fare Schedule (2x - Disc.)</th>
                                                    <th>Specific Car Type</th>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="admin-bt-roundtrip-routes-tbody">
                                                @forelse($allCabRoutes as $cr)
                                                @php
                                                    $discFactor = max(0, 1 - (($cr->roundtrip_discount_pct ?? 0) / 100));
                        @endphp
                                                <tr id="admin-bt-rt-route-row-{{ $cr->id }}" data-route-id="{{ $cr->id }}" data-status="{{ $cr->is_active ? 'active' : 'inactive' }}" data-search="{{ strtolower($cr->from_city . ' ' . $cr->to_city) }}">
                                                    <td>
                                                        <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                                            <span>{{ $cr->from_city }}</span>
                                                            <span style="color:#16a34a;">⇄</span>
                                                            <span>{{ $cr->to_city }}</span>
                                                        </div>
                                                        <div style="font-size:11px; color:#64748b;">Round-Trip &bull; Night Halt ₹{{ number_format($nightRate) }}/night</div>
                                                    </td>
                                                    <td>
                                                        <span class="admin-badge badge-primary" style="font-family:monospace; font-size:11.5px;">
                                                            🔁 {{ $cr->distance_km * 2 }} KM
                                                        </span>
                                                        <div style="font-size:10.5px; color:#64748b; margin-top:2px;">{{ $rtMinKm }} KM/Day Min</div>
                                                    </td>
                                                    <td>
                                                        <span style="font-size:11.5px; color:#16a34a; font-weight:700;">
                                                            {{ $cr->roundtrip_discount_pct > 0 ? $cr->roundtrip_discount_pct . '% Return Disc.' : 'Standard (0% Disc.)' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="admin-route-fare-schedule" data-route-id="{{ $cr->id }}" data-roundtrip="1" style="font-size:11px; line-height:1.5; color:#334155; display:flex; flex-wrap:wrap; gap:4px 10px;">
                                                            @foreach($activeRouteCarTypes as $ct)
                                                                @php
                                                                    $ctOneWay = $cr->getFareForCarType($ct->category_key, (float) $ct->rate_per_km);
                                                                    $ctRtFare = round(($ctOneWay * 2) * $discFactor);
                                        @endphp
                                                                <span>{{ $ct->name }}: <strong>₹{{ number_format($ctRtFare) }}</strong></span>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if(!empty($cr->default_car_type))
                                                            <span class="admin-badge badge-secondary" style="font-size:10px; padding:2px 6px;">Car: {{ strtoupper($cr->default_car_type) }}</span>
                                                        @else
                                                            <span style="font-size:11px; color:#94a3b8;">Any Car Type</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($cr->is_active)
                                                            <span class="admin-badge badge-success" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="bt-rt-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Active</span>
                                                        @else
                                                            <span class="admin-badge badge-danger" style="cursor:pointer;" onclick="toggleCabRouteStatus({{ $cr->id }})" id="bt-rt-route-status-badge-{{ $cr->id }}" title="Click to toggle status">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div class="admin-action-btn-group" style="justify-content:flex-end;">
                                                            <button type="button" class="admin-btn-action btn-action-edit" onclick="openEditCabRouteModal({{ $cr->id }})" title="Edit Route & Return Discount">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                            <button type="button" class="admin-btn-action btn-action-delete" onclick="deleteCabRoute({{ $cr->id }})" title="Delete Route">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">No round-trip routes configured yet.</td></tr>
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
                                                        @if(!empty($lp->default_car_type))
                                                            <div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: {{ strtoupper($lp->default_car_type) }}</span></div>
                                                        @endif
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
                                                        @if(!empty($ah->default_car_type))
                                                            <div style="margin-top:3px;"><span class="admin-badge badge-secondary" style="font-size:9.5px; padding:1px 5px;" title="Specific Car Type">Car: {{ strtoupper($ah->default_car_type) }}</span></div>
                                                        @endif
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
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                                        <span style="font-size:12px; color:#475569;">Live fare rules synced with <strong>Fares & Surcharges</strong>.</span>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="switchAdminSection('pricing'); switchPricingSubTab('conditions');">
                                            <span class="material-symbols-outlined" style="font-size:15px;">tune</span>
                                            <span>Edit All Rates, Night Hours & Conditions</span>
                                        </button>
                                    </div>
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
                                            <div style="margin-top:10px; padding-top:8px; border-top:1px dashed #e2e8f0;">
                                                <label for="rule-car-type-oneway" style="display:block; font-size:10.5px; font-weight:700; color:#475569; margin-bottom:4px;">Specific Car Type (Optional)</label>
                                                <select id="rule-car-type-oneway" class="form-control booking-type-car-select" style="font-size:11.5px; padding:4px 8px;" onchange="saveBookingTypeCarTypeRule('oneway', this.value)">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_oneway'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
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
                                                <div><strong>Min Guarantee:</strong> {{ $rtMinKm }} KM / Calendar Day</div>
                                                <div><strong>Night Halt Fee:</strong> ₹{{ number_format($nightRate) }} / night ({{ $nightWindowLabel }})</div>
                                                <div><strong>Return Date:</strong> Enforced post-pickup</div>
                                            </div>
                                            <div style="margin-top:10px; padding-top:8px; border-top:1px dashed #e2e8f0;">
                                                <label for="rule-car-type-roundtrip" style="display:block; font-size:10.5px; font-weight:700; color:#475569; margin-bottom:4px;">Specific Car Type (Optional)</label>
                                                <select id="rule-car-type-roundtrip" class="form-control booking-type-car-select" style="font-size:11.5px; padding:4px 8px;" onchange="saveBookingTypeCarTypeRule('roundtrip', this.value)">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_roundtrip'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
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
                                            <div style="margin-top:10px; padding-top:8px; border-top:1px dashed #e2e8f0;">
                                                <label for="rule-car-type-local" style="display:block; font-size:10.5px; font-weight:700; color:#475569; margin-bottom:4px;">Specific Car Type (Optional)</label>
                                                <select id="rule-car-type-local" class="form-control booking-type-car-select" style="font-size:11.5px; padding:4px 8px;" onchange="saveBookingTypeCarTypeRule('local', this.value)">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_local'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
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
                                            <div style="margin-top:10px; padding-top:8px; border-top:1px dashed #e2e8f0;">
                                                <label for="rule-car-type-airport" style="display:block; font-size:10.5px; font-weight:700; color:#475569; margin-bottom:4px;">Specific Car Type (Optional)</label>
                                                <select id="rule-car-type-airport" class="form-control booking-type-car-select" style="font-size:11.5px; padding:4px 8px;" onchange="saveBookingTypeCarTypeRule('airport', this.value)">
                                                    <option value="">-- Any / All Car Types --</option>
                                                    @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                                        <option value="{{ strtolower($ct->category_key) }}" {{ ($platformSettings['default_car_type_airport'] ?? '') === strtolower($ct->category_key) ? 'selected' : '' }}>{{ $ct->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. DRIVERS & FLEET MANAGEMENT -->
                        @php
                            $fleetDriversList = $allDrivers ?? $drivers ?? collect();
                            $driverEmailsByLast10 = [];
                            foreach (($allUsers ?? []) as $usr) {
                                if ($usr->role === 'driver' && ! empty($usr->phone)) {
                                    $dDigits = preg_replace('/[^0-9]/', '', (string) $usr->phone) ?? '';
                                    $dKey = strlen($dDigits) >= 10 ? substr($dDigits, -10) : $dDigits;
                                    if ($dKey !== '') {
                                        $driverEmailsByLast10[$dKey] = (string) $usr->email;
                                    }
                                }
                            }
@endphp
                        <div class="admin-section" id="admin-sec-fleet">
                            <div class="card">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">badge</span>
                                            <span>Registered Fleet &amp; Driver Verification Directory</span>
                                        </h3>
                                        <p class="card-subtitle">Chauffeurs verified with Police Clearances &amp; Commercial Permits</p>
                                    </div>
                                    <span class="admin-count-pill" id="admin-fleet-filter-count">{{ count($fleetDriversList) }} Chauffeurs</span>
                                </div>

                                <!-- Search & Filter Toolbar -->
                                <div class="admin-bookings-toolbar">
                                    <div class="admin-search-box" style="flex:1; min-width:260px;">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="admin-fleet-search-input" placeholder="Search by driver name, phone number, email, or car number..." oninput="handleAdminFleetFilterChange()">
                                    </div>
                                    <div class="admin-toolbar-filters">
                                        <div class="admin-filter-item">
                                            <label for="admin-fleet-filter-category">Category:</label>
                                            <select id="admin-fleet-filter-category" onchange="handleAdminFleetFilterChange()">
                                                <option value="all">All Categories</option>
                                                <option value="sedan">Sedan</option>
                                                <option value="prime">Prime</option>
                                                <option value="suv">SUV</option>
                                                <option value="luxury">Luxury</option>
                                                <option value="sleeper_van">Sleeper Van</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('admin-fleet-search-input').value=''; document.getElementById('admin-fleet-filter-category').value='all'; handleAdminFleetFilterChange();">
                                            <span>Reset</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="admin-table-responsive">
                                    <table class="admin-data-table">
                                        <thead>
                                            <tr>
                                                <th>Chauffeur</th>
                                                <th>Vehicle Model &amp; Plate</th>
                                                <th>Category</th>
                                                <th>Rating &amp; Trips</th>
                                                <th>Wallet Escrow</th>
                                                <th>Compliance</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-fleet-directory-tbody">
                                            @forelse($fleetDriversList as $driver)
                                            @php
                                                $drvDigits = preg_replace('/[^0-9]/', '', (string) $driver->phone) ?? '';
                                                $drvLast10 = strlen($drvDigits) >= 10 ? substr($drvDigits, -10) : $drvDigits;
                                                $drvEmail = $driverEmailsByLast10[$drvLast10] ?? '';
                                                $plateCompact = preg_replace('/\s+/', '', (string) $driver->vehicle_plate) ?? '';
                                                $fleetSearchBlob = strtolower(trim(implode(' ', array_filter([
                                                    $driver->name,
                                                    $driver->phone,
                                                    $drvDigits,
                                                    $drvEmail,
                                                    $driver->vehicle_model,
                                                    $driver->vehicle_plate,
                                                    $plateCompact,
                                                    $driver->fleet_category,
                                                ]))));
                    @endphp
                                            <tr class="admin-fleet-row" data-search="{{ $fleetSearchBlob }}" data-category="{{ strtolower((string) $driver->fleet_category) }}">
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <span style="font-size:22px; width:32px; height:32px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">@include('partials.avatar', ['avatar' => $driver->avatar ?? null, 'fallback' => '👨🏽‍✈️', 'alt' => $driver->name])</span>
                                                        <div>
                                                            <div style="font-weight:700; font-size:13px;">{{ $driver->name }}</div>
                                                            <div style="font-size:11px; color:#64748b;">{{ $driver->phone }}</div>
                                                            @if($drvEmail !== '')
                                                                <div style="font-size:11px; color:#0284c7;">{{ $drvEmail }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600; font-size:12.5px;">{{ $driver->vehicle_model }}</div>
                                                    <div style="font-size:11px; font-family:monospace; color:#0f172a; font-weight:700;">{{ $driver->vehicle_plate }}</div>
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
                                            @empty
                                            <tr data-empty-row="1">
                                                <td colspan="6" style="text-align:center; padding:24px; color:#94a3b8;">No drivers registered yet.</td>
                                            </tr>
                                            @endforelse
                                            <tr id="admin-fleet-empty-row" data-empty-row="1" style="display:none;">
                                                <td colspan="6" style="text-align:center; padding:24px; color:#64748b;">No drivers match your search criteria.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="admin-pagination-bar" id="pag-bar-admin-fleet"></div>
                            </div>
                        </div>

                        <!-- 6.5 DRIVER PAYOUT APPROVALS -->
                        @php
                            $adminPayoutList = collect($adminPayoutRequests ?? []);
                            $pendingPayoutBadgeCount = $adminPayoutList->where('status', 'pending')->count();
                        @endphp
                        <div class="admin-section" id="admin-sec-payouts">
                            <div class="card">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:18px;">account_balance_wallet</span>
                                            <span>Driver Wallet Payout Approvals</span>
                                        </h3>
                                        <p class="card-subtitle">Approve driver withdrawal requests via Manual Transfer (UTR/IMPS) or Payment Gateway API, or reject &amp; refund to wallet</p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <select id="admin-payouts-status-filter" class="form-control" style="height:34px; font-size:12px;" onchange="loadAdminPayoutRequests(this.value)">
                                            <option value="all">All Requests</option>
                                            <option value="pending">Pending Approval</option>
                                            <option value="paid">Paid</option>
                                            <option value="rejected">Rejected &amp; Refunded</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="loadAdminPayoutRequests()">
                                            <span class="material-symbols-outlined" style="font-size:15px;">refresh</span>
                                            <span>Refresh</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="driver-responsive-table-wrap" tabindex="0" role="region" aria-label="Driver Payout Requests">
                                    <table class="admin-data-table admin-table-compact driver-responsive-table">
                                        <thead>
                                            <tr>
                                                <th>Request Ref</th>
                                                <th>Driver</th>
                                                <th>Destination (UPI / Bank)</th>
                                                <th>Amount</th>
                                                <th>Status &amp; Mode</th>
                                                <th style="text-align:right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-payouts-tbody">
                                            @forelse($adminPayoutList as $pr)
                                                <tr>
                                                    <th>
                                                        <div class="booking-ref-link">{{ $pr->payout_reference }}</div>
                                                        <div class="cell-subtext">{{ $pr->requested_at ? $pr->requested_at->format('d M Y, h:i A') : $pr->created_at?->format('d M Y, h:i A') }}</div>
                                                    </th>
                                                    <td data-col-label="Driver">
                                                        <div class="cell-primary-text">{{ $pr->driver?->name ?? 'Driver' }}</div>
                                                        <div class="cell-subtext">{{ $pr->driver?->phone ?? '' }}</div>
                                                    </td>
                                                    <td data-col-label="Destination">
                                                        <div class="cell-primary-text">{{ strtoupper($pr->payout_method) }}</div>
                                                        <div class="cell-subtext">{{ $pr->destinationSummary() }}</div>
                                                    </td>
                                                    <td data-col-label="Amount" class="text-tabular">
                                                        <strong>₹{{ number_format((float) $pr->amount) }}</strong>
                                                    </td>
                                                    <td data-col-label="Status & Mode">
                                                        @if($pr->status === 'paid')
                                                            <span class="admin-badge badge-success" style="font-size:10px;">PAID ({{ strtoupper($pr->payout_mode ?? 'manual') }})</span>
                                                            @if($pr->transaction_reference)
                                                                <div class="cell-subtext" style="font-family:monospace;">Ref: {{ $pr->transaction_reference }}</div>
                                                            @endif
                                                        @elseif($pr->status === 'rejected')
                                                            <span class="admin-badge badge-danger" style="font-size:10px;">REJECTED &amp; REFUNDED</span>
                                                        @else
                                                            <span class="admin-badge badge-warning" style="font-size:10px;">PENDING APPROVAL</span>
                                                        @endif
                                                    </td>
                                                    <td data-col-label="Action" style="text-align:right;">
                                                        @if($pr->status === 'pending')
                                                            @php
                                                                $prPayload = [
                                                                    'id' => $pr->id,
                                                                    'payout_reference' => $pr->payout_reference,
                                                                    'driver_name' => $pr->driver?->name,
                                                                    'amount' => (float) $pr->amount,
                                                                    'payout_method' => $pr->payout_method,
                                                                    'destination_summary' => $pr->destinationSummary(),
                                                                ];
                                                            @endphp
                                                            <button type="button" class="btn btn-xs btn-primary" onclick='openAdminProcessPayoutModal(@json($prPayload))'>
                                                                <span>Approve / Process</span>
                                                            </button>
                                                        @else
                                                            <span class="cell-subtext">{{ $pr->processed_at?->format('d M, h:i A') ?? 'Processed' }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" style="text-align:center; padding:24px; color:#64748b;">No driver payout requests yet.</td>
                                                </tr>
                                            @endforelse
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

                                @if(config('demo.enabled'))
                                <div class="card demo-only">
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
                                @endif
                            </div>

                            <!-- Platform Feature & Booking Module Controls -->
                            <div class="card" style="margin-top:20px;">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">tune</span>
                                            <span>Platform Feature & Booking Module Controls</span>
                                        </h3>
                                        <p class="card-subtitle">Turn booking services ON or OFF across the public customer portal and mobile dock</p>
                                    </div>
                                    <span class="admin-badge badge-primary">Modules Toggle</span>
                                </div>

                                <div style="padding:16px 20px;">
                                    <!-- Schedule & Berth Feature Switch Row -->
                                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:16px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                                        <div style="display:flex; align-items:flex-start; gap:14px; max-width:650px;">
                                            <div style="width:42px; height:42px; border-radius:8px; background:rgba(217,119,6,0.1); color:#d97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                                <span class="material-symbols-outlined" style="font-size:24px;">directions_bus</span>
                                            </div>
                                            <div>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <span style="font-weight:700; font-size:15px; color:#1e293b;">Schedules & Sleeper Berths Booking Option</span>
                                                    <span id="setting-schedules-berths-badge" class="badge {{ ($isSchedulesEnabled ?? false) ? 'badge-success' : 'badge-danger' }}" style="font-size:11px; padding:2px 8px;">
                                                        {{ ($isSchedulesEnabled ?? false) ? 'ON (Active)' : 'OFF (Disabled)' }}
                                                    </span>
                                                </div>
                                                <p style="font-size:12.5px; color:#64748b; margin:4px 0 0; line-height:1.45;">
                                                    Enable or disable the <strong>Schedules & Berths</strong> booking channel for passengers. When kept <strong>OFF</strong>, the Schedules navigation tab, dropdown item, and mobile dock button remain hidden from customers, focusing the platform strictly on Outstation Cabs.
                                                </p>
                                            </div>
                                        </div>

                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <label class="switch-toggle" style="position:relative; display:inline-block; width:52px; height:28px; margin:0; cursor:pointer;">
                                                <input type="checkbox" id="setting-schedules-berths-toggle" {{ ($isSchedulesEnabled ?? false) ? 'checked' : '' }} onchange="toggleAdminSchedulesAndBerths(this.checked)" style="opacity:0; width:0; height:0;">
                                                <span class="switch-slider" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:{{ ($isSchedulesEnabled ?? false) ? '#10b981' : '#cbd5e1' }}; transition:.3s; border-radius:28px;">
                                                    <span style="position:absolute; content:''; height:20px; width:20px; left:{{ ($isSchedulesEnabled ?? false) ? '28px' : '4px' }}; bottom:4px; background-color:white; transition:.3s; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                                                </span>
                                            </label>
                                            <button type="button" class="btn btn-sm {{ ($isSchedulesEnabled ?? false) ? 'btn-outline-danger' : 'btn-primary' }}" id="setting-schedules-berths-btn" onclick="toggleAdminSchedulesAndBerths(!document.getElementById('setting-schedules-berths-toggle').checked)">
                                                <span class="material-symbols-outlined" style="font-size:15px;">{{ ($isSchedulesEnabled ?? false) ? 'power_settings_new' : 'check_circle' }}</span>
                                                <span>{{ ($isSchedulesEnabled ?? false) ? 'Turn OFF' : 'Turn ON' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkout Payment Options & Advance Percentage Settings -->
                            @php
                                $adminPaySettings = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
                                $adminAdvPct = $adminPaySettings['payment_advance_pct'] ?? '20';
                                $adminPayDefault = $adminPaySettings['payment_default_option'] ?? 'full_online';
    @endphp
                            <div class="card" style="margin-top:20px;" id="admin-payment-options-card">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">payments</span>
                                            <span>Checkout Payment Options & Advance Percentage</span>
                                        </h3>
                                        <p class="card-subtitle">Control which payment modes customers see at checkout and set the advance percentage for Part Pay</p>
                                    </div>
                                    <span class="admin-badge badge-gold">Checkout Controls</span>
                                </div>

                                <form id="admin-payment-options-form" onsubmit="saveAdminPaymentOptions(event)" style="padding:16px 20px;">
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:16px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-payment-advance-pct">
                                                <span style="font-weight:700;">Advance Payment Percentage (%)</span>
                                                <span style="font-size:11px; color:#64748b;">(1% – 99% for Part Pay)</span>
                                            </label>
                                            <div class="input-with-icon">
                                                <span class="material-symbols-outlined">percent</span>
                                                <input type="number" id="setting-payment-advance-pct" class="form-control" min="1" max="99" step="1" value="{{ $adminAdvPct }}" required>
                                            </div>
                                        </div>

                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-payment-default-option">
                                                <span style="font-weight:700;">Default Selected Option</span>
                                                <span style="font-size:11px; color:#64748b;">(Pre-selected at checkout)</span>
                                            </label>
                                            <select id="setting-payment-default-option" class="form-control">
                                                <option value="full_online" {{ $adminPayDefault === 'full_online' ? 'selected' : '' }}>Full Pay Online (Card / NetBanking / UPI)</option>
                                                <option value="part_pay" {{ $adminPayDefault === 'part_pay' ? 'selected' : '' }}>Part Pay (Advance % + Balance to Driver)</option>
                                                <option value="full_upi" {{ $adminPayDefault === 'full_upi' ? 'selected' : '' }}>Full UPI Payment</option>
                                                <option value="cash" {{ $adminPayDefault === 'cash' ? 'selected' : '' }}>Cash / Pay Later to Chauffeur</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:8px;">Visible Checkout Payment Options</div>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; margin-bottom:16px;">
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px;">
                                            <input type="checkbox" id="setting-pay-show-part" {{ ($adminPaySettings['payment_show_part_pay'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span><strong>Part Pay</strong> (Advance %)</span>
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px;">
                                            <input type="checkbox" id="setting-pay-show-full" {{ ($adminPaySettings['payment_show_full_online'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span><strong>Full Pay Online</strong> (Card / NetBanking)</span>
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px;">
                                            <input type="checkbox" id="setting-pay-show-upi" {{ ($adminPaySettings['payment_show_full_upi'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span><strong>Full UPI</strong> (Instant UPI)</span>
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; cursor:pointer; font-size:13px;">
                                            <input type="checkbox" id="setting-pay-show-cash" {{ ($adminPaySettings['payment_show_cash'] ?? '1') === '1' ? 'checked' : '' }}>
                                            <span><strong>Cash / Pay Later</strong> (To Chauffeur)</span>
                                        </label>
                                    </div>

                                    <!-- Direct UPI ID, QR Code & Admin UTR Verification Configuration -->
                                    @php
                                        $adminUpiId = $adminPaySettings['upi_id'] ?? 'intercitywheels@upi';
                                        $adminUpiPayee = $adminPaySettings['upi_payee_name'] ?? 'Intercity Wheels';
                                        $adminUpiInstructions = $adminPaySettings['upi_instructions'] ?? 'Scan QR or pay to this UPI ID via GPay, PhonePe, Paytm, or BHIM, then enter your 12-digit UTR / Ref ID for Admin confirmation (or choose Driver Collection on completion).';
                                        $adminUpiQrUrl = $adminPaySettings['upi_qr_url'] ?? '';
                                    @endphp
                                    <div style="border-top:1px dashed #cbd5e1; padding-top:16px; margin-bottom:16px;" id="admin-upi-settings-box">
                                        <div style="margin-bottom:10px;">
                                            <div style="font-size:13.5px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                                <span class="material-symbols-outlined" style="color:#d49b4c; font-size:19px;">qr_code_2</span>
                                                <span>Direct UPI ID &amp; QR Code Settings (Shown to Customer at Checkout)</span>
                                            </div>
                                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Set your UPI ID and optionally upload a custom QR image (if blank, a live scannable QR is auto-generated from your UPI ID and exact fare).</div>
                                        </div>
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-upi-id">Platform UPI ID (VPA)</label>
                                                <input type="text" id="setting-upi-id" class="form-control" value="{{ $adminUpiId }}" placeholder="intercitywheels@upi" oninput="renderAdminUpiQrPreview()">
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-upi-payee-name">UPI Payee / Account Name</label>
                                                <input type="text" id="setting-upi-payee-name" class="form-control" value="{{ $adminUpiPayee }}" placeholder="Intercity Wheels" oninput="renderAdminUpiQrPreview()">
                                            </div>
                                            <div class="form-group" style="margin-bottom:0; grid-column:1 / -1;">
                                                <label class="form-label" for="setting-upi-instructions">UPI Checkout Instructions</label>
                                                <input type="text" id="setting-upi-instructions" class="form-control" value="{{ $adminUpiInstructions }}" placeholder="Pay to this UPI ID and enter your 12-digit UTR...">
                                            </div>
                                            <div style="grid-column:1 / -1; display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                                                <div id="admin-upi-qr-preview" style="width:110px; height:110px; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;">
                                                    @if($adminUpiQrUrl !== '')
                                                        <img src="{{ $adminUpiQrUrl }}" alt="Custom UPI QR" style="width:100%; height:100%; object-fit:contain;">
                                                    @else
                                                        <span style="font-size:11px; color:#64748b; text-align:center; padding:6px;">Auto QR</span>
                                                    @endif
                                                </div>
                                                <div style="flex:1; min-width:220px; display:flex; flex-direction:column; gap:8px;">
                                                    <div style="font-size:12px; font-weight:700; color:#0f172a;">
                                                        UPI QR Code Image
                                                        <span id="admin-upi-qr-mode-badge" class="admin-badge {{ $adminUpiQrUrl !== '' ? 'badge-gold' : 'badge-success' }}" style="font-size:10px; margin-left:6px;">
                                                            {{ $adminUpiQrUrl !== '' ? 'CUSTOM QR IMAGE' : 'AUTO-GENERATED FROM UPI ID' }}
                                                        </span>
                                                    </div>
                                                    <input type="text" id="setting-upi-qr-url" class="form-control" value="{{ $adminUpiQrUrl }}" placeholder="Optional custom QR image URL (leave blank for auto-generated QR)" oninput="renderAdminUpiQrPreview()" style="font-size:12px;">
                                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                        <label class="btn btn-xs btn-outline" style="cursor:pointer; margin:0;">
                                                            <span class="material-symbols-outlined" style="font-size:14px;">upload</span>
                                                            <span>Upload QR Image</span>
                                                            <input type="file" id="setting-upi-qr-file" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="uploadAdminUpiQr(this)">
                                                        </label>
                                                        <button type="button" class="btn btn-xs btn-outline" onclick="clearAdminUpiQr()">
                                                            <span class="material-symbols-outlined" style="font-size:14px;">auto_awesome</span>
                                                            <span>Use Auto-Generated QR</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Razorpay Payment Gateway Configuration -->
                                    @php
                                        $rzpGateway = $adminPaySettings['payment_gateway'] ?? 'manual';
                                        $rzpMode = $adminPaySettings['razorpay_mode'] ?? 'test';
                                        $rzpKeyId = $adminPaySettings['razorpay_key_id'] ?? '';
                                        $rzpDisplayName = $adminPaySettings['razorpay_display_name'] ?? 'Intercity Wheels';
                                        $rzpSecretSet = ($adminPaySettings['razorpay_secret_configured'] ?? '0') === '1';
            @endphp
                                    <div style="border-top:1px dashed #cbd5e1; padding-top:16px; margin-bottom:16px;" id="admin-razorpay-gateway-box">
                                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                                            <div>
                                                <div style="font-size:13.5px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                                    <span class="material-symbols-outlined" style="color:#0284c7; font-size:19px;">account_balance</span>
                                                    <span>Online Payment Gateway (Razorpay)</span>
                                                    <span id="setting-razorpay-status-badge" class="badge {{ $rzpGateway === 'razorpay' ? 'badge-success' : 'badge-secondary' }}" style="font-size:10.5px; padding:2px 8px;">
                                                        {{ $rzpGateway === 'razorpay' ? 'RAZORPAY ACTIVE' : 'MANUAL / ESCROW' }}
                                                    </span>
                                                </div>
                                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Enable Razorpay Checkout for online payments. When Razorpay is OFF, the Manual UPI method (UPI ID + Admin UTR Confirmation / Driver Collection) acts automatically.</div>
                                            </div>

                                            <label style="display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #bae6fd; background:#f0f9ff; border-radius:8px; cursor:pointer; font-size:12.5px; font-weight:700; color:#0369a1;">
                                                <input type="checkbox" id="setting-razorpay-enabled" {{ $rzpGateway === 'razorpay' ? 'checked' : '' }} onchange="toggleRazorpayAdminBadge()">
                                                <span>Use Razorpay Gateway</span>
                                            </label>
                                        </div>

                                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:10px 14px; margin-bottom:12px; background:#0b0f19; border:1px solid #d49b4c; border-radius:8px; color:#f8fafc;">
                                            <div style="display:flex; align-items:center; gap:8px; font-size:12.5px;">
                                                <span class="material-symbols-outlined" style="color:#f5d061; font-size:18px;">vpn_key</span>
                                                <span>Need your Razorpay Key ID &amp; Secret?</span>
                                            </div>
                                            <a href="http://mabia.in/m/razorpay" target="_blank" rel="noopener noreferrer" id="get-razorpay-credentials-link" class="btn btn-sm btn-gold" style="text-decoration:none; font-weight:800;">
                                                <span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span>
                                                <span>Get Razorpay Credentials — Click Here</span>
                                            </a>
                                        </div>

                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-razorpay-mode">Gateway Mode</label>
                                                <select id="setting-razorpay-mode" class="form-control">
                                                    <option value="test" {{ $rzpMode === 'test' ? 'selected' : '' }}>Test / Sandbox (rzp_test_...)</option>
                                                    <option value="live" {{ $rzpMode === 'live' ? 'selected' : '' }}>Live Production (rzp_live_...)</option>
                                                </select>
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-razorpay-key-id">Razorpay Key ID</label>
                                                <input type="text" id="setting-razorpay-key-id" class="form-control" value="{{ $rzpKeyId }}" placeholder="rzp_test_xxxxxxxxxxxx">
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-razorpay-key-secret">
                                                    <span>Razorpay Key Secret</span>
                                                    <span id="setting-razorpay-secret-hint" style="font-size:10.5px; font-weight:600; color:{{ $rzpSecretSet ? '#16a34a' : '#64748b' }};">
                                                        {{ $rzpSecretSet ? '(Saved — leave blank to keep)' : '(Required for Razorpay)' }}
                                                    </span>
                                                </label>
                                                <input type="password" id="setting-razorpay-key-secret" class="form-control" value="" autocomplete="new-password" placeholder="{{ $rzpSecretSet ? '••••••••••••••••••••' : 'Enter Razorpay Key Secret' }}">
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-razorpay-display-name">Checkout Business Name</label>
                                                <input type="text" id="setting-razorpay-display-name" class="form-control" value="{{ $rzpDisplayName }}" placeholder="Intercity Wheels">
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-razorpayx-account-number">RazorpayX Payout Account No. (Optional)</label>
                                                <input type="text" id="setting-razorpayx-account-number" class="form-control" value="{{ $adminPaySettings['razorpayx_account_number'] ?? '' }}" placeholder="For Gateway API Driver Payouts">
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex; justify-content:flex-end;">
                                        <button type="submit" id="save-payment-options-btn" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Payment Options</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Invoice Template & Branding Settings -->
                            @php
                                $invCfg = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
                                $invStyle = $invCfg['invoice_template_style'] ?? 'classic_gst';
                                $invAccent = $invCfg['invoice_accent_color'] ?? '#d49b4c';
    @endphp
                            <div class="card" style="margin-top:20px;" id="admin-invoice-template-card">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">receipt_long</span>
                                            <span>Tax Invoice Template & Branding</span>
                                        </h3>
                                        <p class="card-subtitle">Choose from 4 pre-built invoice layouts and customize company details, GST rates, and footer notes</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="previewInvoiceTemplate()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">visibility</span>
                                        <span>Preview Sample Invoice</span>
                                    </button>
                                </div>

                                <form id="admin-invoice-template-form" onsubmit="saveAdminInvoiceTemplate(event)" style="padding:16px 20px;">
                                    <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:8px;">Select Pre-Built Invoice Style</div>
                                    <div class="inv-style-picker-grid" id="inv-style-picker-grid">
                                        <label class="inv-style-card {{ $invStyle === 'classic_gst' ? 'selected' : '' }}" data-style-option="classic_gst" onclick="selectInvoiceTemplateStyle('classic_gst')">
                                            <input type="radio" name="invoice_template_style" value="classic_gst" {{ $invStyle === 'classic_gst' ? 'checked' : '' }}>
                                            <div class="inv-mini-thumb thumb-classic">
                                                <div class="inv-mini-bar"></div>
                                                <div class="inv-mini-bar short"></div>
                                                <div class="inv-mini-bar"></div>
                                            </div>
                                            <div>
                                                <div class="inv-style-label">Classic GST</div>
                                                <div class="inv-style-desc">Traditional corporate tax invoice with gold border & seal</div>
                                            </div>
                                        </label>

                                        <label class="inv-style-card {{ $invStyle === 'executive_dark' ? 'selected' : '' }}" data-style-option="executive_dark" onclick="selectInvoiceTemplateStyle('executive_dark')">
                                            <input type="radio" name="invoice_template_style" value="executive_dark" {{ $invStyle === 'executive_dark' ? 'checked' : '' }}>
                                            <div class="inv-mini-thumb thumb-executive">
                                                <div class="mini-top"></div>
                                                <div style="padding:5px; display:flex; flex-direction:column; gap:4px;">
                                                    <div class="inv-mini-bar"></div>
                                                    <div class="inv-mini-bar short"></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="inv-style-label">Executive Navy</div>
                                                <div class="inv-style-desc">Bold dark navy banner header with boxed party cards</div>
                                            </div>
                                        </label>

                                        <label class="inv-style-card {{ $invStyle === 'modern_minimal' ? 'selected' : '' }}" data-style-option="modern_minimal" onclick="selectInvoiceTemplateStyle('modern_minimal')">
                                            <input type="radio" name="invoice_template_style" value="modern_minimal" {{ $invStyle === 'modern_minimal' ? 'checked' : '' }}>
                                            <div class="inv-mini-thumb thumb-minimal">
                                                <div class="inv-mini-bar"></div>
                                                <div class="inv-mini-bar short"></div>
                                                <div class="inv-mini-bar"></div>
                                            </div>
                                            <div>
                                                <div class="inv-style-label">Modern Minimal</div>
                                                <div class="inv-style-desc">Clean borderless layout with left accent stripe & pill badge</div>
                                            </div>
                                        </label>

                                        <label class="inv-style-card {{ $invStyle === 'compact_receipt' ? 'selected' : '' }}" data-style-option="compact_receipt" onclick="selectInvoiceTemplateStyle('compact_receipt')">
                                            <input type="radio" name="invoice_template_style" value="compact_receipt" {{ $invStyle === 'compact_receipt' ? 'checked' : '' }}>
                                            <div class="inv-mini-thumb thumb-compact">
                                                <div class="inv-mini-bar" style="margin:0 auto;"></div>
                                                <div class="inv-mini-bar short" style="margin:0 auto;"></div>
                                                <div class="inv-mini-bar" style="margin:0 auto;"></div>
                                            </div>
                                            <div>
                                                <div class="inv-style-label">Compact Receipt</div>
                                                <div class="inv-style-desc">Narrow dashed thermal-style express trip receipt</div>
                                            </div>
                                        </label>
                                    </div>

                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:12px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-brand-name">Brand Heading</label>
                                            <input type="text" id="setting-inv-brand-name" class="form-control" value="{{ $invCfg['invoice_brand_name'] ?? 'INTERCITY WHEELS' }}" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-legal-name">Legal Company Name</label>
                                            <input type="text" id="setting-inv-legal-name" class="form-control" value="{{ $invCfg['invoice_company_legal'] ?? 'IntercityWheels Mobility Services Pvt. Ltd.' }}" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-title">Document Title</label>
                                            <input type="text" id="setting-inv-title" class="form-control" value="{{ $invCfg['invoice_title'] ?? 'TAX INVOICE' }}" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-prefix">Invoice Number Prefix</label>
                                            <input type="text" id="setting-inv-prefix" class="form-control" value="{{ $invCfg['invoice_prefix'] ?? 'INV' }}" required>
                                        </div>
                                    </div>

                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:12px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-gstin">GSTIN</label>
                                            <input type="text" id="setting-inv-gstin" class="form-control" value="{{ $invCfg['invoice_gstin'] ?? '27AAACI9912K1Z8' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-sac">HSN / SAC Code</label>
                                            <input type="text" id="setting-inv-sac" class="form-control" value="{{ $invCfg['invoice_sac_code'] ?? '996412 (Passenger Transport Service)' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-pos">Place of Supply</label>
                                            <input type="text" id="setting-inv-pos" class="form-control" value="{{ $invCfg['invoice_place_of_supply'] ?? 'Maharashtra (27)' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-accent">Accent Color</label>
                                            <div style="display:flex; gap:6px; align-items:center;">
                                                <input type="color" id="setting-inv-accent" value="{{ $invAccent }}" style="width:44px; height:38px; padding:2px; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer;">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('setting-inv-accent').value='#d49b4c'">Gold</button>
                                                <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('setting-inv-accent').value='#0284c7'">Blue</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:12px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-phone">Support Helpline</label>
                                            <input type="text" id="setting-inv-phone" class="form-control" value="{{ $invCfg['invoice_support_phone'] ?? '700 3434 088' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-email">Billing Email</label>
                                            <input type="email" id="setting-inv-email" class="form-control" value="{{ $invCfg['invoice_support_email'] ?? 'billing@intercitywheels.com' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-cgst">CGST (%)</label>
                                            <input type="number" id="setting-inv-cgst" class="form-control" min="0" max="25" step="0.5" value="{{ $invCfg['invoice_cgst_pct'] ?? '2.5' }}" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-sgst">SGST (%)</label>
                                            <input type="number" id="setting-inv-sgst" class="form-control" min="0" max="25" step="0.5" value="{{ $invCfg['invoice_sgst_pct'] ?? '2.5' }}" required>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label class="form-label" for="setting-inv-address">Registered Office Address</label>
                                        <input type="text" id="setting-inv-address" class="form-control" value="{{ $invCfg['invoice_company_address'] ?? '' }}" required>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-signatory-name">Signatory Organization</label>
                                            <input type="text" id="setting-inv-signatory-name" class="form-control" value="{{ $invCfg['invoice_signatory_name'] ?? 'IntercityWheels Mobility Services' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-inv-signatory-label">Signatory Title</label>
                                            <input type="text" id="setting-inv-signatory-label" class="form-control" value="{{ $invCfg['invoice_signatory_label'] ?? 'Authorized Signatory' }}">
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label class="form-label" for="setting-inv-terms">Invoice Terms & Notes (one per line)</label>
                                        <textarea id="setting-inv-terms" class="form-control" rows="3">{{ $invCfg['invoice_terms'] ?? '' }}</textarea>
                                    </div>

                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                        <div style="display:flex; gap:16px; flex-wrap:wrap;">
                                            <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; cursor:pointer;">
                                                <input type="checkbox" id="setting-inv-show-stamp" {{ ($invCfg['invoice_show_stamp'] ?? '1') === '1' ? 'checked' : '' }}>
                                                <span>Show Payment Status Stamp</span>
                                            </label>
                                            <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; cursor:pointer;">
                                                <input type="checkbox" id="setting-inv-show-otp" {{ ($invCfg['invoice_show_otp'] ?? '1') === '1' ? 'checked' : '' }}>
                                                <span>Show Ride Start OTP on Invoice</span>
                                            </label>
                                        </div>
                                        <button type="submit" id="save-invoice-template-btn" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Invoice Template</span>
                                        </button>
                                    </div>
                                </form>
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

                            <!-- Editable Privacy Policy & Terms & Conditions Page Content -->
                            @php
                                $legalCfg = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
    @endphp
                            <div class="card" style="margin-top:20px;" id="admin-legal-pages-card">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">edit_document</span>
                                            <span>Editable Privacy Policy &amp; Terms Pages</span>
                                        </h3>
                                        <p class="card-subtitle">Customize the live content shown on <a href="{{ url('/privacy-policy') }}" target="_blank" style="color:#0284c7; text-decoration:underline;">/privacy-policy</a> and <a href="{{ url('/terms-conditions') }}" target="_blank" style="color:#0284c7; text-decoration:underline;">/terms-conditions</a></p>
                                    </div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="{{ url('/privacy-policy') }}" target="_blank" class="btn btn-sm btn-outline">
                                            <span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span>
                                            <span>View Privacy Policy</span>
                                        </a>
                                        <a href="{{ url('/terms-conditions') }}" target="_blank" class="btn btn-sm btn-outline">
                                            <span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span>
                                            <span>View T&amp;Cs</span>
                                        </a>
                                    </div>
                                </div>

                                <form id="admin-legal-pages-form" onsubmit="saveAdminLegalPages(event)" style="padding:16px 20px;">
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:18px; margin-bottom:16px;">
                                        <!-- Privacy Policy Editor -->
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                                            <div style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:10px;">Privacy Policy (/privacy-policy)</div>
                                            <div class="form-group" style="margin-bottom:10px;">
                                                <label class="form-label" for="setting-privacy-title">Page Heading</label>
                                                <input type="text" id="setting-privacy-title" class="form-control" value="{{ $legalCfg['privacy_policy_title'] ?? 'IntercityWheels Privacy Policy' }}" required>
                                            </div>
                                            <div class="form-group" style="margin-bottom:10px;">
                                                <label class="form-label" for="setting-privacy-updated">Last Updated Label</label>
                                                <input type="text" id="setting-privacy-updated" class="form-control" value="{{ $legalCfg['privacy_policy_updated_at'] ?? 'November 2026' }}" required>
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-privacy-body">Privacy Policy Content (separate sections with a blank line)</label>
                                                <textarea id="setting-privacy-body" class="form-control" rows="8" required>{{ $legalCfg['privacy_policy_body'] ?? '' }}</textarea>
                                            </div>
                                        </div>

                                        <!-- Terms & Conditions Editor -->
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                                            <div style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:10px;">Terms &amp; Conditions (/terms-conditions)</div>
                                            <div class="form-group" style="margin-bottom:10px;">
                                                <label class="form-label" for="setting-terms-title">Page Heading</label>
                                                <input type="text" id="setting-terms-title" class="form-control" value="{{ $legalCfg['terms_conditions_title'] ?? 'IntercityWheels Rider & Chauffeur Terms & Conditions' }}" required>
                                            </div>
                                            <div class="form-group" style="margin-bottom:10px;">
                                                <label class="form-label" for="setting-terms-updated">Last Updated Label</label>
                                                <input type="text" id="setting-terms-updated" class="form-control" value="{{ $legalCfg['terms_conditions_updated_at'] ?? 'November 2026' }}" required>
                                            </div>
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label class="form-label" for="setting-terms-body">Terms &amp; Conditions Content (separate sections with a blank line)</label>
                                                <textarea id="setting-terms-body" class="form-control" rows="8" required>{{ $legalCfg['terms_conditions_body'] ?? '' }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-legal-helpline">Legal Footer Helpline</label>
                                            <input type="text" id="setting-legal-helpline" class="form-control" value="{{ $legalCfg['legal_contact_helpline'] ?? '700 3434 088' }}">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-legal-email">Legal Contact Email</label>
                                            <input type="email" id="setting-legal-email" class="form-control" value="{{ $legalCfg['legal_contact_email'] ?? 'support@intercitywheels.com' }}">
                                        </div>
                                    </div>

                                    <div style="display:flex; justify-content:flex-end;">
                                        <button type="submit" id="save-legal-pages-btn" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Legal Pages Content</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Optional Clerk Authentication Configuration (Hidden on Login Screen by Default) -->
                            @php
                                $clerkCfg = $platformSettings ?? \App\Models\PlatformSetting::allAdminSettings();
                                $clerkEnabled = ($clerkCfg['clerk_login_enabled'] ?? '0') === '1';
                                $clerkPubKey = $clerkCfg['clerk_publishable_key'] ?? '';
                                $clerkSecretSet = ($clerkCfg['clerk_secret_configured'] ?? '0') === '1';
    @endphp
                            <div class="card" style="margin-top:20px;" id="admin-clerk-auth-card">
                                <div class="card-header" style="flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
                                            <span class="material-symbols-outlined" style="color:#6c47ff; font-size:20px;">passkey</span>
                                            <span>Clerk Authentication (Optional Login Provider)</span>
                                            <span id="setting-clerk-status-badge" class="badge {{ $clerkEnabled ? 'badge-success' : 'badge-secondary' }}" style="font-size:10.5px; padding:2px 8px;">
                                                {{ $clerkEnabled ? 'CLERK ENABLED' : 'HIDDEN FROM LOGIN' }}
                                            </span>
                                        </h3>
                                        <p class="card-subtitle">Clerk Auth is hidden from the login screen by default. Enable and configure keys here to display it.</p>
                                    </div>
                                    <label style="display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #ddd6fe; background:#f5f3ff; border-radius:8px; cursor:pointer; font-size:12.5px; font-weight:700; color:#5b21b6;">
                                        <input type="checkbox" id="setting-clerk-enabled" {{ $clerkEnabled ? 'checked' : '' }} onchange="toggleClerkAdminBadge()">
                                        <span>Show Clerk Option on Login Screen</span>
                                    </label>
                                </div>

                                <form id="admin-clerk-auth-form" onsubmit="saveAdminClerkAuthSettings(event)" style="padding:16px 20px;">
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:14px;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-clerk-publishable-key">Clerk Publishable Key</label>
                                            <input type="text" id="setting-clerk-publishable-key" class="form-control" value="{{ $clerkPubKey }}" placeholder="pk_test_... or pk_live_...">
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" for="setting-clerk-secret-key">
                                                <span>Clerk Secret Key</span>
                                                <span id="setting-clerk-secret-hint" style="font-size:10.5px; font-weight:600; color:{{ $clerkSecretSet ? '#16a34a' : '#64748b' }};">
                                                    {{ $clerkSecretSet ? '(Saved — leave blank to keep)' : '(Optional for server verify)' }}
                                                </span>
                                            </label>
                                            <input type="password" id="setting-clerk-secret-key" class="form-control" value="" autocomplete="new-password" placeholder="{{ $clerkSecretSet ? '••••••••••••••••••••' : 'sk_test_...' }}">
                                        </div>
                                    </div>

                                    <div style="display:flex; justify-content:flex-end;">
                                        <button type="submit" id="save-clerk-auth-btn" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                                            <span>Save Clerk Auth Settings</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- 5. SERVICE LOCATIONS SETUP (Admin)                       -->
                        <!-- ======================================================== -->
                        <div class="admin-section" id="admin-sec-locations">
                            <div class="card" id="admin-discovered-cities-card" style="margin-bottom:20px; border-left:4px solid #f59e0b;">
                                <div class="card-header" style="flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <h3 class="card-title">
                                            <span class="material-symbols-outlined" style="color:#f59e0b; font-size:20px; vertical-align:middle;">travel_explore</span>
                                            <span>Discovered & Beyond-Region Cities (OSM Cache)</span>
                                        </h3>
                                        <p class="card-subtitle">Cities looked up via OpenStreetMap are cached locally. Out-of-region booking destinations await admin review to join the active city list.</p>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="refreshDiscoveredLocations()">
                                        <span class="material-symbols-outlined" style="font-size:16px;">refresh</span>
                                        <span>Refresh Queue</span>
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="admin-table" id="admin-discovered-locations-table">
                                        <thead>
                                            <tr>
                                                <th>City / Place</th>
                                                <th>State</th>
                                                <th>GPS Coordinates</th>
                                                <th>Status</th>
                                                <th>Booking / Hits</th>
                                                <th style="text-align:right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-discovered-cities-tbody">
                                            @forelse(($discoveredLocations ?? collect()) as $disc)
                                            @php
                                                $discStatus = $disc->approval_status ?? 'cached';
                                                $discBadgeStyle = match ($discStatus) {
                                                    'pending' => 'background:#fef3c7; color:#b45309;',
                                                    'approved' => 'background:#dcfce7; color:#15803d;',
                                                    'dismissed' => 'background:#f1f5f9; color:#64748b;',
                                                    default => 'background:#e0f2fe; color:#0369a1;',
                                                };
                                                $discStatusLabel = match ($discStatus) {
                                                    'pending' => 'Beyond Region · Pending Approval',
                                                    'approved' => 'Approved · In City List',
                                                    'dismissed' => 'Dismissed',
                                                    default => ($disc->is_beyond_region ? 'Beyond Region · Cached' : 'OSM Cached'),
                                                };
                    @endphp
                                            <tr id="admin-discovered-row-{{ $disc->id }}">
                                                <td>
                                                    <strong>{{ $disc->name }}</strong>
                                                    @if($disc->display_name && $disc->display_name !== $disc->name)
                                                        <div style="font-size:11px; color:#64748b; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $disc->display_name }}">{{ $disc->display_name }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $disc->state ?: '—' }}</td>
                                                <td style="font-family:monospace; font-size:11.5px; color:#475569;">
                                                    {{ number_format((float) $disc->latitude, 4) }}, {{ number_format((float) $disc->longitude, 4) }}
                                                </td>
                                                <td>
                                                    <span class="admin-badge" id="discovered-status-badge-{{ $disc->id }}" style="{{ $discBadgeStyle }}">{{ $discStatusLabel }}</span>
                                                </td>
                                                <td style="font-size:12px; color:#475569;">
                                                    @if($disc->last_booking_reference)
                                                        <span style="font-weight:600; color:#0f172a;">{{ $disc->last_booking_reference }}</span> ·
                                                    @endif
                                                    <span>{{ (int) $disc->hits }} {{ (int) $disc->hits === 1 ? 'hit' : 'hits' }}</span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <div class="admin-action-btn-group" style="justify-content:flex-end; gap:6px;">
                                                        @if($discStatus !== 'approved')
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="approveDiscoveredLocation({{ $disc->id }}, this)" title="Approve & add to active Service Locations">
                                                            <span class="material-symbols-outlined" style="font-size:15px;">add_location_alt</span>
                                                            <span>Approve &amp; Add</span>
                                                        </button>
                                                        @endif
                                                        <button type="button" class="btn btn-sm btn-outline" onclick="dismissDiscoveredLocation({{ $disc->id }}, this)" title="Remove from OSM cache">
                                                            <span>{{ $discStatus === 'pending' ? 'Dismiss' : 'Remove' }}</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr id="admin-discovered-empty-row">
                                                <td colspan="6" style="text-align:center; padding:18px; color:#94a3b8;">No OpenStreetMap or out-of-region cities cached yet.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

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
                                <div class="admin-pagination-bar" id="pag-bar-admin-locations"></div>
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
                                                    <div class="admin-route-fare-schedule" data-route-id="{{ $cr->id }}" data-roundtrip="0" style="font-size:11px; line-height:1.5; color:#334155; display:flex; flex-wrap:wrap; gap:4px 10px;">
                                                        @foreach($activeRouteCarTypes as $ct)
                                                            @php $ctFare = round($cr->getFareForCarType($ct->category_key, (float) $ct->rate_per_km)); @endphp
                                                            <span>{{ $ct->name }}: <strong>₹{{ number_format($ctFare) }}</strong></span>
                                                        @endforeach
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-cab-routes"></div>
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

                                <div id="berth-routes-feature-alert" style="background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin:0 20px 14px; display:{{ ($isSchedulesEnabled ?? false) ? 'none' : 'flex' }}; align-items:center; justify-content:space-between; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="material-symbols-outlined" style="color:#d97706; font-size:20px;">info</span>
                                        <div>
                                            <strong style="color:#92400e; font-size:12.5px;">Schedules & Berths Option is currently OFF</strong>
                                            <p style="color:#b45309; font-size:11.5px; margin:1px 0 0;">Customer booking for this module is currently disabled. You can manage routes here and turn it ON from Admin Settings.</p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline" style="font-size:11.5px; padding:3px 8px; white-space:nowrap;" onclick="switchAdminSection('system')">
                                        <span>Admin Settings</span>
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-berth-routes"></div>
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-berths"></div>
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
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <span class="admin-badge badge-primary" style="font-size:11px; padding:4px 8px;">{{ count($allCarTypes ?? []) }} Total Fleet Types</span>
                                        <span class="admin-badge badge-success" style="font-size:11px; padding:4px 8px;">{{ ($allCarTypes ?? collect())->where('is_active', true)->count() }} Active</span>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="switchAdminSection('pricing'); switchPricingSubTab('car-rates');">
                                            <span class="material-symbols-outlined" style="font-size:16px;">tune</span>
                                            <span>Bulk Edit Rates</span>
                                        </button>
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
                                                <th>Car Features</th>
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
                                                <td>
                                                    @php $vehFeatures = $veh->featureLabels(); @endphp
                                                    @if(count($vehFeatures) > 0)
                                                        <div style="display:flex; flex-wrap:wrap; gap:3px; max-width:190px;">
                                                            @foreach(array_slice($vehFeatures, 0, 3) as $feature)
                                                                <span class="admin-badge badge-secondary" style="font-size:9.5px; padding:2px 5px; white-space:nowrap;">{{ $feature }}</span>
                                                            @endforeach
                                                            @if(count($vehFeatures) > 3)
                                                                <span class="admin-badge badge-secondary" style="font-size:9.5px; padding:2px 5px;" title="{{ implode(', ', array_slice($vehFeatures, 3)) }}">+{{ count($vehFeatures) - 3 }}</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span style="font-size:10.5px; color:#94a3b8;">Not listed</span>
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
                                <div class="admin-pagination-bar" id="pag-bar-admin-vehicles"></div>
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
                @if($isSchedulesEnabled)
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'schedules' ? 'active' : '' }}" data-target="schedules" id="mobile-dock-btn-schedules">
                    <span class="material-symbols-outlined">directions_bus</span>
                    <span>Schedules</span>
                </button>
                @endif
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
                <button type="button" class="mobile-dock-btn {{ $defaultTab === 'driver-profile' ? 'active' : '' }}" data-target="driver-profile">
                    <span class="material-symbols-outlined">badge</span>
                    <span>Profile</span>
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
<!-- MODAL: MOBILE-FIRST STEP-BY-STEP PAYMENT FLOW            -->
<!-- ======================================================== -->
<div class="modal-overlay" id="checkout-payment-step-modal">
    <div class="modal-dialog" style="max-width: 440px; width: calc(100% - 20px); border-radius: 16px; overflow: hidden; color-scheme: light; accent-color: #0f172a;">
        <div class="modal-header" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; background: #ffffff;">
            <div>
                <div id="step-pay-subtitle" style="font-size: 10.5px; font-weight: 800; color: #b45309; text-transform: uppercase; letter-spacing: 0.06em;">Step 1 of 3</div>
                <h3 class="modal-title" id="step-pay-modal-title" tabindex="-1" style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;">Choose Payment Method</h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('checkout-payment-step-modal')">×</button>
        </div>

        <div class="modal-body" style="padding: 16px 18px; background: #ffffff;">
            <!-- Minimal Progress Bar -->
            <div style="height: 4px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 16px;">
                <div id="step-pay-progress-bar" style="height: 100%; width: 33%; background: linear-gradient(90deg, #d49b4c, #0f172a); transition: width 0.25s ease;"></div>
            </div>

            <!-- SCREEN 2: CHOOSE PAYMENT METHOD (PAYMENT GATEWAY vs MANUAL UPI MODE) -->
            <div id="step-pay-pane-method" style="display:none;">
                <div style="text-align:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px; margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Amount Payable Now</div>
                    <div id="step-method-amount" style="font-size:24px; font-weight:800; color:#0f172a; margin-top:2px;">₹0</div>
                    <div id="step-method-split-note" style="display:none; font-size:11.5px; color:#475569; margin-top:4px;"></div>
                </div>

                <fieldset style="border:0; padding:0; margin:0;">
                    <legend style="font-size:11.5px; font-weight:800; color:#334155; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px; padding:0;">Select How You Want to Pay</legend>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <label id="step-method-razorpay-card" style="display:{{ $isRazorpayActive ? 'flex' : 'none' }}; align-items:center; gap:12px; min-height:56px; padding:14px; border:1.5px solid #cbd5e1; border-radius:12px; cursor:pointer; background:#ffffff;" for="step-channel-razorpay">
                            <input type="radio" id="step-channel-razorpay" name="step_payment_channel" value="razorpay" {{ $isRazorpayActive ? 'checked' : '' }} style="width:18px; height:18px; flex-shrink:0;">
                            <div style="flex:1;">
                                <div style="font-size:13.5px; font-weight:800; color:#0f172a;">Payment Gateway (Razorpay)</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Instant UPI, Card, NetBanking &amp; Wallets — verified automatically</div>
                            </div>
                            <span class="material-symbols-outlined" style="color:#0284c7;">verified_user</span>
                        </label>

                        <label id="step-method-manual-upi-card" style="display:flex; align-items:center; gap:12px; min-height:56px; padding:14px; border:1.5px solid #cbd5e1; border-radius:12px; cursor:pointer; background:#ffffff;" for="step-channel-manual-upi">
                            <input type="radio" id="step-channel-manual-upi" name="step_payment_channel" value="manual_upi" {{ !$isRazorpayActive ? 'checked' : '' }} style="width:18px; height:18px; flex-shrink:0;">
                            <div style="flex:1;">
                                <div style="font-size:13.5px; font-weight:800; color:#0f172a;">Manual UPI Mode (QR &amp; UPI ID)</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Show QR / UPI ID / Open UPI App, then submit UTR for verification</div>
                            </div>
                            <span class="material-symbols-outlined" style="color:#d49b4c;">qr_code_2</span>
                        </label>

                        <label id="step-modal-driver-collect-opt" style="display:{{ $showCash ? 'flex' : 'none' }}; align-items:center; gap:12px; min-height:56px; padding:14px; border:1.5px solid #cbd5e1; border-radius:12px; cursor:pointer; background:#ffffff;" for="step-channel-driver-collect">
                            <input type="radio" id="step-channel-driver-collect" name="step_payment_channel" value="driver_collect" style="width:18px; height:18px; flex-shrink:0;">
                            <div style="flex:1;">
                                <div style="font-size:13.5px; font-weight:800; color:#0f172a;">Pay Driver on Completion</div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Driver collects full fare when drive completes</div>
                            </div>
                            <span class="material-symbols-outlined" style="color:#16a34a;">local_taxi</span>
                        </label>
                    </div>
                </fieldset>

                <!-- Partial Pay Agreement (shown inline when Partial Payment is selected) -->
                <div id="step-method-partpay-box" style="display:none; margin-top:12px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:10px; font-size:12.5px;">
                        <div style="display:flex; justify-content:space-between; color:#475569; margin-bottom:4px;">
                            <span>Total Trip Fare</span>
                            <strong id="step-agree-total" style="color:#0f172a;">₹0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; color:#0284c7; margin-bottom:4px;">
                            <span>Advance Now (<span id="step-agree-adv-pct">20</span>%)</span>
                            <strong id="step-agree-adv">₹0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; color:#b45309; border-top:1px dashed #cbd5e1; padding-top:6px; margin-top:4px;">
                            <span>Balance on Completion</span>
                            <strong id="step-agree-bal">₹0</strong>
                        </div>
                    </div>

                    <label for="step-part-pay-agree-checkbox" style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; background:#fffbeb; border:1px solid #f59e0b; border-radius:10px; padding:10px 12px; font-size:12.5px; color:#0f172a; line-height:1.45; margin:0;">
                        <input type="checkbox" id="step-part-pay-agree-checkbox" style="margin-top:2px; width:18px; height:18px; flex-shrink:0;">
                        <span>
                            <strong>I agree to pay the remaining balance (<span id="step-agree-bal-inline">₹0</span>) and additional charges if any</strong> (extra km, waiting, tolls, parking, or night stay) on trip completion.
                        </span>
                    </label>
                </div>

                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="button" id="step-method-back-btn" class="btn btn-secondary" style="flex:1; min-height:44px;" onclick="closeModal('checkout-payment-step-modal')">Back</button>
                    <button type="button" id="step-method-next-btn" class="btn btn-primary" style="flex:2; min-height:44px;" onclick="proceedFromMethodStep()">
                        <span>Continue</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            <!-- SCREEN 3: MANUAL UPI — SCAN QR CODE, UPI ID & TOUCH TO OPEN UPI APP -->
            <div id="step-pay-pane-qr" style="display:none;">
                <div style="text-align:center; margin-bottom:12px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Scan &amp; Pay Exact Amount</div>
                    <div id="step-utr-amount-now" style="font-size:26px; font-weight:800; color:#0f172a; margin:2px 0;">₹0</div>
                    <div style="font-size:12px; color:#475569;">Payee: <strong id="step-utr-payee">Intercity Wheels</strong></div>
                </div>

                <div id="step-upi-qr-container" style="display:flex; justify-content:center; align-items:center; padding:8px 0 12px;"></div>

                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; background:#f8fafc; border:1px dashed #94a3b8; border-radius:10px; padding:10px 12px; margin-bottom:10px;">
                    <div style="min-width:0;">
                        <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">UPI ID</div>
                        <code id="step-utr-upi-id" style="font-size:13.5px; font-weight:800; color:#0f172a; word-break:break-all;">intercitywheels@upi</code>
                    </div>
                    <button type="button" class="btn btn-xs btn-outline" onclick="copyCheckoutUpiId()" style="flex-shrink:0; min-height:36px; padding:0 12px;">
                        <span class="material-symbols-outlined" style="font-size:14px;">content_copy</span>
                        <span>Copy</span>
                    </button>
                </div>

                <a id="step-utr-upi-link" href="#" class="btn btn-outline btn-block" style="width:100%; min-height:44px; justify-content:center; text-decoration:none; font-weight:700; margin-bottom:10px;">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#0284c7;">open_in_new</span>
                    <span>Touch &amp; Open UPI App</span>
                </a>

                <div id="step-utr-instructions" style="font-size:11.5px; color:#64748b; text-align:center; line-height:1.45;"></div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="button" class="btn btn-secondary" style="flex:1; min-height:44px;" onclick="openCheckoutPaymentStepModal(2)">Back</button>
                    <button type="button" id="step-qr-paid-btn" class="btn btn-primary" style="flex:2; min-height:44px;" onclick="openCheckoutPaymentStepModal(4)">
                        <span>I Have Paid — Enter UTR</span>
                        <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                    </button>
                </div>
            </div>

            <!-- SCREEN 4: MANUAL UPI — ENTER UTR & SEND FOR VERIFICATION -->
            <div id="step-pay-pane-utr" style="display:none;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px;">
                        <span style="color:#475569;">Amount Paid via UPI</span>
                        <strong id="step-confirm-amount" style="font-size:16px; color:#0f172a;">₹0</strong>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">UPI ID: <code id="step-confirm-upi-id">intercitywheels@upi</code></div>
                    <div id="step-utr-balance-note" style="display:none; font-size:11.5px; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:6px 8px; margin-top:8px;"></div>
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" for="step-modal-upi-utr" style="font-weight:800; font-size:12.5px; color:#0f172a;">
                        Enter UTR / UPI Transaction Reference *
                    </label>
                    <input type="text" id="step-modal-upi-utr" class="form-control" maxlength="40" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" enterkeyhint="done" aria-describedby="step-utr-help" placeholder="e.g. 432198765432" style="font-size:16px; height:48px; font-family:monospace; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; text-align:center;" oninput="this.removeAttribute('aria-invalid')">
                    <div id="step-utr-help" style="font-size:11.5px; color:#64748b; margin-top:6px;">
                        Find the 12-digit UTR / Ref ID in your GPay, PhonePe, Paytm, or BHIM receipt. It will be sent for verification.
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="button" id="step-utr-back-btn" class="btn btn-secondary" style="flex:1; min-height:44px;" onclick="openCheckoutPaymentStepModal(3)">Back</button>
                    <button type="button" id="step-utr-process-btn" class="btn btn-primary" style="flex:2; min-height:44px;" onclick="confirmStepModalUpiAndProcess()">
                        <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                        <span>Send for Verification &amp; Book</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

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

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; font-size:12.5px;">
                <div>
                    <span class="text-micro" style="color:#64748b;">Booking Ref</span>
                    <div style="font-weight:700; font-size:14px; color:#0f172a;" id="ticket-display-id">ICW-90412</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Route</span>
                    <div style="font-weight:700; font-size:14px; color:#0f172a;" id="ticket-display-route">Pune → Goa</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Operator / Chauffeur</span>
                    <div style="font-weight:600; color:#0f172a;" id="ticket-display-operator">Intercity Express #8092</div>
                </div>
                <div>
                    <span class="text-micro" style="color:#64748b;">Assigned Units</span>
                    <div style="font-weight:700; color:var(--primary);" id="ticket-display-berths">L4, L5</div>
                </div>
            </div>

            <div id="ticket-driver-contact-row" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <div>
                        <div style="font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Driver &amp; Vehicle Contact</div>
                        <div id="ticket-display-driver-name" style="font-weight:700; color:#0f172a; margin-top:2px;">Allotment in progress</div>
                        <div id="ticket-display-driver-vehicle" style="font-size:11.5px; color:#475569;">Verified chauffeur details appear once allotted</div>
                        <div id="ticket-display-customer-contact" style="font-size:11px; color:#64748b; margin-top:3px;"></div>
                    </div>
                    <a id="ticket-call-driver-btn" href="#" class="btn btn-xs btn-primary" style="display:none; text-decoration:none;">
                        <span class="material-symbols-outlined" style="font-size:14px;">call</span>
                        <span>Call Driver</span>
                    </a>
                </div>
            </div>

            <div id="ticket-support-bar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 12px; margin-bottom:14px; font-size:11.5px;">
                <span style="font-weight:700; color:#92400e;">Need help at any stage? 24/7 Support</span>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <a id="ticket-call-support-btn" href="tel:7003434088" class="btn btn-xs btn-outline" style="text-decoration:none;">
                        <span class="material-symbols-outlined" style="font-size:13px;">support_agent</span>
                        <span>Call Support</span>
                    </a>
                    <a id="ticket-email-support-btn" href="mailto:support@intercitywheels.com" class="btn btn-xs btn-outline" style="text-decoration:none;">
                        <span class="material-symbols-outlined" style="font-size:13px;">mail</span>
                        <span>Email</span>
                    </a>
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
<!-- MODAL: OPEN DRIVER BIDDING (reverse auction)             -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-bidding-open-modal">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">gavel</span>
                <span>Send Booking to Driver Bidding</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-bidding-open-modal')">×</button>
        </div>
        <div class="modal-body">
            <div id="bidding-open-form">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <span id="bidding-info-ref" style="font-family:monospace; font-weight:700; color:var(--primary); font-size:13px;">—</span>
                        <span id="bidding-info-fare" style="font-weight:800; color:#0f172a;">₹0</span>
                    </div>
                    <div id="bidding-info-route" style="font-weight:600; color:#334155;">—</div>
                    <div id="bidding-info-customer" style="color:#64748b; margin-top:2px;">—</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label" for="bidding-fee-pct">Minimum admin margin (%)</label>
                        <input type="number" id="bidding-fee-pct" class="form-control" value="{{ $ps['bidding_min_service_charge_pct'] ?? 15 }}" min="0" max="90" step="0.5" oninput="recalculateBiddingCeiling()">
                        <small style="color:#64748b; font-size:11px;">Your guaranteed cut of the customer fare.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="bidding-start-amount">Start price (max driver price)</label>
                        <input type="number" id="bidding-start-amount" class="form-control" min="1" step="1">
                        <small style="color:#64748b; font-size:11px;" id="bidding-ceiling-hint">Max allowed: ₹0</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bidding-closes-at">Bidding deadline (optional)</label>
                    <input type="datetime-local" id="bidding-closes-at" class="form-control">
                    <small style="color:#64748b; font-size:11px;">Leave blank to keep the auction open until a driver is allotted.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bidding-notes">Internal note (optional)</label>
                    <input type="text" id="bidding-notes" class="form-control" placeholder="e.g. Airport pickup, toll included">
                </div>

                <div id="bidding-open-error" style="display:none; font-size:12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:6px; padding:8px 10px; margin-bottom:10px;"></div>

                <button type="button" class="btn btn-primary btn-block" id="bidding-open-submit" onclick="submitOpenBidding()">
                    <span class="material-symbols-outlined" style="font-size:18px;">campaign</span>
                    Open bidding &amp; generate WhatsApp link
                </button>
            </div>

            <div id="bidding-share-panel" style="display:none;">
                <div style="font-size:12.5px; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; border-radius:6px; padding:8px 10px; margin-bottom:12px;">
                    Bidding is live. Send this link to your verified drivers.
                </div>
                <label class="form-label" for="bidding-share-url">Driver bidding link</label>
                <div style="display:flex; gap:8px; margin-bottom:12px;">
                    <input type="text" id="bidding-share-url" class="form-control" readonly style="font-family:monospace; font-size:12px;">
                    <button type="button" class="btn btn-outline" onclick="copyBiddingShare()" style="white-space:nowrap;">Copy Link</button>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                    <button type="button" class="btn btn-outline" onclick="copyBiddingInvitationText()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; border-color:#bbf7d0; color:#15803d; background:#f0fdf4;">
                        <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span>
                        <span>Copy WhatsApp Text</span>
                    </button>
                    <a id="bidding-whatsapp-link" class="btn btn-primary" target="_blank" rel="noopener" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:6px; background:#16a34a; border-color:#15803d;">
                        <span class="material-symbols-outlined" style="font-size:18px;">chat</span>
                        <span>Share on WhatsApp</span>
                    </a>
                </div>
                <label class="form-label" style="margin-top:14px;">WhatsApp message preview</label>
                <pre id="bidding-share-text" style="white-space:pre-wrap; background:#0f172a; color:#e2e8f0; border-radius:8px; padding:12px; font-size:11.5px; line-height:1.6;"></pre>
                <button type="button" class="btn btn-outline btn-block" style="margin-top:10px;" onclick="closeModal('admin-bidding-open-modal'); switchAdminSection('bidding'); renderAdminBiddings();">
                    View the auction board
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: BIDDING DETAIL / AWARD WINNER                     -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-bidding-detail-modal">
    <div class="modal-dialog" style="max-width: 720px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px; vertical-align:middle;">format_list_numbered</span>
                <span>Bids &amp; Winner Approval</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-bidding-detail-modal')">×</button>
        </div>
        <div class="modal-body">
            <div id="bidding-detail-summary" style="margin-bottom:12px;"></div>

            <table class="admin-data-table admin-table-compact">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Bid</th>
                        <th>Placed</th>
                        <th>IP</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody id="bidding-detail-bids">
                    <tr><td colspan="6" style="text-align:center; padding:24px; color:#94a3b8;">No bids yet.</td></tr>
                </tbody>
            </table>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <button type="button" class="btn btn-primary" id="bidding-detail-award" onclick="awardWinningBid()">
                    <span class="material-symbols-outlined" style="font-size:18px;">verified</span>
                    Approve lowest bid &amp; assign
                </button>
                <button type="button" class="btn btn-outline" id="bidding-detail-close" onclick="closeBiddingSession()">
                    Close bidding
                </button>
                <button type="button" class="btn btn-outline" style="color:#b91c1c; border-color:#fecaca;" onclick="cancelBiddingSession()">
                    Cancel auction
                </button>
                <button type="button" class="btn btn-outline" onclick="copyBiddingDetailLink()">
                    Copy driver link
                </button>
            </div>
            <div id="bidding-detail-reassign-wrap" style="display:none; margin-top:12px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                <div style="font-size:11.5px; font-weight:700; color:#334155; margin-bottom:6px;">Change Allotted Driver</div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <select id="bidding-detail-reassign-driver" class="form-control" style="flex:1; min-width:200px; height:34px; font-size:12.5px;">
                        @foreach($allDrivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->vehicle_model }} ({{ $d->vehicle_plate }})</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" onclick="reassignAllottedDriverFromBidding()">
                        Update Allotted Driver
                    </button>
                </div>
            </div>
            <div id="bidding-detail-error" style="display:none; margin-top:10px; font-size:12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:6px; padding:8px 10px;"></div>
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
                    <div style="display:flex; gap:6px; margin-top:4px; flex-wrap:wrap;">
                        <span class="admin-badge badge-gold" id="view-booking-service-badge">CAB</span>
                        <span class="admin-badge badge-secondary" id="view-booking-cab-type" style="display:none;">SUV</span>
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
                <!-- Passenger & Who Booked Info Card -->
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between; gap:4px;">
                        <span style="display:flex; align-items:center; gap:4px;">
                            <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">person</span>
                            <span>Who Booked &amp; Passenger</span>
                        </span>
                        <a href="javascript:void(0)" id="view-booking-profile-link" style="font-size:10.5px; font-weight:700; color:var(--primary); text-decoration:underline;">View Profile</a>
                    </div>
                    <div style="font-size:13px; font-weight:700; color:#0f172a;" id="view-booking-customer-name">Rahul Sharma</div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;" id="view-booking-booked-by-meta">Account: Registered User</div>
                    <div style="font-size:12px; color:#475569; margin-top:4px;">
                        📞 <a href="tel:" id="view-booking-customer-phone" style="color:inherit; text-decoration:none;">+91 98220 11920</a>
                    </div>
                    <div style="font-size:12px; color:#475569; margin-top:2px;">
                        ✉️ <span id="view-booking-customer-email">customer@intercitywheels.com</span>
                    </div>
                    <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; font-size:11.5px; color:#64748b;">
                        <div><strong>Pickup:</strong> <span id="view-booking-pickup">Kothrud, Pune</span></div>
                        <div style="margin-top:2px;"><strong>Drop:</strong> <span id="view-booking-drop">Candolim, Goa</span></div>
                        <div style="margin-top:2px;"><strong>Booked At:</strong> <span id="view-booking-created-at">—</span></div>
                        <div style="margin-top:2px;" id="view-booking-extra-meta"></div>
                    </div>
                </div>

                <!-- Chauffeur / Allocation Card -->
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;" id="view-booking-driver-box">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:#16a34a;">local_taxi</span>
                        <span>Assigned Chauffeur &amp; Allocation</span>
                    </div>
                    <div id="view-booking-driver-content">
                        <!-- dynamic chauffeur or unassigned state -->
                    </div>
                    <div id="view-booking-allocation-meta" style="margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; font-size:11px; color:#475569;"></div>
                </div>
            </div>

            <!-- Financial Breakdown Card -->
            <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px; background:#fafafa;">
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:10px; display:flex; justify-content:space-between;">
                    <span style="display:flex; align-items:center; gap:4px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:var(--primary);">payments</span>
                        <span>Payment Breakdown & Financial Audit</span>
                    </span>
                    <span id="view-booking-payment-badge" class="admin-badge badge-success" style="font-size:10px;">ESCROW VERIFIED</span>
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
                        <span>Total Fare:</span>
                        <span style="color:var(--primary);" id="view-booking-total-fare">₹0</span>
                    </div>
                </div>

                <div style="margin-top:10px; padding:8px 10px; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; font-size:11px; color:#64748b; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                        <span><strong>Payment Mode:</strong> <span id="view-booking-payment-mode">Online Escrow (Prepaid)</span></span>
                        <span><strong>UTR / Ref:</strong> <span id="view-booking-txnid" style="font-family:monospace; font-weight:700; color:#0f172a;">TXN-1029384</span></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                        <span><strong>Collection By:</strong> <span id="view-booking-collection-by" style="font-weight:700; color:#0f172a;">Platform</span></span>
                        <span id="view-booking-payment-note" style="color:#475569;"></span>
                    </div>
                </div>

                <!-- Admin UPI / Payment Confirmation Controls -->
                <div id="view-booking-admin-verify-box" style="display:none; margin-top:10px; padding:10px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px;">
                    <div style="font-size:11.5px; font-weight:800; color:#92400e; margin-bottom:6px;">Admin UPI UTR Verification &amp; Collection Control</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                        <input type="text" id="view-verify-utr-input" class="form-control" placeholder="Customer UPI UTR / Ref No." style="font-size:12px; height:32px; font-family:monospace; text-transform:uppercase;">
                        <input type="text" id="view-verify-notes-input" class="form-control" placeholder="Admin verification note (optional)" style="font-size:12px; height:32px;">
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-xs btn-primary" onclick="verifyAdminBookingPayment('confirm_utr')">
                            <span class="material-symbols-outlined" style="font-size:14px;">verified</span>
                            <span>Confirm UTR &amp; Mark Paid</span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="verifyAdminBookingPayment('reject_assign_driver')">
                            <span class="material-symbols-outlined" style="font-size:14px;">local_taxi</span>
                            <span>Assign Collection to Driver</span>
                        </button>
                    </div>
                </div>

                <!-- Payment Attempts Audit Log -->
                <div id="view-booking-attempts-box" style="display:none; margin-top:10px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                    <div style="font-size:11px; font-weight:800; color:#334155; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; display:flex; align-items:center; gap:5px;">
                        <span class="material-symbols-outlined" style="font-size:14px; color:#0284c7;">history</span>
                        <span>Payment Attempts History</span>
                    </div>
                    <div id="view-booking-attempts-list" style="display:flex; flex-direction:column; gap:6px; font-size:11.5px;"></div>
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
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-service-type">Service Type</label>
                        <select id="edit-service-type" class="form-control">
                            <option value="cab">Outstation Cab</option>
                            <option value="bus">Volvo Sleeper Berth</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-cab-type">Cab Type</label>
                        <select id="edit-cab-type" class="form-control">
                            <option value="">-- Auto / Unspecified --</option>
                            @forelse(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                <option value="{{ strtolower($ct->category_key) }}">{{ $ct->name }}</option>
                            @empty
                                <option value="hatchback">Hatchback</option>
                                <option value="sedan">Sedan</option>
                                <option value="prime">Prime Sedan</option>
                                <option value="suv">SUV</option>
                                <option value="innova">Innova Crysta</option>
                                <option value="tempo">Tempo Traveller</option>
                                <option value="luxury">Luxury</option>
                            @endforelse
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
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="add-service-type">Service Type</label>
                        <select id="add-service-type" class="form-control">
                            <option value="cab" selected>Outstation Cab</option>
                            <option value="bus">Luxury Sleeper Berth</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-cab-type">Cab Type</label>
                        <select id="add-cab-type" class="form-control">
                            <option value="">-- Auto / Unspecified --</option>
                            @forelse(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                                <option value="{{ strtolower($ct->category_key) }}">{{ $ct->name }}</option>
                            @empty
                                <option value="hatchback">Hatchback</option>
                                <option value="sedan">Sedan</option>
                                <option value="prime">Prime Sedan</option>
                                <option value="suv">SUV</option>
                                <option value="innova">Innova Crysta</option>
                                <option value="tempo">Tempo Traveller</option>
                                <option value="luxury">Luxury</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-trip-type">Trip Direction</label>
                        <select id="add-trip-type" class="form-control" onchange="autoSuggestAddFares()">
                            <option value="oneway" selected>One-Way Outstation</option>
                            <option value="roundtrip">Round-Trip Outstation</option>
                            <option value="local">Local Hourly Package</option>
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
                @if($userRole === 'admin')
                <button type="button" class="btn btn-sm btn-outline" onclick="closeModal('admin-invoice-modal'); openAdminInvoiceTemplateEditor();" title="Customize Invoice Template">
                    <span class="material-symbols-outlined" style="font-size:16px;">tune</span>
                    <span>Edit Template</span>
                </button>
                @endif
                <button type="button" class="btn btn-sm btn-primary" onclick="printAdminInvoice()">
                    <span class="material-symbols-outlined" style="font-size:16px;">print</span>
                    <span>Print / Save PDF</span>
                </button>
                <button type="button" class="modal-close-btn" onclick="closeModal('admin-invoice-modal')">×</button>
            </div>
        </div>
        <div class="modal-body" style="max-height: calc(88vh - 120px); overflow-y:auto; padding:20px; background:#f1f5f9;">
            <!-- Official Invoice Canvas (Printable Area) -->
            <div class="invoice-paper-card" id="invoice-printable-area" data-invoice-style="{{ $invStyle ?? 'classic_gst' }}" style="--inv-accent: {{ $invAccent ?? '#d49b4c' }};">
                <!-- Header: Company & Tax Identity -->
                <div class="invoice-header-row">
                    <div class="invoice-brand">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <span style="font-size:22px;">🚗</span>
                            <span class="invoice-company-name" id="inv-brand-name">INTERCITY WHEELS</span>
                        </div>
                        <div class="invoice-company-legal" id="inv-company-legal">IntercityWheels Mobility Services Pvt. Ltd.</div>
                        <div class="invoice-company-sub" id="inv-company-address">Regd. Office: Floor 4, Tech Vista Towers, Senapati Bapat Road, Pune - 411016</div>
                        <div class="invoice-company-sub"><strong>GSTIN:</strong> <span id="inv-gstin">27AAACI9912K1Z8</span> &nbsp;|&nbsp; <strong>SAC Code:</strong> <span id="inv-sac-code">996412 (Passenger Transport)</span></div>
                        <div class="invoice-company-sub">Helpline: <span id="inv-support-phone">700 3434 088</span> &nbsp;|&nbsp; <span id="inv-support-email">billing@intercitywheels.com</span></div>
                    </div>
                    <div class="invoice-meta-box">
                        <div class="invoice-badge-title" id="inv-badge-title">TAX INVOICE</div>
                        <div class="invoice-meta-item"><strong>Invoice No:</strong> <span id="inv-doc-number">INV-2026-ICW-00000</span></div>
                        <div class="invoice-meta-item"><strong>Invoice Date:</strong> <span id="inv-doc-date">08 Nov 2026</span></div>
                        <div class="invoice-meta-item"><strong>Booking Ref:</strong> <span id="inv-booking-ref" style="font-family:monospace; font-weight:700;">ICW-00000</span></div>
                        <div class="invoice-meta-item"><strong>Place of Supply:</strong> <span id="inv-place-of-supply">Maharashtra (27)</span></div>
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
                        <div class="invoice-paid-stamp" id="inv-paid-stamp-box">
                            <span class="material-symbols-outlined" style="font-size:18px;">verified</span>
                            <span id="inv-paid-stamp-text">PAID IN FULL</span>
                        </div>
                        <div style="font-size:10.5px; color:#64748b; margin-top:6px;">
                            <strong>Payment Mode:</strong> <span id="inv-payment-mode-text">Online Escrow (Prepaid)</span><br>
                            <strong>Ref:</strong> <span id="inv-txn-ref">TXN-0000000</span><br>
                            <span id="inv-otp-line"><strong>Start OTP:</strong> <span id="inv-start-otp" class="admin-otp-pill">0000</span></span>
                        </div>
                    </div>

                    <div class="invoice-calc-col">
                        <div class="inv-calc-line">
                            <span>Subtotal (Taxable Value):</span>
                            <span id="inv-subtotal">₹0.00</span>
                        </div>
                        <div class="inv-calc-line">
                            <span id="inv-cgst-label">CGST (2.5%):</span>
                            <span id="inv-cgst">₹0.00</span>
                        </div>
                        <div class="inv-calc-line">
                            <span id="inv-sgst-label">SGST (2.5%):</span>
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
                        <div id="inv-terms-list">
                            <div>1. This is a computer-generated tax invoice and complies with Section 31 of CGST Act 2017.</div>
                            <div>2. All tolls and state border permits are included per the scheduled booking agreement.</div>
                            <div>3. Disputes are subject to Pune, Maharashtra jurisdiction.</div>
                        </div>
                    </div>
                    <div class="invoice-signatory">
                        <div class="signatory-stamp">
                            <span class="material-symbols-outlined" style="color:var(--inv-accent); font-size:28px;">verified_user</span>
                        </div>
                        <div id="inv-signatory-name" style="font-weight:700; font-size:11.5px; color:#0f172a;">IntercityWheels Mobility Services</div>
                        <div id="inv-signatory-label" style="font-size:10px; color:#64748b;">Authorized Signatory</div>
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
                    <label class="form-label" for="edit-user-role">Account Role &amp; Access Level *</label>
                    <select id="edit-user-role" class="form-control">
                        <option value="customer">Customer (Traveler / Passenger)</option>
                        <option value="driver">Driver Partner (Commercial Chauffeur)</option>
                        <option value="admin">Platform Administrator (Operations Super Admin)</option>
                    </select>
                    <small style="display:block; margin-top:4px; font-size:11px; color:#64748b;">Granting <strong>Driver</strong> provisions a verified chauffeur profile automatically; <strong>Admin</strong> grants full platform access.</small>
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
                <div style="font-size:36px; width:52px; height:52px; border-radius:50%; background:#ffffff; border:1px solid #cbd5e1; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0;" id="view-user-avatar">
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
                <div class="driver-modal-grid-2" style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-model">Vehicle Make & Model *</label>
                        <input type="text" id="driver-new-veh-model" class="form-control" placeholder="e.g. Toyota Innova Crysta, Maruti Ertiga" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-new-veh-plate">License Plate Number *</label>
                        <input type="text" id="driver-new-veh-plate" class="form-control" placeholder="MH 12 AB 1234" style="text-transform:uppercase;" required>
                    </div>
                </div>

                <div class="driver-modal-grid-3" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
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

                <div class="driver-modal-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
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

                <div class="driver-modal-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-name">Full Name *</label>
                        <input type="text" id="driver-edit-name" class="form-control" value="{{ $driverProfile->name ?? 'Ramesh Patil' }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-phone">Phone Number *</label>
                        <input type="text" id="driver-edit-phone" class="form-control" value="{{ $driverProfile->phone ?? '+91 98765 43210' }}" required>
                    </div>
                </div>

                <div class="driver-modal-grid-2" style="display:grid; grid-template-columns: 1.4fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-model">Primary Vehicle Model *</label>
                        <input type="text" id="driver-edit-model" class="form-control" value="{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="driver-edit-plate">License Plate Number *</label>
                        <input type="text" id="driver-edit-plate" class="form-control" value="{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}" style="text-transform:uppercase;" required>
                    </div>
                </div>

                <div class="driver-modal-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px;">
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

<!-- ======================================================== -->
<!-- MODAL: ADMIN PROCESS DRIVER PAYOUT REQUEST               -->
<!-- ======================================================== -->
<div class="modal-overlay" id="admin-process-payout-modal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;">account_balance_wallet</span>
                <span>Process Driver Payout Request</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('admin-process-payout-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:18px 20px;">
            <input type="hidden" id="admin-payout-req-id" value="">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:14px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:#64748b;">Request Ref</span>
                    <strong id="admin-payout-req-ref" style="font-family:monospace;">—</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:#64748b;">Driver</span>
                    <strong id="admin-payout-req-driver">—</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:#64748b;">Amount</span>
                    <strong id="admin-payout-req-amount" style="color:#15803d; font-size:15px;">₹0</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Destination</span>
                    <strong id="admin-payout-req-dest" style="text-align:right;">—</strong>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label" for="admin-payout-txn-ref">Transfer Reference / UTR / IMPS Ref (Required for Manual Mode)</label>
                <input type="text" id="admin-payout-txn-ref" class="form-control" placeholder="e.g. IMPS4321987654 or UPIUTR12345" style="font-family:monospace; text-transform:uppercase;">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" for="admin-payout-notes">Admin Notes / Rejection Reason</label>
                <input type="text" id="admin-payout-notes" class="form-control" placeholder="Optional note or rejection reason">
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <button type="button" class="btn btn-primary" style="min-height:42px; justify-content:center;" onclick="submitAdminProcessPayout('approve_manual')">
                    <span class="material-symbols-outlined" style="font-size:17px;">check_circle</span>
                    <span>Approve &amp; Record Manual Payout</span>
                </button>
                <button type="button" class="btn btn-outline" style="min-height:42px; justify-content:center; border-color:#0284c7; color:#0284c7;" onclick="submitAdminProcessPayout('approve_gateway')">
                    <span class="material-symbols-outlined" style="font-size:17px;">bolt</span>
                    <span>Approve &amp; Pay via Payment Gateway API</span>
                </button>
                <button type="button" class="btn btn-outline-danger" style="min-height:40px; justify-content:center;" onclick="submitAdminProcessPayout('reject')">
                    <span class="material-symbols-outlined" style="font-size:17px;">cancel</span>
                    <span>Reject &amp; Refund to Driver Wallet</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DRIVER ITEM DETAILS SCREEN                        -->
<!-- ======================================================== -->
<div class="modal-overlay" id="driver-item-details-modal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="color:var(--primary); font-size:20px;" id="driver-details-icon">info</span>
                <span id="driver-details-title">Details</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('driver-item-details-modal')">×</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;" id="driver-details-body">
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
                <input type="hidden" id="admin-cab-route-from-lat" value="">
                <input type="hidden" id="admin-cab-route-from-lng" value="">
                <input type="hidden" id="admin-cab-route-to-lat" value="">
                <input type="hidden" id="admin-cab-route-to-lng" value="">
                <input type="hidden" id="admin-cab-route-calc-mode" value="manual">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-from">From City (Origin) *</label>
                        <input type="text" id="admin-cab-route-from" class="form-control" placeholder="e.g. Pune, Kolkata" required list="route-cities-list" oninput="onCabRouteEndpointInput()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-to">To City (Destination) *</label>
                        <input type="text" id="admin-cab-route-to" class="form-control" placeholder="e.g. Goa, Murshidabad" required list="route-cities-list" oninput="onCabRouteEndpointInput()">
                    </div>
                </div>

                <datalist id="route-cities-list">
                    @foreach($serviceLocations as $loc)
                        <option value="{{ $loc->name }}">
                    @endforeach
                </datalist>

                <div style="background:#f8fafc; border:1px solid var(--outline); border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span style="font-size:11.5px; font-weight:700; color:#334155;">KM Calculation:</span>
                            <button type="button" id="admin-cab-route-mode-api-btn" class="btn btn-primary btn-sm" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;" onclick="calculateAdminCabRouteDistance()">
                                <span class="material-symbols-outlined" style="font-size:14px;">route</span>
                                <span>Calculate via API</span>
                            </button>
                            <button type="button" id="admin-cab-route-mode-manual-btn" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;" onclick="setCabRouteCalcMode('manual')">
                                <span class="material-symbols-outlined" style="font-size:14px;">edit_road</span>
                                <span>Manual Entry</span>
                            </button>
                        </div>
                        <span id="admin-cab-route-calc-status" style="font-size:11px; color:#64748b; font-weight:600;">Mode: Manual Entry</span>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-dist">Highway Distance (KM) *</label>
                        <input type="number" id="admin-cab-route-dist" class="form-control" placeholder="e.g. 450" min="1" required oninput="onCabRouteManualMetricEdit()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-time">Est. Travel Time *</label>
                        <input type="text" id="admin-cab-route-time" class="form-control" placeholder="e.g. 9h 15m, 4h 30m" required oninput="onCabRouteManualMetricEdit()">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group">
                        <label class="form-label" for="admin-cab-route-toll">Highway Base Toll (₹)</label>
                        <input type="number" id="admin-cab-route-toll" class="form-control" placeholder="e.g. 480" min="0" value="0" oninput="onCabRouteManualMetricEdit()">
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

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-cab-route-default-car-type">Preferred / Specific Car Type (Optional)</label>
                    <select id="admin-cab-route-default-car-type" class="form-control booking-type-car-select">
                        <option value="">-- Any / All Car Types --</option>
                        @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                            <option value="{{ strtolower($ct->category_key) }}">{{ $ct->name }}</option>
                        @endforeach
                    </select>
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

                <div style="display:grid; grid-template-columns: 2.2fr 0.8fr; gap:12px; margin-bottom:12px; align-items:start;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="admin-car-type-image">Vehicle Photo (File Upload ≤ 30 KB or URL)</label>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap;">
                            <img id="admin-car-type-preview-img" src="https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80" alt="Car Preview" style="width:76px; height:48px; object-fit:cover; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; flex-shrink:0;">
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <label class="btn btn-xs btn-outline" style="cursor:pointer; margin:0;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">upload</span>
                                        <span>Choose Image &amp; Crop</span>
                                        <input type="file" id="admin-car-type-file-input" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="onCarTypeFileSelected(this)">
                                    </label>
                                    <span id="admin-car-type-img-meta" style="font-size:11px; color:#64748b; font-weight:600;">Max 30 KB after crop</span>
                                </div>
                            </div>
                        </div>
                        <input type="text" id="admin-car-type-image" class="form-control" placeholder="https://... or /uploads/car-types/..." value="https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80" oninput="syncCarTypeImagePreview()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="admin-car-type-order">Order Index</label>
                        <input type="number" id="admin-car-type-order" class="form-control" value="0" min="0">
                    </div>
                </div>

                <!-- Interactive Image Cropper & <=30 KB Compressor -->
                <div id="car-type-cropper-box" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <div style="font-size:12px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">crop</span>
                            <span>Crop &amp; Reduce Vehicle Photo (&le; 30 KB)</span>
                        </div>
                        <span id="car-type-crop-size-badge" class="admin-badge badge-success" style="font-size:10.5px;">Estimating…</span>
                    </div>

                    <div style="display:flex; justify-content:center; background:#0f172a; border-radius:8px; padding:8px; overflow:hidden; margin-bottom:10px;">
                        <canvas id="car-type-crop-canvas" width="360" height="225" style="max-width:100%; border-radius:6px; cursor:grab; touch-action:none; background:#1e293b;"></canvas>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:11.5px; font-weight:600; color:#334155; flex:1; min-width:160px;">
                            <span>Zoom</span>
                            <input type="range" id="car-type-crop-zoom" min="1" max="3" step="0.05" value="1" oninput="updateCarTypeCropZoom(this.value)" style="flex:1;">
                        </label>
                        <select id="car-type-crop-aspect" class="form-control" onchange="setCarTypeCropAspect(this.value)" style="width:auto; height:30px; font-size:11.5px; padding:2px 8px;">
                            <option value="16:10" selected>16:10 Fleet Card</option>
                            <option value="4:3">4:3 Standard</option>
                            <option value="1:1">1:1 Square</option>
                        </select>
                        <button type="button" class="btn btn-xs btn-outline" onclick="rotateCarTypeCrop()">
                            <span class="material-symbols-outlined" style="font-size:14px;">rotate_right</span>
                            <span>Rotate</span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="resetCarTypeCropTransform()">Fit</button>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="btn btn-xs btn-secondary" onclick="cancelCarTypeCropper()">Cancel Crop</button>
                        <button type="button" class="btn btn-xs btn-primary" id="car-type-crop-upload-btn" onclick="uploadCroppedCarTypeImage()">
                            <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span>
                            <span>Crop &amp; Upload (&le; 30 KB)</span>
                        </button>
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

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-local-pkg-default-car-type">Preferred / Specific Car Type (Optional)</label>
                    <select id="admin-local-pkg-default-car-type" class="form-control booking-type-car-select">
                        <option value="">-- Any / All Car Types --</option>
                        @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                            <option value="{{ strtolower($ct->category_key) }}">{{ $ct->name }}</option>
                        @endforeach
                    </select>
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

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label" for="admin-airport-default-car-type">Preferred / Specific Car Type (Optional)</label>
                    <select id="admin-airport-default-car-type" class="form-control booking-type-car-select">
                        <option value="">-- Any / All Car Types --</option>
                        @foreach(($allCarTypes ?? collect())->where('is_active', true) as $ct)
                            <option value="{{ strtolower($ct->category_key) }}">{{ $ct->name }}</option>
                        @endforeach
                    </select>
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
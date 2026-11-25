<!-- ======================================================== -->
<!-- UNIFORM MASTER DRIVER CONSOLE HEADER & SUBNAV           -->
<!-- ======================================================== -->
<div class="driver-master-banner animate-load stagger-1">
    <div class="driver-banner-left">
        <div class="driver-banner-avatar driver-avatar-display" onclick="openDriverEditProfileModal()" style="cursor:pointer;" title="Click to Edit Profile">
            @include('partials.avatar', ['avatar' => $driverProfile->avatar ?? null, 'fallback' => '👨🏽‍✈️', 'alt' => $driverProfile->name ?? 'Driver'])
        </div>
        <div class="driver-banner-info">
            <div class="driver-banner-name-row">
                <span class="driver-banner-name driver-name-display">{{ $driverProfile->name ?? 'Ramesh Patil' }}</span>
                <span class="admin-badge badge-success driver-verified-pill">VERIFIED</span>
            </div>
            <div class="driver-banner-status-inline">
                <span class="driver-inline-rating">★ {{ $driverProfile->rating ?? '4.90' }} <span class="driver-inline-trips">({{ $driverProfile->trips_count ?? 840 }})</span></span>
                <button type="button" class="driver-duty-toggle-pill {{ ($driverProfile->is_online ?? true) ? 'online' : 'offline' }} driver-duty-pill-toggle" onclick="toggleDriverDutyStatus()" title="Click to toggle Online/Offline duty">
                    <span class="status-indicator-dot {{ ($driverProfile->is_online ?? true) ? 'bg-success' : 'bg-secondary' }} driver-duty-dot"></span>
                    <span class="driver-duty-text">{{ ($driverProfile->is_online ?? true) ? 'Duty Online' : 'Off Duty' }}</span>
                </button>
            </div>
            <div class="driver-banner-meta">
                <span class="driver-vehicle-display">{{ $driverProfile->vehicle_model ?? 'Toyota Innova Crysta' }}</span>
                <span class="driver-meta-sep">&bull;</span>
                <span class="driver-plate-code driver-plate-display">{{ $driverProfile->vehicle_plate ?? 'MH 12 QX 4920' }}</span>
            </div>
        </div>
    </div>

    <div class="driver-banner-right">
        <div class="driver-banner-metric">
            <div class="driver-banner-metric-label">Wallet</div>
            <div class="driver-banner-metric-val driver-header-wallet-bal">₹{{ number_format($driverProfile->wallet_balance ?? 14500) }}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-gold driver-banner-action-btn" onclick="openDriverEditProfileModal()" aria-label="Edit Profile">
            <span class="material-symbols-outlined" style="font-size:15px;">badge</span>
            <span>Edit</span>
        </button>
        <button type="button" class="btn btn-sm btn-primary driver-banner-action-btn" onclick="openDriverListVehicleModal()" aria-label="Register Car">
            <span class="material-symbols-outlined" style="font-size:15px;">add_circle</span>
            <span>Add Car</span>
        </button>
    </div>
</div>

<!-- Standardized Uniform Sub-Navigation Pills -->
<div class="driver-subnav-bar">
    <button type="button" class="driver-subnav-btn {{ ($activeDriverTab ?? '') === 'bidding' ? 'active' : '' }}" onclick="switchTab('bidding')">
        <span class="material-symbols-outlined" style="font-size:17px;">local_offer</span>
        <span>Live Bids & Auctions</span>
    </button>
    <button type="button" class="driver-subnav-btn {{ ($activeDriverTab ?? '') === 'driver-trips' ? 'active' : '' }}" onclick="switchTab('driver-trips')">
        <span class="material-symbols-outlined" style="font-size:17px;">local_taxi</span>
        <span>My Assigned Trips</span>
        <span class="nav-tab-badge badge-success">{{ count($driverTrips ?? []) }}</span>
    </button>
    <button type="button" class="driver-subnav-btn {{ ($activeDriverTab ?? '') === 'driver-wallet' ? 'active' : '' }}" onclick="switchTab('driver-wallet')">
        <span class="material-symbols-outlined" style="font-size:17px;">account_balance_wallet</span>
        <span>Wallet & Payouts</span>
    </button>
    <button type="button" class="driver-subnav-btn {{ ($activeDriverTab ?? '') === 'driver-profile' ? 'active' : '' }}" onclick="switchTab('driver-profile')">
        <span class="material-symbols-outlined" style="font-size:17px;">person</span>
        <span>Chauffeur Profile & Fleet</span>
        <span class="nav-tab-badge badge-primary">{{ count($driverMyVehicles ?? []) }}</span>
    </button>
</div>


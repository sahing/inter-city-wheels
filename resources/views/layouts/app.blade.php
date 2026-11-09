<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Intercity Wheels') }} | Connecting Cities, Anytime</title>
    <meta name="description" content="Laravel-powered premium intercity cab bookings, luxury sleeper bus berths, and real-time driver bidding marketplace across India.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Symbols Outlined -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Leaflet CSS for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ time() }}">
</head>
@php
    $userRole = Auth::check() ? Auth::user()->role : null;
    $defaultTab = match($userRole) {
        'admin' => 'admin',
        'driver' => 'bidding',
        default => 'cabs'
    };
@endphp
<body class="{{ $defaultTab === 'admin' ? 'role-admin-mode admin-active-view' : '' }}">

    <!-- Top Announcement Bar -->
    <div class="top-notice-bar">
        <div class="notice-container">
            <div class="notice-left">
                <span class="notice-tag">Intercity Wheels Pvt. Ltd.</span>
                <span>⚡ Verified Outstation Cabs, Luxury Sleeper Berths available</span>
            </div>
            <div class="notice-right">
                <span style="display:flex; align-items:center; gap:4px; font-weight:600; color:#f5d061;">
                    <span class="material-symbols-outlined" style="font-size:15px;">support_agent</span> 24/7 Helpline: 1800-209-WHEELS
                </span>
                <span>•</span>
                <button type="button" onclick="showToast('Emergency SOS dispatch ready for active rides.', 'warning')">
                    <span class="material-symbols-outlined" style="font-size:15px; color:#ef4444;">shield</span> SOS Safety
                </button>
            </div>
        </div>
    </div>

    <!-- Main Luxury Brand Header -->
    <header class="main-header">
        <div class="header-container">
            <!-- Brand Logo & Tagline -->
            <a class="brand-link" onclick="switchTab('cabs')" style="cursor:pointer;">
                <img src="{{ asset('assets/logo.jpg') }}" alt="Intercity Wheels Logo" class="brand-logo-img">
                <div class="brand-fallback-title">
                    <span class="brand-name">INTERCITY WHEELS</span>
                    <span class="brand-tagline">Connecting Cities, Anytime</span>
                </div>
            </a>

            <!-- Desktop Navigation Tabs (Strictly Role Filtered) -->
            <nav class="main-nav" aria-label="Main Navigation">
                @if(!$userRole || $userRole === 'customer')
                    {{-- CUSTOMER / GUEST VIEW --}}
                    <button class="nav-tab-btn {{ $defaultTab === 'cabs' ? 'active' : '' }}" data-target="cabs">
                        <span class="material-symbols-outlined">directions_car</span>
                        <span>Outstation Cabs</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'schedules' ? 'active' : '' }}" data-target="schedules">
                        <span class="material-symbols-outlined">directions_bus</span>
                        <span>Schedules & Berths</span>
                    </button>
                    @auth
                        <button class="nav-tab-btn {{ $defaultTab === 'my-bookings' ? 'active' : '' }}" data-target="my-bookings">
                            <span class="material-symbols-outlined">confirmation_number</span>
                            <span>My Trips</span>
                            <span class="nav-tab-badge badge-primary">{{ count($customerBookings ?? []) }}</span>
                        </button>
                    @endauth
                @elseif($userRole === 'driver')
                    {{-- DRIVER / CHAUFFEUR PARTNER VIEW --}}
                    <button class="nav-tab-btn {{ $defaultTab === 'bidding' ? 'active' : '' }}" data-target="bidding">
                        <span class="material-symbols-outlined">local_offer</span>
                        <span>Live Bids & Auctions</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'driver-trips' ? 'active' : '' }}" data-target="driver-trips">
                        <span class="material-symbols-outlined">local_taxi</span>
                        <span>My Assigned Trips</span>
                        <span class="nav-tab-badge badge-success">{{ count($driverTrips ?? []) }}</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'driver-wallet' ? 'active' : '' }}" data-target="driver-wallet">
                        <span class="material-symbols-outlined">account_balance_wallet</span>
                        <span>Driver Wallet</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'driver-profile' ? 'active' : '' }}" data-target="driver-profile">
                        <span class="material-symbols-outlined">badge</span>
                        <span>Driver Profile & Fleet</span>
                    </button>
                @elseif($userRole === 'admin')
                    {{-- ADMIN VIEW --}}
                    <button class="nav-tab-btn {{ $defaultTab === 'admin' ? 'active' : '' }}" data-target="admin">
                        <span class="material-symbols-outlined">admin_panel_settings</span>
                        <span>Admin Panel</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'cabs' ? 'active' : '' }}" data-target="cabs" title="Customer View: Cabs">
                        <span class="material-symbols-outlined">directions_car</span>
                        <span>Cabs (Preview)</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'schedules' ? 'active' : '' }}" data-target="schedules" title="Customer View: Schedules">
                        <span class="material-symbols-outlined">directions_bus</span>
                        <span>Berths (Preview)</span>
                    </button>
                    <button class="nav-tab-btn {{ $defaultTab === 'bidding' ? 'active' : '' }}" data-target="bidding" title="Driver View: Auctions">
                        <span class="material-symbols-outlined">local_offer</span>
                        <span>Driver Console (Preview)</span>
                    </button>
                @endif
            </nav>

            <!-- Header Right Actions: Role-Based Auth & Admin -->
            <div class="header-actions">
                @auth
                    <div class="user-profile-menu" id="user-profile-menu">
                        <button type="button" class="user-profile-btn" id="user-profile-btn" onclick="toggleUserMenu()">
                            <span class="user-avatar-badge">{{ Auth::user()->avatar ?? '👤' }}</span>
                            <div class="user-meta-info">
                                <span class="user-meta-name">{{ Auth::user()->name }}</span>
                                <span class="user-meta-role role-{{ Auth::user()->role }}">{{ strtoupper(Auth::user()->role) }}</span>
                            </div>
                            <span class="material-symbols-outlined" style="font-size:16px; color:#94a3b8;">expand_more</span>
                        </button>
                        <div class="user-dropdown-shelf" id="user-dropdown-shelf" style="display:none;">
                            <div class="dropdown-user-header">
                                <div class="dropdown-user-name">{{ Auth::user()->name }}</div>
                                <div class="dropdown-user-email">{{ Auth::user()->email }}</div>
                                <div class="dropdown-role-indicator role-{{ Auth::user()->role }}">
                                    Logged In Role: <strong>{{ ucfirst(Auth::user()->role) }}</strong>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-section-title">Quick Role Perspective Switch</div>
                            <button type="button" class="dropdown-item-btn {{ Auth::user()->role === 'customer' ? 'active-role' : '' }}" onclick="quickDemoLogin('customer')">
                                <span class="dropdown-item-icon">👤</span>
                                <div class="dropdown-item-text">
                                    <span class="dropdown-item-title">Customer</span>
                                    <span class="dropdown-item-sub">Book Cabs & Berths</span>
                                </div>
                                @if(Auth::user()->role === 'customer')
                                    <span class="material-symbols-outlined dropdown-check">check</span>
                                @endif
                            </button>
                            <button type="button" class="dropdown-item-btn {{ Auth::user()->role === 'driver' ? 'active-role' : '' }}" onclick="quickDemoLogin('driver')">
                                <span class="dropdown-item-icon">👨🏽‍✈️</span>
                                <div class="dropdown-item-text">
                                    <span class="dropdown-item-title">Chauffeur Partner</span>
                                    <span class="dropdown-item-sub">Live Bids, Trips & Wallet</span>
                                </div>
                                @if(Auth::user()->role === 'driver')
                                    <span class="material-symbols-outlined dropdown-check">check</span>
                                @endif
                            </button>
                            <button type="button" class="dropdown-item-btn {{ Auth::user()->role === 'admin' ? 'active-role' : '' }}" onclick="quickDemoLogin('admin')">
                                <span class="dropdown-item-icon">🛡️</span>
                                <div class="dropdown-item-text">
                                    <span class="dropdown-item-title">Platform Admin</span>
                                    <span class="dropdown-item-sub">Full Management & Dispatch</span>
                                </div>
                                @if(Auth::user()->role === 'admin')
                                    <span class="material-symbols-outlined dropdown-check">check</span>
                                @endif
                            </button>
                            <div class="dropdown-divider"></div>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                                @csrf
                            </form>
                            <button type="button" class="dropdown-item-btn dropdown-logout-btn" onclick="performLogout()">
                                <span class="material-symbols-outlined" style="font-size:17px; color:#ef4444;">logout</span>
                                <span>Sign Out</span>
                            </button>
                        </div>
                    </div>

                    {{-- Admin Ops: Visible ONLY when authenticated as Admin --}}
                    @if($userRole === 'admin')
                        <button class="btn-outline-gold admin-highlight-active" onclick="handleAdminOpsClick()" title="Operations & Config Console">
                            <span class="material-symbols-outlined" style="font-size:17px;">admin_panel_settings</span>
                            <span>Admin Ops</span>
                        </button>
                    @endif
                @else
                    <!-- Minimal MVP Distraction-Free Sign In for Customers -->
                    <button type="button" class="btn-auth-trigger" onclick="openLoginModal('customer')">
                        <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                        <span>Sign In</span>
                    </button>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Yield -->
    @yield('content')

    <!-- ======================================================== -->
    <!-- MODAL: ROLE-BASED LOGIN (CUSTOMER / DRIVER / ADMIN)      -->
    <!-- ======================================================== -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-dialog auth-modal-dialog">
            <div class="modal-header auth-modal-header">
                <div class="auth-header-brand">
                    <img src="{{ asset('assets/logo.jpg') }}" alt="Intercity Wheels" class="auth-logo-img">
                    <div>
                        <h3 class="modal-title" style="color:#ffffff;">Portal Sign In</h3>
                        <p class="auth-header-subtitle">Select role & authenticate securely</p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" style="color:#cbd5e1;" onclick="closeModal('login-modal')">×</button>
            </div>

            <div class="modal-body auth-modal-body">
                <!-- Role Selector Tabs -->
                <div class="role-selector-pills">
                    <button type="button" class="role-pill active" data-role="customer" onclick="selectLoginRole('customer')">
                        <span class="role-icon">👤</span>
                        <span class="role-label">Customer</span>
                    </button>
                    <button type="button" class="role-pill" data-role="driver" onclick="selectLoginRole('driver')">
                        <span class="role-icon">👨🏽‍✈️</span>
                        <span class="role-label">Driver</span>
                    </button>
                    <button type="button" class="role-pill" data-role="admin" onclick="selectLoginRole('admin')">
                        <span class="role-icon">🛡️</span>
                        <span class="role-label">Admin</span>
                    </button>
                </div>

                <!-- Scope Explanation Notice -->
                <div class="role-scope-notice" id="role-scope-notice">
                    <span class="material-symbols-outlined" id="role-scope-icon" style="color:#38bdf8;">directions_car</span>
                    <span id="role-scope-text">Customer Portal: Book sanitised outstation cabs and luxury sleeper bus berths.</span>
                </div>

                <!-- Role Login Form -->
                <form id="role-login-form" action="{{ route('login') }}" method="POST" onsubmit="handleFormLogin(event)">
                    @csrf
                    <input type="hidden" name="role" id="login-role-input" value="customer">

                    <div class="form-group">
                        <label class="form-label" for="login-email">Account Email</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined">mail</span>
                            <input type="email" name="email" id="login-email" class="form-control" placeholder="user@intercitywheels.com" required value="customer@intercitywheels.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined">lock</span>
                            <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required value="password123">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; font-size:12px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; color:#475569;">
                            <input type="checkbox" name="remember" id="login-remember" value="1" checked>
                            <span>Remember session</span>
                        </label>
                        <span style="color:#0284c7; font-weight:600; cursor:pointer;" onclick="showToast('Password recovery token sent to your email.', 'info')">Forgot?</span>
                    </div>

                    <button type="submit" id="login-submit-btn" class="btn btn-primary btn-block" style="height:44px; font-size:13.5px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">lock_open</span>
                        <span id="login-submit-label">Sign In as Customer</span>
                    </button>

                    <!-- Terms & Privacy Policy Legal Agreement Notice -->
                    <div class="login-terms-notice" style="text-align:center; font-size:11px; color:#64748b; margin-top:12px; line-height:1.4;">
                        By proceeding, you agree to 
                        <a href="{{ $platformSettings['privacy_policy_url'] ?? '/privacy-policy' }}" id="login-link-privacy" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">Privacy Policy</a> 
                        and 
                        <a href="{{ $platformSettings['terms_conditions_url'] ?? '/terms-conditions' }}" id="login-link-terms" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">T&Cs</a>.
                    </div>
                </form>

                <!-- One-Click Quick Role Switchers -->
                <!-- <div class="demo-login-drawer">
                    <div class="demo-login-divider">
                        <span>⚡ One-Click Instant Role Demo</span>
                    </div>
                    <div class="demo-role-buttons">
                        <button type="button" class="btn-demo-role" onclick="quickDemoLogin('customer')">
                            <span class="demo-avatar">👤</span>
                            <span class="demo-label">Customer</span>
                        </button>
                        <button type="button" class="btn-demo-role" onclick="quickDemoLogin('driver')">
                            <span class="demo-avatar">👨🏽‍✈️</span>
                            <span class="demo-label">Driver Partner</span>
                        </button>
                        <button type="button" class="btn-demo-role" onclick="quickDemoLogin('admin')">
                            <span class="demo-avatar">🛡️</span>
                            <span class="demo-label">Platform Admin</span>
                        </button>
                    </div>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Toast Shelf -->
    <div class="toast-shelf" id="toast-shelf"></div>

    <!-- Global App State -->
    @php
        $jsUser = Auth::user() ? [
            'id' => Auth::user()->id,
            'name' => Auth::user()->name,
            'email' => Auth::user()->email,
            'role' => Auth::user()->role,
            'phone' => Auth::user()->phone,
            'avatar' => Auth::user()->avatar ?? '👤',
        ] : null;
    @endphp
    <script>
        window.CURRENT_USER = {!! json_encode($jsUser) !!};
    </script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Application Logic -->
    <script src="{{ asset('js/app.js') }}?v={{ time() }}"></script>
</body>
</html>

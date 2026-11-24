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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

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
                            @if(Auth::user()->role === 'customer')
                                <div class="dropdown-section-title">Customer Menu</div>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('my-bookings'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:var(--primary);">confirmation_number</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">My Trips & Tickets</span>
                                        <span class="dropdown-item-sub">Live passes, receipts & OTPs</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('cabs'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#2563eb;">directions_car</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Book Outstation Cab</span>
                                        <span class="dropdown-item-sub">One-way, round trip & airport</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('schedules'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#0d9488;">directions_bus</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Luxury Sleeper Berths</span>
                                        <span class="dropdown-item-sub">Reserve AC sleeper coach</span>
                                    </div>
                                </button>
                            @elseif(Auth::user()->role === 'driver')
                                <div class="dropdown-section-title">Chauffeur Console</div>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('driver-trips'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#16a34a;">local_taxi</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Assigned Trips</span>
                                        <span class="dropdown-item-sub">Active rides & route duty</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('bidding'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:var(--primary);">local_offer</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Live Bids & Auctions</span>
                                        <span class="dropdown-item-sub">Bid on customer ride requests</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('driver-wallet'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#0284c7;">account_balance_wallet</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Chauffeur Wallet</span>
                                        <span class="dropdown-item-sub">Earnings & instant payout</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="switchTab('driver-profile'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#64748b;">badge</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Driver Profile & Fleet</span>
                                        <span class="dropdown-item-sub">KYC, documents & vehicles</span>
                                    </div>
                                </button>
                            @elseif(Auth::user()->role === 'admin')
                                <div class="dropdown-section-title">Platform Administration</div>
                                <button type="button" class="dropdown-item-btn active-role" onclick="switchTab('admin'); toggleUserMenu();">
                                    <span class="material-symbols-outlined dropdown-item-icon" style="font-size:18px; color:#0284c7;">admin_panel_settings</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">AdminLTE 3 Portal</span>
                                        <span class="dropdown-item-sub">Fleet, dispatch & financial audit</span>
                                    </div>
                                </button>
                                <div class="dropdown-divider"></div>
                                <div class="dropdown-section-title">Role Perspective Preview</div>
                                <button type="button" class="dropdown-item-btn" onclick="quickDemoLogin('customer')">
                                    <span class="dropdown-item-icon">👤</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Customer Preview</span>
                                        <span class="dropdown-item-sub">Book Cabs & Berths</span>
                                    </div>
                                </button>
                                <button type="button" class="dropdown-item-btn" onclick="quickDemoLogin('driver')">
                                    <span class="dropdown-item-icon">👨🏽‍✈️</span>
                                    <div class="dropdown-item-text">
                                        <span class="dropdown-item-title">Driver Preview</span>
                                        <span class="dropdown-item-sub">Live Bids, Trips & Wallet</span>
                                    </div>
                                </button>
                            @endif
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
                    <!-- Minimal MVP Distraction-Free Sign In & Sign Up for Customers -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" class="btn-auth-trigger" onclick="openLoginModal('customer', 'signin')">
                            <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                            <span>Sign In</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openLoginModal('customer', 'signup')" style="font-size:12px; height:34px; padding:0 12px; font-weight:700;">
                            <span class="material-symbols-outlined" style="font-size:15px;">person_add</span>
                            <span>Sign Up</span>
                        </button>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Yield -->
    @yield('content')

    <!-- ======================================================== -->
    <!-- MODAL: ROLE-BASED AUTHENTICATION (SIGN IN & SIGN UP)     -->
    <!-- ======================================================== -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-dialog auth-modal-dialog" style="max-width: 460px;">
            <div class="modal-header auth-modal-header">
                <div class="auth-header-brand">
                    <img src="{{ asset('assets/logo.jpg') }}" alt="Intercity Wheels" class="auth-logo-img">
                    <div>
                        <h3 class="modal-title" id="auth-modal-title" style="color:#ffffff;">Portal Sign In</h3>
                        <p class="auth-header-subtitle" id="auth-modal-subtitle">Authenticate securely or register a new account</p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" style="color:#cbd5e1;" onclick="closeModal('login-modal')">×</button>
            </div>

            <div class="modal-body auth-modal-body">
                <!-- Top Mode Switcher: Sign In vs Sign Up -->
                <div class="auth-mode-nav" style="display:flex; background:#f1f5f9; border-radius:8px; padding:3px; margin-bottom:14px; gap:4px;">
                    <button type="button" class="auth-mode-btn active" id="tab-btn-signin" onclick="switchAuthMode('signin')" style="flex:1; padding:8px 12px; border:none; border-radius:6px; font-weight:700; font-size:12.5px; cursor:pointer; background:#ffffff; color:#0f172a; box-shadow:0 1px 3px rgba(0,0,0,0.08); display:flex; align-items:center; justify-content:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                        <span>Sign In</span>
                    </button>
                    <button type="button" class="auth-mode-btn" id="tab-btn-signup" onclick="switchAuthMode('signup')" style="flex:1; padding:8px 12px; border:none; border-radius:6px; font-weight:700; font-size:12.5px; cursor:pointer; background:transparent; color:#64748b; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:16px;">person_add</span>
                        <span>Sign Up</span>
                    </button>
                </div>

                <!-- ================= SECTION 1: SIGN IN ================= -->
                <div id="auth-signin-container">
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

                    <!-- Sign In Method Switcher (Mobile OTP vs Password) -->
                    <div class="signin-method-toggle" style="display:flex; justify-content:center; gap:8px; margin-bottom:14px;">
                        <button type="button" class="method-toggle-btn active" id="method-btn-otp" onclick="switchSignInMethod('otp')" style="border:1px solid #0284c7; background:#f0f9ff; color:#0369a1; padding:6px 14px; border-radius:20px; font-size:11.5px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined" style="font-size:15px; color:#0284c7;">smartphone</span>
                            <span>Mobile OTP Login</span>
                        </button>
                        <button type="button" class="method-toggle-btn" id="method-btn-password" onclick="switchSignInMethod('password')" style="border:1px solid #cbd5e1; background:#ffffff; color:#64748b; padding:6px 14px; border-radius:20px; font-size:11.5px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-outlined" style="font-size:15px; color:#64748b;">key</span>
                            <span>Email & Password</span>
                        </button>
                    </div>

                    <!-- METHOD A: MOBILE OTP SIGN IN -->
                    <div id="signin-view-otp">
                        <!-- Step 1: Send OTP -->
                        <form id="otp-request-form" onsubmit="handleSendOtp(event)">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label" for="login-otp-phone">Enter Mobile Number</label>
                                <div style="display:flex; align-items:stretch; gap:6px;">
                                    <span style="display:flex; align-items:center; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:0 10px; font-size:13px; font-weight:700; color:#334155;">🇮🇳 +91</span>
                                    <input type="tel" id="login-otp-phone" class="form-control" placeholder="10-digit mobile number" maxlength="10" required pattern="[0-9]{10}" style="font-weight:700; font-size:14px; letter-spacing:0.5px;" value="9876543210">
                                </div>
                                <span style="font-size:11px; color:#64748b; margin-top:3px; display:block;">Instant OTP authentication via Fast2SMS SMS Gateway.</span>
                            </div>

                            <button type="submit" id="btn-send-otp" class="btn btn-primary btn-block" style="height:44px; font-size:13.5px;">
                                <span class="material-symbols-outlined" style="font-size:18px;">send</span>
                                <span id="btn-send-otp-text">Send Verification OTP</span>
                            </button>
                        </form>

                        <!-- Step 2: Enter & Verify OTP (Revealed after OTP is sent) -->
                        <form id="otp-verify-form" onsubmit="handleVerifyOtp(event)" style="display:none; margin-top:14px;">
                            <!-- Live OTP Preview Callout -->
                            <div id="otp-live-preview-box" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-bottom:12px; text-align:center;">
                                <div style="font-size:11.5px; color:#1e40af; font-weight:600;">
                                    📱 <span id="otp-status-gateway-label">Fast2SMS Gateway Preview Mode</span>
                                </div>
                                <div style="font-size:12px; color:#1e3a8a; margin-top:4px;">
                                    Your Verification OTP is: <strong id="otp-display-number" style="font-family:monospace; font-size:20px; font-weight:900; color:#1d4ed8; letter-spacing:3px; background:#ffffff; padding:2px 8px; border-radius:4px; border:1px dashed #60a5fa; margin-left:4px;">1234</strong>
                                </div>
                                <div style="margin-top:6px;">
                                    <button type="button" class="btn btn-xs btn-outline" onclick="autoFillReceivedOtp()" style="font-size:10.5px; padding:2px 8px; color:#0284c7; border-color:#93c5fd; background:#ffffff;">
                                        ⚡ Click to Auto-Fill OTP
                                    </button>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label" for="login-otp-input">Enter Verification OTP</label>
                                <input type="text" id="login-otp-input" class="form-control" placeholder="••••" maxlength="6" required style="font-family:monospace; font-size:22px; font-weight:800; text-align:center; letter-spacing:6px; height:46px;">
                            </div>

                            <button type="submit" id="btn-verify-otp" class="btn btn-primary btn-block" style="height:44px; font-size:13.5px; margin-bottom:8px;">
                                <span class="material-symbols-outlined" style="font-size:18px;">verified_user</span>
                                <span>Verify & Sign In</span>
                            </button>

                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; margin-top:10px;">
                                <span style="color:#64748b; cursor:pointer; text-decoration:underline;" onclick="resetOtpStep()">
                                    &larr; Change mobile number
                                </span>
                                <span id="resend-otp-btn" style="color:#0284c7; font-weight:700; cursor:pointer;" onclick="resendOtp()">
                                    Resend OTP
                                </span>
                            </div>
                        </form>
                    </div>

                    <!-- METHOD B: EMAIL & PASSWORD SIGN IN -->
                    <div id="signin-view-password" style="display:none;">
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
                        </form>
                    </div>

                    <!-- Alternate Sign In Providers (Firebase & Clerk) -->
                    <div class="alternate-auth-section" id="alternate-auth-signin">
                        <div class="auth-divider">
                            <span>Or continue with</span>
                        </div>
                        <div class="alternate-auth-buttons">
                            <button type="button" class="btn-alternate-auth btn-firebase-google" id="btn-auth-firebase-google" onclick="handleAlternateLogin('firebase')" title="Sign in with Google via Firebase">
                                <svg width="18" height="18" viewBox="0 0 24 24" style="flex-shrink:0;">
                                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.15z"/>
                                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                </svg>
                                <span>Google / Firebase</span>
                            </button>
                            <button type="button" class="btn-alternate-auth btn-clerk-auth" id="btn-auth-clerk" onclick="handleAlternateLogin('clerk')" title="Sign in with Clerk">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;">
                                    <rect width="24" height="24" rx="4" fill="#6C47FF"/>
                                    <path d="M12 6.5C8.96 6.5 6.5 8.96 6.5 12C6.5 15.04 8.96 17.5 12 17.5C13.88 17.5 15.54 16.55 16.52 15.11L14.73 13.91C14.15 14.87 13.15 15.5 12 15.5C10.07 15.5 8.5 13.93 8.5 12C8.5 10.07 10.07 8.5 12 8.5C13.15 8.5 14.15 9.13 14.73 10.09L16.52 8.89C15.54 7.45 13.88 6.5 12 6.5Z" fill="white"/>
                                </svg>
                                <span>Clerk Auth</span>
                            </button>
                        </div>
                    </div>

                    <!-- Toggle to Sign Up -->
                    <div style="text-align:center; font-size:12px; color:#475569; margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                        Don't have an account yet? 
                        <a href="javascript:void(0)" onclick="switchAuthMode('signup')" style="color:var(--primary); font-weight:700; text-decoration:none;">Create Account (Sign Up)</a>
                    </div>
                </div>

                <!-- ================= SECTION 2: SIGN UP ================= -->
                <div id="auth-signup-container" style="display:none;">
                    <!-- Role Switcher for Registration (Customer vs Driver Partner) -->
                    <div class="role-selector-pills" style="grid-template-columns: 1fr 1fr; margin-bottom:12px;">
                        <button type="button" class="role-pill active" id="signup-role-btn-customer" onclick="selectSignupRole('customer')">
                            <span class="role-icon">👤</span>
                            <span class="role-label">Passenger / Customer</span>
                        </button>
                        <button type="button" class="role-pill" id="signup-role-btn-driver" onclick="selectSignupRole('driver')">
                            <span class="role-icon">👨🏽‍✈️</span>
                            <span class="role-label">Driver / Fleet Partner</span>
                        </button>
                    </div>

                    <form id="auth-signup-form" onsubmit="handleFormRegister(event)">
                        <input type="hidden" id="signup-role-input" value="customer">

                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" for="signup-name">Full Name *</label>
                            <div class="input-with-icon">
                                <span class="material-symbols-outlined">badge</span>
                                <input type="text" id="signup-name" class="form-control" placeholder="e.g. Rahul Sharma" required>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="signup-phone">Mobile Number *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">call</span>
                                    <input type="tel" id="signup-phone" class="form-control" placeholder="9876543210" maxlength="10" required pattern="[0-9]{10}">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="signup-email">Email Address *</label>
                                <div class="input-with-icon">
                                    <span class="material-symbols-outlined">mail</span>
                                    <input type="email" id="signup-email" class="form-control" placeholder="rahul@example.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" for="signup-password">Choose Password *</label>
                            <div class="input-with-icon">
                                <span class="material-symbols-outlined">lock</span>
                                <input type="password" id="signup-password" class="form-control" placeholder="At least 6 characters" minlength="6" required>
                            </div>
                        </div>

                        <!-- Driver Extra Fields (Visible when Driver Partner selected) -->
                        <div id="signup-driver-fields" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:12px;">
                            <div style="font-size:11.5px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">local_taxi</span>
                                <span>Chauffeur Vehicle Details</span>
                            </div>
                            <div style="display:grid; grid-template-columns: 1.3fr 1fr; gap:8px;">
                                <div>
                                    <label class="form-label" style="font-size:10.5px;">Vehicle Model</label>
                                    <input type="text" id="signup-veh-model" class="form-control" placeholder="e.g. Innova Crysta / Ertiga">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size:10.5px;">Plate Number</label>
                                    <input type="text" id="signup-veh-plate" class="form-control" placeholder="WB 02 AB 1234" style="text-transform:uppercase;">
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="signup-submit-btn" class="btn btn-primary btn-block" style="height:44px; font-size:13.5px; margin-top:4px;">
                            <span class="material-symbols-outlined" style="font-size:18px;">how_to_reg</span>
                            <span id="signup-submit-label">Create Customer Account</span>
                        </button>
                    </form>

                    <!-- Alternate Registration Providers (Firebase & Clerk) -->
                    <div class="alternate-auth-section" id="alternate-auth-signup">
                        <div class="auth-divider">
                            <span>Or register with</span>
                        </div>
                        <div class="alternate-auth-buttons">
                            <button type="button" class="btn-alternate-auth btn-firebase-google" id="btn-signup-firebase-google" onclick="handleAlternateLogin('firebase')" title="Sign up with Google via Firebase">
                                <svg width="18" height="18" viewBox="0 0 24 24" style="flex-shrink:0;">
                                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.15z"/>
                                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                </svg>
                                <span>Google / Firebase</span>
                            </button>
                            <button type="button" class="btn-alternate-auth btn-clerk-auth" id="btn-signup-clerk" onclick="handleAlternateLogin('clerk')" title="Sign up with Clerk">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;">
                                    <rect width="24" height="24" rx="4" fill="#6C47FF"/>
                                    <path d="M12 6.5C8.96 6.5 6.5 8.96 6.5 12C6.5 15.04 8.96 17.5 12 17.5C13.88 17.5 15.54 16.55 16.52 15.11L14.73 13.91C14.15 14.87 13.15 15.5 12 15.5C10.07 15.5 8.5 13.93 8.5 12C8.5 10.07 10.07 8.5 12 8.5C13.15 8.5 14.15 9.13 14.73 10.09L16.52 8.89C15.54 7.45 13.88 6.5 12 6.5Z" fill="white"/>
                                </svg>
                                <span>Clerk Auth</span>
                            </button>
                        </div>
                    </div>

                    <!-- Toggle to Sign In -->
                    <div style="text-align:center; font-size:12px; color:#475569; margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                        Already registered? 
                        <a href="javascript:void(0)" onclick="switchAuthMode('signin')" style="color:var(--primary); font-weight:700; text-decoration:none;">Sign In to Your Account</a>
                    </div>
                </div>

                <!-- Terms & Privacy Policy Legal Agreement Notice -->
                <div class="login-terms-notice" style="text-align:center; font-size:11px; color:#64748b; margin-top:12px; line-height:1.4;">
                    By proceeding, you agree to 
                    <a href="{{ $platformSettings['privacy_policy_url'] ?? '/privacy-policy' }}" id="login-link-privacy" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">Privacy Policy</a> 
                    and 
                    <a href="{{ $platformSettings['terms_conditions_url'] ?? '/terms-conditions' }}" id="login-link-terms" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:underline;">T&Cs</a>.
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: ALTERNATE AUTHENTICATION (FIREBASE / CLERK)       -->
    <!-- ======================================================== -->
    <div class="modal-overlay" id="alternate-auth-modal">
        <div class="modal-dialog auth-modal-dialog" style="max-width: 440px;">
            <div class="modal-header auth-modal-header">
                <div class="auth-header-brand">
                    <span id="alt-modal-brand-icon" style="font-size:24px; display:flex; align-items:center;">🔥</span>
                    <div>
                        <h3 class="modal-title" id="alt-modal-title" style="color:#ffffff;">Alternate Sign In</h3>
                        <p class="auth-header-subtitle" id="alt-modal-subtitle">Firebase & Clerk Authentication Portal</p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" style="color:#cbd5e1;" onclick="closeModal('alternate-auth-modal')">×</button>
            </div>

            <div class="modal-body auth-modal-body">
                <!-- Status Notice -->
                <div id="alt-status-notice" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:12px; color:#475569; display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" id="alt-status-icon" style="color:#0284c7; font-size:20px;">info</span>
                    <span id="alt-status-text">Authenticate instantly with your account credentials.</span>
                </div>

                <!-- Provider Badge & Selected Role Info -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; background:#f1f5f9; padding:8px 12px; border-radius:6px; font-size:12px;">
                    <span style="color:#64748b; font-weight:600;">Provider: <strong id="alt-provider-name" style="color:#0f172a;">Firebase (Google)</strong></span>
                    <span style="color:#64748b; font-weight:600;">Target Role: <strong id="alt-role-name" style="color:var(--primary); text-transform:capitalize;">Customer</strong></span>
                </div>

                <!-- 1-Click Fast Profiles -->
                <div style="margin-bottom:14px;">
                    <label class="form-label" style="font-size:11.5px; font-weight:700; color:#475569; margin-bottom:6px; display:block;">Quick Simulated Accounts</label>
                    <div style="display:grid; grid-template-columns: 1fr; gap:6px;">
                        <button type="button" class="btn btn-outline" onclick="selectAltDemoProfile('customer')" style="text-align:left; justify-content:flex-start; padding:8px 10px; font-size:12px; background:#ffffff; border-color:#cbd5e1; gap:8px;">
                            <span style="font-size:16px;">👤</span>
                            <div style="flex:1;">
                                <div style="font-weight:700; color:#0f172a;">Priya Sharma (Passenger)</div>
                                <div style="font-size:11px; color:#64748b;">priya.customer@gmail.com</div>
                            </div>
                            <span style="font-size:11px; color:#0284c7; font-weight:700;">Select &rarr;</span>
                        </button>
                        <button type="button" class="btn btn-outline" onclick="selectAltDemoProfile('driver')" style="text-align:left; justify-content:flex-start; padding:8px 10px; font-size:12px; background:#ffffff; border-color:#cbd5e1; gap:8px;">
                            <span style="font-size:16px;">👨🏽‍✈️</span>
                            <div style="flex:1;">
                                <div style="font-weight:700; color:#0f172a;">Rajesh Chauffeur (Driver)</div>
                                <div style="font-size:11px; color:#64748b;">rajesh.driver@gmail.com</div>
                            </div>
                            <span style="font-size:11px; color:#0284c7; font-weight:700;">Select &rarr;</span>
                        </button>
                    </div>
                </div>

                <!-- Divider -->
                <div class="auth-divider" style="margin:10px 0;">
                    <span>Or specify account credentials</span>
                </div>

                <!-- Custom Account Input Form -->
                <form id="alt-auth-form" onsubmit="submitAlternateAuthForm(event)">
                    <input type="hidden" id="alt-input-provider" value="firebase">
                    <input type="hidden" id="alt-input-role" value="customer">

                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" for="alt-input-name" style="font-size:11.5px;">Full Name</label>
                        <input type="text" id="alt-input-name" class="form-control" placeholder="e.g. Ananya Sen" value="Priya Sharma" required>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="alt-input-email" style="font-size:11.5px;">Email Address</label>
                        <input type="email" id="alt-input-email" class="form-control" placeholder="name@domain.com" value="priya.customer@gmail.com" required>
                    </div>

                    <button type="submit" id="alt-submit-btn" class="btn btn-primary btn-block" style="height:42px; font-size:13px;">
                        <span class="material-symbols-outlined" style="font-size:17px;">verified_user</span>
                        <span id="alt-submit-label">Sign In with Firebase</span>
                    </button>
                </form>

                <div id="alt-env-tip" style="margin-top:12px; font-size:11px; color:#64748b; background:#f0f9ff; border:1px dashed #7dd3fc; border-radius:6px; padding:8px 10px; line-height:1.4;">
                    💡 <strong>Live SDK Setup:</strong> Add <code>FIREBASE_API_KEY</code> or <code>CLERK_PUBLISHABLE_KEY</code> in <code>.env</code> to trigger the live popup window automatically.
                </div>
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
        $jsLocations = isset($serviceLocations) 
            ? $serviceLocations 
            : \App\Models\ServiceLocation::orderBy('name')->get();
        $jsCabRoutes = isset($allCabRoutes) 
            ? $allCabRoutes 
            : \App\Models\RouteFare::where('is_active', true)->get();
        $alternateAuthConfig = app(\App\Services\AlternateAuthService::class)->getPublicConfig();
    @endphp
    <script>
        window.CURRENT_USER = {!! json_encode($jsUser) !!};
        window.INITIAL_SERVICE_LOCATIONS = {!! json_encode($jsLocations) !!};
        window.INITIAL_CAB_ROUTES = {!! json_encode($jsCabRoutes) !!};
        window.ALTERNATE_AUTH_CONFIG = {!! json_encode($alternateAuthConfig) !!};
    </script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Application Logic -->
    <script src="{{ asset('js/app.js') }}?v={{ time() }}"></script>
</body>
</html>

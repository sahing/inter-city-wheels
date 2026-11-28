@extends('layouts.app')

@section('content')
@php
    $portalMeta = match($portalRole) {
        'driver' => [
            'title' => 'Driver Partner Portal',
            'subtitle' => 'Sign in or register your chauffeur & vehicle profile',
            'badge' => 'DRIVER PARTNER',
            'icon' => '👨🏽‍✈️',
            'loginAction' => route('driver.login'),
            'registerAction' => route('driver.register'),
            'allowSignup' => true,
            'defaultEmail' => 'driver@intercitywheels.com',
            'submitLabel' => 'Sign In as Driver',
            'signupLabel' => 'Register as Driver Partner',
        ],
        'admin' => [
            'title' => 'Platform Admin Sign In',
            'subtitle' => 'Operations, fleet dispatch & financial console',
            'badge' => 'ADMIN ACCESS',
            'icon' => '🛡️',
            'loginAction' => route('admin.login'),
            'registerAction' => null,
            'allowSignup' => false,
            'defaultEmail' => 'admin@intercitywheels.com',
            'submitLabel' => 'Sign In to Admin Console',
            'signupLabel' => null,
        ],
        default => [
            'title' => 'Customer Sign In',
            'subtitle' => 'Book verified outstation cabs & track your trips',
            'badge' => 'CUSTOMER',
            'icon' => '👤',
            'loginAction' => route('login'),
            'registerAction' => route('register'),
            'allowSignup' => true,
            'defaultEmail' => 'customer@intercitywheels.com',
            'submitLabel' => 'Sign In as Customer',
            'signupLabel' => 'Create Customer Account',
        ],
    };
@endphp
<main class="app-viewport" style="min-height:calc(100vh - 120px); display:flex; align-items:center; justify-content:center; padding:32px 16px;">
    <div class="auth-modal-dialog" style="width:100%; max-width:460px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 20px 50px rgba(15, 23, 42, 0.25); border:1px solid #e2e8f0;">
        <div class="modal-header auth-modal-header">
            <div class="auth-header-brand">
                <span style="font-size:26px; width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,0.1); display:inline-flex; align-items:center; justify-content:center;">{{ $portalMeta['icon'] }}</span>
                <div>
                    <h2 class="modal-title" style="color:#ffffff; margin:0; font-size:17px;">{{ $portalMeta['title'] }}</h2>
                    <p class="auth-header-subtitle" style="margin:2px 0 0;">{{ $portalMeta['subtitle'] }}</p>
                </div>
            </div>
            <span class="admin-badge badge-gold" style="font-size:10px;">{{ $portalMeta['badge'] }}</span>
        </div>

        <div class="modal-body auth-modal-body" style="max-height:none;">
            @if($portalMeta['allowSignup'])
            <div class="auth-mode-nav" style="display:flex; background:#f1f5f9; border-radius:8px; padding:3px; margin-bottom:14px; gap:4px;">
                <button type="button" class="auth-mode-btn active" id="portal-tab-signin" onclick="switchPortalMode('signin')" style="flex:1; padding:8px 12px; border:none; border-radius:6px; font-weight:700; font-size:12.5px; cursor:pointer; background:#ffffff; color:#0f172a; box-shadow:0 1px 3px rgba(0,0,0,0.08); display:flex; align-items:center; justify-content:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                    <span>Sign In</span>
                </button>
                <button type="button" class="auth-mode-btn" id="portal-tab-signup" onclick="switchPortalMode('signup')" style="flex:1; padding:8px 12px; border:none; border-radius:6px; font-weight:700; font-size:12.5px; cursor:pointer; background:transparent; color:#64748b; display:flex; align-items:center; justify-content:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:16px;">person_add</span>
                    <span>{{ $portalRole === 'driver' ? 'Driver Sign Up' : 'Sign Up' }}</span>
                </button>
            </div>
            @endif

            <div id="portal-signin-pane">
                @if($portalRole !== 'admin')
                <div class="alternate-auth-section" style="margin-top:0; margin-bottom:12px;">
                    <div class="alternate-auth-buttons">
                        <button type="button" class="btn-alternate-auth btn-firebase-google" onclick="portalAlternateLogin('firebase')" style="padding:9px 12px; font-weight:700;">
                            <span>Firebase ID / Google</span>
                        </button>
                        <button type="button" class="btn-alternate-auth btn-clerk-auth" onclick="portalAlternateLogin('clerk')" style="padding:9px 12px; font-weight:700;">
                            <span>Clerk Auth</span>
                        </button>
                    </div>
                    <div class="auth-divider" style="margin:12px 0 10px;">
                        <span>or sign in with email</span>
                    </div>
                </div>
                @endif

                <form id="portal-login-form" action="{{ $portalMeta['loginAction'] }}" method="POST" onsubmit="submitPortalForm(event, '{{ $portalMeta['loginAction'] }}', 'portal-login-btn')">
                    @csrf
                    <input type="hidden" name="role" value="{{ $portalRole }}">

                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" for="portal-login-email" style="font-size:12px; margin-bottom:4px;">Account Email</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined">mail</span>
                            <input type="email" name="email" id="portal-login-email" class="form-control" required value="{{ old('email', config('demo.enabled') ? $portalMeta['defaultEmail'] : '') }}" placeholder="you@intercitywheels.com">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="portal-login-password" style="font-size:12px; margin-bottom:4px;">Password</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined">lock</span>
                            <input type="password" name="password" id="portal-login-password" class="form-control" required value="{{ config('demo.enabled') ? 'password123' : '' }}" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" id="portal-login-btn" class="btn btn-primary btn-block" style="height:42px; font-size:13px; font-weight:700;">
                        <span class="material-symbols-outlined" style="font-size:17px;">lock_open</span>
                        <span>{{ $portalMeta['submitLabel'] }}</span>
                    </button>
                </form>
            </div>

            @if($portalMeta['allowSignup'])
            <div id="portal-signup-pane" style="display:none;">
                <form id="portal-signup-form" action="{{ $portalMeta['registerAction'] }}" method="POST" onsubmit="submitPortalForm(event, '{{ $portalMeta['registerAction'] }}', 'portal-signup-btn')">
                    @csrf
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" for="portal-signup-name">Full Name *</label>
                        <input type="text" name="name" id="portal-signup-name" class="form-control" placeholder="{{ $portalRole === 'driver' ? 'e.g. Rajesh Chauffeur' : 'e.g. Rahul Sharma' }}" required>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="portal-signup-phone">Mobile Number *</label>
                            <input type="tel" name="phone" id="portal-signup-phone" class="form-control" placeholder="9876543210" maxlength="20" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="portal-signup-email">Email Address *</label>
                            <input type="email" name="email" id="portal-signup-email" class="form-control" placeholder="name@example.com" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" for="portal-signup-password">Password *</label>
                        <input type="password" name="password" id="portal-signup-password" class="form-control" placeholder="At least 6 characters" minlength="6" required>
                    </div>

                    @if($portalRole === 'driver')
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:12px;">
                        <div style="font-size:11.5px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                            <span class="material-symbols-outlined" style="font-size:16px; color:var(--primary);">local_taxi</span>
                            <span>Chauffeur Vehicle Details</span>
                        </div>
                        <div style="display:grid; grid-template-columns:1.3fr 1fr; gap:8px;">
                            <div>
                                <label class="form-label" style="font-size:10.5px;">Vehicle Model</label>
                                <input type="text" name="vehicle_model" class="form-control" placeholder="e.g. Innova Crysta / Ertiga">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:10.5px;">Plate Number</label>
                                <input type="text" name="vehicle_plate" class="form-control" placeholder="WB 02 AB 1234" style="text-transform:uppercase;">
                            </div>
                        </div>
                    </div>
                    @endif

                    <button type="submit" id="portal-signup-btn" class="btn btn-primary btn-block" style="height:44px; font-size:13.5px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">how_to_reg</span>
                        <span>{{ $portalMeta['signupLabel'] }}</span>
                    </button>
                </form>
            </div>
            @endif

            <div style="text-align:center; font-size:11.5px; color:#64748b; margin-top:14px; padding-top:10px; border-top:1px dashed #e2e8f0;">
                @if($portalRole !== 'customer')
                    <a href="{{ route('login.portal') }}" style="color:#0284c7; font-weight:600; text-decoration:none;">Customer Login</a> &middot;
                @endif
                @if($portalRole !== 'driver')
                    <a href="{{ route('driver.portal') }}" style="color:#0284c7; font-weight:600; text-decoration:none;">Driver Portal</a> &middot;
                @endif
                @if($portalRole !== 'admin')
                    <a href="{{ route('admin.portal') }}" style="color:#0284c7; font-weight:600; text-decoration:none;">Admin Login</a> &middot;
                @endif
                <a href="{{ route('home') }}" style="color:#475569; font-weight:600; text-decoration:none;">Back to Home</a>
            </div>
        </div>
    </div>
</main>

<script>
function switchPortalMode(mode) {
    const isSignup = mode === 'signup';
    const signinPane = document.getElementById('portal-signin-pane');
    const signupPane = document.getElementById('portal-signup-pane');
    const btnIn = document.getElementById('portal-tab-signin');
    const btnUp = document.getElementById('portal-tab-signup');
    if (signinPane) signinPane.style.display = isSignup ? 'none' : 'block';
    if (signupPane) signupPane.style.display = isSignup ? 'block' : 'none';
    if (btnIn) {
        btnIn.style.background = isSignup ? 'transparent' : '#ffffff';
        btnIn.style.color = isSignup ? '#64748b' : '#0f172a';
    }
    if (btnUp) {
        btnUp.style.background = isSignup ? '#ffffff' : 'transparent';
        btnUp.style.color = isSignup ? '#0f172a' : '#64748b';
    }
}

async function submitPortalForm(event, endpoint, btnId) {
    event.preventDefault();
    const form = event.target;
    const btn = document.getElementById(btnId);
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) btn.disabled = true;

    const payload = Object.fromEntries(new FormData(form).entries());

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.success) {
            if (typeof showToast === 'function') showToast(data.message || 'Authenticated!', 'success');
            window.location.replace(data.redirect_url || '/');
        } else {
            const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Authentication failed.');
            if (typeof showToast === 'function') showToast(msg, 'warning');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    } catch (err) {
        if (typeof showToast === 'function') showToast('Network error during authentication.', 'warning');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

function portalAlternateLogin(provider) {
    const role = @json($portalRole);
    if (provider === 'firebase' && window.ALTERNATE_AUTH_CONFIG?.firebase?.is_configured && typeof window.loginWithFirebaseLive === 'function') {
        return window.loginWithFirebaseLive(role);
    }
    if (provider === 'clerk' && window.ALTERNATE_AUTH_CONFIG?.clerk?.is_configured && typeof window.loginWithClerkLive === 'function') {
        return window.loginWithClerkLive(role);
    }
    if (typeof window.openAlternateAuthModal === 'function') {
        return window.openAlternateAuthModal(provider, role);
    }
}
</script>
@endsection

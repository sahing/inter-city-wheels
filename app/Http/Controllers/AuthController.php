<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\User;
use App\Services\AlternateAuthService;
use App\Services\Fast2SmsService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Render the dedicated Customer sign-in / registration portal (/login).
     */
    public function showCustomerPortal()
    {
        if (Auth::check() && Auth::user()->role === 'customer') {
            return redirect()->route('home');
        }

        return view('auth.portal', ['portalRole' => 'customer']);
    }

    /**
     * Render the dedicated Driver Partner sign-in / registration portal (/driver).
     */
    public function showDriverPortal()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['driver', 'admin'], true)) {
            return redirect()->route('home', ['tab' => 'bidding']);
        }

        return view('auth.portal', ['portalRole' => 'driver']);
    }

    /**
     * Render the dedicated Admin sign-in portal (/admin).
     */
    public function showAdminPortal()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('home', ['tab' => 'admin']);
        }

        return view('auth.portal', ['portalRole' => 'admin']);
    }

    /**
     * Authenticate user with role-aware logic.
     */
    public function login(Request $request)
    {
        return $this->authenticateWithRole($request);
    }

    /**
     * Authenticate a driver on the dedicated /driver/login endpoint.
     */
    public function driverLogin(Request $request)
    {
        return $this->authenticateWithRole($request, 'driver');
    }

    /**
     * Authenticate an admin on the dedicated /admin/login endpoint.
     */
    public function adminLogin(Request $request)
    {
        return $this->authenticateWithRole($request, 'admin');
    }

    /**
     * Shared role-checked credential authentication.
     */
    private function authenticateWithRole(Request $request, ?string $forcedRole = null)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'nullable|string|in:customer,driver,admin',
            'remember' => 'nullable|boolean',
        ]);

        $credentials = [
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        $user = User::where('email', $credentials['email'])->first();

        $passwordMatches = $user && Hash::check($credentials['password'], $user->password);

        // If user was created via Firebase/SSO and enters a new custom password (6+ chars) while default password is set
        if ($user && ! $passwordMatches && $user->auth_provider === 'firebase' && Hash::check('password123', $user->password) && strlen($credentials['password']) >= 6) {
            $user->update(['password' => Hash::make($credentials['password'])]);
            $passwordMatches = true;
        }

        if (! $user || ! $passwordMatches) {
            if (! $request->wantsJson() && ! $request->ajax()) {
                return back()->withErrors(['email' => 'Invalid email or password. Please verify your credentials.'])->withInput();
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password. Please verify your credentials.',
            ], 422);
        }

        $expectedRole = $forcedRole ?? ($validated['role'] ?? null);

        // Check role match when a specific portal or role tab is targeted
        if (! empty($expectedRole) && $user->role !== $expectedRole) {
            $msg = 'This account is registered as a '.ucfirst($user->role).'. Please select the '.ucfirst($user->role).' tab to log in.';
            if (! $request->wantsJson() && ! $request->ajax()) {
                return back()->withErrors(['email' => $msg])->withInput();
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $redirectTo = $this->safeNext($request) ?? route('home');

        // Target landing tab according to role
        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect($redirectTo)->with('success', "Welcome back, {$user->name}!");
        }

        return response()->json([
            'success' => true,
            'message' => "Welcome back, {$user->name}!",
            'redirect_url' => $redirectTo,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
            ],
            'target_tab' => $targetTab,
        ]);
    }

    /**
     * Register a new Customer account (/register).
     */
    public function register(Request $request)
    {
        $request->validate([
            'role' => 'nullable|string|in:customer',
        ], [
            'role.in' => 'Driver accounts must register via /driver/register.',
        ]);

        return $this->registerAccount($request, 'customer');
    }

    /**
     * Register a new Driver Partner account (/driver/register).
     */
    public function driverRegister(Request $request)
    {
        return $this->registerAccount($request, 'driver');
    }

    /**
     * Shared user / driver account provisioning.
     */
    private function registerAccount(Request $request, string $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:30',
        ]);

        $phone = trim($validated['phone']);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $phone,
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'avatar' => $role === 'driver' ? '👨🏽‍✈️' : '👤',
        ]);

        if ($role === 'driver') {
            $vehicleModel = trim((string) ($validated['vehicle_model'] ?? ''));
            $vehiclePlate = strtoupper(trim((string) ($validated['vehicle_plate'] ?? '')));

            Driver::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $user->name,
                    'avatar' => '👨🏽‍✈️',
                    'vehicle_model' => $vehicleModel !== '' ? $vehicleModel : 'Maruti Suzuki Ertiga',
                    'vehicle_plate' => $vehiclePlate !== '' ? $vehiclePlate : 'WB 02 '.rand(10, 99).' '.rand(1000, 9999),
                    'fleet_category' => 'suv',
                    'rating' => 4.90,
                    'trips_count' => 0,
                    'wallet_balance' => 0,
                    'is_verified' => true,
                    'is_online' => true,
                ]
            );
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $targetTab = $user->role === 'driver' ? 'bidding' : 'cabs';

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('home')->with('success', "Welcome to Intercity Wheels, {$user->name}! Your account has been registered.");
        }

        return response()->json([
            'success' => true,
            'message' => "Welcome to Intercity Wheels, {$user->name}! Registration completed.",
            'redirect_url' => route('home'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
            ],
            'target_tab' => $targetTab,
        ]);
    }

    /**
     * Dispatch an OTP to a mobile number.
     *
     * P1-5: codes are issued through OtpService (CSPRNG, hashed at rest, TTL,
     * attempt cap, per-phone send throttle). The code is only echoed back in
     * demo mode — i.e. when no SMS gateway is configured — because otherwise
     * the response would hand the login code to whoever asked for it.
     */
    public function sendMobileOtp(Request $request, Fast2SmsService $smsService, OtpService $otpService)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'role' => 'nullable|string|in:customer,driver,admin',
        ]);

        $cleanPhone = $otpService->normalizePhone($validated['phone']);

        if (strlen($cleanPhone) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 10-digit mobile number.',
            ], 422);
        }

        $issued = $otpService->issue($cleanPhone, 'login');

        if ($issued['throttled']) {
            return response()->json([
                'success' => false,
                'code' => 'otp_throttled',
                'message' => 'Too many OTP requests for this number. Try again in '
                    .$issued['retry_after'].' seconds.',
                'retry_after' => $issued['retry_after'],
            ], 429);
        }

        // Attempt SMS dispatch via Fast2SMS
        $smsResult = $smsService->sendOtp($cleanPhone, $issued['otp']);
        $isDemo = ! ($smsResult['sent'] ?? false);

        // Echoing the code back is a demo convenience only. In production the
        // code is delivered by SMS and must never be returned to the caller.
        $echoOtp = $isDemo && (bool) config('demo.enabled');

        $msg = $smsResult['sent']
            ? "OTP sent to +91 {$cleanPhone} via Fast2SMS."
            : ($echoOtp
                ? "OTP for +91 {$cleanPhone} generated: {$issued['otp']} (Demo Mode)"
                : 'We could not reach the SMS gateway right now. Please try again shortly.');

        return response()->json([
            'success' => true,
            'message' => $msg,
            'phone' => $cleanPhone,
            // Never leak a live code once a real gateway is delivering it.
            'otp' => $echoOtp ? $issued['otp'] : null,
            'display_otp' => $echoOtp ? $issued['otp'] : null,
            'expires_in' => $issued['expires_in'],
            'sms_sent' => $smsResult['sent'],
            'is_demo' => $smsResult['is_demo'] ?? true,
            'gateway_note' => $smsResult['message'] ?? null,
        ]);
    }

    /**
     * Verify mobile OTP and authenticate/provision user session.
     */
    public function verifyMobileOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'otp' => 'required|string|min:4|max:8',
            'role' => 'nullable|string|in:customer,driver,admin',
        ]);

        $cleanPhone = $otpService->normalizePhone($validated['phone']);
        $inputOtp = trim($validated['otp']);

        $verification = $otpService->verify($cleanPhone, $inputOtp, 'login');

        if (! $verification['ok']) {
            $messages = [
                'missing' => 'No active OTP for this number. Please request a new one.',
                'expired' => 'This OTP has expired. Please request a new one.',
                'locked' => 'Too many incorrect attempts. Please request a new OTP.',
                'invalid' => 'Invalid OTP. '.$verification['attempts_left'].' attempt(s) remaining.',
            ];

            $status = $verification['reason'] === 'locked' ? 429 : 422;

            return response()->json([
                'success' => false,
                'code' => 'otp_'.$verification['reason'],
                'message' => $messages[$verification['reason']] ?? 'Invalid or expired OTP.',
                'attempts_left' => $verification['attempts_left'],
            ], $status);
        }

        // Look up user by phone or partial phone match
        $user = User::where('phone', $cleanPhone)
            ->orWhere('phone', '+91 '.$cleanPhone)
            ->orWhere('phone', 'like', "%{$cleanPhone}%")
            ->first();

        $role = $validated['role'] ?? 'customer';

        if (! $user) {
            // Auto-provision user account for this phone
            $user = User::create([
                'name' => ($role === 'driver' ? 'Chauffeur ' : 'Customer ').substr($cleanPhone, -4),
                'email' => "user{$cleanPhone}@intercitywheels.com",
                'phone' => "+91 {$cleanPhone}",
                'password' => Hash::make('otp_'.bin2hex(random_bytes(6))),
                'role' => $role,
                'avatar' => $role === 'driver' ? '👨🏽‍✈️' : ($role === 'admin' ? '🛡️' : '👤'),
            ]);
        }

        if ($user->role === 'driver') {
            Driver::firstOrCreate(
                ['phone' => $user->phone],
                [
                    'name' => $user->name,
                    'avatar' => '👨🏽‍✈️',
                    'vehicle_model' => 'Toyota Innova Crysta',
                    'vehicle_plate' => 'WB 02 QX '.rand(1000, 9999),
                    'fleet_category' => 'suv',
                    'rating' => 4.90,
                    'trips_count' => 0,
                    'wallet_balance' => 0,
                    'is_verified' => true,
                    'is_online' => true,
                ]
            );
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('home')->with('success', "Welcome, {$user->name}!");
        }

        return response()->json([
            'success' => true,
            'message' => "Welcome, {$user->name}! Mobile authentication verified.",
            'redirect_url' => route('home'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
            ],
            'target_tab' => $targetTab,
        ]);
    }

    /**
     * Instant 1-click demo login for rapid role testing.
     */
    public function quickDemoLogin(Request $request)
    {
        $role = $request->input('role', 'customer');

        $demoEmails = [
            'admin' => 'admin@intercitywheels.com',
            'driver' => 'driver@intercitywheels.com',
            'customer' => 'customer@intercitywheels.com',
        ];

        $targetEmail = $demoEmails[$role] ?? 'customer@intercitywheels.com';
        $user = User::where('email', $targetEmail)->first();

        if (! $user) {
            $user = User::create([
                'name' => ucfirst($role).' User',
                'email' => $targetEmail,
                'password' => Hash::make('password123'),
                'role' => $role,
                'phone' => '+91 98000 00000',
                'avatar' => $role === 'admin' ? '🛡️' : ($role === 'driver' ? '👨🏽‍✈️' : '👤'),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $redirectTo = $this->safeNext($request) ?? route('home');

        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect($redirectTo)->with('success', 'Logged in as '.ucfirst($user->role));
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged in as '.ucfirst($user->role)." ({$user->name})",
            'redirect_url' => $redirectTo,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
            ],
            'target_tab' => $targetTab,
        ]);
    }

    /**
     * Log user out and invalidate session.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out.',
                'redirect_url' => route('home'),
            ]);
        }

        return redirect()->route('home')->with('info', 'Successfully signed out.');
    }

    /**
     * Only accept an in-app relative path as a post-login destination.
     *
     * The bidding page sends drivers here as /?next=/bid/{token}. Anything with
     * a host, a scheme, a protocol-relative prefix or control characters is
     * ignored, so this can never become an open redirect.
     */
    private function safeNext(Request $request): ?string
    {
        $next = (string) $request->input('next', '');

        if ($next === '') {
            return null;
        }

        if (! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return null;
        }

        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $next) === 1) {
            return null;
        }

        return $next;
    }

    /**
     * Get active authenticated user state.
     */
    public function current(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'authenticated' => false,
                'user' => null,
            ]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
            ],
        ]);
    }

    /**
     * Get alternate login client configuration (Firebase / Clerk).
     */
    public function alternateConfig(AlternateAuthService $authService)
    {
        return response()->json([
            'success' => true,
            'config' => $authService->getPublicConfig(),
        ]);
    }

    /**
     * Authenticate or register using Firebase credentials.
     */
    public function firebaseLogin(Request $request, AlternateAuthService $authService)
    {
        $validated = $request->validate([
            'email' => 'nullable|email',
            'firebase_id' => 'nullable|string',
            'id_token' => 'nullable|string',
            'uid' => 'nullable|string',
            'name' => 'nullable|string|max:100',
            'photo_url' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|in:customer,driver,admin',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:30',
        ]);

        if (empty($validated['email']) && empty($validated['firebase_id']) && empty($validated['uid']) && empty($validated['id_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide an email or Firebase ID.',
            ], 422);
        }

        $result = $authService->authenticateWithFirebase($validated);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('home')->with('success', $result['message']);
        }

        return response()->json($result);
    }

    /**
     * Authenticate or register using Clerk credentials.
     */
    public function clerkLogin(Request $request, AlternateAuthService $authService)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'nullable|string',
            'session_id' => 'nullable|string',
            'clerk_user_id' => 'nullable|string',
            'name' => 'nullable|string|max:100',
            'avatar' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|string|in:customer,driver,admin',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:30',
        ]);

        $result = $authService->authenticateWithClerk($validated);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('home')->with('success', $result['message']);
        }

        return response()->json($result);
    }

    /**
     * Unified alternate login handler supporting both providers.
     */
    public function alternateLogin(Request $request, AlternateAuthService $authService)
    {
        $provider = strtolower(trim($request->input('provider', 'firebase')));

        if ($provider === 'clerk') {
            return $this->clerkLogin($request, $authService);
        }

        return $this->firebaseLogin($request, $authService);
    }
}

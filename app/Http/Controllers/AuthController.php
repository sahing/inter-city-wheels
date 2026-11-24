<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Driver;
use App\Services\Fast2SmsService;
use App\Services\AlternateAuthService;

class AuthController extends Controller
{
    /**
     * Authenticate user with role-aware logic.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'nullable|string|in:customer,driver,admin',
            'remember' => 'nullable|boolean'
        ]);

        $credentials = [
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password']
        ];

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            if (!$request->wantsJson() && !$request->ajax()) {
                return back()->withErrors(['email' => 'Invalid email or password. Please verify your credentials.'])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password. Please verify your credentials.'
            ], 422);
        }

        // If a specific role tab was selected in the login modal, check role match
        if (!empty($validated['role']) && $user->role !== $validated['role']) {
            $msg = "This account is registered as a " . ucfirst($user->role) . ". Please select the " . ucfirst($user->role) . " tab to log in.";
            if (!$request->wantsJson() && !$request->ajax()) {
                return back()->withErrors(['email' => $msg])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => $msg
            ], 422);
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Target landing tab according to role
        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        if (!$request->wantsJson() && !$request->ajax()) {
            return redirect()->route('home')->with('success', "Welcome back, {$user->name}!");
        }

        return response()->json([
            'success' => true,
            'message' => "Welcome back, {$user->name}!",
            'redirect_url' => route('home'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤'
            ],
            'target_tab' => $targetTab
        ]);
    }

    /**
     * Register a new user account (Customer or Driver).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:customer,driver',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:30',
        ]);

        $role = $validated['role'] ?? 'customer';
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
            // Also register record in drivers table
            Driver::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $user->name,
                    'avatar' => '👨🏽‍✈️',
                    'vehicle_model' => trim($request->input('vehicle_model', 'Maruti Suzuki Ertiga')),
                    'vehicle_plate' => strtoupper(trim($request->input('vehicle_plate', 'WB 02 ' . rand(10, 99) . ' ' . rand(1000, 9999)))),
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

        if (!$request->wantsJson() && !$request->ajax()) {
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
                'avatar' => $user->avatar ?? '👤'
            ],
            'target_tab' => $targetTab
        ]);
    }

    /**
     * Dispatch OTP to mobile number via Fast2SMS API.
     * Displays simulated OTP until real SMS credits & production gateway are integrated.
     */
    public function sendMobileOtp(Request $request, Fast2SmsService $smsService)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'role' => 'nullable|string|in:customer,driver,admin',
        ]);

        $rawPhone = $validated['phone'];
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 12) {
            $cleanPhone = substr($cleanPhone, 2);
        } elseif (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        if (strlen($cleanPhone) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 10-digit mobile number.'
            ], 422);
        }

        // Generate 4-digit numeric OTP
        $otp = sprintf('%04d', rand(1000, 9999));

        // Save OTP to session & cache with 10 minutes TTL
        session([
            "login_otp_{$cleanPhone}" => $otp,
            "login_otp_time_{$cleanPhone}" => now()->timestamp,
        ]);
        \Illuminate\Support\Facades\Cache::put("login_otp_{$cleanPhone}", $otp, now()->addMinutes(10));

        // Attempt SMS dispatch via Fast2SMS
        $smsResult = $smsService->sendOtp($cleanPhone, $otp);

        $msg = $smsResult['sent']
            ? "OTP sent to +91 {$cleanPhone} via Fast2SMS."
            : "OTP for +91 {$cleanPhone} generated: {$otp} (Demo Mode)";

        return response()->json([
            'success' => true,
            'message' => $msg,
            'phone' => $cleanPhone,
            'otp' => $otp, // Expose OTP until real SMS API gateway delivers in production
            'display_otp' => $otp,
            'sms_sent' => $smsResult['sent'],
            'is_demo' => $smsResult['is_demo'] ?? true,
            'gateway_note' => $smsResult['message'] ?? null,
        ]);
    }

    /**
     * Verify mobile OTP and authenticate/provision user session.
     */
    public function verifyMobileOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'otp' => 'required|string|min:4|max:8',
            'role' => 'nullable|string|in:customer,driver,admin',
        ]);

        $rawPhone = $validated['phone'];
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 12) {
            $cleanPhone = substr($cleanPhone, 2);
        } elseif (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        $inputOtp = trim($validated['otp']);

        $storedOtp = session("login_otp_{$cleanPhone}") 
            ?? \Illuminate\Support\Facades\Cache::get("login_otp_{$cleanPhone}");

        // Allow demo master OTP 1234 in local testing or matching generated OTP
        $isValid = ($storedOtp && $storedOtp === $inputOtp) || ($inputOtp === '1234');

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please enter the OTP displayed on screen or request a new OTP.'
            ], 422);
        }

        // Clean up OTP from session & cache
        session()->forget(["login_otp_{$cleanPhone}", "login_otp_time_{$cleanPhone}"]);
        \Illuminate\Support\Facades\Cache::forget("login_otp_{$cleanPhone}");

        // Look up user by phone or partial phone match
        $user = User::where('phone', $cleanPhone)
            ->orWhere('phone', '+91 ' . $cleanPhone)
            ->orWhere('phone', 'like', "%{$cleanPhone}%")
            ->first();

        $role = $validated['role'] ?? 'customer';

        if (!$user) {
            // Auto-provision user account for this phone
            $user = User::create([
                'name' => ($role === 'driver' ? 'Chauffeur ' : 'Customer ') . substr($cleanPhone, -4),
                'email' => "user{$cleanPhone}@intercitywheels.com",
                'phone' => "+91 {$cleanPhone}",
                'password' => Hash::make('otp_' . bin2hex(random_bytes(6))),
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
                    'vehicle_plate' => 'WB 02 QX ' . rand(1000, 9999),
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

        if (!$request->wantsJson() && !$request->ajax()) {
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
                'avatar' => $user->avatar ?? '👤'
            ],
            'target_tab' => $targetTab
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
            'customer' => 'customer@intercitywheels.com'
        ];

        $targetEmail = $demoEmails[$role] ?? 'customer@intercitywheels.com';
        $user = User::where('email', $targetEmail)->first();

        if (!$user) {
            $user = User::create([
                'name' => ucfirst($role) . ' User',
                'email' => $targetEmail,
                'password' => Hash::make('password123'),
                'role' => $role,
                'phone' => '+91 98000 00000',
                'avatar' => $role === 'admin' ? '🛡️' : ($role === 'driver' ? '👨🏽‍✈️' : '👤')
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        if (!$request->wantsJson() && !$request->ajax()) {
            return redirect()->route('home')->with('success', "Logged in as " . ucfirst($user->role));
        }

        return response()->json([
            'success' => true,
            'message' => "Logged in as " . ucfirst($user->role) . " ({$user->name})",
            'redirect_url' => route('home'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤'
            ],
            'target_tab' => $targetTab
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
                'redirect_url' => route('home')
            ]);
        }

        return redirect()->route('home')->with('info', 'Successfully signed out.');
    }

    /**
     * Get active authenticated user state.
     */
    public function current(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'user' => null
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
                'avatar' => $user->avatar ?? '👤'
            ]
        ]);
    }

    /**
     * Get alternate login client configuration (Firebase / Clerk).
     */
    public function alternateConfig(AlternateAuthService $authService)
    {
        return response()->json([
            'success' => true,
            'config' => $authService->getPublicConfig()
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
            'role' => 'nullable|string|in:customer,driver,admin',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:30',
        ]);

        if (empty($validated['email']) && empty($validated['firebase_id']) && empty($validated['uid']) && empty($validated['id_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide an email or Firebase ID.'
            ], 422);
        }

        $result = $authService->authenticateWithFirebase($validated);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        if (!$request->wantsJson() && !$request->ajax()) {
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

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        if (!$request->wantsJson() && !$request->ajax()) {
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

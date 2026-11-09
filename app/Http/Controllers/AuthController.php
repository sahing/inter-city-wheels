<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
}

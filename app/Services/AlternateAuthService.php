<?php

namespace App\Services;

use App\Models\User;
use App\Models\Driver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlternateAuthService
{
    /**
     * Retrieve public configuration for Firebase and Clerk to initialize client-side SDKs.
     */
    public function getPublicConfig(): array
    {
        $firebaseConfig = config('services.firebase');
        $clerkConfig = config('services.clerk');

        $isFirebaseConfigured = !empty($firebaseConfig['api_key']) && !empty($firebaseConfig['project_id']);
        $isClerkConfigured = !empty($clerkConfig['publishable_key']);

        return [
            'firebase' => [
                'apiKey' => $firebaseConfig['api_key'] ?? '',
                'authDomain' => $firebaseConfig['auth_domain'] ?? '',
                'projectId' => $firebaseConfig['project_id'] ?? '',
                'storageBucket' => $firebaseConfig['storage_bucket'] ?? '',
                'messagingSenderId' => $firebaseConfig['messaging_sender_id'] ?? '',
                'appId' => $firebaseConfig['app_id'] ?? '',
                'measurementId' => $firebaseConfig['measurement_id'] ?? '',
                'is_configured' => $isFirebaseConfigured,
            ],
            'clerk' => [
                'publishableKey' => $clerkConfig['publishable_key'] ?? '',
                'is_configured' => $isClerkConfigured,
            ]
        ];
    }

    /**
     * Authenticate or register a user using Firebase credentials / token / Firebase ID.
     */
    public function authenticateWithFirebase(array $payload): array
    {
        $firebaseId = trim($payload['firebase_id'] ?? $payload['uid'] ?? '');
        $email = strtolower(trim($payload['email'] ?? ''));

        // If email is missing, resolve by existing user having this Firebase ID
        if (empty($email) && !empty($firebaseId)) {
            $matchedUser = User::findByFirebaseId($firebaseId);
            if ($matchedUser) {
                $email = $matchedUser->email;
            } else {
                $email = "firebase_{$firebaseId}@intercitywheels.com";
            }
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'A valid email or Firebase ID is required for authentication.'
            ];
        }

        $uid = $firebaseId ?: ($payload['id_token'] ? substr(md5($payload['id_token']), 0, 24) : 'fb_' . bin2hex(random_bytes(6)));
        $name = trim($payload['name'] ?? '') ?: explode('@', $email)[0];
        $role = in_array($payload['role'] ?? '', ['customer', 'driver', 'admin']) ? $payload['role'] : 'customer';
        $phone = !empty($payload['phone']) ? trim($payload['phone']) : null;
        $avatar = !empty($payload['photo_url']) ? $payload['photo_url'] : ($role === 'driver' ? '👨🏽‍✈️' : '👤');

        // Optional token verification if Google / Firebase project is active & token provided
        $projectId = config('services.firebase.project_id');
        if (!empty($payload['id_token']) && !empty($projectId) && !app()->environment('testing')) {
            try {
                $verifyRes = Http::timeout(4)->get("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($payload['id_token']));
                if ($verifyRes->successful()) {
                    $tokenData = $verifyRes->json();
                    if (!empty($tokenData['email'])) {
                        $email = strtolower(trim($tokenData['email']));
                    }
                    if (!empty($tokenData['sub'])) {
                        $uid = $tokenData['sub'];
                    }
                    if (!empty($tokenData['name']) && empty($payload['name'])) {
                        $name = $tokenData['name'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Firebase / Google token verification warning: ' . $e->getMessage());
            }
        }

        return $this->provisionAndLogin([
            'provider' => 'firebase',
            'provider_id' => $uid,
            'firebase_id' => $uid,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'phone' => $phone,
            'avatar' => $avatar,
            'vehicle_model' => $payload['vehicle_model'] ?? null,
            'vehicle_plate' => $payload['vehicle_plate'] ?? null,
        ]);
    }

    /**
     * Authenticate or register a user using Clerk credentials / token.
     */
    public function authenticateWithClerk(array $payload): array
    {
        $email = strtolower(trim($payload['email'] ?? ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'A valid email address is required from Clerk authentication.'
            ];
        }

        $clerkUserId = trim($payload['clerk_user_id'] ?? ($payload['token'] ? 'user_' . substr(md5($payload['token']), 0, 20) : 'clerk_' . bin2hex(random_bytes(6))));
        $name = trim($payload['name'] ?? '') ?: explode('@', $email)[0];
        $role = in_array($payload['role'] ?? '', ['customer', 'driver', 'admin']) ? $payload['role'] : 'customer';
        $phone = !empty($payload['phone']) ? trim($payload['phone']) : null;
        $avatar = !empty($payload['avatar']) ? $payload['avatar'] : ($role === 'driver' ? '👨🏽‍✈️' : '👤');

        // Optional Clerk backend verification if secret key is configured
        $secretKey = config('services.clerk.secret_key');
        if (!empty($payload['session_id']) && !empty($secretKey) && !app()->environment('testing')) {
            try {
                $clerkRes = Http::withToken($secretKey)
                    ->timeout(4)
                    ->get("https://api.clerk.com/v1/sessions/" . urlencode($payload['session_id']));
                if ($clerkRes->successful()) {
                    $sessionData = $clerkRes->json();
                    if (!empty($sessionData['user_id'])) {
                        $clerkUserId = $sessionData['user_id'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Clerk token verification warning: ' . $e->getMessage());
            }
        }

        return $this->provisionAndLogin([
            'provider' => 'clerk',
            'provider_id' => $clerkUserId,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'phone' => $phone,
            'avatar' => $avatar,
            'vehicle_model' => $payload['vehicle_model'] ?? null,
            'vehicle_plate' => $payload['vehicle_plate'] ?? null,
        ]);
    }

    /**
     * Provision or locate user record and establish Laravel Auth session.
     */
    protected function provisionAndLogin(array $userData): array
    {
        $email = $userData['email'];
        $provider = $userData['provider'];
        $providerId = $userData['provider_id'];
        $firebaseId = $userData['firebase_id'] ?? ($provider === 'firebase' ? $providerId : null);

        // 1. Look up existing user by Firebase ID, provider ID or email
        $user = null;
        if (!empty($firebaseId)) {
            $user = User::findByFirebaseId($firebaseId);
        }

        if (!$user) {
            $user = User::where('auth_provider', $provider)
                ->where('auth_provider_id', $providerId)
                ->first();
        }

        if (!$user && !empty($email)) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Update auth provider information if not linked
            $user->auth_provider = $provider;
            if ($provider === 'firebase' && empty($user->firebase_id)) {
                $user->firebase_id = $firebaseId;
            }
            if (empty($user->auth_provider_id)) {
                $user->auth_provider_id = $providerId;
            }
            if (empty($user->phone) && !empty($userData['phone'])) {
                $user->phone = $userData['phone'];
            }
            if ((empty($user->avatar) || $user->avatar === '👤') && !empty($userData['avatar'])) {
                $user->avatar = $userData['avatar'];
            }
            $user->save();
        } else {
            // Auto-provision new user
            $role = $userData['role'];
            $phone = $userData['phone'] ?? '+91 ' . rand(7000000000, 9999999999);

            $user = User::create([
                'name' => $userData['name'],
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($provider . '_' . bin2hex(random_bytes(10))),
                'role' => $role,
                'avatar' => $userData['avatar'],
                'auth_provider' => $provider,
                'auth_provider_id' => $providerId,
                'firebase_id' => $firebaseId,
            ]);

            // If registering as driver, ensure driver record exists
            if ($role === 'driver') {
                Driver::firstOrCreate(
                    ['phone' => $phone],
                    [
                        'name' => $user->name,
                        'avatar' => '👨🏽‍✈️',
                        'vehicle_model' => $userData['vehicle_model'] ?? 'Toyota Innova Crysta',
                        'vehicle_plate' => $userData['vehicle_plate'] ?? ('WB 02 ' . rand(10, 99) . ' ' . rand(1000, 9999)),
                        'fleet_category' => 'suv',
                        'rating' => 4.90,
                        'trips_count' => 0,
                        'wallet_balance' => 0,
                        'is_verified' => true,
                        'is_online' => true,
                    ]
                );
            }
        }

        // Establish Laravel Auth session
        Auth::login($user, true);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $targetTab = match ($user->role) {
            'admin' => 'admin',
            'driver' => 'bidding',
            default => 'cabs'
        };

        $providerLabel = $provider === 'firebase' ? 'Firebase' : 'Clerk';

        return [
            'success' => true,
            'message' => "Welcome to Intercity Wheels, {$user->name}! Signed in via {$providerLabel}.",
            'redirect_url' => route('home'),
            'provider' => $provider,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? '👤',
                'auth_provider' => $user->auth_provider,
                'firebase_id' => $user->firebase_id,
            ],
            'target_tab' => $targetTab,
        ];
    }
}

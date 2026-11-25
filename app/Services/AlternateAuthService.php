<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
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

        $isFirebaseConfigured = ! empty($firebaseConfig['api_key']) && ! empty($firebaseConfig['project_id']);
        $isClerkConfigured = ! empty($clerkConfig['publishable_key']);

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
            ],
        ];
    }

    /**
     * Authenticate or register a user using Firebase credentials / token / Firebase ID.
     *
     * A supplied ID token is verified before anything is trusted, and the token
     * wins over every client-supplied claim. Without a token the caller may only
     * sign in when `services.firebase.allow_id_only` is enabled (local demos);
     * otherwise the request is refused, because a posted `firebase_id` proves
     * nothing and would let anyone claim any account.
     */
    public function authenticateWithFirebase(array $payload): array
    {
        $idToken = trim((string) ($payload['id_token'] ?? ''));
        $claimedUid = trim((string) ($payload['firebase_id'] ?? $payload['uid'] ?? ''));
        $claimedEmail = strtolower(trim((string) ($payload['email'] ?? '')));

        $verified = null;

        if ($idToken !== '') {
            $verified = $this->verifyFirebaseToken($idToken);

            if (isset($verified['error'])) {
                return ['success' => false, 'message' => $verified['error']];
            }
        } elseif (! $this->idOnlyLoginAllowed()) {
            return [
                'success' => false,
                'message' => 'A verified Firebase sign-in is required. Please sign in with Google again.',
            ];
        }

        // A verified token is authoritative; client claims fill the gaps only.
        if ($verified !== null) {
            $uid = $verified['sub'];
            $email = $verified['email'] !== '' ? $verified['email'] : $claimedEmail;
            $name = $verified['name'] !== '' ? $verified['name'] : trim((string) ($payload['name'] ?? ''));
            $avatar = $verified['picture'] !== ''
                ? $verified['picture']
                : ($payload['photo_url'] ?? '');
        } else {
            $uid = $claimedUid;
            $email = $claimedEmail;
            $name = trim((string) ($payload['name'] ?? ''));
            $avatar = (string) ($payload['photo_url'] ?? '');
        }

        // If email is missing, resolve by existing user having this Firebase ID
        if (empty($email) && ! empty($uid)) {
            $matchedUser = User::findByFirebaseId($uid);
            $email = $matchedUser
                ? $matchedUser->email
                : "firebase_{$uid}@intercitywheels.com";
        }

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'A valid email or Firebase ID is required for authentication.',
            ];
        }

        $uid = $uid ?: 'fb_'.bin2hex(random_bytes(6));
        $name = $name ?: explode('@', $email)[0];

        $requestedRole = (string) ($payload['role'] ?? '');
        $role = in_array($requestedRole, ['customer', 'driver', 'admin'], true) ? $requestedRole : 'customer';

        // A verified Google identity can never mint a new admin account; admin
        // users are created by an existing admin. The local demo keeps its
        // "sign in as admin" button because it runs with allow_id_only.
        if ($role === 'admin' && ! $this->idOnlyLoginAllowed()) {
            $role = 'customer';
        }

        $phone = ! empty($payload['phone']) ? trim($payload['phone']) : null;
        $avatar = $avatar !== '' ? $avatar : ($role === 'driver' ? '👨🏽‍✈️' : '👤');

        return $this->provisionAndLogin([
            'provider' => 'firebase',
            'provider_id' => $uid,
            'firebase_id' => $uid,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'phone' => $phone,
            'avatar' => $avatar,
            'password' => $payload['password'] ?? null,
            'vehicle_model' => $payload['vehicle_model'] ?? null,
            'vehicle_plate' => $payload['vehicle_plate'] ?? null,
        ]);
    }

    /**
     * Is a signed-in identity without an ID token acceptable here?
     */
    protected function idOnlyLoginAllowed(): bool
    {
        return (bool) config('services.firebase.allow_id_only', false);
    }

    /**
     * Verify a Firebase ID token against Google and bind it to this project.
     *
     * Google's tokeninfo endpoint validates the signature and expiry and echoes
     * the claims; this method then enforces the parts tokeninfo does not know
     * about us: the issuer, the audience (our project's OAuth client), expiry
     * with clock tolerance and a verified email.
     *
     * @return array{sub: string, email: string, name: string, picture: string}|array{error: string}
     */
    protected function verifyFirebaseToken(string $idToken): array
    {
        $projectId = trim((string) config('services.firebase.project_id', ''));
        $expectedClientId = trim((string) config('services.firebase.client_id', ''));
        $tokeninfoUrl = (string) config('services.firebase.tokeninfo_url', 'https://oauth2.googleapis.com/tokeninfo');

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get($tokeninfoUrl, ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            Log::warning('Firebase token verification could not reach Google: '.$e->getMessage());

            return ['error' => 'We could not reach Google to verify your sign-in. Please try again.'];
        }

        if (! $response->successful()) {
            Log::info('Firebase token rejected by Google with HTTP '.$response->status());

            return ['error' => 'Your Google sign-in could not be verified. Please sign in again.'];
        }

        $data = $response->json();

        if (! is_array($data)) {
            return ['error' => 'Your Google sign-in could not be verified. Please sign in again.'];
        }

        $issuer = (string) ($data['iss'] ?? '');
        $issuers = ['https://accounts.google.com', 'accounts.google.com'];

        if ($projectId !== '') {
            $issuers[] = "https://securetoken.google.com/{$projectId}";
        }

        if (! in_array($issuer, $issuers, true)) {
            Log::warning('Firebase token issuer rejected: '.$issuer);

            return ['error' => 'That sign-in token was not issued by Google for this app.'];
        }

        $audience = (string) ($data['aud'] ?? '');

        if ($audience === '') {
            return ['error' => 'Your Google sign-in token is missing its audience.'];
        }

        if ($expectedClientId !== '') {
            if ($audience !== $expectedClientId) {
                Log::warning('Firebase token audience mismatch: '.$audience);

                return ['error' => 'That Google sign-in belongs to a different app configuration.'];
            }
        } elseif ($projectId !== '' && $audience !== $projectId && ! str_starts_with($audience, $projectId.'-')) {
            Log::warning('Firebase token audience is outside project '.$projectId.': '.$audience);

            return ['error' => 'That Google sign-in belongs to a different Firebase project.'];
        }

        $expiresAt = (int) ($data['exp'] ?? 0);

        if ($expiresAt > 0 && $expiresAt < time() - 30) {
            return ['error' => 'Your Google sign-in has expired. Please sign in again.'];
        }

        $sub = trim((string) ($data['sub'] ?? ''));

        if ($sub === '') {
            return ['error' => 'Your Google sign-in token has no subject claim.'];
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $emailVerified = filter_var($data['email_verified'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($email !== '' && $emailVerified === false) {
            return ['error' => 'Your Google email address is not verified.'];
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => trim((string) ($data['name'] ?? '')),
            'picture' => trim((string) ($data['picture'] ?? '')),
        ];
    }

    /**
     * Authenticate or register a user using Clerk credentials / token.
     */
    public function authenticateWithClerk(array $payload): array
    {
        $email = strtolower(trim($payload['email'] ?? ''));
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'A valid email address is required from Clerk authentication.',
            ];
        }

        $clerkUserId = trim($payload['clerk_user_id'] ?? ($payload['token'] ? 'user_'.substr(md5($payload['token']), 0, 20) : 'clerk_'.bin2hex(random_bytes(6))));
        $name = trim($payload['name'] ?? '') ?: explode('@', $email)[0];
        $role = in_array($payload['role'] ?? '', ['customer', 'driver', 'admin']) ? $payload['role'] : 'customer';
        $phone = ! empty($payload['phone']) ? trim($payload['phone']) : null;
        $avatar = ! empty($payload['avatar']) ? $payload['avatar'] : ($role === 'driver' ? '👨🏽‍✈️' : '👤');

        // Optional Clerk backend verification if secret key is configured
        $secretKey = config('services.clerk.secret_key');
        if (! empty($payload['session_id']) && ! empty($secretKey) && ! app()->environment('testing')) {
            try {
                $clerkRes = Http::withToken($secretKey)
                    ->timeout(4)
                    ->get('https://api.clerk.com/v1/sessions/'.urlencode($payload['session_id']));
                if ($clerkRes->successful()) {
                    $sessionData = $clerkRes->json();
                    if (! empty($sessionData['user_id'])) {
                        $clerkUserId = $sessionData['user_id'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Clerk token verification warning: '.$e->getMessage());
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
        if (! empty($firebaseId)) {
            $user = User::findByFirebaseId($firebaseId);
        }

        if (! $user) {
            $user = User::where('auth_provider', $provider)
                ->where('auth_provider_id', $providerId)
                ->first();
        }

        if (! $user && ! empty($email)) {
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
            if (empty($user->phone) && ! empty($userData['phone'])) {
                $user->phone = $userData['phone'];
            }
            if ((empty($user->avatar) || $user->avatar === '👤') && ! empty($userData['avatar'])) {
                $user->avatar = $userData['avatar'];
            }
            if (! empty($userData['password'])) {
                $user->password = Hash::make($userData['password']);
            }
            $user->save();
        } else {
            // Auto-provision new user in local database with usable credentials
            $role = $userData['role'];
            $phone = $userData['phone'] ?? '+91 '.rand(7000000000, 9999999999);
            $rawPassword = ! empty($userData['password']) ? trim($userData['password']) : 'password123';

            $user = User::create([
                'name' => $userData['name'],
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($rawPassword),
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
                        'vehicle_plate' => $userData['vehicle_plate'] ?? ('WB 02 '.rand(10, 99).' '.rand(1000, 9999)),
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

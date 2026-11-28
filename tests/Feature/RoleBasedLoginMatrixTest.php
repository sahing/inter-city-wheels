<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role-based sign-in matrix — customer / driver / admin.
 *
 * Proves, over real HTTP through the middleware stack:
 *   - every seeded role signs in, lands on its own tab, and knows its own role;
 *   - a role tab never authenticates an account that belongs to another role;
 *   - a brand-new account created during the run (self-registration and
 *     admin-provisioned) logs in exactly like a seeded one;
 *   - logging out returns the session to guest and privileged endpoints drop
 *     back to 401;
 *   - the role guard answers 401 (guest) / 403 (wrong role) / 200 (right role)
 *     for each role, so the matrix is asserted in both directions.
 */
class RoleBasedLoginMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Seeded demo account per role, with the landing tab that role must receive. */
    private const SEEDED = [
        'customer' => ['email' => 'customer@intercitywheels.com', 'tab' => 'cabs'],
        'driver' => ['email' => 'driver@intercitywheels.com', 'tab' => 'bidding'],
        'admin' => ['email' => 'admin@intercitywheels.com', 'tab' => 'admin'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSetting::set('enable_schedules_and_berths', '1');
    }

    private function user(string $role): User
    {
        $user = User::where('role', $role)->first();
        $this->assertNotNull($user, "expected a seeded {$role} account");

        return $user;
    }

    private function logoutIfAuthenticated(): void
    {
        if (auth()->check()) {
            $this->postJson('/logout')->assertStatus(200);
        }
    }

    // ------------------------------------------------------------------
    // Sign-in per role
    // ------------------------------------------------------------------

    public function test_each_role_can_log_in_and_lands_on_its_own_tab(): void
    {
        foreach (self::SEEDED as $role => $account) {
            $this->logoutIfAuthenticated();

            $response = $this->postJson('/login', [
                'email' => $account['email'],
                'password' => 'password123',
                'role' => $role,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('user.role', $role)
                ->assertJsonPath('target_tab', $account['tab']);

            $this->assertAuthenticated();
            $this->assertSame($role, auth()->user()->role, "session role for {$role}");
            $this->assertSame($account['email'], auth()->user()->email);
        }
    }

    public function test_every_role_rejects_a_login_tab_that_belongs_to_another_role(): void
    {
        $tabs = array_keys(self::SEEDED);

        foreach ($tabs as $actualRole) {
            foreach ($tabs as $selectedTab) {
                if ($actualRole === $selectedTab) {
                    continue;
                }

                $response = $this->postJson('/login', [
                    'email' => self::SEEDED[$actualRole]['email'],
                    'password' => 'password123',
                    'role' => $selectedTab,
                ]);

                $response->assertStatus(422)
                    ->assertJsonPath('success', false);

                $this->assertStringContainsString(
                    'registered as a '.ucfirst($actualRole),
                    (string) $response->json('message'),
                    "{$actualRole} account must be refused on the {$selectedTab} tab"
                );

                $this->assertGuest();
            }
        }
    }

    public function test_wrong_password_is_rejected_for_every_role(): void
    {
        foreach (self::SEEDED as $role => $account) {
            $this->logoutIfAuthenticated();

            $this->postJson('/login', [
                'email' => $account['email'],
                'password' => 'definitely-not-the-password',
                'role' => $role,
            ])->assertStatus(422)->assertJsonPath('success', false);

            $this->assertGuest();
        }
    }

    // ------------------------------------------------------------------
    // Newly created users
    // ------------------------------------------------------------------

    public function test_a_newly_registered_customer_can_log_out_and_log_back_in(): void
    {
        $email = 'brand.new.customer@example.com';

        $this->postJson('/register', [
            'name' => 'Brand New Customer',
            'email' => $email,
            'phone' => '9820011122',
            'password' => 'freshpass123',
            'role' => 'customer',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('target_tab', 'cabs');

        $this->assertAuthenticated();

        // The new account is usable straight away.
        $this->getJson('/api/auth/user')->assertStatus(200)
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.role', 'customer');

        $this->postJson('/logout')->assertStatus(200)->assertJsonPath('success', true);
        $this->assertGuest();
        $this->getJson('/api/auth/user')->assertStatus(401);

        // And it signs back in through the customer tab.
        $this->postJson('/login', [
            'email' => $email,
            'password' => 'freshpass123',
            'role' => 'customer',
        ])->assertStatus(200)
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('target_tab', 'cabs');

        $this->assertAuthenticated();
    }

    public function test_admin_can_create_a_new_customer_who_then_logs_in(): void
    {
        $email = 'minted.customer@example.com';

        $create = $this->actingAs($this->user('admin'))->postJson('/api/admin/users', [
            'name' => 'Minted Customer',
            'email' => $email,
            'phone' => '9830044556',
            'role' => 'customer',
            'password' => 'mintedpass123',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('user.email', $email);

        $this->assertDatabaseHas('users', ['email' => $email, 'role' => 'customer']);

        $this->postJson('/logout')->assertStatus(200);

        $this->postJson('/login', [
            'email' => $email,
            'password' => 'mintedpass123',
            'role' => 'customer',
        ])->assertStatus(200)
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('target_tab', 'cabs');

        $this->assertAuthenticated();
        $this->assertSame($email, auth()->user()->email);
    }

    public function test_admin_can_create_a_new_driver_who_lands_on_the_bidding_tab(): void
    {
        $email = 'minted.driver@example.com';

        $this->actingAs($this->user('admin'))->postJson('/api/admin/users', [
            'name' => 'Minted Driver',
            'email' => $email,
            'phone' => '9830055667',
            'role' => 'driver',
            'password' => 'mintedpass456',
            'vehicle_model' => 'Toyota Innova Crysta',
            'vehicle_plate' => 'WB 06 MN 4321',
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'driver');

        $this->assertDatabaseHas('drivers', [
            'vehicle_plate' => 'WB 06 MN 4321',
            'vehicle_model' => 'Toyota Innova Crysta',
        ]);

        $this->postJson('/logout')->assertStatus(200);

        $this->postJson('/login', [
            'email' => $email,
            'password' => 'mintedpass456',
            'role' => 'driver',
        ])->assertStatus(200)
            ->assertJsonPath('user.role', 'driver')
            ->assertJsonPath('target_tab', 'bidding');

        $this->assertAuthenticated();

        // A freshly created driver reaches the driver console.
        $this->getJson('/api/bids')->assertStatus(200)->assertJsonPath('success', true);
    }

    // ------------------------------------------------------------------
    // Role guard, asserted in both directions
    // ------------------------------------------------------------------

    public function test_guests_are_refused_on_every_privileged_endpoint(): void
    {
        $this->assertGuest();

        // The `auth` middleware runs first, so guests get the framework 401 on
        // every privileged route and no role name is revealed to them.
        foreach (['/api/auth/user', '/api/admin/users', '/api/bids', '/api/driver/profile'] as $uri) {
            $response = $this->getJson($uri)->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');

            $this->assertStringNotContainsString('Required role', $response->getContent());
            $this->assertStringNotContainsString('admin|', $response->getContent());
        }
    }

    public function test_admin_directory_is_reachable_only_by_admin(): void
    {
        $this->actingAs($this->user('admin'))
            ->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        foreach (['customer', 'driver'] as $role) {
            $this->actingAs($this->user($role))
                ->getJson('/api/admin/users')
                ->assertStatus(403)
                ->assertJsonPath('message', 'Forbidden. Required role: admin');
        }
    }

    public function test_driver_console_admits_driver_and_admin_but_refuses_customer(): void
    {
        $this->actingAs($this->user('driver'))
            ->getJson('/api/bids')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($this->user('admin'))
            ->getJson('/api/bids')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($this->user('customer'))
            ->getJson('/api/bids')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Required role: driver|admin');
    }

    public function test_bid_acceptance_belongs_to_customer_and_admin_not_driver(): void
    {
        // Customer clears the role guard and is stopped only by validation.
        $this->actingAs($this->user('customer'))
            ->postJson('/api/bids/accept', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['driver_id', 'amount', 'route', 'customer_name', 'customer_phone']);

        // Driver is refused by the role guard, before any validation runs.
        $this->actingAs($this->user('driver'))
            ->postJson('/api/bids/accept', [])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Required role: customer|admin');
    }
}

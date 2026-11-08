<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Driver;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_list_all_registered_users(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/users');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'users' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'phone'
                ]
            ]
        ]);
    }

    public function test_admin_can_register_new_driver_partner(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $payload = [
            'name' => 'Sanjay Manjrekar',
            'email' => 'sanjay.driver@intercitywheels.com',
            'phone' => '+91 98111 22334',
            'role' => 'driver',
            'password' => 'secret123',
            'vehicle_model' => 'Toyota Fortuner',
            'vehicle_plate' => 'MH 14 CC 9999',
            'fleet_category' => 'suv'
        ];

        $response = $this->actingAs($admin)->postJson('/api/admin/users', $payload);
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'user' => [
                'name' => 'Sanjay Manjrekar',
                'email' => 'sanjay.driver@intercitywheels.com',
                'role' => 'driver'
            ]
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sanjay.driver@intercitywheels.com',
            'role' => 'driver'
        ]);

        $this->assertDatabaseHas('drivers', [
            'vehicle_plate' => 'MH 14 CC 9999',
            'name' => 'Sanjay Manjrekar'
        ]);
    }

    public function test_admin_can_view_and_update_user(): void
    {
        $admin = User::where('role', 'admin')->first();
        $customer = User::where('role', 'customer')->first();
        $this->assertNotNull($admin);
        $this->assertNotNull($customer);

        // View single user
        $showRes = $this->actingAs($admin)->getJson("/api/admin/users/{$customer->id}");
        $showRes->assertStatus(200);
        $showRes->assertJson([
            'success' => true,
            'user' => [
                'id' => $customer->id,
                'email' => $customer->email
            ]
        ]);

        // Update user
        $updateRes = $this->actingAs($admin)->putJson("/api/admin/users/{$customer->id}", [
            'name' => 'Rahul Sharma Updated',
            'email' => 'rahul.updated@intercitywheels.com',
            'phone' => '+91 98765 00000',
            'role' => 'customer'
        ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJson([
            'success' => true,
            'user' => [
                'name' => 'Rahul Sharma Updated',
                'email' => 'rahul.updated@intercitywheels.com'
            ]
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Rahul Sharma Updated'
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $tempUser = User::create([
            'name' => 'Temporary User',
            'email' => 'temp@example.com',
            'phone' => '+91 90000 00000',
            'role' => 'customer',
            'password' => bcrypt('password')
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/users/{$tempUser->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', [
            'id' => $tempUser->id
        ]);
    }

    public function test_admin_panel_renders_users_and_drivers_link_and_section(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        // Check sidebar link
        $response->assertSee('Users & Drivers', false);
        $response->assertSee('data-section="users"', false);

        // Check section and table
        $response->assertSee('id="admin-sec-users"', false);
        $response->assertSee('Registered Users & Chauffeur Directory', false);
        $response->assertSee('admin-add-user-modal', false);
        $response->assertSee('admin-edit-user-modal', false);
        $response->assertSee('admin-view-user-modal', false);
    }
}


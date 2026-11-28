<?php

namespace Tests\Feature;

use App\Models\CustomerTraveller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileAndTravellersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_update_profile_phone_number_and_email(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Aarav Sen',
            'phone' => '9800011111',
            'email' => 'aarav@example.com',
        ]);

        $this->actingAs($customer)->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('profile.name', 'Aarav Sen')
            ->assertJsonPath('profile.phone', '9800011111');

        $this->actingAs($customer)->postJson('/api/customer/profile', [
            'name' => 'Aarav Sen Updated',
            'phone' => '+91 98765 43210',
            'email' => 'aarav.updated@example.com',
            'avatar' => '🧔🏻',
        ])->assertOk()
            ->assertJsonPath('profile.name', 'Aarav Sen Updated')
            ->assertJsonPath('profile.phone', '+91 98765 43210')
            ->assertJsonPath('profile.email', 'aarav.updated@example.com');

        $fresh = $customer->fresh();
        $this->assertSame('Aarav Sen Updated', $fresh->name);
        $this->assertSame('+91 98765 43210', $fresh->phone);
        $this->assertSame('aarav.updated@example.com', $fresh->email);
    }

    public function test_customer_can_add_edit_set_default_and_delete_saved_travellers(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);

        // Create two travellers, second marked as default
        $t1 = $this->actingAs($customer)->postJson('/api/customer/travellers', [
            'name' => 'Riya Sen',
            'phone' => '+91 98111 22222',
            'relation' => 'Spouse',
            'age' => 29,
            'gender' => 'Female',
            'is_default' => true,
        ])->assertStatus(201);
        $t1Id = (int) $t1->json('traveller.id');

        $t2 = $this->actingAs($customer)->postJson('/api/customer/travellers', [
            'name' => 'Rohan Sen',
            'phone' => '+91 98111 33333',
            'relation' => 'Parent',
            'age' => 58,
            'gender' => 'Male',
            'is_default' => true,
        ])->assertStatus(201);
        $t2Id = (int) $t2->json('traveller.id');

        // Default flag moved to t2
        $this->assertFalse((bool) CustomerTraveller::findOrFail($t1Id)->is_default);
        $this->assertTrue((bool) CustomerTraveller::findOrFail($t2Id)->is_default);

        // Update t1 phone and name
        $this->actingAs($customer)->putJson("/api/customer/travellers/{$t1Id}", [
            'name' => 'Riya S.',
            'phone' => '+91 99999 88888',
            'relation' => 'Spouse',
        ])->assertOk()
            ->assertJsonPath('traveller.name', 'Riya S.')
            ->assertJsonPath('traveller.phone', '+91 99999 88888');

        // Another customer cannot edit or delete someone else's traveller
        $this->actingAs($otherCustomer)->putJson("/api/customer/travellers/{$t1Id}", [
            'name' => 'Hacked',
            'phone' => '9999999999',
        ])->assertStatus(404);
        $this->actingAs($otherCustomer)->deleteJson("/api/customer/travellers/{$t1Id}")
            ->assertStatus(404);

        // Owner deletes t1
        $this->actingAs($customer)->deleteJson("/api/customer/travellers/{$t1Id}")
            ->assertOk()
            ->assertJsonCount(1, 'travellers');
    }

    public function test_customer_portal_renders_profile_tab_and_saved_travellers(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Meera Nair',
            'phone' => '+91 98450 12345',
        ]);
        CustomerTraveller::create([
            'user_id' => $customer->id,
            'name' => 'Vikram Nair',
            'phone' => '+91 98450 99999',
            'relation' => 'Spouse',
            'is_default' => true,
        ]);

        $html = $this->actingAs($customer)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="tab-customer-profile"', $html);
        $this->assertStringContainsString('id="customer-profile-form"', $html);
        $this->assertStringContainsString('id="cust-profile-phone"', $html);
        $this->assertStringContainsString('id="customer-traveller-form"', $html);
        $this->assertStringContainsString('Vikram Nair', $html);
        $this->assertStringContainsString('id="cab-saved-travellers-bar"', $html);
    }

    public function test_driver_and_guest_cannot_access_customer_profile_endpoints(): void
    {
        $driver = User::where('role', 'driver')->firstOrFail();

        $this->getJson('/api/customer/profile')->assertStatus(401);
        $this->actingAs($driver)->getJson('/api/customer/profile')->assertStatus(403);
        $this->actingAs($driver)->postJson('/api/customer/travellers', [
            'name' => 'Test',
            'phone' => '9876543210',
        ])->assertStatus(403);
    }
}

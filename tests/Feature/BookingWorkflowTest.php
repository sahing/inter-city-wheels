<?php

namespace Tests\Feature;

use App\Exceptions\InvalidBookingTransition;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use App\Services\BookingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-5 — bookings must follow the configured workflow.
 *
 * Before: BookingController wrote status => 'Confirmed' on creation, the admin
 * screens could PATCH any status string onto any booking, and nothing rejected
 * Confirmed -> Completed. The five stages in workflow_stages were decorative.
 *
 * Customer and driver writes are still guarded by the stage table. The admin
 * console now has an explicit, logged override so ops can correct (or reopen)
 * a booking at any stage — see BookingWorkflow::forceTransition().
 */
class BookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function driverUser(): User
    {
        return User::where('role', 'driver')->firstOrFail();
    }

    public function test_a_new_booking_starts_as_requested_not_confirmed(): void
    {
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Workflow Tester',
            'customer_phone' => '+91 90000 11111',
            'fleet_category' => 'suv',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('booking.status', BookingWorkflow::REQUESTED);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Workflow Tester',
            'status' => BookingWorkflow::REQUESTED,
        ]);
    }

    public function test_admin_created_booking_can_start_confirmed_through_a_legal_transition(): void
    {
        // Admins legitimately create already-confirmed bookings; the end state is
        // allowed, but it is reached by a transition, not by a raw insert.
        $response = $this->actingAs($this->admin())->postJson('/api/admin/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Mumbai',
            'pickup_location' => 'Koregaon Park',
            'drop_location' => 'BKC',
            'customer_name' => 'Admin Created',
            'customer_phone' => '+91 90000 22222',
            'base_fare' => 2500,
            'status' => 'Confirmed',
        ]);

        $response->assertStatus(201)->assertJsonPath('booking.status', 'Confirmed');
    }

    public function test_admin_can_place_a_booking_at_any_stage_even_skipping_the_order(): void
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();

        // Ops override: the admin console is explicitly not bound by the stage
        // order, so a mis-staged trip can be corrected in one save.
        $response = $this->actingAs($this->admin())->putJson("/api/admin/bookings/{$booking->id}", [
            'status' => 'Completed',
            'base_fare' => 2750,
        ]);

        $response->assertStatus(200)->assertJsonPath('booking.status', 'Completed');

        $fresh = $booking->fresh();
        $this->assertSame('Completed', $fresh->status);
        $this->assertSame(2750.0, $fresh->base_fare);
    }

    public function test_admin_can_reopen_a_terminal_booking_and_edit_its_details(): void
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();
        $booking->forceFill([
            'status' => BookingWorkflow::COMPLETED,
            'total_amount' => 3000,
        ])->save();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/bookings/{$booking->id}", [
                'customer_name' => 'Corrected Passenger',
                'status' => 'Started',
            ])
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'Started')
            ->assertJsonPath('booking.customer_name', 'Corrected Passenger');

        $fresh = $booking->fresh();
        $this->assertSame('Started', $fresh->status);
        $this->assertSame('Corrected Passenger', $fresh->customer_name);
    }

    public function test_admin_can_reassign_a_driver_on_a_completed_booking_without_changing_stage(): void
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();
        $driver = Driver::firstOrFail();

        $booking->forceFill([
            'status' => BookingWorkflow::COMPLETED,
            'assigned_driver_id' => null,
        ])->save();

        // The form sends "" for "keep the current stage": the trip already
        // finished, so only the chauffeur record is corrected.
        $this->actingAs($this->admin())
            ->postJson("/api/admin/bookings/{$booking->id}/allot-driver", [
                'driver_id' => $driver->id,
                'status' => '',
            ])
            ->assertStatus(200)
            ->assertJsonPath('booking.assigned_driver_id', $driver->id)
            ->assertJsonPath('booking.status', BookingWorkflow::COMPLETED);

        $fresh = $booking->fresh();
        $this->assertSame(BookingWorkflow::COMPLETED, $fresh->status);
        $this->assertSame($driver->id, $fresh->assigned_driver_id);
    }

    public function test_admin_cannot_set_an_unknown_status(): void
    {
        $booking = Booking::firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'Teleported'])
            ->assertStatus(422);

        $this->assertSame($booking->status, $booking->fresh()->status);
    }

    public function test_legal_admin_transition_is_accepted(): void
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'Cancelled'])
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'Cancelled');
    }

    public function test_driver_cannot_start_a_trip_that_was_never_assigned(): void
    {
        $booking = Booking::create([
            'booking_reference' => 'ICW-900001',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Baner',
            'drop_location' => 'Bandra',
            'customer_name' => 'Unassigned Trip',
            'customer_phone' => '+91 90000 33333',
            'base_fare' => 2000,
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 2000,
            'status' => 'Confirmed',
            'start_otp' => '4242',
        ]);

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/verify-otp", ['otp' => '4242'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('Confirmed', $booking->fresh()->status);
    }

    public function test_driver_cannot_start_another_drivers_trip(): void
    {
        $otherDriver = Driver::create([
            'name' => 'Someone Else',
            'phone' => '+91 90000 44444',
            'vehicle_model' => 'Toyota Etios',
            'vehicle_plate' => 'MH 12 ZZ 1111',
            'fleet_category' => 'sedan',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'ICW-900002',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Baner',
            'drop_location' => 'Bandra',
            'customer_name' => "Other Driver's Trip",
            'customer_phone' => '+91 90000 55555',
            'base_fare' => 2000,
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 2000,
            'status' => 'Driver Assigned',
            'start_otp' => '4242',
            'assigned_driver_id' => $otherDriver->id,
        ]);

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/verify-otp", ['otp' => '4242'])
            ->assertStatus(403);

        $this->assertSame('Driver Assigned', $booking->fresh()->status);
    }

    public function test_assigned_driver_still_cannot_skip_from_confirmed_to_started(): void
    {
        $driver = Driver::firstOrFail();

        $booking = Booking::create([
            'booking_reference' => 'ICW-900003',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Baner',
            'drop_location' => 'Bandra',
            'customer_name' => 'Confirmed With Driver',
            'customer_phone' => '+91 90000 66666',
            'base_fare' => 2000,
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 2000,
            'status' => 'Confirmed',
            'start_otp' => '4242',
            'assigned_driver_id' => $driver->id,
        ]);

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/verify-otp", ['otp' => '4242'])
            ->assertStatus(422)
            ->assertJsonPath('transition.from', 'Confirmed')
            ->assertJsonPath('transition.to', 'Started');

        $this->assertSame('Confirmed', $booking->fresh()->status);
    }

    public function test_completed_trips_cannot_be_completed_twice(): void
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();
        $driver = Driver::firstOrFail();
        $booking->forceFill([
            'status' => BookingWorkflow::STARTED,
            'assigned_driver_id' => $driver->id,
        ])->save();

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(200);

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(422);
    }

    /**
     * The transition table itself, including terminal states and idempotency.
     */
    public function test_transition_table_is_explicit(): void
    {
        $this->assertTrue(BookingWorkflow::canTransition('Requested', 'Confirmed'));
        $this->assertTrue(BookingWorkflow::canTransition('Bidding', 'Driver Assigned'));
        $this->assertTrue(BookingWorkflow::canTransition('Confirmed', 'Driver Assigned'));
        $this->assertTrue(BookingWorkflow::canTransition('Driver Assigned', 'Started'));
        $this->assertTrue(BookingWorkflow::canTransition('Started', 'Completed'));
        $this->assertTrue(BookingWorkflow::canTransition('Confirmed', 'Cancelled'));

        // Same-status writes are idempotent, not illegal.
        $this->assertTrue(BookingWorkflow::canTransition('Confirmed', 'Confirmed'));

        // Skipping stages is not.
        $this->assertFalse(BookingWorkflow::canTransition('Requested', 'Completed'));
        $this->assertFalse(BookingWorkflow::canTransition('Requested', 'Started'));
        $this->assertFalse(BookingWorkflow::canTransition('Confirmed', 'Completed'));
        $this->assertFalse(BookingWorkflow::canTransition('Completed', 'Started'));

        // Terminal states are terminal.
        $this->assertSame([], BookingWorkflow::allowedFrom('Completed'));
        $this->assertSame([], BookingWorkflow::allowedFrom('Cancelled'));

        // Unknown strings are never valid.
        $this->assertFalse(BookingWorkflow::canTransition('Confirmed', 'Teleported'));
    }

    public function test_assert_transition_throws_with_both_statuses(): void
    {
        $this->expectException(InvalidBookingTransition::class);
        $this->expectExceptionMessage('Confirmed -> Completed');

        BookingWorkflow::assertTransition('Confirmed', 'Completed');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Admin endpoints now require an authenticated admin (role middleware).
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    public function test_admin_can_list_all_bookings(): void
    {
        $response = $this->getJson('/api/admin/bookings');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'bookings' => [
                '*' => [
                    'id',
                    'booking_reference',
                    'customer_name',
                    'total_amount',
                    'status',
                    'start_otp',
                ],
            ],
        ]);
    }

    public function test_admin_can_manually_create_booking(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Kothrud, Pune',
            'drop_location' => 'Calangute, Goa',
            'customer_name' => 'Dr. A. P. J. Abdul Kalam',
            'customer_phone' => '+91 98220 99999',
            'customer_email' => 'kalam@isro.org',
            'base_fare' => 6500,
            'toll_fare' => 480,
            'addon_fare' => 350,
            'discount_fare' => 500,
        ];

        $response = $this->postJson('/api/admin/bookings', $payload);
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Dr. A. P. J. Abdul Kalam',
                'service_type' => 'cab',
                'total_amount' => 6830,
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Dr. A. P. J. Abdul Kalam',
            'total_amount' => 6830,
        ]);
    }

    public function test_admin_can_show_and_update_booking(): void
    {
        $booking = Booking::first();
        $this->assertNotNull($booking);

        $showRes = $this->getJson("/api/admin/bookings/{$booking->id}");
        $showRes->assertStatus(200);
        $showRes->assertJson([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
            ],
        ]);

        $updatePayload = [
            'customer_name' => 'Ratan Tata',
            'pickup_location' => 'Tata Motors Gate, Pune',
            'base_fare' => 5000,
            'toll_fare' => 400,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'status' => 'Confirmed',
        ];

        $updateRes = $this->putJson("/api/admin/bookings/{$booking->id}", $updatePayload);
        $updateRes->assertStatus(200);
        $updateRes->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Ratan Tata',
                'pickup_location' => 'Tata Motors Gate, Pune',
                'total_amount' => 5400,
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_name' => 'Ratan Tata',
        ]);
    }

    public function test_admin_can_allot_driver_to_booking(): void
    {
        $booking = Booking::first();
        $driver = Driver::first();
        $this->assertNotNull($booking);
        $this->assertNotNull($driver);

        $response = $this->postJson("/api/admin/bookings/{$booking->id}/allot-driver", [
            'driver_id' => $driver->id,
            'status' => 'Driver Assigned',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'assigned_driver_id' => $driver->id,
                'status' => 'Driver Assigned',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'assigned_driver_id' => $driver->id,
            'status' => 'Driver Assigned',
        ]);
    }

    public function test_admin_can_generate_tax_invoice(): void
    {
        $booking = Booking::first();
        $this->assertNotNull($booking);

        $response = $this->getJson("/api/admin/bookings/{$booking->id}/invoice");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'invoice' => [
                'invoice_number',
                'invoice_date',
                'company' => [
                    'name',
                    'gstin',
                    'hsn_sac',
                ],
                'customer' => [
                    'name',
                    'phone',
                    'pickup_location',
                    'drop_location',
                ],
                'charges' => [
                    'base_fare',
                    'toll_fare',
                    'taxable_amount',
                    'cgst',
                    'sgst',
                    'total_amount',
                ],
                'payment' => [
                    'status',
                    'transaction_ref',
                    'start_otp',
                ],
            ],
            'booking',
        ]);
    }

    public function test_admin_bookings_include_and_render_cab_type(): void
    {
        $createRes = $this->postJson('/api/admin/bookings', [
            'service_type' => 'cab',
            'cab_type' => 'innova',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Kothrud, Pune',
            'drop_location' => 'Calangute, Goa',
            'customer_name' => 'Cab Type Tester',
            'customer_phone' => '+91 98220 12345',
            'base_fare' => 7500,
        ]);

        $createRes->assertStatus(201);
        $createRes->assertJsonPath('booking.cab_type', 'INNOVA');
        $bookingId = $createRes->json('booking.id');

        $listRes = $this->getJson('/api/admin/bookings');
        $listRes->assertStatus(200);
        $found = collect($listRes->json('bookings'))->firstWhere('id', $bookingId);
        $this->assertNotNull($found);
        $this->assertSame('INNOVA', $found['cab_type']);

        $updateRes = $this->putJson("/api/admin/bookings/{$bookingId}", [
            'cab_type' => 'sedan',
        ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('booking.cab_type', 'SEDAN');

        $pageRes = $this->get('/');
        $pageRes->assertStatus(200);
        $pageRes->assertSee('SEDAN');
    }

    public function test_admin_dashboard_and_bookings_tables_render_pagination_bars(): void
    {
        $pageRes = $this->get('/');
        $pageRes->assertStatus(200)
            ->assertSee('id="admin-dashboard-fleet-tbody"', false)
            ->assertSee('id="pag-bar-dashboard-fleet"', false)
            ->assertSee('id="admin-recent-bookings-tbody"', false)
            ->assertSee('id="pag-bar-dashboard-bookings"', false)
            ->assertSee('id="admin-bookings-table-tbody"', false)
            ->assertSee('id="pag-bar-admin-bookings"', false);
    }

    public function test_customer_sees_allotted_driver_contact_and_customer_and_driver_can_reach_support(): void
    {
        PlatformSetting::set('legal_contact_helpline', '+91 80011 22334');
        PlatformSetting::set('legal_contact_email', 'helpdesk@intercitywheels.test');

        $customer = User::where('role', 'customer')->firstOrFail();
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
            'phone' => '+91 91111 00001',
        ]);
        $driver = Driver::firstOrFail();
        $driverUser = User::where('role', 'driver')->firstOrFail();

        // Customer creates a confirmed/pending booking (before driver allotment, Support is already reachable)
        $bookRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Shivajinagar, Pune',
            'drop_location' => 'Panaji, Goa',
            'customer_name' => 'Asha Kulkarni',
            'customer_phone' => '+91 98888 12345',
            'fleet_category' => 'sedan',
            'payment_mode' => 'cash',
        ])->assertOk();

        $bookingId = (int) $bookRes->json('booking.id');
        $bookingRef = (string) $bookRes->json('booking.booking_reference');

        $beforeHtml = $this->actingAs($customer)->get('/')->getContent();
        $this->assertStringContainsString('tel:+918001122334', $beforeHtml);
        $this->assertStringContainsString('mailto:helpdesk@intercitywheels.test', $beforeHtml);
        $this->assertStringContainsString('Call Support', $beforeHtml);

        // Admin allots a driver (Driver Assigned / Confirmed stage)
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $driver->id,
            'status' => 'Confirmed',
        ])->assertOk();

        // Concerned customer sees allotted driver name, plate, phone & Call Driver button in My Trips, API show & lookup
        $afterHtml = $this->actingAs($customer)->get('/')->getContent();
        $cleanDriverTel = preg_replace('/[^0-9+]/', '', (string) $driver->phone);
        $this->assertStringContainsString($driver->name, $afterHtml);
        $this->assertStringContainsString((string) $driver->phone, $afterHtml);
        $this->assertStringContainsString("tel:{$cleanDriverTel}", $afterHtml);
        $this->assertStringContainsString('Call Driver', $afterHtml);
        $this->assertStringContainsString('+91 98888 12345', $afterHtml);
        $this->assertStringContainsString('id="ticket-call-driver-btn"', $afterHtml);
        $this->assertStringContainsString('id="ticket-call-support-btn"', $afterHtml);

        $this->actingAs($customer)->getJson("/api/bookings/{$bookingRef}")
            ->assertOk()
            ->assertJsonPath('booking.driver.id', $driver->id)
            ->assertJsonPath('booking.driver.phone', $driver->phone);

        // Another unrelated customer cannot view this booking's details or driver phone
        $this->actingAs($otherCustomer)->getJson("/api/bookings/{$bookingRef}")
            ->assertStatus(403);
        $otherHtml = $this->actingAs($otherCustomer)->get('/')->getContent();
        $this->assertStringNotContainsString($bookingRef, $otherHtml);

        // Driver sees both Support contact (across driver console & header) and Confirmed customer contact
        $driverHtml = $this->actingAs($driverUser)->get('/')->getContent();
        $this->assertStringContainsString('tel:+918001122334', $driverHtml);
        $this->assertStringContainsString('mailto:helpdesk@intercitywheels.test', $driverHtml);
        $this->assertStringContainsString('Call Driver Support', $driverHtml);
        $this->assertStringContainsString('+91 98888 12345', $driverHtml);
        $this->assertStringContainsString('Asha Kulkarni', $driverHtml);
    }

    public function test_admin_can_upload_crop_and_compress_car_type_image_under_30kb(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $customer = User::where('role', 'customer')->firstOrFail();

        // Modal renders file picker & interactive cropper controls
        $html = $this->actingAs($admin)->get('/')->getContent();
        $this->assertStringContainsString('id="admin-car-type-file-input"', $html);
        $this->assertStringContainsString('id="car-type-cropper-box"', $html);
        $this->assertStringContainsString('id="car-type-crop-canvas"', $html);
        $this->assertStringContainsString('id="car-type-crop-upload-btn"', $html);

        // Create a rich noisy 1200x800 PNG (> 30 KB raw) so server-side crop & compression must reduce it <= 30 KB
        $tmpPng = tempnam(sys_get_temp_dir(), 'cartype_').'.png';
        $im = imagecreatetruecolor(1200, 800);
        for ($y = 0; $y < 800; $y += 8) {
            for ($x = 0; $x < 1200; $x += 8) {
                $col = imagecolorallocate($im, ($x * 3) % 255, ($y * 5) % 255, (($x + $y) * 7) % 255);
                imagefilledrectangle($im, $x, $y, $x + 7, $y + 7, $col);
            }
        }
        imagepng($im, $tmpPng);
        imagedestroy($im);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tmpPng,
            'large-fleet-photo.png',
            'image/png',
            null,
            true
        );

        // Non-admin cannot upload
        $this->actingAs($customer)
            ->post('/api/admin/car-types/upload-image', ['image' => $uploadedFile], ['Accept' => 'application/json'])
            ->assertStatus(403);

        $uploadRes = $this->actingAs($admin)->post('/api/admin/car-types/upload-image', [
            'image' => $uploadedFile,
            'crop_x' => 100,
            'crop_y' => 80,
            'crop_w' => 960,
            'crop_h' => 600,
        ], ['Accept' => 'application/json']);

        @unlink($tmpPng);

        $uploadRes->assertOk()
            ->assertJsonPath('success', true);

        $imageUrl = (string) $uploadRes->json('image_url');
        $sizeBytes = (int) $uploadRes->json('size_bytes');
        $savedPath = public_path(ltrim($imageUrl, '/'));

        try {
            $this->assertStringStartsWith('/uploads/car-types/car-type-', $imageUrl);
            $this->assertFileExists($savedPath);
            $this->assertGreaterThan(0, $sizeBytes);
            $this->assertLessThanOrEqual(30720, $sizeBytes);
            $this->assertLessThanOrEqual(30720, filesize($savedPath));

            // Save a Car Type using the compressed image URL
            $storeRes = $this->actingAs($admin)->postJson('/api/admin/car-types', [
                'name' => 'EV Executive Sedan',
                'category_key' => 'ev_sedan',
                'badge' => 'Zero Emission',
                'models' => 'Tata Tigor EV, MG ZS EV',
                'seats' => '4 Seats',
                'luggage' => '2 Bags',
                'rate_per_km' => 15.5,
                'image_url' => $imageUrl,
            ]);

            $storeRes->assertStatus(201)
                ->assertJsonPath('car_type.image_url', $imageUrl);
        } finally {
            @unlink($savedPath);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_prebuilt_style_and_customize_invoice_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Non-admin is forbidden
        $this->actingAs($customer)->postJson('/api/admin/settings/invoice-template', [
            'invoice_template_style' => 'executive_dark',
            'invoice_title' => 'TAX INVOICE',
            'invoice_prefix' => 'INV',
            'invoice_brand_name' => 'TEST',
            'invoice_company_legal' => 'Test Co',
            'invoice_company_address' => 'Test Addr',
            'invoice_cgst_pct' => 2.5,
            'invoice_sgst_pct' => 2.5,
        ])->assertStatus(403);

        // Invalid template style is rejected
        $this->actingAs($admin)->postJson('/api/admin/settings/invoice-template', [
            'invoice_template_style' => 'unknown_style',
            'invoice_title' => 'TAX INVOICE',
            'invoice_prefix' => 'INV',
            'invoice_brand_name' => 'TEST',
            'invoice_company_legal' => 'Test Co',
            'invoice_company_address' => 'Test Addr',
            'invoice_cgst_pct' => 2.5,
            'invoice_sgst_pct' => 2.5,
        ])->assertStatus(422)->assertJsonValidationErrors(['invoice_template_style']);

        // Valid update across all fields
        $res = $this->actingAs($admin)->postJson('/api/admin/settings/invoice-template', [
            'invoice_template_style' => 'executive_dark',
            'invoice_accent_color' => '#0284c7',
            'invoice_title' => 'CORPORATE GST BILL',
            'invoice_prefix' => 'ICWBILL',
            'invoice_brand_name' => 'WHEELS PRO',
            'invoice_company_legal' => 'Wheels Pro Mobility LLP',
            'invoice_company_address' => 'Tower B, Cyber Park, Kolkata 700091',
            'invoice_gstin' => '19AAACW1234A1Z5',
            'invoice_sac_code' => '996412',
            'invoice_place_of_supply' => 'West Bengal (19)',
            'invoice_support_phone' => '9000011111',
            'invoice_support_email' => 'accounts@wheelspro.in',
            'invoice_cgst_pct' => 6,
            'invoice_sgst_pct' => 6,
            'invoice_show_stamp' => false,
            'invoice_show_otp' => false,
            'invoice_terms' => "1. Valid without signature.\n2. Subject to Kolkata jurisdiction.",
            'invoice_signatory_name' => 'Wheels Pro Billing',
            'invoice_signatory_label' => 'Finance Controller',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.invoice_template_style', 'executive_dark')
            ->assertJsonPath('settings.invoice_accent_color', '#0284c7')
            ->assertJsonPath('settings.invoice_prefix', 'ICWBILL')
            ->assertJsonPath('settings.invoice_show_stamp', '0');

        $booking = Booking::create([
            'booking_reference' => 'ICW-TPL01',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Kolkata → Digha',
            'pickup_location' => 'Salt Lake',
            'drop_location' => 'Digha Beach',
            'customer_name' => 'Meera Roy',
            'customer_phone' => '9876543210',
            'customer_email' => 'meera@example.com',
            'base_fare' => 2000,
            'toll_fare' => 200,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 2200,
            'status' => 'Confirmed',
            'start_otp' => '1234',
        ]);

        $invRes = $this->actingAs($admin)->getJson("/api/admin/bookings/{$booking->id}/invoice");
        $invRes->assertOk()
            ->assertJsonPath('invoice.template.style', 'executive_dark')
            ->assertJsonPath('invoice.template.accent_color', '#0284c7')
            ->assertJsonPath('invoice.template.title', 'CORPORATE GST BILL')
            ->assertJsonPath('invoice.template.brand_name', 'WHEELS PRO')
            ->assertJsonPath('invoice.template.show_stamp', false)
            ->assertJsonPath('invoice.template.show_otp', false)
            ->assertJsonPath('invoice.company.name', 'Wheels Pro Mobility LLP')
            ->assertJsonPath('invoice.company.gstin', '19AAACW1234A1Z5')
            ->assertJsonPath('invoice.company.support_email', 'accounts@wheelspro.in');

        $this->assertStringStartsWith('ICWBILL-', $invRes->json('invoice.invoice_number'));
        $this->assertEquals(120.0, (float) $invRes->json('invoice.charges.cgst'));
        $this->assertEquals(120.0, (float) $invRes->json('invoice.charges.sgst'));
        $this->assertCount(2, $invRes->json('invoice.template.terms'));

        // Admin page renders the 4 prebuilt style options & saved style on modal
        $page = $this->actingAs($admin)->get('/');
        $page->assertOk()
            ->assertSee('admin-invoice-template-card', false)
            ->assertSee('data-style-option="classic_gst"', false)
            ->assertSee('data-style-option="executive_dark"', false)
            ->assertSee('data-style-option="modern_minimal"', false)
            ->assertSee('data-style-option="compact_receipt"', false)
            ->assertSee('data-invoice-style="executive_dark"', false);
    }

    public function test_all_four_prebuilt_styles_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['classic_gst', 'executive_dark', 'modern_minimal', 'compact_receipt'] as $style) {
            $this->actingAs($admin)->postJson('/api/admin/settings/invoice-template', [
                'invoice_template_style' => $style,
                'invoice_title' => 'TAX INVOICE',
                'invoice_prefix' => 'INV',
                'invoice_brand_name' => 'INTERCITY WHEELS',
                'invoice_company_legal' => 'IntercityWheels Mobility Services Pvt. Ltd.',
                'invoice_company_address' => 'Pune, India',
                'invoice_cgst_pct' => 2.5,
                'invoice_sgst_pct' => 2.5,
            ])->assertOk()->assertJsonPath('settings.invoice_template_style', $style);
        }
    }
}

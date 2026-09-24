<?php

namespace Tests\Feature\OldJewellery;

use App\Models\Vendor;
use App\Services\Vendors\PanelAccessService;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The vendor password-setup link must either really reach a mail server or
 * tell the admin exactly why not — with the link itself, so it can be passed
 * on by hand — instead of a vague "could not be sent" (or a false "sent").
 */
class VendorSetupLinkDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendor(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'name' => 'Delivery Vendor',
            'mobile' => '9000000011',
            'email' => 'delivery@example.com',
            'is_active' => true,
            'access_role' => Vendor::ACCESS_ROLE_VENDOR,
        ], $overrides));
    }

    public function test_setup_link_is_emailed(): void
    {
        $vendor = $this->makeVendor();
        $service = app(PanelAccessService::class);

        $this->assertTrue($service->grant($vendor));
        $this->assertNull($service->lastError);
        $this->assertStringContainsString('/panel/set-password/', (string) $service->lastSetupUrl);
    }

    public function test_mail_server_failure_is_reported_with_the_reason_and_the_link(): void
    {
        $vendor = $this->makeVendor();
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('553 Sender address rejected'));
        $service = app(PanelAccessService::class);

        $this->assertFalse($service->grant($vendor));
        $this->assertStringContainsString('553 Sender address rejected', $service->lastError);
        $this->assertStringContainsString('/panel/set-password/', $service->failureDetails());
        // The account is kept, so the admin can resend once mail works.
        $this->assertNotNull($vendor->fresh()->user_id);
    }

    public function test_whatsapp_failure_does_not_mark_a_delivered_email_as_failed(): void
    {
        $vendor = $this->makeVendor(['whatsapp_number' => '9000000012']);
        $this->app->instance(WhatsAppGateway::class, new class implements WhatsAppGateway {
            public function send(string $to, string $message): void
            {
                throw new \RuntimeException('WhatsApp API down');
            }
        });

        $this->assertTrue(app(PanelAccessService::class)->grant($vendor));
    }
}

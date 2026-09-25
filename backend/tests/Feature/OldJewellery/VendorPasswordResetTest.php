<?php

namespace Tests\Feature\OldJewellery;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\PanelAccessInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The vendor portal's self-service "Forgot password?": emails the same
 * one-time password link as the admin's "Resend setup link", without ever
 * revealing which addresses have vendor accounts.
 */
class VendorPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_STATUS = 'If that email belongs to a vendor account, a password reset link is on its way. Check your inbox (and spam folder).';

    private function makeVendorWithLogin(array $overrides = []): Vendor
    {
        $user = User::factory()->create([
            'email' => 'reset-vendor@example.com',
            'password' => Hash::make('old-password'),
        ]);

        return Vendor::create(array_merge([
            'name' => 'Reset Vendor',
            'mobile' => '9000000021',
            'whatsapp_number' => '9000000022',
            'email' => $user->email,
            'is_active' => true,
            'access_role' => Vendor::ACCESS_ROLE_VENDOR,
            'user_id' => $user->id,
        ], $overrides));
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('vendor.login'))
            ->assertOk()
            ->assertSee(route('vendor.password.request'), false);

        $this->get(route('vendor.password.request'))
            ->assertOk()
            ->assertSee('Send Reset Link');
    }

    public function test_vendor_is_emailed_a_reset_link_and_no_whatsapp(): void
    {
        Notification::fake();
        $vendor = $this->makeVendorWithLogin();

        $this->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com'])
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo(
            $vendor,
            PanelAccessInvitation::class,
            fn (PanelAccessInvitation $notification, array $channels) => $channels === ['mail'] && $notification->isResend,
        );
        Notification::assertSentToTimes($vendor, PanelAccessInvitation::class, 1);
    }

    public function test_emailed_link_sets_a_new_password_that_signs_in(): void
    {
        Notification::fake();
        $vendor = $this->makeVendorWithLogin();

        $this->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com']);

        $setupUrl = null;
        Notification::assertSentTo($vendor, PanelAccessInvitation::class, function (PanelAccessInvitation $notification) use (&$setupUrl) {
            $setupUrl = $notification->setupUrl;

            return true;
        });

        $this->get($setupUrl)->assertOk();

        $token = basename(parse_url($setupUrl, PHP_URL_PATH));
        $this->post(route('panel.password.store'), [
            'token' => $token,
            'email' => 'reset-vendor@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('vendor.login'));

        $this->post(route('vendor.login.store'), [
            'email' => 'reset-vendor@example.com',
            'password' => 'brand-new-password',
        ])->assertRedirect(route('vendor.dashboard'));
    }

    public function test_unknown_email_gets_the_same_reply_and_nothing_is_sent(): void
    {
        Notification::fake();

        $this->post(route('vendor.password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_customer_admin_contact_and_inactive_vendor_are_not_sent_a_link(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'customer@example.com']);
        $this->post(route('vendor.password.email'), ['email' => 'customer@example.com'])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        $this->makeVendorWithLogin(['access_role' => Vendor::ACCESS_ROLE_ADMIN]);
        $this->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com'])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Vendor::query()->update(['access_role' => Vendor::ACCESS_ROLE_VENDOR, 'is_active' => false]);
        $this->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com'])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_requests_for_one_email_are_rate_limited(): void
    {
        Notification::fake();
        $vendor = $this->makeVendorWithLogin();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com'])
                ->assertSessionHas('status');
        }

        $this->from(route('vendor.password.request'))
            ->post(route('vendor.password.email'), ['email' => 'reset-vendor@example.com'])
            ->assertRedirect(route('vendor.password.request'))
            ->assertSessionHas('error');

        Notification::assertSentToTimes($vendor, PanelAccessInvitation::class, 3);
    }

    public function test_mail_test_command_sends_through_the_configured_mailer(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array'],
        ]);

        $this->artisan('mail:test', ['to' => 'owner@example.com'])
            ->expectsOutputToContain('Sent to owner@example.com')
            ->assertSuccessful();

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('owner@example.com', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_mail_test_command_fails_when_mail_is_only_logged(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:test', ['to' => 'owner@example.com'])
            ->expectsOutputToContain('only records emails')
            ->assertFailed();

        $this->artisan('mail:test', ['to' => 'not-an-email'])
            ->assertFailed();
    }
}

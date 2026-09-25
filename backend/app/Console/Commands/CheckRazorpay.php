<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Payment\RazorpayGateway;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Shows why online payment does or doesn't work on this server, by checking
 * exactly what checkout uses and then asking Razorpay for a ₹1 test order:
 *
 *   php artisan razorpay:check
 *
 * Nothing is charged: a Razorpay order is only a payment request, and this
 * one is never shown to anyone.
 */
class CheckRazorpay extends Command
{
    protected $signature = 'razorpay:check';

    protected $description = 'Check the Razorpay settings on this server and try creating a Rs 1 test order';

    public function handle(RazorpayGateway $razorpay): int
    {
        $keyId = (string) config('services.razorpay.key_id');
        $provider = Setting::where('key', 'payment_provider')->value('value') ?? '(not set: cod)';
        $ok = true;

        $this->line('payment_provider setting: '.$provider);
        $this->line('RAZORPAY_KEY_ID:         '.($keyId !== '' ? substr($keyId, 0, 12).'…  ('.(str_starts_with($keyId, 'rzp_live_') ? 'LIVE' : (str_starts_with($keyId, 'rzp_test_') ? 'TEST' : 'unrecognised')).' key)' : 'missing'));
        $this->line('RAZORPAY_KEY_SECRET:     '.(filled(config('services.razorpay.key_secret')) ? 'set' : 'missing'));
        $this->line('RAZORPAY_WEBHOOK_SECRET: '.(filled(config('services.razorpay.webhook_secret')) ? 'set' : 'missing (webhook payments will be rejected)'));
        $this->line('Settings cached:         '.(app()->configurationIsCached() ? 'yes (run php artisan optimize:clear && php artisan optimize after editing .env)' : 'no'));

        if ($provider !== 'razorpay') {
            $this->warn('Online payment is switched off: set payment_provider to "razorpay" in the admin (Settings).');
            $ok = false;
        }

        if (! $razorpay->isConfigured()) {
            $this->error('RAZORPAY_KEY_ID and/or RAZORPAY_KEY_SECRET are missing from .env (or cached settings are stale).');

            return self::FAILURE;
        }

        $probe = new Order([
            'order_number' => 'CHECK-'.now()->format('YmdHis'),
            'total' => 1,
            'wallet_amount_used' => 0,
        ]);

        try {
            $result = $razorpay->createOrder($probe);
        } catch (RequestException $e) {
            $status = $e->response->status();
            $reason = $e->response->json('error.description') ?? trim($e->response->body());
            $this->error("Razorpay refused the test order (HTTP {$status}): ".($reason !== '' ? $reason : 'no details given'));

            if ($status === 401) {
                $this->warn('401 means the key id and key secret don\'t match: copy both again from the Razorpay dashboard (same mode, test or live).');
            } elseif ($reason === '') {
                $this->warn('An empty reply usually means a firewall or proxy on this server blocked the request before it reached Razorpay: ask the host to allow outgoing HTTPS to api.razorpay.com.');
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('This server could not reach Razorpay: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Razorpay accepted a test order ('.$result['id'].'). Online payment can work on this server.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

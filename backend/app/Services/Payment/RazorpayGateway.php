<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Real Razorpay API client. Only ever consulted when RAZORPAY_KEY_ID and
 * RAZORPAY_KEY_SECRET are set (see isConfigured()) — PaymentManager keeps
 * checkout COD-only otherwise, so this class is safe to leave wired in
 * before a Razorpay account exists (same pattern as ShiprocketService).
 */
class RazorpayGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    /**
     * RAZORPAY_BASE_URL is for local end-to-end testing against a stand-in
     * API only; leave it unset everywhere else.
     */
    private function baseUrl(): string
    {
        return rtrim((string) (config('services.razorpay.base_url') ?: self::BASE_URL), '/');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    /**
     * @return array{id: string, amount: int, currency: string}
     */
    public function createOrder(Order $order): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Razorpay is not configured.');
        }

        return Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            // Transient network blips (DNS hiccups, a dropped TLS handshake)
            // shouldn't surface "payment unavailable" to a customer on the
            // very first hitch — retry connection-level failures and 5xx
            // responses a few times before giving up. Never retry a 4xx
            // (bad credentials, malformed request): that's deterministic and
            // an extra attempt only risks creating a duplicate Razorpay order.
            ->retry(3, 300, function (\Throwable $exception) {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            })
            ->post($this->baseUrl().'/orders', [
                // Only what's left after the wallet — the wallet part was
                // already debited at checkout.
                'amount' => $this->rupeesToPaise($order->amountDue()),
                'currency' => 'INR',
                'receipt' => $order->order_number,
                'notes' => [
                    'order_number' => $order->order_number,
                ],
            ])
            ->throw()
            ->json();
    }

    /**
     * Every payment attempt made against a Razorpay order (failed ones
     * included), each with its `id` and `status` (created / authorized /
     * captured / refunded / failed). Throws when Razorpay can't be reached,
     * so a caller can tell "not paid" apart from "couldn't check".
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPayments(string $razorpayOrderId): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Razorpay is not configured.');
        }

        return Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            ->timeout(10)
            ->get($this->baseUrl().'/orders/'.rawurlencode($razorpayOrderId).'/payments')
            ->throw()
            ->json('items', []);
    }

    public function verifyPaymentSignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$razorpayOrderId}|{$razorpayPaymentId}", (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        // Fail CLOSED: without a configured webhook secret there is nothing to
        // verify against, so ANY request claiming to be from Razorpay is
        // rejected rather than accepted (the previous hash_equals against an
        // empty-secret HMAC effectively trusted every sender).
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Razorpay's Orders API (and its checkout.js widget) want an integer
     * amount in the smallest currency unit (paise), not a decimal rupee
     * amount. Order::total comes back as a numeric string via the
     * decimal:2 cast (e.g. "499.00") — cast to float before multiplying and
     * round explicitly rather than truncating, so a value like "10.10"
     * becomes 1010 paise, not 1009 from float drift. Public: the checkout
     * page needs the exact same figure to render the payment widget.
     */
    public function rupeesToPaise(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}

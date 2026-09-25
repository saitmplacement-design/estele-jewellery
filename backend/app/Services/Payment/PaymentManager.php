<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point checkout/webhook code calls for online payment. Which
 * provider is active is a Setting (payment_provider), not code — same
 * config-change-not-a-rewrite approach as ShippingManager.
 */
class PaymentManager
{
    /**
     * payment_status values that must never be overwritten by a late/replayed
     * gateway event — a captured-after-refund or failed-after-paid race must
     * not downgrade a settled order.
     */
    private const SETTLED_STATUSES = ['paid', 'refunded', 'partially_refunded'];

    public function __construct(private readonly RazorpayGateway $razorpay) {}

    public function isOnlinePaymentEnabled(): bool
    {
        return $this->activeProvider() === 'razorpay' && $this->razorpay->isConfigured();
    }

    /**
     * @return array{id: string, amount: int, currency: string}
     */
    public function createOrder(Order $order): array
    {
        return $this->razorpay->createOrder($order);
    }

    public function keyId(): ?string
    {
        return config('services.razorpay.key_id');
    }

    public function amountInPaise(Order $order): int
    {
        return $this->razorpay->rupeesToPaise($order->amountDue());
    }

    /**
     * Asks Razorpay what actually happened to an online order and records a
     * captured payment. This is the safety net for a customer whose browser
     * closed before the success callback ran, when the payment.captured
     * webhook never arrived either: without it the order would sit on
     * "pending" although the money was taken.
     *
     * Returns 'paid' (a captured payment was found and recorded),
     * 'authorized' (money is being taken but isn't captured yet — leave the
     * order alone) or 'unpaid'. Throws when Razorpay can't be reached.
     */
    public function syncFromGateway(Order $order): string
    {
        if (blank($order->razorpay_order_id)) {
            return 'unpaid';
        }

        $payments = $this->razorpay->fetchPayments($order->razorpay_order_id);

        foreach ($payments as $payment) {
            if (($payment['status'] ?? null) === 'captured') {
                $this->markPaid($order, (string) $payment['id']);

                return 'paid';
            }
        }

        foreach ($payments as $payment) {
            if (($payment['status'] ?? null) === 'authorized') {
                return 'authorized';
            }
        }

        return 'unpaid';
    }

    public function verifyPaymentSignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        return $this->razorpay->verifyPaymentSignature($razorpayOrderId, $razorpayPaymentId, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        return $this->razorpay->verifyWebhookSignature($rawBody, $signatureHeader);
    }

    public function markPaid(Order $order, string $razorpayPaymentId): void
    {
        // A cancelled order must never be resurrected — record the captured
        // payment as an audit trail (payment_reference + admin_notes) instead
        // of flipping payment_status.
        if ($this->recordCancelledCapture($order, $razorpayPaymentId)) {
            return;
        }

        if (in_array($order->payment_status, self::SETTLED_STATUSES, true)) {
            Log::info('Ignoring repeat "paid" signal for an already-settled order.', ['order_number' => $order->order_number]);

            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_reference' => $razorpayPaymentId,
        ]);
    }

    public function markFailed(Order $order): void
    {
        if ($order->status === 'cancelled') {
            Log::info('Ignoring payment-failed signal for a cancelled order.', ['order_number' => $order->order_number]);

            return;
        }

        if (in_array($order->payment_status, self::SETTLED_STATUSES, true)) {
            Log::warning('Ignoring "failed" signal for an already-settled order — a captured event likely raced this one.', ['order_number' => $order->order_number]);

            return;
        }

        $order->update(['payment_status' => 'failed']);
    }

    /**
     * A captured payment arriving for an already-cancelled order is logged as
     * an audit note on the order (payment_reference + admin_notes) without
     * resurrecting the order to `paid`. Idempotent: a duplicate capture for
     * the same payment_id is a no-op. Returns true when the order was already
     * cancelled and the capture was (or already had been) recorded.
     */
    public function recordCancelledCapture(Order $order, string $razorpayPaymentId): bool
    {
        if ($order->status !== 'cancelled') {
            return false;
        }

        // Already recorded for this payment — idempotent.
        if ($order->payment_reference === $razorpayPaymentId) {
            return true;
        }

        $note = "Captured payment {$razorpayPaymentId} arrived after this order was cancelled — recorded for manual refund.";

        // Store the first captured payment id as the canonical reference so
        // admins can look it up on the Razorpay dashboard.
        if ($order->payment_reference === null) {
            $order->payment_reference = $razorpayPaymentId;
        }

        // Only append the note if it isn't already the latest one.
        $notes = trim((string) $order->admin_notes);
        if ($notes === '' || ! str_ends_with($notes, $note)) {
            $order->admin_notes = $notes === '' ? $note : $notes."\n".$note;
        }

        $order->save();

        Log::warning('Captured payment arrived for a cancelled order — refund pending.', [
            'order_number' => $order->order_number,
            'razorpay_payment_id' => $razorpayPaymentId,
        ]);

        return true;
    }

    private function activeProvider(): string
    {
        return $this->settings()['payment_provider'] ?? 'cod';
    }

    private function settings(): array
    {
        return Cache::remember('site.settings', 3600, fn () => Setting::pluck('value', 'key')->toArray());
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Payment\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * An online (Razorpay) order takes its stock, coupon use and any wallet
 * amount the moment it's placed, before the customer pays. When the payment
 * is abandoned, this frees all of that again: after PAYMENT_WINDOW_MINUTES
 * the order is cancelled, and Order's own cancel handling puts the stock
 * back, releases the coupon and refunds the wallet.
 *
 * Razorpay is always asked first, so a payment that went through without
 * the site hearing about it (tab closed, no webhook) is recorded as paid
 * instead of cancelled. If Razorpay can't be reached the order is left for
 * the next run — never cancelled on a guess.
 */
class CancelUnpaidOnlineOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PAYMENT_WINDOW_MINUTES = 120;

    public function handle(?PaymentManager $payments = null): void
    {
        $payments ??= app(PaymentManager::class);

        Order::where('payment_method', 'razorpay')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->where('status', 'placed')
            ->where('created_at', '<=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES))
            ->orderBy('id')
            ->cursor()
            ->each(function (Order $order) use ($payments) {
                try {
                    $state = $payments->syncFromGateway($order);
                } catch (\Throwable $e) {
                    Log::warning('Could not check an unpaid online order with Razorpay; will retry next run.', [
                        'order_number' => $order->order_number,
                        'exception' => $e->getMessage(),
                    ]);

                    return;
                }

                if ($state !== 'unpaid') {
                    return;
                }

                $order->refresh();

                if ($order->status !== 'placed' || ! in_array($order->payment_status, ['pending', 'failed'], true)) {
                    return;
                }

                // One order failing must not stop the rest of this run.
                try {
                    $order->admin_notes = trim(((string) $order->admin_notes)."\n\nCancelled automatically: online payment was not completed within "
                        .(self::PAYMENT_WINDOW_MINUTES / 60).' hours.');
                    $order->status = 'cancelled';
                    $order->save();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function show(Order $order)
    {
        $this->authorizeOwnOrder($order);

        if ($order->status === 'cancelled') {
            return redirect()->route('home')
                ->with('error', 'This order was cancelled before payment completed.');
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('checkout.confirmation', $order);
        }

        if (blank($order->razorpay_order_id)) {
            // createOrder() at checkout time failed (transient gateway
            // outage) — the order itself is safe (stock/coupon already
            // committed), so retry once here rather than stranding it.
            try {
                $razorpayOrder = $this->payments->createOrder($order);
                $order->update(['razorpay_order_id' => $razorpayOrder['id']]);
            } catch (\Throwable $e) {
                report($e);

                return view('payment.unavailable', compact('order'));
            }
        }

        return view('payment.show', [
            'order' => $order,
            'razorpayKeyId' => $this->payments->keyId(),
            'amountPaise' => $this->payments->amountInPaise($order),
        ]);
    }

    public function callback(Order $order, Request $request)
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        // Defends against a tampered form re-targeting a different order —
        // the signature alone proves the payment is genuine, not that it was
        // ever meant for *this* order.
        if ($validated['razorpay_order_id'] !== $order->razorpay_order_id) {
            return redirect()->route('payment.show', $order)
                ->with('error', 'This payment does not match the order. Please try again.');
        }

        $valid = $this->payments->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        );

        if (! $valid) {
            Log::warning('Razorpay payment signature verification failed.', ['order_number' => $order->order_number]);

            return redirect()->route('payment.show', $order)
                ->with('error', 'We couldn\'t verify that payment. Please try again.');
        }

        if ($this->payments->recordCancelledCapture($order, $validated['razorpay_payment_id'])) {
            return redirect()->route('home')
                ->with('error', 'This order was cancelled before your payment completed. Your payment has been recorded; please contact support for a refund.');
        }

        $this->payments->markPaid($order, $validated['razorpay_payment_id']);

        return redirect()->route('checkout.confirmation', $order)->with('success', 'Order placed successfully.');
    }

    public function webhook(Request $request)
    {
        $signature = (string) $request->header('X-Razorpay-Signature');

        if (! $this->payments->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Razorpay webhook signature verification failed.');

            return response()->json(['status' => 'invalid signature'], 400);
        }

        $event = $request->input('event');
        $razorpayOrderId = $request->input('payload.payment.entity.order_id')
            ?? $request->input('payload.order.entity.id');

        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

        // An unknown order (deleted/never existed) or an event type we don't
        // act on isn't an error worth a retry storm — Razorpay retries hard
        // on non-2xx, so acknowledge and move on.
        if (! $order) {
            return response()->json(['status' => 'ok']);
        }

        match ($event) {
            'payment.captured' => $this->payments->markPaid($order, (string) $request->input('payload.payment.entity.id')),
            'payment.failed' => $this->payments->markFailed($order),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * The payment page renders the customer's name, email and phone into the
     * Razorpay prefill, so it must not be reachable by order_number alone.
     * user_id is nullable for pre-auth-checkout legacy orders; those are
     * treated as nobody's rather than everybody's, since a null === null match
     * would hand them to any logged-out visitor.
     *
     * webhook() and callback() are deliberately exempt: both are authenticated
     * by Razorpay's own HMAC signature over the order id rather than by the
     * session, and callback() is a payment result — refusing to record it
     * because the session lapsed mid-payment would strand a real payment on a
     * page that shows no PII of its own.
     */
    private function authorizeOwnOrder(Order $order): void
    {
        abort_unless($order->user_id !== null && $order->user_id === auth()->id(), 404);
    }
}

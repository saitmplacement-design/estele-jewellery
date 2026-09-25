<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponses;
use App\Http\Api\OrderResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OldJewellery\OldJewelleryWalletSpendService;
use App\Services\Payment\PaymentManager;
use App\Services\Shipping\ShippingManager;
use App\Services\Shipping\UnserviceableAddressException;
use App\Services\WalletService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CheckoutController
{
    use ApiResponses;

    public function __construct(
        private readonly ShippingManager $shipping,
        private readonly PaymentManager $payments,
        private readonly WalletService $wallet,
    ) {}

    /**
     * GET /api/checkout/pincode/{postal_code} — auto-fill city/state from the
     * postal PIN lookup.
     */
    public function pincodeLookup(string $postalCode): JsonResponse
    {
        if (! preg_match('/^\d{6}$/', $postalCode)) {
            return $this->error('Postal code must be exactly 6 digits.', 422);
        }

        $result = Cache::remember("pincode.{$postalCode}", now()->addDays(30), function () use ($postalCode) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get("https://api.postalpincode.in/pincode/{$postalCode}")
                    ->throw()
                    ->json();
            } catch (\Throwable) {
                return null;
            }

            $postOffice = $response[0]['PostOffice'][0] ?? null;

            if (! $postOffice) {
                return null;
            }

            return [
                'city' => $postOffice['District'] ?? '',
                'state' => $postOffice['State'] ?? '',
            ];
        });

        if (! $result) {
            return $this->error('PIN code not found.', 404);
        }

        return $this->ok($result);
    }

    /**
     * POST /api/checkout — place the order. Returns the created order plus a
     * Razorpay order id when payment_method=razorpay. Cart is cleared. Requires
     * a bearer token (checkout must be authenticated, like the web flow).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->filled('customer_phone')) {
            $request->merge(['customer_phone' => Phone::normalise((string) $request->input('customer_phone'))]);
        }

        $validated = $request->validate([
            'customer_first_name' => ['required', 'string', 'max:255'],
            'customer_last_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'digits:10'],
            'shipping_address_line1' => ['required', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_state' => ['required', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'order_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cod,razorpay'],
            'wallet_amount_used' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [
            'customer_phone.digits' => 'Enter a valid 10-digit mobile number.',
        ], [
            'customer_first_name' => 'first name',
            'customer_last_name' => 'last name',
            'customer_email' => 'email',
            'customer_phone' => 'phone',
            'shipping_address_line1' => 'address',
            'shipping_address_line2' => 'address line 2',
            'shipping_city' => 'city',
            'shipping_state' => 'state',
            'shipping_postal_code' => 'PIN code',
        ]);

        $validated['customer_name'] = trim($validated['customer_first_name'].' '.$validated['customer_last_name']);
        unset($validated['customer_first_name'], $validated['customer_last_name']);

        if ($validated['payment_method'] === 'razorpay' && ! $this->payments->isOnlinePaymentEnabled()) {
            return $this->error('Online payment is currently unavailable. Please choose Cash on Delivery.', 422, [
                'payment_method' => ['Online payment is currently unavailable. Please choose Cash on Delivery.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $items = $cart->items()->with(['product', 'variant'])->get();

        if ($items->isEmpty()) {
            return $this->error('Your cart is empty.', 422);
        }

        foreach ($items as $item) {
            if ($item->quantity > $item->availableStock()) {
                return $this->error("\"{$item->product->title}\" no longer has enough stock. Please update your cart.", 422);
            }
        }

        try {
            $shippingFee = $this->shipping->quote(
                $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity),
                (int) $items->sum('quantity'),
                $validated['shipping_postal_code'],
            )['fee'];
        } catch (UnserviceableAddressException) {
            return $this->error('We\'re unable to deliver to this address. Please double check the PIN code or try a different address.', 422, [
                'shipping_postal_code' => ['We\'re unable to deliver to this address.'],
            ]);
        }

        try {
            $order = DB::transaction(function () use ($validated, $cart, $items, $shippingFee, $user) {
                $coupon = $cart->coupon_id
                    ? Coupon::where('id', $cart->coupon_id)->lockForUpdate()->first()
                    : null;

                $lockedStock = [];
                foreach ($items->sortBy('product_id') as $item) {
                    if ($item->product_variant_id) {
                        $locked = ProductVariant::where('id', $item->product_variant_id)->lockForUpdate()->first();
                    } else {
                        $locked = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    }

                    if (! $locked || $item->quantity > $locked->stock_quantity) {
                        throw new \DomainException("\"{$item->product->title}\" no longer has enough stock.");
                    }

                    $lockedStock[$item->id] = $locked;
                }

                $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);

                $discountAmount = 0.0;
                if ($coupon) {
                    $result = $coupon->isValidFor($cart);
                    $discountAmount = $result['valid'] ? $result['discount'] : 0.0;
                    if (! $result['valid']) {
                        $coupon = null;
                    }
                }
$discountAmount = min($discountAmount, $subtotal);

$payable = max(0.0, $subtotal - $discountAmount + $shippingFee);

$lockedUser = User::whereKey($user->id)->lockForUpdate()->first() ?? $user;

// Wallet use is clamped server-side to the user's REAL balance and the
// actual payable — a client-reported wallet_amount_used is never trusted.
$walletUsed = round(min(
    round((float) ($validated['wallet_amount_used'] ?? 0), 2),
    (float) $lockedUser->wallet_balance,
    $payable,
), 2);

// Razorpay can't take less than ₹1: a partial wallet use never leaves a
// smaller online remainder than that.
if ($validated['payment_method'] === 'razorpay' && $walletUsed < $payable) {
    $walletUsed = round(min($walletUsed, max(0.0, $payable - 1.0)), 2);
}

// `total` is the full order value, wallet part included — the same as
// the website's checkout, the invoice and the refund limit expect.
// What's left to pay is Order::amountDue() (total minus wallet).
$order = Order::create([
    ...$validated,
    'user_id' => $user->id,
    'order_number' => $this->generateOrderNumber(),
    'shipping_country' => 'India',
    'subtotal' => $subtotal,
    'coupon_code' => $coupon?->code,
    'discount_amount' => $discountAmount,
    'shipping_fee' => $shippingFee,
    'wallet_amount_used' => $walletUsed,
    'total' => round($payable, 2),
    'payment_status' => 'pending',
    'status' => 'placed',
]);

                foreach ($items as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_title' => $item->product->title,
                        'sku' => $item->variant?->sku ?? $item->product->sku,
                        'price' => $item->unitPrice(),
                        'quantity' => $item->quantity,
                        'subtotal' => $item->unitPrice() * $item->quantity,
                    ]);

                    $lockedStock[$item->id]->decrement('stock_quantity', $item->quantity);
                }

                if ($coupon) {
                    $coupon->increment('used_count');
                    $order->couponUsages()->create([
                        'coupon_id' => $coupon->id,
                        'discount_amount' => $discountAmount,
                    ]);
                }

                $cart->items()->delete();
                $cart->update(['coupon_id' => null]);

                // Debit happens inside the same outer transaction, against the
                // row-locked user (savepoint under MySQL). Same spend service
                // as the website, so expiring old-jewellery credits are used
                // up first rather than left to expire.
                if ($walletUsed > 0.0) {
                    app(OldJewelleryWalletSpendService::class)->applySpend($lockedUser, $walletUsed, $order);
                }

                return $order;
            }, 3);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $razorpayOrderId = null;
        if ($order->payment_method === 'razorpay' && $order->payment_status === 'pending' && $order->amountDue() > 0) {
            try {
                $razorpayOrder = $this->payments->createOrder($order);
                $order->update(['razorpay_order_id' => $razorpayOrder['id']]);
                $razorpayOrderId = $razorpayOrder['id'];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Wallet fully covered the order — nothing left to charge, mark it paid
        // (an earlier version tried to create a zero-amount Razorpay order).
        if ($order->payment_status === 'pending' && $order->amountDue() <= 0.0) {
            $order->update([
                'payment_status' => 'paid',
                'payment_reference' => 'wallet',
            ]);
        }

        return $this->created([
            'order' => OrderResource::payload($order->fresh()->load('items')),
            'razorpay' => $razorpayOrderId ? [
                'order_id' => $razorpayOrderId,
                'key_id' => $this->payments->keyId(),
                'amount' => $this->payments->amountInPaise($order->fresh()),
            ] : null,
            'payment_required' => $order->payment_method === 'razorpay' && $order->payment_status === 'pending',
        ]);
    }

    /**
     * POST /api/payment/{order_number}/retry — re-attempt Razorpay order
     * creation for an order whose gateway call failed at checkout (the order
     * itself is safe: stock/coupon already committed). Lets the app finish in
     * place instead of stranding the customer on a stuck "placing" screen.
     */
    public function retry(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->payment_method === 'razorpay', 422);
        abort_unless($order->status === 'placed', 422, 'This order is no longer payable.');
        abort_unless(in_array($order->payment_status, ['pending', 'failed'], true), 422);

        if (! $this->payments->isOnlinePaymentEnabled()) {
            return $this->error('Online payment is currently unavailable.', 422, [
                'payment_method' => ['Online payment is currently unavailable.'],
            ]);
        }

        try {
            // Reuse an already-created Razorpay order (failed payment on the
            // same order can be retried), otherwise create one fresh.
            $razorpayOrderId = filled($order->razorpay_order_id)
                ? $order->razorpay_order_id
                : $this->payments->createOrder($order)['id'];

            if (! filled($order->razorpay_order_id)) {
                $order->update([
                    'razorpay_order_id' => $razorpayOrderId,
                    'payment_status' => 'pending',
                ]);
            } else {
                $order->update(['payment_status' => 'pending']);
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->error('We couldn\'t reach the payment gateway right now. Please try again in a moment.', 502);
        }

        return $this->ok([
            'order' => OrderResource::payload($order->fresh()->load('items')),
            'razorpay' => [
                'order_id' => $razorpayOrderId,
                'key_id' => $this->payments->keyId(),
                'amount' => $this->payments->amountInPaise($order->fresh()),
            ],
            'payment_required' => true,
        ]);
    }

    /**
     * POST /api/payment/callback — verify a Razorpay signature after the
     * Flutter Razorpay SDK completes checkout.
     */
    public function paymentCallback(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if ($validated['razorpay_order_id'] !== $order->razorpay_order_id) {
            return $this->error('This payment does not match the order. Please try again.', 422);
        }

        $valid = $this->payments->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        );

        if (! $valid) {
            return $this->error('We couldn\'t verify that payment. Please try again.', 422);
        }

        if ($this->payments->recordCancelledCapture($order, $validated['razorpay_payment_id'])) {
            return $this->ok([
                'order' => OrderResource::payload($order->fresh()->load('items')),
                'message' => 'This order was cancelled before your payment completed. Your payment has been recorded; please contact support for a refund.',
            ]);
        }

        $this->payments->markPaid($order, $validated['razorpay_payment_id']);

        return $this->ok([
            'order' => OrderResource::payload($order->fresh()->load('items')),
            'message' => 'Payment verified. Order placed successfully.',
        ]);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
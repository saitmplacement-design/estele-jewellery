<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Payment\PaymentManager;
use App\Services\Shipping\ShippingManager;
use App\Services\Shipping\UnserviceableAddressException;
use App\Services\WalletService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly ShippingManager $shipping,
        private readonly PaymentManager $payments,
        private readonly WalletService $wallet,
    ) {}

    public function index(Request $request)
    {
        $cart = $this->currentCart($request);
        $cart->loadMissing('coupon');
        $items = $cart->items()->with(['product', 'variant'])->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $discount = 0.0;
        if ($cart->coupon) {
            $result = $cart->coupon->isValidFor($cart);
            $discount = $result['valid'] ? $result['discount'] : 0.0;
        }

        // No delivery pincode yet at this point (same form collects address
        // and places the order) — quote on subtotal alone. store() below
        // re-quotes with the submitted pincode for the amount actually charged.
        $shipping = $this->shipping->quote($subtotal, (int) $items->sum('quantity'));

        $addresses = auth()->check()
            ? auth()->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get()
            : collect();

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'couponCode' => $cart->coupon?->code,
            'addresses' => $addresses,
            // Passed explicitly rather than via SiteDataComposer: that composer
            // is bound to the 'layouts.app' view, which @extends only renders
            // at the very end of the compiled child template — its data is
            // visible inside layouts/app.blade.php itself, but never inside a
            // page's own @section('content') body, which executes first.
            'onlinePaymentEnabled' => $this->payments->isOnlinePaymentEnabled(),
        ]);
    }

    /**
     * Backs the PIN code field's auto-fill: looks up city/state from India
     * Post's public directory so the checkout form doesn't default every
     * order to whatever state happens to be first in a hardcoded list.
     */
    public function pincodeLookup(string $postalCode)
    {
        $result = Cache::remember("pincode.{$postalCode}", now()->addDays(30), function () use ($postalCode) {
            try {
                // Without a User-Agent header this API resets the connection
                // (PHP's cURL default sends none, unlike a browser or the curl CLI).
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
            return response()->json(['message' => 'PIN code not found.'], 404);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        // A saved address picked on the checkout page skips the manual
        // fields entirely (they're not even rendered) — so those fields are
        // only required when no address_id came through. Ownership is
        // enforced by scoping the query to the logged-in user, not just by
        // the exists:addresses rule, so one shopper can never place an
        // order against another shopper's saved address by guessing an id.
        $usingSavedAddress = $request->filled('address_id');

        if ($request->filled('customer_phone')) {
            $request->merge(['customer_phone' => Phone::normalise((string) $request->input('customer_phone'))]);
        }

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer'],
            'customer_first_name' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:255'],
            'customer_last_name' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => [Rule::requiredIf(! $usingSavedAddress), 'digits:10'],
            'shipping_address_line1' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:120'],
            'shipping_state' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:120'],
            'shipping_postal_code' => [Rule::requiredIf(! $usingSavedAddress), 'string', 'max:20'],
            'order_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cod,razorpay'],
            'wallet_amount' => ['nullable', 'numeric', 'min:0'],
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

        if ($usingSavedAddress) {
            $address = auth()->check()
                ? auth()->user()->addresses()->find($validated['address_id'])
                : null;

            if (! $address) {
                return redirect()->route('checkout.index')->withInput()
                    ->with('error', 'That saved address could not be found. Please choose another or add a new one.');
            }

            $validated['customer_name'] = trim(auth()->user()->name ?: $address->label ?: 'Customer');
            $validated['customer_phone'] = $address->phone ?: $validated['customer_phone'] ?? '';
            $validated['shipping_address_line1'] = $address->line1;
            $validated['shipping_address_line2'] = $address->line2;
            $validated['shipping_city'] = $address->city;
            $validated['shipping_state'] = $address->state;
            $validated['shipping_postal_code'] = $address->postal_code;
        } else {
            $validated['customer_name'] = trim($validated['customer_first_name'].' '.$validated['customer_last_name']);
        }

        unset($validated['customer_first_name'], $validated['customer_last_name'], $validated['address_id']);

        if (empty($validated['customer_phone'])) {
            return redirect()->route('checkout.index')->withInput()
                ->with('error', 'That saved address has no phone number. Please add one to your address book or enter one below.');
        }

        // Defends a stale page (online payment was enabled when the checkout
        // form loaded, then disabled) or a crafted POST — never silently fall
        // back to COD for a customer who didn't choose it.
        if ($validated['payment_method'] === 'razorpay' && ! $this->payments->isOnlinePaymentEnabled()) {
            return redirect()->route('checkout.index')->withInput()
                ->with('error', 'Online payment is currently unavailable. Please choose Cash on Delivery.');
        }

        $cart = $this->currentCart($request);
        $items = $cart->items()->with(['product', 'variant'])->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Fast pre-lock check: avoids opening a transaction for the common
        // case where the cart is already obviously stale. The authoritative
        // check happens inside the transaction below, under row locks.
        foreach ($items as $item) {
            if ($item->quantity > $item->availableStock()) {
                return redirect()->route('cart.index')
                    ->with('error', "\"{$item->product->title}\" no longer has enough stock. Please update your cart.");
            }
        }

        // Quoted outside the transaction below: a Shiprocket rate check is an
        // outbound HTTP call, and it shouldn't hold the stock/coupon row
        // locks open for however long that takes.
        try {
            $shippingFee = $this->shipping->quote(
                $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity),
                (int) $items->sum('quantity'),
                $validated['shipping_postal_code']
            )['fee'];
        } catch (UnserviceableAddressException) {
            return redirect()->route('checkout.index')->withInput()
                ->with('error', 'We\'re unable to deliver to this address. Please double check the PIN code or try a different address.');
        }

        try {
            $order = DB::transaction(function () use ($validated, $cart, $items, $shippingFee) {
                // Lock in a fixed order (coupon, then products by id) across
                // every checkout so two concurrent transactions can never
                // deadlock each other waiting on the same rows in reverse order.
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

                // Re-validate the applied coupon now that it (and the cart's
                // contents) are locked — never trust anything computed earlier
                // in the request. If it's since expired / been exhausted /
                // deleted / no longer meets the min order value, drop it
                // silently and let checkout proceed at full price rather than
                // failing the whole order over a stale coupon.
                $discountAmount = 0.0;
                if ($coupon) {
                    $result = $coupon->isValidFor($cart);
                    $discountAmount = $result['valid'] ? $result['discount'] : 0.0;
                    if (! $result['valid']) {
                        $coupon = null;
                    }
                }
                $discountAmount = min($discountAmount, $subtotal);

                $order = Order::create([
                    ...$validated,
                    'user_id' => auth()->id(),
                    'order_number' => $this->generateOrderNumber(),
                    'shipping_country' => 'India',
                    'subtotal' => $subtotal,
                    'coupon_code' => $coupon?->code,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => $shippingFee,
                    'total' => $subtotal - $discountAmount + $shippingFee,
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

                $walletAmountUsed = 0.0;
                if (auth()->check() && (float) ($validated['wallet_amount'] ?? 0) > 0) {
                    $walletAmountUsed = min(
                        (float) $validated['wallet_amount'],
                        (float) $order->total,
                        (float) auth()->user()->wallet_balance,
                    );

                    // Razorpay always charges $order->total in full — PaymentManager
                    // doesn't know about wallet_amount_used. Applying a PARTIAL wallet
                    // debit here while still routing the remainder through Razorpay
                    // would charge the customer twice for that portion. So for razorpay
                    // orders, only ever apply the wallet when it fully covers the total
                    // (order is marked paid below and Razorpay is skipped entirely).
                    // Otherwise leave the wallet untouched and let the full amount go
                    // through the gateway as normal. COD has no such risk (settled at
                    // delivery), so partial wallet use is always allowed there.
                    if ($validated['payment_method'] === 'razorpay' && $walletAmountUsed < (float) $order->total) {
                        $walletAmountUsed = 0.0;
                    }

                    if ($walletAmountUsed > 0) {
                        // WalletService::debit() throws \DomainException on insufficient
                        // balance. Should never actually happen here — $walletAmountUsed
                        // is already clamped to the live wallet_balance above — but if it
                        // somehow does, it must surface as a field error on the checkout
                        // form rather than the generic cart-index redirect the stock-check
                        // \DomainExceptions below use. Re-thrown as \RuntimeException so
                        // the outer catch can tell the two apart.
                        try {
                            app(\App\Services\OldJewellery\OldJewelleryWalletSpendService::class)
                                ->applySpend(auth()->user(), $walletAmountUsed, $order);
                        } catch (\DomainException $e) {
                            throw new \RuntimeException($e->getMessage(), previous: $e);
                        }

                        $order->update([
                            'wallet_amount_used' => $walletAmountUsed,
                            'payment_status' => $walletAmountUsed >= (float) $order->total ? 'paid' : $order->payment_status,
                        ]);
                    }
                }

                $cart->items()->delete();
                $cart->update(['coupon_id' => null]);

                return $order;
            }, 3);
        } catch (\RuntimeException $e) {
            return redirect()->route('checkout.index')->withInput()
                ->withErrors(['wallet_amount' => $e->getMessage()]);
        } catch (\DomainException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        if ($order->payment_status === 'paid' || $order->payment_method !== 'razorpay') {
            self::rememberPlacedOrder($request, $order);

            return redirect()->route('checkout.confirmation', $order)->with('success', 'Order placed successfully.');
        }

        // Stock/coupon are already committed above — same as COD. The
        // Razorpay order-id call is a separate outbound HTTP request and
        // deliberately happens after the transaction, so it never holds the
        // stock/coupon row locks open (same reasoning as the shipping quote
        // above). A failure here doesn't lose the order: PaymentController's
        // "show" action retries createOrder() once for a still-blank
        // razorpay_order_id before giving up.
        try {
            $razorpayOrder = $this->payments->createOrder($order);
            $order->update(['razorpay_order_id' => $razorpayOrder['id']]);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('payment.show', $order);
    }

    /**
     * Reachable by order_number alone, so it must prove the caller owns the
     * order before rendering the customer's name and full shipping address.
     * Express checkout is a genuine guest flow (routes/web.php has no auth on
     * checkout.express.*), so a just-placed order is also allowed through via
     * a session marker — see rememberPlacedOrder().
     */
    public function confirmation(Request $request, Order $order)
    {
        abort_unless(self::mayViewOrder($request, $order), 404);

        if ($order->payment_method === 'razorpay' && $order->payment_status === 'pending') {
            return redirect()->route('payment.show', $order);
        }

        $order->load('items');

        return view('checkout.confirmation', compact('order'));
    }

    private function currentCart(Request $request): Cart
    {
        return Cart::firstOrCreate(['session_id' => $request->session()->getId()]);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * A just-placed order is remembered against the session so the guest
     * express-checkout flow can land on its own confirmation page. Stored as a
     * list because the Razorpay hop returns through here a second time, and a
     * later order in the same session must not evict the earlier one.
     */
    public static function rememberPlacedOrder(Request $request, Order $order): void
    {
        $ids = (array) $request->session()->get('placed_order_ids', []);
        $ids[] = $order->id;
        $request->session()->put('placed_order_ids', array_values(array_unique(array_slice($ids, -10))));
    }

    public static function mayViewOrder(Request $request, Order $order): bool
    {
        if ($order->user_id !== null && $order->user_id === auth()->id()) {
            return true;
        }

        return in_array($order->id, (array) $request->session()->get('placed_order_ids', []), true);
    }
}

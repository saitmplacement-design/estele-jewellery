<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyCheckoutWalletUsageTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutPayload(): array
    {
        return [
            'customer_first_name' => 'Jane',
            'customer_last_name' => 'Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address_line1' => '1 Main St',
            'shipping_city' => 'Hyderabad',
            'shipping_state' => 'Telangana',
            'shipping_postal_code' => '500001',
            'payment_method' => 'cod',
        ];
    }

    public function test_partial_wallet_use_reduces_debit_and_keeps_order_pending_payment(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100]);
        [$sessionCookieName, $sessionCookieValue] = $this->seedCart($user, $this->makeProduct(500));

        $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), ['wallet_amount' => 100]));

        $response->assertRedirect();
        $order = Order::where('customer_email', 'jane@example.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('100.00', $order->wallet_amount_used);
        $this->assertSame('0.00', $user->fresh()->wallet_balance);
        $this->assertSame('pending', $order->payment_status);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'debit',
            'amount' => '100.00',
            'reason' => 'order_payment',
        ]);
    }

    public function test_wallet_amount_is_clamped_to_available_balance(): void
    {
        $user = User::factory()->create(['wallet_balance' => 30]);
        [$sessionCookieName, $sessionCookieValue] = $this->seedCart($user, $this->makeProduct(500));

        $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), ['customer_email' => 'clamp@example.com', 'wallet_amount' => 999]))
            ->assertRedirect();

        $order = Order::where('customer_email', 'clamp@example.com')->first();
        $this->assertSame('30.00', $order->wallet_amount_used);
        $this->assertSame('0.00', $user->fresh()->wallet_balance);
    }

    public function test_wallet_fully_covering_total_marks_order_paid_immediately(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1000]);
        [$sessionCookieName, $sessionCookieValue] = $this->seedCart($user, $this->makeProduct(200));

        $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), ['customer_email' => 'fullcover@example.com', 'wallet_amount' => 1000]))
            ->assertRedirect();

        $order = Order::where('customer_email', 'fullcover@example.com')->first();
        $this->assertSame((float) $order->total, (float) $order->wallet_amount_used);
        $this->assertSame('paid', $order->payment_status);
    }

    /**
     * Partial wallet use with Razorpay: the wallet is debited once, and the
     * Razorpay order is created for only the remainder (Order::amountDue()),
     * so the wallet-covered part is never charged twice. (Before, the wallet
     * was silently dropped for online payment because Razorpay was asked
     * for the full total.)
     */
    public function test_razorpay_with_partial_wallet_charges_only_the_remainder(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100]);
        $this->enableRazorpay();
        [$sessionCookieName, $sessionCookieValue] = $this->seedCart($user, $this->makeProduct(500));

        $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), [
                'customer_email' => 'razorpaypartial@example.com',
                'payment_method' => 'razorpay',
                'wallet_amount' => 100,
            ]));

        $order = Order::where('customer_email', 'razorpaypartial@example.com')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('payment.show', $order));

        $this->assertSame('100.00', $order->wallet_amount_used);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('0.00', $user->fresh()->wallet_balance);
        $this->assertSame(1, \App\Models\WalletTransaction::where('user_id', $user->id)->where('type', 'debit')->count());

        $expectedPaise = (int) round(((float) $order->total - 100) * 100);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/orders') && $request['amount'] === $expectedPaise);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/v1/orders') && $request['amount'] === (int) round((float) $order->total * 100));
    }

    /**
     * The companion case: when the wallet fully covers the total, it's safe
     * to apply even with payment_method = razorpay, since the existing
     * skip-gateway-when-paid branch means Razorpay is never actually called.
     */
    public function test_razorpay_with_wallet_fully_covering_total_still_applies_and_skips_gateway(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1000]);
        $this->enableRazorpay();
        [$sessionCookieName, $sessionCookieValue] = $this->seedCart($user, $this->makeProduct(200));

        $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), [
                'customer_email' => 'razorpayfullcover@example.com',
                'payment_method' => 'razorpay',
                'wallet_amount' => 1000,
            ]));

        $order = Order::where('customer_email', 'razorpayfullcover@example.com')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame((float) $order->total, (float) $order->wallet_amount_used);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->razorpay_order_id, 'Razorpay must never be called when the wallet fully covers the total.');
    }

    /**
     * A guest (unauthenticated) checkout has no wallet to debit from. In this
     * app /checkout sits behind the 'auth' middleware, so an unauthenticated
     * POST never even reaches CheckoutController::store() — it's redirected
     * to login before any order or wallet interaction happens. This confirms
     * that boundary holds and that posting wallet_amount as a guest cannot
     * create an order or a wallet_transactions row.
     */
    public function test_guest_checkout_ignores_wallet_amount(): void
    {
        config(['session.driver' => 'database']);
        $product = $this->makeProduct(500);

        $firstResponse = $this->get('/cart');
        $sessionCookieName = config('session.cookie');
        $sessionCookieValue = collect($firstResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $sessionCookieName)
            ->getValue();
        $sessionId = DB::table('sessions')->orderByDesc('last_activity')->value('id');

        $cart = Cart::firstOrCreate(['session_id' => $sessionId]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', array_merge($this->checkoutPayload(), [
                'customer_email' => 'guest@example.com',
                'wallet_amount' => 500,
            ]));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'), 'Guest POST must be redirected to login by the auth middleware, never reach the controller.');
        $this->assertNull(Order::where('customer_email', 'guest@example.com')->first(), 'No order should be created for an unauthenticated checkout attempt.');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function enableRazorpay(): void
    {
        Setting::updateOrCreate(['key' => 'payment_provider'], ['value' => 'razorpay']);
        config(['services.razorpay.key_id' => 'rzp_test_fake', 'services.razorpay.key_secret' => 'fake_secret']);
        Http::fake([
            '*/v1/orders' => Http::response(['id' => 'order_fake123', 'amount' => 50000, 'currency' => 'INR'], 200),
        ]);
    }

    private function makeProduct(float $price): Product
    {
        return Product::create([
            'title' => 'Wallet Test Product',
            'slug' => 'wallet-test-product-'.uniqid(),
            'sku' => 'SKU-WALLET-'.uniqid(),
            'price' => $price,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: string, 1: string} [sessionCookieName, sessionCookieValue]
     */
    private function seedCart(User $user, Product $product): array
    {
        $this->actingAs($user);
        config(['session.driver' => 'database']);

        $firstResponse = $this->get('/cart');
        $sessionCookieName = config('session.cookie');
        $sessionCookieValue = collect($firstResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $sessionCookieName)
            ->getValue();
        $sessionId = DB::table('sessions')->orderByDesc('last_activity')->value('id');

        $cart = Cart::firstOrCreate(['session_id' => $sessionId]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        return [$sessionCookieName, $sessionCookieValue];
    }
}

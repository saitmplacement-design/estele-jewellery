<?php

namespace Tests\Feature;

use App\Jobs\CancelUnpaidOnlineOrdersJob;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Shipping\ShiprocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Online payment (Razorpay) and COD end to end: the amount charged after
 * wallet use, the app's checkout/order endpoints, finding a payment the
 * site never heard about, cleaning up abandoned online orders, the COD
 * amount handed to Shiprocket, and not accepting an unpaid online order.
 */
class VerifyPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    // ---- Mobile app checkout ------------------------------------------------

    public function test_app_cod_checkout_returns_the_order_and_stores_the_full_total(): void
    {
        [$user, $token] = $this->appUser(walletBalance: 100);
        $product = $this->makeProduct(500);
        Cart::firstOrCreate(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->withToken($token)->postJson('/api/checkout', $this->appCheckoutFields([
            'payment_method' => 'cod',
            'wallet_amount_used' => 100,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.order.payment_method', 'cod')
            ->assertJsonPath('data.order.wallet_amount_used', 100)
            ->assertJsonPath('data.payment_required', false);

        $order = Order::sole();
        $this->assertSame((float) $order->subtotal + (float) $order->shipping_fee, (float) $order->total, 'total includes the wallet part, like the website');
        $this->assertSame((float) $order->total - 100, $order->amountDue());
        $this->assertEquals($order->amountDue(), $response->json('data.order.amount_due'));
        $this->assertSame('0.00', $user->fresh()->wallet_balance);
        $this->assertSame(0, Cart::where('user_id', $user->id)->first()->items()->count());
    }

    public function test_app_razorpay_checkout_charges_only_what_is_left_after_the_wallet(): void
    {
        $this->enableRazorpay();
        [$user, $token] = $this->appUser(walletBalance: 100);
        $product = $this->makeProduct(500);
        Cart::firstOrCreate(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->withToken($token)->postJson('/api/checkout', $this->appCheckoutFields([
            'payment_method' => 'razorpay',
            'wallet_amount_used' => 100,
        ]));

        $order = Order::sole();
        $expectedPaise = (int) round($order->amountDue() * 100);

        $response->assertCreated()
            ->assertJsonPath('data.payment_required', true)
            ->assertJsonPath('data.razorpay.order_id', 'order_fake123')
            ->assertJsonPath('data.razorpay.amount', $expectedPaise);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/orders') && $request['amount'] === $expectedPaise);
    }

    public function test_wallet_never_leaves_less_than_one_rupee_to_pay_online(): void
    {
        $this->enableRazorpay();
        [$user, $token] = $this->appUser(walletBalance: 499.50);
        $product = $this->makeProduct(500);
        Cart::firstOrCreate(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->withToken($token)->postJson('/api/checkout', $this->appCheckoutFields([
            'payment_method' => 'razorpay',
            'wallet_amount_used' => 499.50,
        ]))->assertCreated()->assertJsonPath('data.razorpay.amount', 100);

        $order = Order::sole();
        $this->assertSame('499.00', $order->wallet_amount_used);
        $this->assertSame(1.0, $order->amountDue());
        $this->assertSame('0.50', $user->fresh()->wallet_balance);
    }

    public function test_app_order_list_and_detail_work(): void
    {
        [$user, $token] = $this->appUser();
        $order = $this->makeOrder(['user_id' => $user->id, 'payment_method' => 'cod']);

        $this->withToken($token)->getJson('/api/account/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_number', $order->order_number);

        $this->withToken($token)->getJson('/api/account/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.items.0.title', 'Payment Test Product')
            ->assertJsonPath('data.amount_due', 590);
    }

    // ---- Payment page ----------------------------------------------------------

    public function test_payment_page_records_a_payment_that_went_through_without_the_callback(): void
    {
        $this->enableRazorpay();
        Http::fake([
            '*/v1/orders/order_abc/payments' => Http::response(['items' => [
                ['id' => 'pay_failed1', 'status' => 'failed'],
                ['id' => 'pay_ok1', 'status' => 'captured'],
            ]]),
        ]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc']);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertRedirect(route('checkout.confirmation', $order));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pay_ok1', $order->payment_reference);
    }

    public function test_payment_page_still_opens_when_razorpay_cannot_be_checked(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_abc/payments' => Http::response('down', 503)]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc']);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('Complete your payment')
            ->assertSee('₹590.00');
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_payment_page_shows_the_amount_after_wallet(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_abc/payments' => Http::response(['items' => []])]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc', 'wallet_amount_used' => 90]);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('₹500.00')
            ->assertSee('amount: 50000,', false);
    }

    public function test_cod_order_never_opens_the_online_payment_page(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'payment_method' => 'cod']);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertRedirect(route('checkout.confirmation', $order));
    }

    public function test_order_page_offers_complete_payment_for_an_unpaid_online_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Complete Payment')
            ->assertSee(route('payment.show', $order), false);

        $order->update(['payment_status' => 'paid']);

        $this->actingAs($user)->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Complete Payment');
    }

    // ---- Abandoned online orders -------------------------------------------------

    public function test_unpaid_online_order_is_cancelled_and_everything_is_given_back(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_old/payments' => Http::response(['items' => [['id' => 'pay_x', 'status' => 'failed']]])]);
        $user = User::factory()->create(['wallet_balance' => 0]);
        $order = $this->makeOrder([
            'user_id' => $user->id, 'razorpay_order_id' => 'order_old',
            'payment_status' => 'failed', 'wallet_amount_used' => 90,
        ], ageMinutes: 180);
        $stockBefore = $order->items->first()->product->fresh()->stock_quantity;

        (new CancelUnpaidOnlineOrdersJob)->handle();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertStringContainsString('Cancelled automatically', $order->admin_notes);
        $this->assertSame($stockBefore + 1, $order->items->first()->product->fresh()->stock_quantity);
        $this->assertSame('90.00', $user->fresh()->wallet_balance, 'wallet part refunded');
    }

    public function test_a_mail_server_failure_does_not_break_cancelling_or_accepting(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_old/payments' => Http::response(['items' => []])]);
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection to smtp.gmail.com timed out'));
        $user = User::factory()->create(['wallet_balance' => 0, 'email' => 'buyer@example.com']);
        $abandoned = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_old', 'wallet_amount_used' => 90], ageMinutes: 180);
        $cod = $this->makeOrder(['payment_method' => 'cod']);

        (new CancelUnpaidOnlineOrdersJob)->handle();
        $cod->update(['status' => 'accepted']);

        $this->assertSame('cancelled', $abandoned->fresh()->status);
        $this->assertSame('90.00', $user->fresh()->wallet_balance);
        $this->assertSame('accepted', $cod->fresh()->status);
    }

    public function test_cleanup_marks_paid_instead_of_cancelling_when_razorpay_has_the_payment(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_old/payments' => Http::response(['items' => [['id' => 'pay_late', 'status' => 'captured']]])]);
        $order = $this->makeOrder(['razorpay_order_id' => 'order_old'], ageMinutes: 180);

        (new CancelUnpaidOnlineOrdersJob)->handle();

        $order->refresh();
        $this->assertSame('placed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pay_late', $order->payment_reference);
    }

    public function test_cleanup_leaves_orders_alone_when_razorpay_cannot_be_reached_or_it_is_too_early(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/*' => Http::response('down', 503)]);
        $unreachable = $this->makeOrder(['razorpay_order_id' => 'order_old'], ageMinutes: 180);
        $recent = $this->makeOrder(['razorpay_order_id' => null], ageMinutes: 30);
        $cod = $this->makeOrder(['payment_method' => 'cod'], ageMinutes: 600);
        $authorized = $this->makeOrder(['razorpay_order_id' => 'order_auth'], ageMinutes: 180);
        Http::fake(['*/v1/orders/order_auth/payments' => Http::response(['items' => [['id' => 'pay_a', 'status' => 'authorized']]])]);

        (new CancelUnpaidOnlineOrdersJob)->handle();

        foreach ([$unreachable, $recent, $cod, $authorized] as $order) {
            $this->assertSame('placed', $order->fresh()->status, $order->order_number.' must not be cancelled');
        }
    }

    public function test_cleanup_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('orders:cancel-unpaid-online');
    }

    // ---- Admin + shipping -----------------------------------------------------------

    public function test_unpaid_online_order_cannot_be_accepted_but_paid_and_cod_orders_can(): void
    {
        $unpaid = $this->makeOrder();

        try {
            $unpaid->update(['status' => 'accepted']);
            $this->fail('An unpaid online order was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('has not been paid yet', $e->getMessage());
        }
        $this->assertSame('placed', $unpaid->fresh()->status);

        $paid = $this->makeOrder(['payment_status' => 'paid']);
        $paid->update(['status' => 'accepted']);
        $this->assertSame('accepted', $paid->fresh()->status);

        $cod = $this->makeOrder(['payment_method' => 'cod']);
        $cod->update(['status' => 'accepted']);
        $this->assertSame('accepted', $cod->fresh()->status);
    }

    public function test_shiprocket_is_told_the_real_cod_amount(): void
    {
        config(['services.shiprocket.email' => 'fake@example.com', 'services.shiprocket.password' => 'fake']);
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'faketoken']),
            '*/orders/create/adhoc' => Http::response(['shipment_id' => 1, 'awb_code' => 'AWB1']),
        ]);
        $order = $this->makeOrder([
            'payment_method' => 'cod', 'subtotal' => 1000, 'discount_amount' => 100,
            'shipping_fee' => 50, 'total' => 950, 'wallet_amount_used' => 200,
        ]);

        app(ShiprocketService::class)->createShipment($order);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/orders/create/adhoc')) {
                return false;
            }
            $collect = $request['sub_total'] + $request['shipping_charges'] - $request['total_discount'];

            return $request['payment_method'] === 'COD' && abs($collect - 750) < 0.001;
        });
    }

    // ---- Never stuck: visible errors, COD fallback, diagnostics ----------------------

    public function test_an_unexpected_checkout_error_is_shown_instead_of_a_silent_reload(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100]);
        [$cookieName, $cookieValue] = $this->seedWebCart($user);
        $this->mock(\App\Services\OldJewellery\OldJewelleryWalletSpendService::class)
            ->shouldReceive('applySpend')->andThrow(new \RuntimeException('SQLSTATE[HY000]: General error'));

        $response = $this->withUnencryptedCookie($cookieName, $cookieValue)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/checkout', $this->webCheckoutFields(['payment_method' => 'cod', 'wallet_amount' => 50]));

        $response->assertRedirect(route('checkout.index'));
        $this->assertStringContainsString('couldn\'t place your order', (string) $response->getSession()->get('error'));
        $this->assertFalse(optional($response->getSession()->get('errors'))->has('wallet_amount') ?? false);
        $this->assertSame(0, Order::count(), 'the failed order is rolled back');
        $this->assertSame('100.00', $user->fresh()->wallet_balance);
    }

    public function test_a_wallet_problem_is_shown_on_screen(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100]);
        [$cookieName, $cookieValue] = $this->seedWebCart($user);
        $this->mock(\App\Services\OldJewellery\OldJewelleryWalletSpendService::class)
            ->shouldReceive('applySpend')->andThrow(new \DomainException('Wallet balance is insufficient for this debit.'));

        $response = $this->withUnencryptedCookie($cookieName, $cookieValue)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/checkout', $this->webCheckoutFields(['payment_method' => 'cod', 'wallet_amount' => 50]));

        $response->assertRedirect(route('checkout.index'));
        $this->assertSame('Wallet balance is insufficient for this debit.', $response->getSession()->get('error'));
        $this->assertSame(0, Order::count());
    }

    public function test_payment_unavailable_page_offers_cash_on_delivery(): void
    {
        $this->enableRazorpay(Http::response(['error' => ['description' => 'Authentication failed']], 401));
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => null]);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('Payment temporarily unavailable')
            ->assertSee('Pay with Cash on Delivery instead')
            ->assertSee(route('payment.cod', $order), false);
    }

    public function test_customer_can_switch_a_stuck_online_order_to_cash_on_delivery(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => null, 'payment_status' => 'failed']);

        $this->actingAs($user)->post(route('payment.cod', $order))
            ->assertRedirect(route('checkout.confirmation', $order));

        $order->refresh();
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('placed', $order->status);

        // Now a normal COD order: the admin can accept it and the cleanup ignores it.
        $order->update(['status' => 'accepted']);
        $this->assertSame('accepted', $order->fresh()->status);
    }

    public function test_switching_to_cod_records_the_payment_instead_when_razorpay_already_has_it(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_abc/payments' => Http::response(['items' => [['id' => 'pay_done', 'status' => 'captured']]])]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc']);

        $this->actingAs($user)->post(route('payment.cod', $order))
            ->assertRedirect(route('checkout.confirmation', $order));

        $order->refresh();
        $this->assertSame('razorpay', $order->payment_method);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_switching_to_cod_waits_when_razorpay_cannot_be_checked(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_abc/payments' => Http::response('down', 503)]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc']);

        $this->actingAs($user)->post(route('payment.cod', $order))
            ->assertRedirect(route('payment.show', $order));

        $this->assertSame('razorpay', $order->fresh()->payment_method);
    }

    public function test_only_the_owner_can_switch_an_order_to_cod(): void
    {
        $order = $this->makeOrder(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())->post(route('payment.cod', $order))->assertNotFound();
        $this->assertSame('razorpay', $order->fresh()->payment_method);
    }

    public function test_payment_page_offers_cash_on_delivery_too(): void
    {
        $this->enableRazorpay();
        Http::fake(['*/v1/orders/order_abc/payments' => Http::response(['items' => []])]);
        $user = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $user->id, 'razorpay_order_id' => 'order_abc']);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('Pay with Cash on Delivery instead');
    }

    public function test_razorpay_check_reports_a_working_setup(): void
    {
        $this->enableRazorpay();

        $this->artisan('razorpay:check')
            ->expectsOutputToContain('payment_provider setting: razorpay')
            ->expectsOutputToContain('Razorpay accepted a test order (order_fake123)')
            ->assertSuccessful();
    }

    public function test_razorpay_check_explains_wrong_keys(): void
    {
        $this->enableRazorpay(Http::response(['error' => ['description' => 'Authentication failed']], 401));

        $this->artisan('razorpay:check')
            ->expectsOutputToContain('Razorpay refused the test order (HTTP 401): Authentication failed')
            ->expectsOutputToContain('key id and key secret don\'t match')
            ->assertFailed();
    }

    public function test_razorpay_check_explains_missing_setup(): void
    {
        config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

        $this->artisan('razorpay:check')
            ->expectsOutputToContain('Online payment is switched off')
            ->expectsOutputToContain('RAZORPAY_KEY_ID and/or RAZORPAY_KEY_SECRET are missing')
            ->assertFailed();
    }

    // ---- Helpers ------------------------------------------------------------------

    private function webCheckoutFields(array $overrides = []): array
    {
        return array_merge([
            'customer_first_name' => 'Asha',
            'customer_last_name' => 'Rao',
            'customer_email' => 'asha@example.com',
            'customer_phone' => '9876543210',
            'shipping_address_line1' => '12 MG Road',
            'shipping_city' => 'Hyderabad',
            'shipping_state' => 'Telangana',
            'shipping_postal_code' => '500001',
            'payment_method' => 'cod',
        ], $overrides);
    }

    /** @return array{0: string, 1: string} [sessionCookieName, sessionCookieValue] */
    private function seedWebCart(User $user): array
    {
        $this->actingAs($user);
        config(['session.driver' => 'database']);

        $first = $this->get('/cart');
        $name = config('session.cookie');
        $value = collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === $name)->getValue();
        $sessionId = \Illuminate\Support\Facades\DB::table('sessions')->orderByDesc('last_activity')->value('id');

        Cart::firstOrCreate(['session_id' => $sessionId])
            ->items()->create(['product_id' => $this->makeProduct(500)->id, 'quantity' => 1]);

        return [$name, $value];
    }

    /** Http fakes match first-registered-first, so a test wanting a different create-order reply passes it here. */
    private function enableRazorpay(?\GuzzleHttp\Promise\PromiseInterface $createOrderResponse = null): void
    {
        Setting::updateOrCreate(['key' => 'payment_provider'], ['value' => 'razorpay']);
        config(['services.razorpay.key_id' => 'rzp_test_fake', 'services.razorpay.key_secret' => 'fake_secret']);
        Http::fake(['*/v1/orders' => $createOrderResponse ?? Http::response(['id' => 'order_fake123', 'amount' => 1, 'currency' => 'INR'])]);
    }

    /** @return array{0: User, 1: string} */
    private function appUser(float $walletBalance = 0): array
    {
        $user = User::factory()->create(['wallet_balance' => $walletBalance]);

        return [$user, PersonalAccessToken::issue($user)];
    }

    private function appCheckoutFields(array $overrides = []): array
    {
        return array_merge([
            'customer_first_name' => 'Asha',
            'customer_last_name' => 'Rao',
            'customer_email' => 'asha@example.com',
            'customer_phone' => '9876543210',
            'shipping_address_line1' => '12 MG Road',
            'shipping_city' => 'Hyderabad',
            'shipping_state' => 'Telangana',
            'shipping_postal_code' => '500001',
        ], $overrides);
    }

    private function makeProduct(float $price): Product
    {
        return Product::create([
            'title' => 'Payment Test Product',
            'slug' => 'payment-test-product-'.uniqid(),
            'sku' => 'SKU-PAY-'.uniqid(),
            'price' => $price,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeOrder(array $overrides = [], int $ageMinutes = 0): Order
    {
        $product = $this->makeProduct(590);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-TEST-'.strtoupper(uniqid()),
            'customer_name' => 'Asha Rao',
            'customer_email' => 'asha@example.com',
            'customer_phone' => '9876543210',
            'shipping_address_line1' => '12 MG Road',
            'shipping_city' => 'Hyderabad',
            'shipping_state' => 'Telangana',
            'shipping_postal_code' => '500001',
            'shipping_country' => 'India',
            'subtotal' => 590,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => 590,
            'wallet_amount_used' => 0,
            'payment_method' => 'razorpay',
            'payment_status' => 'pending',
            'status' => 'placed',
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'sku' => $product->sku,
            'price' => 590,
            'quantity' => 1,
            'subtotal' => 590,
        ]);

        if ($ageMinutes) {
            Order::whereKey($order->id)->update(['created_at' => now()->subMinutes($ageMinutes)]);
        }

        return $order->fresh('items.product');
    }
}

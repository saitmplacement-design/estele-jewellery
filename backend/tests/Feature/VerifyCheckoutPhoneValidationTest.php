<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The checkout phone field used to accept any text up to 20 characters. It
 * now takes a 10-digit mobile number only; formatting a shopper commonly
 * types or pastes ("+91 98765-43210") is tidied first rather than rejected.
 */
class VerifyCheckoutPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalise_strips_formatting_and_country_code_but_keeps_letters(): void
    {
        $this->assertSame('9876543210', Phone::normalise('+91 98765-43210'));
        $this->assertSame('9876543210', Phone::normalise('919876543210'));
        $this->assertSame('9876543210', Phone::normalise('098765 43210'));
        $this->assertSame('9876543210', Phone::normalise('(98765) 43210'));
        $this->assertSame('98765abcde', Phone::normalise('98765 abcde'));
    }

    /**
     * Validation runs before the cart is read, so these need no cart (and
     * stay on the default array session, where flashed errors are visible).
     */
    public function test_checkout_rejects_a_phone_with_letters(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->from('/checkout')
            ->post('/checkout', $this->orderFields(['customer_phone' => '98765abcde']))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['customer_phone' => 'Enter a valid 10-digit mobile number.']);

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_a_phone_that_is_too_short(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->from('/checkout')
            ->post('/checkout', $this->orderFields(['customer_phone' => '98765']))
            ->assertSessionHasErrors(['customer_phone' => 'Enter a valid 10-digit mobile number.']);

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_stores_a_formatted_number_as_ten_digits(): void
    {
        [$cookieName, $cookieValue] = $this->seedCart(User::factory()->create());

        $this->withUnencryptedCookie($cookieName, $cookieValue)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/checkout', $this->orderFields(['customer_phone' => '+91 98765-43210']))
            ->assertSessionHasNoErrors();

        $this->assertSame('9876543210', Order::sole()->customer_phone);
    }

    public function test_checkout_phone_input_is_digits_only(): void
    {
        [$cookieName, $cookieValue] = $this->seedCart(User::factory()->create());

        $this->withUnencryptedCookie($cookieName, $cookieValue)
            ->get('/checkout')
            ->assertOk()
            ->assertSee('name="customer_phone" type="tel"', false)
            ->assertSee('pattern="[0-9]{10}"', false)
            ->assertSee('data-digits-only data-max-digits="10"', false);
    }

    public function test_api_checkout_rejects_a_phone_with_letters(): void
    {
        $token = PersonalAccessToken::issue(User::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/checkout', [
                'customer_first_name' => 'Asha',
                'customer_last_name' => 'Rao',
                'customer_email' => 'asha@example.com',
                'customer_phone' => 'call me',
                'shipping_address_line1' => '12 MG Road',
                'shipping_city' => 'Hyderabad',
                'shipping_state' => 'Telangana',
                'shipping_postal_code' => '500001',
                'payment_method' => 'cod',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_phone.0', 'Enter a valid 10-digit mobile number.');
    }

    private function orderFields(array $overrides = []): array
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

    /**
     * @return array{0: string, 1: string} [sessionCookieName, sessionCookieValue]
     */
    private function seedCart(User $user): array
    {
        $this->actingAs($user);
        config(['session.driver' => 'database']);

        $firstResponse = $this->get('/cart');
        $cookieName = config('session.cookie');
        $cookieValue = collect($firstResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $cookieName)
            ->getValue();
        $sessionId = DB::table('sessions')->orderByDesc('last_activity')->value('id');

        $product = Product::create([
            'title' => 'Phone Test Product',
            'slug' => 'phone-test-product-'.uniqid(),
            'sku' => 'SKU-PHONE-'.uniqid(),
            'price' => 500,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Cart::firstOrCreate(['session_id' => $sessionId])
            ->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        return [$cookieName, $cookieValue];
    }
}

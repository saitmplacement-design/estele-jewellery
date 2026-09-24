<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Below md, several pages keep their primary action in a bar pinned to the
 * bottom edge, rendered through the layout's @yield('sticky_bar'). On the
 * product page that bar is the ONLY add-to-bag control on a phone, so when the
 * layout stopped yielding it, mobile shoppers could not buy anything and no
 * test noticed. These lock the bars into the rendered HTML.
 */
class MobileStickyBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_renders_the_mobile_buy_bar(): void
    {
        $product = $this->makeProduct();

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('class="buybar md:hidden"', false);
        $response->assertSeeInOrder(['class="buybar md:hidden"', 'Buy Now', 'Add to Bag'], false);
    }

    public function test_category_page_renders_the_mobile_sort_and_filter_bar(): void
    {
        $category = Category::create(['name' => 'Bangles', 'slug' => 'bangles']);

        $response = $this->get(route('categories.show', $category));

        $response->assertOk();
        $response->assertSee('listing-bar', false);
        $response->assertSee('data-sheet-open="sort"', false);
        $response->assertSee('data-filter-open', false);
    }

    public function test_checkout_renders_a_mobile_place_order_bar_bound_to_the_checkout_form(): void
    {
        $this->actingAs(User::factory()->create());
        config(['session.driver' => 'database']);

        $firstResponse = $this->get('/cart');
        $sessionCookieName = config('session.cookie');
        $sessionCookieValue = collect($firstResponse->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $sessionCookieName)
            ->getValue();
        $sessionId = DB::table('sessions')->orderByDesc('last_activity')->value('id');

        Cart::firstOrCreate(['session_id' => $sessionId])
            ->items()->create(['product_id' => $this->makeProduct()->id, 'quantity' => 1]);

        $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookieValue)->get('/checkout');

        $response->assertOk();
        $response->assertSee('<form id="checkout-form"', false);
        $response->assertSee('form="checkout-form">Place Order</button>', false);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'title' => 'Sticky Bar Necklace',
            'slug' => 'sticky-bar-necklace-'.uniqid(),
            'sku' => 'SKU-STICKY-'.uniqid(),
            'price' => 1299,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }
}

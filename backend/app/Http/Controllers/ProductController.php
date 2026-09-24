<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $relatedProducts = Cache::tags(['product:' . $product->id])->remember(
            "product.{$product->id}.show",
            now()->addMinutes(15),
            function () use ($product) {
                $product->load('variants', 'categories');

                return Product::where('is_active', true)
                    ->where('id', '!=', $product->id)
                    ->with('media')
                    ->withCount('approvedReviews')
                    ->withAvg('approvedReviews', 'rating')
                    ->whereHas('categories', function ($query) use ($product) {
                        $query->whereIn('categories.id', $product->categories->pluck('id'));
                    })
                    ->take(8)
                    ->get();
            }
        );

        $product->loadMissing('variants', 'categories');

        // Star breakdown for the summary bars (5 → 1), from approved reviews only.
        $ratingBreakdown = $product->approvedReviews()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        // Optional "show only N-star reviews" filter from the summary chips.
        $reviewRating = (int) $request->query('review_rating');
        $reviewRating = $reviewRating >= 1 && $reviewRating <= 5 ? $reviewRating : null;

        // Newest first by the date shown on the card (an admin-set
        // review_date, else when it was submitted).
        $reviews = $product->approvedReviews()
            ->with('media')
            ->when($reviewRating, fn ($query) => $query->where('rating', $reviewRating))
            ->orderByRaw('COALESCE(review_date, DATE(created_at)) DESC')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'reviews_page')
            ->withQueryString()
            ->fragment('reviews');

        // Every customer photo across this product's approved reviews, for
        // the "Customer photos" strip above the list.
        $reviewPhotos = Media::query()
            ->where('model_type', (new Review)->getMorphClass())
            ->where('collection_name', 'photos')
            ->whereIn('model_id', $product->approvedReviews()->select('id'))
            ->latest('id')
            ->take(12)
            ->get();

        return view('products.show', compact('product', 'relatedProducts', 'reviews', 'ratingBreakdown', 'reviewRating', 'reviewPhotos'));
    }

    /**
     * Saved items live in the visitor's own browser, not the database, so the
     * server has no way to know which products to send. It sends the active
     * catalogue and app.js hides every card the visitor never saved.
     */
    public function wishlist()
    {
        $products = Product::where('is_active', true)
            ->with('media')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest()
            ->get();

        return view('products.wishlist', compact('products'));
    }
}

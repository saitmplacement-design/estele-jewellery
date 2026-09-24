<?php

namespace App\Observers;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;

class ReviewObserver
{
    public function saved(Review $review): void
    {
        $this->flush($review);
    }

    public function deleted(Review $review): void
    {
        $this->flush($review);
    }

    /**
     * A review changes the product's rating everywhere it's listed, not only
     * on its own page: the homepage and its category listings are cached
     * with the rating baked into each card, so clear those too.
     */
    private function flush(Review $review): void
    {
        $tags = ['product:'.$review->product_id, 'home'];

        foreach ($review->product?->categories()->pluck('categories.id') ?? [] as $categoryId) {
            $tags[] = 'category:'.$categoryId;
        }

        Cache::tags($tags)->flush();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        // Hidden honeypot field — real visitors never fill it in, bots
        // filling every input on the form will.
        abort_if(filled($request->input('website')), 422);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            // Signed-in shoppers are identified by their account (phone-OTP
            // accounts often have no email), so email is only asked of guests.
            'customer_email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        // "Verified purchase" = a non-cancelled order under this email that
        // actually contains this product — doesn't require delivery, matching
        // how most storefronts badge it as soon as the order is confirmed.
        $user = $request->user();
        $email = $validated['customer_email'] ?? $user?->email;

        $matchingOrder = Order::query()
            ->where(function ($query) use ($user, $email) {
                if ($email) {
                    $query->where('customer_email', $email);
                }
                if ($user) {
                    $query->orWhere('user_id', $user->id);
                }
                if (! $email && ! $user) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->latest()
            ->first();

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'order_id' => $matchingOrder?->id,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $email,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'status' => 'pending',
            'is_verified_purchase' => $matchingOrder !== null,
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $review->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->to(url()->previous().'#reviews')->with('review_success', 'Thanks for your review! It will appear here once approved.');
    }
}

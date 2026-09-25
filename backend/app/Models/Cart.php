<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_id',
    ];

    /**
     * carts.session_id is NOT NULL and unique, but the app keys a signed-in
     * customer's cart by user_id alone (Api\CartController,
     * Api\CheckoutController), so creating one failed outright. Such a cart
     * gets a key of its own that no browser session id (40 random
     * characters) can ever collide with.
     */
    protected static function booted(): void
    {
        static::creating(function (Cart $cart) {
            if (blank($cart->session_id) && $cart->user_id) {
                $cart->session_id = 'user-'.$cart->user_id;
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Merge quantity of every line item from $source into $target, then delete
     * $source. Used to fold a guest cart (keyed by a device cart-token) into a
     * user's cart on login/registration — same merge-firstOrNew shape as
     * CartController::store().
     */
    public static function mergeCarts(Cart $source, Cart $target): void
    {
        foreach ($source->items as $item) {
            $existing = $target->items()->firstOrNew([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
            ]);

            $stock = $item->availableStock();
            $existing->quantity = min($stock, ($existing->exists ? $existing->quantity : 0) + $item->quantity);
            $existing->save();
        }

        $source->delete();
    }

    /**
     * Re-keys a guest cart onto a new session ID after login. Carts are
     * looked up purely by session_id (see CartController::currentCart), and
     * every login path in this app calls session()->regenerate() for the
     * standard fixation-attack reason — which immediately changes what
     * session()->getId() returns. Without this, a guest who adds an item via
     * Buy It Now, gets sent through OTP login, and lands back on checkout
     * would find an empty cart: the item is still there, just filed under a
     * session_id nothing points to anymore.
     *
     * If a cart already exists under the new session_id (rare — e.g. a device
     * with a leftover authenticated session from a previous user), merges
     * quantities into it instead of overwriting, same firstOrNew+add shape as
     * CartController::store().
     */
    public static function transferSession(string $oldSessionId, string $newSessionId): void
    {
        if ($oldSessionId === $newSessionId) {
            return;
        }

        $old = static::where('session_id', $oldSessionId)->first();
        if (! $old) {
            return;
        }

        $existing = static::where('session_id', $newSessionId)->first();

        if (! $existing) {
            $old->update(['session_id' => $newSessionId]);

            return;
        }

        foreach ($old->items as $item) {
            $target = $existing->items()->firstOrNew([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
            ]);
            $target->quantity = ($target->exists ? $target->quantity : 0) + $item->quantity;
            $target->save();
        }

        $old->delete();
    }
}

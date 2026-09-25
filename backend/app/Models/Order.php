<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    /**
     * Valid next-status moves. Enforced here (not just in the admin UI) so a
     * direct API/tinker/bulk-action update can't skip or reverse the pipeline.
     */
    public const ALLOWED_TRANSITIONS = [
        'placed' => ['accepted', 'cancelled'],
        'accepted' => ['packed', 'cancelled'],
        'packed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public const RESTOCKING_STATUSES = ['cancelled', 'returned'];

    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'order_note',
        'subtotal',
        'coupon_code',
        'discount_amount',
        'shipping_fee',
        'total',
        'wallet_amount_used',
        'payment_method',
        'payment_status',
        'payment_reference',
        'razorpay_order_id',
        'refunded_amount',
        'refund_reason',
        'status',
        'tracking_number',
        'carrier',
        'shiprocket_shipment_id',
        'shiprocket_awb_code',
        'tracking_status',
        'tracking_synced_at',
        'admin_notes',
        'cancellation_requested_at',
        'cancellation_reason',
    ];

    /**
     * Statuses a customer can still request cancellation/return from — mirrors
     * ALLOWED_TRANSITIONS but from the customer's side: once shipped, "cancel"
     * isn't offered (packed/placed only), "return" only after delivered.
     */
    public const CANCELLABLE_STATUSES = ['placed', 'accepted', 'packed'];

    public const RETURNABLE_STATUSES = ['delivered'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'wallet_amount_used' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'tracking_synced_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
        ];
    }

    /**
     * Whether the customer can currently submit a cancellation/return request
     * — false once one is already pending (cancellation_requested_at set) or
     * the order is past the window (see CANCELLABLE_STATUSES/RETURNABLE_STATUSES).
     */
    public function canRequestCancellation(): bool
    {
        return $this->cancellation_requested_at === null
            && in_array($this->status, [...self::CANCELLABLE_STATUSES, ...self::RETURNABLE_STATUSES], true);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function rewardSubmission(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RewardSubmission::class);
    }

    protected static function booted(): void
    {
        // Same reason as User::booted(): the submission's video must go with it.
        static::deleting(function (Order $order) {
            $order->rewardSubmission?->delete();
        });

        static::updating(function (Order $order) {
            if (! $order->isDirty('status')) {
                return;
            }

            $from = $order->getOriginal('status');
            $to = $order->status;

            // Unknown legacy value on $from: don't block, just don't apply
            // the transition side effects below either.
            if (! array_key_exists($from, self::ALLOWED_TRANSITIONS)) {
                return;
            }

            if (! in_array($to, self::ALLOWED_TRANSITIONS[$from], true)) {
                throw ValidationException::withMessages([
                    'status' => "Order cannot move from \"{$from}\" to \"{$to}\".",
                ]);
            }

            // Accepting is what sends the "packed and heading to shipping"
            // email/WhatsApp, so an online order must be paid first — an
            // abandoned Razorpay checkout would otherwise be packed and
            // shipped for free. (An admin who confirmed the payment another
            // way can set payment_status to paid in the same save.)
            if ($to === 'accepted' && $order->payment_method === 'razorpay' && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'This online order has not been paid yet, so it cannot be accepted.',
                ]);
            }

            if (in_array($to, self::RESTOCKING_STATUSES, true)) {
                $order->restock();
            }

            $order->releaseCouponUsageIfCancelledUnpaid();

            if ($to === 'delivered' && $order->payment_method === 'cod' && $order->payment_status === 'pending') {
                $order->payment_status = 'paid';
            }
        });

        // Once accepted, the customer gets the "packed and ready for shipping"
        // notice and the invoice becomes downloadable (see OrdersTable::streamInvoice
        // visibility). Sent from `updated` (after commit) so it never fires if the
        // transition guard above rejects the move.
        static::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->status === 'accepted' && filled($order->customer_email)) {
                // Sent inline on the live server (no queue worker); a mail
                // outage must not error the admin's already-saved change.
                try {
                    \Illuminate\Support\Facades\Mail::to($order->customer_email)->queue(new \App\Mail\OrderPacked($order));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if ($order->wasChanged('status') && $order->status === 'accepted' && filled($order->customer_phone)) {
                // A WhatsApp outage must never undo or block the status change
                // the admin just made — report it and move on.
                try {
                    app(\App\Services\WhatsApp\WhatsAppGateway::class)->send(
                        $order->customer_phone,
                        "Your Estele order {$order->order_number} is packed and heading to shipping — you can track it from your account. Order total: ₹".number_format((float) $order->total, 2),
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        // Runs post-commit (updating() cannot be used here — WalletService::credit()
        // opens and commits its OWN transaction, so crediting the wallet from a
        // pre-commit hook could leave the wallet credited even if the outer status
        // update itself later rolled back). wasChanged() (not isDirty()) is the
        // correct check here: by the time `updated` fires, dirty-tracking has
        // already been cleared, but wasChanged() still reports what the save
        // actually persisted.
        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }

            if (
                in_array($order->status, self::RESTOCKING_STATUSES, true)
                && (float) $order->wallet_amount_used > 0
                && $order->user_id
            ) {
                app(\App\Services\WalletService::class)->credit(
                    $order->user,
                    (float) $order->wallet_amount_used,
                    'order_refund',
                    $order,
                );
            }
        });
    }

    /**
     * A cancelled order that was never paid for frees its coupon slot: the
     * order is dead and no revenue ever landed, so counting it against the
     * coupon's usage_limit would let abandoned carts exhaust a promo the
     * store never profited from (and an auto-cancelled customer who reorders
     * would find the code "maxed out"). Paid cancellations and returns keep
     * their usage — the coupon genuinely helped close that sale.
     *
     * Runs inside the same `updating` hook as restock(), so it only fires on
     * the single live status transition (cancelled/returned are terminal, so
     * it can never fire twice for the same order). Idempotent and floor-safe:
     * it never pushes used_count below zero. Fires `updated` on the coupon
     * (which clears the storefront's public-coupon cache, same as the
     * increment at checkout).
     */
    protected function releaseCouponUsageIfCancelledUnpaid(): void
    {
        if ($this->status !== 'cancelled' || ! in_array($this->payment_status, ['pending', 'failed'], true)) {
            return;
        }

        $usage = $this->couponUsages()->first();

        if (! $usage) {
            return;
        }

        Coupon::whereKey($usage->coupon_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');
    }

    /**
     * Puts each line item's quantity back into stock. Only ever called from
     * the transition guard above, which by construction only allows this to
     * fire once per order (cancelled/returned are terminal — no further
     * status change, and thus no repeat restock, is possible afterward).
     */
    protected function restock(): void
    {
        $this->loadMissing('items.product', 'items.variant');

        foreach ($this->items as $item) {
            if ($item->variant) {
                $item->variant->increment('stock_quantity', $item->quantity);
            } elseif ($item->product) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }
    }

    /**
     * Records a (possibly partial) refund against the order. Callers must
     * validate $amount against the remaining refundable balance themselves
     * (EditOrder's "Refund" action does this before calling in) — this
     * method only applies the already-validated amount and flips
     * payment_status accordingly.
     */
    /**
     * The gateway-refundable total: everything except the part the customer
     * paid out of their wallet balance.
     */
    public function maxRefundableAmount(): float
    {
        return $this->amountDue();
    }

    /**
     * What the customer still pays after the wallet: charged through
     * Razorpay for online orders, collected by the courier for COD. `total`
     * always includes the wallet-paid part (web and app checkout alike).
     */
    public function amountDue(): float
    {
        return round(max(0.0, (float) $this->total - (float) $this->wallet_amount_used), 2);
    }

    public function applyRefund(float $amount, string $reason): void
    {
        $newRefunded = (float) $this->refunded_amount + $amount;

        $this->update([
            'refunded_amount' => $newRefunded,
            'refund_reason' => $reason,
            // Compare against what is actually refundable through the gateway,
            // not the full total: the wallet-paid portion was never charged, so
            // measuring against total left every wallet-paid order stuck on
            // 'partially_refunded' even once it was refunded in full.
            'payment_status' => $newRefunded >= $this->maxRefundableAmount() ? 'refunded' : 'partially_refunded',
        ]);
    }
}

<?php

namespace App\Http\Api;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * The JSON shape of an order for the mobile app: checkout, payment
 * callback/retry and the account order list/detail all return this, so the
 * app reads one model everywhere.
 *
 * `total` is the full order value (wallet part included); `amount_due` is
 * what's left to pay after the wallet — the Razorpay charge for an online
 * order, the cash collected on delivery for COD.
 */
class OrderResource
{
    public static function payload(Order $order): array
    {
        $order->loadMissing('items.product.media');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'payment_reference' => $order->payment_reference,
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'coupon_code' => $order->coupon_code,
            'shipping_fee' => (float) $order->shipping_fee,
            'wallet_amount_used' => (float) $order->wallet_amount_used,
            'total' => (float) $order->total,
            'amount_due' => $order->amountDue(),
            'refunded_amount' => (float) $order->refunded_amount,
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'shipping_address' => [
                'line1' => $order->shipping_address_line1,
                'line2' => $order->shipping_address_line2,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'postal_code' => $order->shipping_postal_code,
                'country' => $order->shipping_country,
            ],
            'order_note' => $order->order_note,
            'tracking' => [
                'number' => $order->tracking_number,
                'carrier' => $order->carrier,
                'awb_code' => $order->shiprocket_awb_code,
                'status' => $order->tracking_status,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_slug' => $item->product?->slug,
                'title' => $item->product_title,
                'sku' => $item->sku,
                'price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'image' => $item->product?->getFirstMediaUrl('gallery', 'card') ?: null,
            ])->values()->all(),
            'can_request_cancellation' => $order->canRequestCancellation(),
            'cancellation_requested_at' => $order->cancellation_requested_at?->toIso8601String(),
            'payment_required' => $order->payment_method === 'razorpay'
                && in_array($order->payment_status, ['pending', 'failed'], true)
                && $order->status === 'placed',
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}

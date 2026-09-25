<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Real Shiprocket API client. Only ever consulted when SHIPROCKET_EMAIL and
 * SHIPROCKET_PASSWORD are set (see isConfigured()) — ShippingManager falls
 * back to FlatRateCalculator otherwise, so this class is safe to leave
 * wired in before a Shiprocket account exists.
 *
 * createShipment()/trackShipment() aren't called from anywhere yet —
 * fulfilment is still manual (OrderForm's tracking_number/carrier fields).
 * They exist as the extension point for a future "Create Shipment"/"Refresh
 * Tracking" admin action, per the spec's "config change, not a rewrite"
 * requirement for swapping in courier automation later.
 */
class ShiprocketService implements ShippingRateCalculator
{
    private const BASE_URL = 'https://apiv2.shiprocket.in/v1/external';

    public function isConfigured(): bool
    {
        return filled(config('services.shiprocket.email')) && filled(config('services.shiprocket.password'));
    }

    public function quote(float $cartSubtotal, int $itemCount, ?string $postalCode = null): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Shiprocket is not configured.');
        }

        if (! $postalCode) {
            throw new \RuntimeException('A delivery postal code is required for a Shiprocket rate check.');
        }

        $settings = $this->settings();
        $pickupPincode = $settings['shipping_pickup_pincode'] ?? config('services.shiprocket.pickup_pincode');

        $response = Http::withToken($this->token())
            ->get(self::BASE_URL.'/courier/serviceability/', [
                'pickup_postcode' => $pickupPincode,
                'delivery_postcode' => $postalCode,
                'weight' => $this->estimatedWeightKg($itemCount, $settings),
                'cod' => 1,
                'declared_value' => $cartSubtotal,
            ])
            ->throw()
            ->json();

        $couriers = $response['data']['available_courier_companies'] ?? [];
        if (empty($couriers)) {
            // A confirmed "we cannot deliver here" — must not be treated as
            // a fallback-worthy API hiccup by the caller (see ShippingManager).
            throw new UnserviceableAddressException('No courier is serviceable for this pincode.');
        }

        // Shiprocket returns several serviceable couriers; the storefront
        // just needs one delivery fee, not a courier picker, so take the
        // cheapest.
        $cheapest = collect($couriers)->sortBy('rate')->first();

        // Same null-vs-zero distinction as FlatRateCalculator — see there.
        $thresholdRaw = $settings['shipping_free_threshold'] ?? null;
        $threshold = ($thresholdRaw === null || $thresholdRaw === '') ? null : (float) $thresholdRaw;

        $fee = ($threshold !== null && $cartSubtotal >= $threshold) ? 0.0 : round((float) $cheapest['rate'], 2);

        return [
            'fee' => $fee,
            'free_shipping_applied' => $fee <= 0.0,
            'label' => $fee > 0 ? ($cheapest['courier_name'] ?? 'Standard shipping') : 'Free shipping',
        ];
    }

    /**
     * Creates an adhoc order on Shiprocket and returns the shipment/AWB
     * details.
     *
     * @return array{shipment_id: ?int, awb_code: ?string, courier_name: ?string}
     */
    public function createShipment(Order $order): array
    {
        $order->loadMissing('items');

        $response = Http::withToken($this->token())
            ->post(self::BASE_URL.'/orders/create/adhoc', [
                'order_id' => $order->order_number,
                'order_date' => $order->created_at->format('Y-m-d H:i'),
                'pickup_location' => 'Primary',
                'billing_customer_name' => $order->customer_name,
                'billing_last_name' => '',
                'billing_address' => $order->shipping_address_line1,
                'billing_address_2' => $order->shipping_address_line2 ?? '',
                'billing_city' => $order->shipping_city,
                'billing_pincode' => $order->shipping_postal_code,
                'billing_state' => $order->shipping_state,
                'billing_country' => $order->shipping_country,
                'billing_email' => $order->customer_email,
                'billing_phone' => $order->customer_phone,
                'shipping_is_billing' => true,
                'order_items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_title,
                    'sku' => $item->sku,
                    'units' => $item->quantity,
                    'selling_price' => $item->price,
                ])->all(),
                'payment_method' => $order->payment_method === 'cod' ? 'COD' : 'Prepaid',
                // Shiprocket works out what the courier collects on a COD
                // order as sub_total + shipping_charges - total_discount.
                // Sending only the item subtotal made it ignore the coupon,
                // the shipping fee and any wallet part already paid, so the
                // courier asked for the wrong amount. Wallet counts as a
                // discount here: it's money the customer has already paid.
                'sub_total' => (float) $order->subtotal,
                'shipping_charges' => (float) $order->shipping_fee,
                'total_discount' => round((float) $order->discount_amount + (float) $order->wallet_amount_used, 2),
                'length' => 10,
                'breadth' => 10,
                'height' => 5,
                'weight' => $this->estimatedWeightKg((int) $order->items->sum('quantity'), $this->settings()),
            ])
            ->throw()
            ->json();

        return [
            'shipment_id' => $response['shipment_id'] ?? null,
            'awb_code' => $response['awb_code'] ?? null,
            'courier_name' => $response['courier_name'] ?? null,
        ];
    }

    /**
     * @return array{status: ?string, checkpoints: array}
     */
    public function trackShipment(string $awbCode): array
    {
        $response = Http::withToken($this->token())
            ->get(self::BASE_URL.'/courier/track/awb/'.$awbCode)
            ->throw()
            ->json();

        $trackData = collect($response['tracking_data'] ?? [])->first();

        return [
            'status' => $trackData['shipment_track'][0]['current_status'] ?? null,
            'checkpoints' => $trackData['shipment_track_activities'] ?? [],
        ];
    }

    /**
     * Auth tokens are valid ~10 days per Shiprocket's docs; cached well
     * under that so a manual password rotation is picked up same-day.
     */
    private function token(): string
    {
        return Cache::remember('shiprocket.auth_token', now()->addHours(20), function () {
            $response = Http::post(self::BASE_URL.'/auth/login', [
                'email' => config('services.shiprocket.email'),
                'password' => config('services.shiprocket.password'),
            ])->throw()->json();

            // A 200 with no token is a malformed/unexpected response, not a
            // valid session — don't let it get cached as one. Caching `null`
            // here would silently break every quote for the next 20 hours
            // (Cache::remember only re-runs this closure once the entry expires).
            if (empty($response['token'])) {
                throw new \RuntimeException('Shiprocket login did not return a token.');
            }

            return $response['token'];
        });
    }

    private function estimatedWeightKg(int $itemCount, array $settings): float
    {
        $gramsPerItem = (float) ($settings['shipping_avg_item_weight_grams'] ?? 100);

        return max(0.1, round(($itemCount * $gramsPerItem) / 1000, 2));
    }

    private function settings(): array
    {
        return Cache::remember('site.settings', 3600, fn () => Setting::pluck('value', 'key')->toArray());
    }
}

<?php

namespace App\Services;

use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_ITEMS  = 'cart.items';
    private const SESSION_COUPON = 'cart.coupon';

    // ─────────────────────────────────────────────────────────────
    //  Mutations
    // ─────────────────────────────────────────────────────────────

    /**
     * Add or increment a tour in the cart.
     * rowId = md5(tour_id . travel_date) — ensures one row per tour+date combo.
     */
    public function add(Tour $tour, int $adults, int $children, string $travelDate): void
    {
        $rowId = $this->buildRowId($tour->id, $travelDate);
        $items = $this->rawItems();

        if (isset($items[$rowId])) {
            $items[$rowId]['adults']   = $adults;
            $items[$rowId]['children'] = $children;
            $items[$rowId]['quantity'] = $adults + $children;
            $items[$rowId]['subtotal'] = round($items[$rowId]['unit_price'] * ($adults + $children), 2);
        } else {
            $locale = app()->getLocale();
            $items[$rowId] = [
                'row_id'           => $rowId,
                'tour_id'          => $tour->id,
                'title_snapshot'   => $tour->{"title_{$locale}"} ?: $tour->title_es,
                'cover_image'      => $tour->cover_image,
                'duration'         => $tour->duration,
                'language'         => $tour->language,
                'group_type'       => $tour->group_type,
                'unit_price'       => (float) $tour->price,
                'adults'           => $adults,
                'children'         => $children,
                'quantity'         => $adults + $children,
                'subtotal'         => round((float) $tour->price * ($adults + $children), 2),
                'travel_date'      => $travelDate,
            ];
        }

        Session::put(self::SESSION_ITEMS, $items);
    }

    /**
     * Update an existing row (adults / children).
     */
    public function update(string $rowId, array $data): void
    {
        $items = $this->rawItems();

        if (! isset($items[$rowId])) {
            return;
        }

        if (isset($data['adults']))   $items[$rowId]['adults']   = (int) $data['adults'];
        if (isset($data['children'])) $items[$rowId]['children'] = (int) $data['children'];

        $items[$rowId]['quantity'] = $items[$rowId]['adults'] + $items[$rowId]['children'];
        $items[$rowId]['subtotal'] = round($items[$rowId]['unit_price'] * $items[$rowId]['quantity'], 2);

        Session::put(self::SESSION_ITEMS, $items);
    }

    /**
     * Remove a single row from the cart.
     */
    public function remove(string $rowId): void
    {
        $items = $this->rawItems();
        unset($items[$rowId]);
        Session::put(self::SESSION_ITEMS, $items);
    }

    /**
     * Destroy the entire cart (items + coupon).
     */
    public function clear(): void
    {
        Session::forget([self::SESSION_ITEMS, self::SESSION_COUPON]);
    }

    /**
     * Replace the whole cart with a list of item arrays (rebuilds the keyed
     * session structure). Used to restore an abandoned cart from its snapshot.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function replace(array $items): void
    {
        $keyed = [];

        foreach ($items as $item) {
            if (empty($item['tour_id'])) {
                continue;
            }

            $rowId = $item['row_id']
                ?? $this->buildRowId($item['tour_id'], $item['travel_date'] ?? '');

            $item['row_id'] = $rowId;
            $keyed[$rowId]  = $item;
        }

        Session::put(self::SESSION_ITEMS, $keyed);
    }

    // ─────────────────────────────────────────────────────────────
    //  Coupons
    // ─────────────────────────────────────────────────────────────

    /**
     * Validate and store a coupon code.
     *
     * @return array{success: bool, message: string, discount: float}
     */
    public function applyCoupon(string $code): array
    {
        $code    = strtoupper(trim($code));
        $coupons = config('cart.coupons', []);

        if (! isset($coupons[$code])) {
            return [
                'success'  => false,
                'message'  => __('cart.coupon_invalid'),
                'discount' => 0.0,
            ];
        }

        $coupon   = $coupons[$code];
        $discount = $this->calculateDiscount($coupon, $this->subtotal());

        Session::put(self::SESSION_COUPON, [
            'code'     => $code,
            'type'     => $coupon['type'],
            'value'    => $coupon['value'],
            'discount' => $discount,
        ]);

        return [
            'success'  => true,
            'message'  => __('cart.coupon_applied'),
            'discount' => $discount,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Reads
    // ─────────────────────────────────────────────────────────────

    /**
     * Return cart items as a Collection with all display fields.
     */
    public function items(): Collection
    {
        return collect(array_values($this->rawItems()));
    }

    public function count(): int
    {
        return count($this->rawItems());
    }

    public function subtotal(): float
    {
        return round(collect($this->rawItems())->sum('subtotal'), 2);
    }

    public function couponCode(): ?string
    {
        return Session::get(self::SESSION_COUPON . '.code');
    }

    public function couponDiscount(): float
    {
        $stored = Session::get(self::SESSION_COUPON);

        if (! $stored) {
            return 0.0;
        }

        // Recalculate each time subtotal may have changed
        $coupons = config('cart.coupons', []);
        $code    = $stored['code'] ?? null;

        if (! $code || ! isset($coupons[$code])) {
            return 0.0;
        }

        $discount = $this->calculateDiscount($coupons[$code], $this->subtotal());

        // Keep stored value in sync
        Session::put(self::SESSION_COUPON . '.discount', $discount);

        return $discount;
    }

    public function total(): float
    {
        return max(0.0, round($this->subtotal() - $this->couponDiscount(), 2));
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function buildRowId(int|string $tourId, string $travelDate): string
    {
        return md5($tourId . $travelDate);
    }

    /** @return array<string, array<string, mixed>> */
    private function rawItems(): array
    {
        return Session::get(self::SESSION_ITEMS, []);
    }

    private function calculateDiscount(array $coupon, float $subtotal): float
    {
        if ($coupon['type'] === 'percent') {
            return round($subtotal * $coupon['value'] / 100, 2);
        }

        // fixed
        return min((float) $coupon['value'], $subtotal);
    }
}

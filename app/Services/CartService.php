<?php

namespace App\Services;

use App\Exceptions\CartItemNotFoundException;
use App\Exceptions\CartLimitExceededException;
use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_ITEMS = 'cart.items';

    private const SESSION_COUPON = 'cart.coupon';

    /**
     * Tope duro de filas por carrito. Sin límite, un carrito con 400 filas
     * distintas guarda un `cart.items` de ~178 KB en la sesión, y ese mismo
     * blob se copia entero a `abandoned_carts.items` en cada guardado de
     * contacto (ver AbandonedCartService::capture()).
     */
    private const MAX_ROWS = 20;

    /**
     * Tope de pasajeros por fila, igual al `max:20` de adultos/niños en
     * CartItemRequest. Se usa también al fundir dos filas en update(): sin
     * esto, sumar dos filas de más de 20 pax en total recortaba la
     * diferencia en silencio con `min(20, ...)`.
     */
    private const MAX_PAX_PER_ROW = 20;

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

        // El tope solo aplica a filas NUEVAS: incrementar una fila que ya
        // existe (mismo tour + misma fecha) no crece el carrito.
        if (! isset($items[$rowId]) && count($items) >= self::MAX_ROWS) {
            throw new CartLimitExceededException(__('cart.cart_full', ['max' => self::MAX_ROWS]));
        }

        // CartItemRequest solo valida adults<=20 y children<=20 POR
        // SEPARADO: nada impedía mandar adults=20 + children=20 en una sola
        // fila (40 pasajeros). Mismo tope que ya se aplicaba al FUNDIR filas
        // en update(), ahora también en la vía directa de agregar.
        if ($adults + $children > self::MAX_PAX_PER_ROW) {
            throw new CartLimitExceededException(
                __('cart.row_pax_exceeded', ['max' => self::MAX_PAX_PER_ROW])
            );
        }

        if (isset($items[$rowId])) {
            $items[$rowId]['adults'] = $adults;
            $items[$rowId]['children'] = $children;
            $items[$rowId]['quantity'] = $adults + $children;
            $items[$rowId]['subtotal'] = round($items[$rowId]['unit_price'] * ($adults + $children), 2);
        } else {
            $locale = app()->getLocale();
            $items[$rowId] = [
                'row_id' => $rowId,
                'tour_id' => $tour->id,
                'title_snapshot' => $tour->{"title_{$locale}"} ?: $tour->title_es,
                'cover_image' => $tour->cover_image,
                'duration' => $tour->duration,
                'language' => $tour->language,
                'group_type' => $tour->group_type,
                'unit_price' => (float) $tour->price,
                'adults' => $adults,
                'children' => $children,
                'quantity' => $adults + $children,
                'subtotal' => round((float) $tour->price * ($adults + $children), 2),
                'travel_date' => $travelDate,
            ];
        }

        Session::put(self::SESSION_ITEMS, $items);
    }

    /**
     * Update an existing row (adults / children / travel_date).
     *
     * Cambiar la fecha CAMBIA la clave de la fila, porque el rowId es
     * md5(tour_id . travel_date) — es lo que permite tener el mismo tour dos
     * veces en fechas distintas. Por eso devuelve el rowId resultante: quien
     * llame tiene que quedarse con el nuevo o perderá la fila de vista.
     *
     * @return string rowId después del cambio (igual al de entrada si la
     *                fecha no cambió)
     *
     * @throws CartItemNotFoundException si $rowId no existe en el carrito
     * @throws CartLimitExceededException si fundir con la fila de destino
     *                                    superaría el máximo de pasajeros
     */
    public function update(string $rowId, array $data): string
    {
        $items = $this->rawItems();

        // Antes salía en silencio y devolvía el mismo $rowId: el controlador
        // respondía 200 `{"success":true}` para una fila que nunca se tocó
        // porque no existe (sesión distinta, fila ya borrada, rowId
        // inventado). Un fallo real pasaba por éxito.
        if (! isset($items[$rowId])) {
            throw new CartItemNotFoundException($rowId);
        }

        if (isset($data['adults'])) {
            $items[$rowId]['adults'] = (int) $data['adults'];
        }
        if (isset($data['children'])) {
            $items[$rowId]['children'] = (int) $data['children'];
        }

        $newRowId = $rowId;
        $newDate = $data['travel_date'] ?? null;

        if ($newDate && $newDate !== ($items[$rowId]['travel_date'] ?? null)) {
            $row = $items[$rowId];
            $row['travel_date'] = $newDate;

            $newRowId = $this->buildRowId($row['tour_id'], $newDate);
            unset($items[$rowId]);

            // Ya había una fila de ESTE MISMO tour en la fecha de destino: se
            // funden, o el carrito mostraría dos tarjetas idénticas y la
            // segunda pisaría a la primera en la sesión.
            if (isset($items[$newRowId])) {
                $mergedAdults = $items[$newRowId]['adults'] + $row['adults'];
                $mergedChildren = $items[$newRowId]['children'] + $row['children'];

                // `min(20, $a + $b)` recortaba la diferencia en silencio: si
                // las dos filas sumaban más de 20 pax, la gente que sobraba
                // desaparecía del carrito sin ningún aviso. Se rechaza el
                // movimiento entero en vez de reservar un grupo incompleto —
                // el cliente ve el mensaje y decide (dividir el grupo, quitar
                // pasajeros, o no mover la fecha), en vez de enterarse después
                // de que faltan personas en su reserva.
                if ($mergedAdults + $mergedChildren > self::MAX_PAX_PER_ROW) {
                    throw new CartLimitExceededException(
                        __('cart.merge_overflow', ['max' => self::MAX_PAX_PER_ROW])
                    );
                }

                $row['adults'] = $mergedAdults;
                $row['children'] = $mergedChildren;
            }

            $row['row_id'] = $newRowId;
            $items[$newRowId] = $row;
        }

        $items[$newRowId]['quantity'] = $items[$newRowId]['adults'] + $items[$newRowId]['children'];
        $items[$newRowId]['subtotal'] = round($items[$newRowId]['unit_price'] * $items[$newRowId]['quantity'], 2);

        Session::put(self::SESSION_ITEMS, $items);

        return $newRowId;
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

            // Mismo tope que add(): cart.recover restaura desde un snapshot
            // guardado hace tiempo (AbandonedCart::items), alcanzable por GET
            // con solo el token, sin throttle. Sin este corte, un snapshot
            // con más de MAX_ROWS filas se colaba entero por esta vía y
            // esquivaba el tope que sí se aplica en add().
            if (count($keyed) >= self::MAX_ROWS) {
                break;
            }

            $rowId = $item['row_id']
                ?? $this->buildRowId($item['tour_id'], $item['travel_date'] ?? '');

            $item['row_id'] = $rowId;
            $keyed[$rowId] = $item;
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
        $code = strtoupper(trim($code));
        $coupons = config('cart.coupons', []);

        if (! isset($coupons[$code])) {
            return [
                'success' => false,
                'message' => __('cart.coupon_invalid'),
                'discount' => 0.0,
            ];
        }

        $coupon = $coupons[$code];
        $discount = $this->calculateDiscount($coupon, $this->subtotal());

        Session::put(self::SESSION_COUPON, [
            'code' => $code,
            'type' => $coupon['type'],
            'value' => $coupon['value'],
            'discount' => $discount,
        ]);

        return [
            'success' => true,
            'message' => __('cart.coupon_applied'),
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
        return Session::get(self::SESSION_COUPON.'.code');
    }

    public function couponDiscount(): float
    {
        $stored = Session::get(self::SESSION_COUPON);

        if (! $stored) {
            return 0.0;
        }

        // Recalculate each time subtotal may have changed
        $coupons = config('cart.coupons', []);
        $code = $stored['code'] ?? null;

        if (! $code || ! isset($coupons[$code])) {
            return 0.0;
        }

        $discount = $this->calculateDiscount($coupons[$code], $this->subtotal());

        // Keep stored value in sync
        Session::put(self::SESSION_COUPON.'.discount', $discount);

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
        return md5($tourId.$travelDate);
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

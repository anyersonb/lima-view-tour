<?php

namespace App\Exceptions;

/**
 * Un ítem del carrito llega al checkout con una fecha que ya no es
 * reservable (pasada, por debajo de la antelación mínima, o más allá del
 * horizonte máximo — ver App\Support\BookingCalendar). Un carrito abierto en
 * una pestaña días atrás no vuelve a pasar por la validación del formulario,
 * que solo mira el campo `travel_date` que se envía, nunca los ítems.
 *
 * Se lanza desde CheckoutController::finalizeBookings() y desde
 * CheckoutController::paypalCaptureOrder() (ahí, ANTES de capturar el pago:
 * ver el comentario en finalizeBookings() sobre por qué se rechaza el
 * carrito completo en vez de guardar unos ítems sí y otros no).
 */
class UnbookableDateException extends \RuntimeException
{
    public function __construct(
        public readonly ?string $travelDate,
        public readonly int|string|null $tourId,
    ) {
        parent::__construct("Travel date [{$travelDate}] is not bookable for tour [{$tourId}].");
    }
}

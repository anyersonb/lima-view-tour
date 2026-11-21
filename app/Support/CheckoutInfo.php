<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Tour;
use Illuminate\Support\Collection;

/**
 * Datos reales para la pantalla de checkout (resources/views/checkout.blade.php).
 * Nada de horarios, políticas ni métodos de pago inventados: lo que no esté
 * cargado en el admin llega como null / lista vacía y la vista lo oculta.
 */
class CheckoutInfo
{
    /**
     * @param  Collection<int, array<string, mixed>>  $items  items del carrito (cada uno con 'tour_id')
     * @return array{
     *   guest_checkout: bool,
     *   cancellation_summary: ?string,
     *   refund_policy_url: string,
     *   departure_times: array<int, array{time: string, label: string}>,
     *   payment_methods: array{paypal: bool, culqi: bool, any: bool}
     * }
     */
    public static function build(Collection $items, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return [
            // El checkout NO exige cuenta: las rutas checkout.* no tienen
            // middleware auth y BookingCreationService::resolveCustomerId()
            // crea/reutiliza el cliente por correo. Ver CheckoutHomeDataTest.
            'guest_checkout' => true,
            'cancellation_summary' => Setting::getLocalized('legal_cancellation_summary', $locale),
            'refund_policy_url' => LocalizedPages::url('legal.refund', $locale),
            'departure_times' => self::departureTimes($items),
            'payment_methods' => self::paymentMethods(),
        ];
    }

    /**
     * Hora de salida REAL por tour (tours.departure_time, un solo valor
     * "HH:MM"). Un tour sin hora cargada NO aparece en el arreglo: no se usa
     * el 06:00 por defecto de config/booking.php (eso es solo regla interna
     * de antelación, no una promesa al cliente).
     *
     * @return array<int, array{time: string, label: string}>  indexado por tour_id
     */
    public static function departureTimes(Collection $items): array
    {
        $ids = $items->pluck('tour_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (Tour::whereIn('id', $ids)->get(['id', 'departure_time']) as $tour) {
            $time = DepartureTime::normalize($tour->departure_time);
            if ($time !== null) {
                $out[$tour->id] = ['time' => $time, 'label' => date('g:i A', strtotime($time))];
            }
        }

        return $out;
    }

    /**
     * Mismas condiciones que usa checkout.blade.php para pintar cada método.
     *
     * @return array{paypal: bool, culqi: bool, any: bool}
     */
    public static function paymentMethods(): array
    {
        $paypal = \App\Services\PayPalService::availableForCustomers();
        $culqi = (bool) config('services.culqi.enabled')
            && filled(config('services.culqi.public_key'))
            && filled(config('services.culqi.secret_key'));

        return ['paypal' => $paypal, 'culqi' => $culqi, 'any' => $paypal || $culqi];
    }
}

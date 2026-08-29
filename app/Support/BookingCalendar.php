<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Qué día es "hoy" y desde cuándo se puede reservar, según el reloj del
 * OPERADOR (Lima), no el del servidor ni el del visitante.
 *
 * Existe porque las tres cosas discrepaban:
 *  · El servidor corre en UTC. Entre las 19:00 y la medianoche de Lima ya
 *    está en el día siguiente, así que `after:today` rechazaba justo la
 *    fecha de mañana que el cliente acababa de elegir.
 *  · El calendario de la ficha calculaba su `minDate` en el NAVEGADOR, con
 *    la zona horaria del visitante: uno en Madrid y otro en Lima veían
 *    calendarios distintos para el mismo tour.
 *  · Los `min=""` de los <input type="date"> salían de `now()->addDay()`,
 *    o sea de UTC otra vez.
 *
 * Un tour sale un día concreto en Perú. Ese día no cambia porque el visitante
 * esté en otro huso ni porque el servidor esté en UTC.
 *
 * Ver config/booking.php.
 */
class BookingCalendar
{
    /**
     * `config('booking.timezone', 'America/Lima')` solo cae al default
     * cuando la CLAVE falta, no cuando el `.env` la define vacía
     * (`BOOKING_TIMEZONE=`): `env('BOOKING_TIMEZONE', 'America/Lima')`
     * devuelve `''`, no el default. `Carbon::now('')` lanza
     * InvalidFormatException, y como `earliestDate()` se llama desde las
     * Blade, esa excepción escapaba del `try` de `CartController@index` y
     * reventaba la página del carrito y la de cada ficha de tour enteras.
     * Por eso se valida contra la lista real de zonas de PHP en vez de
     * confiar en que el `.env` traiga algo razonable.
     */
    public static function timezone(): string
    {
        $timezone = (string) config('booking.timezone', 'America/Lima');

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return 'America/Lima';
        }

        return $timezone;
    }

    /** Hoy en el reloj del operador. */
    public static function today(): Carbon
    {
        return Carbon::now(static::timezone())->startOfDay();
    }

    /**
     * Primer día reservable (por defecto, mañana en Lima).
     *
     * `(int) '' === 0`: un `BOOKING_MIN_DAYS_AHEAD=` vacío en el `.env`
     * pasaba `max(0, 0)` y habilitaba reservas para el mismo día sin que
     * nadie lo decidiera a propósito. Se exige un mínimo de 1 en vez de 0.
     */
    public static function earliest(): Carbon
    {
        $minDaysAhead = (int) config('booking.min_days_ahead', 1);

        return static::today()->addDays(max(1, $minDaysAhead));
    }

    /** Primer día reservable en 'Y-m-d', para los `min` y las validaciones. */
    public static function earliestDate(): string
    {
        return static::earliest()->toDateString();
    }

    /**
     * Último día reservable: sin tope, un `9999-12-31` pasaba la validación
     * igual que una fecha razonable. El horizonte es configurable
     * (`booking.max_months_ahead`) porque cuánto se puede reservar por
     * adelantado es una decisión de negocio, no un límite técnico.
     */
    public static function latest(): Carbon
    {
        $monthsAhead = (int) config('booking.max_months_ahead', 18);

        return static::today()->addMonths(max(1, $monthsAhead));
    }

    /** Último día reservable en 'Y-m-d'. */
    public static function latestDate(): string
    {
        return static::latest()->toDateString();
    }

    /**
     * Regla de validación para una fecha de tour. Se usa igual en el carrito y
     * en el checkout, que antes tenían reglas distintas (`after_or_equal:today`
     * al añadir y `after:today` al pagar): se podía meter al carrito una fecha
     * que el pago iba a rechazar después.
     *
     * @return array<int, string>
     */
    public static function dateRules(): array
    {
        return [
            'date',
            'after_or_equal:'.static::earliestDate(),
            'before_or_equal:'.static::latestDate(),
        ];
    }

    /**
     * Mensajes de validación para una fecha de tour, con el primer día
     * reservable ya escrito y formateado en el idioma activo.
     *
     * Sin esto, el `after_or_equal` cae en el mensaje por defecto de Laravel:
     * en inglés y soltando la fecha cruda ("must be a date after or equal to
     * 2026-08-27"), justo en el carrito de un sitio en tres idiomas.
     *
     * @param  string  $field  Nombre del campo, por si no se llama travel_date
     * @return array<string, string>
     */
    public static function dateMessages(string $field = 'travel_date'): array
    {
        // isoFormat('LL') y no un patrón a mano: un 'D [de] MMMM' escrito para
        // el español daba "27 de August" en inglés. 'LL' es el formato largo
        // que cada idioma usa de verdad.
        $first = static::earliest()->locale(app()->getLocale())->isoFormat('LL');
        $last = static::latest()->locale(app()->getLocale())->isoFormat('LL');

        return [
            "{$field}.required" => __('cart.validation.date_required'),
            "{$field}.date" => __('cart.validation.date_invalid'),
            "{$field}.after_or_equal" => __('cart.validation.date_future', ['date' => $first]),
            "{$field}.before_or_equal" => __('cart.validation.date_too_far', ['date' => $last]),
        ];
    }

    /** ¿Esta fecha es reservable (ni muy pronto ni más allá del horizonte)? */
    public static function isBookable(?string $date): bool
    {
        if (! $date) {
            return false;
        }

        try {
            $parsed = Carbon::parse($date, static::timezone())->startOfDay();

            return $parsed->greaterThanOrEqualTo(static::earliest())
                && $parsed->lessThanOrEqualTo(static::latest());
        } catch (\Throwable) {
            return false;
        }
    }
}

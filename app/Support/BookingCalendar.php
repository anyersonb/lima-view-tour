<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * Antelación mínima en horas entre "ahora" (Lima) y la SALIDA del tour.
     *
     * `config('booking.min_hours_ahead', 24)` no cubre un `.env` con la
     * clave vacía (`BOOKING_MIN_HOURS_AHEAD=` devuelve '' y `(int) ''` es 0,
     * o sea "sin antelación" por accidente): un valor vacío, no numérico o
     * negativo cae a 24, igual que la zona horaria cae a America/Lima.
     */
    public static function minHoursAhead(): int
    {
        $raw = config('booking.min_hours_ahead', 24);

        if (is_int($raw) || (is_string($raw) && preg_match('/^\d+$/', trim($raw)))) {
            $hours = (int) $raw;

            return $hours >= 0 ? $hours : 24;
        }

        return 24;
    }

    /**
     * Hora de salida que se usa cuando el tour no tiene `departure_time`
     * (o no se puede leer). Configurable; inválida o vacía => '06:00'.
     */
    public static function defaultDepartureTime(): string
    {
        return DepartureTime::normalize((string) config('booking.default_departure_time', '06:00')) ?? '06:00';
    }

    /**
     * Hora de salida efectiva de un tour, 'HH:MM' en hora de Lima. Pasa por
     * DepartureTime::normalize() también al leer, así que un valor legado
     * ("05:00 AM") sigue funcionando aunque la migración aún no haya corrido.
     */
    public static function departureTime(?Tour $tour = null): string
    {
        return DepartureTime::normalize($tour?->departure_time) ?? static::defaultDepartureTime();
    }

    /** ¿El tour tiene una hora de salida propia (no el fallback)? */
    public static function hasOwnDepartureTime(?Tour $tour = null): bool
    {
        return DepartureTime::normalize($tour?->departure_time) !== null;
    }

    /** Instante de salida del tour el día $date, en hora de Lima. */
    public static function departureAt(string $date, ?Tour $tour = null): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', static::departureTime($tour)));

        return Carbon::parse($date, static::timezone())->startOfDay()->setTime($h, $m);
    }

    /**
     * Primer día reservable para un tour: la menor fecha D (>= mañana, o lo que
     * diga min_days_ahead) tal que D + hora de salida (Lima) >= ahora (Lima) +
     * min_hours_ahead. Sin tour usa la hora de salida por defecto.
     *
     * Ejemplo, tour de 05:00 con 24 h: a las 04:59 la primera fecha es mañana;
     * a las 05:01 pasa a ser pasado mañana.
     *
     * `(int) ''` === 0: un `BOOKING_MIN_DAYS_AHEAD=` vacío habilitaba reservas
     * para el mismo día sin que nadie lo decidiera, por eso el piso es 1.
     */
    public static function earliest(?Tour $tour = null): Carbon
    {
        $floor = static::today()->addDays(max(1, (int) config('booking.min_days_ahead', 1)));
        $cutoff = Carbon::now(static::timezone())->addHours(static::minHoursAhead());
        $candidate = $floor->copy();

        // La salida más tardía del día D es D+23:59, así que como mucho hay que
        // avanzar minHoursAhead/24 + 1 días; el tope evita un bucle absurdo si
        // alguien configura miles de horas.
        for ($i = 0; $i < 400; $i++) {
            if (static::departureAt($candidate->toDateString(), $tour)->greaterThanOrEqualTo($cutoff)) {
                return $candidate;
            }

            $candidate->addDay();
        }

        return $candidate;
    }

    /** Primer día reservable en 'Y-m-d', para los `min` y las validaciones. */
    public static function earliestDate(?Tour $tour = null): string
    {
        return static::earliest($tour)->toDateString();
    }

    /**
     * Menor primera fecha entre los tours de un carrito (para el campo
     * `travel_date` del formulario, que lleva la fecha más temprana).
     *
     * @param  iterable<int, array<string, mixed>>  $items
     */
    public static function earliestDateForItems(iterable $items): string
    {
        $items = collect($items);
        $tours = Tour::query()
            ->whereIn('id', $items->pluck('tour_id')->filter()->all())
            ->get(['id', 'departure_time']);

        return $tours->map(fn (Tour $t) => static::earliestDate($t))->sort()->first()
            ?? static::earliestDate();
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
     * Con la regla de antelación la fecha mínima depende del TOUR (su hora de
     * salida): se calcula por ítem, nunca con una regla global.
     *
     * @return array<int, string>
     */
    public static function dateRules(?Tour $tour = null): array
    {
        return [
            'date',
            'date_format:Y-m-d',
            'after_or_equal:'.static::earliestDate($tour),
            'before_or_equal:'.static::latestDate(),
        ];
    }

    /**
     * Regla del campo `travel_date` del FORMULARIO de pago: formato, no pasado
     * y horizonte. Ese campo lleva la fecha más temprana del carrito, que no
     * pertenece a un tour concreto; la antelación se valida por ÍTEM
     * (unbookableItems()) con el tour de cada uno.
     *
     * @return array<int, string>
     */
    public static function formDateRules(): array
    {
        // after_or_equal:today only discards plainly past dates; the real lead
        // time is per item.
        return [
            'date',
            'date_format:Y-m-d',
            'after_or_equal:'.static::today()->toDateString(),
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
    public static function dateMessages(string $field = 'travel_date', ?Tour $tour = null): array
    {
        // isoFormat('LL') y no un patrón a mano: un 'D [de] MMMM' escrito para
        // el español daba "27 de August" en inglés. 'LL' es el formato largo
        // que cada idioma usa de verdad.
        $first = static::earliest($tour)->locale(app()->getLocale())->isoFormat('LL');
        $last = static::latest()->locale(app()->getLocale())->isoFormat('LL');

        return [
            "{$field}.required" => __('cart.validation.date_required'),
            "{$field}.date" => __('cart.validation.date_invalid'),
            "{$field}.date_format" => __('cart.validation.date_invalid'),
            "{$field}.after_or_equal" => __('cart.validation.date_future', [
                'date' => $first,
                'time' => static::departureTime($tour),
            ]),
            "{$field}.before_or_equal" => __('cart.validation.date_too_far', ['date' => $last]),
        ];
    }

    /**
     * ¿Esta fecha es reservable para este tour (con la antelación mínima y sin
     * pasarse del horizonte)? Sin tour usa la hora de salida por defecto.
     */
    public static function isBookable(?string $date, ?Tour $tour = null): bool
    {
        if (! $date) {
            return false;
        }

        try {
            // Strict Y-m-d: Carbon::parse() would accept "+2 days", "@1792000000" or "2026-10-13x".
            $parsed = Carbon::createFromFormat('!Y-m-d', $date, static::timezone());

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                return false;
            }

            return $parsed->greaterThanOrEqualTo(static::earliest($tour))
                && $parsed->lessThanOrEqualTo(static::latest());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ¿La salida de este tour en esa fecha todavía no ocurrió (hora de Lima)?
     * Es la comprobación que usan los links de pago creados por un operador:
     * exentos de la antelación mínima, pero no se cobra una salida ya pasada.
     */
    public static function isUpcoming(?string $date, ?Tour $tour = null): bool
    {
        if (! $date) {
            return false;
        }

        try {
            return static::departureAt($date, $tour)->greaterThanOrEqualTo(Carbon::now(static::timezone()));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mensaje por ítem: nombra el tour, la primera fecha disponible y la hora
     * de salida, en el idioma activo.
     */
    public static function itemMessage(string $tourTitle, ?Tour $tour = null): string
    {
        return __('booking.date_item_unavailable', [
            'tour' => $tourTitle,
            'date' => static::earliest($tour)->locale(app()->getLocale())->isoFormat('LL'),
            'time' => static::departureTime($tour),
        ]);
    }

    /**
     * Ítems del carrito cuya fecha ya no es reservable, cada uno validado con
     * SU tour. Una sola consulta para todos los tours.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  string|null  $fallbackDate  fecha del formulario para ítems sin fecha propia
     * @return array<int, array{row_id: ?string, tour_id: mixed, title: string, date: ?string, first_date: string, message: string}>
     */
    public static function unbookableItems(Collection $items, ?string $fallbackDate = null): array
    {
        $tours = Tour::query()
            ->whereIn('id', $items->pluck('tour_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $bad = [];

        foreach ($items as $item) {
            $tour = $tours->get($item['tour_id'] ?? null);
            $date = $item['travel_date'] ?? $fallbackDate;

            // A cart item pointing at a tour that no longer exists (or is soft-deleted)
            // is not bookable: never fall back to the default departure time.
            $tourMissing = ($item['tour_id'] ?? null) && ! $tour;

            if (! $tourMissing && static::isBookable($date, $tour)) {
                continue;
            }

            $title = (string) ($item['title_snapshot'] ?? $tour?->title ?? '');

            $bad[] = [
                'row_id' => $item['row_id'] ?? null,
                'tour_id' => $item['tour_id'] ?? null,
                'title' => $title,
                'date' => $date,
                'first_date' => static::earliestDate($tour),
                'message' => static::itemMessage($title, $tour),
            ];
        }

        return $bad;
    }
}

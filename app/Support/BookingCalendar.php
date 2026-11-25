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
 * Mismo día (2026-11-24): los tours con `allows_same_day` (solo tours cortos
 * en Lima) se pueden reservar HOY si faltan al menos N horas para el inicio
 * del horario elegido. Los demás tours mantienen la regla de siempre. La
 * fecha mínima, los horarios por fecha, `dateRules`, `isBookable` y los `min`
 * de los inputs salen todos de este archivo: una sola fuente de verdad.
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
     * Hora de corte diario (HH:MM, hora de Lima) para reservar el día siguiente.
     *
     * `config('booking.next_day_cutoff', '19:00')` no cubre un `.env` con la
     * clave vacía ni un valor mal escrito ('7pm', '25:00'): cualquier cosa que
     * no sea HH:MM válido cae a '19:00'. El default inline del `config()`
     * protege además un bootstrap/cache/config.php viejo sin la clave nueva.
     */
    public static function nextDayCutoff(): string
    {
        $raw = trim((string) config('booking.next_day_cutoff', '19:00'));

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        return '19:00';
    }

    /** Corte en el formato del idioma activo ("7:00 p. m.", "7:00 PM", "19:00"). */
    public static function nextDayCutoffLabel(): string
    {
        return static::timeLabel(static::nextDayCutoff());
    }

    /** Hora 'HH:MM' en el formato del idioma activo ("2:30 p. m.", "2:30 PM", "14:30"). */
    public static function timeLabel(string $time, ?string $locale = null): string
    {
        $time = DepartureTime::normalize($time) ?? $time;
        [$h, $m] = array_map('intval', explode(':', $time));
        $at = Carbon::createFromTime($h, $m, 0, static::timezone());

        return match ($locale ?? app()->getLocale()) {
            'es' => ltrim($at->format('h:i'), '0').($h < 12 ? ' a. m.' : ' p. m.'),
            'en' => ltrim($at->format('h:i'), '0').($h < 12 ? ' AM' : ' PM'),
            default => $at->format('H:i'),
        };
    }

    /**
     * Hora de salida que se usa cuando el tour no tiene `departure_time`
     * (o no se puede leer). Configurable; inválida o vacía => '06:00'.
     */
    public static function defaultDepartureTime(): string
    {
        return DepartureTime::normalize((string) config('booking.default_departure_time', '06:00')) ?? '06:00';
    }

    // ── Horarios del tour ───────────────────────────────────────────────────

    /**
     * Horarios propios del tour: `departure_times` (JSON) si trae alguno
     * legible; si no, su `departure_time`. "HH:MM", ordenados, sin duplicados.
     * [] = el tour no tiene hora propia (se usa la de config).
     *
     * @return list<string>
     */
    public static function slots(?Tour $tour = null): array
    {
        $list = DepartureTime::normalizeList($tour?->departure_times);

        if ($list === []) {
            $own = DepartureTime::normalize($tour?->departure_time);
            $list = $own !== null ? [$own] : [];
        }

        return $list;
    }

    /** ¿El cliente debe ELEGIR horario (el tour tiene más de uno)? */
    public static function hasMultipleSlots(?Tour $tour = null): bool
    {
        return count(static::slots($tour)) > 1;
    }

    /**
     * Horario efectivo de una reserva: el elegido si es válido para el tour;
     * si el tour tiene uno solo, ese; si no, null (tampoco se inventa uno
     * cuando el tour no tiene hora propia).
     */
    public static function resolveSlot(?Tour $tour, mixed $slot = null): ?string
    {
        $times = static::slots($tour);
        $given = is_string($slot) ? DepartureTime::normalize($slot) : null;

        if ($given !== null) {
            return in_array($given, $times, true) ? $given : null;
        }

        return count($times) === 1 ? $times[0] : null;
    }

    /**
     * ¿El horario recibido es aceptable para el tour? Vacío vale si el tour
     * tiene 0 o 1 horario (se asigna solo); con varios es obligatorio. Un
     * horario que el tour no ofrece NUNCA es válido.
     */
    public static function slotIsValid(?Tour $tour, mixed $slot = null): bool
    {
        $times = static::slots($tour);
        $blank = $slot === null || (is_string($slot) && trim($slot) === '');

        if ($blank) {
            return count($times) <= 1;
        }

        $given = is_string($slot) ? DepartureTime::normalize($slot) : null;

        return $given !== null && in_array($given, $times, true);
    }

    /**
     * Hora de salida efectiva, 'HH:MM' en hora de Lima: el horario elegido si
     * viene, si no el primero del tour, si no el de config. Pasa por
     * normalize() también al leer, así que un valor legado ("05:00 AM") sigue
     * funcionando aunque la migración aún no haya corrido.
     */
    public static function departureTime(?Tour $tour = null, ?string $slot = null): string
    {
        return DepartureTime::normalize($slot)
            ?? (static::slots($tour)[0] ?? null)
            ?? static::defaultDepartureTime();
    }

    /** ¿El tour tiene una hora de salida propia (no el fallback)? */
    public static function hasOwnDepartureTime(?Tour $tour = null): bool
    {
        return static::slots($tour) !== [];
    }

    /** Instante de salida del tour el día $date (y horario $slot), en hora de Lima. */
    public static function departureAt(string $date, ?Tour $tour = null, ?string $slot = null): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', static::departureTime($tour, $slot)));

        return Carbon::parse($date, static::timezone())->startOfDay()->setTime($h, $m);
    }

    // ── Reserva el mismo día ────────────────────────────────────────────────

    /** ¿El tour admite reservas para hoy? (toggle `allows_same_day`). */
    public static function allowsSameDay(?Tour $tour = null): bool
    {
        return $tour !== null && (bool) ($tour->allows_same_day ?? false);
    }

    /**
     * Horas mínimas entre ahora y el inicio del horario para reservar hoy: la
     * del tour si tiene, si no config('booking.same_day_min_hours'). Vacío o
     * inválido => 4 (`(int) ''` valdría 0 y abriría la puerta sin querer).
     */
    public static function sameDayMinHours(?Tour $tour = null): int
    {
        foreach ([$tour?->same_day_min_hours, config('booking.same_day_min_hours', 4)] as $candidate) {
            if ((is_int($candidate) || (is_string($candidate) && preg_match('/^\d{1,2}$/', trim($candidate))))
                && (int) $candidate >= 0 && (int) $candidate <= 48) {
                return (int) $candidate;
            }
        }

        return 4;
    }

    /**
     * Horarios que todavía se pueden reservar HOY (hora de Lima): los que
     * empiezan dentro de al menos N horas. [] si el tour no admite mismo día.
     * Un tour con toggle pero sin hora propia usa la de config para el cálculo.
     *
     * @return list<string>
     */
    public static function todaySlots(?Tour $tour = null, ?Carbon $now = null): array
    {
        if (! static::allowsSameDay($tour)) {
            return [];
        }

        $now = $now ? $now->copy()->setTimezone(static::timezone()) : Carbon::now(static::timezone());
        $limit = $now->copy()->addHours(static::sameDayMinHours($tour));
        $today = $now->toDateString();
        $times = static::slots($tour) ?: [static::defaultDepartureTime()];

        return array_values(array_filter(
            $times,
            fn (string $t) => static::departureAt($today, $tour, $t)->greaterThanOrEqualTo($limit)
        ));
    }

    /**
     * Horarios reservables para una fecha 'Y-m-d': hoy, solo los que cumplen
     * el margen; otros días, todos los del tour.
     *
     * @return list<string>
     */
    public static function slotsForDate(string $date, ?Tour $tour = null): array
    {
        if ($date === static::today()->toDateString()) {
            return static::todaySlots($tour);
        }

        return static::slots($tour);
    }

    /**
     * Todo lo que la ficha necesita para pintar fecha y horario SIN calcular
     * nada en el navegador (el huso del visitante no cuenta).
     *
     * @return array<string, mixed>
     */
    public static function availability(?Tour $tour = null): array
    {
        $slots = static::slots($tour);
        $todaySlots = static::todaySlots($tour);

        return [
            'today' => static::today()->toDateString(),
            'min_date' => static::earliestDate($tour),
            'max_date' => static::latestDate(),
            'same_day' => static::allowsSameDay($tour),
            'today_available' => $todaySlots !== [],
            'min_hours' => static::sameDayMinHours($tour),
            'multiple_slots' => count($slots) > 1,
            'slots' => $slots,
            'today_slots' => $todaySlots,
            'default_time' => static::departureTime($tour),
            'labels' => collect(array_unique(array_merge($slots, $todaySlots)))
                ->mapWithKeys(fn (string $t) => [$t => static::timeLabel($t)])->all(),
            'full_payment_hours' => PaymentPlan::FULL_PAYMENT_WITHIN_HOURS,
        ];
    }

    // ── Primer día reservable ───────────────────────────────────────────────

    /**
     * Primer día reservable con la regla ESTÁNDAR (tours sin mismo día).
     * Corte diario, hora de Lima: antes de las 19:00 (configurable) la
     * primera fecha es MAÑANA; desde las 19:00 es PASADO MAÑANA. Hoy nunca.
     *
     * `min_days_ahead` sigue siendo un piso. `(int) ''` === 0: un
     * `BOOKING_MIN_DAYS_AHEAD=` vacío habilitaba reservas para el mismo día sin
     * que nadie lo decidiera, por eso el piso es 1.
     */
    private static function standardEarliest(): Carbon
    {
        $now = Carbon::now(static::timezone());
        $today = $now->copy()->startOfDay();
        $floor = $today->copy()->addDays(max(1, (int) config('booking.min_days_ahead', 1)));

        [$h, $m] = array_map('intval', explode(':', static::nextDayCutoff()));
        $afterCutoff = ($now->hour * 60 + $now->minute) >= ($h * 60 + $m);

        $candidate = $today->copy()->addDays($afterCutoff ? 2 : 1);

        return $candidate->greaterThan($floor) ? $candidate : $floor;
    }

    /**
     * Primer día reservable (web pública). Regla estándar, salvo que el tour
     * admita mismo día Y todavía le quede algún horario hoy con el margen
     * (todaySlots()): entonces es HOY. Los días siguientes siguen la regla
     * estándar (corte 19:00).
     */
    public static function earliest(?Tour $tour = null): Carbon
    {
        if (static::todaySlots($tour) !== []) {
            return static::today();
        }

        return static::standardEarliest();
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
            ->get();

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
     * Valida solo la FECHA; el horario (y el margen de hoy) se valida con
     * isBookable()/slotRules().
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
     * Regla del campo `departure_slot` para un tour y una fecha: horario que
     * el tour ofrece (obligatorio si ofrece varios) y, si la fecha es hoy, que
     * cumpla el margen de horas.
     *
     * @return array<int, mixed>
     */
    public static function slotRules(?Tour $tour = null, ?string $date = null, bool $requiredWhenMultiple = false): array
    {
        return [
            // `required` es implícita: sin ella Laravel salta la regla con el campo vacío.
            $requiredWhenMultiple && static::hasMultipleSlots($tour) ? 'required' : 'nullable',
            'string',
            'max:8',
            function (string $attribute, mixed $value, \Closure $fail) use ($tour, $date): void {
                if (! static::slotIsValid($tour, $value)) {
                    $fail(__('booking.slot_invalid'));

                    return;
                }

                if ($date && $date === static::today()->toDateString() && static::allowsSameDay($tour)) {
                    $eff = static::resolveSlot($tour, $value);
                    $ok = $eff === null ? static::todaySlots($tour) !== [] : in_array($eff, static::todaySlots($tour), true);

                    if (! $ok) {
                        $fail(__('booking.slot_too_soon', ['hours' => static::sameDayMinHours($tour)]));
                    }
                }
            },
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
                'cutoff' => static::nextDayCutoffLabel(),
            ]),
            "{$field}.before_or_equal" => __('cart.validation.date_too_far', ['date' => $last]),
        ];
    }

    /**
     * ¿Esta fecha (y horario) es reservable? Corte diario, horizonte, horario
     * válido para el tour y, si es hoy, margen de horas respecto al horario.
     */
    public static function isBookable(?string $date, ?Tour $tour = null, ?string $slot = null): bool
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

            if (! static::dateInRange($date, $tour)) {
                return false;
            }

            if (! static::slotIsValid($tour, $slot)) {
                return false;
            }

            if ($date === static::today()->toDateString()) {
                $eff = static::resolveSlot($tour, $slot);

                return $eff === null
                    ? static::todaySlots($tour) !== []
                    : in_array($eff, static::todaySlots($tour), true);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** ¿La fecha 'Y-m-d' está entre el primer y el último día reservable (sin mirar horario)? */
    public static function dateInRange(?string $date, ?Tour $tour = null): bool
    {
        if (! $date) {
            return false;
        }

        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date, static::timezone());

            return $parsed !== false
                && $parsed->format('Y-m-d') === $date
                && $parsed->greaterThanOrEqualTo(static::earliest($tour))
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
    public static function isUpcoming(?string $date, ?Tour $tour = null, ?string $slot = null): bool
    {
        if (! $date) {
            return false;
        }

        try {
            return static::departureAt($date, $tour, $slot)->greaterThanOrEqualTo(Carbon::now(static::timezone()));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ¿La salida es en menos de 48 h (PaymentPlan::FULL_PAYMENT_WITHIN_HOURS)?
     * Entonces el pago es SOLO 100% ahora (no hay tiempo de cobrar un saldo).
     */
    public static function departsWithinFullPaymentWindow(?string $date, ?Tour $tour = null, ?string $slot = null, ?Carbon $now = null): bool
    {
        if (! $date) {
            return false;
        }

        try {
            $now = $now ? $now->copy()->setTimezone(static::timezone()) : Carbon::now(static::timezone());

            return static::departureAt($date, $tour, $slot)
                ->lessThan($now->addHours(PaymentPlan::FULL_PAYMENT_WITHIN_HOURS));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mensaje por ítem: nombra el tour, la primera fecha disponible y el corte
     * de las 19:00, en el idioma activo.
     */
    public static function itemMessage(string $tourTitle, ?Tour $tour = null): string
    {
        return __('booking.date_item_unavailable', [
            'tour' => $tourTitle,
            'date' => static::earliest($tour)->locale(app()->getLocale())->isoFormat('LL'),
            'cutoff' => static::nextDayCutoffLabel(),
        ]);
    }

    /**
     * Ítems del carrito cuya fecha u horario ya no es reservable, cada uno
     * validado con SU tour. Una sola consulta para todos los tours.
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
            $slot = $item['departure_slot'] ?? null;

            // A cart item pointing at a tour that no longer exists (or is soft-deleted)
            // is not bookable: never fall back to the default departure time.
            $tourMissing = ($item['tour_id'] ?? null) && ! $tour;

            if (! $tourMissing && static::isBookable($date, $tour, $slot)) {
                continue;
            }

            $title = (string) ($item['title_snapshot'] ?? $tour?->title ?? '');

            // Fecha dentro del rango pero horario no válido o sin margen (hoy): mensaje de horario.
            $message = (! $tourMissing && static::dateInRange($date, $tour))
                ? __('booking.slot_item_unavailable', ['tour' => $title])
                : static::itemMessage($title, $tour);

            $bad[] = [
                'row_id' => $item['row_id'] ?? null,
                'tour_id' => $item['tour_id'] ?? null,
                'title' => $title,
                'date' => $date,
                'first_date' => static::earliestDate($tour),
                'message' => $message,
            ];
        }

        return $bad;
    }
}

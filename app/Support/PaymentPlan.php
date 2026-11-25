<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Modos de pago del checkout (decisión del cliente, 2026-11-21):
 *
 *   full     100% ahora            -> status confirmed, payment_status paid
 *   deposit  50% ahora, resto luego -> status confirmed, payment_status partial
 *   later    reservar y pagar luego -> status pending,   payment_status pending
 *
 * Todo cálculo es en CENTAVOS enteros: nunca se suma ni se compara con floats.
 * El front solo manda el MODO; el monto sale siempre del carrito en servidor.
 */
class PaymentPlan
{
    public const FULL = 'full';

    public const DEPOSIT = 'deposit';

    public const LATER = 'later';

    public const MODES = [self::FULL, self::DEPOSIT, self::LATER];

    /** Modos que cobran dinero en el checkout (los que pasan por Culqi/PayPal). */
    public const PAYING_MODES = [self::FULL, self::DEPOSIT];

    /** Porcentaje cobrado ahora en modo deposit. */
    public const DEPOSIT_PERCENT = 50;

    /** El adelanto es reembolsable si se cancela con MÁS de estas horas de antelación. */
    public const REFUND_HOURS = 24;

    /** El link de pago del saldo vence estas horas antes del tour. */
    public const BALANCE_LINK_EXPIRES_HOURS_BEFORE = 48;

    /** Días antes del tour en que sale el recordatorio automático del saldo. */
    public const BALANCE_REMINDER_DAYS_BEFORE = 3;

    /**
     * Si alguna salida es en menos de estas horas (mismo día o mañana cercano)
     * el pago es SOLO 100% ahora: no hay margen para cobrar un saldo, que
     * además vence 48 h antes (BALANCE_LINK_EXPIRES_HOURS_BEFORE).
     */
    public const FULL_PAYMENT_WITHIN_HOURS = 48;

    /**
     * ¿Algún ítem del carrito sale en menos de 48 h? Entonces solo vale
     * `full`. Cada ítem se mide con SU tour, fecha y horario (hora de Lima).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public static function mustPayInFull(Collection $items, ?Carbon $now = null): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        $tours = Tour::query()
            ->whereIn('id', $items->pluck('tour_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $date = $item['travel_date'] ?? null;

            if ($date && BookingCalendar::departsWithinFullPaymentWindow(
                (string) $date,
                $tours->get($item['tour_id'] ?? null),
                $item['departure_slot'] ?? null,
                $now,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Modos que se pueden ofrecer/aceptar para estos ítems.
     *
     * @return list<string>
     */
    public static function allowedModes(Collection $items, ?Carbon $now = null): array
    {
        return self::mustPayInFull($items, $now) ? [self::FULL] : self::MODES;
    }

    /**
     * Normaliza un modo recibido del cliente. Devuelve null si no es uno de
     * los tres (el llamador decide si usar un default o rechazar).
     */
    public static function normalize(mixed $mode): ?string
    {
        if (! is_string($mode)) {
            return null;
        }

        $mode = strtolower(trim($mode));

        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    /** @return int centavos */
    public static function toCents(float|string|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Reparte un total entre "cobrado ahora" y "saldo".
     *
     * deposit: ahora = round(total / 2) en centavos (mitad hacia arriba si el
     * total es impar: el cliente nunca paga MENOS de la mitad), saldo = resto.
     * ahora + saldo == total SIEMPRE.
     *
     * @return array{mode: string, total: float, due: float, balance: float, total_cents: int, due_cents: int, balance_cents: int}
     */
    public static function split(float|string|int $total, string $mode): array
    {
        $totalCents = max(0, self::toCents($total));

        $dueCents = match ($mode) {
            self::FULL => $totalCents,
            self::DEPOSIT => (int) round($totalCents * self::DEPOSIT_PERCENT / 100),
            default => 0, // later
        };

        $balanceCents = $totalCents - $dueCents;

        return [
            'mode' => $mode,
            'total' => self::fromCents($totalCents),
            'due' => self::fromCents($dueCents),
            'balance' => self::fromCents($balanceCents),
            'total_cents' => $totalCents,
            'due_cents' => $dueCents,
            'balance_cents' => $balanceCents,
        ];
    }

    /**
     * Montos de los tres modos para un total dado (lo que el front pinta).
     *
     * @return array<string, array{mode: string, total: float, due: float, balance: float, total_cents: int, due_cents: int, balance_cents: int}>
     */
    public static function amounts(float|string|int $total): array
    {
        $out = [];
        foreach (self::MODES as $mode) {
            $out[$mode] = self::split($total, $mode);
        }

        return $out;
    }

    /**
     * Reparte lo cobrado entre las reservas del carrito, proporcional a lo que
     * vale cada una (neto del cupón si lo hubo). Un solo cobro => N reservas,
     * y cada Booking debe saber cuánto se pagó de la suya y cuánto debe.
     *
     * Largest-remainder en centavos: la suma de cada columna es EXACTA
     * (sum(paid) == $dueCents, sum(net) == $totalCents) y ninguna reserva
     * queda con pagado > neto.
     *
     * @param  Collection|array<int, array{subtotal: float|int|string}>  $items  ítems del carrito, en orden
     * @return list<array{net_cents: int, paid_cents: int, balance_cents: int}> mismo orden que $items
     */
    public static function allocate(Collection|array $items, int $totalCents, int $dueCents): array
    {
        $items = array_values($items instanceof Collection ? $items->all() : $items);
        $count = count($items);

        if ($count === 0) {
            return [];
        }

        $weights = array_map(fn ($i) => max(0, self::toCents($i['subtotal'] ?? 0)), $items);
        $net = self::distribute($totalCents, $weights);
        $paid = self::distribute($dueCents, $net);

        $rows = [];
        foreach ($items as $idx => $_) {
            // Un reparto proporcional nunca excede el neto, pero se acota por seguridad.
            $p = min($paid[$idx], $net[$idx]);
            $rows[] = ['net_cents' => $net[$idx], 'paid_cents' => $p, 'balance_cents' => $net[$idx] - $p];
        }

        return $rows;
    }

    /**
     * Distribuye $amountCents proporcionalmente a $weights (enteros), con el
     * método del mayor resto. Si todos los pesos son 0, reparte en partes iguales.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private static function distribute(int $amountCents, array $weights): array
    {
        $n = count($weights);
        $sum = array_sum($weights);

        if ($sum <= 0) {
            $weights = array_fill(0, $n, 1);
            $sum = $n;
        }

        $base = [];
        $rem = [];
        $assigned = 0;

        foreach ($weights as $i => $w) {
            $exact = $amountCents * $w;
            $base[$i] = intdiv($exact, $sum);
            $rem[$i] = $exact % $sum;
            $assigned += $base[$i];
        }

        $left = $amountCents - $assigned;

        // Los centavos que sobran van a quienes tienen mayor resto; empate => el primero.
        $order = array_keys($rem);
        usort($order, fn ($a, $b) => $rem[$b] <=> $rem[$a] ?: $a <=> $b);

        foreach ($order as $i) {
            if ($left <= 0) {
                break;
            }
            $base[$i]++;
            $left--;
        }

        return $base;
    }
}

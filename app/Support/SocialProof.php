<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Prueba social real de la ficha: viajeros (adultos + niños) con reserva
 * confirmada o pagada de ese tour en los últimos N días. Nunca inventa cifras.
 */
class SocialProof
{
    public static function travelers(Tour $tour): int
    {
        $days = self::days();
        $ttl = max(60, (int) config('booking.social_proof.cache_ttl', 3600));

        return (int) Cache::remember(
            'social_proof.travelers.'.$tour->getKey().'.'.$days,
            $ttl,
            function () use ($tour, $days): int {
                $row = Booking::query()
                    ->where('tour_id', $tour->getKey())
                    ->where('created_at', '>=', now()->subDays($days))
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                            ->orWhereIn('status', ['confirmed', 'completed']);
                    })
                    ->where('status', '!=', 'cancelled')
                    ->where('payment_status', '!=', 'refunded')
                    ->selectRaw('COALESCE(SUM(adults), 0) as a, COALESCE(SUM(children), 0) as c')
                    ->first();

                return (int) ($row->a ?? 0) + (int) ($row->c ?? 0);
            }
        );
    }

    /** Cifra a mostrar, o null si no alcanza el umbral. */
    public static function displayable(Tour $tour): ?int
    {
        $n = self::travelers($tour);

        return $n >= max(1, (int) config('booking.social_proof.min', 5)) ? $n : null;
    }

    public static function days(): int
    {
        return max(1, (int) config('booking.social_proof.days', 30));
    }
}

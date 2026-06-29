<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedDate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'    => 'date',
        'weekday' => 'integer',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────────

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static helpers used by controllers and the view
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns an array of 'Y-m-d' strings blocked for the given tour
     * (global records + tour-specific records), from today onward.
     */
    public static function blockedDatesFor(?int $tourId): array
    {
        return static::whereNotNull('date')
            ->where('date', '>=', now()->toDateString())
            ->where(function ($q) use ($tourId) {
                $q->whereNull('tour_id');
                if ($tourId) {
                    $q->orWhere('tour_id', $tourId);
                }
            })
            ->pluck('date')
            ->map(fn ($d) => ($d instanceof \Illuminate\Support\Carbon ? $d : \Illuminate\Support\Carbon::parse($d))->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Returns an array of integer weekdays (0–6) blocked for the given tour
     * (global records + tour-specific records).
     */
    public static function blockedWeekdaysFor(?int $tourId): array
    {
        return static::whereNotNull('weekday')
            ->where(function ($q) use ($tourId) {
                $q->whereNull('tour_id');
                if ($tourId) {
                    $q->orWhere('tour_id', $tourId);
                }
            })
            ->pluck('weekday')
            ->map(fn ($w) => (int) $w)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Returns true if the given date string ('Y-m-d') is blocked for the tour.
     */
    public static function isBlocked(string $date, ?int $tourId): bool
    {
        $carbon = \Illuminate\Support\Carbon::parse($date);

        // Check specific date
        $dateBlocked = static::whereNotNull('date')
            ->where('date', $date)
            ->where(function ($q) use ($tourId) {
                $q->whereNull('tour_id');
                if ($tourId) {
                    $q->orWhere('tour_id', $tourId);
                }
            })
            ->exists();

        if ($dateBlocked) {
            return true;
        }

        // Check weekday (0=Sunday … 6=Saturday, matching PHP/JS convention)
        $weekday = (int) $carbon->dayOfWeek; // Carbon uses 0=Sunday

        return static::whereNotNull('weekday')
            ->where('weekday', $weekday)
            ->where(function ($q) use ($tourId) {
                $q->whereNull('tour_id');
                if ($tourId) {
                    $q->orWhere('tour_id', $tourId);
                }
            })
            ->exists();
    }
}

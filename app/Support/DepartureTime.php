<?php

namespace App\Support;

/**
 * Parser/normalizer for `tours.departure_time`.
 *
 * The column was free text for years ("05:00 AM", "5 am", "17h"...). Booking
 * lead-time rules need a real clock time, so everything goes through here and
 * ends up as zero-padded 24 h "HH:MM", or null when it cannot be read with
 * certainty (ranges, "Todo el día", a bare number...). Null means "use the
 * configured default" (config/booking.php), never "block the tour".
 */
class DepartureTime
{
    /**
     * @return string|null "HH:MM" (24 h) or null when unreadable
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = strtolower(trim($raw));

        if ($value === '') {
            return null;
        }

        // [hour][sep minutes][seconds optional][am/pm | h | hs | hrs]
        // Accepts: 05:00 AM, 5:00 am, 5 AM, 5am, 5:00 a.m., 17:00, 17h, 17:30hs,
        // 5.30 pm, 05:00:00. A bare number ("5") is ambiguous and is rejected.
        $pattern = '/^(\d{1,2})(?:[:.h](\d{2})(?::\d{2})?)?\s*(?:(a|p)\.?\s?m\.?|h|hs|hrs|hr)?$/';

        if (! preg_match($pattern, $value, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $meridiem = $m[3] ?? '';
        $hasMinutes = isset($m[2]) && $m[2] !== '';
        $hasUnitSuffix = (bool) preg_match('/(?:\d|\s)(h|hs|hrs|hr)$/', $value);

        // Bare "5": no minutes, no am/pm, no h suffix.
        if (! $hasMinutes && $meridiem === '' && ! $hasUnitSuffix) {
            return null;
        }

        if ($minute > 59) {
            return null;
        }

        if ($meridiem !== '') {
            if ($hour < 1 || $hour > 12) {
                return null;
            }

            $hour = $hour % 12;

            if ($meridiem === 'p') {
                $hour += 12;
            }
        } elseif ($hour > 23) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}

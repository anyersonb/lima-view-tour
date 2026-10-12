<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Tolerant access to tours.pickup_included / tours.free_cancellation.
 *
 * The columns come from a migration that may not be applied yet (production
 * migrates from a button). Without them the site must keep working with the
 * migration default: both promises are on (true).
 */
final class TourFlags
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function has(string $column): bool
    {
        return self::$cache[$column] ??= Schema::hasColumn('tours', $column);
    }

    /** Drop the memoized schema lookups (tests, long-lived workers). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Columns of the two flags that actually exist.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_values(array_filter(
            ['pickup_included', 'free_cancellation'],
            fn (string $c) => self::has($c)
        ));
    }
}

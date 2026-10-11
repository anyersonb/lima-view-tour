<?php

use App\Support\DepartureTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * tours.departure_time was free text ("05:00 AM", "5 am", "17h"...). The
 * 24 h booking rule needs a real clock time, so values are normalized to
 * "HH:MM" (24 h). The column stays a nullable string: no ALTER, so it is
 * safe on MySQL 8 and SQLite, and a TIME column would hand back "05:00:00"
 * to code that already reads this field as text.
 *
 * Values that cannot be read with certainty (ranges, "Todo el día", a bare
 * number...) become NULL => the default departure time of config/booking.php
 * applies. Every change is logged and saved to a JSON backup so down() can
 * restore the original text.
 */
return new class extends Migration
{
    private const BACKUP = 'migrations/tours_departure_time_backup.json';

    public function up(): void
    {
        $backup = [];
        $unparsed = [];

        DB::table('tours')
            ->whereNotNull('departure_time')
            ->orderBy('id')
            ->select(['id', 'departure_time'])
            ->chunkById(200, function ($rows) use (&$backup, &$unparsed) {
                foreach ($rows as $row) {
                    $raw = (string) $row->departure_time;
                    $normalized = DepartureTime::normalize($raw);

                    if ($normalized === $raw) {
                        continue;
                    }

                    $backup[$row->id] = $raw;

                    if ($normalized === null) {
                        $unparsed[$row->id] = $raw;
                    }

                    DB::table('tours')->where('id', $row->id)->update(['departure_time' => $normalized]);
                }
            });

        if ($backup !== []) {
            // Merge with an earlier backup so re-running never loses the first original.
            $previous = Storage::disk('local')->exists(self::BACKUP)
                ? (json_decode(Storage::disk('local')->get(self::BACKUP), true) ?: [])
                : [];

            Storage::disk('local')->put(self::BACKUP, json_encode($previous + $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $summary = 'tours.departure_time normalized: '.count($backup).' changed, '.count($unparsed).' set to NULL (unparseable).';
        Log::info($summary, ['unparsed' => $unparsed]);

        // Only talk when something changed, so fresh installs and test runs stay quiet.
        if ($backup !== []) {
            echo '  '.$summary.($unparsed ? ' NULL ids: '.json_encode($unparsed, JSON_UNESCAPED_UNICODE) : '').PHP_EOL;
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::BACKUP)) {
            return;
        }

        foreach (json_decode($disk->get(self::BACKUP), true) ?: [] as $id => $original) {
            DB::table('tours')->where('id', $id)->update(['departure_time' => $original]);
        }

        $disk->delete(self::BACKUP);
    }
};

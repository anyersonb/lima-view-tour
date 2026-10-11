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
 * applies.
 *
 * Safety: the backup (id => {from, to}) is computed and written to disk BEFORE
 * any update, and the updates run in one transaction. If the backup cannot be
 * written, the migration aborts without touching data. down() only restores
 * rows whose current value is still the one this migration wrote.
 */
return new class extends Migration
{
    private const BACKUP = 'migrations/tours_departure_time_backup.json';

    public function up(): void
    {
        $changes = []; // id => ['from' => original, 'to' => normalized|null]

        DB::table('tours')
            ->whereNotNull('departure_time')
            ->orderBy('id')
            ->select(['id', 'departure_time'])
            ->chunkById(200, function ($rows) use (&$changes) {
                foreach ($rows as $row) {
                    $raw = (string) $row->departure_time;
                    $normalized = DepartureTime::normalize($raw);

                    if ($normalized !== $raw) {
                        $changes[$row->id] = ['from' => $raw, 'to' => $normalized];
                    }
                }
            });

        if ($changes === []) {
            Log::info('tours.departure_time normalized: 0 changed.');

            return;
        }

        // 1) Backup first. Any failure here (invalid UTF-8, unwritable disk) throws
        //    before a single row is modified.
        $disk = Storage::disk('local');
        $previous = $disk->exists(self::BACKUP)
            ? (json_decode($disk->get(self::BACKUP), true, 512, JSON_THROW_ON_ERROR) ?: [])
            : [];

        // Keep the FIRST original when re-running: earlier entries win.
        $merged = $previous + $changes;
        $payload = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (! $disk->put(self::BACKUP, $payload)) {
            throw new RuntimeException('Cannot write the departure_time backup; migration aborted, no data touched.');
        }

        // 2) Updates, all or nothing. The WHERE guards against rows edited since the read.
        DB::transaction(function () use ($changes) {
            foreach ($changes as $id => $change) {
                DB::table('tours')
                    ->where('id', $id)
                    ->where('departure_time', $change['from'])
                    ->update(['departure_time' => $change['to']]);
            }
        });

        $unparsed = array_filter($changes, fn ($c) => $c['to'] === null);

        // Log only: this runs from the Maintenance page (Livewire) where any echo
        // would be glued to the JSON response.
        Log::info('tours.departure_time normalized: '.count($changes).' changed, '.count($unparsed).' set to NULL (unparseable).', [
            'unparsed' => array_map(fn ($c) => $c['from'], $unparsed),
        ]);
    }

    public function down(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::BACKUP)) {
            return;
        }

        $entries = json_decode($disk->get(self::BACKUP), true, 512, JSON_THROW_ON_ERROR) ?: [];

        DB::transaction(function () use ($entries) {
            foreach ($entries as $id => $change) {
                // Only roll back rows still holding the value this migration set;
                // anything an admin typed afterwards is kept.
                $query = DB::table('tours')->where('id', $id);
                $change['to'] === null
                    ? $query->whereNull('departure_time')
                    : $query->where('departure_time', $change['to']);

                $query->update(['departure_time' => $change['from']]);
            }
        });

        $disk->delete(self::BACKUP);
    }
};

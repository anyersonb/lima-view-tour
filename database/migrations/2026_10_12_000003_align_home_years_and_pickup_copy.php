<?php

use App\Support\HomeCopyAlignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Client decision 2026-10-12: the company has "+11 años" (the site said +16 and
 * "más de 10 años" in places) and the pickup promise is not universal.
 *
 * Touches ONLY settings rows whose value actually changes (stats_years, the two
 * home FAQ blocks `home_faqs` / `faqs`, any text saying "N años de experiencia").
 * Idempotent: a second run changes nothing. The previous value of every changed
 * row is written to Log::critical (and to storage/app/migrations/...json) BEFORE
 * the update, so nothing is lost.
 */
return new class extends Migration
{
    private const BACKUP = 'migrations/home_copy_alignment_backup.json';

    public function up(): void
    {
        $changes = []; // key => ['from' => raw, 'to' => raw]

        foreach (DB::table('settings')->select(['key', 'value', 'type'])->get() as $row) {
            if ($row->value === null || $row->value === '') {
                continue;
            }

            // The FAQ blocks are JSON even when stored with type "string"
            // (the panel decodes them by hand, see Settings.php).
            $isJson = in_array($row->type, ['array', 'json'], true) || in_array($row->key, ['home_faqs', 'faqs'], true);
            $decoded = $isJson ? json_decode($row->value, true) : $row->value;

            if ($isJson && (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded))) {
                continue;
            }

            $new = HomeCopyAlignment::transform($row->key, $decoded);

            if ($new === $decoded) {
                continue;
            }

            $changes[$row->key] = [
                'from' => $row->value,
                'to' => $isJson ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $new,
            ];
        }

        if ($changes === []) {
            Log::info('home copy alignment: 0 settings changed.');

            return;
        }

        // 1) Backup first: log (always) + disk (best effort).
        Log::critical('home copy alignment: previous values before update', $changes);

        try {
            $disk = Storage::disk('local');
            $disk->makeDirectory('migrations');
            $previous = $disk->exists(self::BACKUP) ? (json_decode($disk->get(self::BACKUP), true) ?: []) : [];
            $disk->put(self::BACKUP, json_encode($previous + $changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            Log::error("home copy alignment backup: disk unavailable ({$e->getMessage()}).");
        }

        // 2) Update in one transaction, then drop the settings cache.
        DB::transaction(function () use ($changes) {
            foreach ($changes as $key => $change) {
                DB::table('settings')->where('key', $key)->update(['value' => $change['to']]);
            }
        });

        Log::info('home copy alignment: settings changed.', ['keys' => array_keys($changes)]);

        \Illuminate\Support\Facades\Cache::forget('settings.all');
        \Illuminate\Support\Facades\Cache::forget('settings.all_view');
    }

    /** Restores rows whose value is still what this migration wrote (backup on disk or in the log). */
    public function down(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::BACKUP)) {
            Log::warning('home copy alignment down(): no disk backup; restore from the Log::critical entry.');

            return;
        }

        foreach (json_decode($disk->get(self::BACKUP), true) ?: [] as $key => $change) {
            DB::table('settings')->where('key', $key)->where('value', $change['to'])->update(['value' => $change['from']]);
        }

        \Illuminate\Support\Facades\Cache::forget('settings.all');
        \Illuminate\Support\Facades\Cache::forget('settings.all_view');
    }
};

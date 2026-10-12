<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\ImageVariants;
use App\Support\TourThumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateImageVariants extends Command
{
    protected $signature = 'images:variants
        {--force : Regenerate variants that already exist}
        {--max-seconds=90 : Stop starting new images after this many seconds (0 = no limit). Keeps web-triggered runs under max_execution_time}
        {--disk=* : Only these disks (public, media). Default: both}';

    protected $description = 'Generate responsive WebP variants (-w480/-w960/-w1440) next to every uploaded image. Idempotent, works in batches.';

    public function handle(): int
    {
        $started = microtime(true);
        $budget = max(0, (int) $this->option('max-seconds'));
        $force = (bool) $this->option('force');
        $disks = $this->option('disk') ?: ['public', 'media'];

        // Web-triggered runs share the PHP time limit: the budget below is what protects them.
        @set_time_limit(max(30, $budget + 30));

        // Tour card thumbnails (mobile listing) stay part of the same job. Cheap and idempotent.
        $this->tourThumbnails($force);

        $counts = ['images' => 0, 'created' => 0, 'up_to_date' => 0, 'skipped' => 0, 'errors' => 0, 'remaining' => 0];
        $queue = [];

        foreach ($disks as $disk) {
            if (! in_array($disk, ['public', 'media'], true)) {
                continue;
            }

            $files = collect(Storage::disk($disk)->allFiles())
                ->filter(fn (string $p) => ImageVariants::isEligible($p, $disk))
                ->sort()
                ->values();

            foreach ($files as $path) {
                $queue[] = [$disk, $path];
            }
        }

        $counts['images'] = count($queue);

        foreach ($queue as $i => [$disk, $path]) {
            if ($budget > 0 && (microtime(true) - $started) >= $budget) {
                // Out of time: count what is still pending (header-only reads) and stop.
                foreach (array_slice($queue, $i) as [$d, $p]) {
                    $pending = ImageVariants::pendingWidths($p, $d, $force);
                    if ($pending !== null && $pending['missing'] !== []) {
                        $counts['remaining']++;
                    }
                }
                break;
            }

            try {
                $result = ImageVariants::generate($path, $disk, $force);
            } catch (\Throwable $e) {
                Log::warning('images:variants failed', ['disk' => $disk, 'path' => $path, 'error' => $e->getMessage()]);
                $counts['errors']++;

                continue;
            }

            if (isset($result['*'])) {
                $result['*'] === 'unsupported' || $result['*'] === 'too_big' ? $counts['skipped']++ : $counts['errors']++;

                continue;
            }

            $created = count(array_filter($result, fn ($s) => $s === 'created'));
            $counts['errors'] += count(array_filter($result, fn ($s) => $s === 'error'));
            $created > 0 ? $counts['created'] += $created : $counts['up_to_date']++;
        }

        $summary = sprintf(
            'Imágenes: %d. Variantes creadas: %d. Al día: %d. Omitidas: %d. Errores: %d. Pendientes: %d.',
            $counts['images'], $counts['created'], $counts['up_to_date'], $counts['skipped'], $counts['errors'], $counts['remaining']
        );

        if ($counts['remaining'] > 0) {
            $summary .= ' Vuelve a ejecutarlo para continuar.';
        }

        $this->line($summary);
        Log::info('images:variants', $counts);

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tourThumbnails(bool $force): void
    {
        Tour::query()
            ->whereNotNull('cover_image')
            ->where('cover_image', '!=', '')
            ->select(['id', 'cover_image'])
            ->orderBy('id')
            ->each(function (Tour $tour) use ($force) {
                try {
                    TourThumbnail::generate($tour->cover_image, $force);
                } catch (\Throwable $e) {
                    Log::warning('tour thumbnail failed', ['tour_id' => $tour->id, 'error' => $e->getMessage()]);
                }
            });
    }
}

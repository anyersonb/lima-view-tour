<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Support\TourThumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateTourThumbnails extends Command
{
    protected $signature = 'tours:thumbnails {--force : Regenerate even if the thumbnail already exists}';

    protected $description = 'Generate lightweight WebP thumbnails for tour covers (mobile listing cards). Idempotent.';

    public function handle(): int
    {
        // Web-triggered runs (Maintenance page) share the PHP time limit.
        @set_time_limit(300);

        $counts = [];

        Tour::query()
            ->whereNotNull('cover_image')
            ->where('cover_image', '!=', '')
            ->select(['id', 'cover_image'])
            ->orderBy('id')
            ->each(function (Tour $tour) use (&$counts) {
                try {
                    $status = TourThumbnail::generate($tour->cover_image, (bool) $this->option('force'));
                } catch (\Throwable $e) {
                    Log::warning('tour thumbnail failed', ['tour_id' => $tour->id, 'error' => $e->getMessage()]);
                    $status = 'error';
                }

                $counts[$status] = ($counts[$status] ?? 0) + 1;
            });

        ksort($counts);
        $summary = $counts === []
            ? 'No tours with a cover image.'
            : implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($counts), $counts));

        $this->line($summary);
        Log::info('tours:thumbnails', $counts);

        return ($counts['error'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}

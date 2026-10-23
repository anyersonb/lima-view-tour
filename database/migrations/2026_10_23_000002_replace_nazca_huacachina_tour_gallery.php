<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the gallery and cover of the Nazca + Huacachina tour (this slug only).
 *
 * The gallery column held the 14 "OASIS-DE-HUACACHINA-ISLAS-BALLESTAS-EN-PARACAS-N"
 * photos of the Paracas tour (plus a duplicate of NAZCA-5). They are only unlinked
 * here; the files stay on disk because the Paracas tour uses them.
 *
 * Guards: the swap happens only when the current value is exactly the legacy one
 * (gallery made only of Paracas files + NAZCA-5-1, cover tours/155.jpg). Anything else
 * is left untouched and logged. Idempotent. Does not touch seo_image (og:image).
 */
return new class extends Migration
{
    private const SLUG = 'las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina';

    private const LEGACY_COVER = 'tours/155.jpg';

    private const NEW_COVER = 'tours/FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5.jpg';

    /** @return list<string> */
    private function newGallery(): array
    {
        return array_map(fn (string $f): string => 'tours/'.$f.'.jpg', [
            'FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-2',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-3',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-4',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-5',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-6-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-7-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-8-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-9-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-10-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-11',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-12',
        ]);
    }

    public function up(): void
    {
        $tour = DB::table('tours')->where('slug', self::SLUG)->first();

        if (! $tour) {
            Log::warning('nazca gallery: tour not found, nothing done.', ['slug' => self::SLUG]);

            return;
        }

        $current = json_decode((string) $tour->gallery, true);
        $current = is_array($current) ? $current : [];
        $update = [];

        if ($current === $this->newGallery()) {
            // already applied
        } elseif ($this->isLegacyGallery($current)) {
            $update['gallery'] = json_encode($this->newGallery(), JSON_UNESCAPED_SLASHES);
        } else {
            Log::warning('nazca gallery: current gallery is not the legacy Paracas one, left untouched.', [
                'tour_id' => $tour->id,
                'gallery' => $current,
            ]);
        }

        if ($tour->cover_image === self::LEGACY_COVER) {
            $update['cover_image'] = self::NEW_COVER;
        } elseif ($tour->cover_image !== self::NEW_COVER) {
            Log::warning('nazca gallery: unexpected cover, left untouched.', ['tour_id' => $tour->id, 'cover' => $tour->cover_image]);
        }

        if ($update !== []) {
            DB::table('tours')->where('id', $tour->id)->update($update);
            Log::info('nazca gallery: updated.', ['tour_id' => $tour->id, 'fields' => array_keys($update)]);
        }
    }

    /** Restores the legacy gallery/cover only if they still hold what up() wrote. */
    public function down(): void
    {
        $tour = DB::table('tours')->where('slug', self::SLUG)->first();

        if (! $tour) {
            return;
        }

        $update = [];

        if (json_decode((string) $tour->gallery, true) === $this->newGallery()) {
            $update['gallery'] = json_encode($this->legacyGallery(), JSON_UNESCAPED_SLASHES);
        }

        if ($tour->cover_image === self::NEW_COVER) {
            $update['cover_image'] = self::LEGACY_COVER;
        }

        if ($update !== []) {
            DB::table('tours')->where('id', $tour->id)->update($update);
        }
    }

    /** @return list<string> */
    private function legacyGallery(): array
    {
        $paracas = fn (int $n): string => "tours/OASIS-DE-HUACACHINA-ISLAS-BALLESTAS-EN-PARACAS-{$n}.jpg";

        return [
            $paracas(5), $paracas(4), $paracas(3), $paracas(2), $paracas(1), $paracas(9),
            'tours/FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5-1.jpg',
            $paracas(10), $paracas(14), $paracas(13), $paracas(12), $paracas(8), $paracas(7), $paracas(6),
        ];
    }

    /** Non-empty and made only of Paracas photos plus the NAZCA-5-1 duplicate. */
    private function isLegacyGallery(array $gallery): bool
    {
        if ($gallery === []) {
            return false;
        }

        foreach ($gallery as $path) {
            if (! is_string($path)
                || ! preg_match('#^tours/(OASIS-DE-HUACACHINA-ISLAS-BALLESTAS-EN-PARACAS-\d+|FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5-1)\.jpg$#', $path)) {
                return false;
            }
        }

        return true;
    }
};

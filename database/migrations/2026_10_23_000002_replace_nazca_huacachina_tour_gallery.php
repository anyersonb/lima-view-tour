<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the gallery and cover of the Nazca + Huacachina tour (this slug only).
 *
 * The gallery column held the "OASIS-DE-HUACACHINA-ISLAS-BALLESTAS-EN-PARACAS-N" photos
 * of the Paracas tour (plus a duplicate of NAZCA-5, which is an Islas Ballestas photo too).
 * They are only unlinked here; the files stay on disk because the Paracas tour uses them.
 *
 * Guard: the swap happens only when the current gallery still contains a legacy photo
 * (any ...-EN-PARACAS-* or FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5*), so nothing uploaded by hand
 * gets overwritten; when the gallery already equals the new one it is a no-op. Cover and
 * gallery are swapped together. seo_image (og:image) is not touched: the same file is reused
 * as cover and first photo (real Nazca photo, already on production).
 */
return new class extends Migration
{
    private const SLUG = 'las-enigmaticas-lineas-de-nazca-el-oasis-de-huacachina';

    private const LEGACY_COVER = 'tours/155.jpg';

    /** Same relative path stored in tours.seo_image (disk "public"). */
    private const NEW_COVER = 'tours/seo/01m260qgvsdt0xhhtbt346jjgy.webp';

    /** @return list<string> */
    private function newGallery(): array
    {
        return array_merge([self::NEW_COVER], array_map(fn (string $f): string => 'tours/'.$f.'.jpg', [
            'OASIS-DE-HUACACHINA-CON-BUGGIE-2',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-3',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-4',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-6-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-7-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-8-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-9-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-10-scaled-1',
            'OASIS-DE-HUACACHINA-CON-BUGGIE-11',
        ]));
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

        if ($current === $this->newGallery()) {
            return; // already applied
        }

        if (! $this->hasLegacyPhoto($current)) {
            Log::warning('nazca gallery: no legacy photo in the current gallery, left untouched.', [
                'tour_id' => $tour->id,
                'gallery' => $current,
                'cover' => $tour->cover_image,
            ]);

            return;
        }

        DB::table('tours')->where('id', $tour->id)->update([
            'gallery' => json_encode($this->newGallery(), JSON_UNESCAPED_SLASHES),
            'cover_image' => self::NEW_COVER,
        ]);

        Log::info('nazca gallery: replaced gallery and cover.', ['tour_id' => $tour->id, 'previous_cover' => $tour->cover_image]);
    }

    /** Restores the legacy gallery/cover only if they still hold what up() wrote. */
    public function down(): void
    {
        $tour = DB::table('tours')->where('slug', self::SLUG)->first();

        if (! $tour || json_decode((string) $tour->gallery, true) !== $this->newGallery()) {
            return;
        }

        DB::table('tours')->where('id', $tour->id)->update([
            'gallery' => json_encode($this->legacyGallery(), JSON_UNESCAPED_SLASHES),
            'cover_image' => self::LEGACY_COVER,
        ]);
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

    private function hasLegacyPhoto(array $gallery): bool
    {
        foreach ($gallery as $path) {
            if (is_string($path) && preg_match('#(OASIS-DE-HUACACHINA-ISLAS-BALLESTAS-EN-PARACAS-|FULL-DAY-A-LAS-LINEAS-DE-NAZCA-5)#', $path)) {
                return true;
            }
        }

        return false;
    }
};

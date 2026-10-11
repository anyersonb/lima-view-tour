<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Lightweight thumbnails for tour covers (the mobile listing card shows a
 * cover at ~100-160 px but used to download the full 1600 px file, up to
 * 2.4 MB for legacy PNGs).
 *
 * - Thumbnails live on the "public" disk (public/storage/tours/thumbs/), next
 *   to the originals, so they are served exactly like them in production.
 * - They are generated OUT of the request (artisan command, Maintenance
 *   action, or when the cover changes in the admin), never on the fly: a
 *   2.4 MB PNG decoded by GD on a page view would cost memory and seconds.
 * - url() never fails: with no thumbnail it falls back to the original.
 * - Uses native GD (already used by ImageOptimizer); no new dependency.
 */
class TourThumbnail
{
    public const WIDTH = 480;

    public const QUALITY = 78;

    private const DIRECTORY = 'tours/thumbs';

    /** Below this size an original is already light enough: no thumbnail needed. */
    private const SMALL_ENOUGH_BYTES = 61440;

    /** Only paths stored relative to the public disk can have a thumbnail. */
    public static function isStoredPath(?string $path): bool
    {
        return $path !== null
            && $path !== ''
            && ! str_starts_with($path, 'http://')
            && ! str_starts_with($path, 'https://')
            && ! str_starts_with($path, 'assets/')
            && ! str_starts_with($path, '/')
            && ! str_starts_with($path, self::DIRECTORY.'/');
    }

    public static function thumbPath(string $stored): string
    {
        $name = pathinfo($stored, PATHINFO_FILENAME);

        return self::DIRECTORY.'/'.$name.'-'.substr(md5($stored), 0, 6).'-w'.self::WIDTH.'.webp';
    }

    /** Thumbnail URL if one was generated; otherwise the original's URL. */
    public static function url(?string $stored): ?string
    {
        if (self::isStoredPath($stored)) {
            $thumb = self::thumbPath($stored);

            if (Storage::disk('public')->exists($thumb)) {
                return ImagePath::url($thumb);
            }
        }

        return ImagePath::url($stored);
    }

    /**
     * Generates the thumbnail for one stored cover.
     *
     * @return string created|exists|small|missing|unsupported|too_big|error
     */
    public static function generate(string $stored, bool $force = false): string
    {
        if (! self::isStoredPath($stored)) {
            return 'unsupported';
        }

        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return 'unsupported';
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($stored)) {
            return 'missing';
        }

        $thumb = self::thumbPath($stored);

        if (! $force && $disk->exists($thumb) && $disk->lastModified($thumb) >= $disk->lastModified($stored)) {
            return 'exists';
        }

        $raw = $disk->get($stored);

        if (! is_string($raw) || $raw === '') {
            return 'error';
        }

        $info = @getimagesizefromstring($raw);

        if ($info === false) {
            return 'unsupported';
        }

        [$w, $h] = $info;

        if ($w <= self::WIDTH && strlen($raw) <= self::SMALL_ENOUGH_BYTES) {
            return 'small';
        }

        // GD holds ~4-5 bytes per pixel decoded (+ the resized copy). Refuse
        // instead of dying with a fatal memory error in a web request.
        if (ImageOptimizer::exceedsPixelLimit($w, $h) || ! self::fitsInMemory($w, $h)) {
            return 'too_big';
        }

        $src = @imagecreatefromstring($raw);

        if ($src === false) {
            return 'unsupported';
        }

        unset($raw);

        $tw = min(self::WIDTH, $w);
        $th = max(1, (int) round($h * ($tw / $w)));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($src);

        ob_start();
        $ok = imagewebp($dst, null, self::QUALITY);
        $webp = (string) ob_get_clean();
        imagedestroy($dst);

        if (! $ok || $webp === '' || ! $disk->put($thumb, $webp)) {
            return 'error';
        }

        return 'created';
    }

    private static function fitsInMemory(int $w, int $h): bool
    {
        $limit = self::memoryLimitBytes();

        if ($limit < 0) {
            return true;
        }

        $needed = (int) ($w * $h * 5.5) + (self::WIDTH * self::WIDTH * 6);

        return $needed < $limit - memory_get_usage(true);
    }

    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return -1;
        }

        $num = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => $num,
        };
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Responsive WebP variants for every uploaded image (generalises TourThumbnail).
 *
 * Variants live NEXT TO the original, same disk: "<dir>/<name>-w{N}.webp" for
 * N in WIDTHS (480, 960, 1440), quality 78, never upscaled (a width is only
 * generated when it is smaller than the original's width). They are generated
 * out of the request path (Filament save, `images:variants`, Maintenance), never
 * on the fly.
 *
 * Reading side is tolerant: srcset()/responsive() return a variant URL only if
 * the file exists, else the original; they never produce a 404.
 *
 * Disks: "public" (tours/..., blog/..., seo/...) or "media" (home/...).
 */
final class ImageVariants
{
    public const WIDTHS = [480, 960, 1440];

    public const QUALITY = 78;

    /** Extensions of originals that get variants. */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var array<string, bool> request-scoped exists() memo */
    private static array $exists = [];

    /** Path of the variant for $width, next to the original. */
    public static function variantPath(string $stored, int $width): string
    {
        $dir = pathinfo($stored, PATHINFO_DIRNAME);
        $name = pathinfo($stored, PATHINFO_FILENAME);

        return ($dir === '.' ? '' : $dir.'/').$name.'-w'.$width.'.webp';
    }

    /** True for a path that is itself a variant / legacy thumbnail (never re-processed). */
    public static function isVariant(string $path): bool
    {
        return (bool) preg_match('/-w\d+\.webp$/i', $path) || str_starts_with($path, 'tours/thumbs/');
    }

    /** Can this stored path have variants on $disk? (relative upload, supported extension, not a variant) */
    public static function isEligible(?string $path, string $disk = 'public'): bool
    {
        if ($path === null || $path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            || str_starts_with($path, 'assets/') || str_starts_with($path, '/') || str_contains($path, '..') || self::isVariant($path)) {
            return false;
        }

        // media disk: only "dir/file" uploads; a bare name is a legacy asset under public/assets/banners.
        if ($disk === 'media' && ! str_contains($path, '/')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /** Widths worth generating for an original of $origWidth px: only strictly smaller ones. */
    public static function neededWidths(int $origWidth): array
    {
        return array_values(array_filter(self::WIDTHS, fn (int $w) => $w < $origWidth));
    }

    // ───────────────────────────── reading side ─────────────────────────────

    /**
     * "url480 480w, url960 960w, ..." with the variants that EXIST; null when
     * there are none (the caller then keeps a plain src).
     *
     * @param  array<int, int>|null  $sizes  subset of WIDTHS (default: all)
     */
    public static function srcset(?string $path, ?array $sizes = null, string $disk = 'public'): ?string
    {
        if (! self::isEligible($path, $disk)) {
            return null;
        }

        $parts = [];
        foreach ($sizes ?? self::WIDTHS as $w) {
            $variant = self::variantPath($path, (int) $w);

            if (self::has($disk, $variant)) {
                $parts[] = self::url($variant, $disk).' '.$w.'w';
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * URL to put in src for an image displayed at about $width CSS px: the
     * smallest existing variant that is at least $width wide; if none is wide
     * enough, the original (it is the biggest file); if the path cannot have
     * variants, the plain URL. Never a missing file.
     */
    public static function responsive(?string $path, int $width, string $disk = 'public'): ?string
    {
        if (! self::isEligible($path, $disk)) {
            return self::url($path, $disk);
        }

        foreach (self::WIDTHS as $w) {
            if ($w >= $width) {
                $variant = self::variantPath($path, $w);

                if (self::has($disk, $variant)) {
                    return self::url($variant, $disk);
                }
            }
        }

        return self::url($path, $disk);
    }

    /**
     * URL of any stored path (same rules as ImagePath::url / homeImage), but
     * for files on the storage disks it is root-relative ("/storage/...",
     * "/media/..."): the site serves from the domain root and APP_URL may be the
     * apex while visitors are on www (a 301 or a CSP block per image).
     * External URLs and public/assets paths are returned as ImagePath does.
     */
    public static function url(?string $path, string $disk = 'public'): ?string
    {
        $url = $disk === 'media' ? ImagePath::homeImage($path) : ImagePath::url($path);

        if ($url === null || $path === null || str_starts_with($path, 'http') || str_starts_with($path, 'assets/') || str_starts_with($path, '/')) {
            return $url;
        }

        // Bare legacy names (media disk) resolve to asset(): leave them alone.
        if ($disk === 'media' && ! str_contains($path, '/')) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return $url;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }


    /**
     * Quita esquema+host de una URL de los discos del sitio (/storage, /media, /assets):
     * APP_URL puede ser el apex mientras el visitante esta en www. Otras URLs no se tocan.
     */
    public static function relative(?string $url): ?string
    {
        if ($url === null || ! preg_match('#^https?://[^/]+(/(?:storage|media|assets)/.*)$#i', $url, $m)) {
            return $url;
        }

        return $m[1];
    }

    /**
     * Datos de <img> para una card de tour. Nunca devuelve el original pesado si hay algo mejor:
     *  1. variantes WebP 480/960/1440 existentes -> srcset (+ src = la de 480);
     *  2. sin variantes, miniatura TourThumbnail (480 px) si existe;
     *  3. el original (solo cuando no hay ninguna derivada).
     * Todas las URLs salen root-relativas. No se emite <source> sin type: un unico <img>.
     *
     * @return array{src: ?string, srcset: ?string, sizes: ?string}
     */
    public static function card(?string $path, string $sizes, int $maxWidth = 960): array
    {
        $set = self::srcset($path, array_values(array_filter(self::WIDTHS, fn (int $w) => $w <= $maxWidth)));

        if ($set !== null) {
            return ['src' => self::responsive($path, 480), 'srcset' => $set, 'sizes' => $sizes];
        }

        return ['src' => self::relative(TourThumbnail::url($path)), 'srcset' => null, 'sizes' => null];
    }

    /** Drop the memoized exists() lookups (tests, long-lived workers). */
    public static function flush(): void
    {
        self::$exists = [];
    }

    private static function has(string $disk, string $path): bool
    {
        return self::$exists[$disk.':'.$path] ??= Storage::disk($disk)->exists($path);
    }

    // ───────────────────────────── writing side ─────────────────────────────

    /**
     * Generates the missing variants of one stored original.
     *
     * @return array<int|string, string> width => created|exists|skipped|error ; or ['*' => missing|unsupported|too_big]
     */
    public static function generate(string $stored, string $disk = 'public', bool $force = false): array
    {
        if (! self::isEligible($stored, $disk) || ! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return ['*' => 'unsupported'];
        }

        $fs = Storage::disk($disk);

        if (! $fs->exists($stored)) {
            return ['*' => 'missing'];
        }

        $pending = self::pendingWidths($stored, $disk, $force);

        if ($pending === null) {
            return ['*' => 'unsupported'];
        }

        $result = [];
        foreach (self::WIDTHS as $w) {
            if (! isset($pending['needed'][$w])) {
                $result[$w] = 'skipped';
            } elseif (! in_array($w, $pending['missing'], true)) {
                $result[$w] = 'exists';
            }
        }

        if ($pending['missing'] === []) {
            return $result;
        }

        [$ow, $oh] = [$pending['w'], $pending['h']];

        if (ImageOptimizer::exceedsPixelLimit($ow, $oh) || ! self::fitsInMemory($ow, $oh)) {
            return ['*' => 'too_big'];
        }

        $raw = $fs->get($stored);
        $src = (is_string($raw) && $raw !== '') ? @imagecreatefromstring($raw) : false;
        unset($raw);

        if ($src === false) {
            return ['*' => 'unsupported'];
        }

        foreach ($pending['missing'] as $w) {
            $result[$w] = self::writeVariant($src, $ow, $oh, $w, $fs, self::variantPath($stored, $w)) ? 'created' : 'error';
        }

        imagedestroy($src);
        self::flush();
        ksort($result);

        return $result;
    }

    /**
     * Widths still missing for one original (header-only read; no decoding).
     * Null when the file is not a readable image.
     *
     * @return array{w: int, h: int, needed: array<int, bool>, missing: array<int, int>}|null
     */
    public static function pendingWidths(string $stored, string $disk = 'public', bool $force = false): ?array
    {
        $fs = Storage::disk($disk);
        $info = self::dimensions($fs, $stored);

        if ($info === null) {
            return null;
        }

        $needed = [];
        $missing = [];
        foreach (self::neededWidths($info[0]) as $w) {
            $needed[$w] = true;
            $variant = self::variantPath($stored, $w);

            if ($force || ! $fs->exists($variant) || $fs->lastModified($variant) < $fs->lastModified($stored)) {
                $missing[] = $w;
            }
        }

        return ['w' => $info[0], 'h' => $info[1], 'needed' => $needed, 'missing' => $missing];
    }

    /** @return array{0: int, 1: int}|null */
    private static function dimensions($fs, string $stored): ?array
    {
        try {
            $info = @getimagesize($fs->path($stored));
        } catch (\Throwable) {
            $info = false;
        }

        if ($info === false) {
            $raw = $fs->get($stored);
            $info = is_string($raw) ? @getimagesizefromstring($raw) : false;
        }

        return $info === false ? null : [(int) $info[0], (int) $info[1]];
    }

    private static function writeVariant($src, int $ow, int $oh, int $width, $fs, string $target): bool
    {
        $th = max(1, (int) round($oh * ($width / $ow)));
        $dst = imagecreatetruecolor($width, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $th, $ow, $oh);

        ob_start();
        $ok = imagewebp($dst, null, self::QUALITY);
        $webp = (string) ob_get_clean();
        imagedestroy($dst);

        return $ok && $webp !== '' && $fs->put($target, $webp);
    }

    private static function fitsInMemory(int $w, int $h): bool
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return true;
        }

        $num = (int) $raw;
        $limit = match (strtolower(substr($raw, -1))) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => $num,
        };

        // source decoded + largest variant (1440 wide) being resized.
        $needed = (int) ($w * $h * 5.5) + (1440 * 1440 * 6);

        return $needed < $limit - memory_get_usage(true);
    }
}

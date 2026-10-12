<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Convierte las subidas de imágenes del CMS a WebP y las reescala a un ancho
 * máximo (manteniendo proporción, sin agrandar). Usa GD nativo — no requiere
 * dependencias externas. Si GD no puede decodificar el origen (p.ej. AVIF),
 * guarda el archivo original tal cual para no perder la subida.
 */
class ImageOptimizer
{
    /**
     * Absolute pixel cap (40 MP), independent of memory_limit (which may be -1).
     * A tiny file can declare a huge canvas (decompression bomb): GD would try
     * to allocate ~5 bytes/pixel. Checked from the header BEFORE decoding.
     * Single source for ImageOptimizer and TourThumbnail.
     */
    public const MAX_PIXELS = 40_000_000;

    /** Longest side an uploaded original is kept at (px). */
    public const MAX_SIDE = 2000;

    public static function exceedsPixelLimit(int $w, int $h): bool
    {
        return $w * $h > self::MAX_PIXELS;
    }

    /**
     * Hallazgo de seguridad #5 (2026-09-30): la extensión en disco NUNCA sale
     * del cliente (`getClientOriginalExtension()`), siempre del MIME
     * DETECTADO por el propio archivo (finfo, vía Symfony
     * File::getMimeType(), que lee los bytes reales — no lo que el
     * navegador dice en el Content-Type). Sin esto, un archivo cuyo
     * CONTENIDO GD no puede decodificar (la rama de abajo) pero cuyo NOMBRE
     * dice "shell.php" se guardaba como "<ulid>.php": si el hosting
     * ejecuta PHP dentro de storage/, eso es RCE.
     *
     * `image/svg+xml` NO está en el mapa a propósito: un SVG es un vector de
     * XSS ejecutable si se abre directo en el navegador (mismo criterio que
     * ya aplicaba MediaAssetResource al excluirlo de sus tipos aceptados).
     * Cualquier MIME fuera del mapa cae al extension por defecto ('bin'),
     * nunca a algo que un servidor pueda interpretar como script.
     *
     * @var array<string, string>
     */
    private const SAFE_IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
    ];

    /**
     * Extensión segura para guardar en disco, derivada del MIME que el
     * propio archivo reporta tras inspeccionar su contenido (nunca el
     * nombre/extensión que mandó el cliente). `$map` permite restringir a
     * un subconjunto (p.ej. TestimonialResource solo admite jpg/png/webp);
     * por defecto usa el set completo de formatos de imagen que este
     * servicio sabe preservar.
     *
     * Type-hint `UploadedFile` (no `TemporaryUploadedFile`, aunque es lo
     * único que Filament pasa en producción) para poder probar esta función
     * directamente en tests con `UploadedFile::fake()`, que en
     * `Illuminate\Http\Testing\File::getMimeType()` devuelve el MIME
     * forzado — hace las veces del finfo real de producción sin tener que
     * levantar todo el pipeline de subida de Livewire.
     *
     * @param  array<string, string>|null  $map
     */
    public static function safeExtensionFromDetectedMime(UploadedFile $file, ?array $map = null, string $default = 'bin'): string
    {
        return ($map ?? self::SAFE_IMAGE_EXTENSIONS)[$file->getMimeType()] ?? $default;
    }

    /**
     * Closure para FileUpload::getUploadedFileNameForStorageUsing(): nombre
     * aleatorio (Str::ulid()) + extensión segura (ver
     * safeExtensionFromDetectedMime()). Para los FileUpload ->image() del
     * panel que NO pasan por store()/saver() (no necesitan reescalado ni
     * conversión a WebP) pero sí necesitan el mismo endurecimiento del
     * nombre en disco.
     *
     * @param  array<string, string>|null  $map
     */
    public static function safeImageNamer(?array $map = null, string $default = 'bin'): Closure
    {
        return static fn (TemporaryUploadedFile $file): string => Str::ulid().'.'.self::safeExtensionFromDetectedMime($file, $map, $default);
    }

    /**
     * Procesa y almacena la imagen. Devuelve la ruta relativa dentro del disco
     * (p.ej. "tours/covers/01hxxxx.webp").
     */
    public static function store(
        TemporaryUploadedFile $file,
        string $directory,
        int $maxWidth,
        int $quality = 82,
        string $disk = 'public'
    ): string {
        $directory = trim($directory, '/');
        $raw = @file_get_contents($file->getRealPath());

        // Reject oversized canvases from the header, before any decoding.
        $info = $raw !== false ? @getimagesizefromstring($raw) : false;

        if ($info !== false && self::exceedsPixelLimit($info[0], $info[1])) {
            Log::warning('image_optimizer.too_many_pixels', ['width' => $info[0], 'height' => $info[1], 'limit' => self::MAX_PIXELS, 'directory' => $directory]);

            throw new ImageTooLargeException($info[0], $info[1]);
        }

        // GD con WebP disponible? Si no, o si GD no decodifica el origen
        // (p.ej. AVIF), guardar el archivo original intacto — nunca falla.
        $gdReady = function_exists('imagewebp') && function_exists('imagecreatefromstring');
        $img = ($gdReady && $raw !== false) ? @imagecreatefromstring($raw) : false;

        if ($img === false) {
            $ext = self::safeExtensionFromDetectedMime($file);
            $path = $directory.'/'.strtolower((string) Str::ulid()).'.'.$ext;
            Storage::disk($disk)->put($path, $raw !== false ? $raw : file_get_contents($file->getRealPath()));

            return $path;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        // Never keep an original bigger than MAX_SIDE on either side (the page
        // serves responsive variants; the original is only the source for them).
        $scale = min(1, $maxWidth / $w, self::MAX_SIDE / $w, self::MAX_SIDE / $h);

        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $resized = imagecreatetruecolor($nw, $nh);
            // Preserva transparencia (PNG/WebP con alfa).
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        } else {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        imagewebp($img, null, $quality);
        $webp = ob_get_clean();
        imagedestroy($img);

        $path = $directory.'/'.strtolower((string) Str::ulid()).'.webp';
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }

    /**
     * Closure para FileUpload::saveUploadedFileUsing().
     *
     * @param  bool  $deletePrevious  Borra del disco el archivo anterior al
     *                                reemplazar (solo campos de UNA imagen).
     */
    public static function saver(
        string $directory,
        int $maxWidth,
        int $quality = 82,
        string $disk = 'public',
        bool $deletePrevious = false
    ): Closure {
        return function (TemporaryUploadedFile $file, FileUpload $component) use ($directory, $maxWidth, $quality, $disk, $deletePrevious) {
            // Capture the previous path now, but delete it only AFTER the new file is
            // stored: a rejected upload must not leave the record pointing to a deleted file.
            $old = $deletePrevious ? $component->getRecord()?->getAttribute($component->getName()) : null;

            try {
                $path = self::store($file, $directory, $maxWidth, $quality, $disk);

                // Responsive variants next to the original. Best effort: a failure here
                // must never lose the upload (images:variants fills the gaps later).
                try {
                    ImageVariants::generate($path, $disk);
                } catch (\Throwable $e) {
                    Log::warning('image variants failed', ['path' => $path, 'error' => $e->getMessage()]);
                }

                if (is_string($old) && $old !== '' && $old !== $path && Storage::disk($disk)->exists($old)) {
                    Storage::disk($disk)->delete($old);

                    // Its responsive variants are orphans now.
                    foreach (ImageVariants::WIDTHS as $w) {
                        Storage::disk($disk)->delete(ImageVariants::variantPath($old, $w));
                    }
                }

                return $path;
            } catch (ImageTooLargeException $e) {
                // Clear field error in the panel instead of a 500.
                throw ValidationException::withMessages([$component->getStatePath() => $e->getMessage()]);
            }
        };
    }
}

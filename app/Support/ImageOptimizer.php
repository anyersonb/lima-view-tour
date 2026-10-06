<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        if ($w > $maxWidth) {
            $nw = $maxWidth;
            $nh = (int) round($h * ($maxWidth / $w));
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
            if ($deletePrevious) {
                $old = $component->getRecord()?->getAttribute($component->getName());
                if (is_string($old) && $old !== '' && Storage::disk($disk)->exists($old)) {
                    Storage::disk($disk)->delete($old);
                }
            }

            return self::store($file, $directory, $maxWidth, $quality, $disk);
        };
    }
}

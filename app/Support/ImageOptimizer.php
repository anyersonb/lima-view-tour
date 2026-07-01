<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
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
            $ext = strtolower($file->getClientOriginalExtension() ?: 'img');
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

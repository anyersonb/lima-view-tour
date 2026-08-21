<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ImagePath
{
    /**
     * Resolve an image stored path into a browsable URL.
     *
     * Handles two coexisting conventions:
     *  - Seeded assets shipped under public/ (e.g. "assets/banners/foo.jpg")
     *  - Uploads via Filament on disk("public"), saved as relative paths
     *    like "tours/abc.jpg" served through the storage symlink.
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $encode = static fn (string $p): string => implode('/', array_map('rawurlencode', explode('/', ltrim($p, '/'))));

        if (str_starts_with($path, 'assets/') || str_starts_with($path, '/')) {
            return asset($encode($path));
        }

        return Storage::disk('public')->url($encode($path));
    }

    /**
     * Resolve an image used by the home sections (destinos, tipos de tour,
     * experiencias). Coexisting conventions:
     *  - Uploads via Filament FileUpload on disk("media"), directory "home"
     *    (relative path with a slash, e.g. "home/abc.jpg")
     *  - Legacy bare filenames shipped under public/assets/banners/
     *    (e.g. "Rectangle 19218.jpg", no slash)
     *  - Absolute/asset paths ("assets/…", "/…", "http…")
     */
    public static function homeImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $encode = static fn (string $p): string => implode('/', array_map('rawurlencode', explode('/', ltrim($p, '/'))));

        if (str_starts_with($path, 'assets/') || str_starts_with($path, '/')) {
            return asset($encode($path));
        }

        if (str_contains($path, '/')) {
            // Subida vía FileUpload en el disco "media".
            return Storage::disk('media')->url($encode($path));
        }

        // Nombre de archivo legacy bajo public/assets/banners/.
        return asset('assets/banners/' . rawurlencode($path));
    }
}

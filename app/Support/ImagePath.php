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
}

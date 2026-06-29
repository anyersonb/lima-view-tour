<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Delete the physical file when the record is deleted
        static::deleting(function (MediaAsset $asset): void {
            if (Storage::disk($asset->disk)->exists($asset->path)) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
        });
    }

    /**
     * Get the public URL of the asset.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Determine whether the asset is an image based on its MIME type.
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}

<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    /**
     * Override record creation to iterate the uploaded file paths and create
     * one MediaAsset row per file. Returns the first created record so Filament
     * can redirect to it after creation.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $files = $data['files'] ?? [];
        $collection = $data['collection'] ?? null;
        $disk = 'media';

        $first = null;

        foreach ((array) $files as $path) {
            $storage = Storage::disk($disk);

            $mime = null;
            $size = null;

            // Attempt to read meta from the stored file
            if ($storage->exists($path)) {
                $size = $storage->size($path);
                $mime = $storage->mimeType($path) ?: null;
            }

            $asset = MediaAsset::create([
                'disk'       => $disk,
                'path'       => $path,
                'name'       => basename($path),
                'mime'       => $mime,
                'size'       => $size,
                'collection' => $collection,
            ]);

            $first ??= $asset;
        }

        // Filament requires a Model to be returned; fall back to an empty one
        // if no files were uploaded (should not happen in practice).
        return $first ?? new MediaAsset;
    }
}

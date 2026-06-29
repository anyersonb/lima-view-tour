<?php

namespace App\Filament\Resources\BlockedDateResource\Pages;

use App\Filament\Resources\BlockedDateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedDate extends CreateRecord
{
    protected static string $resource = BlockedDateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BlockedDateResource::mutateFormDataBeforeCreate($data);
    }
}

<?php

namespace App\Filament\Resources\BlockedDateResource\Pages;

use App\Filament\Resources\BlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlockedDate extends EditRecord
{
    protected static string $resource = BlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Reconstruct the virtual "type" field when loading an existing record
        $data['type'] = isset($data['date']) && $data['date'] !== null ? 'date' : 'weekday';
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BlockedDateResource::mutateFormDataBeforeSave($data);
    }
}

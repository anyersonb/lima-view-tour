<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Reconstruye el toggle "tour personalizado" al abrir una reserva:
     * si no tiene tour del catálogo (tour_id null), es un tour manual.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['use_custom_tour'] = empty($data['tour_id']);

        return $data;
    }
}

<?php

namespace App\Filament\Resources\AbandonedCartResource\Pages;

use App\Filament\Resources\AbandonedCartResource;
use App\Models\AbandonedCart;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAbandonedCart extends ViewRecord
{
    protected static string $resource = AbandonedCartResource::class;

    protected function getHeaderActions(): array
    {
        /** @var AbandonedCart $record */
        $record = $this->record;

        return [
            Actions\Action::make('whatsapp')
                ->label('Escribir por WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url($record->whatsapp_url)
                ->openUrlInNewTab()
                ->visible(filled($record->whatsapp_url)),
            Actions\Action::make('call')
                ->label('Llamar')
                ->icon('heroicon-o-phone')
                ->url($record->tel_url)
                ->visible(filled($record->tel_url)),
            Actions\Action::make('email')
                ->label('Enviar correo')
                ->icon('heroicon-o-envelope')
                ->url($record->mailto_url)
                ->visible(filled($record->mailto_url)),
        ];
    }
}

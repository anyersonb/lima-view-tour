<?php

namespace App\Filament\Resources\PaymentLinkResource\Pages;

use App\Filament\Resources\PaymentLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentLink extends EditRecord
{
    protected static string $resource = PaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // B-5 (PARCIAL en la re-auditoría, ahora cerrado): un link con
            // un cobro real registrado no se borra (ver guardián en
            // PaymentLink::booted(), que mira paypal_capture_id — no solo
            // status==='paid' — así que también cubre reembolsado/rechazado.
            // Esto solo evita mostrar un botón que de todos modos no haría
            // nada).
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->paypal_capture_id !== null),
        ];
    }
}

<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Services\AbandonedCartService;
use App\Services\BookingNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Tras crear la reserva desde el panel, envía el correo de confirmación
     * al cliente y la notificación al administrador (salvo que se haya
     * desmarcado "Enviar correos" en el formulario).
     */
    protected function afterCreate(): void
    {
        if (($this->data['send_emails'] ?? true) === false) {
            return;
        }

        try {
            $paid = ($this->record->payment_status === 'paid')
                || ($this->record->status === 'confirmed');

            app(BookingNotifier::class)->send(
                collect([$this->record]),
                $paid,
                $this->record->customer_email,
            );

            // Cierra cualquier carrito abandonado activo de este cliente
            if ($this->record->customer_email) {
                app(AbandonedCartService::class)
                    ->markConverted(null, $this->record->customer_email);
            }
        } catch (\Throwable $e) {
            Log::warning('admin.create_booking.notify_failed', [
                'reference' => $this->record->reference ?? null,
                'message'   => $e->getMessage(),
            ]);
        }
    }
}

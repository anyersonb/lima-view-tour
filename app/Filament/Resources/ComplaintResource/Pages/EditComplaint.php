<?php

namespace App\Filament\Resources\ComplaintResource\Pages;

use App\Filament\Resources\ComplaintResource;
use App\Models\Complaint;
use Filament\Resources\Pages\EditRecord;

class EditComplaint extends EditRecord
{
    protected static string $resource = ComplaintResource::class;

    protected static ?string $title = 'Reclamación';

    // Registro legal: sin botón de eliminar.
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Los campos de solo lectura se muestran con formato legible.
        $record = $this->getRecord();
        $data['created_at'] = $record->created_at?->format('d/m/Y H:i');
        $data['response_due_at'] = $record->response_due_at?->format('d/m/Y');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === Complaint::STATUS_ANSWERED && empty($data['responded_at'])) {
            $data['responded_at'] = now();
        }

        return $data;
    }
}

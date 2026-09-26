<?php

namespace App\Filament\Resources\PaymentLinkResource\Pages;

use App\Filament\Resources\PaymentLinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentLink extends CreateRecord
{
    protected static string $resource = PaymentLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}

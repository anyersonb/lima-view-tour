<?php

namespace App\Filament\Resources\PaymentLinkResource\Pages;

use App\Filament\Resources\PaymentLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentLinks extends ListRecords
{
    protected static string $resource = PaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

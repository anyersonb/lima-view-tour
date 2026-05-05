<?php

namespace App\Filament\Resources\ContactLeadResource\Pages;

use App\Filament\Resources\ContactLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateContactLead extends CreateRecord
{
    protected static string $resource = ContactLeadResource::class;
}

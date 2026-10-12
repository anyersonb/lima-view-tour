<?php

namespace App\Filament\Auth;

use Filament\Forms\Components\Component;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

/**
 * Panel profile without e-mail editing. The e-mail is an access anchor
 * (canAccessPanel() domain check, MaintenanceAccess) and Filament's default
 * profile changes it without any verification. Registered via ->profile() in
 * AdminPanelProvider; lives outside app/Filament/Pages so discoverPages()
 * does not register it twice.
 */
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->disabled()
            ->dehydrated(false)
            ->helperText('El correo no se puede cambiar desde el perfil. Pídelo al administrador del sitio.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Defence in depth: never persist an e-mail coming from this form.
        unset($data['email']);

        return parent::mutateFormDataBeforeSave($data);
    }
}

<?php

namespace App\Filament\Auth;

use App\Services\RecaptchaService;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Login del panel con reCAPTCHA. Registrada explícitamente vía ->login() en
 * AdminPanelProvider; vive fuera de app/Filament/Pages para que el
 * discoverPages() del panel no la registre por duplicado.
 *
 * Usa la misma configuración global que los formularios públicos
 * (Configuración → reCAPTCHA en el admin, con fallback a .env).
 */
class Login extends BaseLogin
{
    /**
     * Token que el widget de Google inyecta desde el navegador vía
     * $wire.set(). Se verifica server-side contra Google en authenticate().
     */
    public ?string $recaptchaToken = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                ViewField::make('recaptcha')
                    ->view('filament.auth.recaptcha-field')
                    ->dehydrated(false)
                    ->visible(fn (): bool => $this->recaptchaActive()),
            ])
            ->statePath('data');
    }

    public function authenticate(): ?LoginResponse
    {
        if ($this->recaptchaActive()) {
            $svc = app(RecaptchaService::class);
            $token = $this->recaptchaToken;

            // Los tokens son de un solo uso: se descarta siempre para que un
            // intento fallido de credenciales no reutilice un token ya gastado.
            $this->recaptchaToken = null;

            if (! $svc->verify($token, 'admin_login', request()->ip())) {
                throw ValidationException::withMessages([
                    'data.email' => __('No pudimos verificar que no eres un robot. Vuelve a intentarlo.'),
                ]);
            }
        }

        return parent::authenticate();
    }

    /**
     * Solo se exige reCAPTCHA cuando está habilitado Y ambas claves existen:
     * con la site key ausente el widget no puede renderizar y bloquearía el
     * acceso al panel por completo (lockout).
     */
    private function recaptchaActive(): bool
    {
        $svc = app(RecaptchaService::class);

        return $svc->enabled() && filled($svc->siteKey()) && filled($svc->secretKey());
    }
}

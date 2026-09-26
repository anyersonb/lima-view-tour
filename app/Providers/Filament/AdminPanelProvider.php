<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->profile()
            ->brandName('Lima View Tours')
            ->colors([
                'primary' => Color::hex('#15474B'),
                'danger' => Color::hex('#C81F21'),
                'success' => Color::hex('#145212'),
                'warning' => Color::hex('#E29347'),
            ])
            ->favicon(asset('favicon.ico'))
            ->sidebarCollapsibleOnDesktop()
            // Item 6 (docs/payment-links/QA.md): fallback de "copiar al
            // portapapeles" para http (sin navigator.clipboard) — ver
            // resources/views/filament/partials/copy-fallback-script.blade.php.
            // Registrado UNA vez a nivel de panel para que cualquier
            // elemento con data-copy-text="..." (tabla, acciones, formulario
            // de Links de pago) quede copiable sin depender de ->copyable().
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.copy-fallback-script')->render(),
            )
            ->navigationGroups([
                'Catálogo',
                'Contenido',
                'Marketing',
                'Sistema',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

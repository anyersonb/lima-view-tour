<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class Maintenance extends Page
{
    protected static ?string $navigationIcon    = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup   = 'Sistema';
    protected static ?string $navigationLabel   = 'Mantenimiento';
    protected static ?string $title             = 'Mantenimiento del sitio';
    protected static ?int    $navigationSort    = 100;

    protected static string $view = 'filament.pages.maintenance';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_cache')
                ->label('Limpiar caché')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Limpiar toda la caché')
                ->modalDescription('Esto limpiará la caché de configuración, rutas, vistas y aplicación. El sitio seguirá funcionando normalmente.')
                ->modalSubmitActionLabel('Sí, limpiar')
                ->action(function () {
                    Artisan::call('cache:clear');
                    Artisan::call('config:clear');
                    Artisan::call('route:clear');
                    Artisan::call('view:clear');

                    Notification::make()
                        ->title('Caché limpiada correctamente')
                        ->body('Se limpiaron: caché de aplicación, configuración, rutas y vistas.')
                        ->success()
                        ->send();
                }),

            // TEMPORAL: ejecutar seeders de traducción (home + contenido BD). Quitar tras correr.
            Action::make('run_translations')
                ->label('Traducir contenido (EN/PT)')
                ->icon('heroicon-o-language')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Ejecutar traducciones')
                ->modalDescription('Rellena los valores EN y PT del contenido (home y BD) traducidos del español.')
                ->modalSubmitActionLabel('Sí, traducir')
                ->action(function () {
                    $out = [];
                    foreach (['TranslateHomeContentSeeder', 'TranslateContentSeeder'] as $cls) {
                        if (class_exists('Database\\Seeders\\' . $cls)) {
                            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => $cls, '--force' => true]);
                            $out[] = $cls . ': ' . trim(\Illuminate\Support\Facades\Artisan::output());
                        }
                    }
                    \Illuminate\Support\Facades\Artisan::call('cache:clear');

                    Notification::make()
                        ->title('Traducciones ejecutadas')
                        ->body(implode(' | ', $out) ?: 'No se encontraron seeders.')
                        ->success()
                        ->send();
                }),

            Action::make('optimize')
                ->label('Optimizar (producción)')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Optimizar para producción')
                ->modalDescription('Cachea la configuración y las vistas para acelerar el sitio. Ejecútalo después de cambiar el .env o desplegar código nuevo.')
                ->modalSubmitActionLabel('Sí, optimizar')
                ->action(function () {
                    Artisan::call('config:cache');
                    Artisan::call('view:cache');
                    Notification::make()
                        ->title('Sitio optimizado')
                        ->body('Configuración y vistas cacheadas. El sitio debería responder más rápido.')
                        ->success()
                        ->send();
                }),

            Action::make('view_sitemap')
                ->label('Ver sitemap')
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->url(url('/sitemap.xml'))
                ->openUrlInNewTab(),
        ];
    }
}

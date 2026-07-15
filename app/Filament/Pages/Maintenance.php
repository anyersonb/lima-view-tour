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

            Action::make('publish_assets')
                ->label('Recompilar assets')
                ->icon('heroicon-o-paint-brush')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Recompilar assets del panel')
                ->modalDescription('Vuelve a publicar los archivos JS/CSS de Filament en /public. Úsalo si el panel se ve raro o si funciones como "arrastrar para reordenar" dejaron de responder después de un deploy.')
                ->modalSubmitActionLabel('Sí, recompilar')
                ->action(function () {
                    Artisan::call('filament:assets');

                    Notification::make()
                        ->title('Assets recompilados')
                        ->body('Se republicaron los assets de Filament. Recarga el panel con Ctrl+F5.')
                        ->success()
                        ->send();
                }),

            Action::make('run_migrations')
                ->label('Ejecutar migraciones')
                ->icon('heroicon-o-circle-stack')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Ejecutar migraciones pendientes')
                ->modalDescription('Aplica a la base de datos los cambios de estructura pendientes (nuevas columnas/tablas). No borra datos. Ejecútalo después de desplegar código que incluya migraciones nuevas.')
                ->modalSubmitActionLabel('Sí, migrar')
                ->action(function () {
                    Artisan::call('migrate', ['--force' => true]);

                    Notification::make()
                        ->title('Migraciones ejecutadas')
                        ->body(trim(Artisan::output()) ?: 'No había migraciones pendientes.')
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

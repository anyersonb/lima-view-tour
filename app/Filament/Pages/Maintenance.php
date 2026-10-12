<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class Maintenance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Mantenimiento';

    protected static ?string $title = 'Mantenimiento del sitio';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.maintenance';

    /**
     * Runs artisan commands in order and reports the TRUTH: any non-zero exit
     * code or exception stops the sequence, is logged and shown as danger.
     *
     * @param  array<string, array<string, mixed>>  $commands  command => arguments
     */
    protected function runCommands(array $commands, string $okTitle, string $okBody, string $failTitle): void
    {
        $outputs = [];

        try {
            foreach ($commands as $command => $arguments) {
                $exit = Artisan::call($command, $arguments);
                $output = trim(Artisan::output());
                $outputs[] = $output;

                if ($exit !== 0) {
                    Log::error('Maintenance command failed', ['command' => $command, 'exit' => $exit, 'output' => $output]);
                    Notification::make()
                        ->title($failTitle)
                        ->body($output !== '' ? $output : "{$command}: código de salida {$exit}.")
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }
            }
        } catch (Throwable $e) {
            Log::error('Maintenance command threw', ['command' => $command ?? null, 'exception' => $e]);
            Notification::make()
                ->title($failTitle)
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $body = trim(implode(PHP_EOL, array_filter($outputs)));

        Notification::make()
            ->title($okTitle)
            ->body(count($commands) === 1 && $body !== '' ? $body : $okBody)
            ->success()
            ->send();
    }

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
                    $this->runCommands(
                        ['cache:clear' => [], 'config:clear' => [], 'route:clear' => [], 'view:clear' => []],
                        'Caché limpiada correctamente',
                        'Se limpiaron: caché de aplicación, configuración, rutas y vistas.',
                        'La limpieza de caché falló',
                    );
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
                    $this->runCommands(
                        ['config:cache' => [], 'view:cache' => []],
                        'Sitio optimizado',
                        'Configuración y vistas cacheadas. El sitio debería responder más rápido.',
                        'La optimización falló',
                    );
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
                    $this->runCommands(
                        ['filament:assets' => []],
                        'Assets recompilados',
                        'Se republicaron los assets de Filament. Recarga el panel con Ctrl+F5.',
                        'La recompilación de assets falló',
                    );
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
                    $this->runCommands(['migrate' => ['--force' => true]], 'Migraciones ejecutadas', 'No había migraciones pendientes.', 'Las migraciones fallaron');
                }),

            Action::make('tour_thumbnails')
                ->label('Generar miniaturas de tours')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Generar miniaturas de portadas')
                ->modalDescription('Crea una versión liviana (WebP, 480 px) de la portada de cada tour para las tarjetas del listado en móvil. Solo procesa las que faltan; se puede ejecutar varias veces.')
                ->modalSubmitActionLabel('Sí, generar')
                ->action(function () {
                    $this->runCommands(['tours:thumbnails' => []], 'Miniaturas generadas', 'Sin cambios.', 'Miniaturas con errores');
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

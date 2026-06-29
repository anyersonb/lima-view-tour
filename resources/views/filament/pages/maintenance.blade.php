<x-filament-panels::page>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        {{-- Caché --}}
        <x-filament::section>
            <x-slot name="heading">Caché del sitio</x-slot>
            <x-slot name="description">
                Usa el botón superior "Limpiar caché" para borrar configuración, rutas, vistas y caché de aplicación en un solo paso.
            </x-slot>

            <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
                    <code>cache:clear</code> — caché de aplicación y settings
                </li>
                <li class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
                    <code>config:clear</code> — configuración compilada
                </li>
                <li class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
                    <code>route:clear</code> — rutas compiladas
                </li>
                <li class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
                    <code>view:clear</code> — vistas compiladas
                </li>
            </ul>
        </x-filament::section>

        {{-- Sitemap --}}
        <x-filament::section>
            <x-slot name="heading">Sitemap XML</x-slot>
            <x-slot name="description">
                El sitemap se genera dinámicamente. No requiere cron ni regeneración manual.
            </x-slot>

            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                <p>Incluye automáticamente:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Página de inicio (ES / EN / PT)</li>
                    <li>Todos los tours publicados</li>
                    <li>Páginas de categorías activas</li>
                    <li>Reseñas, Nosotros, Contacto</li>
                    <li>Páginas legales (Términos / Privacidad)</li>
                </ul>
                <p class="mt-2">
                    URL:
                    <a href="{{ url('/sitemap.xml') }}"
                       target="_blank"
                       class="text-primary-600 hover:underline font-mono">
                        {{ url('/sitemap.xml') }}
                    </a>
                </p>
                <p>
                    Robots:
                    <a href="{{ url('/robots.txt') }}"
                       target="_blank"
                       class="text-primary-600 hover:underline font-mono">
                        {{ url('/robots.txt') }}
                    </a>
                </p>
            </div>
        </x-filament::section>

    </div>

</x-filament-panels::page>

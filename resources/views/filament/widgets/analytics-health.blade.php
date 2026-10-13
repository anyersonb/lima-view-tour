<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Estado de la medición</x-slot>

        @if (empty($problems))
            <p data-analytics-health="ok" style="color:#15803d;font-weight:600;">Midiendo visitas</p>
        @else
            <div data-analytics-health="problem" style="color:#b91c1c;">
                <p style="font-weight:600;">No se están midiendo todas las visitas</p>
                <ul style="list-style:disc;padding-left:1.25rem;">
                    @foreach ($problems as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

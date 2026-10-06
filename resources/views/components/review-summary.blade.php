@props([
    'stats',           // array de Tour::reviewStats(): ['total'=>int,'average'=>float|null,'distribution'=>[5=>['count','percent'],...]]
    'variant' => 'd',  // 'm' | 'd' — solo para unicidad de ids/aria
])

@php
    // Cero cálculos de agregados acá: total/average/distribution ya vienen
    // resueltos desde Tour::reviewStats(). Si no hay reseñas, no se pinta
    // nada — nunca un "0.0/5" ni un promedio inventado.
    if (empty($stats) || (int) ($stats['total'] ?? 0) === 0 || $stats['average'] === null) {
        return;
    }

    $total = (int) $stats['total'];
    $average = (float) $stats['average'];

    // Derivar full/media/vacía a partir del promedio YA calculado (no es un
    // cálculo de agregado, es solo la representación visual de un escalar).
    $fullStars = (int) floor($average);
    $hasHalfStar = round($average - $fullStars, 2) > 0;
    $emptyStars = max(0, 5 - $fullStars - ($hasHalfStar ? 1 : 0));

    $band = match (true) {
        $average >= 4.5 => __('ui.rating_excellent'),
        $average >= 4.0 => __('ui.rating_very_good'),
        $average >= 3.0 => __('ui.rating_good'),
        $average >= 2.0 => __('ui.rating_average'),
        default          => __('ui.rating_poor'),
    };

    $basedOnText = $total === 1 ? __('ui.based_on_one_review') : __('ui.based_on_reviews', ['count' => $total]);

    $starPath = 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z';

    $rowLabels = [
        5 => __('ui.n_stars', ['count' => 5]),
        4 => __('ui.n_stars', ['count' => 4]),
        3 => __('ui.n_stars', ['count' => 3]),
        2 => __('ui.n_stars', ['count' => 2]),
        1 => __('ui.one_star'),
    ];

    $summaryId = 'review-summary-' . ($variant ?? 'd');

    // El fondo real de la sección difiere entre árboles: en mobile la página
    // ya es cream-100 (ver <section class="bg-cream-100"> que envuelve todo
    // el árbol <768px), así que la tarjeta necesita blanco para no fundirse
    // con el fondo. En desktop la <section> de reseñas es blanca, así que la
    // tarjeta usa cream-100 (mismo patrón que las cajas Google/Tripadvisor
    // que reemplaza). Medido con getComputedStyle antes de fijarlo así.
    $cardBg = $variant === 'm' ? 'bg-white' : 'bg-cream-100';
@endphp

<div class="{{ $cardBg }} rounded-2xl ring-1 ring-teal-800/10 p-4 sm:p-5" role="group" aria-labelledby="{{ $summaryId }}">
    <p id="{{ $summaryId }}" class="sr-only">{{ __('ui.traveler_reviews') }}</p>
    <div class="flex items-start gap-4 sm:gap-6">

        {{-- Promedio --}}
        <div class="shrink-0 w-[92px]">
            <p class="font-price text-4xl font-bold text-teal-800 leading-none">{{ number_format($average, 1) }}</p>
            <div class="flex gap-0.5 mt-2" aria-hidden="true">
                @for ($s = 0; $s < $fullStars; $s++)
                    <svg class="w-3.5 h-3.5 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                @endfor
                @if ($hasHalfStar)
                    <span class="relative inline-block w-3.5 h-3.5">
                        <svg class="absolute inset-0 w-3.5 h-3.5 text-orange-200 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                        <span class="absolute inset-0 overflow-hidden" style="width:50%">
                            <svg class="w-3.5 h-3.5 text-orange-400 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                        </span>
                    </span>
                @endif
                @for ($s = 0; $s < $emptyStars; $s++)
                    <svg class="w-3.5 h-3.5 text-orange-200 fill-current" viewBox="0 0 20 20"><path d="{{ $starPath }}"/></svg>
                @endfor
            </div>
            <p class="text-xs font-bold text-teal-800 mt-1.5">{{ $band }}</p>
            <p class="text-[11px] text-teal-800/55 mt-0.5 leading-tight">{{ $basedOnText }}</p>
        </div>

        {{-- Distribución por estrella --}}
        <div class="flex-1 min-w-0 space-y-1.5" aria-hidden="true">
            @foreach ([5, 4, 3, 2, 1] as $stars)
                @php $row = $stats['distribution'][$stars] ?? ['count' => 0, 'percent' => 0]; @endphp
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-16 shrink-0 text-teal-800/70">{{ $rowLabels[$stars] }}</span>
                    <div class="flex-1 h-2 rounded-full bg-teal-800/10 overflow-hidden">
                        <div class="h-full rounded-full bg-teal-700" style="width: {{ $row['percent'] }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-teal-800/55 tabular-nums">{{ $row['percent'] }}%</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

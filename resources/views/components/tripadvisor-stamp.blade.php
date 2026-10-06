@props([
    'stamp',              // array{percent:int,count:int|null,updated_at:\Illuminate\Support\Carbon|null}|null de Setting::tripadvisorStamp()
    'variant' => 'd',     // 'm' | 'd' — solo para unicidad de ids/aria
    'spacingClass' => 'mt-4', // margen respecto al elemento anterior; default = comportamiento original
                              // (apilado bajo el resumen). El árbol desktop lo pasa vacío porque ahí el
                              // sello va DENTRO de la misma banda horizontal (gap del flex hace el espaciado).
])

@php
    // Sin dato configurado en Configuración → APIs: no se pinta el sello.
    // Nunca un porcentaje de ejemplo cableado.
    if (empty($stamp) || ! isset($stamp['percent'])) {
        return;
    }
@endphp

<div class="{{ $spacingClass }} inline-flex items-center gap-3 bg-state-success/10 ring-1 ring-state-success/20 rounded-2xl px-4 py-3">
    <span class="w-10 h-10 rounded-full bg-white grid place-items-center shrink-0 shadow-sm" aria-hidden="true">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="11" fill="#00AA6C"/>
            <circle cx="9" cy="10" r="2.5" fill="white"/>
            <circle cx="15" cy="10" r="2.5" fill="white"/>
            <circle cx="9" cy="10" r="1.2" fill="#00AA6C"/>
            <circle cx="15" cy="10" r="1.2" fill="#00AA6C"/>
            <path d="M7 14.5 C8.5 16.5 15.5 16.5 17 14.5" stroke="white" stroke-width="1.3" stroke-linecap="round" fill="none"/>
        </svg>
    </span>
    <div class="min-w-0">
        <p class="text-2xl font-bold text-state-success leading-none">{{ $stamp['percent'] }}%</p>
        <p class="text-xs font-semibold text-state-success mt-1">{{ __('ui.tripadvisor_recommends_it') }}</p>
    </div>
</div>

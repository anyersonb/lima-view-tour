{{--
    Grupos de filtro de /tours (Tarea D), compartidos entre el aside sticky
    de escritorio y el panel deslizante de móvil/tablet. Se incluye dos veces
    con un $prefix distinto ("desktop" / "mobile") para que los `id`/`for` no
    se dupliquen en el DOM; el motor en resources/js/tour-filters.js sincroniza
    ambas instancias por `data-filter-group` + `value`, así que el estado no
    se desincroniza si el usuario redimensiona la ventana.

    Variables esperadas en el scope del padre (tours/index.blade.php):
    $regionOptions, $priceMin, $priceMax, $durationOptions, $languageOptions,
    $groupOptions, $offerCount, $prefix
--}}
@php $prefix = $prefix ?? 'desktop'; @endphp

<div class="space-y-6">
    {{-- Destino / región --}}
    @if ($regionOptions->isNotEmpty())
        <div class="border-b border-teal-800/10 pb-5">
            <button type="button"
                    id="{{ $prefix }}-acc-destino"
                    data-accordion-trigger
                    aria-expanded="true"
                    aria-controls="{{ $prefix }}-panel-destino"
                    class="flex w-full items-center justify-between text-left text-sm font-bold uppercase tracking-[0.08em] text-teal-800">
                {{ __('ui.filter_destination') }}
                <svg class="w-4 h-4 shrink-0 text-teal-800/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div id="{{ $prefix }}-panel-destino" class="mt-3 space-y-2.5">
                @foreach ($regionOptions as $opt)
                    <label class="flex items-center justify-between gap-2 text-sm text-teal-800/85 cursor-pointer">
                        <span class="flex items-center gap-2">
                            <input type="checkbox"
                                   id="{{ $prefix }}-destino-{{ $opt['value'] }}"
                                   data-filter-checkbox
                                   data-filter-group="destination"
                                   value="{{ $opt['value'] }}"
                                   class="rounded border-teal-800/30 text-teal-700 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1">
                            {{ $opt['label'] }}
                        </span>
                        <span class="text-xs text-teal-800/45">{{ $opt['count'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Precio máximo --}}
    <div class="border-b border-teal-800/10 pb-5">
        <button type="button"
                id="{{ $prefix }}-acc-precio"
                data-accordion-trigger
                aria-expanded="true"
                aria-controls="{{ $prefix }}-panel-precio"
                class="flex w-full items-center justify-between text-left text-sm font-bold uppercase tracking-[0.08em] text-teal-800">
            {{ __('ui.filter_max_price') }}
            <svg class="w-4 h-4 shrink-0 text-teal-800/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
            </svg>
        </button>
        <div id="{{ $prefix }}-panel-precio" class="mt-3">
            <label for="{{ $prefix }}-price-range" class="sr-only">{{ __('ui.filter_max_price') }}</label>
            <input type="range"
                   id="{{ $prefix }}-price-range"
                   data-filter-price
                   class="tours-filters__price w-full accent-orange-500"
                   min="{{ (int) $priceMin }}"
                   max="{{ (int) $priceMax }}"
                   step="1"
                   value="{{ (int) $priceMax }}">
            <p class="mt-1.5 text-sm text-teal-800/70">
                <span data-filter-price-output>{{ __('ui.up_to') }} US${{ (int) $priceMax }}</span>
            </p>
        </div>
    </div>

    {{-- Duración --}}
    @if ($durationOptions->isNotEmpty())
        <div class="border-b border-teal-800/10 pb-5">
            <button type="button"
                    id="{{ $prefix }}-acc-duracion"
                    data-accordion-trigger
                    aria-expanded="true"
                    aria-controls="{{ $prefix }}-panel-duracion"
                    class="flex w-full items-center justify-between text-left text-sm font-bold uppercase tracking-[0.08em] text-teal-800">
                {{ __('ui.filter_duration') }}
                <svg class="w-4 h-4 shrink-0 text-teal-800/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div id="{{ $prefix }}-panel-duracion" class="mt-3 space-y-2.5">
                @foreach ($durationOptions as $opt)
                    <label class="flex items-center justify-between gap-2 text-sm text-teal-800/85 cursor-pointer">
                        <span class="flex items-center gap-2">
                            <input type="checkbox"
                                   id="{{ $prefix }}-duracion-{{ $opt['value'] }}"
                                   data-filter-checkbox
                                   data-filter-group="duration"
                                   value="{{ $opt['value'] }}"
                                   class="rounded border-teal-800/30 text-teal-700 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1">
                            {{ $opt['label'] }}
                        </span>
                        <span class="text-xs text-teal-800/45">{{ $opt['count'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Idioma --}}
    @if ($languageOptions->isNotEmpty())
        <div class="border-b border-teal-800/10 pb-5">
            <button type="button"
                    id="{{ $prefix }}-acc-idioma"
                    data-accordion-trigger
                    aria-expanded="true"
                    aria-controls="{{ $prefix }}-panel-idioma"
                    class="flex w-full items-center justify-between text-left text-sm font-bold uppercase tracking-[0.08em] text-teal-800">
                {{ __('ui.filter_language') }}
                <svg class="w-4 h-4 shrink-0 text-teal-800/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div id="{{ $prefix }}-panel-idioma" class="mt-3 space-y-2.5">
                @foreach ($languageOptions as $opt)
                    <label class="flex items-center justify-between gap-2 text-sm text-teal-800/85 cursor-pointer">
                        <span class="flex items-center gap-2">
                            <input type="checkbox"
                                   id="{{ $prefix }}-idioma-{{ $opt['value'] }}"
                                   data-filter-checkbox
                                   data-filter-group="language"
                                   value="{{ $opt['value'] }}"
                                   class="rounded border-teal-800/30 text-teal-700 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1">
                            {{ $opt['label'] }}
                        </span>
                        <span class="text-xs text-teal-800/45">{{ $opt['count'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Modalidad --}}
    @if ($groupOptions->isNotEmpty())
        <div class="border-b border-teal-800/10 pb-5">
            <button type="button"
                    id="{{ $prefix }}-acc-modalidad"
                    data-accordion-trigger
                    aria-expanded="true"
                    aria-controls="{{ $prefix }}-panel-modalidad"
                    class="flex w-full items-center justify-between text-left text-sm font-bold uppercase tracking-[0.08em] text-teal-800">
                {{ __('ui.filter_modality') }}
                <svg class="w-4 h-4 shrink-0 text-teal-800/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div id="{{ $prefix }}-panel-modalidad" class="mt-3 space-y-2.5">
                @foreach ($groupOptions as $opt)
                    <label class="flex items-center justify-between gap-2 text-sm text-teal-800/85 cursor-pointer">
                        <span class="flex items-center gap-2">
                            <input type="checkbox"
                                   id="{{ $prefix }}-modalidad-{{ $opt['value'] }}"
                                   data-filter-checkbox
                                   data-filter-group="modality"
                                   value="{{ $opt['value'] }}"
                                   class="rounded border-teal-800/30 text-teal-700 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1">
                            {{ $opt['label'] }}
                        </span>
                        <span class="text-xs text-teal-800/45">{{ $opt['count'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- En oferta --}}
    @if ($offerCount > 0)
        <div class="pb-1">
            <label class="flex items-center justify-between gap-2 text-sm font-semibold text-teal-800 cursor-pointer">
                <span class="flex items-center gap-2">
                    <input type="checkbox"
                           id="{{ $prefix }}-oferta"
                           data-filter-offer
                           class="rounded border-teal-800/30 text-orange-500 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1">
                    {{ __('ui.filter_on_offer') }}
                </span>
                <span class="text-xs text-teal-800/45">{{ $offerCount }}</span>
            </label>
        </div>
    @endif
</div>

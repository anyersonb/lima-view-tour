@props([
    'data',            // array normalizado de Tour::comparisonData()
    'variant' => 'd',  // 'm' (mobile tree) | 'd' (desktop tree) — solo para unicidad de ids/aria
])

@php
    if (empty($data) || !is_array($data)) { return; }

    $isOrange = ($data['color'] ?? 'teal') === 'orange';

    // Paletas por color de fondo
    if ($isOrange) {
        $sectionStyle = 'background: linear-gradient(155deg,#eda45b 0%,#d97b32 55%,#b5611f 100%);';
        $badgeCls   = 'bg-teal-900 text-white';
        $titleCls   = 'text-white';
        $hlCls      = 'text-teal-900';
        $introCls   = 'text-white/90';
        $convPanel  = 'bg-white/20 ring-white/25';
        $convTitle  = 'text-white/90';
        $convItem   = 'text-white/90';
        $convIco    = 'bg-white/25 text-white/70';
        $premPanel  = 'bg-white ring-black/5 shadow-lg';
        $premTitle  = 'text-orange-600';
        $premItem   = 'text-teal-900/90';
        $premIco    = 'bg-orange-500 text-white';
        $vsCls      = 'bg-teal-900 text-white';
        $footerCls  = 'bg-teal-900/90 text-white';
        $footerBar  = 'before:bg-orange-400';
    } else {
        $sectionStyle = 'background: linear-gradient(158deg,#124347 0%,#0c3438 55%,#07242700 100%),#0a2c2f;';
        $badgeCls   = 'bg-orange-500 text-white';
        $titleCls   = 'text-white';
        $hlCls      = 'text-orange-400';
        $introCls   = 'text-white/75';
        $convPanel  = 'bg-white/[0.055] ring-white/10';
        $convTitle  = 'text-white/55';
        $convItem   = 'text-white/70';
        $convIco    = 'bg-white/10 text-white/40';
        $premPanel  = 'bg-orange-500/[0.14] ring-orange-400/30';
        $premTitle  = 'text-orange-300';
        $premItem   = 'text-white';
        $premIco    = 'bg-orange-500 text-white';
        $vsCls      = 'bg-orange-500 text-white';
        $footerCls  = 'bg-white/[0.06] text-white/90';
        $footerBar  = 'before:bg-orange-400';
    }

    $titleId = 'cmp-' . ($variant ?? 'd') . '-title';
@endphp

<section aria-labelledby="{{ $titleId }}"
         class="relative isolate overflow-hidden rounded-3xl shadow-xl ring-1 ring-black/10 text-left"
         style="{{ $sectionStyle }}">

    {{-- textura sutil --}}
    <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.06]"
         style="background-image:radial-gradient(circle at 20% 15%,#fff 0,transparent 45%),radial-gradient(circle at 85% 80%,#fff 0,transparent 40%);"></div>

    <div class="p-5 sm:p-7 lg:p-8">

        {{-- Etiqueta superior --}}
        @if (!empty($data['badge']))
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.14em] {{ $badgeCls }}">
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.77l-5.2 2.73.99-5.79L1.58 7.62l5.82-.85L10 1.5z"/></svg>
                {{ $data['badge'] }}
            </span>
        @endif

        {{-- Cabecera: título + imagen --}}
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
            <div class="flex-1 min-w-0">
                <h2 id="{{ $titleId }}" class="font-display font-normal leading-tight text-[22px] sm:text-[26px] lg:text-[30px]">
                    <span class="{{ $titleCls }}">{{ $data['title'] }}</span>
                    @if (!empty($data['title_hl']))
                        <span class="{{ $hlCls }}"> {{ $data['title_hl'] }}</span>
                    @endif
                </h2>
                @if (!empty($data['intro']))
                    <p class="mt-3 text-[13px] sm:text-sm leading-relaxed {{ $introCls }}">{{ $data['intro'] }}</p>
                @endif
            </div>
            @if (!empty($data['image']))
                <img src="{{ $data['image'] }}" alt="" loading="lazy"
                     onerror="this.remove()"
                     class="w-full sm:w-44 lg:w-52 h-28 sm:h-32 lg:h-36 object-cover rounded-2xl ring-1 ring-white/15 shrink-0">
            @endif
        </div>

        {{-- Comparativa: apilada en móvil (1 columna), 2 columnas + VS en ≥sm --}}
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] items-stretch gap-3">

            {{-- Convencional --}}
            <div class="rounded-2xl ring-1 {{ $convPanel }} p-3.5 sm:p-5">
                <p class="text-[10px] sm:text-xs font-extrabold uppercase tracking-wider {{ $convTitle }} mb-3 text-center">{{ $data['conv_title'] ?: 'Tour convencional' }}</p>
                <ul class="space-y-2.5">
                    @foreach ($data['conv'] as $item)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 grid place-items-center w-4 h-4 sm:w-5 sm:h-5 rounded-full shrink-0 {{ $convIco }}" aria-hidden="true">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l8 8M14 6l-8 8"/></svg>
                            </span>
                            <span class="text-[13px] sm:text-sm leading-snug {{ $convItem }}">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- VS --}}
            <div class="flex items-center justify-center px-0.5">
                <span class="grid place-items-center w-8 h-8 sm:w-11 sm:h-11 rounded-full font-extrabold text-[11px] sm:text-sm ring-4 ring-black/10 {{ $vsCls }}">VS</span>
            </div>

            {{-- Premium --}}
            <div class="rounded-2xl ring-1 {{ $premPanel }} p-3.5 sm:p-5">
                <p class="text-[10px] sm:text-xs font-extrabold uppercase tracking-wider {{ $premTitle }} mb-3 text-center">{{ $data['prem_title'] ?: 'Nuestra experiencia premium' }}</p>
                <ul class="space-y-2.5">
                    @foreach ($data['prem'] as $item)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 grid place-items-center w-4 h-4 sm:w-5 sm:h-5 rounded-full shrink-0 {{ $premIco }}" aria-hidden="true">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg>
                            </span>
                            <span class="text-[13px] sm:text-sm leading-snug font-medium {{ $premItem }}">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Frase destacada final --}}
        @if (!empty($data['footer']))
            <div class="relative mt-5 rounded-2xl overflow-hidden {{ $footerCls }} pl-5 pr-4 py-3.5 before:absolute before:inset-y-0 before:left-0 before:w-1.5 {{ $footerBar }}">
                <div class="flex items-center gap-3">
                    <span class="text-xl sm:text-2xl leading-none shrink-0" aria-hidden="true">🌅</span>
                    <p class="text-[12.5px] sm:text-sm font-semibold leading-snug">{{ $data['footer'] }}</p>
                </div>
            </div>
        @endif
    </div>
</section>

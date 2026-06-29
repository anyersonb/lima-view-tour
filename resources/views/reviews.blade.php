@extends('layouts.app')

@section('title', ($locale ?? app()->getLocale()) === 'en' ? 'Customer reviews — ' . __('seo.site_name') : (($locale ?? app()->getLocale()) === 'pt' ? 'Avaliações de clientes — ' . __('seo.site_name') : 'Comentarios de nuestros clientes — ' . __('seo.site_name')))
@section('description', 'Reseñas reales de nuestros viajeros en Google, Tripadvisor y desde nuestra web. Conoce la experiencia de quienes ya viajaron con Lima View Tours.')

@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);

    $testimonials = $testimonials ?? collect();
    $stats        = $stats        ?? collect();
    $overall      = $overall      ?? ['count' => 0, 'rating' => 5.0];
    $links        = $links        ?? ['google' => null, 'tripadvisor' => null, 'trivago' => null];

    // Normaliza el origen a una clave estable
    $srcKey = function ($t): string {
        $s = strtolower(trim((string) ($t->source ?? '')));
        return str_contains($s, 'google') ? 'google'
            : (str_contains($s, 'tripadvisor') ? 'tripadvisor'
            : (str_contains($s, 'trivago') ? 'trivago' : 'web'));
    };

    // Metadatos visuales por origen
    $srcMeta = [
        'google'      => ['label' => 'Google',      'dot' => '#4285F4', 'soft' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        'tripadvisor' => ['label' => 'Tripadvisor', 'dot' => '#00AA6C', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'trivago'     => ['label' => 'Trivago',     'dot' => '#E5546C', 'soft' => 'bg-rose-50 text-rose-700 ring-rose-200'],
        'web'         => ['label' => $L('Nuestra web', 'Our website', 'Nosso site'), 'dot' => '#0E7C6B', 'soft' => 'bg-teal-50 text-teal-700 ring-teal-200'],
    ];

    // Tarjetas de resumen (solo orígenes con reseñas), en orden fijo
    $summaryOrder = ['google', 'tripadvisor', 'web', 'trivago'];
@endphp

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white min-h-[48vh] md:min-h-[52vh] flex flex-col justify-end" aria-labelledby="reviews-hero-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="eager" fetchpriority="high"
             width="1440" height="900">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/45 via-teal-900/50 to-teal-900/80"></div>
    </div>

    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="py-5 text-xs text-white/75">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400 transition-colors">{{ $L('Inicio', 'Home', 'Início') }}</a></li>
                <li aria-hidden="true" class="text-white/50">/</li>
                <li aria-current="page">{{ $L('Reseñas', 'Reviews', 'Avaliações') }}</li>
            </ol>
        </nav>
    </div>

    <div class="container mx-auto px-5 lg:px-10 pb-12 md:pb-16">
        <p class="text-[11px] uppercase tracking-[0.28em] text-orange-300 font-bold mb-3">
            {{ $L('Lo que dicen de nosotros', 'What they say about us', 'O que dizem de nós') }}
        </p>
        <h1 id="reviews-hero-title" class="font-display font-normal leading-[0.98] text-[40px] sm:text-[54px] lg:text-[64px] max-w-3xl">
            {{ $L('Comentarios de nuestros clientes', 'Reviews from our clients', 'Comentários dos nossos clientes') }}
        </h1>

        {{-- Rating global --}}
        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="text-orange-400 text-2xl leading-none" aria-hidden="true">
                @php $full = (int) round($overall['rating']); @endphp
                {{ str_repeat('★', max(0, min(5, $full))) }}<span class="text-white/30">{{ str_repeat('★', 5 - max(0, min(5, $full))) }}</span>
            </span>
            <span class="text-lg font-semibold">{{ number_format($overall['rating'], 1) }}/5</span>
            <span class="text-white/75 text-sm">
                {{ $overall['count'] }} {{ $L('reseñas verificadas', 'verified reviews', 'avaliações verificadas') }}
            </span>
        </div>
    </div>
</section>

{{-- ───────── RESUMEN POR ORIGEN ───────── --}}
@if ($overall['count'] > 0)
<section class="bg-cream-100 py-10 lg:py-14" aria-label="{{ $L('Resumen por plataforma', 'Summary by platform', 'Resumo por plataforma') }}">
    <div class="container mx-auto px-5 lg:px-10 max-w-6xl">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-5">
            @foreach ($summaryOrder as $key)
                @php $s = $stats[$key] ?? null; @endphp
                @if ($s)
                    <div class="bg-white rounded-2xl ring-1 ring-black/5 shadow-sm px-4 py-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full" style="background: {{ $srcMeta[$key]['dot'] }}"></span>
                            <span class="font-semibold text-teal-900">{{ $srcMeta[$key]['label'] }}</span>
                        </div>
                        <p class="font-display text-3xl lg:text-4xl text-teal-900 leading-none">{{ number_format($s['rating'], 1) }}</p>
                        <p class="text-orange-400 text-sm mt-1" aria-hidden="true">{{ str_repeat('★', max(0, min(5, (int) round($s['rating'])))) }}</p>
                        <p class="text-xs text-teal-800/60 mt-1.5">{{ $s['count'] }} {{ $L('reseñas', 'reviews', 'avaliações') }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ───────── LISTADO + FILTROS ───────── --}}
<section class="bg-white py-12 lg:py-16" x-data="{ f: 'all' }" aria-label="{{ $L('Comentarios', 'Reviews', 'Comentários') }}">
    <div class="container mx-auto px-5 lg:px-10 max-w-6xl">

        @if ($overall['count'] > 0)
            {{-- Filtros --}}
            <div class="flex flex-wrap gap-2 justify-center mb-9">
                @php
                    $chips = [['all', $L('Todos', 'All', 'Todos')]];
                    foreach ($summaryOrder as $k) { if (($stats[$k] ?? null)) { $chips[] = [$k, $srcMeta[$k]['label']]; } }
                @endphp
                @foreach ($chips as [$val, $label])
                    <button type="button" @click="f = '{{ $val }}'"
                            class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors"
                            :class="f === '{{ $val }}'
                                ? 'bg-teal-800 text-white border-teal-800'
                                : 'bg-white text-teal-800 border-teal-800/20 hover:border-teal-800/50'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Grid de comentarios --}}
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($testimonials as $t)
                    @php
                        $k = $srcKey($t);
                        $meta = $srcMeta[$k];
                        $rate = (int) round((float) ($t->rating ?? 5));
                        $quote = trim((string) ($t->quote ?? ''));
                        $initial = strtoupper(mb_substr(trim((string) ($t->name ?? '?')), 0, 1));
                    @endphp
                    <article class="flex flex-col bg-white rounded-2xl ring-1 ring-black/5 shadow-sm p-5"
                             x-show="f === 'all' || f === '{{ $k }}'" x-transition.opacity>
                        <div class="flex items-center gap-3">
                            @if (!empty($t->avatar))
                                <img src="{{ $t->avatar }}" alt="{{ $t->name }}" class="w-11 h-11 rounded-full object-cover shrink-0"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                <span class="w-11 h-11 rounded-full bg-teal-800 text-white font-semibold place-items-center shrink-0" style="display:none">{{ $initial }}</span>
                            @else
                                <span class="w-11 h-11 rounded-full bg-teal-800 text-white font-semibold grid place-items-center shrink-0">{{ $initial }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-teal-900 truncate">{{ $t->name }}</p>
                                @if (!empty($t->country))
                                    <p class="text-xs text-teal-800/60 truncate">{{ $t->country }}</p>
                                @endif
                            </div>
                            <span class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $meta['soft'] }}">
                                <span class="inline-block w-1.5 h-1.5 rounded-full" style="background: {{ $meta['dot'] }}"></span>
                                {{ $meta['label'] }}
                            </span>
                        </div>

                        <p class="text-orange-400 text-sm mt-3" aria-label="{{ $rate }} {{ $L('de 5 estrellas', 'out of 5 stars', 'de 5 estrelas') }}">
                            {{ str_repeat('★', max(0, min(5, $rate))) }}<span class="text-teal-900/15">{{ str_repeat('★', 5 - max(0, min(5, $rate))) }}</span>
                        </p>

                        <blockquote class="mt-2.5 text-[15px] leading-relaxed text-teal-900/85 flex-1">“{{ $quote }}”</blockquote>

                        @if ($t->tour)
                            <p class="mt-4 pt-3 border-t border-black/5 text-xs text-teal-800/60">
                                {{ $L('Tour', 'Tour', 'Tour') }}: <span class="font-medium text-teal-800">{{ $t->tour->title }}</span>
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            {{-- Estado vacío --}}
            <div class="text-center max-w-md mx-auto py-10">
                <p class="text-5xl mb-4" aria-hidden="true">💬</p>
                <h2 class="font-display text-2xl text-teal-900">{{ $L('Aún no hay reseñas publicadas', 'No reviews published yet', 'Ainda não há avaliações publicadas') }}</h2>
                <p class="text-teal-800/70 mt-2">{{ $L('Muy pronto compartiremos aquí la experiencia de nuestros viajeros.', 'Soon we will share our travelers\' experiences here.', 'Em breve compartilharemos aqui a experiência dos nossos viajantes.') }}</p>
            </div>
        @endif

        {{-- CTA: deja tu reseña --}}
        @if ($links['google'] || $links['tripadvisor'] || $links['trivago'])
            <div class="mt-14 text-center">
                <p class="font-display text-2xl text-teal-900 mb-1">{{ $L('¿Viajaste con nosotros?', 'Traveled with us?', 'Viajou com a gente?') }}</p>
                <p class="text-teal-800/70 mb-5">{{ $L('Déjanos tu reseña, nos ayuda muchísimo.', 'Leave us a review, it helps a lot.', 'Deixe sua avaliação, isso nos ajuda muito.') }}</p>
                <div class="flex flex-wrap gap-3 justify-center">
                    @if ($links['google'])
                        <a href="{{ $links['google'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-teal-800 text-white font-semibold text-sm hover:bg-teal-900 transition-colors">
                            <span class="inline-block w-2 h-2 rounded-full" style="background:#4285F4"></span> {{ $L('Reseña en Google', 'Review on Google', 'Avaliar no Google') }}
                        </a>
                    @endif
                    @if ($links['tripadvisor'])
                        <a href="{{ $links['tripadvisor'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white text-teal-800 border border-teal-800/25 font-semibold text-sm hover:border-teal-800/60 transition-colors">
                            <span class="inline-block w-2 h-2 rounded-full" style="background:#00AA6C"></span> Tripadvisor
                        </a>
                    @endif
                    @if ($links['trivago'])
                        <a href="{{ $links['trivago'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white text-teal-800 border border-teal-800/25 font-semibold text-sm hover:border-teal-800/60 transition-colors">
                            <span class="inline-block w-2 h-2 rounded-full" style="background:#E5546C"></span> Trivago
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>

@endsection

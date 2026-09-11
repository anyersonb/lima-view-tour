@extends('layouts.app')

{{-- Meta título/descripción administrables desde el panel Filament →
     Páginas → slug "resenas" → SEO — [idioma]. Vacío = cae al texto fijo
     de siempre (ver ReviewController@index, que carga $page). --}}
@section('title', ($page ?? null)?->metaTitle ?: (($locale ?? app()->getLocale()) === 'en' ? 'Customer reviews — ' . __('seo.site_name') : (($locale ?? app()->getLocale()) === 'pt' ? 'Avaliações de clientes — ' . __('seo.site_name') : 'Comentarios de nuestros clientes — ' . __('seo.site_name'))))
@section('description', ($page ?? null)?->metaDescription ?: 'Reseñas reales de nuestros viajeros en Google, Tripadvisor y desde nuestra web. Conoce la experiencia de quienes ya viajaron con Lima View Tours.')

@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string => $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);

    $testimonials = $testimonials ?? collect();
    $stats        = $stats        ?? collect();
    $overall      = $overall      ?? ['count' => 0, 'rating' => 5.0];
    $links        = $links        ?? ['google' => null, 'tripadvisor' => null, 'trivago' => null];
    $page         = $page         ?? null;

    // JSON-LD manual (panel → SEO — [idioma] → Datos estructurados). Sin
    // schema autogenerado propio para esta página: se inyecta el manual
    // cuando existe, si está vacío no se emite nada extra aquí.
    $customSchema = $page?->schemaJsonLd();

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

@if ($customSchema)
    @push('schema')
    <x-schema-raw :json="$customSchema" />
    @endpush
@endif

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

            {{-- Grid de comentarios (cards uniformes, con "ver más" cuando el texto es largo) --}}
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 items-start">
                @foreach ($testimonials as $t)
                    @php
                        $k = $srcKey($t);
                        $meta = $srcMeta[$k];
                        $rate = (int) round((float) ($t->rating ?? 5));
                        $quote = trim((string) ($t->quote ?? ''));
                        $initial = strtoupper(mb_substr(trim((string) ($t->name ?? '?')), 0, 1));
                        $long = mb_strlen($quote) > 200; // umbral para mostrar "ver más"
                    @endphp
                    <article class="flex flex-col bg-white rounded-2xl ring-1 ring-black/5 shadow-sm hover:shadow-md transition-shadow p-5 h-full"
                             x-data="{ exp: false }"
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

                        <blockquote class="mt-2.5 text-sm leading-relaxed text-teal-900/85"
                                    :class="(!exp && {{ $long ? 'true' : 'false' }}) ? 'line-clamp-5' : ''">“{{ $quote }}”</blockquote>

                        @if ($long)
                            <button type="button" @click="exp = !exp"
                                    class="self-start mt-1.5 text-xs font-bold text-orange-500 hover:text-orange-600 transition-colors">
                                <span x-show="!exp">{{ $L('Ver más', 'Read more', 'Ver mais') }}</span>
                                <span x-show="exp" x-cloak>{{ $L('Ver menos', 'Show less', 'Ver menos') }}</span>
                            </button>
                        @endif

                        @if ($t->tour)
                            <p class="mt-auto pt-4 text-xs text-teal-800/60">
                                <span class="border-t border-black/5 pt-3 block">{{ $L('Tour', 'Tour', 'Tour') }}: <span class="font-medium text-teal-800">{{ $t->tour->title }}</span></span>
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

        {{-- ───────── SISTEMA PROPIO: DEJA TU RESEÑA ───────── --}}
        <div id="dejar-resena" class="mt-16 scroll-mt-28">
            <div class="max-w-2xl mx-auto rounded-3xl bg-cream-100 ring-1 ring-teal-800/10 shadow-sm overflow-hidden">
                {{-- Encabezado --}}
                <div class="bg-teal-800 text-white px-6 sm:px-8 py-6 text-center">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-orange-300 font-bold mb-1.5">
                        {{ $L('Tu opinión cuenta', 'Your opinion matters', 'Sua opinião conta') }}
                    </p>
                    <h2 class="font-display text-2xl sm:text-[28px] leading-tight">
                        {{ $L('Deja tu reseña', 'Leave your review', 'Deixe sua avaliação') }}
                    </h2>
                    <p class="text-white/75 text-sm mt-1.5">
                        {{ $L('Cuéntanos cómo fue tu experiencia. Se publicará tras una breve revisión.',
                              'Tell us about your experience. It will be published after a quick review.',
                              'Conte-nos como foi sua experiência. Será publicada após uma breve revisão.') }}
                    </p>
                </div>

                <div class="px-6 sm:px-8 py-7"
                     x-data="{ rating: {{ (int) old('rating', 0) }}, hover: 0 }">

                    {{-- Mensaje de éxito --}}
                    @if (session('review_status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium px-4 py-3 flex items-start gap-2">
                            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ session('review_status') }}</span>
                        </div>
                    @endif

                    {{-- Errores de validación --}}
                    @if ($errors->any())
                        <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('reviews.store', ['locale' => $locale]) }}" class="space-y-5">
                        @csrf

                        {{-- Puntuación --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-teal-800 mb-2">
                                {{ $L('Tu puntuación', 'Your rating', 'Sua nota') }} <span class="text-orange-500">*</span>
                            </label>
                            <div class="flex gap-1.5 text-3xl leading-none select-none">
                                <template x-for="n in 5" :key="n">
                                    <button type="button" @click="rating = n" @mouseenter="hover = n" @mouseleave="hover = 0"
                                            class="transition-transform hover:scale-110 focus:outline-none"
                                            :aria-label="n + '/5'"
                                            :class="(hover || rating) >= n ? 'text-orange-400' : 'text-teal-800/20'">★</button>
                                </template>
                            </div>
                            <input type="hidden" name="rating" :value="rating">
                        </div>

                        {{-- Comentario --}}
                        <div>
                            <label for="rv-comment" class="block text-xs font-bold uppercase tracking-wide text-teal-800 mb-2">
                                {{ $L('Tu comentario', 'Your review', 'Seu comentário') }} <span class="text-orange-500">*</span>
                            </label>
                            <textarea id="rv-comment" name="comment" required minlength="10" maxlength="2000" rows="4"
                                      placeholder="{{ $L('¿Qué fue lo que más te gustó de tu viaje?', 'What did you enjoy most about your trip?', 'O que você mais gostou da sua viagem?') }}"
                                      class="w-full rounded-xl border border-teal-800/20 bg-white px-4 py-3 text-sm text-teal-900 placeholder:text-teal-800/35 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700">{{ old('comment') }}</textarea>
                        </div>

                        {{-- Nombre + País --}}
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="rv-name" class="block text-xs font-bold uppercase tracking-wide text-teal-800 mb-2">
                                    {{ $L('Nombre', 'Name', 'Nome') }} <span class="text-orange-500">*</span>
                                </label>
                                <input id="rv-name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                                       class="w-full rounded-xl border border-teal-800/20 bg-white px-4 py-3 text-sm text-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700">
                            </div>
                            <div>
                                <label for="rv-country" class="block text-xs font-bold uppercase tracking-wide text-teal-800 mb-2">
                                    {{ $L('País', 'Country', 'País') }}
                                </label>
                                <input id="rv-country" type="text" name="country" value="{{ old('country') }}" maxlength="120" autocomplete="country-name"
                                       placeholder="{{ $L('Opcional', 'Optional', 'Opcional') }}"
                                       class="w-full rounded-xl border border-teal-800/20 bg-white px-4 py-3 text-sm text-teal-900 placeholder:text-teal-800/35 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700">
                            </div>
                        </div>

                        {{-- Correo --}}
                        <div>
                            <label for="rv-email" class="block text-xs font-bold uppercase tracking-wide text-teal-800 mb-2">
                                {{ $L('Correo', 'Email', 'E-mail') }} <span class="text-orange-500">*</span>
                            </label>
                            <input id="rv-email" type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email"
                                   class="w-full rounded-xl border border-teal-800/20 bg-white px-4 py-3 text-sm text-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700">
                            <p class="text-[11px] text-teal-800/50 mt-1.5">{{ $L('No se publicará. Solo para verificar tu reseña.', 'It will not be published. Only to verify your review.', 'Não será publicado. Apenas para verificar sua avaliação.') }}</p>
                        </div>

                        {{-- Honeypot anti-spam --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] w-px h-px opacity-0">

                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm py-3 px-8 transition-colors">
                            {{ $L('Enviar reseña', 'Submit review', 'Enviar avaliação') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Plataformas externas (opcional, secundario) --}}
            @if ($links['google'] || $links['tripadvisor'] || $links['trivago'])
                <div class="mt-8 text-center">
                    <p class="text-sm text-teal-800/60 mb-3">{{ $L('¿Prefieres reseñarnos en otra plataforma?', 'Prefer to review us on another platform?', 'Prefere nos avaliar em outra plataforma?') }}</p>
                    <div class="flex flex-wrap gap-3 justify-center">
                        @if ($links['google'])
                            <a href="{{ $links['google'] }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white text-teal-800 border border-teal-800/20 font-semibold text-xs hover:border-teal-800/50 transition-colors">
                                <span class="inline-block w-2 h-2 rounded-full" style="background:#4285F4"></span> Google
                            </a>
                        @endif
                        @if ($links['tripadvisor'])
                            <a href="{{ $links['tripadvisor'] }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white text-teal-800 border border-teal-800/20 font-semibold text-xs hover:border-teal-800/50 transition-colors">
                                <span class="inline-block w-2 h-2 rounded-full" style="background:#00AA6C"></span> Tripadvisor
                            </a>
                        @endif
                        @if ($links['trivago'])
                            <a href="{{ $links['trivago'] }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white text-teal-800 border border-teal-800/20 font-semibold text-xs hover:border-teal-800/50 transition-colors">
                                <span class="inline-block w-2 h-2 rounded-full" style="background:#E5546C"></span> Trivago
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

@endsection

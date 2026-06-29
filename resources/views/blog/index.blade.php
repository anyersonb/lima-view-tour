@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string =>
        $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);
@endphp

@extends('layouts.app')

@section('title', $L('Blog de Viajes — Lima View Tours', 'Travel Blog — Lima View Tours', 'Blog de Viagens — Lima View Tours'))
@section('description', $L(
    'Descubre consejos, guías de destinos y experiencias de viaje en Lima, Ica y Cusco.',
    'Discover travel tips, destination guides and travel experiences in Lima, Ica and Cusco.',
    'Descubra dicas, guias de destinos e experiências de viagem em Lima, Ica e Cusco.'
))

@section('content')

    {{-- ── Hero ── --}}
    <section class="bg-teal-800 py-16 md:py-20" aria-labelledby="blog-heading">
        <div class="container mx-auto px-5 lg:px-10 text-center text-white">
            <p class="text-orange-400 font-semibold text-sm tracking-widest uppercase mb-2">Lima View Tours</p>
            <h1 id="blog-heading" class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight mb-4">
                {{ $L('Blog de Viajes', 'Travel Blog', 'Blog de Viagens') }}
            </h1>
            <p class="text-cream-100/80 text-lg max-w-2xl mx-auto">
                {{ $L(
                    'Guías, consejos y experiencias para que tu próxima aventura sea inolvidable.',
                    'Guides, tips and experiences to make your next adventure unforgettable.',
                    'Guias, dicas e experiências para tornar sua próxima aventura inesquecível.'
                ) }}
            </p>
        </div>
    </section>

    {{-- ── Breadcrumb ── --}}
    <nav aria-label="{{ $L('Ruta de navegación', 'Breadcrumb', 'Caminho de navegação') }}"
         class="bg-cream-100 border-b border-cream-200">
        <div class="container mx-auto px-5 lg:px-10 py-3">
            <ol class="flex items-center gap-2 text-sm text-teal-800/60" itemscope itemtype="https://schema.org/BreadcrumbList">
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="{{ route('home', ['locale' => $locale]) }}"
                       class="hover:text-teal-800 transition-colors">
                        <span itemprop="name">{{ $L('Inicio', 'Home', 'Início') }}</span>
                    </a>
                    <meta itemprop="position" content="1">
                </li>
                <li aria-hidden="true"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name" class="text-teal-800 font-medium">Blog</span>
                    <meta itemprop="position" content="2">
                </li>
            </ol>
        </div>
    </nav>

    {{-- ── Category filter pills ── --}}
    @if ($categories->isNotEmpty())
        <section class="bg-white border-b border-cream-200" aria-label="{{ $L('Filtrar por categoría', 'Filter by category', 'Filtrar por categoria') }}">
            <div class="container mx-auto px-5 lg:px-10 py-4 flex flex-wrap gap-2">
                <a href="{{ route('blog.index', ['locale' => $locale]) }}"
                   class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors border
                          {{ ! request('categoria') ? 'bg-teal-800 text-white border-teal-800' : 'bg-white text-teal-800 border-teal-800/30 hover:border-teal-800 hover:bg-cream-100' }}">
                    {{ $L('Todos', 'All', 'Todos') }}
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog.index', ['locale' => $locale, 'categoria' => $cat]) }}"
                       class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors border
                              {{ request('categoria') === $cat ? 'bg-teal-800 text-white border-teal-800' : 'bg-white text-teal-800 border-teal-800/30 hover:border-teal-800 hover:bg-cream-100' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Posts grid ── --}}
    <section class="bg-cream-100 py-12 lg:py-16" aria-labelledby="posts-list-label">
        <h2 id="posts-list-label" class="sr-only">{{ $L('Artículos del blog', 'Blog articles', 'Artigos do blog') }}</h2>
        <div class="container mx-auto px-5 lg:px-10">

            @if ($posts->isEmpty())
                <div class="text-center py-20 text-teal-800/50">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                    <p class="text-lg">{{ $L('No hay artículos publicados todavía.', 'No articles published yet.', 'Nenhum artigo publicado ainda.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    @foreach ($posts as $post)
                        <article class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col"
                                 itemscope itemtype="https://schema.org/BlogPosting">

                            {{-- Cover image --}}
                            <a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $post->slug]) }}"
                               class="block aspect-[16/9] overflow-hidden bg-cream-200" tabindex="-1" aria-hidden="true">
                                @if ($post->cover_image)
                                    <img src="{{ asset('storage/' . $post->cover_image) }}"
                                         alt="{{ $post->title }}"
                                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                         itemprop="image"
                                         loading="lazy"
                                         width="640" height="360">
                                @else
                                    {{-- Placeholder when no cover image --}}
                                    <div class="w-full h-full bg-teal-800/10 flex items-center justify-center">
                                        <svg class="w-16 h-16 text-teal-800/20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                        </svg>
                                    </div>
                                @endif
                            </a>

                            {{-- Card body --}}
                            <div class="p-5 flex flex-col flex-1">

                                {{-- Category badge --}}
                                @if ($post->category)
                                    <span class="inline-block self-start mb-3 px-3 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                        {{ $post->category }}
                                    </span>
                                @endif

                                {{-- Title --}}
                                <h2 class="font-display text-xl text-teal-800 leading-snug mb-2 line-clamp-2" itemprop="headline">
                                    <a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $post->slug]) }}"
                                       class="hover:text-orange-600 transition-colors">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                {{-- Excerpt --}}
                                <p class="text-teal-800/70 text-sm leading-relaxed mb-4 flex-1 line-clamp-3" itemprop="description">
                                    {{ Str::limit(strip_tags($post->excerpt), 120) }}
                                </p>

                                {{-- Meta: date + reading time --}}
                                <div class="flex items-center gap-3 text-xs text-teal-800/50 mt-auto pt-4 border-t border-cream-200">
                                    @if ($post->published_at)
                                        <time datetime="{{ $post->published_at->toIso8601String() }}" itemprop="datePublished">
                                            {{ $post->published_at->translatedFormat('d M Y') }}
                                        </time>
                                    @endif
                                    @if ($post->reading_minutes)
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $post->reading_minutes }} min {{ $L('lectura', 'read', 'leitura') }}</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if ($posts->hasPages())
                    <div class="mt-10 flex justify-center">
                        {{ $posts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- ── CTA Tours ── --}}
    <section class="bg-teal-800 py-12 text-center" aria-labelledby="blog-cta-label">
        <div class="container mx-auto px-5 lg:px-10">
            <h2 id="blog-cta-label" class="font-display text-3xl text-white mb-4">
                {{ $L('¿Listo para vivir la experiencia?', 'Ready to live the experience?', 'Pronto para viver a experiência?') }}
            </h2>
            <p class="text-cream-100/80 mb-6">
                {{ $L('Reserva tu tour en Lima, Ica o Cusco hoy mismo.', 'Book your tour in Lima, Ica or Cusco today.', 'Reserve seu tour em Lima, Ica ou Cusco hoje.') }}
            </p>
            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
               class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-full transition-colors">
                {{ $L('Ver todos los tours', 'See all tours', 'Ver todos os tours') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </section>

@endsection

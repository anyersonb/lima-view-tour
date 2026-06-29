@php
    $locale = app()->getLocale();
    $L = fn (string $es, string $en, string $pt): string =>
        $locale === 'pt' ? $pt : ($locale === 'en' ? $en : $es);
@endphp

@extends('layouts.app')

@section('title', $post->metaTitle ?: $post->title)
@section('description', $post->metaDescription ?: Str::limit(strip_tags($post->excerpt), 160))
@section('og_image', $post->cover_image ? asset('storage/' . $post->cover_image) : null)

@push('schema')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "BlogPosting",
            "headline": "{{ addslashes($post->title) }}",
            "image": "{{ $post->cover_image ? asset('storage/' . $post->cover_image) : asset('assets/banners/banner-hero.jpg') }}",
            "datePublished": "{{ $post->published_at?->toIso8601String() }}",
            "dateModified": "{{ $post->updated_at->toIso8601String() }}",
            "author": {
                "@type": "Person",
                "name": "{{ addslashes($post->author_name ?? 'Lima View Tours') }}"
            },
            "publisher": {
                "@type": "Organization",
                "name": "Lima View Tours",
                "logo": {
                    "@type": "ImageObject",
                    "url": "{{ asset('assets/logos/logo.png') }}"
                }
            },
            "mainEntityOfPage": {
                "@type": "WebPage",
                "@id": "{{ url()->current() }}"
            },
            "description": "{{ addslashes(Str::limit(strip_tags($post->excerpt), 160)) }}",
            "inLanguage": "{{ $locale }}"
            @if ($post->tags)
            ,"keywords": "{{ implode(', ', $post->tags) }}"
            @endif
        },
        {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "{{ $L('Inicio', 'Home', 'Início') }}",
                    "item": "{{ route('home', ['locale' => $locale]) }}"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "Blog",
                    "item": "{{ route('blog.index', ['locale' => $locale]) }}"
                },
                {
                    "@type": "ListItem",
                    "position": 3,
                    "name": "{{ addslashes($post->title) }}",
                    "item": "{{ url()->current() }}"
                }
            ]
        }
    ]
}
</script>
@endpush

@section('content')

    {{-- ── Breadcrumb ── --}}
    <nav aria-label="{{ $L('Ruta de navegación', 'Breadcrumb', 'Caminho de navegação') }}"
         class="bg-cream-100 border-b border-cream-200">
        <div class="container mx-auto px-5 lg:px-10 py-3">
            <ol class="flex items-center gap-2 text-sm text-teal-800/60 flex-wrap" itemscope itemtype="https://schema.org/BreadcrumbList">
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="{{ route('home', ['locale' => $locale]) }}"
                       class="hover:text-teal-800 transition-colors">
                        <span itemprop="name">{{ $L('Inicio', 'Home', 'Início') }}</span>
                    </a>
                    <meta itemprop="position" content="1">
                </li>
                <li aria-hidden="true"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></li>
                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <a itemprop="item" href="{{ route('blog.index', ['locale' => $locale]) }}"
                       class="hover:text-teal-800 transition-colors">
                        <span itemprop="name">Blog</span>
                    </a>
                    <meta itemprop="position" content="2">
                </li>
                <li aria-hidden="true"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></li>
                <li class="text-teal-800 font-medium truncate max-w-xs" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <span itemprop="name">{{ $post->title }}</span>
                    <meta itemprop="position" content="3">
                </li>
            </ol>
        </div>
    </nav>

    {{-- ── Article ── --}}
    <article class="bg-white" itemscope itemtype="https://schema.org/BlogPosting">

        {{-- Category + title header --}}
        <header class="bg-cream-100 py-10 md:py-14">
            <div class="container mx-auto px-5 lg:px-10 max-w-4xl">

                @if ($post->category)
                    <a href="{{ route('blog.index', ['locale' => $locale, 'categoria' => $post->category]) }}"
                       class="inline-block mb-4 px-3 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors">
                        {{ $post->category }}
                    </a>
                @endif

                <h1 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight mb-6"
                    itemprop="headline">
                    {{ $post->title }}
                </h1>

                {{-- Meta: author + date + reading time --}}
                <div class="flex flex-wrap items-center gap-4 text-sm text-teal-800/60">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <span itemprop="name">{{ $post->author_name ?? 'Lima View Tours' }}</span>
                        </span>
                    </span>

                    @if ($post->published_at)
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                            </svg>
                            <time datetime="{{ $post->published_at->toIso8601String() }}" itemprop="datePublished">
                                {{ $post->published_at->translatedFormat('d \d\e F \d\e Y') }}
                            </time>
                        </span>
                    @endif

                    @if ($post->reading_minutes)
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $post->reading_minutes }} min {{ $L('de lectura', 'read', 'de leitura') }}
                        </span>
                    @endif
                </div>
            </div>
        </header>

        {{-- Cover image --}}
        @if ($post->cover_image)
            <div class="container mx-auto px-5 lg:px-10 max-w-4xl -mt-4 mb-8">
                <img src="{{ asset('storage/' . $post->cover_image) }}"
                     alt="{{ $post->title }}"
                     class="w-full rounded-2xl shadow-md object-cover max-h-[480px]"
                     itemprop="image"
                     width="896" height="480">
            </div>
        @endif

        {{-- Body --}}
        <div class="container mx-auto px-5 lg:px-10 max-w-4xl pb-12">
            <div class="prose prose-lg max-w-none
                        prose-headings:font-display prose-headings:text-teal-800
                        prose-a:text-orange-600 prose-a:no-underline hover:prose-a:underline
                        prose-img:rounded-xl prose-img:shadow-sm
                        prose-blockquote:border-orange-500 prose-blockquote:text-teal-800/80"
                 itemprop="articleBody">
                {!! $post->body !!}
            </div>

            {{-- Tags --}}
            @if (! empty($post->tags))
                <div class="mt-8 pt-6 border-t border-cream-200 flex flex-wrap gap-2">
                    <span class="text-sm font-semibold text-teal-800/60 mr-1">{{ $L('Etiquetas:', 'Tags:', 'Etiquetas:') }}</span>
                    @foreach ($post->tags as $tag)
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-cream-100 text-teal-800 border border-cream-200">
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </article>

    {{-- ── Related posts ── --}}
    @if ($related->isNotEmpty())
        <section class="bg-cream-100 py-12 lg:py-16" aria-labelledby="related-label">
            <div class="container mx-auto px-5 lg:px-10">
                <h2 id="related-label" class="font-display text-2xl md:text-3xl text-teal-800 mb-8">
                    {{ $L('Artículos relacionados', 'Related articles', 'Artigos relacionados') }}
                </h2>
                <div class="related-slider" style="display:flex;gap:18px;overflow-x:auto;scroll-snap-type:x mandatory;padding:4px 2px 14px;scrollbar-width:thin;">
                    @foreach ($related as $rel)
                        <article class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col"
                                 style="flex:0 0 270px;max-width:270px;scroll-snap-align:start;">
                            <a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $rel->slug]) }}"
                               class="block aspect-[16/9] overflow-hidden bg-cream-200" tabindex="-1" aria-hidden="true">
                                @if ($rel->cover_image)
                                    <img src="{{ asset('storage/' . $rel->cover_image) }}"
                                         alt="{{ $rel->title }}"
                                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                         loading="lazy" width="400" height="225">
                                @else
                                    <div class="w-full h-full bg-teal-800/10 flex items-center justify-center">
                                        <svg class="w-12 h-12 text-teal-800/20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                        </svg>
                                    </div>
                                @endif
                            </a>
                            <div class="p-4 flex flex-col flex-1">
                                @if ($rel->category)
                                    <span class="inline-block self-start mb-2 px-3 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                        {{ $rel->category }}
                                    </span>
                                @endif
                                <h3 class="font-display text-base text-teal-800 leading-snug mb-2 line-clamp-2">
                                    <a href="{{ route('blog.show', ['locale' => $locale, 'slug' => $rel->slug]) }}"
                                       class="hover:text-orange-600 transition-colors">
                                        {{ $rel->title }}
                                    </a>
                                </h3>
                                @if ($rel->published_at)
                                    <time class="text-xs text-teal-800/50 mt-auto pt-3" datetime="{{ $rel->published_at->toIso8601String() }}">
                                        {{ $rel->published_at->translatedFormat('d M Y') }}
                                    </time>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── CTA Tours ── --}}
    <section class="bg-teal-800 py-12 text-center" aria-labelledby="post-cta-label">
        <div class="container mx-auto px-5 lg:px-10">
            <h2 id="post-cta-label" class="font-display text-3xl text-white mb-4">
                {{ $L('¿Te animaste a viajar?', 'Ready to travel?', 'Animou-se a viajar?') }}
            </h2>
            <p class="text-cream-100/80 mb-6">
                {{ $L('Reserva tu tour y vive Lima, Ica o Cusco como nunca.', 'Book your tour and experience Lima, Ica or Cusco like never before.', 'Reserve seu tour e experimente Lima, Ica ou Cusco como nunca.') }}
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

@extends('layouts.app')

@section('title', __('seo.home_title'))
@section('description', __('seo.home_description'))

@push('schema')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => __('seo.site_name'),
    'url' => url('/' . app()->getLocale()),
    'inLanguage' => app()->getLocale(),
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => url('/' . app()->getLocale() . '/tours?q={search_term_string}'),
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<section class="home-hero bg-cream-100" aria-labelledby="hero-title">
    <div class="container mx-auto py-20 md:py-28 text-center">
        <h1 id="hero-title">
            Lima View Tours
        </h1>
        <p class="mt-5 text-lg md:text-xl text-teal-800/80 max-w-2xl mx-auto">
            {{ __('seo.default_description') }}
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('tours.index', ['locale' => app()->getLocale()]) }}" class="btn--primary">
                {{ __('nav.book_now') }}
            </a>
            <a href="#destacados" class="btn--primary-outline">
                {{ __('common.view_details') }}
            </a>
        </div>
    </div>
</section>

<section id="destacados" class="py-16 bg-white" aria-labelledby="featured-title">
    <div class="container mx-auto">
        <header class="mb-10 text-center">
            <h2 id="featured-title">{{ __('nav.tours') }}</h2>
        </header>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @for ($i = 1; $i <= 3; $i++)
                <article class="tour-card" aria-labelledby="tour-{{ $i }}-title">
                    <picture>
                        <img src="https://placehold.co/600x400/15474b/f5f0ed?text=Tour+{{ $i }}"
                             alt="Tour {{ $i }} en Lima"
                             loading="lazy"
                             width="600" height="400"
                             class="w-full h-56 object-cover">
                    </picture>
                    <div class="p-5">
                        <h3 id="tour-{{ $i }}-title" class="text-xl">Tour ejemplo {{ $i }}</h3>
                        <p class="mt-2 text-sm text-teal-800/70">
                            <span class="sr-only">{{ __('common.duration') }}:</span>
                            <time datetime="PT4H">4h</time>
                        </p>
                        <p class="mt-3 price text-2xl">
                            {{ __('common.price_from') }} S/ 99 <span class="text-sm font-normal text-teal-800/60 not-italic">/ {{ __('common.per_person') }}</span>
                        </p>
                        <a href="#" class="btn--primary btn--block mt-4">{{ __('common.view_details') }}</a>
                    </div>
                </article>
            @endfor
        </div>
    </div>
</section>
@endsection

@extends('layouts.app')

@section('title', __('ui.search_results') . ' — ' . __('seo.site_name'))

@php $locale = app()->getLocale(); @endphp

@section('content')
<section class="bg-cream-100 pt-8 pb-4">
    <nav aria-label="Breadcrumb" class="container mx-auto px-5 lg:px-10 text-xs text-teal-800/70">
        <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-500">{{ __('ui.home') }}</a> &gt;
        <span>{{ __('ui.search_results') }}</span>
    </nav>
</section>

<section class="bg-cream-100 pb-10">
    <div class="container mx-auto px-5 lg:px-10">
        <h1 class="font-display text-3xl md:text-4xl text-center text-teal-800">{{ __('ui.search_results') }}</h1>
        <form action="{{ route('tours.results', ['locale' => $locale]) }}" method="get"
              class="mt-6 mx-auto max-w-3xl bg-white rounded-pill p-2 flex items-center gap-2 shadow-sm">
            <label class="flex-1 flex items-center gap-3 px-4">
                <svg class="w-5 h-5 text-teal-700/60" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('ui.search_destination') }}" class="w-full bg-transparent border-0 focus:ring-0 placeholder:text-teal-700/50 text-base">
            </label>
            <button class="btn--primary !px-8">{{ __('ui.search_tour') }}</button>
        </form>
    </div>
</section>

<section class="bg-cream-100 pb-20">
    <div class="container mx-auto px-5 lg:px-10">
        <p class="text-sm text-teal-800/70">{{ $tours->total() }} {{ $tours->total() !== 1 ? __('ui.results') : __('ui.result') }}{{ $q ? " para \"$q\"" : '' }}</p>
        <hr class="mt-2 mb-6 border-teal-800/15">

        <div class="space-y-5">
            @forelse ($tours as $tour)
                <article class="bg-white rounded-2xl overflow-hidden shadow-sm grid gap-6 sm:grid-cols-[minmax(0,260px)_minmax(0,1fr)_minmax(0,260px)] items-stretch p-4">
                    <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $tour->slug]) }}" class="relative block min-h-[160px]">
                        <img src="{{ $tour->cover_url }}" alt="{{ $tour->title }}" class="absolute inset-0 w-full h-full object-cover rounded-xl" loading="lazy" width="260" height="160">
                    </a>
                    <div class="flex flex-col justify-center">
                        <h3 class="font-display text-xl text-teal-800 leading-snug">{{ $tour->title }}</h3>
                        @if ($tour->rating)
                            <p class="mt-2 flex items-center gap-2 text-sm text-teal-800/80">
                                <span class="font-semibold">{{ $tour->rating }}</span>
                                <span class="text-orange-400 tracking-tight">★★★★★</span>
                                <span class="text-teal-800/60 text-xs">( {{ $tour->testimonials()->count() }} {{ __('ui.comments') }} )</span>
                            </p>
                        @endif
                        @php
                            $feats = array_filter([
                                $tour->languages ?? null,
                                $tour->{"duration_{$locale}"} ?? $tour->duration_es ?? null,
                                $tour->group_type ?? null,
                                __('ui.pickup_return'),
                            ]);
                        @endphp
                        <ul class="mt-3 grid grid-cols-4 gap-2 text-[10px] text-teal-800/70 max-w-md">
                            @foreach ($feats as $feat)
                                <li class="flex flex-col items-center gap-1">
                                    <span class="w-7 h-7 rounded-full bg-cream-200 grid place-items-center text-teal-700"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    <span>{{ $feat }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-lg border border-teal-800/15 overflow-hidden self-center">
                        <p class="bg-teal-700 text-white text-[10px] tracking-[0.2em] uppercase text-center py-1.5">{{ __('ui.price_per_person') }}</p>
                        <div class="p-4 text-center">
                            <p class="text-sm">
                                @if ($tour->price_before && $tour->price_before > $tour->price)
                                    <span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60">{{ __('ui.before') }}</span>
                                    <span class="font-price text-base text-teal-800/60 line-through ml-1">${{ number_format((float) $tour->price_before, 0) }}</span>
                                    <span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60 ml-2">{{ __('ui.now') }}</span>
                                @endif
                                <span class="font-price text-2xl text-state-error ml-1">${{ number_format((float) $tour->price, 0) }}</span>
                            </p>
                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $tour->slug]) }}"
                               class="btn--primary btn--block mt-3">{{ __('ui.book_tour') }}</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="text-center py-16">
                    <p class="text-teal-800/70 text-lg">{{ __('ui.no_results_found') }}{{ $q ? " para \"$q\"" : '' }}.</p>
                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-6 inline-block">
                        {{ __('ui.see_all_tours') }}
                    </a>
                </div>
            @endforelse
        </div>

        @if ($tours->hasPages())
            <div class="mt-10">
                {{ $tours->links() }}
            </div>
        @endif
    </div>
</section>
@endsection

@extends('layouts.app')

@section('title', 'Resultados de búsqueda — ' . __('seo.site_name'))

@php
    $locale = app()->getLocale();
    $q = request('q', 'Desierto');
    $results = [
        ['title' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas', 'badge' => '5 CUPOS DE 20 DISPONIBLES', 'badgeType' => 'error', 'before' => 125, 'now' => 100, 'rating' => '4.8', 'reviews' => 28, 'img' => 'Rectangle 19210.jpg'],
        ['title' => 'Tour de día completo al Oasis de Huacachina con buggie privado (canam)+ Islas Ballestas en Paracas', 'badge' => 'SE RESERVÓ 5 VECES AYER', 'badgeType' => 'warn', 'before' => 125, 'now' => 100, 'rating' => '4.8', 'reviews' => 28, 'img' => 'Rectangle 19211.jpg'],
        ['title' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas', 'badge' => 'CON MÁS RESERVAS TODO EL MES', 'badgeType' => 'success', 'before' => 125, 'now' => 100, 'rating' => '4.8', 'reviews' => 28, 'img' => 'Rectangle 19212.jpg'],
    ];
@endphp

@section('content')
<section class="bg-cream-100 pt-8 pb-4">
    <nav aria-label="Breadcrumb" class="container mx-auto px-5 lg:px-10 text-xs text-teal-800/70">
        <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-500">Inicio</a> &gt;
        <span>Resultados de búsqueda</span>
    </nav>
</section>

<section class="bg-cream-100 pb-10">
    <div class="container mx-auto px-5 lg:px-10">
        <h1 class="font-display text-3xl md:text-4xl text-center text-teal-800">Resultados de búsqueda</h1>
        <form action="{{ route('tours.results', ['locale' => $locale]) }}" method="get"
              class="mt-6 mx-auto max-w-3xl bg-white rounded-pill p-2 flex items-center gap-2 shadow-sm">
            <label class="flex-1 flex items-center gap-3 px-4">
                <svg class="w-5 h-5 text-teal-700/60" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="{{ $q }}" class="w-full bg-transparent border-0 focus:ring-0 placeholder:text-teal-700/50 text-base">
            </label>
            <button class="btn--primary !px-8">Buscar tour</button>
        </form>
    </div>
</section>

<section class="bg-cream-100 pb-20">
    <div class="container mx-auto px-5 lg:px-10">
        <p class="text-sm text-teal-800/70">{{ count($results) }} resultados</p>
        <hr class="mt-2 mb-6 border-teal-800/15">

        <div class="space-y-5">
            @foreach ($results as $tour)
                @php
                    $bg = match($tour['badgeType']) {
                        'success' => 'bg-state-success',
                        'error' => 'bg-state-error',
                        default => 'bg-orange-400',
                    };
                @endphp
                <article class="bg-white rounded-2xl overflow-hidden shadow-sm grid gap-6 sm:grid-cols-[minmax(0,260px)_minmax(0,1fr)_minmax(0,260px)] items-stretch p-4">
                    <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => 'huacachina-paracas']) }}" class="relative block min-h-[160px]">
                        <img src="{{ asset('assets/banners/' . $tour['img']) }}" alt="{{ $tour['title'] }}" class="absolute inset-0 w-full h-full object-cover rounded-xl" loading="lazy">
                        <span class="absolute top-3 left-3 right-3 {{ $bg }} text-white text-[10px] uppercase tracking-[0.15em] font-semibold py-1.5 px-3 rounded-md text-center">{{ $tour['badge'] }}</span>
                    </a>
                    <div class="flex flex-col justify-center">
                        <h3 class="font-display text-xl text-teal-800 leading-snug">{{ $tour['title'] }}</h3>
                        <p class="mt-2 flex items-center gap-2 text-sm text-teal-800/80">
                            <span class="font-semibold">{{ $tour['rating'] }}</span>
                            <span class="text-orange-400 tracking-tight">★★★★★</span>
                            <span class="text-teal-800/60 text-xs">( {{ $tour['reviews'] }} Comentarios )</span>
                        </p>
                        <ul class="mt-3 grid grid-cols-4 gap-2 text-[10px] text-teal-800/70 max-w-md">
                            @foreach (['Español/Inglés','Full Day','Tour Grupal','Recojo y retorno'] as $feat)
                                <li class="flex flex-col items-center gap-1">
                                    <span class="w-7 h-7 rounded-full bg-cream-200 grid place-items-center text-orange-500"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="6"/></svg></span>
                                    <span>{{ $feat }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-lg border border-teal-800/15 overflow-hidden self-center">
                        <p class="bg-teal-700 text-white text-[10px] tracking-[0.2em] uppercase text-center py-1.5">PRECIO POR PERSONA</p>
                        <div class="p-4 text-center">
                            <p class="text-sm">
                                <span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60">Antes</span>
                                <span class="font-price text-base text-teal-800/60 line-through ml-1">${{ $tour['before'] }}</span>
                                <span class="text-[11px] tracking-[0.2em] uppercase text-teal-800/60 ml-2">Ahora</span>
                                <span class="font-price text-2xl text-state-error ml-1">${{ $tour['now'] }}</span>
                            </p>
                            <a href="#" class="btn--primary btn--block mt-3">Reservar tour</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endsection

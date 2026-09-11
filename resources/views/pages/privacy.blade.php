@extends('layouts.app')

{{-- Meta título/descripción administrables desde el panel Filament →
     Páginas → slug "privacidad" → SEO — [idioma]. Vacío = cae al texto
     fijo (ver PageController@privacy, que carga $page). --}}
@section('title', ($page ?? null)?->metaTitle ?: ($title . ' – Lima View Tours'))
@section('description', ($page ?? null)?->metaDescription ?: __('legal.last_updated'))

@php
    // JSON-LD manual (panel → SEO — [idioma] → Datos estructurados). Sin
    // schema autogenerado propio para esta página: se inyecta el manual
    // cuando existe, si está vacío no se emite nada extra aquí.
    $customSchema = ($page ?? null)?->schemaJsonLd();
@endphp

@if ($customSchema)
    @push('schema')
    <x-schema-raw :json="$customSchema" />
    @endpush
@endif

@section('content')
<div class="container mx-auto px-5 lg:px-10 py-16">

    {{-- Page header --}}
    <header class="mb-12 border-b border-gray-200 pb-8">
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-700 leading-tight">
            {{ $title }}
        </h1>
        <p class="mt-3 text-sm text-gray-500">{{ __('legal.last_updated') }}</p>
    </header>

    {{-- Section 1: Data collected --}}
    <section class="mb-10" aria-labelledby="privacy-s1">
        <h2 id="privacy-s1" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.privacy_s1_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.privacy_s1_body') }}</p>
    </section>

    {{-- Section 2: Use of data --}}
    <section class="mb-10" aria-labelledby="privacy-s2">
        <h2 id="privacy-s2" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.privacy_s2_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.privacy_s2_body') }}</p>
    </section>

    {{-- Section 3: Sharing --}}
    <section class="mb-10" aria-labelledby="privacy-s3">
        <h2 id="privacy-s3" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.privacy_s3_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.privacy_s3_body') }}</p>
    </section>

    {{-- Section 4: Cookies --}}
    <section class="mb-10" aria-labelledby="privacy-s4">
        <h2 id="privacy-s4" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.privacy_s4_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.privacy_s4_body') }}</p>
    </section>

    {{-- Section 5: User rights --}}
    <section class="mb-10" aria-labelledby="privacy-s5">
        <h2 id="privacy-s5" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.privacy_s5_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.privacy_s5_body') }}</p>
    </section>

    {{-- Back link --}}
    <div class="mt-12 pt-8 border-t border-gray-200">
        <a href="{{ route('home', ['locale' => $locale]) }}"
           class="inline-flex items-center gap-2 text-teal-700 hover:text-orange-500 font-medium transition-colors">
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            {{ __('common.back_home') }}
        </a>
    </div>
</div>
@endsection

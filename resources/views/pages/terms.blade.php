@extends('layouts.app')

{{-- Meta título/descripción administrables desde el panel Filament →
     Páginas → slug "terminos" → SEO — [idioma]. Vacío = cae al texto fijo
     (ver PageController@terms, que carga $page). --}}
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

    {{-- Section 1: Acceptance --}}
    <section class="mb-10" aria-labelledby="terms-s1">
        <h2 id="terms-s1" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.terms_s1_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.terms_s1_body') }}</p>
    </section>

    {{-- Section 2: Service --}}
    <section class="mb-10" aria-labelledby="terms-s2">
        <h2 id="terms-s2" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.terms_s2_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.terms_s2_body') }}</p>
    </section>

    {{-- Section 3: Bookings --}}
    <section class="mb-10" aria-labelledby="terms-s3">
        <h2 id="terms-s3" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.terms_s3_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.terms_s3_body') }}</p>
    </section>

    {{-- Section 4: Cancellations --}}
    <section class="mb-10" aria-labelledby="terms-s4">
        <h2 id="terms-s4" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.terms_s4_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.terms_s4_body') }}</p>
    </section>

    {{-- Section 5: Contact --}}
    <section class="mb-10" aria-labelledby="terms-s5">
        <h2 id="terms-s5" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
            {{ __('legal.terms_s5_title') }}
        </h2>
        <p class="text-gray-700 leading-relaxed">{{ __('legal.terms_s5_body') }}</p>
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

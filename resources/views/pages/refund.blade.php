@extends('layouts.app')

{{-- Meta título/descripción administrables desde Filament → Páginas → slug
     "politica-de-cambios-y-devoluciones". Vacío = texto fijo del idioma. --}}
@section('title', ($page ?? null)?->metaTitle ?: ($title . ' – Lima View Tours'))
@section('description', ($page ?? null)?->metaDescription ?: __('legal.refund_description'))

@php
    $customSchema = ($page ?? null)?->schemaJsonLd();
    // TODO cliente: confirmar el plazo exacto de devolución (días hábiles) y,
    // si cambia, editarlo en Configuración → Datos legales → "Plazo de
    // devolución". Mientras esté vacío se muestra 10 (valor provisional).
    $refundDays = (int) (\App\Models\Setting::get('legal_refund_days') ?: 10);
    $sections = [
        1 => ['s1', []],
        2 => ['s2', []],
        // TODO cliente: confirmar si el cambio de fecha es sin penalidad hasta 24 h antes.
        3 => ['s3', []],
        // TODO cliente: confirmar la devolución cuando ninguna fecha alternativa sirve (fuerza mayor / cancelación por LVT).
        4 => ['s4', []],
        5 => ['s5', ['email' => \App\Support\LegalInfo::email(), 'phone' => \App\Support\LegalInfo::phone()]],
        6 => ['s6', ['days' => $refundDays]],
        7 => ['s7', []],
    ];
@endphp

@if ($customSchema)
    @push('schema')
    <x-schema-raw :json="$customSchema" />
    @endpush
@endif

@section('content')
<div class="container mx-auto px-5 lg:px-10 py-16">

    <header class="mb-12 border-b border-gray-200 pb-8">
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-700 leading-tight">
            {{ $title }}
        </h1>
        <p class="mt-3 text-sm text-gray-500">{{ __('legal.refund_updated') }}</p>
    </header>

    @foreach ($sections as $n => [$key, $params])
        <section class="mb-10" aria-labelledby="refund-s{{ $n }}">
            <h2 id="refund-s{{ $n }}" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
                {{ __('legal.refund_'.$key.'_title') }}
            </h2>
            <p class="text-gray-700 leading-relaxed">{{ __('legal.refund_'.$key.'_body', $params) }}</p>
            @if ($n === 7)
                <p class="mt-3">
                    <a href="{{ \App\Support\LocalizedPages::url('complaints', $locale) }}"
                       class="inline-flex items-center min-h-[44px] text-teal-700 hover:text-orange-700 font-medium underline underline-offset-2">
                        {{ __('legal.link_complaints') }}
                    </a>
                </p>
            @endif
        </section>
    @endforeach

    @include('partials.legal-company')

    <div class="mt-12 pt-8 border-t border-gray-200">
        <a href="{{ route('home', ['locale' => $locale]) }}"
           class="inline-flex items-center gap-2 min-h-[44px] text-teal-700 hover:text-orange-700 font-medium transition-colors">
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            {{ __('common.back_home') }}
        </a>
    </div>
</div>
@endsection

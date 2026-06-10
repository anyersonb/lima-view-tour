@extends('layouts.app')

@php
    $locale    = app()->getLocale();
    $firstRef  = $bookings->first()['reference'] ?? '';
    $firstName = $bookings->first()['customer_name'] ?? 'viajero';
@endphp

@section('title', __('checkout.thank_you_title') . ' — ' . __('seo.site_name'))
@section('description', 'Tu reserva en Lima View Tours ha sido confirmada. Recibirás un email con los detalles.')

@push('head')
<meta name="robots" content="noindex,nofollow">
@endpush

@section('content')

{{-- ───────── HERO ───────── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt="" class="w-full h-full object-cover" loading="eager">
        <div class="absolute inset-0 bg-teal-900/65"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-16 md:py-24 text-center">
        {{-- Success icon --}}
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white/10 border-2 border-orange-400 mb-8">
            <svg class="w-10 h-10 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">{{ __('checkout.thank_you_title') }}</h1>

        @if ($firstRef)
            <p class="mt-4 text-white/85 text-sm md:text-base">
                {{ __('checkout.reference_label') }}: <strong class="text-orange-300 font-mono tracking-widest">{{ $firstRef }}</strong>
            </p>
        @endif

        <p class="mt-4 mx-auto max-w-2xl text-sm md:text-base text-white/80">
            {{ __('checkout.thank_you_subtitle') }}
        </p>
    </div>
</section>

{{-- ───────── DETALLE RESERVAS ───────── --}}
@if ($bookings->isNotEmpty())
<section class="bg-cream-100 py-14 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 max-w-3xl">
        <h2 class="font-display text-2xl text-teal-800 mb-6">Detalle de tu reserva</h2>

        <div class="space-y-5">
            @foreach ($bookings as $booking)
                <article class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg text-teal-800 leading-snug">
                                {{ $booking['tour_title_snapshot'] }}
                            </h3>
                            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-teal-800/65">
                                <li>
                                    <svg class="inline w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ \Carbon\Carbon::parse($booking['travel_date'])->format('d M Y') }}
                                </li>
                                <li>
                                    <svg class="inline w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87M12 12a4 4 0 100-8 4 4 0 000 8z"/>
                                    </svg>
                                    {{ ($booking['adults'] ?? 1) + ($booking['children'] ?? 0) }} personas
                                    ({{ $booking['adults'] ?? 1 }} adultos, {{ $booking['children'] ?? 0 }} niños)
                                </li>
                            </ul>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-price text-2xl text-teal-800 leading-none">
                                ${{ number_format($booking['total_price'], 2) }}
                                <span class="text-[10px] uppercase tracking-[0.15em] text-teal-800/55 font-sans block mt-1">{{ $booking['currency'] ?? 'USD' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-teal-800/10 flex flex-wrap gap-4 text-xs text-teal-800/70">
                        <span>
                            Referencia: <strong class="font-mono text-teal-800">{{ $booking['reference'] }}</strong>
                        </span>
                        @php
                            $isPaid = ($booking['payment_status'] ?? 'pending') === 'paid';
                        @endphp
                        <span class="inline-flex items-center gap-1">
                            Estado:
                            @if ($isPaid)
                                <span class="inline-block rounded-full bg-state-success/10 text-state-success px-2 py-0.5 font-semibold uppercase tracking-wide text-[10px]">
                                    Confirmada
                                </span>
                            @else
                                <span class="inline-block rounded-full bg-orange-100 text-orange-600 px-2 py-0.5 font-semibold uppercase tracking-wide text-[10px]">
                                    Pendiente de pago
                                </span>
                            @endif
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- CTAs --}}
        <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
               class="btn--primary text-center">
                {{ __('checkout.back_to_tours') }}
            </a>
            <a href="{{ route('home', ['locale' => $locale]) }}"
               class="inline-flex items-center justify-center gap-2 rounded-pill border-2 border-teal-800/30 text-teal-800 px-6 py-3 text-sm font-semibold hover:border-teal-800 transition">
                Volver al inicio
            </a>
        </div>
    </div>
</section>
@else
<section class="bg-cream-100 py-20">
    <div class="container mx-auto px-5 lg:px-10 text-center">
        <p class="text-teal-800/70">No hay detalles de reserva disponibles.</p>
        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary mt-6 inline-block">Ver tours</a>
    </div>
</section>
@endif

@endsection

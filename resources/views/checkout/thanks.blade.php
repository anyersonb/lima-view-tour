@extends('layouts.app')

{{-- Gracias del checkout nuevo: mismo lenguaje visual (public/css/checkout.css), barra mínima. --}}
@php
    $forceNoindex = true; // el layout imprime el único <meta robots> noindex
    $minimalChrome = true;
    $locale = app()->getLocale();
    $usd = fn ($n) => 'US$' . number_format((float) $n, 2, '.', ',');
    $first = $bookings->first();
    $firstRef = $first['reference'] ?? ($payment['reference'] ?? '');
    $mode = $payment['mode'] ?? null;
    $type = $payment['type'] ?? null;
    $lead = match ($mode) {
        'full' => __('checkout_payment.thanks_full'),
        'deposit' => __('checkout_payment.thanks_deposit'),
        'later' => __('checkout_payment.thanks_later'),
        'balance' => __('checkout_payment.thanks_balance_paid'),
        default => __('checkout.thank_you_subtitle'),
    };
    $waNumber = preg_replace('/\D/', '', (string) (\App\Models\Setting::get('whatsapp') ?: '51925886725'));
@endphp

@section('title', __('checkout.thank_you_title') . ' — ' . __('seo.site_name'))
@section('description', __('checkout.thanks_meta_description'))
@section('body_class', 'lvt-page-checkout')

@push('head')
<link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v=20261121e">
@endpush

@section('content')
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
    <symbol id="co-i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="co-i-chat" viewBox="0 0 24 24"><path d="M20 11.5a7.8 7.8 0 0 1-8 7.5 8.8 8.8 0 0 1-4-.9l-4 1.4.8-4A7 7 0 0 1 4 11.5a8 8 0 0 1 16 0Z"/><path d="M8 11h8"/></symbol>
</svg>
<div class="co">
    <header class="co-bar">
        <a class="co-brand" href="{{ route('home', ['locale' => $locale]) }}" aria-label="{{ __('checkout_ui.brand') }}">
            <span class="co-mark" aria-hidden="true"><img src="{{ asset('assets/logos/logo-mark.png') }}" alt="" width="22" height="22"></span>
            <span class="co-brandtext">
                <span class="co-brandname">{{ __('checkout_ui.brand') }}</span>
                <span class="co-brandmeta" style="display:block">{{ __('checkout_ui.brand_meta') }}</span>
            </span>
        </a>
        <a class="co-help" href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode(__('checkout_ui.help_wa_text')) }}" target="_blank" rel="noopener noreferrer" data-wa-location="gracias">
            <svg class="co-svg co-svg--sm" aria-hidden="true"><use href="#co-i-chat"/></svg> {{ __('checkout_ui.help') }}
        </a>
    </header>

    <div class="co-wrap" style="display:block;max-width:594px">
        <div class="co-main">
            @if ($bookings->isNotEmpty())
                <div class="co-thanks">
                    <div class="co-thanks__icon"><svg class="co-svg" aria-hidden="true"><use href="#co-i-check"/></svg></div>
                    <h1>{{ __('checkout_ui.thanks_title') }}</h1>
                    <p>{{ $lead }}</p>
                    @if ($firstRef)
                        <p style="margin-top:10px">{{ __('checkout_ui.thanks_ref') }}: <span class="co-ref">{{ $firstRef }}</span></p>
                    @endif
                </div>

                <div style="margin-top:18px">
                    @foreach ($bookings as $booking)
                        @php
                            $pax = (int) ($booking['adults'] ?? 1) + (int) ($booking['children'] ?? 0);
                            $date = ! empty($booking['travel_date']) ? \Carbon\Carbon::parse($booking['travel_date'])->locale($locale)->translatedFormat('d M Y') : __('payment_links.date_to_be_arranged');
                        @endphp
                        <article class="co-booking">
                            <h3>{{ $booking['tour_title_snapshot'] }}</h3>
                            <ul>
                                <li>{{ __('checkout_ui.thanks_date') }}: <strong>{{ $date }}</strong></li>
                                <li>{{ __('checkout_ui.thanks_people') }}: <strong>{{ $pax }}</strong></li>
                                @if (! $loop->first || $bookings->count() > 1)
                                    <li>{{ __('checkout_ui.thanks_ref') }}: <strong>{{ $booking['reference'] }}</strong></li>
                                @endif
                            </ul>
                        </article>
                    @endforeach

                    @if ($payment)
                        <div class="co-moneybox">
                            <div class="co-moneyline"><span>{{ __('checkout_ui.thanks_method') }}</span><strong>{{ __('checkout_ui.thanks_method_' . ($type === 'culqi' || $type === 'paypal' ? $type : 'later')) }}</strong></div>
                            <div class="co-moneyline"><span>{{ __('checkout_ui.thanks_total') }}</span><strong>{{ $usd($payment['total']) }}</strong></div>
                            @if ((float) $payment['balance'] > 0)
                                <div class="co-moneyline"><span>{{ __('checkout_ui.thanks_balance') }}</span><strong>{{ $usd($payment['balance']) }}</strong></div>
                            @endif
                            <div class="co-moneyline co-moneyline--big"><span>{{ __('checkout_ui.thanks_paid') }}</span><strong>{{ $usd($payment['amount']) }}</strong></div>
                        </div>
                    @endif
                    <p class="co-sub" style="margin-top:12px;text-align:center">{{ __('checkout_ui.thanks_next') }}</p>
                </div>
            @else
                <div class="co-empty">
                    <h2>{{ __('checkout.no_booking_details') }}</h2>
                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="co-btn">{{ __('checkout_ui.explore_tours') }}</a>
                </div>
            @endif

            <div class="co-actions">
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="co-btn">{{ __('checkout_ui.thanks_back_tours') }}</a>
                <a href="{{ route('home', ['locale' => $locale]) }}" class="co-btn co-btn--ghost">{{ __('checkout_ui.thanks_back_home') }}</a>
            </div>
        </div>
    </div>
</div>

@if ($conversionPayload)
@push('scripts')
{{--
    purchase / reserva_pagar_despues — one shot, gated server-side by
    CheckoutController@thanks via session()->pull('conversion_pending').
--}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.lvtTrack === 'function') {
            window.lvtTrack(
                @json($conversionPayload['event']),
                @json($conversionPayload['data']),
                { fb: @json($conversionPayload['fb']) }
            );
        }
    });
</script>
@endpush
@endif

@endsection

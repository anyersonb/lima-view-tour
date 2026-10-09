@extends('layouts.app')

@php
    $reason = null;
    if (! $link) {
        $reason = 'not_found';
    } else {
        $link->checkAndExpire();
        if ($link->status === 'paid') {
            $reason = 'already_paid';
        } elseif ($link->status === 'cancelled') {
            $reason = 'cancelled';
        } elseif ($link->status === 'expired' || $link->isPastExpiry()) {
            $reason = 'expired';
        }
    }
    $usable = $link && $reason === null;
    $pageTitle = $tour?->title ?? __('payment_links.generic_tour_name');
    // Un link de pago es un enlace privado compartido con un cliente
    // concreto: nunca debe indexarse ni aparecer en el sitemap (ver
    // routes/web.php y SitemapController, que no lo lista). $forceNoindex
    // lo lee el layout (resources/views/layouts/app.blade.php) para NO
    // imprimir a la vez su propio meta "index,follow" por defecto — antes
    // esta vista lo agregaba con su propio @push('head') y quedaban DOS
    // <meta name="robots"> conflictivos en el HTML (X-Robots-Tag va aparte,
    // como cabecera, en PaymentLinkController::show()).
    $forceNoindex = true;
    // Locale del SDK de PayPal según el idioma resuelto del link.
    $paypalLocale = ['es' => 'es_PE', 'en' => 'en_US', 'pt' => 'pt_BR'][$locale] ?? 'en_US';
@endphp

@section('title', __('payment_links.page_title', ['tour' => $pageTitle]) . ' — ' . __('seo.site_name'))
@section('description', __('payment_links.meta_description'))

@section('content')

<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ $tour?->cover_url ?? asset('assets/banners/Rectangle 19215.jpg') }}"
             alt="" class="w-full h-full object-cover" loading="eager">
        <div class="absolute inset-0 bg-teal-900/70"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-14 md:py-20 text-center">
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl leading-tight">
            {{ __('payment_links.page_heading') }}
        </h1>
        @if ($tour)
            <p class="mt-3 mx-auto max-w-xl text-sm md:text-base text-white/85">{{ $tour->title }}</p>
        @endif
    </div>
</section>

<section class="bg-cream-100 py-14 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 max-w-2xl">

        @if (! $usable)
            {{-- Link no disponible: sin botón de pago, mensaje claro. --}}
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-orange-100 text-orange-500 mb-5">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="font-display text-2xl text-teal-800 mb-3">
                    @switch($reason)
                        @case('not_found') {{ __('payment_links.not_found_title') }} @break
                        @case('already_paid') {{ __('payment_links.already_paid_title') }} @break
                        @case('cancelled') {{ __('payment_links.cancelled_title') }} @break
                        @case('expired') {{ __('payment_links.expired_title') }} @break
                        @default {{ __('payment_links.not_available_title') }}
                    @endswitch
                </h2>
                <p class="text-teal-800/70 text-sm leading-relaxed">
                    @switch($reason)
                        @case('not_found') {{ __('payment_links.not_found') }} @break
                        @case('already_paid') {{ __('payment_links.already_paid') }} @break
                        @case('cancelled') {{ __('payment_links.cancelled') }} @break
                        @case('expired') {{ __('payment_links.expired') }} @break
                        @default {{ __('payment_links.not_available') }}
                    @endswitch
                </p>
                <a href="{{ route('home', ['locale' => $locale]) }}" class="btn--primary mt-6 inline-block">
                    {{ __('ui.explore_tours') }}
                </a>
            </div>
        @else
            <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm">
                <h2 class="font-display text-xl text-teal-800 mb-1">{{ $pageTitle }}</h2>
                {{-- M-1 (docs/payment-links/SECURITY.md): "note" es la nota
                     INTERNA del admin (PaymentLinkResource la etiqueta "Solo
                     visible en el panel — no se muestra al comprador") — no
                     se imprime nunca en esta vista pública. --}}

                <div class="grid gap-2 text-sm border-t border-teal-800/10 pt-4 mt-4">
                    <div class="flex justify-between">
                        <span class="text-teal-800/60">{{ __('ui.passengers') }}</span>
                        <b class="text-teal-800">{{ trans_choice('payment_links.adults_count', $link->adults, ['count' => $link->adults]) }}@if($link->children > 0), {{ trans_choice('payment_links.children_count', $link->children, ['count' => $link->children]) }}@endif</b>
                    </div>
                    @if ($link->travel_date)
                        <div class="flex justify-between">
                            <span class="text-teal-800/60">{{ __('checkout.travel_date') }}</span>
                            <b class="text-teal-800">{{ $link->travel_date->locale($locale)->translatedFormat('d M Y') }}</b>
                        </div>
                    @endif
                </div>

                <div class="mt-5 bg-cream-100 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-teal-800/60">{{ __('checkout.total') }}</span>
                    <span class="font-price text-2xl text-teal-800">US${{ number_format((float) $link->amount, 2) }}</span>
                </div>

                {{-- Datos del comprador: mínimos para poder crear la reserva y
                     enviar la confirmación. Igual que el checkout, se validan
                     en servidor en captureOrder() — esto es solo UX. --}}
                <div class="mt-6 grid gap-4">
                    <div>
                        <label for="pl-name" class="block text-xs font-bold uppercase tracking-wide text-teal-800/60 mb-1">
                            {{ __('checkout.customer_name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="pl-name" required maxlength="255"
                               class="w-full h-11 rounded-lg border border-teal-800/20 px-3 text-sm">
                    </div>
                    <div>
                        <label for="pl-email" class="block text-xs font-bold uppercase tracking-wide text-teal-800/60 mb-1">
                            {{ __('checkout.customer_email') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="pl-email" required maxlength="255"
                               value="{{ $link->customer_email }}"
                               class="w-full h-11 rounded-lg border border-teal-800/20 px-3 text-sm">
                    </div>
                    <div>
                        <label for="pl-phone" class="block text-xs font-bold uppercase tracking-wide text-teal-800/60 mb-1">
                            {{ __('payment_links.phone_label') }}
                        </label>
                        <input type="tel" id="pl-phone" maxlength="20"
                               value="{{ $link->customer_phone }}"
                               class="w-full h-11 rounded-lg border border-teal-800/20 px-3 text-sm">
                    </div>
                </div>

                <p id="payment-link-msg" class="mt-4 text-sm text-red-600" style="display:none;" role="alert"></p>

                <div id="paypal-buttons" class="mt-6"></div>
                <p class="mt-4 text-center text-[11px] text-teal-800/50">{{ __('payment_links.secure_payment') }}</p>
            </div>
        @endif
    </div>
</section>

@if ($usable)
@push('scripts')
@if (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id'))
<script
    src="https://www.paypal.com/sdk/js?client-id={{ (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id')) }}&currency=USD&intent=capture&locale={{ $paypalLocale }}"
    data-namespace="paypal_sdk">
</script>
@endif
@php
    $plCreateUrl  = route('payment-links.paypal.create', ['code' => $link->code, 'lang' => $locale]);
    $plCaptureUrl = route('payment-links.paypal.capture', ['code' => $link->code, 'lang' => $locale]);
@endphp
<script>
(function () {
    'use strict';

    const createUrl = @json($plCreateUrl);
    const captureUrl = @json($plCaptureUrl);

    function showMsg(text) {
        const el = document.getElementById('payment-link-msg');
        if (!el) return;
        el.textContent = text;
        el.style.display = '';
    }

    function collectCustomer() {
        return {
            customer_name: document.getElementById('pl-name')?.value.trim() ?? '',
            customer_email: document.getElementById('pl-email')?.value.trim() ?? '',
            customer_phone: document.getElementById('pl-phone')?.value.trim() ?? '',
        };
    }

    function validateCustomer() {
        const c = collectCustomer();
        if (!c.customer_name || !c.customer_email) {
            showMsg(@json(__('ui.fix_errors')));
            return false;
        }
        return true;
    }

    async function ppCreateOrder() {
        if (!validateCustomer()) {
            return Promise.reject(new Error('validation_failed'));
        }
        const res = await fetch(createUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();
        if (!res.ok || !data.id) {
            throw new Error(data.error ?? @json(__('payment_links.js_create_failed')));
        }
        return data.id;
    }

    async function ppOnApprove(paypalData) {
        const customer = collectCustomer();
        const res = await fetch(captureUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                orderID: paypalData.orderID,
                customer_name: customer.customer_name,
                customer_email: customer.customer_email,
                customer_phone: customer.customer_phone,
            }),
        });
        const data = await res.json();

        if (data.success && data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (data.code === 'payment_captured_booking_pending') {
            showMsg(data.message);
            return;
        }

        showMsg(data.error ?? data.message ?? @json(__('payment_links.js_capture_failed')));
    }

    function ppOnError(err) {
        showMsg(@json(__('payment_links.js_paypal_error')));
        console.error('PayPal error:', err);
    }

    if (typeof paypal_sdk !== 'undefined') {
        paypal_sdk.Buttons({
            style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },
            createOrder: ppCreateOrder,
            onApprove: ppOnApprove,
            onError: ppOnError,
        }).render('#paypal-buttons');
    }
})();
</script>
@endpush
@endif

@endsection

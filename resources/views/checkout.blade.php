@extends('layouts.app')

{{--
    CHECKOUT NUEVO (21-nov-2026) — pantalla única con scroll, tal cual el diseño
    aprobado LIMA_VIEW_TOURS_CHECKOUT_MOBILE_INTERACTIVO. Estilos: public/css/checkout.css.
    Lógica: public/js/checkout.js. El back (CheckoutPayment/PaymentPlan/CheckoutController)
    calcula todos los montos: aquí solo se pintan y se envía el MODO (full|deposit|later).
--}}

@php
    // El layout imprime UN solo <meta robots> noindex (ver layouts/app.blade.php).
    $forceNoindex = true;
    // Barra mínima propia: sin header/footer ni WhatsApp flotante (decisión del cliente).
    $minimalChrome = true;
    $locale = app()->getLocale();

    $usd = fn ($n) => 'US$' . number_format((float) $n, 2, '.', ',');
    $usdShort = fn ($n) => 'US$' . (floor((float) $n) == (float) $n ? number_format((float) $n, 0, '.', ',') : number_format((float) $n, 2, '.', ','));

    $methods = $checkout['methods'];
    $hasGateway = (bool) ($methods['culqi'] || $methods['paypal']);
    $fullOnly = (bool) ($checkout['full_only'] ?? false);
    $defaultMode = $hasGateway ? $checkout['default_mode'] : ($fullOnly ? \App\Support\PaymentPlan::FULL : \App\Support\PaymentPlan::LATER);
    $defaultMethod = $methods['culqi'] ? 'culqi' : ($methods['paypal'] ? 'paypal' : null);
    $modes = $checkout['modes'];

    // Hora de salida REAL (CheckoutInfo::departureTimes): sin hora cargada no se inventa chip.
    $timeLabel = function (?string $hhmm) use ($locale): ?string {
        if (! $hhmm || ! preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = $m[2];
        if ($locale === 'pt') {
            return sprintf('%02d:%s', $h, $min);
        }
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        $am = $h < 12;
        if ($locale === 'en') {
            return $h12 . ':' . $min . ' ' . ($am ? 'AM' : 'PM');
        }

        return $h12 . ':' . $min . ($am ? ' a. m.' : ' p. m.');
    };

    // Idioma(s) de guía: "Español / Inglés" (texto libre del tour) -> "ES / EN".
    $guideLabel = function (?string $raw): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $parts = preg_split('/\s*(?:\/|,|;|\+|&|\by\b|\band\b|\be\b)\s*/iu', $raw) ?: [$raw];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $code = \App\Support\TourLanguage::normalize($p);
            $out[] = $code ? strtoupper($code) : $p;
        }

        return $out ? implode(' / ', array_unique($out)) : null;
    };

    $minDateCart = $formMinDate ?? now()->addDay()->toDateString();
    $waNumber = preg_replace('/\D/', '', (string) (\App\Models\Setting::get('whatsapp') ?: '51925886725'));

    $jsItems = $items->map(function ($it) use ($checkoutInfo, $timeLabel) {
        $dep = $checkoutInfo['departure_times'][$it['tour_id'] ?? 0] ?? null;
        if (! empty($it['departure_slot'])) {
            $dep = ['time' => $it['departure_slot'], 'label' => $it['departure_slot']];
        }

        return [
            'row_id' => $it['row_id'],
            'tour_id' => $it['tour_id'] ?? null,
            'title' => $it['title_snapshot'] ?? '',
            'img' => \App\Support\ImagePath::url($it['cover_image'] ?? null) ?: asset('assets/banners/Rectangle 19210.jpg'),
            'unit_price' => (float) ($it['unit_price'] ?? 0),
            'adults' => (int) ($it['adults'] ?? 1),
            'children' => (int) ($it['children'] ?? 0),
            'travel_date' => $it['travel_date'] ?? null,
            'min_date' => $it['min_date'] ?? null,
            'time' => $dep ? $timeLabel($dep['time']) : null,
        ];
    })->values()->all();

    $pickupSuggestions = [
        'Miraflores, Lima', 'San Isidro, Lima', 'Barranco, Lima', 'Santiago de Surco, Lima', 'San Borja, Lima',
        'Magdalena del Mar, Lima', 'Aeropuerto Jorge Chávez (Callao)', 'Centro Histórico de Lima',
        'JW Marriott Hotel Lima (Miraflores)', 'Belmond Miraflores Park', 'Hilton Lima Miraflores',
        'Casa Andina Premium Miraflores', 'Country Club Lima Hotel (San Isidro)', 'The Westin Lima (San Isidro)',
        'Novotel Lima', 'Ibis Larco Miraflores', 'Meliá Lima', 'Hotel B (Barranco)',
        'Huacachina, Ica', 'Paracas (El Chaco)', 'Hotel Paracas, a Luxury Collection Resort',
        'Plaza de Armas de Cusco', 'Casa Andina Premium Cusco',
    ];

    $legal = [
        'terms' => \App\Support\LocalizedPages::url('legal.terms', $locale),
        'privacy' => \App\Support\LocalizedPages::url('legal.privacy', $locale),
        'refund' => \App\Support\LocalizedPages::url('legal.refund', $locale),
        'complaints' => \App\Support\LocalizedPages::url('complaints', $locale),
    ];
@endphp

@section('title', __('ui.cart_title') . ' — ' . __('seo.site_name'))
@section('description', __('checkout_ui.lede'))
@section('body_class', 'lvt-page-checkout')

@push('head')
<link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v=20261124a">
@endpush

@section('content')
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
    <symbol id="co-i-back" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
    <symbol id="co-i-chat" viewBox="0 0 24 24"><path d="M20 11.5a7.8 7.8 0 0 1-8 7.5 8.8 8.8 0 0 1-4-.9l-4 1.4.8-4A7 7 0 0 1 4 11.5a8 8 0 0 1 16 0Z"/><path d="M8 11h8"/></symbol>
    <symbol id="co-i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="co-i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="co-i-chevron" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
    <symbol id="co-i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 9h18"/></symbol>
    <symbol id="co-i-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="co-i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m8 12 3 3 5-6"/></symbol>
    <symbol id="co-i-card" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></symbol>
    <symbol id="co-i-minus" viewBox="0 0 24 24"><path d="M4 12h16"/></symbol>
    <symbol id="co-i-plus" viewBox="0 0 24 24"><path d="M4 12h16M12 4v16"/></symbol>
</svg>

<div class="co" id="co">

    {{-- ── Barra superior mínima ── --}}
    <header class="co-bar">
        <button type="button" class="co-back" id="co-back" aria-label="{{ __('checkout_ui.back_aria') }}" title="{{ __('checkout_ui.back_aria') }}" data-tours="{{ route('tours.index', ['locale' => $locale]) }}">
            <svg class="co-svg" aria-hidden="true"><use href="#co-i-back"/></svg>
        </button>
        <a class="co-brand" href="{{ route('home', ['locale' => $locale]) }}" aria-label="{{ __('checkout_ui.brand') }}">
            <span class="co-mark" aria-hidden="true"><img src="{{ asset('assets/logos/logo-mark.png') }}" alt="" width="22" height="22"></span>
            <span class="co-brandtext">
                <span class="co-brandname">{{ __('checkout_ui.brand') }}</span>
                <span class="co-brandmeta" style="display:block">{{ __('checkout_ui.brand_meta') }}</span>
            </span>
        </a>
        <a class="co-help" href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode(__('checkout_ui.help_wa_text')) }}" target="_blank" rel="noopener noreferrer" data-wa-location="checkout">
            <svg class="co-svg co-svg--sm" aria-hidden="true"><use href="#co-i-chat"/></svg> {{ __('checkout_ui.help') }}
        </a>
    </header>

    @if ($items->isEmpty())
        {{-- ── Carrito vacío ── --}}
        <div class="co-wrap" style="display:block;max-width:594px">
            <div class="co-main">
                @if (session('error'))<div class="co-flash co-flash--error" role="alert" style="margin-top:20px">{{ session('error') }}</div>@endif
                @if (session('success'))<div class="co-flash co-flash--ok" role="status" style="margin-top:20px">{{ session('success') }}</div>@endif
                <div class="co-empty">
                    <h2>{{ __('checkout_ui.empty_title') }}</h2>
                    <p>{{ __('checkout_ui.empty_desc') }}</p>
                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="co-btn">{{ __('checkout_ui.explore_tours') }}</a>
                </div>
                <div class="co-seal"><svg class="co-svg" aria-hidden="true"><use href="#co-i-shield"/></svg> {{ __('checkout_ui.seal') }}</div>
            </div>
        </div>
    @else
    <form id="co-form" method="POST" action="{{ route('checkout.process', ['locale' => $locale]) }}" novalidate>
        @csrf
        <input type="hidden" name="customer_phone" id="co-phone-full" value="{{ old('customer_phone') }}">

        <div class="co-wrap">
            <div class="co-main">

                <div class="co-hero">
                    <div class="co-eyebrow">{{ __('checkout_ui.eyebrow') }}</div>
                    <h1 class="co-h1">{{ __('checkout_ui.h1_a') }} <i>{{ __('checkout_ui.h1_b') }}</i></h1>
                    <p class="co-lede">{{ __('checkout_ui.lede') }}</p>
                </div>

                <ol class="co-steps" aria-label="{{ __('checkout_ui.steps_label') }}">
                    <li class="co-step is-active" data-co-step="tour"><a href="#co-tours"><b>1</b><span>{{ __('checkout_ui.step_tour') }}</span></a></li>
                    <li class="co-step" data-co-step="data"><a href="#co-data"><b>2</b><span>{{ __('checkout_ui.step_data') }}</span></a></li>
                    <li class="co-step" data-co-step="pay"><a href="#co-pay"><b>3</b><span>{{ __('checkout_ui.step_pay') }}</span></a></li>
                </ol>

                {{-- Avisos del servidor (redirects de processPayment, fechas vencidas, validación) --}}
                @if (session('success'))<div class="co-flash co-flash--ok" role="status">{{ session('success') }}</div>@endif
                @if (session('error'))<div class="co-flash co-flash--error" role="alert">{{ session('error') }}</div>@endif
                @if ($errors->any())
                    <div class="co-flash co-flash--error" role="alert">
                        <strong>{{ __('ui.fix_errors') }}</strong>
                        <ul>@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- Pago capturado con reserva pendiente / revisión manual. Persistente (PaymentLock). --}}
                <div id="co-review" class="co-review" role="alert" aria-live="assertive" @unless ($paymentLock ?? null) hidden @endunless>
                    <b>{{ __('ui.payment_review_title') }}</b>
                    <p id="co-review-msg">{{ $paymentLock['message'] ?? '' }}</p>
                    <span>{{ __('ui.payment_review_reference_label') }}</span><br>
                    <code id="co-review-ref">{{ $paymentLock['reference'] ?? '' }}</code>
                    <button type="button" id="co-review-copy">{{ __('ui.payment_review_copy') }}</button>
                </div>

                {{-- ── Tarjeta(s) del tour + Sección 1: fecha, horario, personas ── --}}
                <div id="co-tours" class="co-anchor" style="scroll-margin-top:78px">
                @foreach ($items as $i => $item)
                    @php
                        $imgSrc = \App\Support\ImagePath::url($item['cover_image'] ?? null) ?: asset('assets/banners/Rectangle 19210.jpg');
                        $pax = (int) $item['adults'] + (int) ($item['children'] ?? 0);
                        $dep = $checkoutInfo['departure_times'][$item['tour_id'] ?? 0] ?? null;
                        if (! empty($item['departure_slot'])) {
                            $dep = ['time' => $item['departure_slot'], 'label' => $item['departure_slot']];
                        }
                        $depLabel = $dep ? $timeLabel($dep['time']) : null;
                        $guide = $guideLabel($item['language'] ?? null);
                        $dateExpired = (bool) ($item['date_expired'] ?? false);
                        $dateValue = $item['travel_date'] ?? '';
                        $minDate = $item['min_date'] ?? $minDateCart;
                    @endphp

                    <article class="co-tour" data-co-item data-row-id="{{ $item['row_id'] }}">
                        <img src="{{ $imgSrc }}" alt="{{ __('checkout_ui.photo_alt', ['tour' => $item['title_snapshot']]) }}" width="108" height="112" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                        <div class="co-tour__body">
                            <div class="co-overline">{{ __('checkout_ui.chosen') }}</div>
                            <h2>{{ $item['title_snapshot'] }}</h2>
                            @if (filled($item['duration'] ?? null) || $guide)
                                <div class="co-tags">
                                    @if (filled($item['duration'] ?? null))<span class="co-tag">{{ \App\Support\TourListing::durationLabel($item['duration']) }}</span>@endif
                                    @if ($guide)<span class="co-tag co-tag--alt">{{ __('checkout_ui.guide', ['lang' => $guide]) }}</span>@endif
                                </div>
                            @endif
                            <div class="co-tour__foot">
                                <span class="co-price">{{ $usdShort($item['unit_price']) }} <small>{{ __('checkout_ui.per_person') }}</small></span>
                                <span class="co-tour__actions">
                                    <a class="co-linkbtn" href="{{ route('tours.index', ['locale' => $locale]) }}">{{ __('checkout_ui.change_tour') }}</a>
                                    @if ($items->count() > 1)
                                        <button type="button" class="co-linkbtn co-linkbtn--danger" data-co-remove>{{ __('checkout_ui.remove') }}</button>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </article>

                    @if ($i === 0)
                        <div class="co-sectionhead" style="margin-top:19px">
                            <span class="co-num">1</span>
                            <h2 id="co-h-date" style="margin:0;font-family:var(--co-title);font-size:22px;line-height:1.2;font-weight:700;letter-spacing:-.2px;color:#0b4035">{{ __('checkout_ui.sec_date') }}</h2>
                        </div>
                    @endif

                    <div class="co-box co-stack co-tourbox" data-co-box data-row-id="{{ $item['row_id'] }}">
                        <div class="co-field @if ($dateExpired) is-invalid @endif" data-co-field="date">
                            <label class="co-label" for="co-date-{{ $i }}">{{ __('checkout_ui.date_label') }}</label>
                            <input class="co-input" id="co-date-{{ $i }}" type="date" value="{{ $dateValue }}" min="{{ $minDate }}" required data-co-date aria-describedby="co-date-err-{{ $i }}">
                            <div class="co-error" id="co-date-err-{{ $i }}" data-co-date-err role="alert">{{ $dateExpired ? ($item['date_expired_message'] ?? __('checkout_ui.date_invalid')) : __('checkout_ui.date_invalid') }}</div>
                        </div>

                        <div>
                            @php
                                // Varios horarios: se puede cambiar desde aquí (PATCH con departure_slot). Un solo horario: chip fijo.
                                $slotAv = is_array($item['available_slots'] ?? null) ? $item['available_slots'] : [];
                                $slotOptions = ! empty($slotAv['multiple_slots']) ? array_values($slotAv['slots'] ?? []) : [];
                                $slotToday = ($item['travel_date'] ?? null) === ($slotAv['today'] ?? null);
                                $slotAllowed = $slotToday ? ($slotAv['today_slots'] ?? []) : $slotOptions;
                                $slotCurrent = $item['departure_slot'] ?? ($dep['time'] ?? null);
                            @endphp
                            @if (count($slotOptions) > 1)
                                <div class="co-label" id="co-slot-label-{{ $i }}">{{ __('checkout_ui.time_label') }}</div>
                                <div class="co-chips" role="group" aria-labelledby="co-slot-label-{{ $i }}" data-co-slots>
                                    @foreach ($slotOptions as $s)
                                        <button type="button" class="co-chip @if ($s === $slotCurrent) is-on @endif" data-co-slot="{{ $s }}" data-co-slot-label="{{ $timeLabel($s) }}" aria-pressed="{{ $s === $slotCurrent ? 'true' : 'false' }}" @disabled(! in_array($s, $slotAllowed, true))>
                                            <svg class="co-svg" aria-hidden="true"><use href="#co-i-clock"/></svg> {{ $timeLabel($s) }}
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($depLabel)
                                <div class="co-label">{{ __('checkout_ui.time_label') }}</div>
                                <div class="co-chips">
                                    <span class="co-chip is-on"><svg class="co-svg" aria-hidden="true"><use href="#co-i-clock"/></svg> {{ $depLabel }}</span>
                                </div>
                            @endif
                            <small class="co-sub">{{ __('checkout_ui.time_note') }}</small>
                        </div>

                        <hr class="co-divider">

                        <div>
                            <div class="co-row">
                                <div class="co-paxlabel">
                                    <strong>{{ __('checkout_ui.pax_title') }}</strong>
                                    <span class="co-muted">{{ __('checkout_ui.pax_sub') }}</span>
                                </div>
                                <div class="co-counter">
                                    <button type="button" data-co-dec aria-label="{{ __('checkout_ui.pax_dec') }}"><svg class="co-svg" aria-hidden="true"><use href="#co-i-minus"/></svg></button>
                                    <output data-co-pax aria-live="polite">{{ $pax }}</output>
                                    <button type="button" data-co-inc aria-label="{{ __('checkout_ui.pax_inc') }}"><svg class="co-svg" aria-hidden="true"><use href="#co-i-plus"/></svg></button>
                                </div>
                            </div>
                            <p class="co-paxnote" data-co-paxnote hidden>
                                <a href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode(__('checkout_ui.help_wa_text')) }}" target="_blank" rel="noopener noreferrer">{{ __('checkout_ui.pax_max_note', ['max' => 12]) }}</a>
                            </p>
                        </div>
                    </div>
                @endforeach
                </div>

                {{-- ── Sección 2: tus datos ── --}}
                <section class="co-section" id="co-data" aria-labelledby="co-h-data">
                    <div class="co-sectionhead"><span class="co-num">2</span><h2 id="co-h-data">{{ __('checkout_ui.sec_data') }}</h2></div>
                    <div class="co-box co-stack" data-clarity-mask="true" data-hj-suppress>
                        <div class="co-field" data-co-field="name">
                            <label class="co-label" for="co-name">{{ __('checkout_ui.name_label') }}</label>
                            <input class="co-input" id="co-name" name="customer_name" value="{{ old('customer_name') }}" placeholder="{{ __('checkout_ui.name_ph') }}" autocomplete="name" required data-hj-suppress>
                            <div class="co-error" role="alert">{{ __('checkout_ui.name_invalid') }}</div>
                        </div>
                        <div class="co-field" data-co-field="phone">
                            <label class="co-label" for="co-phone">{{ __('checkout_ui.phone_label') }}</label>
                            <div class="co-telrow">
                                <x-phone-country-select id="co-dial" class="co-select" />
                                <input class="co-input" id="co-phone" type="tel" placeholder="{{ __('checkout_ui.phone_ph') }}" autocomplete="tel-national" inputmode="tel" maxlength="15" required data-hj-suppress>
                            </div>
                            <div class="co-error" role="alert">{{ __('checkout_ui.phone_invalid') }}</div>
                        </div>
                        <div class="co-field" data-co-field="email">
                            <label class="co-label" for="co-email">{{ __('checkout_ui.email_label') }}</label>
                            <input class="co-input" id="co-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="{{ __('checkout_ui.email_ph') }}" autocomplete="email" required data-hj-suppress>
                            <div class="co-error" role="alert">{{ __('checkout_ui.email_invalid') }}</div>
                        </div>
                        <div class="co-tip"><svg class="co-svg" aria-hidden="true"><use href="#co-i-shield"/></svg><div>{{ __('checkout_ui.tip') }}</div></div>

                        <button type="button" class="co-optional" id="co-toggle-extra" aria-expanded="false" aria-controls="co-extra">
                            <svg class="co-svg co-svg--sm" aria-hidden="true"><use href="#co-i-plus"/></svg> {{ __('checkout_ui.extra_toggle') }}
                        </button>
                        <div id="co-extra" class="co-optionalpanel" hidden>
                            @if ($cartHasPickup ?? true)
                                <label class="co-label" for="co-hotel">{{ __('checkout_ui.hotel_label') }}</label>
                                <input class="co-input" id="co-hotel" name="pickup_point" list="co-hotel-options" maxlength="100" value="{{ old('pickup_point') }}" placeholder="{{ __('checkout_ui.hotel_ph') }}" autocomplete="off" data-hj-suppress>
                                <datalist id="co-hotel-options">@foreach ($pickupSuggestions as $opt)<option value="{{ $opt }}"></option>@endforeach</datalist>
                                <p class="co-sub">{{ __('checkout_ui.hotel_help') }}</p>
                            @endif
                            <label class="co-label" for="co-notes" style="margin-top:11px">{{ __('checkout_ui.notes_label') }}</label>
                            <textarea class="co-input" id="co-notes" name="notes" maxlength="1000" placeholder="{{ __('checkout_ui.notes_ph') }}">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </section>

                {{-- ── Sección 3: pago ── --}}
                <section class="co-section" id="co-pay" aria-labelledby="co-h-pay">
                    <div class="co-sectionhead"><span class="co-num">3</span><h2 id="co-h-pay">{{ __('checkout_ui.sec_pay') }}</h2></div>
                    <div class="co-box">
                        <div class="co-label" id="co-amount-label">{{ __('checkout_payment.amount_label') }}</div>
                        <div class="co-paychoices" role="radiogroup" aria-labelledby="co-amount-label" style="margin-bottom:13px">
                            @unless ($fullOnly)
                            <label class="co-paychoice @if ($defaultMode === 'deposit') is-selected @endif">
                                <input type="radio" name="payment_mode" value="deposit" @checked($defaultMode === 'deposit') @disabled(! $hasGateway)>
                                <div><span>{{ __('checkout_payment.mode_deposit_title') }}</span><small>{{ __('checkout_payment.mode_deposit_sub') }}</small></div>
                            </label>
                            @endunless
                            <label class="co-paychoice @if ($defaultMode === 'full') is-selected @endif">
                                <input type="radio" name="payment_mode" value="full" @checked($defaultMode === 'full') @disabled(! $hasGateway)>
                                <div><span>{{ __('checkout_payment.mode_full_title') }}</span><small>{{ __('checkout_payment.mode_full_sub') }}</small></div>
                            </label>
                            @unless ($fullOnly)
                            <label class="co-paychoice co-paychoice--wide @if ($defaultMode === 'later') is-selected @endif">
                                <input type="radio" name="payment_mode" value="later" @checked($defaultMode === 'later')>
                                <div><span>{{ __('checkout_payment.mode_later_title') }}</span><small>{{ __('checkout_payment.mode_later_sub') }}</small></div>
                            </label>
                            @endunless
                        </div>
                        @if ($fullOnly)
                            <p class="co-sub" data-full-only-notice style="margin:-4px 0 12px">{{ __('checkout_payment.full_only_notice') }}</p>
                        @endif

                        @if ($hasGateway)
                            <div id="co-methods" class="co-methods" @if ($defaultMode === 'later') hidden @endif>
                                <hr class="co-divider" style="margin:12px 0">
                                <div class="co-label" id="co-method-label">{{ __('checkout_payment.method_label') }}</div>
                                <div class="co-paychoices" role="radiogroup" aria-labelledby="co-method-label">
                                    @if ($methods['culqi'])
                                        <label class="co-paychoice @if ($defaultMethod === 'culqi') is-selected @endif @unless ($methods['paypal']) co-paychoice--wide @endunless">
                                            <input type="radio" name="pay_method" value="culqi" @checked($defaultMethod === 'culqi')>
                                            <svg class="co-svg" aria-hidden="true"><use href="#co-i-card"/></svg>
                                            <div><span>{{ __('checkout_payment.method_card') }}</span><small>{{ __('checkout_payment.method_card_sub') }}</small></div>
                                        </label>
                                    @endif
                                    @if ($methods['paypal'])
                                        <label class="co-paychoice @if ($defaultMethod === 'paypal') is-selected @endif @unless ($methods['culqi']) co-paychoice--wide @endunless">
                                            <input type="radio" name="pay_method" value="paypal" @checked($defaultMethod === 'paypal')>
                                            <div><span class="co-paypal-word">PayPal</span><small>{{ __('checkout_payment.method_paypal') }}</small></div>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="co-sub" style="margin-top:0" data-no-gateway>{{ $fullOnly ? __('checkout_payment.no_method_available') : __('checkout_ui.no_gateway') }}
                                @if ($waNumber)<a href="https://wa.me/{{ $waNumber }}" rel="noopener" target="_blank">WhatsApp</a>@endif</p>
                        @endif

                        <div class="co-only-mobile">
                            @include('checkout.partials.moneybox')
                        </div>

                        <label class="co-agreement" id="co-terms-wrap">
                            <input type="checkbox" name="accept_terms" value="1" id="co-terms" required>
                            <span>{{ __('checkout_ui.terms_a') }} <button type="button" id="co-terms-link">{{ __('checkout_ui.terms_link') }}</button>.</span>
                        </label>
                        <div class="co-error" id="co-terms-err" role="alert">{{ __('checkout_ui.terms_error') }}</div>

                        <p class="co-policy"><svg class="co-svg co-svg--sm" aria-hidden="true"><use href="#co-i-lock"/></svg> <span>{{ __('checkout_ui.secure_note') }}</span></p>
                    </div>
                </section>

                <div class="co-seal"><svg class="co-svg" aria-hidden="true"><use href="#co-i-shield"/></svg> {{ __('checkout_ui.seal') }}</div>
            </div>

            {{-- ── Resumen lateral (solo escritorio ≥1024) ── --}}
            <aside class="co-aside" aria-label="{{ __('checkout_ui.summary') }}">
                <div class="co-box">
                    <h2>{{ __('checkout_ui.summary') }}</h2>
                    @include('checkout.partials.moneybox')
                    <button type="button" class="co-cta" data-co-cta>
                        <span data-co-cta-label>{{ $defaultMode === 'later' ? __('checkout_ui.continue_later') : __('checkout_ui.continue_pay') }}</span>
                        <svg class="co-svg" aria-hidden="true"><use href="#co-i-chevron"/></svg>
                    </button>
                </div>
            </aside>
        </div>

        {{-- ── Barra inferior pegada (<1024) ── --}}
        <div class="co-sticky">
            <div class="co-sticky__price">
                <small>{{ __('checkout_payment.pay_now_label') }}</small>
                <strong data-co-due>{{ $usd($modes[$defaultMode]['due']) }}</strong>
                <small data-co-sticky-note>{{ __('checkout_payment.pay_now_' . $defaultMode . '_note') }}</small>
            </div>
            <button type="button" class="co-cta" data-co-cta>
                <span data-co-cta-label>{{ $defaultMode === 'later' ? __('checkout_ui.continue_later') : __('checkout_ui.continue_pay') }}</span>
                <svg class="co-svg" aria-hidden="true"><use href="#co-i-chevron"/></svg>
            </button>
        </div>
    </form>

    {{-- ── Sheet: Revisa antes de pagar ── --}}
    <div class="co-modal" id="co-modal-review" hidden>
        <div class="co-veil" data-co-close></div>
        <section class="co-sheet" role="dialog" aria-modal="true" aria-labelledby="co-review-title">
            <div class="co-sheettop">
                <h3 id="co-review-title">{{ __('checkout_ui.review_title') }}</h3>
                <button class="co-close" type="button" data-co-close aria-label="{{ __('checkout_ui.close') }}">×</button>
            </div>
            <div class="co-sheetcontent">
                <p class="co-sheetlead" data-co-review-lead>{{ __('checkout_ui.review_lead') }}</p>
                <div data-co-review-items></div>
                <div class="co-moneybox">
                    <div class="co-moneyline"><span>{{ __('checkout_payment.total_label') }}</span><strong data-co-r-total></strong></div>
                    <div class="co-moneyline"><span>{{ __('checkout_ui.review_mode') }}</span><strong data-co-r-mode></strong></div>
                    <div class="co-moneyline co-moneyline--big"><span>{{ __('checkout_payment.pay_now_label') }}</span><strong data-co-r-due></strong></div>
                </div>
                <div class="co-msg" id="co-msg" role="alert"></div>
                @if ($methods['culqi'])
                <button type="button" class="co-sheet-cta" id="co-pay-culqi" hidden>
                    <svg class="co-svg" aria-hidden="true"><use href="#co-i-card"/></svg> <span data-co-culqi-label></span>
                </button>
                @endif
                <div class="co-paypal-wrap" id="co-paypal-buttons" hidden></div>
                <button type="button" class="co-sheet-cta" id="co-confirm-later" hidden>{{ __('checkout_ui.confirm_later_cta') }}</button>
            </div>
        </section>
    </div>

    {{-- ── Sheet: Condiciones de reserva ── --}}
    <div class="co-modal" id="co-modal-terms" hidden>
        <div class="co-veil" data-co-close></div>
        <section class="co-sheet" role="dialog" aria-modal="true" aria-labelledby="co-terms-title">
            <div class="co-sheettop">
                <h3 id="co-terms-title">{{ __('checkout_payment.conditions_title') }}</h3>
                <button class="co-close" type="button" data-co-close aria-label="{{ __('checkout_ui.close') }}">×</button>
            </div>
            <div class="co-sheetcontent">
                <div class="co-cond" style="margin-top:0"><strong>{{ __('checkout_payment.conditions_cancel_title') }}</strong><p>{{ __('checkout_payment.conditions_cancel_body') }}</p></div>
                <div class="co-cond co-cond--warm"><strong>{{ __('checkout_payment.conditions_payment_title') }}</strong><p>{{ __('checkout_payment.conditions_payment_body') }}</p></div>
                <div class="co-cond"><strong>{{ __('checkout_payment.conditions_schedule_title') }}</strong><p>{{ __('checkout_payment.conditions_schedule_body') }}</p></div>
                @if (filled($checkoutInfo['cancellation_summary'] ?? null))
                    <div class="co-cond co-cond--warm"><p>{{ $checkoutInfo['cancellation_summary'] }}</p></div>
                @endif
                <div class="co-links">
                    <a href="{{ $legal['terms'] }}" target="_blank" rel="noopener">{{ __('checkout_ui.conditions_links_terms') }}</a>
                    <a href="{{ $legal['privacy'] }}" target="_blank" rel="noopener">{{ __('checkout_ui.conditions_links_privacy') }}</a>
                    <a href="{{ $checkoutInfo['refund_policy_url'] ?? $legal['refund'] }}" target="_blank" rel="noopener">{{ __('checkout_ui.conditions_links_refund') }}</a>
                    <a href="{{ $legal['complaints'] }}" target="_blank" rel="noopener">{{ __('checkout_ui.conditions_links_complaints') }}</a>
                </div>
                <button type="button" class="co-sheet-cta" data-co-close>{{ __('checkout_ui.close') }}</button>
            </div>
        </section>
    </div>
    @endif
</div>
@endsection

@if ($items->isNotEmpty())
@php
    $paypalClientId = \App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id');
    $paypalLocale = ['es' => 'es_PE', 'en' => 'en_US', 'pt' => 'pt_BR'][$locale] ?? 'en_US';

    $coConfig = [
        'locale' => $locale,
        'checkout' => $checkout,
        'items' => $jsItems,
        // type/value permiten replicar el descuento en el recálculo optimista del JS (el servidor reconcilia).
        'coupon' => [
            'code' => $couponCode,
            'discount' => (float) $checkout['discount'],
            'type' => $couponCode ? config('cart.coupons.'.$couponCode.'.type') : null,
            'value' => $couponCode ? config('cart.coupons.'.$couponCode.'.value') : null,
        ],
        'defaultMode' => $defaultMode,
        'defaultMethod' => $defaultMethod,
        'hasGateway' => $hasGateway,
        'paypal' => (bool) ($methods['paypal'] && $paypalClientId),
        'culqi' => (bool) $methods['culqi'],
        'maxPax' => 12,
        'paymentLock' => $paymentLock ?? null,
        'urls' => [
            'cartUpdate' => route('cart.update', ['locale' => $locale, 'rowId' => '__ROW__']),
            'cartDestroy' => route('cart.destroy', ['locale' => $locale, 'rowId' => '__ROW__']),
            'cartIndex' => route('cart.index', ['locale' => $locale]),
            'contact' => route('cart.contact', ['locale' => $locale]),
            'tours' => route('tours.index', ['locale' => $locale]),
        ],
        'i18n' => [
            'adultOne' => __('checkout_ui.adult_one'),
            'adultMany' => __('checkout_ui.adult_many'),
            'peopleOne' => __('checkout_ui.people_one'),
            'peopleMany' => __('checkout_ui.people_many'),
            'discountLine' => __('checkout_ui.discount_line', ['code' => ':code']),
            'discountPlain' => __('checkout_ui.discount_plain'),
            'modeDeposit' => __('checkout_payment.pay_now_deposit_note'),
            'modeFull' => __('checkout_payment.pay_now_full_note'),
            'modeLater' => __('checkout_payment.pay_now_later_note'),
            'balanceLabel' => __('checkout_payment.balance_label'),
            'laterBalanceLabel' => __('checkout_ui.later_balance_label'),
            'continuePay' => __('checkout_ui.continue_pay'),
            'continueLater' => __('checkout_ui.continue_later'),
            'reviewLead' => __('checkout_ui.review_lead'),
            'reviewLeadLater' => __('checkout_ui.review_lead_later'),
            'payCard' => __('checkout_ui.pay_card_cta', ['amount' => ':amount']),
            'processing' => __('checkout_ui.processing'),
            'genericError' => __('checkout_ui.generic_error'),
            'fixFields' => __('checkout_ui.fix_fields'),
            'dateSavedError' => __('checkout_ui.date_saved_error'),
            'paypalError' => __('checkout_ui.paypal_error'),
            'removeConfirm' => __('checkout_ui.remove_confirm'),
            'copy' => __('ui.payment_review_copy'),
            'copied' => __('ui.payment_review_copied'),
            'culqiTitle' => __('checkout_culqi.checkout_title'),
            'culqiScriptFail' => __('checkout_culqi.script_failed'),
            'culqiTokenError' => __('checkout_culqi.token_error'),
            'culqiFailed' => __('checkout_culqi.charge_failed'),
            'paypalCreateFailed' => __('checkout_paypal.create_order_failed'),
            'paymentFailed' => __('booking.payment_failed'),
            'dateInvalid' => __('checkout_ui.date_invalid'),
        ],
    ];
@endphp
@push('scripts')
@if ($coConfig['culqi'])
{{-- Culqi Checkout v4 (https://docs.culqi.com/es/documentacion/checkout/v4) --}}
<script src="https://checkout.culqi.com/js/v4" defer></script>
@endif
@if ($coConfig['paypal'])
<script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency=USD&intent=capture&locale={{ $paypalLocale }}&components=buttons&disable-funding=card,credit,paylater" data-namespace="paypal_sdk" defer></script>
@endif
<script>window.LVT_CO = {!! json_encode($coConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};</script>
<script src="{{ asset('js/checkout.js') }}?v=20261124a" defer></script>
@endpush
@endif

@extends('layouts.app')

@section('title', $title.' – Lima View Tours')
@section('description', __('complaints.meta_description'))

@php
    $companyLines = \App\Support\LegalInfo::lines();
    $hasErrors = $errors->any();
    $docType = old('doc_type', 'dni');
    $isMinor = (bool) old('is_minor');
    $docInputmode = $docType === 'dni' ? 'numeric' : 'text';

    // Helpers de campo: id estable, error visible por campo y aria-*.
    $err = fn (string $f): ?string => $errors->first($f) ?: null;
    $aria = function (string $f, ?string $hint = null) use ($err): string {
        $ids = trim(($hint ? 'cb-'.$f.'-hint ' : '').($err($f) ? 'cb-'.$f.'-err' : ''));

        return ($err($f) ? 'aria-invalid="true" ' : '').($ids !== '' ? 'aria-describedby="'.$ids.'"' : '');
    };
@endphp

@section('content')
<div class="lvt-cb container mx-auto px-5 lg:px-10 py-12 md:py-16">

    <header class="lvt-cb__head">
        <p class="lvt-cb__eyebrow">{{ __('complaints.eyebrow') }}</p>
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl text-teal-700 leading-tight">{{ $title }}</h1>
        <p class="lvt-cb__lead">{{ __('complaints.intro') }}</p>
        <p class="lvt-cb__banner" role="note">{{ __('complaints.deadline_banner') }}</p>
    </header>

    @if ($registered)
        {{-- Estado de éxito: código de la hoja + fecha límite --}}
        <section class="lvt-cb__card lvt-cb__success" role="status" aria-labelledby="cb-success-title" tabindex="-1" id="cb-success">
            <h2 id="cb-success-title" class="font-display text-2xl text-teal-700">{{ __('complaints.success_title') }}</h2>
            <p>{{ __('complaints.success_body') }}</p>
            <dl class="lvt-cb__code-box">
                <div>
                    <dt>{{ __('complaints.success_code') }}</dt>
                    <dd class="lvt-cb__code" data-testid="complaint-code">{{ $registered['code'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('complaints.success_due') }}</dt>
                    <dd>{{ $registered['due'] }}</dd>
                </div>
            </dl>
            <p>{{ __('complaints.success_email', ['email' => $registered['email']]) }}</p>
            <p class="lvt-cb__fine">{{ __('complaints.note_other_ways') }}</p>
            <a href="{{ route('home', ['locale' => $locale]) }}" class="lvt-cb__link">{{ __('complaints.success_new') }}</a>
        </section>
    @else
        <form action="{{ route('complaints.submit', ['locale' => $locale]) }}" method="post" id="form-complaint"
              class="lvt-cb__card" novalidate data-hj-suppress data-clarity-mask="true">
            @csrf
            {{-- Honeypot: los bots lo rellenan, las personas nunca lo ven --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">

            <h2 class="font-display text-2xl text-teal-700">{{ __('complaints.sheet_title') }}</h2>

            @if ($hasErrors)
                <div role="alert" tabindex="-1" id="cb-summary" class="lvt-cb__alert">
                    <strong>{{ __('complaints.err_summary') }}</strong>
                    @if ($errors->has('form') || $errors->has('recaptcha'))
                        <p>{{ $errors->first('form') ?: $errors->first('recaptcha') }}</p>
                    @endif
                </div>
            @endif

            {{-- Cabecera de la hoja: fecha, correlativo y proveedor --}}
            <dl class="lvt-cb__meta">
                <div><dt>{{ __('complaints.date') }}</dt><dd>{{ now()->format('d/m/Y') }}</dd></div>
                <div><dt>{{ __('complaints.number') }}</dt><dd>{{ __('complaints.number_pending') }}</dd></div>
            </dl>

            <section class="lvt-cb__provider" aria-labelledby="cb-provider-title">
                <h3 id="cb-provider-title">{{ __('complaints.provider_heading') }}</h3>
                <p><strong>Lima View Tours</strong></p>
                @foreach ($companyLines as $label => $value)
                    <p>{{ $label }}: {{ $value }}</p>
                @endforeach
                <p>{{ __('legal.company_phone') }}: <a href="tel:{{ \App\Support\LegalInfo::phoneTel() }}">{{ \App\Support\LegalInfo::phone() }}</a> · {{ \App\Support\LegalInfo::email() }}</p>
            </section>

            <p class="lvt-cb__fine">{{ __('complaints.required_hint') }}</p>

            {{-- 1. Consumidor --}}
            <fieldset class="lvt-cb__set">
                <legend>{{ __('complaints.s1_title') }}</legend>

                <div class="lvt-cb__grid">
                    <div class="lvt-cb__field">
                        <label for="cb-first_name">{{ __('complaints.first_name') }} *</label>
                        <input id="cb-first_name" name="first_name" type="text" required maxlength="120"
                               autocomplete="given-name" value="{{ old('first_name') }}" {!! $aria('first_name') !!}>
                        @if ($err('first_name'))<p class="lvt-cb__err" id="cb-first_name-err">{{ $err('first_name') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field">
                        <label for="cb-last_name">{{ __('complaints.last_name') }} *</label>
                        <input id="cb-last_name" name="last_name" type="text" required maxlength="120"
                               autocomplete="family-name" value="{{ old('last_name') }}" {!! $aria('last_name') !!}>
                        @if ($err('last_name'))<p class="lvt-cb__err" id="cb-last_name-err">{{ $err('last_name') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field">
                        <label for="cb-doc_type">{{ __('complaints.doc_type') }} *</label>
                        <select id="cb-doc_type" name="doc_type" required {!! $aria('doc_type') !!}>
                            @foreach (['dni', 'ce', 'passport'] as $opt)
                                <option value="{{ $opt }}" @selected($docType === $opt)>{{ __('complaints.doc_'.$opt) }}</option>
                            @endforeach
                        </select>
                        @if ($err('doc_type'))<p class="lvt-cb__err" id="cb-doc_type-err">{{ $err('doc_type') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field">
                        <label for="cb-doc_number">{{ __('complaints.doc_number') }} *</label>
                        <input id="cb-doc_number" name="doc_number" type="text" required maxlength="20"
                               inputmode="{{ $docInputmode }}" autocomplete="off" autocapitalize="characters"
                               value="{{ old('doc_number') }}" {!! $aria('doc_number') !!}>
                        @if ($err('doc_number'))<p class="lvt-cb__err" id="cb-doc_number-err">{{ $err('doc_number') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field lvt-cb__field--full">
                        <label for="cb-address">{{ __('complaints.address') }} *</label>
                        <input id="cb-address" name="address" type="text" required maxlength="255"
                               autocomplete="street-address" value="{{ old('address') }}" {!! $aria('address') !!}>
                        @if ($err('address'))<p class="lvt-cb__err" id="cb-address-err">{{ $err('address') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field">
                        <label for="cb-phone">{{ __('complaints.phone') }} *</label>
                        <input id="cb-phone" name="phone" type="tel" required maxlength="30" inputmode="tel"
                               autocomplete="tel" value="{{ old('phone') }}" {!! $aria('phone') !!}>
                        @if ($err('phone'))<p class="lvt-cb__err" id="cb-phone-err">{{ $err('phone') }}</p>@endif
                    </div>
                    <div class="lvt-cb__field">
                        <label for="cb-email">{{ __('complaints.email') }} *</label>
                        <input id="cb-email" name="email" type="email" required maxlength="200" inputmode="email"
                               autocomplete="email" autocapitalize="none" spellcheck="false"
                               value="{{ old('email') }}" {!! $aria('email') !!}>
                        @if ($err('email'))<p class="lvt-cb__err" id="cb-email-err">{{ $err('email') }}</p>@endif
                    </div>
                </div>

                <label class="lvt-cb__check" for="cb-is_minor">
                    <input id="cb-is_minor" type="checkbox" name="is_minor" value="1" @checked($isMinor)>
                    <span>{{ __('complaints.is_minor') }}</span>
                </label>
                <div class="lvt-cb__field" id="cb-guardian-wrap" @if (! $isMinor) hidden @endif>
                    <label for="cb-guardian_name">{{ __('complaints.guardian_name') }} *</label>
                    <input id="cb-guardian_name" name="guardian_name" type="text" maxlength="255"
                           autocomplete="off" value="{{ old('guardian_name') }}" {!! $aria('guardian_name') !!}>
                    @if ($err('guardian_name'))<p class="lvt-cb__err" id="cb-guardian_name-err">{{ $err('guardian_name') }}</p>@endif
                </div>
            </fieldset>

            {{-- 2. Bien contratado --}}
            <fieldset class="lvt-cb__set">
                <legend>{{ __('complaints.s2_title') }}</legend>

                <div class="lvt-cb__field">
                    <span class="lvt-cb__label" id="cb-good_type-label">{{ __('complaints.good_type') }} *</span>
                    <div class="lvt-cb__radios" role="radiogroup" aria-labelledby="cb-good_type-label" {!! $aria('good_type') !!}>
                        @foreach (['service' => 'good_service', 'product' => 'good_product'] as $val => $lbl)
                            <label class="lvt-cb__radio">
                                <input type="radio" name="good_type" value="{{ $val }}" @checked(old('good_type', 'service') === $val)>
                                <span>{{ __('complaints.'.$lbl) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($err('good_type'))<p class="lvt-cb__err" id="cb-good_type-err">{{ $err('good_type') }}</p>@endif
                </div>

                <div class="lvt-cb__grid">
                    <div class="lvt-cb__field">
                        <label for="cb-booking_reference">{{ __('complaints.booking_reference') }}</label>
                        <input id="cb-booking_reference" name="booking_reference" type="text" maxlength="30"
                               autocomplete="off" autocapitalize="characters" placeholder="LVT-ABCD1234"
                               value="{{ old('booking_reference') }}" {!! $aria('booking_reference', 'hint') !!}>
                        <p class="lvt-cb__hint" id="cb-booking_reference-hint">{{ __('complaints.booking_hint') }}</p>
                        @if ($err('booking_reference'))<p class="lvt-cb__err" id="cb-booking_reference-err">{{ $err('booking_reference') }}</p>@endif
                    </div>
                    <div class="lvt-cb__grid lvt-cb__grid--amount">
                        <div class="lvt-cb__field">
                            <label for="cb-claimed_amount">{{ __('complaints.claimed_amount') }}</label>
                            <input id="cb-claimed_amount" name="claimed_amount" type="text" inputmode="decimal"
                                   maxlength="12" autocomplete="off" placeholder="0.00"
                                   value="{{ old('claimed_amount') }}" {!! $aria('claimed_amount') !!}>
                            @if ($err('claimed_amount'))<p class="lvt-cb__err" id="cb-claimed_amount-err">{{ $err('claimed_amount') }}</p>@endif
                        </div>
                        <div class="lvt-cb__field">
                            <label for="cb-currency">{{ __('complaints.currency') }}</label>
                            <select id="cb-currency" name="currency" {!! $aria('currency') !!}>
                                <option value="PEN" @selected(old('currency', 'PEN') === 'PEN')>{{ __('complaints.currency_pen') }}</option>
                                <option value="USD" @selected(old('currency') === 'USD')>{{ __('complaints.currency_usd') }}</option>
                            </select>
                            @if ($err('currency'))<p class="lvt-cb__err" id="cb-currency-err">{{ $err('currency') }}</p>@endif
                        </div>
                    </div>
                </div>

                <div class="lvt-cb__field">
                    <label for="cb-good_description">{{ __('complaints.good_description') }} *</label>
                    <textarea id="cb-good_description" name="good_description" rows="3" required maxlength="2000"
                              {!! $aria('good_description', 'hint') !!}>{{ old('good_description') }}</textarea>
                    <p class="lvt-cb__hint" id="cb-good_description-hint">{{ __('complaints.good_description_hint') }}</p>
                    @if ($err('good_description'))<p class="lvt-cb__err" id="cb-good_description-err">{{ $err('good_description') }}</p>@endif
                </div>
            </fieldset>

            {{-- 3. Reclamo / queja --}}
            <fieldset class="lvt-cb__set">
                <legend>{{ __('complaints.s3_title') }}</legend>

                <div class="lvt-cb__field">
                    <span class="lvt-cb__label" id="cb-type-label">{{ __('complaints.type') }} *</span>
                    <div class="lvt-cb__types" role="radiogroup" aria-labelledby="cb-type-label" {!! $aria('type') !!}>
                        @foreach (['claim' => 'type_claim', 'complaint' => 'type_complaint'] as $val => $lbl)
                            <label class="lvt-cb__type">
                                <input type="radio" name="type" value="{{ $val }}" @checked(old('type', 'claim') === $val)>
                                <span>
                                    <strong>{{ __('complaints.'.$lbl) }}</strong>
                                    <small>{{ __('complaints.'.$lbl.'_desc') }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($err('type'))<p class="lvt-cb__err" id="cb-type-err">{{ $err('type') }}</p>@endif
                </div>

                <div class="lvt-cb__field">
                    <label for="cb-detail">{{ __('complaints.detail') }} *</label>
                    <textarea id="cb-detail" name="detail" rows="5" required maxlength="4000" {!! $aria('detail') !!}>{{ old('detail') }}</textarea>
                    @if ($err('detail'))<p class="lvt-cb__err" id="cb-detail-err">{{ $err('detail') }}</p>@endif
                </div>
                <div class="lvt-cb__field">
                    <label for="cb-consumer_request">{{ __('complaints.consumer_request') }} *</label>
                    <textarea id="cb-consumer_request" name="consumer_request" rows="3" required maxlength="4000"
                              {!! $aria('consumer_request', 'hint') !!}>{{ old('consumer_request') }}</textarea>
                    <p class="lvt-cb__hint" id="cb-consumer_request-hint">{{ __('complaints.consumer_request_hint') }}</p>
                    @if ($err('consumer_request'))<p class="lvt-cb__err" id="cb-consumer_request-err">{{ $err('consumer_request') }}</p>@endif
                </div>
            </fieldset>

            {{-- 4. Parte del proveedor (informativo) --}}
            <section class="lvt-cb__provider" aria-labelledby="cb-s4-title">
                <h3 id="cb-s4-title">{{ __('complaints.s4_title') }}</h3>
                <p>{{ __('complaints.s4_note') }}</p>
            </section>

            <div class="lvt-cb__field">
                <label class="lvt-cb__check" for="cb-accepted_declaration">
                    <input id="cb-accepted_declaration" type="checkbox" name="accepted_declaration" value="1" required
                           @checked(old('accepted_declaration')) {!! $aria('accepted_declaration') !!}>
                    <span>{{ __('complaints.declaration') }} *</span>
                </label>
                @if ($err('accepted_declaration'))<p class="lvt-cb__err" id="cb-accepted_declaration-err">{{ $err('accepted_declaration') }}</p>@endif
            </div>

            <p class="lvt-cb__fine">
                {{ __('complaints.privacy_prefix') }}
                <a href="{{ \App\Support\LocalizedPages::url('legal.privacy', $locale) }}">{{ __('footer.privacy') }}</a>.
            </p>

            @include('partials.recaptcha', ['recaptchaAction' => 'complaint', 'recaptchaFormId' => 'form-complaint'])

            <button type="submit" class="lvt-cb__submit">{{ __('complaints.submit') }}</button>
        </form>
    @endif

    {{-- Notas legales (siempre visibles) --}}
    <aside class="lvt-cb__legal" aria-label="{{ __('complaints.page_title') }}">
        <p>{{ __('complaints.note_claim_def') }}</p>
        <p>{{ __('complaints.note_other_ways') }}</p>
        <p>{{ __('complaints.note_deadline', ['days' => $dueDays]) }}</p>
        <p><a href="{{ \App\Support\LocalizedPages::url('legal.refund', $locale) }}">{{ __('complaints.refund_link') }}</a>
            · <a href="{{ \App\Support\LocalizedPages::url('legal.terms', $locale) }}">{{ __('footer.terms') }}</a></p>
    </aside>
</div>

<style>
    /* Estilos propios de la hoja (no dependen del build de Vite de producción). */
    .lvt-cb { max-width: 60rem; }
    .lvt-cb__head { margin-bottom: 1.5rem; }
    .lvt-cb__eyebrow { font-size: .75rem; letter-spacing: .2em; text-transform: uppercase; font-weight: 600; color: #9f5a26; margin-bottom: .5rem; }
    .lvt-cb__lead { margin-top: 1rem; color: #374151; line-height: 1.65; }
    .lvt-cb__banner { margin-top: 1rem; padding: .85rem 1rem; border-radius: .75rem; background: #fdf6ee; border: 1px solid #f4d2a4; color: #623820; font-weight: 600; line-height: 1.5; }
    .lvt-cb__card { background: #fff; border: 1px solid #e5e7eb; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    .lvt-cb__card > * + * { margin-top: 1.25rem; }
    .lvt-cb__alert { background: #fdeaea; border: 1px solid #c81f21; color: #8f1517; border-radius: .75rem; padding: .85rem 1rem; line-height: 1.5; }
    .lvt-cb__alert:focus { outline: 2px solid #c81f21; outline-offset: 2px; }
    .lvt-cb__meta { display: grid; gap: .75rem; grid-template-columns: 1fr; font-size: .95rem; }
    .lvt-cb__meta dt, .lvt-cb__code-box dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; color: #6b7280; font-weight: 600; }
    .lvt-cb__meta dd { margin: .15rem 0 0; color: #111827; }
    .lvt-cb__provider { background: #f0f7f7; border-radius: .75rem; padding: .9rem 1rem; color: #0f3438; font-size: .92rem; line-height: 1.6; overflow-wrap: anywhere; }
    .lvt-cb__provider h3 { font-weight: 700; margin-bottom: .35rem; }
    .lvt-cb__provider a { text-decoration: underline; }
    .lvt-cb__set { border: 0; padding: 0; margin: 0; min-width: 0; }
    .lvt-cb__set > legend { font-family: inherit; font-weight: 700; font-size: 1.05rem; color: #15474b; padding: 0 0 .75rem; width: 100%; border-bottom: 1px solid #dcecec; margin-bottom: 1rem; }
    .lvt-cb__set > * + * { margin-top: 1rem; }
    .lvt-cb__grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
    .lvt-cb__grid--amount { grid-template-columns: 1fr 1fr; align-items: start; margin-top: 0; }
    .lvt-cb__field { min-width: 0; }
    .lvt-cb__field label, .lvt-cb__label { display: block; font-size: .9rem; font-weight: 600; color: #0f3438; margin-bottom: .35rem; }
    .lvt-cb__field input[type=text], .lvt-cb__field input[type=tel], .lvt-cb__field input[type=email],
    .lvt-cb__field select, .lvt-cb__field textarea {
        display: block; width: 100%; box-sizing: border-box; min-height: 48px; padding: .7rem .9rem; font-size: 16px; line-height: 1.4;
        border: 1px solid #8ebdbf; border-radius: .6rem; background: #fff; color: #111827; font-family: inherit;
    }
    .lvt-cb__field textarea { resize: vertical; }
    .lvt-cb__field input:focus, .lvt-cb__field select:focus, .lvt-cb__field textarea:focus { outline: 2px solid #e29347; outline-offset: 1px; border-color: #e29347; }
    .lvt-cb__field [aria-invalid="true"] { border-color: #c81f21; background: #fff8f8; }
    .lvt-cb__hint { margin-top: .3rem; font-size: .8rem; color: #4b5563; }
    .lvt-cb__err { margin-top: .35rem; font-size: .88rem; font-weight: 600; color: #b01a1c; }
    .lvt-cb__check { display: flex; align-items: flex-start; gap: .7rem; min-height: 44px; padding: .35rem 0; cursor: pointer; font-size: .95rem; line-height: 1.5; color: #1f2937; }
    .lvt-cb__check input { flex: none; width: 22px; height: 22px; margin-top: .1rem; accent-color: #15474b; }
    .lvt-cb__radios { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; }
    .lvt-cb__radio { display: inline-flex; align-items: center; gap: .55rem; min-height: 44px; cursor: pointer; }
    .lvt-cb__radio input, .lvt-cb__type input { width: 22px; height: 22px; flex: none; accent-color: #15474b; }
    .lvt-cb__types { display: grid; gap: .65rem; grid-template-columns: 1fr; }
    .lvt-cb__type { display: flex; align-items: flex-start; gap: .7rem; padding: .8rem .9rem; border: 1px solid #b9d9da; border-radius: .75rem; cursor: pointer; min-height: 44px; }
    .lvt-cb__type:has(input:checked) { border-color: #15474b; background: #f0f7f7; }
    .lvt-cb__type strong { display: block; color: #0f3438; }
    .lvt-cb__type small { display: block; margin-top: .15rem; font-size: .85rem; line-height: 1.45; color: #374151; }
    .lvt-cb__fine { font-size: .82rem; color: #4b5563; line-height: 1.55; }
    .lvt-cb__fine a, .lvt-cb__legal a { color: #15474b; text-decoration: underline; }
    .lvt-cb__submit { display: block; width: 100%; min-height: 52px; padding: .9rem 1.25rem; border: 0; border-radius: 999px; background: #15474b; color: #fff; font-weight: 700; font-size: 1rem; cursor: pointer; font-family: inherit; }
    .lvt-cb__submit:hover { background: #0f3438; }
    .lvt-cb__submit:focus-visible { outline: 3px solid #e29347; outline-offset: 2px; }
    .lvt-cb__success { text-align: center; }
    .lvt-cb__success:focus { outline: none; }
    .lvt-cb__code-box { display: grid; gap: 1rem; padding: 1rem; background: #f0f7f7; border-radius: .75rem; }
    .lvt-cb__code { font-size: 1.6rem; font-weight: 800; letter-spacing: .04em; color: #15474b; overflow-wrap: anywhere; }
    .lvt-cb__link { display: inline-flex; align-items: center; min-height: 44px; color: #15474b; font-weight: 600; text-decoration: underline; }
    .lvt-cb__legal { margin-top: 1.5rem; padding: 1rem 0 0; border-top: 1px solid #e5e7eb; font-size: .85rem; color: #374151; line-height: 1.6; }
    .lvt-cb__legal p + p { margin-top: .6rem; }
    .lvt-cb__legal a { display: inline-block; padding: .65rem 0; }
    @media (min-width: 640px) {
        .lvt-cb__card { padding: 2rem; }
        .lvt-cb__meta, .lvt-cb__grid { grid-template-columns: 1fr 1fr; }
        .lvt-cb__field--full { grid-column: 1 / -1; }
        .lvt-cb__types { grid-template-columns: 1fr 1fr; }
        .lvt-cb__code-box { grid-template-columns: 1fr 1fr; }
    }
</style>

@push('scripts')
<script>
(function () {
    var f = document.getElementById('form-complaint');
    var ok = document.getElementById('cb-success');
    if (ok) { ok.focus(); return; }
    if (!f) { return; }

    // Menor de edad: muestra el campo del padre/madre/apoderado.
    var minor = document.getElementById('cb-is_minor');
    var wrap = document.getElementById('cb-guardian-wrap');
    var guardian = document.getElementById('cb-guardian_name');
    function syncMinor() {
        wrap.hidden = !minor.checked;
        if (guardian) { guardian.required = minor.checked; }
    }
    minor.addEventListener('change', syncMinor);
    syncMinor();

    // Tipo de documento: teclado numérico solo para DNI.
    var type = document.getElementById('cb-doc_type');
    var num = document.getElementById('cb-doc_number');
    function syncDoc() {
        num.setAttribute('inputmode', type.value === 'dni' ? 'numeric' : 'text');
        num.maxLength = type.value === 'dni' ? 8 : 12;
    }
    type.addEventListener('change', syncDoc);
    syncDoc();

    // Con errores del servidor: el foco va al aviso (lectores de pantalla) y se muestra completo.
    var summary = document.getElementById('cb-summary');
    if (summary) { summary.focus(); }
})();
</script>
@endpush
@endsection

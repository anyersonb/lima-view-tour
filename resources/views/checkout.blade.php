@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $firstTravelDate ??= now()->addDays(7)->toDateString();
    $cancelDeadline = \Carbon\Carbon::parse($firstTravelDate)->subDay()->setTime(9, 0);
    $openStep = session('open_step', 'reservas'); // 'reservas'|'datos'|'pago'
@endphp

@section('title', __('ui.cart_title') . ' — ' . __('seo.site_name'))
@section('description', __('ui.cart_subtitle'))

@push('head')
<meta name="robots" content="noindex,nofollow">
<style>
/* ════════════════════════════════════════════════════════════
   CARRITO — 3 pasos en una sola pantalla
   Prefijo: cart-   (no colisiona con Tailwind)
════════════════════════════════════════════════════════════ */
:root {
    --cart-ink:    #143E40;
    --cart-muted:  #746F69;
    --cart-line:   #E6D9C8;
    --cart-green:  #143E40;
    --cart-green2: #1B4D4F;
    --cart-gold:   #D9A15C;
    --cart-soft:   #F7F1E8;
    --cart-okbg:   #F2F5EF;
    --cart-ok:     #1B4D4F;
    --cart-danger: #C86E57;
    --cart-paper:  #FFFCF8;
    --cart-shadow: 0 12px 28px rgba(20,62,64,.075);
}

/* ── Switcher ── */
.cart-switcher {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 16px 0 0;
    margin-bottom: 20px;
}
.cart-switch-btn {
    height: 44px;
    border: 1px solid var(--cart-line);
    background: #fff;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: var(--cart-ink);
    box-shadow: 0 8px 22px rgba(0,0,0,.05);
    cursor: pointer;
    transition: background .18s, color .18s, border-color .18s;
    white-space: nowrap;
}
.cart-switch-btn.active,
.cart-switch-btn:focus-visible {
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff;
    border-color: transparent;
    outline: none;
}

/* ── Screens ── */
.cart-screen { display: none; }
.cart-screen.active { display: block; }

/* ── Stepper numerado ── */
.cart-segment {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 18px;
}
.cart-seg-btn {
    border: 1px solid var(--cart-line);
    background: #fff;
    border-radius: 15px;
    padding: 9px 8px;
    text-align: center;
    box-shadow: 0 8px 20px rgba(0,0,0,.04);
    cursor: pointer;
    transition: border-color .18s, box-shadow .18s;
    text-decoration: none;
    display: block;
}
.cart-seg-btn.active {
    border-color: rgba(20,62,64,.35);
    box-shadow: 0 10px 22px rgba(20,62,64,.08);
}
.cart-seg-num {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    margin: 0 auto 5px;
    background: #F5EBDD;
    color: #A87A45;
    font-size: 10.5px;
    font-weight: 900;
    transition: background .18s, color .18s;
}
.cart-seg-btn.active .cart-seg-num {
    background: var(--cart-green);
    color: #fff;
}
.cart-seg-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    color: #435158;
}

/* ── Panels ── */
.cart-panel { display: none; }
.cart-panel.active { display: block; }

/* ── Cabecera de sección ── */
.cart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 2px 0 14px;
}
.cart-head h2 {
    margin: 0;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 22px;
    line-height: 1;
    color: var(--cart-ink);
}
.cart-meta-link {
    color: var(--cart-green);
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
}

/* ── Lista tours ── */
.cart-list { display: grid; gap: 14px; }

/* ── Tour card ── */
.cart-tour-card {
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 22px;
    box-shadow: var(--cart-shadow);
    padding: 14px;
    position: relative;
    overflow: hidden;
    transition: box-shadow .2s;
}
.cart-tour-card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, var(--cart-gold), #e9c68b);
}
.cart-tour-card.removing {
    opacity: 0;
    transform: translateX(-12px);
    transition: opacity .28s ease, transform .28s ease;
}

/* chips */
.cart-chip-row { display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 10px; padding-left: 6px; }
.cart-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 9px; border-radius: 999px;
    background: var(--cart-soft); border: 1px solid var(--cart-line);
    font-size: 9.6px; font-weight: 700; color: #5E5A55; letter-spacing: .2px;
}
.cart-chip.promo { background: var(--cart-okbg); border-color: #d7ecdf; color: var(--cart-ok); }

/* tour top */
.cart-tour-top { display: grid; grid-template-columns: 82px 1fr auto; gap: 12px; align-items: start; }
.cart-thumb { width: 82px; height: 82px; border-radius: 16px; overflow: hidden; flex-shrink: 0; }
.cart-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cart-tour-info h3 {
    margin: 0 0 5px;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 16px; line-height: 1.1; color: var(--cart-ink);
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.cart-meta-line { font-size: 11px; color: var(--cart-gold); font-weight: 700; margin-bottom: 5px; }

/* price box */
.cart-price-box { text-align: right; min-width: 92px; display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.cart-price-tag { font-size: 8.5px; font-weight: 900; letter-spacing: .85px; text-transform: uppercase; color: #7a848a; }
.cart-price-before { font-size: 12px; color: #948C83; text-decoration: line-through; font-weight: 700; }
.cart-price-now { font-family: 'Instrument Serif', Georgia, serif; font-size: 24px; line-height: 1; color: var(--cart-green); }
.cart-price-saved {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--cart-okbg); border: 1px solid #d5ecdf; color: var(--cart-ok);
    padding: 4px 7px; border-radius: 999px; font-size: 8.5px; font-weight: 700;
}
.cart-price-regular { font-family: 'Instrument Serif', Georgia, serif; font-size: 24px; line-height: 1; color: var(--cart-green); margin-top: 10px; }
.cart-remove-btn {
    margin-top: 5px; color: var(--cart-danger); font-size: 10px; font-weight: 700;
    cursor: pointer; background: none; border: none; padding: 0;
    text-decoration: underline; text-underline-offset: 2px;
}
.cart-remove-btn:focus-visible { outline: 2px solid var(--cart-danger); border-radius: 3px; }

/* field boxes */
.cart-tour-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; margin-top: 12px; }
.cart-field-box { background: #FCF7F0; border: 1px solid #E8DED1; border-radius: 16px; padding: 9px; }
.cart-field-box label { display: block; font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #746F69; margin: 0 0 6px; }
.cart-mini-input, .cart-mini-select {
    height: 42px; border-radius: 12px; border: 1px solid #ded5c8;
    background: #fff; padding: 0 10px; font-size: 16px; color: #45545a;
    width: 100%; outline: none; font-family: inherit;
}
.cart-mini-input:focus, .cart-mini-select:focus { border-color: var(--cart-green2); box-shadow: 0 0 0 2px rgba(27,77,79,.15); }

/* pax shell */
.cart-pax-shell { margin-top: 12px; background: #FCF7F0; border: 1px solid #E8DED1; border-radius: 18px; padding: 11px; }
.cart-pax-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 9px; }
.cart-pax-head strong { font-size: 10px; letter-spacing: .9px; text-transform: uppercase; color: #6e767c; font-weight: 700; }
.cart-pax-head span { font-size: 11px; color: #7c858b; font-weight: 600; }
.cart-pax-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cart-pax-card { background: #fff; border: 1px solid var(--cart-line); border-radius: 14px; padding: 10px; }
.cart-pax-card label { display: block; font-size: 11px; font-weight: 700; color: var(--cart-ink); margin-bottom: 7px; }
.cart-qty-control { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.cart-qty-btn {
    width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center;
    background: #fff; border: 1px solid #ded4c7; color: var(--cart-green);
    font-weight: 900; cursor: pointer; font-size: 16px; line-height: 1;
    transition: background .15s, border-color .15s;
}
.cart-qty-btn:hover { background: var(--cart-soft); border-color: var(--cart-gold); }
.cart-qty-btn:focus-visible { outline: 2px solid var(--cart-green2); }
.cart-qty-btn:disabled { opacity: .35; cursor: not-allowed; }
.cart-qty-value { font-size: 16px; font-weight: 900; color: var(--cart-ink); min-width: 16px; text-align: center; }

/* ── Venta cruzada ── */
.cart-section-title-card {
    display: flex; align-items: center; justify-content: space-between;
    margin: 18px 0 12px; background: #fff; border: 1px solid var(--cart-line);
    border-radius: 18px; padding: 12px 14px; box-shadow: 0 8px 18px rgba(20,62,64,.04);
}
.cart-section-title-card h3 { margin: 0; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 18px; color: var(--cart-ink); }
.cart-section-title-card span { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--cart-gold); }
/* Base (carrito vacío / "compact"): grid responsive que LLENA el ancho
   → 3-4 tarjetas grandes en desktop, 2 en tablet, 1 amplia en mobile */
.cart-more-tours {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 14px; padding: 2px 2px 8px;
}
/* Con items en el carrito: slider horizontal de tarjetas amplias */
.cart-more-tours:not(.compact) {
    grid-auto-flow: column; grid-template-columns: none;
    grid-auto-columns: minmax(230px, 1fr);
    overflow-x: auto; scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.cart-more-tours::-webkit-scrollbar { display: none; }
.cart-more-card { scroll-snap-align: start; background: #fff; border: 1px solid var(--cart-line); border-radius: 22px; overflow: hidden; box-shadow: 0 10px 22px rgba(0,0,0,.05); display: flex; flex-direction: column; }
.cart-more-card img { width: 100%; height: clamp(140px, 19vw, 180px); object-fit: cover; display: block; }
.cart-more-body { padding: 14px 14px 16px; display: flex; flex-direction: column; flex: 1; }
.cart-more-tag { font-size: 10px; font-weight: 700; letter-spacing: 1.6px; color: var(--cart-gold); text-transform: uppercase; margin: 0 0 6px; }
.cart-more-card h4 { margin: 0 0 10px; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 17px; line-height: 1.15; color: var(--cart-ink); min-height: 40px; }
.cart-more-price { font-size: 18px; font-weight: 700; color: var(--cart-green); margin-top: auto; margin-bottom: 10px; }
.cart-add-btn {
    width: 100%; height: 38px; border-radius: 14px; border: 1px solid #D8E5DE;
    background: #F1F7F3; color: var(--cart-green); padding: 0 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; cursor: pointer;
    transition: background .15s, color .15s; font-family: inherit;
    text-decoration: none;
}
.cart-add-btn:hover { background: #e2f0e7; }
.cart-add-btn.added { background: #EAF4EE; color: #167A56; border-color: #D5E7DA; cursor: default; }
.cart-add-btn:focus-visible { outline: 2px solid var(--cart-green2); }

/* ── Panel 2: Datos — card genérica ── */
.cart-card { background: #fff; border: 1px solid var(--cart-line); border-radius: 24px; box-shadow: var(--cart-shadow); overflow: hidden; margin-bottom: 14px; }
.cart-card-pad { padding: 16px; }
.cart-form-grid { display: grid; gap: 11px; }
.cart-two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cart-field label {
    display: block; margin: 0 0 6px;
    font-size: 10px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #798188;
}
.cart-field label .req { color: var(--cart-danger); }
.cart-real-input, .cart-real-select, textarea.cart-real-input {
    width: 100%; min-height: 44px; border-radius: 15px;
    border: 1px solid #ded5c8; background: #fcfbf8;
    padding: 0 13px; font-size: 16px; color: #26343a; outline: none; font-family: inherit;
}
/* Flecha desplegable visible (consistente en iOS/Android/desktop) */
.cart-real-select {
    -webkit-appearance: none; -moz-appearance: none; appearance: none;
    padding-right: 34px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230f3438' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 14px 14px;
}
textarea.cart-real-input { padding-top: 12px; min-height: 90px; resize: vertical; }
.cart-real-input:focus, .cart-real-select:focus {
    border-color: var(--cart-green2);
    box-shadow: 0 0 0 2px rgba(27,77,79,.15);
}
.cart-field-help {
    margin-top: 7px; font-size: 11px; line-height: 1.32; color: #6b747a;
    background: #f8f2e8; border: 1px solid #eadfce; border-radius: 13px; padding: 9px 10px;
}
.cart-save-btn {
    width: 100%; height: 44px; border-radius: 14px;
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff; border: none; font-size: 13px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: opacity .15s;
}
.cart-save-btn:hover { opacity: .9; }

/* ── Panel 3: Pago — payment-tour-cards ── */
.cart-payment-tour-list { display: grid; gap: 10px; margin-bottom: 14px; }
.cart-payment-tour-card {
    background: #fff; border: 1px solid var(--cart-line);
    border-radius: 18px; padding: 11px; box-shadow: 0 8px 18px rgba(0,0,0,.04);
}
.cart-payment-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
.cart-payment-head-left { display: grid; gap: 7px; min-width: 0; }
.cart-payment-order {
    display: inline-flex; align-items: center; gap: 6px;
    width: max-content; padding: 5px 9px; border-radius: 999px;
    background: #f8f2e8; border: 1px solid #eadfce;
    font-size: 9px; font-weight: 900; letter-spacing: .55px; text-transform: uppercase; color: #6b7278;
}
.cart-payment-order-num {
    width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center;
    background: var(--cart-green); color: #fff; font-size: 9px; font-weight: 900;
}
.cart-payment-tour-card h4 { margin: 0; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 15.5px; line-height: 1.08; color: var(--cart-ink); }
.cart-payment-badge {
    display: inline-flex; align-items: center; padding: 5px 8px; border-radius: 999px;
    background: #f8f2e8; border: 1px solid #eadfce; font-size: 9px; font-weight: 900; color: #58646a; white-space: nowrap;
}
.cart-payment-badge.promo { background: var(--cart-okbg); border-color: #d7ecdf; color: var(--cart-ok); }
.cart-payment-body { display: grid; gap: 6px; }
.cart-payment-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 6px 0; border-bottom: 1px dashed #eee3d2;
}
.cart-payment-row:last-of-type { border-bottom: 0; }
.cart-payment-row span { font-size: 11.5px; color: #617077; }
.cart-payment-row b { font-size: 12px; color: var(--cart-ink); }
.cart-payment-row .strike { text-decoration: line-through; color: #8e979e; }
.cart-payment-row .green { color: var(--cart-ok); }
.cart-payment-final {
    margin-top: 6px; background: #fbf7f1; border: 1px solid #ece1d1;
    border-radius: 14px; padding: 9px 10px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.cart-payment-final span { font-size: 10px; font-weight: 900; letter-spacing: .6px; text-transform: uppercase; color: #69757b; }
.cart-payment-final b { font-family: 'Instrument Serif', Georgia, serif; font-size: 21px; line-height: 1; color: var(--cart-green); }

/* summary rows */
.cart-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #eee6da; }
.cart-summary-row:last-child { border-bottom: 0; }
.cart-summary-row span { font-size: 13px; color: #59646a; }
.cart-summary-row b { font-size: 14px; color: var(--cart-ink); }
.cart-summary-row.cart-total-row b { font-family: 'Instrument Serif', Georgia, serif; font-size: 24px; color: var(--cart-green); }

/* pay chips */
.cart-pay-chips { display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 12px; }
.cart-pay-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 8px; border-radius: 999px;
    background: #f8f2e8; border: 1px solid #eadfce;
    font-size: 10px; font-weight: 900; color: #49565c;
}

/* pay methods */
.cart-pay-methods { display: grid; gap: 9px; }
.cart-pay {
    border: 1px solid var(--cart-line); border-radius: 17px;
    background: #fff; padding: 12px;
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; transition: border-color .18s, box-shadow .18s;
}
.cart-pay.active,
.cart-pay:has(input:checked) {
    border-color: rgba(20,62,64,.4);
    box-shadow: 0 8px 18px rgba(20,62,64,.06);
}
.cart-pay-left { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 900; color: #273d43; }
.cart-pay-icon { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--cart-soft); font-size: 18px; }
.cart-pay-radio { width: 20px; height: 20px; border-radius: 50%; border: 2px solid #d5cab9; }
.cart-pay input[type="radio"] { display: none; }
.cart-pay input[type="radio"]:checked ~ .cart-pay-radio { border: 6px solid var(--cart-green); }

/* policy box */
.cart-policy-box {
    margin-top: 10px; background: #f8f1e7; border: 1px solid #eadcca;
    border-radius: 18px; padding: 11px 12px; color: #4f5b60; font-size: 12px; line-height: 1.35;
}
.cart-policy-box b { display: block; color: var(--cart-ink); margin-bottom: 4px; }

/* terms */
.cart-terms-row {
    margin-top: 12px; display: flex; align-items: flex-start; gap: 10px;
    font-size: 12px; color: #617077; line-height: 1.4; cursor: pointer;
}
.cart-terms-row input { margin-top: 2px; }
.cart-terms-row a { color: var(--cart-gold); text-decoration: underline; text-underline-offset: 2px; }

/* timing radios */
.cart-timing-grid { display: grid; gap: 9px; margin-bottom: 12px; }
.cart-timing-label {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 14px; border-radius: 16px; border: 2px solid var(--cart-line);
    cursor: pointer; transition: border-color .18s;
}
.cart-timing-label:has(input:checked) { border-color: var(--cart-green); background: rgba(20,62,64,.03); }
.cart-timing-label input { margin-top: 3px; accent-color: var(--cart-green); }
.cart-timing-text { flex: 1; }
.cart-timing-text b { display: block; font-size: 13px; font-weight: 700; color: var(--cart-ink); margin-bottom: 3px; }
.cart-timing-text span { font-size: 11px; color: #617077; }
.cart-timing-price { font-family: 'Instrument Serif', Georgia, serif; font-size: 18px; color: var(--cart-green); white-space: nowrap; }

/* ── Cupón ── */
.cart-coupon-card {
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff; border-radius: 20px; padding: 14px 16px;
    display: flex; flex-direction: column; gap: 10px;
}
@media (min-width: 540px) { .cart-coupon-card { flex-direction: row; align-items: center; } }
.cart-coupon-text { font-size: 13px; flex: 1; line-height: 1.4; }
.cart-coupon-form { display: flex; gap: 8px; }
.cart-coupon-input {
    flex: 1; min-width: 0; height: 40px; border-radius: 999px;
    background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.3);
    color: #fff; padding: 0 14px; font-size: 13px; font-family: inherit; outline: none;
}
.cart-coupon-input::placeholder { color: rgba(255,255,255,.55); }
.cart-coupon-input:focus { border-color: rgba(255,255,255,.6); }
.cart-coupon-submit {
    height: 40px; border-radius: 999px; background: #fff; color: var(--cart-green);
    border: none; padding: 0 18px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em; cursor: pointer; white-space: nowrap;
    transition: background .15s; font-family: inherit;
}
.cart-coupon-submit:hover { background: var(--cart-soft); }

/* ── Sticky footer ── */
.cart-sticky {
    position: fixed; left: 50%; transform: translateX(-50%);
    bottom: 12px; width: min(600px, calc(100vw - 24px));
    z-index: 40; pointer-events: none;
}
.cart-sticky-inner {
    pointer-events: auto; background: rgba(255,255,255,.97);
    border: 1px solid #e8ddce; border-radius: 20px;
    box-shadow: 0 -8px 26px rgba(0,0,0,.12);
    padding: 10px 14px; display: grid;
    grid-template-columns: 1fr 1.4fr; gap: 12px; align-items: center;
}
.cart-total-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #737a80; margin-bottom: 2px; }
.cart-total-price { font-family: 'Instrument Serif', Georgia, serif; font-size: 26px; line-height: 1; color: var(--cart-green); }
.cart-cta-btn {
    height: 50px; border-radius: 16px;
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff; border: none; display: flex; align-items: center; justify-content: center;
    gap: 9px; font-size: 13.5px; font-weight: 700;
    box-shadow: 0 12px 22px rgba(20,62,64,.18); cursor: pointer;
    text-decoration: none; font-family: inherit; transition: opacity .15s; width: 100%;
}
.cart-cta-btn:hover { opacity: .9; }
.cart-cta-btn:focus-visible { outline: 2px solid var(--cart-gold); outline-offset: 2px; }
.cart-cta-arrow {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--cart-gold); display: grid; place-items: center;
    color: #fff; font-size: 14px; flex-shrink: 0;
}

/* ── Botones de navegación entre pasos (Atrás / Adelante) ── */
.cart-step-nav { display: flex; align-items: center; gap: 10px; margin-top: 20px; }
.cart-step-nav .nav-next { margin-left: auto; }
.cart-step-btn {
    display: inline-flex; align-items: center; gap: 8px;
    height: 48px; padding: 0 20px; border-radius: 14px;
    font-weight: 800; font-size: 14px; cursor: pointer; font-family: inherit;
    border: 1px solid var(--cart-line); background: #fff; color: var(--cart-ink);
    transition: background .15s, opacity .15s;
}
.cart-step-btn:hover { background: #f6f1e9; }
.cart-step-btn.primary { background: var(--cart-green); color: #fff; border-color: transparent; }
.cart-step-btn.primary:hover { background: var(--cart-green2); opacity: 1; }

/* ── Estado vacío ── */
.cart-empty-state {
    background: linear-gradient(180deg, #FFFDF9 0%, var(--cart-soft) 100%);
    border: 1px solid var(--cart-line); border-radius: 22px;
    padding: 18px 16px; box-shadow: 0 10px 22px rgba(20,62,64,.05); margin-top: 4px;
}
.cart-empty-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.cart-empty-icon {
    width: 52px; height: 52px; border-radius: 18px;
    background: linear-gradient(135deg, var(--cart-green), var(--cart-green2));
    display: grid; place-items: center; font-size: 24px;
    box-shadow: 0 12px 20px rgba(20,62,64,.14); flex-shrink: 0;
}
.cart-empty-state h3 { margin: 0; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 20px; line-height: 1.1; color: var(--cart-ink); }
.cart-empty-state p { margin: 6px 0 0; font-size: 12.5px; line-height: 1.5; color: var(--cart-muted); }
.cart-empty-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.cart-empty-pill { padding: 7px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--cart-line); font-size: 11px; font-weight: 700; color: var(--cart-ink); }
.cart-empty-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
.cart-btn-dark {
    flex: 1; min-width: 120px; height: 40px; border-radius: 12px;
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff; border: none; display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; transition: opacity .15s;
}
.cart-btn-dark:hover { opacity: .88; }
.cart-btn-outline {
    flex: 1; min-width: 120px; height: 40px; border-radius: 12px;
    background: #fff; border: 1px solid #d8dcd8; color: var(--cart-green);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; transition: background .15s;
}
.cart-btn-outline:hover { background: var(--cart-soft); }
.cart-empty-note-card { margin-top: 12px; background: #fff; border: 1px dashed #D8C7AE; border-radius: 18px; padding: 14px; }
.cart-empty-note-card h4 { margin: 0 0 8px; font-size: 13px; font-weight: 700; color: var(--cart-ink); }
.cart-empty-note-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.cart-empty-note-list li { font-size: 12px; color: var(--cart-muted); display: flex; gap: 8px; align-items: flex-start; line-height: 1.4; }
.cart-empty-note-list b { color: var(--cart-ink); }

/* ── Estado bloqueado/vacío de paneles Datos y Pago ── */
.cart-lock-card { background: linear-gradient(180deg,#FFFDF9 0%,#F7F1E8 100%); border: 1px solid var(--cart-line); border-radius: 22px; padding: 18px 16px; box-shadow: 0 10px 22px rgba(20,62,64,.05); }
.cart-lock-card h3 { margin: 0; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 18px; color: var(--cart-ink); }
.cart-lock-card p { margin: 7px 0 0; color: var(--cart-muted); font-size: 12.8px; line-height: 1.5; }
.cart-lock-steps { display: grid; gap: 8px; margin-top: 12px; }
.cart-lock-step { display: flex; gap: 10px; align-items: flex-start; background: #fff; border: 1px solid var(--cart-line); border-radius: 15px; padding: 10px 11px; }
.cart-lock-step-num { width: 22px; height: 22px; border-radius: 50%; background: #F5EBDD; color: #A87A45; font-size: 11px; font-weight: 800; display: grid; place-items: center; flex: 0 0 auto; }
.cart-lock-step .cart-lock-step-txt { font-size: 12px; color: var(--cart-muted); line-height: 1.45; }
.cart-lock-step b { color: var(--cart-ink); }

/* ── Pantalla reservas guardadas ── */
.cart-saved-wrap { display: grid; gap: 14px; }

/* Voucher */
.cart-voucher {
    background: #fff; border: 1px solid var(--cart-line);
    border-radius: 22px; padding: 13px;
    box-shadow: 0 8px 20px rgba(0,0,0,.045); margin-bottom: 12px;
}
.cart-voucher-top { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 9px; }
.cart-ok-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #eaf6ef; border: 1px solid #d5ecdf; color: #14815c;
    border-radius: 999px; padding: 6px 9px; font-size: 10px; font-weight: 900;
}
.cart-voucher h3 { margin: 7px 0 6px; font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 17px; line-height: 1.08; color: var(--cart-ink); }
.cart-code { font-size: 11px; color: #5b666c; line-height: 1.3; }
.cart-readonly-grid { display: grid; gap: 8px; margin-top: 10px; }
.cart-info-row { background: #fbf7f1; border: 1px solid #ece1d1; border-radius: 15px; padding: 9px 10px; }
.cart-info-row label { display: block; font-size: 8.8px; font-weight: 900; text-transform: uppercase; letter-spacing: .8px; color: #7b8389; margin: 0 0 5px; }
.cart-info-value { font-size: 12px; color: #23343a; font-weight: 700; line-height: 1.35; }
.cart-edit-grid { display: none; gap: 8px; margin-top: 10px; }
.cart-edit-mode .cart-readonly-grid { display: none; }
.cart-edit-mode .cart-edit-grid { display: grid; }
.cart-voucher-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.cart-btn-lite { height: 35px; border-radius: 12px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; cursor: pointer; background: #f7f2e9; border: 1px solid #eadfce; color: #7a5828; }

.cart-info-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; margin-top: 12px; }
.cart-info-mini { background: #fff; border: 1px solid var(--cart-line); border-radius: 15px; padding: 10px 11px; }
.cart-info-mini small { display: block; font-size: 10px; color: var(--cart-gold); font-weight: 700; letter-spacing: .6px; text-transform: uppercase; margin-bottom: 4px; }
.cart-info-mini span { display: block; font-size: 12px; color: var(--cart-ink); line-height: 1.4; font-weight: 600; }

.cart-ghost-history { background: #fff; border: 1px solid var(--cart-line); border-radius: 20px; padding: 14px; }

/* ── Resumen aside (desktop) ── */
.cart-aside {
    background: #fff; border: 1px solid var(--cart-line);
    border-radius: 22px; padding: 18px; box-shadow: var(--cart-shadow);
}
.cart-aside h3 { font-family: 'Hedvig Letters Serif', Georgia, serif; font-size: 18px; color: var(--cart-ink); margin: 0 0 14px; }
.cart-aside-list { display: grid; gap: 10px; padding-bottom: 12px; border-bottom: 1px solid var(--cart-line); }
.cart-aside-item { display: flex; align-items: flex-start; gap: 10px; }
.cart-aside-item-name { flex: 1; font-size: 12.5px; color: var(--cart-ink); line-height: 1.3; }
.cart-aside-item-sub { font-size: 11px; color: var(--cart-muted); display: block; margin-top: 2px; }
.cart-aside-item-price { font-size: 13px; font-weight: 700; color: var(--cart-ink); white-space: nowrap; }
.cart-aside-dl { margin-top: 12px; display: grid; gap: 8px; }
.cart-aside-row { display: flex; justify-content: space-between; font-size: 12.5px; }
.cart-aside-label { color: var(--cart-muted); }
.cart-aside-value { font-weight: 600; color: var(--cart-ink); }
.cart-aside-value.ok { color: var(--cart-ok); }
.cart-aside-total { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--cart-line); display: flex; justify-content: space-between; align-items: baseline; }
.cart-aside-total-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--cart-muted); font-weight: 700; }
.cart-aside-total-price { font-family: 'Instrument Serif', Georgia, serif; font-size: 28px; line-height: 1; color: var(--cart-green); }
.cart-aside-total-usd { font-size: 11px; color: var(--cart-muted); margin-left: 3px; }
.cart-aside-cta { width: 100%; justify-content: center; margin-top: 16px; height: 50px; font-size: 15px; }
.cart-aside-cta-help { font-size: 11px; color: var(--cart-muted); text-align: center; margin-top: 8px; line-height: 1.4; }

/* ── Flash ── */
.cart-flash { padding: 12px 16px; border-radius: 14px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }
.cart-flash.success { background: rgba(27,77,79,.08); border: 1px solid rgba(27,77,79,.2); color: var(--cart-ok); }
.cart-flash.error { background: rgba(200,110,87,.08); border: 1px solid rgba(200,110,87,.25); color: var(--cart-danger); }

/* ── Spacer sticky ── */
.cart-sticky-spacer { height: 84px; }
@media (max-width: 1023px) { .cart-panel.active { padding-bottom: 28px; } .cart-sticky-spacer { height: 112px; } }
/* Desktop: el sidebar ya tiene el CTA → ocultar la barra sticky inferior (evita solape) */
@media (min-width: 1024px) { .cart-sticky, .cart-sticky-spacer { display: none !important; } }

/* ── Badge Best seller en thumb + subtítulo (diseño Image #11) ── */
.cart-thumb { position: relative; }
.cart-thumb-badge {
    position: absolute; left: 6px; top: 6px;
    background: rgba(12,60,52,.94); color: #fff;
    border-radius: 999px; padding: 4px 7px;
    font-size: 8px; font-weight: 900; letter-spacing: .4px;
    text-transform: uppercase; line-height: 1;
}
.cart-sub { font-size: 11px; line-height: 1.25; color: var(--cart-muted); margin-top: 3px; }

/* ── compact grid when empty ── */
@media (max-width: 639px) {
    .cart-more-tours.compact {
        display: grid; grid-auto-flow: unset;
        grid-template-columns: 1fr; overflow-x: visible;
    }
    .cart-more-tours.compact .cart-more-card { min-width: 0; }
    .cart-two { grid-template-columns: 1fr; }
    /* "Agregar más tours" (con items): 1 card por vista en mobile (antes se veían cortadas) */
    .cart-more-tours:not(.compact) { grid-auto-columns: 86%; }
}

/* ── Modo nocturno DESACTIVADO (tema claro forzado; reactivar quitando "and (min-width:99999px)") ── */
@media (prefers-color-scheme: dark) and (min-width: 99999px) {
    :root {
        --cart-ink:   #e7eceb;
        --cart-muted: rgba(231,236,235,.62);
        --cart-line:  rgba(255,255,255,.10);
        --cart-soft:  #18403d;
        --cart-okbg:  rgba(27,77,79,.35);
        --cart-paper: #14302f;
        --cart-shadow: 0 12px 28px rgba(0,0,0,.5);
    }
    /* superficies claras → oscuras */
    .cart-tour-card, .cart-card, .cart-pax-card, .cart-more-card, .cart-aside,
    .cart-voucher, .cart-payment-tour-card, .cart-section-title-card,
    .cart-empty-note-card, .cart-info-mini, .cart-ghost-history, .cart-switch-btn,
    .cart-seg-btn, .cart-qty-btn, .cart-pay, .cart-empty-pill, .cart-lock-step { background-color: #14302f !important; }
    .cart-empty-state, .cart-lock-card { background: linear-gradient(180deg,#16332f 0%,#0e2527 100%) !important; }
    /* inputs / cajas internas */
    .cart-field-box, .cart-pax-shell, .cart-payment-final, .cart-policy-box,
    .cart-timing, .cart-mini-input, .cart-mini-select, .real-input, .real-select,
    textarea.real-input, .cart-field-help { background-color: #0e2527 !important; color: #e7eceb !important; }
    .cart-mini-input, .cart-mini-select, .real-input, .real-select { border-color: rgba(255,255,255,.14) !important; }
    /* seg num inactivo */
    .cart-seg-num { background: #18403d !important; color: #d8b079 !important; }
    /* textos secundarios que usan literales claros */
    .cart-seg-label, .cart-pax-head span, .cart-sub, .cart-summary-row span,
    .cart-payment-row span, .cart-mini-row span, .cart-price-tag { color: rgba(231,236,235,.6) !important; }
    .cart-price-before { color: rgba(231,236,235,.45) !important; }
    /* CTA / sticky se mantienen verdes (acento), solo el contenedor sticky */
    .cart-sticky-inner { background: rgba(20,48,47,.97) !important; border-color: rgba(255,255,255,.10) !important; }
    /* precios/total/steppers legibles en oscuro (antes quedaban verde oscuro invisible) */
    .cart-total-price, .cart-total-label, .cart-qty-value, .cart-meta-link, .cart-head h2 { color: #e7eceb !important; }
    .cart-price-now, .cart-price-regular, .cart-aside-total-price,
    .cart-payment-final b, .cart-summary-row.cart-total-row b { color: #f0e6d2 !important; }
    .cart-qty-btn { color: #e7eceb !important; border-color: rgba(255,255,255,.22) !important; }
}
</style>
@endpush

@section('content')

{{-- ── HERO ── --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19215.jpg') }}" alt="" class="w-full h-full object-cover" loading="eager">
        <div class="absolute inset-0 bg-teal-900/65"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/80">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">{{ __('ui.home') }}</a> &rsaquo; <span>{{ __('ui.cart_title') }}</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-14 md:py-18 text-center">
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl leading-tight">{{ __('ui.cart_title') }}</h1>
        <p class="mt-3 mx-auto max-w-xl text-sm text-white/85">
            {{ __('ui.cart_subtitle') }}
        </p>
    </div>
</section>

{{-- ── CONTENIDO PRINCIPAL ── --}}
<section class="bg-cream-100 pb-10 lg:pb-16">
    <div class="container mx-auto px-5 lg:px-10">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="cart-flash success mt-6" role="alert">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="cart-flash error mt-6" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->has('general'))
            <div class="cart-flash error mt-6" role="alert">{{ $errors->first('general') }}</div>
        @endif
        @if ($errors->any() && !$errors->has('general'))
            <div class="cart-flash error mt-6" role="alert">
                <strong>{{ __('ui.fix_errors') }}</strong>
                <ul class="mt-1 list-disc list-inside">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Switcher Carrito / Reservas --}}
        <div class="cart-switcher" role="tablist" aria-label="{{ __('ui.views_label') }}">
            <button class="cart-switch-btn active"
                    role="tab" aria-selected="true"
                    aria-controls="screen-carrito"
                    data-screen-btn="carrito">
                {{ __('ui.cart') }}
            </button>
            <button class="cart-switch-btn"
                    role="tab" aria-selected="false"
                    aria-controls="screen-reservas"
                    data-screen-btn="reservasGuardadas">
                {{ __('ui.view_my_bookings') }}
            </button>
        </div>

        {{-- ══════════════════════════════════════
             PANTALLA: CARRITO
        ══════════════════════════════════════ --}}
        <div id="screen-carrito" class="cart-screen active" data-screen="carrito" role="tabpanel">

            {{-- Stepper 1·2·3 --}}
            <nav class="cart-segment" aria-label="{{ __('ui.checkout_steps_label') }}">
                <button class="cart-seg-btn active" data-step-btn="reservas" type="button" aria-current="step">
                    <div class="cart-seg-num">1</div>
                    <span class="cart-seg-label">{{ __('ui.step_bookings') }}</span>
                </button>
                <button class="cart-seg-btn" data-step-btn="datos" type="button">
                    <div class="cart-seg-num">2</div>
                    <span class="cart-seg-label">{{ __('ui.step_data') }}</span>
                </button>
                <button class="cart-seg-btn" data-step-btn="pago" type="button">
                    <div class="cart-seg-num">3</div>
                    <span class="cart-seg-label">{{ __('ui.step_payment') }}</span>
                </button>
            </nav>

            {{-- ────────────────────────────────────────
                 PANEL 1 — RESERVAS
            ──────────────────────────────────────── --}}
            <div class="cart-panel active" data-step="reservas">

                <div class="lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-8 lg:items-start">

                    {{-- Columna principal --}}
                    <div>
                        <div class="cart-head">
                            <h2>{{ __('ui.your_bookings') }}</h2>
                            <span class="cart-meta-link">
                                <span data-cart-count>{{ $items->count() }}</span> tour{{ $items->count() !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        @if ($items->isEmpty())
                            <div class="cart-empty-state" id="cart-empty-state">
                                <div class="cart-empty-head">
                                    <div class="cart-empty-icon" aria-hidden="true">🛒</div>
                                    <div>
                                        <h3>{{ __('ui.cart_empty_title') }}</h3>
                                        <p>{{ __('ui.cart_empty_desc') }}</p>
                                    </div>
                                </div>
                                <div class="cart-empty-pills" aria-hidden="true">
                                    <span class="cart-empty-pill">{{ __('ui.quick_booking') }}</span>
                                    <span class="cart-empty-pill">{{ __('ui.secure_payment') }}</span>
                                    <span class="cart-empty-pill">{{ __('ui.instant_confirmation') }}</span>
                                </div>
                                <div class="cart-empty-actions">
                                    <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">{{ __('ui.explore_tours') }}</a>
                                    <button type="button" class="cart-btn-outline" data-screen-btn="reservasGuardadas">{{ __('ui.view_my_bookings') }}</button>
                                </div>
                            </div>

                            <div class="cart-empty-note-card">
                                <h4>{{ __('ui.cart_steps_title') }}</h4>
                                <ul class="cart-empty-note-list">
                                    <li><span aria-hidden="true">•</span><span><b>{{ __('ui.step_bookings') }} 1:</b> {{ __('ui.cart_step1_text') }}</span></li>
                                    <li><span aria-hidden="true">•</span><span><b>{{ __('ui.step_data') }} 2:</b> {{ __('ui.cart_step2_text') }}</span></li>
                                    <li><span aria-hidden="true">•</span><span><b>{{ __('ui.step_payment') }} 3:</b> {{ __('ui.cart_step3_text') }}</span></li>
                                </ul>
                            </div>

                        @else
                            <div class="cart-list" id="cart-items-list">
                                @foreach ($items as $item)
                                    @php
                                        $cover   = $item['cover_image'] ?? null;
                                        // ImagePath resuelve el prefijo /storage correctamente
                                        // (asset() omitía /storage y las portadas subidas daban 404).
                                        $imgSrc  = \App\Support\ImagePath::url($cover)
                                            ?: asset('assets/banners/Rectangle 19210.jpg');
                                        $qty         = (int) ($item['adults'] + $item['children']);
                                        $beforeUnit  = $item['price_before'] ?? null;
                                        $hasDiscount = $beforeUnit && (float) $beforeUnit > (float) $item['unit_price'];
                                        $beforeTotal = $hasDiscount ? (float) $beforeUnit * $qty : 0;
                                        $savedTotal  = $hasDiscount ? $beforeTotal - (float) $item['subtotal'] : 0;
                                        $isBest      = (bool) ($item['is_featured'] ?? false) || $hasDiscount;
                                    @endphp

                                    <article class="cart-tour-card"
                                             data-cart-item
                                             data-row-id="{{ $item['row_id'] }}"
                                             data-unit-price="{{ $item['unit_price'] }}"
                                             data-before-unit="{{ $beforeUnit ?? '' }}"
                                             data-discount="{{ $hasDiscount ? '1' : '0' }}"
                                             data-adults="{{ $item['adults'] }}"
                                             data-children="{{ $item['children'] }}">

                                        <div class="cart-chip-row">
                                            <div class="cart-chip">{{ __('ui.tour_added') }}</div>
                                            @if ($hasDiscount)
                                                <div class="cart-chip promo">{{ __('ui.special_offer_active') }}</div>
                                            @else
                                                <div class="cart-chip">{{ __('ui.regular_price') }}</div>
                                            @endif
                                        </div>

                                        <div class="cart-tour-top">
                                            <div class="cart-thumb">
                                                <img src="{{ $imgSrc }}"
                                                     alt="{{ $item['title_snapshot'] }}"
                                                     width="82" height="82"
                                                     loading="lazy">
                                                @if ($isBest)
                                                    <span class="cart-thumb-badge">Best seller</span>
                                                @endif
                                            </div>

                                            <div class="cart-tour-info">
                                                <h3>{{ $item['title_snapshot'] }}</h3>
                                                <div class="cart-meta-line" data-item-meta>
                                                    {{ $item['duration'] ?? '' }}
                                                    · <span data-adults-label>{{ $item['adults'] }}</span> adulto{{ $item['adults'] !== 1 ? 's' : '' }}
                                                    @if ($item['children'] > 0)
                                                        · <span data-children-label>{{ $item['children'] }}</span> niño{{ $item['children'] !== 1 ? 's' : '' }}
                                                    @endif
                                                </div>
                                                <div class="cart-sub">{{ $hasDiscount ? __('ui.booking_with_discount') : __('ui.regular_price_no_discount') }}</div>
                                            </div>

                                            <div class="cart-price-box">
                                                @if ($hasDiscount)
                                                    <div class="cart-price-tag">{{ __('ui.price_before') }}</div>
                                                    <div class="cart-price-before" data-item-before>US${{ number_format($beforeTotal, 0) }}</div>
                                                    <div class="cart-price-tag">{{ __('ui.price_now') }}</div>
                                                    <div class="cart-price-now" data-item-now>US${{ number_format($item['subtotal'], 0) }}</div>
                                                    <div class="cart-price-saved" data-item-saved>{{ __('ui.saving') }} US${{ number_format($savedTotal, 0) }}</div>
                                                @else
                                                    <div class="cart-price-tag">{{ __('ui.regular_price') }}</div>
                                                    <div class="cart-price-regular" data-item-regular>US${{ number_format($item['subtotal'], 0) }}</div>
                                                @endif
                                                <button type="button"
                                                        class="cart-remove-btn"
                                                        data-remove-btn
                                                        aria-label="{{ __('ui.remove') }} {{ $item['title_snapshot'] }}">
                                                    {{ __('ui.remove') }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="cart-tour-grid">
                                            <div class="cart-field-box">
                                                <label for="date-{{ $item['row_id'] }}">{{ __('ui.tour_date') }}</label>
                                                <input type="date"
                                                       id="date-{{ $item['row_id'] }}"
                                                       class="cart-mini-input"
                                                       value="{{ $item['travel_date'] }}"
                                                       data-date-input
                                                       readonly
                                                       title="{{ __('ui.tour_date') }}">
                                            </div>
                                            <div class="cart-field-box">
                                                <label for="lang-{{ $item['row_id'] }}">{{ __('ui.tour_language_label') }}</label>
                                                <select id="lang-{{ $item['row_id'] }}"
                                                        class="cart-mini-select"
                                                        data-lang-select>
                                                    @foreach (['Español', 'English', 'Português'] as $lang)
                                                        <option value="{{ $lang }}" @selected($item['language'] === $lang)>{{ $lang }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="cart-pax-shell">
                                            <div class="cart-pax-head">
                                                <strong>{{ __('ui.passengers') }}</strong>
                                                <span>{{ __('ui.adults_children_same_price') }}</span>
                                            </div>
                                            <div class="cart-pax-grid">
                                                <div class="cart-pax-card">
                                                    <label>{{ __('ui.adults') }}</label>
                                                    <div class="cart-qty-control">
                                                        <button type="button" class="cart-qty-btn"
                                                                data-qty-btn="minus" data-target="adults"
                                                                aria-label="{{ __('ui.remove_adult') }}">−</button>
                                                        <span class="cart-qty-value" data-adults-value>{{ $item['adults'] }}</span>
                                                        <button type="button" class="cart-qty-btn"
                                                                data-qty-btn="plus" data-target="adults"
                                                                aria-label="{{ __('ui.add_adult') }}">+</button>
                                                    </div>
                                                </div>
                                                <div class="cart-pax-card">
                                                    <label>{{ __('ui.children') }}</label>
                                                    <div class="cart-qty-control">
                                                        <button type="button" class="cart-qty-btn"
                                                                data-qty-btn="minus" data-target="children"
                                                                aria-label="{{ __('ui.remove_child') }}">−</button>
                                                        <span class="cart-qty-value" data-children-value>{{ $item['children'] }}</span>
                                                        <button type="button" class="cart-qty-btn"
                                                                data-qty-btn="plus" data-target="children"
                                                                aria-label="{{ __('ui.add_child') }}">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </article>
                                @endforeach
                            </div>

                            {{-- Cupón --}}
                            <div class="cart-coupon-card mt-4">
                                <p class="cart-coupon-text">
                                    @if ($couponCode)
                                        {{ __('ui.coupon_applied_message', ['code' => $couponCode]) }}
                                    @else
                                        {{ __('ui.coupon_prompt') }}
                                    @endif
                                </p>
                                <form method="POST"
                                      action="{{ route('cart.coupon', ['locale' => $locale]) }}"
                                      class="cart-coupon-form">
                                    @csrf
                                    <input type="text"
                                           name="code"
                                           placeholder="{{ __('checkout.coupon_code') }}"
                                           value="{{ $couponCode ?? '' }}"
                                           class="cart-coupon-input"
                                           aria-label="{{ __('checkout.coupon_code') }}">
                                    <button type="submit" class="cart-coupon-submit">{{ __('checkout.apply_coupon') }}</button>
                                </form>
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                                   class="text-sm font-semibold text-teal-700 hover:text-orange-500 transition">
                                    &lsaquo; {{ __('ui.keep_exploring') }}
                                </a>
                                <form method="POST" action="{{ route('cart.clear', ['locale' => $locale]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs text-teal-800/50 hover:text-state-error underline"
                                            onclick="return confirm('¿Vaciar el carrito?')">
                                        {{ __('ui.clear_cart') }}
                                    </button>
                                </form>
                            </div>

                        @endif

                        {{-- Venta cruzada --}}
                        @if ($related->isNotEmpty())
                            <div class="cart-section-title-card">
                                <h3>{{ __('ui.add_more_tours') }}</h3>
                                <span>{{ __('ui.recommended') }}</span>
                            </div>
                            <div class="cart-more-tours {{ $items->isEmpty() ? 'compact' : '' }}">
                                @foreach ($related as $relTour)
                                    @php $relImg = $relTour->cover_url ?? asset('assets/banners/Rectangle 19210.jpg'); @endphp
                                    <div class="cart-more-card">
                                        <img src="{{ $relImg }}" alt="{{ $relTour->title }}" width="160" height="96" loading="lazy"
                                             onerror="this.onerror=null;this.src='{{ asset('assets/banners/Rectangle 19210.jpg') }}';">
                                        <div class="cart-more-body">
                                            @if ($relTour->region)
                                                <div class="cart-more-tag">{{ $relTour->region->name_es }}</div>
                                            @endif
                                            <h4>{{ $relTour->title }}</h4>
                                            <div class="cart-more-price">US${{ number_format($relTour->price, 0) }}</div>
                                            <div class="mt-auto">
                                                <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $relTour->slug]) }}"
                                                   class="cart-add-btn" data-add-tour>
                                                    {{ __('ui.add_tour') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    </div>{{-- /columna principal --}}

                    {{-- Aside resumen (desktop, solo con items) --}}
                    @if ($items->isNotEmpty())
                        <aside class="cart-aside hidden lg:block lg:sticky lg:top-24 mt-6 lg:mt-0" aria-label="{{ __('checkout.order_summary') }}">
                            <h3>{{ __('checkout.order_summary') }}</h3>
                            <div class="cart-aside-list" id="aside-list">
                                @foreach ($items as $item)
                                    <div class="cart-aside-item"
                                         data-aside-item
                                         data-row-id="{{ $item['row_id'] }}"
                                         data-unit="{{ $item['unit_price'] }}"
                                         data-adults="{{ $item['adults'] }}"
                                         data-children="{{ $item['children'] }}">
                                        <div class="cart-aside-item-name">
                                            {{ \Illuminate\Support\Str::limit($item['title_snapshot'], 38) }}
                                            <span class="cart-aside-item-sub" data-aside-pax>x {{ $item['quantity'] }} {{ __('ui.persons') }}</span>
                                        </div>
                                        <span class="cart-aside-item-price" data-aside-price>US${{ number_format($item['subtotal'], 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @php
                                $asBefore = 0; $asNow = 0;
                                foreach ($items as $it) {
                                    $q   = (int) ($it['adults'] + $it['children']);
                                    $now = (float) $it['unit_price'];
                                    $bu  = $it['price_before'] ?? null;
                                    $asBefore += (($bu && (float) $bu > $now) ? (float) $bu : $now) * $q;
                                    $asNow    += $now * $q;
                                }
                                $asPromo = max(0, $asBefore - $asNow);
                            @endphp
                            <dl class="cart-aside-dl">
                                @if ($asPromo > 0)
                                    <div class="cart-aside-row">
                                        <dt class="cart-aside-label">{{ __('ui.regular_price') }}</dt>
                                        <dd class="cart-aside-value" data-aside-before><s>US${{ number_format($asBefore, 0) }}</s></dd>
                                    </div>
                                    <div class="cart-aside-row">
                                        <dt class="cart-aside-label">{{ __('ui.promo_discount') }}</dt>
                                        <dd class="cart-aside-value ok" data-aside-promo>−US${{ number_format($asPromo, 0) }}</dd>
                                    </div>
                                @endif
                                <div class="cart-aside-row">
                                    <dt class="cart-aside-label">{{ __('checkout.subtotal') }} ({{ $items->count() }} {{ $items->count() === 1 ? 'tour' : 'tours' }})</dt>
                                    <dd class="cart-aside-value" data-aside-subtotal>US${{ number_format($subtotal, 0) }}</dd>
                                </div>
                                @if ($discount > 0)
                                    <div class="cart-aside-row">
                                        <dt class="cart-aside-label">{{ __('checkout.coupon_code') }}{{ $couponCode ? ' (' . $couponCode . ')' : '' }}</dt>
                                        <dd class="cart-aside-value ok" data-aside-discount>−US${{ number_format($discount, 0) }}</dd>
                                    </div>
                                @endif
                            </dl>
                            <div class="cart-aside-total">
                                <span class="cart-aside-total-label">{{ __('checkout.total') }}</span>
                                <span>
                                    <span class="cart-aside-total-price" data-aside-total>US${{ number_format($total, 0) }}</span>
                                    <span class="cart-aside-total-usd">USD</span>
                                </span>
                            </div>
                            <button type="button" class="cart-step-btn primary cart-aside-cta" data-step-next>
                                {{ __('ui.continue_to_data_payment') }} <span aria-hidden="true">→</span>
                            </button>
                            <p class="cart-aside-cta-help">{{ __('ui.checkout_help_text') }}</p>
                        </aside>
                    @endif

                </div>{{-- /lg:grid --}}
                @if ($items->isNotEmpty())
                    <div class="cart-step-nav">
                        <button type="button" class="cart-step-btn primary nav-next" data-step-next>{{ __('ui.continue_to_data_payment') }} <span aria-hidden="true">→</span></button>
                    </div>
                @endif
            </div>{{-- /panel reservas --}}

            {{-- ────────────────────────────────────────
                 PANEL 2 — DATOS
                 Envuelto en el form real de checkout
            ──────────────────────────────────────── --}}
            <form id="payment-form"
                  method="POST"
                  action="{{ route('checkout.process', ['locale' => $locale]) }}"
                  novalidate>
                @csrf
                <input type="hidden" name="payment_timing" id="payment_timing_hidden" value="later">

                {{-- ─── Panel 2 ─── --}}
                <div class="cart-panel" data-step="datos">
                    <div class="cart-head">
                        <h2>{{ __('ui.booking_data_title') }}</h2>
                        <span class="cart-meta-link">{{ __('ui.required') }}</span>
                    </div>

                    @if ($items->isEmpty())
                        <div class="cart-lock-card">
                            <div class="cart-empty-head" style="margin-bottom:0">
                                <div class="cart-empty-icon" aria-hidden="true">📝</div>
                                <div>
                                    <h3>{{ __('ui.add_tour_first') }}</h3>
                                    <p>{{ __('ui.lock_datos_desc') }}</p>
                                </div>
                            </div>
                            <div class="cart-lock-steps">
                                <div class="cart-lock-step"><div class="cart-lock-step-num">1</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step1_title') }}</b><br>{{ __('ui.lock_step1_desc') }}</div></div>
                                <div class="cart-lock-step"><div class="cart-lock-step-num">2</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step2_title') }}</b><br>{{ __('ui.lock_step2_desc') }}</div></div>
                                <div class="cart-lock-step"><div class="cart-lock-step-num">3</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step3_title') }}</b><br>{{ __('ui.lock_step3_desc') }}</div></div>
                            </div>
                            <div class="cart-empty-actions">
                                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">{{ __('ui.explore_tours') }}</a>
                            </div>
                        </div>
                    @else
                    <div class="cart-card">
                        <div class="cart-card-pad">
                            <div class="cart-form-grid">

                                <div class="cart-field">
                                    <label for="customer_name">{{ __('customer.name') }} <span class="req">*</span></label>
                                    <input type="text"
                                           id="customer_name"
                                           name="customer_name"
                                           value="{{ old('customer_name') }}"
                                           placeholder="Ej. María García López"
                                           autocomplete="name"
                                           class="cart-real-input @error('customer_name') border-red-500 @enderror">
                                    @error('customer_name')<p class="text-xs mt-1" style="color:var(--cart-danger)">{{ $message }}</p>@enderror
                                </div>

                                <div class="cart-two">
                                    <div class="cart-field">
                                        <label for="phone_local">{{ __('ui.phone_number') }} <span class="req">*</span></label>
                                        <div style="display:flex; gap:8px;">
                                            <select id="phone_prefix" class="cart-real-select" style="max-width:128px; flex:0 0 auto;" aria-label="{{ __('ui.country_code') }}">
                                                <option value="+51" selected>🇵🇪 +51</option>
                                                <option value="+1">🇺🇸 +1</option>
                                                <option value="+1">🇨🇦 +1</option>
                                                <option value="+44">🇬🇧 +44</option>
                                                <option value="+34">🇪🇸 +34</option>
                                                <option value="+52">🇲🇽 +52</option>
                                                <option value="+57">🇨🇴 +57</option>
                                                <option value="+56">🇨🇱 +56</option>
                                                <option value="+54">🇦🇷 +54</option>
                                                <option value="+55">🇧🇷 +55</option>
                                                <option value="+593">🇪🇨 +593</option>
                                                <option value="+591">🇧🇴 +591</option>
                                                <option value="+61">🇦🇺 +61</option>
                                                <option value="+49">🇩🇪 +49</option>
                                                <option value="+33">🇫🇷 +33</option>
                                                <option value="+39">🇮🇹 +39</option>
                                            </select>
                                            <input type="tel" id="phone_local" placeholder="999 999 999" autocomplete="tel-national"
                                                   class="cart-real-input @error('customer_phone') border-red-500 @enderror" style="flex:1; min-width:0;">
                                        </div>
                                        <input type="hidden" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}">
                                        @error('customer_phone')<p class="text-xs mt-1" style="color:var(--cart-danger)">{{ $message }}</p>@enderror
                                        <script>
                                        (function(){
                                            var pfx=document.getElementById('phone_prefix'), loc=document.getElementById('phone_local'), hid=document.getElementById('customer_phone');
                                            if(!pfx||!loc||!hid) return;
                                            function sync(){ var d=loc.value.replace(/\D/g,''); hid.value = d ? (pfx.value + d) : ''; }
                                            pfx.addEventListener('change', sync); loc.addEventListener('input', sync);
                                        })();
                                        </script>
                                    </div>
                                    <div class="cart-field">
                                        <label for="customer_email">{{ __('customer.email') }} <span class="req">*</span></label>
                                        <input type="email"
                                               id="customer_email"
                                               name="customer_email"
                                               value="{{ old('customer_email') }}"
                                               placeholder="correo@ejemplo.com"
                                               autocomplete="email"
                                               class="cart-real-input @error('customer_email') border-red-500 @enderror">
                                        @error('customer_email')<p class="text-xs mt-1" style="color:var(--cart-danger)">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="cart-field">
                                    <label for="pickup_point">{{ __('ui.pickup_hotel_label') }} <span class="text-teal-800/40 font-normal normal-case">({{ __('ui.optional') }})</span></label>
                                    @php
                                        // Punto de recojo. Si hay Google Maps API Key configurada se usa
                                        // autocompletado de Google Places (el viajero busca cualquier lugar;
                                        // consume API por búsqueda). Si no hay key, cae a una lista curada
                                        // gratis vía <datalist> nativo. Siempre es texto libre.
                                        $pickupMapsKey = \App\Models\Setting::get('google_maps_api_key') ?: config('services.google.maps_api_key');
                                        // [TEMPORAL 2026-07-21] A pedido del cliente: desactivar el buscador de
                                        // Google Maps en el recojo y dejar el campo como texto libre. Para
                                        // reactivar el autocompletado de Google, borra la línea de abajo.
                                        $pickupMapsKey = null;
                                        // Zonas de recogida del admin — para limitar el autocompletado por radio
                                        $pickupZonesRaw = \App\Models\Setting::get('pickup_zones');
                                        $pickupZonesRaw = is_string($pickupZonesRaw) ? (json_decode($pickupZonesRaw, true) ?: []) : (is_array($pickupZonesRaw) ? $pickupZonesRaw : []);
                                        $pickupZones = collect($pickupZonesRaw)
                                            ->filter(fn ($z) => is_array($z) && isset($z['lat'], $z['lng']) && is_numeric($z['lat']) && is_numeric($z['lng']))
                                            ->map(fn ($z) => ['lat' => (float) $z['lat'], 'lng' => (float) $z['lng'], 'radius_km' => (float) ($z['radius_km'] ?? 2), 'label' => (string) ($z['label'] ?? '')])
                                            ->values()->all();
                                        $pickupSuggestions = [
                                            // Lima — zonas
                                            'Miraflores, Lima', 'San Isidro, Lima', 'Barranco, Lima',
                                            'Santiago de Surco, Lima', 'San Borja, Lima', 'Magdalena del Mar, Lima',
                                            'Aeropuerto Jorge Chávez (Callao)', 'Centro Histórico de Lima',
                                            // Lima — hoteles frecuentes
                                            'JW Marriott Hotel Lima (Miraflores)', 'Belmond Miraflores Park',
                                            'Hilton Lima Miraflores', 'Casa Andina Premium Miraflores',
                                            'Country Club Lima Hotel (San Isidro)', 'The Westin Lima (San Isidro)',
                                            'Novotel Lima', 'Ibis Larco Miraflores', 'Meliá Lima', 'Hotel B (Barranco)',
                                            // Ica / Paracas / Huacachina
                                            'Huacachina, Ica', 'Hotel Mossone (Huacachina)', 'El Huacachinero (Huacachina)',
                                            'Paracas (El Chaco)', 'Hotel Paracas, a Luxury Collection Resort', 'La Hacienda Bahía Paracas',
                                            // Cusco
                                            'Plaza de Armas de Cusco', 'Belmond Hotel Monasterio (Cusco)',
                                            'Palacio del Inka (Cusco)', 'JW Marriott El Convento Cusco',
                                            'Casa Andina Premium Cusco', 'Tierra Viva Cusco Plaza',
                                        ];
                                    @endphp
                                    <input type="text"
                                           id="pickup_point"
                                           name="pickup_point"
                                           @if (blank($pickupMapsKey)) list="pickup_options" @endif
                                           value="{{ old('pickup_point') }}"
                                           placeholder="{{ __('ui.pickup_placeholder') }}"
                                           autocomplete="off"
                                           class="cart-real-input @error('pickup_point') border-red-500 @enderror">
                                    <div id="pickup_zone_warn" role="alert" style="display:none;margin-top:6px;color:#c0392b;font-size:12px;font-weight:600;line-height:1.4;"></div>
                                    @if (blank($pickupMapsKey))
                                    <datalist id="pickup_options">
                                        @foreach ($pickupSuggestions as $opt)
                                            <option value="{{ $opt }}"></option>
                                        @endforeach
                                    </datalist>
                                    @else
                                    {{-- Autocompletado de Google Places LIMITADO a las zonas de recogida del admin (sesga por bounds + valida por radio/distancia). Aislado y en try/catch. --}}
                                    <script>
                                    (function () {
                                        var ZONES = @json($pickupZones);
                                        var OUT_MSG = @json(__('ui.pickup_out_of_zone'));

                                        function haversineKm(aLat, aLng, bLat, bLng) {
                                            var R = 6371, toR = Math.PI / 180;
                                            var dLat = (bLat - aLat) * toR, dLng = (bLng - aLng) * toR;
                                            var s = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                                                    Math.cos(aLat * toR) * Math.cos(bLat * toR) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                                            return 2 * R * Math.asin(Math.sqrt(s));
                                        }
                                        function toggleWarn(show) {
                                            var w = document.getElementById('pickup_zone_warn');
                                            if (! w) return;
                                            if (show) {
                                                var labels = ZONES.map(function (z) { return z.label; }).filter(Boolean).join(', ');
                                                w.textContent = OUT_MSG + (labels ? ' (' + labels + ')' : '');
                                                w.style.display = 'block';
                                            } else { w.style.display = 'none'; }
                                        }
                                        function initCheckoutPickup() {
                                            var el = document.getElementById('pickup_point');
                                            if (! el || el.dataset.gac || ! (window.google && google.maps && google.maps.places)) return;
                                            el.dataset.gac = '1';
                                            try {
                                                var ac = new google.maps.places.Autocomplete(el, { fields: ['name', 'formatted_address', 'geometry'], componentRestrictions: { country: 'pe' } });
                                                if (ZONES.length) {
                                                    var b = new google.maps.LatLngBounds();
                                                    ZONES.forEach(function (z) {
                                                        var dLat = z.radius_km / 111;
                                                        var dLng = z.radius_km / (111 * Math.cos(z.lat * Math.PI / 180));
                                                        b.extend({ lat: z.lat + dLat, lng: z.lng + dLng });
                                                        b.extend({ lat: z.lat - dLat, lng: z.lng - dLng });
                                                    });
                                                    ac.setBounds(b);
                                                    ac.setOptions({ strictBounds: true });
                                                }
                                                ac.addListener('place_changed', function () {
                                                    try {
                                                        var p = ac.getPlace();
                                                        var loc = p && p.geometry && p.geometry.location;
                                                        if (ZONES.length && loc) {
                                                            var plat = loc.lat(), plng = loc.lng();
                                                            var inZone = ZONES.some(function (z) { return haversineKm(plat, plng, z.lat, z.lng) <= z.radius_km; });
                                                            if (! inZone) { toggleWarn(true); el.value = ''; el.focus(); return; }
                                                        }
                                                        toggleWarn(false);
                                                        var name = p && p.name ? p.name : '';
                                                        var addr = p && p.formatted_address ? p.formatted_address : '';
                                                        var txt = name && addr ? (name + ' — ' + addr) : (name || addr);
                                                        if (txt) { el.value = txt; el.dispatchEvent(new Event('change', { bubbles: true })); }
                                                    } catch (e) {}
                                                });
                                            } catch (e) {}
                                        }
                                        window.__lvtCheckoutPickupReady = initCheckoutPickup;
                                        if (window.google && google.maps && google.maps.places) { initCheckoutPickup(); return; }
                                        var existing = document.getElementById('lvt-gmaps-sdk');
                                        if (existing) { existing.addEventListener('load', initCheckoutPickup); return; }
                                        var s = document.createElement('script');
                                        s.id = 'lvt-gmaps-sdk';
                                        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $pickupMapsKey }}&libraries=places&loading=async&callback=__lvtCheckoutPickupReady';
                                        s.async = true; s.defer = true;
                                        document.head.appendChild(s);
                                    })();
                                    </script>
                                    @endif
                                    <div class="cart-field-help">
                                        {{ __('ui.pickup_help') }}
                                    </div>
                                </div>

                                <div class="cart-field">
                                    <label for="tour_language">{{ __('ui.tour_language_label') }}</label>
                                    <select id="tour_language" name="tour_language" class="cart-real-select">
                                        <option value="en" @selected(old('tour_language', 'en') === 'en')>English</option>
                                        <option value="es" @selected(old('tour_language') === 'es')>Español</option>
                                    </select>
                                </div>

                                <div class="cart-field">
                                    <label for="travel_date">{{ __('checkout.travel_date') }}</label>
                                    <input type="date"
                                           id="travel_date"
                                           name="travel_date"
                                           value="{{ old('travel_date', $firstTravelDate) }}"
                                           min="{{ now()->addDay()->format('Y-m-d') }}"
                                           readonly
                                           class="cart-real-input @error('travel_date') border-red-500 @enderror"
                                           style="background:#f1ece3; cursor:not-allowed;">
                                    <div class="cart-field-help">{{ __('ui.travel_date_help') }}</div>
                                    @error('travel_date')<p class="text-xs mt-1" style="color:var(--cart-danger)">{{ $message }}</p>@enderror
                                </div>

                                <div class="cart-field">
                                    <label for="notes">{{ __('ui.notes_label') }}</label>
                                    <textarea id="notes" name="notes" rows="3"
                                              placeholder="{{ __('ui.notes_placeholder') }}"
                                              class="cart-real-input">{{ old('notes') }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="cart-step-nav">
                        <button type="button" class="cart-step-btn" data-step-prev><span aria-hidden="true">←</span> {{ __('ui.back') }}</button>
                        <button type="button" class="cart-step-btn primary nav-next" data-step-next>{{ __('ui.continue_to_payment') }} <span aria-hidden="true">→</span></button>
                    </div>
                    @endif

                </div>{{-- /panel datos --}}

                {{-- ─── Panel 3: PAGO ─── --}}
                <div class="cart-panel" data-step="pago">
                    <div class="cart-head">
                        <h2>{{ __('checkout.title') }}</h2>
                        <span class="cart-meta-link">USD</span>
                    </div>

                    @if ($items->isEmpty())
                        <div class="cart-lock-card">
                            <div class="cart-empty-head" style="margin-bottom:0">
                                <div class="cart-empty-icon" aria-hidden="true">💳</div>
                                <div>
                                    <h3>{{ __('ui.add_tour_first') }}</h3>
                                    <p>{{ __('ui.lock_pago_desc') }}</p>
                                </div>
                            </div>
                            <div class="cart-lock-steps">
                                <div class="cart-lock-step"><div class="cart-lock-step-num">1</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step1_title') }}</b><br>{{ __('ui.lock_step1_desc') }}</div></div>
                                <div class="cart-lock-step"><div class="cart-lock-step-num">2</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step2_title') }}</b><br>{{ __('ui.lock_step2_desc') }}</div></div>
                                <div class="cart-lock-step"><div class="cart-lock-step-num">3</div><div class="cart-lock-step-txt"><b>{{ __('ui.lock_step3_title') }}</b><br>{{ __('ui.lock_step3_desc') }}</div></div>
                            </div>
                            <div class="cart-empty-actions">
                                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">{{ __('ui.explore_tours') }}</a>
                            </div>
                        </div>
                    @else

                    {{-- Lista numerada de tours a pagar --}}
                    @if ($items->isNotEmpty())
                        <div class="cart-payment-tour-list">
                            @foreach ($items as $idx => $item)
                                @php
                                    $paxPago = $item['adults'] + $item['children'];
                                    $beforeUnitPago   = $item['price_before'] ?? null;
                                    $hasDiscount      = $beforeUnitPago && (float) $beforeUnitPago > (float) $item['unit_price'];
                                    $beforeTotalPago  = $hasDiscount ? (float) $beforeUnitPago * $paxPago : 0;
                                    $discountTotalPago = $hasDiscount ? $beforeTotalPago - (float) $item['subtotal'] : 0;
                                @endphp
                                <div class="cart-payment-tour-card">
                                    <div class="cart-payment-head">
                                        <div class="cart-payment-head-left">
                                            <div class="cart-payment-order">
                                                <span class="cart-payment-order-num">{{ $idx + 1 }}</span>
                                                {{ __('ui.booking_number') }} {{ $idx + 1 }}
                                            </div>
                                            <h4>{{ $item['title_snapshot'] }}</h4>
                                        </div>
                                        @if ($hasDiscount)
                                            <div class="cart-payment-badge promo">{{ __('ui.discount_applied') }}</div>
                                        @else
                                            <div class="cart-payment-badge">{{ __('ui.regular_price') }}</div>
                                        @endif
                                    </div>
                                    <div class="cart-payment-body">
                                        <div class="cart-payment-row">
                                            <span>{{ __('ui.passengers') }}</span>
                                            <b>{{ $item['adults'] }} adulto{{ $item['adults'] !== 1 ? 's' : '' }}
                                               @if ($item['children'] > 0) · {{ $item['children'] }} niño{{ $item['children'] !== 1 ? 's' : '' }} @endif
                                            </b>
                                        </div>
                                        @if ($hasDiscount)
                                            <div class="cart-payment-row">
                                                <span>{{ __('ui.price_before_label') }}</span>
                                                <b class="strike">US${{ number_format($beforeTotalPago, 0) }}</b>
                                            </div>
                                            <div class="cart-payment-row">
                                                <span>{{ __('ui.discount_applied') }}</span>
                                                <b class="green">−US${{ number_format($discountTotalPago, 0) }}</b>
                                            </div>
                                        @else
                                            <div class="cart-payment-row">
                                                <span>{{ __('ui.regular_price') }}</span>
                                                <b>US${{ number_format($item['subtotal'], 0) }}</b>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="cart-payment-final">
                                        <span>{{ $hasDiscount ? __('ui.you_will_pay_now') : __('ui.you_will_pay') }}</span>
                                        <b>US${{ number_format($item['subtotal'], 0) }}</b>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Total final --}}
                    <div class="cart-card">
                        <div class="cart-card-pad">
                            <div class="cart-summary-row">
                                <span>{{ __('checkout.subtotal') }} ({{ $items->count() }} {{ $items->count() === 1 ? 'tour' : 'tours' }})</span>
                                <b>US${{ number_format($subtotal, 0) }}</b>
                            </div>
                            @if ($discount > 0)
                                <div class="cart-summary-row">
                                    <span>{{ __('checkout.discount') }}{{ $couponCode ? ' (' . $couponCode . ')' : '' }}</span>
                                    <b style="color:var(--cart-ok)">−US${{ number_format($discount, 0) }}</b>
                                </div>
                            @endif
                            <div class="cart-summary-row cart-total-row">
                                <span>{{ __('ui.grand_total') }}</span>
                                <b data-total>US${{ number_format($total, 0) }}</b>
                            </div>
                        </div>
                    </div>

                    {{-- Pay chips --}}
                    <div class="cart-pay-chips">
                        <div class="cart-pay-chip">{{ __('ui.secure_payment_chip') }}</div>
                        <div class="cart-pay-chip">{{ __('ui.quick_confirmation') }}</div>
                        <div class="cart-pay-chip">{{ __('ui.protected_booking') }}</div>
                    </div>

                    {{-- Timing: Pagar ahora / Pagar después --}}
                    <div class="cart-card">
                        <div class="cart-card-pad">
                            <p style="font-size:11px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:#798188;margin:0 0 10px;">{{ __('ui.when_to_pay') }}</p>
                            <div class="cart-timing-grid">
                                <label class="cart-timing-label">
                                    <input type="radio" name="payment_timing_ui" value="now" id="timing-now" checked>
                                    <div class="cart-timing-text">
                                        <b>{{ __('ui.pay_now') }}</b>
                                        <span>{{ __('ui.pay_now_desc') }}</span>
                                    </div>
                                    <div class="cart-timing-price">US${{ number_format($total, 0) }}</div>
                                </label>
                                <label class="cart-timing-label">
                                    <input type="radio" name="payment_timing_ui" value="later" id="timing-later">
                                    <div class="cart-timing-text">
                                        <b>{{ __('ui.reserve_pay_later') }}</b>
                                        <span>{{ __('ui.pay_later_desc', ['date' => $cancelDeadline->locale('es')->isoFormat('D [de] MMM')]) }}</span>
                                    </div>
                                    <div class="cart-timing-price" style="color:var(--cart-ok)">{{ __('ui.zero_now') }}</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Política de cancelación --}}
                    <div class="cart-card">
                        <div class="cart-card-pad">
                            <div class="cart-policy-box">
                                <b>{{ __('ui.free_cancellation_title') }}</b>
                                {{ __('ui.free_cancellation_desc', ['date' => $cancelDeadline->locale('es')->isoFormat('D [de] MMM, YYYY')]) }}
                            </div>
                        </div>
                    </div>

                    {{-- Términos --}}
                    <div class="cart-card">
                        <div class="cart-card-pad">
                            <label class="cart-terms-row" id="terms-row">
                                <input type="checkbox" name="accept_terms" id="accept_terms" value="1">
                                <span>
                                    {{ __('ui.terms_accept_prefix') }} <a href="{{ \App\Support\LocalizedPages::url('legal.terms', $locale) }}">{{ __('ui.terms_link') }}</a>,
                                    la <a href="{{ \App\Support\LocalizedPages::url('legal.privacy', $locale) }}">{{ __('ui.privacy_link') }}</a>
                                    {{ __('ui.contract_text') }}
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Botones de pago PayPal (visibles solo cuando timing=now) --}}
                    <div id="paypal-section" style="display:none; margin-top:12px;">
                        @if ((\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id')))
                            <div id="paypal-buttons"></div>
                            <div id="paypal-msg" role="alert" style="display:none; margin-top:10px; padding:10px 14px; border-radius:14px; font-size:13px; font-weight:600; background:rgba(200,110,87,.08); border:1px solid rgba(200,110,87,.25); color:#C86E57;"></div>
                        @else
                            <div class="cart-policy-box" style="text-align:center;">
                                <b>{{ __('ui.gateway_config_title') }}</b>
                                {{ __('ui.gateway_config_desc') }}
                            </div>
                        @endif
                    </div>

                    {{-- Botón "Reservar y pagar después" (visible solo cuando timing=later) --}}
                    <div id="paylater-section" style="display:none; margin-top:12px;">
                        <button type="submit"
                                form="payment-form"
                                id="btn-pay-later"
                                class="cart-cta-btn"
                                style="height:50px; border-radius:16px; width:100%;">
                            <span>{{ __('ui.reserve_pay_later') }}</span>
                            <span class="cart-cta-arrow" aria-hidden="true">→</span>
                        </button>
                    </div>
                    <div class="cart-step-nav">
                        <button type="button" class="cart-step-btn" data-step-prev><span aria-hidden="true">←</span> {{ __('ui.back_to_data') }}</button>
                    </div>
                    @endif

                </div>{{-- /panel pago --}}

            </form>{{-- /payment-form --}}

        </div>{{-- /screen carrito --}}

        {{-- ══════════════════════════════════════
             PANTALLA: VER MIS RESERVAS
        ══════════════════════════════════════ --}}
        <div id="screen-reservas" class="cart-screen" data-screen="reservasGuardadas" role="tabpanel">
            <div class="cart-head">
                <h2>{{ __('ui.confirmed_bookings') }}</h2>
                <span class="cart-meta-link">{{ __('ui.zero_active') }}</span>
            </div>
            <div class="cart-saved-wrap">
                <div class="cart-empty-state">
                    <div class="cart-empty-head">
                        <div class="cart-empty-icon" aria-hidden="true">✦</div>
                        <div>
                            <h3>{{ __('ui.no_confirmed_bookings') }}</h3>
                            <p>{{ __('ui.no_confirmed_bookings_desc') }}</p>
                        </div>
                    </div>
                    <div class="cart-info-mini-grid">
                        <div class="cart-info-mini">
                            <small>{{ __('ui.here_you_see') }}</small>
                            <span>{{ __('ui.mini_schedule_text') }}</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>{{ __('ui.also_see') }}</small>
                            <span>{{ __('ui.mini_data_text') }}</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>{{ __('ui.status') }}</small>
                            <span>{{ __('ui.mini_confirmation_text') }}</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>{{ __('ui.edition') }}</small>
                            <span>{{ __('ui.mini_edit_text') }}</span>
                        </div>
                    </div>
                    <div class="cart-empty-actions">
                        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">{{ __('ui.explore_tours') }}</a>
                        <a href="mailto:info@limaviewtours.com" class="cart-btn-outline">{{ __('ui.contact_support') }}</a>
                    </div>
                </div>

                <div class="cart-ghost-history">
                    <div class="cart-head" style="margin-top:0">
                        <h2 style="font-size:18px">{{ __('ui.payment_history') }}</h2>
                        <span class="cart-meta-link">{{ __('ui.no_payments') }}</span>
                    </div>
                    <div class="cart-summary-row"><span>{{ __('ui.paid_bookings') }}</span><b>0 tours</b></div>
                    <div class="cart-summary-row"><span>{{ __('ui.payment_method') }}</span><b>—</b></div>
                    <div class="cart-summary-row"><span>{{ __('ui.status') }}</span><b>{{ __('customer.status_pending') }}</b></div>
                    <div class="cart-summary-row cart-total-row"><span>{{ __('ui.total_paid') }}</span><b>US$0</b></div>
                </div>
            </div>
        </div>{{-- /screen reservas --}}

    </div>{{-- /container --}}
</section>

{{-- Spacer para evitar que el sticky tape el contenido --}}
@if ($items->isNotEmpty())
    <div class="cart-sticky-spacer" aria-hidden="true"></div>
@endif

{{-- ── STICKY FOOTER ── --}}
<div class="cart-sticky" id="cart-sticky-footer" @if($items->isEmpty()) style="display:none" @endif>
    <div class="cart-sticky-inner">
        <div>
            <div class="cart-total-label">{{ __('checkout.total') }}</div>
            <div class="cart-total-price" data-total>US${{ number_format($total, 0) }}</div>
        </div>
        <button type="button"
                class="cart-cta-btn"
                id="cart-cta-main"
                data-step-cta>
            <span data-cta-text>{{ __('ui.continue') }}</span>
            <span class="cart-cta-arrow" aria-hidden="true">→</span>
        </button>
    </div>
</div>

@push('scripts')
@if ($items->isNotEmpty() && (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id')))
<script
    src="https://www.paypal.com/sdk/js?client-id={{ (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id')) }}&currency=USD&intent=capture&locale=es_PE"
    data-namespace="paypal_sdk">
</script>
@endif
{{-- El punto de recojo usa autocompletado nativo con <datalist> (gratis, sin
     API de Google Maps). Ver el campo #pickup_point y #pickup_options arriba. --}}
<script>
(function () {
    'use strict';

    const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const locale  = '{{ $locale }}';
    const cartBase = @json(route('cart.index', ['locale' => $locale]));
    const toursUrl = @json(route('tours.index', ['locale' => $locale]));

    // ── URLs de los endpoints PayPal ────────────────────────────
    const paypalCreateUrl  = @json(route('checkout.paypal.create',  ['locale' => $locale]));
    const paypalCaptureUrl = @json(route('checkout.paypal.capture', ['locale' => $locale]));

    // ── Estado en memoria ───────────────────────────────────────
    const cartState = new Map();
    document.querySelectorAll('[data-cart-item]').forEach(card => {
        cartState.set(card.dataset.rowId, {
            adults:     parseInt(card.dataset.adults,    10),
            children:   parseInt(card.dataset.children,  10),
            unitPrice:  parseFloat(card.dataset.unitPrice),
            beforeUnit: parseFloat(card.dataset.beforeUnit) || 0,
            hasDiscount: card.dataset.discount === '1',
        });
    });
    const serverDiscount = {{ $discount ?? 0 }};

    // ── Helpers ─────────────────────────────────────────────────
    async function patchCart(rowId, adults, children) {
        const r = await fetch(`${cartBase}/${rowId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ adults, children }),
        });
        if (!r.ok) throw new Error('patch failed');
        return r.json();
    }
    async function deleteCartItem(rowId) {
        const r = await fetch(`${cartBase}/${rowId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!r.ok) throw new Error('delete failed');
        return r.json();
    }

    // ── Recalcular UI ────────────────────────────────────────────
    function calcSubtotal() {
        let s = 0;
        cartState.forEach(i => { s += i.unitPrice * (i.adults + i.children); });
        return s;
    }
    function calcBeforeTotal() {
        let b = 0;
        cartState.forEach(i => {
            const q = i.adults + i.children;
            const unit = (i.hasDiscount && i.beforeUnit > i.unitPrice) ? i.beforeUnit : i.unitPrice;
            b += unit * q;
        });
        return b;
    }
    function fmt(v) { return 'US$' + Math.round(v); }
    function updateTotalUI() {
        const sub    = calcSubtotal();
        const before = calcBeforeTotal();
        const promo  = Math.max(0, before - sub);
        const total  = Math.max(0, sub - serverDiscount);
        document.querySelectorAll('[data-total]').forEach(el => el.textContent = fmt(total));
        const asSub = document.querySelector('[data-aside-subtotal]');
        const asTot = document.querySelector('[data-aside-total]');
        const asBefore = document.querySelector('[data-aside-before]');
        const asPromo  = document.querySelector('[data-aside-promo]');
        if (asSub) asSub.textContent = fmt(sub);
        if (asTot) asTot.textContent = fmt(total);
        if (asBefore) asBefore.innerHTML = '<s>' + fmt(before) + '</s>';
        if (asPromo)  asPromo.textContent = '−' + fmt(promo);
        document.querySelectorAll('[data-aside-item]').forEach(item => {
            const s = cartState.get(item.dataset.rowId);
            if (!s) return;
            const qty = s.adults + s.children;
            const priceEl = item.querySelector('[data-aside-price]');
            const paxEl   = item.querySelector('[data-aside-pax]');
            if (priceEl) priceEl.textContent = fmt(s.unitPrice * qty);
            if (paxEl)   paxEl.textContent   = `x ${qty} persona${qty !== 1 ? 's' : ''}`;
        });
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = cartState.size);
    }
    function updateCardPriceUI(card) {
        const s = cartState.get(card.dataset.rowId);
        if (!s) return;
        const qty  = s.adults + s.children;
        const reg  = card.querySelector('[data-item-regular]');
        if (reg) reg.textContent = fmt(s.unitPrice * qty);
        // Líneas con descuento (Antes / Ahora / Ahorro)
        const beforeUnit = parseFloat(card.dataset.beforeUnit || '0');
        if (card.dataset.discount === '1' && beforeUnit > 0) {
            const beforeT = beforeUnit * qty;
            const nowT    = s.unitPrice * qty;
            const bEl = card.querySelector('[data-item-before]');
            const nEl = card.querySelector('[data-item-now]');
            const sEl = card.querySelector('[data-item-saved]');
            if (bEl) bEl.textContent = fmt(beforeT);
            if (nEl) nEl.textContent = fmt(nowT);
            if (sEl) sEl.textContent = 'Ahorro ' + fmt(beforeT - nowT);
        }
        const aL = card.querySelector('[data-adults-label]');
        const cL = card.querySelector('[data-children-label]');
        if (aL) aL.textContent = s.adults;
        if (cL) cL.textContent = s.children;
    }

    // ── Steppers ─────────────────────────────────────────────────
    let debounceTimers = {};
    document.querySelectorAll('[data-cart-item]').forEach(card => {
        const rowId = card.dataset.rowId;
        card.querySelectorAll('[data-qty-btn]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.target;
                const action = btn.dataset.qtyBtn;
                const s = cartState.get(rowId);
                if (!s) return;
                if (action === 'plus') {
                    s[target] = Math.min(20, s[target] + 1);
                } else {
                    s[target] = Math.max(target === 'adults' ? 1 : 0, s[target] - 1);
                }
                card.querySelector('[data-adults-value]').textContent   = s.adults;
                card.querySelector('[data-children-value]').textContent = s.children;
                const mA = card.querySelector('[data-qty-btn="minus"][data-target="adults"]');
                const mC = card.querySelector('[data-qty-btn="minus"][data-target="children"]');
                if (mA) mA.disabled = s.adults   <= 1;
                if (mC) mC.disabled = s.children <= 0;
                updateCardPriceUI(card);
                updateTotalUI();
                clearTimeout(debounceTimers[rowId]);
                debounceTimers[rowId] = setTimeout(() => {
                    patchCart(rowId, s.adults, s.children).catch(() => {});
                }, 500);
            });
        });
        const s  = cartState.get(rowId);
        if (s) {
            const mA = card.querySelector('[data-qty-btn="minus"][data-target="adults"]');
            const mC = card.querySelector('[data-qty-btn="minus"][data-target="children"]');
            if (mA) mA.disabled = s.adults   <= 1;
            if (mC) mC.disabled = s.children <= 0;
        }
    });

    // ── Eliminar tour ────────────────────────────────────────────
    document.querySelectorAll('[data-remove-btn]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const card  = btn.closest('[data-cart-item]');
            const rowId = card.dataset.rowId;
            card.classList.add('removing');
            try { await deleteCartItem(rowId); } catch { card.classList.remove('removing'); return; }
            cartState.delete(rowId);
            const asideItem = document.querySelector(`[data-aside-item][data-row-id="${rowId}"]`);
            if (asideItem) asideItem.remove();
            setTimeout(() => {
                card.remove();
                if (cartState.size === 0) {
                    // Carrito en 0 → recargar para reiniciar TODO (formulario, paneles 2/3, badge, sesión)
                    window.location.reload();
                    return;
                }
                updateTotalUI();
            }, 300);
        });
    });

    function showEmptyState() {
        const list        = document.getElementById('cart-items-list');
        const stickyFooter = document.getElementById('cart-sticky-footer');
        const asideEl     = document.querySelector('[aria-label="Resumen del pedido"]');
        if (list)         list.remove();
        if (stickyFooter) stickyFooter.style.display = 'none';
        if (asideEl)      asideEl.remove();
        document.querySelectorAll('.cart-coupon-card, .flex.items-center.justify-between.mt-4').forEach(el => el.remove());
        const colPrincipal = document.querySelector('[data-step="reservas"] .lg\\:grid > div');
        const head         = colPrincipal?.querySelector('.cart-head');
        if (!head) return;
        const html = `
            <div class="cart-empty-state" id="cart-empty-state">
                <div class="cart-empty-head">
                    <div class="cart-empty-icon" aria-hidden="true">🛒</div>
                    <div><h3>Tu carrito está vacío</h3><p>Aquí aparecerán los tours que agregues antes de completar tu reserva.</p></div>
                </div>
                <div class="cart-empty-pills" aria-hidden="true">
                    <span class="cart-empty-pill">Reserva rápida</span>
                    <span class="cart-empty-pill">Pago seguro</span>
                    <span class="cart-empty-pill">Confirmación inmediata</span>
                </div>
                <div class="cart-empty-actions">
                    <a href="${toursUrl}" class="cart-btn-dark">Explorar tours</a>
                </div>
            </div>`;
        head.insertAdjacentHTML('afterend', html);
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = '0');
        // Ocultar steps 2 y 3 del CTA
        const ctaText = document.querySelector('[data-cta-text]');
        if (ctaText) ctaText.textContent = 'Continuar';
    }

    // ── Switcher screens ─────────────────────────────────────────
    const screens    = document.querySelectorAll('[data-screen]');
    const switchBtns = document.querySelectorAll('[data-screen-btn]');
    function setScreen(name) {
        screens.forEach(s    => s.classList.toggle('active', s.dataset.screen === name));
        switchBtns.forEach(b => {
            const a = b.dataset.screenBtn === name;
            b.classList.toggle('active', a);
            b.setAttribute('aria-selected', a ? 'true' : 'false');
        });
        // Ocultar/mostrar sticky cuando cambia la pantalla
        const sticky = document.getElementById('cart-sticky-footer');
        if (sticky) sticky.style.display = (name === 'carrito' && cartState.size > 0) ? '' : 'none';
    }
    switchBtns.forEach(btn => btn.addEventListener('click', () => setScreen(btn.dataset.screenBtn)));

    // ── Stepper 3 pasos ──────────────────────────────────────────
    const steps    = ['reservas', 'datos', 'pago'];
    const ctaLabels = { reservas: 'Continuar', datos: 'Ir al pago', pago: 'Ver opciones de pago' };
    let currentStep = '{{ $openStep }}';

    function setStep(name) {
        if (!steps.includes(name)) return;
        currentStep = name;

        // Panels
        document.querySelectorAll('.cart-panel').forEach(p => p.classList.toggle('active', p.dataset.step === name));

        // Seg btns
        document.querySelectorAll('[data-step-btn]').forEach(b => {
            b.classList.toggle('active', b.dataset.stepBtn === name);
            b.setAttribute('aria-current', b.dataset.stepBtn === name ? 'step' : 'false');
        });

        // CTA text
        const ctaText = document.querySelector('[data-cta-text]');
        if (ctaText) ctaText.textContent = ctaLabels[name] ?? 'Continuar';

        // Sticky: ocultar en reservas vacío; siempre visible si hay items
        const sticky = document.getElementById('cart-sticky-footer');
        if (sticky) sticky.style.display = cartState.size > 0 ? '' : 'none';

        // Sync payment section visibility when entering the "pago" panel
        if (name === 'pago') syncPaymentSection();
    }

    // ── Navegación CONDICIONADA por pasos ────────────────────────
    function validateDatos() {
        const d = collectCustomerData();
        if (!d.customer_name || !d.customer_email || !d.customer_phone || !d.travel_date) {
            alert('Completa tu nombre, correo, teléfono y fecha de viaje antes de continuar.');
            return false;
        }
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.customer_email)) {
            alert('Ingresa un correo electrónico válido.');
            return false;
        }
        return true;
    }
    function canAdvanceTo(target) {
        const ti = steps.indexOf(target), ci = steps.indexOf(currentStep);
        if (ti <= ci) return true;  // volver a un paso previo siempre se permite
        if (cartState.size === 0) { alert('Agrega al menos un tour para continuar.'); return false; }
        // Para llegar a "Pago" los datos deben estar completos
        if (ti >= steps.indexOf('pago') && !validateDatos()) {
            setStep('datos');
            document.querySelector('#customer_name')?.focus();
            return false;
        }
        return true;
    }

    // Stepper (números): permite volver; condiciona avanzar
    document.querySelectorAll('[data-step-btn]').forEach(btn => {
        btn.addEventListener('click', () => { if (canAdvanceTo(btn.dataset.stepBtn)) setStep(btn.dataset.stepBtn); });
    });
    // Botones Atrás / Adelante dentro de cada panel
    document.querySelectorAll('[data-step-prev]').forEach(b => b.addEventListener('click', () => {
        const p = steps[steps.indexOf(currentStep) - 1];
        if (p) { setStep(p); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    }));
    document.querySelectorAll('[data-step-next]').forEach(b => b.addEventListener('click', () => {
        const n = steps[steps.indexOf(currentStep) + 1];
        if (n && canAdvanceTo(n)) { setStep(n); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    }));

    // ── Helpers para validar campos del cliente ─────────────────
    function collectCustomerData() {
        const form = document.getElementById('payment-form');
        return {
            customer_name:  form.querySelector('#customer_name')?.value.trim()  ?? '',
            customer_email: form.querySelector('#customer_email')?.value.trim() ?? '',
            customer_phone: form.querySelector('#customer_phone')?.value.trim() ?? '',
            travel_date:    form.querySelector('#travel_date')?.value            ?? '',
            pickup_point:   form.querySelector('#pickup_point')?.value           ?? '',
            pickup_detail:  form.querySelector('[name="pickup_detail"]')?.value  ?? '',
        };
    }

    // ── Carrito abandonado: captura de contacto en segundo plano ─────
    // Cuando el visitante escribe su correo (y datos) en el checkout,
    // guardamos el carrito + contacto para poder recuperarlo si no paga.
    const CART_CONTACT_URL = @json(route('cart.contact', ['locale' => app()->getLocale()]));
    let lastContactSnapshot = '';
    function persistAbandonedContact() {
        const d = collectCustomerData();
        if (!d.customer_email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.customer_email)) return;
        const snapshot = [d.customer_email, d.customer_name, d.customer_phone].join('|');
        if (snapshot === lastContactSnapshot) return; // no reenviar si nada cambió
        lastContactSnapshot = snapshot;
        try {
            fetch(CART_CONTACT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     CSRF,
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    customer_email: d.customer_email,
                    customer_name:  d.customer_name,
                    customer_phone: d.customer_phone,
                }),
                keepalive: true,
            }).catch(() => {});
        } catch (e) { /* silencioso: nunca romper el checkout */ }
    }
    ['#customer_email', '#customer_name', '#phone_local'].forEach(sel => {
        const el = document.querySelector(sel);
        if (el) el.addEventListener('blur', persistAbandonedContact);
    });

    function validateCustomer() {
        const d     = collectCustomerData();
        const terms = document.getElementById('accept_terms')?.checked;
        if (!d.customer_name || !d.customer_email || !d.customer_phone || !d.travel_date) {
            alert('Por favor completa todos los campos obligatorios (nombre, correo, teléfono y fecha de viaje) antes de continuar.');
            setStep('datos');
            document.querySelector('#customer_name')?.focus();
            return false;
        }
        if (!terms) {
            alert('Debes aceptar los términos y condiciones para continuar.');
            document.getElementById('accept_terms')?.focus();
            return false;
        }
        return true;
    }

    // ── Mostrar/ocultar sección de pago según timing ─────────────
    function syncPaymentSection() {
        const timing = document.querySelector('input[name="payment_timing_ui"]:checked')?.value ?? 'now';
        const ppSection  = document.getElementById('paypal-section');
        const plSection  = document.getElementById('paylater-section');
        document.getElementById('payment_timing_hidden').value = timing;
        if (ppSection) ppSection.style.display  = (timing === 'now')   ? '' : 'none';
        if (plSection) plSection.style.display  = (timing === 'later') ? '' : 'none';
    }

    document.querySelectorAll('input[name="payment_timing_ui"]').forEach(radio => {
        radio.addEventListener('change', syncPaymentSection);
    });

    // ── Inicializar PayPal Buttons ───────────────────────────────
    @if ($items->isNotEmpty() && (\App\Models\Setting::get('paypal_client_id') ?: config('services.paypal.client_id')))
    if (typeof paypal_sdk !== 'undefined') {
        paypal_sdk.Buttons({
            style: {
                layout: 'vertical',
                color:  'gold',
                shape:  'rect',
                label:  'pay',
            },
            createOrder: async function () {
                if (!validateCustomer()) {
                    return Promise.reject(new Error('validation_failed'));
                }
                const res = await fetch(paypalCreateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                if (!res.ok || !data.id) {
                    throw new Error(data.error ?? 'No se pudo crear la orden.');
                }
                return data.id;
            },
            onApprove: async function (paypalData) {
                const customer = collectCustomerData();
                const msgEl    = document.getElementById('paypal-msg');
                if (msgEl) { msgEl.style.display = 'none'; msgEl.textContent = ''; }

                const res = await fetch(paypalCaptureUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'application/json',
                    },
                    body: JSON.stringify({
                        orderID:        paypalData.orderID,
                        customer_name:  customer.customer_name,
                        customer_email: customer.customer_email,
                        customer_phone: customer.customer_phone,
                        travel_date:    customer.travel_date,
                        pickup_point:   customer.pickup_point,
                        pickup_detail:  customer.pickup_detail,
                    }),
                });
                const data = await res.json();
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    if (msgEl) {
                        msgEl.textContent  = data.message ?? 'El pago no pudo completarse. Por favor inténtalo de nuevo.';
                        msgEl.style.display = '';
                    }
                }
            },
            onError: function (err) {
                const msgEl = document.getElementById('paypal-msg');
                if (msgEl) {
                    msgEl.textContent  = 'Ocurrió un error con PayPal. Por favor recarga la página e inténtalo de nuevo.';
                    msgEl.style.display = '';
                }
                console.error('PayPal error:', err);
            },
            onCancel: function () {
                // Buyer cancelled — do nothing, keep the page open
            },
        }).render('#paypal-buttons');
    }
    @endif

    // ── CTA del footer sticky ────────────────────────────────────
    const ctaMain = document.getElementById('cart-cta-main');
    if (ctaMain) {
        ctaMain.addEventListener('click', () => {
            const idx = steps.indexOf(currentStep);
            if (currentStep === 'pago') {
                // In "pago" step the CTA just scrolls into view so the user can
                // interact with the PayPal buttons or the pay-later button.
                // For pay-later we submit directly; for pay-now the PayPal SDK
                // handles the click via its own buttons rendered in #paypal-buttons.
                const timing = document.querySelector('input[name="payment_timing_ui"]:checked')?.value ?? 'now';
                if (timing === 'later') {
                    if (!validateCustomer()) return;
                    document.getElementById('payment-form').submit();
                } else {
                    // Scroll to the PayPal buttons area
                    document.getElementById('paypal-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (idx < steps.length - 1) {
                const next = steps[idx + 1];
                if (canAdvanceTo(next)) setStep(next);
            }
        });
    }

    // Agregar tour → feedback visual
    document.querySelectorAll('[data-add-tour]').forEach(btn => {
        if (btn.tagName === 'A') {
            btn.addEventListener('click', () => {
                btn.classList.add('added');
                btn.textContent = 'Yendo al tour…';
            });
        }
    });

    // ── Inicializar step desde sesión (cuando se viene de checkout.pay) ──
    setStep(currentStep);

})();
</script>
@endpush

@endsection

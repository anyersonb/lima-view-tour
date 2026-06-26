@extends('layouts.app')

@php
    $locale = app()->getLocale();
@endphp

@section('title', 'Carrito de compra — ' . __('seo.site_name'))
@section('description', 'Confirma tus reservas y procede al pago seguro. Lima View Tours.')

@push('head')
<meta name="robots" content="noindex,nofollow">
<style>
/* ── Cart premium — variables locales ── */
:root {
    --cart-ink: #143E40;
    --cart-muted: #746F69;
    --cart-line: #E6D9C8;
    --cart-green: #143E40;
    --cart-green2: #1B4D4F;
    --cart-gold: #D9A15C;
    --cart-soft: #F7F1E8;
    --cart-okbg: #F2F5EF;
    --cart-ok: #1B4D4F;
    --cart-danger: #C86E57;
    --cart-paper: #FFFCF8;
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

/* ── Screens (carrito / reservas) ── */
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
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, var(--cart-gold), #e9c68b);
}
.cart-tour-card.removing {
    opacity: 0;
    transform: translateX(-12px);
    transition: opacity .28s ease, transform .28s ease;
}

/* chips */
.cart-chip-row {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    padding-left: 6px;
}
.cart-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 999px;
    background: var(--cart-soft);
    border: 1px solid var(--cart-line);
    font-size: 9.6px;
    font-weight: 700;
    color: #5E5A55;
    letter-spacing: .2px;
}
.cart-chip.promo {
    background: var(--cart-okbg);
    border-color: #d7ecdf;
    color: var(--cart-ok);
}

/* tour top layout */
.cart-tour-top {
    display: grid;
    grid-template-columns: 82px 1fr auto;
    gap: 12px;
    align-items: start;
}
.cart-thumb {
    width: 82px;
    height: 82px;
    border-radius: 16px;
    overflow: hidden;
    flex-shrink: 0;
}
.cart-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* info */
.cart-tour-info h3 {
    margin: 0 0 5px;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 16px;
    line-height: 1.1;
    color: var(--cart-ink);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cart-meta-line {
    font-size: 11px;
    color: var(--cart-gold);
    font-weight: 700;
    margin-bottom: 5px;
}

/* price box */
.cart-price-box {
    text-align: right;
    min-width: 92px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
}
.cart-price-tag {
    font-size: 8.5px;
    font-weight: 900;
    letter-spacing: .85px;
    text-transform: uppercase;
    color: #7a848a;
}
.cart-price-before {
    font-size: 12px;
    color: #948C83;
    text-decoration: line-through;
    font-weight: 700;
}
.cart-price-now {
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 24px;
    line-height: 1;
    color: var(--cart-green);
}
.cart-price-saved {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--cart-okbg);
    border: 1px solid #d5ecdf;
    color: var(--cart-ok);
    padding: 4px 7px;
    border-radius: 999px;
    font-size: 8.5px;
    font-weight: 700;
}
.cart-price-regular {
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 24px;
    line-height: 1;
    color: var(--cart-green);
    margin-top: 10px;
}
.cart-remove-btn {
    margin-top: 5px;
    color: var(--cart-danger);
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.cart-remove-btn:focus-visible {
    outline: 2px solid var(--cart-danger);
    border-radius: 3px;
}

/* field boxes */
.cart-tour-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
    margin-top: 12px;
}
.cart-field-box {
    background: #FCF7F0;
    border: 1px solid #E8DED1;
    border-radius: 16px;
    padding: 9px;
}
.cart-field-box label {
    display: block;
    font-size: 8.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #746F69;
    margin: 0 0 6px;
}
.cart-mini-input,
.cart-mini-select {
    height: 36px;
    border-radius: 12px;
    border: 1px solid #ded5c8;
    background: #fff;
    padding: 0 10px;
    font-size: 12px;
    color: #45545a;
    width: 100%;
    outline: none;
    font-family: inherit;
}
.cart-mini-input:focus,
.cart-mini-select:focus {
    border-color: var(--cart-green2);
    box-shadow: 0 0 0 2px rgba(27,77,79,.15);
}

/* pax shell */
.cart-pax-shell {
    margin-top: 12px;
    background: #FCF7F0;
    border: 1px solid #E8DED1;
    border-radius: 18px;
    padding: 11px;
}
.cart-pax-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 9px;
}
.cart-pax-head strong {
    font-size: 10px;
    letter-spacing: .9px;
    text-transform: uppercase;
    color: #6e767c;
    font-weight: 700;
}
.cart-pax-head span {
    font-size: 11px;
    color: #7c858b;
    font-weight: 600;
}
.cart-pax-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.cart-pax-card {
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 14px;
    padding: 10px;
}
.cart-pax-card label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--cart-ink);
    margin-bottom: 7px;
}
.cart-qty-control {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.cart-qty-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #fff;
    border: 1px solid #ded4c7;
    color: var(--cart-green);
    font-weight: 900;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    transition: background .15s, border-color .15s;
}
.cart-qty-btn:hover {
    background: var(--cart-soft);
    border-color: var(--cart-gold);
}
.cart-qty-btn:focus-visible {
    outline: 2px solid var(--cart-green2);
}
.cart-qty-btn:disabled {
    opacity: .35;
    cursor: not-allowed;
}
.cart-qty-value {
    font-size: 16px;
    font-weight: 900;
    color: var(--cart-ink);
    min-width: 16px;
    text-align: center;
}

/* ── Venta cruzada ── */
.cart-section-title-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 18px 0 12px;
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 18px;
    padding: 12px 14px;
    box-shadow: 0 8px 18px rgba(20,62,64,.04);
}
.cart-section-title-card h3 {
    margin: 0;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 18px;
    color: var(--cart-ink);
}
.cart-section-title-card span {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--cart-gold);
}
.cart-more-tours {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 160px;
    gap: 10px;
    overflow-x: auto;
    padding: 2px 2px 8px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.cart-more-tours::-webkit-scrollbar { display: none; }
.cart-more-card {
    scroll-snap-align: start;
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 10px 22px rgba(0,0,0,.05);
    display: flex;
    flex-direction: column;
    min-height: 250px;
}
.cart-more-card img {
    width: 100%;
    height: 96px;
    object-fit: cover;
    display: block;
}
.cart-more-body {
    padding: 10px 10px 12px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.cart-more-tag {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.6px;
    color: var(--cart-gold);
    text-transform: uppercase;
    margin: 0 0 6px;
}
.cart-more-card h4 {
    margin: 0 0 8px;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 15px;
    line-height: 1.1;
    color: var(--cart-ink);
    min-height: 34px;
}
.cart-more-price {
    font-size: 15px;
    font-weight: 700;
    color: var(--cart-green);
    margin-top: auto;
    margin-bottom: 8px;
}
.cart-add-btn {
    width: 100%;
    height: 38px;
    border-radius: 14px;
    border: 1px solid #D8E5DE;
    background: #F1F7F3;
    color: var(--cart-green);
    padding: 0 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, color .15s;
    font-family: inherit;
}
.cart-add-btn:hover {
    background: #e2f0e7;
}
.cart-add-btn.added {
    background: #EAF4EE;
    color: #167A56;
    border-color: #D5E7DA;
    cursor: default;
}
.cart-add-btn:focus-visible {
    outline: 2px solid var(--cart-green2);
}

/* ── Cupón ── */
.cart-coupon-card {
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff;
    border-radius: 20px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
@media (min-width: 540px) {
    .cart-coupon-card { flex-direction: row; align-items: center; }
}
.cart-coupon-text { font-size: 13px; flex: 1; line-height: 1.4; }
.cart-coupon-form { display: flex; gap: 8px; }
.cart-coupon-input {
    flex: 1;
    min-width: 0;
    height: 40px;
    border-radius: 999px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.3);
    color: #fff;
    padding: 0 14px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
}
.cart-coupon-input::placeholder { color: rgba(255,255,255,.55); }
.cart-coupon-input:focus { border-color: rgba(255,255,255,.6); }
.cart-coupon-submit {
    height: 40px;
    border-radius: 999px;
    background: #fff;
    color: var(--cart-green);
    border: none;
    padding: 0 18px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
    font-family: inherit;
}
.cart-coupon-submit:hover { background: var(--cart-soft); }
.cart-coupon-submit:focus-visible { outline: 2px solid var(--cart-gold); }

/* ── Footer sticky ── */
.cart-sticky {
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    bottom: 12px;
    width: min(560px, calc(100vw - 24px));
    z-index: 40;
    pointer-events: none;
}
.cart-sticky-inner {
    pointer-events: auto;
    background: rgba(255,255,255,.97);
    border: 1px solid #e8ddce;
    border-radius: 20px;
    box-shadow: 0 -8px 26px rgba(0,0,0,.12);
    padding: 10px 14px;
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 12px;
    align-items: center;
}
.cart-total-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #737a80;
    margin-bottom: 2px;
}
.cart-total-price {
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 26px;
    line-height: 1;
    color: var(--cart-green);
}
.cart-cta-btn {
    height: 50px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 12px 22px rgba(20,62,64,.18);
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    transition: opacity .15s;
    width: 100%;
}
.cart-cta-btn:hover { opacity: .9; }
.cart-cta-btn:focus-visible { outline: 2px solid var(--cart-gold); outline-offset: 2px; }
.cart-cta-arrow {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--cart-gold);
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 14px;
    flex-shrink: 0;
}

/* ── Estado vacío premium ── */
.cart-empty-state {
    background: linear-gradient(180deg, #FFFDF9 0%, var(--cart-soft) 100%);
    border: 1px solid var(--cart-line);
    border-radius: 22px;
    padding: 18px 16px;
    box-shadow: 0 10px 22px rgba(20,62,64,.05);
    margin-top: 4px;
}
.cart-empty-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}
.cart-empty-icon {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--cart-green), var(--cart-green2));
    display: grid;
    place-items: center;
    font-size: 24px;
    box-shadow: 0 12px 20px rgba(20,62,64,.14);
    flex-shrink: 0;
}
.cart-empty-state h3 {
    margin: 0;
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 20px;
    line-height: 1.1;
    color: var(--cart-ink);
}
.cart-empty-state p {
    margin: 6px 0 0;
    font-size: 12.5px;
    line-height: 1.5;
    color: var(--cart-muted);
}
.cart-empty-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.cart-empty-pill {
    padding: 7px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--cart-line);
    font-size: 11px;
    font-weight: 700;
    color: var(--cart-ink);
}
.cart-empty-actions {
    display: flex;
    gap: 10px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.cart-btn-dark {
    flex: 1;
    min-width: 120px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--cart-green2), var(--cart-green));
    color: #fff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    transition: opacity .15s;
}
.cart-btn-dark:hover { opacity: .88; }
.cart-btn-dark:focus-visible { outline: 2px solid var(--cart-gold); }
.cart-btn-outline {
    flex: 1;
    min-width: 120px;
    height: 40px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #d8dcd8;
    color: var(--cart-green);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    transition: background .15s;
}
.cart-btn-outline:hover { background: var(--cart-soft); }
.cart-btn-outline:focus-visible { outline: 2px solid var(--cart-green2); }

.cart-empty-note-card {
    margin-top: 12px;
    background: #fff;
    border: 1px dashed #D8C7AE;
    border-radius: 18px;
    padding: 14px;
}
.cart-empty-note-card h4 {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--cart-ink);
}
.cart-empty-note-list {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.cart-empty-note-list li {
    font-size: 12px;
    color: var(--cart-muted);
    display: flex;
    gap: 8px;
    align-items: flex-start;
    line-height: 1.4;
}
.cart-empty-note-list b { color: var(--cart-ink); }

/* ── Reservas confirmadas (estado vacío) ── */
.cart-saved-wrap { display: grid; gap: 14px; }
.cart-info-mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
    margin-top: 12px;
}
.cart-info-mini {
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 15px;
    padding: 10px 11px;
}
.cart-info-mini small {
    display: block;
    font-size: 10px;
    color: var(--cart-gold);
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.cart-info-mini span {
    display: block;
    font-size: 12px;
    color: var(--cart-ink);
    line-height: 1.4;
    font-weight: 600;
}
.cart-ghost-history {
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 20px;
    padding: 14px;
}
.cart-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid #eee6da;
}
.cart-summary-row:last-child { border-bottom: 0; }
.cart-summary-row span { font-size: 13px; color: #59646a; }
.cart-summary-row b { font-size: 14px; color: var(--cart-ink); }
.cart-summary-row.cart-total-row b {
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 24px;
    color: var(--cart-green);
}

/* ── Resumen aside (desktop) ── */
.cart-aside {
    background: #fff;
    border: 1px solid var(--cart-line);
    border-radius: 22px;
    padding: 18px;
    box-shadow: var(--cart-shadow);
}
.cart-aside h3 {
    font-family: 'Hedvig Letters Serif', Georgia, serif;
    font-size: 18px;
    color: var(--cart-ink);
    margin: 0 0 14px;
}
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
.cart-aside-total {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--cart-line);
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}
.cart-aside-total-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--cart-muted); font-weight: 700; }
.cart-aside-total-price {
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 28px;
    line-height: 1;
    color: var(--cart-green);
}
.cart-aside-total-usd { font-size: 11px; color: var(--cart-muted); margin-left: 3px; }

/* ── Flash messages ── */
.cart-flash {
    padding: 12px 16px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 14px;
}
.cart-flash.success {
    background: rgba(27,77,79,.08);
    border: 1px solid rgba(27,77,79,.2);
    color: var(--cart-ok);
}
.cart-flash.error {
    background: rgba(200,110,87,.08);
    border: 1px solid rgba(200,110,87,.25);
    color: var(--cart-danger);
}

/* ── Spacer para evitar que el sticky tape los items ── */
.cart-sticky-spacer { height: 80px; }

/* ── Inline más tours (grid 2 cols cuando vacío) ── */
@media (max-width: 639px) {
    .cart-more-tours.compact {
        display: grid;
        grid-auto-flow: unset;
        grid-template-columns: 1fr 1fr;
        overflow-x: visible;
    }
    .cart-more-tours.compact .cart-more-card {
        min-width: 0;
    }
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
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a> &rsaquo; <span>Carrito de compra</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-14 md:py-18 text-center">
        <h1 class="font-display text-3xl md:text-4xl lg:text-5xl leading-tight">Carrito de compra</h1>
        <p class="mt-3 mx-auto max-w-xl text-sm text-white/85">
            Revisa tus reservas, ajusta pasajeros y procede al pago. Cancelación gratuita hasta 48 h antes.
        </p>
    </div>
</section>

{{-- ── CONTENIDO CARRITO ── --}}
<section class="bg-cream-100 pb-10 lg:pb-16">
    <div class="container mx-auto px-5 lg:px-10">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="cart-flash success" role="alert">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="cart-flash error" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->has('general'))
            <div class="cart-flash error" role="alert">{{ $errors->first('general') }}</div>
        @endif

        {{-- Switcher Carrito / Reservas --}}
        <div class="cart-switcher" role="tablist" aria-label="Vistas">
            <button class="cart-switch-btn active"
                    role="tab"
                    aria-selected="true"
                    aria-controls="screen-carrito"
                    data-screen-btn="carrito">
                Carrito
            </button>
            <button class="cart-switch-btn"
                    role="tab"
                    aria-selected="false"
                    aria-controls="screen-reservas"
                    data-screen-btn="reservasGuardadas">
                Ver mis reservas
            </button>
        </div>

        {{-- ── PANTALLA: CARRITO ── --}}
        <div id="screen-carrito" class="cart-screen active" data-screen="carrito" role="tabpanel">

            {{-- Stepper numerado --}}
            <div class="cart-segment" aria-label="Pasos del proceso de compra">
                <button class="cart-seg-btn active" data-step-btn="reservas" aria-current="step">
                    <div class="cart-seg-num">1</div>
                    <span class="cart-seg-label">Reservas</span>
                </button>
                <a href="{{ route('checkout.pay', ['locale' => $locale]) }}"
                   class="cart-seg-btn"
                   data-step-btn="datos">
                    <div class="cart-seg-num">2</div>
                    <span class="cart-seg-label">Datos</span>
                </a>
                <a href="{{ route('checkout.pay', ['locale' => $locale]) }}"
                   class="cart-seg-btn"
                   data-step-btn="pago">
                    <div class="cart-seg-num">3</div>
                    <span class="cart-seg-label">Pago</span>
                </a>
            </div>

            {{-- ── Panel Reservas ── --}}
            <div class="lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-8 lg:items-start">

                {{-- Columna principal --}}
                <div>
                    {{-- Cabecera --}}
                    <div class="cart-head">
                        <h2>Tus reservas</h2>
                        <span class="cart-meta-link">
                            <span data-cart-count>{{ $items->count() }}</span> tour{{ $items->count() !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    @if ($items->isEmpty())
                        {{-- Estado vacío premium --}}
                        <div class="cart-empty-state" id="cart-empty-state">
                            <div class="cart-empty-head">
                                <div class="cart-empty-icon" aria-hidden="true">🛒</div>
                                <div>
                                    <h3>Tu carrito está vacío</h3>
                                    <p>Aquí aparecerán los tours que agregues antes de completar tu reserva. Elige una experiencia y continúa en pocos pasos.</p>
                                </div>
                            </div>
                            <div class="cart-empty-pills" aria-hidden="true">
                                <span class="cart-empty-pill">Reserva rápida</span>
                                <span class="cart-empty-pill">Pago seguro</span>
                                <span class="cart-empty-pill">Confirmación inmediata</span>
                            </div>
                            <div class="cart-empty-actions">
                                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">Explorar tours</a>
                                <button class="cart-btn-outline" data-screen-btn="reservasGuardadas" type="button">Ver mis reservas</button>
                            </div>
                        </div>

                        <div class="cart-empty-note-card">
                            <h4>¿Qué pasará cuando agregues un tour?</h4>
                            <ul class="cart-empty-note-list">
                                <li><span aria-hidden="true">•</span><span><b>Paso 1:</b> verás tu tour, fecha, idioma y cantidad de pasajeros.</span></li>
                                <li><span aria-hidden="true">•</span><span><b>Paso 2:</b> completarás tus datos de contacto y hotel o Airbnb.</span></li>
                                <li><span aria-hidden="true">•</span><span><b>Paso 3:</b> revisarás el pago final y confirmarás tu reserva.</span></li>
                            </ul>
                        </div>

                    @else
                        {{-- Lista de tours --}}
                        <div class="cart-list" id="cart-items-list">
                            @foreach ($items as $item)
                                @php
                                    $cover = $item['cover_image'] ?? null;
                                    $imgSrc = $cover
                                        ? (str_starts_with($cover, 'http') ? $cover : asset($cover))
                                        : asset('assets/banners/Rectangle 19210.jpg');
                                    $hasDiscount = false; // el modelo actual no tiene precio_anterior; extensible si se agrega
                                @endphp

                                <article class="cart-tour-card"
                                         data-cart-item
                                         data-row-id="{{ $item['row_id'] }}"
                                         data-unit-price="{{ $item['unit_price'] }}"
                                         data-adults="{{ $item['adults'] }}"
                                         data-children="{{ $item['children'] }}">

                                    {{-- Chips de estado --}}
                                    <div class="cart-chip-row">
                                        <div class="cart-chip">Tour agregado</div>
                                        @if ($hasDiscount)
                                            <div class="cart-chip promo">Oferta especial</div>
                                        @endif
                                    </div>

                                    {{-- Top: imagen · info · precio --}}
                                    <div class="cart-tour-top">
                                        <div class="cart-thumb">
                                            <img src="{{ $imgSrc }}"
                                                 alt="{{ $item['title_snapshot'] }}"
                                                 width="82" height="82"
                                                 loading="lazy">
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
                                        </div>

                                        <div class="cart-price-box">
                                            @if ($hasDiscount)
                                                <div class="cart-price-tag">Antes</div>
                                                <div class="cart-price-before" data-item-before>US${{ number_format($item['unit_price'] * 1.15, 0) }}</div>
                                                <div class="cart-price-tag">Ahora</div>
                                                <div class="cart-price-now" data-item-now>US${{ number_format($item['subtotal'], 0) }}</div>
                                                <div class="cart-price-saved" data-item-saved>Ahorro US${{ number_format($item['unit_price'] * 1.15 * $item['quantity'] - $item['subtotal'], 0) }}</div>
                                            @else
                                                <div class="cart-price-regular" data-item-regular>US${{ number_format($item['subtotal'], 0) }}</div>
                                            @endif
                                            <button type="button"
                                                    class="cart-remove-btn"
                                                    data-remove-btn
                                                    aria-label="Eliminar {{ $item['title_snapshot'] }}">
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Fecha e idioma --}}
                                    <div class="cart-tour-grid">
                                        <div class="cart-field-box">
                                            <label for="date-{{ $item['row_id'] }}">Fecha del tour</label>
                                            <input type="date"
                                                   id="date-{{ $item['row_id'] }}"
                                                   class="cart-mini-input"
                                                   name="travel_date"
                                                   value="{{ $item['travel_date'] }}"
                                                   data-date-input
                                                   readonly
                                                   title="Para cambiar la fecha elimina el tour y agrégalo de nuevo con la fecha correcta">
                                        </div>
                                        <div class="cart-field-box">
                                            <label for="lang-{{ $item['row_id'] }}">Idioma del tour</label>
                                            <select id="lang-{{ $item['row_id'] }}"
                                                    class="cart-mini-select"
                                                    name="language"
                                                    data-lang-select>
                                                @foreach (['Español', 'English', 'Português'] as $lang)
                                                    <option value="{{ $lang }}" @selected($item['language'] === $lang)>{{ $lang }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    {{-- Pasajeros --}}
                                    <div class="cart-pax-shell">
                                        <div class="cart-pax-head">
                                            <strong>Pasajeros</strong>
                                            <span>Adultos y niños pagan igual</span>
                                        </div>
                                        <div class="cart-pax-grid">
                                            <div class="cart-pax-card">
                                                <label>Adultos</label>
                                                <div class="cart-qty-control">
                                                    <button type="button"
                                                            class="cart-qty-btn"
                                                            data-qty-btn="minus"
                                                            data-target="adults"
                                                            aria-label="Quitar un adulto">−</button>
                                                    <span class="cart-qty-value" data-adults-value>{{ $item['adults'] }}</span>
                                                    <button type="button"
                                                            class="cart-qty-btn"
                                                            data-qty-btn="plus"
                                                            data-target="adults"
                                                            aria-label="Agregar un adulto">+</button>
                                                </div>
                                            </div>
                                            <div class="cart-pax-card">
                                                <label>Niños</label>
                                                <div class="cart-qty-control">
                                                    <button type="button"
                                                            class="cart-qty-btn"
                                                            data-qty-btn="minus"
                                                            data-target="children"
                                                            aria-label="Quitar un niño">−</button>
                                                    <span class="cart-qty-value" data-children-value>{{ $item['children'] }}</span>
                                                    <button type="button"
                                                            class="cart-qty-btn"
                                                            data-qty-btn="plus"
                                                            data-target="children"
                                                            aria-label="Agregar un niño">+</button>
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
                                    Cupón <strong>{{ $couponCode }}</strong> aplicado correctamente.
                                @else
                                    ¿Tienes un cupón de descuento? Aplícalo aquí.
                                @endif
                            </p>
                            <form method="POST"
                                  action="{{ route('cart.coupon', ['locale' => $locale]) }}"
                                  class="cart-coupon-form">
                                @csrf
                                <input type="text"
                                       name="code"
                                       placeholder="Código de cupón"
                                       value="{{ $couponCode ?? '' }}"
                                       class="cart-coupon-input"
                                       aria-label="Código de cupón">
                                <button type="submit" class="cart-coupon-submit">Aplicar</button>
                            </form>
                        </div>

                        {{-- Links utilidad --}}
                        <div class="flex items-center justify-between mt-4">
                            <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                               class="text-sm font-semibold text-teal-700 hover:text-orange-500 transition">
                                &lsaquo; Seguir explorando
                            </a>
                            <form method="POST"
                                  action="{{ route('cart.clear', ['locale' => $locale]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-teal-800/50 hover:text-state-error underline"
                                        onclick="return confirm('¿Vaciar el carrito?')">
                                    Vaciar carrito
                                </button>
                            </form>
                        </div>

                    @endif

                    {{-- Venta cruzada (siempre visible) --}}
                    @if ($related->isNotEmpty())
                        <div class="cart-section-title-card">
                            <h3>Agregar más tours</h3>
                            <span>Recomendados</span>
                        </div>
                        <div class="cart-more-tours {{ $items->isEmpty() ? 'compact' : '' }}">
                            @foreach ($related as $relTour)
                                @php
                                    $relImg = $relTour->cover_url ?? asset('assets/banners/Rectangle 19210.jpg');
                                @endphp
                                <div class="cart-more-card">
                                    <img src="{{ $relImg }}"
                                         alt="{{ $relTour->title }}"
                                         width="160" height="96"
                                         loading="lazy">
                                    <div class="cart-more-body">
                                        @if ($relTour->region)
                                            <div class="cart-more-tag">{{ $relTour->region->name_es }}</div>
                                        @endif
                                        <h4>{{ $relTour->title }}</h4>
                                        <div class="cart-more-price">US${{ number_format($relTour->price, 0) }}</div>
                                        <div class="mt-auto">
                                            <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $relTour->slug]) }}"
                                               class="cart-add-btn"
                                               data-add-tour>
                                                Agregar tour
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>{{-- /columna principal --}}

                {{-- Aside resumen (solo visible con items) --}}
                @if ($items->isNotEmpty())
                    <aside class="cart-aside hidden lg:block lg:sticky lg:top-24 mt-6 lg:mt-0" aria-label="Resumen del pedido">
                        <h3>Resumen</h3>
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
                                        <span class="cart-aside-item-sub" data-aside-pax>x {{ $item['quantity'] }} personas</span>
                                    </div>
                                    <span class="cart-aside-item-price" data-aside-price>US${{ number_format($item['subtotal'], 0) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <dl class="cart-aside-dl">
                            <div class="cart-aside-row">
                                <dt class="cart-aside-label">Subtotal ({{ $items->count() }} {{ $items->count() === 1 ? 'tour' : 'tours' }})</dt>
                                <dd class="cart-aside-value" data-aside-subtotal>US${{ number_format($subtotal, 0) }}</dd>
                            </div>
                            <div class="cart-aside-row">
                                <dt class="cart-aside-label">Descuento{{ $couponCode ? ' (' . $couponCode . ')' : '' }}</dt>
                                <dd class="cart-aside-value ok" data-aside-discount>−US${{ number_format($discount, 0) }}</dd>
                            </div>
                        </dl>
                        <div class="cart-aside-total">
                            <span class="cart-aside-total-label">Total</span>
                            <span>
                                <span class="cart-aside-total-price" data-aside-total>US${{ number_format($total, 0) }}</span>
                                <span class="cart-aside-total-usd">USD</span>
                            </span>
                        </div>
                        <a href="{{ route('checkout.pay', ['locale' => $locale]) }}"
                           class="cart-cta-btn mt-4 block text-center" style="text-decoration:none">
                            Pasar por caja
                            <span class="cart-cta-arrow" aria-hidden="true">→</span>
                        </a>
                        <p class="text-xs text-center mt-2" style="color:var(--cart-muted)">Aceptamos VISA, Mastercard y PayPal</p>
                    </aside>
                @endif

            </div>{{-- /grid --}}

        </div>{{-- /screen carrito --}}

        {{-- ── PANTALLA: VER MIS RESERVAS ── --}}
        <div id="screen-reservas" class="cart-screen" data-screen="reservasGuardadas" role="tabpanel">
            <div class="cart-head">
                <h2>Reservas confirmadas</h2>
                <span class="cart-meta-link">0 activas</span>
            </div>
            <div class="cart-saved-wrap">
                {{-- Estado vacío de reservas confirmadas --}}
                <div class="cart-empty-state">
                    <div class="cart-empty-head">
                        <div class="cart-empty-icon" aria-hidden="true">✦</div>
                        <div>
                            <h3>Aún no tienes reservas confirmadas</h3>
                            <p>Cuando completes el pago de un tour, aquí podrás ver tus reservas, revisarlas y editar algunos datos si fuera necesario.</p>
                        </div>
                    </div>
                    <div class="cart-info-mini-grid">
                        <div class="cart-info-mini">
                            <small>Aquí verás</small>
                            <span>Fecha, horario e idioma del tour</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>También verás</small>
                            <span>Nombre, pasajeros y hotel o Airbnb</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>Estado</small>
                            <span>Confirmación, voucher y método de pago</span>
                        </div>
                        <div class="cart-info-mini">
                            <small>Edición</small>
                            <span>Podrás actualizar datos si lo necesitas</span>
                        </div>
                    </div>
                    <div class="cart-empty-actions">
                        <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="cart-btn-dark">Explorar tours</a>
                        <a href="mailto:info@limaviewtours.com" class="cart-btn-outline">Contactar soporte</a>
                    </div>
                </div>

                <div class="cart-ghost-history">
                    <div class="cart-head" style="margin-top:0">
                        <h2 style="font-size:18px">Historial de pago</h2>
                        <span class="cart-meta-link">Sin pagos</span>
                    </div>
                    <div class="cart-summary-row"><span>Reservas pagadas</span><b>0 tours</b></div>
                    <div class="cart-summary-row"><span>Estado</span><b>Pendiente</b></div>
                    <div class="cart-summary-row cart-total-row"><span>Total pagado</span><b>US$0</b></div>
                </div>
            </div>
        </div>{{-- /screen reservas --}}

    </div>{{-- /container --}}
</section>

{{-- Spacer para sticky footer --}}
@if ($items->isNotEmpty())
    <div class="cart-sticky-spacer" aria-hidden="true"></div>

    {{-- ── FOOTER STICKY ── --}}
    <div class="cart-sticky" id="cart-sticky-footer">
        <div class="cart-sticky-inner">
            <div>
                <div class="cart-total-label">Total</div>
                <div class="cart-total-price" data-total>US${{ number_format($total, 0) }}</div>
            </div>
            <a href="{{ route('checkout.pay', ['locale' => $locale]) }}"
               class="cart-cta-btn"
               id="cart-cta-main"
               data-pay-cta>
                Continuar
                <span class="cart-cta-arrow" aria-hidden="true">→</span>
            </a>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const locale = '{{ $locale }}';
    // Bases absolutas desde route() — incluyen el prefijo de subcarpeta (/limaprogramacion en staging).
    const cartBase = @json(route('cart.index', ['locale' => $locale]));
    const toursUrl = @json(route('tours.index', ['locale' => $locale]));

    // ── Helpers fetch AJAX ──────────────────────────────────────────
    async function patchCart(rowId, adults, children) {
        const url = `${cartBase}/${rowId}`;
        const r = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ adults, children }),
        });
        if (!r.ok) throw new Error('patch failed');
        return r.json();
    }

    async function deleteCartItem(rowId) {
        const url = `${cartBase}/${rowId}`;
        const r = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!r.ok) throw new Error('delete failed');
        return r.json();
    }

    // ── Estado en memoria (inicializado desde Blade) ────────────────
    const cartState = new Map();
    document.querySelectorAll('[data-cart-item]').forEach(card => {
        const rowId = card.dataset.rowId;
        cartState.set(rowId, {
            adults: parseInt(card.dataset.adults, 10),
            children: parseInt(card.dataset.children, 10),
            unitPrice: parseFloat(card.dataset.unitPrice),
        });
    });

    // Descuento (no cambia en cliente; viene del backend)
    const serverDiscount = {{ $discount }};

    // ── Recalcular totales ──────────────────────────────────────────
    function calcSubtotal() {
        let sub = 0;
        cartState.forEach(item => {
            sub += item.unitPrice * (item.adults + item.children);
        });
        return sub;
    }

    function updateTotalUI() {
        const sub = calcSubtotal();
        const total = Math.max(0, sub - serverDiscount);
        const fmt = v => 'US$' + Math.round(v);

        document.querySelectorAll('[data-total]').forEach(el => el.textContent = fmt(total));

        // Aside
        const asideSubtotal = document.querySelector('[data-aside-subtotal]');
        const asideTotal = document.querySelector('[data-aside-total]');
        if (asideSubtotal) asideSubtotal.textContent = fmt(sub);
        if (asideTotal) asideTotal.textContent = fmt(total);

        // Aside items
        document.querySelectorAll('[data-aside-item]').forEach(item => {
            const rowId = item.dataset.rowId;
            const state = cartState.get(rowId);
            if (!state) return;
            const qty = state.adults + state.children;
            const lineSub = state.unitPrice * qty;
            const priceEl = item.querySelector('[data-aside-price]');
            const paxEl = item.querySelector('[data-aside-pax]');
            if (priceEl) priceEl.textContent = fmt(lineSub);
            if (paxEl) paxEl.textContent = `x ${qty} persona${qty !== 1 ? 's' : ''}`;
        });

        // Cart count
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = cartState.size);
    }

    function updateCardPriceUI(card) {
        const rowId = card.dataset.rowId;
        const state = cartState.get(rowId);
        if (!state) return;
        const qty = state.adults + state.children;
        const lineSub = state.unitPrice * qty;
        const regularEl = card.querySelector('[data-item-regular]');
        if (regularEl) regularEl.textContent = 'US$' + Math.round(lineSub);
        // Actualizar meta line
        const adultsLabel = card.querySelector('[data-adults-label]');
        const childrenLabel = card.querySelector('[data-children-label]');
        if (adultsLabel) adultsLabel.textContent = state.adults;
        if (childrenLabel) childrenLabel.textContent = state.children;
    }

    // ── Steppers de cantidad ────────────────────────────────────────
    let debounceTimers = {};

    document.querySelectorAll('[data-cart-item]').forEach(card => {
        const rowId = card.dataset.rowId;

        card.querySelectorAll('[data-qty-btn]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.target; // 'adults' | 'children'
                const action = btn.dataset.qtyBtn; // 'plus' | 'minus'
                const state = cartState.get(rowId);
                if (!state) return;

                if (action === 'plus') {
                    state[target] = Math.min(20, state[target] + 1);
                } else {
                    const min = target === 'adults' ? 1 : 0;
                    state[target] = Math.max(min, state[target] - 1);
                }

                // Actualizar spans visuales
                card.querySelector('[data-adults-value]').textContent = state.adults;
                card.querySelector('[data-children-value]').textContent = state.children;

                // Deshabilitar botón minus en mínimos
                const minusAdults = card.querySelector('[data-qty-btn="minus"][data-target="adults"]');
                const minusChildren = card.querySelector('[data-qty-btn="minus"][data-target="children"]');
                if (minusAdults) minusAdults.disabled = state.adults <= 1;
                if (minusChildren) minusChildren.disabled = state.children <= 0;

                updateCardPriceUI(card);
                updateTotalUI();

                // Debounce PATCH al backend (500ms)
                clearTimeout(debounceTimers[rowId]);
                debounceTimers[rowId] = setTimeout(async () => {
                    try {
                        await patchCart(rowId, state.adults, state.children);
                    } catch (e) {
                        // Silencioso — el total visual ya se actualizó; el backend sincronizará en recarga
                    }
                }, 500);
            });
        });

        // Estado inicial de botones
        const state = cartState.get(rowId);
        if (state) {
            const minusAdults = card.querySelector('[data-qty-btn="minus"][data-target="adults"]');
            const minusChildren = card.querySelector('[data-qty-btn="minus"][data-target="children"]');
            if (minusAdults) minusAdults.disabled = state.adults <= 1;
            if (minusChildren) minusChildren.disabled = state.children <= 0;
        }
    });

    // ── Eliminar tour ───────────────────────────────────────────────
    document.querySelectorAll('[data-remove-btn]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const card = btn.closest('[data-cart-item]');
            const rowId = card.dataset.rowId;

            // Animación salida
            card.classList.add('removing');

            try {
                await deleteCartItem(rowId);
            } catch (e) {
                card.classList.remove('removing');
                return;
            }

            // Eliminar del DOM y del estado
            cartState.delete(rowId);

            // Eliminar aside item
            const asideItem = document.querySelector(`[data-aside-item][data-row-id="${rowId}"]`);
            if (asideItem) asideItem.remove();

            setTimeout(() => {
                card.remove();

                if (cartState.size === 0) {
                    // Mostrar estado vacío
                    showEmptyState();
                } else {
                    updateTotalUI();
                }
            }, 300);
        });
    });

    function showEmptyState() {
        const list = document.getElementById('cart-items-list');
        const stickyFooter = document.getElementById('cart-sticky-footer');
        const asideEl = document.querySelector('[aria-label="Resumen del pedido"]');

        if (list) list.remove();
        if (stickyFooter) stickyFooter.remove();
        if (asideEl) asideEl.remove();

        // Ocultar cupón y clear
        document.querySelectorAll('.cart-coupon-card, .flex.items-center.justify-between.mt-4').forEach(el => el.remove());

        // Insertar empty state
        const container = document.querySelector('.lg\\:grid');
        if (!container) return;
        const colPrincipal = container.querySelector('div');
        if (!colPrincipal) return;

        const head = colPrincipal.querySelector('.cart-head');

        const emptyHtml = `
            <div class="cart-empty-state" id="cart-empty-state">
                <div class="cart-empty-head">
                    <div class="cart-empty-icon" aria-hidden="true">🛒</div>
                    <div>
                        <h3>Tu carrito está vacío</h3>
                        <p>Aquí aparecerán los tours que agregues antes de completar tu reserva.</p>
                    </div>
                </div>
                <div class="cart-empty-pills" aria-hidden="true">
                    <span class="cart-empty-pill">Reserva rápida</span>
                    <span class="cart-empty-pill">Pago seguro</span>
                    <span class="cart-empty-pill">Confirmación inmediata</span>
                </div>
                <div class="cart-empty-actions">
                    <a href="${toursUrl}" class="cart-btn-dark">Explorar tours</a>
                </div>
            </div>
        `;

        if (head) head.insertAdjacentHTML('afterend', emptyHtml);

        // Actualizar count
        document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = '0');
    }

    // ── Switcher pantallas ──────────────────────────────────────────
    const screens = document.querySelectorAll('[data-screen]');
    const switchBtns = document.querySelectorAll('[data-screen-btn]');

    function setScreen(name) {
        screens.forEach(s => {
            const active = s.dataset.screen === name;
            s.classList.toggle('active', active);
        });
        switchBtns.forEach(b => {
            const active = b.dataset.screenBtn === name;
            b.classList.toggle('active', active);
            b.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    switchBtns.forEach(btn => {
        btn.addEventListener('click', () => setScreen(btn.dataset.screenBtn));
    });

    // ── "Agregar más tours" → enlaza a página del tour ─────────────
    // Los botones ya son <a> tags, solo marcamos como "visitado" visualmente
    document.querySelectorAll('[data-add-tour]').forEach(btn => {
        if (btn.tagName === 'A') {
            btn.addEventListener('click', () => {
                btn.classList.add('added');
                btn.textContent = 'Yendo al tour…';
            });
        }
    });

})();
</script>
@endpush

@endsection

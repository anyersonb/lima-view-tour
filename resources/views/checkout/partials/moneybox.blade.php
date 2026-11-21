{{--
    Caja de montos + regla de cancelación. Se incluye DOS veces (dentro de la
    sección 3 en móvil/tablet y en el resumen lateral en escritorio): ambas se
    actualizan por [data-co-*] desde public/js/checkout.js, nunca por id.
    Los importes iniciales salen del servidor ($checkout); JS solo los refresca.
--}}
@php
    $usd = fn ($n) => 'US$' . number_format((float) $n, 2, '.', ',');
    $m0 = $checkout['modes'][$checkout['default_mode']];
    $multi = $items->count() > 1 || (float) $checkout['discount'] > 0;
@endphp
<div class="co-moneybox" aria-live="polite">
    <div data-co-lines>
        @foreach ($items as $it)
            @php $n = (int) $it['adults'] + (int) ($it['children'] ?? 0); @endphp
            <div class="co-moneyline">
                <span>{{ $n }} {{ $n === 1 ? __('checkout_ui.adult_one') : __('checkout_ui.adult_many') }} × {{ $usd($it['unit_price']) }}</span>
                <strong>{{ $usd($it['subtotal']) }}</strong>
            </div>
        @endforeach
        @if ((float) $checkout['discount'] > 0)
            <div class="co-moneyline co-moneyline--ok">
                <span>{{ $couponCode ? __('checkout_ui.discount_line', ['code' => $couponCode]) : __('checkout_ui.discount_plain') }}</span>
                <strong>−{{ $usd($checkout['discount']) }}</strong>
            </div>
        @endif
    </div>
    <div class="co-moneyline" data-co-totalrow @unless ($multi) hidden @endunless>
        <span>{{ __('checkout_payment.total_label') }}</span>
        <strong data-co-total>{{ $usd($m0['total']) }}</strong>
    </div>
    <div class="co-moneyline" data-co-balancerow @if ($m0['balance'] <= 0) hidden @endif>
        <span data-co-balance-label>{{ __('checkout_payment.balance_label') }}</span>
        <strong data-co-balance>{{ $usd($m0['balance']) }}</strong>
    </div>
    <div class="co-moneyline co-moneyline--big">
        <span>{{ __('checkout_payment.pay_now_label') }}</span>
        <strong data-co-due>{{ $usd($m0['due']) }}</strong>
    </div>
</div>

<div class="co-highlight">
    <strong>
        <svg class="co-svg co-svg--sm" aria-hidden="true"><use href="#co-i-calendar"/></svg>
        {{ __('checkout_payment.cancellation_title') }}
    </strong>
    <p data-co-copy="full" hidden>{{ __('checkout_payment.cancellation_full') }}</p>
    <p data-co-copy="deposit">{{ __('checkout_payment.cancellation_deposit') }}</p>
    <p data-co-copy="later" hidden>{{ __('checkout_payment.cancellation_later') }}</p>
    <p data-co-copy="deposit" data-co-balance-info>{{ __('checkout_payment.balance_info') }}</p>
</div>

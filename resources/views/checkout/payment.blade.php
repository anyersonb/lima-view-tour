@extends('layouts.app')

@php
    $locale = app()->getLocale();
@endphp

@section('title', __('checkout.title') . ' — ' . __('seo.site_name'))
@section('description', 'Completa tu pago de forma segura con tarjeta de crédito o débito. Lima View Tours.')

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
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/80">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a>
            &gt;
            <a href="{{ route('cart.index', ['locale' => $locale]) }}" class="hover:text-orange-400">Carrito</a>
            &gt;
            <span>Pago</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-16 md:py-20 text-center">
        <h1 class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">{{ __('checkout.title') }}</h1>
        <p class="mt-4 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Completa tus datos y paga con tarjeta de crédito o débito de forma segura con Culqi.
        </p>
    </div>
</section>

{{-- ───────── CONTENIDO PAGO ───────── --}}
<section class="bg-cream-100 py-14 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">

        {{-- Flash errors --}}
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-state-error/10 border border-state-error/30 text-state-error px-5 py-3 text-sm font-semibold" role="alert">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-state-error/10 border border-state-error/30 text-state-error px-5 py-4 text-sm" role="alert">
                <p class="font-semibold mb-2">Por favor corrige los siguientes errores:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">

            {{-- Payment form --}}
            <div class="bg-white rounded-2xl p-6 lg:p-8 shadow-sm">
                <h2 class="font-display text-2xl text-teal-800 mb-6">Datos del viajero</h2>

                <form id="payment-form"
                      method="POST"
                      action="{{ route('checkout.process', ['locale' => $locale]) }}"
                      novalidate>
                    @csrf

                    {{-- Hidden Culqi token field --}}
                    <input type="hidden" name="culqi_token" id="culqi_token">

                    <div class="grid gap-5 md:grid-cols-2">
                        {{-- Name --}}
                        <div class="md:col-span-2">
                            <label for="customer_name" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                {{ __('checkout.customer_name') }} <span class="text-state-error">*</span>
                            </label>
                            <input type="text"
                                   id="customer_name"
                                   name="customer_name"
                                   value="{{ old('customer_name') }}"
                                   placeholder="Ej. María García López"
                                   autocomplete="name"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_name') border-state-error @enderror">
                            @error('customer_name')
                                <p class="mt-1 text-xs text-state-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="customer_email" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                {{ __('checkout.customer_email') }} <span class="text-state-error">*</span>
                            </label>
                            <input type="email"
                                   id="customer_email"
                                   name="customer_email"
                                   value="{{ old('customer_email') }}"
                                   placeholder="correo@ejemplo.com"
                                   autocomplete="email"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_email') border-state-error @enderror">
                            @error('customer_email')
                                <p class="mt-1 text-xs text-state-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label for="customer_phone" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                {{ __('checkout.customer_phone') }} <span class="text-state-error">*</span>
                            </label>
                            <input type="tel"
                                   id="customer_phone"
                                   name="customer_phone"
                                   value="{{ old('customer_phone') }}"
                                   placeholder="+51 999 999 999"
                                   autocomplete="tel"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_phone') border-state-error @enderror">
                            @error('customer_phone')
                                <p class="mt-1 text-xs text-state-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Travel date --}}
                        <div class="md:col-span-2">
                            <label for="travel_date" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                {{ __('checkout.travel_date') }} <span class="text-state-error">*</span>
                            </label>
                            <input type="date"
                                   id="travel_date"
                                   name="travel_date"
                                   value="{{ old('travel_date') }}"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-teal-600 @error('travel_date') border-state-error @enderror">
                            @error('travel_date')
                                <p class="mt-1 text-xs text-state-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- CTA --}}
                    <div class="mt-8 border-t border-teal-800/10 pt-6">
                        <p class="text-xs text-teal-800/55 mb-4 text-center">
                            Al hacer clic en "Pagar con tarjeta" serás dirigido a la pasarela de pago seguro de Culqi.
                        </p>
                        <button type="button"
                                id="btn-culqi-open"
                                class="btn--primary btn--block text-base py-4">
                            Pagar con tarjeta — ${{ number_format($total, 2) }} USD
                        </button>
                        <p class="mt-3 text-[11px] text-center text-teal-800/55">
                            Aceptamos VISA, Mastercard y AmEx &nbsp;·&nbsp; {{ __('checkout.secure_payment') }}
                        </p>
                    </div>
                </form>
            </div>

            {{-- Order summary sidebar --}}
            <aside class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm lg:sticky lg:top-24">
                <h3 class="font-display text-xl text-teal-800">{{ __('checkout.order_summary') }}</h3>

                <ul class="mt-5 space-y-3 text-sm border-b border-teal-800/10 pb-5">
                    @foreach ($items as $item)
                        <li class="flex items-start gap-3 text-teal-800/85">
                            <span class="flex-1 leading-snug">
                                {{ \Illuminate\Support\Str::limit($item['title_snapshot'], 40) }}
                                <span class="block text-xs text-teal-800/55">
                                    x {{ $item['quantity'] }} {{ $item['quantity'] === 1 ? 'persona' : 'personas' }}
                                    &nbsp;&middot;&nbsp;
                                    {{ \Carbon\Carbon::parse($item['travel_date'])->format('d M Y') }}
                                </span>
                            </span>
                            <span class="font-semibold whitespace-nowrap">${{ number_format($item['subtotal'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-teal-800/70">{{ __('checkout.subtotal') }}</dt>
                        <dd class="font-semibold text-teal-800">${{ number_format($subtotal, 2) }}</dd>
                    </div>
                    @if ($discount > 0)
                        <div class="flex justify-between">
                            <dt class="text-teal-800/70">{{ __('checkout.discount') }}{{ $couponCode ? ' (' . $couponCode . ')' : '' }}</dt>
                            <dd class="font-semibold text-state-success">−${{ number_format($discount, 2) }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-5 pt-5 border-t border-teal-800/10 flex justify-between items-baseline">
                    <span class="text-sm uppercase tracking-wide text-teal-800/70 font-semibold">{{ __('checkout.total') }}</span>
                    <span class="font-price text-3xl text-teal-800">
                        ${{ number_format($total, 2) }}<span class="text-xs text-teal-800/55 ml-1">USD</span>
                    </span>
                </div>

                <a href="{{ route('cart.index', ['locale' => $locale]) }}"
                   class="mt-6 inline-flex items-center gap-1 text-xs text-teal-800/60 hover:text-orange-500 transition">
                    &lsaquo; Volver al carrito
                </a>
            </aside>
        </div>
    </div>
</section>

@push('scripts')
<script src="https://checkout.culqi.com/js/v4"></script>
<script>
(function () {
    // Culqi configuration
    Culqi.publicKey = '{{ $public_key }}';

    Culqi.settings({
        title:    'Lima View Tours',
        currency: 'USD',
        amount:   {{ $total_centavos }},
        order:    '',
    });

    Culqi.options({
        lang:         'auto',
        installments: false,
        modal:        true,
        container:    '#payment-form',
        executeCulqi: true,
    });

    // Open Culqi modal on button click — validate form fields first
    document.getElementById('btn-culqi-open').addEventListener('click', function () {
        const form    = document.getElementById('payment-form');
        const name    = form.querySelector('#customer_name').value.trim();
        const email   = form.querySelector('#customer_email').value.trim();
        const phone   = form.querySelector('#customer_phone').value.trim();
        const date    = form.querySelector('#travel_date').value;

        if (!name || !email || !phone || !date) {
            alert('Por favor, completa todos los campos requeridos antes de continuar.');
            return;
        }

        Culqi.open();
    });

    // Culqi callback — receives the token after card tokenisation
    window.culqi = function () {
        if (Culqi.token) {
            document.getElementById('culqi_token').value = Culqi.token.id;
            document.getElementById('payment-form').submit();
        } else if (Culqi.error) {
            console.error('Culqi error:', Culqi.error);
            alert('Error al procesar la tarjeta: ' + (Culqi.error.user_message || 'Inténtalo de nuevo.'));
        }
    };
})();
</script>
@endpush

@endsection

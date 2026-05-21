@extends('layouts.app')

@php
    $locale = app()->getLocale();

    $firstTravelDate = optional(collect($items)->first())['travel_date'] ?? now()->addDays(7)->toDateString();
    $cancelDeadline = \Carbon\Carbon::parse($firstTravelDate)->subDay()->setTime(9, 0);
    $isPast = $cancelDeadline->isPast();

    $totalPen = round($total * 3.70, 2); // USD → PEN aprox para mostrar como Viator
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
            <div class="space-y-4" x-data="{ paymentTiming: 'now', paymentMethod: 'card' }">

                {{-- Cancellation policy banner --}}
                <div class="bg-white rounded-2xl p-5 lg:p-6 shadow-sm flex items-start gap-4 border-l-4 border-state-success">
                    <span class="w-10 h-10 rounded-full bg-state-success/10 grid place-items-center text-state-success shrink-0 mt-0.5" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div class="flex-1">
                        <p class="font-semibold text-teal-800 text-sm">Cancelación gratuita + reprogramación ilimitada</p>
                        <p class="text-xs text-teal-800/70 mt-1">Hasta las 9:00 del {{ $cancelDeadline->locale('es')->isoFormat('D [de] MMM, YYYY') }}.</p>
                    </div>
                    <a href="{{ route('cart.index', ['locale' => $locale]) }}" class="text-orange-600 text-xs font-semibold uppercase tracking-wide hover:text-orange-500 underline-offset-2 hover:underline shrink-0">Modificar</a>
                </div>

                <form id="payment-form"
                      method="POST"
                      action="{{ route('checkout.process', ['locale' => $locale]) }}"
                      novalidate
                      class="space-y-4">
                    @csrf
                    <input type="hidden" name="culqi_token" id="culqi_token">
                    <input type="hidden" name="payment_timing" :value="paymentTiming">
                    <input type="hidden" name="payment_method" :value="paymentMethod">

                    {{-- Main traveler --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <h2 class="font-display text-xl text-teal-800 mb-5 flex items-center gap-2">
                            <span class="w-2 h-6 bg-orange-500 rounded-full" aria-hidden="true"></span>
                            Viajero principal
                        </h2>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="customer_name" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                    {{ __('checkout.customer_name') }} <span class="text-state-error">*</span>
                                </label>
                                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}"
                                       placeholder="Ej. María García López" autocomplete="name"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_name') border-state-error @enderror">
                                @error('customer_name')<p class="mt-1 text-xs text-state-error">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="customer_email" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                    {{ __('checkout.customer_email') }} <span class="text-state-error">*</span>
                                </label>
                                <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}"
                                       placeholder="correo@ejemplo.com" autocomplete="email"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_email') border-state-error @enderror">
                                @error('customer_email')<p class="mt-1 text-xs text-state-error">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="customer_phone" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                    {{ __('checkout.customer_phone') }} <span class="text-state-error">*</span>
                                </label>
                                <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}"
                                       placeholder="+51 999 999 999" autocomplete="tel"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600 @error('customer_phone') border-state-error @enderror">
                                @error('customer_phone')<p class="mt-1 text-xs text-state-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="travel_date" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                                    {{ __('checkout.travel_date') }} <span class="text-state-error">*</span>
                                </label>
                                <input type="date" id="travel_date" name="travel_date"
                                       value="{{ old('travel_date', $firstTravelDate) }}"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-teal-600 @error('travel_date') border-state-error @enderror">
                                @error('travel_date')<p class="mt-1 text-xs text-state-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Pickup point --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <h2 class="font-display text-xl text-teal-800 mb-5 flex items-center gap-2">
                            <span class="w-2 h-6 bg-orange-500 rounded-full" aria-hidden="true"></span>
                            Punto de recogida
                        </h2>
                        <label for="pickup_point" class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">
                            Zona / dirección de tu hotel <span class="text-state-error">*</span>
                        </label>
                        <select id="pickup_point" name="pickup_point"
                                class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-teal-600">
                            <option value="">Selecciona tu zona</option>
                            <option {{ old('pickup_point') === 'Miraflores' ? 'selected' : '' }}>Miraflores</option>
                            <option {{ old('pickup_point') === 'San Isidro' ? 'selected' : '' }}>San Isidro</option>
                            <option {{ old('pickup_point') === 'Barranco' ? 'selected' : '' }}>Barranco</option>
                            <option {{ old('pickup_point') === 'Centro de Lima' ? 'selected' : '' }}>Centro de Lima</option>
                            <option {{ old('pickup_point') === 'Aeropuerto Jorge Chávez' ? 'selected' : '' }}>Aeropuerto Jorge Chávez</option>
                            <option {{ old('pickup_point') === 'Otro' ? 'selected' : '' }}>Otra zona (indicar abajo)</option>
                        </select>
                        <input type="text" name="pickup_detail" value="{{ old('pickup_detail') }}"
                               placeholder="Nombre del hotel, dirección o referencia"
                               class="mt-3 w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600">
                    </div>

                    {{-- Other details --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <h2 class="font-display text-xl text-teal-800 mb-5 flex items-center gap-2">
                            <span class="w-2 h-6 bg-orange-500 rounded-full" aria-hidden="true"></span>
                            Otros detalles
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">Idioma del tour</label>
                                <select name="tour_language" class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-teal-600">
                                    <option value="es" {{ old('tour_language') === 'es' ? 'selected' : '' }}>Español</option>
                                    <option value="en" {{ old('tour_language', 'en') === 'en' ? 'selected' : '' }}>Inglés</option>
                                    <option value="es-en" {{ old('tour_language') === 'es-en' ? 'selected' : '' }}>Bilingüe (Español + Inglés)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">Tipo de guía</label>
                                <select name="guide_type" class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-teal-600">
                                    <option value="group" {{ old('guide_type', 'group') === 'group' ? 'selected' : '' }}>Grupal (compartido)</option>
                                    <option value="private" {{ old('guide_type') === 'private' ? 'selected' : '' }}>Privado</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/70 mb-1.5">Comentarios adicionales (opcional)</label>
                                <textarea name="notes" rows="2" placeholder="Ej. Restricciones alimentarias, movilidad, edades de los niños…"
                                          class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-teal-600">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Payment info — when to pay --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <h2 class="font-display text-xl text-teal-800 mb-2 flex items-center gap-2">
                            <span class="w-2 h-6 bg-orange-500 rounded-full" aria-hidden="true"></span>
                            Información de pago
                        </h2>
                        <p class="text-sm text-teal-800/70 mb-4">Elige cuándo pagar</p>

                        <div class="grid gap-3">
                            <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                                   :class="paymentTiming === 'now' ? 'border-orange-500 bg-orange-50/40' : 'border-teal-800/15 hover:border-teal-800/30'">
                                <input type="radio" name="payment_timing_ui" value="now" x-model="paymentTiming"
                                       class="mt-1 text-orange-500 focus:ring-orange-400">
                                <div class="flex-1">
                                    <p class="font-semibold text-teal-800 text-sm">Pagar ahora</p>
                                    <p class="text-xs text-teal-800/65 mt-0.5">Confirma tu reserva al instante con tarjeta.</p>
                                </div>
                                <span class="font-price text-lg text-teal-800 whitespace-nowrap">${{ number_format($total, 2) }} <span class="text-xs text-teal-800/55">USD</span></span>
                            </label>

                            <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                                   :class="paymentTiming === 'later' ? 'border-orange-500 bg-orange-50/40' : 'border-teal-800/15 hover:border-teal-800/30'">
                                <input type="radio" name="payment_timing_ui" value="later" x-model="paymentTiming"
                                       class="mt-1 text-orange-500 focus:ring-orange-400">
                                <div class="flex-1">
                                    <p class="font-semibold text-teal-800 text-sm">Reservar ahora y pagar después</p>
                                    <p class="text-xs text-teal-800/65 mt-0.5">Sin cargos adicionales. Se te cobrará ${{ number_format($total, 2) }} USD el {{ $cancelDeadline->locale('es')->isoFormat('D [de] MMM') }}.</p>
                                </div>
                                <span class="font-price text-lg text-state-success whitespace-nowrap">$0,00 <span class="text-xs text-teal-800/55">ahora</span></span>
                            </label>
                        </div>
                    </div>

                    {{-- Payment method --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <h2 class="font-display text-xl text-teal-800 mb-4 flex items-center gap-2">
                            <span class="w-2 h-6 bg-orange-500 rounded-full" aria-hidden="true"></span>
                            Pagar con
                        </h2>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ([
                                ['card', 'Tarjeta', '💳', false],
                                ['yape', 'Yape', '🟣', true],
                                ['plin', 'Plin', '🔷', true],
                                ['paypal', 'PayPal', '🅿️', true],
                            ] as [$id, $label, $emoji, $soon])
                                <label class="relative flex flex-col items-center gap-1 p-3 rounded-xl border-2 cursor-pointer transition {{ $soon ? 'opacity-50 cursor-not-allowed' : '' }}"
                                       :class="paymentMethod === '{{ $id }}' ? 'border-orange-500 bg-orange-50/40' : 'border-teal-800/15 hover:border-teal-800/30'">
                                    <input type="radio" name="payment_method_ui" value="{{ $id }}" x-model="paymentMethod"
                                           {{ $soon ? 'disabled' : '' }}
                                           class="sr-only">
                                    <span class="text-2xl" aria-hidden="true">{{ $emoji }}</span>
                                    <span class="text-xs font-semibold text-teal-800">{{ $label }}</span>
                                    @if ($soon)
                                        <span class="absolute -top-2 -right-2 bg-orange-400 text-white text-[9px] uppercase tracking-wider rounded-full px-2 py-0.5">Próx.</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Terms + CTA --}}
                    <div class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm">
                        <label class="flex items-start gap-3 cursor-pointer text-xs text-teal-800/75 leading-relaxed">
                            <input type="checkbox" name="accept_terms" required value="1"
                                   class="mt-0.5 w-4 h-4 rounded border-teal-800/30 text-orange-500 focus:ring-orange-400 shrink-0">
                            <span>
                                Acepto los <a href="{{ route('legal.terms', ['locale' => $locale]) }}" class="text-orange-600 underline underline-offset-2">términos y condiciones</a>,
                                la <a href="{{ route('legal.privacy', ['locale' => $locale]) }}" class="text-orange-600 underline underline-offset-2">política de privacidad</a>
                                y la formalización del contrato directo con el proveedor.
                            </span>
                        </label>

                        <button type="button"
                                id="btn-culqi-open"
                                class="btn--primary btn--block mt-5 text-base py-4">
                            <span x-show="paymentTiming === 'now'">Pagar ahora — ${{ number_format($total, 2) }} USD</span>
                            <span x-show="paymentTiming === 'later'">Reservar ahora · pagar después</span>
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

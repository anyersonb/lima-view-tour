@extends('layouts.app')

@php
    $locale = app()->getLocale();
@endphp

@section('title', 'Carrito de compra — ' . __('seo.site_name'))
@section('description', 'Confirma tus reservas y procede al pago seguro. Lima View Tours.')

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
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a> &gt; <span>Carrito de compra</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-16 md:py-20 text-center">
        <h1 class="font-display text-4xl md:text-5xl lg:text-6xl leading-tight">Carrito de compra</h1>
        <p class="mt-4 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Revisa tus reservas, edita la cantidad de viajeros y procede al pago. Cancelación gratuita hasta 48 horas antes.
        </p>
    </div>
</section>

{{-- ───────── CONTENIDO CARRITO ───────── --}}
<section class="bg-cream-100 py-14 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <h2 class="font-display text-2xl text-teal-800 mb-6">Carrito de compras</h2>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-state-success/10 border border-state-success/30 text-state-success px-5 py-3 text-sm font-semibold" role="alert">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-state-error/10 border border-state-error/30 text-state-error px-5 py-3 text-sm font-semibold" role="alert">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->has('general'))
            <div class="mb-6 rounded-xl bg-state-error/10 border border-state-error/30 text-state-error px-5 py-3 text-sm font-semibold" role="alert">
                {{ $errors->first('general') }}
            </div>
        @endif

        @if ($items->isEmpty())
            {{-- Empty cart state --}}
            <div class="flex flex-col items-center justify-center py-24 text-center">
                <svg class="w-20 h-20 text-teal-800/20 mb-6" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.4 6M7 13l-1-4m12 4l1.4 6M17 17a2 2 0 11-4 0 2 2 0 014 0zM9 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="font-display text-2xl text-teal-800 mb-2">{{ __('cart.empty') }}</p>
                <p class="text-sm text-teal-800/65 mb-8">Explora nuestros tours y agrega los que más te gusten.</p>
                <a href="{{ route('tours.index', ['locale' => $locale]) }}" class="btn--primary">
                    Ver tours &rsaquo;
                </a>
            </div>
        @else
            <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">
                {{-- Items --}}
                <div class="space-y-5">
                    @foreach ($items as $item)
                        <article class="relative bg-white rounded-2xl p-5 lg:p-6 shadow-sm flex flex-col md:flex-row gap-5">
                            {{-- Cover image --}}
                            <div class="block shrink-0">
                                @php
                                    $cover = $item['cover_image'] ?? null;
                                    $imgSrc = $cover
                                        ? (str_starts_with($cover, 'http') ? $cover : asset($cover))
                                        : asset('assets/banners/Rectangle 19210.jpg');
                                @endphp
                                <img src="{{ $imgSrc }}"
                                     alt="{{ $item['title_snapshot'] }}"
                                     class="w-full md:w-44 h-32 md:h-32 object-cover rounded-xl" loading="lazy">
                            </div>

                            <div class="flex-1 flex flex-col">
                                <h3 class="font-display text-lg text-teal-800 leading-snug">{{ $item['title_snapshot'] }}</h3>
                                <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-teal-800/65">
                                    @if ($item['duration'] ?? null)
                                        <li>&#9202; {{ $item['duration'] }}</li>
                                    @endif
                                    @if ($item['language'] ?? null)
                                        <li>&#128100; {{ $item['language'] }}</li>
                                    @endif
                                    @if ($item['group_type'] ?? null)
                                        <li>&#128101; {{ $item['group_type'] }}</li>
                                    @endif
                                    <li>&#128197; {{ \Carbon\Carbon::parse($item['travel_date'])->format('d M Y') }}</li>
                                </ul>

                                <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                                    {{-- Quantity update form --}}
                                    <form method="POST"
                                          action="{{ route('cart.update', ['locale' => $locale, 'rowId' => $item['row_id']]) }}"
                                          class="flex items-center gap-3"
                                          id="form-update-{{ $item['row_id'] }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="adults"   value="{{ $item['adults'] }}">
                                        <input type="hidden" name="children" value="{{ $item['children'] }}">
                                        <span class="text-xs uppercase tracking-wide text-teal-800/65">Personas</span>
                                        <div class="flex items-center rounded-pill border border-teal-800/15">
                                            <button type="button"
                                                    class="w-9 h-9 grid place-items-center text-teal-800 hover:text-orange-500"
                                                    aria-label="Quitar una persona"
                                                    onclick="adjustQty('{{ $item['row_id'] }}', -1, {{ $item['adults'] }}, {{ $item['children'] }})">−</button>
                                            <span class="w-8 text-center text-sm font-semibold text-teal-800"
                                                  id="qty-{{ $item['row_id'] }}">{{ $item['quantity'] }}</span>
                                            <button type="button"
                                                    class="w-9 h-9 grid place-items-center text-teal-800 hover:text-orange-500"
                                                    aria-label="Agregar una persona"
                                                    onclick="adjustQty('{{ $item['row_id'] }}', 1, {{ $item['adults'] }}, {{ $item['children'] }})">+</button>
                                        </div>
                                    </form>

                                    <div class="text-right">
                                        <p class="font-price text-2xl text-teal-800 leading-none">
                                            ${{ number_format($item['unit_price'], 0) }}
                                            <span class="text-[10px] uppercase tracking-[0.2em] text-teal-800/55 font-sans block mt-1">POR PERSONA</span>
                                        </p>
                                        <p class="text-xs text-teal-800/65 mt-1">
                                            Subtotal: <strong>${{ number_format($item['subtotal'], 2) }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Remove button --}}
                            <div class="md:absolute md:top-4 md:right-4 self-end md:self-start">
                                <form method="POST"
                                      action="{{ route('cart.destroy', ['locale' => $locale, 'rowId' => $item['row_id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-teal-800/40 hover:text-state-error w-9 h-9 grid place-items-center rounded-full border border-teal-800/10"
                                            aria-label="Eliminar {{ $item['title_snapshot'] }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach

                    {{-- Coupon --}}
                    <div class="bg-teal-700 text-white rounded-2xl p-5 lg:p-6 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                        <p class="text-sm flex-1">
                            @if ($couponCode)
                                Cupón <strong>{{ $couponCode }}</strong> aplicado.
                            @else
                                ¿Tienes un cupón de descuento? Aplícalo aquí.
                            @endif
                        </p>
                        <form method="POST"
                              action="{{ route('cart.coupon', ['locale' => $locale]) }}"
                              class="flex gap-2 w-full sm:w-auto">
                            @csrf
                            <input type="text"
                                   name="code"
                                   placeholder="Ingresar cupón"
                                   value="{{ $couponCode ?? '' }}"
                                   class="rounded-pill bg-white/10 border border-white/30 placeholder-white/50 text-white px-5 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-400 flex-1 sm:w-56">
                            <button type="submit"
                                    class="rounded-pill bg-white text-teal-800 px-6 py-2.5 text-xs font-semibold uppercase tracking-wider hover:bg-cream-100 transition">
                                Aplicar
                            </button>
                        </form>
                    </div>

                    {{-- Clear cart --}}
                    <div class="flex items-center justify-between">
                        <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-teal-800 hover:text-orange-500">
                            &lsaquo; Seguir comprando
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
                </div>

                {{-- Summary sidebar --}}
                <aside class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm lg:sticky lg:top-24">
                    <h3 class="font-display text-xl text-teal-800">Resumen</h3>

                    <ul class="mt-5 space-y-3 text-sm border-b border-teal-800/10 pb-5">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-3 text-teal-800/85">
                                <span class="flex-1 leading-snug">
                                    {{ \Illuminate\Support\Str::limit($item['title_snapshot'], 40) }}
                                    <span class="block text-xs text-teal-800/55">x {{ $item['quantity'] }} personas</span>
                                </span>
                                <span class="font-semibold whitespace-nowrap">${{ number_format($item['subtotal'], 2) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-teal-800/70">Subtotal ({{ $items->count() }} {{ $items->count() === 1 ? 'tour' : 'tours' }})</dt>
                            <dd class="font-semibold text-teal-800">${{ number_format($subtotal, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-teal-800/70">Descuento{{ $couponCode ? ' (' . $couponCode . ')' : '' }}</dt>
                            <dd class="font-semibold text-state-success">−${{ number_format($discount, 2) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 pt-5 border-t border-teal-800/10 flex justify-between items-baseline">
                        <span class="text-sm uppercase tracking-wide text-teal-800/70 font-semibold">Total</span>
                        <span class="font-price text-3xl text-teal-800">
                            ${{ number_format($total, 2) }}<span class="text-xs text-teal-800/55 ml-1">USD</span>
                        </span>
                    </div>

                    <a href="#" data-checkout-pending
                       class="btn--primary btn--block mt-6">Pasar por caja</a>
                    <p class="mt-3 text-[11px] text-center text-teal-800/55">Aceptamos VISA, Mastercard, AmEx y PayPal</p>
                </aside>
            </div>
        @endif
    </div>
</section>

{{-- ───────── TOURS RECOMENDADOS ───────── --}}
@if ($related->isNotEmpty())
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <div class="text-center mb-10">
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">SIGUE EXPLORANDO</p>
            <h2 class="mt-3 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">Nuestros tours más comprados</h2>
        </div>
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($related as $relatedTour)
                <article class="relative rounded-2xl overflow-hidden min-h-[24rem] flex">
                    <img src="{{ $relatedTour->cover_url }}"
                         alt="{{ $relatedTour->title }}"
                         class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/15"></div>
                    <div class="relative w-full p-6 flex flex-col text-white">
                        @if ($relatedTour->region)
                            <span class="self-start bg-orange-500 text-[10px] font-semibold uppercase tracking-wider px-3 py-1 rounded">
                                {{ $relatedTour->region->name_es }}
                            </span>
                        @endif
                        <div class="mt-auto">
                            <h3 class="font-display text-xl leading-snug">{{ $relatedTour->title }}</h3>
                            <div class="mt-4 flex items-end justify-between">
                                <p class="font-price text-2xl">
                                    ${{ number_format($relatedTour->price, 0) }}
                                    <span class="block text-[10px] uppercase tracking-[0.2em] font-sans text-white/70 mt-1">POR PERSONA</span>
                                </p>
                                <a href="{{ route('tours.show', ['locale' => $locale, 'slug' => $relatedTour->slug]) }}"
                                   class="rounded-pill bg-white text-teal-800 px-4 py-2 text-xs font-semibold uppercase tracking-wider hover:bg-cream-100 transition">
                                    Ver tour
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@push('scripts')
<script>
// Quantity adjustor for cart items — submits PATCH form on change
function adjustQty(rowId, delta, adults, children) {
    const form = document.getElementById('form-update-' + rowId);
    if (!form) return;

    const totalQty = adults + children;
    const newQty   = Math.max(1, Math.min(40, totalQty + delta));
    if (newQty === totalQty) return;

    // Distribute delta into adults (keep children as-is if possible)
    const newAdults = Math.max(1, adults + delta);
    form.querySelector('input[name="adults"]').value   = newAdults;
    form.querySelector('input[name="children"]').value = children;

    document.getElementById('qty-' + rowId).textContent = newAdults + children;
    form.submit();
}
</script>
@endpush

@endsection

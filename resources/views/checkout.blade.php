@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $items = [
        [
            'title' => 'Tour de día Completo al Oasis de Huacachina + Islas Ballestas en Paracas',
            'img' => 'Rectangle 19210.jpg',
            'before' => 125,
            'price' => 100,
            'qty' => 2,
            'duration' => 'Full Day',
            'lang' => 'ES/EN',
            'group' => 'Grupal',
        ],
        [
            'title' => 'Las Enigmáticas Líneas de Nazca + el Oasis de Huacachina e Islas Ballestas',
            'img' => 'Rectangle 19212.jpg',
            'before' => 250,
            'price' => 200,
            'qty' => 2,
            'duration' => '2 Días',
            'lang' => 'ES/EN',
            'group' => 'Grupal',
        ],
    ];
    $subtotal = 0;
    foreach ($items as $it) { $subtotal += $it['price'] * $it['qty']; }
    $discount = 0;
    $total = $subtotal - $discount;

    $related = [
        ['Lima', 'Full Day Lima Ancestral, Colonial y Moderna', 'Rectangle 19211.jpg', 200],
        ['Cusco', 'Tour completo a Machu Picchu en tren', 'Rectangle 19217.jpg', 480],
        ['Ica', 'Líneas de Nazca + Oasis de Huacachina', 'Rectangle 19219.jpg', 300],
    ];
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

        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">
            {{-- Items --}}
            <div class="space-y-5">
                @foreach ($items as $i => $item)
                    <article class="bg-white rounded-2xl p-5 lg:p-6 shadow-sm flex flex-col md:flex-row gap-5">
                        <a href="#" class="block shrink-0">
                            <img src="{{ asset('assets/banners/' . $item['img']) }}" alt="{{ $item['title'] }}"
                                 class="w-full md:w-44 h-32 md:h-32 object-cover rounded-xl" loading="lazy">
                        </a>
                        <div class="flex-1 flex flex-col">
                            <h3 class="font-display text-lg text-teal-800 leading-snug">{{ $item['title'] }}</h3>
                            <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-teal-800/65">
                                <li>&#9202; {{ $item['duration'] }}</li>
                                <li>&#128100; {{ $item['lang'] }}</li>
                                <li>&#128101; {{ $item['group'] }}</li>
                            </ul>

                            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs uppercase tracking-wide text-teal-800/65">Personas</span>
                                    <div class="flex items-center rounded-pill border border-teal-800/15">
                                        <button type="button" class="w-9 h-9 grid place-items-center text-teal-800 hover:text-orange-500" aria-label="Quitar">−</button>
                                        <span class="w-8 text-center text-sm font-semibold text-teal-800">{{ $item['qty'] }}</span>
                                        <button type="button" class="w-9 h-9 grid place-items-center text-teal-800 hover:text-orange-500" aria-label="Agregar">+</button>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p class="text-xs text-teal-800/55 line-through">${{ $item['before'] }}</p>
                                    <p class="font-price text-2xl text-teal-800 leading-none">${{ $item['price'] }}<span class="text-[10px] uppercase tracking-[0.2em] text-teal-800/55 font-sans block mt-1">POR PERSONA</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="md:absolute md:top-4 md:right-4 self-end md:self-start">
                            <button type="button" class="text-teal-800/40 hover:text-state-error w-9 h-9 grid place-items-center rounded-full border border-teal-800/10" aria-label="Eliminar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </article>
                @endforeach

                {{-- Cupón --}}
                <div class="bg-teal-700 text-white rounded-2xl p-5 lg:p-6 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                    <p class="text-sm flex-1">¿Tienes un cupón de descuento? Aplícalo aquí.</p>
                    <form class="flex gap-2 w-full sm:w-auto">
                        <input type="text" name="cupon" placeholder="Ingresar cupón"
                               class="rounded-pill bg-white/10 border border-white/30 placeholder-white/50 text-white px-5 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-400 flex-1 sm:w-56">
                        <button type="submit" class="rounded-pill bg-white text-teal-800 px-6 py-2.5 text-xs font-semibold uppercase tracking-wider hover:bg-cream-100 transition">Aplicar</button>
                    </form>
                </div>

                <a href="{{ route('tours.index', ['locale' => $locale]) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-teal-800 hover:text-orange-500">
                    &lsaquo; Seguir comprando
                </a>
            </div>

            {{-- Resumen --}}
            <aside class="bg-white rounded-2xl p-6 lg:p-7 shadow-sm lg:sticky lg:top-24">
                <h3 class="font-display text-xl text-teal-800">Resumen</h3>

                <ul class="mt-5 space-y-3 text-sm border-b border-teal-800/10 pb-5">
                    @foreach ($items as $item)
                        <li class="flex items-start gap-3 text-teal-800/85">
                            <span class="flex-1 leading-snug">{{ \Illuminate\Support\Str::limit($item['title'], 40) }} <span class="block text-xs text-teal-800/55">x {{ $item['qty'] }} personas</span></span>
                            <span class="font-semibold whitespace-nowrap">${{ number_format($item['price'] * $item['qty'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-teal-800/70">Subtotal ({{ count($items) }} tours)</dt><dd class="font-semibold text-teal-800">${{ number_format($subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-teal-800/70">Descuento</dt><dd class="font-semibold text-state-success">−${{ number_format($discount, 2) }}</dd></div>
                </dl>

                <div class="mt-5 pt-5 border-t border-teal-800/10 flex justify-between items-baseline">
                    <span class="text-sm uppercase tracking-wide text-teal-800/70 font-semibold">Total</span>
                    <span class="font-price text-3xl text-teal-800">${{ number_format($total, 2) }}<span class="text-xs text-teal-800/55 ml-1">USD</span></span>
                </div>

                <a href="#" class="btn--primary btn--block mt-6">Pasar por caja</a>
                <p class="mt-3 text-[11px] text-center text-teal-800/55">Aceptamos VISA, Mastercard, AmEx y PayPal</p>
            </aside>
        </div>
    </div>
</section>

{{-- ───────── TOURS RECOMENDADOS ───────── --}}
<section class="bg-white py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10">
        <header class="text-center mb-10">
            <p class="text-[11px] uppercase tracking-[0.2em] text-teal-800/70 font-semibold">SIGUE EXPLORANDO</p>
            <h2 class="mt-3 font-display text-3xl md:text-4xl lg:text-5xl text-teal-800 leading-tight">Nuestros tours más comprados</h2>
        </header>
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($related as [$cat,$title,$img,$price])
                <article class="relative rounded-2xl overflow-hidden min-h-[24rem] flex">
                    <img src="{{ asset('assets/banners/' . $img) }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/15"></div>
                    <div class="relative w-full p-6 flex flex-col text-white">
                        <span class="self-start bg-orange-500 text-[10px] font-semibold uppercase tracking-wider px-3 py-1 rounded">{{ $cat }}</span>
                        <div class="mt-auto">
                            <h3 class="font-display text-xl leading-snug">{{ $title }}</h3>
                            <div class="mt-4 flex items-end justify-between">
                                <p class="font-price text-2xl">${{ $price }}<span class="block text-[10px] uppercase tracking-[0.2em] font-sans text-white/70 mt-1">POR PERSONA</span></p>
                                <a href="#" class="rounded-pill bg-white text-teal-800 px-4 py-2 text-xs font-semibold uppercase tracking-wider hover:bg-cream-100 transition">Ver tour</a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

@endsection

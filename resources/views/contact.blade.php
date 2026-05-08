@extends('layouts.app')

@section('title', 'Contáctanos — ' . __('seo.site_name'))
@section('description', 'Estamos aquí para resolver tus dudas. Contáctanos y planifica tu próxima aventura por Perú.')

@php $locale = app()->getLocale(); @endphp

@section('content')

{{-- Hero --}}
<section class="relative isolate text-white">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19216.jpg') }}" alt="" class="w-full h-full object-cover" loading="eager">
        <div class="absolute inset-0 bg-teal-900/60"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="pt-6 text-xs text-white/85">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400">Inicio</a> &gt; <span>Contáctanos</span>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 py-20 md:py-28 text-center">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold opacity-90">RESOLVEMOS TUS DUDAS</p>
        <h1 class="mt-4 font-display text-5xl md:text-6xl lg:text-7xl leading-[1.05]">Contáctanos</h1>
        <p class="mt-5 mx-auto max-w-2xl text-sm md:text-base text-white/85">
            Vive una aventura inolvidable por los destinos más impresionantes del Perú. Desde Machu Picchu hasta la Huacachina, nuestros tours están diseñados para que disfrutes lo mejor del país con seguridad, comodidad y guías expertos.
        </p>
    </div>
</section>

{{-- Form + collage --}}
<section class="bg-cream-100 py-16 lg:py-20">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-2 items-center">
        {{-- Collage --}}
        <div class="relative h-[26rem] md:h-[32rem]">
            <img src="{{ asset('assets/banners/Rectangle 19210.jpg') }}" alt="" class="absolute top-0 left-0 w-44 md:w-56 h-44 md:h-56 object-cover rounded-2xl shadow-md" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19211.jpg') }}" alt="" class="absolute top-4 right-10 w-44 md:w-56 h-44 md:h-56 object-cover rounded-2xl shadow-md" loading="lazy">
            <img src="{{ asset('assets/banners/Rectangle 19212.jpg') }}" alt="" class="absolute bottom-12 left-6 w-44 md:w-52 h-44 md:h-52 object-cover rounded-2xl shadow-md" loading="lazy">
            <img src="{{ asset('assets/images/personas.png') }}" alt="" class="absolute bottom-0 right-0 w-56 md:w-64 h-56 md:h-64 object-cover rounded-2xl shadow-md" loading="lazy">
        </div>

        {{-- Form --}}
        <form action="{{ route('contact.submit', ['locale' => $locale]) }}" method="post" class="bg-white rounded-2xl p-7 lg:p-10 shadow-sm">
            @csrf
            {{-- Honeypot: must remain empty; bots fill it automatically --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
                   aria-hidden="true">
            <header class="flex items-center gap-4 mb-6">
                <span class="w-12 h-12 rounded-full border border-teal-800/20 grid place-items-center text-teal-700">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <h2 class="font-display text-3xl text-teal-800 leading-tight">Formulario de<br>contacto</h2>
            </header>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs uppercase tracking-wide text-teal-800/70">Nombre</span>
                    <input type="text" name="nombre" required placeholder="Johanna"
                           class="mt-1 w-full rounded-pill border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                </label>
                <label class="block">
                    <span class="text-xs uppercase tracking-wide text-teal-800/70">Apellido</span>
                    <input type="text" name="apellido" required placeholder="Escribe tu apellido"
                           class="mt-1 w-full rounded-pill border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                </label>
            </div>
            <label class="block mt-4">
                <span class="text-xs uppercase tracking-wide text-teal-800/70">Número celular</span>
                <input type="tel" name="celular" required placeholder="Escribe tu número celular"
                       class="mt-1 w-full rounded-pill border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
            </label>
            <label class="block mt-4">
                <span class="text-xs uppercase tracking-wide text-teal-800/70">Correo electrónico</span>
                <input type="email" name="email" required placeholder="Escribe tu correo electrónico"
                       class="mt-1 w-full rounded-pill border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
            </label>
            <label class="block mt-4">
                <span class="text-xs uppercase tracking-wide text-teal-800/70">Mensaje</span>
                <textarea name="mensaje" required rows="3" placeholder="Escribe tu mensaje"
                          class="mt-1 w-full rounded-2xl border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400"></textarea>
            </label>
            <label class="mt-5 flex items-start gap-2 text-xs text-teal-800/75">
                <input type="checkbox" required class="mt-0.5 rounded border-teal-800/30 text-orange-500 focus:ring-orange-400">
                <span>Acepto la <a href="#" class="text-orange-500 underline">política de privacidad</a> y los <a href="#" class="text-orange-500 underline">términos y condiciones</a></span>
            </label>
            <button type="submit" class="btn--primary mt-6">Enviar mensaje</button>
        </form>
    </div>
</section>

{{-- Three contact methods --}}
<section class="bg-white py-14">
    <div class="container mx-auto px-5 lg:px-10 grid gap-10 md:grid-cols-3 text-center">
        @foreach ([
            ['email','Email','We will reply within 2 working days','HELLO@FLOW.COM','mailto:hello@flow.com'],
            ['phone','Phone','We are available Monday-Friday from 8 AM until 5 PM','(239) 555-0108','tel:+12395550108'],
            ['pin','HQ Office','Visit us in our office','3891 RANCHVIEW DR. RICHARDSON, CALIFORNIA 62639','#'],
        ] as [$icon,$title,$line1,$value,$href])
            <div>
                <span class="mx-auto w-12 h-12 rounded-full bg-cream-100 grid place-items-center text-teal-700 mb-3">
                    @switch($icon)
                        @case('email')<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6m-18 0V18a2 2 0 002 2h14a2 2 0 002-2V8m-18 0a2 2 0 012-2h14a2 2 0 012 2"/></svg>@break
                        @case('phone')<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>@break
                        @case('pin')<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>@break
                    @endswitch
                </span>
                <h3 class="font-display text-xl text-teal-800">{{ $title }}</h3>
                <p class="mt-2 text-xs text-teal-800/70 max-w-xs mx-auto">{{ $line1 }}</p>
                <a href="{{ $href }}" class="mt-2 inline-block text-orange-500 text-sm font-semibold tracking-wide">{{ $value }}</a>
            </div>
        @endforeach
    </div>
</section>
@endsection

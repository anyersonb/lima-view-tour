@extends('layouts.app')

@section('title', 'Contáctanos — ' . __('seo.site_name'))
@section('description', 'Estamos aquí para resolver tus dudas. Contáctanos y planifica tu próxima aventura por Perú.')

@php
    $locale = app()->getLocale();
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 925 886 725');
    $contactPhoneTel = str_replace([' ', '+'], '', $contactPhone);
@endphp

@section('content')

{{-- Hero --}}
<section class="relative isolate text-white min-h-[55vh] flex flex-col justify-end" aria-labelledby="contact-hero-title">
    <div class="absolute inset-0 -z-10">
        <img src="{{ asset('assets/banners/Rectangle 19216.jpg') }}" alt=""
             class="w-full h-full object-cover object-center" loading="eager" fetchpriority="high"
             width="1440" height="800">
        <div class="absolute inset-0 bg-gradient-to-b from-teal-900/25 via-teal-900/50 to-teal-900/75"></div>
    </div>
    <div class="container mx-auto px-5 lg:px-10">
        <nav aria-label="Breadcrumb" class="py-5 text-xs text-white/75">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home', ['locale' => $locale]) }}" class="hover:text-orange-400 transition-colors">Inicio</a></li>
                <li aria-hidden="true" class="text-white/50">/</li>
                <li aria-current="page">Contáctanos</li>
            </ol>
        </nav>
    </div>
    <div class="container mx-auto px-5 lg:px-10 pb-16 md:pb-24 text-center">
        <p class="text-[11px] uppercase tracking-[0.25em] font-semibold text-white/80">RESOLVEMOS TUS DUDAS</p>
        <h1 id="contact-hero-title" class="mt-3 font-display text-5xl md:text-6xl lg:text-7xl leading-[1.05]">Contáctanos</h1>
        <p class="mt-5 mx-auto max-w-2xl text-sm md:text-base text-white/85 leading-relaxed">
            Vive una aventura inolvidable por los destinos más impresionantes del Perú. Desde Machu Picchu hasta la Huacachina, nuestros tours están diseñados para que disfrutes lo mejor del país con seguridad, comodidad y guías expertos.
        </p>
    </div>
</section>

{{-- Form + collage --}}
<section class="bg-cream-100 py-16 lg:py-20" aria-labelledby="contact-form-title">
    <div class="container mx-auto px-5 lg:px-10 grid gap-12 lg:grid-cols-2 items-start">

        {{-- Collage — layout irregular tipo mockup --}}
        <div class="relative h-[30rem] md:h-[34rem] lg:h-[38rem] hidden sm:block" aria-hidden="true">
            {{-- Imagen grande izquierda arriba --}}
            <img src="{{ asset('assets/banners/Rectangle 19210.jpg') }}" alt=""
                 class="absolute top-0 left-0 w-[48%] h-[46%] object-cover rounded-2xl shadow-md"
                 loading="lazy" width="300" height="260">
            {{-- Imagen pequeña centro-arriba --}}
            <img src="{{ asset('assets/banners/Rectangle 19211.jpg') }}" alt=""
                 class="absolute top-[8%] right-0 w-[46%] h-[38%] object-cover rounded-2xl shadow-md"
                 loading="lazy" width="280" height="220">
            {{-- Imagen grande izquierda abajo --}}
            <img src="{{ asset('assets/images/personas.png') }}" alt=""
                 class="absolute bottom-0 left-0 w-[52%] h-[52%] object-cover object-top rounded-2xl shadow-md"
                 loading="lazy" width="320" height="300">
            {{-- Imagen derecha abajo --}}
            <img src="{{ asset('assets/banners/Rectangle 19212.jpg') }}" alt=""
                 class="absolute bottom-[6%] right-0 w-[44%] h-[44%] object-cover rounded-2xl shadow-md"
                 loading="lazy" width="270" height="260">
        </div>

        {{-- Form --}}
        <form action="{{ route('contact.submit', ['locale' => $locale]) }}" method="post"
              id="form-contact"
              class="bg-white rounded-2xl p-7 lg:p-9 shadow-sm"
              novalidate>
            @csrf
            {{-- Honeypot --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
                   aria-hidden="true">

            <div class="flex items-start gap-4 mb-7">
                <x-icon-compass class="w-10 h-10 lg:w-12 lg:h-12 text-teal-700 shrink-0 mt-1" />
                <h2 id="contact-form-title" class="font-display text-3xl lg:text-4xl text-teal-800 leading-tight">
                    Formulario de<br>contacto
                </h2>
            </div>

            @if (session('success'))
                <div role="alert" class="mb-5 bg-state-success/10 border border-state-success/30 text-state-success rounded-xl px-5 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-5 bg-state-error/10 border border-state-error/30 text-state-error rounded-xl px-5 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">Nombre</span>
                    <input type="text" name="nombre" required
                           placeholder="Johanna"
                           value="{{ old('nombre') }}"
                           class="mt-1.5 w-full rounded-pill border border-teal-800/20 bg-cream-100/50 px-5 py-3 text-sm text-teal-800 placeholder:text-teal-800/35 focus:border-orange-400 focus:ring-1 focus:ring-orange-400 focus:outline-none transition">
                </label>
                <label class="block">
                    <span class="text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">Apellido</span>
                    <input type="text" name="apellido" required
                           placeholder="Escribe tu apellido"
                           value="{{ old('apellido') }}"
                           class="mt-1.5 w-full rounded-pill border border-teal-800/20 bg-cream-100/50 px-5 py-3 text-sm text-teal-800 placeholder:text-teal-800/35 focus:border-orange-400 focus:ring-1 focus:ring-orange-400 focus:outline-none transition">
                </label>
            </div>

            <label class="block mt-4">
                <span class="text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">Número celular</span>
                <input type="tel" name="celular" required
                       placeholder="Escribe tu número celular"
                       value="{{ old('celular') }}"
                       class="mt-1.5 w-full rounded-pill border border-teal-800/20 bg-cream-100/50 px-5 py-3 text-sm text-teal-800 placeholder:text-teal-800/35 focus:border-orange-400 focus:ring-1 focus:ring-orange-400 focus:outline-none transition">
            </label>

            <label class="block mt-4">
                <span class="text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">Correo electrónico</span>
                <input type="email" name="email" required
                       placeholder="Escribe tu correo electrónico"
                       value="{{ old('email') }}"
                       class="mt-1.5 w-full rounded-pill border border-teal-800/20 bg-cream-100/50 px-5 py-3 text-sm text-teal-800 placeholder:text-teal-800/35 focus:border-orange-400 focus:ring-1 focus:ring-orange-400 focus:outline-none transition">
            </label>

            <label class="block mt-4">
                <span class="text-[11px] uppercase tracking-[0.15em] text-teal-800/60 font-semibold">Mensaje</span>
                <textarea name="mensaje" required rows="4"
                          placeholder="Escribe tu mensaje aquí..."
                          class="mt-1.5 w-full rounded-2xl border border-teal-800/20 bg-cream-100/50 px-5 py-3 text-sm text-teal-800 placeholder:text-teal-800/35 focus:border-orange-400 focus:ring-1 focus:ring-orange-400 focus:outline-none transition resize-none">{{ old('mensaje') }}</textarea>
            </label>

            <label class="mt-4 flex items-start gap-2.5 text-xs text-teal-800/70 cursor-pointer">
                <input type="checkbox" required
                       class="mt-0.5 w-4 h-4 rounded border-teal-800/30 text-orange-500 focus:ring-orange-400 focus:ring-offset-0 shrink-0">
                <span>
                    Acepto la
                    <a href="{{ route('legal.privacy', ['locale' => $locale]) }}" class="text-orange-600 underline underline-offset-2 hover:text-orange-500 transition-colors">política de privacidad</a>
                    y los
                    <a href="{{ route('legal.terms', ['locale' => $locale]) }}" class="text-orange-600 underline underline-offset-2 hover:text-orange-500 transition-colors">términos y condiciones</a>
                </span>
            </label>

            @include('partials.recaptcha', ['recaptchaAction' => 'contact', 'recaptchaFormId' => 'form-contact'])

            <button type="submit" class="btn--primary btn--block mt-6 !py-4">Enviar mensaje</button>
        </form>
    </div>
</section>

{{-- Three contact methods --}}
<section class="bg-white py-14 lg:py-16" aria-labelledby="contact-methods-title">
    <h2 id="contact-methods-title" class="sr-only">Canales de contacto</h2>
    <div class="container mx-auto px-5 lg:px-10 grid gap-8 sm:grid-cols-3 text-center">
        @foreach ([
            ['email','Email','We are available during the day','RESERVAS@LIMAVIEWTOURS.COM','mailto:reservas@limaviewtours.com',
                '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>'],
            ['phone','Phone','We are available Mon-Sun from 9:00 a.m. – 6:30 p.m.',$contactPhone,'tel:' . $contactPhoneTel,
                '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>'],
            ['pin','My Office','Visit us in our office','JR. LAMPA 209 LIMA CENTER','https://maps.google.com/?q=Jr.+Lampa+209,+Lima',
                '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>'],
        ] as [$iconKey,$title,$line1,$value,$href,$iconPath])
            <div class="flex flex-col items-center">
                <span class="w-14 h-14 rounded-full bg-cream-100 grid place-items-center text-teal-700 mb-4" aria-hidden="true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        {!! $iconPath !!}
                    </svg>
                </span>
                <h3 class="font-display text-xl text-teal-800">{{ $title }}</h3>
                <p class="mt-2 text-xs text-teal-800/65 max-w-[14rem]">{{ $line1 }}</p>
                <a href="{{ $href }}"
                   class="mt-3 text-orange-600 text-sm font-semibold tracking-wide hover:text-orange-500 transition-colors underline-offset-2 hover:underline">
                    {{ $value }}
                </a>
            </div>
        @endforeach
    </div>
</section>
@endsection

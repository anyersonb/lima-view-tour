@props([
    'id' => 'site-popup',
    'image' => 'banners/Rectangle 19216.jpg',
    'eyebrow' => 'Newsletter',
    'title' => 'Suscríbete y obtén 10% de descuento',
    'description' => 'Recibe nuestras mejores ofertas, nuevos tours y consejos de viaje directamente en tu correo.',
])

<div id="{{ $id }}"
     x-data="{ show: false }"
     x-init="setTimeout(() => show = true, 8000)"
     x-show="show" x-cloak
     x-transition.opacity
     class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-teal-900/60 backdrop-blur-sm"
     role="dialog" aria-modal="true" :aria-labelledby="'{{ $id }}-title'">
    <div @click.outside="show = false"
         class="relative w-full max-w-3xl bg-white rounded-2xl overflow-hidden grid md:grid-cols-2 shadow-2xl">
        <button type="button" @click="show = false"
                class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 text-teal-800 grid place-items-center hover:bg-cream-100"
                aria-label="Cerrar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img src="{{ asset('assets/' . $image) }}" alt="" class="w-full h-56 md:h-full object-cover" loading="lazy">
        <div class="p-8 lg:p-10 flex flex-col">
            <p class="text-[11px] uppercase tracking-[0.25em] text-orange-500 font-semibold">{{ $eyebrow }}</p>
            <h2 id="{{ $id }}-title" class="mt-2 font-display text-3xl text-teal-800 leading-tight">{{ $title }}</h2>
            <p class="mt-3 text-sm text-teal-800/75 leading-relaxed">{{ $description }}</p>
            <form action="{{ route('newsletter.subscribe') }}" method="post" class="mt-5 space-y-3">
                @csrf
                {{-- Honeypot: must remain empty; bots fill it automatically --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off"
                       style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
                       aria-hidden="true">
                <input type="email" name="email" required placeholder="Tu correo electrónico"
                       class="w-full rounded-pill border border-teal-800/20 px-5 py-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                <button type="submit" class="btn--primary btn--block">Suscribirme</button>
            </form>
        </div>
    </div>
</div>

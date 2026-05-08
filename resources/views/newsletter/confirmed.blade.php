@extends('layouts.app')

@section('title', __('newsletter.confirmed_title', [], $locale ?? app()->getLocale()) . ' — ' . __('seo.site_name'))

@section('content')
<section class="min-h-[60vh] flex items-center justify-center bg-cream-100 py-20">
    <div class="container mx-auto px-5 text-center max-w-xl">
        <span class="mx-auto w-16 h-16 rounded-full bg-teal-100 grid place-items-center text-teal-700 mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </span>
        @php $locale = $locale ?? app()->getLocale(); @endphp
        @if($locale === 'en')
            <h1 class="font-display text-4xl text-teal-800">Subscription confirmed!</h1>
            <p class="mt-4 text-teal-800/75">
                You are now subscribed to the Lima View Tours newsletter. Get ready to discover the best of Peru!
            </p>
            <a href="{{ route('home', ['locale' => 'en']) }}" class="btn--primary mt-8 inline-block">Back to home</a>
        @else
            <h1 class="font-display text-4xl text-teal-800">¡Suscripción confirmada!</h1>
            <p class="mt-4 text-teal-800/75">
                Ya estás suscrito al boletín de Lima View Tours. ¡Prepárate para descubrir lo mejor del Perú!
            </p>
            <a href="{{ route('home', ['locale' => 'es']) }}" class="btn--primary mt-8 inline-block">Volver al inicio</a>
        @endif
    </div>
</section>
@endsection

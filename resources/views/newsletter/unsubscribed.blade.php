@extends('layouts.app')

@section('title', 'Desuscripción — ' . __('seo.site_name'))

@section('content')
<section class="min-h-[60vh] flex items-center justify-center bg-cream-100 py-20">
    <div class="container mx-auto px-5 text-center max-w-xl">
        <span class="mx-auto w-16 h-16 rounded-full bg-orange-100 grid place-items-center text-orange-500 mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
            </svg>
        </span>
        <h1 class="font-display text-4xl text-teal-800">Te has desuscrito</h1>
        <p class="mt-4 text-teal-800/75">
            Hemos eliminado tu dirección de correo de nuestra lista de suscriptores. No recibirás más correos de Lima View Tours.
        </p>
        <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="btn--primary mt-8 inline-block">Volver al inicio</a>
    </div>
</section>
@endsection

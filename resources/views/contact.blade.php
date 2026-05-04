@extends('layouts.app')

@section('title', __('nav.contact') . ' | ' . __('seo.site_name'))

@section('content')
<section class="container mx-auto py-12">
    <h1>{{ __('nav.contact') }}</h1>
    <address class="not-italic mt-4 text-slate-600 space-y-1">
        <p>Lima, Perú</p>
        <p><a href="mailto:hola@limaviewtours.com">hola@limaviewtours.com</a></p>
    </address>
</section>
@endsection

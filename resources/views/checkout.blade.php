@extends('layouts.app')

@section('title', 'Checkout | ' . __('seo.site_name'))

@push('head')
<meta name="robots" content="noindex,nofollow">
@endpush

@section('content')
<section class="container mx-auto py-12">
    <h1>Checkout</h1>
    <p class="mt-4 text-slate-600">{{-- TODO: integración Culqi + maqueta Figma --}}</p>
</section>
@endsection

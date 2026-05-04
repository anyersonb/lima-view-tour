@extends('layouts.app')

@section('title', __('seo.tours_title'))
@section('description', __('seo.tours_description'))

@section('content')
<section class="container mx-auto py-12">
    <h1>{{ __('nav.tours') }}</h1>
    <p class="mt-4 text-slate-600">{{-- TODO: maquetar desde Figma --}}</p>
</section>
@endsection

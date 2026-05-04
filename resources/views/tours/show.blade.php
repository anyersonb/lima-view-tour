@extends('layouts.app')

@section('title', $slug . ' | ' . __('seo.site_name'))

@section('content')
<article class="container mx-auto py-12">
    <h1>{{ $slug }}</h1>
    <p class="mt-4 text-slate-600">{{-- TODO: maquetar desde Figma --}}</p>
</article>
@endsection

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $supported = config('app.supported_locales', ['es', 'en']);
    $locale = request()->getPreferredLanguage($supported) ?: config('app.locale');
    return redirect("/{$locale}");
});

Route::prefix('{locale}')
    ->where(['locale' => 'es|en'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', fn () => view('home'))->name('home');
        Route::get('/tours', fn () => view('tours.index'))->name('tours.index');
        Route::get('/tours/{slug}', fn ($locale, $slug) => view('tours.show', compact('slug')))->name('tours.show');
        Route::get('/checkout', fn () => view('checkout'))->name('checkout');
        Route::get('/contacto', fn () => view('contact'))->name('contact');
    });

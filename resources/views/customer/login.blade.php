@extends('layouts.app')

@section('title')
    @php $locale = app()->getLocale(); $L = fn($es,$en,$pt) => $locale==='pt'?$pt:($locale==='en'?$en:$es); @endphp
    {{ $L('Iniciar sesión', 'Log in', 'Entrar') }} — Lima View Tours
@endsection

@section('content')
@php
    $locale = app()->getLocale();
    $L = fn($es,$en,$pt) => $locale==='pt'?$pt:($locale==='en'?$en:$es);
@endphp

<section class="min-h-[70vh] bg-cream-100 flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md">

        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-lg overflow-hidden">

            {{-- Header teal --}}
            <div class="bg-teal-800 px-8 py-7 text-center">
                <h1 class="font-display text-2xl text-white tracking-wide">
                    {{ $L('Bienvenido de vuelta', 'Welcome back', 'Bem-vindo de volta') }}
                </h1>
                <p class="text-cream-100/80 text-sm mt-1">
                    {{ $L('Accede a tu cuenta para ver tus reservas', 'Sign in to view your bookings', 'Acesse sua conta para ver suas reservas') }}
                </p>
            </div>

            <div class="px-8 py-8">

                {{-- Alerts --}}
                @if (session('success'))
                    <div class="mb-5 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.login.post', ['locale' => $locale]) }}" id="form-login" novalidate>
                    @csrf

                    <div class="space-y-5">
                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.email') }}
                            </label>
                            <input type="email" id="email" name="email"
                                   value="{{ old('email') }}"
                                   autocomplete="email"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition @error('email') border-red-400 @enderror"
                                   placeholder="tu@correo.com"
                                   required>
                        </div>

                        {{-- Password --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="password" class="block text-sm font-semibold text-teal-800">
                                    {{ __('customer.password') }}
                                </label>
                                <a href="{{ route('customer.password.request', ['locale' => $locale]) }}"
                                   class="text-xs text-orange-500 hover:underline">
                                    {{ __('customer.forgot_password') }}
                                </a>
                            </div>
                            <input type="password" id="password" name="password"
                                   autocomplete="current-password"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition"
                                   required>
                        </div>

                        {{-- Remember --}}
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="remember" name="remember"
                                   class="w-4 h-4 rounded border-teal-800/30 text-teal-700 focus:ring-teal-600">
                            <label for="remember" class="text-sm text-teal-700">
                                {{ __('customer.remember_me') }}
                            </label>
                        </div>
                    </div>

                    @include('partials.recaptcha', ['recaptchaAction' => 'login', 'recaptchaFormId' => 'form-login'])

                    <button type="submit"
                            class="mt-7 w-full rounded-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold py-3.5 text-sm transition">
                        {{ __('customer.login') }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-teal-700">
                    {{ __('customer.no_account') }}
                    <a href="{{ route('customer.register', ['locale' => $locale]) }}"
                       class="font-semibold text-orange-500 hover:underline ml-1">
                        {{ __('customer.register') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

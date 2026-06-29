@extends('layouts.app')

@section('title')
    @php $locale = app()->getLocale(); $L = fn($es,$en,$pt) => $locale==='pt'?$pt:($locale==='en'?$en:$es); @endphp
    {{ $L('Crear cuenta', 'Create account', 'Criar conta') }} — Lima View Tours
@endsection

@section('content')
@php
    $locale = app()->getLocale();
    $L = fn($es,$en,$pt) => $locale==='pt'?$pt:($locale==='en'?$en:$es);
@endphp

<section class="min-h-[70vh] bg-cream-100 flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-lg overflow-hidden">

            <div class="bg-teal-800 px-8 py-7 text-center">
                <h1 class="font-display text-2xl text-white tracking-wide">
                    {{ $L('Crea tu cuenta', 'Create your account', 'Crie sua conta') }}
                </h1>
                <p class="text-cream-100/80 text-sm mt-1">
                    {{ $L('Gestiona tus reservas fácilmente', 'Manage your bookings easily', 'Gerencie suas reservas facilmente') }}
                </p>
            </div>

            <div class="px-8 py-8">

                @if ($errors->any())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.register.post', ['locale' => $locale]) }}" id="form-register" novalidate>
                    @csrf

                    <div class="space-y-5">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.name') }}
                            </label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name') }}"
                                   autocomplete="name"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition @error('name') border-red-400 @enderror"
                                   required>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.email') }}
                            </label>
                            <input type="email" id="email" name="email"
                                   value="{{ old('email') }}"
                                   autocomplete="email"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition @error('email') border-red-400 @enderror"
                                   required>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.phone') }}
                            </label>
                            <input type="tel" id="phone" name="phone"
                                   value="{{ old('phone') }}"
                                   autocomplete="tel"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 placeholder-teal-800/40 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition @error('phone') border-red-400 @enderror">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.password') }}
                            </label>
                            <input type="password" id="password" name="password"
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition"
                                   required>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-semibold text-teal-800 mb-1.5">
                                {{ __('customer.password_confirmation') }}
                            </label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-4 py-3 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition"
                                   required>
                        </div>
                    </div>

                    @include('partials.recaptcha', ['recaptchaAction' => 'register', 'recaptchaFormId' => 'form-register'])

                    <button type="submit"
                            class="mt-7 w-full rounded-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold py-3.5 text-sm transition">
                        {{ __('customer.register') }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-teal-700">
                    {{ __('customer.already_have_account') }}
                    <a href="{{ route('customer.login', ['locale' => $locale]) }}"
                       class="font-semibold text-orange-500 hover:underline ml-1">
                        {{ __('customer.login') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

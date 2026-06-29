@extends('layouts.app')

@section('title')
    {{ __('customer.my_account') }} — Lima View Tours
@endsection

@section('content')
@php
    $locale   = app()->getLocale();
    $L        = fn($es,$en,$pt) => $locale==='pt'?$pt:($locale==='en'?$en:$es);
    $customer = auth('customer')->user();

    $statusLabels = [
        'pending'   => __('customer.status_pending'),
        'confirmed' => __('customer.status_confirmed'),
        'cancelled' => __('customer.status_cancelled'),
        'completed' => __('customer.status_completed'),
    ];
    $statusColors = [
        'pending'   => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'completed' => 'bg-teal-100 text-teal-800',
    ];
@endphp

<section class="bg-cream-100 min-h-screen py-12 px-4">
    <div class="max-w-5xl mx-auto">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 px-5 py-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Page heading --}}
        <div class="mb-8">
            <h1 class="font-display text-3xl text-teal-800">{{ __('customer.my_account') }}</h1>
            <p class="text-teal-700/70 mt-1 text-sm">{{ $L('Hola', 'Hello', 'Olá') }}, <strong>{{ $customer->name }}</strong></p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">

            {{-- ── Sidebar: perfil ── --}}
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-sm p-6" x-data="{ editing: false }">

                    <div class="flex items-center justify-between mb-5">
                        <h2 class="font-semibold text-teal-800 text-base">{{ __('customer.edit_profile') }}</h2>
                        <button @click="editing = !editing"
                                class="text-xs font-semibold text-orange-500 hover:underline"
                                :aria-expanded="editing.toString()">
                            <span x-text="editing ? '{{ $L('Cancelar', 'Cancel', 'Cancelar') }}' : '{{ $L('Editar', 'Edit', 'Editar') }}'"></span>
                        </button>
                    </div>

                    {{-- View mode --}}
                    <div x-show="!editing" class="space-y-3 text-sm">
                        <div>
                            <span class="text-teal-700/60 block text-xs">{{ __('customer.name') }}</span>
                            <span class="font-medium text-teal-800">{{ $customer->name }}</span>
                        </div>
                        <div>
                            <span class="text-teal-700/60 block text-xs">{{ __('customer.email') }}</span>
                            <span class="font-medium text-teal-800">{{ $customer->email }}</span>
                        </div>
                        @if ($customer->phone)
                        <div>
                            <span class="text-teal-700/60 block text-xs">{{ __('customer.phone') }}</span>
                            <span class="font-medium text-teal-800">{{ $customer->phone }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Edit form --}}
                    <div x-show="editing" x-cloak>
                        @if ($errors->any())
                            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-800">
                                <ul class="space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('customer.profile.update', ['locale' => $locale]) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="name" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.name') }}</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition" required>
                            </div>
                            <div>
                                <label for="profile_email" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.email') }}</label>
                                <input type="email" id="profile_email" name="email" value="{{ old('email', $customer->email) }}"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition" required>
                            </div>
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.phone') }}</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition">
                            </div>

                            <hr class="border-teal-800/10">

                            <div>
                                <label for="current_password" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.current_password') }}</label>
                                <input type="password" id="current_password" name="current_password"
                                       autocomplete="current-password"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition">
                            </div>
                            <div>
                                <label for="new_password" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.new_password') }}</label>
                                <input type="password" id="new_password" name="password"
                                       autocomplete="new-password"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition">
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-xs font-semibold text-teal-800 mb-1">{{ __('customer.password_confirmation') }}</label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       autocomplete="new-password"
                                       class="w-full rounded-xl border border-teal-800/20 bg-cream-100/40 px-3 py-2.5 text-sm text-teal-800 focus:border-teal-600 focus:ring-1 focus:ring-teal-600 outline-none transition">
                            </div>

                            <button type="submit"
                                    class="w-full rounded-full bg-teal-800 hover:bg-teal-900 text-white text-sm font-semibold py-3 transition">
                                {{ __('customer.save_changes') }}
                            </button>
                        </form>
                    </div>

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('customer.logout', ['locale' => $locale]) }}" class="mt-5">
                        @csrf
                        <button type="submit" class="w-full text-center text-xs text-teal-700/60 hover:text-red-600 transition">
                            {{ __('customer.logout') }}
                        </button>
                    </form>
                </div>
            </aside>

            {{-- ── Main: reservas ── --}}
            <main class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm p-6">
                    <h2 class="font-semibold text-teal-800 text-base mb-5">{{ __('customer.my_bookings') }}</h2>

                    @if ($bookings->isEmpty())
                        <p class="text-sm text-teal-700/60 py-6 text-center">{{ __('customer.no_bookings') }}</p>
                    @else
                        {{-- Desktop table --}}
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-teal-800/10 text-left text-xs font-semibold text-teal-700/60 uppercase tracking-wide">
                                        <th class="pb-3 pr-4">{{ __('customer.booking_reference') }}</th>
                                        <th class="pb-3 pr-4">{{ __('customer.booking_tour') }}</th>
                                        <th class="pb-3 pr-4">{{ __('customer.booking_date') }}</th>
                                        <th class="pb-3 pr-4">{{ __('customer.booking_pax') }}</th>
                                        <th class="pb-3 pr-4">{{ __('customer.booking_total') }}</th>
                                        <th class="pb-3">{{ __('customer.booking_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-teal-800/5">
                                    @foreach ($bookings as $booking)
                                        <tr>
                                            <td class="py-3.5 pr-4 font-mono text-xs text-teal-700">{{ $booking->reference }}</td>
                                            <td class="py-3.5 pr-4 font-medium text-teal-800 max-w-[160px] truncate">{{ $booking->tour_title_snapshot }}</td>
                                            <td class="py-3.5 pr-4 text-teal-700 whitespace-nowrap">{{ \Carbon\Carbon::parse($booking->travel_date)->locale($locale)->isoFormat('D MMM YYYY') }}</td>
                                            <td class="py-3.5 pr-4 text-teal-700">{{ $booking->total_pax }}</td>
                                            <td class="py-3.5 pr-4 font-semibold text-teal-800">US$ {{ number_format($booking->total_price, 2) }}</td>
                                            <td class="py-3.5">
                                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                    {{ $statusLabels[$booking->status] ?? $booking->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile cards --}}
                        <div class="sm:hidden space-y-4">
                            @foreach ($bookings as $booking)
                                <div class="rounded-2xl border border-teal-800/10 p-4 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-medium text-teal-800 text-sm leading-snug">{{ $booking->tour_title_snapshot }}</span>
                                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusLabels[$booking->status] ?? $booking->status }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-teal-700/70 space-y-0.5">
                                        <div><span class="font-medium">{{ __('customer.booking_reference') }}:</span> {{ $booking->reference }}</div>
                                        <div><span class="font-medium">{{ __('customer.booking_date') }}:</span> {{ \Carbon\Carbon::parse($booking->travel_date)->locale($locale)->isoFormat('D MMM YYYY') }}</div>
                                        <div><span class="font-medium">{{ __('customer.booking_pax') }}:</span> {{ $booking->total_pax }}</div>
                                        <div><span class="font-medium">{{ __('customer.booking_total') }}:</span> US$ {{ number_format($booking->total_price, 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        @if ($bookings->hasPages())
                            <div class="mt-6">
                                {{ $bookings->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </main>

        </div>
    </div>
</section>
@endsection

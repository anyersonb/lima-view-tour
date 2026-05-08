@component('mail::message')

@if ($locale === 'en')
# Booking Confirmed!

Hello **{{ $bookings->first()->customer_name }}**,

Thank you for booking with **Lima View Tours**. Your payment has been confirmed and your reservation is ready.

@else
# ¡Reserva confirmada!

Hola **{{ $bookings->first()->customer_name }}**,

Gracias por reservar con **Lima View Tours**. Tu pago ha sido confirmado y tu reserva está lista.

@endif

---

@if ($locale === 'en')
## Your Bookings
@else
## Detalle de tus reservas
@endif

@component('mail::table')
| @if($locale === 'en') Tour @else Tour @endif | @if($locale === 'en') Date @else Fecha @endif | @if($locale === 'en') Travelers @else Viajeros @endif | @if($locale === 'en') Total @else Total @endif | @if($locale === 'en') Reference @else Referencia @endif |
|:------|:----------:|:----------:|----------:|:----------:|
@foreach ($bookings as $booking)
| {{ $booking->tour_title_snapshot }} | {{ \Carbon\Carbon::parse($booking->travel_date)->format('d M Y') }} | {{ $booking->adults + $booking->children }} | {{ $booking->currency }} {{ number_format($booking->total_price, 2) }} | `{{ $booking->reference }}` |
@endforeach
@endcomponent

@if ($locale === 'en')
Please save your reference number(s) — you will need them to identify your booking.
@else
Guarda tu(s) número(s) de referencia — los necesitarás para identificar tu reserva.
@endif

@php
    $contactEmail = \App\Models\Setting::get('contact_email', 'info@limaviewtours.com');
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 999 999 999');
@endphp

---

@if ($locale === 'en')
**Need help?** Write to us at [{{ $contactEmail }}](mailto:{{ $contactEmail }}) or call us at {{ $contactPhone }}.
@else
**¿Necesitas ayuda?** Escríbenos a [{{ $contactEmail }}](mailto:{{ $contactEmail }}) o llámanos al {{ $contactPhone }}.
@endif

@component('mail::button', ['url' => url('/'.($locale === 'en' ? 'en' : 'es').'/tours'), 'color' => 'success'])
@if ($locale === 'en') Explore More Tours @else Ver más tours @endif
@endcomponent

@if ($locale === 'en')
Thanks,
**Lima View Tours Team**
@else
Gracias,
**Equipo Lima View Tours**
@endif

@endcomponent

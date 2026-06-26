@component('mail::message')

@php
    $first     = $bookings->first();
    $locale    = $first?->locale ?? app()->getLocale();
    $payingNow = $first?->payment_method === 'culqi';
@endphp

@if ($locale === 'en')
# Booking Confirmed!

Hello **{{ $first->customer_name }}**,

@if ($payingNow)
Thank you for booking with **Lima View Tours**. Your payment has been confirmed and your reservation is ready.
@else
Thank you for booking with **Lima View Tours**. Your reservation has been received. Our team will contact you to arrange payment before your tour date.
@endif

@else
# ¡Reserva confirmada!

Hola **{{ $first->customer_name }}**,

@if ($payingNow)
Gracias por reservar con **Lima View Tours**. Tu pago ha sido confirmado y tu reserva está lista.
@else
Gracias por reservar con **Lima View Tours**. Hemos recibido tu reserva. Nuestro equipo se pondrá en contacto contigo para coordinar el pago antes de la fecha del tour.
@endif

@endif

---

@if ($locale === 'en')
## Your Booking Details
@else
## Detalle de tus reservas
@endif

@component('mail::table')
| @if($locale === 'en') Tour @else Tour @endif | @if($locale === 'en') Date @else Fecha @endif | @if($locale === 'en') Adults @else Adultos @endif | @if($locale === 'en') Children @else Niños @endif | @if($locale === 'en') Total @else Total @endif | @if($locale === 'en') Reference @else Referencia @endif |
|:------|:-----------:|:---:|:---:|----------:|:----------:|
@foreach ($bookings as $booking)
| {{ $booking->tour_title_snapshot }} | {{ \Carbon\Carbon::parse($booking->travel_date)->format('d M Y') }} | {{ $booking->adults }} | {{ $booking->children }} | {{ $booking->currency }} {{ number_format($booking->total_price, 2) }} | `{{ $booking->reference }}` |
@endforeach
@endcomponent

@if ($locale === 'en')
Please save your reference number(s) — you will need them to identify your booking.
@else
Guarda tu(s) número(s) de referencia — los necesitarás para identificar tu reserva.
@endif

---

@if ($first->pickup_point)
@if ($locale === 'en')
**Pickup point:** {{ $first->pickup_point }}{{ $first->pickup_detail ? ' — ' . $first->pickup_detail : '' }}
@else
**Punto de recogida:** {{ $first->pickup_point }}{{ $first->pickup_detail ? ' — ' . $first->pickup_detail : '' }}
@endif

---
@endif

@if ($locale === 'en')
**Payment method:** {{ $payingNow ? 'Paid by card' : 'Pay later (pending)' }}
@else
**Modo de pago:** {{ $payingNow ? 'Pagado con tarjeta' : 'Pagar después (pendiente)' }}
@endif

---

@php
    $contactEmail = \App\Models\Setting::get('contact_email', 'info@limaviewtours.com');
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 999 999 999');
@endphp

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

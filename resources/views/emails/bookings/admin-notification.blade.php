@component('mail::message')

# Nueva reserva recibida

@php
    $first          = $bookings->first();
    $payLabel       = $paymentTiming === 'now' ? 'Pago inmediato (tarjeta)' : 'Reservar y pagar después';
    $statusLabel    = $first->status === 'confirmed' ? 'Confirmada' : 'Pendiente';
    $payStatusLabel = $first->payment_status === 'paid' ? 'Pagado' : 'Pendiente de pago';
@endphp

---

## Datos del cliente

| Campo       | Valor |
|:------------|:------|
| **Nombre**  | {{ $first->customer_name }} |
| **Email**   | {{ $first->customer_email }} |
| **Teléfono**| {{ $first->customer_phone ?? '—' }} |

---

## Detalle de la(s) reserva(s)

@component('mail::table')
| Ref. | Tour | Fecha de viaje | Adultos | Niños | Total | Estado |
|:-----|:-----|:--------------:|:-------:|:-----:|------:|:------:|
@foreach ($bookings as $booking)
| `{{ $booking->reference }}` | {{ $booking->tour_title_snapshot }} | {{ \Carbon\Carbon::parse($booking->travel_date)->format('d/m/Y') }} | {{ $booking->adults }} | {{ $booking->children }} | {{ $booking->currency }} {{ number_format($booking->total_price, 2) }} | {{ $booking->status }} |
@endforeach
@endcomponent

---

## Punto de recogida

@if ($first->pickup_point)
**Zona:** {{ $first->pickup_point }}
@if ($first->pickup_detail)

**Detalle:** {{ $first->pickup_detail }}
@endif
@else
No especificado.
@endif

---

## Información de pago

| Campo             | Valor |
|:------------------|:------|
| **Modo de pago**  | {{ $payLabel }} |
| **Estado de pago**| {{ $payStatusLabel }} |
@if ($first->payment_reference)
| **Referencia Culqi** | `{{ $first->payment_reference }}` |
@endif

---

@component('mail::button', ['url' => config('app.url') . '/admin/bookings', 'color' => 'primary'])
Ver reservas en el panel
@endcomponent

Este es un aviso automático de **Lima View Tours**.

@endcomponent

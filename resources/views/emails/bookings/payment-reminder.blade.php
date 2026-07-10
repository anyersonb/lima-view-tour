@php
    $locale = $locale ?? ($bookings->first()?->locale ?? app()->getLocale());
    $L = fn (string $es, string $en): string => $locale === 'en' ? $en : $es;
    $first        = $bookings->first();
    $grandTotal   = (float) $bookings->sum('total_price');
    $currency     = $first?->currency ?? 'USD';
    $cur          = $currency === 'USD' ? 'US$' : $currency . ' ';
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $wa           = preg_replace('/\D/', '', \App\Models\Setting::get('whatsapp') ?: $contactPhone);
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $toursUrl     = url('/' . ($locale === 'en' ? 'en' : 'es') . '/tours');
    $fallbackImg  = asset('assets/banners/banner-hero.jpg');
    $travelDate   = $first?->travel_date ? \Carbon\Carbon::parse($first->travel_date) : null;
    $daysLeft     = $travelDate ? max(0, now()->startOfDay()->diffInDays($travelDate->copy()->startOfDay())) : null;
    $waText       = $L(
        "Hola, quiero completar el pago de mi reserva {$first?->reference} de Lima View Tours.",
        "Hi, I'd like to complete the payment for my Lima View Tours booking {$first?->reference}."
    );
    $waUrl = 'https://wa.me/' . $wa . '?text=' . rawurlencode($waText);
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Recordatorio de pago', 'Payment reminder') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $L('Tu tour está cerca y el pago sigue pendiente', 'Your tour is coming up and payment is still pending') }} · {{ $first?->reference }}
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;">

    {{-- Marca --}}
    <tr><td style="background:linear-gradient(135deg,#052f32,#0a4a4d);background-color:#0a4a4d;padding:22px;text-align:center;color:#fff;">
        <img src="{{ asset('assets/logos/logo-gold.png') }}" alt="Lima View Tours" width="200" height="80" style="width:200px;height:auto;display:inline-block;border:0;">
    </td></tr>

    {{-- Hero --}}
    <tr><td style="padding:26px 28px 6px;">
        <span style="display:inline-block;background:#fbe9d6;color:#b0560d;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">{{ $L('PAGO PENDIENTE', 'PAYMENT PENDING') }}</span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Hola, ', 'Hi, ') }}{{ $first?->customer_name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            @if ($daysLeft !== null && $daysLeft > 0)
                {{ $L('Tu tour es en', 'Your tour is in') }}
                <b style="color:#b0560d;">{{ $daysLeft }} {{ $daysLeft == 1 ? $L('día', 'day') : $L('días', 'days') }}</b>
                {{ $L('y el pago de tu reserva aún está pendiente. Complétalo para asegurar tu lugar.', 'and your booking payment is still pending. Complete it to secure your spot.') }}
            @else
                {{ $L('El pago de tu reserva aún está pendiente. Complétalo cuanto antes para asegurar tu lugar.', 'Your booking payment is still pending. Please complete it as soon as possible to secure your spot.') }}
            @endif
        </p>
    </td></tr>

    {{-- Banner monto pendiente --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#b0560d;border-radius:16px;">
            <tr>
                <td style="padding:15px 18px;color:#fff;font-size:16px;font-weight:bold;">
                    {{ $L('Monto pendiente de pago', 'Amount pending') }}
                </td>
                <td align="right" style="padding:15px 18px;">
                    <span style="display:inline-block;background:#fff;color:#b0560d;border-radius:999px;padding:8px 13px;font-weight:bold;font-size:14px;white-space:nowrap;">
                        {{ $cur }}{{ number_format($grandTotal, 2) }}
                    </span>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Una tarjeta por reserva --}}
    @foreach ($bookings as $booking)
        @php
            $img = optional($booking->tour)->cover_url ?: $fallbackImg;
            $pax = (int) $booking->adults + (int) $booking->children;
        @endphp
        <tr><td style="padding:18px 28px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ece6dc;border-radius:16px;overflow:hidden;">
                <tr>
                    <td width="120" style="padding:14px 0 14px 14px;vertical-align:top;">
                        <img src="{{ $img }}" alt="" width="110" height="80" style="width:110px;height:80px;border-radius:12px;object-fit:cover;display:block;">
                    </td>
                    <td style="padding:14px;vertical-align:top;">
                        <div style="font-size:11px;color:#9a631e;font-weight:bold;letter-spacing:.4px;">{{ $L('TOUR RESERVADO', 'BOOKED TOUR') }}</div>
                        <div style="font-family:Georgia,serif;font-size:17px;color:#0b3035;margin-top:3px;line-height:1.2;">{{ $booking->tour_title_snapshot }}</div>
                        <div style="margin-top:6px;font-size:13px;color:#0b7c56;font-weight:bold;">{{ $L('Código', 'Code') }}: {{ $booking->reference }}</div>
                    </td>
                </tr>
                <tr><td colspan="2" style="padding:0 14px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#153d46;">
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Fecha del tour', 'Tour date') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ \Carbon\Carbon::parse($booking->travel_date)->locale($locale)->isoFormat('D MMM YYYY') }}</td></tr>
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Pasajeros', 'Passengers') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->adults }} {{ $L('adulto(s)', 'adult(s)') }}@if($booking->children > 0) · {{ $booking->children }} {{ $L('niño(s)', 'child(ren)') }}@endif</td></tr>
                        @if ($booking->hasDiscount())
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Descuento aplicado', 'Discount applied') }}@if($booking->discount_type === 'percent') ({{ rtrim(rtrim(number_format($booking->discount_value, 2), '0'), '.') }}%)@endif</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;color:#b0560d;">− {{ $cur }}{{ number_format($booking->discount_amount, 2) }}</td></tr>
                        @endif
                        <tr><td style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">{{ $L('Subtotal', 'Subtotal') }} ({{ $pax }})</td><td align="right" style="padding:11px 0;border-top:1px solid #ece6dc;color:#b0560d;font-weight:bold;font-size:16px;">{{ $cur }}{{ number_format($booking->total_price, 2) }}</td></tr>
                        @if ($booking->custom_tour_details)
                        <tr><td colspan="2" style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;font-size:13px;line-height:1.5;"><b style="color:#0b3035;">{{ $L('Detalle del tour', 'Tour details') }}:</b> {{ $booking->custom_tour_details }}</td></tr>
                        @endif
                    </table>
                </td></tr>
            </table>
        </td></tr>
    @endforeach

    {{-- Cómo pagar --}}
    <tr><td style="padding:18px 28px 4px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#edf7f1;border:1px solid #e4eadf;border-radius:16px;">
            <tr>
                <td width="48" style="padding:18px 0 18px 18px;vertical-align:top;font-size:30px;color:#0c7354;">&#128179;</td>
                <td style="padding:18px;">
                    <div style="font-family:Georgia,serif;font-size:18px;color:#0b3035;margin-bottom:5px;">{{ $L('¿Cómo completar el pago?', 'How to complete your payment?') }}</div>
                    <div style="font-size:14px;color:#344b52;line-height:1.55;">{{ $L('Escríbenos por WhatsApp con tu código de reserva y te enviaremos el enlace de pago. Nuestro equipo te ayudará en minutos.', 'Message us on WhatsApp with your booking code and we will send you the payment link. Our team will help you in minutes.') }}</div>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Botones --}}
    <tr><td style="padding:18px 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:7px;">
                    <a href="{{ $waUrl }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Pagar por WhatsApp', 'Pay via WhatsApp') }}</a>
                </td>
                <td style="padding-left:7px;">
                    <a href="{{ $toursUrl }}" style="display:block;text-align:center;background:#073b3d;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Ver mis tours', 'View tours') }}</a>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Footer --}}
    <tr><td style="background:#073b3d;color:#fff;padding:18px;text-align:center;font-size:13px;">
        <a href="{{ url('/') }}" style="color:#fff;text-decoration:none;">limaviewtours.com</a>
        <span style="color:#dca03a;margin:0 10px;">|</span>{{ $contactEmail }}
        <span style="color:#dca03a;margin:0 10px;">|</span>{{ $contactPhone }}
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>

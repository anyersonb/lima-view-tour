@php
    $locale = $locale ?? ($bookings->first()?->locale ?? app()->getLocale());
    $L = fn (string $es, string $en, ?string $pt = null): string => match ($locale) {
        'en'    => $en,
        'pt'    => $pt ?? $es,
        default => $es,
    };
    $first      = $bookings->first();
    $payingNow  = ($first?->payment_status === 'paid');
    $grandTotal = (float) $bookings->sum('total_price');
    $currency   = $first?->currency ?? 'USD';
    $cur        = $currency === 'USD' ? 'US$' : $currency . ' ';
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $wa  = preg_replace('/\D/', '', \App\Models\Setting::get('whatsapp') ?: $contactPhone);
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $urlLocale = in_array($locale, ['en', 'pt'], true) ? $locale : 'es';
    $toursUrl = url('/' . $urlLocale . '/tours');
    $fallbackImg = asset('assets/banners/banner-hero.jpg');
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Reserva confirmada', 'Booking confirmed', 'Reserva confirmada') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $L('Tu reserva fue recibida correctamente', 'Your booking was received successfully', 'Sua reserva foi recebida com sucesso') }} · {{ $first?->reference }}
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;">

    {{-- Marca --}}
    <tr><td style="background:linear-gradient(135deg,#052f32,#0a4a4d);background-color:#0a4a4d;padding:22px;text-align:center;color:#fff;font-family:Georgia,serif;font-size:22px;letter-spacing:6px;font-weight:bold;">
        <img src="{{ asset('assets/logos/logo-gold.png') }}" alt="Lima View Tours" width="200" height="80" style="width:200px;height:auto;display:inline-block;border:0;">

    </td></tr>

    {{-- Hero --}}
    <tr><td style="padding:26px 28px 6px;">
        <span style="display:inline-block;background:#f6eee3;color:#9a631e;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">{{ $L('RESERVA CONFIRMADA', 'BOOKING CONFIRMED', 'RESERVA CONFIRMADA') }}</span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Gracias, ', 'Thank you, ', 'Obrigado, ') }}{{ $first?->customer_name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            @if ($payingNow)
                {{ $L('Tu pago fue confirmado y tu reserva está lista. Aquí tienes el detalle.', 'Your payment was confirmed and your booking is ready. Here are the details.', 'Seu pagamento foi confirmado e sua reserva está pronta. Aqui estão os detalhes.') }}
            @else
                {{ $L('Recibimos tu reserva. Nuestro equipo te contactará para coordinar el pago antes de la fecha del tour.', 'We received your booking. Our team will contact you to arrange payment before your tour date.', 'Recebemos sua reserva. Nossa equipe entrará em contato para coordenar o pagamento antes da data do passeio.') }}
            @endif
        </p>
    </td></tr>

    {{-- Banner confirmación --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b4b4d;border-radius:16px;">
            <tr>
                <td style="padding:15px 18px;color:#fff;font-size:16px;font-weight:bold;">
                    {{ $payingNow ? $L('¡Tu reserva está confirmada!', 'Your booking is confirmed!', 'Sua reserva está confirmada!') : $L('¡Tu reserva fue recibida!', 'Your booking was received!', 'Sua reserva foi recebida!') }}
                </td>
                <td align="right" style="padding:15px 18px;">
                    <span style="display:inline-block;background:{{ $payingNow ? '#e9922a' : '#557' }};background-color:#e9922a;color:#fff;border-radius:999px;padding:8px 13px;font-weight:bold;font-size:12px;white-space:nowrap;">
                        {{ $payingNow ? $L('Pagado', 'Paid', 'Pago') : $L('Pendiente', 'Pending', 'Pendente') }} · {{ $cur }}{{ number_format($grandTotal, 2) }}
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
                {{-- Cabecera de tarjeta: foto + título --}}
                <tr>
                    <td width="120" style="padding:14px 0 14px 14px;vertical-align:top;">
                        <img src="{{ $img }}" alt="" width="110" height="80" style="width:110px;height:80px;border-radius:12px;object-fit:cover;display:block;">
                    </td>
                    <td style="padding:14px;vertical-align:top;">
                        <div style="font-size:11px;color:#9a631e;font-weight:bold;letter-spacing:.4px;">{{ $L('TOUR RESERVADO', 'BOOKED TOUR', 'PASSEIO RESERVADO') }}</div>
                        <div style="font-family:Georgia,serif;font-size:17px;color:#0b3035;margin-top:3px;line-height:1.2;">{{ $booking->tour_title_snapshot }}</div>
                        <div style="margin-top:6px;font-size:13px;color:#0b7c56;font-weight:bold;">{{ $L('Código', 'Code', 'Código') }}: {{ $booking->reference }}</div>
                    </td>
                </tr>
                {{-- Filas de detalle --}}
                <tr><td colspan="2" style="padding:0 14px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#153d46;">
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Fecha del tour', 'Tour date', 'Data do passeio') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ \Carbon\Carbon::parse($booking->travel_date)->locale($locale)->isoFormat('D MMM YYYY') }}</td></tr>
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Pasajeros', 'Passengers', 'Passageiros') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->adults }} {{ $L('adulto(s)', 'adult(s)', 'adulto(s)') }}@if($booking->children > 0) · {{ $booking->children }} {{ $L('niño(s)', 'child(ren)', 'criança(s)') }}@endif</td></tr>
                        @if ($booking->pickup_point)
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Hotel de recojo', 'Pickup hotel', 'Hotel de embarque') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->pickup_point }}@if($booking->pickup_detail)<br><span style="font-weight:normal;color:#71808a;font-size:12px;">{{ $booking->pickup_detail }}</span>@endif</td></tr>
                        @endif
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Precio por persona', 'Price per person', 'Preço por pessoa') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $cur }}{{ number_format($booking->unit_price, 2) }}</td></tr>
                        @if ($booking->hasDiscount())
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Descuento aplicado', 'Discount applied', 'Desconto aplicado') }}@if($booking->discount_type === 'percent') ({{ rtrim(rtrim(number_format($booking->discount_value, 2), '0'), '.') }}%)@endif</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;color:#b0560d;">− {{ $cur }}{{ number_format($booking->discount_amount, 2) }}</td></tr>
                        @endif
                        <tr><td style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">{{ $L('Subtotal', 'Subtotal', 'Subtotal') }} ({{ $pax }})</td><td align="right" style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b7c56;font-weight:bold;font-size:16px;">{{ $cur }}{{ number_format($booking->total_price, 2) }}</td></tr>
                        @if ($booking->custom_tour_details)
                        <tr><td colspan="2" style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;font-size:13px;line-height:1.5;"><b style="color:#0b3035;">{{ $L('Detalle del tour', 'Tour details', 'Detalhes do passeio') }}:</b> {{ $booking->custom_tour_details }}</td></tr>
                        @endif
                    </table>
                </td></tr>
            </table>
        </td></tr>
    @endforeach

    {{-- Datos del cliente --}}
    <tr><td style="padding:18px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f4ed;border:1px solid #eadfce;border-radius:14px;">
            <tr><td style="padding:14px 16px;font-size:13px;color:#53646b;line-height:1.6;">
                <b style="color:#0b3035;">{{ $L('A nombre de', 'Booked by', 'Em nome de') }}:</b> {{ $first?->customer_name }}<br>
                <b style="color:#0b3035;">{{ $L('Correo', 'Email', 'E-mail') }}:</b> {{ $first?->customer_email }}<br>
                <b style="color:#0b3035;">{{ $L('Teléfono', 'Phone', 'Telefone') }}:</b> {{ $first?->customer_phone ?: '—' }}
            </td></tr>
        </table>
    </td></tr>

    {{-- Total general (si hay más de 1) --}}
    @if ($bookings->count() > 1)
    <tr><td style="padding:14px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b4b4d;border-radius:14px;">
            <tr>
                <td style="padding:14px 18px;color:#fff;font-weight:bold;font-size:14px;">{{ $L('Total de tu reserva', 'Booking total', 'Total da sua reserva') }}</td>
                <td align="right" style="padding:14px 18px;color:#f2b95c;font-weight:bold;font-size:20px;">{{ $cur }}{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </table>
    </td></tr>
    @endif

    {{-- ¿Qué sigue? --}}
    <tr><td style="padding:18px 28px 4px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#edf7f1;border:1px solid #e4eadf;border-radius:16px;">
            <tr>
                <td width="48" style="padding:18px 0 18px 18px;vertical-align:top;font-size:30px;color:#0c7354;">&#9742;</td>
                <td style="padding:18px;">
                    <div style="font-family:Georgia,serif;font-size:18px;color:#0b3035;margin-bottom:5px;">{{ $L('¿Qué sigue?', "What's next?", 'O que vem a seguir?') }}</div>
                    <div style="font-size:14px;color:#344b52;line-height:1.55;">{{ $L('Un día antes del tour, entre las 6:00 y 7:00 p.m., te enviaremos el horario exacto de recojo por WhatsApp o correo.', 'One day before the tour, between 6:00 and 7:00 p.m., we will send you the exact pickup time via WhatsApp or email.', 'Um dia antes do passeio, entre 18h e 19h, enviaremos o horário exato de embarque por WhatsApp ou e-mail.') }}</div>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Botones --}}
    <tr><td style="padding:18px 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:7px;">
                    <a href="https://wa.me/{{ $wa }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Escríbenos por WhatsApp', 'Message us on WhatsApp', 'Fale conosco pelo WhatsApp') }}</a>
                </td>
                <td style="padding-left:7px;">
                    <a href="{{ $toursUrl }}" style="display:block;text-align:center;background:#073b3d;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Ver más tours', 'Explore more tours', 'Ver mais passeios') }}</a>
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

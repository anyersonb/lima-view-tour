@php
    $locale = $locale ?? ($bookings->first()?->locale ?? app()->getLocale());
    $L = fn (string $es, string $en, ?string $pt = null): string => match ($locale) {
        'en'    => $en,
        'pt'    => $pt ?? $es,
        default => $es,
    };
    $first        = $bookings->first();
    $grandTotal   = (float) $bookings->sum('total_price');
    $currency     = $first?->currency ?? 'USD';
    $cur          = $currency === 'USD' ? 'US$' : $currency . ' ';
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $wa           = preg_replace('/\D/', '', \App\Models\Setting::get('whatsapp') ?: $contactPhone);
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $urlLocale    = in_array($locale, ['en', 'pt'], true) ? $locale : 'es';
    $toursUrl     = url('/' . $urlLocale . '/tours');
    $fallbackImg  = asset('assets/banners/banner-hero.jpg');
    $travelDate   = $first?->travel_date ? \Carbon\Carbon::parse($first->travel_date) : null;
    $daysLeft     = $travelDate ? max(0, now()->startOfDay()->diffInDays($travelDate->copy()->startOfDay())) : null;
    // Link de pago cargado desde el panel (Reservas → Link de pago), si existe.
    $payLink      = $bookings->pluck('payment_link_url')->filter()->first();
    $waText       = $L(
        "Hola, quiero completar el pago de mi reserva {$first?->reference} de Lima View Tours.",
        "Hi, I'd like to complete the payment for my Lima View Tours booking {$first?->reference}.",
        "Olá, quero concluir o pagamento da minha reserva {$first?->reference} da Lima View Tours."
    );
    $waUrl = 'https://wa.me/' . $wa . '?text=' . rawurlencode($waText);
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Tu reserva aún no está confirmada', 'Your booking is not confirmed yet', 'Sua reserva ainda não está confirmada') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $L('Sin el pago tu reserva no se hace efectiva', 'Without payment your booking is not confirmed', 'Sem o pagamento sua reserva não é efetivada') }} · {{ $first?->reference }}
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
        <span style="display:inline-block;background:#fbe9d6;color:#b0560d;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">{{ $L('RESERVA SIN CONFIRMAR', 'BOOKING NOT CONFIRMED', 'RESERVA NÃO CONFIRMADA') }}</span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Hola, ', 'Hi, ', 'Olá, ') }}{{ $first?->customer_name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            @if ($daysLeft !== null && $daysLeft > 0)
                {{ $L('Tu tour es en', 'Your tour is in', 'Seu tour é em') }}
                <b style="color:#b0560d;">{{ $daysLeft }} {{ $daysLeft == 1 ? $L('día', 'day', 'dia') : $L('días', 'days', 'dias') }}</b>
                {{ $L('y todavía no registramos tu pago. Tu reserva no se hace efectiva hasta que el pago esté completo.', 'and we have not received your payment yet. Your booking is not confirmed until payment is complete.', 'e ainda não registramos o seu pagamento. Sua reserva não é efetivada até que o pagamento seja concluído.') }}
            @else
                {{ $L('Todavía no registramos tu pago. Tu reserva no se hace efectiva hasta que el pago esté completo.', 'We have not received your payment yet. Your booking is not confirmed until payment is complete.', 'Ainda não registramos o seu pagamento. Sua reserva não é efetivada até que o pagamento seja concluído.') }}
            @endif
        </p>
    </td></tr>

    {{-- Banner monto pendiente --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#b0560d;border-radius:16px;">
            <tr>
                <td style="padding:15px 18px;color:#fff;font-size:16px;font-weight:bold;">
                    {{ $L('Monto pendiente de pago', 'Amount pending', 'Valor pendente de pagamento') }}
                </td>
                <td align="right" style="padding:15px 18px;">
                    <span style="display:inline-block;background:#fff;color:#b0560d;border-radius:999px;padding:8px 13px;font-weight:bold;font-size:14px;white-space:nowrap;">
                        {{ $cur }}{{ number_format($grandTotal, 2) }}
                    </span>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Aviso: sin pago la reserva no se hace efectiva --}}
    <tr><td style="padding:14px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf1ec;border:1px solid #f3d3c6;border-left:4px solid #c0491f;border-radius:12px;">
            <tr>
                <td width="42" style="padding:14px 0 14px 14px;vertical-align:top;font-size:22px;color:#c0491f;">&#9888;</td>
                <td style="padding:14px 16px 14px 8px;font-size:14px;color:#6b2c14;line-height:1.55;">
                    <b style="color:#8e2f10;">{{ $L('Importante', 'Important', 'Importante') }}:</b>
                    {{ $L(
                        'la reserva queda registrada, pero NO se hace efectiva hasta que recibamos el pago. Si no lo completas antes de la fecha del tour, no podemos garantizar tu cupo y el lugar se libera para otro pasajero.',
                        'your booking is registered, but it is NOT confirmed until we receive your payment. If it is not completed before the tour date, we cannot guarantee your spot and it will be released to another traveller.',
                        'a reserva fica registrada, mas NÃO é efetivada até recebermos o pagamento. Se não for concluído antes da data do passeio, não podemos garantir a sua vaga e o lugar será liberado para outro passageiro.'
                    ) }}
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
                        <div style="font-size:11px;color:#9a631e;font-weight:bold;letter-spacing:.4px;">{{ $L('TOUR RESERVADO', 'BOOKED TOUR', 'TOUR RESERVADO') }}</div>
                        <div style="font-family:Georgia,serif;font-size:17px;color:#0b3035;margin-top:3px;line-height:1.2;">{{ $booking->tour_title_snapshot }}</div>
                        <div style="margin-top:6px;font-size:13px;color:#0b7c56;font-weight:bold;">{{ $L('Código', 'Code', 'Código') }}: {{ $booking->reference }}</div>
                    </td>
                </tr>
                <tr><td colspan="2" style="padding:0 14px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#153d46;">
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Fecha del tour', 'Tour date', 'Data do tour') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ \Carbon\Carbon::parse($booking->travel_date)->locale($locale)->isoFormat('D MMM YYYY') }}</td></tr>
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Pasajeros', 'Passengers', 'Passageiros') }}</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->adults }} {{ $L('adulto(s)', 'adult(s)', 'adulto(s)') }}@if($booking->children > 0) · {{ $booking->children }} {{ $L('niño(s)', 'child(ren)', 'criança(s)') }}@endif</td></tr>
                        @if ($booking->hasDiscount())
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Descuento aplicado', 'Discount applied', 'Desconto aplicado') }}@if($booking->discount_type === 'percent') ({{ rtrim(rtrim(number_format($booking->discount_value, 2), '0'), '.') }}%)@endif</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;color:#b0560d;">− {{ $cur }}{{ number_format($booking->discount_amount, 2) }}</td></tr>
                        @endif
                        <tr><td style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">{{ $L('Subtotal', 'Subtotal', 'Subtotal') }} ({{ $pax }})</td><td align="right" style="padding:11px 0;border-top:1px solid #ece6dc;color:#b0560d;font-weight:bold;font-size:16px;">{{ $cur }}{{ number_format($booking->total_price, 2) }}</td></tr>
                        @if ($booking->custom_tour_details)
                        <tr><td colspan="2" style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;font-size:13px;line-height:1.5;"><b style="color:#0b3035;">{{ $L('Detalle del tour', 'Tour details', 'Detalhes do tour') }}:</b> {{ $booking->custom_tour_details }}</td></tr>
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
                    <div style="font-family:Georgia,serif;font-size:18px;color:#0b3035;margin-bottom:5px;">{{ $L('¿Cómo completar el pago?', 'How to complete your payment?', 'Como concluir o pagamento?') }}</div>
                    <div style="font-size:14px;color:#344b52;line-height:1.55;">
                        @if ($payLink)
                            {{ $L('Usa el botón "Pagar ahora" para completar el pago en línea. En cuanto se registre, te llegará la confirmación de tu reserva. Si prefieres, escríbenos por WhatsApp con tu código.', 'Use the "Pay now" button to complete your payment online. As soon as it is registered, you will receive your booking confirmation. If you prefer, message us on WhatsApp with your code.', 'Use o botão "Pagar agora" para concluir o pagamento online. Assim que for registrado, você receberá a confirmação da sua reserva. Se preferir, fale conosco no WhatsApp com o seu código.') }}
                        @else
                            {{ $L('Escríbenos por WhatsApp con tu código de reserva y te enviaremos el enlace de pago. Nuestro equipo te ayudará en minutos.', 'Message us on WhatsApp with your booking code and we will send you the payment link. Our team will help you in minutes.', 'Fale conosco no WhatsApp com o seu código de reserva e enviaremos o link de pagamento. Nossa equipe ajuda você em minutos.') }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Botones --}}
    <tr><td style="padding:18px 28px 24px;">
        @if ($payLink)
            <a href="{{ $payLink }}" style="display:block;text-align:center;background:#c0491f;color:#fff;text-decoration:none;border-radius:999px;padding:15px;font-weight:bold;font-size:15px;margin-bottom:10px;">{{ $L('Pagar ahora y confirmar mi reserva', 'Pay now and confirm my booking', 'Pagar agora e confirmar minha reserva') }}</a>
        @endif
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:7px;">
                    <a href="{{ $waUrl }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Pagar por WhatsApp', 'Pay via WhatsApp', 'Pagar pelo WhatsApp') }}</a>
                </td>
                <td style="padding-left:7px;">
                    <a href="{{ $toursUrl }}" style="display:block;text-align:center;background:#073b3d;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Ver mis tours', 'View tours', 'Ver meus tours') }}</a>
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

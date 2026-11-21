@php
    $locale = $locale ?? ($booking->locale ?? app()->getLocale());
    $L = fn (string $es, string $en, ?string $pt = null): string => match ($locale) {
        'en'    => $en,
        'pt'    => $pt ?? $es,
        default => $es,
    };
    $urlLocale    = in_array($locale, ['en', 'pt'], true) ? $locale : 'es';
    $currency     = $booking->currency ?? 'USD';
    $cur          = $currency === 'USD' ? 'US$' : $currency . ' ';
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $wa           = preg_replace('/\D/', '', \App\Models\Setting::get('whatsapp') ?: $contactPhone);
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $payUrl       = $link->url . '?lang=' . $urlLocale;
    $tz           = \App\Support\BookingCalendar::timezone();
    $deadline     = $link->expires_at ? $link->expires_at->copy()->setTimezone($tz)->locale($urlLocale)->isoFormat('D MMM YYYY, HH:mm') : null;
    $travelDate   = $booking->travel_date ? $booking->travel_date->copy()->locale($urlLocale)->isoFormat('D MMM YYYY') : null;
    $departure    = \App\Support\DepartureTime::normalize($booking->tour?->departure_time);
    $waText       = $L(
        "Hola, tengo una consulta sobre el saldo de mi reserva {$booking->reference} de Lima View Tours.",
        "Hi, I have a question about the balance of my Lima View Tours booking {$booking->reference}.",
        "Olá, tenho uma dúvida sobre o saldo da minha reserva {$booking->reference} da Lima View Tours."
    );
    $waUrl = 'https://wa.me/' . $wa . '?text=' . rawurlencode($waText);
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Paga el saldo de tu reserva', 'Pay the balance of your booking', 'Pague o saldo da sua reserva') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;">

    <tr><td style="background-color:#0a4a4d;padding:22px;text-align:center;color:#fff;">
        <img src="{{ asset('assets/logos/logo-gold.png') }}" alt="Lima View Tours" width="200" height="80" style="width:200px;height:auto;display:inline-block;border:0;">
    </td></tr>

    <tr><td style="padding:26px 28px 6px;">
        <span style="display:inline-block;background:#e4f3ea;color:#0b6b4a;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">
            {{ $isReminder ? $L('RECORDATORIO DE PAGO', 'PAYMENT REMINDER', 'LEMBRETE DE PAGAMENTO') : $L('SALDO DE TU RESERVA', 'YOUR BOOKING BALANCE', 'SALDO DA SUA RESERVA') }}
        </span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Hola, ', 'Hi, ', 'Olá, ') }}{{ $booking->customer_name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            {{ $L(
                'Tu reserva está confirmada con el adelanto que ya pagaste. Para completarla, paga el saldo con el botón de abajo.',
                'Your booking is confirmed with the deposit you already paid. To complete it, pay the balance with the button below.',
                'Sua reserva está confirmada com o sinal que você já pagou. Para concluí-la, pague o saldo com o botão abaixo.'
            ) }}
        </p>
    </td></tr>

    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ece6dc;border-radius:16px;font-size:14px;color:#153d46;">
            <tr><td style="padding:12px 16px;color:#71808a;">{{ $L('Reserva', 'Booking', 'Reserva') }}</td><td align="right" style="padding:12px 16px;font-weight:bold;">{{ $booking->reference }}</td></tr>
            <tr><td style="padding:10px 16px;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Tour', 'Tour', 'Passeio') }}</td><td align="right" style="padding:10px 16px;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->tour_title_snapshot }}</td></tr>
            @if ($travelDate)
            <tr><td style="padding:10px 16px;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Fecha del tour', 'Tour date', 'Data do passeio') }}</td><td align="right" style="padding:10px 16px;border-top:1px solid #ece6dc;font-weight:bold;">{{ $travelDate }}@if ($departure) · {{ $departure }}@endif</td></tr>
            @endif
            <tr><td style="padding:10px 16px;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Ya pagado', 'Already paid', 'Já pago') }}</td><td align="right" style="padding:10px 16px;border-top:1px solid #ece6dc;font-weight:bold;">{{ $cur }}{{ number_format((float) $booking->amount_paid, 2) }}</td></tr>
            <tr><td style="padding:12px 16px;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">{{ $L('Saldo a pagar', 'Balance to pay', 'Saldo a pagar') }}</td><td align="right" style="padding:12px 16px;border-top:1px solid #ece6dc;color:#b0560d;font-weight:bold;font-size:18px;">{{ $cur }}{{ number_format((float) $link->amount, 2) }}</td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:18px 28px 0;">
        <a href="{{ $payUrl }}" style="display:block;text-align:center;background:#0b4b4d;color:#fff;text-decoration:none;border-radius:999px;padding:16px;font-weight:bold;font-size:16px;">{{ $L('Pagar saldo', 'Pay balance', 'Pagar saldo') }} · {{ $cur }}{{ number_format((float) $link->amount, 2) }}</a>
        @if ($deadline)
        <p style="margin:10px 0 0;text-align:center;font-size:13px;color:#6b2c14;line-height:1.5;">
            {{ $L('Este enlace vence el', 'This link expires on', 'Este link expira em') }} <b>{{ $deadline }}</b>
            ({{ $L('48 horas antes de la salida', '48 hours before departure', '48 horas antes da saída') }}).
        </p>
        @endif
    </td></tr>

    <tr><td style="padding:18px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#edf7f1;border:1px solid #e4eadf;border-radius:14px;">
            <tr><td style="padding:14px 16px;font-size:13px;color:#344b52;line-height:1.55;">
                <b>{{ $L('Cancelación', 'Cancellation', 'Cancelamento') }}:</b>
                {{ __('checkout_payment.cancellation_deposit') }}
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:18px 28px 24px;">
        <a href="{{ $waUrl }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">{{ $L('Consultar por WhatsApp', 'Ask on WhatsApp', 'Perguntar pelo WhatsApp') }}</a>
    </td></tr>

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

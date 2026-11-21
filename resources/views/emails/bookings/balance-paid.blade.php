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
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $travelDate   = $booking->travel_date ? $booking->travel_date->copy()->locale($urlLocale)->isoFormat('D MMM YYYY') : null;
    $departure    = \App\Support\DepartureTime::normalize($booking->tour?->departure_time);
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Saldo recibido', 'Balance received', 'Saldo recebido') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;">

    <tr><td style="background-color:#0a4a4d;padding:22px;text-align:center;color:#fff;">
        <img src="{{ asset('assets/logos/logo-gold.png') }}" alt="Lima View Tours" width="200" height="80" style="width:200px;height:auto;display:inline-block;border:0;">
    </td></tr>

    <tr><td style="padding:26px 28px 6px;">
        <span style="display:inline-block;background:#e4f3ea;color:#0b6b4a;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">{{ $L('PAGADO AL 100%', 'PAID IN FULL', 'PAGO INTEGRALMENTE') }}</span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Gracias, ', 'Thank you, ', 'Obrigado, ') }}{{ $booking->customer_name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            {{ $L('Recibimos el saldo de tu reserva. Ya no tienes nada pendiente: tu reserva está pagada al 100%.', 'We received the balance of your booking. Nothing is pending: your booking is paid in full.', 'Recebemos o saldo da sua reserva. Não há nada pendente: sua reserva está paga integralmente.') }}
        </p>
    </td></tr>

    <tr><td style="padding:16px 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ece6dc;border-radius:16px;font-size:14px;color:#153d46;">
            <tr><td style="padding:12px 16px;color:#71808a;">{{ $L('Reserva', 'Booking', 'Reserva') }}</td><td align="right" style="padding:12px 16px;font-weight:bold;">{{ $booking->reference }}</td></tr>
            <tr><td style="padding:10px 16px;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Tour', 'Tour', 'Passeio') }}</td><td align="right" style="padding:10px 16px;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->tour_title_snapshot }}</td></tr>
            @if ($travelDate)
            <tr><td style="padding:10px 16px;border-top:1px solid #ece6dc;color:#71808a;">{{ $L('Fecha del tour', 'Tour date', 'Data do passeio') }}</td><td align="right" style="padding:10px 16px;border-top:1px solid #ece6dc;font-weight:bold;">{{ $travelDate }}@if ($departure) · {{ $departure }}@endif</td></tr>
            @endif
            <tr><td style="padding:12px 16px;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">{{ $L('Total pagado', 'Total paid', 'Total pago') }}</td><td align="right" style="padding:12px 16px;border-top:1px solid #ece6dc;color:#0b7c56;font-weight:bold;font-size:18px;">{{ $cur }}{{ number_format((float) $booking->amount_paid, 2) }}</td></tr>
        </table>
        <p style="margin:14px 0 0;font-size:13px;color:#5f6f78;line-height:1.5;">
            {{ __('checkout_payment.departure_note') }}
        </p>
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

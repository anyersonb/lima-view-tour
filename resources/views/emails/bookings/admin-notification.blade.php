@php
    $first      = $bookings->first();
    $payingNow  = ($paymentTiming === 'now') || ($first?->payment_status === 'paid');
    $grandTotal = (float) $bookings->sum('total_price');
    $currency   = $first?->currency ?? 'USD';
    $cur        = $currency === 'USD' ? 'US$' : $currency . ' ';
    $payLabel   = $payingNow ? 'Pago inmediato (PayPal)' : 'Reservar y pagar después';
    $wa         = preg_replace('/\D/', '', $first?->customer_phone ?? '');
    $adminUrl   = rtrim(config('app.url'), '/') . '/admin/bookings';
    $fallbackImg = asset('assets/banners/banner-hero.jpg');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Nueva reserva — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $first?->customer_name }} · {{ $bookings->sum(fn($b)=>$b->adults+$b->children) }} pax · {{ $cur }}{{ number_format($grandTotal,2) }} · {{ $first?->reference }}
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;border-top:6px solid #d99a32;">

    {{-- Marca --}}
    <tr><td style="background:linear-gradient(135deg,#052f32,#0a4a4d);background-color:#0a4a4d;padding:20px;text-align:center;color:#fff;font-family:Georgia,serif;font-size:21px;letter-spacing:6px;font-weight:bold;">
        <img src="{{ asset('assets/logos/logo-gold.png') }}" alt="Lima View Tours" width="200" height="80" style="width:200px;height:auto;display:inline-block;border:0;">

    </td></tr>

    {{-- Banner acción requerida --}}
    <tr><td style="padding:22px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b4b4d;border-radius:16px;">
            <tr>
                <td style="padding:15px 18px;color:#fff;font-size:17px;font-weight:bold;">Nueva reserva recibida</td>
                <td align="right" style="padding:15px 18px;">
                    <span style="display:inline-block;background:#e9922a;color:#fff;border-radius:999px;padding:8px 13px;font-weight:bold;font-size:12px;white-space:nowrap;">
                        {{ $payingNow ? 'Pagado · ' . $cur . number_format($grandTotal, 2) : 'Acción requerida' }}
                    </span>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Tarjeta(s) --}}
    @foreach ($bookings as $booking)
        @php
            $img = optional($booking->tour)->cover_url ?: $fallbackImg;
            $pax = (int) $booking->adults + (int) $booking->children;
        @endphp
        <tr><td style="padding:16px 28px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ece6dc;border-radius:16px;overflow:hidden;">
                <tr>
                    <td width="120" style="padding:14px 0 14px 14px;vertical-align:top;">
                        <img src="{{ $img }}" alt="" width="110" height="80" style="width:110px;height:80px;border-radius:12px;object-fit:cover;display:block;">
                    </td>
                    <td style="padding:14px;vertical-align:top;">
                        <div style="font-size:11px;color:#9a631e;font-weight:bold;letter-spacing:.4px;">TOUR RESERVADO</div>
                        <div style="font-family:Georgia,serif;font-size:17px;color:#0b3035;margin-top:3px;line-height:1.2;">{{ $booking->tour_title_snapshot }}</div>
                        <div style="margin-top:6px;font-size:13px;color:#0b7c56;font-weight:bold;">Código: {{ $booking->reference }}</div>
                    </td>
                </tr>
                <tr><td colspan="2" style="padding:0 14px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#153d46;">
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">Fecha</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ \Carbon\Carbon::parse($booking->travel_date)->locale('es')->isoFormat('D MMM YYYY') }}</td></tr>
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">Pasajeros</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->adults }} adulto(s)@if($booking->children > 0) · {{ $booking->children }} niño(s)@endif</td></tr>
                        @if ($booking->pickup_point)
                        <tr><td style="padding:9px 0;border-top:1px solid #ece6dc;color:#71808a;">Hotel de recojo</td><td align="right" style="padding:9px 0;border-top:1px solid #ece6dc;font-weight:bold;">{{ $booking->pickup_point }}@if($booking->pickup_detail)<br><span style="font-weight:normal;color:#71808a;font-size:12px;">{{ $booking->pickup_detail }}</span>@endif</td></tr>
                        @endif
                        <tr><td style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b3035;font-weight:bold;">Total ({{ $pax }})</td><td align="right" style="padding:11px 0;border-top:1px solid #ece6dc;color:#0b7c56;font-weight:bold;font-size:16px;">{{ $cur }}{{ number_format($booking->total_price, 2) }}</td></tr>
                    </table>
                </td></tr>
            </table>
        </td></tr>
    @endforeach

    {{-- Datos del cliente --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f4ed;border:1px solid #eadfce;border-radius:14px;">
            <tr><td style="padding:14px 16px;font-size:13px;color:#53646b;line-height:1.7;">
                <b style="color:#0b3035;">Cliente:</b> {{ $first?->customer_name }}<br>
                <b style="color:#0b3035;">Correo:</b> <a href="mailto:{{ $first?->customer_email }}" style="color:#0b4b4d;">{{ $first?->customer_email }}</a><br>
                <b style="color:#0b3035;">Teléfono:</b> {{ $first?->customer_phone ?: '—' }}<br>
                <b style="color:#0b3035;">Modo de pago:</b> {{ $payLabel }}@if($first?->payment_reference) · <span style="color:#71808a;">ref. {{ $first->payment_reference }}</span>@endif
            </td></tr>
        </table>
    </td></tr>

    {{-- Nota operativa --}}
    <tr><td style="padding:14px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8ef;border:1px solid #eadfce;border-radius:14px;">
            <tr>
                <td width="40" style="padding:14px 0 14px 16px;vertical-align:top;font-size:22px;">&#128221;</td>
                <td style="padding:14px;font-size:13px;color:#344b52;line-height:1.5;">
                    <b style="color:#0b3035;">Nota operativa:</b> Enviar al cliente el horario exacto de recojo un día antes, entre 6:00 y 7:00 p.m.
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Botones --}}
    <tr><td style="padding:18px 28px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:7px;">
                    <a href="{{ $adminUrl }}" style="display:block;text-align:center;background:#073b3d;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">Abrir en el panel</a>
                </td>
                @if ($wa)
                <td style="padding-left:7px;">
                    <a href="https://wa.me/{{ $wa }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">WhatsApp al cliente</a>
                </td>
                @endif
            </tr>
        </table>
    </td></tr>

    {{-- Footer --}}
    <tr><td style="background:#073b3d;color:#fff;padding:16px;text-align:center;font-size:12px;">
        Aviso interno automático <span style="color:#dca03a;margin:0 8px;">|</span> Lima View Tours
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>

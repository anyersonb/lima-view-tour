@php
    $locale = $locale ?? ($cart->locale ?: 'es');
    /** Helper de traducción ES/EN/PT */
    $L = function (string $es, string $en, string $pt) use ($locale): string {
        return match ($locale) { 'en' => $en, 'pt' => $pt, default => $es };
    };

    $items    = collect($cart->items ?? []);
    $currency = 'US$';
    $total    = (float) $cart->total;

    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $fallbackImg  = asset('assets/banners/banner-hero.jpg');

    $recoverUrl = route('cart.recover', ['locale' => $locale, 'token' => $cart->token]);
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Tu carrito te espera', 'Your cart is waiting', 'Seu carrinho está esperando') }} — Lima View Tours</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $L('Guardamos los tours que elegiste. Retoma tu reserva cuando quieras.', 'We saved the tours you picked. Resume your booking anytime.', 'Guardamos os passeios que você escolheu. Retome sua reserva quando quiser.') }}
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
        <span style="display:inline-block;background:#f6eee3;color:#9a631e;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">
            {{ $L('TU CARRITO TE ESPERA', 'YOUR CART IS WAITING', 'SEU CARRINHO ESTÁ ESPERANDO') }}
        </span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            @if($cart->name)
                {{ $L('¡Hola, ', 'Hi, ', 'Olá, ') }}{{ $cart->name }}!
            @else
                {{ $L('¡Tu aventura casi lista!', 'Your adventure is almost ready!', 'Sua aventura está quase pronta!') }}
            @endif
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.5;">
            @if($reminder >= 2)
                {{ $L('Todavía guardamos tu selección de tours. No dejes pasar tu experiencia en Perú — completa tu reserva en un par de clics.',
                      'We are still holding the tours you picked. Don’t miss your Peru experience — finish your booking in a couple of clicks.',
                      'Ainda guardamos os passeios que você escolheu. Não perca sua experiência no Peru — finalize sua reserva em poucos cliques.') }}
            @else
                {{ $L('Notamos que dejaste algunos tours en tu carrito. Los guardamos para ti: retoma tu reserva justo donde la dejaste.',
                      'We noticed you left some tours in your cart. We saved them for you — pick up right where you left off.',
                      'Notamos que você deixou alguns passeios no carrinho. Guardamos para você — retome de onde parou.') }}
            @endif
        </p>
    </td></tr>

    {{-- Items del carrito --}}
    @foreach ($items as $item)
        @php
            $img   = $item['cover_image'] ?? $fallbackImg;
            $title = $item['title_snapshot'] ?? ($item['title'] ?? 'Tour');
            $qty   = (int) ($item['quantity'] ?? (($item['adults'] ?? 1) + ($item['children'] ?? 0)));
            $sub   = (float) ($item['subtotal'] ?? 0);
            $date  = $item['travel_date'] ?? null;
        @endphp
        <tr><td style="padding:14px 28px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ece6dc;border-radius:16px;overflow:hidden;">
                <tr>
                    <td width="120" style="padding:14px 0 14px 14px;vertical-align:top;">
                        <img src="{{ $img }}" alt="" width="110" height="80" style="width:110px;height:80px;border-radius:12px;object-fit:cover;display:block;">
                    </td>
                    <td style="padding:14px;vertical-align:top;">
                        <div style="font-family:Georgia,serif;font-size:17px;color:#0b3035;line-height:1.2;">{{ $title }}</div>
                        @if($date)
                            <div style="margin-top:6px;font-size:13px;color:#71808a;">
                                {{ $L('Fecha', 'Date', 'Data') }}: {{ \Carbon\Carbon::parse($date)->locale($locale)->isoFormat('D MMM YYYY') }}
                            </div>
                        @endif
                        <div style="margin-top:4px;font-size:13px;color:#71808a;">
                            {{ $qty }} {{ $L('pasajero(s)', 'passenger(s)', 'passageiro(s)') }}
                        </div>
                    </td>
                    <td align="right" style="padding:14px;vertical-align:top;white-space:nowrap;">
                        <span style="font-weight:bold;color:#0b7c56;font-size:16px;">{{ $currency }}{{ number_format($sub, 2) }}</span>
                    </td>
                </tr>
            </table>
        </td></tr>
    @endforeach

    {{-- Total --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b4b4d;border-radius:14px;">
            <tr>
                <td style="padding:14px 18px;color:#fff;font-weight:bold;font-size:14px;">{{ $L('Total de tu carrito', 'Your cart total', 'Total do seu carrinho') }}</td>
                <td align="right" style="padding:14px 18px;color:#f2b95c;font-weight:bold;font-size:20px;">{{ $currency }}{{ number_format($total, 2) }}</td>
            </tr>
        </table>
    </td></tr>

    {{-- CTA --}}
    <tr><td style="padding:22px 28px 8px;">
        <a href="{{ $recoverUrl }}" style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:16px;font-weight:bold;font-size:16px;">
            {{ $L('Completar mi reserva', 'Complete my booking', 'Finalizar minha reserva') }}
        </a>
    </td></tr>
    <tr><td style="padding:0 28px 20px;text-align:center;">
        <p style="margin:8px 0 0;color:#8a97a0;font-size:12px;line-height:1.5;">
            {{ $L('O escríbenos si tienes dudas — te ayudamos a coordinar tu tour.',
                  'Or reach out if you have questions — we’ll help you arrange your tour.',
                  'Ou fale conosco se tiver dúvidas — ajudamos a organizar seu passeio.') }}
        </p>
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

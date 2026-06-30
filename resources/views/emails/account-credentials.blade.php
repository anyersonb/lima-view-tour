@php
    $L = fn (string $es, string $en, string $pt): string => match ($locale) {
        'en'    => $en,
        'pt'    => $pt,
        default => $es,
    };
    $accountUrl = route('customer.account', ['locale' => $locale]);
    $loginUrl   = route('customer.login',   ['locale' => $locale]);
    $contactEmail = \App\Models\Setting::get('contact_email') ?: 'reservas@limaviewtours.com';
    $contactPhone = \App\Models\Setting::get('contact_phone') ?: '+51 925 886 725';
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $L('Tu cuenta en Lima View Tours', 'Your Lima View Tours account', 'Sua conta na Lima View Tours') }}</title>
</head>
<body style="margin:0;padding:0;background:#e9edf1;font-family:Arial,Helvetica,sans-serif;color:#0d2f3b;">

{{-- Preheader hidden text --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    {{ $L('Tus credenciales de acceso a Lima View Tours', 'Your Lima View Tours login credentials', 'Suas credenciais de acesso à Lima View Tours') }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9edf1;padding:22px 10px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;border:1px solid #eee3d4;">

    {{-- Brand header --}}
    <tr><td style="background:linear-gradient(135deg,#052f32,#0a4a4d);background-color:#0a4a4d;padding:22px;text-align:center;color:#fff;font-family:Georgia,serif;font-size:22px;letter-spacing:6px;font-weight:bold;">
        <span style="color:#d7a041;">&#9673;</span>&nbsp; LIMA VIEW TOURS
    </td></tr>

    {{-- Hero --}}
    <tr><td style="padding:26px 28px 10px;">
        <span style="display:inline-block;background:#f6eee3;color:#9a631e;border-radius:999px;padding:6px 12px;font-weight:bold;font-size:11px;letter-spacing:.5px;">
            {{ $L('CUENTA CREADA', 'ACCOUNT CREATED', 'CONTA CRIADA') }}
        </span>
        <h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.15;margin:12px 0 6px;color:#092f35;">
            {{ $L('¡Hola, ', 'Hello, ', 'Olá, ') }}{{ $customer->name }}!
        </h1>
        <p style="margin:0;color:#5f6f78;font-size:15px;line-height:1.55;">
            {{ $L(
                'Creamos una cuenta para que puedas gestionar tus reservas fácilmente.',
                'We created an account so you can manage your bookings easily.',
                'Criamos uma conta para que você possa gerenciar suas reservas facilmente.'
            ) }}
        </p>
    </td></tr>

    {{-- Credentials box --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#0b4b4d;border-radius:16px;overflow:hidden;">
            <tr><td style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:bold;letter-spacing:.6px;color:#9dd8d8;text-transform:uppercase;margin-bottom:14px;">
                    {{ $L('Tus datos de acceso', 'Your login details', 'Seus dados de acesso') }}
                </div>

                {{-- Username --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;">
                    <tr>
                        <td style="font-size:13px;color:#9dd8d8;padding-bottom:3px;">
                            {{ $L('Usuario', 'Username', 'Usuário') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#07373a;border-radius:10px;padding:12px 14px;font-family:'Courier New',Courier,monospace;font-size:15px;color:#f2f8f8;letter-spacing:.3px;word-break:break-all;">
                            {{ $customer->email }}
                        </td>
                    </tr>
                </table>

                {{-- Password --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:13px;color:#9dd8d8;padding-bottom:3px;">
                            {{ $L('Contraseña temporal', 'Temporary password', 'Senha temporária') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#07373a;border-radius:10px;padding:12px 14px;font-family:'Courier New',Courier,monospace;font-size:18px;color:#f2b95c;font-weight:bold;letter-spacing:2px;">
                            {{ $password }}
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </td></tr>

    {{-- Security notice --}}
    <tr><td style="padding:16px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#fff8ec;border:1px solid #f0d9a8;border-radius:14px;">
            <tr>
                <td width="40" style="padding:16px 0 16px 16px;vertical-align:top;font-size:22px;">&#128274;</td>
                <td style="padding:16px 16px 16px 10px;font-size:13px;color:#7a4e0c;line-height:1.55;">
                    <strong style="color:#5a3700;display:block;margin-bottom:3px;">
                        {{ $L('Nota de seguridad', 'Security note', 'Nota de segurança') }}
                    </strong>
                    {{ $L(
                        'Por tu seguridad, cambia esta contraseña la primera vez que ingreses a tu cuenta.',
                        'For your security, please change this password the first time you log in.',
                        'Por sua segurança, altere esta senha na primeira vez que acessar sua conta.'
                    ) }}
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- CTA buttons --}}
    <tr><td style="padding:22px 28px 10px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding-right:7px;">
                    <a href="{{ $accountUrl }}"
                       style="display:block;text-align:center;background:#0b4b4d;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">
                        {{ $L('Ir a mi cuenta', 'Go to my account', 'Ir para minha conta') }}
                    </a>
                </td>
                <td style="padding-left:7px;">
                    <a href="{{ $loginUrl }}"
                       style="display:block;text-align:center;background:#dd8523;color:#fff;text-decoration:none;border-radius:999px;padding:14px;font-weight:bold;font-size:14px;">
                        {{ $L('Iniciar sesión', 'Log in', 'Entrar') }}
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin:14px 0 0;font-size:12px;color:#8a9ea5;text-align:center;line-height:1.5;">
            {{ $L(
                'Si no reconoces esta reserva, ignora este correo o contáctanos.',
                'If you did not make this booking, ignore this email or contact us.',
                'Se você não fez esta reserva, ignore este e-mail ou entre em contato.'
            ) }}
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

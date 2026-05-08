<x-mail::message>
@if($locale === 'en')
# Confirm your subscription

Hi {{ $subscriber->name ?? 'traveler' }},

Thank you for subscribing to **Lima View Tours** newsletter. Please confirm your email address by clicking the button below.

<x-mail::button :url="$confirmUrl" color="primary">
Confirm my subscription
</x-mail::button>

This link will remain active. If you did not subscribe, you can safely ignore this email.
@else
# Confirma tu suscripción

Hola {{ $subscriber->name ?? 'viajero' }},

Gracias por suscribirte al boletín de **Lima View Tours**. Por favor confirma tu dirección de correo haciendo clic en el botón de abajo.

<x-mail::button :url="$confirmUrl" color="primary">
Confirmar mi suscripción
</x-mail::button>

Este enlace estará siempre activo. Si no te suscribiste, puedes ignorar este mensaje con toda tranquilidad.
@endif

Lima View Tours
</x-mail::message>

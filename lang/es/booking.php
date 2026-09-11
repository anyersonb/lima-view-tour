<?php

return [
    'date_blocked' => 'Esa fecha no está disponible para reservar. Elige otra.',
    // Carrito abierto en una pestaña días atrás: la fecha guardada ya no
    // cumple la antelación mínima o quedó fuera del horizonte de reservas.
    'date_outdated' => 'La fecha de uno de tus tours ya no está disponible (cambió mientras tenías el carrito abierto). Revisa las fechas y vuelve a intentarlo.',
    // PayPal capture 422 con details[0].issue = INSTRUMENT_DECLINED: el
    // banco/emisor rechazó la tarjeta, no cobramos nada. El comprador sigue
    // en la pantalla de pago y puede reintentar con otro instrumento.
    'card_declined' => 'Tu banco rechazó la tarjeta. Por favor intenta con otra tarjeta o usa tu saldo de PayPal.',
    // Mensaje genérico de fallo de captura de pago: se usa en tres puntos
    // distintos de paypalCaptureOrder() (snapshot desconocido/expirado,
    // importe/moneda no coinciden, y catch genérico sin captureId). Los tres
    // comparten la misma consecuencia para el comprador -no se cobró nada,
    // puede reintentar o escribirnos- así que comparten una sola clave.
    'payment_failed' => 'El pago no pudo completarse. Por favor inténtalo de nuevo o contáctanos.',
];

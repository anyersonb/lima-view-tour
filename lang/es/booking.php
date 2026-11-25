<?php

return [
    'date_blocked' => 'Esa fecha no está disponible para reservar. Elige otra.',
    // Carrito abierto en una pestaña días atrás: la fecha guardada ya no
    // cumple la antelación mínima o quedó fuera del horizonte de reservas.
    // Per-item message at cart/checkout: names the tour and its first bookable date.
    'date_item_unavailable' => 'La fecha de «:tour» ya no está disponible. La primera fecha disponible es el :date. Para el día siguiente se reserva hasta las :cutoff (hora de Lima).',
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

    // Horarios y reserva el mismo día (2026-11-24)
    'slot_required' => 'Elige el horario de salida.',
    'slot_invalid' => 'Ese horario no está disponible para este tour. Elige otro.',
    'slot_too_soon' => 'Ese horario ya no se puede reservar para hoy: se reserva con al menos :hours horas de anticipación. Elige otro horario o una fecha posterior.',
    'slot_item_unavailable' => 'El horario de «:tour» ya no está disponible para esa fecha. Cámbialo o elige otra fecha.',
    'same_day_notice' => 'Reserva para hoy disponible: con al menos :hours horas de anticipación al horario elegido.',
    'same_day_mail_prefix' => '[MISMO DÍA]',
    'same_day_mail_banner' => 'RESERVA PARA EL MISMO DÍA — confirmar hoy mismo. Salida el :date a las :time',
    'slot_label' => 'Horario de salida',
];

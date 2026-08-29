<?php

/*
|--------------------------------------------------------------------------
| Calendario de reservas (2026-08-27)
|--------------------------------------------------------------------------
|
| `app.timezone` es UTC y NO se toca: Laravel guarda los timestamps en esa
| zona y cambiarla reinterpretaría en 5 horas todo lo ya grabado.
|
| Pero las FECHAS DE TOUR no son timestamps, son días del calendario de un
| operador que está en Lima. Calculadas en UTC, entre las 19:00 y la
| medianoche de Lima el servidor ya está en el día siguiente: "mañana" para
| el cliente es "hoy" para la validación, y el checkout rechazaba la reserva
| con "La fecha de viaje debe ser posterior a hoy". Cinco horas cada noche en
| las que no se podía reservar para el día siguiente.
|
| Todo lo que decida qué día se puede reservar tiene que pasar por
| App\Support\BookingCalendar, que lee esta config.
|
*/

return [

    /*
     | Zona horaria del operador. Es la que define qué día es "hoy" a efectos
     | de reservas, independientemente de dónde esté el servidor o el visitante.
     */
    'timezone' => env('BOOKING_TIMEZONE', 'America/Lima'),

    /*
     | Días de antelación mínima. 1 = desde mañana; 0 permitiría el mismo día.
     | Es el valor que ya aplicaban de hecho el calendario (minDate: tomorrow) y
     | la validación del checkout (after:today), ahora en un solo sitio.
     */
    'min_days_ahead' => (int) env('BOOKING_MIN_DAYS_AHEAD', 1),

    /*
     | Horizonte máximo de reserva, en meses desde hoy (hora de Lima). Sin
     | esto, `dateRules()` solo comprobaba el mínimo y una fecha como
     | 9999-12-31 pasaba la validación igual que una razonable.
     */
    'max_months_ahead' => (int) env('BOOKING_MAX_MONTHS_AHEAD', 18),

];

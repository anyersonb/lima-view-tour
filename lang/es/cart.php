<?php

return [
    'added' => 'Tour agregado al carrito.',
    'updated' => 'Carrito actualizado correctamente.',
    'removed' => 'Tour eliminado del carrito.',
    'cleared' => 'Carrito vaciado.',
    'empty' => 'Tu carrito está vacío.',
    'coupon_applied' => '¡Cupón aplicado correctamente!',
    'coupon_invalid' => 'El cupón ingresado no es válido.',
    'coupon_expired' => 'Este cupón ha expirado.',
    'max_quantity' => 'La cantidad máxima permitida es 20.',
    'error' => 'Ocurrió un error. Por favor intenta de nuevo.',
    'row_not_found' => 'Ese tour ya no está en tu carrito. Actualiza la página e inténtalo de nuevo.',
    'cart_full' => 'Tu carrito ya tiene el máximo de :max tours distintos. Elimina alguno para agregar otro.',
    'merge_overflow' => 'Esa fecha ya tiene una reserva del mismo tour y juntarlas superaría el máximo de :max personas. Reduce la cantidad o elige otra fecha.',
    'row_pax_exceeded' => 'El máximo de personas por reserva es :max. Reduce la cantidad de adultos o niños.',

    'validation' => [
        'tour_required' => 'Debes seleccionar un tour.',
        'tour_not_found' => 'El tour seleccionado no existe.',
        'adults_required' => 'Indica el número de adultos.',
        'adults_integer' => 'El número de adultos debe ser un entero.',
        'adults_min' => 'Debe haber al menos 1 adulto.',
        'children_required' => 'Indica el número de niños.',
        'children_integer' => 'El número de niños debe ser un entero.',
        'children_min' => 'El número de niños no puede ser negativo.',
        'date_required' => 'Selecciona la fecha del tour.',
        'date_invalid' => 'La fecha no tiene un formato válido.',
        'date_future' => 'La primera fecha disponible es el :date.',
        'date_too_far' => 'La fecha máxima disponible para reservar es el :date.',
        'max_quantity' => 'La cantidad máxima permitida es 20.',
    ],

    'recover_success' => 'Recuperamos tu carrito. Puedes continuar con tu reserva.',
    'recover_expired' => 'El enlace de recuperación ya no es válido o el carrito está vacío.',
];

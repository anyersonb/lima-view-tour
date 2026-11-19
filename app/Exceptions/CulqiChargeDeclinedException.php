<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Culqi rechazó el cargo por un motivo de la TARJETA (type=card_error u operacion_denegada:
 * fondos insuficientes, tarjeta robada, CVV incorrecto, etc.). No se movió
 * dinero y el comprador puede reintentar con otra tarjeta. Cualquier otro
 * fallo (credenciales, red, 5xx) sigue siendo RuntimeException genérica.
 */
class CulqiChargeDeclinedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $declineCode = null,
        public readonly ?string $culqiCode = null,
    ) {
        parent::__construct($message);
    }
}

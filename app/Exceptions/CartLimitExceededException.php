<?php

namespace App\Exceptions;

/**
 * Se superó un límite duro del carrito: demasiadas filas distintas, o una
 * fusión de filas (mismo tour, misma fecha nueva) que sumaría más pasajeros
 * de los permitidos por reserva. El mensaje ya viene traducido (ver
 * CartService), listo para mostrarse al cliente.
 */
class CartLimitExceededException extends \RuntimeException
{
    public function __construct(string $translatedMessage)
    {
        parent::__construct($translatedMessage);
    }
}

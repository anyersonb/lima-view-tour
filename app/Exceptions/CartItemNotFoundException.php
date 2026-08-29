<?php

namespace App\Exceptions;

/**
 * La fila de carrito referenciada (rowId) ya no existe en la sesión: se
 * borró en otra pestaña, expiró la sesión, o el cliente manda un rowId que
 * nunca existió. Antes `CartService::update()` salía en silencio sin tocar
 * nada y el controlador devolvía 200 `{"success":true}` igual — un fallo
 * real pasaba por éxito. Ver CartController::updateItem().
 */
class CartItemNotFoundException extends \RuntimeException
{
    public function __construct(public readonly string $rowId)
    {
        parent::__construct("Cart row [{$rowId}] not found.");
    }
}

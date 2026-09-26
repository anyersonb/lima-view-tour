<?php

namespace App\Exceptions;

/**
 * Un link de pago no se puede usar AHORA MISMO: no existe, ya está pagado,
 * vencido, anulado, o el orderID que llega a capturar no es el que este
 * mismo endpoint emitió en createOrder() para este código (ver
 * PaymentLinkController). Se lanza DENTRO de la transacción con
 * lockForUpdate() de captureOrder() — DB::transaction() la deja pasar tal
 * cual tras el rollback (no hubo nada que escribir todavía en ese punto).
 */
class PaymentLinkUnavailableException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Payment link unavailable: {$reason}");
    }
}

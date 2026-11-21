<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No se puede generar / cobrar el link de pago del saldo de una reserva. El
 * mensaje está pensado para mostrarse tal cual al administrador (panel).
 */
class BalanceLinkException extends RuntimeException {}

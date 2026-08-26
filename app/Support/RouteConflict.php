<?php

namespace App\Support;

/**
 * Un choque de URL detectado por RouteRegistry: qué path se pisa, quién lo
 * ocupa hoy y dónde se edita ese otro contenido.
 */
class RouteConflict
{
    public const BLOCKING = 'blocking';
    public const WARNING  = 'warning';

    public function __construct(
        public readonly string $severity,
        public readonly string $locale,
        public readonly string $path,
        /** Descripción del ocupante, ej. 'el tour «City Tour Lima»'. */
        public readonly string $owner,
        /** URL de edición del ocupante en el panel, si es contenido del CMS. */
        public readonly ?string $editUrl = null,
        /** Explicación de la consecuencia concreta. */
        public readonly string $consequence = '',
    ) {}

    public function isBlocking(): bool
    {
        return $this->severity === self::BLOCKING;
    }

    /**
     * Mensaje en texto plano — el que ve el editor en el error de validación.
     */
    public function message(): string
    {
        $msg = "La URL {$this->path} ya está ocupada por {$this->owner}.";

        if ($this->consequence !== '') {
            $msg .= ' '.$this->consequence;
        }

        return $msg;
    }
}

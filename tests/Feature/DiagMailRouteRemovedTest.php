<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Hallazgo de seguridad ALTO, abierto desde el 27/08 y reconfirmado el
 * 2026-09-30 (docs/security/2026-09-30-lote-paypal-resenas.md #10):
 * `/_diag/mail` era un relay de correo protegido solo por un token estático
 * (`lvt-mail-diag-2026`, el mismo que quedó documentado en texto plano en
 * docs/seo/RESPUESTA-ESPASEO-2026-08-21.md — hallazgo #9 del mismo informe).
 * Cualquiera con el token podía mandar correo arbitrario "desde" el dominio.
 * Se elimina la ruta completa (era un closure inline en routes/web.php, sin
 * controlador aparte que quedara huérfano).
 */
class DiagMailRouteRemovedTest extends TestCase
{
    public function test_diag_mail_route_no_longer_exists(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('diag.mail'),
            'La ruta diag.mail debía eliminarse por completo, no solo bloquearse.'
        );
    }

    public function test_diag_mail_url_returns_404_even_with_the_leaked_token(): void
    {
        $response = $this->get('/_diag/mail?key=lvt-mail-diag-2026&to=test@example.com');

        $response->assertNotFound();
    }

    public function test_diag_mail_url_returns_404_without_any_token(): void
    {
        $response = $this->get('/_diag/mail');

        $response->assertNotFound();
    }
}

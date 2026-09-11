<?php

namespace Tests\Feature\Seo;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vulnerabilidad crítica confirmada por security-engineer (2026-08-31): los
 * once emisores de JSON-LD imprimían el valor crudo con `{!! !!}` dentro de
 * <script type="application/ld+json">. Un JSON sintácticamente válido puede
 * contener `</script>` en un string — el parser del navegador corta el
 * <script> ahí mismo y todo lo que sigue se interpreta como HTML normal,
 * materializando el <img onerror=...> como elemento real del árbol DOM.
 *
 * El fix vive en resources/views/components/schema-raw.blade.php: decodifica
 * y vuelve a codificar con JSON_HEX_TAG (entre otros), que escapa `<`/`>`
 * como `<`/`>` DENTRO de la gramática JSON — no queda ningún `<`
 * literal que el parser HTML pueda interpretar como inicio de etiqueta.
 *
 * Estos tests verifican el árbol DOM realmente parseado (DOMDocument), no
 * con strpos()/assertStringContainsString() sobre el HTML crudo — eso es
 * exactamente lo que hizo el hallazgo original de seguridad y lo que un
 * fix superficial podría seguir pasando en falso.
 */
class SchemaJsonLdXssTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD_NAME = 'Lima View Tours</script><img src=x onerror=alert(document.domain)><script>';

    private function maliciousPayload(): string
    {
        // JSON_UNESCAPED_SLASHES a propósito: es como llega el valor real,
        // ya sea pegado a mano por un SEO desde un generador de schema o
        // insertado por un deploy-*.sql. Sin este flag, PHP escaparía
        // "/" como "\/" y el "</script" de la prueba dejaría de ser el
        // byte-a-byte que el parser del navegador realmente busca — un
        // json_encode() de PHP "de más" enmascararía el propio hallazgo.
        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => self::PAYLOAD_NAME,
            'url' => 'https://limaviewtours.com',
        ], JSON_UNESCAPED_SLASHES);
    }

    private function benignPayload(): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Lima View Tours',
            'url' => 'https://limaviewtours.com',
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{dom: \DOMDocument, imgOnerror: int, imgTotal: int} */
    private function parse(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        return [
            'dom' => $dom,
            // Marcador específico del payload inyectado: `<img src=x
            // onerror=...>`. Ningún <img> legítimo del sitio usa src="x" —
            // los onerror= legítimos (fallback de imagen rota en
            // tour-card.blade.php, reviews.blade.php, etc.) siempre traen un
            // src real hacia assets/. Por eso esto puede ser una aserción
            // absoluta (0), a diferencia del total de onerror del sitio.
            'imgInjected' => $xpath->query('//img[@src="x"]')->length,
            'imgOnerrorTotal' => $xpath->query('//img[@onerror]')->length,
            'imgTotal' => $dom->getElementsByTagName('img')->length,
        ];
    }

    /** Bloques <script type="application/ld+json"> tal como llegan al DOM (ya cortados por el parser si el escape fallara). */
    private function ldJsonScriptTexts(array $parsed): array
    {
        $xpath = new \DOMXPath($parsed['dom']);
        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        $out = [];
        foreach ($nodes as $node) {
            $out[] = $node->textContent;
        }

        return $out;
    }

    // ── 1) El payload malicioso NO materializa ningún <img> inyectado ────

    public function test_malicious_payload_does_not_materialize_any_img_element_in_the_dom(): void
    {
        $before = $this->parse($this->get('/es')->assertOk()->getContent());

        Setting::set('schema_jsonld_global_es', $this->maliciousPayload());

        $after = $this->parse($this->get('/es')->assertOk()->getContent());

        $this->assertSame(
            0,
            $after['imgInjected'],
            'Se materializó el <img src=x onerror=...> del payload como elemento real del DOM.'
        );

        $this->assertSame(
            $before['imgOnerrorTotal'],
            $after['imgOnerrorTotal'],
            'Aparecieron <img onerror=...> nuevos (de más) tras guardar el JSON-LD malicioso: algo del payload se materializó como HTML.'
        );

        $this->assertSame(
            $before['imgTotal'],
            $after['imgTotal'],
            'El número total de <img> en la página cambió al guardar el JSON-LD malicioso: algo del payload se materializó como HTML.'
        );
    }

    // ── 2) El JSON-LD resultante sigue siendo válido y el name es literal ──

    public function test_resulting_jsonld_stays_valid_json_and_recovers_the_literal_name(): void
    {
        Setting::set('schema_jsonld_global_es', $this->maliciousPayload());

        $html = $this->get('/es')->assertOk()->getContent();
        $parsed = $this->parse($html);

        $texts = $this->ldJsonScriptTexts($parsed);
        $this->assertNotEmpty($texts, 'No se encontró ningún <script type="application/ld+json"> en el HTML.');

        $found = null;
        foreach ($texts as $raw) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && ($decoded['@type'] ?? null) === 'Organization') {
                $found = $decoded;
                break;
            }
        }

        $this->assertNotNull($found, 'El bloque JSON-LD global no es JSON válido (o no se encontró) tras el fix.');
        $this->assertSame(
            self::PAYLOAD_NAME,
            $found['name'],
            'El contenido literal del name no se recuperó igual al original tras decodificar.'
        );

        // El HTML crudo servido no debe contener el marcador `</script` DENTRO
        // del bloque JSON-LD (buscamos en la fuente cruda, no en el DOM ya
        // reparado por el navegador, para probar que el navegador nunca tuvo
        // la oportunidad de cortar el <script> ahí).
        preg_match('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $html, $m);
        $this->assertArrayHasKey(1, $m, 'No se encontró el bloque <script> del JSON-LD global en el HTML crudo.');
        $this->assertStringNotContainsStringIgnoringCase(
            '</script',
            $m[1],
            'El contenido del <script> JSON-LD sigue trayendo un </script> literal sin escapar.'
        );
    }

    // ── 3) Control negativo: un JSON benigno se renderiza y sigue siendo válido ──

    public function test_benign_payload_renders_correctly_and_stays_valid(): void
    {
        $before = $this->parse($this->get('/es')->assertOk()->getContent());

        Setting::set('schema_jsonld_global_es', $this->benignPayload());

        $html = $this->get('/es')->assertOk()->getContent();
        $after = $this->parse($html);

        $this->assertSame($before['imgTotal'], $after['imgTotal']);
        $this->assertSame($before['imgOnerrorTotal'], $after['imgOnerrorTotal']);
        $this->assertSame(0, $after['imgInjected']);

        $texts = $this->ldJsonScriptTexts($after);
        $found = null;
        foreach ($texts as $raw) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && ($decoded['name'] ?? null) === 'Lima View Tours') {
                $found = $decoded;
                break;
            }
        }

        $this->assertNotNull($found, 'El JSON-LD benigno no se encontró o dejó de ser válido tras el fix.');
        $this->assertSame('https://limaviewtours.com', $found['url']);
    }
}

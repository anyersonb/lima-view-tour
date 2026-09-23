<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the PII-masking contract added on 2026-09-22: Microsoft Clarity and
 * Hotjar (loaded via GTM, see fix/csp 90d9923) must never record raw
 * customer PII from session replays.
 *
 * Note: /checkout/gracias (checkout/thanks.blade.php) is NOT covered here.
 * It renders tour title, dates, price, booking reference and payment
 * status — never customer_name/email/phone/pickup/notes (confirmed by
 * reading the view and its layout/components: no @include or <x-component>
 * there touches session PII). There is nothing to mask on that page today,
 * so no test is asserted against it. If a future change adds customer PII
 * to that page, mirror the pattern below (assert the exact element that
 * shows it carries data-clarity-mask="true" + data-hj-suppress).
 *
 * The test below parses the rendered DOM (DOMDocument/DOMXPath) instead of
 * doing a global str_contains() on the HTML: a masking attribute silently
 * dropped from ONE specific element (but still present elsewhere on the
 * page) must fail the test, not slip through because "the word appears
 * somewhere".
 */
class PiiMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    private function tour(array $overrides = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'price' => 150.00,
            'is_published' => true,
        ], $overrides));
    }

    private function crawl(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    // ─────────────────────────────────────────────────────────────
    //  /carrito (panel "Datos"): email y teléfono deben llevar
    //  data-hj-suppress, y el contenedor que agrupa el formulario debe
    //  llevar data-clarity-mask="true" + data-hj-suppress.
    // ─────────────────────────────────────────────────────────────

    public function test_checkout_form_suppresses_email_and_phone_inputs_from_hotjar(): void
    {
        $tour = $this->tour();
        app(CartService::class)->add($tour, 1, 0, now()->addDays(10)->format('Y-m-d'));

        $response = $this->get(route('cart.index', ['locale' => self::LOCALE]));
        $response->assertOk();

        $xpath = $this->crawl($response->getContent());

        $emailInputs = $xpath->query("//input[@id='customer_email']");
        $this->assertSame(
            1,
            $emailInputs->length,
            'No se encontró (o se encontró más de una vez) el input #customer_email en /carrito.'
        );
        $this->assertTrue(
            $emailInputs->item(0)->hasAttribute('data-hj-suppress'),
            '#customer_email debe llevar data-hj-suppress.'
        );

        $phoneInputs = $xpath->query("//input[@id='phone_local']");
        $this->assertSame(
            1,
            $phoneInputs->length,
            'No se encontró (o se encontró más de una vez) el input #phone_local en /carrito.'
        );
        $this->assertTrue(
            $phoneInputs->item(0)->hasAttribute('data-hj-suppress'),
            '#phone_local debe llevar data-hj-suppress.'
        );

        // El contenedor que agrupa todo el panel de datos personales
        // (nombre, teléfono, email, pickup, notas) debe llevar ambos
        // atributos de enmascarado.
        $nameInputs = $xpath->query("//input[@id='customer_name']");
        $this->assertSame(1, $nameInputs->length, 'No se encontró el input #customer_name en /carrito.');

        $ancestor = $nameInputs->item(0)->parentNode;
        $maskedAncestor = null;
        while ($ancestor !== null) {
            if ($ancestor->nodeType === XML_ELEMENT_NODE
                && $ancestor->hasAttribute('data-clarity-mask')
                && $ancestor->getAttribute('data-clarity-mask') === 'true'
                && $ancestor->hasAttribute('data-hj-suppress')) {
                $maskedAncestor = $ancestor;
                break;
            }
            $ancestor = $ancestor->parentNode;
        }

        $this->assertNotNull(
            $maskedAncestor,
            'Ningún ancestro de #customer_name lleva data-clarity-mask="true" + data-hj-suppress '
                .'(el contenedor .cart-card del panel Datos).'
        );
    }
}

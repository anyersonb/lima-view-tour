<?php

namespace Tests\Feature;

use App\Models\Booking;
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
 * Both tests parse the rendered DOM (DOMDocument/DOMXPath) instead of doing
 * a global str_contains() on the HTML: a masking attribute silently dropped
 * from ONE specific element (but still present elsewhere on the page) must
 * fail the test, not slip through because "the word appears somewhere".
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
    //  /checkout/gracias: el nombre del cliente debe ir DENTRO de un
    //  elemento marcado para Clarity y Hotjar.
    // ─────────────────────────────────────────────────────────────

    public function test_thanks_page_renders_customer_name_inside_masked_element(): void
    {
        $tour = $this->tour();

        $booking = Booking::create([
            'tour_id' => $tour->id,
            'tour_title_snapshot' => $tour->title_es,
            'customer_name' => 'Rosa Quispe Mamani',
            'customer_email' => 'rosa.quispe@example.com',
            'customer_phone' => '987000111',
            'travel_date' => now()->addDays(9)->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'unit_price' => 150.00,
            'total_price' => 150.00,
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paypal',
            'payment_reference' => 'CAPTURE-MASK-1',
            'locale' => 'es',
        ]);

        $response = $this->withSession([
            'last_bookings' => [$booking->toArray()],
        ])->get(route('checkout.thanks', ['locale' => self::LOCALE]));

        $response->assertOk();

        $xpath = $this->crawl($response->getContent());

        // Extracción vacía = falla: si el nombre no aparece como texto de
        // NINGÚN elemento, el test debe fallar aquí, no seguir de largo.
        $nameNodes = $xpath->query("//*[normalize-space(text())='Rosa Quispe Mamani']");
        $this->assertGreaterThan(
            0,
            $nameNodes->length,
            'El nombre del cliente no se encontró como texto de ningún elemento en /checkout/gracias.'
        );

        $maskedFound = false;
        foreach ($nameNodes as $node) {
            if ($node->getAttribute('data-clarity-mask') === 'true'
                && $node->hasAttribute('data-hj-suppress')) {
                $maskedFound = true;
                break;
            }
        }

        $this->assertTrue(
            $maskedFound,
            'El nombre del cliente debe estar DENTRO de un elemento con data-clarity-mask="true" y data-hj-suppress.'
        );
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

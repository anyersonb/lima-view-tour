<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El selector de código de país del checkout traía 16 países escritos a mano.
 * Pedido del 2026-08-25: "deben aparecer todos".
 */
class PhoneCountryCodesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El formulario de datos del cliente —y con él el selector— solo se
     * renderiza con el carrito cargado.
     */
    private function cartUrl(string $locale = 'es'): string
    {
        app(CartService::class)->add(
            Tour::factory()->create(['is_published' => true]),
            2,
            1,
            now()->addDays(10)->format('Y-m-d')
        );

        return "/{$locale}/carrito";
    }

    /** @test */
    public function the_config_covers_every_country_with_an_assigned_dial_code(): void
    {
        $countries = config('phone_codes.countries');

        // 242 territorios ISO 3166-1 con prefijo E.164 asignado.
        $this->assertGreaterThanOrEqual(240, count($countries));

        foreach ($countries as $iso => $country) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $iso);
            $this->assertMatchesRegularExpression('/^\d{1,4}$/', $country['dial'], "Prefijo inválido en {$iso}.");
            $this->assertNotEmpty($country['flag'], "Falta la bandera de {$iso}.");

            foreach (['es', 'en', 'pt'] as $locale) {
                $this->assertNotEmpty($country['names'][$locale] ?? null, "Falta el nombre de {$iso} en {$locale}.");
            }
        }
    }

    /** @test */
    public function the_checkout_renders_an_option_for_every_country(): void
    {
        $html = $this->get($this->cartUrl())->assertOk()->getContent();

        $countries = config('phone_codes.countries');

        // Peru + los destacados salen dos veces (grupo de frecuentes + lista
        // completa); el resto una sola.
        $featured = count(config('phone_codes.featured'));

        $this->assertSame(
            count($countries) + $featured,
            substr_count($html, 'data-iso="'),
            'El selector no está listando todos los países.'
        );

        // Muestras de los tres continentes que faltaban por completo.
        foreach (['JP' => '+81', 'ZA' => '+27', 'IN' => '+91', 'NO' => '+47'] as $iso => $dial) {
            $this->assertStringContainsString('data-iso="'.$iso.'"', $html);
            $this->assertStringContainsString('value="'.$dial.'"', $html);
        }
    }

    /** @test */
    public function peru_stays_preselected(): void
    {
        $html = $this->get($this->cartUrl())->assertOk()->getContent();

        // Se acota al selector de teléfono: la página tiene otros <select> con
        // su propia opción marcada.
        $this->assertSame(
            1,
            preg_match('/<select id="phone_prefix".*?<\/select>/s', $html, $m),
            'No se encontró el selector de código de país.'
        );

        // Tiene que quedar marcado el PE del grupo de frecuentes, y solo ese:
        // varios países comparten prefijo, así que preseleccionar por valor
        // ("+1", "+7") marcaría de más y el navegador mostraría el equivocado.
        $this->assertSame(1, preg_match_all('/data-iso="PE"[^>]*\bselected\b/', $m[0]));
        $this->assertSame(1, preg_match_all('/<option[^>]*\bselected\b/', $m[0]));
    }

    /** @test */
    public function the_names_are_translated_in_english_and_portuguese(): void
    {
        $en = $this->get($this->cartUrl('en'))->assertOk()->getContent();
        $pt = $this->get($this->cartUrl('pt'))->assertOk()->getContent();

        $this->assertStringContainsString('Germany', $en);
        $this->assertStringNotContainsString('· Alemania', $en);

        $this->assertStringContainsString('Alemanha', $pt);
    }
}

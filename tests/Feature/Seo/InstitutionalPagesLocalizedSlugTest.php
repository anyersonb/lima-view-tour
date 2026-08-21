<?php

namespace Tests\Feature\Seo;

use App\Support\LocalizedPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * URL por idioma de las páginas institucionales — propuesta ESPASEO v3
 * (secciones 4 y 5): Nosotros/About Us/Sobre Nós, Contacto, Reseñas y las dos
 * legales dejan de compartir el slug español entre los 3 idiomas.
 */
class InstitutionalPagesLocalizedSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La página de reseñas consulta Google Places cuando hay key cargada.
        // En test no debe salir a la red.
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['results' => []], 200)]);
    }

    /** @test */
    public function cada_pagina_responde_200_en_el_slug_de_su_idioma(): void
    {
        foreach (LocalizedPages::keys() as $key) {
            foreach (['es', 'en', 'pt'] as $locale) {
                $path = LocalizedPages::path($key, $locale);

                $this->get($path)->assertOk("Esperaba 200 en {$path} ({$key})");
            }
        }
    }

    /** @test */
    public function el_slug_espanol_redirige_301_al_traducido_en_en_y_pt(): void
    {
        foreach (LocalizedPages::keys() as $key) {
            $es = LocalizedPages::slugFor($key, 'es');

            foreach (['en', 'pt'] as $locale) {
                if (LocalizedPages::slugFor($key, $locale) === $es) {
                    continue; // sin traducción declarada, no hay nada que canonicalizar
                }

                $response = $this->get("/{$locale}/{$es}");

                $response->assertStatus(301);
                $response->assertRedirect(LocalizedPages::url($key, $locale));
            }
        }
    }

    /** @test */
    public function un_slug_de_otro_idioma_bajo_espanol_tambien_se_canonicaliza(): void
    {
        $response = $this->get('/es/about-us');

        $response->assertStatus(301);
        $response->assertRedirect(LocalizedPages::url('about', 'es'));
    }

    /** @test */
    public function el_301_conserva_la_query_string(): void
    {
        $response = $this->get('/en/nosotros?utm_source=newsletter&utm_medium=email');

        $response->assertStatus(301);
        $response->assertRedirect(
            LocalizedPages::url('about', 'en').'?utm_source=newsletter&utm_medium=email'
        );
    }

    /** @test */
    public function el_hreflang_y_el_canonical_apuntan_a_las_urls_reales(): void
    {
        $response = $this->get('/en/about-us');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="http://lima-tour.test/en/about-us">', false);
        $response->assertSee('hreflang="es"      href="http://lima-tour.test/es/nosotros"', false);
        $response->assertSee('hreflang="en"      href="http://lima-tour.test/en/about-us"', false);
        $response->assertSee('hreflang="pt"      href="http://lima-tour.test/pt/sobre-nos"', false);
        $response->assertSee('hreflang="x-default" href="http://lima-tour.test/es/nosotros"', false);
    }

    /** @test */
    public function los_enlaces_internos_ya_salen_traducidos_sin_pasar_por_el_301(): void
    {
        // Menú y pie se renderizan en el layout, así que cualquier página sirve.
        $response = $this->get('/en/contact-us');

        $response->assertOk();
        $response->assertSee('http://lima-tour.test/en/about-us', false);
        $response->assertSee('http://lima-tour.test/en/reviews', false);
        $response->assertSee('http://lima-tour.test/en/privacy', false);
        $response->assertDontSee('http://lima-tour.test/en/nosotros', false);
        $response->assertDontSee('http://lima-tour.test/en/resenas', false);
        $response->assertDontSee('http://lima-tour.test/en/privacidad', false);
    }

    /** @test */
    public function el_sitemap_publica_las_urls_traducidas_y_no_las_que_redirigen(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('http://lima-tour.test/en/about-us', false);
        $response->assertSee('http://lima-tour.test/pt/sobre-nos', false);
        $response->assertSee('http://lima-tour.test/es/nosotros', false);
        $response->assertDontSee('http://lima-tour.test/en/nosotros', false);
        $response->assertDontSee('http://lima-tour.test/pt/nosotros', false);
        $response->assertDontSee('http://lima-tour.test/en/resenas', false);
    }

    /** @test */
    public function ningun_slug_se_repite_entre_paginas_del_mismo_idioma(): void
    {
        // Dos páginas con el mismo slug en un idioma hacen inalcanzable a la
        // registrada después: la ruta de la primera se queda con la URL.
        foreach (['es', 'en', 'pt'] as $locale) {
            $slugs = [];

            foreach (LocalizedPages::keys() as $key) {
                $slugs[] = LocalizedPages::slugFor($key, $locale);
            }

            $this->assertSame(
                count($slugs),
                count(array_unique($slugs)),
                "Slugs repetidos en '{$locale}': ".implode(', ', $slugs)
            );
        }
    }

    /** @test */
    public function el_slug_cumple_el_formato_pedido_en_la_propuesta(): void
    {
        foreach (LocalizedPages::keys() as $key) {
            foreach (LocalizedPages::slugs($key) as $locale => $slug) {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    $slug,
                    "El slug '{$slug}' ({$key}/{$locale}) no es minúsculas-con-guiones sin tildes"
                );
                $this->assertLessThanOrEqual(60, strlen($slug), "El slug '{$slug}' pasa los 60 caracteres");
            }
        }
    }

    /** @test */
    public function route_sigue_funcionando_sin_pasar_el_slug(): void
    {
        // Red de seguridad: cualquier route('about', ['locale' => x]) que quede
        // en el código (o en un mail viejo) tiene que generar una URL válida,
        // no una excepción de parámetro faltante. Sale el slug español, que
        // luego se canonicaliza con el 301.
        $this->assertSame('http://lima-tour.test/en/nosotros', route('about', ['locale' => 'en']));
        $this->assertSame('http://lima-tour.test/es/contacto', route('contact', ['locale' => 'es']));
    }
}

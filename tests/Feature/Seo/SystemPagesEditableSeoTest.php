<?php

namespace Tests\Feature\Seo;

use App\Models\Setting;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metas editables de las páginas que NO son un contenido del CMS: home,
 * catálogo de tours, catálogo por región y listado del blog. Antes salían
 * fijas de lang/{idioma}/seo.php y no había forma de tocarlas desde el admin.
 *
 * Lo que se protege aquí: que el valor cargado en Configuración → SEO →
 * Metas por página sea el que realmente se emite en el <head>, que cada URL
 * tenga el suyo (el catálogo de Lima no puede heredar el de Cusco), y que un
 * campo vacío NO publique un title en blanco sino el texto por defecto.
 */
class SystemPagesEditableSeoTest extends TestCase
{
    use RefreshDatabase;

    private function setMeta(string $page, string $field, string $locale, string $value): void
    {
        Setting::set(PageSeo::settingKey($page, $field, $locale), $value);
    }

    private function region(string $slug, array $overrides = []): \App\Models\Region
    {
        return \App\Models\Region::create(array_merge([
            'slug' => $slug,
            'name_es' => ucfirst($slug),
            'name_en' => ucfirst($slug),
            'is_active' => true,
        ], $overrides));
    }

    private function title(string $html): string
    {
        preg_match('#<title>(.*?)</title>#si', $html, $m);

        return trim($m[1] ?? '');
    }

    private function description(string $html): string
    {
        preg_match('#<meta name="description" content="(.*?)"#si', $html, $m);

        return trim(html_entity_decode($m[1] ?? '', ENT_QUOTES));
    }

    /** @test */
    public function la_home_emite_el_title_y_la_description_cargados_en_el_admin(): void
    {
        $this->setMeta('home', 'title', 'es', 'Tours en Lima al mejor precio | Lima View Tours');
        $this->setMeta('home', 'description', 'es', 'Reserva tu tour en Lima con guías locales y confirmación inmediata.');

        $html = $this->get('/es')->assertOk()->getContent();

        $this->assertSame('Tours en Lima al mejor precio | Lima View Tours', $this->title($html));
        $this->assertSame(
            'Reserva tu tour en Lima con guías locales y confirmación inmediata.',
            $this->description($html)
        );
    }

    /** @test */
    public function cada_idioma_emite_su_propia_meta(): void
    {
        $this->setMeta('home', 'title', 'es', 'Título ES de la home');
        $this->setMeta('home', 'title', 'en', 'English home title');
        $this->setMeta('home', 'title', 'pt', 'Título PT da home');

        $this->assertSame('Título ES de la home', $this->title($this->get('/es')->getContent()));
        $this->assertSame('English home title', $this->title($this->get('/en')->getContent()));
        $this->assertSame('Título PT da home', $this->title($this->get('/pt')->getContent()));
    }

    /** @test */
    public function un_idioma_sin_cargar_cae_al_espanol(): void
    {
        $this->setMeta('home', 'title', 'es', 'Solo cargué el español');

        $this->assertSame('Solo cargué el español', $this->title($this->get('/en')->getContent()));
    }

    /** @test */
    public function sin_nada_cargado_se_mantiene_el_texto_por_defecto(): void
    {
        $html = $this->get('/es')->assertOk()->getContent();

        $this->assertSame(__('seo.home_title', [], 'es'), $this->title($html));
        $this->assertNotSame('', $this->title($html));
    }

    /** @test */
    public function un_campo_vacio_no_publica_un_title_en_blanco(): void
    {
        $this->setMeta('home', 'title', 'es', '   ');

        $this->assertSame(__('seo.home_title', [], 'es'), $this->title($this->get('/es')->getContent()));
    }

    /** @test */
    public function el_catalogo_y_cada_region_tienen_su_propia_meta(): void
    {
        $this->region('lima');
        $this->region('cusco');

        $this->setMeta('tours', 'title', 'es', 'Todos los tours — meta del catálogo');
        $this->setMeta('tours_lima', 'title', 'es', 'Tours en Lima — meta de Lima');
        $this->setMeta('tours_cusco', 'title', 'es', 'Tours en Cusco — meta de Cusco');

        $this->assertSame('Todos los tours — meta del catálogo', $this->title($this->get('/es/tours')->getContent()));
        $this->assertSame('Tours en Lima — meta de Lima', $this->title($this->get('/es/tours/categoria/lima')->getContent()));
        $this->assertSame('Tours en Cusco — meta de Cusco', $this->title($this->get('/es/tours/categoria/cusco')->getContent()));
    }

    /** @test */
    public function una_region_sin_meta_no_hereda_la_de_otra_region(): void
    {
        $this->region('lima');
        $this->region('ica');

        $this->setMeta('tours_lima', 'title', 'es', 'Tours en Lima — meta de Lima');

        $ica = $this->title($this->get('/es/tours/categoria/ica')->getContent());

        $this->assertNotSame('Tours en Lima — meta de Lima', $ica);
        $this->assertNotSame('', $ica);
    }

    /** @test */
    public function el_campo_seo_viejo_de_la_region_se_usa_si_no_hay_meta_por_idioma(): void
    {
        $this->region('ica', ['seo_title' => 'Meta vieja cargada en Regiones']);

        $this->assertSame(
            'Meta vieja cargada en Regiones',
            $this->title($this->get('/es/tours/categoria/ica')->getContent())
        );
    }

    /** @test */
    public function la_meta_por_idioma_le_gana_al_campo_viejo_de_la_region(): void
    {
        $this->region('ica', ['seo_title' => 'Meta vieja cargada en Regiones']);
        $this->setMeta('tours_ica', 'title', 'es', 'Meta nueva por idioma');

        $this->assertSame(
            'Meta nueva por idioma',
            $this->title($this->get('/es/tours/categoria/ica')->getContent())
        );
    }

    /** @test */
    public function el_listado_del_blog_toma_su_meta(): void
    {
        $this->setMeta('blog', 'title', 'es', 'Blog de viajes — meta cargada');
        $this->setMeta('blog', 'description', 'es', 'Guías y consejos para viajar por Perú.');

        $html = $this->get('/es/blog')->assertOk()->getContent();

        $this->assertSame('Blog de viajes — meta cargada', $this->title($html));
        $this->assertSame('Guías y consejos para viajar por Perú.', $this->description($html));
    }

    /** @test */
    public function editar_una_meta_se_ve_sin_limpiar_la_cache_a_mano(): void
    {
        $this->setMeta('home', 'title', 'es', 'Primera versión');
        $this->assertSame('Primera versión', $this->title($this->get('/es')->getContent()));

        $this->setMeta('home', 'title', 'es', 'Segunda versión');
        $this->assertSame('Segunda versión', $this->title($this->get('/es')->getContent()));
    }

    /** @test */
    public function el_aserto_falla_si_la_meta_no_se_aplica(): void
    {
        // Control: si PageSeo dejara de leer el setting, el resto de los tests
        // de este archivo tienen que ponerse rojos. Aquí se comprueba que el
        // título por defecto y el cargado son efectivamente distintos, o sea
        // que las aserciones de arriba miden algo.
        $porDefecto = $this->title($this->get('/es')->getContent());

        $this->setMeta('home', 'title', 'es', 'Meta cargada por el admin');
        $conMeta = $this->title($this->get('/es')->getContent());

        $this->assertNotSame($porDefecto, $conMeta);
    }
}

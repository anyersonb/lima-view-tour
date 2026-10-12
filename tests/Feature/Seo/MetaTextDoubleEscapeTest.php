<?php

namespace Tests\Feature\Seo;

use App\Models\Tour;
use App\Support\MetaText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** S-08: "&" in a meta title must reach <title>/og:title escaped exactly once. */
class MetaTextDoubleEscapeTest extends TestCase
{
    use RefreshDatabase;

    private function tour(string $metaTitleEn, string $metaDescEn = 'Plain description'): Tour
    {
        return Tour::factory()->create([
            'slug' => 'cusco-city-tour-es',
            'slug_en' => 'cusco-city-tour',
            'meta_title_en' => $metaTitleEn,
            'meta_description_en' => $metaDescEn,
            'is_published' => true,
        ]);
    }

    public function test_plain_ampersand_is_escaped_once(): void
    {
        $this->tour('Cusco City Tour: Qoricancha & Sacsayhuaman', "Don't miss Cusco & Sacsayhuaman");

        $html = $this->get('/en/tours/detalle/cusco-city-tour')->assertOk()->getContent();

        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('<title>Cusco City Tour: Qoricancha &amp; Sacsayhuaman</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Cusco City Tour: Qoricancha &amp; Sacsayhuaman">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Cusco City Tour: Qoricancha &amp; Sacsayhuaman">', $html);
        $this->assertStringContainsString('<meta name="description" content="Don&#039;t miss Cusco &amp; Sacsayhuaman">', $html);
        $this->assertStringNotContainsString('&amp;#039;', $html);
    }

    public function test_value_stored_with_an_entity_is_also_escaped_once(): void
    {
        $this->tour('Lima City Tour with Catacombs &amp; Old Town');

        $html = $this->get('/en/tours/detalle/cusco-city-tour')->getContent();

        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('<title>Lima City Tour with Catacombs &amp; Old Town</title>', $html);
    }

    public function test_markup_in_the_title_stays_escaped(): void
    {
        $this->tour('<script>alert(1)</script> & co');

        $html = $this->get('/en/tours/detalle/cusco-city-tour')->getContent();

        $this->assertStringContainsString('<title>&lt;script&gt;alert(1)&lt;/script&gt; &amp; co</title>', $html);
        $this->assertStringNotContainsString('<title><script>', $html);
    }

    public function test_plain_decodes_to_stable_text(): void
    {
        $this->assertSame('A & B', MetaText::plain('A &amp;amp; B'));
        $this->assertSame("It's", MetaText::plain('It&#039;s'));
        $this->assertSame('', MetaText::plain(null));
    }
}

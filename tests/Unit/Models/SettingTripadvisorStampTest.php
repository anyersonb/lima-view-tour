<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting::tripadvisorStamp() — sello manual de Tripadvisor (sin API key
 * aprobada todavía). Nunca debe devolver un porcentaje inventado, y el
 * campo nuevo debe entrar a la caché de settings.all sin cache:clear manual
 * (trampa ya conocida en este proyecto: ver Setting::booted()).
 */
class SettingTripadvisorStampTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_percent_is_not_configured(): void
    {
        $this->assertNull(Setting::tripadvisorStamp());
    }

    public function test_returns_null_when_percent_is_an_empty_string(): void
    {
        Setting::set('tripadvisor_recommend_percent', '');

        $this->assertNull(Setting::tripadvisorStamp());
    }

    public function test_returns_the_real_configured_values(): void
    {
        Setting::set('tripadvisor_recommend_percent', '96');
        Setting::set('tripadvisor_reviews_count', '187');
        Setting::set('tripadvisor_stats_updated_at', '2026-09-01');

        $stamp = Setting::tripadvisorStamp();

        $this->assertSame(96, $stamp['percent']);
        $this->assertSame(187, $stamp['count']);
        $this->assertSame('2026-09-01', $stamp['updated_at']->toDateString());
    }

    public function test_count_and_updated_at_are_null_when_not_configured_but_percent_is(): void
    {
        Setting::set('tripadvisor_recommend_percent', '80');

        $stamp = Setting::tripadvisorStamp();

        $this->assertSame(80, $stamp['percent']);
        $this->assertNull($stamp['count']);
        $this->assertNull($stamp['updated_at']);
    }

    /**
     * Trampa ya documentada en este proyecto (Setting::get() cachea
     * 'settings.all' con rememberForever): un campo NUEVO tiene que entrar
     * de verdad tras guardarse, no quedar sirviendo null para siempre.
     */
    public function test_a_brand_new_setting_key_is_readable_immediately_after_being_set(): void
    {
        // Lee primero (puebla/cachea el estado "sin configurar").
        $this->assertNull(Setting::get('tripadvisor_recommend_percent'));

        Setting::set('tripadvisor_recommend_percent', '99');

        $this->assertSame('99', Setting::get('tripadvisor_recommend_percent'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\HomeCopyAlignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** 2026-10-12 client decision: "+11 años" everywhere and a non-universal pickup answer. */
class HomeCopyAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_10_12_000003_align_home_years_and_pickup_copy.php');
    }

    private function raw(string $key): ?string
    {
        return \Illuminate\Support\Facades\DB::table('settings')->where('key', $key)->value('value');
    }

    public function test_it_rewrites_only_the_stale_values_and_is_idempotent(): void
    {
        Setting::set('stats_years', '+16');
        Setting::set('home_faqs', [
            ['q_es' => '¿Incluyen recojo desde el hotel?', 'q_en' => 'Do you offer hotel pickup?', 'a_es' => 'Sí, todos...', 'a_en' => 'Yes, all...', 'a_pt' => 'Sim, todos...'],
            ['q_es' => '¿Es seguro?', 'a_es' => 'Contamos con +16 años de experiencia, guías.', 'a_en' => 'We have 16+ years of experience.', 'a_pt' => 'Temos mais de 10 anos de experiência.'],
        ], 'json');
        Setting::set('faqs', json_encode([
            ['question_es' => '¿Hay recojo?', 'answer_es' => 'En la mayoría de nuestros tours', 'answer_en' => 'In most tours'],
            ['question_es' => '¿Cancelación?', 'answer_es' => 'Hasta 24 h antes.'],
        ], JSON_UNESCAPED_UNICODE));
        Setting::set('about_note', 'Niños de más de 10 años pagan adulto.');
        Setting::set('hero_text', 'Más de 10 años de experiencia en Perú');

        $this->migration()->up();

        $this->assertSame('+11', $this->raw('stats_years'));

        $home = json_decode($this->raw('home_faqs'), true);
        $this->assertSame(HomeCopyAlignment::PICKUP['es'], $home[0]['a_es']);
        $this->assertSame(HomeCopyAlignment::PICKUP['en'], $home[0]['a_en']);
        $this->assertSame(HomeCopyAlignment::PICKUP['pt'], $home[0]['a_pt']);
        $this->assertSame('Contamos con +11 años de experiencia, guías.', $home[1]['a_es']);
        $this->assertSame('We have 11+ years of experience.', $home[1]['a_en']);
        $this->assertSame('Temos mais de 11 anos de experiência.', $home[1]['a_pt']);

        $faqs = json_decode($this->raw('faqs'), true);
        $this->assertSame(HomeCopyAlignment::PICKUP['es'], $faqs[0]['answer_es']);
        $this->assertSame(HomeCopyAlignment::PICKUP['en'], $faqs[0]['answer_en']);
        $this->assertSame('Hasta 24 h antes.', $faqs[1]['answer_es'], 'unrelated FAQ untouched');

        $this->assertSame('Niños de más de 10 años pagan adulto.', $this->raw('about_note'), 'an age rule is not seniority');
        $this->assertSame('Más de 11 años de experiencia en Perú', $this->raw('hero_text'));

        // Second run changes nothing and logs no backup.
        Log::spy();
        $before = \Illuminate\Support\Facades\DB::table('settings')->orderBy('key')->pluck('value', 'key')->all();
        $this->migration()->up();
        $after = \Illuminate\Support\Facades\DB::table('settings')->orderBy('key')->pluck('value', 'key')->all();
        $this->assertSame($before, $after);
        Log::shouldNotHaveReceived('critical');
    }

    public function test_previous_values_go_to_log_critical_before_updating(): void
    {
        Setting::set('stats_years', '+16');
        Log::spy();

        $this->migration()->up();

        Log::shouldHaveReceived('critical')
            ->withArgs(fn ($msg, $ctx) => ($ctx['stats_years']['from'] ?? null) === '+16' && ($ctx['stats_years']['to'] ?? null) === '+11')
            ->once();
    }

    public function test_correct_values_are_left_alone(): void
    {
        Setting::set('stats_years', '+11');
        Log::spy();

        $this->migration()->up();

        $this->assertSame('+11', $this->raw('stats_years'));
        Log::shouldNotHaveReceived('critical');
    }

    public function test_lang_says_eleven_years_and_has_the_pickup_answer_in_all_locales(): void
    {
        foreach (['es', 'en', 'pt'] as $l) {
            $this->assertStringContainsString('11', __('ui.over_10_years', [], $l));
            $this->assertStringNotContainsString('10', __('ui.over_10_years', [], $l));
            $this->assertSame(HomeCopyAlignment::PICKUP[$l], __('ui.home_faq_pickup_answer', [], $l));
        }
    }
}

<?php

namespace Tests\Feature;

use App\Mail\ComplaintReceipt;
use App\Mail\ComplaintReceivedAdmin;
use App\Models\Complaint;
use App\Models\Setting;
use App\Support\LegalInfo;
use App\Support\LocalizedPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Libro de Reclamaciones virtual (INDECOPI) + Política de devoluciones +
 * datos legales administrables (exigencias Culqi).
 */
class ComplaintsBookTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'first_name' => 'María',
            'last_name' => 'Quispe Rojas',
            'doc_type' => 'dni',
            'doc_number' => '12345678',
            'address' => 'Av. Arequipa 123, Lima',
            'phone' => '+51 999 888 777',
            'email' => 'maria@example.com',
            'good_type' => 'service',
            'booking_reference' => 'lvt-abcd1234',
            'claimed_amount' => '150,50',
            'currency' => 'USD',
            'good_description' => 'Tour Islas Ballestas 20/11',
            'type' => 'claim',
            'detail' => 'El tour salió 1 hora tarde y no hubo guía en inglés.',
            'consumer_request' => 'Devolución parcial del importe.',
            'accepted_declaration' => '1',
            'website' => '',
        ], $o);
    }

    private function submitUrl(string $locale = 'es'): string
    {
        return route('complaints.submit', ['locale' => $locale]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pages_render_200_in_three_locales_with_legal_notes(): void
    {
        foreach (['es', 'en', 'pt'] as $locale) {
            foreach (['complaints', 'legal.refund'] as $key) {
                $this->get(LocalizedPages::path($key, $locale))->assertOk();
            }
        }

        $this->get('/es/libro-de-reclamaciones')
            ->assertSee('no impide acudir a otras vías de solución de controversias', false)
            ->assertSee('quince (15) días hábiles')
            ->assertSee('name="website"', false);
        $this->get('/en/complaints-book')->assertOk();
        $this->get('/pt/livro-de-reclamacoes')->assertOk();
        $this->get('/en/refund-policy')->assertOk();
        $this->get('/pt/politica-de-reembolso')->assertOk();
    }

    public function test_valid_submission_creates_record_code_and_queues_both_mails(): void
    {
        Mail::fake();
        Setting::set('contact_email', 'admin@lvt.test');
        Carbon::setTestNow('2026-11-18 10:00:00'); // miércoles

        $res = $this->post($this->submitUrl(), $this->payload());

        $res->assertRedirect(LocalizedPages::url('complaints', 'es'));
        $c = Complaint::firstOrFail();
        $this->assertSame('R-2026-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT), $c->code);
        $this->assertMatchesRegularExpression('/^R-\d{4}-\d{6}$/', $c->code);
        $this->assertSame('pending', $c->status);
        $this->assertSame('LVT-ABCD1234', $c->booking_reference);
        $this->assertSame('150.50', (string) $c->claimed_amount);
        $this->assertSame('USD', $c->currency);
        // 15 días hábiles desde miércoles 18-nov → miércoles 9-dic
        $this->assertSame('2026-12-09', $c->response_due_at->toDateString());

        Mail::assertQueued(ComplaintReceipt::class, fn ($m) => $m->hasTo('maria@example.com'));
        Mail::assertQueued(ComplaintReceivedAdmin::class, fn ($m) => $m->hasTo('admin@lvt.test'));

        // Estado de éxito con el código en pantalla (el flash sobrevive al redirect)
        $this->get(LocalizedPages::path('complaints', 'es'))->assertSee($c->code);
    }

    public function test_receipt_mail_renders_in_each_locale(): void
    {
        foreach (['es' => 'hoja de reclamación', 'en' => 'complaint book sheet', 'pt' => 'folha do livro de reclamações'] as $locale => $needle) {
            $c = Complaint::register(array_merge(
                collect($this->payload())->except(['website'])->all(),
                ['locale' => $locale, 'accepted_declaration' => true, 'claimed_amount' => 10, 'is_minor' => false]
            ));
            $mail = new ComplaintReceipt($c);
            $html = $mail->render();
            $this->assertStringContainsString($c->code, $html);
            $this->assertStringContainsStringIgnoringCase($needle, $mail->envelope()->subject);
        }
    }

    public function test_validation_errors_are_shown_per_field(): void
    {
        Mail::fake();

        $res = $this->from('/es/libro-de-reclamaciones')->post($this->submitUrl(), [
            'doc_type' => 'dni', 'doc_number' => '123', 'email' => 'no-es-correo',
        ]);

        $res->assertSessionHasErrors(['first_name', 'last_name', 'doc_number', 'address', 'phone', 'email', 'good_type', 'good_description', 'type', 'detail', 'consumer_request', 'accepted_declaration']);
        $this->assertSame(0, Complaint::count());
        Mail::assertNothingQueued();

        $page = $this->followingRedirects()->from('/es/libro-de-reclamaciones')->post($this->submitUrl(), [
            'doc_type' => 'dni', 'doc_number' => '123',
        ]);
        $page->assertSee('aria-invalid="true"', false)->assertSee('El DNI debe tener 8 dígitos.');
    }

    public function test_minor_requires_guardian_and_doc_rules_depend_on_type(): void
    {
        $this->post($this->submitUrl(), $this->payload(['is_minor' => '1', 'guardian_name' => '']))
            ->assertSessionHasErrors('guardian_name');

        $this->post($this->submitUrl(), $this->payload(['doc_type' => 'passport', 'doc_number' => 'AB1']))
            ->assertSessionHasErrors('doc_number');

        $this->post($this->submitUrl(), $this->payload(['doc_type' => 'ce', 'doc_number' => '001234567']))
            ->assertSessionDoesntHaveErrors('doc_number');

        $this->post($this->submitUrl('en'), $this->payload(['is_minor' => '1', 'guardian_name' => 'Ana Rojas', 'doc_number' => '87654321']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Ana Rojas', Complaint::latest('id')->first()->guardian_name);
    }

    public function test_honeypot_discards_the_submission(): void
    {
        Mail::fake();

        $this->post($this->submitUrl(), $this->payload(['website' => 'http://spam.test']))
            ->assertRedirect(LocalizedPages::url('complaints', 'es'));

        $this->assertSame(0, Complaint::count());
        Mail::assertNothingQueued();
    }

    public function test_post_is_throttled(): void
    {
        Mail::fake();
        $last = null;
        for ($i = 0; $i < 7; $i++) {
            $last = $this->post($this->submitUrl(), $this->payload(['website' => 'bot']));
        }
        $last->assertStatus(429);
    }

    public function test_legal_info_lines_hidden_when_empty_and_shown_when_set(): void
    {
        $this->assertSame([], LegalInfo::lines());
        $this->get('/es/terminos')->assertOk()->assertDontSee('RUC:');

        Setting::set('legal_business_name', 'Lima View Tours S.A.C.');
        Setting::set('legal_ruc', '20123456789');
        Setting::set('legal_address', 'Jr. Lampa 209, Lima');

        foreach (['/es/terminos', '/es/privacidad', '/es/politica-de-cambios-y-devoluciones', '/es/libro-de-reclamaciones'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('Lima View Tours S.A.C.')
                ->assertSee('20123456789')
                ->assertSee('Jr. Lampa 209, Lima');
        }
    }

    public function test_terms_use_unified_phone_mention_culqi_and_link_new_pages(): void
    {
        $this->get('/es/terminos')
            ->assertOk()
            ->assertDontSee('935 542 384')
            ->assertSee('+51 925 886 725')
            ->assertSee('Culqi')
            ->assertSee(LocalizedPages::url('legal.refund', 'es'), false)
            ->assertSee(LocalizedPages::url('complaints', 'es'), false);

        Setting::set('contact_phone', '+51 900 111 222');
        $this->get('/en/terms')->assertSee('+51 900 111 222')->assertDontSee('925 886 725');

        foreach (['es', 'en', 'pt'] as $locale) {
            $this->assertStringContainsString('Culqi', __('legal.privacy_s1_body', [], $locale));
        }
    }

    public function test_footer_links_to_complaints_and_refund_in_each_locale(): void
    {
        foreach (['es', 'en', 'pt'] as $locale) {
            $html = $this->get(LocalizedPages::path('legal.terms', $locale))->getContent();
            $this->assertStringContainsString(LocalizedPages::url('complaints', $locale), $html);
            $this->assertStringContainsString(LocalizedPages::url('legal.refund', $locale), $html);
        }
    }

    public function test_migration_down_drops_the_table(): void
    {
        $this->assertTrue(Schema::hasTable('complaints'));
        $migration = require database_path('migrations/2026_11_18_000001_create_complaints_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('complaints'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('complaints'));
    }
}

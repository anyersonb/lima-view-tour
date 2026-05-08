<?php

namespace Tests\Feature;

use App\Mail\ContactAcknowledgement;
use App\Mail\ContactReceived;
use App\Models\ContactLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'es';

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter state between tests
        RateLimiter::clear('contact');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre'  => 'Juan',
            'apellido' => 'Pérez',
            'celular' => '999888777',
            'email'   => 'juan@example.com',
            'mensaje' => 'Me gustaría información sobre sus tours.',
            'website' => '', // honeypot empty = real user
        ], $overrides);
    }

    private function submitUrl(): string
    {
        return route('contact.submit', ['locale' => self::LOCALE]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Honeypot
    // ─────────────────────────────────────────────────────────────

    public function test_contact_form_rejects_filled_honeypot(): void
    {
        Mail::fake();

        $response = $this->post($this->submitUrl(), $this->validPayload([
            'website' => 'http://spam.example.com',
        ]));

        // Bot gets silently redirected to thanks, but NO lead is created
        $response->assertRedirect(route('contact.thanks', ['locale' => self::LOCALE]));
        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingQueued();
    }

    // ─────────────────────────────────────────────────────────────
    //  Rate limit
    // ─────────────────────────────────────────────────────────────

    public function test_contact_form_rate_limited_after_3_submissions_per_minute(): void
    {
        Mail::fake();

        // 3 successful submissions
        for ($i = 0; $i < 3; $i++) {
            $this->post($this->submitUrl(), $this->validPayload(['email' => "user{$i}@example.com"]))->assertRedirect();
        }

        // 4th should be throttled
        $response = $this->post($this->submitUrl(), $this->validPayload(['email' => 'fourth@example.com']));
        $response->assertStatus(429);
    }

    // ─────────────────────────────────────────────────────────────
    //  Happy path
    // ─────────────────────────────────────────────────────────────

    public function test_contact_form_creates_lead_and_sends_emails(): void
    {
        Mail::fake();

        $response = $this->post($this->submitUrl(), $this->validPayload());

        $response->assertRedirect(route('contact.thanks', ['locale' => self::LOCALE]));

        // Lead persisted
        $this->assertDatabaseHas('contact_leads', [
            'email'  => 'juan@example.com',
            'name'   => 'Juan',
            'source' => 'contact_form',
            'locale' => self::LOCALE,
        ]);

        // Both emails queued
        Mail::assertQueued(ContactReceived::class, function ($mail) {
            return $mail->lead->email === 'juan@example.com';
        });

        Mail::assertQueued(ContactAcknowledgement::class, function ($mail) {
            return $mail->lead->email === 'juan@example.com';
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────────

    public function test_contact_form_validates_email_required_message(): void
    {
        Mail::fake();

        $response = $this->post($this->submitUrl(), $this->validPayload(['email' => '']));

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingQueued();
    }
}

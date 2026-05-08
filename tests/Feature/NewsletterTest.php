<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('newsletter');
    }

    private function subscribeUrl(): string
    {
        return route('newsletter.subscribe');
    }

    // ─────────────────────────────────────────────────────────────
    //  Subscribe — happy path
    // ─────────────────────────────────────────────────────────────

    public function test_newsletter_creates_unconfirmed_subscriber_and_sends_email(): void
    {
        Mail::fake();

        $response = $this->post($this->subscribeUrl(), [
            'email'   => 'viajero@example.com',
            'name'    => 'María',
            'website' => '', // honeypot empty
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('newsletter_success');

        $subscriber = NewsletterSubscriber::where('email', 'viajero@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->confirmation_token);

        Mail::assertQueued(NewsletterConfirmation::class, function ($mail) use ($subscriber) {
            return $mail->subscriber->id === $subscriber->id;
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Honeypot
    // ─────────────────────────────────────────────────────────────

    public function test_newsletter_rejects_honeypot(): void
    {
        Mail::fake();

        $response = $this->post($this->subscribeUrl(), [
            'email'   => 'bot@spam.com',
            'website' => 'http://spam-site.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Mail::assertNothingQueued();
    }

    // ─────────────────────────────────────────────────────────────
    //  Confirm link
    // ─────────────────────────────────────────────────────────────

    public function test_newsletter_confirm_link_marks_confirmed_at(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email'              => 'confirm@example.com',
            'locale'             => 'es',
            'is_active'          => true,
            'subscribed_at'      => now(),
            'confirmation_token' => 'test-token-123456789012345678901234567890123456',
        ]);

        $response = $this->get(route('newsletter.confirm', ['token' => 'test-token-123456789012345678901234567890123456']));

        $response->assertOk();
        $response->assertViewIs('newsletter.confirmed');

        $subscriber->refresh();
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->unsubscribe_token);
    }

    public function test_newsletter_confirm_invalid_token_404(): void
    {
        $response = $this->get(route('newsletter.confirm', ['token' => 'invalid-token-that-does-not-exist']));

        $response->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────
    //  Already confirmed
    // ─────────────────────────────────────────────────────────────

    public function test_newsletter_already_confirmed_returns_message(): void
    {
        Mail::fake();

        NewsletterSubscriber::create([
            'email'        => 'confirmed@example.com',
            'locale'       => 'es',
            'is_active'    => true,
            'subscribed_at' => now(),
            'confirmed_at'  => now(),
        ]);

        $response = $this->post($this->subscribeUrl(), [
            'email'   => 'confirmed@example.com',
            'website' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('newsletter_success');

        // No new confirmation email should be sent
        Mail::assertNothingQueued();
    }

    // ─────────────────────────────────────────────────────────────
    //  Unsubscribe
    // ─────────────────────────────────────────────────────────────

    public function test_newsletter_unsubscribe_works(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email'             => 'unsub@example.com',
            'locale'            => 'es',
            'is_active'         => true,
            'subscribed_at'     => now(),
            'confirmed_at'      => now(),
            'unsubscribe_token' => 'unsub-token-abcdefghijklmnopqrstuvwxyz123456789012',
        ]);

        $response = $this->get(route('newsletter.unsubscribe', ['token' => 'unsub-token-abcdefghijklmnopqrstuvwxyz123456789012']));

        $response->assertOk();
        $response->assertViewIs('newsletter.unsubscribed');

        $subscriber->refresh();
        $this->assertFalse($subscriber->is_active);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }
}

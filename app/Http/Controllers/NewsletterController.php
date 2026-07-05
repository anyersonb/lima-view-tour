<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        // Honeypot: bots fill hidden fields that real users never see
        if ($request->filled('website')) {
            Log::info('newsletter.honeypot_triggered', ['ip' => $request->ip()]);

            return back()->with('newsletter_success', '¡Gracias por suscribirte!');
        }

        // reCAPTCHA verification (no-op when module is disabled)
        // v3 envía recaptcha_token (hidden); el checkbox v2 envía g-recaptcha-response
        if (! $recaptcha->verify($request->input('recaptcha_token') ?: $request->input('g-recaptcha-response'), 'newsletter', $request->ip())) {
            return back()
                ->withInput()
                ->withErrors(['recaptcha' => __('recaptcha.failed')]);
        }

        $data = $request->validate([
            'name'  => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:200'],
        ]);

        try {
            $subscriber = NewsletterSubscriber::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'         => $data['name'] ?? null,
                    'locale'       => app()->getLocale(),
                    'is_active'    => true,
                    'subscribed_at' => now(),
                ]
            );

            // Already confirmed — nothing else to do
            if ($subscriber->isConfirmed()) {
                return back()->with('newsletter_success', '¡Gracias por suscribirte!');
            }

            // Generate token and send confirmation email
            $subscriber->update([
                'confirmation_token' => Str::random(48),
                'subscribed_at'      => now(),
                'unsubscribed_at'    => null,
                'is_active'          => true,
            ]);

            // Refresh to get the persisted token
            $subscriber->refresh();

            Mail::to($subscriber->email)->queue(new NewsletterConfirmation($subscriber));

            Log::info('newsletter.confirmation_sent', [
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('newsletter.subscribe_failed', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? null,
            ]);
        }

        return back()->with('newsletter_success', 'Te enviamos un correo para confirmar tu suscripcion.');
    }

    public function confirm(string $token): View
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)->firstOrFail();

        $subscriber->update([
            'confirmed_at'      => now(),
            'unsubscribe_token' => Str::random(48),
            'is_active'         => true,
        ]);

        Log::info('newsletter.confirmed', ['subscriber_id' => $subscriber->id]);

        return view('newsletter.confirmed', ['locale' => $subscriber->locale]);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->firstOrFail();

        $subscriber->update([
            'is_active'       => false,
            'unsubscribed_at' => now(),
        ]);

        Log::info('newsletter.unsubscribed', ['subscriber_id' => $subscriber->id]);

        return view('newsletter.unsubscribed');
    }
}

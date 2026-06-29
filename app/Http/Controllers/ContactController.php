<?php

namespace App\Http\Controllers;

use App\Mail\ContactAcknowledgement;
use App\Mail\ContactReceived;
use App\Models\ContactLead;
use App\Models\Page;
use App\Models\Setting;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        $page = Page::where('slug', 'contacto')->first();

        return view('contact', compact('page'));
    }

    public function submit(string $locale, Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        // Honeypot: bots fill hidden fields that real users never see
        if ($request->filled('website')) {
            Log::info('contact.honeypot_triggered', ['ip' => $request->ip()]);

            return redirect()->route('contact.thanks', ['locale' => $locale]);
        }

        // reCAPTCHA verification (no-op when module is disabled)
        if (! $recaptcha->verify($request->input('recaptcha_token'), 'contact', $request->ip())) {
            return back()
                ->withInput()
                ->withErrors(['recaptcha' => __('recaptcha.failed')]);
        }

        $data = $request->validate([
            'nombre'   => ['required', 'string', 'max:120'],
            'apellido' => ['nullable', 'string', 'max:120'],
            'celular'  => ['nullable', 'string', 'max:30'],
            'email'    => ['required', 'email', 'max:200'],
            'mensaje'  => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        try {
            $lead = ContactLead::create([
                'name'       => $data['nombre'],
                'lastname'   => $data['apellido'] ?? null,
                'phone'      => $data['celular'] ?? null,
                'email'      => $data['email'],
                'message'    => $data['mensaje'],
                'source'     => 'contact_form',
                'locale'     => $locale,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Notify admin
            $adminEmail = Setting::get('contact_email', config('mail.from.address'));
            Mail::to($adminEmail)->queue(new ContactReceived($lead));

            // Acknowledge user
            Mail::to($lead->email)->queue(new ContactAcknowledgement($lead));

            Log::info('contact.lead_created', [
                'lead_id' => $lead->id,
                'email'   => $lead->email,
                'locale'  => $locale,
            ]);
        } catch (\Throwable $e) {
            Log::error('contact.submit_failed', [
                'error'  => $e->getMessage(),
                'ip'     => $request->ip(),
                'locale' => $locale,
            ]);
        }

        return redirect()->route('contact.thanks', ['locale' => $locale]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\ComplaintReceivedAdmin;
use App\Mail\ComplaintReceipt;
use App\Models\Complaint;
use App\Services\RecaptchaService;
use App\Support\LegalInfo;
use App\Support\LocalizedPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Libro de Reclamaciones virtual (INDECOPI, D.S. 011-2011-PCM y modificatorias).
 * Mismo patrón anti-bot que ContactController: honeypot + reCAPTCHA (no-op si
 * el módulo está apagado) + throttle en la ruta.
 */
class ComplaintController extends Controller
{
    public function show(): View
    {
        return view('pages.complaints', [
            'locale' => app()->getLocale(),
            'title' => __('complaints.page_title'),
            // Flash del registro recién creado (código + fecha límite).
            'registered' => session('complaint_registered'),
            'dueDays' => Complaint::RESPONSE_BUSINESS_DAYS,
        ]);
    }

    public function submit(string $locale, Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $back = LocalizedPages::url('complaints', $locale);

        // Honeypot: un humano nunca ve este campo.
        if ($request->filled('website')) {
            Log::info('complaint.honeypot_triggered', ['ip' => $request->ip()]);

            return redirect()->to($back);
        }

        if (! $recaptcha->verify($request->input('recaptcha_token') ?: $request->input('g-recaptcha-response'), 'complaint', $request->ip())) {
            return back()
                ->withInput()
                ->withErrors(['recaptcha' => __('recaptcha.failed')]);
        }

        $docType = (string) $request->input('doc_type');

        // "150,50" (coma decimal, habitual en es/pt) → "150.50"
        if ($request->filled('claimed_amount')) {
            $request->merge(['claimed_amount' => str_replace(',', '.', trim((string) $request->input('claimed_amount')))]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'doc_type' => ['required', Rule::in(['dni', 'ce', 'passport'])],
            'doc_number' => ['required', 'string', 'max:20', match ($docType) {
                'dni' => 'regex:/^[0-9]{8}$/',
                'ce' => 'regex:/^[A-Za-z0-9]{9,12}$/',
                default => 'regex:/^[A-Za-z0-9]{6,12}$/',
            }],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'email' => ['required', 'email:rfc', 'max:200'],
            'is_minor' => ['nullable', 'boolean'],
            'guardian_name' => ['nullable', 'required_if:is_minor,1', 'string', 'max:255'],
            'good_type' => ['required', Rule::in(['product', 'service'])],
            'booking_reference' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'claimed_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', Rule::in(['PEN', 'USD'])],
            'good_description' => ['required', 'string', 'min:5', 'max:2000'],
            'type' => ['required', Rule::in([Complaint::TYPE_CLAIM, Complaint::TYPE_COMPLAINT])],
            'detail' => ['required', 'string', 'min:10', 'max:4000'],
            'consumer_request' => ['required', 'string', 'min:5', 'max:4000'],
            'accepted_declaration' => ['accepted'],
        ], $this->messages(), $this->attributes());

        $isMinor = (bool) ($data['is_minor'] ?? false);

        try {
            $complaint = Complaint::register([
                'locale' => $locale,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'doc_type' => $data['doc_type'],
                'doc_number' => strtoupper($data['doc_number']),
                'address' => $data['address'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'is_minor' => $isMinor,
                'guardian_name' => $isMinor ? $data['guardian_name'] : null,
                'good_type' => $data['good_type'],
                'booking_reference' => ($data['booking_reference'] ?? null) ? strtoupper($data['booking_reference']) : null,
                'claimed_amount' => $data['claimed_amount'] ?? null,
                'currency' => $data['currency'],
                'good_description' => $data['good_description'],
                'type' => $data['type'],
                'detail' => $data['detail'],
                'consumer_request' => $data['consumer_request'],
                'accepted_declaration' => true,
                'status' => Complaint::STATUS_PENDING,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            Log::error('complaint.create_failed', ['error' => $e->getMessage(), 'locale' => $locale]);

            return back()
                ->withInput()
                ->withErrors(['form' => __('complaints.err_save_failed')]);
        }

        // La hoja ya quedó registrada: un fallo de correo NO debe perderla ni
        // mostrarle un error al consumidor (queda en el panel igualmente).
        try {
            Mail::to(LegalInfo::email())->queue(new ComplaintReceivedAdmin($complaint));
            Mail::to($complaint->email)->queue(new ComplaintReceipt($complaint));
        } catch (\Throwable $e) {
            Log::error('complaint.mail_failed', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
        }

        Log::info('complaint.registered', ['complaint_id' => $complaint->id, 'code' => $complaint->code, 'locale' => $locale]);

        return redirect()->to($back)->with('complaint_registered', [
            'code' => $complaint->code,
            'due' => $complaint->response_due_at?->format('d/m/Y'),
            'email' => $complaint->email,
        ]);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => __('complaints.err_required'),
            'required_if' => __('complaints.err_required'),
            'email' => __('complaints.err_email'),
            'max' => __('complaints.err_max'),
            'min' => __('complaints.err_min'),
            'in' => __('complaints.err_in'),
            'numeric' => __('complaints.err_numeric'),
            'accepted' => __('complaints.err_accepted'),
            'doc_number.regex' => __('complaints.err_doc_number_'.$this->docKey()),
            'phone.regex' => __('complaints.err_phone'),
            'booking_reference.regex' => __('complaints.err_booking_reference'),
        ];
    }

    protected function docKey(): string
    {
        return in_array(request()->input('doc_type'), ['dni', 'ce'], true) ? request()->input('doc_type') : 'passport';
    }

    /** @return array<string, string> */
    protected function attributes(): array
    {
        return (array) __('complaints.attributes');
    }
}

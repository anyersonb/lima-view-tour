<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Returns true when reCAPTCHA is enabled in Settings (or .env fallback).
     */
    public function enabled(): bool
    {
        $setting = Setting::get('recaptcha_enabled');

        if ($setting !== null) {
            return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.recaptcha.enabled', false);
    }

    /**
     * Returns the configured reCAPTCHA version ('v2' or 'v3').
     */
    public function version(): string
    {
        return Setting::get('recaptcha_version') ?: config('services.recaptcha.version', 'v3');
    }

    /**
     * Returns the site key (public) from Settings or .env.
     */
    public function siteKey(): ?string
    {
        return Setting::get('recaptcha_site_key') ?: config('services.recaptcha.site_key');
    }

    /**
     * Returns the secret key (private) from Settings or .env.
     */
    public function secretKey(): ?string
    {
        return Setting::get('recaptcha_secret_key') ?: config('services.recaptcha.secret_key');
    }

    /**
     * Returns the v3 score threshold (0–1, default 0.5).
     */
    public function threshold(): float
    {
        $val = Setting::get('recaptcha_v3_threshold');

        return $val !== null
            ? (float) $val
            : (float) config('services.recaptcha.threshold', 0.5);
    }

    /**
     * Verify a reCAPTCHA token.
     *
     * Behaviour:
     * - If reCAPTCHA is disabled → always returns true (zero friction / no regression).
     * - If secret key is missing → logs a warning and returns false (fail-closed when enabled).
     * - Network errors → fail-closed: logs and returns false.
     * - v3: requires success AND score >= threshold (optionally checks action).
     * - v2: requires success only.
     *
     * @param string|null $token  The g-recaptcha-response / recaptcha_token value from the form.
     * @param string|null $action The expected v3 action name (e.g. "contact", "register").
     * @param string|null $ip     The end-user IP address (optional, improves accuracy).
     */
    public function verify(?string $token, ?string $action = null, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (empty($token)) {
            Log::warning('recaptcha.missing_token', ['action' => $action, 'ip' => $ip]);

            return false;
        }

        $secret = $this->secretKey();

        if (empty($secret)) {
            Log::warning('recaptcha.missing_secret_key');

            return false;
        }

        try {
            $payload = ['secret' => $secret, 'response' => $token];

            if ($ip) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::timeout(5)
                ->asForm()
                ->post(self::VERIFY_URL, $payload);

            if (! $response->successful()) {
                Log::warning('recaptcha.http_error', ['status' => $response->status()]);

                return false;
            }

            $body = $response->json();

            if (! ($body['success'] ?? false)) {
                Log::info('recaptcha.failed', [
                    'error-codes' => $body['error-codes'] ?? [],
                    'action'      => $action,
                    'ip'          => $ip,
                ]);

                return false;
            }

            // v3: additional score check
            if ($this->version() === 'v3') {
                $score = (float) ($body['score'] ?? 0.0);

                if ($score < $this->threshold()) {
                    Log::info('recaptcha.score_too_low', [
                        'score'     => $score,
                        'threshold' => $this->threshold(),
                        'action'    => $action,
                        'ip'        => $ip,
                    ]);

                    return false;
                }

                // Optionally validate action name
                if ($action !== null && isset($body['action']) && $body['action'] !== $action) {
                    Log::warning('recaptcha.action_mismatch', [
                        'expected' => $action,
                        'received' => $body['action'],
                        'ip'       => $ip,
                    ]);

                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('recaptcha.exception', [
                'error'  => $e->getMessage(),
                'action' => $action,
                'ip'     => $ip,
            ]);

            return false;
        }
    }
}

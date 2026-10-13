<?php

namespace App\Console\Commands;

use App\Services\BookingNotifier;
use App\Support\AnalyticsHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AnalyticsHealthCommand extends Command
{
    protected $signature = 'analytics:health';

    protected $description = 'Revisa que la medición (GA4/GTM) esté configurada; avisa por correo (1 vez cada 24 h) si no.';

    private const THROTTLE_KEY = 'analytics_health.alert_sent';

    public function handle(BookingNotifier $notifier): int
    {
        $problems = AnalyticsHealth::check();

        if ($problems === []) {
            $this->info('Midiendo visitas: OK');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        Log::error('analytics_health.problems', ['problems' => $problems]);

        // Cache::add is atomic: only the first caller within 24 h gets true.
        if (Cache::add(self::THROTTLE_KEY, now()->toIso8601String(), now()->addHours(24))) {
            try {
                $body = "La medición del sitio tiene problemas:\n\n- ".implode("\n- ", $problems)
                    ."\n\nMientras esto no se corrija, se pierden visitas en Analytics/Hotjar/Clarity/Ads."
                    ."\nConfiguración: panel admin > Ajustes > SEO.";
                $recipients = $notifier->adminRecipients();

                if ($recipients !== []) {
                    Mail::raw($body, fn ($m) => $m->to($recipients)->subject('[Acción requerida] La medición del sitio no está funcionando — Lima View Tours'));
                }
            } catch (\Throwable $e) {
                // Mail failed: release the throttle so tomorrow's run retries.
                Cache::forget(self::THROTTLE_KEY);
                Log::error('analytics_health.mail_failed', ['message' => $e->getMessage()]);
            }
        }

        return self::FAILURE;
    }
}

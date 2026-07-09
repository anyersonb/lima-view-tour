<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'carts:send-recovery
                            {--dry : Muestra qué se enviaría sin enviar correos ni tocar la BD}';

    protected $description = 'Envía correos de recuperación de carritos abandonados (recordatorios 1h y 24h)';

    public function handle(): int
    {
        $cfg          = config('cart.abandoned');
        $firstMin     = (int) ($cfg['first_reminder_after_minutes']  ?? 60);
        $secondMin    = (int) ($cfg['second_reminder_after_minutes'] ?? 60 * 24);
        $maxReminders = (int) ($cfg['max_reminders'] ?? 2);
        $expireDays   = (int) ($cfg['expire_after_days'] ?? 7);
        $batch        = (int) ($cfg['batch_size'] ?? 50);
        $dry          = (bool) $this->option('dry');

        $now             = now();
        $firstThreshold  = $now->copy()->subMinutes($firstMin);
        $secondThreshold = $now->copy()->subMinutes($secondMin);
        $expireThreshold = $now->copy()->subDays($expireDays);

        // 1) Caducar carritos activos demasiado viejos (ya no se recuperan)
        if (! $dry) {
            $expired = AbandonedCart::where('status', 'active')
                ->where('last_activity_at', '<=', $expireThreshold)
                ->update(['status' => 'expired']);

            if ($expired) {
                $this->info("Carritos caducados: {$expired}");
            }
        }

        // 2) Carritos con recordatorio pendiente
        $carts = AbandonedCart::where('status', 'active')
            ->whereNotNull('email')
            ->where('reminders_sent', '<', $maxReminders)
            ->where('last_activity_at', '>', $expireThreshold)
            ->where(function ($q) use ($firstThreshold, $secondThreshold) {
                $q->where(function ($q1) use ($firstThreshold) {
                    $q1->where('reminders_sent', 0)
                       ->where('last_activity_at', '<=', $firstThreshold);
                })->orWhere(function ($q2) use ($secondThreshold) {
                    $q2->where('reminders_sent', 1)
                       ->where('last_activity_at', '<=', $secondThreshold);
                });
            })
            ->orderBy('last_activity_at')
            ->limit($batch)
            ->get();

        if ($carts->isEmpty()) {
            $this->info('No hay carritos por recuperar.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($carts as $cart) {
            if (empty($cart->items)) {
                continue;
            }

            $reminderNumber = $cart->reminders_sent + 1;

            if ($dry) {
                $this->line("[dry] #{$cart->id} → {$cart->email} (recordatorio {$reminderNumber})");
                continue;
            }

            try {
                Mail::to($cart->email)->send(new AbandonedCartReminder($cart, $reminderNumber));

                $cart->forceFill([
                    'reminders_sent'   => $reminderNumber,
                    'last_reminder_at' => now(),
                ])->save();

                $sent++;

                Log::info('abandoned_cart.reminder.sent', [
                    'cart_id'  => $cart->id,
                    'email'    => $cart->email,
                    'reminder' => $reminderNumber,
                ]);
            } catch (\Throwable $e) {
                Log::warning('abandoned_cart.reminder.failed', [
                    'cart_id' => $cart->id,
                    'email'   => $cart->email,
                    'message' => $e->getMessage(),
                ]);
                $this->error("Fallo al enviar a {$cart->email}: {$e->getMessage()}");
            }
        }

        $this->info($dry
            ? "Simulación: {$carts->count()} carrito(s) elegibles."
            : "Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}

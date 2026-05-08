<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $payment
    ) {}

    /**
     * Handle incoming Culqi webhook events.
     *
     * Events handled:
     *  - charge.succeeded
     *  - charge.failed
     */
    public function culqi(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Culqi-Signature', '');

        // Validate HMAC signature
        if (! $this->payment->verifyWebhookSignature($payload, $signature)) {
            Log::warning('webhook.culqi.invalid_signature', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);

            abort(400, 'Invalid webhook signature.');
        }

        $event     = json_decode($payload, true);
        $eventType = $event['type'] ?? null;
        $chargeId  = $event['data']['id'] ?? null;

        Log::info('webhook.culqi.received', [
            'type'      => $eventType,
            'charge_id' => $chargeId,
        ]);

        match ($eventType) {
            'charge.succeeded' => $this->handleChargeSucceeded($chargeId),
            'charge.failed'    => $this->handleChargeFailed($chargeId),
            default            => Log::info('webhook.culqi.unhandled_event', ['type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Private event handlers
    // ─────────────────────────────────────────────────────────────

    private function handleChargeSucceeded(?string $chargeId): void
    {
        if (! $chargeId) {
            Log::warning('webhook.culqi.charge_succeeded.missing_id');
            return;
        }

        $bookings = Booking::where('payment_reference', $chargeId)->get();

        if ($bookings->isEmpty()) {
            Log::warning('webhook.culqi.charge_succeeded.no_bookings', ['charge_id' => $chargeId]);
            return;
        }

        foreach ($bookings as $booking) {
            // Idempotent: skip if already processed
            if ($booking->payment_status === 'paid') {
                Log::info('webhook.culqi.charge_succeeded.skipped_idempotent', [
                    'booking_id' => $booking->id,
                    'reference'  => $booking->reference,
                ]);
                continue;
            }

            $booking->update([
                'payment_status' => 'paid',
                'status'         => 'confirmed',
            ]);

            Log::info('webhook.culqi.charge_succeeded.updated', [
                'booking_id' => $booking->id,
                'reference'  => $booking->reference,
                'charge_id'  => $chargeId,
            ]);
        }
    }

    private function handleChargeFailed(?string $chargeId): void
    {
        if (! $chargeId) {
            Log::warning('webhook.culqi.charge_failed.missing_id');
            return;
        }

        $bookings = Booking::where('payment_reference', $chargeId)->get();

        if ($bookings->isEmpty()) {
            Log::warning('webhook.culqi.charge_failed.no_bookings', ['charge_id' => $chargeId]);
            return;
        }

        foreach ($bookings as $booking) {
            // Idempotent: skip if already in final failed state
            if ($booking->payment_status === 'failed') {
                Log::info('webhook.culqi.charge_failed.skipped_idempotent', [
                    'booking_id' => $booking->id,
                ]);
                continue;
            }

            $booking->update(['payment_status' => 'failed']);

            Log::info('webhook.culqi.charge_failed.updated', [
                'booking_id' => $booking->id,
                'reference'  => $booking->reference,
                'charge_id'  => $chargeId,
            ]);
        }
    }
}

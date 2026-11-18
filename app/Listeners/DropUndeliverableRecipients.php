<?php

namespace App\Listeners;

use Closure;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Quita de cada correo saliente los destinatarios cuyo dominio NO tiene servidor de correo (MX).
 *
 * Por qué: los bots llenan el formulario/carrito/newsletter con correos de dominios sin MX
 * (boostleadgen.com, ultraleadgeneration.com...). Exim reintenta esos envíos 4 días y cada
 * reintento cuenta como "defer"; al pasar el límite del hosting ("max defers and failures per
 * hour 5/5") Exim DESCARTA todo el correo de limaviewtours.com durante una hora, incluidas las
 * confirmaciones de reserva reales (pasó el 28-oct-2026).
 *
 * Falla abierta: si la consulta DNS da error (no "sin registros"), el correo se envía igual.
 */
class DropUndeliverableRecipients
{
    /** @var (Closure(string): (array|false))|null Sustituible en tests. */
    public static ?Closure $resolver = null;

    public function handle(MessageSending $event): ?bool
    {
        // En tests sin resolver falso no tocamos DNS real (los tests usan example.com, que tiene null MX).
        if (! config('mail.mx_guard', true) || (app()->runningUnitTests() && self::$resolver === null)) {
            return null;
        }

        $message = $event->message;
        $dropped = [];

        foreach (['To' => 'to', 'Cc' => 'cc', 'Bcc' => 'bcc'] as $getter => $setter) {
            $addresses = $message->{'get'.$getter}();
            if ($addresses === []) {
                continue;
            }
            $kept = array_values(array_filter($addresses, function (Address $a) use (&$dropped) {
                if ($this->domainAcceptsMail($a->getAddress())) {
                    return true;
                }
                $dropped[] = $a->getAddress();

                return false;
            }));
            if (count($kept) !== count($addresses)) {
                $message->{$setter}(...$kept);
            }
        }

        if ($dropped === []) {
            return null;
        }

        Log::error('mail.skipped_no_mx', [
            'dropped' => $dropped,
            'subject' => $message->getSubject(),
        ]);

        // Sin destinatarios válidos: cancelar el envío (Laravel no manda si el listener devuelve false).
        return ($message->getTo() === [] && $message->getCc() === [] && $message->getBcc() === [])
            ? false
            : null;
    }

    private function domainAcceptsMail(string $email): bool
    {
        $domain = strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));
        if ($domain === '') {
            return true; // formato raro: no decidimos nosotros
        }

        return Cache::remember('mail_mx_ok:'.$domain, now()->addDay(), function () use ($domain) {
            $records = $this->lookupMx($domain);
            if ($records === false) {
                return true; // error DNS: falla abierta
            }
            foreach ($records as $r) {
                $target = rtrim((string) ($r['target'] ?? ''), '.');
                if ($target !== '') {
                    return true; // al menos un MX real (descarta el "null MX" de RFC 7505)
                }
            }

            return false;
        });
    }

    /** @return array<int, array<string, mixed>>|false */
    private function lookupMx(string $domain): array|false
    {
        if (self::$resolver) {
            return (self::$resolver)($domain);
        }

        return @dns_get_record($domain.'.', DNS_MX);
    }
}

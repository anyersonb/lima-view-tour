<?php

namespace Tests\Feature;

use App\Listeners\DropUndeliverableRecipients;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DropUndeliverableRecipientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        Cache::flush();
        DropUndeliverableRecipients::$resolver = fn (string $d) => match ($d) {
            'boostleadgen.com' => [],                                  // sin MX
            'nullmx.test' => [['host' => 'nullmx.test', 'target' => '']], // null MX
            'dnsfail.test' => false,                                    // error DNS
            default => [['host' => $d, 'target' => 'mx.'.$d]],
        };
    }

    protected function tearDown(): void
    {
        DropUndeliverableRecipients::$resolver = null;
        parent::tearDown();
    }

    private function sent(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }

    public function test_no_envia_a_dominio_sin_mx(): void
    {
        Mail::raw('hola', fn ($m) => $m->to('hello@boostleadgen.com')->subject('Ack'));
        $this->assertCount(0, $this->sent());
    }

    public function test_no_envia_a_null_mx(): void
    {
        Mail::raw('hola', fn ($m) => $m->to('a@nullmx.test')->subject('Ack'));
        $this->assertCount(0, $this->sent());
    }

    public function test_envia_a_dominio_con_mx(): void
    {
        Mail::raw('hola', fn ($m) => $m->to('mi_tang@outlook.com')->subject('Reserva'));
        $this->assertCount(1, $this->sent());
    }

    public function test_falla_abierta_si_dns_da_error(): void
    {
        Mail::raw('hola', fn ($m) => $m->to('x@dnsfail.test')->subject('Reserva'));
        $this->assertCount(1, $this->sent());
    }

    public function test_quita_solo_el_destinatario_malo(): void
    {
        Mail::raw('hola', fn ($m) => $m->to(['ok@gmail.com', 'bot@boostleadgen.com'])->bcc('bot2@boostleadgen.com')->subject('Mix'));
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $email = $sent[0]->getOriginalMessage();
        $this->assertSame(['ok@gmail.com'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertSame([], $email->getBcc());
    }
}

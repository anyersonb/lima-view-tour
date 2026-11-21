<?php

/*
| Novo checkout (2026-11-21): pagar 100% / 50% / pagar depois, link de
| pagamento do saldo e regras de cancelamento. Pronto para o checkout e para
| a página/modal de Condições.
|
| Regra de negócio (cliente): o sinal de 50% é reembolsável se o cancelamento
| for feito com MAIS de 24 horas de antecedência da saída; com menos de 24 horas não.
*/

return [
    // Erros de servidor
    'mode_invalid' => 'Escolha quanto pagar agora: 100% ou 50% de sinal.',
    'pay_now_via_gateway' => 'O pagamento imediato deve ser feito com cartão (Culqi) ou PayPal.',
    'nothing_to_charge' => 'Não há nenhum valor a cobrar neste momento.',

    // Seção "Como prefere pagar?"
    'title' => 'Como prefere pagar?',
    'amount_label' => 'Escolha quanto pagar agora',
    'mode_full_title' => '100% agora',
    'mode_full_sub' => 'Tudo de uma vez',
    'mode_deposit_title' => '50% agora',
    'mode_deposit_sub' => 'Reserve com sinal',
    'mode_later_title' => 'Reservar e pagar depois',
    'mode_later_sub' => 'Sem cobrança agora',
    'method_label' => 'Forma de pagamento',
    'method_card' => 'Cartão',
    'method_card_sub' => 'Visa · Mastercard · Amex',
    'method_paypal' => 'Pagar com minha conta PayPal',
    'total_label' => 'Total do passeio',
    'balance_label' => 'Saldo posterior (50%)',
    'pay_now_label' => 'A pagar agora',
    'pay_now_deposit_note' => '50% de sinal',
    'pay_now_full_note' => 'Pagamento completo',
    'pay_now_later_note' => 'Nada é cobrado agora',

    // Horário (decisão 5): apenas o horário de saída do passeio, sem seletor.
    'departure_note' => 'Você receberá a confirmação do seu horário por e-mail e WhatsApp.',

    // Cancelamento (decisão 4)
    'cancellation_title' => 'Cancelamento: reembolso até 24 h antes',
    'cancellation_deposit' => 'O sinal de 50% é reembolsável se você cancelar com mais de 24 horas de antecedência da saída. Com menos de 24 horas, o sinal não é reembolsável.',
    'cancellation_full' => 'Se você cancelar com mais de 24 horas de antecedência da saída, devolvemos o valor pago. Com menos de 24 horas, o pagamento não é reembolsável.',
    'cancellation_later' => 'Nada é cobrado agora. Sua reserva fica pendente até você concluir o pagamento.',
    'balance_info' => 'O saldo é pago com um link seguro que enviaremos por e-mail. Lembramos você 3 dias antes do passeio e o link expira 48 horas antes da saída.',

    // Modal / página "Condições de reserva"
    'conditions_title' => 'Condições de reserva',
    'conditions_cancel_title' => 'Cancelamento e alterações',
    'conditions_cancel_body' => 'Você pode cancelar sua reserva e receber o reembolso do valor pago (sinal de 50% ou pagamento completo) se avisar com mais de 24 horas de antecedência da saída do passeio. Com menos de 24 horas de antecedência, ou se não comparecer ao ponto de saída, o valor pago não é reembolsável.',
    'conditions_payment_title' => 'Pagamento e saldo',
    'conditions_payment_body' => 'Você pode pagar 100% agora ou reservar com um sinal de 50%. O saldo é pago com um link de pagamento seguro enviado por e-mail; lembramos você 3 dias antes do passeio e ele expira 48 horas antes da saída. Se o saldo não for pago a tempo, entraremos em contato.',
    'conditions_schedule_title' => 'Horário',
    'conditions_schedule_body' => 'Cada passeio sai no seu horário habitual. Você receberá a confirmação do seu horário por e-mail e WhatsApp.',
    'terms_s4_deposit' => 'Para reservas com sinal de 50%: o sinal é reembolsado integralmente se o cancelamento for solicitado com mais de 24 horas de antecedência da saída; com 24 horas ou menos, o sinal não é reembolsável. O saldo é pago por meio de um link de pagamento que expira 48 horas antes da saída.',
    'refund_s2_deposit' => 'Se você reservou com sinal de 50%, devolvemos o sinal quando você cancela com mais de 24 horas de antecedência da saída. Com 24 horas ou menos, o sinal não é reembolsado.',

    // Obrigado
    'thanks_full' => 'Pagamento completo recebido. Sua reserva está confirmada.',
    'thanks_deposit' => 'Recebemos seu sinal de 50%. Sua reserva está confirmada; o saldo é pago depois com um link que enviaremos por e-mail.',
    'thanks_later' => 'Sua reserva foi registrada. Ela é confirmada quando você concluir o pagamento.',
    'thanks_balance_paid' => 'Saldo pago. Sua reserva está paga integralmente.',
];

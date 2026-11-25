<?php

/*
| Pagamento com cartão via Culqi (checkout, etapa "Pagamento").
*/

return [
    'title' => 'Pagar com cartão',
    'subtitle' => 'Visa, Mastercard, American Express e Diners. Pagamento seguro processado pela Culqi.',
    'pay_button' => 'Pagar US$:amount com cartão',
    'processing' => 'Processando seu pagamento…',
    'or_pay_with_paypal' => 'ou pague com PayPal',
    'checkout_title' => 'Lima View Tours',
    'unavailable' => 'O pagamento com cartão não está disponível no momento. Reserve e pague depois ou fale conosco pelo WhatsApp.',
    'token_missing' => 'Não foi possível ler os dados do seu cartão. Tente novamente.',
    'in_progress' => 'Já estamos processando seu pagamento. Aguarde alguns segundos.',
    'charge_failed' => 'Não foi possível concluir o pagamento com cartão. Você não foi cobrado. Tente novamente ou fale conosco pelo WhatsApp.',
    'script_failed' => 'Não foi possível carregar o formulário do cartão. Verifique sua conexão e recarregue a página.',
    'token_error' => 'Não foi possível validar seu cartão. Confira os dados e tente novamente.',
    'captured_booking_pending' => 'Seu pagamento foi processado, mas tivemos um problema ao confirmar sua reserva. Nossa equipe foi notificada e vai concluí-la manualmente; entraremos em contato em breve. Por favor, NÃO tente pagar novamente. Guarde esta referência para qualquer consulta: :reference',
    'declined' => [
        'generic' => 'Seu cartão foi recusado e você não foi cobrado. Tente outro cartão ou fale conosco pelo WhatsApp.',
        'insufficient_funds' => 'Seu cartão não tem saldo suficiente. Você não foi cobrado. Tente outro cartão ou fale conosco pelo WhatsApp.',
        'stolen_card' => 'Seu banco recusou este cartão. Você não foi cobrado. Tente outro cartão ou fale com seu banco.',
        'lost_card' => 'Seu banco recusou este cartão. Você não foi cobrado. Tente outro cartão ou fale com seu banco.',
        'contact_issuer' => 'Seu banco não autorizou o pagamento. Entre em contato com ele ou tente outro cartão. Você não foi cobrado.',
        'incorrect_cvv' => 'O código de segurança (CVV) está incorreto. Confira e tente novamente. Você não foi cobrado.',
        'expired_card' => 'Seu cartão está vencido. Tente outro cartão. Você não foi cobrado.',
        'invalid_card' => 'Os dados do cartão não são válidos. Confira ou tente outro cartão. Você não foi cobrado.',
        'fraudulent' => 'Seu banco recusou o pagamento por segurança. Tente outro cartão ou fale conosco pelo WhatsApp. Você não foi cobrado.',
        'issuer_not_available' => 'Seu banco não respondeu. Tente novamente em alguns minutos ou use outro cartão. Você não foi cobrado.',
        'issuer_decline_operation' => 'Seu banco não permite esta operação com o cartão. Tente outro ou fale conosco pelo WhatsApp. Você não foi cobrado.',
        'processing_error' => 'Ocorreu um erro ao processar o cartão. Tente novamente ou fale conosco pelo WhatsApp. Você não foi cobrado.',
    ],
];

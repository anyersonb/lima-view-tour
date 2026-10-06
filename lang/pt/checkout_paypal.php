<?php

/*
|--------------------------------------------------------------------------
| Novos textos do lote B1-B4 (PayPal no checkout normal)
|--------------------------------------------------------------------------
|
| Arquivo novo de propósito: o coordenador pediu que TODOS os textos novos
| deste lote fiquem aqui, sem tocar lang/*\/ui.php nem lang/*\/checkout.php
| (o agente de frontend edita esses em paralelo). Algumas chaves não são
| "do PayPal" estritamente (ex. pickup_detail_label, accept_terms_required
| também vale para "pagar depois"), mas ficam aqui mesmo assim por essa
| instrução.
|
*/

return [
    // B1: separador entre o botão de cartão e o botão do PayPal. Só aparece
    // quando o botão de cartão realmente é renderizado (isEligible()).
    'or_pay_with_paypal' => 'ou pague com sua conta PayPal',

    // B3: label do <input> #pickup_detail (a coluna já existia e já era
    // persistida; faltava o campo no formulário). O placeholder reaproveita
    // checkout.pickup_detail_placeholder, já presente nos 3 locales.
    'pickup_detail_label' => 'Detalhe adicional da coleta',

    // B4: mensagem de validação quando o checkbox de termos e condições não
    // chega marcado (ProcessPaymentRequest e CheckoutController::paypalCreateOrder()).
    'accept_terms_required' => 'Você deve aceitar os termos e condições para continuar.',
    'phone_invalid' => 'Informe um telefone válido, com código do país e de 7 a 15 dígitos.',

    // Alertas y errores del JS del checkout (antes en español fijo).
    'validate_datos_fields' => 'Preencha seu nome, e-mail, telefone e data da viagem antes de continuar.',
    'validate_email' => 'Informe um e-mail válido.',
    'validate_empty_cart' => 'Adicione pelo menos um passeio para continuar.',
    'validate_customer_fields' => 'Preencha todos os campos obrigatórios (nome, e-mail, telefone e data da viagem) antes de continuar.',
    'create_order_failed' => 'Não foi possível criar o pedido.',
];

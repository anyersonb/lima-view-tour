<?php

return [
    'page_title' => 'Pagar :tour',
    'page_heading' => 'Complete seu pagamento',
    'meta_description' => 'Complete seu pagamento com segurança via PayPal.',
    'generic_tour_name' => 'Tour personalizado',
    'date_to_be_arranged' => 'Data a combinar',

    'not_found_title' => 'Link não encontrado',
    'not_found' => 'Este link de pagamento não existe ou não está mais disponível. Se você acha que isso é um erro, entre em contato conosco.',

    'already_paid_title' => 'Este pagamento já foi realizado',
    'already_paid' => 'Este link já foi usado para concluir um pagamento. Se tiver dúvidas sobre sua reserva, entre em contato conosco.',

    'cancelled_title' => 'Link cancelado',
    'cancelled' => 'Este link de pagamento foi cancelado. Se precisar de um novo, entre em contato conosco.',

    'expired_title' => 'Link expirado',
    'expired' => 'Este link de pagamento expirou. Se precisar de um novo, entre em contato conosco.',

    'not_available_title' => 'Link não disponível',
    'not_available' => 'Este link de pagamento não está mais disponível.',

    'processing_wait' => 'Seu pagamento está sendo processado. Aguarde alguns segundos e não feche esta página.',

    'captured_booking_pending' => 'Seu pagamento foi processado com sucesso, mas tivemos um problema ao confirmar sua reserva. '
        .'Nossa equipe já foi notificada e irá concluí-la manualmente; entraremos em contato em breve. '
        .'Por favor, NÃO tente pagar novamente. Guarde esta referência para qualquer consulta: :reference',

    // i18n fix 2026-10-08
    'adults_count' => '{1} :count Adulto|[2,*] :count Adultos',
    'children_count' => '{1} :count Criança|[2,*] :count Crianças',
    'secure_payment' => 'Pagamento 100% seguro com PayPal',
    'phone_label' => 'Telefone',
    'js_create_failed' => 'Não foi possível iniciar o pagamento.',
    'js_capture_failed' => 'Não conseguimos concluir o pagamento.',
    'js_paypal_error' => 'Ocorreu um erro com o PayPal. Por favor, recarregue a página e tente novamente.',
];

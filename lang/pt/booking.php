<?php

return [
    'date_blocked' => 'Essa data não está disponível para reserva. Escolha outra.',
    // Per-item message at cart/checkout: names the tour and its first bookable date.
    'date_item_unavailable' => 'A data de «:tour» não está mais disponível. A primeira data disponível é :date. Para o dia seguinte, a reserva vai até às :cutoff (horário de Lima).',
    'date_outdated' => 'A data de um dos seus tours não está mais disponível (mudou enquanto o carrinho estava aberto). Revise as datas e tente novamente.',
    'card_declined' => 'Seu banco recusou o cartão. Por favor, tente outro cartão ou use o saldo da sua conta PayPal.',
    'payment_failed' => 'Não foi possível concluir o pagamento. Por favor, tente novamente ou entre em contato conosco.',

    // Horários e reserva no mesmo dia (2026-11-24)
    'slot_required' => 'Escolha o horário de saída.',
    'slot_invalid' => 'Esse horário não está disponível para este passeio. Escolha outro.',
    'slot_too_soon' => 'Esse horário não pode mais ser reservado para hoje: a reserva exige pelo menos :hours horas de antecedência. Escolha outro horário ou uma data posterior.',
    'slot_item_unavailable' => 'O horário de ":tour" não está mais disponível nessa data. Altere-o ou escolha outra data.',
    'same_day_notice' => 'Reserva para hoje disponível: com pelo menos :hours horas de antecedência do horário escolhido.',
    'same_day_mail_prefix' => '[MESMO DIA]',
    'same_day_mail_banner' => 'RESERVA PARA O MESMO DIA — saída em :date às :time. Confirmar hoje.',
    'slot_label' => 'Horário de saída',
];

<?php

return [
    'added' => 'Tour adicionado ao carrinho.',
    'updated' => 'Carrinho atualizado com sucesso.',
    'removed' => 'Tour removido do carrinho.',
    'cleared' => 'Carrinho esvaziado.',
    'empty' => 'Seu carrinho está vazio.',
    'coupon_applied' => 'Cupom aplicado com sucesso!',
    'coupon_invalid' => 'O cupom informado não é válido.',
    'coupon_expired' => 'Este cupom expirou.',
    'max_quantity' => 'A quantidade máxima permitida é 20.',
    'error' => 'Ocorreu um erro. Por favor, tente novamente.',
    'row_not_found' => 'Esse tour não está mais no seu carrinho. Atualize a página e tente novamente.',
    'cart_full' => 'Seu carrinho já tem o máximo de :max tours diferentes. Remova algum para adicionar outro.',
    'merge_overflow' => 'Essa data já tem uma reserva do mesmo tour, e juntá-las ultrapassaria o máximo de :max pessoas. Reduza a quantidade ou escolha outra data.',
    'row_pax_exceeded' => 'O máximo de pessoas por reserva é :max. Reduza o número de adultos ou crianças.',

    'validation' => [
        'tour_required' => 'Você deve selecionar um tour.',
        'tour_not_found' => 'O tour selecionado não existe.',
        'adults_required' => 'Informe o número de adultos.',
        'adults_integer' => 'O número de adultos deve ser um número inteiro.',
        'adults_min' => 'Deve haver pelo menos 1 adulto.',
        'children_required' => 'Informe o número de crianças.',
        'children_integer' => 'O número de crianças deve ser um número inteiro.',
        'children_min' => 'O número de crianças não pode ser negativo.',
        'date_required' => 'Selecione a data do tour.',
        'date_invalid' => 'A data não tem um formato válido.',
        'date_future' => 'A primeira data disponível é :date.',
        'date_too_far' => 'A última data disponível para reserva é :date.',
        'max_quantity' => 'A quantidade máxima permitida é 20.',
    ],

    'recover_success' => 'Recuperamos seu carrinho. Você pode continuar com sua reserva.',
    'recover_expired' => 'O link de recuperação não é mais válido ou o carrinho está vazio.',
];

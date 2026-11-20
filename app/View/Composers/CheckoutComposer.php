<?php

namespace App\View\Composers;

use App\Support\CheckoutInfo;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Expone a resources/views/checkout.blade.php la variable $checkoutInfo
 * (ver App\Support\CheckoutInfo y contrato-datos.md, sección 8).
 */
class CheckoutComposer
{
    public function compose(View $view): void
    {
        try {
            $items = $view->getData()['items'] ?? collect();
            $info = CheckoutInfo::build($items instanceof \Illuminate\Support\Collection ? $items : collect($items));
        } catch (\Throwable $e) {
            Log::error('CheckoutComposer: falló', ['error' => $e->getMessage()]);
            $info = null;
        }

        $view->with('checkoutInfo', $info);
    }
}

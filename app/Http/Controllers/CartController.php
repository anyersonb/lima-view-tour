<?php

namespace App\Http\Controllers;

use App\Exceptions\CartItemNotFoundException;
use App\Exceptions\CartLimitExceededException;
use App\Http\Requests\CartItemRequest;
use App\Models\AbandonedCart;
use App\Models\BlockedDate;
use App\Models\Tour;
use App\Services\AbandonedCartService;
use App\Services\CartService;
use App\Services\PaymentLockService;
use App\Support\BookingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly AbandonedCartService $abandoned,
        private readonly PaymentLockService $paymentLock,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  index — render cart view
    // ─────────────────────────────────────────────────────────────

    public function index(string $locale): View
    {
        try {
            $items = $this->cart->items();
            $subtotal = $this->cart->subtotal();
            $discount = $this->cart->couponDiscount();
            $total = $this->cart->total();
            $couponCode = $this->cart->couponCode();

            // Enriquecer items con datos de descuento/badge del tour (sin tocar BD)
            if ($items->isNotEmpty()) {
                $tourMeta = Tour::whereIn('id', $items->pluck('tour_id')->filter()->all())
                    ->get(['id', 'price_before', 'is_featured'])
                    ->keyBy('id');
                $items = $items->map(function (array $it) use ($tourMeta) {
                    $t = $tourMeta->get($it['tour_id'] ?? null);
                    $it['price_before'] = $t?->price_before;
                    $it['is_featured'] = (bool) ($t?->is_featured ?? false);

                    return $it;
                });
            }

            // Related tours — fallback to empty collection on error
            $related = collect();
            try {
                $related = Tour::published()
                    ->inRandomOrder()
                    ->limit(3)
                    ->get();
            } catch (\Throwable $e) {
                Log::error('CartController@index: failed to load related tours', [
                    'error' => $e->getMessage(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('CartController@index: unexpected error', [
                'error' => $e->getMessage(),
            ]);
            $items = collect();
            $subtotal = 0.0;
            $discount = 0.0;
            $total = 0.0;
            $couponCode = null;
            $related = collect();
        }

        $public_key = config('services.culqi.public_key');
        $total_centavos = (int) round($total * 100);
        // La fecha de cada tour es la que manda y la que se graba en su reserva
        // (CheckoutController::finalizeBookings). Este valor es el que viaja en
        // el campo `travel_date` del formulario, que el checkout sigue exigiendo
        // y que alimenta los correos: la MÁS TEMPRANA de las del carrito, que es
        // la primera que ocurre. Antes era la del primer tour AÑADIDO, que no es
        // lo mismo si el cliente agregó primero el tour más lejano.
        $itemDates = $items->pluck('travel_date')->filter()->sort()->values();
        $firstTravelDate = $itemDates->first() ?? BookingCalendar::earliestDate();
        $datesDiffer = $itemDates->unique()->count() > 1;

        // Hallazgo de seguridad [ALTO] (2026-08-27, "puerta 3"): un pago
        // capturado por PayPal cuya reserva no se creó dejaba el panel de
        // aviso viviendo SOLO en JS (showPaymentReviewPanel(), disparado por
        // el fetch que falló). Un F5 volvía a pintar el formulario de pago
        // entero como si nada — el marcador seguía vivo en el servidor, pero
        // la pantalla no lo reflejaba. Se resuelve leyendo el marcador acá y
        // pasándolo a la vista: el bootstrap en checkout.blade.php llama al
        // MISMO showPaymentReviewPanel() ya existente, ahora también desde
        // una carga de página normal, no solo desde un fetch fallido.
        $paymentLock = null;
        if ($lock = $this->paymentLock->current()) {
            $paymentLock = [
                'message' => $this->paymentLock->message($lock),
                'reference' => $lock['capture_id'],
            ];
        }

        return view('checkout', compact(
            'items',
            'subtotal',
            'discount',
            'total',
            'couponCode',
            'related',
            'public_key',
            'total_centavos',
            'firstTravelDate',
            'datesDiffer',
            'paymentLock',
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  store — add item to cart
    // ─────────────────────────────────────────────────────────────

    public function store(CartItemRequest $request, string $locale): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $tour = Tour::findOrFail($request->validated('tour_id'));

            // Server-side check: reject blocked dates before adding to cart
            $travelDate = $request->validated('travel_date');
            if (BlockedDate::isBlocked($travelDate, $tour->id)) {
                Log::info('CartController@store: blocked date rejected', [
                    'tour_id' => $tour->id,
                    'travel_date' => $travelDate,
                ]);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => __('booking.date_blocked'),
                    ], 422);
                }

                return redirect()->back()->withErrors(['travel_date' => __('booking.date_blocked')]);
            }

            $this->cart->add(
                $tour,
                (int) $request->validated('adults'),
                (int) $request->validated('children'),
                $request->validated('travel_date'),
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('cart.added'),
                    'count' => $this->cart->count(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.added'));

        } catch (CartLimitExceededException $e) {
            Log::info('CartController@store: cart row limit reached', [
                'tour_id' => $request->input('tour_id'),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['general' => $e->getMessage()]);

        } catch (\Throwable $e) {
            Log::error('CartController@store: failed to add item', [
                'tour_id' => $request->input('tour_id'),
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.error')], 500);
            }

            return redirect()->back()->withErrors(['general' => __('cart.error')]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  updateItem — change adults/children for a row
    // ─────────────────────────────────────────────────────────────

    public function updateItem(Request $request, string $locale, string $rowId): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'adults' => ['required', 'integer', 'min:1', 'max:20'],
                'children' => ['required', 'integer', 'min:0', 'max:20'],
                // Opcional: solo viaja cuando el cliente cambia la fecha desde
                // el carrito. Misma regla que al añadir y que en el checkout —
                // antes el carrito aceptaba `after_or_equal:today` y el pago
                // exigía `after:today`, así que se podía meter al carrito una
                // fecha que el pago iba a rechazar al final.
                'travel_date' => array_merge(['sometimes', 'required'], BookingCalendar::dateRules()),
            ], BookingCalendar::dateMessages());

            // Misma comprobación de fechas bloqueadas que al añadir al carrito:
            // si no, se puede esquivar el bloqueo editando la fecha acá.
            if (isset($validated['travel_date'])) {
                $tourId = $this->cart->items()->firstWhere('row_id', $rowId)['tour_id'] ?? null;

                if (BlockedDate::isBlocked($validated['travel_date'], $tourId)) {
                    Log::info('CartController@updateItem: blocked date rejected', [
                        'row_id' => $rowId,
                        'tour_id' => $tourId,
                        'travel_date' => $validated['travel_date'],
                    ]);

                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => __('booking.date_blocked'),
                        ], 422);
                    }

                    return redirect()->route('cart.index', ['locale' => $locale])
                        ->with('error', __('booking.date_blocked'));
                }
            }

            // El rowId cambia si cambió la fecha: hay que devolver el nuevo.
            $newRowId = $this->cart->update($rowId, $validated);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('cart.updated'),
                    'row_id' => $newRowId,
                    'subtotal' => $this->cart->subtotal(),
                    'total' => $this->cart->total(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.updated'));

        } catch (ValidationException $e) {
            // El catch de \Throwable de abajo se tragaba también esto y devolvía
            // un 500 con "ocurrió un error", así que una fecha inválida se veía
            // como una caída del sitio en vez de decir qué pasaba. Se deja
            // pasar para que Laravel emita el 422 con el mensaje del campo.
            throw $e;
        } catch (CartItemNotFoundException $e) {
            // La fila no existe: antes se devolvía 200 `{"success":true}`
            // igual, así que un fallo real (sesión distinta, fila ya
            // borrada) pasaba por éxito. Ver CartService::update().
            Log::info('CartController@updateItem: row not found', ['row_id' => $rowId]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.row_not_found')], 404);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('error', __('cart.row_not_found'));

        } catch (CartLimitExceededException $e) {
            Log::info('CartController@updateItem: cart limit reached', ['row_id' => $rowId]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('error', $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('CartController@updateItem: failed to update row', [
                'row_id' => $rowId,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.error')], 500);
            }

            return redirect()->back()->withErrors(['general' => __('cart.error')]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  destroy — remove a single item
    // ─────────────────────────────────────────────────────────────

    public function destroy(Request $request, string $locale, string $rowId): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $this->cart->remove($rowId);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('cart.removed'),
                    'count' => $this->cart->count(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.removed'));

        } catch (\Throwable $e) {
            Log::error('CartController@destroy: failed to remove row', [
                'row_id' => $rowId,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.error')], 500);
            }

            return redirect()->back()->withErrors(['general' => __('cart.error')]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  applyCoupon
    // ─────────────────────────────────────────────────────────────

    public function applyCoupon(Request $request, string $locale): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $request->validate(['code' => ['required', 'string', 'max:50']]);

            $result = $this->cart->applyCoupon($request->input('code'));

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            $flash = $result['success'] ? 'success' : 'error';

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with($flash, $result['message']);

        } catch (\Throwable $e) {
            Log::error('CartController@applyCoupon: unexpected error', [
                'code' => $request->input('code'),
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.error')], 500);
            }

            return redirect()->back()->withErrors(['general' => __('cart.error')]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  saveContact — persist cart + contact for abandoned-cart recovery
    //  (llamado por AJAX cuando el visitante escribe su email en el checkout)
    // ─────────────────────────────────────────────────────────────

    public function saveContact(Request $request, string $locale): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_email' => ['nullable', 'email', 'max:255'],
                'customer_name' => ['nullable', 'string', 'max:255'],
                'customer_phone' => ['nullable', 'string', 'max:40'],
            ]);

            $this->abandoned->capture([
                'email' => $validated['customer_email'] ?? null,
                'name' => $validated['customer_name'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            // Nunca romper la UX del checkout por esto
            Log::warning('CartController@saveContact: capture failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false], 200);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  recover — restore an abandoned cart from its recovery link
    // ─────────────────────────────────────────────────────────────

    public function recover(string $locale, string $token): RedirectResponse
    {
        $cart = AbandonedCart::where('token', $token)->first();

        if (! $cart || empty($cart->items)) {
            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('error', __('cart.recover_expired'));
        }

        $this->abandoned->restore($cart);

        Log::info('CartController@recover: cart restored', [
            'token' => $token,
            'locale' => $locale,
        ]);

        return redirect()->route('cart.index', ['locale' => $locale])
            ->with('success', __('cart.recover_success'));
    }

    // ─────────────────────────────────────────────────────────────
    //  clear — destroy entire cart
    // ─────────────────────────────────────────────────────────────

    public function clear(Request $request, string $locale): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $this->cart->clear();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => __('cart.cleared')]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.cleared'));

        } catch (\Throwable $e) {
            Log::error('CartController@clear: unexpected error', ['error' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('cart.error')], 500);
            }

            return redirect()->back()->withErrors(['general' => __('cart.error')]);
        }
    }
}

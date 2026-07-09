<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartItemRequest;
use App\Models\AbandonedCart;
use App\Models\BlockedDate;
use App\Models\Tour;
use App\Services\AbandonedCartService;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService          $cart,
        private readonly AbandonedCartService $abandoned,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  index — render cart view
    // ─────────────────────────────────────────────────────────────

    public function index(string $locale): View
    {
        try {
            $items    = $this->cart->items();
            $subtotal = $this->cart->subtotal();
            $discount = $this->cart->couponDiscount();
            $total    = $this->cart->total();
            $couponCode = $this->cart->couponCode();

            // Enriquecer items con datos de descuento/badge del tour (sin tocar BD)
            if ($items->isNotEmpty()) {
                $tourMeta = Tour::whereIn('id', $items->pluck('tour_id')->filter()->all())
                    ->get(['id', 'price_before', 'is_featured'])
                    ->keyBy('id');
                $items = $items->map(function (array $it) use ($tourMeta) {
                    $t = $tourMeta->get($it['tour_id'] ?? null);
                    $it['price_before'] = $t?->price_before;
                    $it['is_featured']  = (bool) ($t?->is_featured ?? false);
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
            $items      = collect();
            $subtotal   = 0.0;
            $discount   = 0.0;
            $total      = 0.0;
            $couponCode = null;
            $related    = collect();
        }

        $public_key      = config('services.culqi.public_key');
        $total_centavos  = (int) round($total * 100);
        $firstTravelDate = optional($items->first())['travel_date'] ?? now()->addDays(7)->toDateString();

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
                    'tour_id'     => $tour->id,
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
                    'count'   => $this->cart->count(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.added'));

        } catch (\Throwable $e) {
            Log::error('CartController@store: failed to add item', [
                'tour_id' => $request->input('tour_id'),
                'error'   => $e->getMessage(),
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
                'adults'   => ['required', 'integer', 'min:1', 'max:20'],
                'children' => ['required', 'integer', 'min:0', 'max:20'],
            ]);

            $this->cart->update($rowId, $validated);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => __('cart.updated'),
                    'subtotal' => $this->cart->subtotal(),
                    'total'    => $this->cart->total(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.updated'));

        } catch (\Throwable $e) {
            Log::error('CartController@updateItem: failed to update row', [
                'row_id' => $rowId,
                'error'  => $e->getMessage(),
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
                    'count'   => $this->cart->count(),
                ]);
            }

            return redirect()->route('cart.index', ['locale' => $locale])
                ->with('success', __('cart.removed'));

        } catch (\Throwable $e) {
            Log::error('CartController@destroy: failed to remove row', [
                'row_id' => $rowId,
                'error'  => $e->getMessage(),
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
                'code'  => $request->input('code'),
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
                'customer_name'  => ['nullable', 'string', 'max:255'],
                'customer_phone' => ['nullable', 'string', 'max:40'],
            ]);

            $this->abandoned->capture([
                'email' => $validated['customer_email'] ?? null,
                'name'  => $validated['customer_name']  ?? null,
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
            'token'  => $token,
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

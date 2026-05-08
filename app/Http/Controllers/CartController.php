<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartItemRequest;
use App\Models\Tour;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

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

        return view('checkout', compact(
            'items',
            'subtotal',
            'discount',
            'total',
            'couponCode',
            'related',
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  store — add item to cart
    // ─────────────────────────────────────────────────────────────

    public function store(CartItemRequest $request, string $locale): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $tour = Tour::findOrFail($request->validated('tour_id'));

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

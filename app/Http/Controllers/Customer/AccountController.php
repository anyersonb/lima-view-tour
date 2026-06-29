<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $customer = auth('customer')->user();

        $bookings = Booking::where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('customer_email', $customer->email);
            })
            ->with('tour:id,title_es,title_en,title_pt,slug')
            ->latest()
            ->paginate(10);

        return view('customer.account', compact('customer', 'bookings'));
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $locale   = app()->getLocale();
        $customer = auth('customer')->user();
        $data     = $request->validated();

        // Verify current password if changing password
        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'] ?? '', $customer->password)) {
                return back()->withErrors(['current_password' => __('customer.wrong_current_password')]);
            }
        }

        $updateData = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $customer->update($updateData);

        return redirect()
            ->route('customer.account', ['locale' => $locale])
            ->with('success', __('customer.profile_updated'));
    }
}

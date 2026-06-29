<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showForm(): View
    {
        return view('customer.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $locale = app()->getLocale();

        $customer = Customer::create([
            'name'     => $request->validated('name'),
            'email'    => $request->validated('email'),
            'phone'    => $request->validated('phone'),
            'password' => $request->validated('password'),
            'locale'   => $locale,
        ]);

        Auth::guard('customer')->login($customer);

        return redirect()
            ->route('customer.account', ['locale' => $locale])
            ->with('success', __('customer.registered_successfully'));
    }
}

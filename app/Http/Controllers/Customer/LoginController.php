<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showForm(): View
    {
        return view('customer.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $locale = app()->getLocale();

        $credentials = $request->only('email', 'password');

        if (Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()
                ->intended(route('customer.account', ['locale' => $locale]))
                ->with('success', __('customer.logged_in'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('customer.invalid_credentials')]);
    }
}

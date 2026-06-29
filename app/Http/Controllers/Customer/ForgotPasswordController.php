<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showForm(): View
    {
        return view('customer.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always send the same neutral response to avoid email enumeration
        Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        return back()->with(
            'status',
            __('customer.reset_link_sent')
        );
    }
}

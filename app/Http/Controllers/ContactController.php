<?php

namespace App\Http\Controllers;

use App\Models\ContactLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact');
    }

    public function submit(string $locale, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['nullable', 'string', 'max:120'],
            'celular' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:200'],
            'mensaje' => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        ContactLead::create([
            'name' => $data['nombre'],
            'lastname' => $data['apellido'] ?? null,
            'phone' => $data['celular'] ?? null,
            'email' => $data['email'],
            'message' => $data['mensaje'],
            'source' => 'contact_form',
            'locale' => $locale,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('contact.thanks', ['locale' => $locale]);
    }
}

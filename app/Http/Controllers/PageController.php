<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PageController extends Controller
{
    public function terms(): View
    {
        try {
            $locale = app()->getLocale();
            $title = __('legal.terms_title');

            return view('pages.terms', compact('locale', 'title'));
        } catch (\Throwable $e) {
            Log::error('PageController@terms: failed to render terms page', [
                'locale' => app()->getLocale(),
                'exception' => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    public function privacy(): View
    {
        try {
            $locale = app()->getLocale();
            $title = __('legal.privacy_title');

            return view('pages.privacy', compact('locale', 'title'));
        } catch (\Throwable $e) {
            Log::error('PageController@privacy: failed to render privacy page', [
                'locale' => app()->getLocale(),
                'exception' => $e->getMessage(),
            ]);

            abort(500);
        }
    }
}

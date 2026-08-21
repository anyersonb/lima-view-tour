<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Content Security Policy directives.
     *
     * Allows Culqi v4 checkout, Google Fonts, Google Analytics / GTM,
     * Meta Pixel (Facebook), and Cloudflare CDN assets already used in
     * the app. Update this list if new third-party scripts are added.
     */
    private const CSP_BASE = [
        'default-src' => "'self'",
        'script-src'  => "'self' 'unsafe-inline' 'unsafe-eval' "
            ."https://checkout.culqi.com https://js.culqi.com "
            ."https://www.paypal.com https://*.paypal.com https://*.paypalobjects.com "
            ."https://cdnjs.cloudflare.com "
            ."https://www.googletagmanager.com https://www.google-analytics.com "
            ."https://connect.facebook.net "
            ."https://maps.googleapis.com "
            ."https://www.jscache.com https://*.tacdn.com "
            ."https://www.tripadvisor.com https://*.tripadvisor.com "
            ."https://www.google.com https://www.gstatic.com",
        'style-src'   => "'self' 'unsafe-inline' "
            ."https://fonts.googleapis.com https://fonts.bunny.net https://cdnjs.cloudflare.com "
            ."https://*.tacdn.com https://www.tripadvisor.com",
        'font-src'    => "'self' https://fonts.gstatic.com https://fonts.bunny.net https://*.tacdn.com data:",
        'img-src'     => "'self' data: https: blob:",
        'frame-src'   => "https://*.culqi.com https://www.googletagmanager.com "
            ."https://www.paypal.com https://*.paypal.com "
            ."https://www.tripadvisor.com https://*.tripadvisor.com "
            ."https://www.google.com",
        'connect-src' => "'self' https://api.culqi.com "
            ."https://www.paypal.com https://*.paypal.com https://*.paypalobjects.com "
            ."https://www.google-analytics.com "
            ."https://maps.googleapis.com https://maps.gstatic.com "
            ."https://www.google.com https://www.gstatic.com "
            ."https://www.tripadvisor.com https://*.tripadvisor.com https://*.tacdn.com",
        // FilePond (subida de imágenes en el panel Filament) crea un Web Worker
        // desde un blob: para procesar/redimensionar imágenes. Sin worker-src
        // blob: la CSP lo bloquea y la subida falla silenciosamente (queda null).
        'worker-src'  => "'self' blob:",
        'object-src'  => "'none'",
        'base-uri'    => "'self'",
        'form-action' => "'self' https://checkout.culqi.com https://www.paypal.com https://*.paypal.com",
    ];

    /**
     * Build CSP string. In non-production env, allow Vite dev server origins
     * so HMR / @vite assets load without being blocked.
     */
    private function buildCsp(): string
    {
        $csp = self::CSP_BASE;

        if (! app()->environment('production')) {
            $viteHttp = 'http://localhost:5173 http://127.0.0.1:5173';
            $viteWs   = 'ws://localhost:5173 ws://127.0.0.1:5173';
            $csp['script-src']  .= ' '.$viteHttp;
            $csp['style-src']   .= ' '.$viteHttp;
            $csp['connect-src'] .= ' '.$viteHttp.' '.$viteWs;
        }

        $parts = [];
        foreach ($csp as $directive => $value) {
            $parts[] = "{$directive} {$value}";
        }

        return implode('; ', $parts).';';
    }

    /**
     * Handle an incoming request and inject security headers in the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        // HSTS only when explicitly forced (production) or the connection is already secure
        if (config('app.force_https', false) || $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}

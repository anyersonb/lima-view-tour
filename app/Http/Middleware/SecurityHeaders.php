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
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval' "
            ."https://checkout.culqi.com https://js.culqi.com "
            ."https://cdnjs.cloudflare.com "
            ."https://www.googletagmanager.com https://www.google-analytics.com "
            ."https://connect.facebook.net; "
        ."style-src 'self' 'unsafe-inline' "
            ."https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        ."font-src 'self' https://fonts.gstatic.com data:; "
        ."img-src 'self' data: https: blob:; "
        ."frame-src https://*.culqi.com https://www.googletagmanager.com; "
        ."connect-src 'self' https://api.culqi.com "
            ."https://www.google-analytics.com; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self' https://checkout.culqi.com;";

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
        $response->headers->set('Content-Security-Policy', self::CSP);

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

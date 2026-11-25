<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;
        // Nunca indexar: es una sorpresa privada.
        $h->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        // No enviar la URL (que puede contener tokens) a otros sitios.
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $h->has('Content-Security-Policy')) {
            $dev = app()->environment('local') ? ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173' : '';
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'".$dev,
                "style-src 'self' 'unsafe-inline'".$dev,
                "img-src 'self' data: blob:",
                "media-src 'self' blob:",
                "font-src 'self' data:".$dev,
                "connect-src 'self'".$dev,
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        if (str_starts_with($request->path(), 'api/')) {
            $h->set('Cache-Control', $h->get('Cache-Control') ?: 'no-store');
        }

        return $response;
    }
}

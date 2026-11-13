<?php

namespace Webkul\Churn\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class ChurnCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return app(VerifyCsrfToken::class)->handle($request, $next);
        } catch (TokenMismatchException $error) {
            // Preserve rejection status despite the native non-debug HTML exception renderer.
            return $request->expectsJson() ? response()->json(['message' => 'Session token mismatch'], 419) : response('Session token mismatch', 419);
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce for local provider — Pinat SSO users are pre-verified
        if ($user && $user->isLocal() && ! $user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified.',
                'code'    => 'EMAIL_UNVERIFIED',
            ], 403);
        }

        return $next($request);
    }
}

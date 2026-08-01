<?php

namespace App\Http\Middleware;

use App\Services\ProfileService;
use App\Services\PinatJwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePinatUser
{
    public function __construct(
        protected PinatJwtService $jwt,
        protected ProfileService $profiles,
    ) {}
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $token = $this->extractBearerToken($request);
        $payload = $this->jwt->verify($token);
        $profile = $this->profiles->syncFromJwt($payload);
        $request->setUserResolver(fn() => $profile);
        $request->attributes->set('profile', $profile);

        return $next($request);
    }
    private function extractBearerToken(Request $request): string
    {
        $token = $request->bearerToken();

        if (!$token) {
            abort(401, 'Missing bearer token');
        }

        return $token;
    }
}

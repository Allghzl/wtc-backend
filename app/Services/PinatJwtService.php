<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PinatJwtService
{
    public function getBearerToken(Request $request): string
    {
        $token = $request->bearerToken();

        if (! $token) {
            throw new Exception('Bearer token missing.');
        }

        return $token;
    }

    public function verify(string $token): object
    {
        $jwks = Cache::remember(
            'pinatauth.jwks',
            now()->addMinutes(10),
            function () {

                $response = Http::get(
                    rtrim(config('pinat-auth.url'), '/')
                        . '/.well-known/jwks.json'
                );

                if (! $response->successful()) {
                    throw new Exception('Unable to fetch JWKS.');
                }

                return $response->json();
            }
        );

        $keys = JWK::parseKeySet($jwks);

        return JWT::decode($token, $keys);
    }

    public function payload(string $token): array
    {
        $parts = explode('.', $token);

        return json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );
    }

    public function getHeader(string $token): array
    {
        $parts = explode('.', $token);

        return json_decode(
            base64_decode(strtr($parts[0], '-_', '+/')),
            true
        );
    }

    public function getKid(string $token): string
    {
        return $this->getHeader($token)['kid'];
    }
}

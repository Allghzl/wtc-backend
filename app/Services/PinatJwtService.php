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
        try {
            $jwks = Cache::remember(
                'pinatauth.jwks',
                now()->addMinutes(10),
                function () {
                    $url = rtrim(config('pinat-auth.url'), '/')
                        . '/.well-known/jwks.json';

                    $response = Http::get($url);

                    if (! $response->successful()) {
                        throw new Exception(
                            "Unable to fetch JWKS. HTTP {$response->status()}"
                        );
                    }

                    return $response->json();
                }
            );

            logger()->info('PINATAUTH JWT DEBUG', [
                'token_kid' => $this->getKid($token),
                'jwks_kids' => collect($jwks['keys'] ?? [])
                    ->pluck('kid')
                    ->values()
                    ->all(),
                'issuer' => $this->payload($token)['iss'] ?? null,
                'exp' => $this->payload($token)['exp'] ?? null,
                'now' => time(),
            ]);

            $keys = JWK::parseKeySet($jwks);

            return JWT::decode($token, $keys);
        } catch (\Throwable $e) {

            logger()->error('PINATAUTH JWT VERIFY FAILED', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
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

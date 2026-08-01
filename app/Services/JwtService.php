<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret = config('app.key');
    }

    /**
     * Verify and decode JWT token
     *
     * @param string $token
     * @return object
     * @throws Exception
     */
    public function verify(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new Exception('Token has expired', 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new Exception('Invalid token signature', 401);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            throw new Exception('Token not yet valid', 401);
        } catch (Exception $e) {
            throw new Exception('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Generate JWT token
     *
     * @param array $payload
     * @param int|null $expiresIn Expiration time in seconds
     * @return string
     */
    public function generate(array $payload, ?int $expiresIn = null): string
    {
        $issuedAt = time();
        $data = [
            'iat' => $issuedAt,
            'data' => $payload,
        ];

        if ($expiresIn !== null) {
            $data['exp'] = $issuedAt + $expiresIn;
        }

        return JWT::encode($data, $this->secret, $this->algorithm);
    }
}

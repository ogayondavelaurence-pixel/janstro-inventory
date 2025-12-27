<?php

namespace Janstro\InventorySystem\Utils;

class JWT
{
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'janstro_secret_key_change_in_production';
    }

    private static function getAlgorithm(): string
    {
        return $_ENV['JWT_ALGORITHM'] ?? 'HS256';
    }

    private static function getExpiration(): int
    {
        return (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
    }

    public static function generate(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::getExpiration();

        $token = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => $payload
        ];

        return \SimpleJWT::encode($token, self::getSecret(), self::getAlgorithm());
    }

    public static function validate(string $token): ?object
    {
        try {
            if (empty($token)) return null;

            $decoded = \SimpleJWT::decode($token, self::getSecret());

            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            if (!isset($decoded->data) || !isset($decoded->data->user_id)) {
                return null;
            }

            return $decoded->data;
        } catch (\Exception $e) {
            error_log("JWT Validation Error: " . $e->getMessage());
            return null;
        }
    }

    public static function getFromHeader(): ?string
    {
        $authHeader = null;

        // Method 1: getallheaders() - case-insensitive
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }

        // Method 2: $_SERVER fallback
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ??
                $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        if (!$authHeader) {
            return null;
        }

        // Extract token from "Bearer <token>"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

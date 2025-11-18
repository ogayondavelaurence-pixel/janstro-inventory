<?php

namespace Janstro\InventorySystem\Utils;

/**
 * JWT Utility - Works with Manual Autoloader
 */
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

        // Use SimpleJWT from autoloader
        return \SimpleJWT::encode($token, self::getSecret(), self::getAlgorithm());
    }

    public static function validate(string $token): ?object
    {
        try {
            // Use SimpleJWT from autoloader
            $decoded = \SimpleJWT::decode($token, self::getSecret());
            return $decoded->data;
        } catch (\Exception $e) {
            error_log("JWT Validation Error: " . $e->getMessage());
            return null;
        }
    }

    public static function getFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

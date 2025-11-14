<?php

namespace Janstro\InventorySystem\Utils;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;

class JWT
{
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'janstro_secret_key';
    }

    private static function getAlgorithm(): string
    {
        return $_ENV['JWT_ALGORITHM'] ?? 'HS256';
    }

    private static function getExpiration(): int
    {
        return (int)($_ENV['JWT_EXPIRATION'] ?? 3600); // default 1 hour
    }

    /**
     * Generate JWT token
     */
    public static function generate(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::getExpiration();

        $token = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => $payload
        ];

        return FirebaseJWT::encode($token, self::getSecret(), self::getAlgorithm());
    }

    /**
     * Validate JWT token and return user data
     */
    public static function validate(string $token): ?object
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::getSecret(), self::getAlgorithm()));
            return $decoded->data;
        } catch (ExpiredException $e) {
            error_log("JWT Token Expired: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("JWT Validation Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Bearer token from HTTP Authorization header
     */
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

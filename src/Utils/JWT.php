<?php

namespace Janstro\InventorySystem\Utils;

/**
 * JWT Utility - Enhanced Token Validation v2.0
 * CRITICAL FIX: Better error logging and validation
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

        $jwt = \SimpleJWT::encode($token, self::getSecret(), self::getAlgorithm());

        error_log("✅ JWT Generated: " . substr($jwt, 0, 30) . "... (expires: " . date('Y-m-d H:i:s', $expire) . ")");

        return $jwt;
    }

    /**
     * Validate JWT token - ENHANCED
     */
    public static function validate(string $token): ?object
    {
        try {
            if (empty($token)) {
                error_log("❌ JWT: Empty token provided");
                return null;
            }

            error_log("🔍 JWT: Validating token: " . substr($token, 0, 30) . "...");

            // Validate token format (must have 3 parts)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                error_log("❌ JWT: Invalid token format (expected 3 parts, got " . count($parts) . ")");
                return null;
            }

            // Each part must be base64url-safe
            foreach ($parts as $i => $part) {
                if (empty($part) || !preg_match('/^[A-Za-z0-9_-]+$/', $part)) {
                    error_log("❌ JWT: Invalid token encoding in part " . ($i + 1));
                    return null;
                }
            }

            // Decode token
            $decoded = \SimpleJWT::decode($token, self::getSecret());

            // Validate expiration
            if (isset($decoded->exp) && $decoded->exp < time()) {
                error_log("❌ JWT: Token expired at " . date('Y-m-d H:i:s', $decoded->exp));
                return null;
            }

            // Validate required fields
            if (!isset($decoded->data) || !isset($decoded->data->user_id)) {
                error_log("❌ JWT: Missing required data in token");
                return null;
            }

            error_log("✅ JWT: Token valid for user_id: " . $decoded->data->user_id);

            return $decoded->data;
        } catch (\Exception $e) {
            error_log("❌ JWT Validation Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Extract token from Authorization header - ENHANCED
     */
    public static function getFromHeader(): ?string
    {
        // Try different header name variations
        $headers = getallheaders() ?: [];

        // Case-insensitive header search
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (!$authHeader) {
            error_log("⚠️ JWT: No Authorization header found");
            error_log("Available headers: " . json_encode(array_keys($headers)));
            return null;
        }

        error_log("📥 JWT: Authorization header found: " . substr($authHeader, 0, 50) . "...");

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            error_log("✅ JWT: Token extracted from Bearer header (length: " . strlen($token) . ")");
            return $token;
        }

        error_log("⚠️ JWT: Authorization header does not contain Bearer token");
        return null;
    }
}

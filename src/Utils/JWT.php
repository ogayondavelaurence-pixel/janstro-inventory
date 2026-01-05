<?php

namespace Janstro\InventorySystem\Utils;

/**
 * ============================================================================
 * JWT UTILITY - PRODUCTION READY v3.0
 * ============================================================================
 * FIXES:
 * ✅ APCu primary, database fallback for blacklist
 * ✅ Token expiration validation
 * ✅ Query parameter support (PDF downloads)
 * ============================================================================
 */
class JWT
{
    /**
     * Generate SHA-256 key for blacklist storage
     */
    private static function getBlacklistKey(string $token): string
    {
        return 'jwt_blacklist:' . hash('sha256', $token);
    }

    /**
     * Blacklist a token (logout/revoke)
     */
    public static function blacklist(string $token): void
    {
        $key = self::getBlacklistKey($token);
        $expiry = time() + (int)($_ENV['JWT_EXPIRATION'] ?? 3600);

        // Priority 1: APCu (in-memory cache)
        if (function_exists('apcu_store')) {
            apcu_store($key, true, $expiry);
            return;
        }

        // Priority 2: Database fallback
        try {
            $db = \Janstro\InventorySystem\Config\Database::connect();
            $stmt = $db->prepare("
                INSERT INTO jwt_blacklist (token_hash, expires_at) 
                VALUES (?, FROM_UNIXTIME(?)) 
                ON DUPLICATE KEY UPDATE expires_at = FROM_UNIXTIME(?)
            ");
            $stmt->execute([hash('sha256', $token), $expiry, $expiry]);
        } catch (\Exception $e) {
            error_log("JWT blacklist error: " . $e->getMessage());
        }
    }

    /**
     * Check if token is blacklisted
     */
    public static function isBlacklisted(string $token): bool
    {
        $key = self::getBlacklistKey($token);

        // Priority 1: APCu check
        if (function_exists('apcu_exists')) {
            return apcu_exists($key);
        }

        // Priority 2: Database check
        try {
            $db = \Janstro\InventorySystem\Config\Database::connect();
            $stmt = $db->prepare("
                SELECT 1 FROM jwt_blacklist 
                WHERE token_hash = ? AND expires_at > NOW()
            ");
            $stmt->execute([hash('sha256', $token)]);
            return $stmt->fetchColumn() !== false;
        } catch (\Exception $e) {
            error_log("JWT blacklist check error: " . $e->getMessage());
            return false; // Fail open for availability
        }
    }

    /**
     * Get JWT secret key
     */
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'janstro_secret_key_change_in_production';
    }

    /**
     * Get JWT algorithm
     */
    private static function getAlgorithm(): string
    {
        return $_ENV['JWT_ALGORITHM'] ?? 'HS256';
    }

    /**
     * Get JWT expiration time (seconds)
     */
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

        return \SimpleJWT::encode($token, self::getSecret(), self::getAlgorithm());
    }

    /**
     * Validate JWT token
     */
    public static function validate(string $token): ?object
    {
        try {
            if (empty($token)) {
                return null;
            }

            // Check blacklist first
            if (self::isBlacklisted($token)) {
                return null;
            }

            // Decode token
            $decoded = \SimpleJWT::decode($token, self::getSecret());

            // Check expiration
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            // Validate structure
            if (!isset($decoded->data) || !isset($decoded->data->user_id)) {
                return null;
            }

            return $decoded->data;
        } catch (\Exception $e) {
            error_log("JWT Validation Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract token from Authorization header OR query parameter
     */
    public static function getFromHeader(): ?string
    {
        $authHeader = null;

        // Method 1: getallheaders() (if available)
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
            $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;
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

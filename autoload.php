<?php

/**
 * MANUAL AUTOLOADER - NO COMPOSER REQUIRED
 * Place this file at: C:\xampp\htdocs\janstro-inventory\autoload.php
 */

// Define base path
define('BASE_PATH', __DIR__);

// Simple autoloader function
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'Janstro\\InventorySystem\\';
    $base_dir = BASE_PATH . '/src/';

    // Check if class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get relative class name
    $relative_class = substr($class, $len);

    // Convert to file path
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Load file if exists
    if (file_exists($file)) {
        require $file;
    }
});

// Manual .env loader (replaces vlucas/phpdotenv)
function loadEnv($path = BASE_PATH . '/.env')
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes
            $value = trim($value, '"\'');

            // Set environment variable
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load environment variables
loadEnv();

// Manual JWT implementation (replaces firebase/php-jwt)
class SimpleJWT
{
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode($payload, $secret, $algo = 'HS256')
    {
        $header = ['typ' => 'JWT', 'alg' => $algo];

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));

        $signing_input = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing_input, $secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode($jwt, $secret)
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT structure');
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        // Verify signature
        $signing_input = "$headerB64.$payloadB64";
        $signature = hash_hmac('sha256', $signing_input, $secret, true);
        $expected = self::base64UrlEncode($signature);

        if ($signatureB64 !== $expected) {
            throw new Exception('Invalid signature');
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadB64));

        // Check expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            throw new Exception('Token expired');
        }

        return $payload;
    }
}

// Make SimpleJWT available globally
class_alias('SimpleJWT', 'Firebase\\JWT\\JWT');
class_alias('SimpleJWT', 'Firebase\\JWT\\Key');

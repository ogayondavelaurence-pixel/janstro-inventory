<?php

namespace Janstro\InventorySystem\Utils;

class Response
{
    private static function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowedOrigins = [
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://localhost',
        ];

        if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }

    public static function success($data = null, string $message = 'Success', int $code = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], $code);
    }

    public static function error(string $message = 'Error', $errors = null, int $code = 400): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ], $code);
    }

    public static function badRequest(string $message = 'Bad Request', $errors = null): void
    {
        self::error($message, $errors, 400);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, null, 403);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, null, 404);
    }

    public static function serverError(string $message = 'Internal server error'): void
    {
        error_log("500 Error: $message");
        self::error($message, null, 500);
    }

    public static function validationError(array $errors): void
    {
        self::json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
            'timestamp' => date('c')
        ], 422);
    }

    private static function json(array $data, int $code): void
    {
        self::setCorsHeaders();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

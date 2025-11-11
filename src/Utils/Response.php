<?php

namespace Janstro\InventorySystem\Utils;

class Response
{
    public static function success($data = null, string $message = 'Success', int $code = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }

    public static function error(string $message = 'Error', $errors = null, int $code = 400): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
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
        self::error($message, null, 500);
    }

    private static function json(array $data, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

<?php

namespace Janstro\InventorySystem\Middleware;

class InputValidation
{
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return is_string($input)
            ? htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8')
            : $input;
    }

    public static function validateJSON(): ?array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return null;

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON input');
        }

        return self::sanitize($data);
    }
}

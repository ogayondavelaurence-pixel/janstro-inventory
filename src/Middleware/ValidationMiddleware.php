<?php

namespace Janstro\InventorySystem\Middleware;

class ValidationMiddleware
{
    private static array $rules = [
        'customer_name' => ['type' => 'string', 'max' => 255, 'required' => true],
        'email' => ['type' => 'email', 'max' => 100],
        'phone' => ['type' => 'phone', 'pattern' => '/^(09|\+639)\d{9}$/'],
        'quantity' => ['type' => 'int', 'min' => 1, 'max' => 999999],
        'unit_price' => ['type' => 'decimal', 'min' => 0, 'max' => 99999999.99],
        'username' => ['type' => 'alphanumeric', 'min' => 3, 'max' => 50],
        'password' => ['type' => 'string', 'min' => 8, 'max' => 72]
    ];

    public static function validate(array $data, array $fields): array
    {
        $errors = [];
        $sanitized = [];

        foreach ($fields as $field => $required) {
            $value = $data[$field] ?? null;
            $rule = self::$rules[$field] ?? ['type' => 'string'];

            // Required check
            if ($required && empty($value)) {
                $errors[$field] = "$field is required";
                continue;
            }

            if ($value === null) continue;

            // Type validation
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = "Invalid email format";
                    }
                    break;
                case 'int':
                    if (!is_numeric($value) || intval($value) != $value) {
                        $errors[$field] = "$field must be integer";
                    }
                    $value = (int)$value;
                    break;
                case 'decimal':
                    if (!is_numeric($value)) {
                        $errors[$field] = "$field must be numeric";
                    }
                    $value = (float)$value;
                    break;
                case 'phone':
                    if (!preg_match($rule['pattern'], $value)) {
                        $errors[$field] = "Invalid phone format";
                    }
                    break;
                case 'alphanumeric':
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                        $errors[$field] = "$field must be alphanumeric";
                    }
                    break;
            }

            // Length/Range validation
            if (isset($rule['min'])) {
                if (is_string($value) && strlen($value) < $rule['min']) {
                    $errors[$field] = "$field min length: {$rule['min']}";
                } elseif (is_numeric($value) && $value < $rule['min']) {
                    $errors[$field] = "$field min value: {$rule['min']}";
                }
            }

            if (isset($rule['max'])) {
                if (is_string($value) && strlen($value) > $rule['max']) {
                    $errors[$field] = "$field max length: {$rule['max']}";
                } elseif (is_numeric($value) && $value > $rule['max']) {
                    $errors[$field] = "$field max value: {$rule['max']}";
                }
            }

            // Sanitize
            $sanitized[$field] = is_string($value)
                ? htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8')
                : $value;
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $sanitized];
    }
}

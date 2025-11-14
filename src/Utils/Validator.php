<?php

namespace Janstro\InventorySystem\Utils;

class Validator
{
    public static function required($value): bool
    {
        return isset($value) && trim($value) !== '';
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function password(string $value): bool
    {
        // Minimum 8 characters, at least one letter and one number
        return preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $value);
    }

    public static function username(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]{3,20}$/', $value);
    }

    public static function positiveInt($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false && $value > 0;
    }

    public static function solarItemCode(string $code): bool
    {
        return preg_match('/^[A-Z]{2,5}-\d{3,5}$/', $code);
    }

    /**
     * Validate array of data against rules
     * Usage:
     * $errors = Validator::validate([
     *    'username' => ['required', 'username'],
     *    'password' => ['required', 'password']
     * ], $inputData);
     */
    public static function validate(array $rules, array $data): array
    {
        $errors = [];

        foreach ($rules as $field => $validators) {
            foreach ($validators as $validator) {
                if (!call_user_func([self::class, $validator], $data[$field] ?? null)) {
                    $errors[$field][] = "Invalid $field";
                }
            }
        }

        return $errors;
    }
}

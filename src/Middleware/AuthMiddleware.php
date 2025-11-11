<?php

namespace Janstro\InventorySystem\Middleware;

use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Utils\Response;

class AuthMiddleware
{
    public static function authenticate(): ?object
    {
        $token = JWT::getFromHeader();

        if (!$token) {
            Response::unauthorized('No token provided');
            return null;
        }

        $user = JWT::validate($token);

        if (!$user) {
            Response::unauthorized('Invalid or expired token');
            return null;
        }

        return $user;
    }

    public static function requireRole(array $allowedRoles): ?object
    {
        $user = self::authenticate();

        if (!$user) {
            return null;
        }

        if (!in_array($user->role, $allowedRoles)) {
            Response::forbidden('Insufficient permissions');
            return null;
        }

        return $user;
    }
}

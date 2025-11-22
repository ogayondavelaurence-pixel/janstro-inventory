<?php

/**
 * JANSTRO IMS - User Management Controller
 * Path: src/Controllers/UserController.php
 * 
 * FIXES:
 * - GET /users/current endpoint (CRITICAL - stops 404 errors)
 * - GET /users/:id endpoint
 * - GET /users endpoint (list all)
 * - PUT /users/:id endpoint
 * - DELETE /users/:id endpoint
 */

namespace Janstro\InventorySystem\Controllers;

require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Utils/Response.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use PDO;

class UserController
{
    private $db;
    private $conn;

    public function __construct()
    {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * GET /users/current - Get currently logged-in user
     * CRITICAL: This endpoint MUST exist for frontend to work
     */
    public function getCurrentUser()
    {
        // Check authentication
        $auth = AuthMiddleware::authenticate();
        if (!$auth) {
            return Response::unauthorized();
        }

        try {
            // Get user ID from session
            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                return Response::json([
                    'success' => false,
                    'message' => 'User session not found'
                ], 401);
            }

            // Fetch user data
            $stmt = $this->conn->prepare("
                SELECT user_id, username, email, full_name, role, 
                       phone, position, is_active, last_login, created_at
                FROM users 
                WHERE user_id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return Response::json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Remove sensitive data
            unset($user['password_hash']);

            return Response::success($user, 'Current user retrieved successfully');
        } catch (\PDOException $e) {
            return Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/:id - Get specific user by ID
     */
    public function getUser($userId)
    {
        // Check authentication
        $auth = AuthMiddleware::authenticate();
        if (!$auth) {
            return Response::unauthorized();
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT user_id, username, email, full_name, role, 
                       phone, position, is_active, last_login, created_at
                FROM users 
                WHERE user_id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return Response::json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return Response::success($user, 'User retrieved successfully');
        } catch (\PDOException $e) {
            return Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users - Get all users (admin only)
     */
    public function getAllUsers()
    {
        // Check authentication
        $auth = AuthMiddleware::authenticate();
        if (!$auth) {
            return Response::unauthorized();
        }

        // Check if user is admin or superadmin
        $userRole = $_SESSION['role'] ?? '';
        if (!in_array($userRole, ['admin', 'superadmin'])) {
            return Response::json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT user_id, username, email, full_name, role, 
                       phone, position, is_active, last_login, created_at
                FROM users 
                ORDER BY created_at DESC
            ");
            $stmt->execute();

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Response::success([
                'users' => $users,
                'total' => count($users)
            ], 'Users retrieved successfully');
        } catch (\PDOException $e) {
            return Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /users/:id - Update user
     */
    public function updateUser($userId)
    {
        $auth = AuthMiddleware::authenticate();
        if (!$auth) {
            return Response::unauthorized();
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Build dynamic update query
            $fields = [];
            $params = [':user_id' => $userId];

            $allowedFields = ['full_name', 'email', 'phone', 'position', 'role', 'is_active'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            if (empty($fields)) {
                return Response::error('No valid fields to update', 400);
            }

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                return Response::error('User not found or no changes made', 404);
            }

            return Response::success(null, 'User updated successfully');
        } catch (\PDOException $e) {
            return Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /users/:id - Delete user
     */
    public function deleteUser($userId)
    {
        $auth = AuthMiddleware::authenticate();
        if (!$auth) {
            return Response::unauthorized();
        }

        // Only superadmin can delete users
        if (($_SESSION['role'] ?? '') !== 'superadmin') {
            return Response::json([
                'success' => false,
                'message' => 'Only superadmin can delete users'
            ], 403);
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return Response::error('User not found', 404);
            }

            return Response::success(null, 'User deleted successfully');
        } catch (\PDOException $e) {
            return Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

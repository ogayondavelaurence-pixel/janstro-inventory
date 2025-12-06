<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Services\UserService;
use PDO;

/**
 * JANSTRO IMS - User Management Controller (FIXED)
 * Path: src/Controllers/UserController.php
 */
class UserController
{
    private PDO $db;
    private UserService $userService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->userService = new UserService();
    }

    /**
     * GET /users/current - Get currently logged-in user
     */
    public function getCurrentUser(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT u.user_id, u.username, u.name, u.email, u.role_id, 
                       r.role_name, u.status, u.contact_no, u.created_at
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$user->user_id]);
            $userData = $stmt->fetch();

            if (!$userData) {
                Response::notFound('User not found');
                return;
            }

            Response::success([
                'user_id' => (int)$userData['user_id'],
                'username' => $userData['username'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'role_id' => (int)$userData['role_id'],
                'role' => $userData['role_name'],
                'role_name' => $userData['role_name'],
                'status' => $userData['status'],
                'contact_no' => $userData['contact_no']
            ], 'Current user retrieved');
        } catch (\PDOException $e) {
            error_log("getCurrentUser error: " . $e->getMessage());
            Response::error('Database error', null, 500);
        }
    }

    /**
     * GET /users/:id - Get specific user
     */
    public function getById(int $userId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $userData = $this->userService->getUserById($userId);
            if ($userData) {
                Response::success($userData, 'User retrieved');
            } else {
                Response::notFound('User not found');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /users - Get all users
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $users = $this->userService->getAllUsers();
            Response::success($users, 'Users retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * POST /users - Create user
     */
    public function create(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->userService->createUser($data);
            Response::success($result, 'User created', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 400);
        }
    }

    /**
     * PUT /users/:id - Update user
     */
    public function update(int $userId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $success = $this->userService->updateUser($userId, $data);
            if ($success) {
                Response::success(null, 'User updated');
            } else {
                Response::error('Failed to update user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 400);
        }
    }

    /**
     * DELETE /users/:id - Delete user
     */
    public function delete(int $userId): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $success = $this->userService->deleteUser($userId, $user->user_id);
            if ($success) {
                Response::success(null, 'User deleted');
            } else {
                Response::error('Failed to delete user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 400);
        }
    }
}

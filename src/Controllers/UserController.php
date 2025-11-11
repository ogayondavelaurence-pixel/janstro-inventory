<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\UserService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * User Controller
 * Handles user management HTTP requests
 * ISO/IEC 25010: Security, Usability
 */
class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * GET /users
     * Get all users (superadmin only)
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Only admin and superadmin can view all users
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            Response::forbidden('Only superadmin can view all users');
            return;
        }

        try {
            $users = $this->userService->getAllUsers();
            Response::success($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /users/{id}
     * Get single user
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Users can only view their own profile unless they're superadmin
        if ($user->role !== 'superadmin' && $user->user_id !== $id) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $userData = $this->userService->getUserById($id);

            if (!$userData) {
                Response::notFound('User not found');
                return;
            }

            Response::success($userData, 'User found');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /users/roles - NEW METHOD
     * Get all available roles
     */
    public function getRoles(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Only admin and superadmin can view roles
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $roles = $this->userService->getRoles();
            Response::success($roles, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /users
     * Create new user (superadmin only)
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can create users');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->userService->createUser($data);

            Response::success($result, 'User created successfully', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * PUT /users/{id}
     * Update user
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Users can update their own profile or superadmin can update anyone
        if ($user->role !== 'superadmin' && $user->user_id !== $id) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Non-superadmins cannot change their role
            if ($user->role !== 'superadmin' && isset($data['role_id'])) {
                unset($data['role_id']);
            }

            $success = $this->userService->updateUser($id, $data);

            if ($success) {
                Response::success(null, 'User updated successfully');
            } else {
                Response::error('Failed to update user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * PUT /users/{id}/deactivate - NEW METHOD
     * Deactivate user account
     */
    public function deactivate(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can deactivate users');
            return;
        }

        // Prevent self-deactivation
        if ($id === $user->user_id) {
            Response::error('Cannot deactivate your own account');
            return;
        }

        try {
            $success = $this->userService->deactivateUser($id);

            if ($success) {
                Response::success(null, 'User deactivated successfully');
            } else {
                Response::error('Failed to deactivate user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * PUT /users/{id}/activate - NEW METHOD
     * Activate user account
     */
    public function activate(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can activate users');
            return;
        }

        try {
            $success = $this->userService->activateUser($id);

            if ($success) {
                Response::success(null, 'User activated successfully');
            } else {
                Response::error('Failed to activate user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * DELETE /users/{id}
     * Delete user (superadmin only)
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can delete users');
            return;
        }

        try {
            $success = $this->userService->deleteUser($id, $user->user_id);

            if ($success) {
                Response::success(null, 'User deleted successfully');
            } else {
                Response::error('Failed to delete user');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /users/change-password
     * Change user password
     */
    public function changePassword(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                Response::error('Current and new password are required');
                return;
            }

            $success = $this->userService->changePassword(
                $user->user_id,
                $data['current_password'],
                $data['new_password']
            );

            if ($success) {
                Response::success(null, 'Password changed successfully');
            } else {
                Response::error('Failed to change password');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}

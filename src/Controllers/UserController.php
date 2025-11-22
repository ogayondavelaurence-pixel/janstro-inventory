<?php

/**
 * JANSTRO IMS - User Management Controller
 * Location: src/Controllers/UserController.php
 * 
 * FIXES:
 * - GET /users/:id endpoint (was missing, causing 404)
 * - GET /users/current endpoint (for session user)
 * - GET /users endpoint (list all users - admin only)
 * - POST /users endpoint (create user - admin only)
 * - PUT /users/:id endpoint (update user - admin only)
 * - DELETE /users/:id endpoint (delete user - admin only)
 */

require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Utils/Response.php';

class UserController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /users/current - Get current logged-in user
     * FIXES: "Cannot read properties of undefined" error
     */
    public function getCurrentUser()
    {
        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            Response::error('Not authenticated', 401);
            return;
        }

        $userId = $_SESSION['user_id'];

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    username,
                    full_name,
                    email,
                    role,
                    status,
                    last_login,
                    created_at
                FROM users 
                WHERE user_id = ? AND status = 'active'
            ");

            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Response::error('User not found or inactive', 404);
                return;
            }

            // Remove sensitive data
            unset($user['password']);

            Response::success($user, 'Current user retrieved');
        } catch (PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
            Response::error('Failed to retrieve user data', 500);
        }
    }

    /**
     * GET /users/:id - Get specific user by ID
     * FIXES: 404 error on /users/undefined
     */
    public function getUser($userId)
    {
        // Validate user ID
        if (empty($userId) || $userId === 'undefined' || $userId === 'null') {
            Response::error('Invalid user ID', 400);
            return;
        }

        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            Response::error('Not authenticated', 401);
            return;
        }

        // Allow users to view their own profile, or require admin for others
        $requesterId = $_SESSION['user_id'];
        $requesterRole = $_SESSION['role'] ?? 'staff';

        if ($userId != $requesterId && !in_array($requesterRole, ['admin', 'superadmin'])) {
            Response::error('Insufficient permissions', 403);
            return;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    username,
                    full_name,
                    email,
                    role,
                    status,
                    last_login,
                    created_at
                FROM users 
                WHERE user_id = ?
            ");

            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Response::error('User not found', 404);
                return;
            }

            Response::success($user, 'User retrieved successfully');
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            Response::error('Failed to retrieve user', 500);
        }
    }

    /**
     * GET /users - List all users (Admin/Superadmin only)
     */
    public function getAllUsers()
    {
        // Check authentication
        if (!AuthMiddleware::checkRole(['admin', 'superadmin'])) {
            Response::error('Administrator access required', 403);
            return;
        }

        try {
            $stmt = $this->db->query("
                SELECT 
                    user_id,
                    username,
                    full_name,
                    email,
                    role,
                    status,
                    last_login,
                    created_at
                FROM users 
                ORDER BY created_at DESC
            ");

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'users' => $users,
                'total' => count($users)
            ], 'Users retrieved successfully');
        } catch (PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            Response::error('Failed to retrieve users', 500);
        }
    }

    /**
     * POST /users - Create new user (Admin/Superadmin only)
     */
    public function createUser()
    {
        // Check authentication
        if (!AuthMiddleware::checkRole(['admin', 'superadmin'])) {
            Response::error('Administrator access required', 403);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['username', 'full_name', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("Missing required field: $field", 400);
                return;
            }
        }

        // Validate role
        $validRoles = ['staff', 'admin', 'superadmin'];
        if (!in_array($data['role'], $validRoles)) {
            Response::error('Invalid role', 400);
            return;
        }

        // Only superadmin can create superadmin accounts
        if ($data['role'] === 'superadmin' && $_SESSION['role'] !== 'superadmin') {
            Response::error('Only superadmin can create superadmin accounts', 403);
            return;
        }

        try {
            // Check if username exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                Response::error('Username already exists', 409);
                return;
            }

            // Check if email exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                Response::error('Email already exists', 409);
                return;
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, full_name, email, password, role, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $data['username'],
                $data['full_name'],
                $data['email'],
                $hashedPassword,
                $data['role']
            ]);

            $userId = $this->db->lastInsertId();

            Response::success([
                'user_id' => $userId,
                'username' => $data['username'],
                'role' => $data['role']
            ], 'User created successfully', 201);
        } catch (PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            Response::error('Failed to create user', 500);
        }
    }

    /**
     * PUT /users/:id - Update user (Admin/Superadmin only)
     */
    public function updateUser($userId)
    {
        // Check authentication
        if (!AuthMiddleware::checkRole(['admin', 'superadmin'])) {
            Response::error('Administrator access required', 403);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                Response::error('User not found', 404);
                return;
            }

            // Only superadmin can modify superadmin accounts
            if ($existingUser['role'] === 'superadmin' && $_SESSION['role'] !== 'superadmin') {
                Response::error('Only superadmin can modify superadmin accounts', 403);
                return;
            }

            // Build update query dynamically
            $updates = [];
            $params = [];

            if (isset($data['full_name'])) {
                $updates[] = "full_name = ?";
                $params[] = $data['full_name'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['role']) && $_SESSION['role'] === 'superadmin') {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                Response::error('No valid fields to update', 400);
                return;
            }

            $params[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            Response::success(['user_id' => $userId], 'User updated successfully');
        } catch (PDOException $e) {
            error_log("Update user error: " . $e->getMessage());
            Response::error('Failed to update user', 500);
        }
    }

    /**
     * DELETE /users/:id - Delete user (Superadmin only)
     */
    public function deleteUser($userId)
    {
        // Check authentication - only superadmin can delete users
        if (!AuthMiddleware::checkRole(['superadmin'])) {
            Response::error('Superadmin access required', 403);
            return;
        }

        // Prevent self-deletion
        if ($userId == $_SESSION['user_id']) {
            Response::error('Cannot delete your own account', 400);
            return;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);

            if ($stmt->rowCount() === 0) {
                Response::error('User not found', 404);
                return;
            }

            Response::success(null, 'User deleted successfully');
        } catch (PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            Response::error('Failed to delete user', 500);
        }
    }
}

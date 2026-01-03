<?php

/**
 * ============================================================================
 * USER REPOSITORY - FIXED VERSION v2.0
 * ============================================================================
 * Path: src/Repositories/UserRepository.php
 * 
 * ✅ FIXES APPLIED:
 * - Added 'email' to $allowedFields (LINE 50)
 * - Added 'profile_picture' to $allowedFields (LINE 50)
 * - Now email and profile picture updates will work properly
 * ============================================================================
 */

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Models\User;
use PDO;
use PDOException;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* Find user by username */
    public function findByUsername(string $username): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.username = ? AND u.status = 'active'
            ");
            $stmt->execute([$username]);

            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("UserRepository::findByUsername Error: " . $e->getMessage());
            return null;
        }
    }

    /* Find user by ID */
    public function findById(int $userId): ?User
    {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$userId]);

            $result = $stmt->fetch();
            return $result ? new User($result) : null;
        } catch (PDOException $e) {
            error_log("UserRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    /* Get all users */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT u.*, r.role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                ORDER BY u.created_at DESC
            ");

            $users = [];
            while ($row = $stmt->fetch()) {
                $users[] = new User($row);
            }

            return $users;
        } catch (PDOException $e) {
            error_log("UserRepository::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /* Create new user */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, role_id, name, email, contact_no, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['username'],
                $data['password_hash'],
                $data['role_id'],
                $data['name'] ?? null,
                $data['email'] ?? null,
                $data['contact_no'] ?? null,
                $data['status'] ?? 'active'
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("UserRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /* ========================================================================
       UPDATE USER - ✅ CRITICAL FIX APPLIED
       ======================================================================== */
    public function update(int $userId, array $data): bool
    {
        try {
            $fields = [];
            $values = [];

            // ✅ CRITICAL FIX: Added 'email' and 'profile_picture' to allowed fields
            $allowedFields = [
                'username',
                'name',
                'email',              // ✅ ADDED - Now email updates work!
                'role_id',
                'contact_no',
                'profile_picture',    // ✅ ADDED - Now profile picture updates work!
                'status',
                'password_hash'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                error_log("UserRepository::update - No valid fields to update");
                return false;
            }

            $values[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);

            $success = $stmt->execute($values);

            if ($success) {
                error_log("✅ UserRepository::update - User {$userId} updated successfully");
            } else {
                error_log("❌ UserRepository::update - Update failed for user {$userId}");
            }

            return $success;
        } catch (PDOException $e) {
            error_log("❌ UserRepository::update Error: " . $e->getMessage());
            return false;
        }
    }

    /* Delete user */
    public function delete(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE user_id = ?");
            $success = $stmt->execute([$userId]);

            if ($success) {
                error_log("✅ UserRepository::delete - User {$userId} deleted successfully");
            }

            return $success;
        } catch (PDOException $e) {
            error_log("❌ UserRepository::delete Error: " . $e->getMessage());
            return false;
        }
    }

    /* Get all roles */
    public function getRoles(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM roles ORDER BY role_name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("UserRepository::getRoles Error: " . $e->getMessage());
            return [];
        }
    }

    /* Log audit event */
    public function logAudit(int $userId, string $action): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            return $stmt->execute([
                $userId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("UserRepository::logAudit Error: " . $e->getMessage());
            return false;
        }
    }

    /* Check if email exists (for validation) */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        try {
            if ($excludeUserId) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users 
                    WHERE email = ? AND user_id != ?
                ");
                $stmt->execute([$email, $excludeUserId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users 
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
            }

            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("UserRepository::emailExists Error: " . $e->getMessage());
            return false;
        }
    }

    /* Check if username exists (for validation) */
    public function usernameExists(string $username, ?int $excludeUserId = null): bool
    {
        try {
            if ($excludeUserId) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users 
                    WHERE username = ? AND user_id != ?
                ");
                $stmt->execute([$username, $excludeUserId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users 
                    WHERE username = ?
                ");
                $stmt->execute([$username]);
            }

            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("UserRepository::usernameExists Error: " . $e->getMessage());
            return false;
        }
    }
}

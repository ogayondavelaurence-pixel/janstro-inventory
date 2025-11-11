<?php

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

    /**
     * Find user by username
     */
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

    /**
     * Find user by ID
     */
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

    /**
     * Get all users
     */
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

    /**
     * Create new user
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, role_id, name, contact_no, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['username'],
                $data['password_hash'],
                $data['role_id'],
                $data['name'] ?? null,
                $data['contact_no'] ?? null,
                $data['status'] ?? 'active'
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("UserRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user
     */
    public function update(int $userId, array $data): bool
    {
        try {
            $fields = [];
            $values = [];

            $allowedFields = ['username', 'name', 'role_id', 'contact_no', 'status', 'password_hash'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("UserRepository::update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete user
     */
    public function delete(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("UserRepository::delete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all roles
     */
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

    /**
     * Log audit event
     */
    public function logAudit(int $userId, string $action): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            return $stmt->execute([$userId, $action]);
        } catch (PDOException $e) {
            error_log("UserRepository::logAudit Error: " . $e->getMessage());
            return false;
        }
    }
}

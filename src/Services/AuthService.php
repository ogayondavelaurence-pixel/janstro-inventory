<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;

class AuthService
{
    private $db;
    private $jwtSecret;
    private $jwtExpiry;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'janstro_inventory_secret_key_2025';
        $this->jwtExpiry = (int)($_ENV['JWT_EXPIRATION'] ?? 86400);

        error_log("======================================");
        error_log("🔧 AuthService Initialized");
        error_log("JWT Expiry: {$this->jwtExpiry}s (" . floor($this->jwtExpiry / 3600) . " hours)");
        error_log("======================================");
    }

    /**
     * ========================================================================
     * LOGIN - WITH JWT TOKEN FIX
     * ========================================================================
     */
    public function login($identifier, $password)
    {
        error_log("========================================");
        error_log("🔐 LOGIN ATTEMPT STARTED");
        error_log("========================================");
        error_log("📥 Input Identifier: " . var_export($identifier, true));
        error_log("📥 Input Password Length: " . strlen($password));

        // STEP 1: Validate inputs
        if (empty($identifier)) {
            error_log("❌ FAIL: Empty identifier");
            throw new \Exception("Username or email is required");
        }

        if (empty($password)) {
            error_log("❌ FAIL: Empty password");
            throw new \Exception("Password is required");
        }

        // STEP 2: Query database
        error_log("🔍 Querying database for user...");

        $sql = "
            SELECT 
                u.user_id, 
                u.username, 
                u.email, 
                u.password_hash, 
                u.role_id,
                u.name,
                u.status,
                r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            error_log("✅ Database query executed successfully");
        } catch (\PDOException $e) {
            error_log("❌ DATABASE ERROR: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }

        // STEP 3: Check if user exists
        if (!$user) {
            error_log("❌ FAIL: User not found in database");
            throw new \Exception("Invalid credentials");
        }

        error_log("✅ User found in database:");
        error_log("  - User ID: {$user['user_id']}");
        error_log("  - Username: {$user['username']}");
        error_log("  - Role: {$user['role_name']}");
        error_log("  - Status: {$user['status']}");

        // STEP 4: Check account status
        if ($user['status'] !== 'active') {
            error_log("❌ FAIL: Account is not active");
            throw new \Exception("Account is inactive. Contact administrator.");
        }

        // STEP 5: Verify password
        error_log("🔑 Verifying password...");

        if (!preg_match('/^\$2[ayb]\$.{56}$/', $user['password_hash'])) {
            error_log("❌ FAIL: Invalid bcrypt hash format!");
            throw new \Exception("Invalid password hash in database");
        }

        $passwordValid = password_verify($password, $user['password_hash']);

        if (!$passwordValid) {
            error_log("❌ FAIL: Password verification failed");
            throw new \Exception("Invalid credentials");
        }

        error_log("✅ PASSWORD VERIFIED SUCCESSFULLY!");

        // STEP 6: Update last login
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            error_log("✅ Last login updated");
        } catch (\PDOException $e) {
            error_log("⚠️ Warning: Could not update last_login: " . $e->getMessage());
        }

        // STEP 7: Create audit log
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'],
                "User logged in successfully",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            error_log("✅ Audit log created");
        } catch (\PDOException $e) {
            error_log("⚠️ Warning: Could not create audit log: " . $e->getMessage());
        }

        // STEP 8: Generate JWT token (FIXED STRUCTURE)
        error_log("🎟️ Generating JWT token...");

        $tokenPayload = [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'role_id' => (int)$user['role_id']
        ];

        error_log("🎟️ Token payload: " . json_encode($tokenPayload));

        try {
            $token = $this->generateToken($tokenPayload);
            error_log("✅ JWT token generated: " . substr($token, 0, 30) . "...");
        } catch (\Exception $e) {
            error_log("❌ FAIL: Token generation error: " . $e->getMessage());
            throw $e;
        }

        // STEP 9: Build response
        $response = [
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'user_id' => (int)$user['user_id'],
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                    'role_id' => (int)$user['role_id'],
                    'role_name' => $user['role_name']
                ]
            ]
        ];

        error_log("✅ LOGIN SUCCESSFUL!");
        error_log("✅ User: {$user['username']} ({$user['role_name']})");
        error_log("========================================");

        return $response;
    }

    private function generateToken($payload)
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->jwtExpiry;

        // CRITICAL FIX: Wrap payload under 'data' key
        $tokenPayload = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'iss' => 'janstro-ims',
            'data' => $payload  // ← THIS IS THE FIX
        ];

        error_log("🎟️ Token structure: " . json_encode($tokenPayload, JSON_PRETTY_PRINT));
        error_log("🎟️ Token will expire at: " . date('Y-m-d H:i:s', $expiresAt));

        return \SimpleJWT::encode($tokenPayload, $this->jwtSecret, 'HS256');
    }

    /**
     * Validate Token
     */
    public function validateToken($token)
    {
        try {
            $decoded = \SimpleJWT::decode($token, $this->jwtSecret);

            if (isset($decoded->exp) && $decoded->exp < time()) {
                error_log("❌ Token expired at: " . date('Y-m-d H:i:s', $decoded->exp));
                throw new \Exception("Token expired");
            }

            // Token structure now has 'data' wrapper
            if (!isset($decoded->data)) {
                error_log("❌ Token missing 'data' wrapper");
                throw new \Exception("Invalid token structure");
            }

            return (array) $decoded->data;
        } catch (\Exception $e) {
            error_log("❌ Token validation failed: " . $e->getMessage());
            throw new \Exception("Invalid or expired token: " . $e->getMessage());
        }
    }

    /**
     * Logout
     */
    public function logout($userId)
    {
        error_log("👋 User $userId logging out");

        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                "User logged out",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\PDOException $e) {
            error_log("⚠️ Warning: Could not create logout audit log: " . $e->getMessage());
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();

        return ['success' => true, 'message' => 'Logged out successfully'];
    }
}

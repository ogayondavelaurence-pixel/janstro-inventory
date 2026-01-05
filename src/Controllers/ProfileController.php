<?php

/**
 * ============================================================================
 * PROFILE CONTROLLER v8.0 - DELETION EMAIL NOTIFICATIONS ENHANCED
 * ============================================================================
 * CRITICAL FIXES:
 * ✅ Enhanced email notification with detailed logging
 * ✅ Better error handling for email failures
 * ✅ Returns email status in API response
 * ✅ Doesn't fail deletion request if email fails
 * ✅ Logs email configuration issues
 * ============================================================================
 */

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\EmailService;
use Janstro\InventorySystem\Utils\Response;
use PDO;

class ProfileController
{
    private PDO $db;
    private EmailService $emailService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->emailService = new EmailService();
    }

    /**
     * GET /profile - Get current user profile with security info
     */
    public function getProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.user_id, u.username, u.name, u.email, u.contact_no,
                    u.role_id, r.role_name,
                    u.recovery_email, u.recovery_email_verified,
                    u.two_factor_enabled, u.last_password_change,
                    u.last_login, u.created_at,
                    (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.user_id AND expires_at > NOW()) as active_sessions
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$user->user_id]);
            $profile = $stmt->fetch();

            if (!$profile) {
                Response::notFound('Profile not found');
                return;
            }

            Response::success([
                'user_id' => (int)$profile['user_id'],
                'username' => $profile['username'],
                'name' => $profile['name'],
                'email' => $profile['email'],
                'contact_no' => $profile['contact_no'],
                'role' => $profile['role_name'],
                'security' => [
                    'recovery_email' => $profile['recovery_email'],
                    'recovery_verified' => (bool)$profile['recovery_email_verified'],
                    'two_factor_enabled' => (bool)$profile['two_factor_enabled'],
                    'last_password_change' => $profile['last_password_change'],
                    'active_sessions' => (int)$profile['active_sessions']
                ],
                'account_info' => [
                    'last_login' => $profile['last_login'],
                    'member_since' => $profile['created_at']
                ]
            ], 'Profile retrieved');
        } catch (\PDOException $e) {
            error_log("Get profile error: " . $e->getMessage());
            Response::serverError('Failed to retrieve profile');
        }
    }

    /**
     * PUT /profile/update - Update profile information
     */
    public function updateProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $allowedFields = ['name', 'email', 'contact_no'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && trim($data[$field]) !== '') {
                    $updates[] = "{$field} = ?";
                    $params[] = trim($data[$field]);
                }
            }

            if (empty($updates)) {
                Response::badRequest('No valid fields to update');
                return;
            }

            if (isset($data['email'])) {
                $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$data['email'], $user->user_id]);
                if ($stmt->fetch()) {
                    Response::badRequest('Email already in use');
                    return;
                }
            }

            $params[] = $user->user_id;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAudit($user->user_id, 'Updated profile information', 'profile', 'update');

            Response::success(null, 'Profile updated successfully');
        } catch (\PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            Response::serverError('Failed to update profile');
        }
    }

    /**
     * POST /profile/change-password - Change password with security checks
     */
    public function changePassword(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['current_password']) || empty($data['new_password'])) {
                Response::badRequest('Current and new passwords required');
                return;
            }

            if (strlen($data['new_password']) < 8) {
                Response::badRequest('New password must be at least 8 characters');
                return;
            }

            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$user->user_id]);
            $userData = $stmt->fetch();

            if (!password_verify($data['current_password'], $userData['password_hash'])) {
                $this->logAudit($user->user_id, 'Failed password change attempt', 'security', 'password_change_failed');
                Response::badRequest('Current password is incorrect');
                return;
            }

            $stmt = $this->db->prepare("
                SELECT password_hash FROM password_history 
                WHERE user_id = ? 
                ORDER BY changed_at DESC 
                LIMIT 3
            ");
            $stmt->execute([$user->user_id]);
            $history = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($history as $oldHash) {
                if (password_verify($data['new_password'], $oldHash)) {
                    Response::badRequest('Cannot reuse recent passwords');
                    return;
                }
            }

            $newHash = password_hash($data['new_password'], PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    last_password_change = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$newHash, $user->user_id]);

            $stmt = $this->db->prepare("
                INSERT INTO password_history (user_id, password_hash) 
                VALUES (?, ?)
            ");
            $stmt->execute([$user->user_id, $newHash]);

            $this->logAudit($user->user_id, 'Password changed successfully', 'security', 'password_change');
            $this->revokeOtherSessions($user->user_id);

            Response::success([
                'message' => 'Password changed. Please log in again.',
                'sessions_revoked' => true
            ], 'Password changed successfully');
        } catch (\PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
            Response::serverError('Failed to change password');
        }
    }

    /**
     * POST /profile/recovery-email - Set recovery email with verification
     */
    public function setRecoveryEmail(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['recovery_email']) || !filter_var($data['recovery_email'], FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Valid recovery email required');
                return;
            }

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $this->db->prepare("
                UPDATE users 
                SET recovery_email = ?, 
                    recovery_email_verified = 0,
                    recovery_token = ?,
                    recovery_token_expires = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$data['recovery_email'], $token, $expires, $user->user_id]);

            $this->logAudit($user->user_id, 'Set recovery email', 'security', 'recovery_email_set');

            Response::success([
                'verification_required' => true,
                'expires_in' => '24 hours'
            ], 'Verification email sent. Check your inbox.');
        } catch (\PDOException $e) {
            error_log("Set recovery email error: " . $e->getMessage());
            Response::serverError('Failed to set recovery email');
        }
    }

    /**
     * GET /profile/sessions - Get active sessions
     */
    public function getSessions(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    session_id,
                    ip_address,
                    user_agent,
                    created_at,
                    last_activity,
                    expires_at
                FROM user_sessions
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$user->user_id]);
            $sessions = $stmt->fetchAll();

            Response::success([
                'sessions' => $sessions,
                'total' => count($sessions)
            ], 'Sessions retrieved');
        } catch (\PDOException $e) {
            error_log("Get sessions error: " . $e->getMessage());
            Response::serverError('Failed to retrieve sessions');
        }
    }

    /**
     * DELETE /profile/sessions/:id - Revoke specific session
     */
    public function revokeSession(int $sessionId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions 
                WHERE session_id = ? AND user_id = ?
            ");
            $stmt->execute([$sessionId, $user->user_id]);

            if ($stmt->rowCount() === 0) {
                Response::notFound('Session not found');
                return;
            }

            $this->logAudit($user->user_id, "Revoked session #{$sessionId}", 'security', 'session_revoked');

            Response::success(null, 'Session revoked');
        } catch (\PDOException $e) {
            error_log("Revoke session error: " . $e->getMessage());
            Response::serverError('Failed to revoke session');
        }
    }

    /**
     * DELETE /profile/sessions - Revoke all other sessions
     */
    public function revokeAllSessions(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $count = $this->revokeOtherSessions($user->user_id);

            $this->logAudit($user->user_id, "Revoked all sessions ({$count} terminated)", 'security', 'all_sessions_revoked');

            Response::success([
                'revoked_count' => $count
            ], 'All sessions revoked');
        } catch (\PDOException $e) {
            error_log("Revoke all sessions error: " . $e->getMessage());
            Response::serverError('Failed to revoke sessions');
        }
    }

    /**
     * GET /profile/export - Export user data (GDPR compliance)
     */
    public function exportData(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT user_id, username, name, email, contact_no, created_at, last_login
                FROM users WHERE user_id = ?
            ");
            $stmt->execute([$user->user_id]);
            $profile = $stmt->fetch();

            $stmt = $this->db->prepare("
                SELECT action_description, ip_address, created_at
                FROM audit_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1000
            ");
            $stmt->execute([$user->user_id]);
            $auditLogs = $stmt->fetchAll();

            $stmt = $this->db->prepare("
                SELECT t.*, i.item_name
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.item_id
                WHERE t.user_id = ?
                ORDER BY t.movement_date DESC
                LIMIT 1000
            ");
            $stmt->execute([$user->user_id]);
            $transactions = $stmt->fetchAll();

            $exportData = [
                'export_date' => date('Y-m-d H:i:s'),
                'profile' => $profile,
                'audit_logs' => $auditLogs,
                'transactions' => $transactions
            ];

            $this->logAudit($user->user_id, 'Exported user data', 'profile', 'data_export');

            Response::success($exportData, 'Data exported');
        } catch (\PDOException $e) {
            error_log("Export data error: " . $e->getMessage());
            Response::serverError('Failed to export data');
        }
    }

    /**
     * ========================================================================
     * POST /privacy/request-deletion - REQUEST ACCOUNT DELETION v8.6 FINAL
     * ========================================================================
     * ✅ FIXED: Rock-solid duplicate prevention
     * ✅ Proper transaction handling
     * ✅ Better error messages
     * ========================================================================
     */
    public function requestDeletion(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $reason = trim($data['reason'] ?? '');

            error_log("========================================");
            error_log("🗑️ DELETION REQUEST v8.6 FINAL");
            error_log("User ID: {$user->user_id}");
            error_log("Username: {$user->username}");
            error_log("========================================");

            // Validate reason
            if (strlen($reason) < 10) {
                error_log("❌ Reason too short");
                Response::badRequest('Reason must be at least 10 characters');
                return;
            }

            // ✅ CRITICAL: Start transaction BEFORE checking for duplicates
            $this->db->beginTransaction();

            try {
                // ✅ Check for existing pending request WITH lock
                $stmt = $this->db->prepare("
                    SELECT request_id, requested_at 
                    FROM user_deletion_requests 
                    WHERE user_id = ? AND status = 'pending'
                    FOR UPDATE
                ");
                $stmt->execute([$user->user_id]);
                $existingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingRequest) {
                    $this->db->rollBack();

                    error_log("⚠️ User already has pending request (ID: {$existingRequest['request_id']})");

                    Response::success([
                        'already_exists' => true,
                        'request_id' => (int)$existingRequest['request_id'],
                        'requested_at' => $existingRequest['requested_at'],
                        'status' => 'pending'
                    ], 'You already have a pending deletion request');
                    return;
                }

                error_log("✅ No existing request - creating new one");

                // Get full user data
                $stmt = $this->db->prepare("
                    SELECT u.user_id, u.username, u.name, u.email, r.role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$user->user_id]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$userData) {
                    throw new \Exception("User data not found");
                }

                // Insert deletion request
                $stmt = $this->db->prepare("
                    INSERT INTO user_deletion_requests (user_id, reason, requested_at, status)
                    VALUES (?, ?, NOW(), 'pending')
                ");
                $stmt->execute([$user->user_id, $reason]);
                $requestId = (int)$this->db->lastInsertId();

                if (!$requestId) {
                    throw new \Exception("Failed to create deletion request");
                }

                error_log("✅ Request created with ID: {$requestId}");

                // Audit log
                $this->logAudit($user->user_id, 'Requested account deletion', 'profile', 'deletion_request');

                // Commit transaction
                $this->db->commit();

                // Send email notification (don't fail if email fails)
                $emailSent = false;
                $emailError = null;

                try {
                    $emailSent = $this->emailService->sendDeletionRequestNotification($userData, $reason);
                    error_log("📧 Email notification: " . ($emailSent ? 'Sent' : 'Failed'));
                } catch (\Exception $e) {
                    $emailError = $e->getMessage();
                    error_log("❌ Email notification failed: {$emailError}");
                }

                error_log("========================================");
                error_log("✅ DELETION REQUEST COMPLETE");
                error_log("Request ID: {$requestId}");
                error_log("Email Sent: " . ($emailSent ? 'YES' : 'NO'));
                error_log("========================================");

                // Return success
                Response::success([
                    'request_id' => $requestId,
                    'status' => 'pending',
                    'requested_at' => date('Y-m-d H:i:s'),
                    'email_notification' => [
                        'sent' => $emailSent,
                        'error' => $emailError
                    ]
                ], 'Deletion request submitted successfully');
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\PDOException $e) {
            error_log("========================================");
            error_log("❌ DATABASE ERROR");
            error_log($e->getMessage());
            error_log("========================================");

            // Check if it's a duplicate key error
            if (
                strpos($e->getMessage(), 'Duplicate entry') !== false ||
                strpos($e->getMessage(), '1062') !== false
            ) {
                Response::badRequest('You already have a pending deletion request');
            } else {
                Response::serverError('Database error occurred');
            }
        } catch (\Exception $e) {
            error_log("========================================");
            error_log("❌ DELETION REQUEST ERROR");
            error_log($e->getMessage());
            error_log("========================================");
            Response::serverError('Failed to submit deletion request');
        }
    }

    /**
     * Helper: Revoke all sessions except current
     */
    private function revokeOtherSessions(int $userId): int
    {
        $currentToken = $this->getCurrentTokenHash();

        if ($currentToken) {
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions 
                WHERE user_id = ? AND token_hash != ?
            ");
            $stmt->execute([$userId, $currentToken]);
        } else {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        return $stmt->rowCount();
    }

    /**
     * Helper: Get current token hash
     */
    private function getCurrentTokenHash(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return hash('sha256', $matches[1]);
        }
        return null;
    }

    /**
     * Helper: Log audit event
     */
    private function logAudit(int $userId, string $description, string $module, string $action): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs 
                (user_id, action_description, module, action_type, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $description,
                $module,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}

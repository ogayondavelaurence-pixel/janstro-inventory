<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\UserRepository;

class UserService
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    /**
     * Get all users
     * 
     * @return array Array of users
     */
    public function getAllUsers(): array
    {
        $users = $this->userRepo->getAll();
        return array_map(fn($user) => $user->toArray(), $users);
    }

    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|null User data or null
     */
    public function getUserById(int $userId): ?array
    {
        $user = $this->userRepo->findById($userId);
        return $user ? $user->toArray() : null;
    }

    /**
     * Get all roles - NEW METHOD
     * 
     * @return array Array of roles
     */
    public function getRoles(): array
    {
        return $this->userRepo->getRoles();
    }

    /**
     * Create new user
     * 
     * @param array $data User data
     * @return array Created user info
     * @throws \Exception If validation fails
     */
    public function createUser(array $data): array
    {
        $this->validateUserData($data);

        // Check if username exists
        $existing = $this->userRepo->findByUsername($data['username']);
        if ($existing) {
            throw new \Exception('Username already exists');
        }

        // Hash password using bcrypt
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        unset($data['password']);

        $userId = $this->userRepo->create($data);

        if (!$userId) {
            throw new \Exception('Failed to create user');
        }

        return [
            'user_id' => $userId,
            'message' => 'User created successfully'
        ];
    }

    /**
     * Update user
     * 
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success status
     * @throws \Exception If validation fails
     */
    public function updateUser(int $userId, array $data): bool
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // If username is being changed, check for duplicates
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existing = $this->userRepo->findByUsername($data['username']);
            if ($existing) {
                throw new \Exception('Username already exists');
            }
        }

        // If password is being changed, hash it
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }

        return $this->userRepo->update($userId, $data);
    }

    /**
     * Deactivate user - NEW METHOD
     * 
     * @param int $userId User ID
     * @return bool Success status
     * @throws \Exception If user not found
     */
    public function deactivateUser(int $userId): bool
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if ($user->status === 'inactive') {
            throw new \Exception('User is already inactive');
        }

        return $this->userRepo->update($userId, ['status' => 'inactive']);
    }

    /**
     * Activate user - NEW METHOD
     * 
     * @param int $userId User ID
     * @return bool Success status
     * @throws \Exception If user not found
     */
    public function activateUser(int $userId): bool
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if ($user->status === 'active') {
            throw new \Exception('User is already active');
        }

        return $this->userRepo->update($userId, ['status' => 'active']);
    }

    /**
     * Delete user
     * 
     * @param int $userId User ID to delete
     * @param int $currentUserId Current user ID (to prevent self-deletion)
     * @return bool Success status
     * @throws \Exception If validation fails
     */
    public function deleteUser(int $userId, int $currentUserId): bool
    {
        if ($userId === $currentUserId) {
            throw new \Exception('Cannot delete your own account');
        }

        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $this->userRepo->delete($userId);
    }

    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success status
     * @throws \Exception If validation fails
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Get user data with password hash
        $userData = $this->userRepo->findByUsername($user->username);

        // Verify current password
        if (!password_verify($currentPassword, $userData['password_hash'])) {
            throw new \Exception('Current password is incorrect');
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            throw new \Exception('New password must be at least 8 characters');
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        return $this->userRepo->update($userId, ['password_hash' => $passwordHash]);
    }

    /**
     * Validate user data
     * 
     * @param array $data User data to validate
     * @throws \Exception If validation fails
     */
    private function validateUserData(array $data): void
    {
        if (empty($data['username'])) {
            throw new \Exception('Username is required');
        }

        if (strlen($data['username']) > 50) {
            throw new \Exception('Username too long (max 50 characters)');
        }

        if (empty($data['password'])) {
            throw new \Exception('Password is required');
        }

        if (strlen($data['password']) < 8) {
            throw new \Exception('Password must be at least 8 characters');
        }

        if (empty($data['role_id'])) {
            throw new \Exception('Role is required');
        }

        // Validate name length if provided
        if (isset($data['name']) && strlen($data['name']) > 100) {
            throw new \Exception('Name too long (max 100 characters)');
        }
    }
}

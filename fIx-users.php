<?php

/**
 * Janstro Inventory System - User Fix Script
 * Reactivates users and sets proper passwords
 * 
 * Usage: php fix-users.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "╔════════════════════════════════════════════════╗\n";
echo "║  JANSTRO INVENTORY - USER FIX SCRIPT          ║\n";
echo "║  Reactivate Users & Set Passwords             ║\n";
echo "╚════════════════════════════════════════════════╝\n\n";

// Connect to database
try {
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "✅ Database connection successful\n\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// User credentials to set
$users = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'name' => 'System Administrator',
        'role_id' => 1,
        'status' => 'active'
    ],
    [
        'username' => 'staff1',
        'password' => 'staff123',
        'name' => 'Staff User',
        'role_id' => 3,
        'status' => 'active'
    ]
];

echo "🔐 Fixing user accounts...\n\n";

foreach ($users as $user) {
    echo "Processing: {$user['username']}\n";

    // Check if user exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$user['username']]);
    $existing = $stmt->fetch();

    // Hash password with bcrypt
    $passwordHash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    if ($existing) {
        // Update existing user
        $stmt = $db->prepare("
            UPDATE users 
            SET password_hash = ?, 
                name = ?, 
                role_id = ?, 
                status = ? 
            WHERE username = ?
        ");
        $stmt->execute([
            $passwordHash,
            $user['name'],
            $user['role_id'],
            $user['status'],
            $user['username']
        ]);

        echo "   ✅ Updated: {$user['username']}\n";
        echo "   📝 Password: {$user['password']}\n";
        echo "   🔓 Status: {$user['status']}\n\n";
    } else {
        // Create new user
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, name, role_id, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['username'],
            $passwordHash,
            $user['name'],
            $user['role_id'],
            $user['status']
        ]);

        echo "   ✅ Created: {$user['username']}\n";
        echo "   📝 Password: {$user['password']}\n";
        echo "   🔓 Status: {$user['status']}\n\n";
    }
}

// Verify users are active and can login
echo "🔍 Verifying user status...\n";
$stmt = $db->query("SELECT username, status, role_id FROM users ORDER BY user_id");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    $statusIcon = $user['status'] === 'active' ? '✅' : '❌';
    echo "   $statusIcon {$user['username']} - {$user['status']} (role_id: {$user['role_id']})\n";
}

echo "\n";

// Test password verification
echo "🧪 Testing password verification...\n";

$testUsername = 'admin';
$testPassword = 'admin123';

$stmt = $db->prepare("SELECT password_hash FROM users WHERE username = ?");
$stmt->execute([$testUsername]);
$result = $stmt->fetch();

if ($result && password_verify($testPassword, $result['password_hash'])) {
    echo "   ✅ Password verification works for '$testUsername'\n";
} else {
    echo "   ❌ Password verification failed for '$testUsername'\n";
}

echo "\n";

// Final report
echo "╔════════════════════════════════════════════════╗\n";
echo "║           USER FIX COMPLETED                   ║\n";
echo "╚════════════════════════════════════════════════╝\n\n";

echo "🔐 LOGIN CREDENTIALS:\n\n";
echo "   ADMIN ACCESS:\n";
echo "   Username: admin\n";
echo "   Password: admin123\n";
echo "   Dashboard: http://localhost/janstro-inventory/superadmin/dashboard.php\n\n";

echo "   STAFF ACCESS:\n";
echo "   Username: staff1\n";
echo "   Password: staff123\n";
echo "   Dashboard: http://localhost/janstro-inventory/staff/dashboard.php\n\n";

echo "✅ All users are now active and ready to login!\n";
echo "🚀 Next: Test at http://localhost/janstro-inventory/login.php\n\n";

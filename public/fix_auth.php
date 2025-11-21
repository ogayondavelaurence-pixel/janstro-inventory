<?php

/**
 * JANSTRO INVENTORY SYSTEM
 * Password Hash Generator & Database Fixer
 * 
 * INSTRUCTIONS:
 * 1. Save this file as: C:\xampp\htdocs\janstro-inventory\public\fix_auth.php
 * 2. Visit: http://localhost:8080/janstro-inventory/public/fix_auth.php
 * 3. The script will automatically fix all user passwords
 * 
 * Date: 2025-11-21
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<html><head><title>Janstro Auth Fix</title>";
echo "<style>
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
h1 { color: #667eea; }
h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; }
.info { background: #cce5ff; color: #004085; padding: 15px; border-radius: 8px; margin: 10px 0; }
.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #667eea; color: white; }
tr:hover { background: #f5f5f5; }
code { background: #f0f0f0; padding: 3px 8px; border-radius: 4px; font-family: monospace; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 8px; overflow-x: auto; }
.btn { display: inline-block; padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 5px; }
.btn:hover { background: #5a6fd6; }
.btn-success { background: #28a745; }
.btn-danger { background: #dc3545; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Janstro Authentication Fix Tool</h1>";

// ============================================
// STEP 1: Generate Password Hashes
// ============================================
echo "<h2>Step 1: Generate Bcrypt Hashes</h2>";

$users = [
    ['username' => 'staff', 'password' => 'staff123', 'name' => 'Staff User', 'email' => 'staff@janstro.com', 'role_id' => 3],
    ['username' => 'admin', 'password' => 'admin123', 'name' => 'System Administrator', 'email' => 'admin@janstro.com', 'role_id' => 2],
    ['username' => 'superadmin', 'password' => 'superadmin123', 'name' => 'Super Administrator', 'email' => 'superadmin@janstro.com', 'role_id' => 1],
];

echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Role ID</th><th>Generated Hash</th><th>Hash Length</th></tr>";

$generatedHashes = [];
foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $generatedHashes[$user['username']] = [
        'hash' => $hash,
        'password' => $user['password'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role_id' => $user['role_id']
    ];

    $hashLen = strlen($hash);
    $status = $hashLen === 60 ? '✅' : '❌';

    echo "<tr>";
    echo "<td><strong>{$user['username']}</strong></td>";
    echo "<td><code>{$user['password']}</code></td>";
    echo "<td>{$user['role_id']}</td>";
    echo "<td><code style='font-size:11px;'>" . substr($hash, 0, 30) . "...</code></td>";
    echo "<td>$status $hashLen chars</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================
// STEP 2: Test Password Verification
// ============================================
echo "<h2>Step 2: Verify Hashes Work</h2>";

$allValid = true;
foreach ($generatedHashes as $username => $data) {
    $verified = password_verify($data['password'], $data['hash']);
    if ($verified) {
        echo "<div class='success'>✅ <strong>$username</strong>: password_verify('{$data['password']}', hash) = TRUE</div>";
    } else {
        echo "<div class='error'>❌ <strong>$username</strong>: password_verify('{$data['password']}', hash) = FALSE</div>";
        $allValid = false;
    }
}

// ============================================
// STEP 3: Connect to Database and Fix
// ============================================
echo "<h2>Step 3: Update Database</h2>";

try {
    $db = new PDO(
        'mysql:host=127.0.0.1;dbname=janstro_inventory;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<div class='success'>✅ Database connection successful</div>";

    // Ensure roles exist
    echo "<div class='info'>📋 Checking roles table...</div>";

    $db->exec("INSERT IGNORE INTO roles (role_id, role_name, description) VALUES
        (1, 'superadmin', 'Full system access with all privileges'),
        (2, 'admin', 'Administrative access to manage operations'),
        (3, 'staff', 'Standard user access for daily operations')
    ");

    // Delete existing test users
    echo "<div class='warning'>🗑️ Removing old test users...</div>";
    $db->exec("DELETE FROM users WHERE username IN ('admin', 'superadmin', 'staff', 'staff1')");

    // Insert users with correct hashes
    echo "<div class='info'>👤 Creating users with correct passwords...</div>";

    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, name, email, role_id, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");

    foreach ($generatedHashes as $username => $data) {
        $stmt->execute([
            $username,
            $data['hash'],
            $data['name'],
            $data['email'],
            $data['role_id']
        ]);
        echo "<div class='success'>✅ Created user: <strong>$username</strong> (role_id: {$data['role_id']})</div>";
    }

    // ============================================
    // STEP 4: Verify Everything Works
    // ============================================
    echo "<h2>Step 4: Final Verification</h2>";

    $stmt = $db->query("
        SELECT u.user_id, u.username, u.name, u.role_id, r.role_name, u.status, 
               LENGTH(u.password_hash) AS hash_length, u.password_hash
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.username IN ('admin', 'superadmin', 'staff')
        ORDER BY u.role_id
    ");

    echo "<table>";
    echo "<tr><th>User ID</th><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Hash Length</th><th>Password Test</th></tr>";

    $allVerified = true;
    while ($row = $stmt->fetch()) {
        $testPassword = $generatedHashes[$row['username']]['password'] ?? '';
        $verified = password_verify($testPassword, $row['password_hash']);
        $verifyStatus = $verified ? '✅ PASS' : '❌ FAIL';

        if (!$verified) $allVerified = false;

        echo "<tr>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td><strong>{$row['username']}</strong></td>";
        echo "<td>{$row['name']}</td>";
        echo "<td><span style='background:#667eea;color:white;padding:3px 10px;border-radius:12px;'>{$row['role_name']}</span></td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['hash_length']}</td>";
        echo "<td>$verifyStatus</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($allVerified) {
        echo "<div class='success' style='font-size:18px;text-align:center;padding:25px;'>";
        echo "🎉 <strong>AUTHENTICATION FIX COMPLETE!</strong><br><br>";
        echo "All users can now log in with their correct passwords.";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// SUMMARY
// ============================================
echo "<h2>📋 Login Credentials Summary</h2>";
echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Role</th><th>Permissions</th></tr>";
echo "<tr><td><code>staff</code></td><td><code>staff123</code></td><td>Staff</td><td>View inventory, create POs/SOs</td></tr>";
echo "<tr><td><code>admin</code></td><td><code>admin123</code></td><td>Admin</td><td>All operations except user management</td></tr>";
echo "<tr><td><code>superadmin</code></td><td><code>superadmin123</code></td><td>Super Admin</td><td>Full system access</td></tr>";
echo "</table>";

echo "<h2>🚀 Next Steps</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li><strong>Close this page</strong></li>";
echo "<li><strong>Go to the login page:</strong> <a href='/janstro-inventory/frontend/index.html' class='btn'>Open Login Page</a></li>";
echo "<li><strong>Test login with:</strong> <code>admin</code> / <code>admin123</code></li>";
echo "<li><strong>Delete this file after testing</strong> (for security)</li>";
echo "</ol>";
echo "</div>";

echo "<div style='text-align:center;margin-top:30px;'>";
echo "<a href='/janstro-inventory/frontend/index.html' class='btn btn-success' style='font-size:18px;padding:15px 40px;'>🔐 Go to Login Page</a>";
echo "</div>";

echo "</div></body></html>";

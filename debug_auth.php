<?php

/**
 * DEBUG AUTH - Run this to diagnose login issues
 * URL: http://localhost:8080/janstro-inventory/public/debug_auth.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>🔍 Auth Debug Tool</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $db = new PDO(
        'mysql:host=127.0.0.1;dbname=janstro_inventory;charset=utf8mb4',
        'root',
        ''
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✅ Database connected</p>";
} catch (Exception $e) {
    die("<p style='color:red'>❌ DB Error: " . $e->getMessage() . "</p>");
}

// Test 2: Fetch admin user
echo "<h2>2. User Query</h2>";
$stmt = $db->prepare("
    SELECT u.*, r.role_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.username = 'admin' AND u.status = 'active'
");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "<p style='color:green'>✅ User 'admin' found</p>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>user_id</td><td>{$user['user_id']}</td></tr>";
    echo "<tr><td>username</td><td>{$user['username']}</td></tr>";
    echo "<tr><td>role_id</td><td>{$user['role_id']}</td></tr>";
    echo "<tr><td>role_name</td><td>{$user['role_name']}</td></tr>";
    echo "<tr><td>status</td><td>{$user['status']}</td></tr>";
    echo "<tr><td>hash length</td><td>" . strlen($user['password_hash']) . "</td></tr>";
    echo "<tr><td>hash preview</td><td>" . substr($user['password_hash'], 0, 40) . "...</td></tr>";
    echo "</table>";
} else {
    die("<p style='color:red'>❌ User 'admin' NOT found or inactive!</p>");
}

// Test 3: Password Verification
echo "<h2>3. Password Verification</h2>";
$testPassword = 'admin123';
$storedHash = $user['password_hash'];

echo "<p><strong>Testing password:</strong> {$testPassword}</p>";
echo "<p><strong>Hash length:</strong> " . strlen($storedHash) . " (must be 60)</p>";

if (strlen($storedHash) !== 60) {
    echo "<p style='color:red'>❌ CRITICAL: Hash is " . strlen($storedHash) . " chars, should be 60!</p>";
    echo "<p>The password hash is corrupted. Run this SQL to fix:</p>";
    $newHash = password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "<pre>UPDATE users SET password_hash = '{$newHash}' WHERE username = 'admin';</pre>";
}

$result = password_verify($testPassword, $storedHash);

if ($result) {
    echo "<p style='color:green; font-size:20px'>✅ PASSWORD VERIFIED SUCCESSFULLY!</p>";
    echo "<p>The PHP password_verify() works. Issue is elsewhere in the code flow.</p>";
} else {
    echo "<p style='color:red; font-size:20px'>❌ PASSWORD VERIFICATION FAILED!</p>";

    // Generate new hash
    $newHash = password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "<h3>Fix: Run this SQL in phpMyAdmin:</h3>";
    echo "<pre style='background:#f0f0f0;padding:10px;'>
UPDATE users SET password_hash = '{$newHash}' WHERE username = 'admin';
UPDATE users SET password_hash = '{$newHash}' WHERE username = 'superadmin';
UPDATE users SET password_hash = '{$newHash}' WHERE username = 'staff1';
</pre>";
}

// Test 4: Simulate Full Login
echo "<h2>4. Simulated Login Test</h2>";
if ($result) {
    echo "<p style='color:green'>✅ Login would succeed</p>";
    echo "<p><strong>User would get:</strong></p>";
    echo "<ul>";
    echo "<li>user_id: {$user['user_id']}</li>";
    echo "<li>username: {$user['username']}</li>";
    echo "<li>role: {$user['role_name']}</li>";
    echo "</ul>";
} else {
    echo "<p style='color:red'>❌ Login would fail - fix password hash first</p>";
}

echo "<hr><p><em>Debug complete. Check results above.</em></p>";

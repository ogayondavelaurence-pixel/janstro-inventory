<?php

/**
 * Password Verification Test
 * Run: http://localhost:8080/janstro-inventory/test_password.php
 */

$password = 'admin123';
$hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5yvT7m.H6rXwu';

echo "<h2>Password Verification Test</h2>";
echo "<p><strong>Testing password:</strong> admin123</p>";
echo "<p><strong>Against hash:</strong> " . substr($hash, 0, 40) . "...</p>";

$result = password_verify($password, $hash);

if ($result) {
    echo "<h3 style='color:green'>✅ SUCCESS: Password verifies correctly!</h3>";
    echo "<p>The password hash is working. Issue is elsewhere.</p>";
} else {
    echo "<h3 style='color:red'>❌ FAILED: Password does not verify!</h3>";
    echo "<p>The password hash is incorrect. Generate a new one:</p>";
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "<pre>UPDATE users SET password_hash = '$newHash' WHERE username = 'admin';</pre>";
}

// Test database connection
echo "<hr><h3>Database Connection Test</h3>";

try {
    $db = new PDO('mysql:host=localhost;dbname=janstro_inventory', 'root', '');
    echo "<p style='color:green'>✅ Database connection successful</p>";

    // Test user query
    $stmt = $db->prepare("
        SELECT u.*, r.role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.username = 'admin' AND u.status = 'active'
    ");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "<p style='color:green'>✅ User 'admin' found in database</p>";
        echo "<pre>" . print_r($user, true) . "</pre>";

        // Test password from database
        echo "<h4>Password Verification from Database:</h4>";
        $dbResult = password_verify($password, $user['password_hash']);
        if ($dbResult) {
            echo "<p style='color:green'>✅ Password from DB verifies correctly!</p>";
        } else {
            echo "<p style='color:red'>❌ Password from DB does NOT verify!</p>";
        }
    } else {
        echo "<p style='color:red'>❌ User 'admin' not found or not active</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
}

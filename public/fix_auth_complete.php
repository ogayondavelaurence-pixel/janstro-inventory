<?php

/**
 * JANSTRO INVENTORY SYSTEM
 * Complete Authentication Fix v6.0 - PRODUCTION READY
 * 
 * INSTRUCTIONS:
 * 1. Save as: C:\xampp\htdocs\janstro-inventory\public\fix_auth_complete.php
 * 2. Visit: http://localhost:8080/janstro-inventory/public/fix_auth_complete.php
 * 3. This will handle foreign key constraints properly
 * 
 * Date: 2025-11-21
 * Version: 6.0 - Long-term solution
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Styling
echo "<html><head><title>Janstro Auth Fix v6.0</title>";
echo "<style>
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; background: linear-gradient(135deg, #667eea, #764ba2); }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
h1 { color: #667eea; text-align: center; font-size: 32px; margin-bottom: 10px; }
h2 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 12px; margin-top: 30px; }
.success { background: #d4edda; color: #155724; padding: 20px; border-radius: 12px; margin: 15px 0; border-left: 5px solid #28a745; }
.error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 12px; margin: 15px 0; border-left: 5px solid #dc3545; }
.info { background: #cce5ff; color: #004085; padding: 20px; border-radius: 12px; margin: 15px 0; border-left: 5px solid #0056b3; }
.warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 12px; margin: 15px 0; border-left: 5px solid #ffc107; }
table { width: 100%; border-collapse: collapse; margin: 25px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
th, td { padding: 15px; text-align: left; border-bottom: 2px solid #f0f0f0; }
th { background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-weight: 600; }
tr:hover { background: #f8f9fa; }
code { background: #f4f4f4; padding: 4px 10px; border-radius: 6px; font-family: 'Courier New', monospace; color: #e83e8c; }
.btn { display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; margin: 10px; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); transition: all 0.3s; }
.btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
.step { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #667eea; }
.step-number { background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Janstro Authentication Fix v6.0</h1>";
echo "<p style='text-align:center;color:#666;font-size:16px;'>Production-Ready Long-Term Solution</p>";

// ============================================
// STEP 1: Database Connection
// ============================================
echo "<div class='step'><span class='step-number'>1</span><strong>Database Connection</strong></div>";

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
    echo "<div class='success'>✅ Database connected successfully</div>";
} catch (PDOException $e) {
    die("<div class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div></div></body></html>");
}

// ============================================
// STEP 2: Check Foreign Key Constraints
// ============================================
echo "<div class='step'><span class='step-number'>2</span><strong>Analyzing Foreign Key Constraints</strong></div>";

$stmt = $db->query("
    SELECT 
        TABLE_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_NAME = 'users'
    AND TABLE_SCHEMA = 'janstro_inventory'
");

$constraints = $stmt->fetchAll();

if ($constraints) {
    echo "<div class='info'>📋 Found " . count($constraints) . " foreign key constraint(s) referencing users table:</div>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Constraint Name</th><th>References</th></tr>";
    foreach ($constraints as $fk) {
        echo "<tr>";
        echo "<td>{$fk['TABLE_NAME']}</td>";
        echo "<td><code>{$fk['CONSTRAINT_NAME']}</code></td>";
        echo "<td>users.{$fk['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ============================================
// STEP 3: Safe User Update Strategy
// ============================================
echo "<div class='step'><span class='step-number'>3</span><strong>Safe Update Strategy</strong></div>";

echo "<div class='info'>🛡️ <strong>Strategy:</strong> Instead of deleting users (which triggers FK constraints), we'll UPDATE existing users with new passwords. This preserves all relationships and is production-safe.</div>";

// ============================================
// STEP 4: Generate Fresh Password Hashes
// ============================================
echo "<div class='step'><span class='step-number'>4</span><strong>Generate Password Hashes</strong></div>";

$users = [
    ['username' => 'staff', 'password' => 'staff123', 'name' => 'Staff User', 'email' => 'staff@janstro.com', 'role_id' => 3],
    ['username' => 'admin', 'password' => 'admin123', 'name' => 'System Administrator', 'email' => 'admin@janstro.com', 'role_id' => 2],
    ['username' => 'superadmin', 'password' => 'superadmin123', 'name' => 'Super Administrator', 'email' => 'superadmin@janstro.com', 'role_id' => 1],
];

echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Role ID</th><th>Hash Preview</th><th>Verified</th></tr>";

$generatedHashes = [];
foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $verified = password_verify($user['password'], $hash) ? '✅' : '❌';

    $generatedHashes[$user['username']] = [
        'hash' => $hash,
        'password' => $user['password'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role_id' => $user['role_id']
    ];

    echo "<tr>";
    echo "<td><strong>{$user['username']}</strong></td>";
    echo "<td><code>{$user['password']}</code></td>";
    echo "<td>{$user['role_id']}</td>";
    echo "<td><code style='font-size:10px;'>" . substr($hash, 0, 40) . "...</code></td>";
    echo "<td>$verified</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================
// STEP 5: Ensure Roles Exist
// ============================================
echo "<div class='step'><span class='step-number'>5</span><strong>Ensure Role Structure</strong></div>";

try {
    $db->beginTransaction();

    $db->exec("
        INSERT IGNORE INTO roles (role_id, role_name, description) VALUES
        (1, 'superadmin', 'Full system access with all privileges'),
        (2, 'admin', 'Administrative access to manage operations'),
        (3, 'staff', 'Standard user access for daily operations')
    ");

    $stmt = $db->query("SELECT * FROM roles ORDER BY role_id");
    $roles = $stmt->fetchAll();

    echo "<div class='success'>✅ Roles verified:</div>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Role Name</th><th>Description</th></tr>";
    foreach ($roles as $role) {
        echo "<tr>";
        echo "<td>{$role['role_id']}</td>";
        echo "<td><strong>{$role['role_name']}</strong></td>";
        echo "<td>{$role['description']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    echo "<div class='error'>❌ Role setup error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ============================================
// STEP 6: Update or Create Users (Safe Method)
// ============================================
echo "<div class='step'><span class='step-number'>6</span><strong>Update/Create Users (FK-Safe)</strong></div>";

try {
    $db->beginTransaction();

    foreach ($generatedHashes as $username => $data) {
        // Check if user exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // UPDATE existing user (preserves FK relationships)
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    name = ?, 
                    email = ?, 
                    role_id = ?, 
                    status = 'active',
                    updated_at = NOW()
                WHERE username = ?
            ");
            $stmt->execute([
                $data['hash'],
                $data['name'],
                $data['email'],
                $data['role_id'],
                $username
            ]);
            echo "<div class='success'>✅ <strong>Updated</strong> user: $username (preserves all relationships)</div>";
        } else {
            // INSERT new user
            $stmt = $db->prepare("
                INSERT INTO users (username, password_hash, name, email, role_id, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $username,
                $data['hash'],
                $data['name'],
                $data['email'],
                $data['role_id']
            ]);
            echo "<div class='success'>✅ <strong>Created</strong> new user: $username</div>";
        }
    }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    echo "<div class='error'>❌ User update error: " . htmlspecialchars($e->getMessage()) . "</div>";
    die("</div></body></html>");
}

// ============================================
// STEP 7: Final Verification
// ============================================
echo "<div class='step'><span class='step-number'>7</span><strong>Final Verification</strong></div>";

$stmt = $db->query("
    SELECT u.user_id, u.username, u.name, u.email, u.role_id, r.role_name, u.status, 
           LENGTH(u.password_hash) AS hash_length, u.password_hash
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.username IN ('admin', 'superadmin', 'staff')
    ORDER BY u.role_id
");

echo "<table>";
echo "<tr><th>User ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Hash Valid</th><th>Password Test</th></tr>";

$allVerified = true;
while ($row = $stmt->fetch()) {
    $testPassword = $generatedHashes[$row['username']]['password'] ?? '';
    $verified = password_verify($testPassword, $row['password_hash']);
    $verifyStatus = $verified ? '<span style="color:#28a745;font-weight:bold;">✅ PASS</span>' : '<span style="color:#dc3545;font-weight:bold;">❌ FAIL</span>';
    $hashStatus = strlen($row['password_hash']) === 60 ? '✅' : '❌';

    if (!$verified) $allVerified = false;

    echo "<tr>";
    echo "<td>{$row['user_id']}</td>";
    echo "<td><strong>{$row['username']}</strong></td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td><span style='background:#667eea;color:white;padding:5px 12px;border-radius:15px;font-size:12px;'>{$row['role_name']}</span></td>";
    echo "<td><span style='color:#28a745;'>●</span> {$row['status']}</td>";
    echo "<td>$hashStatus {$row['hash_length']}</td>";
    echo "<td>$verifyStatus</td>";
    echo "</tr>";
}
echo "</table>";

if ($allVerified) {
    echo "<div class='success' style='font-size:20px;text-align:center;padding:30px;margin:30px 0;'>";
    echo "🎉 <strong>AUTHENTICATION FIX COMPLETE!</strong><br><br>";
    echo "All users verified successfully. Ready for production use.";
    echo "</div>";
}

// ============================================
// STEP 8: Test Login Credentials
// ============================================
echo "<div class='step'><span class='step-number'>8</span><strong>Login Credentials</strong></div>";

echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Role</th><th>Access Level</th></tr>";
echo "<tr><td><code>staff</code></td><td><code>staff123</code></td><td>Staff</td><td>View inventory, create POs/SOs</td></tr>";
echo "<tr><td><code>admin</code></td><td><code>admin123</code></td><td>Admin</td><td>Full operations (no user mgmt)</td></tr>";
echo "<tr><td><code>superadmin</code></td><td><code>superadmin123</code></td><td>Super Admin</td><td>Complete system access</td></tr>";
echo "</table>";

// ============================================
// STEP 9: Production Deployment Checklist
// ============================================
echo "<div class='step'><span class='step-number'>9</span><strong>Production Deployment Checklist</strong></div>";

echo "<div class='info'>";
echo "<h3 style='margin-top:0;'>✅ Pre-Deployment Checklist:</h3>";
echo "<ol style='line-height:2;'>";
echo "<li>✅ <strong>Test all three accounts</strong> (staff, admin, superadmin)</li>";
echo "<li>✅ <strong>Verify RBAC permissions</strong> on each page</li>";
echo "<li>✅ <strong>Test CRUD operations</strong> (Create, Read, Update, Delete)</li>";
echo "<li>✅ <strong>Test Purchase Orders</strong> → Goods Receipt flow</li>";
echo "<li>✅ <strong>Test Sales Orders</strong> → Invoice processing</li>";
echo "<li>✅ <strong>Verify stock movements</strong> are recorded correctly</li>";
echo "<li>✅ <strong>Check audit logs</strong> for all actions</li>";
echo "<li>⚠️ <strong>DELETE THIS FIX FILE</strong> after successful testing</li>";
echo "<li>⚠️ <strong>Change passwords</strong> before going live</li>";
echo "<li>⚠️ <strong>Update .env file</strong> with production JWT_SECRET</li>";
echo "</ol>";
echo "</div>";

// ============================================
// STEP 10: Security Recommendations
// ============================================
echo "<div class='step'><span class='step-number'>10</span><strong>Security Recommendations</strong></div>";

echo "<div class='warning'>";
echo "<h3 style='margin-top:0;'>🔒 Production Security:</h3>";
echo "<ul style='line-height:2;'>";
echo "<li>Change all default passwords immediately</li>";
echo "<li>Use strong passwords (12+ characters, mixed case, numbers, symbols)</li>";
echo "<li>Enable HTTPS in production</li>";
echo "<li>Update JWT_SECRET in .env file</li>";
echo "<li>Set APP_DEBUG=false in production</li>";
echo "<li>Regular database backups</li>";
echo "<li>Monitor audit_logs table regularly</li>";
echo "</ul>";
echo "</div>";

// ============================================
// Action Buttons
// ============================================
echo "<div style='text-align:center;margin:40px 0;'>";
echo "<a href='/janstro-inventory/frontend/index.html' class='btn' style='font-size:18px;padding:20px 50px;'>🔐 Go to Login Page</a>";
echo "<a href='javascript:window.print()' class='btn' style='background:#28a745;font-size:18px;padding:20px 50px;'>🖨️ Print This Report</a>";
echo "</div>";

echo "<div style='text-align:center;color:#666;margin-top:40px;padding-top:20px;border-top:2px solid #e0e0e0;'>";
echo "<p><strong>Janstro Inventory System v4.0</strong></p>";
echo "<p>Authentication Fix v6.0 - Production Ready</p>";
echo "<p>Date: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

echo "</div></body></html>";

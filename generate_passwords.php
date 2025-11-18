<?php

/**
 * PASSWORD HASH GENERATOR
 * Run this ONCE to get correct password hashes for your database
 * 
 * USAGE:
 * 1. Save as: generate_passwords.php in your project root
 * 2. Run: php generate_passwords.php
 * 3. Copy the SQL output
 * 4. Run SQL in phpMyAdmin
 */

echo "=== JANSTRO INVENTORY PASSWORD HASH GENERATOR ===\n\n";

// Generate hash for 'admin123'
$adminPassword = 'admin123';
$adminHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

// Generate hash for 'staff123'
$staffPassword = 'staff123';
$staffHash = password_hash($staffPassword, PASSWORD_BCRYPT, ['cost' => 12]);

echo "✅ Password hashes generated successfully!\n\n";

echo "=== COPY THIS SQL AND RUN IN PHPMYADMIN ===\n\n";

echo "-- Fix admin password (admin123)\n";
echo "UPDATE users SET password_hash = '{$adminHash}' WHERE username = 'admin';\n\n";

echo "-- Fix staff password (staff123)\n";
echo "UPDATE users SET password_hash = '{$staffHash}' WHERE username = 'staff1';\n\n";

echo "-- Verify passwords\n";
echo "SELECT user_id, username, role_id, status FROM users;\n\n";

echo "=== VERIFICATION ===\n\n";

// Test the hashes
$adminTest = password_verify('admin123', $adminHash);
$staffTest = password_verify('staff123', $staffHash);

echo "Admin password verification: " . ($adminTest ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Staff password verification: " . ($staffTest ? "✅ PASS" : "❌ FAIL") . "\n\n";

echo "=== CREDENTIALS TO USE IN FRONTEND ===\n\n";
echo "Username: admin\n";
echo "Password: admin123\n\n";
echo "Username: staff1\n";
echo "Password: staff123\n\n";

echo "=== DONE! ===\n";
echo "Now run the SQL above in phpMyAdmin, then test login.\n";

<?php

/**
 * Roles Seeder - MUST RUN FIRST
 * Creates the 4 required roles before users
 * 
 * Usage: php database/seed_roles.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Janstro\InventorySystem\Config\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = Database::connect();

echo "🔧 Seeding roles table...\n\n";

try {
    $db->beginTransaction();

    // Define roles with permissions hierarchy
    $roles = [
        [
            'role_name' => 'superadmin',
            'description' => 'Full system access - user management, configuration, all operations'
        ],
        [
            'role_name' => 'admin',
            'description' => 'Administrative access - inventory, orders, reports (no user management)'
        ],
        [
            'role_name' => 'manager',
            'description' => 'Operational management - approve orders, view reports, manage staff'
        ],
        [
            'role_name' => 'staff',
            'description' => 'Daily operations - record transactions, update inventory, create orders'
        ]
    ];

    foreach ($roles as $role) {
        // Check if role exists
        $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->execute([$role['role_name']]);

        if ($stmt->fetch()) {
            echo "  ℹ️  Role '{$role['role_name']}' already exists\n";
            continue;
        }

        // Insert role
        $stmt = $db->prepare("
            INSERT INTO roles (role_name, description)
            VALUES (?, ?)
        ");
        $stmt->execute([$role['role_name'], $role['description']]);

        echo "  ✅ Created role: {$role['role_name']}\n";
    }

    $db->commit();

    echo "\n✅ Roles seeding completed successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Now run: php database/seeder.php\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

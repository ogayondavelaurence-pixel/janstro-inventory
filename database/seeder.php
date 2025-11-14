<?php

/**
 * Database Seeder - Production Test Data
 * Run: php database/seeder.php
 * 
 * Creates:
 * - Test users (all roles)
 * - Sample categories
 * - Sample items
 * - Sample suppliers
 * - Sample customers
 * - Sample purchase orders
 * - Sample sales orders
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Janstro\InventorySystem\Config\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = Database::connect();

echo "🌱 Starting database seeder...\n\n";

try {
    $db->beginTransaction();

    // ============================================
    // 1. CREATE TEST USERS
    // ============================================
    echo "👥 Creating test users...\n";

    $users = [
        [
            'username' => 'superadmin',
            'password' => 'Super@123',
            'name' => 'Super Administrator',
            'role_name' => 'superadmin',
            'contact_no' => '09171234567'
        ],
        [
            'username' => 'admin',
            'password' => 'Admin@123',
            'name' => 'System Administrator',
            'role_name' => 'admin',
            'contact_no' => '09181234567'
        ],
        [
            'username' => 'manager1',
            'password' => 'Manager@123',
            'name' => 'Operations Manager',
            'role_name' => 'manager',
            'contact_no' => '09191234567'
        ],
        [
            'username' => 'staff1',
            'password' => 'Staff@123',
            'name' => 'Warehouse Staff One',
            'role_name' => 'staff',
            'contact_no' => '09201234567'
        ],
        [
            'username' => 'staff2',
            'password' => 'Staff@123',
            'name' => 'Warehouse Staff Two',
            'role_name' => 'staff',
            'contact_no' => '09211234567'
        ]
    ];

    foreach ($users as $userData) {
        // Get role_id
        $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = ?");
        $stmt->execute([$userData['role_name']]);
        $role = $stmt->fetch();

        if (!$role) {
            echo "  ⚠️  Role '{$userData['role_name']}' not found, skipping user {$userData['username']}\n";
            continue;
        }

        // Check if user exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$userData['username']]);

        if ($stmt->fetch()) {
            echo "  ℹ️  User '{$userData['username']}' already exists\n";
            continue;
        }

        // Hash password with bcrypt
        $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, role_id, name, contact_no, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $userData['username'],
            $passwordHash,
            $role['role_id'],
            $userData['name'],
            $userData['contact_no']
        ]);

        echo "  ✅ Created user: {$userData['username']} ({$userData['role_name']}) - Password: {$userData['password']}\n";
    }

    // ============================================
    // 2. CREATE CATEGORIES
    // ============================================
    echo "\n📁 Creating categories...\n";

    $categories = [
        ['name' => 'Solar Panels', 'description' => 'Photovoltaic solar panels'],
        ['name' => 'Inverters', 'description' => 'Solar power inverters'],
        ['name' => 'Batteries', 'description' => 'Energy storage batteries'],
        ['name' => 'Mounting Systems', 'description' => 'Panel mounting hardware'],
        ['name' => 'Cables & Connectors', 'description' => 'Electrical cables and connectors'],
        ['name' => 'Monitoring Systems', 'description' => 'Solar monitoring equipment']
    ];

    foreach ($categories as $cat) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO categories (name, description)
            VALUES (?, ?)
        ");
        $stmt->execute([$cat['name'], $cat['description']]);
        echo "  ✅ Created category: {$cat['name']}\n";
    }

    // ============================================
    // 3. CREATE ITEMS
    // ============================================
    echo "\n📦 Creating inventory items...\n";

    $items = [
        ['name' => 'Monocrystalline Solar Panel 550W', 'category' => 'Solar Panels', 'qty' => 150, 'unit' => 'pcs', 'reorder' => 30, 'price' => 15000],
        ['name' => 'Polycrystalline Solar Panel 450W', 'category' => 'Solar Panels', 'qty' => 200, 'unit' => 'pcs', 'reorder' => 40, 'price' => 12000],
        ['name' => 'Hybrid Solar Inverter 5kW', 'category' => 'Inverters', 'qty' => 80, 'unit' => 'pcs', 'reorder' => 15, 'price' => 45000],
        ['name' => 'Grid-Tie Inverter 3kW', 'category' => 'Inverters', 'qty' => 120, 'unit' => 'pcs', 'reorder' => 20, 'price' => 28000],
        ['name' => 'Lithium Battery 12V 200Ah', 'category' => 'Batteries', 'qty' => 60, 'unit' => 'pcs', 'reorder' => 10, 'price' => 35000],
        ['name' => 'Lead-Acid Battery 12V 150Ah', 'category' => 'Batteries', 'qty' => 90, 'unit' => 'pcs', 'reorder' => 15, 'price' => 18000],
        ['name' => 'Roof Mounting Kit (Aluminum)', 'category' => 'Mounting Systems', 'qty' => 200, 'unit' => 'set', 'reorder' => 40, 'price' => 3500],
        ['name' => 'Ground Mounting Kit (Steel)', 'category' => 'Mounting Systems', 'qty' => 150, 'unit' => 'set', 'reorder' => 30, 'price' => 5000],
        ['name' => 'Solar Cable 4mm² (per meter)', 'category' => 'Cables & Connectors', 'qty' => 1000, 'unit' => 'm', 'reorder' => 200, 'price' => 85],
        ['name' => 'MC4 Connector Pairs', 'category' => 'Cables & Connectors', 'qty' => 500, 'unit' => 'pair', 'reorder' => 100, 'price' => 120],
        ['name' => 'WiFi Solar Monitor', 'category' => 'Monitoring Systems', 'qty' => 50, 'unit' => 'pcs', 'reorder' => 10, 'price' => 2500],
        ['name' => 'LCD Display Monitor', 'category' => 'Monitoring Systems', 'qty' => 70, 'unit' => 'pcs', 'reorder' => 15, 'price' => 3200]
    ];

    foreach ($items as $item) {
        // Get category_id
        $stmt = $db->prepare("SELECT category_id FROM categories WHERE name = ?");
        $stmt->execute([$item['category']]);
        $category = $stmt->fetch();

        if (!$category) continue;

        $stmt = $db->prepare("
            INSERT INTO items (item_name, category_id, quantity, unit, reorder_level, unit_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $item['name'],
            $category['category_id'],
            $item['qty'],
            $item['unit'],
            $item['reorder'],
            $item['price']
        ]);

        echo "  ✅ Created item: {$item['name']}\n";
    }

    // ============================================
    // 4. CREATE SUPPLIERS
    // ============================================
    echo "\n🚚 Creating suppliers...\n";

    $suppliers = [
        [
            'name' => 'SolarTech Philippines Inc.',
            'contact' => '09171111111',
            'email' => 'sales@solartech.ph',
            'address' => '123 Solar Ave, Makati City'
        ],
        [
            'name' => 'Green Energy Solutions',
            'contact' => '09182222222',
            'email' => 'info@greenenergy.com.ph',
            'address' => '456 Renewable St, Quezon City'
        ],
        [
            'name' => 'PowerPlus Supplies Co.',
            'contact' => '09193333333',
            'email' => 'contact@powerplus.ph',
            'address' => '789 Electric Rd, Pasig City'
        ]
    ];

    foreach ($suppliers as $supplier) {
        $stmt = $db->prepare("
            INSERT INTO suppliers (supplier_name, contact_no, email, address)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $supplier['name'],
            $supplier['contact'],
            $supplier['email'],
            $supplier['address']
        ]);

        echo "  ✅ Created supplier: {$supplier['name']}\n";
    }

    // ============================================
    // 5. CREATE CUSTOMERS
    // ============================================
    echo "\n👤 Creating customers...\n";

    $customers = [
        [
            'name' => 'ABC Manufacturing Corp',
            'contact' => '09174444444',
            'email' => 'procurement@abcmfg.com',
            'address' => '111 Industrial Park, Laguna'
        ],
        [
            'name' => 'XYZ Residential Complex',
            'contact' => '09185555555',
            'email' => 'admin@xyzresidential.ph',
            'address' => '222 Condo St, BGC Taguig'
        ],
        [
            'name' => 'Sunshine Beach Resort',
            'contact' => '09196666666',
            'email' => 'manager@sunshineresort.ph',
            'address' => '333 Coastal Road, Batangas'
        ]
    ];

    foreach ($customers as $customer) {
        $stmt = $db->prepare("
            INSERT INTO customers (customer_name, contact_no, email, address)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $customer['name'],
            $customer['contact'],
            $customer['email'],
            $customer['address']
        ]);

        echo "  ✅ Created customer: {$customer['name']}\n";
    }

    // ============================================
    // 6. CREATE SAMPLE PURCHASE ORDERS
    // ============================================
    echo "\n🛒 Creating sample purchase orders...\n";

    // Get manager user
    $stmt = $db->prepare("
        SELECT u.user_id 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE r.role_name = 'manager' 
        LIMIT 1
    ");
    $stmt->execute();
    $manager = $stmt->fetch();

    if ($manager) {
        // Get some suppliers and items
        $stmt = $db->query("SELECT supplier_id FROM suppliers LIMIT 3");
        $suppliers = $stmt->fetchAll();

        $stmt = $db->query("SELECT item_id, unit_price FROM items LIMIT 5");
        $items = $stmt->fetchAll();

        foreach ($suppliers as $index => $supplier) {
            if (!isset($items[$index])) break;

            $item = $items[$index];
            $quantity = rand(10, 50);
            $totalAmount = $quantity * $item['unit_price'];

            $stmt = $db->prepare("
                INSERT INTO purchase_orders (supplier_id, status, created_by, notes)
                VALUES (?, 'pending', ?, 'Sample purchase order for testing')
            ");
            $stmt->execute([$supplier['supplier_id'], $manager['user_id']]);
            $poId = $db->lastInsertId();

            // Add PO items
            $stmt = $db->prepare("
                INSERT INTO purchase_order_items (po_id, item_id, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$poId, $item['item_id'], $quantity, $item['unit_price'], $totalAmount]);

            echo "  ✅ Created PO #{$poId} - Supplier #{$supplier['supplier_id']} - Total: ₱" . number_format($totalAmount, 2) . "\n";
        }
    }

    // ============================================
    // 7. CREATE SAMPLE SALES ORDERS
    // ============================================
    echo "\n💰 Creating sample sales orders...\n";

    // Get staff user
    $stmt = $db->prepare("
        SELECT u.user_id 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE r.role_name = 'staff' 
        LIMIT 1
    ");
    $stmt->execute();
    $staff = $stmt->fetch();

    if ($staff) {
        $stmt = $db->query("SELECT customer_id FROM customers LIMIT 3");
        $customers = $stmt->fetchAll();

        foreach ($customers as $index => $customer) {
            $installationDate = date('Y-m-d', strtotime('+' . (7 + $index * 3) . ' days'));

            $stmt = $db->prepare("
                INSERT INTO sales_orders (
                    customer_id, installation_address, installation_date,
                    total_amount, status, created_by, notes
                ) VALUES (?, 'Installation site TBD', ?, 0, 'pending', ?, 'Sample sales order')
            ");
            $stmt->execute([
                $customer['customer_id'],
                $installationDate,
                $staff['user_id']
            ]);

            $orderId = $db->lastInsertId();

            // Add 2-3 items per order
            $numItems = rand(2, 3);
            $totalAmount = 0;

            for ($i = 0; $i < $numItems; $i++) {
                $itemOffset = rand(0, 4);
                $stmt = $db->query("SELECT item_id, unit_price FROM items LIMIT $itemOffset, 1");
                $item = $stmt->fetch();

                if ($item) {
                    $quantity = rand(5, 20);
                    $lineTotal = $quantity * $item['unit_price'];
                    $totalAmount += $lineTotal;

                    $stmt = $db->prepare("
                        INSERT INTO sales_order_items (order_id, item_id, quantity, unit_price, line_total)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$orderId, $item['item_id'], $quantity, $item['unit_price'], $lineTotal]);
                }
            }

            // Update order total
            $stmt = $db->prepare("UPDATE sales_orders SET total_amount = ? WHERE order_id = ?");
            $stmt->execute([$totalAmount, $orderId]);

            echo "  ✅ Created Sales Order #{$orderId} - Customer #{$customer['customer_id']} - Total: ₱" . number_format($totalAmount, 2) . "\n";
        }
    }

    // ============================================
    // 8. CREATE WAREHOUSES
    // ============================================
    echo "\n🏢 Creating warehouses...\n";

    $warehouses = [
        ['code' => 'MAIN', 'name' => 'Main Warehouse', 'address' => 'Janstro HQ - Calamba, Laguna'],
        ['code' => 'NORTH', 'name' => 'North Warehouse', 'address' => 'Quezon City Branch'],
        ['code' => 'SOUTH', 'name' => 'South Warehouse', 'address' => 'Batangas Branch']
    ];

    foreach ($warehouses as $warehouse) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO warehouses (warehouse_code, warehouse_name, address, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$warehouse['code'], $warehouse['name'], $warehouse['address']]);
        echo "  ✅ Created warehouse: {$warehouse['name']}\n";
    }

    // ============================================
    // COMMIT TRANSACTION
    // ============================================
    $db->commit();

    echo "\n✅ Database seeding completed successfully!\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📝 TEST ACCOUNT CREDENTIALS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Superadmin: superadmin / Super@123\n";
    echo "Admin:      admin / Admin@123\n";
    echo "Manager:    manager1 / Manager@123\n";
    echo "Staff:      staff1 / Staff@123\n";
    echo "Staff:      staff2 / Staff@123\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

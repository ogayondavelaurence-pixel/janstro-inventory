<?php

/**
 * Janstro Inventory System - Setup Verification Script
 * Run this to check if all files are in place
 */

echo "🚀 Janstro Inventory System - Setup Verification\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Check PHP Version
echo "1. Checking PHP Version...\n";
$phpVersion = phpversion();
if (version_compare($phpVersion, '8.2.0', '>=')) {
    $success[] = "✅ PHP Version: $phpVersion (OK)";
} else {
    $errors[] = "❌ PHP Version: $phpVersion (Need 8.2+)";
}

// 2. Check Composer Autoload
echo "2. Checking Composer Autoload...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $success[] = "✅ Composer autoload found";
} else {
    $errors[] = "❌ Composer autoload not found. Run: composer install";
}

// 3. Check .env File
echo "3. Checking .env File...\n";
if (file_exists(__DIR__ . '/.env')) {
    $success[] = "✅ .env file exists";
} else {
    $warnings[] = "⚠️ .env file missing. Copy from .env.example";
}

// 4. Check Required Extensions
echo "4. Checking PHP Extensions...\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "✅ Extension: $ext";
    } else {
        $errors[] = "❌ Missing extension: $ext";
    }
}

// 5. Check Directory Structure
echo "5. Checking Directory Structure...\n";
$requiredDirs = [
    'src/Controllers',
    'src/Services',
    'src/Repositories',
    'src/Models',
    'src/Middleware',
    'src/Utils',
    'config',
    'public',
    'database'
];

foreach ($requiredDirs as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        $success[] = "✅ Directory: $dir";
    } else {
        $errors[] = "❌ Missing directory: $dir";
    }
}

// 6. Check Required Files
echo "6. Checking Required Files...\n";
$requiredFiles = [
    'config/database.php',
    'src/Utils/JWT.php',
    'src/Utils/Response.php',
    'src/Middleware/AuthMiddleware.php',
    'src/Models/User.php',
    'src/Models/Item.php',
    'src/Models/PurchaseOrder.php',
    'src/Models/Supplier.php',
    'src/Repositories/UserRepository.php',
    'src/Repositories/InventoryRepository.php',
    'src/Repositories/PurchaseOrderRepository.php',
    'src/Repositories/SupplierRepository.php',
    'src/Services/AuthService.php',
    'src/Services/InventoryService.php',
    'src/Services/OrderService.php',
    'src/Services/UserService.php',
    'src/Controllers/AuthController.php',
    'src/Controllers/InventoryController.php',
    'src/Controllers/OrderController.php',
    'src/Controllers/UserController.php',
    'public/index.php',
    'composer.json'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "✅ File: $file";
    } else {
        $errors[] = "❌ Missing file: $file";
    }
}

// 7. Test Database Connection
echo "7. Testing Database Connection...\n";
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    try {
        $db = new PDO(
            "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'],
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD']
        );
        $success[] = "✅ Database connection successful";

        // Check tables
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($tables) >= 8) {
            $success[] = "✅ Database tables exist (" . count($tables) . " tables)";
        } else {
            $warnings[] = "⚠️ Only " . count($tables) . " tables found. Expected 8+. Run database/schema.sql";
        }
    } catch (PDOException $e) {
        $errors[] = "❌ Database connection failed: " . $e->getMessage();
    }
} else {
    $warnings[] = "⚠️ Cannot test database (no .env file)";
}

// 8. Test Composer Packages
echo "8. Checking Composer Packages...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $packages = [
        'Firebase\JWT\JWT' => 'firebase/php-jwt',
        'Dotenv\Dotenv' => 'vlucas/phpdotenv'
    ];

    foreach ($packages as $class => $package) {
        if (class_exists($class)) {
            $success[] = "✅ Package: $package";
        } else {
            $errors[] = "❌ Missing package: $package";
        }
    }
}

// Print Results
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 VERIFICATION RESULTS\n";
echo str_repeat("=", 50) . "\n\n";

if (!empty($success)) {
    echo "✅ SUCCESS (" . count($success) . " checks passed)\n";
    foreach ($success as $item) {
        echo "   $item\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️ WARNINGS (" . count($warnings) . " warnings)\n";
    foreach ($warnings as $item) {
        echo "   $item\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . " errors)\n";
    foreach ($errors as $item) {
        echo "   $item\n";
    }
    echo "\n";
}

// Final Verdict
echo str_repeat("=", 50) . "\n";
if (empty($errors)) {
    echo "🎉 SYSTEM READY!\n";
    echo "\n";
    echo "Next Steps:\n";
    echo "1. Start XAMPP (Apache + MySQL)\n";
    echo "2. Visit: http://localhost/janstro-inventory/public/health\n";
    echo "3. Test login: POST /auth/login\n";
    echo "\n";
} else {
    echo "❌ SYSTEM NOT READY\n";
    echo "Please fix the errors above before proceeding.\n";
    echo "\n";
}

echo "Documentation: README.md\n";
echo "Implementation Guide: IMPLEMENTATION_GUIDE.md\n";

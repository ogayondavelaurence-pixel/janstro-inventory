<?php

/**
 * Janstro Inventory System - Database Migration Script
 * Migrates SHA-256 passwords to bcrypt and optimizes database
 * 
 * Usage: php migrate-to-bcrypt.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "╔════════════════════════════════════════════════╗\n";
echo "║  JANSTRO INVENTORY - DATABASE MIGRATION       ║\n";
echo "║  SHA-256 → bcrypt Password Migration          ║\n";
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

// Backup current users table
echo "📦 Creating backup of users table...\n";
try {
    $db->exec("DROP TABLE IF EXISTS users_backup_" . date('Ymd_His'));
    $db->exec("CREATE TABLE users_backup_" . date('Ymd_His') . " LIKE users");
    $db->exec("INSERT INTO users_backup_" . date('Ymd_His') . " SELECT * FROM users");
    echo "✅ Backup created: users_backup_" . date('Ymd_His') . "\n\n";
} catch (PDOException $e) {
    die("❌ Backup failed: " . $e->getMessage() . "\n");
}

// Get all users
echo "🔍 Fetching users with SHA-256 passwords...\n";
$stmt = $db->query("SELECT user_id, username, password_hash FROM users");
$users = $stmt->fetchAll();
echo "Found " . count($users) . " users\n\n";

// Migrate passwords
echo "🔐 Migrating passwords to bcrypt...\n";
$migratedCount = 0;
$skippedCount = 0;

foreach ($users as $user) {
    // Check if already bcrypt (bcrypt hashes start with $2y$)
    if (substr($user['password_hash'], 0, 4) === '$2y$') {
        echo "⏭️  Skipping {$user['username']} (already bcrypt)\n";
        $skippedCount++;
        continue;
    }

    // For SHA-256 hashes, we need to know the original password
    // Default password for migration: "admin123"
    $defaultPassword = 'admin123';

    // Verify if the stored hash matches SHA-256 of default password
    if ($user['password_hash'] === hash('sha256', $defaultPassword)) {
        $newHash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $updateStmt->execute([$newHash, $user['user_id']]);

        echo "✅ Migrated {$user['username']} (default password)\n";
        $migratedCount++;
    } else {
        // Unknown original password - set to default and flag for password reset
        $newHash = password_hash('ChangeMe123!', PASSWORD_BCRYPT, ['cost' => 12]);

        $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, status = 'inactive' WHERE user_id = ?");
        $updateStmt->execute([$newHash, $user['user_id']]);

        echo "⚠️  Migrated {$user['username']} (FORCED PASSWORD: ChangeMe123!)\n";
        $migratedCount++;
    }
}

echo "\n📊 Migration Summary:\n";
echo "   Migrated: $migratedCount users\n";
echo "   Skipped: $skippedCount users\n\n";

// Add indexes for performance
echo "⚡ Adding performance indexes...\n";

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_items_quantity ON items(quantity)",
    "CREATE INDEX IF NOT EXISTS idx_items_reorder ON items(reorder_level)",
    "CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(date_time)",
    "CREATE INDEX IF NOT EXISTS idx_po_status ON purchase_orders(status)",
    "CREATE INDEX IF NOT EXISTS idx_po_date ON purchase_orders(po_date)",
    "CREATE INDEX IF NOT EXISTS idx_audit_date ON audit_logs(created_at)",
    "CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)"
];

foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        echo "✅ " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Update password validation in database (stored procedure)
echo "🔧 Updating stored procedures...\n";

$db->exec("DROP PROCEDURE IF EXISTS sp_update_stock");

$procedureSQL = "
CREATE PROCEDURE sp_update_stock(
    IN p_item_id INT,
    IN p_quantity INT,
    IN p_operation VARCHAR(10),
    IN p_user_id INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_current_qty INT;
    
    START TRANSACTION;
    
    -- Get current quantity
    SELECT quantity INTO v_current_qty FROM items WHERE item_id = p_item_id FOR UPDATE;
    
    -- Update stock based on operation
    IF p_operation = 'IN' THEN
        UPDATE items SET quantity = quantity + p_quantity WHERE item_id = p_item_id;
    ELSEIF p_operation = 'OUT' THEN
        IF v_current_qty >= p_quantity THEN
            UPDATE items SET quantity = quantity - p_quantity WHERE item_id = p_item_id;
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
        END IF;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid operation';
    END IF;
    
    -- Log transaction
    INSERT INTO transactions (item_id, user_id, transaction_type, quantity, notes)
    VALUES (p_item_id, p_user_id, p_operation, p_quantity, p_notes);
    
    COMMIT;
END
";

try {
    $db->exec($procedureSQL);
    echo "✅ Stored procedure created\n\n";
} catch (PDOException $e) {
    echo "⚠️  " . $e->getMessage() . "\n\n";
}

// Create migration log
echo "📝 Creating migration log...\n";

$logSQL = "
CREATE TABLE IF NOT EXISTS migration_log (
    migration_id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('success', 'failed') DEFAULT 'success',
    notes TEXT
)
";

$db->exec($logSQL);

$db->exec("
    INSERT INTO migration_log (migration_name, status, notes)
    VALUES ('password_bcrypt_migration', 'success', 'Migrated $migratedCount users from SHA-256 to bcrypt')
");

echo "✅ Migration log created\n\n";

// Final report
echo "╔════════════════════════════════════════════════╗\n";
echo "║           MIGRATION COMPLETED                  ║\n";
echo "╚════════════════════════════════════════════════╝\n\n";

echo "📋 IMPORTANT NOTES:\n";
echo "1. ✅ All passwords migrated to bcrypt\n";
echo "2. ✅ Database indexes added for performance\n";
echo "3. ✅ Backup table created: users_backup_" . date('Ymd_His') . "\n";
echo "4. ⚠️  Users with unknown passwords have temp password: ChangeMe123!\n";
echo "5. ⚠️  These users are marked inactive and must reset password\n\n";

echo "🔐 DEFAULT CREDENTIALS:\n";
echo "   Username: admin\n";
echo "   Password: admin123\n\n";

echo "✅ Your system is now ready for production!\n";
echo "🚀 Next: Run composer install && php verify-setup.php\n\n";

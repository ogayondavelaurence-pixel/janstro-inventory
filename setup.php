    <?php

    /**
     * Janstro Inventory System - Master Setup Script
     * One-click installation and configuration
     * 
     * Usage: php setup.php
     */

    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║     JANSTRO INVENTORY MANAGEMENT SYSTEM SETUP          ║\n";
    echo "║     Automated Installation & Configuration             ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    $errors = [];
    $warnings = [];

    // ============================================
    // STEP 1: Check Prerequisites
    // ============================================
    echo "STEP 1: Checking Prerequisites\n";
    echo str_repeat("-", 56) . "\n";

    // Check PHP version
    echo "Checking PHP version... ";
    if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
        echo "✅ PHP " . PHP_VERSION . "\n";
    } else {
        $errors[] = "PHP 8.2+ required. Current: " . PHP_VERSION;
        echo "❌ FAILED\n";
    }

    // Check required extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'curl'];
    foreach ($requiredExtensions as $ext) {
        echo "Checking extension $ext... ";
        if (extension_loaded($ext)) {
            echo "✅\n";
        } else {
            $errors[] = "Missing PHP extension: $ext";
            echo "❌\n";
        }
    }

    // Check Composer
    echo "Checking Composer... ";
    if (file_exists('composer.phar') || exec('which composer') || exec('where composer')) {
        echo "✅\n";
    } else {
        $warnings[] = "Composer not found. Will attempt manual dependency check.";
        echo "⚠️\n";
    }

    echo "\n";

    // ============================================
    // STEP 2: Install Dependencies
    // ============================================
    echo "STEP 2: Installing Dependencies\n";
    echo str_repeat("-", 56) . "\n";

    if (!file_exists('vendor/autoload.php')) {
        echo "Installing Composer dependencies...\n";

        if (file_exists('composer.phar')) {
            exec('php composer.phar install', $output, $returnCode);
        } else {
            exec('composer install', $output, $returnCode);
        }

        if ($returnCode === 0) {
            echo "✅ Dependencies installed successfully\n";
        } else {
            $errors[] = "Failed to install dependencies. Run 'composer install' manually.";
            echo "❌ Installation failed\n";
        }
    } else {
        echo "✅ Dependencies already installed\n";
    }

    echo "\n";

    // ============================================
    // STEP 3: Environment Configuration
    // ============================================
    echo "STEP 3: Environment Configuration\n";
    echo str_repeat("-", 56) . "\n";

    if (!file_exists('.env')) {
        echo "Creating .env file from template... ";

        if (file_exists('.env.example')) {
            copy('.env.example', '.env');
            echo "✅\n";
            echo "⚠️  Please edit .env with your database credentials\n";

            // Try to generate JWT secret
            $jwtSecret = bin2hex(random_bytes(32));
            $envContent = file_get_contents('.env');
            $envContent = str_replace(
                'JWT_SECRET=your-secret-key-here',
                'JWT_SECRET=' . $jwtSecret,
                $envContent
            );
            file_put_contents('.env', $envContent);
            echo "✅ Generated JWT secret key\n";
        } else {
            $errors[] = ".env.example not found";
            echo "❌\n";
        }
    } else {
        echo "✅ .env file exists\n";
    }

    echo "\n";

    // ============================================
    // STEP 4: Database Setup
    // ============================================
    echo "STEP 4: Database Setup\n";
    echo str_repeat("-", 56) . "\n";

    // Try to load environment
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';

        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();

            echo "Testing database connection... ";

            $db = new PDO(
                "mysql:host={$_ENV['DB_HOST']};charset=utf8mb4",
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            echo "✅\n";

            // Check if database exists
            echo "Checking database '{$_ENV['DB_DATABASE']}'... ";
            $stmt = $db->query("SHOW DATABASES LIKE '{$_ENV['DB_DATABASE']}'");

            if ($stmt->rowCount() > 0) {
                echo "✅ Database exists\n";

                // Connect to database
                $db = new PDO(
                    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
                    $_ENV['DB_USERNAME'],
                    $_ENV['DB_PASSWORD'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // Check tables
                $stmt = $db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                echo "Found " . count($tables) . " tables\n";

                if (count($tables) < 8) {
                    echo "⚠️  Tables incomplete. Import database/schema.sql\n";
                    $warnings[] = "Database tables incomplete";
                } else {
                    echo "✅ All tables present\n";
                }
            } else {
                echo "❌ Database not found\n";
                echo "Creating database... ";

                $db->exec("CREATE DATABASE {$_ENV['DB_DATABASE']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "✅\n";

                echo "⚠️  Import database/schema.sql to create tables\n";
                $warnings[] = "Database created but tables not imported";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            echo "❌ " . $e->getMessage() . "\n";
        }
    } else {
        $warnings[] = "Cannot test database - dependencies not installed";
        echo "⚠️  Skipped (dependencies missing)\n";
    }

    echo "\n";

    // ============================================
    // STEP 5: Directory Permissions
    // ============================================
    echo "STEP 5: Directory Permissions\n";
    echo str_repeat("-", 56) . "\n";

    $directories = ['logs', 'uploads', 'cache'];

    foreach ($directories as $dir) {
        echo "Checking directory: $dir... ";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✅ Created\n";
        } else {
            echo "✅ Exists\n";
        }

        if (is_writable($dir)) {
            echo "   Writable: ✅\n";
        } else {
            $warnings[] = "Directory not writable: $dir";
            echo "   Writable: ⚠️\n";
        }
    }

    echo "\n";

    // ============================================
    // STEP 6: Create Configuration Files
    // ============================================
    echo "STEP 6: Configuration Files\n";
    echo str_repeat("-", 56) . "\n";

    // Create .htaccess if not exists
    if (!file_exists('public/.htaccess')) {
        echo "Creating .htaccess... ";

        $htaccess = <<<HTACCESS
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /janstro-inventory/public/
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </IfModule>

    # Security Headers
    <IfModule mod_headers.c>
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"
        Header set Referrer-Policy "strict-origin-when-cross-origin"
    </IfModule>

    # Deny access to sensitive files
    <FilesMatch "\.(env|md|gitignore|log)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
    HTACCESS;

        file_put_contents('public/.htaccess', $htaccess);
        echo "✅\n";
    } else {
        echo "✅ .htaccess exists\n";
    }

    echo "\n";

    // ============================================
    // FINAL REPORT
    // ============================================
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║                   SETUP COMPLETE                       ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n\n";

    if (empty($errors)) {
        echo "✅ SUCCESS! Setup completed successfully.\n\n";
    } else {
        echo "❌ ERRORS FOUND (" . count($errors) . "):\n";
        foreach ($errors as $error) {
            echo "   • $error\n";
        }
        echo "\n";
    }

    if (!empty($warnings)) {
        echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
        foreach ($warnings as $warning) {
            echo "   • $warning\n";
        }
        echo "\n";
    }

    echo "📋 NEXT STEPS:\n";
    echo "1. ✅ Review .env file with correct database credentials\n";
    echo "2. ✅ Import database schema:\n";
    echo "      mysql -u root -p janstro_inventory < database/schema.sql\n";
    echo "3. ✅ Run password migration:\n";
    echo "      php migrate-to-bcrypt.php\n";
    echo "4. ✅ Start XAMPP (Apache + MySQL)\n";
    echo "5. ✅ Access system:\n";
    echo "      http://localhost/janstro-inventory/login.php\n\n";

    echo "🔐 DEFAULT CREDENTIALS:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";

    echo "📖 DOCUMENTATION:\n";
    echo "   • README.md - System overview\n";
    echo "   • IMPLEMENTATION_GUIDE.md - Developer guide\n";
    echo "   • API Documentation: /public/health\n\n";

    echo "🚀 READY FOR DEVELOPMENT!\n\n";

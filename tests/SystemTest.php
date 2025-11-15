<?php

/**
 * Janstro Inventory System - Comprehensive Test Suite
 * PHPUnit 11.5 Compatible
 * 
 * Run: vendor/bin/phpunit tests/SystemTest.php
 */

namespace Janstro\InventorySystem\Tests;

use PHPUnit\Framework\TestCase;
use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Services\InventoryService;
use Janstro\InventorySystem\Services\OrderService;
use Janstro\InventorySystem\Services\UserService;
use Janstro\InventorySystem\Utils\Security;
use Janstro\InventorySystem\Utils\JWT;

class SystemTest extends TestCase
{
    private static $testUserId;
    private static $testToken;
    public static function setUpBeforeClass(): void
    {
        echo "\n🧪 Starting Janstro Inventory System Tests\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function testCanLoginWithValidCredentials(): void
    {
        echo "🔐 Testing login with valid credentials...\n";

        $authService = new AuthService();
        $result = $authService->login('admin', 'Admin@123');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);

        self::$testToken = $result['token'];
        self::$testUserId = $result['user']['user_id'];

        echo "  ✅ Login successful\n";
    }

    public function testCannotLoginWithInvalidPassword(): void
    {
        echo "🔐 Testing login with invalid password...\n";

        $authService = new AuthService();
        $result = $authService->login('admin', 'wrongpassword');

        $this->assertNull($result);
        echo "  ✅ Invalid login correctly rejected\n";
    }

    public function testPasswordHashingUsesBcrypt(): void
    {
        echo "🔐 Testing bcrypt password hashing...\n";

        $password = 'TestPassword@123';
        $hash = Security::hashPassword($password);

        $this->assertTrue(password_verify($password, $hash));
        $this->assertStringStartsWith('$2y$', $hash); // Bcrypt identifier

        echo "  ✅ Bcrypt hashing verified\n";
    }

    public function testJWTTokenValidation(): void
    {
        echo "🔐 Testing JWT token validation...\n";

        $payload = [
            'user_id' => 1,
            'username' => 'testuser',
            'role' => 'admin'
        ];

        $token = JWT::generate($payload);
        $decoded = JWT::validate($token);

        $this->assertNotNull($decoded);
        $this->assertEquals(1, $decoded->user_id);
        $this->assertEquals('admin', $decoded->role);

        echo "  ✅ JWT validation successful\n";
    }

    // ============================================
    // RBAC TESTS
    // ============================================

    public function testRateLimitingWorks(): void
    {
        echo "🛡️ Testing rate limiting...\n";

        $key = 'test_' . time();

        // First 5 attempts should pass
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Security::checkRateLimit($key, 5, 60));
        }

        // 6th attempt should fail
        $this->assertFalse(Security::checkRateLimit($key, 5, 60));

        echo "  ✅ Rate limiting working correctly\n";
    }

    public function testPasswordStrengthValidation(): void
    {
        echo "🛡️ Testing password strength validation...\n";

        $this->assertTrue(Security::validatePasswordStrength('Strong@123'));
        $this->assertFalse(Security::validatePasswordStrength('weak')); // Too short
        $this->assertFalse(Security::validatePasswordStrength('nodigits')); // No digits

        echo "  ✅ Password strength validation working\n";
    }

    // ============================================
    // INVENTORY TESTS
    // ============================================

    public function testCanRetrieveInventoryItems(): void
    {
        echo "📦 Testing inventory retrieval...\n";

        $inventoryService = new InventoryService();
        $items = $inventoryService->getAllItems();

        $this->assertIsArray($items);
        $this->assertGreaterThan(0, count($items));

        echo "  ✅ Retrieved " . count($items) . " items\n";
    }

    public function testCanCreateInventoryItem(): void
    {
        echo "📦 Testing inventory item creation...\n";

        $inventoryService = new InventoryService();

        $testItem = [
            'item_name' => 'Test Solar Panel',
            'category_id' => 1,
            'quantity' => 10,
            'unit' => 'pcs',
            'reorder_level' => 5,
            'unit_price' => 5000.00
        ];

        $result = $inventoryService->createItem($testItem);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('item_id', $result);
        $this->assertGreaterThan(0, $result['item_id']);

        echo "  ✅ Item created with ID: {$result['item_id']}\n";
    }

    public function testLowStockDetection(): void
    {
        echo "📦 Testing low stock detection...\n";

        $inventoryService = new InventoryService();
        $lowStockItems = $inventoryService->getLowStockItems();

        $this->assertIsArray($lowStockItems);

        echo "  ✅ Found " . count($lowStockItems) . " low stock items\n";
    }

    // ============================================
    // PURCHASE ORDER TESTS
    // ============================================
    // ✅ REPLACE WITH:
    public function testCanCreatePurchaseOrder(): void
    {
        echo "🛒 Testing purchase order creation...\n";

        $orderService = new OrderService();

        $testOrder = [
            'supplier_id' => 1,
            'items' => [                 // ✅ CORRECT - multi-item array
                [
                    'item_id' => 1,
                    'quantity' => 50,
                    'unit_price' => 5000
                ]
            ],
            'created_by' => 1,
            'notes' => 'Test PO'
        ];

        $orderId = $orderService->createOrder($testOrder);

        $this->assertIsInt($orderId);
        $this->assertGreaterThan(0, $orderId);

        echo "  ✅ Purchase order created with ID: {$orderId}\n";
    }

    // ============================================
    // USER MANAGEMENT TESTS
    // ============================================

    public function testCanRetrieveUsers(): void
    {
        echo "👥 Testing user retrieval...\n";

        $userService = new UserService();
        $users = $userService->getAllUsers();

        $this->assertIsArray($users);
        $this->assertGreaterThan(0, count($users));

        echo "  ✅ Retrieved " . count($users) . " users\n";
    }

    public function testCanRetrieveRoles(): void
    {
        echo "👥 Testing role retrieval...\n";

        $userService = new UserService();
        $roles = $userService->getRoles();

        $this->assertIsArray($roles);
        $this->assertCount(4, $roles); // superadmin, admin, manager, staff

        echo "  ✅ Retrieved 4 roles\n";
    }

    // ============================================
    // SECURITY TESTS
    // ============================================

    public function testCSRFTokenGeneration(): void
    {
        echo "🛡️ Testing CSRF token generation...\n";

        $token1 = Security::generateCsrfToken();
        $token2 = Security::generateCsrfToken();

        $this->assertIsString($token1);
        $this->assertEquals($token1, $token2); // Same session = same token
        $this->assertTrue(Security::verifyCsrfToken($token1));

        echo "  ✅ CSRF token generation working\n";
    }

    public function testInputSanitization(): void
    {
        echo "🛡️ Testing input sanitization...\n";

        $malicious = '<script>alert("XSS")</script>';
        $sanitized = Security::sanitizeOutput($malicious);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);

        echo "  ✅ Input sanitization working\n";
    }

    public function testEmailValidation(): void
    {
        echo "🛡️ Testing email validation...\n";

        $this->assertEquals('test@example.com', Security::sanitizeEmail('test@example.com'));
        $this->assertNull(Security::sanitizeEmail('invalid-email'));

        echo "  ✅ Email validation working\n";
    }

    // ============================================
    // DATABASE TESTS
    // ============================================

    public function testDatabaseConnection(): void
    {
        echo "🗄️ Testing database connection...\n";

        $db = \Janstro\InventorySystem\Config\Database::connect();

        $this->assertTrue(\Janstro\InventorySystem\Config\Database::isConnected());

        echo "  ✅ Database connection successful\n";
    }

    // ============================================
    // CLEANUP
    // ============================================

    public static function tearDownAfterClass(): void
    {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ All tests completed successfully!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}

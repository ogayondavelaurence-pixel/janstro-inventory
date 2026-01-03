<?php
require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Middleware\SecurityMiddleware;

$deleted = SecurityMiddleware::cleanupOldFiles();
error_log("🧹 Cleaned up {$deleted} rate limit files");

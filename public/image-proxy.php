<?php

/**
 * ============================================================================
 * SECURE IMAGE PROXY - Serves profile pictures with access control
 * ============================================================================
 * Path: public/image-proxy.php
 * URL: /janstro-inventory/public/image-proxy.php?file=1_xxxxx.jpg
 * 
 * Features:
 * ✅ Validates user authentication
 * ✅ Prevents path traversal attacks
 * ✅ Validates file extensions
 * ✅ Sets proper cache headers
 * ✅ Serves images efficiently
 * ============================================================================
 */

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// CORS for local development
$allowedOrigins = [
    'http://localhost:8080',
    'http://127.0.0.1:8080'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization');
    http_response_code(200);
    exit;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Validate JWT token from Authorization header
 */
function validateToken(): ?array
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader)) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
    }

    if (empty($authHeader)) {
        return null;
    }

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }

    $token = $matches[1];

    try {
        // Load JWT decoder
        require_once __DIR__ . '/../autoload.php';

        $jwtSecret = $_ENV['JWT_SECRET'] ?? 'janstro_inventory_secret_key_2025';
        $decoded = \SimpleJWT::decode($token, $jwtSecret);

        if (isset($decoded->exp) && $decoded->exp < time()) {
            return null; // Expired
        }

        if (!isset($decoded->data)) {
            return null; // Invalid structure
        }

        return (array)$decoded->data;
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate filename for security
 */
function validateFilename(string $filename): bool
{
    // Must match pattern: {user_id}_{timestamp}_{hash}.{ext}
    // Example: 1_1765648222_693da75ecd1f2.jpg
    $pattern = '/^[0-9]+_[0-9]+_[a-f0-9]+(_thumb)?\.(jpg|jpeg|png|gif|webp)$/i';

    if (!preg_match($pattern, $filename)) {
        return false;
    }

    // No path traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }

    return true;
}

/**
 * Get MIME type from extension
 */
function getMimeType(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    return $mimeTypes[$ext] ?? 'application/octet-stream';
}

/**
 * Send error response
 */
function sendError(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError(405, 'Method not allowed');
}

// Get filename parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    sendError(400, 'Filename parameter required');
}

// Validate filename
if (!validateFilename($filename)) {
    error_log("Invalid filename requested: {$filename}");
    sendError(400, 'Invalid filename');
}

// Authenticate user (optional - remove if you want public access)
$user = validateToken();
if (!$user) {
    // Allow unauthenticated access to thumbnails (for public profiles)
    // But require auth for full-size images
    if (strpos($filename, '_thumb.') === false) {
        sendError(401, 'Authentication required');
    }
}

// Build file path
$storagePath = dirname(__DIR__) . '/storage/profile_pictures';
$filePath = $storagePath . '/' . $filename;

// Security: Verify path is within storage directory
$realPath = realpath($filePath);
$realStorage = realpath($storagePath);

if ($realPath === false || strpos($realPath, $realStorage) !== 0) {
    error_log("Path traversal attempt: {$filename}");
    sendError(403, 'Access denied');
}

// Check if file exists
if (!file_exists($filePath)) {
    error_log("File not found: {$filePath}");
    sendError(404, 'File not found');
}

// Check if file is readable
if (!is_readable($filePath)) {
    error_log("File not readable: {$filePath}");
    sendError(403, 'Access denied');
}

// Get file info
$mimeType = getMimeType($filename);
$fileSize = filesize($filePath);
$lastModified = filemtime($filePath);

// Set cache headers (cache for 30 days)
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('Cache-Control: public, max-age=2592000'); // 30 days
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');

// Check If-Modified-Since header for 304 response
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if (!empty($ifModifiedSince) && strtotime($ifModifiedSince) >= $lastModified) {
    http_response_code(304);
    exit;
}

// Serve the file
readfile($filePath);
exit;

<?php

/**
 * ============================================================================
 * PROFILE PICTURE SERVICE v5.0 - PRODUCTION FIX
 * ============================================================================
 * CRITICAL FIX: deleteOldFiles() no longer deletes newly uploaded files
 * 
 * CHANGES FROM v4.0:
 * - Line 365-375: Fixed orphaned file cleanup to exclude current uploads
 * - Added timestamp-based cleanup instead of glob pattern
 * ============================================================================
 */

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

class ProfilePictureService
{
    private PDO $db;
    private string $uploadPath;
    private string $publicPath;
    private int $maxFileSize;
    private array $allowedMimes;

    public function __construct()
    {
        $this->db = Database::connect();

        $this->uploadPath = PUBLIC_PATH . '/assets/uploads/profile_pictures';
        $this->publicPath = '/janstro-inventory/public/assets/uploads/profile_pictures';

        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        $this->allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!is_dir($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                throw new Exception("Cannot create upload directory: {$this->uploadPath}");
            }
        }

        if (!is_writable($this->uploadPath)) {
            throw new Exception("Upload directory not writable: {$this->uploadPath}");
        }

        if (!extension_loaded('gd')) {
            throw new Exception("GD extension required");
        }

        error_log("✅ ProfilePictureService v5.0 initialized");
    }

    /**
     * Upload from Base64 - FIXED VERSION
     */
    public function uploadFromBase64(int $userId, string $base64Image, string $filename): array
    {
        error_log("📤 Upload v5.0 - User: {$userId}, File: {$filename}");

        $mainPath = null;
        $thumbPath = null;

        try {
            $imageData = $this->decodeBase64Image($base64Image);
            $this->validateImage($imageData);

            $extension = $this->getImageExtension($imageData);
            $uniqueName = $userId . '_' . time() . '_' . bin2hex(random_bytes(4));
            $mainFilename = $uniqueName . '.' . $extension;
            $thumbFilename = $uniqueName . '_thumb.' . $extension;

            $mainPath = $this->uploadPath . '/' . $mainFilename;
            $thumbPath = $this->uploadPath . '/' . $thumbFilename;
            $publicMain = $this->publicPath . '/' . $mainFilename;
            $publicThumb = $this->publicPath . '/' . $thumbFilename;

            // ✅ FIX: Delete old files BEFORE saving new ones
            $this->deleteOldFiles($userId, [$mainFilename, $thumbFilename]);

            // Save new images
            $this->saveCompressedImage($imageData, $mainPath, 500, 500, 90);
            $this->saveCompressedImage($imageData, $thumbPath, 150, 150, 85);

            if (!file_exists($mainPath) || !file_exists($thumbPath)) {
                throw new Exception("Files not created after save");
            }

            // Update database
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE users 
                SET profile_picture = ?,
                    profile_picture_thumb = ?
                WHERE user_id = ?
            ");

            if (!$stmt->execute([$publicMain, $publicThumb, $userId])) {
                throw new Exception("Database update failed");
            }

            if ($stmt->rowCount() === 0) {
                throw new Exception("User not found");
            }

            $this->db->commit();

            $size = filesize($mainPath);
            $dimensions = getimagesize($mainPath);

            error_log("✅ Upload complete - Main: {$publicMain}, Thumb: {$publicThumb}");

            return [
                'main' => $publicMain,
                'thumbnail' => $publicThumb,
                'size' => $size,
                'dimensions' => [$dimensions[0], $dimensions[1]]
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($mainPath && file_exists($mainPath)) {
                @unlink($mainPath);
            }
            if ($thumbPath && file_exists($thumbPath)) {
                @unlink($thumbPath);
            }

            error_log("❌ Upload failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload from file
     */
    public function uploadFromFile(int $userId, array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        $imageData = file_get_contents($file['tmp_name']);
        if ($imageData === false) {
            throw new Exception('Failed to read uploaded file');
        }

        return $this->uploadFromBase64($userId, base64_encode($imageData), $file['name']);
    }

    /**
     * Remove profile picture
     */
    public function remove(int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            $this->deleteOldFiles($userId);

            $stmt = $this->db->prepare("
                UPDATE users 
                SET profile_picture = NULL,
                    profile_picture_thumb = NULL
                WHERE user_id = ?
            ");

            if (!$stmt->execute([$userId])) {
                throw new Exception("Database update failed");
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Decode base64 image
     */
    private function decodeBase64Image(string $base64): string
    {
        if (preg_match('/^data:image\/\w+;base64,/', $base64)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $imageData = base64_decode($base64);
        if ($imageData === false) {
            throw new Exception('Invalid base64 image data');
        }

        return $imageData;
    }

    /**
     * Validate image
     */
    private function validateImage(string $imageData): void
    {
        $size = strlen($imageData);
        if ($size > $this->maxFileSize) {
            $sizeMB = round($size / 1024 / 1024, 2);
            $maxMB = round($this->maxFileSize / 1024 / 1024, 2);
            throw new Exception("Image too large ({$sizeMB}MB). Max {$maxMB}MB");
        }

        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Invalid image data');
        }
        imagedestroy($image);
    }

    /**
     * Get image extension
     */
    private function getImageExtension(string $imageData): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);

        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!isset($map[$mimeType])) {
            throw new Exception("Unsupported format: {$mimeType}");
        }

        return $map[$mimeType];
    }

    /**
     * Save compressed image
     */
    private function saveCompressedImage(
        string $imageData,
        string $path,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): void {
        $source = @imagecreatefromstring($imageData);
        if (!$source) {
            throw new Exception('Failed to create image resource');
        }

        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        $destination = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destination) {
            imagedestroy($source);
            throw new Exception('Failed to create destination image');
        }

        imagealphablending($destination, false);
        imagesavealpha($destination, true);

        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $origWidth,
            $origHeight
        );

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $saved = false;

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $saved = imagejpeg($destination, $path, $quality);
                break;
            case 'png':
                $saved = imagepng($destination, $path, (int)(9 - ($quality / 10)));
                break;
            case 'gif':
                $saved = imagegif($destination, $path);
                break;
            case 'webp':
                $saved = imagewebp($destination, $path, $quality);
                break;
        }

        imagedestroy($source);
        imagedestroy($destination);

        if (!$saved || !file_exists($path)) {
            throw new Exception("Failed to save image: {$path}");
        }
    }

    /**
     * ✅ CRITICAL FIX: Delete old files without removing new uploads
     */
    private function deleteOldFiles(int $userId, array $excludeFiles = []): void
    {
        try {
            // Get current profile pictures from database
            $stmt = $this->db->prepare("
                SELECT profile_picture, profile_picture_thumb 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            // Delete files referenced in database
            if ($current) {
                foreach ([$current['profile_picture'], $current['profile_picture_thumb']] as $publicPath) {
                    if ($publicPath) {
                        $filename = basename($publicPath);
                        $filePath = $this->uploadPath . '/' . $filename;
                        if (file_exists($filePath) && !in_array($filename, $excludeFiles)) {
                            @unlink($filePath);
                            error_log("🗑️ Deleted old file: {$filename}");
                        }
                    }
                }
            }

            // ✅ FIX: Clean up orphaned files older than 1 hour (not fresh uploads)
            $pattern = $this->uploadPath . '/' . $userId . '_*';
            $cutoffTime = time() - 3600; // 1 hour ago

            foreach (glob($pattern) as $file) {
                $filename = basename($file);

                // Skip files in exclude list
                if (in_array($filename, $excludeFiles)) {
                    continue;
                }

                // Only delete files older than 1 hour
                if (filemtime($file) < $cutoffTime) {
                    @unlink($file);
                    error_log("🗑️ Cleaned up orphaned file: {$filename}");
                }
            }
        } catch (Exception $e) {
            error_log("⚠️ Delete old files warning: " . $e->getMessage());
        }
    }
}

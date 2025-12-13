<?php

/**
 * ============================================================================
 * PROFILE PICTURE SERVICE - PRODUCTION FIXED v3.0
 * ============================================================================
 * Path: src/Services/ProfilePictureService.php
 * 
 * CRITICAL FIXES APPLIED:
 * ✅ Atomic operations: Save files → Verify → Update database
 * ✅ Transaction support with rollback on failure
 * ✅ Cleanup partial uploads on error
 * ✅ Comprehensive error logging at each step
 * ✅ Path validation before database commit
 * ✅ Better error messages for debugging
 * 
 * CHANGELOG v3.0:
 * - Fixed: Database updated before files saved (CRITICAL BUG)
 * - Added: Database transaction support
 * - Added: File existence verification before DB commit
 * - Added: Cleanup on failure (delete partial uploads)
 * - Added: More detailed error logging
 * - Fixed: Silent failures now throw exceptions
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

        // Configure paths
        $baseDir = dirname(__DIR__, 2); // Go up 2 levels from Services
        $this->uploadPath = $baseDir . '/storage/profile_pictures';
        $this->publicPath = '/janstro-inventory/storage/profile_pictures';
        $this->maxFileSize = 2 * 1024 * 1024; // 2MB
        $this->allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // ✅ Auto-create directory
        if (!is_dir($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                error_log("❌ CRITICAL: Cannot create directory: {$this->uploadPath}");
                throw new Exception("Storage directory cannot be created. Check permissions.");
            }
            error_log("✅ Created directory: {$this->uploadPath}");
        }

        // ✅ Verify writable
        if (!is_writable($this->uploadPath)) {
            error_log("❌ CRITICAL: Directory not writable: {$this->uploadPath}");
            throw new Exception("Storage directory is not writable. Check permissions.");
        }

        // ✅ Verify GD extension
        if (!extension_loaded('gd')) {
            error_log("❌ CRITICAL: GD extension not loaded");
            throw new Exception("GD extension required but not enabled in PHP.");
        }

        error_log("✅ ProfilePictureService initialized - Upload path: {$this->uploadPath}");
    }

    /**
     * ========================================================================
     * UPLOAD FROM BASE64 - FIXED VERSION WITH ATOMIC OPERATIONS
     * ========================================================================
     * 
     * CRITICAL FIX: Operations now happen in correct order:
     * 1. Decode and validate image
     * 2. Save main image to disk
     * 3. Save thumbnail to disk
     * 4. Verify both files exist
     * 5. Delete old files
     * 6. Update database (ONLY if all above succeed)
     * 7. Rollback on any failure
     * ========================================================================
     */
    public function uploadFromBase64(int $userId, string $base64Image, string $filename): array
    {
        error_log("================================");
        error_log("📤 UPLOAD STARTED");
        error_log("User ID: {$userId}");
        error_log("Filename: {$filename}");
        error_log("================================");

        $mainPath = null;
        $thumbPath = null;
        $publicMain = null;
        $publicThumb = null;

        try {
            // ========================================================================
            // STEP 1: DECODE AND VALIDATE IMAGE
            // ========================================================================
            error_log("STEP 1: Decoding base64...");
            $imageData = $this->decodeBase64Image($base64Image);
            error_log("✅ Base64 decoded - Size: " . strlen($imageData) . " bytes");

            error_log("STEP 2: Validating image...");
            $this->validateImage($imageData);
            error_log("✅ Image validated");

            // ========================================================================
            // STEP 3: GENERATE FILENAMES
            // ========================================================================
            error_log("STEP 3: Generating filenames...");
            $extension = $this->getImageExtension($imageData);
            $uniqueName = $userId . '_' . time() . '_' . uniqid();
            $mainFilename = $uniqueName . '.' . $extension;
            $thumbFilename = $uniqueName . '_thumb.' . $extension;

            $mainPath = $this->uploadPath . '/' . $mainFilename;
            $thumbPath = $this->uploadPath . '/' . $thumbFilename;
            $publicMain = $this->publicPath . '/' . $mainFilename;
            $publicThumb = $this->publicPath . '/' . $thumbFilename;

            error_log("✅ Filenames generated:");
            error_log("   Main: {$mainFilename}");
            error_log("   Thumb: {$thumbFilename}");
            error_log("   Full path: {$mainPath}");

            // ========================================================================
            // STEP 4: SAVE MAIN IMAGE
            // ========================================================================
            error_log("STEP 4: Saving main image...");
            $this->saveCompressedImage($imageData, $mainPath, 800, 800, 85);

            // ✅ CRITICAL: Verify file exists immediately
            if (!file_exists($mainPath)) {
                throw new Exception("Main image file was not created: {$mainPath}");
            }

            $mainSize = filesize($mainPath);
            error_log("✅ Main image saved successfully - Size: {$mainSize} bytes");

            // ========================================================================
            // STEP 5: SAVE THUMBNAIL
            // ========================================================================
            error_log("STEP 5: Saving thumbnail...");
            $this->saveCompressedImage($imageData, $thumbPath, 150, 150, 90);

            // ✅ CRITICAL: Verify file exists immediately
            if (!file_exists($thumbPath)) {
                throw new Exception("Thumbnail file was not created: {$thumbPath}");
            }

            $thumbSize = filesize($thumbPath);
            error_log("✅ Thumbnail saved successfully - Size: {$thumbSize} bytes");

            // ========================================================================
            // STEP 6: VERIFY BOTH FILES BEFORE DATABASE UPDATE
            // ========================================================================
            error_log("STEP 6: Final verification before database update...");

            if (!is_readable($mainPath)) {
                throw new Exception("Main image not readable: {$mainPath}");
            }

            if (!is_readable($thumbPath)) {
                throw new Exception("Thumbnail not readable: {$thumbPath}");
            }

            error_log("✅ Both files verified and readable");

            // ========================================================================
            // STEP 7: DELETE OLD FILES
            // ========================================================================
            error_log("STEP 7: Deleting old files...");
            $this->deleteOldFiles($userId);
            error_log("✅ Old files deleted");

            // ========================================================================
            // STEP 8: UPDATE DATABASE (TRANSACTION)
            // ========================================================================
            error_log("STEP 8: Updating database...");

            try {
                $this->db->beginTransaction();

                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET profile_picture = ?,
                        profile_picture_thumb = ?
                    WHERE user_id = ?
                ");

                if (!$stmt->execute([$publicMain, $publicThumb, $userId])) {
                    throw new Exception("Database execute failed");
                }

                if ($stmt->rowCount() === 0) {
                    throw new Exception("No rows updated - user may not exist");
                }

                $this->db->commit();
                error_log("✅ Database updated successfully");
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("❌ Database update failed: " . $e->getMessage());

                // ✅ CRITICAL: Cleanup uploaded files on database failure
                if (file_exists($mainPath)) {
                    @unlink($mainPath);
                    error_log("🗑️ Cleaned up main file after DB failure");
                }
                if (file_exists($thumbPath)) {
                    @unlink($thumbPath);
                    error_log("🗑️ Cleaned up thumbnail after DB failure");
                }

                throw new Exception("Database update failed: " . $e->getMessage());
            }

            // ========================================================================
            // STEP 9: FINAL VERIFICATION
            // ========================================================================
            error_log("STEP 9: Final verification...");

            // Verify files still exist after all operations
            if (!file_exists($mainPath) || !file_exists($thumbPath)) {
                throw new Exception("Files missing after upload completion");
            }

            // Get image dimensions
            $dimensions = @getimagesize($mainPath);

            error_log("================================");
            error_log("✅ UPLOAD SUCCESSFUL");
            error_log("Main: {$publicMain}");
            error_log("Thumb: {$publicThumb}");
            error_log("Size: {$mainSize} bytes");
            if ($dimensions) {
                error_log("Dimensions: {$dimensions[0]}x{$dimensions[1]}");
            }
            error_log("================================");

            return [
                'main' => $publicMain,
                'thumbnail' => $publicThumb,
                'size' => $mainSize,
                'dimensions' => $dimensions ?: [0, 0]
            ];
        } catch (Exception $e) {
            error_log("================================");
            error_log("❌ UPLOAD FAILED");
            error_log("Error: " . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Stack trace:");
            error_log($e->getTraceAsString());
            error_log("================================");

            // ✅ CRITICAL: Cleanup on any failure
            if ($mainPath && file_exists($mainPath)) {
                @unlink($mainPath);
                error_log("🗑️ Cleaned up main file after error");
            }
            if ($thumbPath && file_exists($thumbPath)) {
                @unlink($thumbPath);
                error_log("🗑️ Cleaned up thumbnail after error");
            }

            throw $e;
        }
    }

    /**
     * Upload from file (multipart/form-data)
     */
    public function uploadFromFile(int $userId, array $file): array
    {
        error_log("📤 Upload from file - User: {$userId}, File: {$file['name']}");

        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        // Read file contents
        $imageData = file_get_contents($file['tmp_name']);

        if ($imageData === false) {
            throw new Exception('Failed to read uploaded file');
        }

        // Use base64 upload method
        return $this->uploadFromBase64($userId, base64_encode($imageData), $file['name']);
    }

    /**
     * Remove profile picture
     */
    public function remove(int $userId): bool
    {
        error_log("================================");
        error_log("🗑️ REMOVE PROFILE PICTURE");
        error_log("User ID: {$userId}");
        error_log("================================");

        try {
            // Start transaction
            $this->db->beginTransaction();

            // Delete physical files FIRST
            $this->deleteOldFiles($userId);

            // Clear database
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

            error_log("✅ Profile picture removed successfully");
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("❌ Remove failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Decode base64 image data
     */
    private function decodeBase64Image(string $base64): string
    {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $imageData = base64_decode($base64);

        if ($imageData === false) {
            throw new Exception('Invalid base64 image data');
        }

        return $imageData;
    }

    /**
     * Validate image data
     */
    private function validateImage(string $imageData): void
    {
        // Check size
        $size = strlen($imageData);
        if ($size > $this->maxFileSize) {
            $sizeMB = round($size / 1024 / 1024, 2);
            $maxMB = round($this->maxFileSize / 1024 / 1024, 2);
            throw new Exception("Image too large ({$sizeMB}MB). Maximum {$maxMB}MB allowed.");
        }

        // Validate as actual image
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            throw new Exception('Invalid image data - not a valid image file');
        }
        imagedestroy($image);
    }

    /**
     * Get image extension from image data
     */
    private function getImageExtension(string $imageData): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);

        return $this->getExtensionFromMime($mimeType);
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!isset($map[$mimeType])) {
            throw new Exception("Unsupported image format: {$mimeType}");
        }

        return $map[$mimeType];
    }

    /**
     * Save compressed and resized image
     */
    private function saveCompressedImage(
        string $imageData,
        string $path,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): void {
        error_log("   🖼️ Processing image...");
        error_log("   Target: {$path}");
        error_log("   Max: {$maxWidth}x{$maxHeight}, Quality: {$quality}%");

        // Create image resource
        $source = @imagecreatefromstring($imageData);
        if (!$source) {
            throw new Exception('Failed to create image resource from data');
        }

        // Get original dimensions
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);
        error_log("   Original: {$origWidth}x{$origHeight}");

        // Calculate new dimensions (maintain aspect ratio)
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        error_log("   New: {$newWidth}x{$newHeight}");

        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destination) {
            imagedestroy($source);
            throw new Exception('Failed to create destination image');
        }

        // Preserve transparency
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);

        // Resize
        if (!imagecopyresampled(
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
        )) {
            imagedestroy($source);
            imagedestroy($destination);
            throw new Exception('Image resampling failed');
        }

        // Save based on extension
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
            default:
                imagedestroy($source);
                imagedestroy($destination);
                throw new Exception("Unsupported image format: {$extension}");
        }

        // Cleanup
        imagedestroy($source);
        imagedestroy($destination);

        if (!$saved) {
            throw new Exception("Failed to save image to: {$path}");
        }

        // ✅ CRITICAL: Verify file was actually created
        if (!file_exists($path)) {
            throw new Exception("File save reported success but file doesn't exist: {$path}");
        }

        $filesize = filesize($path);
        error_log("   ✅ Image saved - Size: {$filesize} bytes");
    }

    /**
     * Delete old profile picture files for user
     */
    private function deleteOldFiles(int $userId): void
    {
        error_log("   🗑️ Deleting old files for user: {$userId}");

        try {
            // Get current files from database
            $stmt = $this->db->prepare("
                SELECT profile_picture, profile_picture_thumb 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $deletedCount = 0;

            if ($current) {
                $files = [
                    $current['profile_picture'],
                    $current['profile_picture_thumb']
                ];

                foreach ($files as $publicPath) {
                    if ($publicPath) {
                        $filename = basename($publicPath);
                        $filePath = $this->uploadPath . '/' . $filename;

                        if (file_exists($filePath)) {
                            if (@unlink($filePath)) {
                                $deletedCount++;
                                error_log("   ✅ Deleted: {$filePath}");
                            } else {
                                error_log("   ⚠️ Failed to delete: {$filePath}");
                            }
                        }
                    }
                }
            }

            // Also cleanup orphaned files
            $pattern = $this->uploadPath . '/' . $userId . '_*';
            $orphans = glob($pattern);

            if ($orphans) {
                foreach ($orphans as $file) {
                    if (@unlink($file)) {
                        $deletedCount++;
                        error_log("   ✅ Deleted orphan: {$file}");
                    }
                }
            }

            error_log("   ✅ Deleted {$deletedCount} old files");
        } catch (Exception $e) {
            error_log("   ⚠️ Delete old files warning: " . $e->getMessage());
            // Don't throw - this is cleanup, not critical
        }
    }
}

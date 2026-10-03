<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ProfileCompletionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhotoApiController extends Controller
{
    use ProfileCompletionTrait;

    // ✅ FIX: The constructor previously called `$this->middleware('auth:api')`,
    // which doesn't exist in Laravel 11/12. The routes in routes/api.php
    // already have `auth:sanctum`, so we don't need to add middleware here.
    // If you ever register these routes without middleware, add it in
    // bootstrap/app.php or the route group instead.

    // ==================== SELF ====================

    /**
     * GET /api/v1/profile/photo
     *
     * ✅ FIX: Streams the actual image bytes instead of returning JSON metadata.
     * Flutter's `Image.network()` expects binary content, not a JSON payload.
     * Returns 404 with JSON when the user has no photo.
     */
    public function show()
    {
        $user = Auth::user();

        if (!$user->photo) {
            return response()->json([
                'success' => false,
                'message' => 'No profile photo set.',
            ], 404);
        }

        return $this->servePhoto($user->photo, $user);
    }

    /**
     * POST /api/v1/profile/photo
     * Upload/update current user's profile photo with optimization
     */
    public function upload(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'photo.required' => 'Please select a photo to upload',
            'photo.image' => 'The file must be an image',
            'photo.mimes' => 'The image must be a JPEG, PNG, JPG, GIF, or WEBP file',
            'photo.max' => 'The image size must not exceed 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;

            if ($oldPhoto) {
                $this->deletePhotoFile($oldPhoto);
            }

            $photoPath = $this->handlePhotoUpload($request->file('photo'), true);

            $user->photo = $photoPath;
            $user->save();

            $this->updateProfileCompletion($user);

            DB::commit();

            $user->refresh();
            $profileCompletion = $this->calculateProfileCompletion($user);

            Log::info("API: Profile photo uploaded successfully", [
                'user_id' => $user->id,
                'photo_path' => $photoPath,
                'old_photo' => $oldPhoto,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully!',
                'data' => [
                    'photo' => $user->photo,
                    // ✅ FIX: point at the API route that streams the file
                    'photo_url' => url('/api/v1/profile/photo'),
                    // Keep the storage URL for cases where Apache serves it directly
                    'storage_url' => Storage::disk('public')->url('users/photos/' . $user->photo),
                    'avatar_url' => $user->avatar_url,
                    'profile_completion' => $profileCompletion,
                    'has_photo' => true,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to upload profile photo: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString(),
                'file_name' => $request->file('photo')?->getClientOriginalName(),
                'file_size' => $request->file('photo')?->getSize(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/profile/photo
     * Remove current user's profile photo
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();

        Log::info('API: Remove profile photo request received', [
            'user_id' => $user->id,
            'has_photo' => !is_null($user->photo),
            'ip' => $request->ip(),
        ]);

        if (!$user->photo) {
            return response()->json([
                'success' => false,
                'message' => 'No profile photo to remove.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;

            $deleted = $this->deletePhotoFile($oldPhoto);

            if (!$deleted) {
                throw new \Exception('Failed to delete photo file from storage');
            }

            $user->photo = null;
            $user->save();

            $this->updateProfileCompletion($user);

            DB::commit();

            $user->refresh();
            $profileCompletion = $this->calculateProfileCompletion($user);

            Log::info("API: Profile photo removed successfully", [
                'user_id' => $user->id,
                'removed_photo' => $oldPhoto,
                'profile_completion' => $profileCompletion,
                'ip' => $request->ip(),
            ]);

            $defaultAvatar = asset('images/default-avatar.png');

            return response()->json([
                'success' => true,
                'message' => 'Profile photo removed successfully!',
                'data' => [
                    'has_photo' => false,
                    'photo_url' => $defaultAvatar,
                    'avatar_url' => $defaultAvatar,
                    'photo' => null,
                    'profile_completion' => $profileCompletion,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Failed to remove profile photo: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString(),
                'photo_path' => $user->photo ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove profile photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== ADMIN ====================

    /**
     * POST /api/v1/profile/photo/admin/users/{userId}
     * Admin: Upload photo for a specific user (Super Admin only)
     */
    public function adminUpload(Request $request, $userId)
    {
        $admin = Auth::user();

        if (!$admin->isSuperAdmin()) {
            Log::warning('API: Unauthorized photo upload attempt', [
                'attempted_by' => $admin->id,
                'target_user' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super admin access required to manage user photos.',
            ], 403);
        }

        try {
            $user = User::findOrFail($userId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ], [
            'photo.required' => 'Please select a photo to upload',
            'photo.image' => 'The file must be an image',
            'photo.mimes' => 'The image must be a JPEG, PNG, JPG, GIF, or WEBP file',
            'photo.max' => 'The image size must not exceed 5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($user->photo) {
                $this->deletePhotoFile($user->photo);
            }

            $photoPath = $this->handlePhotoUpload($request->file('photo'), true);

            $user->photo = $photoPath;
            $user->save();

            $this->updateProfileCompletion($user);

            DB::commit();

            $user->refresh();

            Log::info("API: Admin updated user photo successfully", [
                'admin_id' => $admin->id,
                'target_user_id' => $user->id,
                'photo_path' => $photoPath,
                'target_user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User profile photo updated successfully!',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    // ✅ FIX: point at the admin streaming route
                    'photo_url' => url("/api/v1/profile/photo/admin/users/{$user->id}"),
                    'storage_url' => Storage::disk('public')->url('users/photos/' . $user->photo),
                    'avatar_url' => $user->avatar_url,
                    'profile_completion' => $this->calculateProfileCompletion($user),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Admin failed to upload user photo: ' . $e->getMessage(), [
                'admin_id' => $admin->id,
                'target_user_id' => $userId,
                'error_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/profile/photo/admin/users/{userId}
     * Admin: Remove photo for a specific user (Super Admin only)
     */
    public function adminDestroy(Request $request, $userId)
    {
        $admin = Auth::user();

        if (!$admin->isSuperAdmin()) {
            Log::warning('API: Unauthorized photo removal attempt', [
                'attempted_by' => $admin->id,
                'target_user' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super admin access required to manage user photos.',
            ], 403);
        }

        try {
            $user = User::findOrFail($userId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->photo) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have a profile photo to remove.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;
            $deleted = $this->deletePhotoFile($oldPhoto);

            if (!$deleted) {
                throw new \Exception('Failed to delete photo file from storage');
            }

            $user->photo = null;
            $user->save();

            $this->updateProfileCompletion($user);

            DB::commit();

            $user->refresh();

            Log::info("API: Admin removed user photo successfully", [
                'admin_id' => $admin->id,
                'target_user_id' => $user->id,
                'removed_photo' => $oldPhoto,
                'target_user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            $defaultAvatar = asset('images/default-avatar.png');

            return response()->json([
                'success' => true,
                'message' => 'User profile photo removed successfully!',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'avatar_url' => $defaultAvatar,
                    'has_photo' => false,
                    'profile_completion' => $this->calculateProfileCompletion($user),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Admin failed to remove user photo: ' . $e->getMessage(), [
                'admin_id' => $admin->id,
                'target_user_id' => $userId,
                'error_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/profile/photo/admin/users/{userId}
     *
     * ✅ FIX: Streams the actual image bytes.
     * Super admin only.
     */
    public function adminShow(Request $request, $userId)
    {
        $admin = Auth::user();

        if (!$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        try {
            $user = User::findOrFail($userId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->photo) {
            return response()->json([
                'success' => false,
                'message' => 'This user has no profile photo.',
            ], 404);
        }

        return $this->servePhoto($user->photo, $user);
    }

    /**
     * GET /api/v1/profile/photo/admin/permissions
     */
    public function checkPermissions()
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => [
                'can_manage_photos' => $isSuperAdmin,
                'is_super_admin' => $isSuperAdmin,
                'user_id' => $user?->id,
                'user_type' => $user?->type_name,
            ],
        ]);
    }

    /**
     * POST /api/v1/profile/photo/upload-url
     */
    public function getUploadUrl(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'mime_type' => 'required|string|in:image/jpeg,image/png,image/jpg,image/gif,image/webp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mime type',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $extension = explode('/', $request->mime_type)[1];
            $filename = 'user_' . $this->generateUniqueId() . '.' . $extension;

            $uploadUrl = url('/api/v1/profile/photo');

            Log::info("API: Generated upload URL", [
                'user_id' => $user->id,
                'filename' => $filename,
                'mime_type' => $request->mime_type,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'upload_url' => $uploadUrl,
                    'method' => 'POST',
                    'field' => 'photo',
                    'filename' => $filename,
                    'expires_in' => 3600,
                    'max_file_size' => 5 * 1024 * 1024,
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('API: Failed to generate upload URL: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate upload URL',
            ], 500);
        }
    }

    /**
     * POST /api/v1/profile/photo/batch
     */
    public function batchUpload(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'type' => 'required|string|in:property,unit,tenant,document',
            'related_id' => 'required|integer',
        ], [
            'photos.max' => 'Maximum 10 photos can be uploaded at once',
            'photos.*.max' => 'Each photo must not exceed 5MB',
            'photos.*.mimes' => 'Each photo must be a JPEG, PNG, JPG, GIF, or WEBP file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $uploadedPhotos = [];
            $failedUploads = [];

            foreach ($request->file('photos') as $index => $photo) {
                try {
                    $photoPath = $this->handlePhotoUpload($photo, true);
                    $uploadedPhotos[] = [
                        'index' => $index,
                        'filename' => $photoPath,
                        'url' => Storage::disk('public')->url('users/photos/' . $photoPath),
                        'type' => $request->type,
                        'related_id' => $request->related_id,
                        'uploaded_at' => now()->toISOString(),
                    ];
                } catch (\Exception $e) {
                    $failedUploads[] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'original_name' => $photo->getClientOriginalName(),
                    ];
                }
            }

            DB::commit();

            Log::info("API: Batch photos uploaded", [
                'user_id' => $user->id,
                'success_count' => count($uploadedPhotos),
                'failed_count' => count($failedUploads),
                'type' => $request->type,
                'related_id' => $request->related_id,
                'ip' => $request->ip(),
            ]);

            $responseData = [
                'success' => true,
                'message' => count($uploadedPhotos) . ' photos uploaded successfully',
                'data' => [
                    'photos' => $uploadedPhotos,
                    'total_uploaded' => count($uploadedPhotos),
                ],
            ];

            if (!empty($failedUploads)) {
                $responseData['warnings'] = [
                    'failed_uploads' => $failedUploads,
                    'failed_count' => count($failedUploads),
                    'message' => count($failedUploads) . ' photos failed to upload',
                ];
            }

            return response()->json($responseData);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('API: Batch upload failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString(),
                'type' => $request->type,
                'related_id' => $request->related_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photos: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * ✅ NEW: Stream a stored photo as the HTTP response.
     *
     * Returns the file bytes with the correct Content-Type.
     * HandleCors (registered globally in bootstrap/app.php) adds the
     * Access-Control-* headers, so Flutter web can load this cross-origin.
     *
     * If the file is missing on disk, return 404 JSON.
     */
    private function servePhoto(string $photoFilename, User $user)
    {
        $fullPath = 'users/photos/' . $photoFilename;

        if (!Storage::disk('public')->exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Photo file not found on disk.',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($fullPath);
        $mimeType = Storage::disk('public')->mimeType($fullPath) ?: 'image/jpeg';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Photo-User-Id' => $user->id,
        ]);
    }

    /**
     * Handle photo upload with optimization.
     */
    private function handlePhotoUpload($photoFile, bool $optimize = true): string
    {
        try {
            if (!$photoFile || !$photoFile->isValid()) {
                throw new \Exception('Invalid photo file provided.');
            }

            $disk = 'public';
            $directory = 'users/photos';

            if (!Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->makeDirectory($directory, 0755, true);
            }

            $extension = strtolower($photoFile->getClientOriginalExtension());
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image format.');
            }

            $filename = 'user_' . $this->generateUniqueId() . '.' . $extension;
            $fullPath = $directory . '/' . $filename;

            if ($optimize && $this->isGdAvailable()) {
                $this->processAndStoreImageWithGD($photoFile, $disk, $fullPath);
            } else {
                Storage::disk($disk)->putFileAs($directory, $photoFile, $filename);
            }

            if (!Storage::disk($disk)->exists($fullPath)) {
                throw new \Exception('Failed to store photo file after upload.');
            }

            $fileSize = Storage::disk($disk)->size($fullPath);
            if ($fileSize === 0) {
                Storage::disk($disk)->delete($fullPath);
                throw new \Exception('Uploaded file is empty or corrupted.');
            }

            Log::debug("API: Photo uploaded and verified successfully", [
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $fullPath,
                'disk' => $disk,
                'file_size' => $fileSize,
                'optimized' => $optimize,
                'gd_available' => $this->isGdAvailable(),
            ]);

            return $filename;
        } catch (\Exception $e) {
            Log::error('API: Photo upload failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'original_name' => $photoFile->getClientOriginalName(),
                'file_size' => $photoFile->getSize(),
                'mime_type' => $photoFile->getMimeType(),
            ]);
            throw new \Exception('Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Process and store optimized image using GD library.
     */
    private function processAndStoreImageWithGD($photoFile, string $disk, string $fullPath): void
    {
        try {
            $tempPath = $photoFile->getPathname();
            $imageInfo = getimagesize($tempPath);

            if (!$imageInfo) {
                throw new \Exception('Could not read image file.');
            }

            list($originalWidth, $originalHeight, $imageType) = $imageInfo;

            $maxWidth = 800;
            $maxHeight = 800;

            if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
                Storage::disk($disk)->put($fullPath, file_get_contents($tempPath));
                return;
            }

            $ratio = $originalWidth / $originalHeight;
            if ($maxWidth / $maxHeight > $ratio) {
                $newWidth = $maxHeight * $ratio;
                $newHeight = $maxHeight;
            } else {
                $newWidth = $maxWidth;
                $newHeight = $maxWidth / $ratio;
            }

            $newWidth = (int) round($newWidth);
            $newHeight = (int) round($newHeight);

            $sourceImage = $this->createImageResource($tempPath, $imageType);

            if (!$sourceImage) {
                throw new \Exception('Could not create image resource.');
            }

            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
            }

            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            $this->saveOptimizedImage($newImage, $fullPath, $disk, $imageType);

            imagedestroy($sourceImage);
            imagedestroy($newImage);

            Log::debug("API: Image resized from {$originalWidth}x{$originalHeight} to {$newWidth}x{$newHeight}");
        } catch (\Exception $e) {
            Log::error('API: GD image processing failed: ' . $e->getMessage());
            Storage::disk($disk)->put($fullPath, file_get_contents($photoFile->getPathname()));
        }
    }

    private function createImageResource(string $path, int $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($path);
                }
                return null;
            default:
                return null;
        }
    }

    private function saveOptimizedImage($image, string $fullPath, string $disk, int $imageType): void
    {
        $fullSystemPath = Storage::disk($disk)->path($fullPath);

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($image, $fullSystemPath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($image, $fullSystemPath, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($image, $fullSystemPath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    imagewebp($image, $fullSystemPath, 85);
                } else {
                    imagejpeg($image, $fullSystemPath, 85);
                }
                break;
            default:
                imagejpeg($image, $fullSystemPath, 85);
        }
    }

    private function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    private function generateUniqueId(): string
    {
        return time() . '_' . Str::random(10) . '_' . Auth::id();
    }

    private function deletePhotoFile(string $photoPath): bool
    {
        if (!$photoPath) {
            return true;
        }

        try {
            $disk = 'public';
            $directory = 'users/photos';
            $fullPath = $directory . '/' . $photoPath;

            if (Storage::disk($disk)->exists($fullPath)) {
                $deleted = Storage::disk($disk)->delete($fullPath);

                if ($deleted) {
                    Log::debug("API: Photo deleted successfully from storage", [
                        'photo_path' => $photoPath,
                        'full_path' => $fullPath,
                    ]);
                    return true;
                } else {
                    Log::warning("API: Photo file exists but could not be deleted", [
                        'photo_path' => $photoPath,
                    ]);
                    return false;
                }
            } else {
                Log::debug("API: Photo file not found in storage (may have been already deleted)", [
                    'photo_path' => $photoPath,
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::error('API: Failed to delete photo from storage: ' . $e->getMessage(), [
                'photo_path' => $photoPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
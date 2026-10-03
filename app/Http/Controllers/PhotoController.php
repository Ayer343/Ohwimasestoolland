<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ProfileCompletionTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    use ProfileCompletionTrait;

    /**
     * Update current user's profile photo
     */
    public function updateProfilePhoto(Request $request)
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
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $oldPhoto = $user->photo;
            
            // Delete old photo if exists
            if ($oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            // Upload and optimize new photo
            $photoPath = $this->handlePhotoUpload($request->file('photo'));
            
            // Update user with new photo path
            $user->photo = $photoPath;
            $user->save();

            // Update profile completion stats - NOW USING TRAIT METHOD
            $this->updateProfileCompletion($user);

            DB::commit();

            // Refresh user to get updated photo URLs
            $user->refresh();

            // Get updated profile completion using trait method
            $profileCompletion = $this->calculateProfileCompletion($user);

            // Log photo update
            Log::info("Profile photo updated successfully", [
                'user_id' => $user->id,
                'photo_path' => $photoPath,
                'old_photo' => $oldPhoto,
                'new_photo_url' => $user->photo_url,
                'new_avatar_url' => $user->avatar_url
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile photo updated successfully!',
                    'photo_url' => $user->photo_url,
                    'avatar_url' => $user->avatar_url,
                    'profile_completion' => $profileCompletion,
                    'user' => [
                        'photo' => $user->photo,
                        'photo_url' => $user->photo_url,
                        'avatar_url' => $user->avatar_url,
                    ]
                ]);
            }

            return back()->with('success', 'Profile photo updated successfully!')
                        ->with('photo_updated', true);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update profile photo for user ' . $user->id . ': ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString(),
                'file_name' => $request->file('photo')?->getClientOriginalName(),
                'file_size' => $request->file('photo')?->getSize()
            ]);

            $errorMessage = 'Failed to update profile photo: ' . $e->getMessage();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }

   /**
 * Remove current user's profile photo
 */
public function removeProfilePhoto(Request $request)
{
    $user = Auth::user();

    // Log the request for debugging
    Log::info('Remove profile photo request received', [
        'user_id' => $user->id,
        'has_photo' => !is_null($user->photo),
        'request_method' => $request->method(),
        'expects_json' => $request->expectsJson(),
        'ajax' => $request->ajax(),
        'headers' => $request->headers->all()
    ]);

    if (!$user->photo) {
        $message = 'No profile photo to remove.';
        Log::warning('Attempt to remove non-existent photo', ['user_id' => $user->id]);
        
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 400);
        }
        
        return back()->with('info', $message);
    }

    DB::beginTransaction();

    try {
        $oldPhoto = $user->photo;
        Log::info('Attempting to delete photo', ['user_id' => $user->id, 'photo_path' => $oldPhoto]);
        
        $deleted = $this->deletePhoto($oldPhoto);
        
        if (!$deleted) {
            throw new \Exception('Failed to delete photo file from storage');
        }
        
        // Update user record
        $user->photo = null;
        $user->save();

        // Update profile completion stats
        $this->updateProfileCompletion($user);

        DB::commit();

        // Refresh user to get updated URLs
        $user->refresh();

        // Get updated profile completion
        $profileCompletion = $this->calculateProfileCompletion($user);

        // Log successful removal
        Log::info("Profile photo removed successfully", [
            'user_id' => $user->id,
            'removed_photo' => $oldPhoto,
            'profile_completion' => $profileCompletion
        ]);

        $defaultAvatar = asset('images/default-avatar.png');
        
        $responseData = [
            'success' => true,
            'message' => 'Profile photo removed successfully!',
            'photo' => null,
            'photo_url' => $defaultAvatar,
            'avatar_url' => $defaultAvatar,
            'profile_completion' => $profileCompletion,
            'user' => [
                'photo' => null,
                'photo_url' => $defaultAvatar,
                'avatar_url' => $defaultAvatar,
            ]
        ];

        Log::info('Sending success response', ['response' => $responseData]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($responseData);
        }

        return back()->with('success', 'Profile photo removed successfully!');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to remove profile photo for user ' . $user->id . ': ' . $e->getMessage(), [
            'error_trace' => $e->getTraceAsString(),
            'photo_path' => $user->photo ?? null
        ]);

        $errorMessage = 'Failed to remove profile photo: ' . $e->getMessage();
        
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }

        return back()->with('error', $errorMessage);
    }
}

    /**
     * Update user photo only (admin)
     */
    public function updatePhoto(Request $request, $id)
    {
        try {
            // Enhanced authorization check
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Only super admin can access - with better error message
            if (!auth()->user()->isSuperAdmin()) {
                Log::warning('Unauthorized photo update attempt', [
                    'attempted_by' => auth()->id(),
                    'target_user' => $id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action. Super admin access required to manage user photos.'
                ], 403);
            }

            $user = User::findOrFail($id);

            // Validate request
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
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Delete old photo if exists
                if ($user->photo) {
                    $this->deletePhoto($user->photo);
                }

                // Upload new photo
                $photoPath = $this->handlePhotoUpload($request->file('photo'));
                
                $user->photo = $photoPath;
                $user->save();

                // Update profile completion for the target user - NOW USING TRAIT METHOD
                $this->updateProfileCompletion($user);

                DB::commit();

                // Refresh user to get updated URLs
                $user->refresh();

                // Log successful update
                Log::info("Admin updated user photo successfully", [
                    'admin_id' => auth()->id(),
                    'target_user_id' => $user->id,
                    'photo_path' => $photoPath,
                    'target_user_email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Profile photo updated successfully!',
                    'photo_url' => $user->photo_url,
                    'avatar_url' => $user->avatar_url,
                    'user' => [
                        'photo' => $user->photo,
                        'photo_url' => $user->photo_url,
                        'avatar_url' => $user->avatar_url,
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to update user photo: ' . $e->getMessage(), [
                    'admin_id' => auth()->id(),
                    'target_user_id' => $id,
                    'error_trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update profile photo: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Unexpected error in updatePhoto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.'
            ], 500);
        }
    }

    /**
     * Remove user photo (admin)
     */
    public function removePhoto(Request $request, $id)
    {
        try {
            // Enhanced authorization check
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Only super admin can access - with better error message
            if (!auth()->user()->isSuperAdmin()) {
                Log::warning('Unauthorized photo removal attempt', [
                    'attempted_by' => auth()->id(),
                    'target_user' => $id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action. Super admin access required to manage user photos.'
                ], 403);
            }

            $user = User::findOrFail($id);

            DB::beginTransaction();

            try {
                if ($user->photo) {
                    $oldPhoto = $user->photo;
                    $this->deletePhoto($oldPhoto);
                    $user->photo = null;
                    $user->save();

                    // Update profile completion for the target user - NOW USING TRAIT METHOD
                    $this->updateProfileCompletion($user);

                    DB::commit();

                    // Refresh user to get updated URLs
                    $user->refresh();

                    // Log successful removal
                    Log::info("Admin removed user photo successfully", [
                        'admin_id' => auth()->id(),
                        'target_user_id' => $user->id,
                        'removed_photo' => $oldPhoto,
                        'target_user_email' => $user->email
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Profile photo removed successfully!',
                        'avatar_url' => $user->avatar_url,
                        'user' => [
                            'photo' => null,
                            'photo_url' => $user->photo_url,
                            'avatar_url' => $user->avatar_url,
                        ]
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User does not have a profile photo to remove.'
                    ], 400);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to remove user photo: ' . $e->getMessage(), [
                    'admin_id' => auth()->id(),
                    'target_user_id' => $id,
                    'error_trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove profile photo: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Unexpected error in removePhoto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.'
            ], 500);
        }
    }

    /**
     * NEW METHOD: Check if user can manage photos (for frontend authorization)
     */
    public function checkPhotoAccess(Request $request, $id = null)
    {
        try {
            $canManage = auth()->check() && auth()->user()->isSuperAdmin();
            
            return response()->json([
                'success' => true,
                'can_manage_photos' => $canManage,
                'is_super_admin' => $canManage,
                'current_user_id' => auth()->id()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'can_manage_photos' => false,
                'message' => 'Error checking access: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== PHOTO HANDLING METHODS ====================

    /**
     * Photo upload handler
     */
    public function handlePhotoUpload($photoFile, bool $optimize = true): string
    {
        try {
            // Validate file exists and is valid
            if (!$photoFile || !$photoFile->isValid()) {
                throw new \Exception('Invalid photo file provided.');
            }

            // Use model constants for consistency
            $disk = 'public'; // Always use public disk for web accessibility
            $directory = 'users/photos';

            // Create directory if it doesn't exist with proper permissions
            if (!Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->makeDirectory($directory, 0755, true);
            }

            // Generate unique filename with proper extension
            $extension = strtolower($photoFile->getClientOriginalExtension());
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image format.');
            }

            $filename = 'user_' . $this->generateUniqueId() . '.' . $extension;
            $fullPath = $directory . '/' . $filename;

            // Handle image optimization and upload
            if ($optimize && $this->isGdAvailable()) {
                $this->processAndStoreImageWithGd($photoFile, $disk, $fullPath);
            } else {
                // Store original without processing
                Storage::disk($disk)->putFileAs($directory, $photoFile, $filename);
            }

            // Verify the file was actually stored
            if (!Storage::disk($disk)->exists($fullPath)) {
                throw new \Exception('Failed to store photo file after upload.');
            }

            // Verify file is readable and has size
            $fileSize = Storage::disk($disk)->size($fullPath);
            if ($fileSize === 0) {
                Storage::disk($disk)->delete($fullPath);
                throw new \Exception('Uploaded file is empty or corrupted.');
            }

            Log::info("Photo uploaded and verified successfully", [
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $fullPath,
                'disk' => $disk,
                'file_size' => $fileSize,
                'optimized' => $optimize,
                'gd_available' => $this->isGdAvailable()
            ]);

            return $filename;

        } catch (\Exception $e) {
            Log::error('Photo upload failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'original_name' => $photoFile->getClientOriginalName(),
                'file_size' => $photoFile->getSize(),
                'mime_type' => $photoFile->getMimeType()
            ]);
            throw new \Exception('Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Process and store optimized image using native PHP GD
     */
    private function processAndStoreImageWithGd($photoFile, string $disk, string $fullPath): void
    {
        try {
            $tempPath = $photoFile->getPathname();
            $imageInfo = getimagesize($tempPath);
            
            if (!$imageInfo) {
                throw new \Exception('Could not read image file.');
            }

            list($originalWidth, $originalHeight, $imageType) = $imageInfo;

            // Define maximum dimensions
            $maxWidth = 800;
            $maxHeight = 800;

            // Calculate new dimensions
            if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
                // No resizing needed, just copy the file
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

            // Create image resource based on type
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($tempPath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($tempPath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($tempPath);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $sourceImage = imagecreatefromwebp($tempPath);
                    } else {
                        // Fallback to original if WebP not supported
                        Storage::disk($disk)->put($fullPath, file_get_contents($tempPath));
                        return;
                    }
                    break;
                default:
                    throw new \Exception('Unsupported image type.');
            }

            if (!$sourceImage) {
                throw new \Exception('Could not create image resource.');
            }

            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
            }

            // Resize image
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // Save image based on type
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($newImage, Storage::disk($disk)->path($fullPath), 85);
                    break;
                case IMAGETYPE_PNG:
                    $success = imagepng($newImage, Storage::disk($disk)->path($fullPath), 8);
                    break;
                case IMAGETYPE_GIF:
                    $success = imagegif($newImage, Storage::disk($disk)->path($fullPath));
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $success = imagewebp($newImage, Storage::disk($disk)->path($fullPath), 85);
                    } else {
                        $success = imagejpeg($newImage, Storage::disk($disk)->path($fullPath), 85);
                    }
                    break;
                default:
                    $success = imagejpeg($newImage, Storage::disk($disk)->path($fullPath), 85);
            }

            // Free memory
            imagedestroy($sourceImage);
            imagedestroy($newImage);

            if (!$success) {
                throw new \Exception('Failed to save optimized image.');
            }

            Log::debug("Image resized from {$originalWidth}x{$originalHeight} to {$newWidth}x{$newHeight}");

        } catch (\Exception $e) {
            Log::error('GD image processing failed: ' . $e->getMessage());
            // Fallback: store original image
            Storage::disk($disk)->put($fullPath, file_get_contents($photoFile->getPathname()));
        }
    }

    /**
     * Check if GD extension is available
     */
    private function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    /**
     * Generate unique ID for filenames
     */
    private function generateUniqueId(): string
    {
        return time() . '_' . Str::random(10) . '_' . Auth::id();
    }

    /**
     * Enhanced photo deletion with better error handling
     */
    public function deletePhoto($photoPath): bool
    {
        if (!$photoPath) {
            return true;
        }

        try {
            $disk = 'public';
            $directory = 'users/photos';
            $fullPath = $directory . '/' . $photoPath;
            
            // Check if file exists before attempting deletion
            if (Storage::disk($disk)->exists($fullPath)) {
                $deleted = Storage::disk($disk)->delete($fullPath);
                
                if ($deleted) {
                    Log::info("Photo deleted successfully from storage", [
                        'photo_path' => $photoPath,
                        'full_path' => $fullPath
                    ]);
                    return true;
                } else {
                    Log::warning("Photo file exists but could not be deleted", [
                        'photo_path' => $photoPath
                    ]);
                    return false;
                }
            } else {
                Log::info("Photo file not found in storage (may have been already deleted)", [
                    'photo_path' => $photoPath
                ]);
                return true; // Consider success if file doesn't exist
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to delete photo from storage: ' . $e->getMessage(), [
                'photo_path' => $photoPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get profile completion (fallback method - kept for backward compatibility)
     * Uses the trait method now
     */
    private function getProfileCompletion(User $user): int
    {
        try {
            // Use the trait method
            return $this->calculateProfileCompletion($user);
        } catch (\Exception $e) {
            Log::warning('Error getting profile completion: ' . $e->getMessage());
            
            // Fallback basic calculation
            $completed = 0;
            $totalFields = 0;
            
            $fields = ['name', 'email', 'phone', 'photo'];
            
            foreach ($fields as $field) {
                $totalFields++;
                if (!empty($user->$field)) {
                    $completed++;
                }
            }
            
            return $totalFields > 0 ? round(($completed / $totalFields) * 100) : 0;
        }
    }
}
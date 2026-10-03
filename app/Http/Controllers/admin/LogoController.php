<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class LogoController extends Controller
{
    /**
     * Upload system logo via AJAX
     */
    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'system_logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = SystemSetting::getSettings();
            
            // Delete old logo if exists
            if ($settings->system_logo) {
                $this->deleteLogoFile($settings->system_logo);
            }

            // Upload new logo
            $logoPath = $this->handleLogoUpload($request->file('system_logo'));
            
            $settings->update([
                'system_logo' => $logoPath,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'logo_url' => $settings->getLogoUrl(),
                'logo_path' => $logoPath
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove system logo
     */
    public function removeLogo()
    {
        try {
            $settings = SystemSetting::getSettings();
            
            if (!$settings->system_logo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No logo found to remove'
                ], 404);
            }

            $this->deleteLogoFile($settings->system_logo);
            
            $settings->update([
                'system_logo' => null,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logo removed successfully',
                'logo_url' => $settings->getDisplayLogo()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle logo file upload
     */
    public function handleLogoUpload($file): string
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2048; // 2MB

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        if ($file->getSize() > $maxFileSize * 1024) {
            throw new \Exception('File size too large. Maximum allowed size is ' . $maxFileSize . 'KB.');
        }

        // Generate unique filename
        $filename = 'system-logo-' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store in storage/app/public/logos
        $path = $file->storeAs('logos', $filename, 'public');
        
        return $path;
    }

    /**
     * Delete logo file from storage
     */
    public function deleteLogoFile($logoPath): void
    {
        if (Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }
    }
}
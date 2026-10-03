<?php

namespace App\Http\Controllers;

use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThemePreviewController extends Controller
{
    public function __construct(
        protected ThemeServiceInterface $themeService
    ) {}

    /**
     * Get sidebar theme preview
     */
    public function sidebarPreview(string $theme): JsonResponse
    {
        try {
            $previews = $this->themeService->getSidebarPreviews();
            
            if (!isset($previews[$theme])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Theme not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $previews[$theme]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get sidebar preview', [
                'theme' => $theme,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sidebar preview'
            ], 500);
        }
    }

    /**
     * Get all sidebar previews
     */
    public function allSidebarPreviews(): JsonResponse
    {
        try {
            $previews = $this->themeService->getSidebarPreviews();
            
            return response()->json([
                'success' => true,
                'data' => $previews
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get sidebar previews', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sidebar previews'
            ], 500);
        }
    }

    /**
     * Get color preview
     */
    public function colorPreview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $colors = $this->themeService->getColors($user);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current' => $colors['colors'],
                    'default' => $colors['default_colors']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get color preview', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load color preview'
            ], 500);
        }
    }

    /**
     * Get live preview with current theme
     */
    public function livePreview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $settings = $this->themeService->getSettings($user);
            $colors = $this->themeService->getColors($user);
            $css = $this->themeService->generateThemeCss(
                ThemeSettingsDTO::fromArray($settings['settings']),
                ThemeColorsDTO::fromArray($colors['colors'])
            );
            
            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings['settings'],
                    'colors' => $colors['colors'],
                    'css' => $css,
                    'preview_url' => route('theme.preview.live')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get live preview', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load live preview'
            ], 500);
        }
    }
}
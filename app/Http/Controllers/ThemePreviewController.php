<?php

namespace App\Http\Controllers;

use App\Contracts\Theme\ThemeServiceInterface;
use App\Http\Controllers\Concerns\HandlesThemeErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemePreviewController extends Controller
{
    use HandlesThemeErrors;

    public function __construct(
        protected ThemeServiceInterface $themeService
    ) {}

    /**
     * Get sidebar theme preview
     */
    public function sidebarPreview(Request $request, string $theme): JsonResponse
    {
        try {
            $previews = $this->themeService->getSidebarPreviews();

            if (! isset($previews[$theme])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Theme not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $previews[$theme],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load sidebar preview', $request);
        }
    }

    /**
     * Get all sidebar previews
     */
    public function allSidebarPreviews(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->themeService->getSidebarPreviews(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load sidebar previews', $request);
        }
    }

    /**
     * Get color preview
     */
    public function colorPreview(Request $request): JsonResponse
    {
        try {
            $colors = $this->themeService->getColors($request->user());

            return response()->json([
                'success' => true,
                'data'    => [
                    'current' => $colors['colors'],
                    'default' => $colors['default_colors'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load color preview', $request);
        }
    }

    /**
     * Get live preview with current theme.
     * Now validates DTOs before generating CSS (matching ThemeController::getThemeCss).
     */
    public function livePreview(Request $request): JsonResponse
    {
        try {
            $user     = $request->user();
            $settings = $this->themeService->getSettings($user);
            $colors   = $this->themeService->getColors($user);

            $css = $this->themeService->generateThemeCss(
                $this->makeSettingsDto($settings['settings'] ?? null),
                $this->makeColorsDto($colors['colors'] ?? null),
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'settings'    => $settings['settings'],
                    'colors'      => $colors['colors'],
                    'css'         => $css,
                    'preview_url' => route('theme.preview.live'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load live preview', $request);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Http\Controllers\Concerns\HandlesThemeErrors;
use App\Services\Theme\Support\SidebarThemeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ThemeController extends Controller
{
    use HandlesThemeErrors;

    public function __construct(
        protected ThemeServiceInterface $themeService,
        protected SidebarThemeRegistry $sidebarRegistry,
    ) {}

    /**
     * ============================================
     * PUBLIC API METHODS
     * ============================================
     */

    public function getSettings(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->themeService->getSettings($request->user()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme settings', $request);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->updateSettings($request->user(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Theme settings updated successfully',
                'data'    => $data,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to update theme settings', $request);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Theme settings reset to default',
                'data'    => $this->themeService->resetSettings($request->user()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to reset theme settings', $request);
        }
    }

    /**
     * All theme options + sidebar previews.
     * Sidebar previews come from the service — single source of truth.
     */
    public function getOptions(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'options'          => $this->themeService->getOptions(),
                    'sidebar_previews' => $this->themeService->getSidebarPreviews(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme options', $request);
        }
    }

    public function getColors(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->themeService->getColors($request->user()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to get custom colors', $request);
        }
    }

    public function updateColors(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->updateColors($request->user(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Custom colors updated successfully',
                'data'    => $data,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to update custom colors', $request);
        }
    }

    public function resetColors(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Colors reset to default',
                'data'    => $this->themeService->resetColors($request->user()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to reset colors', $request);
        }
    }

    public function applyTheme(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => $this->themeService->applyTheme($request->user()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to apply theme', $request);
        }
    }

    /**
     * Return the generated theme CSS as text/css.
     * DTO validation is delegated to the trait helpers.
     */
    public function getThemeCss(Request $request): Response
    {
        try {
            $user = $request->user();

            $settings = $this->makeSettingsDto(
                $this->themeService->getSettings($user)['settings'] ?? null
            );
            $colors = $this->makeColorsDto(
                $this->themeService->getColors($user)['colors'] ?? null
            );

            $css = $this->themeService->generateThemeCss($settings, $colors);

            return response($css, 200)
                ->header('Content-Type', 'text/css; charset=UTF-8')
                ->header('Cache-Control', 'private, max-age=300, must-revalidate')
                ->header('Vary', 'Accept-Encoding, Cookie')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Exception $e) {
            Log::error('Failed to generate theme CSS', [
                'user_id' => $request->user()?->id,
                'error'   => $e->getMessage(),
            ]);

            return response('/* Error generating theme CSS */', 500)
                ->header('Content-Type', 'text/css; charset=UTF-8');
        }
    }

    /**
     * Sidebar theme previews for UI pickers.
     * Delegates to the service for a single source of truth.
     */
    public function getSidebarThemePreviews(): array
    {
        return $this->themeService->getSidebarPreviews();
    }

    /**
     * ============================================
     * ADMIN METHODS
     * ============================================
     */

    public function getSystemSettings(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'system_settings' => ThemeSettingsDTO::default()->toArray(),
                    'system_colors'   => ThemeColorsDTO::default()->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load system settings', $request);
        }
    }

    public function getThemeStatistics(Request $request): JsonResponse
    {
        try {
            // Build a {theme_name => 0} map from the registry's names list.
            $names = $this->sidebarRegistry->names();
            $distribution = array_fill_keys(
                array_values(array_unique(array_merge($names, ['custom']))),
                0
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_users'                => \App\Models\User::count(),
                    'users_with_custom_themes'   => 0,
                    'most_popular_theme'         => 'default',
                    'sidebar_theme_distribution' => $distribution,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme statistics', $request);
        }
    }
}
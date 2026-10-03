<?php

namespace App\Http\Controllers;

use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Services\Theme\Support\SidebarThemeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ThemeController extends Controller
{
    public function __construct(
        protected ThemeServiceInterface $themeService,
        protected SidebarThemeRegistry $sidebarRegistry,
    ) {}

    /**
     * ============================================
     * PUBLIC API METHODS
     * ============================================
     */

    /**
     * Get the current user's theme settings + colors.
     * Does NOT generate CSS — that's a separate endpoint.
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->getSettings($request->user());

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme settings', $request);
        }
    }

    /**
     * Update the current user's theme settings.
     * The service returns fresh CSS, so the frontend doesn't need a second call.
     */
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

    /**
     * Reset theme settings to default.
     */
    public function reset(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->resetSettings($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Theme settings reset to default',
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to reset theme settings', $request);
        }
    }

    /**
     * Get all available theme options + sidebar previews.
     */
    public function getOptions(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'options'         => $this->themeService->getOptions(),
                    'sidebar_previews' => $this->sidebarRegistry->previews(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme options', $request);
        }
    }

    /**
     * Get the current user's custom colors + the defaults.
     */
    public function getColors(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->getColors($request->user());

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to get custom colors', $request);
        }
    }

    /**
     * Update the current user's custom colors.
     */
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

    /**
     * Reset custom colors to default.
     */
    public function resetColors(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->resetColors($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Colors reset to default',
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to reset colors', $request);
        }
    }

    /**
     * Resolve the user's full theme bundle (settings + colors + CSS) and
     * cache it in the session. Used by frontend bootstrapping.
     */
    public function applyTheme(Request $request): JsonResponse
    {
        try {
            $data = $this->themeService->applyTheme($request->user());

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to apply theme', $request);
        }
    }

    /**
     * Return the generated theme CSS as text/css.
     * Delegates entirely to the service — no CSS generation in the controller.
     */
    public function getThemeCss(Request $request): Response
    {
        try {
            $user = $request->user();

            $settings = ThemeSettingsDTO::fromArray(
                $this->themeService->getSettings($user)['settings'] ?? null
            );

            $colors = ThemeColorsDTO::fromArray(
                $this->themeService->getColors($user)['colors'] ?? null
            );

            // Extra guard: DTO validation. If either DTO is invalid, the
            // service's write path would have rejected it, but a corrupted
            // DB row could still produce garbage.
            if (!$settings->isValid() || !$colors->isValid()) {
                throw new \RuntimeException('Stored theme settings or colors are invalid');
            }

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
     * ============================================
     * STATIC HELPERS FOR BLADE VIEWS
     * ============================================
     *
     * These intentionally delegate to the service. They are the ONLY
     * view-facing entry points into the theme system.
     */

    /**
     * Get the default colors array — used by the color picker component.
     */
    public static function getDefaultColors(): array
    {
        return ThemeColorsDTO::default()->toArray();
    }

    /**
     * Get the resolved theme for the current request.
     * Checks session first, then falls back to the service.
     */
    public static function getUserTheme(): array
    {
        // Session cache (fast path).
        if (session()->has('theme_settings')) {
            return session('theme_settings');
        }

        $user = auth()->user();

        // Resolve from the service.
        if (app()->bound(ThemeServiceInterface::class)) {
            try {
                $service  = app(ThemeServiceInterface::class);
                $resolved = $service->getSettings($user);

                // The service returns ['settings' => [...], 'colors' => [...]].
                $settings = $resolved['settings'] ?? ThemeSettingsDTO::default()->toArray();

                session(['theme_settings' => $settings]);

                return $settings;
            } catch (\Throwable $e) {
                Log::warning('Could not get theme from service', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final fallback — defaults.
        $defaults = ThemeSettingsDTO::default()->toArray();
        session(['theme_settings' => $defaults]);

        return $defaults;
    }

    /**
     * Get theme settings merged with custom colors and generated CSS.
     * Used by the sidebar blade.
     */
    public static function getThemeWithColors(): array
    {
        $settings = self::getUserTheme();
        $user     = auth()->user();

        try {
            if (!app()->bound(ThemeServiceInterface::class)) {
                return $settings;
            }

            $service = app(ThemeServiceInterface::class);
            $colors  = $service->getColors($user)['colors'] ?? [];

            $settingsDto = ThemeSettingsDTO::fromArray($settings);
            $colorsDto   = ThemeColorsDTO::fromArray($colors);

            $settings['custom_colors'] = $colorsDto->toArray();
            $settings['css'] = $service->generateThemeCss($settingsDto, $colorsDto);

            return $settings;
        } catch (\Throwable $e) {
            Log::warning('Could not get colors for theme', [
                'error' => $e->getMessage(),
            ]);

            return $settings;
        }
    }

    /**
     * Get the current appearance mode.
     */
    public static function getCurrentTheme(): string
    {
        return self::getUserTheme()['appearance'] ?? 'system';
    }

    /**
     * Get the current sidebar theme name.
     */
    public static function getCurrentSidebarTheme(): string
    {
        return self::getUserTheme()['sidebar_theme'] ?? 'default';
    }

    /**
     * Get the current user's custom colors.
     */
    public static function getCurrentCustomColors(): array
    {
        $user = auth()->user();

        try {
            if (app()->bound(ThemeServiceInterface::class)) {
                $service = app(ThemeServiceInterface::class);
                return $service->getColors($user)['colors'] ?? ThemeColorsDTO::default()->toArray();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not get custom colors', [
                'error' => $e->getMessage(),
            ]);
        }

        return ThemeColorsDTO::default()->toArray();
    }

    /**
     * Public endpoint: sidebar theme previews for UI pickers.
     * Delegates to the registry so all consumers share one source of truth.
     */
    public function getSidebarThemePreviews(): array
    {
        return $this->sidebarRegistry->previews();
    }

    /**
     * ============================================
     * ERROR RESPONSE HELPERS
     * ============================================
     */

    protected function errorResponse(\Throwable $e, string $message, Request $request, int $status = 500): JsonResponse
    {
        Log::error($message, [
            'user_id' => $request->user()?->id,
            'error'   => $e->getMessage(),
            'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], $status);
    }

    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $e->errors(),
        ], 422);
    }

    /**
     * ============================================
     * ADMIN METHODS (placeholders — extend as needed)
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
            return response()->json([
                'success' => true,
                'data'    => [
                    'total_users'                => \App\Models\User::count(),
                    'users_with_custom_themes'   => 0,
                    'most_popular_theme'         => 'default',
                    'sidebar_theme_distribution' => array_fill_keys(
                        $this->sidebarRegistry->names() + ['custom' => 'custom'],
                        0
                    ),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load theme statistics', $request);
        }
    }
}
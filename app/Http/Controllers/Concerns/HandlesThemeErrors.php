<?php

namespace App\Http\Controllers\Concerns;

use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait HandlesThemeErrors
{
    /**
     * Log and return a JSON error response.
     * Error details are only exposed when app.debug is true.
     */
    protected function errorResponse(
        \Throwable $e,
        string $message,
        Request $request,
        int $status = 500,
    ): JsonResponse {
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

    /**
     * Return a 422 validation error response.
     */
    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $e->errors(),
        ], 422);
    }

    /**
     * Build a ThemeSettingsDTO from raw input, throwing if invalid.
     * Use this everywhere a DTO is constructed from DB or request data.
     */
    protected function makeSettingsDto(?array $raw): ThemeSettingsDTO
    {
        $dto = ThemeSettingsDTO::fromArray($raw ?? []);

        if (! $dto->isValid()) {
            throw new \RuntimeException('Theme settings are invalid.');
        }

        return $dto;
    }

    /**
     * Build a ThemeColorsDTO from raw input, throwing if invalid.
     */
    protected function makeColorsDto(?array $raw): ThemeColorsDTO
    {
        $dto = ThemeColorsDTO::fromArray($raw ?? []);

        if (! $dto->isValid()) {
            throw new \RuntimeException('Theme colors are invalid.');
        }

        return $dto;
    }
}
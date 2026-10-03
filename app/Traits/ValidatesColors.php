<?php

namespace App\Traits;

use Illuminate\Validation\Validator;

trait ValidatesColors
{
    /**
     * Get the color validation rules.
     *
     * @return array
     */
    protected function getColorValidationRules(): array
    {
        $hexPattern = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        
        return [
            'sidebar_bg_start' => ['nullable', 'string', 'regex:' . $hexPattern],
            'sidebar_bg_end' => ['nullable', 'string', 'regex:' . $hexPattern],
            'sidebar_text' => ['nullable', 'string', 'regex:' . $hexPattern],
            'primary_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'secondary_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'accent_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'success_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'warning_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'danger_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'info_color' => ['nullable', 'string', 'regex:' . $hexPattern],
            'header_bg' => ['nullable', 'string', 'regex:' . $hexPattern],
            'header_text' => ['nullable', 'string', 'regex:' . $hexPattern],
            'card_bg' => ['nullable', 'string', 'regex:' . $hexPattern],
            'card_border' => ['nullable', 'string', 'regex:' . $hexPattern],
            'sidebar_active_bg' => ['nullable', 'string'],
            'sidebar_hover_bg' => ['nullable', 'string'],
            'sidebar_border' => ['nullable', 'string'],
            'sidebar_shadow' => ['nullable', 'string'],
        ];
    }

    /**
     * Validate color data.
     *
     * @param array $data
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateColors(array $data): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            $data,
            $this->getColorValidationRules()
        );
        
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }
}
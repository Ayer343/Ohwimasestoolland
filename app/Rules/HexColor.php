<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class HexColor implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Skip if null (allow nullable)
        if (is_null($value)) {
            return true;
        }
        
        // Validate hex color (3 or 6 characters)
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be a valid hex color code (e.g., #FF0000 or #F00).';
    }
}
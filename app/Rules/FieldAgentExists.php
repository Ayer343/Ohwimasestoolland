<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class FieldAgentExists implements Rule
{
    protected $invalidIds = [];

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Skip validation if value is empty, null, or 'null' string
        if (empty($value) || $value === 'null') {
            return true;
        }

        // Check if the user exists
        $user = User::find($value);
        
        if (!$user) {
            $this->invalidIds[] = "User ID {$value} does not exist";
            return false;
        }

        // FIX: Use isFieldAgent() method which checks both role AND legacy type
        if (!$user->isFieldAgent()) {
            $this->invalidIds[] = "User {$user->name} (ID: {$value}) is not a field agent";
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if (!empty($this->invalidIds)) {
            return 'The following agents are invalid: ' . implode(', ', $this->invalidIds);
        }

        return 'The selected :attribute must be a valid field agent.';
    }
}
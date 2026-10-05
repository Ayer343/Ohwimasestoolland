<?php
// app/Http/Requests/Admin/ReviewFamilyLinkRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewFamilyLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = auth()->user();
        return $u && ($u->isAdmin() || $u->isSuperAdmin());
    }

    public function rules(): array
    {
        return [
            'decision'       => ['required', Rule::in(['approve', 'reject'])],
            'admin_notes'    => 'nullable|string|max:1000',

            // Only relevant when approving
            'permissions'    => 'nullable|array',
            'permissions.*'  => Rule::in(array_keys(config('property_family_links.permissions'))),

            // Admin can override proposed details at approval time
            'override_name'  => 'nullable|string|max:255',
            'override_phone' => 'nullable|string|max:20',
            'override_email' => 'nullable|email|max:255',
        ];
    }
}
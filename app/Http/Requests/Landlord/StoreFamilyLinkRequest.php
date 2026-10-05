<?php
// app/Http/Requests/Landlord/StoreFamilyLinkRequest.php

namespace App\Http\Requests\Landlord;

use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFamilyLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        $maxPerProperty = config('property_family_links.max_links_per_property', 5);

        return [
            'property_id' => [
                'required',
                'exists:properties,id',
                // Landlord must own the property
                Rule::exists('properties', 'id')
                    ->where('landlord_id', auth()->id()),
            ],

            'proposed_name' => 'required|string|max:255',

            'proposed_phone' => [
                'nullable',
                'string',
                'max:20',
                'required_without:proposed_email',
            ],

            'proposed_email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:proposed_phone',
            ],

            'relationship' => [
                'required',
                Rule::in(config('property_family_links.relationship_options')),
            ],

            'relationship_other' => 'required_if:relationship,other|nullable|string|max:100',

            'permissions'   => 'nullable|array',
            'permissions.*' => Rule::in(array_keys(config('property_family_links.permissions'))),

            'landlord_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.exists' => 'You can only link family members to properties you own.',
            'proposed_phone.required_without' => 'Provide at least a phone number or an email address.',
            'proposed_email.required_without' => 'Provide at least a phone number or an email address.',
        ];
    }

    /**
     * Business-rule validation layered on top of the field rules.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $propertyId = $this->input('property_id');
            $phone = $this->input('proposed_phone');
            $email = $this->input('proposed_email');

            // 1. Max links per property
            $existing = PropertyFamilyLink::forProperty($propertyId)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            $max = config('property_family_links.max_links_per_property', 5);

            if ($existing >= $max) {
                $validator->errors()->add(
                    'property_id',
                    "This property already has the maximum of {$max} linked family members."
                );
            }

            // 2. Duplicate phone/email across pending+approved links for this property
            $dupQuery = PropertyFamilyLink::forProperty($propertyId)
                ->whereIn('status', ['pending', 'approved']);

            if ($phone) {
                if ((clone $dupQuery)->where('proposed_phone', $phone)->exists()) {
                    $validator->errors()->add(
                        'proposed_phone',
                        'A pending or approved link already exists for this phone number.'
                    );
                }
            }

            if ($email) {
                if ((clone $dupQuery)->where('proposed_email', $email)->exists()) {
                    $validator->errors()->add(
                        'proposed_email',
                        'A pending or approved link already exists for this email address.'
                    );
                }
            }

            // 3. Landlord cannot propose themselves
            $self = User::where('id', auth()->id())
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) $q->orWhere('phone', $phone);
                    if ($email) $q->orWhere('email', $email);
                })
                ->exists();

            if ($self) {
                $validator->errors()->add(
                    'proposed_phone',
                    'You cannot propose yourself as a linked family member.'
                );
            }

            // 4. Cannot propose an existing admin/super-admin
            if ($phone || $email) {
                $privileged = User::where(function ($q) use ($phone, $email) {
                        if ($phone) $q->orWhere('phone', $phone);
                        if ($email) $q->orWhere('email', $email);
                    })
                    ->whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
                    ->exists();

                if ($privileged) {
                    $validator->errors()->add(
                        'proposed_phone',
                        'This person is already an administrator and cannot be linked as a family member.'
                    );
                }
            }
        });
    }
}
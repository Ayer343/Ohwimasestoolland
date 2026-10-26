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
        $mode       = $this->input('link_mode', 'new');
        $isExisting = $mode === 'existing';

        $rules = [
            'link_mode' => [
                'nullable',
                Rule::in(['new', 'existing']),
            ],

            // ── Property targeting (single + bulk) ──
            'property_ids' => [
                $this->has('property_ids') ? 'required' : 'nullable',
                'array',
                'min:1',
                'max:50',
            ],
            'property_ids.*' => [
                'integer',
                Rule::exists('properties', 'id')
                    ->where('landlord_id', auth()->id()),
            ],

            // Legacy single property input — still accepted.
            'property_id' => [
                $this->has('property_ids') ? 'nullable' : 'required',
                'integer',
                Rule::exists('properties', 'id')
                    ->where('landlord_id', auth()->id()),
            ],

            'permissions'   => 'nullable|array',
            'permissions.*' => Rule::in(array_keys(config('property_family_links.permissions', []))),

            'landlord_notes' => 'nullable|string|max:1000',
        ];

        if ($isExisting) {
            // ── Existing-member mode ──
            $rules = array_merge($rules, [
                'existing_user_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                ],

                // These fields are ignored by the service in existing
                // mode, but the request stays permissive so the payload
                // shape is uniform across modes.
                'proposed_name'  => 'nullable|string|max:255',
                'proposed_phone' => 'nullable|string|max:20',
                'proposed_email' => 'nullable|email|max:255',
                'relationship'   => 'nullable|string|max:100',
            ]);
        } else {
            // ── New-member mode ──
            $rules = array_merge($rules, [
                'proposed_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

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
                    Rule::in(config('property_family_links.relationship_options', [])),
                ],

                'relationship_other' => [
                    'required_if:relationship,other',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'existing_user_id' => 'nullable|integer|exists:users,id',
            ]);
        }

        // ── Resubmission — single property, new-member mode only ──
        $rules['resubmit_from'] = [
            'nullable',
            'integer',
            Rule::exists('property_family_links', 'id')
                ->where(function ($q) {
                    $q->where('landlord_id', auth()->id())
                      ->where('status', PropertyFamilyLink::STATUS_REJECTED);
                }),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'property_id.exists'    => 'You can only link family members to properties you own.',
            'property_ids.*.exists' => 'One or more selected properties do not belong to you.',

            'proposed_phone.required_without' => 'Provide at least a phone number or an email address.',
            'proposed_email.required_without' => 'Provide at least a phone number or an email address.',

            'relationship.in'                => 'Please select a valid relationship from the list.',
            'relationship_other.required_if' => 'Please specify the relationship when "Other" is selected.',

            'permissions.*.in' => 'One or more selected permissions are not recognised.',

            'existing_user_id.required' => 'Choose the existing family member to link.',
            'existing_user_id.exists'   => 'The selected family member could not be found.',

            'resubmit_from.exists' => 'The proposal you are trying to resubmit was not found, is not yours, or is no longer in a resubmittable state.',
        ];
    }

    /**
     * Cross-field validation that can't be expressed in the rules() array.
     *
     * Two modes:
     *
     *   - new:      validates per-property max-links cap, duplicate
     *               phone/email on active links, self-proposal, and
     *               privileged-user guards.
     *   - existing: verifies the landlord has a prior approved link to
     *               the target user. Per-property duplicates are left
     *               to the service, which has the full context.
     *
     * Structural checks (resubmission targeting, mode shape) run in
     * both modes.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $landlordId = auth()->id();

            $mode        = $this->input('link_mode', 'new');
            $isExisting  = $mode === 'existing';
            $resubmitId  = $this->input('resubmit_from');

            $propertyIds = $this->resolvePropertyIds();

            // ── 1. Structural check: resubmission must target exactly one property ──
            if ($resubmitId && count($propertyIds) > 1) {
                $validator->errors()->add(
                    'resubmit_from',
                    'A resubmission must target exactly one property.'
                );
            }

            // ── 2. Mode-specific checks ──
            if ($isExisting) {
                $this->validateExistingMode($validator, $landlordId);
            } else {
                $this->validateNewMode($validator, $landlordId, $propertyIds, $resubmitId);
            }

            // ── 3. Structural check: resubmission source row ──
            // Runs in BOTH modes — it's a cheap ownership/state lookup
            // that should always be enforced, even if a malicious client
            // tries to combine resubmit_from with link_mode=existing.
            if ($resubmitId) {
                $this->validateResubmissionSource($validator, $landlordId, $resubmitId, $propertyIds);
            }
        });
    }

    /**
     * Normalize legacy single-property input into the new array form
     * so downstream code only has one shape to handle.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('property_ids') && $this->filled('property_id')) {
            $this->merge([
                'property_ids' => [(int) $this->input('property_id')],
            ]);
        }

        // Normalize contact fields so duplicate checks compare
        // canonical forms. Phone is trimmed; email is lowercased
        // and trimmed.
        $this->merge([
            'proposed_phone' => $this->filled('proposed_phone')
                ? trim((string) $this->input('proposed_phone'))
                : $this->input('proposed_phone'),
            'proposed_email' => $this->filled('proposed_email')
                ? strtolower(trim((string) $this->input('proposed_email')))
                : $this->input('proposed_email'),
        ]);
    }

    /* ================================================================
       PRIVATE HELPERS
       ================================================================ */

    /**
     * Collect the selected property IDs from either the array field or
     * the legacy single field, deduplicate, and cast to int.
     *
     * @return array<int,int>
     */
    private function resolvePropertyIds(): array
    {
        $propertyIds = (array) $this->input('property_ids', []);

        if (empty($propertyIds) && $this->filled('property_id')) {
            $propertyIds = [(int) $this->input('property_id')];
        }

        return array_values(array_unique(array_map('intval', $propertyIds)));
    }

    /**
     * Existing-member mode.
     *
     * Only check: the landlord must already have an approved link to
     * the target user — that's the security guarantee. Per-property
     * duplicates are skipped here because the service resolves them
     * (and reports skips) at insert time, which avoids reporting a
     * duplicate that the service would actually skip anyway.
     */
    private function validateExistingMode($validator, $landlordId): void
    {
        $existingUserId = (int) $this->input('existing_user_id');

        // If existing_user_id failed its own `exists` rule, don't
        // double-report. If it's set but doesn't resolve, skip — the
        // rule-level error is already on the bag.
        if ($existingUserId <= 0) {
            return;
        }

        $hasPrior = PropertyFamilyLink::query()
            ->where('landlord_id', $landlordId)
            ->where('linked_user_id', $existingUserId)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->exists();

        if (!$hasPrior) {
            $validator->errors()->add(
                'existing_user_id',
                'You can only add properties for family members you have already linked.'
            );
        }
    }

    /**
     * New-member mode.
     *
     * Per-property guards: max-links cap, duplicate phone/email on
     * active links, self-proposal, privileged-user.
     *
     * @param  array<int,int>  $propertyIds
     */
    private function validateNewMode($validator, $landlordId, array $propertyIds, $resubmitId): void
    {
        $phone = $this->input('proposed_phone');
        $email = $this->input('proposed_email');

        // ── Per-property guards ──
        $activeStatuses = [
            PropertyFamilyLink::STATUS_PENDING_LANDLORD,
            PropertyFamilyLink::STATUS_PENDING_ADMIN,
            PropertyFamilyLink::STATUS_PENDING,
            PropertyFamilyLink::STATUS_APPROVED,
        ];

        $max = (int) config('property_family_links.max_links_per_property', 5);

        foreach ($propertyIds as $pid) {
            if (!$pid) {
                continue;
            }

            // Max-links cap
            $existingCount = PropertyFamilyLink::forProperty($pid)
                ->whereIn('status', $activeStatuses)
                ->count();

            if ($existingCount >= $max) {
                $validator->errors()->add(
                    'property_ids',
                    "One of the selected properties already has the maximum of {$max} linked family members."
                );
                break;
            }

            // Duplicate phone/email checks
            $dupQuery = PropertyFamilyLink::forProperty($pid)
                ->whereIn('status', $activeStatuses)
                ->when($resubmitId, fn ($q) => $q->where('id', '!=', $resubmitId));

            if ($phone && (clone $dupQuery)->where('proposed_phone', $phone)->exists()) {
                $validator->errors()->add(
                    'proposed_phone',
                    'A pending or approved link already exists for this phone number on one of the selected properties.'
                );
                break;
            }

            if ($email && (clone $dupQuery)->where('proposed_email', $email)->exists()) {
                $validator->errors()->add(
                    'proposed_email',
                    'A pending or approved link already exists for this email address on one of the selected properties.'
                );
                break;
            }
        }

        // ── Self-proposal guard ──
        if ($phone || $email) {
            $self = User::where('id', $landlordId)
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
        }

        // ── Privileged-user guard ──
        if ($phone || $email) {
            $privileged = User::where(function ($q) use ($phone, $email) {
                    if ($phone) $q->orWhere('phone', $phone);
                    if ($email) $q->orWhere('email', $email);
                })
                ->whereIn('type', [
                    User::TYPE_ADMIN,
                    User::TYPE_SUPER_ADMIN,
                ])
                ->exists();

            if ($privileged) {
                $validator->errors()->add(
                    'proposed_phone',
                    'This person is already an administrator and cannot be linked as a family member.'
                );
            }
        }
    }

    /**
     * Structural check on a resubmission source row.
     *
     * Validates that the resubmit_from row:
     *   - exists
     *   - belongs to the current landlord
     *   - is in STATUS_REJECTED
     *   - targets a property in the submitted property_ids set
     *
     * @param  array<int,int>  $propertyIds
     */
    private function validateResubmissionSource(
        $validator,
        $landlordId,
        $resubmitId,
        array $propertyIds
    ): void {
        $source = PropertyFamilyLink::find($resubmitId);

        if (!$source) {
            $validator->errors()->add(
                'resubmit_from',
                'The original proposal could not be found.'
            );
            return;
        }

        if ((int) $source->landlord_id !== (int) $landlordId) {
            $validator->errors()->add(
                'resubmit_from',
                'You can only resubmit your own rejected proposals.'
            );
            return;
        }

        if ($source->status !== PropertyFamilyLink::STATUS_REJECTED) {
            $validator->errors()->add(
                'resubmit_from',
                'This proposal is no longer in a state that can be resubmitted.'
            );
            return;
        }

        if (!in_array((int) $source->property_id, $propertyIds, true)) {
            $validator->errors()->add(
                'resubmit_from',
                'The original proposal belongs to a different property.'
            );
        }
    }
}
<?php
// app/Http/Requests/Admin/ReviewFamilyLinkRequest.php

namespace App\Http\Requests\Admin;

use App\Models\PropertyFamilyLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewFamilyLinkRequest extends FormRequest
{
    /**
     * Supported delivery channels for family-link invitations.
     *
     * These must match what the admin index blade exposes AND what
     * UserInvitationService knows how to route. Add here when you add
     * a new transport.
     */
    public const SUPPORTED_CHANNELS = ['email', 'sms', 'whatsapp'];

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

            // ────────────────────────────────────────────────────────
            // Delivery channels — only relevant when approving.
            //
            // Required + at least one when the decision is 'approve'.
            // Each channel must be one of SUPPORTED_CHANNELS.
            //
            // Rejections don't need channels; the rule simply doesn't
            // fire because required_if skips when decision !== approve.
            // ────────────────────────────────────────────────────────
            'channels'       => [
                'required_if:decision,approve',
                'array',
                'min:1',
            ],
            'channels.*'     => [
                'string',
                Rule::in(self::SUPPORTED_CHANNELS),
            ],

            // ────────────────────────────────────────────────────────
            // Approval-only fields
            // ────────────────────────────────────────────────────────
            'permissions'    => 'nullable|array',
            'permissions.*'  => Rule::in(array_keys(config('property_family_links.permissions', []))),

            // Admin can override proposed details at approval time
            'override_name'  => 'nullable|string|max:255',
            'override_phone' => 'nullable|string|max:20',
            'override_email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Human-readable messages for the non-obvious failures.
     */
    public function messages(): array
    {
        return [
            'decision.required'    => 'You must choose to approve or reject.',
            'decision.in'          => 'Invalid decision. Must be approve or reject.',

            'channels.required_if' => 'Select at least one delivery channel for the invitation.',
            'channels.array'       => 'Delivery channels must be sent as an array.',
            'channels.min'         => 'Select at least one delivery channel for the invitation.',
            'channels.*.in'        => 'One or more selected channels are not supported.',

            'permissions.*.in'     => 'One or more selected permissions are not recognised.',
        ];
    }

    /**
     * Cross-check: every submitted channel must actually be deliverable
     * for the recipient. The blade disables unavailable channels, but
     * a crafted POST could bypass that — this is the server-side guard.
     *
     * Also strips 'whatsapp' if the WhatsApp provider isn't configured.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Only relevant on approve
            if ($this->input('decision') !== 'approve') {
                return;
            }

            $channels = (array) $this->input('channels', []);

            if (empty($channels)) {
                // The rule already produced the "min:1" error; don't duplicate.
                return;
            }

            /** @var PropertyFamilyLink|null $link */
            $link = $this->route('link');

            if (!$link instanceof PropertyFamilyLink) {
                return; // route model binding failed; other rules handle it
            }

            $hasEmail = !empty($link->proposed_email);
            $hasPhone = !empty($link->proposed_phone);

            // ────────────────────────────────────────────────────────
            // 1. Per-channel availability check
            // ────────────────────────────────────────────────────────
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'email':
                        if (!$hasEmail) {
                            $validator->errors()->add(
                                'channels',
                                'Email was selected but no email address was provided by the landlord.'
                            );
                        }
                        break;

                    case 'sms':
                        if (!$hasPhone) {
                            $validator->errors()->add(
                                'channels',
                                'SMS was selected but no phone number was provided by the landlord.'
                            );
                        }
                        break;

                    case 'whatsapp':
                        if (!$hasPhone) {
                            $validator->errors()->add(
                                'channels',
                                'WhatsApp was selected but no phone number was provided by the landlord.'
                            );
                        } else {
                            // WhatsApp may be unconfigured server-side.
                            // Reject the selection rather than silently skip it.
                            try {
                                $wa = app(\App\Services\WhatsAppService::class);
                                if (!$wa->isConfigured()) {
                                    $validator->errors()->add(
                                        'channels',
                                        'WhatsApp was selected but the WhatsApp provider is not configured.'
                                    );
                                }
                            } catch (\Throwable $e) {
                                $validator->errors()->add(
                                    'channels',
                                    'WhatsApp was selected but the WhatsApp service is unavailable.'
                                );
                            }
                        }
                        break;
                }
            }

            // ────────────────────────────────────────────────────────
            // 2. If everything the admin picked is unavailable, tell
            //    them clearly rather than letting the service fail
            //    silently downstream.
            // ────────────────────────────────────────────────────────
            $available = [];
            if ($hasEmail) $available[] = 'email';
            if ($hasPhone) $available[] = 'sms';
            if ($hasPhone) $available[] = 'whatsapp';

            if (empty($available)) {
                $validator->errors()->add(
                    'channels',
                    'This proposal has no contact method. Cannot send an invitation.'
                );
            }
        });
    }

    /**
     * Normalize the input before validation runs.
     *
     * - Lowercase + trim channel names so "Email" and " email " both pass.
     * - Drop empty strings from the channels array so an accidental
     *   `<input value="">` doesn't count as a selected channel.
     */
    protected function prepareForValidation(): void
    {
        $channels = $this->input('channels');

        if (is_array($channels)) {
            $channels = array_values(array_unique(array_filter(array_map(
                fn ($c) => is_string($c) ? strtolower(trim($c)) : $c,
                $channels
            ), fn ($c) => $c !== null && $c !== '')));

            $this->merge(['channels' => $channels]);
        }
    }

    /**
     * Convenience: get the validated channel list, always non-empty
     * on approve (or empty on reject).
     *
     * Useful in the controller so it doesn't have to guess.
     */
    public function selectedChannels(): array
    {
        if ($this->input('decision') !== 'approve') {
            return [];
        }

        return array_values(array_unique(array_filter(
            (array) $this->input('channels', []),
            fn ($c) => in_array($c, self::SUPPORTED_CHANNELS, true)
        )));
    }
}
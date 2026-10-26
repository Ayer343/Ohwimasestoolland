<?php

namespace App\Http\Requests\Security;

use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSecurityPersonnelRequest extends FormRequest
{
    /**
     * Only the *current active Head of Security* (or an admin acting on
     * their behalf) may submit a new security-personnel record.
     *
     * The submission creates the user in `pending` status and forces
     * `awaiting_approval = true` in metadata. Authority-related fields
     * (status, can_be_supervisor, post assignment, supervisor level,
     * primary flag) are NOT accepted from this request — they are set
     * server-side once an administrator approves the submission.
     */
    public function authorize(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Admins / super-admins may submit on behalf of a head.
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // Must be an active Head of Security.
        if (! $user->isSecurityPersonnel() || ! $user->can_be_supervisor) {
            return false;
        }

        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->exists();
    }

    /**
     * Only identity fields plus an optional "queue invitation" flag.
     *
     * Email is optional at the base level, but becomes required if the
     * head selects the `email` invitation channel while queueing — that
     * cross-field rule is enforced in withValidator() below.
     */
    public function rules(): array
    {
        return [
            // ============================================================
            // Identity
            // ============================================================
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'    => [
                'required',
                'string',
                'max:20',
                // Basic phone sanity — allows digits, spaces, +, -, (, )
                'regex:/^\+?[0-9\s\-\(\)]{9,20}$/',
                'unique:users,phone',
            ],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],

            // ============================================================
            // Optional: pre-queue an invitation for the admin to trigger
            // after approval. Nothing is sent until the admin approves.
            // ============================================================
            'queue_invitation'      => ['sometimes', 'boolean'],
            'invitation_channels'   => ['required_if:queue_invitation,1', 'array', 'min:1'],
            'invitation_channels.*' => ['string', 'distinct', Rule::in(['sms', 'email', 'whatsapp'])],
            'invitation_type'       => ['nullable', 'string', Rule::in([
                'security_welcome',
                'security_orientation',
                'security_training',
                'password_setup',
                'welcome',
                'registration',
                'account_setup',
            ])],
            'invitation_message'    => ['nullable', 'string', 'max:1000'],
            'expires_in_days'       => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * Cross-field validation.
     *
     * Email is only mandatory when the head queues an invitation AND
     * selects the `email` channel. Otherwise it may be omitted.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->boolean('queue_invitation')) {
                return;
            }

            $channels = array_values(array_filter(
                (array) $this->input('invitation_channels', []),
                fn ($c) => is_string($c) && $c !== ''
            ));

            if (in_array('email', $channels, true) && ! $this->filled('email')) {
                $v->errors()->add(
                    'email',
                    'An email address is required to queue an Email invitation.'
                );
            }

            if (
                (in_array('sms', $channels, true) || in_array('whatsapp', $channels, true))
                && ! $this->filled('phone')
            ) {
                $v->errors()->add(
                    'phone',
                    'A phone number is required to queue SMS/WhatsApp invitations.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Full name is required.',

            'email.email'    => 'Please enter a valid email address.',
            'email.unique'   => 'This email is already registered.',

            'phone.required' => 'Phone number is required.',
            'phone.regex'    => 'Please enter a valid phone number (e.g. +233XXXXXXXXX or 0XXXXXXXXX).',
            'phone.unique'   => 'This phone number is already registered.',

            'username.unique' => 'This username is already taken.',

            'queue_invitation.boolean'        => 'The queue-invitation flag must be true or false.',
            'invitation_channels.required_if' => 'Select at least one invitation channel.',
            'invitation_channels.min'         => 'Select at least one invitation channel.',
            'invitation_channels.*.in'        => 'Invalid invitation channel selected.',
            'invitation_channels.*.distinct'  => 'Each invitation channel may only be selected once.',
            'invitation_type.in'              => 'Invalid invitation type selected.',
            'invitation_message.max'          => 'The custom message may not exceed 1000 characters.',
            'expires_in_days.min'             => 'Invitation expiration must be at least 1 day.',
            'expires_in_days.max'             => 'Invitation expiration cannot exceed 30 days.',
        ];
    }

    /**
     * Normalize booleans, empty strings, and channel arrays.
     *
     * The server will override `status`, `can_be_supervisor`, and any
     * authority field regardless of what the client tries to send.
     */
    protected function prepareForValidation(): void
    {
        // Boolean coercion.
        $this->merge([
            'queue_invitation' => filter_var(
                $this->input('queue_invitation', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]);

        // Normalize empty strings to null so `nullable` rules behave.
        foreach (['username', 'email', 'invitation_message', 'invitation_type'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }

        // Filter empty entries out of invitation_channels[] so that
        // required_if + min:1 reflect reality (rather than counting [""]).
        if ($this->has('invitation_channels')) {
            $channels = array_values(array_filter(
                (array) $this->input('invitation_channels', []),
                fn ($c) => is_string($c) && trim($c) !== ''
            ));
            $this->merge(['invitation_channels' => $channels]);
        }

        // Default the invitation type when queueing.
        if ($this->boolean('queue_invitation') && ! $this->filled('invitation_type')) {
            $this->merge(['invitation_type' => 'security_welcome']);
        }

        // Default the expiry when queueing.
        if ($this->boolean('queue_invitation') && ! $this->filled('expires_in_days')) {
            $this->merge(['expires_in_days' => 7]);
        }
    }

    /**
     * Fields the controller is allowed to pass through to
     * UserRepository::createUser(). Anything outside this list is
     * discarded — this is the second line of defence after the rules.
     *
     * NOTE: invitation-related inputs are NOT included here — they are
     * read separately via queuedInvitation() and stored in metadata,
     * never passed to createUser().
     */
    public function validatedPayload(): array
    {
        $data = $this->safe()->only([
            'name', 'email', 'phone', 'username',
        ]);

        // Server-controlled fields — always force these.
        $data['type']              = User::TYPE_SECURITY_PERSONNEL;
        $data['status']            = User::STATUS_PENDING;
        $data['can_be_supervisor'] = false;

        return $data;
    }

    /**
     * Normalized invitation payload for the admin-approval queue.
     * Returns null when the head did not tick "Queue an invitation".
     *
     * The controller persists this under metadata['queued_invitation'].
     * It is dispatched by the admin-side approve flow — never by this
     * submission — so nothing is sent before approval.
     */
    public function queuedInvitation(): ?array
    {
        if (! $this->boolean('queue_invitation')) {
            return null;
        }

        $safe = $this->safe();

        return [
            'channels'        => array_values($safe->input('invitation_channels') ?? []),
            'type'            => $safe->input('invitation_type') ?? 'security_welcome',
            'message'         => $safe->input('invitation_message'),
            'expires_in_days' => (int) ($safe->input('expires_in_days') ?? 7),
            'queued_at'       => now()->toISOString(),
            'queued_by'       => auth()->id(),
        ];
    }
    
}
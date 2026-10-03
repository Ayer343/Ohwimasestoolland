<?php

namespace App\Traits;

use App\Models\User;

trait ProfileCompletionTrait
{
    /**
     * Calculate profile completion percentage.
     *
     * Reads directly from the $user model's current attributes, so the
     * caller must ensure the model is fresh (e.g. `$user->refresh()`) if
     * fields were just updated in the same request.
     */
    public function calculateProfileCompletion(User $user): int
    {
        try {
            $details = $this->calculateProfileCompletionDetails($user);

            return $details['total_weight'] > 0
                ? (int) min(100, round(($details['completed_weight'] / $details['total_weight']) * 100))
                : 0;
        } catch (\Exception $e) {
            \Log::error('Failed to calculate profile completion', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calculate detailed profile completion.
     *
     * Each field's completion flag is computed from the current
     * in-memory values of the model.
     */
    public function calculateProfileCompletionDetails(User $user): array
    {
        $fields = [
            'name' => [
                'weight' => 10,
                'completed' => !empty(trim((string) $user->name)),
            ],
            'email' => [
                'weight' => 15,
                // Both must be true: an email on file AND verified.
                'completed' => !empty($user->email)
                    && !is_null($user->email_verified_at),
            ],
            'phone' => [
                'weight' => 15,
                'completed' => !empty($user->phone)
                    && !is_null($user->phone_verified_at),
            ],
            'digital_address' => [
                'weight' => 10,
                'completed' => !empty($user->digital_address),
            ],
            'region' => [
                'weight' => 10,
                'completed' => !empty($user->region),
            ],
            'location' => [
                'weight' => 10,
                'completed' => !empty($user->location),
            ],
            'gender' => [
                'weight' => 5,
                'completed' => !empty($user->gender),
            ],
            'dob' => [
                'weight' => 5,
                // FIX: a `dob` cast to Carbon may be a non-null object
                // even when the underlying value is null-ish. Use
                // `!== null` on the raw attribute to be safe.
                'completed' => $user->getRawOriginal('dob') !== null
                    || !empty($user->getAttributes()['dob'] ?? null),
            ],
            'photo' => [
                'weight' => 10,
                'completed' => !empty($user->photo),
            ],
        ];

        $details = [];
        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($fields as $field => $data) {
            $details[$field] = [
                'completed' => (bool) $data['completed'],
                'weight'    => (int) $data['weight'],
                'label'     => $this->getFieldLabel($field),
            ];

            $totalWeight += $data['weight'];
            if ($data['completed']) {
                $completedWeight += $data['weight'];
            }
        }

        $details['total_weight']     = $totalWeight;
        $details['completed_weight'] = $completedWeight;

        return $details;
    }

    /**
     * Recompute and persist profile completion for a user.
     *
     * FIX: refresh the model from the DB first, so this trait reads the
     * latest field values even if the caller updated fields on the same
     * instance moments earlier.
     */
    public function updateProfileCompletion(User $user): void
    {
        try {
            // Reload from DB so we calculate against the persisted state.
            // This is the key fix — without it, the trait may read stale
            // attributes when called immediately after `$user->update()`.
            $user->refresh();

            $completion = $this->calculateProfileCompletion($user);

            $metadata = $user->metadata ?? [];
            $metadata['profile_completion']   = $completion;
            $metadata['last_profile_update']  = now()->toDateTimeString();

            $user->metadata = $metadata;
            $user->save();

            \Log::debug('Profile completion updated', [
                'user_id'    => $user->id,
                'completion' => $completion,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update profile completion', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get field label for display.
     */
    private function getFieldLabel(string $field): string
    {
        $labels = [
            'name'            => 'Full Name',
            'email'           => 'Email Address',
            'phone'           => 'Phone Number',
            'digital_address' => 'Digital Address',
            'region'          => 'Region',
            'location'        => 'Location',
            'gender'          => 'Gender',
            'dob'             => 'Date of Birth',
            'photo'           => 'Profile Photo',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
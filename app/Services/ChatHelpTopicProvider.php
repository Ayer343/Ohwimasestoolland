<?php

namespace App\Services;

use App\Models\User;

class ChatHelpTopicProvider
{
    /* ============================================================
       BASE TOPIC SETS
       ============================================================ */

    /**
     * Account-level topics shown to authenticated users only.
     * These make no sense for guests (no account to update).
     */
    protected array $accountTopics = [
        'How do I update my profile?',
        'How to change my password?',
        'I forgot how to login',
    ];

    /**
     * Feature/discovery topics shown to every authenticated user.
     */
    protected array $sharedTopics = [
        'What features are available?',
    ];

    /**
     * ⭐ NEW: Guest-only topics shown when nobody is logged in.
     * Focused on pre-registration questions.
     */
    protected array $guestTopics = [
        'How do I register my property?',
        'Is registration free?',
        'What documents do I need?',
        'How long does approval take?',
        'How do I pay estate dues?',
        'How do I contact support?',
        'How do I log in?',
    ];

    /* ============================================================
       ROLE TOPIC SETS
       ============================================================ */

    /**
     * Role-specific topic sets keyed by User::TYPE_* constants.
     */
    protected function roleTopics(): array
    {
        return [
            User::TYPE_SUPER_ADMIN => [
                'How to manage system settings?',
                'How to configure payment providers?',
                'How to generate invoices?',
                'How to manage users?',
                'How to view system statistics?',
                'How to manage registration plans?',
                'How to access admin dashboard?',
                'How to troubleshoot system issues?',
            ],

            User::TYPE_ADMIN => [
                'How to manage properties?',
                'How to process payments?',
                'How to generate monthly invoices?',
                'How to view payment statistics?',
                'How to manage landlords?',
                'How to handle payment verification?',
                'How to export payment data?',
                'How to filter properties?',
            ],

            User::TYPE_LANDLORD => [
                'How to view my properties?',
                'How to make a payment?',
                'How to verify my payment?',
                'How to check payment history?',
                'How to access my dashboard?',
                'How to view property details?',
                'How to contact support?',
                'How to update property information?',
            ],

            User::TYPE_TENANT => [
                'How to view property details?',
                'How to contact my landlord?',
                'How to check rental information?',
                'How to update my tenant profile?',
                'How to access tenant dashboard?',
                'How to report issues?',
                'How to view payment history?',
                'How to get support?',
            ],

            User::TYPE_FIELD_AGENT => [
                'How to register a property?',
                'How to view my assigned plans?',
                'How to submit a field inspection?',
                'How to record a payment collection?',
                'How to view my performance stats?',
                'How to update my field reports?',
            ],

            User::TYPE_DEVELOPER => [
                'How to view system health?',
                'How to manage maintenance mode?',
                'How to check error logs?',
                'How to run backups?',
                'How to manage developer billing?',
                'How to configure cache and queues?',
                'How to create a Super Admin?',
                'How to monitor performance metrics?',
            ],

            User::TYPE_SECURITY_PERSONNEL => [
                'How to view my shift schedule?',
                'How to check in or check out?',
                'How to request a shift swap?',
                'How to report an incident?',
                'How to manage security posts?',
                'How to complete a handover?',
                'How to set my availability?',
            ],

            User::TYPE_CONTRACTOR => [
                'How to view my contracts?',
                'How to update project progress?',
                'How to submit a milestone?',
                'How to manage my workers?',
                'How to send worker badges?',
                'How to view my calendar?',
                'How to export project reports?',
            ],

            User::TYPE_SANITATION_PERSONNEL => [
                'How to view collection requests?',
                'How to assign a request to personnel?',
                'How to update request status?',
                'How to manage collection zones?',
                'How to link a property?',
                'How to update my availability?',
                'How to manage sanitation settings?',
            ],
        ];
    }

    /**
     * Fallback topics when we don't have a role-specific set.
     */
    protected array $fallbackTopics = [
        'How do I register for an account?',
        'Where can I find my settings?',
        'The app is running slow, what should I do?',
    ];

    /* ============================================================
       PUBLIC API
       ============================================================ */

    /**
     * ⭐ CHANGED: Accept ?User so guests can call this.
     *
     * - $user is a User  → base account topics + role topics
     * - $user is null    → guest topics + shared topics
     *
     * The returned array is de-duplicated and re-indexed
     * so the frontend always gets a clean list of strings.
     */
    public function forUser(?User $user): array
    {
        // ---------- Guest branch ----------
        if (!$user) {
            return $this->dedupe(array_merge(
                $this->guestTopics,
                $this->sharedTopics,
            ));
        }

        // ---------- Authenticated branch ----------
        $roleTopics = $this->roleTopics()[$user->type]
            ?? $this->fallbackTopics;

        return $this->dedupe(array_merge(
            $this->accountTopics,
            $this->sharedTopics,
            $roleTopics,
        ));
    }

    /**
     * ⭐ NEW: Look up topics by role slug instead of legacy type.
     *
     * Useful if you later switch from `$user->type` (integer) to
     * `$user->primary_role->slug` (string). Pass `null` to get guest topics.
     */
    public function forRole(?string $roleSlug): array
    {
        if (!$roleSlug) {
            return $this->forUser(null);
        }

        // Map slug → legacy type
        $slugToType = [
            'super-admin'          => User::TYPE_SUPER_ADMIN,
            'admin'                => User::TYPE_ADMIN,
            'landlord'             => User::TYPE_LANDLORD,
            'tenant'               => User::TYPE_TENANT,
            'field-agent'          => User::TYPE_FIELD_AGENT,
            'developer'            => User::TYPE_DEVELOPER,
            'security-personnel'   => User::TYPE_SECURITY_PERSONNEL,
            'contractor'           => User::TYPE_CONTRACTOR,
            'sanitation-personnel' => User::TYPE_SANITATION_PERSONNEL,
        ];

        if (!isset($slugToType[$roleSlug])) {
            return $this->dedupe(array_merge(
                $this->accountTopics,
                $this->sharedTopics,
                $this->fallbackTopics,
            ));
        }

        return $this->dedupe(array_merge(
            $this->accountTopics,
            $this->sharedTopics,
            $this->roleTopics()[$slugToType[$roleSlug]] ?? [],
        ));
    }

    /* ============================================================
       INTERNAL HELPERS
       ============================================================ */

    /**
     * Remove duplicates while preserving order, and reset keys.
     */
    private function dedupe(array $topics): array
    {
        return array_values(array_unique($topics));
    }
}
<?php
// config/property_family_links.php

return [

    /*
    |--------------------------------------------------------------------------
    | Capacity Limits
    |--------------------------------------------------------------------------
    |
    | Enforced at validation time on both the landlord proposal endpoint
    | and the admin approval endpoint. Changing these affects existing
    | rows only when they are re-saved.
    |
    */

    'max_links_per_property' => 5,
    'max_links_per_landlord' => 15,

    /*
    |--------------------------------------------------------------------------
    | Relationship Options
    |--------------------------------------------------------------------------
    |
    | Used to populate the relationship dropdown and to validate the
    | incoming `relationship` field. Add a new option here and it appears
    | everywhere automatically — no blade edits required.
    |
    */

    'relationship_options' => [
        'spouse', 'son', 'daughter', 'parent',
        'sibling', 'relative', 'caretaker', 'other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Each key is a permission the landlord can grant to a linked family
    | member. The set below maps 1:1 to the landlord's web.php route
    | surface — every permission corresponds to one or more guarded
    | routes in the `family.link.active` group and its neighbours.
    |
    | The label on the right is the human-readable name shown in the
    | landlord's family-link UI. The blade also carries a longer
    | description and an icon for each — see
    | `landlord/family-links/index.blade.php` and
    | `landlord/family-links/for-property.blade.php`.
    |
    | Groups (informational; only used by the blade for visual layout):
    |
    |   basic       → view / edit / photos / notifications
    |   operations  → units / tenants / leases / maintenance / waste /
    |                 contracts
    |   finance     → view_financials / manage_invoices / make_payments
    |
    */

    'permissions' => [

        // ── Basic access ──────────────────────────────────────────
        'view'                  => 'View Property',
        'edit'                  => 'Edit Property',
        'upload_photos'         => 'Manage Photos',
        'receive_notifications' => 'Receive Notifications',

        // ── Property operations ───────────────────────────────────
        'manage_units'          => 'Manage Units',
        'manage_tenants'        => 'Manage Tenants',
        'manage_leases'         => 'Manage Leases',
        'manage_maintenance'    => 'Manage Maintenance',
        'manage_waste'          => 'Manage Waste Collection',
        'manage_contracts'      => 'Manage Construction Contracts',

        // ── Financial access ──────────────────────────────────────
        'view_financials'       => 'View Financials',
        'manage_invoices'       => 'Manage Invoices',
        'make_payments'         => 'Make Payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    |
    | Pre-selected on the proposal form and applied if the admin does not
    | override them at approval time.
    |
    */

    'default_permissions' => ['view', 'receive_notifications'],

    /*
    |--------------------------------------------------------------------------
    | Permission Presets
    |--------------------------------------------------------------------------
    |
    | Convenience bundles the landlord can apply with one click. The
    | blade renders one button per preset. `readonly` is the safety-first
    | default; `full` is everything except ownership changes (which are
    | never grantable via a family link).
    |
    */

    'permission_presets' => [
        'readonly' => [
            'label'       => 'Read-only',
            'description' => 'View everything, change nothing.',
            'permissions' => ['view', 'view_financials', 'receive_notifications'],
        ],

        'standard' => [
            'label'       => 'Standard',
            'description' => 'View and manage day-to-day operations.',
            'permissions' => [
                'view', 'edit', 'upload_photos', 'receive_notifications',
                'manage_units', 'manage_tenants', 'manage_maintenance',
                'view_financials', 'manage_waste',
            ],
        ],

        'full' => [
            'label'       => 'Full access',
            'description' => 'Everything except ownership changes.',
            'permissions' => [
                'view', 'edit', 'upload_photos', 'receive_notifications',
                'manage_units', 'manage_tenants', 'manage_leases',
                'manage_maintenance', 'manage_waste', 'manage_contracts',
                'view_financials', 'manage_invoices', 'make_payments',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Stage Approval Workflow
    |--------------------------------------------------------------------------
    |
    | Flow:
    |   1. Landlord proposes a family member
    |      → status: pending_landlord_confirmation
    |
    |   2. Landlord confirms their own proposal
    |      → status: pending_admin_review
    |
    |   3. Admin approves
    |      → status: approved
    |      → linked user created as PENDING
    |      → invitation dispatched
    |
    |   4. Family member accepts the invitation
    |      → user becomes ACTIVE
    |      → link fully active
    |
    | An invitation is NEVER sent before step 3.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Landlord Self-Confirmation Auto-Approve
    |--------------------------------------------------------------------------
    |
    | Relationships listed here skip the landlord's own confirmation step
    | — the proposal moves directly to `pending_admin_review` when
    | submitted. Useful for close relationships (e.g. spouse) where the
    | landlord's intent is unambiguous.
    |
    | Admin approval is ALWAYS required. This setting only affects the
    | first gate, never the second.
    |
    | Example: ['spouse'] to skip landlord confirmation for spouses.
    |
    */

    'auto_confirm_landlord_relationships' => [],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Channels used by the family-link notification listeners:
    |
    |   - NotifyAdminsOfFamilyLinkProposal   (admins, on landlord confirm)
    |   - NotifyLandlordToConfirmFamilyLink  (landlord, on proposal)
    |   - NotifyLandlordOfFamilyLinkDecision (landlord + linked user, on
    |                                         approve / reject / revoke)
    |
    | These strings must match Laravel's built-in notification channel
    | names, not human-readable labels. The valid built-in values are:
    |
    |   - 'database'  → writes to the `notifications` table (in-app bell).
    |                   Required for the UI to show anything.
    |   - 'mail'      → sends an email. NOT called 'email'.
    |   - 'broadcast' → pushes over websockets (needs a broadcaster).
    |   - 'vonage'    → SMS via Vonage (needs `laravel/vonage-notification-channel`).
    |   - 'slack'     → posts to Slack (needs `laravel/slack-notification-channel`).
    |
    | The name 'email' is NOT valid and will throw "Driver [email] not
    | supported" if passed to a Notification. Use 'mail'.
    |
    | 'database' is always merged in by the listeners regardless of what
    | this list contains, so the in-app notification bell is guaranteed
    | to work even if you forget to list it here. To disable email, drop
    | 'mail'. To disable SMS, drop 'vonage' (or your custom SMS channel).
    |
    */

    'notify_channels' => ['database', 'mail'],

];
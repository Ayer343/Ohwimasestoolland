<?php

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
    | Each key is a permission the admin can grant to a linked family
    | member. Blades render them as checkboxes; authorization traits
    | consult them via the `permissions` JSON column on the link row.
    |
    */

    'permissions' => [
        'view'                   => 'View property details',
        'edit'                   => 'Edit property details',
        'receive_notifications'  => 'Receive notifications',
        'manage_tenants'         => 'Manage tenants',
        'manage_units'           => 'Manage units',
        'upload_photos'          => 'Upload photos',
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
    | Channels used to notify admins when a proposal is awaiting review,
    | and to notify landlords when a proposal is awaiting their own
    | confirmation. The linked family member receives their welcome
    | invitation via the UserInvitationService channel resolver.
    |
    | Allowed: 'email', 'sms', 'whatsapp'
    |
    */

    'notify_channels' => ['email', 'sms'],

];
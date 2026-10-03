<?php

/**
 * =============================================================================
 * LEASE CONFIGURATION
 * =============================================================================
 *
 * Central configuration for the rental agreement / lease subsystem.
 *
 * The `ghana` block encodes the legal rules from the Rent Act, 1963 (Act 220),
 * Section 25(5). The `invoice` block controls how PropertyUnitInvoice records
 * are generated, voided, and reminded. The `defaults` block provides safe
 * fallback values for new leases when the landlord leaves fields blank.
 *
 * Any value here can be overridden via .env, making it easy to adjust
 * compliance behavior without a code deploy.
 * =============================================================================
 */

return [

    /*
    |--------------------------------------------------------------------------
    | GHANA — RENT ACT COMPLIANCE
    |--------------------------------------------------------------------------
    |
    | The Rent Act, 1963 (Act 220) caps the amount of advance rent a landlord
    | may demand:
    |
    |   • Tenancy of 6 months or less ......... 1 month advance
    |   • Tenancy exceeding 6 months .......... 6 months advance
    |   • Renewal of an existing tenancy ...... 3 months advance
    |
    | Landlords cannot *demand* more than the cap, but tenants may voluntarily
    | *offer* more. When they do, the lease is flagged as
    | `exceeds_legal_limit` for reporting purposes only — the app does not
    | block the transaction by default.
    |
    */

    'ghana' => [

        /*
        |----------------------------------------------------------------------
        | Legal Maximum Advance Rent (in months)
        |----------------------------------------------------------------------
        |
        | Used by:
        |   • PropertyUnitLeaseController::createLease()
        |   • PropertyUnitLeaseController::renewLease()
        |   • RentalAgreement::renew()
        |   • The `ghana_advance_compliant` validation rule
        |
        */
        'legal_max_advance_months' => [
            'new_tenancy'   => (int) env('GHANA_MAX_ADVANCE_NEW_TENANCY', 6),
            'renewal'       => (int) env('GHANA_MAX_ADVANCE_RENEWAL', 3),
            'short_tenancy' => (int) env('GHANA_MAX_ADVANCE_SHORT_TENANCY', 1),
        ],

        /*
        |----------------------------------------------------------------------
        | Short Tenancy Threshold
        |----------------------------------------------------------------------
        |
        | A tenancy is considered "short" when its duration is at or below
        | this number of months. Short tenancies have a stricter advance cap
        | (1 month) than longer tenancies (6 months).
        |
        */
        'short_tenancy_threshold_months' => (int) env('GHANA_SHORT_TENANCY_MONTHS', 6),

        /*
        |----------------------------------------------------------------------
        | Enforcement Mode
        |----------------------------------------------------------------------
        |
        | How the app should respond when a lease exceeds the legal cap:
        |
        |   • 'warn'   → Allow, but flag the lease as `exceeds_legal_limit`
        |                and surface warnings in the UI. (Recommended for
        |                the Ghanaian market, where 2-year advances are common.)
        |
        |   • 'block'  → Reject the lease at validation time, unless the
        |                tenant has signed an acknowledgement.
        |
        |   • 'silent' → Allow with no warnings (not recommended — removes
        |                the audit trail).
        |
        */
        'enforce_compliance' => env('GHANA_ENFORCE_ADVANCE_COMPLIANCE', 'warn'),

        /*
        |----------------------------------------------------------------------
        | Acknowledgement Requirement
        |----------------------------------------------------------------------
        |
        | When this is true and a lease exceeds the legal cap, the landlord
        | must tick the "tenant voluntarily offered" acknowledgement checkbox
        | before the lease can be saved. Set to false to skip entirely.
        |
        */
        'require_acknowledgement' => env('GHANA_REQUIRE_ADVANCE_ACKNOWLEDGEMENT', true),

        /*
        |----------------------------------------------------------------------
        | Governing Law Reference
        |----------------------------------------------------------------------
        |
        | Displayed on generated PDFs and used in the lease terms text.
        |
        */
        'governing_law' => env('GHANA_GOVERNING_LAW', 'Rent Act, 1963 (Act 220)'),

        /*
        |----------------------------------------------------------------------
        | Currency
        |----------------------------------------------------------------------
        |
        | Used for formatting and default revenue calculations. Ghana uses GHS.
        |
        */
        'currency' => [
            'code'   => env('LEASE_CURRENCY_CODE', 'GHS'),
            'symbol' => env('LEASE_CURRENCY_SYMBOL', 'GH₵'),
        ],

        /*
        |----------------------------------------------------------------------
        | Reminder Configuration
        |----------------------------------------------------------------------
        |
        | How far in advance to warn tenants whose advance-rent period is
        | ending and whose monthly phase is about to begin.
        |
        */
        'advance_end_reminder_days' => (int) env('LEASE_ADVANCE_REMINDER_DAYS', 14),

        /*
        |----------------------------------------------------------------------
        | Phase Transition Safety Window
        |----------------------------------------------------------------------
        |
        | A daily job flips leases from `advance` to `monthly` phase when
        | `advance_rent_period_end` is in the past. To avoid double-flips
        | or missed days, the transition runs once per day and marks the
        | lease's metadata with `advance_to_monthly_logged_at`.
        |
        | This window controls how far back the transition job looks in
        | case it missed a few days (e.g., server was down).
        |
        */
        'phase_transition_lookback_days' => (int) env('LEASE_PHASE_LOOKBACK_DAYS', 7),

    ],

    /*
    |--------------------------------------------------------------------------
    | LEASE DEFAULTS
    |--------------------------------------------------------------------------
    |
    | Fallback values applied when a landlord creates a lease and leaves
    | optional fields blank. These are also mirrored in the RentalAgreement
    | model's `boot()` method for consistency.
    |
    */

    'defaults' => [

        /*
        |----------------------------------------------------------------------
        | Payment Due Day
        |----------------------------------------------------------------------
        |
        | Day of the month when rent is due. Capped at 28 to avoid
        | month-length edge cases (February).
        |
        */
        'payment_due_day' => (int) env('LEASE_DEFAULT_DUE_DAY', 5),

        /*
        |----------------------------------------------------------------------
        | Grace Period
        |----------------------------------------------------------------------
        |
        | Days after the due date before late fees apply. 0 = no grace.
        |
        */
        'grace_period_days' => (int) env('LEASE_DEFAULT_GRACE_DAYS', 0),

        /*
        |----------------------------------------------------------------------
        | Notice Period
        |----------------------------------------------------------------------
        |
        | Required written notice (in days) before either party can
        | terminate the lease. Must be between 15 and 90.
        |
        */
        'notice_period_days' => (int) env('LEASE_DEFAULT_NOTICE_DAYS', 30),

        /*
        |----------------------------------------------------------------------
        | Deposit Payment Method
        |----------------------------------------------------------------------
        |
        | 'upfront'      → Full security deposit paid before move-in
        | 'installment'  → Split across N months added to rent
        |
        */
        'deposit_payment_method' => env('LEASE_DEFAULT_DEPOSIT_METHOD', 'upfront'),

        /*
        |----------------------------------------------------------------------
        | Advance Rent Default (in months)
        |----------------------------------------------------------------------
        |
        | Pre-filled value in the create-lease form's advance-rent dropdown.
        | 6 is the legal maximum for a new long tenancy — good default for
        | compliance; landlords can change it if the tenant offers more.
        |
        */
        'advance_rent_months' => (int) env('LEASE_DEFAULT_ADVANCE_MONTHS', 6),

        /*
        |----------------------------------------------------------------------
        | Payment Frequency Default
        |----------------------------------------------------------------------
        |
        | 'monthly'      → Advance + monthly payments (recommended, compliant)
        | 'advance_only' → Entire lease term paid upfront
        |
        */
        'payment_frequency' => env('LEASE_DEFAULT_PAYMENT_FREQUENCY', 'monthly'),

        /*
        |----------------------------------------------------------------------
        | Security Deposit Multiplier
        |----------------------------------------------------------------------
        |
        | Suggested security deposit expressed as a multiple of monthly rent.
        | 2.0 = two months' rent.
        |
        */
        'deposit_multiplier' => (float) env('LEASE_DEFAULT_DEPOSIT_MULTIPLIER', 2.0),

        /*
        |----------------------------------------------------------------------
        | Late Fee Defaults
        |----------------------------------------------------------------------
        |
        | Percentage + fixed amounts default to 0 (no late fee).
        | Landlords can enable either or both.
        |
        */
        'late_fee' => [
            'percentage' => (float) env('LEASE_DEFAULT_LATE_FEE_PCT', 0),
            'fixed'      => (float) env('LEASE_DEFAULT_LATE_FEE_FIXED', 0),
        ],

        /*
        |----------------------------------------------------------------------
        | Lease Type
        |----------------------------------------------------------------------
        |
        | 'fixed'          → Specific end date
        | 'month_to_month' → Ongoing, terminated with notice
        |
        */
        'lease_type' => env('LEASE_DEFAULT_TYPE', 'fixed'),

        /*
        |----------------------------------------------------------------------
        | Lease Duration (in months)
        |----------------------------------------------------------------------
        |
        | Default pre-selected duration in the create-lease form.
        |
        */
        'duration_months' => (int) env('LEASE_DEFAULT_DURATION', 12),

        /*
        |----------------------------------------------------------------------
        | Include Standard Terms
        |----------------------------------------------------------------------
        |
        | When true, the generated lease PDF includes the standard 20-clause
        | boilerplate (utilities, maintenance, pets, governing law, etc.).
        |
        */
        'include_standard_terms' => (bool) env('LEASE_INCLUDE_STANDARD_TERMS', true),

        /*
        |----------------------------------------------------------------------
        | Send to Tenant by Default
        |----------------------------------------------------------------------
        |
        | Pre-checks the "Send Lease to Tenant for Signature" checkbox on
        | the create-lease form.
        |
        */
        'send_to_tenant' => (bool) env('LEASE_SEND_TO_TENANT_BY_DEFAULT', true),

        /*
        |----------------------------------------------------------------------
        | Invitation Channels
        |----------------------------------------------------------------------
        |
        | Default channels pre-selected when sending the lease invitation.
        | Any of: 'email', 'sms', 'whatsapp'.
        |
        */
        'invitation_channels' => array_filter(explode(',', env('LEASE_DEFAULT_CHANNELS', 'email'))),

    ],

    /*
    |--------------------------------------------------------------------------
    | INVOICE — PROPERTY UNIT INVOICES
    |--------------------------------------------------------------------------
    |
    | Controls how PropertyUnitInvoice rows are generated, voided, and
    | reminded. These tie directly into the controllers and the scheduled
    | tasks in App\Console\Kernel.
    |
    */

    'invoice' => [

        /*
        |----------------------------------------------------------------------
        | Auto-Generate on Lease Create
        |----------------------------------------------------------------------
        |
        | When true, creating a lease immediately generates:
        |   • One advance-rent invoice (marked paid)
        |   • One monthly-rent invoice per month in the post-advance phase
        |   • One security-deposit invoice (if upfront)
        |
        */
        'auto_generate_on_lease_create' => (bool) env('INVOICE_AUTO_GENERATE', true),

        /*
        |----------------------------------------------------------------------
        | Auto-Generate on Lease Renew
        |----------------------------------------------------------------------
        |
        | Same as above, but applies to renewals. Kept separate so you can
        | disable auto-generation for renewals while keeping it for new leases
        | (or vice versa).
        |
        */
        'auto_generate_on_lease_renew' => (bool) env('INVOICE_AUTO_GENERATE_ON_RENEW', true),

        /*
        |----------------------------------------------------------------------
        | Void Future Invoices on Termination
        |----------------------------------------------------------------------
        |
        | When a lease is terminated, any invoice with a due date after the
        | termination date is voided. This prevents stale reminders.
        |
        */
        'void_future_on_terminate' => (bool) env('INVOICE_VOID_FUTURE_ON_TERMINATE', true),

        /*
        |----------------------------------------------------------------------
        | Mark Overdue After (days)
        |----------------------------------------------------------------------
        |
        | Number of days past the due date before a pending or partial invoice
        | is flipped to `overdue`. The scheduled job
        | `mark-property-unit-invoices-overdue` uses this value.
        |
        */
        'mark_overdue_after_days' => (int) env('INVOICE_OVERDUE_DAYS', 1),

        /*
        |----------------------------------------------------------------------
        | Reminder Days Before Due
        |----------------------------------------------------------------------
        |
        | How many days before an invoice's due date to send the tenant a
        | reminder. Set to 0 to disable reminders.
        |
        */
        'reminder_days_before' => (int) env('INVOICE_REMINDER_DAYS_BEFORE', 7),

        /*
        |----------------------------------------------------------------------
        | Reminder Cooldown (hours)
        |----------------------------------------------------------------------
        |
        | Minimum time between reminders for the same invoice, to avoid
        | spamming tenants if the reminder job runs more than once.
        |
        */
        'reminder_cooldown_hours' => (int) env('INVOICE_REMINDER_COOLDOWN_HOURS', 24),

        /*
        |----------------------------------------------------------------------
        | Advance-Rent Invoice Reference Prefix
        |----------------------------------------------------------------------
        |
        | Used when generating the `reference` column on advance-rent
        | invoices. Example: ADV-AB12CD34
        |
        */
        'reference_prefix' => [
            'advance_rent'     => env('INVOICE_REF_ADVANCE', 'ADV'),
            'monthly_rent'     => env('INVOICE_REF_MONTHLY', 'MR'),
            'security_deposit' => env('INVOICE_REF_DEPOSIT', 'DEP'),
            'utility_deposit'  => env('INVOICE_REF_UTILITY', 'UTIL'),
            'late_fee'         => env('INVOICE_REF_LATE_FEE', 'LATE'),
            'early_termination'=> env('INVOICE_REF_TERMINATION', 'TERM'),
            'other'            => env('INVOICE_REF_OTHER', 'INV'),
        ],

        /*
        |----------------------------------------------------------------------
        | Monthly Summary Recipients
        |----------------------------------------------------------------------
        |
        | Who receives the monthly PropertyUnitInvoice summary notification.
        | Any combination of: 'admin', 'super_admin', 'landlord'.
        |
        | The scheduled task `monthly-property-unit-invoice-summary` uses
        | this list to decide who to notify.
        |
        */
        'summary_recipients' => array_filter(
            explode(',', env('INVOICE_SUMMARY_RECIPIENTS', 'admin,super_admin'))
        ),

        /*
        |----------------------------------------------------------------------
        | Overdue Notification Behavior
        |----------------------------------------------------------------------
        |
        | When an invoice flips to `overdue`, should the tenant be notified?
        | Should late fees auto-apply? Both are opt-in per project needs.
        |
        */
        'notify_tenant_on_overdue' => (bool) env('INVOICE_NOTIFY_ON_OVERDUE', true),
        'auto_apply_late_fees'     => (bool) env('INVOICE_AUTO_APPLY_LATE_FEES', false),

        /*
        |----------------------------------------------------------------------
        | Retention / Archiving
        |----------------------------------------------------------------------
        |
        | How long to keep paid invoices before they can be archived.
        | (Not the same as deleting — see YearEndArchiveService.)
        |
        */
        'paid_invoice_retention_months' => (int) env('INVOICE_PAID_RETENTION_MONTHS', 12),

    ],

    /*
    |--------------------------------------------------------------------------
    | TENANT NOTIFICATIONS
    |--------------------------------------------------------------------------
    |
    | Templates and behavior for tenant-facing lease notifications.
    |
    */

    'notifications' => [

        /*
        |----------------------------------------------------------------------
        | Advance Period Ending
        |----------------------------------------------------------------------
        */
        'advance_ending' => [
            'enabled'         => (bool) env('LEASE_NOTIFY_ADVANCE_ENDING', true),
            'icon'            => 'fas fa-clock text-warning',
            'priority'        => 1,
        ],

        /*
        |----------------------------------------------------------------------
        | Phase Transitioned to Monthly
        |----------------------------------------------------------------------
        */
        'phase_transition' => [
            'enabled'         => (bool) env('LEASE_NOTIFY_PHASE_TRANSITION', true),
            'icon'            => 'fas fa-calendar-check text-info',
            'priority'        => 1,
        ],

        /*
        |----------------------------------------------------------------------
        | Lease Signed (by either party)
        |----------------------------------------------------------------------
        */
        'lease_signed' => [
            'enabled'         => (bool) env('LEASE_NOTIFY_ON_SIGNATURE', true),
            'icon'            => 'fas fa-signature text-success',
            'priority'        => 1,
        ],

        /*
        |----------------------------------------------------------------------
        | Lease Terminated
        |----------------------------------------------------------------------
        */
        'lease_terminated' => [
            'enabled'         => (bool) env('LEASE_NOTIFY_ON_TERMINATION', true),
            'icon'            => 'fas fa-times-circle text-danger',
            'priority'        => 1,
        ],

        /*
        |----------------------------------------------------------------------
        | Advance-Rent Compliance Warning (to admins)
        |----------------------------------------------------------------------
        */
        'compliance_alert' => [
            'enabled'         => (bool) env('LEASE_NOTIFY_COMPLIANCE_ALERTS', true),
            'icon'            => 'fas fa-exclamation-triangle text-warning',
            'priority'        => 2,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | VALIDATION MESSAGES
    |--------------------------------------------------------------------------
    |
    | Custom messages returned by the validation rules registered in
    | AppServiceProvider (ghana_advance_compliant, advance_rent_months, etc.).
    |
    */

    'validation_messages' => [
        'advance_rent_months'       => 'Advance rent must be between 1 and 60 months.',
        'advance_rent_within_term'  => 'Advance rent cannot exceed the total lease duration.',
        'ghana_advance_short'       => 'For tenancies of 6 months or less, the legal maximum advance is 1 month (Rent Act 1963, s.25(5)).',
        'ghana_advance_renewal'     => 'On renewal, the legal maximum advance is 3 months (Rent Act 1963, s.25(5)).',
        'ghana_advance_long'        => 'The advance rent you entered exceeds the legal maximum of :max months. '
                                     . 'The Rent Act permits this only if the tenant voluntarily offers it. '
                                     . 'Please confirm the acknowledgement checkbox to proceed.',
    ],

];
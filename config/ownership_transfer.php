<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ownership Transfer Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for the property ownership
    | transfer system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Bulk Transfer Settings
    |--------------------------------------------------------------------------
    |
    | Enable or disable bulk transfer functionality.
    |
    */
    'enable_bulk_transfer' => env('OWNERSHIP_TRANSFER_ENABLE_BULK', true),

    /*
    |--------------------------------------------------------------------------
    | Digital Signature Settings
    |--------------------------------------------------------------------------
    |
    | Configure digital signature requirements for ownership transfers.
    |
    */
    'require_digital_signature' => env('OWNERSHIP_TRANSFER_REQUIRE_SIGNATURE', false),
    'digital_signature_threshold' => env('OWNERSHIP_TRANSFER_SIGNATURE_THRESHOLD', 1000000),

    /*
    |--------------------------------------------------------------------------
    | Auto Complete on Approval
    |--------------------------------------------------------------------------
    |
    | If set to true, transfers will be automatically completed when approved
    | by an admin. If false, admin needs to click the "Complete" button separately.
    |
    */
    'auto_complete_on_approval' => env('OWNERSHIP_TRANSFER_AUTO_COMPLETE', true),

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configure file upload settings for transfer documents.
    |
    */
    'max_file_size' => env('OWNERSHIP_TRANSFER_MAX_FILE_SIZE', 5), // MB
    'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configure webhook notifications for transfer events.
    |
    */
    'webhook_enabled' => env('OWNERSHIP_TRANSFER_WEBHOOK_ENABLED', false),
    'webhook_url' => env('OWNERSHIP_TRANSFER_WEBHOOK_URL', null),
    'webhook_secret' => env('OWNERSHIP_TRANSFER_WEBHOOK_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Expiry Settings
    |--------------------------------------------------------------------------
    |
    | Configure how long pending transfers remain valid.
    |
    */
    'expiry_days' => env('OWNERSHIP_TRANSFER_EXPIRY_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Full Text Search
    |--------------------------------------------------------------------------
    |
    | Enable or disable full text search functionality.
    |
    */
    'full_text_search' => env('OWNERSHIP_TRANSFER_FULL_TEXT_SEARCH', false),

    /*
    |--------------------------------------------------------------------------
    | Approval Rules
    |--------------------------------------------------------------------------
    |
    | Define approval rules for ownership transfers.
    |
    */
    'approval_rules' => [
        /*
        'amount_limit' => [
            'type' => 'amount_limit',
            'max_amount' => 5000000,
        ],
        'property_type' => [
            'type' => 'property_type',
            'restricted_types' => ['commercial'],
        ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Steps
    |--------------------------------------------------------------------------
    |
    | Define the workflow steps for ownership transfers.
    |
    */
    'workflow' => [
        'approval',
        'verification',
        'completion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash Management Settings
    |--------------------------------------------------------------------------
    |
    | Configure how trashed transfers are handled.
    |
    */
    'trash_retention_days' => env('OWNERSHIP_TRANSFER_TRASH_RETENTION_DAYS', 90),
    
    /*
    |--------------------------------------------------------------------------
    | Minimum Days Before Permanent Delete
    |--------------------------------------------------------------------------
    |
    | Number of days a transfer must remain in trash before it can be permanently deleted.
    | Set to 0 to allow immediate permanent deletion.
    | Set to a positive integer to require a waiting period (e.g., 30 days).
    |
    */
    'min_days_before_permanent_delete' => env('OWNERSHIP_TRANSFER_MIN_DAYS_BEFORE_DELETE', 0),

    /*
    |--------------------------------------------------------------------------
    | Scheduler Token
    |--------------------------------------------------------------------------
    |
    | Token for external scheduler webhook authentication.
    |
    */
    'scheduler_token' => env('OWNERSHIP_TRANSFER_SCHEDULER_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notification channels for transfer events.
    |
    */
    'notifications' => [
        'email' => [
            'enabled' => true,
            'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Property Management System'),
        ],
        'sms' => [
            'enabled' => env('OWNERSHIP_TRANSFER_SMS_ENABLED', false),
            'provider' => env('OWNERSHIP_TRANSFER_SMS_PROVIDER', 'twilio'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Settings
    |--------------------------------------------------------------------------
    |
    | Configure transfer certificate generation.
    |
    */
    'certificate' => [
        'enabled' => true,
        'company_name' => env('APP_NAME', 'Property Management System'),
        'company_logo' => env('OWNERSHIP_TRANSFER_CERTIFICATE_LOGO', null),
        'signature_image' => env('OWNERSHIP_TRANSFER_CERTIFICATE_SIGNATURE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for transfer data.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => env('OWNERSHIP_TRANSFER_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for transfer submissions.
    |
    */
    'rate_limit' => [
        'enabled' => env('OWNERSHIP_TRANSFER_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('OWNERSHIP_TRANSFER_RATE_LIMIT_ATTEMPTS', 10),
        'decay_minutes' => env('OWNERSHIP_TRANSFER_RATE_LIMIT_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure audit logging for transfer actions.
    |
    */
    'audit_logging' => [
        'enabled' => env('OWNERSHIP_TRANSFER_AUDIT_LOGGING', true),
        'log_ip_address' => true,
        'log_user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Custom validation rules for transfer fields.
    |
    */
    'validation' => [
        'phone_regex' => '/^(\+?[0-9]{10,15})$/',
        'name_max_length' => 255,
        'reference_prefix' => 'TRANS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configure dashboard display settings.
    |
    */
    'dashboard' => [
        'show_recent_transfers' => true,
        'recent_transfers_limit' => 10,
        'show_statistics' => true,
        'show_charts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configure export functionality.
    |
    */
    'export' => [
        'formats' => ['csv', 'excel', 'pdf'],
        'default_format' => 'csv',
        'max_records' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transfer Types
    |--------------------------------------------------------------------------
    |
    | Define available transfer types.
    |
    */
    'transfer_types' => [
        'sale' => 'Sale',
        'gift' => 'Gift',
        'inheritance' => 'Inheritance',
        'exchange' => 'Exchange',
    ],

    /*
    |--------------------------------------------------------------------------
    | Required Documents
    |--------------------------------------------------------------------------
    |
    | Define required documents for each document type.
    |
    */
    'required_documents' => [
        'sale_deed' => ['sale_agreement', 'title_deed', 'id_proof'],
        'gift_deed' => ['gift_deed', 'title_deed', 'id_proof'],
        'will_probate' => ['will_document', 'probate_certificate', 'id_proof'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transfer Reversal Settings
    |--------------------------------------------------------------------------
    |
    | Configure transfer reversal functionality for mistaken transfers.
    |
    */
    'reversal' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Transfer Reversal
        |--------------------------------------------------------------------------
        |
        | Enable or disable the ability to request transfer reversals.
        |
        */
        'enabled' => env('OWNERSHIP_TRANSFER_REVERSAL_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Reversal Window Days
        |--------------------------------------------------------------------------
        |
        | Number of days after a transfer is completed during which a reversal
        | can be requested. Set to 0 to allow unlimited time.
        |
        */
        'reversal_window_days' => env('OWNERSHIP_TRANSFER_REVERSAL_WINDOW_DAYS', 30),

        /*
        |--------------------------------------------------------------------------
        | Admin Review Deadline Days
        |--------------------------------------------------------------------------
        |
        | Number of days an admin has to review a reversal request before it expires.
        |
        */
        'review_deadline_days' => env('OWNERSHIP_TRANSFER_REVIEW_DEADLINE_DAYS', 7),

        /*
        |--------------------------------------------------------------------------
        | Require Both Parties Consent
        |--------------------------------------------------------------------------
        |
        | If true, both the current owner (who transferred away) and the new owner
        | must agree to the reversal before admin can approve.
        |
        */
        'require_both_parties_consent' => env('OWNERSHIP_TRANSFER_REQUIRE_BOTH_CONSENT', false),

        /*
        |--------------------------------------------------------------------------
        | Require Admin Approval
        |--------------------------------------------------------------------------
        |
        | Require admin approval for reversal requests. If false, reversals are
        | automatically processed when requested (only recommended for trusted systems).
        |
        */
        'require_admin_approval' => env('OWNERSHIP_TRANSFER_REQUIRE_ADMIN_APPROVAL', true),

        /*
        |--------------------------------------------------------------------------
        | Allow Multiple Reversals
        |--------------------------------------------------------------------------
        |
        | Allow a transfer to be reversed multiple times. If false, a transfer
        | can only be reversed once.
        |
        */
        'allow_multiple_reversals' => env('OWNERSHIP_TRANSFER_ALLOW_MULTIPLE_REVERSALS', false),

        /*
        |--------------------------------------------------------------------------
        | Restore Original Document
        |--------------------------------------------------------------------------
        |
        | When reversing a transfer, restore the original transfer document or
        | create a new reversal document.
        |
        */
        'restore_original_document' => env('OWNERSHIP_TRANSFER_RESTORE_DOCUMENT', false),

        /*
        |--------------------------------------------------------------------------
        | Notify All Parties on Reversal
        |--------------------------------------------------------------------------
        |
        | Send email notifications to all parties when a reversal is requested,
        | approved, rejected, or completed.
        |
        */
        'notify_all_parties' => env('OWNERSHIP_TRANSFER_NOTIFY_PARTIES', true),

        /*
        |--------------------------------------------------------------------------
        | Auto-Archive After Reversal
        |--------------------------------------------------------------------------
        |
        | Automatically archive the reversal transfer record after a certain number
        | of days. Set to 0 to never auto-archive.
        |
        */
        'auto_archive_after_days' => env('OWNERSHIP_TRANSFER_REVERSAL_AUTO_ARCHIVE', 90),

        /*
        |--------------------------------------------------------------------------
        | Reversal Fee
        |--------------------------------------------------------------------------
        |
        | Fee charged for processing a reversal (if applicable).
        | Set to 0 for no fee.
        |
        */
        'reversal_fee' => env('OWNERSHIP_TRANSFER_REVERSAL_FEE', 0),

        /*
        |--------------------------------------------------------------------------
        | Fee Payment Required
        |--------------------------------------------------------------------------
        |
        | Require payment of reversal fee before processing the reversal.
        |
        */
        'require_fee_payment' => env('OWNERSHIP_TRANSFER_REQUIRE_FEE_PAYMENT', false),

        /*
        |--------------------------------------------------------------------------
        | Restore Units on Reversal
        |--------------------------------------------------------------------------
        |
        | Automatically restore property units to the original landlord on reversal.
        |
        */
        'restore_units' => env('OWNERSHIP_TRANSFER_RESTORE_UNITS', true),

        /*
        |--------------------------------------------------------------------------
        | Restore Rental Agreements
        |--------------------------------------------------------------------------
        |
        | Automatically restore rental agreements to the original landlord on reversal.
        |
        */
        'restore_rental_agreements' => env('OWNERSHIP_TRANSFER_RESTORE_AGREEMENTS', true),

        /*
        |--------------------------------------------------------------------------
        | Notification Templates for Reversal
        |--------------------------------------------------------------------------
        |
        | Configure notification templates for reversal events.
        |
        */
        'notifications' => [
            'reversal_requested' => [
                'subject' => 'Transfer Reversal Request Submitted',
                'template' => 'emails.transfer-reversal-requested',
            ],
            'reversal_approved' => [
                'subject' => 'Transfer Reversal Approved',
                'template' => 'emails.transfer-reversal-approved',
            ],
            'reversal_rejected' => [
                'subject' => 'Transfer Reversal Request Rejected',
                'template' => 'emails.transfer-reversal-rejected',
            ],
            'reversal_completed' => [
                'subject' => 'Transfer Reversal Completed',
                'template' => 'emails.transfer-reversal-completed',
            ],
            'reversal_expired' => [
                'subject' => 'Transfer Reversal Request Expired',
                'template' => 'emails.transfer-reversal-expired',
            ],
            'reversal_reminder' => [
                'subject' => 'Pending Transfer Reversal Request',
                'template' => 'emails.transfer-reversal-reminder',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Reminder Settings
        |--------------------------------------------------------------------------
        |
        | Send reminders for pending reversal requests.
        |
        */
        'reminders' => [
            'enabled' => env('OWNERSHIP_TRANSFER_REVERSAL_REMINDERS', true),
            'send_to_admin' => true,
            'send_to_landlord' => true,
            'reminder_days' => [3, 1], // Send reminder 3 days and 1 day before expiry
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin Notifications for Reversal
        |--------------------------------------------------------------------------
        |
        | Notify admins when reversal requests are submitted.
        |
        */
        'admin_notifications' => [
            'enabled' => env('OWNERSHIP_TRANSFER_ADMIN_REVERSAL_NOTIFY', true),
            'on_request' => true,
            'on_approval' => true,
            'on_rejection' => true,
            'on_completion' => true,
            'on_expiry' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Reversal Reason Options
        |--------------------------------------------------------------------------
        |
        | Predefined reasons for requesting a reversal. Set to empty array to
        | allow free-text input only.
        |
        */
        'reason_options' => [
            'wrong_recipient' => 'Transferred to the wrong person',
            'accidental_transfer' => 'Accidental transfer',
            'fraudulent_transfer' => 'Fraudulent or unauthorized transfer',
            'mutual_agreement' => 'Mutual agreement between parties',
            'legal_dispute' => 'Legal dispute or court order',
            'other' => 'Other (please specify)',
        ],

        /*
        |--------------------------------------------------------------------------
        | Maximum Reversal Attempts
        |--------------------------------------------------------------------------
        |
        | Maximum number of times a user can request reversal for the same transfer.
        |
        */
        'max_attempts' => env('OWNERSHIP_TRANSFER_MAX_REVERSAL_ATTEMPTS', 3),

        /*
        |--------------------------------------------------------------------------
        | Cooldown Period Between Attempts
        |--------------------------------------------------------------------------
        |
        | Days a user must wait before submitting another reversal request for the
        | same transfer after a rejection.
        |
        */
        'cooldown_days' => env('OWNERSHIP_TRANSFER_REVERSAL_COOLDOWN_DAYS', 7),

        /*
        |--------------------------------------------------------------------------
        | Reversal Audit Trail
        |--------------------------------------------------------------------------
        |
        | Log detailed audit trail for reversal actions.
        |
        */
        'audit_trail' => [
            'enabled' => true,
            'log_ip' => true,
            'log_user_agent' => true,
            'log_request_data' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Webhook Events for Reversal
        |--------------------------------------------------------------------------
        |
        | Trigger webhooks for reversal-related events.
        |
        */
        'webhook_events' => [
            'reversal_requested' => true,
            'reversal_approved' => true,
            'reversal_rejected' => true,
            'reversal_completed' => true,
            'reversal_expired' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Maximum Reversal Age
        |--------------------------------------------------------------------------
        |
        | Maximum age (in days) of a completed transfer that can be reversed.
        | Set to 0 to disable age limit.
        |
        */
        'max_age_days' => env('OWNERSHIP_TRANSFER_MAX_REVERSAL_AGE', 180),

        /*
        |--------------------------------------------------------------------------
        | Restriction on Reversed Properties
        |--------------------------------------------------------------------------
        |
        | Prevent properties that have been reversed from being transferred again
        | for a certain number of days.
        |
        */
        'restriction_after_reversal_days' => env('OWNERSHIP_TRANSFER_RESTRICTION_DAYS', 30),

        /*
        |--------------------------------------------------------------------------
        | Automatic Reversal for Fraud Detection
        |--------------------------------------------------------------------------
        |
        | Automatically reverse transfers that match certain fraud detection rules.
        |
        */
        'auto_reversal_on_fraud' => env('OWNERSHIP_TRANSFER_AUTO_REVERSE_FRAUD', false),

        /*
        |--------------------------------------------------------------------------
        | Fraud Detection Rules
        |--------------------------------------------------------------------------
        |
        | Rules for automatically flagging potentially fraudulent transfers.
        |
        */
        'fraud_detection' => [
            'max_sale_amount' => env('OWNERSHIP_TRANSFER_FRAUD_MAX_AMOUNT', 10000000),
            'require_verification_for_amount' => env('OWNERSHIP_TRANSFER_VERIFICATION_AMOUNT', 5000000),
            'suspicious_keywords' => ['urgent', 'quick sale', 'cash only'],
        ],

        /*
        |--------------------------------------------------------------------------
        | Reversal Report Generation
        |--------------------------------------------------------------------------
        |
        | Generate reports for reversal requests.
        |
        */
        'reporting' => [
            'enabled' => env('OWNERSHIP_TRANSFER_REVERSAL_REPORTING', true),
            'auto_generate_weekly' => env('OWNERSHIP_TRANSFER_AUTO_REPORTS', true),
            'send_to_admin' => env('OWNERSHIP_TRANSFER_SEND_REPORTS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Archival Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic archival of landlord accounts after property transfers.
    |
    */
    'account_archival' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Account Archival
        |--------------------------------------------------------------------------
        |
        | Enable or disable automatic archival of landlord accounts.
        |
        */
        'enabled' => env('OWNERSHIP_TRANSFER_ARCHIVAL_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Archival Delay Days
        |--------------------------------------------------------------------------
        |
        | Number of days after property transfer before archiving the landlord account.
        | Set to 0 for immediate archival (if no properties remain).
        |
        */
        'archive_delay_days' => env('OWNERSHIP_TRANSFER_ARCHIVE_DELAY_DAYS', 30),

        /*
        |--------------------------------------------------------------------------
        | Archive Immediately
        |--------------------------------------------------------------------------
        |
        | If set to true, accounts will be archived immediately after transfer
        | when no properties remain. If false, accounts will be scheduled for
        | archival after 'archive_delay_days'.
        |
        */
        'archive_immediately' => env('OWNERSHIP_TRANSFER_ARCHIVE_IMMEDIATELY', false),

        /*
        |--------------------------------------------------------------------------
        | Send SMS Warnings
        |--------------------------------------------------------------------------
        |
        | Send SMS notifications to landlords before archival.
        |
        */
        'send_sms_warnings' => env('OWNERSHIP_TRANSFER_SEND_ARCHIVAL_SMS', false),

        /*
        |--------------------------------------------------------------------------
        | Warning Days
        |--------------------------------------------------------------------------
        |
        | Days before archival when warning notifications should be sent.
        |
        */
        'warning_days' => [
            env('OWNERSHIP_TRANSFER_WARNING_DAY_1', 30),
            env('OWNERSHIP_TRANSFER_WARNING_DAY_2', 14),
            env('OWNERSHIP_TRANSFER_WARNING_DAY_3', 7),
            env('OWNERSHIP_TRANSFER_WARNING_DAY_4', 3),
            env('OWNERSHIP_TRANSFER_WARNING_DAY_5', 1),
        ],

        /*
        |--------------------------------------------------------------------------
        | Exclude User Types from Archival
        |--------------------------------------------------------------------------
        |
        | User types that should never be automatically archived.
        |
        */
        'exclude_user_types' => [
            'super-admin',
            'admin',
            'developer',
        ],

        /*
        |--------------------------------------------------------------------------
        | Check Financial Obligations
        |--------------------------------------------------------------------------
        |
        | Check for pending financial obligations before archiving.
        |
        */
        'check_financial_obligations' => env('OWNERSHIP_TRANSFER_CHECK_FINANCIALS', true),

        /*
        |--------------------------------------------------------------------------
        | Check Active Agreements
        |--------------------------------------------------------------------------
        |
        | Check for active rental agreements before archiving.
        |
        */
        'check_active_agreements' => env('OWNERSHIP_TRANSFER_CHECK_AGREEMENTS', true),

        /*
        |--------------------------------------------------------------------------
        | Permanent Deletion After Archival
        |--------------------------------------------------------------------------
        |
        | Number of days after archival before permanently deleting the account.
        | Set to 0 to never delete, or a positive integer to schedule deletion.
        |
        */
        'permanent_deletion_days' => env('OWNERSHIP_TRANSFER_PERMANENT_DELETION_DAYS', 365),

        /*
        |--------------------------------------------------------------------------
        | Data Retention
        |--------------------------------------------------------------------------
        |
        | Configure how long to retain different types of data.
        |
        */
        'data_retention' => [
            'archived_accounts' => env('OWNERSHIP_TRANSFER_RETAIN_ARCHIVED', 365), // days
            'financial_records' => env('OWNERSHIP_TRANSFER_RETAIN_FINANCIAL', 2555), // 7 years
            'transfer_records' => env('OWNERSHIP_TRANSFER_RETAIN_TRANSFERS', 3650), // 10 years
            'activity_logs' => env('OWNERSHIP_TRANSFER_RETAIN_LOGS', 730), // 2 years
        ],

        /*
        |--------------------------------------------------------------------------
        | Notification Templates
        |--------------------------------------------------------------------------
        |
        | Configure notification settings for archival events.
        |
        */
        'notifications' => [
            'archival_warning' => [
                'subject' => 'Important: Your Account Will Be Archived Soon',
                'template' => 'emails.account-archival-warning',
            ],
            'archival_notification' => [
                'subject' => 'Your Account Has Been Archived',
                'template' => 'emails.account-archived-notification',
            ],
            'restoration_notification' => [
                'subject' => 'Your Account Has Been Restored',
                'template' => 'emails.account-restored',
            ],
            'scheduled_deletion_warning' => [
                'subject' => 'Final Notice: Your Account Will Be Permanently Deleted',
                'template' => 'emails.account-deletion-warning',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin Notifications
        |--------------------------------------------------------------------------
        |
        | Notify admins when accounts are archived or scheduled for archival.
        |
        */
        'admin_notifications' => [
            'enabled' => env('OWNERSHIP_TRANSFER_ADMIN_NOTIFICATIONS', true),
            'on_archival' => true,
            'on_scheduling' => true,
            'on_restoration' => true,
            'on_failure' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Automatic Cleanup
        |--------------------------------------------------------------------------
        |
        | Configure automatic cleanup of archived accounts.
        |
        */
        'auto_cleanup' => [
            'enabled' => env('OWNERSHIP_TRANSFER_AUTO_CLEANUP', true),
            'batch_size' => env('OWNERSHIP_TRANSFER_CLEANUP_BATCH_SIZE', 50),
            'run_time' => env('OWNERSHIP_TRANSFER_CLEANUP_TIME', '02:00'), // 2 AM daily
        ],

        /*
        |--------------------------------------------------------------------------
        | Exempted Email Domains
        |--------------------------------------------------------------------------
        |
        | Email domains that should never be automatically archived.
        | Useful for corporate or government accounts.
        |
        */
        'exempted_domains' => [
            'government.gov',
            'corporate.com',
        ],

        /*
        |--------------------------------------------------------------------------
        | Minimum Account Age Before Archival
        |--------------------------------------------------------------------------
        |
        | Minimum number of days an account must exist before being eligible for archival.
        |
        */
        'min_account_age_days' => env('OWNERSHIP_TRANSFER_MIN_ACCOUNT_AGE', 7),

        /*
        |--------------------------------------------------------------------------
        | Grace Period for Reactivation
        |--------------------------------------------------------------------------
        |
        | Number of days after archival during which the account can be easily restored.
        |
        */
        'reactivation_grace_period_days' => env('OWNERSHIP_TRANSFER_REACTIVATION_GRACE', 30),

        /*
        |--------------------------------------------------------------------------
        | Allow User Initiated Archival
        |--------------------------------------------------------------------------
        |
        | Allow users to request account archival voluntarily.
        |
        */
        'allow_user_initiated_archival' => env('OWNERSHIP_TRANSFER_USER_ARCHIVAL', true),

        /*
        |--------------------------------------------------------------------------
        | Require Admin Approval for Archival
        |--------------------------------------------------------------------------
        |
        | Require admin approval before archiving accounts (except automatic after transfers).
        |
        */
        'require_admin_approval' => env('OWNERSHIP_TRANSFER_REQUIRE_APPROVAL', false),

        /*
        |--------------------------------------------------------------------------
        | Webhook Events for Archival
        |--------------------------------------------------------------------------
        |
        | Trigger webhooks for archival-related events.
        |
        */
        'webhook_events' => [
            'account_scheduled_for_archival' => true,
            'account_archived' => true,
            'account_restored' => true,
            'account_permanently_deleted' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Settings (Backward Compatibility)
    |--------------------------------------------------------------------------
    |
    | These settings are maintained for backward compatibility with older code.
    |
    */
    'archive_old_landlord_days' => env('OWNERSHIP_TRANSFER_ARCHIVE_DELAY_DAYS', 30),
    'archive_immediately' => env('OWNERSHIP_TRANSFER_ARCHIVE_IMMEDIATELY', false),
    'send_sms_warnings' => env('OWNERSHIP_TRANSFER_SEND_ARCHIVAL_SMS', false),
];
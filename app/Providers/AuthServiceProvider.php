<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Property;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PropertyOwnershipTransfer;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\DeveloperSetting;
use App\Models\DeveloperBillingRecord;
use App\Models\DeveloperBillingProposal;
use App\Models\AdminBillingRecord;
use App\Models\BillingProposal;
use App\Models\SuperAdminPaymentRequest;
use App\Models\BillingInvoice;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use App\Policies\PropertyPolicy;
use App\Policies\RegistrationPlanPolicy;
use App\Policies\UserPolicy;
use App\Policies\TenantPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyOwnershipTransferPolicy;
use App\Policies\SecuritySchedulePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Property::class => PropertyPolicy::class,
        RegistrationPlan::class => RegistrationPlanPolicy::class,
        User::class => UserPolicy::class,
        Tenant::class => TenantPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        PropertyOwnershipTransfer::class => PropertyOwnershipTransferPolicy::class,
        SecuritySchedule::class => SecuritySchedulePolicy::class,
        \App\Models\RegistrationPlan::class =>
        \App\Policies\RegistrationPlanPolicy::class,
        // Add other model-policy mappings as needed
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // ========== USER TYPE CONSTANTS ========== //
        // Define constants for user types to make code more readable
        define('USER_TYPE_SUPER_ADMIN', 0);
        define('USER_TYPE_ADMIN', 1);
        define('USER_TYPE_LANDLORD', 2);
        define('USER_TYPE_TENANT', 3);
        define('USER_TYPE_FIELD_AGENT', 4);
        define('USER_TYPE_DEVELOPER', 5);
        define('USER_TYPE_SECURITY_PERSONNEL', 6);

        // ============================================
        // ✅ GATE BEFORE HOOK - SUPER ADMIN & SECURITY PERSONNEL OVERRIDE
        // ============================================
        // This intercepts ALL gate checks before they hit policies or other gates
        // ============================================
        
        Gate::before(function ($user, $ability) {
            // 🔹 SUPER ADMIN: Can do everything
            if ($user->type === USER_TYPE_SUPER_ADMIN) {
                \Log::info('Gate::before: Super admin override', [
                    'user_id' => $user->id,
                    'ability' => $ability,
                    'result' => true
                ]);
                return true;
            }
            
            // 🔹 SECURITY PERSONNEL WITH SUPERVISOR LEVEL >= 2: Can create and view users
            if ($user->type === USER_TYPE_SECURITY_PERSONNEL && 
                isset($user->supervisor_level) && 
                $user->supervisor_level >= 2 && 
                $user->can_be_supervisor) {
                
                // Allow 'create' ability - this is what was blocking the form submission
                if ($ability === 'create') {
                    \Log::info('Gate::before: Security Personnel with supervisor level >= 2 - create allowed', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                        'supervisor_level' => $user->supervisor_level,
                    ]);
                    return true;
                }
                
                // Allow 'viewAny' ability - to see the user list
                if ($ability === 'viewAny') {
                    \Log::info('Gate::before: Security Personnel with supervisor level >= 2 - viewAny allowed', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                        'supervisor_level' => $user->supervisor_level,
                    ]);
                    return true;
                }
                
                // Allow 'view' ability - to view specific users
                if ($ability === 'view') {
                    \Log::info('Gate::before: Security Personnel with supervisor level >= 2 - view allowed', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                        'supervisor_level' => $user->supervisor_level,
                    ]);
                    return true;
                }
                
                // Allow 'update' ability - to update users
                if ($ability === 'update') {
                    \Log::info('Gate::before: Security Personnel with supervisor level >= 2 - update allowed', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                        'supervisor_level' => $user->supervisor_level,
                    ]);
                    return true;
                }
            }
            
            // 🔹 AREA SUPERVISORS (by role): Can create and manage security personnel
            if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
                if (in_array($ability, ['create', 'viewAny', 'view', 'update'])) {
                    \Log::info('Gate::before: Area Supervisor - allowed', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                        'roles' => $user->roles->pluck('slug')->toArray(),
                    ]);
                    return true;
                }
            }
            
            // 🔹 Check active supervisor assignments with manage_personnel permission
            $hasAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereJsonContains('metadata->permissions', 'manage_personnel')
                      ->orWhereJsonContains('metadata->permissions', 'add_personnel')
                      ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
                })
                ->exists();
            
            if ($hasAssignment && in_array($ability, ['create', 'viewAny', 'view', 'update'])) {
                \Log::info('Gate::before: Has manage_personnel permission - allowed', [
                    'user_id' => $user->id,
                    'ability' => $ability,
                ]);
                return true;
            }
            
            // Return null to let other gates/policies handle it
            return null;
        });

        // ========== BASIC USER TYPE GATES ========== //
        
        Gate::define('is-super-admin', function (User $user) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });

        Gate::define('is-admin', function (User $user) {
            return $user->type === USER_TYPE_ADMIN;
        });

        Gate::define('is-landlord', function (User $user) {
            return $user->type === USER_TYPE_LANDLORD;
        });

        Gate::define('is-field-agent', function (User $user) {
            return $user->type === USER_TYPE_FIELD_AGENT;
        });

        Gate::define('is-tenant', function (User $user) {
            return $user->type === USER_TYPE_TENANT;
        });

        Gate::define('is-developer', function (User $user) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('is-security-personnel', function (User $user) {
            return $user->type === USER_TYPE_SECURITY_PERSONNEL;
        });

        // ============================================
        // SECURITY PERSONNEL / SUPERVISOR GATES
        // ============================================

        /**
         * Check if user is a supervisor (has active supervisor assignments)
         */
        Gate::define('is-supervisor', function (User $user) {
            if ($user->type !== USER_TYPE_SECURITY_PERSONNEL) {
                return false;
            }
            
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->active()
                ->exists();
        });

        /**
         * Check if user is a supervisor for a specific post
         */
        Gate::define('is-supervisor-for-post', function (User $user, $postId) {
            if ($user->type !== USER_TYPE_SECURITY_PERSONNEL) {
                return false;
            }
            
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->active()
                ->exists();
        });

        /**
         * Check if user has a specific supervisor permission
         */
        Gate::define('has-supervisor-permission', function (User $user, $permission) {
            if ($user->type !== USER_TYPE_SECURITY_PERSONNEL) {
                return false;
            }
            
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where($permission, true)
                ->active()
                ->exists();
        });

        /**
         * Check if user has a specific supervisor permission for a post
         */
        Gate::define('has-supervisor-permission-for-post', function (User $user, $permission, $postId) {
            if ($user->type !== USER_TYPE_SECURITY_PERSONNEL) {
                return false;
            }
            
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->where($permission, true)
                ->active()
                ->exists();
        });

        /**
         * Check if user can view security dashboard
         */
        Gate::define('view-security-dashboard', function (User $user) {
            return $user->type === USER_TYPE_SECURITY_PERSONNEL;
        });

        /**
         * Check if user can view supervisor dashboard
         */
        Gate::define('view-supervisor-dashboard', function (User $user) {
            if ($user->type !== USER_TYPE_SECURITY_PERSONNEL) {
                return false;
            }
            
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->active()
                ->exists();
        });

        /**
         * Check if user can view team today
         */
        Gate::define('view-team-today', function (User $user) {
            // Admin can view team today
            if ($user->isAdmin()) {
                return true;
            }

            // Any supervisor can view team today
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can view team schedule
         */
        Gate::define('view-team-schedule', function (User $user) {
            // Admin can view team schedule
            if ($user->isAdmin()) {
                return true;
            }

            // Any supervisor can view team schedule
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can view team attendance
         */
        Gate::define('view-team-attendance', function (User $user) {
            // Admin can view team attendance
            if ($user->isAdmin()) {
                return true;
            }

            // Any supervisor can view team attendance
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can view team performance
         */
        Gate::define('view-team-performance', function (User $user) {
            // Admin can view team performance
            if ($user->isAdmin()) {
                return true;
            }

            // Any supervisor can view team performance
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can view pending approvals
         */
        Gate::define('view-pending-approvals', function (User $user) {
            // Admin can view all pending approvals
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisors can view pending approvals
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can approve swap requests
         */
        Gate::define('approve-swap', function (User $user, $schedule) {
            // Admin can approve all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with swap approval permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_approve_swaps', $schedule->security_post_id]);
        });

        /**
         * Check if user can approve overtime
         */
        Gate::define('approve-overtime', function (User $user, $schedule) {
            // Admin can approve all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with overtime approval permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_approve_overtime', $schedule->security_post_id]);
        });

        /**
         * Check if user can verify check-ins
         */
        Gate::define('verify-checkin', function (User $user, $schedule) {
            // Admin can verify all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with check-in verification permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_verify_checkins', $schedule->security_post_id]);
        });

        /**
         * Check if user can mark absent
         */
        Gate::define('mark-absent', function (User $user, $schedule) {
            // Admin can mark absent
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor for this post
            return Gate::check('is-supervisor-for-post', $schedule->security_post_id);
        });

        /**
         * Check if user can override check-ins
         */
        Gate::define('override-checkin', function (User $user, $schedule) {
            // Admin can override all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with override permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_override_checkins', $schedule->security_post_id]);
        });

        /**
         * Check if user can review incidents
         */
        Gate::define('review-incident', function (User $user, $schedule) {
            // Admin can review all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with incident review permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_review_incidents', $schedule->security_post_id]);
        });

        /**
         * Check if user can request backup
         */
        Gate::define('request-backup', function (User $user, $schedule) {
            // Admin can request backup
            if ($user->isAdmin()) {
                return true;
            }

            // Any supervisor for this post can request backup
            return Gate::check('has-supervisor-permission-for-post', ['can_request_backup', $schedule->security_post_id]);
        });

        /**
         * Check if user can approve breaks
         */
        Gate::define('approve-break', function (User $user, $schedule) {
            // Admin can approve breaks
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with break approval permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_approve_breaks', $schedule->security_post_id]);
        });

        /**
         * Check if user can escalate issues
         */
        Gate::define('escalate-issue', function (User $user, $schedule) {
            // Admin can escalate
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with escalation permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_escalate_issues', $schedule->security_post_id]);
        });

        /**
         * Check if user can view all schedules
         */
        Gate::define('view-all-schedules', function (User $user) {
            // Admin can view all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with view all schedules permission
            return Gate::check('has-supervisor-permission', 'can_view_all_schedules');
        });

        /**
         * Check if user can edit schedules
         */
        Gate::define('edit-schedule', function (User $user, $schedule) {
            // Admin can edit all
            if ($user->isAdmin()) {
                return true;
            }

            // Check if user is a supervisor with edit schedules permission for this post
            return Gate::check('has-supervisor-permission-for-post', ['can_edit_schedules', $schedule->security_post_id]);
        });

        /**
         * Check if user can view post
         */
        Gate::define('view-post', function (User $user, $postId) {
            // Admin can view all posts
            if ($user->isAdmin()) {
                return true;
            }

            // Security personnel can view their assigned posts
            if ($user->type === USER_TYPE_SECURITY_PERSONNEL) {
                // Check if user is assigned to this post
                return SecuritySchedule::where('user_id', $user->id)
                    ->where('security_post_id', $postId)
                    ->exists();
            }

            // Supervisor can view their supervised posts
            return Gate::check('is-supervisor-for-post', $postId);
        });

        /**
         * Check if user can view post personnel
         */
        Gate::define('view-post-personnel', function (User $user, $postId) {
            // Admin can view all post personnel
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisor can view personnel for their supervised posts
            return Gate::check('is-supervisor-for-post', $postId);
        });

        /**
         * Check if user can view post statistics
         */
        Gate::define('view-post-statistics', function (User $user, $postId) {
            // Admin can view all post statistics
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisor can view statistics for their supervised posts
            return Gate::check('is-supervisor-for-post', $postId);
        });

        /**
         * Check if user can view supervisor assignments
         */
        Gate::define('view-supervisor-assignments', function (User $user) {
            // Admin can view all
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisors can view their own assignments
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can export team data
         */
        Gate::define('export-team-data', function (User $user) {
            // Admin can export team data
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisors can export team data
            return Gate::check('is-supervisor');
        });

        /**
         * Check if user can export post schedule
         */
        Gate::define('export-post-schedule', function (User $user, $postId) {
            // Admin can export all
            if ($user->isAdmin()) {
                return true;
            }

            // Supervisor can export for their supervised posts
            return Gate::check('is-supervisor-for-post', $postId);
        });

        // ============================================
        // DEVELOPER BILLING DASHBOARD GATES
        // ============================================

        Gate::define('access-developer-billing', function (User $user) {
            \Log::info('Gate: access-developer-billing check', [
                'user_id' => $user->id,
                'user_type' => $user->type,
                'is_allowed' => in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN])
            ]);
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('access-superadmin-billing', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN]);
        });

        // ============================================
        // AGREEMENT MANAGEMENT GATES
        // ============================================

        Gate::define('create-billing-agreement', function (User $user) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('update-billing-agreement', function (User $user, AdminBillingRecord $agreement = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('terminate-billing-agreement', function (User $user, AdminBillingRecord $agreement = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('view-billing-agreement', function (User $user, AdminBillingRecord $agreement = null) {
            // Developers and super admins can view agreements
            if (in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN])) {
                // If agreement is provided, check if super admin owns it
                if ($agreement && $user->type === USER_TYPE_SUPER_ADMIN) {
                    return $agreement->super_admin_id === $user->id;
                }
                return true;
            }
            return false;
        });

        Gate::define('view-billing-agreements', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        // ============================================
        // PAYMENT MANAGEMENT GATES
        // ============================================

        Gate::define('update-payment-settings', function (User $user) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('confirm-agreement-payment', function (User $user, AdminBillingRecord $agreement = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('confirm-superadmin-payment', function (User $user, $payment = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('reject-superadmin-payment', function (User $user, $payment = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('cancel-agreement', function (User $user, AdminBillingRecord $agreement = null) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('view-payment-details', function (User $user, $payment = null) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        // ============================================
        // SUPER ADMIN SPECIFIC GATES
        // ============================================

        Gate::define('agree-to-billing-terms', function (User $user, AdminBillingRecord $agreement = null) {
            if ($user->type !== USER_TYPE_SUPER_ADMIN) return false;
            // Super admin can only agree to their own agreements
            if ($agreement) {
                return $agreement->super_admin_id === $user->id;
            }
            return true;
        });

        Gate::define('reject-billing-terms', function (User $user, AdminBillingRecord $agreement = null) {
            if ($user->type !== USER_TYPE_SUPER_ADMIN) return false;
            // Super admin can only reject their own agreements
            if ($agreement) {
                return $agreement->super_admin_id === $user->id;
            }
            return true;
        });

        Gate::define('record-own-payment', function (User $user, AdminBillingRecord $agreement = null) {
            if ($user->type !== USER_TYPE_SUPER_ADMIN) return false;
            // Super admin can only record payment for their own agreements
            if ($agreement) {
                return $agreement->super_admin_id === $user->id;
            }
            return true;
        });

        Gate::define('view-superadmin-agreements', function (User $user) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });

        Gate::define('view-superadmin-agreement', function (User $user, AdminBillingRecord $agreement = null) {
            if ($user->type !== USER_TYPE_SUPER_ADMIN) return false;
            // Super admin can only view their own agreements
            if ($agreement) {
                return $agreement->super_admin_id === $user->id;
            }
            return true;
        });

        Gate::define('view-superadmin-payment-details', function (User $user, $payment = null) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });

        Gate::define('view-superadmin-reports', function (User $user) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });

        // ============================================
        // REPORTS & EXPORT GATES
        // ============================================

        Gate::define('view-billing-reports', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('export-billing-reports', function (User $user) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        // ============================================
        // DEVELOPER SPECIFIC GATES
        // ============================================

        Gate::define('manage-developer-settings', function (User $user, DeveloperSetting $settings = null) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-developer-dashboard', function (User $user, DeveloperSetting $settings = null) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('manage-developer-billing', function (User $user, DeveloperSetting $settings = null) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-developer-billing', function (User $user, DeveloperSetting $settings = null) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-developer-invoice', function (User $user, $invoice) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('confirm-invoice-payment', function (User $user, $invoice) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('pay-invoice', function (User $user, $invoice) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('send-invoice-reminder', function (User $user, $invoice) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('cancel-billing-proposal', function (User $user, $proposal) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('generate-next-invoice', function (User $user, $invoice) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('generate-developer-invoice', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('create-super-admin-request', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('view-super-admin-payments', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('send-super-admin-reminder', function (User $user, $payment) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('cancel-super-admin-request', function (User $user, $payment) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('mark-super-admin-payment-received', function (User $user, $payment) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('generate-custom-invoice', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('cancel-developer-invoice', function (User $user, $invoice) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('update-invoice-status', function (User $user, $invoice) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('manage-developer-email', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('manage-developer-security', function (User $user, DeveloperSetting $settings) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('generate-api-key', function (User $user, DeveloperSetting $settings) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-monitoring-dashboard', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('generate-reports', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('export-billing-data', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('create-backup', function (User $user, DeveloperSetting $settings) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('list-backups', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('restore-backup', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('access-developer-tools', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-system-metrics', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('run-health-check', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('clear-cache', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('clear-logs', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-error-logs', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('optimize-system', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        Gate::define('view-payment-history', function (User $user, DeveloperSetting $settings) {
            return $user->type === USER_TYPE_DEVELOPER;
        });

        Gate::define('process-recurring-billing', function (User $user, DeveloperSetting $settings) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        // ============================================
        // PROPERTY MANAGEMENT GATES
        // ============================================
        
        Gate::define('manage-properties', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });

        Gate::define('view-properties', function (User $user) {
            // All authenticated users can view properties, but with restrictions
            return in_array($user->type, [
                USER_TYPE_SUPER_ADMIN, 
                USER_TYPE_ADMIN, 
                USER_TYPE_LANDLORD, 
                USER_TYPE_TENANT, 
                USER_TYPE_DEVELOPER,
                USER_TYPE_FIELD_AGENT,
                USER_TYPE_SECURITY_PERSONNEL
            ]);
        });

        Gate::define('view-any-properties', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });

        Gate::define('create-properties', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });

        Gate::define('update-any-property', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });

        Gate::define('delete-any-property', function (User $user) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });

        // ============================================
        // PROPERTY OWNERSHIP TRANSFER GATES
        // ============================================
        
        Gate::define('transfer-ownership', function (User $user, Property $property) {
            // Only landlords can transfer ownership
            if ($user->type !== USER_TYPE_LANDLORD) {
                return false;
            }
            
            // Landlord can only transfer their own properties
            if ($user->id !== $property->landlord_id) {
                return false;
            }
            
            // Check if there's already a pending transfer for this property
            if ($property->currentOwnershipTransfer) {
                return false;
            }
            
            return true;
        });
        
        Gate::define('view-property-history', function (User $user, Property $property) {
            // Admins can view all property history
            if (in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return true;
            }
            
            // Current landlord can view their own property history
            if ($user->id === $property->landlord_id) {
                return true;
            }
            
            // Users involved in ownership transfers for this property can view history
            $hasTransferInvolvement = PropertyOwnershipTransfer::where('property_id', $property->id)
                ->where(function($query) use ($user) {
                    $query->where('current_landlord_id', $user->id)
                          ->orWhere('new_landlord_id', $user->id)
                          ->orWhere('requested_by_id', $user->id);
                })
                ->exists();
            
            return $hasTransferInvolvement;
        });
        
        Gate::define('view-ownership-transfer', function (User $user, PropertyOwnershipTransfer $transfer) {
            // Admins can view all transfers
            if (in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return true;
            }
            
            // Current or new landlord can view
            if ($user->id === $transfer->current_landlord_id || 
                $user->id === $transfer->new_landlord_id) {
                return true;
            }
            
            // User who requested the transfer can view
            if ($user->id === $transfer->requested_by_id) {
                return true;
            }
            
            return false;
        });
        
        Gate::define('manage-ownership-transfers', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });
        
        Gate::define('cancel-ownership-transfer', function (User $user, PropertyOwnershipTransfer $transfer) {
            // Only current landlord can cancel their request
            if ($user->id !== $transfer->current_landlord_id) {
                return false;
            }
            
            // Only pending requests can be cancelled
            return $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING;
        });
        
        Gate::define('approve-ownership-transfer', function (User $user, PropertyOwnershipTransfer $transfer) {
            // Only admins can approve
            if (!in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return false;
            }
            
            // Check if transfer can be approved (business logic)
            return $transfer->canBeApproved();
        });
        
        Gate::define('reject-ownership-transfer', function (User $user, PropertyOwnershipTransfer $transfer) {
            // Only admins can reject
            if (!in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return false;
            }
            
            // Check if transfer can be rejected (business logic)
            return $transfer->canBeRejected();
        });
        
        Gate::define('complete-ownership-transfer', function (User $user, PropertyOwnershipTransfer $transfer) {
            // Only admins can complete
            if (!in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return false;
            }
            
            // Check if transfer can be completed (business logic)
            return $transfer->canBeCompleted();
        });

        // ============================================
        // USER MANAGEMENT GATES
        // ============================================
        
        Gate::define('manage-users', function (User $user) {
            return $user->type === USER_TYPE_SUPER_ADMIN;
        });
        
        Gate::define('view-own-profile', function (User $user, User $model) {
            return $user->id === $model->id;
        });
        
        Gate::define('manage-other-users', function (User $user, User $model) {
            // Can't manage yourself
            if ($user->id === $model->id) {
                return false;
            }
            
            // Super admins can manage everyone
            if ($user->type === USER_TYPE_SUPER_ADMIN) {
                return true;
            }
            
            // Admins can manage non-admin users
            if ($user->type === USER_TYPE_ADMIN) {
                return !in_array($model->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
            }
            
            return false;
        });
        
        Gate::define('view-user-profile', function (User $user, User $model) {
            // Everyone can view their own profile
            if ($user->id === $model->id) {
                return true;
            }
            
            // Admins can view all profiles
            if (in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return true;
            }
            
            // Landlords can view tenants of their properties
            if ($user->type === USER_TYPE_LANDLORD) {
                // Check if the user is a tenant in any of the landlord's properties
                return $model->tenants()->whereHas('property', function($query) use ($user) {
                    $query->where('landlord_id', $user->id);
                })->exists();
            }
            
            return false;
        });

        // ============================================
        // REGISTRATION PLAN GATES
        // ============================================
        
        Gate::define('manage-registration-plans', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });
        
        Gate::define('view-registration-plan', function (User $user, RegistrationPlan $plan) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });
        
        Gate::define('purchase-registration-plan', function (User $user) {
            // Allow landlords to purchase registration plans
            return $user->type === USER_TYPE_LANDLORD;
        });

        // ============================================
        // DASHBOARD ACCESS GATES
        // ============================================
        
        Gate::define('access-admin-dashboard', function (User $user) {
            return $user->type === USER_TYPE_ADMIN;
        });
        
        Gate::define('access-landlord-dashboard', function (User $user) {
            return $user->type === USER_TYPE_LANDLORD;
        });
        
        Gate::define('access-field-agent-dashboard', function (User $user) {
            return $user->type === USER_TYPE_FIELD_AGENT;
        });
        
        Gate::define('access-tenant-dashboard', function (User $user) {
            return $user->type === USER_TYPE_TENANT;
        });
        
        Gate::define('access-developer-dashboard', function (User $user) {
            return in_array($user->type, [USER_TYPE_DEVELOPER, USER_TYPE_SUPER_ADMIN]);
        });

        // ============================================
        // FINANCIAL GATES
        // ============================================
        
        Gate::define('view-invoices', function (User $user) {
            // Admins and landlords can view invoices
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN, USER_TYPE_LANDLORD]);
        });
        
        Gate::define('manage-invoices', function (User $user) {
            // Only admins can manage invoices
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });
        
        Gate::define('view-payments', function (User $user) {
            // Admins, landlords, and tenants can view payments (with restrictions)
            return in_array($user->type, [
                USER_TYPE_SUPER_ADMIN, 
                USER_TYPE_ADMIN, 
                USER_TYPE_LANDLORD, 
                USER_TYPE_TENANT
            ]);
        });
        
        Gate::define('manage-payments', function (User $user) {
            // Only admins can manage payments
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
        });

        // ============================================
        // TENANT MANAGEMENT GATES
        // ============================================
        
        Gate::define('manage-tenants', function (User $user) {
            // Admins and landlords can manage tenants
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN, USER_TYPE_LANDLORD]);
        });
        
        Gate::define('view-tenant-details', function (User $user, Tenant $tenant) {
            // Admins can view all tenants
            if (in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN])) {
                return true;
            }
            
            // Landlords can view tenants in their properties
            if ($user->type === USER_TYPE_LANDLORD) {
                return $tenant->property->landlord_id === $user->id;
            }
            
            // Tenants can view their own details
            if ($user->type === USER_TYPE_TENANT) {
                return $tenant->user_id === $user->id;
            }
            
            return false;
        });

        // ============================================
        // REPORTING GATES
        // ============================================
        
        Gate::define('view-reports', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN, USER_TYPE_DEVELOPER]);
        });
        
        Gate::define('generate-reports', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN, USER_TYPE_DEVELOPER]);
        });

        // ============================================
        // SETTINGS GATES
        // ============================================
        
        Gate::define('manage-settings', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_DEVELOPER]);
        });
        
        Gate::define('view-audit-logs', function (User $user) {
            return in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_DEVELOPER]);
        });
    }
}
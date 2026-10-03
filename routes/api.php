<?php

// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Developer\SuperAdminController;
use App\Http\Controllers\Api\Developer\SystemSettingsController;
use App\Http\Controllers\Api\V1\SystemSettingController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\PhotoApiController;
use App\Http\Controllers\Api\UserManagementApiController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\UserInvitationApiController;
use App\Http\Controllers\Api\RegistrationPlanController;
use App\Http\Controllers\Api\PropertyApiController;
use App\Http\Controllers\Api\FieldAgentPropertyApiController;

/*
|--------------------------------------------------------------------------
| API Routes for Flutter Mobile App
|--------------------------------------------------------------------------
| Complete authentication system with 2FA, device management, and security
| Includes full user management for admin/developer roles
| Includes full invitation lifecycle (send, verify, accept, cancel, analytics)
|
| ── FIXED (this revision) ──
|  1. Removed the stray closing `});` that made the file fail to boot.
|  2. Pulled the debug routes out of the `Route::prefix('v1')` group so
|     the properties route blocks load in every environment (they were
|     previously nested inside the local/testing-only `if` block).
|  3. Merged the two separate `prefix('v1')->middleware('auth:sanctum')`
|     blocks into one.
|  4. Fixed the doubled path on `/admin/registration-plans/analytics` —
|     it was declared as `/admin/registration-plans/analytics` INSIDE a
|     group already mounted at `admin/registration-plans`.
|  5. Moved literal routes (`/stats`, `/trashed`, `/zones`, `/sections`,
|     `/validate-pattern`, `/property-counts`, `/analytics`, `/export`,
|     `/analytics/export`) ABOVE the `/{id}` wildcard so Laravel doesn't
|     bind them as IDs.
|  6. Added the new endpoints the Flutter service expects:
|       - GET  /admin/registration-plans/property-counts       (batch)
|       - GET  /admin/registration-plans/{id}/property-counts  (single)
|       - GET  /admin/registration-plans/export
|       - GET  /admin/registration-plans/analytics/export
|       - GET  /field-agent/registration-plans
|       - GET  /field-agent/registration-plans/{id}/property-counts
|  7. Added explicit route names to every new endpoint for `route()`
|     helpers and readable `php artisan route:list` output.
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Health Check (for monitoring)
    |--------------------------------------------------------------------------
    */
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now(),
            'version' => 'v1',
            'environment' => config('app.env'),
        ]);
    })->name('api.health');

    /*
    |--------------------------------------------------------------------------
    | ⭐ SYSTEM SETTINGS - Public
    |--------------------------------------------------------------------------
    */
    Route::get('/system-settings', [SystemSettingController::class, 'getPublicSettings'])
        ->name('api.system-settings');

    Route::get('/registration-status', [SystemSettingController::class, 'getRegistrationStatus'])
        ->name('api.registration-status');

    // ========== PUBLIC PHOTO SHARING ==========
    Route::get('/shared/photo/{token}', [PhotoApiController::class, 'accessSharedPhoto'])
        ->name('api.shared.photo');
    Route::get('/shared/photo/{token}/thumbnail', [PhotoApiController::class, 'accessSharedThumbnail'])
        ->name('api.shared.photo.thumbnail');

    // ========== EMAIL VERIFICATION (Public — signed-URL flow) ==========
    Route::get('/profile/email/verify/{token}', [ProfileApiController::class, 'verifyEmail'])
        ->name('api.profile.email.verify');

    // ========== EMAIL VERIFICATION (Public — token-column flow) ==========
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
        ->name('api.email.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->name('api.email.resend');

    /*
    |--------------------------------------------------------------------------
    | ⭐ INVITATION ACCEPTANCE (Public — invitee is not yet a user)
    |--------------------------------------------------------------------------
    | Full lifecycle: verify → details → accept → track-view
    | Backward-compat POST /invitations/accept shim handles the legacy
    | {invitation_id} payload from older Flutter builds.
    */
    Route::prefix('invitations')->name('api.invitations.')->group(function () {

        // ---- PUBLIC (no auth) ----
        Route::get   ('{token}/verify',     [UserInvitationApiController::class, 'verifyToken'])
            ->name('verify');
        Route::get   ('{token}/details',    [UserInvitationApiController::class, 'getInvitationDetails'])
            ->name('details');
        Route::post  ('{token}/accept',     [UserInvitationApiController::class, 'accept'])
            ->name('accept');
        Route::post  ('{token}/track-view', [UserInvitationApiController::class, 'trackInvitationView'])
            ->name('track-view');

        /*
        |----------------------------------------------------------------------
        | Backward-compat shim for the legacy endpoint
        |----------------------------------------------------------------------
        */
        Route::post('accept', function (\Illuminate\Http\Request $request) {
            $token = $request->input('invitation_id') ?? $request->input('token');

            if (!$token) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'invitation_id or token is required.',
                    'error_code' => 'MISSING_TOKEN',
                ], 422);
            }

            return app(UserInvitationApiController::class)->accept($request, $token);
        })->name('accept.legacy');

        // ---- AUTHENTICATED (admin management) ----
        Route::middleware('auth:sanctum')->group(function () {

            Route::post  ('send',                [UserInvitationApiController::class, 'sendInvitation'])
                ->name('send');
            Route::post  ('{userId}/resend',     [UserInvitationApiController::class, 'resendInvitation'])
                ->name('resend');
            Route::get   ('status/{userId}',     [UserInvitationApiController::class, 'getInvitationStatus'])
                ->name('status');
            Route::get   ('history/{userId}',    [UserInvitationApiController::class, 'getUserInvitationHistory'])
                ->name('history');
            Route::get   ('channels/{userId}',   [UserInvitationApiController::class, 'getAvailableChannels'])
                ->name('channels');
            Route::get   ('statistics',          [UserInvitationApiController::class, 'getInvitationStatistics'])
                ->name('statistics');
            Route::get   ('analytics',           [UserInvitationApiController::class, 'getInvitationAnalytics'])
                ->name('analytics');
            Route::get   ('system-status',       [UserInvitationApiController::class, 'getSystemInvitationStatus'])
                ->name('system-status');
            Route::get   ('expiry-configuration',[UserInvitationApiController::class, 'getExpiryConfiguration'])
                ->name('expiry-configuration');
            Route::post  ('cleanup',             [UserInvitationApiController::class, 'cleanupExpiredInvitations'])
                ->name('cleanup');
            Route::post  ('{id}/cancel',         [UserInvitationApiController::class, 'cancelInvitation'])
                ->name('cancel');

            Route::get   ('/',                   [UserInvitationApiController::class, 'index'])
                ->name('index');
            Route::get   ('/{invitationId}',     [UserInvitationApiController::class, 'show'])
                ->name('show')
                ->where('invitationId', '[0-9]+');
        });
    });

    // ========== AUTHENTICATION ROUTES ==========
    Route::prefix('auth')->name('api.auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify');
        Route::post('/2fa/resend', [AuthController::class, 'resendTwoFactorCode'])->name('2fa.resend');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Requires Valid Sanctum Token)
    |--------------------------------------------------------------------------
    |
    | ── FIXED: the properties and field-agent groups used to be defined in
    | separate top-level `prefix('v1')->middleware('auth:sanctum')` blocks.
    | All of them are now nested inside this single group for clarity.
    */
    Route::middleware(['auth:sanctum'])->group(function () {

        // ========== AUTH SESSION MANAGEMENT ==========
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
        Route::post('/logout-all-devices', [AuthController::class, 'logoutAllDevices'])->name('api.logout.all');
        Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('api.token.refresh');

        // ========== USER INFORMATION ==========
        Route::get('/user', [AuthController::class, 'user'])->name('api.user.profile');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('api.password.change');

        // ========== DEVICE & SESSION MANAGEMENT ==========
        Route::get('/devices', [AuthController::class, 'getUserDevices'])->name('api.devices.list');
        Route::delete('/devices/{deviceId}', [AuthController::class, 'revokeDevice'])->name('api.devices.revoke');
        Route::post('/devices/push-token', [AuthController::class, 'registerPushToken'])->name('api.devices.push-token');

        // ========== SECURITY & HISTORY ==========
        Route::get('/login-history', [AuthController::class, 'getLoginHistory'])->name('api.security.history');
        Route::get('/password-expiry', [AuthController::class, 'checkPasswordExpiry'])->name('api.security.password-expiry');

        // ========== DASHBOARD REDIRECT ==========
        Route::get('/dashboard-redirect', [AuthController::class, 'getDashboardRedirect'])->name('api.dashboard.redirect');

        /*
        |--------------------------------------------------------------------------
        | ⭐ ADMIN ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('admin')->name('api.admin.')->group(function () {

            // ========== SYSTEM SETTINGS CRUD ==========
            Route::get('/system-settings', [SystemSettingController::class, 'getAdminSettings'])
                ->name('system-settings');
            Route::put('/system-settings', [SystemSettingController::class, 'updateSettings'])
                ->name('system-settings.update');

            // ========== REGISTRATION MANAGEMENT ==========
            Route::post('/toggle-registration', [SystemSettingController::class, 'toggleRegistration'])
                ->name('toggle-registration');

            // ========== NOTIFICATION CHANNELS ==========
            Route::get('/notification-channels', [SystemSettingController::class, 'getNotificationChannelSettings'])
                ->name('notification-channels');
            Route::put('/notification-channels', [SystemSettingController::class, 'updateNotificationChannels'])
                ->name('notification-channels.update');
            Route::post('/test-notification-channels', [SystemSettingController::class, 'testNotificationChannels'])
                ->name('test-notification-channels');

            // ========== INVOICE SETTINGS ==========
            Route::get('/invoice-settings', [SystemSettingController::class, 'getInvoiceSettings'])
                ->name('invoice-settings');
            Route::post('/toggle-auto-invoice', [SystemSettingController::class, 'toggleAutoInvoiceGeneration'])
                ->name('toggle-auto-invoice');
            Route::post('/reminder-settings', [SystemSettingController::class, 'updateReminderSettings'])
                ->name('reminder-settings.update');
            Route::post('/validate-invoice-settings', [SystemSettingController::class, 'validateInvoiceSettings'])
                ->name('invoice-settings.validate');

            // ========== PAYMENT GATEWAYS ==========
            Route::get('/payment-gateways', [SystemSettingController::class, 'getPaymentGateways'])
                ->name('payment-gateways');
            Route::put('/payment-gateways', [SystemSettingController::class, 'updatePaymentGateways'])
                ->name('payment-gateways.update');

            // ========== SYSTEM TESTING ==========
            Route::post('/test-configuration', [SystemSettingController::class, 'testSystemConfiguration'])
                ->name('test-configuration');
            Route::post('/test-reminder-system', [SystemSettingController::class, 'testReminderSystem'])
                ->name('test-reminder-system');

            // ========== PROFILE UPDATES (ADMIN) ==========
            Route::put('/profile/personal', [ProfileApiController::class, 'updateAdminPersonal'])
                ->name('profile.personal.update');
            Route::put('/profile/contact', [ProfileApiController::class, 'updateAdminContact'])
                ->name('profile.contact.update');

            // ========== ROLES (Super Admin only) ==========
            Route::get('/roles', [UserManagementApiController::class, 'getAvailableRoles'])
                ->name('available-roles');

            // ========== PROPERTY OWNERS ==========
            Route::get('/property-owners', [UserManagementApiController::class, 'getPropertyOwners'])
                ->name('property-owners');

            // ========== USER EXPORTS ==========
            Route::get('/users/export', [UserManagementApiController::class, 'export'])
                ->name('users.export');
            Route::get('/property-owners/export', [UserManagementApiController::class, 'exportPropertyOwners'])
                ->name('property-owners.export');

            // ========== FIELD AGENTS (for registration plan assignment) ==========
            Route::get('/field-agents', [RegistrationPlanController::class, 'fieldAgents'])
                ->name('field-agents');

            /*
            |--------------------------------------------------------------------------
            | ⭐ REGISTRATION PLANS (Admin)
            |--------------------------------------------------------------------------
            |
            | Effective prefix: /api/v1/admin/registration-plans
            |
            | ── FIXED: literal routes MUST be declared before the /{id}
            |    wildcard, otherwise Laravel matches /stats against
            |    RegistrationPlanController::show and passes 'stats' as $id.
            |
            | ── FIXED: the previous version declared
            |        Route::get('/admin/registration-plans/analytics', ...)
            |    inside this group, producing a doubled path
            |    (/api/v1/admin/registration-plans/admin/registration-plans/analytics).
            |    It is now just '/analytics'.
            |
            | ── NEW endpoints added:
            |    • GET  /property-counts                  (batch counts)
            |    • GET  /{id}/property-counts             (single-plan counts)
            |    • GET  /export                           (CSV export)
            |    • GET  /analytics/export                 (PDF/XLSX export)
            */
            Route::prefix('registration-plans')->name('registration-plans.')->group(function () {

                // ═══════════════════════════════════════════════════
                // Collection routes (MUST come before /{id})
                // ═══════════════════════════════════════════════════
                Route::get   ('/',                 [RegistrationPlanController::class, 'index'])->name('index');
                Route::post  ('/',                 [RegistrationPlanController::class, 'store'])->name('store');

                // ── Metadata / aggregates ──
                Route::get   ('/stats',            [RegistrationPlanController::class, 'stats'])->name('stats');
                Route::get   ('/trashed',          [RegistrationPlanController::class, 'trashed'])->name('trashed');
                Route::get   ('/zones',            [RegistrationPlanController::class, 'zones'])->name('zones');
                Route::get   ('/sections',         [RegistrationPlanController::class, 'sections'])->name('sections');
                Route::post  ('/validate-pattern', [RegistrationPlanController::class, 'validatePattern'])->name('validate-pattern');

                // ── Analytics ──
                Route::get   ('/analytics',        [RegistrationPlanController::class, 'analytics'])->name('analytics');

                // ── Exports ──
                // Referenced by RegistrationPlanService::exportUrl()
                // and analyticsExportUrl().
                Route::get   ('/export',           [RegistrationPlanController::class, 'export'])->name('export');
                Route::get   ('/analytics/export', [RegistrationPlanController::class, 'analyticsExport'])->name('analytics.export');

                // ── Property counts (batch) ──
                //   GET /api/v1/admin/registration-plans/property-counts?ids=1,2,3
                //   → { "data": { "1": { "total": 42, "registered_by_me": 7 }, ... } }
                Route::get   ('/property-counts',  [RegistrationPlanController::class, 'propertyCounts'])
                    ->name('property-counts');

                // ═══════════════════════════════════════════════════
                // Wildcard routes (after all literals)
                // ═══════════════════════════════════════════════════
                Route::get   ('/{id}',             [RegistrationPlanController::class, 'show'])->name('show');
                Route::put   ('/{id}',             [RegistrationPlanController::class, 'update'])->name('update');
                Route::delete('/{id}',             [RegistrationPlanController::class, 'destroy'])->name('destroy');
                Route::post  ('/{id}/restore',     [RegistrationPlanController::class, 'restore'])->name('restore');
                Route::delete('/{id}/force-delete',[RegistrationPlanController::class, 'forceDelete'])->name('force-delete');

                // ── Per-plan property counts (single) ──
                Route::get   ('/{id}/property-counts',
                    [RegistrationPlanController::class, 'propertyCountsForPlan'])
                    ->name('property-counts.show');

                // ── Sequence preview ──
                Route::get   ('/{id}/sequence-preview',
                    [RegistrationPlanController::class, 'sequencePreview'])
                    ->name('sequence-preview');

                // ── Agent assignment management ──
                Route::get   ('/{id}/agents',                             [RegistrationPlanController::class, 'agents'])->name('agents');
                Route::post  ('/{id}/agents',                             [RegistrationPlanController::class, 'assignAgents'])->name('agents.assign');
                Route::delete('/{id}/agents/{agentId}',                   [RegistrationPlanController::class, 'removeAgent'])->name('agents.remove');
                Route::post  ('/{id}/agents/{agentId}/resend-invitation', [RegistrationPlanController::class, 'resendInvitation'])->name('agents.resend-invitation');

                // ── Status transitions ──
                Route::post  ('/{id}/mark-in-progress', [RegistrationPlanController::class, 'markInProgress'])->name('mark-in-progress');
                Route::post  ('/{id}/mark-completed',   [RegistrationPlanController::class, 'markCompleted'])->name('mark-completed');
                Route::post  ('/{id}/reactivate',       [RegistrationPlanController::class, 'reactivate'])->name('reactivate');
                Route::post  ('/{id}/cancel',           [RegistrationPlanController::class, 'cancel'])->name('cancel');
            });
        });

        /*
        |--------------------------------------------------------------------------
        | USER MANAGEMENT API ROUTES (ADMIN & SUPER ADMIN)
        |--------------------------------------------------------------------------
        */
        Route::prefix('admin/users')->name('api.admin.users.')->group(function () {

            // ============================================================
            // 1. COLLECTION ROUTES (no {id} — MUST come first)
            // ============================================================
            Route::get('/create', [UserManagementApiController::class, 'create'])->name('create');
            Route::get('/', [UserManagementApiController::class, 'index'])->name('index');
            Route::post('/', [UserManagementApiController::class, 'store'])->name('store');
            Route::get('/stats', [UserManagementApiController::class, 'getStats'])->name('stats');
            Route::get('/search', [UserManagementApiController::class, 'search'])->name('search');
            Route::get('/email-list', [UserManagementApiController::class, 'getUserListForEmail'])->name('email-list');
            Route::get('/archived', [UserManagementApiController::class, 'getArchivedUsers'])->name('archived');
            Route::get('/trashed', [UserManagementApiController::class, 'getTrashedUsers'])->name('trashed');

            // Bulk operations
            Route::post('/bulk-action', [UserManagementApiController::class, 'bulkAction'])->name('bulk-action');
            Route::post('/bulk-delete', [UserManagementApiController::class, 'bulkDelete'])->name('bulk-delete');
            Route::post('/bulk-restore', [UserManagementApiController::class, 'bulkRestore'])->name('bulk-restore');
            Route::post('/bulk-restore-archived', [UserManagementApiController::class, 'bulkRestoreArchived'])->name('bulk-restore-archived');
            Route::post('/bulk-permanent-delete', [UserManagementApiController::class, 'bulkPermanentDelete'])->name('bulk-permanent-delete');
            Route::post('/bulk-permanent-delete-archived', [UserManagementApiController::class, 'bulkPermanentDeleteArchived'])->name('bulk-permanent-delete-archived');
            Route::post('/bulk-role-action', [UserManagementApiController::class, 'bulkRoleAction'])->name('bulk-role-action');

            Route::post('/empty-trash', [UserManagementApiController::class, 'emptyTrash'])->name('empty-trash');

            // ============================================================
            // 2. WILDCARD ROUTES ({id} — MUST come last)
            // ============================================================
            Route::get('/archived/{id}', [UserManagementApiController::class, 'getArchivedUserDetails'])->name('archived.details');
            Route::get('/trashed/{id}', [UserManagementApiController::class, 'getDeletedUserDetails'])->name('trashed.details');

            Route::get('/{id}/edit', [UserManagementApiController::class, 'edit'])->name('edit');
            Route::get('/{id}', [UserManagementApiController::class, 'show'])->name('show');
            Route::put('/{id}', [UserManagementApiController::class, 'update'])->name('update');
            Route::delete('/{id}', [UserManagementApiController::class, 'destroy'])->name('destroy');

            Route::delete('/{id}/force', [UserManagementApiController::class, 'forceDelete'])->name('force-delete');
            Route::delete('/{id}/force-destroy', [UserManagementApiController::class, 'forceDestroy'])->name('force-destroy');

            Route::post('/{id}/restore', [UserManagementApiController::class, 'restore'])->name('restore');
            Route::get('/{id}/check-relations', [UserManagementApiController::class, 'checkRelations'])->name('check-relations');

            Route::post('/{id}/archive', [UserManagementApiController::class, 'archiveUser'])->name('archive');
            Route::post('/{id}/restore-archived', [UserManagementApiController::class, 'restoreArchivedUser'])->name('restore-archived');
            Route::post('/{id}/cancel-archival-schedule', [UserManagementApiController::class, 'cancelArchivalSchedule'])->name('cancel-archival-schedule');

            Route::post('/{id}/activate', [UserManagementApiController::class, 'activate'])->name('activate');
            Route::post('/{id}/suspend', [UserManagementApiController::class, 'suspend'])->name('suspend');
            Route::post('/{id}/deactivate', [UserManagementApiController::class, 'deactivate'])->name('deactivate');
            Route::post('/{id}/verify-phone', [UserManagementApiController::class, 'markPhoneVerified'])->name('verify-phone');

            Route::post('/{id}/invite', [UserManagementApiController::class, 'sendInvitation'])->name('invite');
            Route::post('/{id}/invite/resend', [UserManagementApiController::class, 'resendInvitation'])->name('invite.resend');
            Route::get('/{id}/invitation-status', [UserManagementApiController::class, 'getInvitationInfo'])->name('invitation-status');
            Route::get('/{id}/available-channels', [UserManagementApiController::class, 'getAvailableChannels'])->name('available-channels');

            Route::put('/{id}/password', [UserManagementApiController::class, 'updatePassword'])->name('password.update');

            Route::get('/{id}/roles', [UserManagementApiController::class, 'getUserRoles'])->name('roles.detail');
            Route::put('/{id}/roles', [UserManagementApiController::class, 'updateUserRoles'])->name('roles.update.detail');
            Route::post('/{id}/assign-landlord-role', [UserManagementApiController::class, 'assignLandlordRole'])->name('assign-landlord-role');
            Route::post('/{id}/remove-landlord-role', [UserManagementApiController::class, 'removeLandlordRole'])->name('remove-landlord-role');
        });

        /*
        |--------------------------------------------------------------------------
        | PROFILE MANAGEMENT API ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('profile')->name('api.profile.')->group(function () {

            // ========== CORE PROFILE OPERATIONS ==========
            Route::get('/', [ProfileApiController::class, 'show'])->name('show');
            Route::put('/personal', [ProfileApiController::class, 'updatePersonal'])->name('personal.update');
            Route::put('/contact', [ProfileApiController::class, 'updateContact'])->name('contact.update');
            Route::put('/password', [ProfileApiController::class, 'updatePassword'])->name('password.update');
            Route::put('/admin/password', [ProfileApiController::class, 'updateAdminPassword'])->name('admin.password.update');

            // ========== PROFILE PHOTO OPERATIONS ==========
            Route::post('/photo', [ProfileApiController::class, 'uploadPhoto'])->name('photo.upload');
            Route::delete('/photo', [ProfileApiController::class, 'removePhoto'])->name('photo.remove');

            // ========== PHONE VERIFICATION ==========
            Route::post('/phone/verify/send', [ProfileApiController::class, 'sendPhoneVerification'])->name('phone.verify.send');
            Route::post('/phone/verify/{code?}', [ProfileApiController::class, 'verifyPhone'])->name('phone.verify');

            // ========== EMAIL VERIFICATION (authenticated resend) ==========
            Route::post('/email/verify/send', [ProfileApiController::class, 'sendEmailVerification'])->name('email.verify.send');

            // ========== PROFILE STATISTICS & DATA ==========
            Route::get('/stats', [ProfileApiController::class, 'getStats'])->name('stats');
            Route::get('/admin/stats', [ProfileApiController::class, 'getAdminProfileStats'])->name('admin.stats');
            Route::get('/security/stats', [ProfileApiController::class, 'getSecurityProfileStats'])->name('security.stats');
            Route::get('/developer/stats', [ProfileApiController::class, 'getDeveloperStats'])->name('developer.stats');
            Route::get('/sanitation/stats', [ProfileApiController::class, 'getSanitationStats'])->name('sanitation.stats');
            Route::get('/contractor/stats', [ProfileApiController::class, 'getContractorStats'])->name('contractor.stats');

            Route::get('/download-data', [ProfileApiController::class, 'downloadData'])->name('download-data');
            Route::get('/admin/download-data', [ProfileApiController::class, 'downloadAdminData'])->name('admin.download-data');
            Route::get('/security/download-data', [ProfileApiController::class, 'downloadSecurityData'])->name('security.download-data');
            Route::get('/developer/download-data', [ProfileApiController::class, 'downloadDeveloperData'])->name('developer.download-data');

            Route::delete('/request-deletion', [ProfileApiController::class, 'requestDeletion'])->name('request-deletion');

            // ========== FIELD AGENT SPECIFIC ==========
            Route::put('/agent/info', [ProfileApiController::class, 'updateAgentInfo'])->name('agent.info.update');
            Route::put('/agent/notifications', [ProfileApiController::class, 'updateNotifications'])->name('agent.notifications.update');
            Route::put('/agent/security', [ProfileApiController::class, 'updateSecurity'])->name('agent.security.update');
            Route::post('/agent/id-document', [ProfileApiController::class, 'uploadIdDocument'])->name('agent.id-document.upload');
            Route::delete('/agent/session/{sessionId}', [ProfileApiController::class, 'revokeSession'])->name('agent.session.revoke');

            // ========== SECURITY PERSONNEL SPECIFIC ==========
            Route::put('/security/info', [ProfileApiController::class, 'updateSecurityContact'])->name('security.info.update');
            Route::post('/security/phone/verify/send', [ProfileApiController::class, 'sendPhoneVerification'])->name('security.phone.verify.send');
            Route::post('/security/phone/verify', [ProfileApiController::class, 'verifyPhone'])->name('security.phone.verify');

            // ========== DEVELOPER SPECIFIC ==========
            Route::put('/developer/settings', [ProfileApiController::class, 'updateDeveloperSettings'])->name('developer.settings.update');
            Route::post('/developer/api-key/generate', [ProfileApiController::class, 'generateApiKey'])->name('developer.api-key.generate');
            Route::delete('/developer/api-key/revoke', [ProfileApiController::class, 'revokeApiKey'])->name('developer.api-key.revoke');

            // ========== SANITATION PERSONNEL SPECIFIC ==========
            Route::put('/sanitation/settings', [ProfileApiController::class, 'updateSanitationSettings'])->name('sanitation.settings.update');
            Route::put('/sanitation/availability', [ProfileApiController::class, 'updateAvailability'])->name('sanitation.availability.update');
        });

        /*
        |--------------------------------------------------------------------------
        | PHOTO MANAGEMENT API ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('profile/photo')->name('api.profile.photo.')->group(function () {

            Route::get('/', [PhotoApiController::class, 'show'])->name('show');
            Route::post('/', [PhotoApiController::class, 'upload'])->name('upload');
            Route::delete('/', [PhotoApiController::class, 'destroy'])->name('remove');
            Route::post('/upload-url', [PhotoApiController::class, 'getUploadUrl'])->name('upload-url');
            Route::post('/batch', [PhotoApiController::class, 'batchUpload'])->name('batch');

            Route::get('/admin/permissions', [PhotoApiController::class, 'checkPermissions'])->name('admin.permissions');
            Route::get('/admin/users/{userId}', [PhotoApiController::class, 'adminShow'])->name('admin.users.show');
            Route::post('/admin/users/{userId}', [PhotoApiController::class, 'adminUpload'])->name('admin.users.upload');
            Route::delete('/admin/users/{userId}', [PhotoApiController::class, 'adminDestroy'])->name('admin.users.remove');
        });

        /*
        |--------------------------------------------------------------------------
        | LANDLORD SPECIFIC ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('landlord')->name('api.landlord.')->group(function () {
            Route::put('/profile/personal', [ProfileApiController::class, 'updateLandlordPersonal'])->name('profile.personal.update');
            Route::put('/profile/contact', [ProfileApiController::class, 'updateLandlordContact'])->name('profile.contact.update');
        });

        /*
        |--------------------------------------------------------------------------
        | CONTRACTOR SPECIFIC ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('contractor')->name('api.contractor.')->group(function () {
            Route::put('/profile/personal', [ProfileApiController::class, 'updateContractorPersonal'])->name('profile.personal.update');
            Route::put('/profile/contact', [ProfileApiController::class, 'updateContractorContact'])->name('profile.contact.update');
            Route::get('/profile/stats', [ProfileApiController::class, 'getContractorStats'])->name('profile.stats');
        });

        /*
        |--------------------------------------------------------------------------
        | SANITATION PERSONNEL SPECIFIC ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('sanitation')->name('api.sanitation.')->group(function () {
            Route::put('/profile/personal', [ProfileApiController::class, 'updateSanitationPersonal'])->name('profile.personal.update');
            Route::put('/profile/contact', [ProfileApiController::class, 'updateSanitationContact'])->name('profile.contact.update');
            Route::put('/profile/settings', [ProfileApiController::class, 'updateSanitationSettings'])->name('profile.settings.update');
            Route::put('/profile/availability', [ProfileApiController::class, 'updateAvailability'])->name('profile.availability.update');
            Route::get('/profile/stats', [ProfileApiController::class, 'getSanitationStats'])->name('profile.stats');
        });

        /*
        |--------------------------------------------------------------------------
        | DEVELOPER SUPER ADMIN MANAGEMENT API ROUTES
        |--------------------------------------------------------------------------
        */
        Route::prefix('developer/super-admins')->name('api.developer.super-admins.')->group(function () {

            Route::get('/', [SuperAdminController::class, 'index'])->name('index');
            Route::post('/', [SuperAdminController::class, 'store'])->name('store');
            Route::get('/{id}', [SuperAdminController::class, 'show'])->name('show');
            Route::put('/{id}', [SuperAdminController::class, 'update'])->name('update');
            Route::delete('/{id}', [SuperAdminController::class, 'destroy'])->name('destroy');

            Route::get('/trash/list', [SuperAdminController::class, 'trash'])->name('trash');
            Route::post('/{id}/restore', [SuperAdminController::class, 'restore'])->name('restore');
            Route::delete('/{id}/force-delete', [SuperAdminController::class, 'forceDelete'])->name('force-delete');

            Route::post('/{id}/change-password', [SuperAdminController::class, 'changePassword'])->name('change-password');

            Route::post('/{id}/send-invitation', [SuperAdminController::class, 'sendInvitation'])->name('send-invitation');
            Route::post('/{id}/resend-invitation', [SuperAdminController::class, 'resendInvitation'])->name('resend-invitation');
            Route::get('/{id}/invitation-status', [SuperAdminController::class, 'getInvitationStatus'])->name('invitation-status');
            Route::get('/invitations/{invitationId}', [SuperAdminController::class, 'getInvitationDetails'])->name('invitation-details');
            Route::get('/{id}/available-channels', [SuperAdminController::class, 'getAvailableChannels'])->name('available-channels');

            Route::post('/{id}/activate', [SuperAdminController::class, 'activateSuperAdmin'])->name('activate');
            Route::post('/{id}/suspend', [SuperAdminController::class, 'suspendSuperAdmin'])->name('suspend');
            Route::post('/{id}/deactivate', [SuperAdminController::class, 'deactivateSuperAdmin'])->name('deactivate');

            Route::post('/{id}/force-verify-phone', [SuperAdminController::class, 'forceVerifyPhone'])->name('force-verify-phone');
            Route::delete('/{id}/remove-phone-verification', [SuperAdminController::class, 'removePhoneVerification'])->name('remove-phone-verification');
            Route::post('/{id}/force-verify-email', [SuperAdminController::class, 'forceVerifyEmail'])->name('force-verify-email');
            Route::delete('/{id}/remove-email-verification', [SuperAdminController::class, 'removeEmailVerification'])->name('remove-email-verification');

            Route::get('/{id}/check-relations', [SuperAdminController::class, 'checkRelations'])->name('check-relations');

            Route::post('/bulk-actions', [SuperAdminController::class, 'bulkAction'])->name('bulk-actions');
            Route::get('/dashboard/statistics', [SuperAdminController::class, 'dashboardStatistics'])->name('dashboard-statistics');

            Route::get('/{id}/invitation-history', [SuperAdminController::class, 'invitationHistory'])->name('invitation-history');
            Route::get('/{id}/activities', [SuperAdminController::class, 'activities'])->name('activities');
            Route::get('/{id}/activities/export', [SuperAdminController::class, 'exportActivities'])->name('activities.export');
        });

        /*
        |--------------------------------------------------------------------------
        | Developer System Settings API Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('developer')->name('api.developer.')->group(function () {

            Route::get('/dashboard', [SystemSettingsController::class, 'dashboard'])->name('dashboard');
            Route::get('/metrics', [SystemSettingsController::class, 'getMetrics'])->name('metrics');
            Route::get('/health', [SystemSettingsController::class, 'getHealthStatus'])->name('health');

            Route::prefix('billing')->name('billing.')->group(function () {
                Route::get('/history', [SystemSettingsController::class, 'getBillingHistory'])->name('history');
                Route::post('/paystack/initiate', [SystemSettingsController::class, 'initiatePaystackPayment'])->name('paystack.initiate');
                Route::post('/paystack/callback', [SystemSettingsController::class, 'paystackCallback'])->name('paystack.callback');
            });

            Route::prefix('system')->name('system.')->group(function () {
                Route::post('/cache/clear', [SystemSettingsController::class, 'clearCache'])->name('cache.clear');
                Route::post('/backup/database', [SystemSettingsController::class, 'backupDatabase'])->name('backup.database');
                Route::post('/optimize', [SystemSettingsController::class, 'optimizeSystem'])->name('optimize');

                Route::prefix('maintenance')->name('maintenance.')->group(function () {
                    Route::post('/down', [SystemSettingsController::class, 'putApplicationDown'])->name('down');
                    Route::post('/up', [SystemSettingsController::class, 'bringApplicationUp'])->name('up');
                });
            });

            Route::prefix('queue')->name('queue.')->group(function () {
                Route::get('/status', [SystemSettingsController::class, 'getQueueStatus'])->name('status');
                Route::post('/restart', [SystemSettingsController::class, 'restartQueue'])->name('restart');
                Route::post('/retry-failed', [SystemSettingsController::class, 'retryFailedJobs'])->name('retry-failed');
            });

            Route::prefix('logs')->name('logs.')->group(function () {
                Route::get('/', [SystemSettingsController::class, 'getLogs'])->name('index');
                Route::delete('/clear', [SystemSettingsController::class, 'clearLogs'])->name('clear');
            });

            Route::prefix('database')->name('database.')->group(function () {
                Route::get('/info', [SystemSettingsController::class, 'getDatabaseInfo'])->name('info');
                Route::post('/optimize', [SystemSettingsController::class, 'optimizeDatabase'])->name('optimize');
                Route::post('/backup', [SystemSettingsController::class, 'backupDatabase'])->name('backup');
            });

            Route::prefix('security')->name('security.')->group(function () {
                Route::post('/scan', [SystemSettingsController::class, 'runSecurityScan'])->name('scan');
                Route::post('/permissions/fix', [SystemSettingsController::class, 'fixPermissions'])->name('permissions.fix');
            });
        });

        // ═══════════════════════════════════════════════════════════════
        // ⭐ PROPERTIES (Authenticated)
        // ═══════════════════════════════════════════════════════════════
        //
        // ── FIXED: this block previously lived inside the
        //    `if (app()->environment('local', 'testing'))` debug block,
        //    which meant properties routes only existed in local/testing.
        //    It is now inside the normal auth-protected group.
        //
        Route::prefix('')->group(function () {

            // ── LIST / CREATE ──
            Route::get   ('/properties',                [PropertyApiController::class, 'index']);
            Route::post  ('/properties',                [PropertyApiController::class, 'store']);

            // ── STATIC SUB-ROUTES (must come before /properties/{property}) ──
            Route::post  ('/properties/check-duplicate', [PropertyApiController::class, 'checkDuplicate']);
            Route::post  ('/properties/construction',    [PropertyApiController::class, 'updateConstruction']);
            Route::get   ('/properties/trash',             [PropertyApiController::class, 'trashed']);
            Route::get   ('/properties/trash/count',       [PropertyApiController::class, 'trashCount']);
            Route::post  ('/properties/trash/restore-all', [PropertyApiController::class, 'restoreAll']);
            Route::delete('/properties/trash/empty',       [PropertyApiController::class, 'emptyTrash']);

            Route::get   ('/landlords',                  [PropertyApiController::class, 'getAvailableLandlords']);
            Route::get   ('/my-properties',              [PropertyApiController::class, 'myProperties']);

            // ── DYNAMIC (by {property} id) ──
            Route::get   ('/properties/{property}',                [PropertyApiController::class, 'show']);
            Route::put   ('/properties/{property}',                [PropertyApiController::class, 'update']);
            Route::patch ('/properties/{property}',                [PropertyApiController::class, 'update']);
            Route::delete('/properties/{property}',                [PropertyApiController::class, 'destroy']);

            Route::post  ('/properties/{property}/restore',        [PropertyApiController::class, 'restore']);
            Route::delete('/properties/{property}/force-delete',   [PropertyApiController::class, 'forceDelete'])
                ->withTrashed();

            Route::put   ('/properties/{property}/construction',   [PropertyApiController::class, 'updateConstructionWithProperty']);
            Route::patch ('/properties/{property}/construction',   [PropertyApiController::class, 'updateConstructionWithProperty']);
            Route::post  ('/properties/{property}/mark-active',    [PropertyApiController::class, 'markActive']);

            // Photos
            Route::get   ('/properties/{property}/photos',                  [PropertyApiController::class, 'getPhotoGallery']);
            Route::post  ('/properties/{property}/photos',                  [PropertyApiController::class, 'uploadPhotos']);
            Route::delete('/properties/{property}/photos/{photo}',          [PropertyApiController::class, 'deletePhoto']);
            Route::post  ('/properties/{property}/photos/{photo}/primary',  [PropertyApiController::class, 'setPrimaryPhoto']);

            // Ownership transfer
            Route::post  ('/properties/{property}/transfer',       [PropertyApiController::class, 'transferOwnership']);
        });

        // ═══════════════════════════════════════════════════════════════
        // ⭐ FIELD AGENT — PROPERTIES
        // ═══════════════════════════════════════════════════════════════
        Route::prefix('field-agent')->group(function () {

            // ── Property list / create ──
            Route::get   ('/properties',                [FieldAgentPropertyApiController::class, 'index']);
            Route::post  ('/properties',                [FieldAgentPropertyApiController::class, 'store']);

            // ── Static sub-routes (MUST come before {property}) ──
            Route::get   ('/properties/type-stats',     [FieldAgentPropertyApiController::class, 'getPropertyTypeStats']);
            Route::get   ('/properties/by-type/{slug}', [FieldAgentPropertyApiController::class, 'getByPropertyType']);
            Route::get   ('/properties/by-plan/{plan}', [FieldAgentPropertyApiController::class, 'getByRegistrationPlan']);

            Route::get   ('/my-properties',             [FieldAgentPropertyApiController::class, 'myProperties']);

            // ── Dynamic: property-scoped ──
            Route::get   ('/properties/{property}',     [FieldAgentPropertyApiController::class, 'show']);
            Route::put   ('/properties/{property}',     [FieldAgentPropertyApiController::class, 'update']);
            Route::patch ('/properties/{property}',     [FieldAgentPropertyApiController::class, 'update']);
            Route::delete('/properties/{property}',     [FieldAgentPropertyApiController::class, 'destroy']);

            Route::get   ('/properties/{property}/counts', [FieldAgentPropertyApiController::class, 'getAssignmentCounts']);

            // ── Dashboard / stats ──
            Route::get   ('/performance',               [FieldAgentPropertyApiController::class, 'performance']);
            Route::get   ('/statistics',                [FieldAgentPropertyApiController::class, 'statistics']);

            /*
            |--------------------------------------------------------------------------
            | ⭐ FIELD AGENT — REGISTRATION PLANS
            |--------------------------------------------------------------------------
            |
            | ── NEW: scoped list of plans assigned to the authenticated agent,
            |    plus a per-plan counts endpoint so the mobile app can render
            |    plan cards without hitting the admin route (which requires
            |    admin role and would 403 a field agent).
            |
            | Effective prefix: /api/v1/field-agent/registration-plans
*/
Route::prefix('registration-plans')->name('api.field-agent.registration-plans.')->group(function () {

    // Assigned plans list.
    Route::get('/', [FieldAgentPropertyApiController::class, 'myRegistrationPlans'])
        ->name('index');

    // Per-plan property counts (plan-wide + per-agent).
    Route::get('/{id}/property-counts',
        [FieldAgentPropertyApiController::class, 'myPlanPropertyCounts'])
        ->name('property-counts');
});
        });
    });

    /*
    |--------------------------------------------------------------------------
    | API Version Information
    |--------------------------------------------------------------------------
    */
    Route::get('/version', function () {
        return response()->json([
            'success' => true,
            'version' => '1.0.0',
            'api_version' => 'v1',
            'release_date' => '2024-01-01',
            'docs_url' => config('app.docs_url', '#'),
        ]);
    })->name('api.version');

    /*
    |--------------------------------------------------------------------------
    | Fallback Route for Undefined Endpoints
    |--------------------------------------------------------------------------
    */
    Route::fallback(function () {
        return response()->json([
            'success' => false,
            'message' => 'API endpoint not found.',
            'error_code' => 'ENDPOINT_NOT_FOUND',
            'status_code' => 404,
            'available_endpoints' => [
                // ========== PUBLIC ENDPOINTS ==========
                'GET    /v1/health',
                'GET    /v1/version',
                'GET    /v1/system-settings',
                'GET    /v1/registration-status',
                'GET    /v1/shared/photo/{token}',
                'GET    /v1/shared/photo/{token}/thumbnail',
                'GET    /v1/profile/email/verify/{token}',
                'POST   /v1/email/verify',
                'POST   /v1/email/resend',
                'POST   /v1/auth/login',
                'POST   /v1/auth/2fa/verify',
                'POST   /v1/auth/2fa/resend',
                'POST   /v1/auth/forgot-password',
                'POST   /v1/auth/reset-password',

                // ========== INVITATIONS (PUBLIC) ==========
                'GET    /v1/invitations/{token}/verify',
                'GET    /v1/invitations/{token}/details',
                'POST   /v1/invitations/{token}/accept',
                'POST   /v1/invitations/{token}/track-view',
                'POST   /v1/invitations/accept                 (legacy shim)',

                // ========== INVITATIONS (AUTHENTICATED — ADMIN) ==========
                'POST   /v1/invitations/send',
                'POST   /v1/invitations/{userId}/resend',
                'GET    /v1/invitations/status/{userId}',
                'GET    /v1/invitations/history/{userId}',
                'GET    /v1/invitations/channels/{userId}',
                'GET    /v1/invitations/statistics',
                'GET    /v1/invitations/analytics',
                'GET    /v1/invitations/system-status',
                'GET    /v1/invitations/expiry-configuration',
                'POST   /v1/invitations/cleanup',
                'POST   /v1/invitations/{id}/cancel',
                'GET    /v1/invitations',
                'GET    /v1/invitations/{invitationId}',

                // ========== SYSTEM SETTINGS (ADMIN) ==========
                'GET    /v1/admin/system-settings',
                'PUT    /v1/admin/system-settings',
                'POST   /v1/admin/toggle-registration',
                'GET    /v1/admin/notification-channels',
                'PUT    /v1/admin/notification-channels',
                'POST   /v1/admin/test-notification-channels',
                'GET    /v1/admin/invoice-settings',
                'POST   /v1/admin/toggle-auto-invoice',
                'POST   /v1/admin/reminder-settings',
                'POST   /v1/admin/validate-invoice-settings',
                'POST   /v1/admin/test-reminder-system',
                'GET    /v1/admin/payment-gateways',
                'PUT    /v1/admin/payment-gateways',
                'POST   /v1/admin/test-configuration',
                'GET    /v1/admin/roles',
                'GET    /v1/admin/property-owners',
                'GET    /v1/admin/users/export',
                'GET    /v1/admin/property-owners/export',

                // ========== REGISTRATION PLANS (ADMIN) ==========
                'GET    /v1/admin/field-agents',
                'GET    /v1/admin/registration-plans',
                'POST   /v1/admin/registration-plans',
                'GET    /v1/admin/registration-plans/stats',
                'GET    /v1/admin/registration-plans/trashed',
                'GET    /v1/admin/registration-plans/zones',
                'GET    /v1/admin/registration-plans/sections',
                'POST   /v1/admin/registration-plans/validate-pattern',
                'GET    /v1/admin/registration-plans/analytics',
                'GET    /v1/admin/registration-plans/export',
                'GET    /v1/admin/registration-plans/analytics/export',
                'GET    /v1/admin/registration-plans/property-counts?ids=1,2,3',
                'GET    /v1/admin/registration-plans/{id}',
                'PUT    /v1/admin/registration-plans/{id}',
                'DELETE /v1/admin/registration-plans/{id}',
                'POST   /v1/admin/registration-plans/{id}/restore',
                'DELETE /v1/admin/registration-plans/{id}/force-delete',
                'GET    /v1/admin/registration-plans/{id}/property-counts',
                'GET    /v1/admin/registration-plans/{id}/sequence-preview',
                'GET    /v1/admin/registration-plans/{id}/agents',
                'POST   /v1/admin/registration-plans/{id}/agents',
                'DELETE /v1/admin/registration-plans/{id}/agents/{agentId}',
                'POST   /v1/admin/registration-plans/{id}/agents/{agentId}/resend-invitation',
                'POST   /v1/admin/registration-plans/{id}/mark-in-progress',
                'POST   /v1/admin/registration-plans/{id}/mark-completed',
                'POST   /v1/admin/registration-plans/{id}/reactivate',
                'POST   /v1/admin/registration-plans/{id}/cancel',

                // ========== REGISTRATION PLANS (FIELD AGENT) ==========
                'GET    /v1/field-agent/registration-plans',
                'GET    /v1/field-agent/registration-plans/{id}/property-counts',

                // ========== PROPERTIES ==========
                'GET    /v1/properties',
                'POST   /v1/properties',
                'POST   /v1/properties/check-duplicate',
                'POST   /v1/properties/construction',
                'GET    /v1/properties/trash',
                'GET    /v1/properties/trash/count',
                'POST   /v1/properties/trash/restore-all',
                'DELETE /v1/properties/trash/empty',
                'GET    /v1/landlords',
                'GET    /v1/my-properties',
                'GET    /v1/properties/{property}',
                'PUT    /v1/properties/{property}',
                'PATCH  /v1/properties/{property}',
                'DELETE /v1/properties/{property}',
                'POST   /v1/properties/{property}/restore',
                'DELETE /v1/properties/{property}/force-delete',
                'PUT    /v1/properties/{property}/construction',
                'PATCH  /v1/properties/{property}/construction',
                'POST   /v1/properties/{property}/mark-active',
                'GET    /v1/properties/{property}/photos',
                'POST   /v1/properties/{property}/photos',
                'DELETE /v1/properties/{property}/photos/{photo}',
                'POST   /v1/properties/{property}/photos/{photo}/primary',
                'POST   /v1/properties/{property}/transfer',

                // ========== FIELD AGENT — PROPERTIES ==========
                'GET    /v1/field-agent/properties',
                'POST   /v1/field-agent/properties',
                'GET    /v1/field-agent/properties/type-stats',
                'GET    /v1/field-agent/properties/by-type/{slug}',
                'GET    /v1/field-agent/properties/by-plan/{plan}',
                'GET    /v1/field-agent/my-properties',
                'GET    /v1/field-agent/properties/{property}',
                'PUT    /v1/field-agent/properties/{property}',
                'PATCH  /v1/field-agent/properties/{property}',
                'DELETE /v1/field-agent/properties/{property}',
                'GET    /v1/field-agent/properties/{property}/counts',
                'GET    /v1/field-agent/performance',
                'GET    /v1/field-agent/statistics',

                // ========== USER MANAGEMENT (ADMIN) ==========
                'GET    /v1/admin/users/create',
                'GET    /v1/admin/users',
                'POST   /v1/admin/users',
                'GET    /v1/admin/users/stats',
                'GET    /v1/admin/users/search',
                'GET    /v1/admin/users/email-list',
                'GET    /v1/admin/users/trashed',
                'GET    /v1/admin/users/archived',
                'POST   /v1/admin/users/bulk-action',
                'POST   /v1/admin/users/bulk-delete',
                'POST   /v1/admin/users/bulk-restore',
                'POST   /v1/admin/users/bulk-restore-archived',
                'POST   /v1/admin/users/bulk-permanent-delete',
                'POST   /v1/admin/users/bulk-permanent-delete-archived',
                'POST   /v1/admin/users/bulk-role-action',
                'POST   /v1/admin/users/empty-trash',
                'GET    /v1/admin/users/{id}/edit',
                'GET    /v1/admin/users/{id}',
                'PUT    /v1/admin/users/{id}',
                'DELETE /v1/admin/users/{id}',
                'DELETE /v1/admin/users/{id}/force',
                'DELETE /v1/admin/users/{id}/force-destroy',
                'POST   /v1/admin/users/{id}/restore',
                'POST   /v1/admin/users/{id}/restore-archived',
                'POST   /v1/admin/users/{id}/archive',
                'POST   /v1/admin/users/{id}/cancel-archival-schedule',
                'GET    /v1/admin/users/{id}/check-relations',
                'GET    /v1/admin/users/archived/{id}',
                'GET    /v1/admin/users/trashed/{id}',
                'POST   /v1/admin/users/{id}/activate',
                'POST   /v1/admin/users/{id}/suspend',
                'POST   /v1/admin/users/{id}/deactivate',
                'POST   /v1/admin/users/{id}/verify-phone',
                'POST   /v1/admin/users/{id}/invite',
                'POST   /v1/admin/users/{id}/invite/resend',
                'GET    /v1/admin/users/{id}/invitation-status',
                'GET    /v1/admin/users/{id}/available-channels',
                'PUT    /v1/admin/users/{id}/password',
                'GET    /v1/admin/users/{id}/roles',
                'PUT    /v1/admin/users/{id}/roles',
                'POST   /v1/admin/users/{id}/assign-landlord-role',
                'POST   /v1/admin/users/{id}/remove-landlord-role',

                // ========== PROTECTED ENDPOINTS ==========
                'POST   /v1/logout',
                'POST   /v1/logout-all-devices',
                'POST   /v1/refresh-token',
                'GET    /v1/user',
                'POST   /v1/change-password',
                'GET    /v1/devices',
                'DELETE /v1/devices/{deviceId}',
                'POST   /v1/devices/push-token',
                'GET    /v1/login-history',
                'GET    /v1/password-expiry',
                'GET    /v1/dashboard-redirect',

                // ========== PROFILE MANAGEMENT ==========
                'GET    /v1/profile',
                'PUT    /v1/profile/personal',
                'PUT    /v1/profile/contact',
                'PUT    /v1/profile/password',
                'PUT    /v1/profile/admin/password',
                'POST   /v1/profile/photo',
                'DELETE /v1/profile/photo',
                'POST   /v1/profile/phone/verify/send',
                'POST   /v1/profile/phone/verify/{code?}',
                'POST   /v1/profile/email/verify/send',
                'GET    /v1/profile/stats',
                'GET    /v1/profile/admin/stats',
                'GET    /v1/profile/security/stats',
                'GET    /v1/profile/developer/stats',
                'GET    /v1/profile/sanitation/stats',
                'GET    /v1/profile/contractor/stats',
                'GET    /v1/profile/download-data',
                'GET    /v1/profile/admin/download-data',
                'GET    /v1/profile/security/download-data',
                'GET    /v1/profile/developer/download-data',
                'DELETE /v1/profile/request-deletion',

                // Field Agent
                'PUT    /v1/profile/agent/info',
                'PUT    /v1/profile/agent/notifications',
                'PUT    /v1/profile/agent/security',
                'POST   /v1/profile/agent/id-document',
                'DELETE /v1/profile/agent/session/{sessionId}',

                // Security Personnel
                'PUT    /v1/profile/security/info',
                'POST   /v1/profile/security/phone/verify/send',
                'POST   /v1/profile/security/phone/verify',

                // Developer
                'PUT    /v1/profile/developer/settings',
                'POST   /v1/profile/developer/api-key/generate',
                'DELETE /v1/profile/developer/api-key/revoke',

                // Sanitation Personnel
                'PUT    /v1/profile/sanitation/settings',
                'PUT    /v1/profile/sanitation/availability',

                // Role-specific personal info
                'PUT    /v1/landlord/profile/personal',
                'PUT    /v1/landlord/profile/contact',
                'PUT    /v1/admin/profile/personal',
                'PUT    /v1/admin/profile/contact',
                'PUT    /v1/contractor/profile/personal',
                'PUT    /v1/contractor/profile/contact',
                'GET    /v1/contractor/profile/stats',
                'PUT    /v1/sanitation/profile/personal',
                'PUT    /v1/sanitation/profile/contact',
                'PUT    /v1/sanitation/profile/settings',
                'PUT    /v1/sanitation/profile/availability',
                'GET    /v1/sanitation/profile/stats',

                // Photo Management
                'GET    /v1/profile/photo',
                'POST   /v1/profile/photo',
                'DELETE /v1/profile/photo',
                'POST   /v1/profile/photo/upload-url',
                'POST   /v1/profile/photo/batch',
                'GET    /v1/profile/photo/admin/permissions',
                'GET    /v1/profile/photo/admin/users/{userId}',
                'POST   /v1/profile/photo/admin/users/{userId}',
                'DELETE /v1/profile/photo/admin/users/{userId}',

                // Developer Super Admin Management
                'GET    /v1/developer/super-admins',
                'POST   /v1/developer/super-admins',
                'GET    /v1/developer/super-admins/{id}',
                'PUT    /v1/developer/super-admins/{id}',
                'DELETE /v1/developer/super-admins/{id}',
                'GET    /v1/developer/super-admins/trash/list',
                'POST   /v1/developer/super-admins/{id}/restore',
                'DELETE /v1/developer/super-admins/{id}/force-delete',
                'POST   /v1/developer/super-admins/{id}/change-password',
                'POST   /v1/developer/super-admins/{id}/send-invitation',
                'POST   /v1/developer/super-admins/{id}/resend-invitation',
                'GET    /v1/developer/super-admins/{id}/invitation-status',
                'GET    /v1/developer/super-admins/invitations/{invitationId}',
                'GET    /v1/developer/super-admins/{id}/available-channels',
                'POST   /v1/developer/super-admins/{id}/activate',
                'POST   /v1/developer/super-admins/{id}/suspend',
                'POST   /v1/developer/super-admins/{id}/deactivate',
                'POST   /v1/developer/super-admins/{id}/force-verify-phone',
                'DELETE /v1/developer/super-admins/{id}/remove-phone-verification',
                'POST   /v1/developer/super-admins/{id}/force-verify-email',
                'DELETE /v1/developer/super-admins/{id}/remove-email-verification',
                'GET    /v1/developer/super-admins/{id}/check-relations',
                'POST   /v1/developer/super-admins/bulk-actions',
                'GET    /v1/developer/super-admins/dashboard/statistics',
                'GET    /v1/developer/super-admins/{id}/invitation-history',
                'GET    /v1/developer/super-admins/{id}/activities',
                'GET    /v1/developer/super-admins/{id}/activities/export',

                // Developer System Settings
                'GET    /v1/developer/dashboard',
                'GET    /v1/developer/metrics',
                'GET    /v1/developer/health',
                'GET    /v1/developer/billing/history',
                'POST   /v1/developer/billing/paystack/initiate',
                'POST   /v1/developer/system/cache/clear',
                'POST   /v1/developer/system/backup/database',
                'POST   /v1/developer/system/optimize',
                'POST   /v1/developer/system/maintenance/down',
                'POST   /v1/developer/system/maintenance/up',
                'GET    /v1/developer/queue/status',
                'POST   /v1/developer/queue/restart',
                'POST   /v1/developer/queue/retry-failed',
                'GET    /v1/developer/logs',
                'DELETE /v1/developer/logs/clear',
                'GET    /v1/developer/database/info',
                'POST   /v1/developer/database/optimize',
                'POST   /v1/developer/security/scan',
                'POST   /v1/developer/security/permissions/fix',
            ],
            'tips' => [
                'Include Bearer token in Authorization header for protected endpoints',
                'Use /v1/health to check API status',
                'Check /v1/version for API version information',
                'System settings endpoints support full configuration management',
                'Notification channels: email, sms, whatsapp',
                'Payment gateways: expresspay, hubtel, paystack, flutterwave',
                'At least one payment gateway must be enabled',
                'User management endpoints require admin or super admin role',
                'Bulk operations require super admin role',
                'Role management endpoints require super admin role',
                'Property owners endpoint requires admin or super admin role',
                'Archived users endpoints require admin or super admin role',
                'Empty trash operation requires super admin role',
                'Photo verification code can be passed as URL parameter or in request body as "verification_code"',
                'All profile photo endpoints support JPEG, PNG, GIF, WEBP formats (max 5MB)',
                'Images are automatically optimized to 800x800 max dimensions',
                'Admin photo management endpoints require super admin privileges',
                'Use pagination parameters: ?page=1&per_page=20 for list endpoints',
                'Email verification tokens expire after 60 minutes',
                'Phone verification codes expire after 10 minutes',
                'Password must contain at least 8 characters, one uppercase, one lowercase, one number, and one special character',
                'Profile completion is automatically updated after any profile change',
                'Role-specific endpoints will return 400 if user type doesn\'t match',
                'Invitation channels: email, sms, whatsapp (requires configured services)',
                'User types: admin, landlord, tenant, field_agent, security_personnel, sanitation_personnel, contractor, developer, super_admin',
                'User statuses: active, pending, suspended, inactive',
                'Favicon is supported with ICO, PNG, SVG, JPG, GIF formats (max 100KB)',
                'Test notification channels before enabling them in production',
                'Export endpoints: add ?export_format=csv for a streamed CSV download. Add ?export_format=pdf to receive a JSON payload (with meta.export_format="pdf") that the client renders into a PDF.',
                'Soft-deleted users are restorable with STATUS_PENDING; use status endpoints to re-activate after restore if needed.',
                'Reminder settings must have days_before between 1 and 30; should not exceed grace_period_days',
                'Auto invoice generation requires at least one enabled payment gateway',
                'Sanitation personnel can toggle availability at any time via /sanitation/profile/availability',
                'Contractor stats include contracts and milestones breakdown',
                'Sanitation stats include waste collected (kg) and completion rate',
                'Registration plan agents can be existing field agents or new ones created via phone/name/email',
                'Registration plan invitation methods: sms, whatsapp, email, all_channels',
                'Registration plan naming patterns: {letter}{number}, {number}, {letter}, {number}{letter}, or custom',
                'Registration plan sequence types: sequential, even_only, odd_only',
                'Starting point must match the naming pattern (e.g., A1 for letter+number, 1A for number+letter)',
                'Plans without agents remain as draft and cannot be activated until an agent is assigned',
                'Invitation acceptance requires: password (min 8, mixed case, number, special), agree_terms, agree_privacy',
                'Invitation tokens auto-repair corrupted expiration dates; check "was_repaired" flag in response',
                'Accepted invitations return a Sanctum token (auth_token) for the newly-active user',
                'Invitation verify/details endpoints are PUBLIC and rate-limit friendly — safe to call on every app launch',
                'Use /v1/invitations/{token}/track-view to record when an invitee opens the invitation screen',
                'Legacy POST /v1/invitations/accept is still supported but requires password fields to succeed',
            ],
        ], 404);
    });
});

/*
|--------------------------------------------------------------------------
| Debug Routes (Local / Testing Only)
|--------------------------------------------------------------------------
|
| ── FIXED: these previously lived INSIDE `Route::prefix('v1')`, which
|    meant anything after them (the properties routes) only existed
|    in local/testing environments. They're now outside, and the
|    properties routes are in the auth-protected group above.
*/
if (app()->environment('local', 'testing')) {

    Route::get('/debug/users', function () {
        $users = \App\Models\User::where('type', '!=', 'developer')->paginate(5);

        $response = response()->json([
            'success' => true,
            'data' => $users->items(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ]);

        $content = $response->getContent();

        for ($i = 0; $i < 3; $i++) {
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            } else {
                break;
            }
        }

        $response->setContent($content);
        $response->headers->set('Content-Length', strlen($content));

        return $response;
    });

    Route::get('/debug/bom-test', function () {
        $response = response()->json([
            'success' => true,
            'message' => 'BOM removal test',
            'data' => ['test' => 'This is a test response'],
        ]);

        $content = $response->getContent();

        for ($i = 0; $i < 3; $i++) {
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            } else {
                break;
            }
        }

        $response->setContent($content);
        return $response;
    });

    Route::get('/debug/invitation/{token}', function (string $token) {
        $invitation = \App\Models\UserInvitation::where('token', $token)
            ->with(['user', 'invitedBy'])
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'invitation' => [
                'id'                 => $invitation->id,
                'token'              => $invitation->token,
                'status'             => $invitation->status,
                'safe_status'        => method_exists($invitation, 'getSafeStatus') ? $invitation->getSafeStatus() : null,
                'invitation_type'    => $invitation->invitation_type,
                'created_at'         => optional($invitation->created_at)->toISOString(),
                'expires_at'         => optional($invitation->expires_at)->toISOString(),
                'accepted_at'        => optional($invitation->accepted_at)->toISOString(),
                'raw_expires_at'     => $invitation->getRawOriginal('expires_at'),
                'raw_created_at'     => $invitation->getRawOriginal('created_at'),
                'hours_difference'   => $invitation->getRawOriginal('created_at') && $invitation->getRawOriginal('expires_at')
                    ? \Carbon\Carbon::parse($invitation->getRawOriginal('created_at'))
                        ->diffInHours(\Carbon\Carbon::parse($invitation->getRawOriginal('expires_at')))
                    : null,
                'user' => $invitation->user ? [
                    'id'     => $invitation->user->id,
                    'name'   => $invitation->user->name,
                    'email'  => $invitation->user->email,
                    'type'   => $invitation->user->type,
                    'status' => $invitation->user->status,
                ] : null,
                'invited_by' => $invitation->invitedBy ? [
                    'id'   => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                ] : null,
            ],
        ]);
    })->where('token', '[A-Za-z0-9\-_]+');
}
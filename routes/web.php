<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\DeviceTrackingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInvitationController; 
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\field\FieldAgentPropertyController;
use App\Http\Controllers\PropertyTrashController;
use App\Http\Controllers\PropertyInvitationController;
use App\Http\Controllers\PropertyUnitController;
use App\Http\Controllers\PropertyUnitTenantController;
use App\Http\Controllers\PropertyUnitMaintenanceController;
use App\Http\Controllers\Tenant\TenantMaintenanceController;
use App\Http\Controllers\PropertyUnitLeaseController;
use App\Http\Controllers\PropertyUnitFinancialController; 
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\RegistrationPlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Admin\PaymentProviderController;
use App\Http\Controllers\Developer\PaymentProviderController as DeveloperPaymentProviderController;
use App\Http\Controllers\Admin\SmsProviderController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AgentInvitationController;
use App\Http\Controllers\SuperAdminController; 
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\LandlordInvitationController;
use App\Http\Controllers\Admin\EmailConfigController;
use App\Http\Controllers\Admin\WhatsAppConfigController;
use App\Http\Controllers\Admin\PaymentConfigController;
use App\Http\Controllers\Admin\LogoController;
use App\Http\Controllers\Admin\SystemInfoController;
use App\Http\Controllers\RegistrationPlanAgentController;
use App\Http\Controllers\RegistrationPlanStatusController;
use App\Http\Controllers\RegistrationPlanTrashController;
use App\Http\Controllers\RegistrationPlanPatternController;
use App\Http\Controllers\RegistrationPlanExportController;
use App\Http\Controllers\RegistrationPlanAnalyticsController;
use App\Http\Controllers\RegistrationPlanServiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Developer\DashboardController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\Developer\LogController;
use App\Http\Controllers\Developer\SystemHealthController;
use App\Http\Controllers\Developer\MaintenanceController;
use App\Http\Controllers\Developer\EmergencyModeController;
use App\Http\Controllers\Admin\SecurityScheduleController as AdminSecurityScheduleController;
use App\Http\Controllers\Security\SecurityScheduleController as SecurityScheduleController;
use App\Http\Controllers\Admin\RotationGroupController;
use App\Http\Controllers\Admin\RotationHistoryController;
use App\Http\Controllers\Admin\SecurityPostController as AdminSecurityPostController;
use App\Http\Controllers\Security\SecurityPostController as SecurityPostController;
use App\Http\Controllers\Admin\PostQrCodeController;
use App\Http\Controllers\Security\SecurityDashboardController;
use App\Http\Controllers\Admin\SecurityShiftController;
use App\Http\Controllers\Admin\SecurityReportsController;
use App\Http\Controllers\Admin\AdminSupervisorAssignmentController;
use App\Http\Controllers\Security\SupervisorAssignmentController;
use App\Http\Controllers\Security\SupervisorController;
use App\Http\Controllers\Admin\WhatsAppProviderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Admin\PropertyOwnershipTransferController;
use App\Http\Controllers\Admin\TransferTrashController;
use App\Http\Controllers\Admin\TransferArchiveController;
use App\Http\Controllers\Landlord\LandlordTransferController;
use App\Http\Controllers\Landlord\TransferResubmissionController;
use App\Http\Controllers\PropertyHistoryController;
use App\Http\Controllers\TransferWebhookController;
use App\Models\PropertyOwnershipTransferArchive;
use App\Models\PropertyOwnershipTransfer;
use App\Models\Property;
use App\Http\Controllers\Developer\DeveloperSuperAdminController;
use App\Http\Controllers\SuperAdmin\SuperAdminSuperAdminController;
use App\Http\Controllers\Developer\DeveloperSystemSettingsController;
use App\Http\Controllers\Developer\DeveloperSettingsController;
use App\Http\Controllers\Developer\DeveloperBillingController;
use App\Http\Controllers\Developer\DeveloperMonitoringController;
use App\Http\Controllers\Developer\DeveloperBackupController;
use App\Http\Controllers\Developer\DeveloperPaymentController;
use App\Http\Controllers\Developer\DeveloperRequestController;
use App\Http\Controllers\Developer\SuperAdminBillingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LandlordConstructionRegistrationController;
use App\Http\Controllers\Admin\AdminConstructionRegistrationController;
use App\Http\Controllers\TenantInvoiceController;
use App\Http\Controllers\Admin\NotificationChannelController;
use App\Http\Controllers\LandlordDashboardController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\FieldAgentDashboardController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Landlord\ConstructionContractController as LandlordConstructionContractController;
use App\Http\Controllers\Admin\ConstructionContractController as AdminConstructionContractController;
use App\Http\Controllers\Contractor\ContractorController;
use App\Http\Controllers\Contractor\ConstructionContractController;
use App\Http\Controllers\Contractor\CalendarController;
use App\Http\Controllers\Contractor\ReportController;
use App\Http\Controllers\Contractor\ConstructionWorkerController;
use App\Http\Controllers\UserEmailAccountController;
use App\Http\Controllers\Public\BadgeVerificationController;
use App\Http\Controllers\Admin\AdminWorkerController;
use App\Http\Controllers\Security\SecurityPersonnelAssignmentController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\Admin\WhatsAppMessageController;
use App\Http\Controllers\Admin\WhatsAppTemplateController;
use App\Http\Controllers\Admin\WhatsAppLogController;
use App\Http\Controllers\Admin\WhatsAppWebhookController;
use App\Http\Controllers\Sanitation\SanitationController;
use App\Http\Controllers\Sanitation\PersonnelController;
use App\Http\Controllers\Sanitation\WorkerController;
use App\Http\Controllers\Sanitation\CollectionZoneController;
use App\Http\Controllers\Sanitation\SanitationSettingController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ThemePreviewController;
use App\Http\Controllers\Landlord\WasteCollectionController;
use App\Http\Middleware\EnsureRootSanitationAccess;
use App\Http\Controllers\LiveChatController;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Auth;

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Homepage route
Route::get('/', [HomeController::class, 'index'])->name('home');

// Legal pages
Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
Route::get('/privacy', [HomeController::class, 'privacy'])->name('privacy');

// Route for dashboard switching (multi-role users)
Route::post('/dashboard/switch', [HomeController::class, 'switchDashboard'])
    ->name('dashboard.switch')
    ->middleware('auth');

// Optional: Endpoint to clear dashboard selection
Route::post('/dashboard/clear-selection', [HomeController::class, 'clearSelectedRole'])
    ->name('dashboard.clear-selection')
    ->middleware('auth');

// API endpoint to get user roles for dashboard switcher
Route::get('/api/user-roles', [HomeController::class, 'getUserRoles'])
    ->name('api.user-roles')
    ->middleware('auth');




// ==================== STANDARD LOGIN ROUTES ====================

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ==================== SESSION STATUS AND MANAGEMENT ====================

// ⭐ ADDED: Session status and CSRF refresh endpoints
Route::get('/session-status', [LoginController::class, 'sessionStatus'])->name('session.status');
Route::post('/refresh-csrf', [LoginController::class, 'refreshCsrfToken'])->name('csrf.refresh');
Route::get('/page-expired', [LoginController::class, 'handlePageExpired'])->name('page.expired');

// ==================== SESSION MANAGEMENT ROUTES (via SessionController) ====================

Route::prefix('session')->name('session.')->group(function () {
    // Check for existing session (AJAX endpoint)
    Route::get('/check', [SessionController::class, 'check'])->name('check');
    
    // Continue with existing session
    Route::post('/continue', [SessionController::class, 'continueSession'])->name('continue');
    
    // Handle expired session
    Route::get('/expired', [SessionController::class, 'handleExpiredSession'])->name('expired');
    
    // Invalidate/clear current session
    Route::post('/invalidate', [SessionController::class, 'invalidateSession'])->name('invalidate');
    
    // Get all active sessions for current user (authenticated only)
    Route::middleware(['auth'])->get('/list', [SessionController::class, 'getActiveSessions'])->name('list');
    
    // Get session statistics (authenticated only)
    Route::middleware(['auth'])->get('/stats', [SessionController::class, 'getSessionStats'])->name('stats');
    
    // Revoke specific session (authenticated only)
    Route::middleware(['auth'])->post('/revoke/{sessionId}', [SessionController::class, 'revokeSession'])->name('revoke');
    
    // Revoke specific API token (authenticated only)
    Route::middleware(['auth'])->post('/revoke-token/{tokenId}', [SessionController::class, 'revokeToken'])->name('revoke-token');
    
    // Logout from all devices (authenticated only)
    Route::middleware(['auth'])->post('/logout-all', [SessionController::class, 'logoutAllDevices'])->name('logout-all');
});

// ==================== UNIFIED SOCIAL LOGIN ROUTES ====================

// Dynamic social login routes (supports multiple providers)
Route::get('/auth/{provider}', [LoginController::class, 'redirectToProvider'])
    ->where('provider', 'google|microsoft|facebook|apple|github')
    ->name('login.social');

Route::get('/auth/{provider}/callback', [LoginController::class, 'handleProviderCallback'])
    ->where('provider', 'google|microsoft|facebook|apple|github')
    ->name('login.social.callback');

// Legacy social login routes (for backward compatibility)
Route::get('/auth/google', [LoginController::class, 'redirectToGoogle'])->name('login.google.legacy');
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback']);
Route::get('/auth/microsoft', [LoginController::class, 'redirectToMicrosoft'])->name('login.microsoft.legacy');
Route::get('/auth/microsoft/callback', [LoginController::class, 'handleMicrosoftCallback']);

// Social account linking/unlinking (authenticated users only)
Route::middleware(['auth'])->group(function () {
    Route::post('/auth/{provider}/link', [LoginController::class, 'linkSocialAccount'])->name('social.link');
    Route::delete('/auth/{provider}/unlink', [LoginController::class, 'unlinkSocialAccount'])->name('social.unlink');
});

// ==================== TWO-FACTOR AUTHENTICATION ROUTES ====================

// 2FA verification routes (accessible before full authentication)
Route::prefix('2fa')->name('2fa.')->group(function () {
    Route::get('/verify', [LoginController::class, 'showTwoFactorForm'])->name('verify');
    Route::post('/verify', [LoginController::class, 'verifyTwoFactor'])->name('verify.submit');
    Route::post('/resend', [LoginController::class, 'resendTwoFactorCode'])->name('resend');
});

// 2FA management routes (authenticated users only)
Route::middleware(['auth'])->prefix('2fa')->name('2fa.')->group(function () {
    Route::post('/enable', [LoginController::class, 'enableTwoFactor'])->name('enable');
    Route::post('/disable', [LoginController::class, 'disableTwoFactor'])->name('disable');
    Route::get('/setup', [LoginController::class, 'showTwoFactorSetup'])->name('setup');
    Route::post('/verify-setup', [LoginController::class, 'verifyTwoFactorSetup'])->name('verify-setup');
    Route::get('/recovery-codes', [LoginController::class, 'showRecoveryCodes'])->name('recovery-codes');
    Route::post('/recovery-codes/regenerate', [LoginController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
});

// ==================== DEVICE TRACKING ROUTES ====================

Route::prefix('device')->name('device.')->group(function () {
    // Get registered devices for current user (authenticated only)
    Route::middleware(['auth'])->get('/list', [LoginController::class, 'getUserDevices'])->name('list');
    
    // Remove specific device (authenticated only)
    Route::middleware(['auth'])->delete('/remove/{deviceId}', [LoginController::class, 'removeDevice'])->name('remove');
    
    // Trust this device (skip 2FA for future logins)
    Route::middleware(['auth'])->post('/trust', [LoginController::class, 'trustDevice'])->name('trust');
    
    // Get current device info (authenticated only)
    Route::middleware(['auth'])->get('/current', [LoginController::class, 'getCurrentDeviceInfo'])->name('current');
});

// ==================== PASSWORD RESET ROUTES ====================

// Forgot Password Routes
Route::get('/forgot-password', [LoginController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [LoginController::class, 'sendResetLink'])->name('password.email');
Route::post('/forgot-password/resend', [LoginController::class, 'resendResetLink'])->name('password.resend');

// Reset Password Routes
Route::get('/reset-password/{token}', [LoginController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [LoginController::class, 'resetPassword'])->name('password.update');

// Password change for authenticated users
Route::middleware(['auth'])->prefix('password')->name('password.')->group(function () {
    Route::post('/change', [LoginController::class, 'changePassword'])->name('change');
    Route::get('/check-expiry', [LoginController::class, 'checkPasswordExpiry'])->name('check-expiry');
});

// ==================== MOBILE API LOGIN ROUTES ====================

Route::prefix('mobile')->name('mobile.')->group(function () {
    // Authentication endpoints
    Route::post('/login', [LoginController::class, 'mobileLogin'])->name('login');
    Route::post('/logout', [LoginController::class, 'mobileLogout'])->name('logout');
    Route::post('/refresh-token', [LoginController::class, 'refreshToken'])->name('refresh');
    
    // 2FA for mobile
    Route::post('/2fa/verify', [LoginController::class, 'mobileVerifyTwoFactor'])->name('2fa.verify');
    Route::post('/2fa/resend', [LoginController::class, 'mobileResendTwoFactor'])->name('2fa.resend');
    
    // Device management for mobile (authenticated via Sanctum)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/devices', [LoginController::class, 'getMobileDevices'])->name('devices');
        Route::delete('/device/{deviceId}', [LoginController::class, 'revokeMobileDevice'])->name('device.revoke');
        Route::post('/device/register-push', [LoginController::class, 'registerPushToken'])->name('device.register-push');
    });
});

// ==================== USER IMPERSONATION ROUTES ====================

Route::middleware(['auth'])->prefix('impersonate')->name('impersonate.')->group(function () {
    // Start impersonating a user
    Route::post('/start/{user}', [LoginController::class, 'startImpersonation'])
        ->name('start')
        ->middleware('can:impersonate-user,user');
    
    // Stop impersonating
    Route::post('/stop', [LoginController::class, 'stopImpersonation'])->name('stop');
    
    // Get current impersonation status
    Route::get('/status', [LoginController::class, 'getImpersonationStatus'])->name('status');
});

// ==================== LOGIN ACTIVITY ROUTES ====================

Route::middleware(['auth'])->prefix('activity')->name('activity.')->group(function () {
    // Get login history for current user
    Route::get('/login-history', [LoginController::class, 'getLoginHistory'])->name('login-history');
    
    // Get detailed activity for specific login
    Route::get('/login/{activityId}', [LoginController::class, 'getActivityDetails'])->name('activity-details');
    
    // Get suspicious activity alerts
    Route::get('/suspicious', [LoginController::class, 'getSuspiciousActivity'])->name('suspicious');
    
    // Clear activity history (requires password)
    Route::delete('/clear', [LoginController::class, 'clearActivityHistory'])->name('clear');
});

// ==================== ACCOUNT RECOVERY ROUTES ====================

Route::prefix('recovery')->name('recovery.')->group(function () {
    // Account recovery request (when user can't access email/phone)
    Route::get('/request', [LoginController::class, 'showRecoveryRequestForm'])->name('request');
    Route::post('/request', [LoginController::class, 'sendRecoveryRequest'])->name('submit');
    
    // Verify recovery identity
    Route::get('/verify/{token}', [LoginController::class, 'showRecoveryVerification'])->name('verify');
    Route::post('/verify/{token}', [LoginController::class, 'verifyRecoveryIdentity'])->name('verify.submit');
    
    // Complete account recovery
    Route::post('/complete', [LoginController::class, 'completeRecovery'])->name('complete');
});

// ==================== RATE LIMITED PUBLIC ENDPOINTS ====================

Route::middleware(['throttle:10,1'])->group(function () {
    // Rate-limited endpoints for security
    Route::post('/auth/check-email', [LoginController::class, 'checkEmailExists'])->name('auth.check-email');
    Route::post('/auth/check-phone', [LoginController::class, 'checkPhoneExists'])->name('auth.check-phone');
    Route::post('/auth/resend-verification', [LoginController::class, 'resendVerificationEmail'])->name('auth.resend-verification');
});

// ==================== API TOKEN MANAGEMENT ====================

Route::middleware(['auth:sanctum'])->prefix('api-tokens')->name('api-tokens.')->group(function () {
    Route::get('/', [LoginController::class, 'listApiTokens'])->name('list');
    Route::post('/create', [LoginController::class, 'createApiToken'])->name('create');
    Route::delete('/{tokenId}', [LoginController::class, 'revokeApiToken'])->name('revoke');
    Route::post('/{tokenId}/update-permissions', [LoginController::class, 'updateTokenPermissions'])->name('update-permissions');
});

// ==================== EMAIL VERIFICATION ROUTES ====================

Route::prefix('email')->name('verification.')->group(function () {
    Route::get('/verify/{id}/{hash}', [LoginController::class, 'verifyEmail'])
        ->name('verify')
        ->middleware(['signed', 'throttle:6,1']);
    
    Route::post('/verification-notification', [LoginController::class, 'sendVerificationEmail'])
        ->name('send')
        ->middleware(['throttle:3,1']);
});

// ==================== WEBHOOKS AND EXTERNAL SERVICES ====================

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Social login webhooks (for provider-specific events)
    Route::post('/google', [LoginController::class, 'handleGoogleWebhook'])->name('google');
    Route::post('/microsoft', [LoginController::class, 'handleMicrosoftWebhook'])->name('microsoft');
    
    // SMS delivery status webhooks
    Route::post('/sms/status', [LoginController::class, 'handleSmsDeliveryStatus'])->name('sms.status');
    
    // Email bounce handling
    Route::post('/email/bounce', [LoginController::class, 'handleEmailBounce'])->name('email.bounce');
});

// ==================== HEALTH CHECK AND MONITORING ====================

Route::prefix('auth')->name('auth.')->group(function () {
    // Health check for authentication services
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'session' => true,
                '2fa' => class_exists(\PragmaRX\Google2FA\Google2FA::class),
                'social' => [
                    'google' => !empty(config('services.google.client_id')),
                    'microsoft' => !empty(config('services.microsoft.client_id')),
                    'facebook' => !empty(config('services.facebook.client_id')),
                    'github' => !empty(config('services.github.client_id'))
                ],
                'rate_limiting' => true,
                'captcha' => config('auth.captcha_enabled', false)
            ]
        ]);
    })->name('health');
    
    // Get authentication configuration (for frontend)
    Route::get('/config', function () {
        return response()->json([
            'two_factor_enabled' => config('auth.two_factor.enabled', true),
            'captcha_enabled' => config('auth.captcha_enabled', false),
            'social_providers' => array_filter([
                'google' => !empty(config('services.google.client_id')),
                'microsoft' => !empty(config('services.microsoft.client_id')),
                'facebook' => !empty(config('services.facebook.client_id')),
                'github' => !empty(config('services.github.client_id'))
            ]),
            'session_lifetime' => config('session.lifetime', 120),
            'password_min_length' => config('auth.password_min_length', 8),
            'csrf_refresh_interval' => config('auth.csrf_refresh_interval', 600) // 10 minutes
        ]);
    })->name('config');
});

// ==================== DEBUG AND TESTING ROUTES ====================

// Only available in local environment
if (app()->environment('local')) {
    Route::prefix('debug')->name('debug.')->group(function () {
        // Phone number debug
        Route::get('/phone', [LoginController::class, 'debugPhoneNormalization'])
            ->name('phone')
            ->middleware(['auth', 'can:is-super-admin']);
        
        // Debug reset email
        Route::get('/reset/{token}', function ($token, Request $request) {
            return [
                'token' => $token,
                'email' => $request->query('email'),
                'decoded_email' => urldecode($request->query('email', '')),
                'full_url' => $request->fullUrl(),
                'timestamp' => now()->toDateTimeString()
            ];
        })->name('reset');
        
        // Debug 2FA
        Route::get('/2fa/{user}', function ($user) {
            $user = \App\Models\User::find($user);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            
            return response()->json([
                'user_id' => $user->id,
                'email' => $user->email,
                'two_factor_enabled' => $user->two_factor_enabled ?? false,
                'two_factor_method' => $user->two_factor_method ?? 'email',
                'has_backup_codes' => !empty($user->two_factor_backup_codes),
                'recovery_codes_count' => $user->two_factor_backup_codes ? count(json_decode($user->two_factor_backup_codes, true)) : 0
            ]);
        })->name('2fa')->middleware(['auth', 'can:is-super-admin']);
        
        // Debug session
        Route::get('/session', function () {
            return response()->json([
                'session_id' => session()->getId(),
                'has_user' => auth()->check(),
                'user_id' => auth()->id(),
                'session_data' => session()->all(),
                'two_factor_pending' => session()->has('2fa:user_id'),
                'impersonating' => session()->has('impersonate'),
                'csrf_token' => session()->token(),
                'cookies' => request()->cookies->all(),
                'headers' => [
                    'x-csrf-token' => request()->header('X-CSRF-TOKEN'),
                    'x-xsrf-token' => request()->header('X-XSRF-TOKEN'),
                ]
            ]);
        })->name('session')->middleware(['auth', 'can:is-super-admin']);
        
        // Debug route info
        Route::get('/routes', function () {
            $routes = collect(\Route::getRoutes())->filter(function ($route) {
                return str_starts_with($route->uri(), 'auth') ||
                       str_starts_with($route->uri(), 'login') ||
                       str_starts_with($route->uri(), 'session') ||
                       str_starts_with($route->uri(), '2fa') ||
                       str_starts_with($route->uri(), 'password') ||
                       str_starts_with($route->uri(), 'mobile');
            })->map(function ($route) {
                return [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                ];
            })->values();
            
            return response()->json([
                'total_auth_routes' => $routes->count(),
                'routes' => $routes
            ]);
        })->name('routes');
    });
}

// ==================== FALLBACK AND ERROR HANDLING ====================

// Catch-all for undefined auth routes (404)
Route::fallback(function () {
    $authPrefixes = ['auth', '2fa', 'session', 'password', 'mobile', 'device', 'impersonate', 'activity', 'recovery'];
    $path = request()->path();
    
    foreach ($authPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            // For API requests
            if (request()->wantsJson()) {
                return response()->json([
                    'error' => 'not_found',
                    'message' => 'Authentication endpoint not found'
                ], 404);
            }
            
            // For web requests, redirect to login
            return redirect()->route('login')
                ->with('error', 'Page not found. Please login to continue.');
        }
    }
});


// Dashboard redirect route
Route::get('/dashboard', [HomeController::class, 'redirectToDashboard'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| ✅ NEW: Developer Dashboard & Tools Routes - ENHANCED VERSION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    
    // =============================================
    // DASHBOARD ROUTES - ENHANCED WITH ALL MISSING ROUTES
    // =============================================
    
    // Main Developer Dashboard
    Route::get('/dashboard', [HomeController::class, 'developerDashboard'])->name('dashboard');
    Route::redirect('/', '/developer/dashboard')->name('index');
    
    // Dashboard Data APIs - COMPLETE SET
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics'])->name('dashboard.metrics');
    Route::get('/dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');
    Route::get('/dashboard/errors', [DashboardController::class, 'getDashboardErrors'])->name('dashboard.errors');
    Route::get('/dashboard/maintenance-schedule', [DashboardController::class, 'getMaintenanceSchedule'])->name('dashboard.maintenance-schedule');
    Route::get('/dashboard/emergency-modes', [DashboardController::class, 'getEmergencyModes'])->name('dashboard.emergency-modes'); // ✅ ADDED
    Route::get('/dashboard/impact-analysis', [DashboardController::class, 'getImpactAnalysis'])->name('dashboard.impact-analysis');
    Route::get('/dashboard/performance-insights', [DashboardController::class, 'getPerformanceInsights'])->name('dashboard.performance-insights'); // ✅ ADDED
    Route::get('/dashboard/alerts', [DashboardController::class, 'getSystemAlerts'])->name('dashboard.alerts');
    Route::get('/dashboard/trends', [DashboardController::class, 'getSystemTrends'])->name('dashboard.trends');
    
    // Dashboard Actions - COMPLETE SET
    Route::post('/dashboard/run-diagnostics', [DashboardController::class, 'runDiagnostics'])->name('dashboard.run-diagnostics');
    Route::post('/dashboard/run-command', [DashboardController::class, 'runCommand'])->name('dashboard.run-command');
    Route::post('/dashboard/generate-health-report', [DashboardController::class, 'generateHealthReport'])->name('dashboard.generate-health-report');
    Route::post('/dashboard/clear-alerts', [DashboardController::class, 'clearAlerts'])->name('dashboard.clear-alerts'); // ✅ ADDED
    
    // =============================================
    // ✅ NEW: QUICK ACTION ROUTES (For Blade Template) - KEEP ONLY THIS ONE
    // =============================================
    
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        // Emergency Mode Toggle (for Blade's emergency mode button)
        Route::post('/emergency/toggle', [DashboardController::class, 'toggleEmergencyMode'])->name('emergency.toggle');
        
        // Mark Error as Resolved (for Blade's mark resolved button)
        Route::post('/errors/{id}/mark-resolved', [DashboardController::class, 'markErrorResolved'])->name('errors.mark-resolved');
        
        // Schedule Maintenance (for Blade's maintenance modal)
        Route::post('/maintenance/schedule', [DashboardController::class, 'scheduleMaintenance'])->name('maintenance.schedule');
        
        // Get Communication Status (for Blade's communication services)
        Route::get('/communication-status', [DashboardController::class, 'getCommunicationStatus'])->name('communication-status');
        
        // Get Performance Metrics (for Blade's performance chart)
        Route::get('/performance-metrics', [DashboardController::class, 'getPerformanceMetrics'])->name('performance-metrics');
        
        // Download Impact Report (for Blade's download button)
        Route::get('/impact-report/download', [DashboardController::class, 'downloadImpactReport'])->name('impact-report.download');
        
        // Download Health Report (for Blade's download button)
        Route::get('/health-report/download', [DashboardController::class, 'downloadHealthReport'])->name('health-report.download');
        
        // Handle Alert Action (for Blade's alert buttons)
        Route::post('/alerts/{id}/action', [DashboardController::class, 'handleAlertAction'])->name('alerts.action');
    });
    
    // =============================================
    // ✅ NEW: LOGS ROUTES (For Blade Template)
    // =============================================
    
    Route::prefix('logs')->name('logs.')->group(function () {
        // Main logs page (Blade references this)
        Route::get('/', [LogController::class, 'index'])->name('index');
        
        // Get error statistics
        Route::get('/statistics/errors', [LogController::class, 'errorStatistics'])->name('statistics.errors');
        
        // Mark error as resolved
        Route::post('/{id}/mark-resolved', [LogController::class, 'markResolved'])->name('mark-resolved');
        
        // Get recent errors
        Route::get('/recent/errors', [LogController::class, 'recentErrors'])->name('recent.errors');
    });
    
    // =============================================
    // SYSTEM HEALTH MONITORING ROUTES
    // =============================================
    
    Route::prefix('health')->name('health.')->group(function () {
        // Overall Health
        Route::get('/', [SystemHealthController::class, 'index'])->name('index');
        Route::get('/overall', [SystemHealthController::class, 'getOverallHealth'])->name('overall');
        Route::get('/status', [SystemHealthController::class, 'getHealthStatus'])->name('status');
        Route::get('/metrics', [SystemHealthController::class, 'getSystemMetrics'])->name('metrics');
        Route::get('/live', [SystemHealthController::class, 'getLiveMetrics'])->name('live');
        
        // Detailed Health Checks
        Route::get('/server', [SystemHealthController::class, 'getServerHealth'])->name('server');
        Route::get('/database', [SystemHealthController::class, 'getDatabaseHealth'])->name('database');
        Route::get('/application', [SystemHealthController::class, 'getApplicationHealth'])->name('application');
        Route::get('/services', [SystemHealthController::class, 'getServiceHealth'])->name('services');
        Route::get('/performance', [SystemHealthController::class, 'getPerformanceHealth'])->name('performance');
        Route::get('/security', [SystemHealthController::class, 'getSecurityHealth'])->name('security');
        
        // Diagnostics & Reports
        Route::post('/diagnostics', [SystemHealthController::class, 'runDiagnostics'])->name('run-diagnostics');
        Route::get('/diagnostics/{id}', [SystemHealthController::class, 'getDiagnosticResult'])->name('diagnostics.result');
        Route::post('/reports/generate', [SystemHealthController::class, 'generateHealthReport'])->name('generate-report');
        Route::get('/reports/{id}', [SystemHealthController::class, 'getHealthReport'])->name('report');
        Route::get('/reports/{id}/download', [SystemHealthController::class, 'downloadHealthReport'])->name('report.download');
        
        // Health History & Trends
        Route::get('/history', [SystemHealthController::class, 'getHealthHistory'])->name('history');
        Route::get('/trends', [SystemHealthController::class, 'getHealthTrends'])->name('trends');
        Route::get('/alerts', [SystemHealthController::class, 'getHealthAlerts'])->name('alerts');
        
        // Custom Health Checks
        Route::post('/checks/custom', [SystemHealthController::class, 'runCustomCheck'])->name('checks.custom');
        Route::get('/checks', [SystemHealthController::class, 'listHealthChecks'])->name('checks.list');
        Route::get('/checks/{check}', [SystemHealthController::class, 'getHealthCheck'])->name('checks.show');
    });
    
    // =============================================
    // SYSTEM LOGS MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('logs')->name('logs.')->group(function () {
        // Log Overview
        Route::get('/', [LogController::class, 'index'])->name('index');
        Route::get('/overview', [LogController::class, 'overview'])->name('overview');
        Route::get('/statistics', [LogController::class, 'getStatistics'])->name('statistics');
        
        // Filtered Log Views
        Route::get('/errors', [LogController::class, 'errors'])->name('errors');
        Route::get('/warnings', [LogController::class, 'warnings'])->name('warnings');
        Route::get('/critical', [LogController::class, 'critical'])->name('critical');
        Route::get('/debug', [LogController::class, 'debug'])->name('debug');
        Route::get('/info', [LogController::class, 'info'])->name('info');
        
        // Log Search & Filter
        Route::get('/search', [LogController::class, 'search'])->name('search');
        Route::post('/filter', [LogController::class, 'filter'])->name('filter');
        Route::get('/by-date/{date}', [LogController::class, 'getByDate'])->name('by-date');
        Route::get('/by-level/{level}', [LogController::class, 'getByLevel'])->name('by-level');
        Route::get('/by-source/{source}', [LogController::class, 'getBySource'])->name('by-source');
        Route::get('/by-user/{userId}', [LogController::class, 'getByUser'])->name('by-user');
        
        // Individual Log Management
        Route::get('/{log}', [LogController::class, 'show'])->name('show');
        Route::put('/{log}', [LogController::class, 'update'])->name('update');
        Route::delete('/{log}', [LogController::class, 'destroy'])->name('destroy');
        Route::post('/{log}/resolve', [LogController::class, 'markAsResolved'])->name('resolve');
        Route::post('/{log}/unresolve', [LogController::class, 'markAsUnresolved'])->name('unresolve');
        
        // Bulk Operations
        Route::post('/bulk/resolve', [LogController::class, 'bulkResolve'])->name('bulk.resolve');
        Route::post('/bulk/delete', [LogController::class, 'bulkDelete'])->name('bulk.delete');
        Route::post('/bulk/archive', [LogController::class, 'bulkArchive'])->name('bulk.archive');
        Route::post('/clear/all', [LogController::class, 'clearAll'])->name('clear.all');
        Route::post('/clear/old', [LogController::class, 'clearOld'])->name('clear.old');
        
        // Log Analysis & Reports
        Route::get('/analysis', [LogController::class, 'analysis'])->name('analysis');
        Route::get('/trends', [LogController::class, 'trends'])->name('trends');
        Route::post('/reports/generate', [LogController::class, 'generateReport'])->name('generate-report');
        Route::get('/reports/{id}', [LogController::class, 'getReport'])->name('report');
        Route::get('/reports/{id}/download', [LogController::class, 'downloadReport'])->name('report.download');
        
        // Log Export
        Route::get('/export', [LogController::class, 'export'])->name('export');
        Route::get('/export/csv', [LogController::class, 'exportToCsv'])->name('export.csv');
        Route::get('/export/json', [LogController::class, 'exportToJson'])->name('export.json');
        Route::get('/export/pdf', [LogController::class, 'exportToPdf'])->name('export.pdf');
        
        // Real-time Log Monitoring
        Route::get('/stream', [LogController::class, 'stream'])->name('stream');
        Route::get('/live', [LogController::class, 'live'])->name('live');
        Route::get('/realtime', [LogController::class, 'realtime'])->name('realtime');
        
        // Log Settings & Configuration
        Route::get('/settings', [LogController::class, 'settings'])->name('settings');
        Route::post('/settings', [LogController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/retention', [LogController::class, 'updateRetention'])->name('settings.retention');
        Route::post('/settings/levels', [LogController::class, 'updateLogLevels'])->name('settings.levels');
        
        // Log Archives
        Route::get('/archives', [LogController::class, 'archives'])->name('archives');
        Route::get('/archives/{archive}', [LogController::class, 'showArchive'])->name('archives.show');
        Route::post('/archives/{archive}/restore', [LogController::class, 'restoreArchive'])->name('archives.restore');
        Route::delete('/archives/{archive}', [LogController::class, 'deleteArchive'])->name('archives.delete');
    });
    
    // =============================================
// MAINTENANCE MANAGEMENT ROUTES - UPDATED WITH ALL MISSING ROUTES
// =============================================

Route::prefix('maintenance')->name('maintenance.')->group(function () {
    // Maintenance Overview
    Route::get('/', [MaintenanceController::class, 'index'])->name('index');
    
    // ✅ ADDED: Statistics route (for index blade)
    Route::get('/statistics', [MaintenanceController::class, 'statistics'])->name('statistics');
    
    // ✅ ADDED: Calendar view (if referenced elsewhere)
    Route::get('/calendar', [MaintenanceController::class, 'calendar'])->name('calendar');
    Route::get('/upcoming', [MaintenanceController::class, 'upcoming'])->name('upcoming');
    Route::get('/active', [MaintenanceController::class, 'active'])->name('active');
    
    // Maintenance CRUD
    Route::get('/create', [MaintenanceController::class, 'create'])->name('create');
    Route::post('/', [MaintenanceController::class, 'store'])->name('store');
    Route::get('/{maintenance}', [MaintenanceController::class, 'show'])->name('show');
    
    // ✅ ADDED: Edit route (for show blade)
    Route::get('/{maintenance}/edit', [MaintenanceController::class, 'edit'])->name('edit');
    Route::put('/{maintenance}', [MaintenanceController::class, 'update'])->name('update');
    Route::delete('/{maintenance}', [MaintenanceController::class, 'destroy'])->name('destroy');
    
    // ✅ ADDED: History route (for show blade overflow menu)
    Route::get('/{maintenance}/history', [MaintenanceController::class, 'history'])->name('history');
    
    // Maintenance Actions
    Route::post('/{maintenance}/approve', [MaintenanceController::class, 'approve'])->name('approve');
    Route::post('/{maintenance}/start', [MaintenanceController::class, 'start'])->name('start');
    Route::post('/{maintenance}/complete', [MaintenanceController::class, 'complete'])->name('complete');
    Route::post('/{maintenance}/cancel', [MaintenanceController::class, 'cancel'])->name('cancel');
    
    // ✅ ADDED: Reschedule and extend routes (for show blade)
    Route::post('/{maintenance}/reschedule', [MaintenanceController::class, 'reschedule'])->name('reschedule');
    Route::post('/{maintenance}/extend', [MaintenanceController::class, 'extend'])->name('extend');
    
    // ✅ ADDED: Duplicate route (for show blade)
    Route::post('/{maintenance}/duplicate', [MaintenanceController::class, 'duplicate'])->name('duplicate');
    
    // ✅ ADDED: Export route (for show blade)
    Route::post('/{maintenance}/export', [MaintenanceController::class, 'export'])->name('export');
    Route::get('/{maintenance}/export/download', [MaintenanceController::class, 'downloadExport'])->name('export.download');
    
    // ✅ ADDED: Report route (for show blade)
    Route::get('/{maintenance}/report', [MaintenanceController::class, 'report'])->name('report');
    
    // Maintenance Notifications
    Route::post('/{maintenance}/resend-notifications', [MaintenanceController::class, 'resendNotifications'])->name('resend-notifications');
    Route::post('/{maintenance}/send-reminder', [MaintenanceController::class, 'sendReminder'])->name('send-reminder');
    Route::post('/{maintenance}/update-progress', [MaintenanceController::class, 'updateProgress'])->name('update-progress');
    
    // Maintenance Impact Analysis
    Route::get('/{maintenance}/impact', [MaintenanceController::class, 'impactAnalysis'])->name('impact');
    Route::get('/{maintenance}/affected-users', [MaintenanceController::class, 'affectedUsers'])->name('affected-users');
    Route::get('/{maintenance}/affected-modules', [MaintenanceController::class, 'affectedModules'])->name('affected-modules');
    
    // Maintenance Reports
    Route::get('/{maintenance}/generate-report', [MaintenanceController::class, 'generateReport'])->name('generate-report');
    Route::get('/{maintenance}/download-report', [MaintenanceController::class, 'downloadReport'])->name('download-report');
    
    // ✅ ADDED: API endpoints for blade templates
    Route::get('/api/upcoming', [MaintenanceController::class, 'apiUpcoming'])->name('api.upcoming');
    Route::get('/api/active', [MaintenanceController::class, 'apiActive'])->name('api.active');
    Route::get('/api/check-module/{module}', [MaintenanceController::class, 'apiCheckModule'])->name('api.check-module');
    
    // ✅ ADDED: Settings and templates (if needed)
    Route::get('/settings', [MaintenanceController::class, 'settings'])->name('settings');
    Route::post('/settings', [MaintenanceController::class, 'updateSettings'])->name('settings.update');
    
    // ✅ ADDED: Templates management
    Route::get('/templates', [MaintenanceController::class, 'templates'])->name('templates');
    Route::post('/templates', [MaintenanceController::class, 'storeTemplate'])->name('templates.store');
    Route::post('/templates/{template}/use', [MaintenanceController::class, 'useTemplate'])->name('templates.use');
});
    
   // =============================================
// EMERGENCY MODE ROUTES - COMPLETE AND UPDATED VERSION - KEEP ONLY THIS ONE
// =============================================

Route::prefix('emergency')->name('emergency.')->group(function () {
    // Main emergency mode listing and creation
    Route::get('/', [EmergencyModeController::class, 'index'])->name('index');
    Route::get('/create', [EmergencyModeController::class, 'create'])->name('create');
    Route::post('/', [EmergencyModeController::class, 'store'])->name('store');
    
    // Show emergency details
    Route::get('/{id}', [EmergencyModeController::class, 'show'])->name('show');
    
    // History and statistics
    Route::get('/{id}/history', [EmergencyModeController::class, 'history'])->name('history');
    Route::get('/statistics', [EmergencyModeController::class, 'statistics'])->name('statistics');
    
    // Emergency mode actions (POST routes) - CRITICAL FOR BLADE TEMPLATE
    Route::post('/{id}/activate', [EmergencyModeController::class, 'activate'])->name('activate');
    Route::post('/{id}/deactivate', [EmergencyModeController::class, 'deactivate'])->name('deactivate');
    Route::post('/{id}/extend', [EmergencyModeController::class, 'extend'])->name('extend');
    Route::post('/{id}/cancel', [EmergencyModeController::class, 'cancel'])->name('cancel');
    
    // Emergency Mode Management (from existing routes)
    Route::get('/status', [EmergencyModeController::class, 'status'])->name('status');
    Route::get('/settings', [EmergencyModeController::class, 'settings'])->name('settings');
    
    // Emergency Mode Actions
    Route::post('/update', [EmergencyModeController::class, 'update'])->name('update');
    
    // Emergency Mode Notifications
    Route::post('/notify/users', [EmergencyModeController::class, 'notifyUsers'])->name('notify.users');
    Route::post('/notify/admins', [EmergencyModeController::class, 'notifyAdmins'])->name('notify.admins');
    Route::post('/notify/all', [EmergencyModeController::class, 'notifyAll'])->name('notify.all');
    
    // Emergency Mode Access Control
    Route::get('/access', [EmergencyModeController::class, 'accessControl'])->name('access');
    Route::post('/access/users', [EmergencyModeController::class, 'manageUserAccess'])->name('access.users');
    Route::post('/access/roles', [EmergencyModeController::class, 'manageRoleAccess'])->name('access.roles');
    Route::post('/access/ip', [EmergencyModeController::class, 'manageIpAccess'])->name('access.ip');
    
    // Emergency Mode Reports
    Route::get('/reports', [EmergencyModeController::class, 'reports'])->name('reports');
    Route::get('/reports/{report}', [EmergencyModeController::class, 'showReport'])->name('reports.show');
    Route::post('/reports/generate', [EmergencyModeController::class, 'generateReport'])->name('reports.generate');
    Route::get('/reports/{report}/download', [EmergencyModeController::class, 'downloadReport'])->name('reports.download');
    
    // Emergency Mode Settings
    Route::post('/settings', [EmergencyModeController::class, 'updateSettings'])->name('settings.update');
    Route::get('/settings/triggers', [EmergencyModeController::class, 'triggerSettings'])->name('settings.triggers');
    Route::post('/settings/triggers', [EmergencyModeController::class, 'updateTriggerSettings'])->name('settings.triggers.update');
    Route::get('/settings/notifications', [EmergencyModeController::class, 'notificationSettings'])->name('settings.notifications');
    Route::post('/settings/notifications', [EmergencyModeController::class, 'updateNotificationSettings'])->name('settings.notifications.update');
    Route::get('/settings/access', [EmergencyModeController::class, 'accessSettings'])->name('settings.access');
    Route::post('/settings/access', [EmergencyModeController::class, 'updateAccessSettings'])->name('settings.access.update');
    
    // API endpoints
    Route::get('/api/active', [EmergencyModeController::class, 'apiActive'])->name('api.active');
    Route::get('/api/check-module/{module}', [EmergencyModeController::class, 'apiCheckModule'])->name('api.check.module');
    
    // ✅ ADDED: Toggle route (for Blade template quick actions)
    Route::post('/{id}/toggle', [EmergencyModeController::class, 'toggle'])->name('toggle');
    
    // ✅ ADDED: Active status API
    Route::get('/status/active', [EmergencyModeController::class, 'activeStatus'])->name('status.active');
});
    
    // =============================================
    // SYSTEM PERFORMANCE & MONITORING ROUTES
    // =============================================
    
    Route::prefix('performance')->name('performance.')->group(function () {
        // Performance Dashboard
        Route::get('/', function () {
            return redirect()->route('developer.performance.dashboard');
        })->name('index');
        Route::get('/dashboard', function () {
            return view('developer.performance.dashboard');
        })->name('dashboard');
        
        // Real-time Metrics
        Route::get('/metrics', [DashboardController::class, 'getMetrics'])->name('metrics');
        Route::get('/metrics/live', function () {
            return view('developer.performance.live-metrics');
        })->name('metrics.live');
        
        // Performance Analysis
        Route::get('/analysis', function () {
            return view('developer.performance.analysis');
        })->name('analysis');
        Route::get('/analysis/slow-queries', function () {
            return view('developer.performance.slow-queries');
        })->name('analysis.slow-queries');
        Route::get('/analysis/api-performance', function () {
            return view('developer.performance.api-performance');
        })->name('analysis.api-performance');
        Route::get('/analysis/database', function () {
            return view('developer.performance.database-performance');
        })->name('analysis.database');
        
        // Performance Trends
        Route::get('/trends', [DashboardController::class, 'getSystemTrends'])->name('trends');
        Route::get('/trends/response-time', function () {
            return view('developer.performance.trends.response-time');
        })->name('trends.response-time');
        Route::get('/trends/memory-usage', function () {
            return view('developer.performance.trends.memory-usage');
        })->name('trends.memory-usage');
        Route::get('/trends/cpu-usage', function () {
            return view('developer.performance.trends.cpu-usage');
        })->name('trends.cpu-usage');
        
        // Performance Testing
        Route::get('/testing', function () {
            return view('developer.performance.testing');
        })->name('testing');
        Route::post('/testing/load', function (Request $request) {
            return response()->json(['message' => 'Load test initiated']);
        })->name('testing.load');
        Route::post('/testing/stress', function (Request $request) {
            return response()->json(['message' => 'Stress test initiated']);
        })->name('testing.stress');
        
        // Performance Optimization
        Route::get('/optimization', function () {
            return view('developer.performance.optimization');
        })->name('optimization');
        Route::post('/optimization/cache', function (Request $request) {
            return response()->json(['message' => 'Cache optimization initiated']);
        })->name('optimization.cache');
        Route::post('/optimization/database', function (Request $request) {
            return response()->json(['message' => 'Database optimization initiated']);
        })->name('optimization.database');
        
        // Performance Reports
        Route::get('/reports', function () {
            return view('developer.performance.reports');
        })->name('reports');
        Route::post('/reports/generate', function (Request $request) {
            return response()->json(['message' => 'Performance report generation initiated']);
        })->name('reports.generate');
    });
    
    // =============================================
    // DATABASE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('database')->name('database.')->group(function () {
        // Database Overview
        Route::get('/', function () {
            return view('developer.database.index');
        })->name('index');
        Route::get('/status', function () {
            return view('developer.database.status');
        })->name('status');
        
        // Database Statistics
        Route::get('/statistics', function () {
            return view('developer.database.statistics');
        })->name('statistics');
        Route::get('/statistics/tables', function () {
            return view('developer.database.statistics-tables');
        })->name('statistics.tables');
        Route::get('/statistics/queries', function () {
            return view('developer.database.statistics-queries');
        })->name('statistics.queries');
        
        // Database Backups
        Route::get('/backups', function () {
            return view('developer.database.backups');
        })->name('backups');
        Route::post('/backups/create', function (Request $request) {
            return response()->json(['message' => 'Backup creation initiated']);
        })->name('backups.create');
        Route::get('/backups/{backup}', function ($backup) {
            return view('developer.database.backup-details', compact('backup'));
        })->name('backups.show');
        Route::post('/backups/{backup}/restore', function (Request $request, $backup) {
            return response()->json(['message' => 'Backup restoration initiated']);
        })->name('backups.restore');
        Route::delete('/backups/{backup}', function ($backup) {
            return response()->json(['message' => 'Backup deleted']);
        })->name('backups.destroy');
        
        // Database Queries
        Route::get('/queries', function () {
            return view('developer.database.queries');
        })->name('queries');
        Route::get('/queries/slow', function () {
            return view('developer.database.slow-queries');
        })->name('queries.slow');
        Route::post('/queries/execute', function (Request $request) {
            return response()->json(['message' => 'Query executed']);
        })->name('queries.execute');
        
        // Database Migrations
        Route::get('/migrations', function () {
            return view('developer.database.migrations');
        })->name('migrations');
        Route::post('/migrations/run', function (Request $request) {
            return response()->json(['message' => 'Migrations executed']);
        })->name('migrations.run');
        Route::post('/migrations/rollback', function (Request $request) {
            return response()->json(['message' => 'Migrations rolled back']);
        })->name('migrations.rollback');
        Route::post('/migrations/refresh', function (Request $request) {
            return response()->json(['message' => 'Migrations refreshed']);
        })->name('migrations.refresh');
        
        // Database Maintenance
        Route::get('/maintenance', function () {
            return view('developer.database.maintenance');
        })->name('maintenance');
        Route::post('/maintenance/optimize', function (Request $request) {
            return response()->json(['message' => 'Database optimization initiated']);
        })->name('maintenance.optimize');
        Route::post('/maintenance/repair', function (Request $request) {
            return response()->json(['message' => 'Database repair initiated']);
        })->name('maintenance.repair');
        Route::post('/maintenance/cleanup', function (Request $request) {
            return response()->json(['message' => 'Database cleanup initiated']);
        })->name('maintenance.cleanup');
    });
    
    // =============================================
    // SECURITY & AUDIT ROUTES
    // =============================================
    
    Route::prefix('security')->name('security.')->group(function () {
        // Security Dashboard
        Route::get('/', function () {
            return view('developer.security.index');
        })->name('index');
        Route::get('/dashboard', function () {
            return view('developer.security.dashboard');
        })->name('dashboard');
        
        // Security Scanning
        Route::get('/scan', function () {
            return view('developer.security.scan');
        })->name('scan');
        Route::post('/scan/run', function (Request $request) {
            return response()->json(['message' => 'Security scan initiated']);
        })->name('scan.run');
        Route::get('/scan/results/{id}', function ($id) {
            return view('developer.security.scan-results', compact('id'));
        })->name('scan.results');
        
        // Vulnerability Management
        Route::get('/vulnerabilities', function () {
            return view('developer.security.vulnerabilities');
        })->name('vulnerabilities');
        Route::get('/vulnerabilities/{id}', function ($id) {
            return view('developer.security.vulnerability-details', compact('id'));
        })->name('vulnerabilities.show');
        
        // Access Logs
        Route::get('/access-logs', function () {
            return view('developer.security.access-logs');
        })->name('access-logs');
        Route::get('/access-logs/{log}', function ($log) {
            return view('developer.security.access-log-details', compact('log'));
        })->name('access-logs.show');
        
        // Audit Trails
        Route::get('/audit', function () {
            return view('developer.security.audit');
        })->name('audit');
        Route::get('/audit/user/{userId}', function ($userId) {
            return view('developer.security.audit-user', compact('userId'));
        })->name('audit.user');
        
        // Security Settings
        Route::get('/settings', function () {
            return view('developer.security.settings');
        })->name('settings');
        Route::post('/settings', function (Request $request) {
            return response()->json(['message' => 'Security settings updated']);
        })->name('settings.update');
    });
    
    // =============================================
    // CACHE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('cache')->name('cache.')->group(function () {
        // Cache Dashboard
        Route::get('/', function () {
            return view('developer.cache.index');
        })->name('index');
        
        // Cache Statistics
        Route::get('/statistics', function () {
            return view('developer.cache.statistics');
        })->name('statistics');
        
        // Cache Management
        Route::get('/management', function () {
            return view('developer.cache.management');
        })->name('management');
        Route::post('/clear', function (Request $request) {
            Artisan::call('cache:clear');
            return response()->json(['message' => 'Cache cleared successfully']);
        })->name('clear');
        Route::post('/clear/{driver}', function (Request $request, $driver) {
            Artisan::call('cache:clear', ['--driver' => $driver]);
            return response()->json(['message' => "{$driver} cache cleared successfully"]);
        })->name('clear.driver');
        
        // Cache Configuration
        Route::get('/configuration', function () {
            return view('developer.cache.configuration');
        })->name('configuration');
        Route::post('/configuration', function (Request $request) {
            return response()->json(['message' => 'Cache configuration updated']);
        })->name('configuration.update');
    });
    
    // =============================================
    // QUEUE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('queue')->name('queue.')->group(function () {
        // Queue Dashboard
        Route::get('/', function () {
            return view('developer.queue.index');
        })->name('index');
        
        // Queue Monitoring
        Route::get('/monitoring', function () {
            return view('developer.queue.monitoring');
        })->name('monitoring');
        
        // Failed Jobs
        Route::get('/failed', function () {
            return view('developer.queue.failed');
        })->name('failed');
        Route::post('/failed/retry/{id}', function (Request $request, $id) {
            Artisan::call('queue:retry', ['id' => $id]);
            return response()->json(['message' => 'Job retried successfully']);
        })->name('failed.retry');
        Route::post('/failed/retry-all', function (Request $request) {
            Artisan::call('queue:retry', ['id' => 'all']);
            return response()->json(['message' => 'All failed jobs retried']);
        })->name('failed.retry-all');
        Route::delete('/failed/{id}', function (Request $request, $id) {
            Artisan::call('queue:forget', ['id' => $id]);
            return response()->json(['message' => 'Job forgotten successfully']);
        })->name('failed.forget');
        Route::delete('/failed/flush', function (Request $request) {
            Artisan::call('queue:flush');
            return response()->json(['message' => 'All failed jobs flushed']);
        })->name('failed.flush');
        
        // Queue Workers
        Route::get('/workers', function () {
            return view('developer.queue.workers');
        })->name('workers');
        Route::post('/workers/restart', function (Request $request) {
            Artisan::call('queue:restart');
            return response()->json(['message' => 'Queue workers restarted']);
        })->name('workers.restart');
    });
    
    // =============================================
    // API MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('api')->name('api.')->group(function () {
        // API Dashboard
        Route::get('/', function () {
            return view('developer.api.index');
        })->name('index');
        
        // API Documentation
        Route::get('/documentation', function () {
            return view('developer.api.documentation');
        })->name('documentation');
        
        // API Testing
        Route::get('/testing', function () {
            return view('developer.api.testing');
        })->name('testing');
        Route::post('/testing/endpoint', function (Request $request) {
            return response()->json(['message' => 'API endpoint tested']);
        })->name('testing.endpoint');
        
        // API Monitoring
        Route::get('/monitoring', function () {
            return view('developer.api.monitoring');
        })->name('monitoring');
        
        // API Keys
        Route::get('/keys', function () {
            return view('developer.api.keys');
        })->name('keys');
        Route::post('/keys', function (Request $request) {
            return response()->json(['message' => 'API key generated']);
        })->name('keys.generate');
        Route::delete('/keys/{key}', function (Request $request, $key) {
            return response()->json(['message' => 'API key revoked']);
        })->name('keys.revoke');
    });
    
    // =============================================
    // FILE & STORAGE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('storage')->name('storage.')->group(function () {
        // Storage Dashboard
        Route::get('/', function () {
            return view('developer.storage.index');
        })->name('index');
        
        // Disk Usage
        Route::get('/usage', function () {
            return view('developer.storage.usage');
        })->name('usage');
        
        // File Management
        Route::get('/files', function () {
            return view('developer.storage.files');
        })->name('files');
        Route::get('/files/{path}', function ($path) {
            return view('developer.storage.files-browse', compact('path'));
        })->name('files.browse');
        
        // Backup Management
        Route::get('/backups', function () {
            return view('developer.storage.backups');
        })->name('backups');
        Route::post('/backups/create', function (Request $request) {
            return response()->json(['message' => 'Backup created']);
        })->name('backups.create');
        
        // Cleanup Tools
        Route::get('/cleanup', function () {
            return view('developer.storage.cleanup');
        })->name('cleanup');
        Route::post('/cleanup/temp', function (Request $request) {
            return response()->json(['message' => 'Temporary files cleaned']);
        })->name('cleanup.temp');
        Route::post('/cleanup/logs', function (Request $request) {
            return response()->json(['message' => 'Log files cleaned']);
        })->name('cleanup.logs');
    });
    
    // =============================================
    // SYSTEM COMMANDS & TOOLS ROUTES
    // =============================================
    
    Route::prefix('tools')->name('tools.')->group(function () {
        // Tools Dashboard
        Route::get('/', function () {
            return view('developer.tools.index');
        })->name('index');
        
        // Artisan Commands
        Route::get('/artisan', function () {
            return view('developer.tools.artisan');
        })->name('artisan');
        Route::post('/artisan/run', [DashboardController::class, 'runCommand'])->name('artisan.run');
        
        // System Commands
        Route::get('/commands', function () {
            return view('developer.tools.commands');
        })->name('commands');
        
        // Cron Job Management
        Route::get('/cron', function () {
            return view('developer.tools.cron');
        })->name('cron');
        Route::get('/cron/jobs', function () {
            return view('developer.tools.cron-jobs');
        })->name('cron.jobs');
        Route::post('/cron/test', function (Request $request) {
            return response()->json(['message' => 'Cron job tested']);
        })->name('cron.test');
        
        // System Information
        Route::get('/system-info', function () {
            return view('developer.tools.system-info');
        })->name('system-info');
        
        // Environment Management
        Route::get('/environment', function () {
            return view('developer.tools.environment');
        })->name('environment');
        Route::get('/environment/edit', function () {
            return view('developer.tools.environment-edit');
        })->name('environment.edit');
        Route::post('/environment', function (Request $request) {
            return response()->json(['message' => 'Environment updated']);
        })->name('environment.update');
        
        // PHP Information
        Route::get('/php-info', function () {
            ob_start();
            phpinfo();
            $phpinfo = ob_get_clean();
            return view('developer.tools.php-info', compact('phpinfo'));
        })->name('php-info');
    });
    
    // =============================================
    // SETTINGS & CONFIGURATION ROUTES
    // =============================================
    
    Route::prefix('settings')->name('settings.')->group(function () {
        // Settings Dashboard
        Route::get('/', function () {
            return view('developer.settings.index');
        })->name('index');
        
        // General Settings
        Route::get('/general', function () {
            return view('developer.settings.general');
        })->name('general');
        Route::post('/general', function (Request $request) {
            return response()->json(['message' => 'General settings updated']);
        })->name('general.update');
        
        // Notification Settings
        Route::get('/notifications', function () {
            return view('developer.settings.notifications');
        })->name('notifications');
        Route::post('/notifications', function (Request $request) {
            return response()->json(['message' => 'Notification settings updated']);
        })->name('notifications.update');
        
        // Monitoring Settings
        Route::get('/monitoring', function () {
            return view('developer.settings.monitoring');
        })->name('monitoring');
        Route::post('/monitoring', function (Request $request) {
            return response()->json(['message' => 'Monitoring settings updated']);
        })->name('monitoring.update');
        
        // Alert Settings
        Route::get('/alerts', function () {
            return view('developer.settings.alerts');
        })->name('alerts');
        Route::post('/alerts', function (Request $request) {
            return response()->json(['message' => 'Alert settings updated']);
        })->name('alerts.update');
        
        // Backup Settings
        Route::get('/backup', function () {
            return view('developer.settings.backup');
        })->name('backup');
        Route::post('/backup', function (Request $request) {
            return response()->json(['message' => 'Backup settings updated']);
        })->name('backup.update');
    });
    
    // =============================================
    // USER IMPACT & ANALYTICS ROUTES
    // =============================================
    
    Route::prefix('analytics')->name('analytics.')->group(function () {
        // Analytics Dashboard
        Route::get('/', function () {
            return view('developer.analytics.index');
        })->name('index');
        
        // User Impact Analytics
        Route::get('/user-impact', [DashboardController::class, 'getImpactAnalysis'])->name('user-impact');
        Route::get('/user-impact/realtime', function () {
            return view('developer.analytics.user-impact-realtime');
        })->name('user-impact.realtime');
        Route::get('/user-impact/historical', function () {
            return view('developer.analytics.user-impact-historical');
        })->name('user-impact.historical');
        
        // System Analytics
        Route::get('/system', function () {
            return view('developer.analytics.system');
        })->name('system');
        Route::get('/system/performance', function () {
            return view('developer.analytics.system-performance');
        })->name('system.performance');
        Route::get('/system/errors', function () {
            return view('developer.analytics.system-errors');
        })->name('system.errors');
        
        // Custom Reports
        Route::get('/reports', function () {
            return view('developer.analytics.reports');
        })->name('reports');
        Route::post('/reports/generate', [DashboardController::class, 'generateHealthReport'])->name('reports.generate');
        Route::get('/reports/{id}', function ($id) {
            return view('developer.analytics.report-details', compact('id'));
        })->name('reports.show');
        Route::get('/reports/{id}/download', function ($id) {
            return response()->download(storage_path("app/reports/{$id}.pdf"));
        })->name('reports.download');
    });
    
    // =============================================
    // NOTIFICATIONS & ALERTS ROUTES
    // =============================================
    
    Route::prefix('notifications')->name('notifications.')->group(function () {
        // Notifications Dashboard
        Route::get('/', function () {
            return view('developer.notifications.index');
        })->name('index');
        
        // System Alerts
        Route::get('/alerts', [DashboardController::class, 'getSystemAlerts'])->name('alerts');
        Route::get('/alerts/active', function () {
            return view('developer.notifications.alerts-active');
        })->name('alerts.active');
        Route::get('/alerts/history', function () {
            return view('developer.notifications.alerts-history');
        })->name('alerts.history');
        
        // Notification Management
        Route::get('/management', function () {
            return view('developer.notifications.management');
        })->name('management');
        Route::post('/send', function (Request $request) {
            return response()->json(['message' => 'Notification sent']);
        })->name('send');
        Route::post('/bulk-send', function (Request $request) {
            return response()->json(['message' => 'Bulk notifications sent']);
        })->name('bulk-send');
        
        // Notification Templates
        Route::get('/templates', function () {
            return view('developer.notifications.templates');
        })->name('templates');
        Route::get('/templates/{template}', function ($template) {
            return view('developer.notifications.template-details', compact('template'));
        })->name('templates.show');
        Route::post('/templates', function (Request $request) {
            return response()->json(['message' => 'Template created']);
        })->name('templates.store');
        Route::put('/templates/{template}', function (Request $request, $template) {
            return response()->json(['message' => 'Template updated']);
        })->name('templates.update');
        Route::delete('/templates/{template}', function (Request $request, $template) {
            return response()->json(['message' => 'Template deleted']);
        })->name('templates.destroy');
    });
    
    // =============================================
    // REAL-TIME MONITORING ROUTES
    // =============================================
    
    Route::prefix('realtime')->name('realtime.')->group(function () {
        // Realtime Dashboard
        Route::get('/', function () {
            return view('developer.realtime.index');
        })->name('index');
        
        // Live System Metrics
        Route::get('/metrics', function () {
            return view('developer.realtime.metrics');
        })->name('metrics');
        Route::get('/metrics/stream', function () {
            return response()->stream(function () {
                while (true) {
                    echo "data: " . json_encode(['metrics' => []]) . "\n\n";
                    ob_flush();
                    flush();
                    sleep(2);
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        })->name('metrics.stream');
        
        // Live Error Monitoring
        Route::get('/errors', function () {
            return view('developer.realtime.errors');
        })->name('errors');
        Route::get('/errors/stream', function () {
            return response()->stream(function () {
                while (true) {
                    echo "data: " . json_encode(['errors' => []]) . "\n\n";
                    ob_flush();
                    flush();
                    sleep(5);
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        })->name('errors.stream');
        
        // Live User Activity
        Route::get('/users', function () {
            return view('developer.realtime.users');
        })->name('users');
        Route::get('/users/stream', function () {
            return response()->stream(function () {
                while (true) {
                    echo "data: " . json_encode(['users' => []]) . "\n\n";
                    ob_flush();
                    flush();
                    sleep(10);
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        })->name('users.stream');
    });
});

// =============================================
// EXISTING ROUTES (from the original file)
// =============================================

/*
|--------------------------------------------------------------------------
| ✅ UPDATED: User Invitation Routes (Public Access - No Auth Required)
|--------------------------------------------------------------------------
*/

// Public user invitation acceptance routes (no auth required)
Route::prefix('invitations')->name('invitation.')->group(function () {
    // ✅ MAIN ROUTE: This matches what the model expects
    Route::get('/accept/{token}', [UserInvitationController::class, 'showAcceptForm'])
        ->name('accept'); // Creates: invitation.accept
    
    Route::post('/accept/{token}', [UserInvitationController::class, 'processAcceptance'])
        ->name('process-acceptance');
    
    Route::get('/expired', [UserInvitationController::class, 'expired'])
        ->name('expired');
    
    Route::get('/invalid', [UserInvitationController::class, 'invalid'])
        ->name('invalid');
    
    Route::get('/success', [UserInvitationController::class, 'success'])
        ->name('success');
    
    Route::get('/validate/{token}', [UserInvitationController::class, 'validateToken'])
        ->name('validate-token');
});

// ✅ BACKWARD COMPATIBILITY: Keep old route names if referenced elsewhere
Route::prefix('user/invitations')->name('user.invitations.')->group(function () {
    Route::get('/accept/{token}', [UserInvitationController::class, 'showAcceptForm'])
        ->name('accept');
    
    Route::post('/accept/{token}', [UserInvitationController::class, 'processAcceptance'])
        ->name('process');
    
    Route::get('/expired', [UserInvitationController::class, 'expired'])
        ->name('expired');
    
    Route::get('/invalid', [UserInvitationController::class, 'invalid'])
        ->name('invalid');
    
    Route::get('/success', [UserInvitationController::class, 'success'])
        ->name('success');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Tenant Invitation Routes (Public Access - No Auth Required)
|--------------------------------------------------------------------------
*/

// Public tenant invitation acceptance routes (no auth required)
Route::prefix('tenant-invitation')->name('tenant.invitation.')->group(function () {
    // ✅ MAIN ROUTE: This matches what the TenantInvitation model expects
    Route::get('/accept/{token}', [TenantInvitationController::class, 'accept'])
        ->name('accept'); // Creates: tenant.invitation.accept
    
    Route::post('/accept/{token}', [TenantInvitationController::class, 'processAcceptance'])
        ->name('process-acceptance');
    
    // Alternative registration completion
    Route::get('/complete/{token}', [TenantInvitationController::class, 'completeRegistration'])
        ->name('complete');
    Route::post('/complete/{token}', [TenantInvitationController::class, 'submitRegistration'])
        ->name('submit');
    
    // Status pages
    Route::get('/expired', [TenantInvitationController::class, 'expired'])
        ->name('expired');
    Route::get('/invalid', [TenantInvitationController::class, 'invalid'])
        ->name('invalid');
    Route::get('/success', [TenantInvitationController::class, 'success'])
        ->name('success');
    
    // Token validation
    Route::get('/validate/{token}', [TenantInvitationController::class, 'validateToken'])
        ->name('validate-token');
        
});

/*
|--------------------------------------------------------------------------
| ✅ BACKWARD COMPATIBILITY: Alternative Tenant Invitation Route Names
|--------------------------------------------------------------------------
*/

// Alternative route names if needed
Route::prefix('tenant/invitations')->name('tenant.invitations.')->group(function () {
    Route::get('/accept/{token}', [TenantInvitationController::class, 'accept'])
        ->name('accept');
    
    Route::post('/accept/{token}', [TenantInvitationController::class, 'processAcceptance'])
        ->name('process');
    
    Route::get('/expired', [TenantInvitationController::class, 'expired'])
        ->name('expired');
    
    Route::get('/invalid', [TenantInvitationController::class, 'invalid'])
        ->name('invalid');
    
    Route::get('/success', [TenantInvitationController::class, 'success'])
        ->name('success');
});

/*
|--------------------------------------------------------------------------
| ✅ UPDATED: User Invitation Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('user-invitations')->name('user-invitations.')->group(function () {
        // Send new invitation
        Route::post('/send', [UserInvitationController::class, 'sendInvitation'])
            ->name('send');
        
        // Resend invitation
        Route::post('/{userId}/resend', [UserInvitationController::class, 'resendInvitation'])
            ->name('resend');
        
        // Get invitation status
        Route::get('/{userId}/status', [UserInvitationController::class, 'getInvitationStatus'])
            ->name('status');
        
        // Cancel invitation
        Route::post('/{invitationId}/cancel', [UserInvitationController::class, 'cancelInvitation'])
            ->name('cancel');
        
        // Validate token (admin access)
        Route::get('/validate/{token}', [UserInvitationController::class, 'validateToken'])
            ->name('validate');
        
        // Get invitation statistics
        Route::get('/statistics', [UserInvitationController::class, 'getInvitationStatistics'])
            ->name('statistics');
        
        // List all invitations
        Route::get('/', [UserInvitationController::class, 'index'])
            ->name('index');
        
        // Show invitation details
        Route::get('/{invitationId}', [UserInvitationController::class, 'show'])
            ->name('show');
        
        // Get expiry configuration
        Route::get('/expiry-configuration', [UserInvitationController::class, 'getExpiryConfiguration'])
            ->name('expiry-configuration');
        
        // Cleanup expired invitations
        Route::post('/cleanup-expired', [UserInvitationController::class, 'cleanupExpiredInvitations'])
            ->name('cleanup-expired');
        
        // Get user channels
        Route::get('/user/{user}/channels', [UserInvitationController::class, 'getUserChannels'])
            ->name('user-channels');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Tenant Invitation Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('tenant-invitations')->name('tenant-invitations.')->group(function () {
        // Send new tenant invitation
        Route::post('/send', [TenantInvitationController::class, 'sendInvitation'])
            ->name('send');
        
        // Resend invitation
        Route::post('/{tenantInvitation}/resend', [TenantInvitationController::class, 'resend'])
            ->name('resend');
        
        // Cancel invitation
        Route::post('/{tenantInvitation}/cancel', [TenantInvitationController::class, 'cancel'])
            ->name('cancel');
        
        // Get property invitations
        Route::get('/property/{propertyId}', [TenantInvitationController::class, 'getPropertyInvitations'])
            ->name('property');
        
        // Get user invitations
        Route::get('/user/{userId}', [TenantInvitationController::class, 'getUserInvitations'])
            ->name('user');
        
        // Get invitation statistics
        Route::get('/statistics', [TenantInvitationController::class, 'statistics'])
            ->name('statistics');
        
        // Get invitation details
        Route::get('/{id}', [TenantInvitationController::class, 'show'])
            ->name('show');
        
        // Test invitation delivery
        Route::post('/{tenantInvitation}/test-delivery', [TenantInvitationController::class, 'testDelivery'])
            ->name('test-delivery');
        
        // List all tenant invitations
        Route::get('/', [TenantInvitationController::class, 'index'])
            ->name('index');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Landlord Invitation Routes (Public Access)
|--------------------------------------------------------------------------
*/

// Public landlord invitation acceptance routes (no auth required)
Route::prefix('landlord/invitations')->name('landlord.invitations.')->group(function () {
    // Show invitation acceptance form
    Route::get('/accept/{token}', [LandlordInvitationController::class, 'accept'])
        ->name('accept');
    
    // Process invitation acceptance - FIXED: Correct route name
    Route::post('/accept/{token}', [LandlordInvitationController::class, 'processAcceptance'])
        ->name('process-acceptance');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Landlord-specific Tenant Invitation Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    Route::prefix('tenant-invitations')->name('tenant-invitations.')->group(function () {
        // Send tenant invitation (for landlord's properties)
        Route::post('/send', [TenantInvitationController::class, 'sendInvitation'])
            ->name('send');
        
        // Get tenant invitations for landlord's properties
        Route::get('/', [TenantInvitationController::class, 'getLandlordInvitations'])
            ->name('index');
        
        // Get tenant invitations for specific property
        Route::get('/property/{propertyId}', [TenantInvitationController::class, 'getPropertyInvitations'])
            ->name('property');
        
        // Resend invitation
        Route::post('/{tenantInvitation}/resend', [TenantInvitationController::class, 'resend'])
            ->name('resend');
        
        // Cancel invitation
        Route::post('/{tenantInvitation}/cancel', [TenantInvitationController::class, 'cancel'])
            ->name('cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Landlord Channels Routes - ADDED MISSING ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    // Landlord channels management
    Route::get('/channels', [HomeController::class, 'landlordChannels'])->name('channels');
    Route::get('/channels/create', [HomeController::class, 'createChannel'])->name('channels.create');
    Route::post('/channels', [HomeController::class, 'storeChannel'])->name('channels.store');
    Route::get('/channels/{channel}', [HomeController::class, 'showChannel'])->name('channels.show');
    Route::get('/channels/{channel}/edit', [HomeController::class, 'editChannel'])->name('channels.edit');
    Route::put('/channels/{channel}', [HomeController::class, 'updateChannel'])->name('channels.update');
    Route::delete('/channels/{channel}', [HomeController::class, 'destroyChannel'])->name('channels.destroy');
    
    // Channel subscription and payment
    Route::get('/channels/{channel}/subscribe', [HomeController::class, 'showChannelSubscription'])->name('channels.subscribe');
    Route::post('/channels/{channel}/subscribe', [HomeController::class, 'processChannelSubscription'])->name('channels.subscribe.process');
    
    // Channel analytics
    Route::get('/channels/{channel}/analytics', [HomeController::class, 'channelAnalytics'])->name('channels.analytics');
    
    // ✅ ADDED: Landlord active invitations
    Route::get('/active-invitations', [LandlordInvitationController::class, 'getLandlordActiveInvitations'])
        ->name('active-invitations');
});

Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('landlord.dashboard');
    })->name('dashboard');
    
    // Profile routes
    Route::prefix('profile')->name('profile.')->group(function () {
        // Main profile edit route
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        
        // Update routes
        Route::put('/update', [ProfileController::class, 'update'])->name('update');
        Route::put('/personal', [ProfileController::class, 'updateLandlordPersonal'])->name('personal.update');
        Route::put('/contact', [ProfileController::class, 'updateLandlordContact'])->name('contact.update'); // ✅ FIXED
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        
        // Photo routes
        Route::post('/photo/update', [ProfileController::class, 'updateProfilePhoto'])->name('photo.update');
        Route::post('/photo/remove', [ProfileController::class, 'removeProfilePhoto'])->name('photo.remove');
        
        // Phone verification routes
        Route::post('/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])->name('phone.verify.send');
        Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])->name('phone.verify');
        
        // Email verification routes
        Route::post('/email/verify/send', [ProfileController::class, 'sendEmailVerification'])->name('email.verify.send');
        
        // Stats and data routes
        Route::get('/stats', [ProfileController::class, 'getProfileStats'])->name('stats');
        Route::get('/download-data', [ProfileController::class, 'downloadPersonalData'])->name('download-data');
    });
});
    
    // Properties route (from previous error)
    Route::get('/properties', function () {
        $properties = Auth::user()->properties()->with(['propertyType', 'units'])->latest()->paginate(10);
        return view('landlord.properties.index', compact('properties'));
    })->name('properties.index');


/*
|--------------------------------------------------------------------------
| ✅ FIXED: Tenant Profile Management Routes - COMPLETE VERSION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:3'])->prefix('tenant')->name('tenant.')->group(function () {
    // Tenant profile management
    Route::prefix('profile')->name('profile.')->group(function () {
        // Edit profile form
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        
        // Update profile route
        Route::put('/update', [ProfileController::class, 'update'])->name('update');
        
        // Password routes
        Route::get('/password', [ProfileController::class, 'edit'])->name('password');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        
        // Personal information routes
        Route::get('/personal-info', [ProfileController::class, 'edit'])->name('personal-info');
        Route::put('/personal-info', [ProfileController::class, 'updatePersonalInfo'])->name('personal-info.update');
        
        // Contact information routes
        Route::get('/contact-info', [ProfileController::class, 'edit'])->name('contact-info');
        Route::put('/contact-info', [ProfileController::class, 'updateContactInfo'])->name('contact-info.update');
        
        // ✅ NEW: Tenant details routes (Missing from original)
        Route::get('/tenant-details', [ProfileController::class, 'edit'])->name('tenant-details');
        Route::put('/tenant-details', [ProfileController::class, 'updateTenantDetails'])->name('tenant-details.update');
        
        // ✅ NEW: Emergency contacts routes (Missing from original)
        Route::get('/emergency-contacts', [ProfileController::class, 'edit'])->name('emergency-contacts');
        Route::put('/emergency-contacts', [ProfileController::class, 'updateEmergencyContacts'])->name('emergency-contacts.update');
        
        // ✅ FIXED: Profile photo management
        Route::get('/photo', [ProfileController::class, 'edit'])->name('photo');
        Route::post('/photo', [PhotoController::class, 'updateProfilePhoto'])->name('photo.update');
        Route::delete('/photo', [PhotoController::class, 'removeProfilePhoto'])->name('photo.remove');
        
        // ✅ FIXED: Phone verification routes - Match blade template names
        Route::get('/phone-verification', [ProfileController::class, 'edit'])->name('phone.verification');
        Route::post('/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])->name('phone.verify.send'); // ✅ FIXED NAME
        Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])->name('phone.verify');
        
        // ✅ NEW: Email verification routes (Missing from original)
        Route::post('/email/verify/send', [ProfileController::class, 'sendEmailVerification'])->name('email.verify.send');
        Route::post('/email/verify', [ProfileController::class, 'verifyEmail'])->name('email.verify');
        
        // Profile statistics
        Route::get('/stats', [ProfileController::class, 'getProfileStats'])->name('stats');
        
        // Download personal data - Fixed to match blade
        Route::get('/data/download', [ProfileController::class, 'downloadPersonalData'])->name('data.download');
        
        // ✅ NEW: Form-specific update routes for the blade tabs - Match form actions
        Route::put('/personal/update', [ProfileController::class, 'updatePersonalInfo'])->name('personal.update');
        Route::put('/contact/update', [ProfileController::class, 'updateContactInfo'])->name('contact.update');
        Route::put('/tenant/update', [ProfileController::class, 'updateTenantDetails'])->name('tenant.update');
        Route::put('/emergency/update', [ProfileController::class, 'updateEmergencyContacts'])->name('emergency.update');
        
        // ✅ NEW: Missing routes from blade template
        Route::put('/password/update', [ProfileController::class, 'updatePassword'])->name('password.update'); // Alternative for blade
        
        // ✅ NEW: Stats route as shown in blade
        Route::get('/profile/stats', [ProfileController::class, 'getProfileStats'])->name('profile.stats');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ FIXED: Security Personnel Profile Management Routes - COMPLETE UPDATE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    // Security Checkpoint profile management
    Route::prefix('profile')->name('profile.')->group(function () {
        // Edit profile form
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        
        // General profile update (main form)
        Route::put('/update', [ProfileController::class, 'update'])->name('update');
        
        // Password routes
        Route::get('/password', [ProfileController::class, 'edit'])->name('password');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        
        // ✅ NEW: Personal information routes for tabbed interface
        Route::put('/personal', [ProfileController::class, 'updatePersonal'])->name('personal.update');
        
        // ✅ NEW: Security information routes for tabbed interface
        Route::put('/security', [ProfileController::class, 'updateSecurity'])->name('security.update');
        
        // Alternative route names for backward compatibility
        Route::get('/personal-info', [ProfileController::class, 'edit'])->name('personal-info');
        Route::put('/personal-info', [ProfileController::class, 'updatePersonalInfo'])->name('personal-info.update');
        
        Route::get('/security-info', [ProfileController::class, 'edit'])->name('security-info');
        Route::put('/security-info', [ProfileController::class, 'updateSecurityInfo'])->name('security-info.update');
        
        // ✅ FIXED: Profile photo management
        Route::get('/photo', [ProfileController::class, 'edit'])->name('photo');
        Route::post('/photo', [PhotoController::class, 'updateProfilePhoto'])->name('photo.update');
        Route::delete('/photo', [PhotoController::class, 'removeProfilePhoto'])->name('photo.remove');
        
        // ✅ FIXED: Phone verification routes - Complete implementation
        Route::get('/phone-verification', [ProfileController::class, 'edit'])->name('phone.verification');
        Route::post('/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])->name('phone.verify.send');
        Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])->name('phone.verify');
        
        // Email verification
        Route::post('/email/verify/send', [ProfileController::class, 'sendEmailVerification'])->name('email.verify.send');
        
        // Profile statistics
        Route::get('/stats', [ProfileController::class, 'getProfileStats'])->name('stats');
        
        // Download personal data
        Route::get('/download-data', [ProfileController::class, 'downloadPersonalData'])->name('download-data');
        
        // API key management (if needed for security personnel)
        Route::post('/api/generate', [ProfileController::class, 'generateApiKey'])->name('api.generate');
    });
    
    
});

/*
|--------------------------------------------------------------------------
| ✅ FIXED: Field agent Profile Management Routes - COMPLETE UPDATE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'multi.auth.user:4'])->prefix('agent')->name('agent.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'agentDashboard'])->name('dashboard');
    
    // Profile Management Routes
    Route::prefix('profile')->name('profile.')->group(function () {
        // Main Profile Page
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        
        // Complete Profile Update (Legacy)
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        
        // Section-based Profile Updates
        Route::put('/personal', [ProfileController::class, 'updatePersonal'])->name('personal.update');
        Route::put('/contact', [ProfileController::class, 'updateContact'])->name('contact.update');
        Route::put('/agent-info', [ProfileController::class, 'updateAgentInfo'])->name('agent.update');
        
        // ✅ FIXED: Added missing notifications route
        Route::put('/notifications', [ProfileController::class, 'updateNotifications'])->name('notifications.update');
        
        Route::put('/security', [ProfileController::class, 'updateSecurity'])->name('security.update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        
        // Photo Management
        Route::post('/photo', [PhotoController::class, 'updateProfilePhoto'])->name('photo.update');
        Route::delete('/photo', [PhotoController::class, 'removeProfilePhoto'])->name('photo.remove');
        
        // Verification Routes
        Route::post('/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])->name('phone.verify.send');
        Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])->name('phone.verify');
        Route::post('/email/verify/send', [ProfileController::class, 'sendEmailVerification'])->name('email.verify.send');
        Route::post('/email/verify', [ProfileController::class, 'verifyEmail'])->name('email.verify');
        
        // ID Document Upload
        Route::post('/id/upload', [ProfileController::class, 'uploadIdDocument'])->name('id.upload');
        
        // Session Management
        Route::post('/session/revoke', [ProfileController::class, 'revokeSession'])->name('session.revoke');
        
        // Data & Statistics
        Route::get('/stats', [ProfileController::class, 'getProfileStats'])->name('stats');
        Route::get('/data/download', [ProfileController::class, 'downloadPersonalData'])->name('data.download');
    });

});

/*
|--------------------------------------------------------------------------
| ✅ FIXED: Profile Management Routes (For All Authenticated Users) - UPDATED PHONE VERIFICATION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('profile')->name('profile.')->group(function () {
    // Edit profile form - ProfileController
    Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
    
    // Update profile - ProfileController
    Route::put('/update', [ProfileController::class, 'update'])->name('update');
    
    // Update password - ProfileController
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    
    // Update profile photo - PhotoController
    Route::post('/photo', [PhotoController::class, 'updateProfilePhoto'])->name('photo.update');
    
    // Remove profile photo - PhotoController
    Route::delete('/photo', [PhotoController::class, 'removeProfilePhoto'])->name('photo.remove');
    
    // ✅ FIXED: Phone verification - Use ProfileController instead of VerificationController
    Route::post('/send-verification', [ProfileController::class, 'sendPhoneVerification'])->name('send-verification');
    Route::post('/verify-phone', [ProfileController::class, 'verifyPhone'])->name('verify-phone');
    
    // Profile statistics - ProfileController
    Route::get('/stats', [ProfileController::class, 'getProfileStats'])->name('stats');
    
    // Personal data download (GDPR compliance) - ProfileController
    Route::get('/download-data', [ProfileController::class, 'downloadPersonalData'])->name('download-data');
    
    // Update personal information separately - ProfileController
    Route::put('/personal-info', [ProfileController::class, 'updatePersonalInfo'])->name('personal-info.update');
    
    // Update contact information separately - ProfileController
    Route::put('/contact-info', [ProfileController::class, 'updateContactInfo'])->name('contact-info.update');
});


/*
|--------------------------------------------------------------------------
| Admin Profile Management Routes - CORRECTED FOR BLADE COMPATIBILITY
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // ==================== PROFILE VIEW & EDIT ====================
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    
    // ==================== PERSONAL INFORMATION ====================
    // These match the blade template expectations
    Route::put('/profile/personal', [ProfileController::class, 'updateAdminPersonal'])->name('profile.personal.update');
    Route::put('/profile/personal-info', [ProfileController::class, 'updateAdminPersonal'])->name('profile.personal-info.update');
    
    // ==================== CONTACT INFORMATION ====================
    // These match the blade template expectations
    Route::put('/profile/contact', [ProfileController::class, 'updateAdminContact'])->name('profile.contact.update');
    Route::put('/profile/contact-info', [ProfileController::class, 'updateAdminContact'])->name('profile.contact-info.update');
    
    // ==================== PASSWORD MANAGEMENT ====================
    Route::put('/profile/password', [ProfileController::class, 'updateAdminPassword'])->name('profile.password.update');
    Route::get('/profile/password', [ProfileController::class, 'edit'])->name('profile.password');
    
    // ==================== PROFILE PHOTO ====================
    Route::post('/profile/photo/update', [ProfileController::class, 'updateProfilePhoto'])->name('profile.photo.update');
    Route::post('/profile/photo/remove', [ProfileController::class, 'removeProfilePhoto'])->name('profile.photo.remove');
    Route::get('/profile/photo', [ProfileController::class, 'edit'])->name('profile.photo');
    
    // ==================== PHONE VERIFICATION ====================
    Route::post('/profile/send-verification', [ProfileController::class, 'sendPhoneVerification'])->name('profile.send-verification');
    Route::post('/profile/verify-phone', [ProfileController::class, 'verifyPhone'])->name('profile.verify-phone');
    Route::get('/profile/phone-verification', [ProfileController::class, 'edit'])->name('profile.phone.verification');
    
    // ==================== EMAIL VERIFICATION ====================
    Route::post('/profile/send-email-verification', [ProfileController::class, 'sendEmailVerification'])->name('profile.send-email-verification');
    Route::post('/profile/verify-email', [ProfileController::class, 'verifyEmail'])->name('profile.verify-email');
    
    // ==================== PROFILE STATISTICS & DATA ====================
    Route::get('/profile/stats', [ProfileController::class, 'getAdminProfileStats'])->name('profile.stats');
    Route::get('/profile/download-data', [ProfileController::class, 'downloadAdminData'])->name('profile.download-data');
    
    // ==================== LEGACY/BACKWARD COMPATIBILITY ====================
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/update-personal', [ProfileController::class, 'updatePersonalInfo'])->name('profile.update-personal');
});


// ==================== DEVELOPER PROFILE ROUTES ====================
/*
|--------------------------------------------------------------------------
| ✅ FIXED: Developer Profile Management Routes - UPDATED PHONE VERIFICATION
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    
    // ==================== PROFILE VIEW & EDIT ====================
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    
    // ==================== PERSONAL INFORMATION ====================
    // Primary routes for blade template
    Route::put('/profile/personal/update', [ProfileController::class, 'updateDeveloperPersonal'])->name('profile.personal.update');
    Route::put('/profile/personal-info', [ProfileController::class, 'updatePersonalInfo'])->name('profile.personal-info.update');
    
    // ==================== CONTACT INFORMATION ====================
    // Primary routes for blade template
    Route::put('/profile/contact/update', [ProfileController::class, 'updateDeveloperContact'])->name('profile.contact.update');
    Route::put('/profile/contact-info', [ProfileController::class, 'updateContactInfo'])->name('profile.contact-info.update');
    
    // ==================== PASSWORD MANAGEMENT ====================
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/password', [ProfileController::class, 'edit'])->name('profile.password');
    
    // ==================== PROFILE PHOTO ====================
    Route::post('/profile/photo/update', [ProfileController::class, 'updateProfilePhoto'])->name('profile.photo.update');
    Route::match(['POST', 'DELETE'], '/profile/photo/remove', [ProfileController::class, 'removeProfilePhoto'])->name('profile.photo.remove');
    Route::get('/profile/photo', [ProfileController::class, 'edit'])->name('profile.photo');
    
    // ==================== PHONE VERIFICATION ====================
    // Primary routes
    Route::post('/profile/send-verification', [ProfileController::class, 'sendPhoneVerification'])->name('profile.send-verification');
    Route::post('/profile/verify-phone', [ProfileController::class, 'verifyPhone'])->name('profile.verify-phone');
    
    // Alternative routes for JavaScript compatibility
    Route::post('/profile/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])->name('profile.phone.verify.send');
    Route::post('/profile/phone/verify', [ProfileController::class, 'verifyPhone'])->name('profile.phone.verify');
    
    Route::get('/profile/phone-verification', [ProfileController::class, 'edit'])->name('profile.phone.verification');
    
    // ==================== EMAIL VERIFICATION ====================
    Route::post('/profile/send-email-verification', [ProfileController::class, 'sendEmailVerification'])->name('profile.send-email-verification');
    Route::post('/profile/email/verify/send', [ProfileController::class, 'sendEmailVerification'])->name('profile.email.verify.send');
    Route::post('/profile/verify-email', [ProfileController::class, 'verifyEmail'])->name('profile.verify-email');
    Route::post('/profile/email/verify', [ProfileController::class, 'verifyEmail'])->name('profile.email.verify');
    
    // ==================== DEVELOPER SETTINGS ====================
    Route::put('/profile/developer/update', [ProfileController::class, 'updateDeveloperSettings'])->name('profile.developer.update');
    
    // ==================== API KEY MANAGEMENT ====================
    Route::post('/profile/api/generate', [ProfileController::class, 'generateApiKey'])->name('profile.api.generate');
    Route::delete('/profile/api/revoke', [ProfileController::class, 'revokeApiKey'])->name('profile.api.revoke');
    
    // ==================== PROFILE STATISTICS & DATA ====================
    Route::get('/profile/stats', [ProfileController::class, 'getDeveloperStats'])->name('profile.stats');
    Route::get('/profile/download-data', [ProfileController::class, 'downloadDeveloperData'])->name('profile.download-data');
    
    // ==================== DEVELOPER TOOLS ====================
    Route::get('/tools', [ProfileController::class, 'developerTools'])->name('tools');
    Route::get('/dashboard', [ProfileController::class, 'developerDashboard'])->name('dashboard');
    
    // ==================== LEGACY/BACKWARD COMPATIBILITY ====================
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/update-personal', [ProfileController::class, 'updatePersonalInfo'])->name('profile.update-personal');
});

// Email verification callback route (GET request for clicking the link)
Route::get('/verify-email/{token}', [ProfileController::class, 'verifyEmail'])
    ->name('developer.profile.email.verify')
    ->withoutMiddleware(['multi.auth.user:5']); // Allow access without developer auth

/*
|--------------------------------------------------------------------------
| ✅ ADDED: User Invitation Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('user-invitations')->name('user-invitations.')->group(function () {
        // Send new user invitation
        Route::post('/send', [UserInvitationController::class, 'sendInvitation'])
            ->name('send');
        
        // Resend invitation
        Route::post('/{userId}/resend', [UserInvitationController::class, 'resendInvitation'])
            ->name('resend');
        
        // Get invitation status
        Route::get('/{userId}/status', [UserInvitationController::class, 'getInvitationStatus'])
            ->name('status');
        
        // Validate token (admin access)
        Route::get('/validate/{token}', [UserInvitationController::class, 'validateToken'])
            ->name('validate');
        
        // Get invitation statistics
        Route::get('/statistics', [UserInvitationController::class, 'getInvitationStatistics'])
            ->name('statistics');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Landlord Invitation Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('landlord-invitations')->name('landlord-invitations.')->group(function () {
        // Send new landlord invitation
        Route::post('/send', [LandlordInvitationController::class, 'store'])
            ->name('send');
        
        // Resend invitation
        Route::post('/{landlordInvitation}/resend', [LandlordInvitationController::class, 'resend'])
            ->name('resend');
        
        // Cancel invitation
        Route::post('/{landlordInvitation}/cancel', [LandlordInvitationController::class, 'cancel'])
            ->name('cancel');
        
        // Regenerate token
        Route::post('/{landlordInvitation}/regenerate-token', [LandlordInvitationController::class, 'regenerateToken'])
            ->name('regenerate-token');
        
        // Get property invitations
        Route::get('/property/{propertyId}', [LandlordInvitationController::class, 'getPropertyInvitations'])
            ->name('property');
        
        // Get invitation statistics
        Route::get('/statistics', [LandlordInvitationController::class, 'statistics'])
            ->name('statistics');
    });
});


/*
|--------------------------------------------------------------------------
| Chatbot Routes — Public + Authenticated
|--------------------------------------------------------------------------
|
| The chat has two audiences:
|
|   🌐  GUESTS (not logged in)
|       - Can see the floating widget on the homepage.
|       - Can send messages, get replies, fetch their session history,
|         and clear it. Identified by a session token.
|       - Strongly rate-limited to prevent abuse.
|
|   🔒  AUTHENTICATED USERS
|       - Can access the full-page chat UI per role.
|       - Have their history persisted against their user_id.
|       - Looser throttles because they're trusted.
|
|   Mobile / Flutter API endpoints live in routes/api.php, not here.
|
*/

/* =========================================================================
   PUBLIC ENDPOINTS — accessible to guests + authenticated users
   ========================================================================= */

Route::middleware(['web'])
    ->prefix('chat')
    ->name('chat.')
    ->group(function () {

        /*
        |------------------------------------------------------------------
        | Send Message
        |------------------------------------------------------------------
        | Accepts { message: string } and returns the bot reply as JSON.
        | Used by the floating widget on the homepage (guests) and by
        | role dashboards (authenticated users).
        |
        | Throttle: 15 requests/minute per IP (strict — spam protection).
        */
        Route::post('/send', [ChatController::class, 'sendMessage'])
            ->name('send')
            ->middleware('throttle:15,1');

        /*
        |------------------------------------------------------------------
        | Quick Help Topics
        |------------------------------------------------------------------
        | Returns the quick-help topic list for the current visitor:
        | role-specific for authenticated users, guest topics otherwise.
        */
        Route::get('/topics', [ChatController::class, 'getQuickHelpTopics'])
            ->name('topics')
            ->middleware('throttle:60,1');

        /*
        |------------------------------------------------------------------
        | Chat History
        |------------------------------------------------------------------
        | Returns the current session's messages:
        |   - By user_id for authenticated users
        |   - By guest_token for guests
        |
        | Safe to make public because the guest token is session-scoped.
        */
        Route::get('/messages', [ChatController::class, 'getMessages'])
            ->name('messages')
            ->middleware('throttle:30,1');

        /*
        |------------------------------------------------------------------
        | Clear History
        |------------------------------------------------------------------
        | Deletes the current session's messages.
        | Destructive, so heavily throttled.
        */
        Route::delete('/clear', [ChatController::class, 'clearHistory'])
            ->name('clear')
            ->middleware('throttle:5,1');

        /*
        |------------------------------------------------------------------
        | User Role Info
        |------------------------------------------------------------------
        | Returns role id, role name, and display name.
        | Returns "Guest" for unauthenticated visitors.
        */
        Route::get('/role-info', [ChatController::class, 'getUserRoleInfo'])
            ->name('role-info')
            ->middleware('throttle:60,1');
    });

/* =========================================================================
   AUTH-ONLY: Full-page chat UI
   ========================================================================= */

Route::middleware(['auth', 'web'])
    ->prefix('chat')
    ->name('chat.')
    ->group(function () {

        /*
        |------------------------------------------------------------------
        | Main Chat UI
        |------------------------------------------------------------------
        | Renders the correct Blade view for the authenticated user's role.
        | Falls back to `chat.index` if a role-specific view is missing.
        */
        Route::get('/', [ChatController::class, 'index'])
            ->name('index')
            ->middleware('throttle:120,1');
    });

/* =========================================================================
   Generic /help alias (auth-only)
   ========================================================================= */

Route::middleware(['auth', 'web'])
    ->get('/help', [ChatController::class, 'index'])
    ->name('help')
    ->middleware('throttle:120,1');

/* =========================================================================
   Role-Scoped Chat Entry Points (auth-only)
   =========================================================================
|
| Each role dashboard can link to its own friendly URL. All resolve to
| ChatController@index, which picks the correct Blade view based on the
| authenticated user's role. Keeps navigation URLs pretty and role-scoped
| without duplicating controller logic.
|
| Examples:
|   /super-admin/chat      /admin/chat
|   /landlord/chat         /tenant/chat
|   /field-agent/chat      /developer/chat
|   /security/chat         /contractor/chat
|   /sanitation/chat
|
*/

Route::middleware(['auth', 'web'])->group(function () {

    $rolePrefixes = [
        'super-admin' => 'multi.auth.user:' . User::TYPE_SUPER_ADMIN,
        'admin'       => 'multi.auth.user:' . User::TYPE_ADMIN,
        'landlord'    => 'multi.auth.user:' . User::TYPE_LANDLORD,
        'tenant'      => 'multi.auth.user:' . User::TYPE_TENANT,
        'field-agent' => 'multi.auth.user:' . User::TYPE_FIELD_AGENT,
        'developer'   => 'multi.auth.user:' . User::TYPE_DEVELOPER,
        'security'    => 'multi.auth.user:' . User::TYPE_SECURITY_PERSONNEL,
        'contractor'  => 'multi.auth.user:' . User::TYPE_CONTRACTOR,
        'sanitation'  => 'multi.auth.user:' . User::TYPE_SANITATION_PERSONNEL,
    ];

    foreach ($rolePrefixes as $prefix => $middleware) {
        Route::middleware([$middleware])
            ->prefix($prefix)
            ->name($prefix . '.chat.')
            ->group(function () {
                Route::get('/chat', [ChatController::class, 'index'])
                    ->name('index')
                    ->middleware('throttle:120,1');

                Route::get('/help', [ChatController::class, 'index'])
                    ->name('help')
                    ->middleware('throttle:120,1');
            });
    }
});

/* =========================================================================
   LIVE CHAT — Human-to-Human Support & Escalation (auth-only)
   =========================================================================
|
| Flow:
|   • Support   : Landlord / Tenant / Field Agent / Security / Sanitation /
|                 Contractor  →  Admin / Super Admin
|   • Escalation: Admin / Super Admin  →  Developer
|
| Guests do NOT have access to live chat. They use the bot chat at
| /chat/* instead. This keeps the agent pool clean and prevents anonymous
| abuse.
|
| ⚠️ IMPORTANT — route order:
|   Static paths (``/``, ``/start``, ``/inbox/agents``, ``/unread/count``)
|   MUST be declared BEFORE the dynamic ``/{conversation}`` route.
|   The ``whereNumber('conversation')`` constraint acts as a second safety
|   net, but declaration order is still the primary guarantee.
|
*/

/* =========================================================================
   LIVE CHAT — Human-to-Human Support & Escalation (auth-only)
   =========================================================================
|
| Flow:
|   • Support   : Landlord / Tenant / Field Agent / Security / Sanitation /
|                 Contractor  →  Admin / Super Admin
|   • Escalation: Admin / Super Admin  →  Developer
|
| Guests do NOT have access to live chat. They use the bot chat at
| /chat/* instead. This keeps the agent pool clean and prevents
| anonymous abuse.
|
| ⚠️ IMPORTANT — route order:
|   Static paths (``/``, ``/start``, ``/inbox/agents``, ``/unread/count``,
|   ``/purge/closed``) MUST be declared BEFORE the dynamic
|   ``/{conversation}`` route. The ``whereNumber('conversation')``
|   constraint acts as a safety net, but declaration order is still the
|   primary guarantee.
|
*/

Route::middleware(['auth', 'web'])
    ->prefix('live-chat')
    ->name('live-chat.')
    ->group(function () {

        /* -----------------------------------------------------------------
         | Inbox / Index
         |------------------------------------------------------------------
         | Shows the current user's conversations.
         | For admins/developers, this doubles as the agent inbox.
         */
        Route::get('/', [LiveChatController::class, 'index'])
            ->name('index')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Start (or resume) a conversation
         |------------------------------------------------------------------
         | POST { subject?: string }
         | Returns: { success, conversation, redirect_url }
         | Reuses an existing open conversation if one exists — so the
         | widget never creates duplicates on repeated clicks.
         */
        Route::post('/start', [LiveChatController::class, 'start'])
            ->name('start')
            ->middleware('throttle:10,1');

        /* -----------------------------------------------------------------
         | Agent Inbox (MUST come before /{conversation})
         |------------------------------------------------------------------
         | Lists all open conversations — unassigned first, then by
         | last activity. Restricted to admin / super-admin / developer.
         */
        Route::get('/inbox/agents', [LiveChatController::class, 'agentInbox'])
            ->name('agents.inbox')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Unread badge count (MUST come before /{conversation})
         |------------------------------------------------------------------
         | Returns the number of unread messages across all of the user's
         | conversations. Used by the launcher badge.
         */
        Route::get('/unread/count', [LiveChatController::class, 'unreadCount'])
            ->name('unread.count')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Purge closed conversations (MUST come before /{conversation})
         |------------------------------------------------------------------
         | POST { days?: int }   (defaults to 30)
         | Permanently deletes all closed (soft-deleted) conversations
         | older than N days. Restricted to admin / super-admin / developer.
         | IRREVERSIBLE.
         */
        Route::post('/purge/closed', [LiveChatController::class, 'purgeClosed'])
            ->name('purge.closed')
            ->middleware('throttle:5,1');

        /* -----------------------------------------------------------------
         | Show a single conversation
         |------------------------------------------------------------------
         | GET /live-chat/{conversation}
         | Renders the full-page chat view. Authorization is enforced
         | inside the controller (must be a participant).
         */
        Route::get('/{conversation}', [LiveChatController::class, 'show'])
            ->name('show')
            ->whereNumber('conversation')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Message feed (JSON) — used by the polling widget
         |------------------------------------------------------------------
         | GET /live-chat/{conversation}/messages?after={lastId}
         | Returns all messages with id > after.
         | Also marks the conversation as read for the requesting user.
         */
        Route::get('/{conversation}/messages', [LiveChatController::class, 'messagesJson'])
            ->name('messages.json')
            ->whereNumber('conversation')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Send a message
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/messages
         | Body: { body: string, attachments?: array }
         | Returns: { success, message }
         */
        Route::post('/{conversation}/messages', [LiveChatController::class, 'send'])
            ->name('send')
            ->whereNumber('conversation')
            ->middleware('throttle:30,1');

        /* -----------------------------------------------------------------
         | Mark as read
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/read
         | Resets the participant's unread cursor to the latest message.
         */
        Route::post('/{conversation}/read', [LiveChatController::class, 'markRead'])
            ->name('read')
            ->whereNumber('conversation')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Close conversation
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/close
         | Sets status = closed. Either the initiator or an agent can do
         | this. A new conversation will be created on the next `start`.
         */
        Route::post('/{conversation}/close', [LiveChatController::class, 'close'])
            ->name('close')
            ->whereNumber('conversation')
            ->middleware('throttle:10,1');

        /* -----------------------------------------------------------------
         | Assign to an agent
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/assign
         | Body: { user_id?: int }  (defaults to the current agent)
         | Restricted to admin / super-admin / developer.
         */
        Route::post('/{conversation}/assign', [LiveChatController::class, 'assign'])
            ->name('assign')
            ->whereNumber('conversation')
            ->middleware('throttle:30,1');

        /* -----------------------------------------------------------------
         | Clear conversation for current user only
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/clear
         | Removes the user's participant row so the conversation
         | disappears from THEIR inbox only. Other participants still
         | see it. The conversation itself is untouched.
         */
        Route::post('/{conversation}/clear', [LiveChatController::class, 'clear'])
            ->name('clear')
            ->whereNumber('conversation')
            ->middleware('throttle:30,1');

        /* -----------------------------------------------------------------
         | Typing indicator
         |------------------------------------------------------------------
         | POST /live-chat/{conversation}/typing
         | Broadcasts a `user.typing` event to other participants.
         | Silently no-ops if broadcasting is not configured.
         */
        Route::post('/{conversation}/typing', [LiveChatController::class, 'typing'])
            ->name('typing')
            ->whereNumber('conversation')
            ->middleware('throttle:120,1');

        /* -----------------------------------------------------------------
         | Delete a conversation (soft delete for everyone)
         |------------------------------------------------------------------
         | DELETE /live-chat/{conversation}
         | Soft-deletes the conversation and all its messages.
         | Permitted for: initiator, assignee, or agent.
         | Declared LAST so it doesn't shadow the subroutes above.
         */
        Route::delete('/{conversation}', [LiveChatController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('conversation')
            ->middleware('throttle:10,1');

    });

/*
|--------------------------------------------------------------------------
| Agent Invitation Routes
|--------------------------------------------------------------------------
*/

// Agent Invitation Acceptance Routes (Public/Agent Access)
Route::prefix('agent/invitations')->name('agent.invitations.')->group(function () {
    // Show invitation acceptance form
    Route::get('/accept/{token}', [AgentInvitationController::class, 'showAcceptForm'])
        ->name('accept');
    
    // Process invitation acceptance
    Route::post('/accept/{token}', [AgentInvitationController::class, 'processAcceptance'])
        ->name('process');
    
    // Success page after acceptance
    Route::get('/success', [AgentInvitationController::class, 'success'])
        ->name('success');
    
    // Expired invitation page
    Route::get('/expired', [AgentInvitationController::class, 'expired'])
        ->name('expired');
    
    // Invalid invitation page
    Route::get('/invalid', [AgentInvitationController::class, 'invalid'])
        ->name('invalid');

    Route::get('/agent/invitations/expired/{token}', [AgentInvitationController::class, 'showExpiredInvitation'])
    ->name('agent.invitations.expired.direct');    
});

// Alternative route name for backward compatibility
Route::get('/invitations/accept/{token}', [AgentInvitationController::class, 'showAcceptForm'])
    ->name('invitations.accept');

/*
|--------------------------------------------------------------------------
| Role-based dashboards with MultiAuthUser middleware
|--------------------------------------------------------------------------
*/

// ✅ UPDATED: Super Admin Dashboard using SuperAdminController - ADDED ALL API ENDPOINTS
Route::middleware(['auth', 'multi.auth.user:0'])->prefix('super-admin')->name('super-admin.')->group(function () {
    // Main dashboard
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])
        ->name('dashboard');
    
    // Dashboard API routes - FIXED: Using consistent naming
    Route::get('/dashboard/data', [SuperAdminController::class, 'getDashboardData'])->name('dashboard.data');
    Route::post('/dashboard/refresh', [SuperAdminController::class, 'refreshDashboard'])->name('dashboard.refresh');
    Route::get('/dashboard/export', [SuperAdminController::class, 'exportDashboardData'])->name('dashboard.export');
    
    // ✅ ADDED: Individual API endpoints for each data type
    Route::get('/api/user-statistics', [SuperAdminController::class, 'apiUserStatistics'])->name('api.user-statistics');
    Route::get('/api/payment-statistics', [SuperAdminController::class, 'apiPaymentStatistics'])->name('api.payment-statistics');
    Route::get('/api/property-statistics', [SuperAdminController::class, 'apiPropertyStatistics'])->name('api.property-statistics');
    Route::get('/api/plan-statistics', [SuperAdminController::class, 'apiPlanStatistics'])->name('api.plan-statistics');
    Route::get('/api/invitation-statistics', [SuperAdminController::class, 'apiInvitationStatistics'])->name('api.invitation-statistics');
    Route::get('/api/system-health', [SuperAdminController::class, 'apiSystemHealth'])->name('api.system-health');
    Route::get('/api/revenue-chart-data', [SuperAdminController::class, 'apiRevenueChartData'])->name('api.revenue-chart-data');
    Route::get('/api/user-registration-chart-data', [SuperAdminController::class, 'apiUserRegistrationChartData'])->name('api.user-registration-chart-data');
    Route::get('/api/property-registration-chart-data', [SuperAdminController::class, 'apiPropertyRegistrationChartData'])->name('api.property-registration-chart-data');
    Route::get('/api/recent-payments', [SuperAdminController::class, 'apiRecentPayments'])->name('api.recent-payments');
    Route::get('/api/recent-users', [SuperAdminController::class, 'apiRecentUsers'])->name('api.recent-users');
    Route::get('/api/recent-properties', [SuperAdminController::class, 'apiRecentProperties'])->name('api.recent-properties');
    Route::get('/api/recent-registration-plans', [SuperAdminController::class, 'apiRecentRegistrationPlans'])->name('api.recent-registration-plans');
    Route::get('/api/pending-actions', [SuperAdminController::class, 'apiPendingActions'])->name('api.pending-actions');
    Route::get('/api/system-alerts', [SuperAdminController::class, 'apiSystemAlerts'])->name('api.system-alerts');
    Route::get('/api/quick-stats', [SuperAdminController::class, 'apiQuickStats'])->name('api.quick-stats');
    Route::get('/api/payment-providers-status', [SuperAdminController::class, 'apiPaymentProvidersStatus'])->name('api.payment-providers-status');
    Route::get('/api/sms-providers-status', [SuperAdminController::class, 'apiSmsProvidersStatus'])->name('api.sms-providers-status');
});

// Alternative route for backward compatibility
Route::middleware(['auth', 'multi.auth.user:0'])->get('/superadmin/dashboard', [SuperAdminController::class, 'dashboard'])
    ->name('superadmin.dashboard');

// ✅ FIXED: Admin Dashboard Routes - Simplified and working
Route::middleware(['auth', 'multi.auth.user:1'])->get('/admin/dashboard', [HomeController::class, 'adminDashboard'])
    ->name('admin.dashboard');

// ✅ ADDED: Enhanced Admin Dashboard Routes with prefix group
Route::middleware(['auth', 'multi.auth.user:1'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard API routes for admin
    Route::get('/dashboard/data', [HomeController::class, 'getAdminDashboardData'])->name('dashboard.data');
    Route::post('/dashboard/refresh', [HomeController::class, 'refreshAdminDashboard'])->name('dashboard.refresh');
    Route::get('/dashboard/export', [HomeController::class, 'exportAdminDashboardData'])->name('dashboard.export');
    
    // Admin dashboard statistics and analytics
    Route::get('/dashboard/statistics', [HomeController::class, 'getAdminStatistics'])->name('dashboard.statistics');
    Route::get('/dashboard/analytics', [HomeController::class, 'getAdminAnalytics'])->name('dashboard.analytics');
    
    // Admin dashboard widgets and customization
    Route::post('/dashboard/widgets/update', [HomeController::class, 'updateDashboardWidgets'])->name('dashboard.widgets.update');
    Route::get('/dashboard/widgets/reset', [HomeController::class, 'resetDashboardWidgets'])->name('dashboard.widgets.reset');
});

Route::middleware(['auth', 'multi.auth.user:2'])->group(function () {
    Route::get('/landlord/dashboard', [HomeController::class, 'landlordDashboard'])->name('landlord.dashboard');
});

Route::middleware(['auth', 'multi.auth.user:3'])->group(function () {
    Route::get('/tenant/dashboard', [HomeController::class, 'tenantDashboard'])->name('tenant.dashboard');
});

// Field Agent Dashboard
Route::middleware(['auth', 'multi.auth.user:4'])->group(function () {
    Route::get('/field-agent/dashboard', [HomeController::class, 'fieldAgentDashboard'])->name('field-agent.dashboard');
});

Route::middleware(['auth', 'multi.auth.user:6'])->group(function () {
    Route::get('/security-personnel/dashboard', [HomeController::class, 'securityPersonnelDashboard'])->name('security-personnel.dashboard');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: User Controller Routes (Landlord Tenant Management)
|--------------------------------------------------------------------------
*/

// Landlord-specific tenant management routes
Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    // Tenant management routes
    Route::get('/tenants', [UserController::class, 'landlordTenants'])->name('tenants.index');
    Route::get('/tenants/{id}', [UserController::class, 'landlordShowTenant'])->name('tenants.show');
    Route::get('/tenants/export', [UserController::class, 'exportTenants'])->name('tenants.export');
    Route::get('/tenants/{id}/statistics', [UserController::class, 'getTenantStatistics'])->name('tenants.statistics');
});

// Tenant-specific routes
Route::middleware(['auth', 'multi.auth.user:3'])->prefix('tenant')->name('tenant.')->group(function () {
    // Tenant profile view
    Route::get('/profile', [UserController::class, 'tenantProfile'])->name('profile');
});

// User search API (available to admins and landlords)
Route::middleware(['auth', 'multi.auth.user:0,1,2'])->group(function () {
    Route::get('/users/search', [UserController::class, 'searchUsers'])->name('users.search');
    Route::get('/available-tenants', [UserController::class, 'getAvailableTenants'])->name('tenants.available');
});

// User notifications API
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [UserController::class, 'getNotifications'])->name('notifications');
    Route::post('/notifications/{id}/read', [UserController::class, 'markNotificationAsRead'])->name('notifications.read');
    Route::post('/notifications/clear', [UserController::class, 'clearAllNotifications'])->name('notifications.clear');
    Route::post('/invitations/resend', [UserController::class, 'resendInvitation'])->name('invitations.resend');
});

// User profile routes (updated with new methods)
Route::middleware(['auth'])->prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [UserController::class, 'showProfile'])->name('show');
});

// Dashboard routes (already have dashboard routes, but need to update UserController reference)
Route::middleware(['auth'])->get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

// Landlord dashboard route (update to use UserController)
Route::middleware(['auth', 'multi.auth.user:2'])->get('/landlord/dashboard', [UserController::class, 'dashboard'])->name('landlord.dashboard');

// Tenant dashboard route (update to use UserController)
Route::middleware(['auth', 'multi.auth.user:3'])->get('/tenant/dashboard', [UserController::class, 'dashboard'])->name('tenant.dashboard');

/*
|--------------------------------------------------------------------------
| Field Agent Routes - UPDATED WITH NEW CONTROLLERS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:4'])->prefix('field-agent')->name('field-agent.')->group(function () {

    // ----------------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------------
    Route::get('/dashboard', [HomeController::class, 'fieldAgentDashboard'])->name('dashboard');

    // ----------------------------------------------------------------------
    // Property Management
    // ----------------------------------------------------------------------
    Route::prefix('properties')->name('properties.')->group(function () {
        // Index
        Route::get('/', [FieldAgentPropertyController::class, 'index'])->name('index');

        // Create
        Route::get('/create', [FieldAgentPropertyController::class, 'create'])->name('create');
        Route::post('/', [FieldAgentPropertyController::class, 'store'])->name('store');

        // --- Specific sub-routes MUST come before the /{property} wildcard ---
        Route::get('/my-properties', [FieldAgentPropertyController::class, 'myProperties'])->name('my-properties');
        Route::get('/registration-plan/{planId}', [FieldAgentPropertyController::class, 'getByRegistrationPlan'])->name('by-registration-plan');
        Route::get('/type-stats', [FieldAgentPropertyController::class, 'getPropertyTypeStats'])->name('type-stats');
        Route::get('/type/{typeSlug}', [FieldAgentPropertyController::class, 'getByPropertyType'])->name('by-type');

        // --- Wildcard routes after ---
        Route::get('/{property}/edit', [FieldAgentPropertyController::class, 'edit'])->name('edit');
        Route::put('/{property}', [FieldAgentPropertyController::class, 'update'])->name('update');
        Route::get('/{property}', [FieldAgentPropertyController::class, 'show'])->name('show');
        Route::delete('/{property}', [FieldAgentPropertyController::class, 'destroy'])->name('destroy');
        Route::put('/properties/{property}/coordinates', [FieldAgentPropertyController::class, 'updateCoordinates'])
                ->name('field-agent.properties.coordinates.update');
    });

    // ----------------------------------------------------------------------
    // Registration Plans (field agent view)
    // ----------------------------------------------------------------------
    Route::get('/registration-plans', [RegistrationPlanController::class, 'index'])->name('registration-plans.index');
    Route::get('/registration-plans/{id}', [RegistrationPlanController::class, 'show'])->name('registration-plans.show');

    // Property Registration from a plan
    Route::get('/registration/{planId}', [RegistrationPlanController::class, 'fieldAgentRegistration'])->name('registration');
    Route::post('/registration/{planId}/register-property', [RegistrationPlanController::class, 'registerProperty'])->name('registration.register-property');

    // ----------------------------------------------------------------------
    // Statistics — points to the real controller method
    // ----------------------------------------------------------------------
    Route::get('/statistics', [FieldAgentPropertyController::class, 'statistics'])->name('statistics');

    // ----------------------------------------------------------------------
    // Performance — points to the real controller method
    // ----------------------------------------------------------------------
    Route::get('/performance', [FieldAgentPropertyController::class, 'performance'])->name('performance');

    // ----------------------------------------------------------------------
    // Inspections (placeholder closures — leave as-is until feature exists)
    // ----------------------------------------------------------------------
    Route::get('/inspections', function () {
        return view('field-agent.inspections.index');
    })->name('inspections');

    Route::get('/inspections/create', function () {
        return view('field-agent.inspections.create');
    })->name('inspections.create');

    // ----------------------------------------------------------------------
    // Verifications (placeholder closures — leave as-is until feature exists)
    // ----------------------------------------------------------------------
    Route::get('/verifications', function () {
        return view('field-agent.verifications.index');
    })->name('verifications');

    Route::get('/verifications/{property}/verify', function ($property) {
        return view('field-agent.verifications.verify', compact('property'));
    })->name('verifications.verify');

    // ----------------------------------------------------------------------
    // Collections (placeholder closures)
    // ----------------------------------------------------------------------
    Route::get('/collections', function () {
        return view('field-agent.collections.index');
    })->name('collections');

    Route::get('/collections/create', function () {
        return view('field-agent.collections.create');
    })->name('collections.create');

    // ----------------------------------------------------------------------
    // Reports (placeholder closures)
    // ----------------------------------------------------------------------
    Route::get('/reports', function () {
        return view('field-agent.reports.index');
    })->name('reports');

    Route::get('/reports/create', function () {
        return view('field-agent.reports.create');
    })->name('reports.create');

    // ----------------------------------------------------------------------
    // Assigned Properties (placeholder closures)
    // ----------------------------------------------------------------------
    Route::get('/assigned-properties', function () {
        return view('field-agent.assigned-properties.index');
    })->name('assigned-properties');

    Route::get('/assigned-properties/{property}', function ($property) {
        return view('field-agent.assigned-properties.show', compact('property'));
    })->name('assigned-properties.show');

    // ----------------------------------------------------------------------
    // Settings (placeholder closures)
    // ----------------------------------------------------------------------
    Route::get('/settings', function () {
        return view('field-agent.settings');
    })->name('settings');

    Route::put('/settings', function (Request $request) {
        return redirect()->route('field-agent.settings')->with('success', 'Settings updated successfully.');
    })->name('settings.update');

});



/*
|--------------------------------------------------------------------------
| ✅ PROPERTY UNIT MANAGEMENT ROUTES - REORGANIZED BY CONTROLLER
|--------------------------------------------------------------------------
*/

// ========== DOCUMENT PREVIEW & DOWNLOAD ROUTES ==========
Route::middleware(['auth'])->group(function () {
    Route::middleware(['multi.auth.user:0,1,2'])->group(function () {
        Route::get('/preview-document/{path}/{disk}', [DocumentController::class, 'preview'])
            ->name('admin.preview-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
        
        Route::get('/download-document/{path}/{disk}', [DocumentController::class, 'download'])
            ->name('admin.download-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
        
        Route::post('/documents/upload', [DocumentController::class, 'upload'])
            ->name('tenant.documents.upload');
    });
});


// ========== WITNESS SIGNING ROUTES (Public Access) ==========
Route::middleware(['web'])->group(function () {
    Route::get('/witness/landlord-sign/{unitId}/{leaseId}', 
        [PropertyUnitLeaseController::class, 'showLandlordWitnessSignForm'])
        ->name('witness.landlord-sign-form');
    
    Route::post('/witness/landlord-sign/{unitId}/{leaseId}', 
        [PropertyUnitLeaseController::class, 'landlordWitnessSign'])
        ->name('witness.landlord-sign');
    
    Route::get('/witness/tenant-sign/{unitId}/{leaseId}', 
        [PropertyUnitLeaseController::class, 'showTenantWitnessSignForm'])
        ->name('witness.tenant-sign-form');
    
    Route::post('/witness/tenant-sign/{unitId}/{leaseId}', 
        [PropertyUnitLeaseController::class, 'tenantWitnessSign'])
        ->name('witness.tenant-sign');
});

// ========== PROPERTY UNIT CONTROLLER ROUTES ==========
Route::middleware(['auth'])->group(function () {
    
    // ----- Index Route -----
    Route::get('/property-units', [PropertyUnitController::class, 'index'])
        ->name('property-units.index')
        ->middleware('multi.auth.user:0,1,2,3');
    
    // ----- Create/Store Routes (Landlord only) -----
    Route::middleware(['multi.auth.user:2'])->group(function () {
        Route::get('/property-units/create', [PropertyUnitController::class, 'create'])
            ->name('property-units.create');
        Route::post('/property-units', [PropertyUnitController::class, 'store'])
            ->name('property-units.store');
    });
    
    // ----- Export Route (Admin and Landlord only) -----
    Route::middleware(['multi.auth.user:0,1,2'])->group(function () {
        Route::get('/property-units/export', [PropertyUnitController::class, 'export'])
            ->name('property-units.export');
    });
    
    // ----- Trash Routes (Landlord only) -----
    Route::middleware(['multi.auth.user:2'])->prefix('property-units')->group(function () {
        Route::get('/trash', [PropertyUnitController::class, 'trash'])
            ->name('property-units.trash');
        Route::post('/trash/empty', [PropertyUnitController::class, 'emptyTrash'])
            ->name('property-units.empty-trash');
    });
    
    // ----- Bulk Selection Redirect -----
    Route::get('/property-units/bulk-selection', function() {
        return redirect()->route('property-units.bulk-approval')
            ->with('info', 'Please use the bulk approval page for unit selection.');
    })->name('property-units.bulk-selection');
    
    // ----- API Routes -----
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/property-units/by-property/{propertyId}', [PropertyUnitController::class, 'getUnitsByProperty'])
            ->name('property-units.by-property');
        Route::get('/property-units/{id}/availability', [PropertyUnitController::class, 'checkAvailability'])
            ->name('property-units.check-availability');
        Route::get('/property-units/dashboard-stats', [PropertyUnitController::class, 'getDashboardStats'])
            ->name('property-units.dashboard-stats');
        Route::get('/property-units/statistics-for-dashboard', [PropertyUnitController::class, 'getStatisticsForDashboard'])
            ->name('property-units.statistics-for-dashboard');
        Route::get('/property-units/{id}/statistics', [PropertyUnitController::class, 'getUnitStatistics'])
            ->name('property-units.statistics');
        Route::get('/property-units/bulk-selection', [PropertyUnitController::class, 'getUnitsForBulkSelection'])
            ->name('property-units.bulk-selection.api');
        Route::get('/property-units/{id}/quick-details', [PropertyUnitController::class, 'getUnitQuickDetails'])
            ->name('property-units.quick-details');
        Route::get('/property-units/search', [PropertyUnitController::class, 'searchUnits'])
            ->name('property-units.search');
    });
    
    // ----- Parameterized Routes (/property-units/{id}) -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // Show route
        Route::get('/', [PropertyUnitController::class, 'show'])
            ->name('property-units.show');
        
        // Edit/Update/Delete (Landlord only)
        Route::middleware(['multi.auth.user:2'])->group(function () {
            Route::get('/edit', [PropertyUnitController::class, 'edit'])
                ->name('property-units.edit');
            Route::put('/', [PropertyUnitController::class, 'update'])
                ->name('property-units.update');
            Route::delete('/', [PropertyUnitController::class, 'destroy'])
                ->name('property-units.destroy');
            Route::post('/archive', [PropertyUnitController::class, 'archive'])
                ->name('property-units.archive');
            Route::post('/restore', [PropertyUnitController::class, 'restore'])
                ->name('property-units.restore');
        });
        
        // Trash Management (Landlord only)
        Route::middleware(['multi.auth.user:2'])->group(function () {
            Route::post('/restore-from-trash', [PropertyUnitController::class, 'restoreFromTrash'])
                ->name('property-units.restore-from-trash');
            Route::delete('/force-delete', [PropertyUnitController::class, 'forceDelete'])
                ->name('property-units.force-delete');
        });
        
        // Contact Landlord (Tenant only)
        Route::middleware(['multi.auth.user:3'])->group(function () {
            Route::get('/contact-landlord', [PropertyUnitController::class, 'showContactLandlordForm'])
                ->name('property-units.contact-landlord.form');
            Route::post('/contact-landlord/send', [PropertyUnitController::class, 'sendMessageToLandlord'])
                ->name('property-units.contact-landlord.send');
        });
        
        // Unit Documents View
        Route::get('/documents', function($id) {
            $unit = App\Models\PropertyUnit::findOrFail($id);
            $user = auth()->user();
            
            if ($user->isLandlord() && $unit->property->landlord_id !== $user->id) {
                abort(403, 'You can only view documents for your own properties.');
            }
            if ($user->isTenant() && $unit->tenant_id !== $user->id) {
                abort(403, 'You can only view documents for your assigned unit.');
            }
            
            return view('property_units.documents', compact('unit'));
        })->name('property-units.documents');
        
        // Payment History Redirect
        Route::get('/payment-history', function($id) {
            return redirect()->route('property-units.financials', $id)
                ->with('info', 'Payment history is available in the financials section.');
        })->name('property-units.payment-history');
    });
});

// ========== PROPERTY UNIT TENANT CONTROLLER ROUTES ==========
Route::middleware(['auth'])->group(function () {
    
    // ----- Pending Approvals -----
    Route::get('/property-units/pending-approvals', [PropertyUnitTenantController::class, 'pendingApprovals'])
        ->name('property-units.pending-approvals')
        ->middleware('multi.auth.user:0,1,2');
    
    // ----- Bulk Approval (Admin only) -----
    Route::middleware(['multi.auth.user:0,1'])->group(function () {
        Route::get('/property-units/bulk-approval', [PropertyUnitTenantController::class, 'showBulkApproval'])
            ->name('property-units.bulk-approval');
        Route::post('/property-units/bulk-approval/process', [PropertyUnitTenantController::class, 'processBulkApproval'])
            ->name('property-units.bulk-approval.process');
    });
    
    // ----- Tenant Invitation Routes (Admin, Super Admin, Landlord) -----
    Route::middleware(['multi.auth.user:0,1,2'])->prefix('property-units')->group(function () {
        Route::get('/{unitId}/invitations/{invitationId}/status', [PropertyUnitTenantController::class, 'viewTenantInvitationStatus'])
            ->name('property-units.view-invitation-status')
            ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
        
        Route::post('/{unitId}/invitations/{invitationId}/resend', [PropertyUnitTenantController::class, 'resendTenantInvitation'])
            ->name('property-units.resend-invitation')
            ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
    });
    
    // ----- Parameterized Routes (/property-units/{id}) -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // Assignment Details (Admin, Super Admin)
        Route::get('/assignment-details', [PropertyUnitTenantController::class, 'viewAssignmentDetails'])
            ->name('property-units.assignment-details')
            ->middleware('multi.auth.user:0,1');
        
        // Mark Vacated Form (Admin, Super Admin, Landlord)
        Route::get('/mark-vacated-form', [PropertyUnitTenantController::class, 'markVacatedForm'])
            ->name('property-units.mark-vacated-form')
            ->middleware('multi.auth.user:0,1,2');
        
        // Mark Vacated (Admin, Super Admin, Landlord)
        Route::put('/mark-vacated', [PropertyUnitTenantController::class, 'markVacated'])
            ->name('property-units.mark-vacated')
            ->middleware('multi.auth.user:0,1,2');
        
        // Terminate Tenant (Admin, Super Admin, Landlord)
        Route::post('/terminate-tenant', [PropertyUnitTenantController::class, 'terminateTenant'])
            ->name('property-units.terminate-tenant')
            ->middleware('multi.auth.user:0,1,2');
        
        // Tenant Approval/Rejection (Admin, Super Admin)
        Route::middleware(['multi.auth.user:0,1'])->group(function () {
            Route::post('/approve-tenant', [PropertyUnitTenantController::class, 'approveTenant'])
                ->name('property-units.approve-tenant');
            Route::post('/reject-tenant', [PropertyUnitTenantController::class, 'rejectTenant'])
                ->name('property-units.reject-tenant');
        });
        
        // Tenant Assignment (Landlord only)
        Route::middleware(['multi.auth.user:2'])->group(function () {
            Route::get('/assign-tenant', [PropertyUnitTenantController::class, 'showAssignTenantForm'])
                ->name('property-units.assign-tenant.form');
            Route::post('/assign-tenant', [PropertyUnitTenantController::class, 'assignTenant'])
                ->name('property-units.assign-tenant');
        });
        
        // Tenant Unit View
        Route::get('/my-unit', [PropertyUnitTenantController::class, 'tenantUnitView'])
            ->name('property-units.my-unit')
            ->middleware('multi.auth.user:3');
        
        // Show for Tenant
        Route::get('/show-for-tenant', [PropertyUnitTenantController::class, 'showForTenant'])
            ->name('property-units.show-for-tenant')
            ->middleware('multi.auth.user:3');
    });
});

// ========== PROPERTY UNIT LEASE CONTROLLER ROUTES ==========
Route::middleware(['auth'])->group(function () {
    
    // ----- Parameterized Routes (/property-units/{id}) -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // Lease Management (Admin, Super Admin, Landlord)
        Route::get('/lease-management', [PropertyUnitLeaseController::class, 'showLeaseManagement'])
            ->name('property-units.lease-management')
            ->middleware('multi.auth.user:0,1,2');
        
        // Lease Creation (Landlord only)
        Route::middleware(['multi.auth.user:2'])->group(function () {
            Route::get('/create-lease', [PropertyUnitLeaseController::class, 'showCreateLeaseForm'])
                ->name('property-units.create-lease.form');
            Route::post('/create-lease', [PropertyUnitLeaseController::class, 'createLease'])
                ->name('property-units.create-lease');
            
            // ✅ FIXED: Renew Lease route - now accessible to anyone with landlord role
            // This works for Super Admin, Admin, Security Personnel who also have landlord role
            Route::post('/renew-lease', [PropertyUnitLeaseController::class, 'renewLease'])
                ->name('property-units.renew-lease');
        });
        
        // ----- Lease Parameterized Routes (/property-units/{id}/leases/{leaseId}) -----
        Route::prefix('leases/{leaseId}')->where(['leaseId' => '[0-9]+'])->group(function () {
            
            // Lease Details (Admin, Super Admin, Landlord, Tenant)
            Route::get('/details', [PropertyUnitLeaseController::class, 'showLeaseDetails'])
                ->name('property-units.lease-details')
                ->middleware('multi.auth.user:0,1,2,3');
            
            // Download PDF (Admin, Super Admin, Landlord, Tenant)
            Route::get('/download-pdf', [PropertyUnitLeaseController::class, 'generateLeasePdf'])
                ->name('property-units.lease-download-pdf')
                ->middleware('multi.auth.user:0,1,2,3');
            
            // Landlord Sign (Landlord only)
            Route::post('/landlord-sign', [PropertyUnitLeaseController::class, 'landlordSignLease'])
                ->name('property-units.landlord-sign-lease')
                ->middleware('multi.auth.user:2');
            
            // Tenant Sign (Tenant only)
            Route::post('/tenant-sign', [PropertyUnitLeaseController::class, 'tenantSignLease'])
                ->name('property-units.tenant-sign-lease')
                ->middleware('multi.auth.user:3');
            
            // Terminate Lease (Admin, Super Admin, Landlord)
            Route::post('/terminate', [PropertyUnitLeaseController::class, 'terminateLease'])
                ->name('property-units.terminate-lease')
                ->middleware('multi.auth.user:0,1,2');
            
            // Edit Redirect (Landlord only)
            Route::get('/edit', function($id, $leaseId) {
                return redirect()->route('property-units.lease-details', [$id, $leaseId])
                    ->with('info', 'Lease editing is not available. Please create a new lease instead.');
            })->middleware('multi.auth.user:2')->name('property-units.lease-edit');
        });
    });
});

// ========== PROPERTY UNIT MAINTENANCE CONTROLLER ROUTES ==========
Route::middleware(['auth'])->group(function () {
    
    // ----- Parameterized Routes (/property-units/{id}) -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // Maintenance Requests (Admin, Super Admin, Landlord, Tenant)
        Route::get('/maintenance-requests', [PropertyUnitMaintenanceController::class, 'showMaintenanceRequests'])
            ->name('property-units.maintenance-requests')
            ->middleware('multi.auth.user:0,1,2,3');
        
        // Create Maintenance Request (Admin, Super Admin, Landlord, Tenant)
        Route::post('/create-maintenance-request', [PropertyUnitMaintenanceController::class, 'createMaintenanceRequest'])
            ->name('property-units.create-maintenance-request')
            ->middleware('multi.auth.user:0,1,2,3');
        
        // Mark Maintenance Complete (Admin, Super Admin, Landlord)
        Route::post('/mark-maintenance-complete', [PropertyUnitMaintenanceController::class, 'markMaintenanceComplete'])
            ->name('property-units.mark-maintenance-complete')
            ->middleware('multi.auth.user:0,1,2');
    });
});




// ========================================
// TENANT MAINTENANCE ROUTES
// ========================================
Route::middleware(['auth', 'multi.auth.user:3'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {
        
        Route::prefix('maintenance')->name('maintenance.')->group(function () {
            
            // ✅ STATIC ROUTES FIRST (no parameters)
            Route::get('/create', [TenantMaintenanceController::class, 'create'])->name('create');
            Route::get('/trashed', [TenantMaintenanceController::class, 'trashed'])->name('trashed');
            Route::get('/export-current-page', [TenantMaintenanceController::class, 'exportCurrentPage'])->name('export-current-page');
            Route::get('/export-all', [TenantMaintenanceController::class, 'exportAll'])->name('export-all');
            Route::get('/api/requests', [TenantMaintenanceController::class, 'apiRequests'])->name('api.requests');
            
            // ✅ POST ROUTES
            Route::post('/', [TenantMaintenanceController::class, 'store'])->name('store');
            
            // ✅ PARAMETERIZED ROUTES (with {id})
            Route::get('/', [TenantMaintenanceController::class, 'index'])->name('index');
            Route::get('/{id}', [TenantMaintenanceController::class, 'show'])->name('show');
            Route::put('/{id}/cancel', [TenantMaintenanceController::class, 'cancel'])->name('cancel');
            Route::delete('/{id}', [TenantMaintenanceController::class, 'destroy'])->name('destroy');
            Route::delete('/{id}/force', [TenantMaintenanceController::class, 'forceDelete'])->name('force-delete');
            Route::get('/{id}/export-pdf', [TenantMaintenanceController::class, 'exportSingle'])->name('export-single');
        });
    });


// ========== PROPERTY UNIT FINANCIAL CONTROLLER ROUTES ==========
//

Route::middleware(['auth'])->group(function () {

    // ----- Parameterized Routes (/property-units/{id}) -----
    Route::prefix('property-units/{id}')
        ->whereNumber('id')
        ->group(function () {

            // ============================================================
            // FINANCIAL OVERVIEW
            // ============================================================

            // ----- Financials Overview -----
            Route::get('/financials', [PropertyUnitFinancialController::class, 'showFinancials'])
                ->name('property-units.financials')
                ->middleware('multi.auth.user:0,1,2,3');

            // ----- Export Financial Report (CSV) -----
            Route::get('/export-financial-report', [PropertyUnitFinancialController::class, 'exportFinancialReport'])
                ->name('property-units.export-financial-report')
                ->middleware('multi.auth.user:2');

            // ============================================================
            // INVOICE CREATION
            // ============================================================

            // ----- Generate Invoice -----
            Route::post('/generate-invoice', [PropertyUnitFinancialController::class, 'generateInvoice'])
                ->name('property-units.generate-invoice')
                ->middleware('multi.auth.user:2');

            // ============================================================
            // INVOICE DETAIL / UPDATE
            // ============================================================

            // ✅ NEW: ----- Show a Single Invoice -----
            Route::get('/invoices/{invoice}', [PropertyUnitFinancialController::class, 'showInvoice'])
                ->whereNumber('invoice')
                ->name('property-units.show-invoice')
                ->middleware('multi.auth.user:2,3');

            // ✅ NEW: ----- Update Invoice (non-financial fields) -----
            Route::patch('/invoices/{invoice}', [PropertyUnitFinancialController::class, 'updateInvoice'])
                ->whereNumber('invoice')
                ->name('property-units.update-invoice')
                ->middleware('multi.auth.user:2');

            // ============================================================
            // INVOICE PAYMENT / STATUS
            // ============================================================

            // ----- Record Payment on an Invoice -----
            Route::post('/invoices/{invoice}/payment', [PropertyUnitFinancialController::class, 'recordPayment'])
                ->whereNumber('invoice')
                ->name('property-units.record-payment')
                ->middleware('multi.auth.user:2');

            // ----- Void an Invoice -----
            Route::post('/invoices/{invoice}/void', [PropertyUnitFinancialController::class, 'voidInvoice'])
                ->whereNumber('invoice')
                ->name('property-units.void-invoice')
                ->middleware('multi.auth.user:2');

            // ✅ NEW: ----- Apply Late Fee to an Overdue Invoice -----
            Route::post('/invoices/{invoice}/apply-late-fee', [PropertyUnitFinancialController::class, 'applyLateFee'])
                ->whereNumber('invoice')
                ->name('property-units.apply-late-fee')
                ->middleware('multi.auth.user:2');

            // ✅ NEW: ----- Recalculate Invoice Status from amount_paid -----
            Route::post('/invoices/{invoice}/recalculate', [PropertyUnitFinancialController::class, 'recalculateStatus'])
                ->whereNumber('invoice')
                ->name('property-units.recalculate-invoice')
                ->middleware('multi.auth.user:2');

            // ============================================================
            // INVOICE DELIVERY
            // ============================================================

            // ✅ NEW: ----- Send Invoice to Tenant via Email -----
            Route::post('/invoices/{invoice}/send-to-tenant', [PropertyUnitFinancialController::class, 'sendInvoiceToTenant'])
                ->whereNumber('invoice')
                ->name('property-units.send-invoice')
                ->middleware('multi.auth.user:2');

            // ----- Download a Single Invoice PDF -----
            Route::get('/invoices/{invoice}/pdf', [PropertyUnitFinancialController::class, 'downloadInvoicePdf'])
                ->whereNumber('invoice')
                ->name('property-units.invoice-pdf')
                ->middleware('multi.auth.user:2,3');

            // ============================================================
            // BULK / MAINTENANCE ACTIONS
            // ============================================================

            // ✅ NEW: ----- Bulk Void Invoices -----
            Route::post('/invoices/bulk-void', [PropertyUnitFinancialController::class, 'bulkVoid'])
                ->name('property-units.bulk-void')
                ->middleware('multi.auth.user:2');

            // ✅ NEW: ----- Mark Overdue Invoices -----
            Route::post('/invoices/mark-overdue', [PropertyUnitFinancialController::class, 'markOverdue'])
                ->name('property-units.mark-overdue')
                ->middleware('multi.auth.user:2');
        });
});

// ========== DEVELOPER ROUTES (Analytics Only) ==========
Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    Route::get('/property-units/analytics', function() {
        return view('developer.property-units.analytics');
    })->name('property-units.analytics');
});

// ========== LANDLORD ROUTES ==========
Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    
    // ----- Property Unit Routes -----
    Route::get('/property-units', [PropertyUnitController::class, 'index'])->name('property-units.index');
    Route::get('/property-units/create', [PropertyUnitController::class, 'create'])->name('property-units.create');
    Route::post('/property-units', [PropertyUnitController::class, 'store'])->name('property-units.store');
    Route::get('/property-units/export', [PropertyUnitController::class, 'export'])->name('property-units.export');
    Route::get('/property-units/trash', [PropertyUnitController::class, 'trash'])->name('property-units.trash');
    Route::post('/property-units/trash/empty', [PropertyUnitController::class, 'emptyTrash'])->name('property-units.empty-trash');
    
    // ----- Bulk Selection Redirect -----
    Route::get('/property-units/bulk-selection', function() {
        return redirect()->route('landlord.property-units.bulk-approval')
            ->with('info', 'Bulk selection is only available to administrators.');
    })->name('property-units.bulk-selection');
    
    // ----- Bulk Approval Redirect -----
    Route::get('/property-units/bulk-approval', function() {
        return redirect()->route('landlord.dashboard')
            ->with('info', 'Bulk approval is only available to administrators.');
    })->name('property-units.bulk-approval');
    
    // ----- Pending Approvals -----
    Route::get('/property-units/pending-approvals', [PropertyUnitTenantController::class, 'pendingApprovals'])
        ->name('property-units.pending-approvals');
    
    // ----- Tenant Invitation Routes -----
    Route::prefix('property-units')->group(function () {
        Route::get('/{unitId}/invitations/{invitationId}/status', [PropertyUnitTenantController::class, 'viewTenantInvitationStatus'])
            ->name('property-units.view-invitation-status')
            ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
        
        Route::post('/{unitId}/invitations/{invitationId}/resend', [PropertyUnitTenantController::class, 'resendTenantInvitation'])
            ->name('property-units.resend-invitation')
            ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
    });
    
    // ----- Property Unit Parameterized Routes -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // PropertyUnitController routes
        Route::get('/', [PropertyUnitController::class, 'show'])->name('property-units.show');
        Route::get('/edit', [PropertyUnitController::class, 'edit'])->name('property-units.edit');
        Route::put('/', [PropertyUnitController::class, 'update'])->name('property-units.update');
        Route::delete('/', [PropertyUnitController::class, 'destroy'])->name('property-units.destroy');
        Route::post('/archive', [PropertyUnitController::class, 'archive'])->name('property-units.archive');
        Route::post('/restore', [PropertyUnitController::class, 'restore'])->name('property-units.restore');
        Route::post('/restore-from-trash', [PropertyUnitController::class, 'restoreFromTrash'])->name('property-units.restore-from-trash');
        Route::delete('/force-delete', [PropertyUnitController::class, 'forceDelete'])->name('property-units.force-delete');
        
        // PropertyUnitTenantController routes
        Route::get('/mark-vacated-form', [PropertyUnitTenantController::class, 'markVacatedForm'])->name('property-units.mark-vacated-form');
        Route::put('/mark-vacated', [PropertyUnitTenantController::class, 'markVacated'])->name('property-units.mark-vacated');
        Route::post('/terminate-tenant', [PropertyUnitTenantController::class, 'terminateTenant'])->name('property-units.terminate-tenant');
        Route::get('/assign-tenant', [PropertyUnitTenantController::class, 'showAssignTenantForm'])->name('property-units.assign-tenant.form');
        Route::post('/assign-tenant', [PropertyUnitTenantController::class, 'assignTenant'])->name('property-units.assign-tenant');
        
        // PropertyUnitLeaseController routes
        Route::get('/lease-management', [PropertyUnitLeaseController::class, 'showLeaseManagement'])->name('property-units.lease-management');
        Route::get('/create-lease', [PropertyUnitLeaseController::class, 'showCreateLeaseForm'])->name('property-units.create-lease.form');
        Route::post('/create-lease', [PropertyUnitLeaseController::class, 'createLease'])->name('property-units.create-lease');
        // ✅ FIXED: Landlord-specific renew route (kept for backward compatibility)
        Route::post('/renew-lease', [PropertyUnitLeaseController::class, 'renewLease'])->name('property-units.renew-lease');
        
        // PropertyUnitMaintenanceController routes
        Route::post('/create-maintenance-request', [PropertyUnitMaintenanceController::class, 'createMaintenanceRequest'])->name('property-units.create-maintenance-request');
        Route::post('/mark-maintenance-complete', [PropertyUnitMaintenanceController::class, 'markMaintenanceComplete'])->name('property-units.mark-maintenance-complete');
        Route::get('/maintenance-requests', [PropertyUnitMaintenanceController::class, 'showMaintenanceRequests'])->name('property-units.maintenance-requests');
        
        // PropertyUnitFinancialController routes
        Route::get('/financials', [PropertyUnitFinancialController::class, 'showFinancials'])->name('property-units.financials');
        Route::get('/export-financial-report', [PropertyUnitFinancialController::class, 'exportFinancialReport'])->name('property-units.export-financial-report');
        Route::post('/generate-invoice', [PropertyUnitFinancialController::class, 'generateInvoice'])->name('property-units.generate-invoice');
        
        // Document routes
        Route::prefix('documents')->group(function () {
            Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
                ->name('landlord.property-units.documents.preview')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
                ->name('landlord.property-units.documents.download')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/application/{index}/download', [DocumentController::class, 'downloadApplicationDocument'])
                ->name('landlord.property-units.documents.download-application')
                ->where(['index' => '[0-9]+']);
            
            Route::delete('/{documentId}', [DocumentController::class, 'destroy'])
                ->name('landlord.property-units.documents.destroy')
                ->where(['documentId' => '[0-9]+']);
        });
        
        // Unit documents view
        Route::get('/documents', function($id) {
            $unit = App\Models\PropertyUnit::findOrFail($id);
            $user = auth()->user();
            
            if ($unit->property->landlord_id !== $user->id) {
                abort(403, 'You can only view documents for your own properties.');
            }
            
            return view('landlord.property_units.documents', compact('unit'));
        })->name('property-units.documents');
        
        // Payment history redirect
        Route::get('/payment-history', function($id) {
            return redirect()->route('landlord.property-units.financials', $id)
                ->with('info', 'Payment history is available in the financials section.');
        })->name('property-units.payment-history');
        
        // Lease parameterized routes
        Route::prefix('leases/{leaseId}')->where(['leaseId' => '[0-9]+'])->group(function () {
            Route::get('/details', [PropertyUnitLeaseController::class, 'showLeaseDetails'])->name('property-units.lease-details');
            Route::get('/download-pdf', [PropertyUnitLeaseController::class, 'generateLeasePdf'])->name('property-units.lease-download-pdf');
            Route::post('/landlord-sign', [PropertyUnitLeaseController::class, 'landlordSignLease'])->name('property-units.landlord-sign-lease');
            Route::post('/terminate', [PropertyUnitLeaseController::class, 'terminateLease'])->name('property-units.terminate-lease');
        });
    });
    
    // ----- Document Routes -----
    Route::prefix('documents')->group(function () {
        Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
            ->name('landlord.preview-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
        
        Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
            ->name('landlord.download-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
    });
    
    // ----- Tenant Management Routes -----
    Route::prefix('tenants')->group(function () {
        Route::get('/', function() {
            $user = auth()->user();
            $tenants = User::where('type', User::TYPE_TENANT)
                ->whereHas('propertyUnits', function($query) use ($user) {
                    $query->whereHas('property', function($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    });
                })
                ->orderBy('name')
                ->paginate(20);
            
            return view('landlord.tenants.index', compact('tenants'));
        })->name('tenants.index');
        
        Route::get('/{id}', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            $user = auth()->user();
            
            $hasAccess = $tenant->propertyUnits()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to view this tenant.');
            }
            
            return view('landlord.tenants.show', compact('tenant'));
        })->name('tenants.show');
        
        Route::get('/{id}/financials', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            $user = auth()->user();
            
            $hasAccess = $tenant->propertyUnits()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to view this tenant\'s financials.');
            }
            
            $unit = $tenant->propertyUnits()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })
                ->first();
            
            if (!$unit) {
                return redirect()->route('landlord.tenants.show', $tenant->id)
                    ->with('error', 'Tenant is not currently assigned to any of your units.');
            }
            
            return redirect()->route('landlord.property-units.financials', $unit->id);
        })->name('tenants.financials');
        
        Route::post('/{id}/send-message', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            $user = auth()->user();
            
            $hasAccess = $tenant->propertyUnits()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to message this tenant.');
            }
            
            $validated = request()->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
                'send_email' => 'nullable|boolean',
                'send_sms' => 'nullable|boolean',
            ]);
            
            try {
                $message = new \App\Models\Message([
                    'sender_id' => $user->id,
                    'receiver_id' => $tenant->id,
                    'subject' => $validated['subject'],
                    'content' => $validated['message'],
                    'type' => 'landlord_to_tenant',
                    'status' => 'sent'
                ]);
                $message->save();
                
                return redirect()->route('landlord.tenants.show', $tenant->id)
                    ->with('success', 'Message sent successfully.');
            } catch (\Exception $e) {
                \Log::error('Failed to send message: ' . $e->getMessage());
                return redirect()->route('landlord.tenants.show', $tenant->id)
                    ->with('error', 'Failed to send message. Please try again.');
            }
        })->name('tenants.send-message');
        
        Route::put('/{id}', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            $user = auth()->user();
            
            $hasAccess = $tenant->propertyUnits()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to update this tenant.');
            }
            
            $validated = request()->validate([
                'notes' => 'nullable|string|max:500',
            ]);
            
            if (isset($validated['notes'])) {
                $tenant->landlord_notes = $validated['notes'];
                $tenant->save();
                
                return redirect()->route('landlord.tenants.show', $tenant->id)
                    ->with('success', 'Tenant notes updated successfully.');
            }
            
            return redirect()->route('landlord.tenants.show', $tenant->id);
        })->name('tenants.update');
        
        Route::get('/create', function() {
            return redirect()->route('landlord.property-units.index')
                ->with('info', 'Please assign a tenant to a unit first. Tenants are created when assigned to units.');
        })->name('tenants.create');
        
        Route::post('/', function() {
            return redirect()->route('landlord.tenants.index')
                ->with('info', 'Please assign a tenant to a unit first. Tenants are created when assigned to units.');
        })->name('tenants.store');
        
        Route::get('/{id}/edit', function($id) {
            return redirect()->route('landlord.tenants.show', $id)
                ->with('info', 'Tenant editing is limited to notes only. Use the notes field on the tenant page.');
        })->name('tenants.edit');
        
        Route::delete('/{id}', function($id) {
            return redirect()->route('landlord.tenants.index')
                ->with('error', 'Tenants cannot be deleted directly. Please vacate them from units instead.');
        })->name('tenants.destroy');
    });
});

// ========== ADMIN ROUTES ==========
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // ----- Property Unit Routes -----
    Route::get('/property-units', [PropertyUnitController::class, 'index'])->name('property-units.index');
    Route::get('/property-units/export', [PropertyUnitController::class, 'export'])->name('property-units.export');
    Route::get('/property-units/bulk-selection', [PropertyUnitController::class, 'getUnitsForBulkSelection'])
        ->name('property-units.bulk-selection');
    
    // ----- Pending Approvals -----
    Route::get('/property-units/pending-approvals', [PropertyUnitTenantController::class, 'pendingApprovals'])
        ->name('property-units.pending-approvals');
    
    // ----- Bulk Approval -----
    Route::get('/property-units/bulk-approval', [PropertyUnitTenantController::class, 'showBulkApproval'])
        ->name('property-units.bulk-approval');
    Route::post('/property-units/bulk-approval/process', [PropertyUnitTenantController::class, 'processBulkApproval'])
        ->name('property-units.bulk-approval.process');
    
    // ----- Tenant Invitation Routes -----
    Route::prefix('property-units')->group(function () {
        Route::get('/{unitId}/invitations/{invitationId}/status', [PropertyUnitTenantController::class, 'viewTenantInvitationStatus'])
            ->name('property-units.view-invitation-status')
            ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
    });
    
    // ----- Property Unit Parameterized Routes -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // PropertyUnitController routes
        Route::get('/', [PropertyUnitController::class, 'show'])->name('property-units.show');
        
        // PropertyUnitTenantController routes
        Route::get('/assignment-details', [PropertyUnitTenantController::class, 'viewAssignmentDetails'])->name('property-units.assignment-details');
        Route::get('/mark-vacated-form', [PropertyUnitTenantController::class, 'markVacatedForm'])->name('property-units.mark-vacated-form');
        Route::put('/mark-vacated', [PropertyUnitTenantController::class, 'markVacated'])->name('property-units.mark-vacated');
        Route::post('/terminate-tenant', [PropertyUnitTenantController::class, 'terminateTenant'])->name('property-units.terminate-tenant');
        Route::post('/approve-tenant', [PropertyUnitTenantController::class, 'approveTenant'])->name('property-units.approve-tenant');
        Route::post('/reject-tenant', [PropertyUnitTenantController::class, 'rejectTenant'])->name('property-units.reject-tenant');
        
        // PropertyUnitLeaseController routes
        Route::get('/lease-management', [PropertyUnitLeaseController::class, 'showLeaseManagement'])->name('property-units.lease-management');
        Route::get('/create-lease', [PropertyUnitLeaseController::class, 'showCreateLeaseForm'])->name('property-units.create-lease.form');
        // ✅ FIXED: Admin-specific renew route (kept for backward compatibility)
        Route::post('/renew-lease', [PropertyUnitLeaseController::class, 'renewLease'])->name('property-units.renew-lease');
        
        // PropertyUnitMaintenanceController routes
        Route::post('/create-maintenance-request', [PropertyUnitMaintenanceController::class, 'createMaintenanceRequest'])->name('property-units.create-maintenance-request');
        Route::post('/mark-maintenance-complete', [PropertyUnitMaintenanceController::class, 'markMaintenanceComplete'])->name('property-units.mark-maintenance-complete');
        Route::get('/maintenance-requests', [PropertyUnitMaintenanceController::class, 'showMaintenanceRequests'])->name('property-units.maintenance-requests');
        
        // PropertyUnitFinancialController routes
        Route::get('/financials', [PropertyUnitFinancialController::class, 'showFinancials'])->name('property-units.financials');
        Route::get('/export-financial-report', [PropertyUnitFinancialController::class, 'exportFinancialReport'])->name('property-units.export-financial-report');
        Route::post('/generate-invoice', [PropertyUnitFinancialController::class, 'generateInvoice'])->name('property-units.generate-invoice');
        
        // Document routes
        Route::prefix('documents')->group(function () {
            Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
                ->name('admin.property-units.documents.preview')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
                ->name('admin.property-units.documents.download')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/application/{index}/download', [DocumentController::class, 'downloadApplicationDocument'])
                ->name('admin.property-units.documents.download-application')
                ->where(['index' => '[0-9]+']);
            
            Route::delete('/{documentId}', [DocumentController::class, 'destroy'])
                ->name('admin.property-units.documents.destroy')
                ->where(['documentId' => '[0-9]+']);
        });
        
        // Unit documents view
        Route::get('/documents', function($id) {
            $unit = App\Models\PropertyUnit::findOrFail($id);
            return view('admin.property_units.documents', compact('unit'));
        })->name('property-units.documents');
        
        // Payment history redirect
        Route::get('/payment-history', function($id) {
            return redirect()->route('admin.property-units.financials', $id)
                ->with('info', 'Payment history is available in the financials section.');
        })->name('property-units.payment-history');
        
        // Lease parameterized routes
        Route::prefix('leases/{leaseId}')->where(['leaseId' => '[0-9]+'])->group(function () {
            Route::get('/details', [PropertyUnitLeaseController::class, 'showLeaseDetails'])->name('property-units.lease-details');
            Route::get('/download-pdf', [PropertyUnitLeaseController::class, 'generateLeasePdf'])->name('property-units.lease-download-pdf');
            Route::post('/terminate', [PropertyUnitLeaseController::class, 'terminateLease'])->name('property-units.terminate-lease');
            
            Route::get('/landlord-sign', function($id, $leaseId) {
                return redirect()->route('admin.property-units.lease-details', [$id, $leaseId])
                    ->with('info', 'Only landlords can sign leases.');
            })->name('property-units.landlord-sign-lease');
        });
    });
    
    // ----- Document Routes -----
    Route::prefix('documents')->group(function () {
        Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
            ->name('admin.preview-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
        
        Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
            ->name('admin.download-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
    });
    
    // ----- Tenant Management Routes -----
    Route::prefix('tenants')->group(function () {
        Route::get('/', function() {
            $tenants = User::where('type', User::TYPE_TENANT)
                ->orderBy('name')
                ->paginate(20);
            
            return view('admin.tenants.index', compact('tenants'));
        })->name('tenants.index');
        
        Route::get('/{id}', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            return view('admin.tenants.show', compact('tenant'));
        })->name('tenants.show');
        
        Route::get('/{id}/financials', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            
            $unit = $tenant->propertyUnits()->first();
            
            if ($unit) {
                return redirect()->route('admin.property-units.financials', $unit->id);
            }
            
            return redirect()->route('admin.tenants.show', $tenant->id)
                ->with('error', 'Tenant is not currently assigned to any unit.');
        })->name('tenants.financials');
        
        Route::post('/{id}/send-message', function($id) {
            $tenant = User::where('type', User::TYPE_TENANT)->findOrFail($id);
            $user = auth()->user();
            
            $validated = request()->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
                'send_email' => 'nullable|boolean',
                'send_sms' => 'nullable|boolean',
            ]);
            
            try {
                $message = new \App\Models\Message([
                    'sender_id' => $user->id,
                    'receiver_id' => $tenant->id,
                    'subject' => $validated['subject'],
                    'content' => $validated['message'],
                    'type' => 'admin_to_tenant',
                    'status' => 'sent'
                ]);
                $message->save();
                
                return redirect()->route('admin.tenants.show', $tenant->id)
                    ->with('success', 'Message sent successfully.');
            } catch (\Exception $e) {
                \Log::error('Failed to send message: ' . $e->getMessage());
                return redirect()->route('admin.tenants.show', $tenant->id)
                    ->with('error', 'Failed to send message. Please try again.');
            }
        })->name('tenants.send-message');
    });
});

// ========== SUPER ADMIN ROUTES ==========
Route::middleware(['auth', 'multi.auth.user:0'])->prefix('super-admin')->name('super-admin.')->group(function () {
    
    // ----- Property Unit Routes -----
    Route::get('/property-units', [PropertyUnitController::class, 'index'])->name('property-units.index');
    Route::get('/property-units/bulk-selection', [PropertyUnitController::class, 'getUnitsForBulkSelection'])
        ->name('property-units.bulk-selection');
    
    // ----- Pending Approvals -----
    Route::get('/property-units/pending-approvals', [PropertyUnitTenantController::class, 'pendingApprovals'])
        ->name('property-units.pending-approvals');
    
    // ----- Bulk Approval -----
    Route::get('/property-units/bulk-approval', [PropertyUnitTenantController::class, 'showBulkApproval'])
        ->name('property-units.bulk-approval');
    
    // ----- Tenant Invitation Routes -----
    Route::get('/property-units/{unitId}/invitations/{invitationId}/status', [PropertyUnitTenantController::class, 'viewTenantInvitationStatus'])
        ->name('property-units.view-invitation-status')
        ->where(['unitId' => '[0-9]+', 'invitationId' => '[0-9]+']);
    
    // ----- Property Unit Parameterized Routes -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // PropertyUnitController routes
        Route::get('/', [PropertyUnitController::class, 'show'])->name('property-units.show');
        
        // PropertyUnitTenantController routes
        Route::get('/assignment-details', [PropertyUnitTenantController::class, 'viewAssignmentDetails'])->name('property-units.assignment-details');
        Route::get('/mark-vacated-form', [PropertyUnitTenantController::class, 'markVacatedForm'])->name('property-units.mark-vacated-form');
        Route::put('/mark-vacated', [PropertyUnitTenantController::class, 'markVacated'])->name('property-units.mark-vacated');
        Route::post('/terminate-tenant', [PropertyUnitTenantController::class, 'terminateTenant'])->name('property-units.terminate-tenant');
        Route::post('/approve-tenant', [PropertyUnitTenantController::class, 'approveTenant'])->name('property-units.approve-tenant');
        Route::post('/reject-tenant', [PropertyUnitTenantController::class, 'rejectTenant'])->name('property-units.reject-tenant');
        
        // PropertyUnitMaintenanceController routes
        Route::post('/create-maintenance-request', [PropertyUnitMaintenanceController::class, 'createMaintenanceRequest'])->name('property-units.create-maintenance-request');
        Route::post('/mark-maintenance-complete', [PropertyUnitMaintenanceController::class, 'markMaintenanceComplete'])->name('property-units.mark-maintenance-complete');
        Route::get('/maintenance-requests', [PropertyUnitMaintenanceController::class, 'showMaintenanceRequests'])->name('property-units.maintenance-requests');
        
        // PropertyUnitLeaseController routes
        Route::get('/lease-management', [PropertyUnitLeaseController::class, 'showLeaseManagement'])->name('property-units.lease-management');
        // ✅ FIXED: Super Admin-specific renew route (kept for backward compatibility)
        Route::post('/renew-lease', [PropertyUnitLeaseController::class, 'renewLease'])->name('property-units.renew-lease');
        
        // Document routes
        Route::prefix('documents')->group(function () {
            Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
                ->name('super-admin.property-units.documents.preview')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
                ->name('super-admin.property-units.documents.download')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/application/{index}/download', [DocumentController::class, 'downloadApplicationDocument'])
                ->name('super-admin.property-units.documents.download-application')
                ->where(['index' => '[0-9]+']);
            
            Route::delete('/{documentId}', [DocumentController::class, 'destroy'])
                ->name('super-admin.property-units.documents.destroy')
                ->where(['documentId' => '[0-9]+']);
        });
        
        // Lease parameterized routes
        Route::prefix('leases/{leaseId}')->where(['leaseId' => '[0-9]+'])->group(function () {
            Route::get('/details', [PropertyUnitLeaseController::class, 'showLeaseDetails'])->name('property-units.lease-details');
            Route::get('/download-pdf', [PropertyUnitLeaseController::class, 'generateLeasePdf'])->name('property-units.lease-download-pdf');
            Route::post('/terminate', [PropertyUnitLeaseController::class, 'terminateLease'])->name('property-units.terminate-lease');
            
            Route::get('/landlord-sign', function($id, $leaseId) {
                return redirect()->route('super-admin.property-units.lease-details', [$id, $leaseId])
                    ->with('info', 'Only landlords can sign leases.');
            })->name('property-units.landlord-sign-lease');
        });
    });
    
    // ----- Document Routes -----
    Route::prefix('documents')->group(function () {
        Route::get('/preview/{path}/{disk}', [DocumentController::class, 'preview'])
            ->name('super-admin.preview-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
        
        Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
            ->name('super-admin.download-document')
            ->where(['path' => '[a-zA-Z0-9+/=]+']);
    });
});

// ========== TENANT ROUTES ==========
Route::middleware(['auth', 'multi.auth.user:3'])->prefix('tenant')->name('tenant.')->group(function () {
    
    // ----- Dashboard -----
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    
    // ----- Property Unit Routes -----
    Route::get('/property-units/my-unit', [PropertyUnitTenantController::class, 'tenantUnitView'])
        ->name('property-units.my-unit');
    
    Route::get('/property-units/bulk-selection', function() {
        return redirect()->route('tenant.dashboard')
            ->with('info', 'Bulk selection is not available to tenants.');
    })->name('property-units.bulk-selection');
    
    // ----- Contact Landlord Routes -----
    Route::prefix('contact-landlord')->group(function () {
        Route::get('/', [PropertyUnitController::class, 'showContactLandlordForm'])
            ->name('contact-landlord.form');
        
        Route::post('/send', [PropertyUnitController::class, 'sendMessageToLandlord'])
            ->name('contact-landlord.send');
        
        Route::post('/', function() {
            return redirect()->route('tenant.contact-landlord.send');
        });
    });
    
    // ----- Property Unit Parameterized Routes -----
    Route::prefix('property-units/{id}')->where(['id' => '[0-9]+'])->group(function () {
        
        // PropertyUnitTenantController routes
        Route::get('/', [PropertyUnitTenantController::class, 'showForTenant'])->name('property-units.show');
        
        // PropertyUnitLeaseController routes
        Route::get('/lease-management', function($id) {
            $unit = App\Models\PropertyUnit::findOrFail($id);
            $user = auth()->user();
            
            if ($unit->tenant_id !== $user->id || $unit->tenant_status !== App\Models\PropertyUnit::TENANT_STATUS_APPROVED) {
                abort(403, 'You can only view lease management for your assigned unit.');
            }
            
            $activeLease = $unit->leases()->where('status', 'active')->first();
            
            if (!$activeLease) {
                return redirect()->route('tenant.property-units.show', $id)
                    ->with('info', 'No active lease found for this unit.');
            }
            
            return redirect()->route('tenant.property-units.lease-details', [$id, 'leaseId' => $activeLease->id]);
        })->name('property-units.lease-management');
        
        // PropertyUnitFinancialController routes
        Route::get('/financials', [PropertyUnitFinancialController::class, 'showFinancials'])->name('property-units.financials');
        
        // PropertyUnitMaintenanceController routes
        Route::post('/create-maintenance-request', [PropertyUnitMaintenanceController::class, 'createMaintenanceRequest'])->name('property-units.create-maintenance-request');
        Route::get('/maintenance-requests', [PropertyUnitMaintenanceController::class, 'showMaintenanceRequests'])->name('property-units.maintenance-requests');
        
        // Document routes
        Route::prefix('documents')->group(function () {
            Route::get('/download/{path}/{disk}', [DocumentController::class, 'download'])
                ->name('tenant.property-units.documents.download')
                ->where(['path' => '[a-zA-Z0-9+/=]+']);
            
            Route::get('/application/{index}/download', [DocumentController::class, 'downloadApplicationDocument'])
                ->name('tenant.property-units.documents.download-application')
                ->where(['index' => '[0-9]+']);
            
            Route::delete('/{documentId}', [DocumentController::class, 'destroy'])
                ->name('tenant.property-units.documents.destroy')
                ->where(['documentId' => '[0-9]+']);
        });
        
        // Unit documents view
        Route::get('/documents', function($id) {
            $unit = App\Models\PropertyUnit::findOrFail($id);
            $user = auth()->user();
            
            if ($unit->tenant_id !== $user->id || $unit->tenant_status !== App\Models\PropertyUnit::TENANT_STATUS_APPROVED) {
                abort(403, 'You can only view documents for your assigned unit.');
            }
            
            return view('tenant.property_units.documents', compact('unit'));
        })->name('property-units.documents');
        
        // Payment history redirect
        Route::get('/payment-history', function($id) {
            return redirect()->route('tenant.property-units.financials', $id)
                ->with('info', 'Payment history is available in the financials section.');
        })->name('property-units.payment-history');
        
        // Contact landlord routes
        Route::get('/contact-landlord', [PropertyUnitController::class, 'showContactLandlordForm'])
            ->name('property-units.contact-landlord.form');
        Route::post('/contact-landlord/send', [PropertyUnitController::class, 'sendMessageToLandlord'])
            ->name('property-units.contact-landlord.send');
        Route::post('/contact-landlord', function($id) {
            return redirect()->route('tenant.property-units.contact-landlord.send', $id);
        });
        
        // Lease parameterized routes
        Route::prefix('leases/{leaseId}')->where(['leaseId' => '[0-9]+'])->group(function () {
            Route::get('/details', [PropertyUnitLeaseController::class, 'showLeaseDetails'])->name('property-units.lease-details');
            Route::get('/download-pdf', [PropertyUnitLeaseController::class, 'generateLeasePdf'])->name('property-units.lease-download-pdf');
            Route::post('/tenant-sign', [PropertyUnitLeaseController::class, 'tenantSignLease'])->name('property-units.tenant-sign-lease');
        });
    });
    
    // ----- Profile Routes -----
    Route::prefix('profile')->group(function () {
        Route::get('/', function() {
            $user = auth()->user();
            return view('tenant.profile.show', compact('user'));
        })->name('profile.show');
        
        Route::get('/edit', function() {
            $user = auth()->user();
            return view('tenant.profile.edit', compact('user'));
        })->name('profile.edit');
        
        Route::put('/', function() {
            $user = auth()->user();
            $validated = request()->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'emergency_contact' => 'nullable|array',
                'emergency_contact.name' => 'required_with:emergency_contact|string|max:255',
                'emergency_contact.phone' => 'required_with:emergency_contact|string|max:20',
                'emergency_contact.relationship' => 'nullable|string|max:100',
            ]);
            
            $user->update($validated);
            
            return redirect()->route('tenant.profile.show')
                ->with('success', 'Profile updated successfully.');
        })->name('profile.update');
    });
});


/*
|--------------------------------------------------------------------------
| SMS Provider — Read-Only (Super-Admin + Developer)
|--------------------------------------------------------------------------
| Super-admins can view status, usage stats, and configuration details,
| but cannot write any credentials.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'multi.auth.user:0,5'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sms-providers', [SmsProviderController::class, 'index'])->name('sms-providers.index');
    Route::get('/sms-providers/status', [SmsProviderController::class, 'getProviderStatus'])->name('sms-providers.status');
    Route::get('/sms-providers/usage', [SmsProviderController::class, 'getUsageStatistics'])->name('sms-providers.usage');
    Route::get('/sms-providers/config', [SmsProviderController::class, 'getProviderConfig'])->name('sms-providers.config');
    Route::post('/sms-providers/verify-environment', [SmsProviderController::class, 'verifyEnvironment'])->name('sms-providers.verify-environment');
});

/*
|--------------------------------------------------------------------------
| SMS Provider — Write Access (Developer Only)
|--------------------------------------------------------------------------
| Every endpoint that mutates state or writes to .env is developer-only.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'multi.auth.user:5'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/sms-providers/configure', [SmsProviderController::class, 'configureProvider'])->name('sms-providers.configure');
    Route::post('/sms-providers/toggle', [SmsProviderController::class, 'toggleProvider'])->name('sms-providers.toggle');
    Route::post('/sms-providers/test-connection', [SmsProviderController::class, 'testConnection'])->name('sms-providers.test-connection');
    Route::post('/sms-providers/reset', [SmsProviderController::class, 'resetProvider'])->name('sms-providers.reset');
    Route::post('/sms-providers/cleanup-backups', [SmsProviderController::class, 'cleanupBackups'])->name('sms-providers.cleanup-backups');
    Route::post('/sms-providers/cleanup-logs', [SmsProviderController::class, 'cleanupOldLogs'])->name('sms-providers.cleanup-logs');
    Route::post('/sms-providers/send-test', [SmsProviderController::class, 'sendTestSMS'])->name('sms-providers.send-test');
    Route::post('/sms-providers/set-default', [SmsProviderController::class, 'setDefaultProvider'])->name('sms-providers.set-default');
    Route::post('/sms-providers/debug-arkesel', [SmsProviderController::class, 'debugArkeselApi'])->name('sms-providers.debug-arkesel');
    Route::post('/sms-providers/validate-config', [SmsProviderController::class, 'validateProviderConfiguration'])->name('sms-providers.validate-config');
});

/*
|--------------------------------------------------------------------------
| ✅ ENHANCED: Payment Provider Configuration Routes (Super Admin Only) - UPDATED
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0'])->prefix('admin')->name('admin.')->group(function () {
    // =============================================
    // MAIN PAYMENT PROVIDER ROUTES
    // =============================================
    
    // Payment Provider Dashboard
    Route::get('/payment-providers', [PaymentProviderController::class, 'index'])->name('payment-providers.index');
    
    // Configuration Management
    Route::post('/payment-providers/configure', [PaymentProviderController::class, 'configureProvider'])->name('payment-providers.configure');
    Route::post('/payment-providers/reset', [PaymentProviderController::class, 'resetProvider'])->name('payment-providers.reset');
    
    // =============================================
    // STATUS & MONITORING ROUTES (ENHANCED)
    // =============================================
    
    // Enhanced Status Endpoints
    Route::get('/payment-providers/status', [PaymentProviderController::class, 'getProviderStatus'])->name('payment-providers.status');
    
    // ✅ NEW: Immediate status endpoint for real-time polling
    Route::post('/payment-providers/immediate-status', [PaymentProviderController::class, 'getImmediateProviderStatus'])->name('payment-providers.immediate-status');
    
    // ✅ NEW: Update status for AJAX polling
    Route::get('/payment-providers/update-status', [PaymentProviderController::class, 'getUpdateStatus'])->name('payment-providers.update-status');
    
    // =============================================
    // CONNECTION TESTING ROUTES
    // =============================================
    
    // Connection Testing
    Route::post('/payment-providers/test-connection', [PaymentProviderController::class, 'testConnection'])->name('payment-providers.test-connection');
    
    // ✅ NEW: Environment verification
    Route::post('/payment-providers/verify-environment', [PaymentProviderController::class, 'verifyEnvironmentSwitch'])->name('payment-providers.verify-environment');
    
    // Backward compatibility
    Route::post('/payment-providers/test', [PaymentProviderController::class, 'testConnection'])->name('payment-providers.test');
    
    // =============================================
    // JOB & BACKGROUND PROCESSING ROUTES
    // =============================================
    
    // ✅ NEW: Job status checking
    Route::post('/payment-providers/check-job-status', [PaymentProviderController::class, 'checkJobStatus'])->name('payment-providers.check-job-status');
    
    // ✅ NEW: Manual retry for failed updates
    Route::post('/payment-providers/retry-update', [PaymentProviderController::class, 'retryPaymentUpdate'])->name('payment-providers.retry-update');
    
    // ✅ NEW: Check pending updates (for session-based fallback)
    Route::post('/payment-providers/check-pending-updates', [PaymentProviderController::class, 'checkPendingPaymentUpdates'])->name('payment-providers.check-pending-updates');
    
    // =============================================
    // STATE MANAGEMENT ROUTES (Debug & Testing)
    // =============================================
    
    // ✅ NEW: Clear provider states (for debugging/testing)
    Route::post('/payment-providers/clear-states', [PaymentProviderController::class, 'clearProviderStates'])->name('payment-providers.clear-states');
    
    // =============================================
    // CONFIGURATION & WEBHOOK ROUTES
    // =============================================
    
    // Configuration endpoints
    Route::get('/payment-providers/config', [PaymentProviderController::class, 'getProviderConfig'])->name('payment-providers.config');
    Route::get('/payment-providers/webhook-urls', [PaymentProviderController::class, 'getWebhookUrls'])->name('payment-providers.webhook-urls');
    
    // =============================================
    // MAINTENANCE & BACKUP ROUTES
    // =============================================
    
    // Backup management
    Route::post('/payment-providers/cleanup-backups', [PaymentProviderController::class, 'cleanupBackups'])->name('payment-providers.cleanup-backups');
});


/*
|--------------------------------------------------------------------------
| ✅ ENHANCED: Payment Provider Configuration Routes (Developer)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    
    // =============================================
    // MAIN VIEW ROUTES
    // =============================================
    
    Route::get('/payment-providers', [DeveloperPaymentProviderController::class, 'index'])
        ->name('payment-providers.index');
    
    Route::get('/payment-providers/{provider}', [DeveloperPaymentProviderController::class, 'show'])
        ->name('payment-providers.show');
    
    // =============================================
    // DEVELOPER CONFIGURATION ROUTES
    // =============================================
    
    Route::post('/payment-providers/configure', [DeveloperPaymentProviderController::class, 'configureDeveloperProvider'])
        ->name('payment-providers.configure');
    
    Route::post('/payment-providers/test-developer-connection', [DeveloperPaymentProviderController::class, 'testDeveloperConnection'])
        ->name('payment-providers.test-developer-connection');
    
    Route::post('/payment-providers/bill-admin', [DeveloperPaymentProviderController::class, 'billAdmin'])
        ->name('payment-providers.bill-admin');
    
    Route::get('/payment-providers/developer-config', [DeveloperPaymentProviderController::class, 'getDeveloperConfigJson'])
        ->name('payment-providers.developer-config');
    
    // =============================================
    // STATUS & MONITORING ROUTES
    // =============================================
    
    Route::get('/payment-providers/status', [DeveloperPaymentProviderController::class, 'getProviderStatus'])
        ->name('payment-providers.status');
    
    Route::post('/payment-providers/immediate-status', [DeveloperPaymentProviderController::class, 'getImmediateProviderStatus'])
        ->name('payment-providers.immediate-status');
    
    Route::get('/payment-providers/config', [DeveloperPaymentProviderController::class, 'getConfig'])
        ->name('payment-providers.config');
    
    Route::get('/payment-providers/webhook-urls', [DeveloperPaymentProviderController::class, 'getWebhookUrls'])
        ->name('payment-providers.webhook-urls');
    
    // =============================================
    // TESTING ROUTES
    // =============================================
    
    Route::post('/payment-providers/test-connection', [DeveloperPaymentProviderController::class, 'testConnection'])
        ->name('payment-providers.test-connection');
    
    // =============================================
    // DEBUG & DEVELOPMENT ROUTES
    // =============================================
    
    Route::get('/payment-providers/cache-status', [DeveloperPaymentProviderController::class, 'getCacheStatus'])
        ->name('payment-providers.cache-status');
    
    Route::get('/payment-providers/environment', [DeveloperPaymentProviderController::class, 'getEnvironmentInfo'])
        ->name('payment-providers.environment');
    
    Route::get('/payment-providers/performance', [DeveloperPaymentProviderController::class, 'getPerformanceMetrics'])
        ->name('payment-providers.performance');
    
    Route::post('/payment-providers/clear-cache', [DeveloperPaymentProviderController::class, 'clearCache'])
        ->name('payment-providers.clear-cache');
    
    // =============================================
    // LOGGING ROUTES
    // =============================================
    
    Route::get('/payment-providers/logs', [DeveloperPaymentProviderController::class, 'getLogs'])
        ->name('payment-providers.logs');
});

/*
|--------------------------------------------------------------------------
| ✅ UPDATED: System Settings Routes with Split Controllers
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0'])->prefix('admin')->name('admin.')->group(function () {
    
    // ========== MAIN SYSTEM SETTINGS ROUTES ==========
    Route::get('/system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
    Route::get('/system-settings/create', [SystemSettingController::class, 'create'])->name('system-settings.create');
    Route::post('/system-settings', [SystemSettingController::class, 'store'])->name('system-settings.store');
    Route::get('/system-settings/edit', [SystemSettingController::class, 'edit'])->name('system-settings.edit');
    Route::put('/system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::delete('/system-settings', [SystemSettingController::class, 'destroy'])->name('system-settings.destroy');
    Route::post('/system-settings/restore', [SystemSettingController::class, 'restore'])->name('system-settings.restore');

    // Background update status + retry (used by index.blade.php)
    Route::get('/system-settings/update-status', [SystemSettingController::class, 'updateStatus'])
        ->name('system-settings.update-status');

    Route::post('/system-settings/retry-env-update', [SystemSettingController::class, 'retryEnvUpdate'])
        ->name('system-settings.retry-env-update');

    // ========== SMS SENDER ID (ADMIN-EDITABLE ONLY) ==========
    // Whitelisted endpoint — plain admins can update ONLY the sender ID.
    // Developers and super-admins also hit this for the AJAX save path.
    // NOTE: This route MUST come BEFORE the catch-all PUT above if you
    // ever reorder; currently it sits in a separate path so ordering is
    // irrelevant. Kept explicit for future readers.
    Route::post('/system-settings/update-sender-id', [SystemSettingController::class, 'updateSenderId'])
        ->name('system-settings.update-sender-id');

    // ========== REGISTRATION CONTROL ROUTES ==========
    Route::prefix('system-settings/registration')->name('system-settings.registration.')->group(function () {
        Route::post('/toggle', [SystemSettingController::class, 'toggleRegistration'])->name('toggle');
        Route::get('/status', [SystemSettingController::class, 'getRegistrationStatus'])->name('status');
    });

    
    // ========== NOTIFICATION CHANNEL ROUTES ==========
    Route::prefix('notification-channels')->name('notification-channels.')->group(function () {
        // Main notification channel management
        Route::get('/', [NotificationChannelController::class, 'index'])->name('index');
        Route::get('/settings', [NotificationChannelController::class, 'getSettings'])->name('settings');
        Route::post('/update', [NotificationChannelController::class, 'updateSettings'])->name('update');
        
        // SMS specific routes (using existing SMS service)
        Route::prefix('sms')->name('sms.')->group(function () {
            Route::get('/status', [NotificationChannelController::class, 'getSmsStatus'])->name('status');
            Route::post('/test', [NotificationChannelController::class, 'testSms'])->name('test');
            Route::post('/toggle', [NotificationChannelController::class, 'toggleSms'])->name('toggle');
            Route::get('/rate-limits', [NotificationChannelController::class, 'getSmsRateLimits'])->name('rate-limits');
            Route::post('/rate-limits/update', [NotificationChannelController::class, 'updateSmsRateLimits'])->name('rate-limits.update');
            Route::get('/templates', [NotificationChannelController::class, 'getSmsTemplates'])->name('templates');
            Route::post('/templates/update', [NotificationChannelController::class, 'updateSmsTemplates'])->name('templates.update');
        });
        
        // WhatsApp specific routes
        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/status', [NotificationChannelController::class, 'getWhatsAppStatus'])->name('status');
            Route::post('/test', [NotificationChannelController::class, 'testWhatsApp'])->name('test');
            Route::post('/toggle', [NotificationChannelController::class, 'toggleWhatsApp'])->name('toggle');
            Route::get('/templates', [NotificationChannelController::class, 'getWhatsAppTemplates'])->name('templates');
            Route::post('/templates/update', [NotificationChannelController::class, 'updateWhatsAppTemplates'])->name('templates.update');
        });
        
        // Test notification channels
        Route::post('/test', [NotificationChannelController::class, 'testNotificationChannels'])->name('test');
        Route::post('/test-all', [NotificationChannelController::class, 'testAllChannels'])->name('test-all');
        
        // Bulk notification routes
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::get('/config', [NotificationChannelController::class, 'getBulkNotificationConfig'])->name('config');
            Route::post('/config/update', [NotificationChannelController::class, 'updateBulkNotificationConfig'])->name('config.update');
            Route::post('/send', [NotificationChannelController::class, 'sendBulkNotifications'])->name('send');
            Route::get('/status/{jobId}', [NotificationChannelController::class, 'getBulkNotificationStatus'])->name('status');
        });
        
        // Notification logs
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::get('/', [NotificationChannelController::class, 'getNotificationLogs'])->name('index');
            Route::get('/export', [NotificationChannelController::class, 'exportNotificationLogs'])->name('export');
            Route::delete('/cleanup', [NotificationChannelController::class, 'cleanupNotificationLogs'])->name('cleanup');
            Route::get('/stats', [NotificationChannelController::class, 'getNotificationStats'])->name('stats');
        });
        
        // User notification preferences
        Route::prefix('preferences')->name('preferences.')->group(function () {
            Route::get('/user/{userId}', [NotificationChannelController::class, 'getUserPreferences'])->name('user');
            Route::post('/user/{userId}', [NotificationChannelController::class, 'updateUserPreferences'])->name('user.update');
            Route::post('/bulk-update', [NotificationChannelController::class, 'bulkUpdateUserPreferences'])->name('bulk-update');
            Route::post('/reset/{userId}', [NotificationChannelController::class, 'resetUserPreferences'])->name('user.reset');
        });
        
        // System health and diagnostics
        Route::prefix('health')->name('health.')->group(function () {
            Route::get('/system', [NotificationChannelController::class, 'getSystemHealth'])->name('system');
            Route::get('/diagnostics', [NotificationChannelController::class, 'runDiagnostics'])->name('diagnostics');
            Route::post('/retry-failed', [NotificationChannelController::class, 'retryFailedNotifications'])->name('retry-failed');
        });
    });
    
    // ========== EMAIL CONFIGURATION ROUTES ==========
    Route::prefix('email-config')->name('email-config.')->group(function () {
        Route::get('/status', [EmailConfigController::class, 'getEmailConfigurationStatus'])->name('status');
        Route::post('/test-sending', [EmailConfigController::class, 'testEmailSending'])->name('test-sending');
        Route::post('/update', [EmailConfigController::class, 'updateEmailConfiguration'])->name('update');
        Route::post('/test-env', [EmailConfigController::class, 'testEnvConfiguration'])->name('test-env');
        Route::get('/env-config', [EmailConfigController::class, 'getEnvMailConfiguration'])->name('env-config');
        Route::post('/update-env', [EmailConfigController::class, 'updateEnvMailConfiguration'])->name('update-env');
        Route::get('/test-smtp', [EmailConfigController::class, 'testSmtpConnection'])->name('test-smtp');
        Route::get('/retry-env-update', [EmailConfigController::class, 'retryEnvUpdate'])->name('retry-env-update');
        Route::get('/templates', [EmailConfigController::class, 'getEmailTemplates'])->name('templates');
        Route::post('/templates/update', [EmailConfigController::class, 'updateEmailTemplates'])->name('templates.update');
    });
    
    // ========== WHATSAPP CONFIGURATION ROUTES ==========
    Route::prefix('whatsapp-config')->name('whatsapp-config.')->group(function () {
        Route::get('/status', [WhatsAppConfigController::class, 'getWhatsAppConfigurationStatus'])->name('status');
        Route::post('/test', [WhatsAppConfigController::class, 'testWhatsAppConfiguration'])->name('test');
        Route::get('/config', [WhatsAppConfigController::class, 'getWhatsAppConfiguration'])->name('config');
        Route::post('/update', [WhatsAppConfigController::class, 'updateWhatsAppConfiguration'])->name('update');
        Route::get('/retry-update', [WhatsAppConfigController::class, 'retryWhatsAppUpdate'])->name('retry-update');
        Route::get('/templates', [WhatsAppConfigController::class, 'getWhatsAppTemplates'])->name('templates');
        Route::post('/templates/update', [WhatsAppConfigController::class, 'updateWhatsAppTemplates'])->name('templates.update');
        Route::post('/test-message', [WhatsAppConfigController::class, 'sendTestWhatsAppMessage'])->name('test-message');
        Route::get('/providers', [WhatsAppConfigController::class, 'getWhatsAppProviders'])->name('providers');
        Route::post('/providers/toggle', [WhatsAppConfigController::class, 'toggleWhatsAppProvider'])->name('providers.toggle');
    });
    
    // ========== PAYMENT CONFIGURATION ROUTES ==========
    Route::prefix('payment-config')->name('payment-config.')->group(function () {
        Route::get('/configuration', [PaymentConfigController::class, 'getPaymentConfiguration'])->name('configuration');
        Route::get('/available-methods', [PaymentConfigController::class, 'getAvailablePaymentMethods'])->name('available-methods');
        Route::post('/sync-providers', [PaymentConfigController::class, 'syncPaymentProviders'])->name('sync-providers');
        Route::post('/update-recipient', [PaymentConfigController::class, 'updatePaymentRecipient'])->name('update-recipient');
        Route::post('/update-bank', [PaymentConfigController::class, 'updateBankDetails'])->name('update-bank');
        Route::post('/update-instructions', [PaymentConfigController::class, 'updatePaymentInstructions'])->name('update-instructions');
        Route::post('/test-connection', [PaymentConfigController::class, 'testPaymentConnection'])->name('test-connection');
    });
    
    // ========== LOGO MANAGEMENT ROUTES ==========
    Route::prefix('logo')->name('logo.')->group(function () {
        Route::post('/upload', [LogoController::class, 'uploadLogo'])->name('upload');
        Route::delete('/remove', [LogoController::class, 'removeLogo'])->name('remove');
        Route::get('/preview', [LogoController::class, 'previewLogo'])->name('preview');
    });
    
    // ========== SYSTEM INFORMATION API ROUTES ==========
    Route::prefix('system-info')->name('system-info.')->group(function () {
        Route::get('/info', [SystemInfoController::class, 'getSystemInfo'])->name('info');
        Route::post('/calculate-dues', [SystemInfoController::class, 'calculateDuesApi'])->name('calculate-dues');
        Route::get('/bulk-payment-enabled', [SystemInfoController::class, 'checkBulkPaymentEnabled'])->name('bulk-payment-enabled');
        Route::get('/invoice-settings', [SystemInfoController::class, 'getInvoiceSettings'])->name('invoice-settings');
        Route::get('/notification-settings', [SystemInfoController::class, 'getNotificationSettings'])->name('notification-settings');
        Route::get('/system-health', [SystemInfoController::class, 'getSystemHealth'])->name('system-health');
        Route::get('/environment', [SystemInfoController::class, 'getEnvironmentInfo'])->name('environment');
    });
    
    // ========== INVOICE SETTINGS ROUTES ==========
    Route::prefix('invoice-settings')->name('invoice-settings.')->group(function () {
        Route::post('/toggle-auto-generation', [SystemSettingController::class, 'toggleAutoInvoiceGeneration'])->name('toggle-auto-generation');
        Route::post('/update-reminder-settings', [SystemSettingController::class, 'updateReminderSettings'])->name('update-reminder-settings');
        Route::get('/status', [SystemSettingController::class, 'getInvoiceSettings'])->name('status');
        Route::post('/validate', [SystemSettingController::class, 'validateInvoiceSettings'])->name('validate');
        Route::post('/test-generation', [SystemSettingController::class, 'testInvoiceGeneration'])->name('test-generation');
        Route::get('/generation-summary', [SystemSettingController::class, 'getGenerationSummary'])->name('generation-summary');
    });
    
    // ========== BULK PAYMENT SETTINGS ROUTES ==========
    Route::prefix('bulk-payment-settings')->name('bulk-payment-settings.')->group(function () {
        Route::get('/config', [SystemSettingController::class, 'getBulkPaymentSettings'])->name('config');
        Route::post('/update', [SystemSettingController::class, 'updateBulkPaymentSettings'])->name('update');
        Route::get('/options', [SystemSettingController::class, 'getBulkPaymentOptions'])->name('options');
        Route::post('/calculate', [SystemSettingController::class, 'calculateBulkPaymentAmount'])->name('calculate');
    });
    
    // ========== BACKWARD COMPATIBILITY (Keep for existing links) ==========
    Route::post('/system-settings/retry-env-update', [EmailConfigController::class, 'retryEnvUpdate'])->name('system-settings.retry-env-update');
    Route::get('/system-settings/check-env-update', [EmailConfigController::class, 'checkPendingEnvUpdates'])->name('system-settings.check-env-update');
    Route::get('/system-settings/whatsapp-config-status', [WhatsAppConfigController::class, 'getWhatsAppConfigurationStatus'])->name('system-settings.whatsapp-config-status');
    Route::post('/system-settings/test-whatsapp-config', [WhatsAppConfigController::class, 'testWhatsAppConfiguration'])->name('system-settings.test-whatsapp-config');
    Route::post('/system-settings/test-whatsapp', [WhatsAppConfigController::class, 'testWhatsAppConfiguration'])->name('system-settings.test-whatsapp');
    Route::get('/system-settings/get-whatsapp-config', [WhatsAppConfigController::class, 'getWhatsAppConfiguration'])->name('system-settings.get-whatsapp-config');
    Route::post('/system-settings/retry-whatsapp-update', [WhatsAppConfigController::class, 'retryWhatsAppUpdate'])->name('system-settings.retry-whatsapp-update');
    Route::get('/system-settings/check-whatsapp-update', [WhatsAppConfigController::class, 'checkPendingWhatsAppUpdates'])->name('system-settings.check-whatsapp-update');
    Route::post('/system-settings/upload-logo', [LogoController::class, 'uploadLogo'])->name('system-settings.upload-logo');
    Route::delete('/system-settings/remove-logo', [LogoController::class, 'removeLogo'])->name('system-settings.remove-logo');
    Route::get('/system-settings/system-info', [SystemInfoController::class, 'getSystemInfo'])->name('system-settings.system-info');
    Route::post('/system-settings/calculate-dues', [SystemInfoController::class, 'calculateDuesApi'])->name('system-settings.calculate-dues');
    Route::get('/system-settings/bulk-payment-enabled', [SystemInfoController::class, 'checkBulkPaymentEnabled'])->name('system-settings.bulk-payment-enabled');
    Route::get('/system-settings/payment-configuration', [PaymentConfigController::class, 'getPaymentConfiguration'])->name('system-settings.payment-configuration');
    Route::get('/system-settings/available-payment-methods', [PaymentConfigController::class, 'getAvailablePaymentMethods'])->name('system-settings.available-payment-methods');
    Route::post('/system-settings/sync-payment-providers', [PaymentConfigController::class, 'syncPaymentProviders'])->name('system-settings.sync-payment-providers');
    Route::post('/system-settings/update-payment-recipient', [PaymentConfigController::class, 'updatePaymentRecipient'])->name('system-settings.update-payment-recipient');
    Route::post('/system-settings/test-env-config', [EmailConfigController::class, 'testEnvConfiguration'])->name('system-settings.test-env-config');
    Route::get('/system-settings/get-env-config', [EmailConfigController::class, 'getEnvMailConfiguration'])->name('system-settings.get-env-config');
    Route::post('/system-settings/update-env-config', [EmailConfigController::class, 'updateEnvMailConfiguration'])->name('system-settings.update-env-config');
    Route::post('/system-settings/test-email', [EmailConfigController::class, 'testEmailSending'])->name('system-settings.test-email');
    Route::get('/system-settings/email-config-status', [EmailConfigController::class, 'getEmailConfigurationStatus'])->name('system-settings.email-config-status');
    Route::post('/system-settings/update-email-config', [EmailConfigController::class, 'updateEmailConfiguration'])->name('system-settings.update-email-config');
    Route::post('/system-settings/test-smtp', [EmailConfigController::class, 'testSmtpConnection'])->name('system-settings.test-smtp');
    Route::post('/system-settings/toggle-registration', [SystemSettingController::class, 'toggleRegistration'])->name('system-settings.toggle-registration');
    Route::get('/system-settings/registration-status', [SystemSettingController::class, 'getRegistrationStatus'])->name('system-settings.registration-status');
    
    // ========== NOTIFICATION CHANNEL BACKWARD COMPATIBILITY ==========
    Route::prefix('system-settings')->name('system-settings.')->group(function () {
        Route::get('/notification-channels', [NotificationChannelController::class, 'index'])->name('notification-channels');
        Route::get('/notification-settings', [NotificationChannelController::class, 'getSettings'])->name('notification-settings');
        Route::post('/update-notification-channels', [NotificationChannelController::class, 'updateSettings'])->name('update-notification-channels');
        Route::post('/test-notification-channels', [NotificationChannelController::class, 'testNotificationChannels'])->name('test-notification-channels');
        Route::get('/sms-status', [NotificationChannelController::class, 'getSmsStatus'])->name('sms-status');
        Route::post('/test-sms', [NotificationChannelController::class, 'testSms'])->name('test-sms');
    });
});

/*
|--------------------------------------------------------------------------
| Invoice Management Routes - COMPLETE WITH ALL ROUTES (PROPERLY ORDERED)
|--------------------------------------------------------------------------
|
*/

// ==================== ADMIN INVOICE MANAGEMENT ROUTES ====================
// Routes for Super Admin (type: 0) and Admin (type: 1)
Route::middleware(['auth', 'multi.auth.user:0,1'])->group(function () {
    
    // ---------- STATIC ROUTES (NO PARAMETERS) - MUST COME FIRST ----------
    
    // Trash Management
    Route::get('/invoices/trash', [InvoiceController::class, 'trash'])->name('invoices.trash');
    
    // ========== NEW ARCHIVE MANAGEMENT ROUTES ==========
    // Archive Management
    Route::get('/invoices/archives', [InvoiceController::class, 'archives'])->name('invoices.archives');
    Route::get('/invoices/archives/export-all', [InvoiceController::class, 'exportAllArchives'])->name('invoices.archives.export-all');
    Route::get('/invoices/archives/cleanup', [InvoiceController::class, 'archiveCleanup'])->name('invoices.archives.cleanup');
    Route::post('/invoices/archives/perform-cleanup', [InvoiceController::class, 'performArchiveCleanup'])->name('invoices.archives.perform-cleanup');
    Route::get('/invoices/archives/preview-cleanup', [InvoiceController::class, 'previewCleanup'])->name('invoices.archives.preview-cleanup');
    
    // Bulk delete archives (for selection cleanup)
    Route::post('/invoices/archives/bulk-delete', [InvoiceController::class, 'bulkDeleteArchives'])->name('invoices.archives.bulk-delete');
    
    // Single Archive Operations (with {id} parameter)
    Route::get('/invoices/archives/{id}/pdf', [InvoiceController::class, 'exportArchivePDF'])->name('invoices.archives.pdf');
    Route::get('/invoices/archives/{id}/json', [InvoiceController::class, 'exportArchive'])->name('invoices.archives.json');
    Route::get('/invoices/archives/{id}/details', [InvoiceController::class, 'getArchiveDetails'])->name('invoices.archives.details');
    
    // Archive Log Management
    Route::delete('/invoices/archives/logs/cleanup', [InvoiceController::class, 'cleanupOldLogs'])->name('invoices.archives.cleanup-logs');
    Route::delete('/invoices/archives/logs/selected', [InvoiceController::class, 'deleteSelectedLogs'])->name('invoices.archives.delete-selected-logs');
    Route::get('/invoices/archives/logs/preview', [InvoiceController::class, 'previewLogCleanup'])->name('invoices.archives.preview-logs');
    Route::get('/invoices/archives/logs/all', [InvoiceController::class, 'getAllRecordsForCleanup'])->name('invoices.archives.all-records');
    
    // Archive Export for Cleanup
    Route::post('/invoices/archives/export-for-cleanup', [InvoiceController::class, 'exportArchivesForCleanup'])->name('invoices.archives.export-for-cleanup');
    
    // ========== YEAR-END ARCHIVE ROUTES ==========
    Route::get('/invoices/year-end/management', [InvoiceController::class, 'yearEndManagement'])->name('invoices.year-end.management');
    Route::get('/invoices/year-end/statistics/{year?}', [InvoiceController::class, 'getYearEndStatistics'])->name('invoices.year-end.statistics');
    Route::post('/invoices/year-end/process', [InvoiceController::class, 'processYearEndArchive'])->name('invoices.year-end.process');
    Route::post('/invoices/year-end/send-reminders', [InvoiceController::class, 'sendYearEndReminders'])->name('invoices.year-end.send-reminders');
    
    // Unpaid Invoices from Previous Years
    Route::get('/invoices/unpaid-previous-years', [InvoiceController::class, 'unpaidFromPreviousYears'])->name('invoices.unpaid-previous-years');
    Route::post('/invoices/send-reminder/{invoice}', [InvoiceController::class, 'sendReminder'])->name('invoices.send-reminder');
    Route::post('/invoices/bulk-send-reminders', [InvoiceController::class, 'bulkSendReminders'])->name('invoices.bulk-send-reminders');
    Route::get('/invoices/export-unpaid', [InvoiceController::class, 'exportUnpaidInvoices'])->name('invoices.export-unpaid');
    Route::post('/invoices/export-selected', [InvoiceController::class, 'exportSelectedInvoices'])->name('invoices.export-selected');
    
    // Post-Payment Archiving
    Route::post('/invoices/post-payment-archive', [InvoiceController::class, 'processPostPaymentArchive'])->name('invoices.post-payment-archive');
    Route::get('/invoices/post-payment-preview', [InvoiceController::class, 'previewPostPaymentArchive'])->name('invoices.post-payment-preview');
    
    // ========== TENANT YEAR-END ARCHIVE ROUTES (if needed) ==========
    Route::get('/tenant-invoices/year-end/management', [TenantInvoiceController::class, 'yearEndManagement'])->name('tenant-invoices.year-end.management');
    Route::get('/tenant-invoices/year-end/statistics/{year?}', [TenantInvoiceController::class, 'getYearEndStatistics'])->name('tenant-invoices.year-end.statistics');
    Route::post('/tenant-invoices/year-end/process', [TenantInvoiceController::class, 'processYearEndArchive'])->name('tenant-invoices.year-end.process');
    Route::post('/tenant-invoices/year-end/send-reminders', [TenantInvoiceController::class, 'sendYearEndReminders'])->name('tenant-invoices.year-end.send-reminders');
    
    // PDF Export Routes (GET)
    Route::get('/invoices/export-current-page', [InvoiceController::class, 'exportCurrentPage'])->name('invoices.export-current-page');
    Route::get('/invoices/export-all-filtered', [InvoiceController::class, 'exportAllFiltered'])->name('invoices.export-all-filtered');
    Route::post('/invoices/bulk-export', [InvoiceController::class, 'bulkExport'])->name('invoices.bulk-export');
    
    // Admin PDF Export for single invoice
    Route::get('/invoices/{invoice}/export-admin-pdf', [InvoiceController::class, 'exportAdminInvoicePdf'])->name('invoices.export-admin-pdf');
    Route::post('/invoices/bulk-export-admin-pdf', [InvoiceController::class, 'bulkExportAdminPdf'])->name('invoices.bulk-export-admin-pdf');
    Route::get('/invoices/export-current-page-pdf', [InvoiceController::class, 'exportCurrentPagePdf'])->name('invoices.export-current-page-pdf');
    Route::get('/invoices/export-all-filtered-pdf', [InvoiceController::class, 'exportAllFilteredPdf'])->name('invoices.export-all-filtered-pdf');
    Route::post('/invoices/bulk-print', [InvoiceController::class, 'bulkPrintAdmin'])->name('invoices.bulk-print');
    
    // Statistics & Reports
    Route::get('/invoices/statistics', [InvoiceController::class, 'getStatistics'])->name('invoices.statistics');
    Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/generation-summary', [InvoiceController::class, 'getGenerationSummary'])->name('invoices.generation-summary');
    Route::get('/invoices/auto-generation-status', [InvoiceController::class, 'getAutoGenerationStatus'])->name('invoices.auto-generation-status');
    Route::get('/invoices/settings', [InvoiceController::class, 'getSettings'])->name('invoices.settings');
    
    // Bulk Coverage (static)
    Route::get('/invoices/check-bulk-coverage', [InvoiceController::class, 'checkBulkCoverage'])->name('invoices.check-bulk-coverage');
    
    // ---------- CREATE & GENERATION ROUTES ----------
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices/generate-manual', [InvoiceController::class, 'generateManual'])->name('invoices.generate-manual');
    Route::post('/invoices/generate-monthly', [InvoiceController::class, 'generateMonthlyInvoices'])->name('invoices.generate-monthly');
    Route::post('/invoices/manual-generate', [InvoiceController::class, 'manualGenerateInvoices'])->name('invoices.manual-generate');
    Route::post('/invoices/test-calculation', [InvoiceController::class, 'testCalculation'])->name('invoices.test-calculation');
    
    // ---------- BULK OPERATIONS (NO PARAMETERS) ----------
    Route::post('/invoices/bulk-mark-paid', [InvoiceController::class, 'bulkMarkPaid'])->name('invoices.bulk-mark-paid');
    Route::post('/invoices/bulk-update-status', [InvoiceController::class, 'bulkUpdateStatus'])->name('invoices.bulk-update-status');
    Route::post('/invoices/trash/bulk-restore', [InvoiceController::class, 'bulkRestore'])->name('invoices.bulk-restore');
    Route::delete('/invoices/trash/bulk-force-delete', [InvoiceController::class, 'bulkForceDelete'])->name('invoices.bulk-force-delete');
    Route::delete('/invoices/trash/empty', [InvoiceController::class, 'emptyTrash'])->name('invoices.empty-trash');
    
    // ---------- STATUS MANAGEMENT ----------
    Route::post('/invoices/mark-overdue', [InvoiceController::class, 'markOverdueInvoices'])->name('invoices.mark-overdue');
    
    // ---------- SYSTEM SETTINGS ----------
    Route::post('/invoices/toggle-auto-generation', [InvoiceController::class, 'toggleAutoGeneration'])->name('invoices.toggle-auto-generation');
    Route::post('/invoices/update-reminder-settings', [InvoiceController::class, 'updateReminderSettings'])->name('invoices.update-reminder-settings');
    
    // ---------- PARAMETERIZED ROUTES (WITH {invoice}) - COMES AFTER STATIC ROUTES ----------
    
    // Read operations
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    
    // Single PDF Export
    Route::get('/invoices/{invoice}/export-pdf', [InvoiceController::class, 'exportSinglePdf'])->name('invoices.export-pdf');
    
    // Delete eligibility check
    Route::get('/invoices/{invoice}/can-delete', [InvoiceController::class, 'checkCanDelete'])->name('invoices.can-delete');
    
    // Coverage info
    Route::get('/invoices/{invoice}/coverage-info', [InvoiceController::class, 'getCoverageInfo'])->name('invoices.coverage-info');
    
    // Status updates
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');
    
    // Penalty management
    Route::post('/invoices/{invoice}/apply-penalty', [InvoiceController::class, 'applyPenalty'])->name('invoices.apply-penalty');
    Route::post('/invoices/{invoice}/remove-penalty', [InvoiceController::class, 'removePenalty'])->name('invoices.remove-penalty');
    
    // Notification management
    Route::post('/invoices/{invoice}/resend-notification', [InvoiceController::class, 'resendNotification'])->name('invoices.resend-notification');
    
    // Update & Delete
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    
    // Consolidation management
    Route::post('/invoices/{bulkInvoice}/reverse-consolidation', [InvoiceController::class, 'reverseConsolidation'])->name('invoices.reverse-consolidation');
    
    // Archive info (for single invoice)
    Route::get('/invoices/{invoice}/archive-info', [InvoiceController::class, 'getArchiveInfo'])->name('invoices.archive-info');
    
    // Eligible for year-end archive
    Route::get('/invoices/eligible-for-year-end/{year}', [InvoiceController::class, 'getEligibleForYearEndArchive'])->name('invoices.eligible-for-year-end');
    
    // ---------- PARAMETERIZED ROUTES WITH ID (FOR TRASH OPERATIONS) ----------
    Route::post('/invoices/trash/restore/{id}', [InvoiceController::class, 'restore'])->name('invoices.restore');
    Route::delete('/invoices/trash/force-delete/{id}', [InvoiceController::class, 'forceDelete'])->name('invoices.force-delete');
    
    // ---------- PARAMETERIZED ROUTES WITH PROPERTY ----------
    Route::get('/properties/{property}/bulk-coverage-summary', [InvoiceController::class, 'getBulkCoverageSummary'])->name('invoices.bulk-coverage-summary');
    
    // ---------- INDEX ROUTE (MUST BE LAST) ----------
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
});

// ==================== LANDLORD INVOICE MANAGEMENT ROUTES ====================
// Routes for Landlord (type: 2)
Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    
    // ---------- STATIC ROUTES (NO PARAMETERS) - MUST COME FIRST ----------
    
    // Dashboard & Statistics
    Route::get('/dashboard/statistics', [InvoiceController::class, 'getLandlordStatistics'])->name('dashboard.statistics');
    
    // PDF Export Routes (GET)
    Route::get('/invoices/export-current-page', [InvoiceController::class, 'landlordExportCurrentPage'])->name('invoices.export-current-page');
    Route::get('/invoices/export-all', [InvoiceController::class, 'landlordExportAllInvoices'])->name('invoices.export-all');
    Route::post('/invoices/bulk-export', [InvoiceController::class, 'landlordBulkExport'])->name('invoices.bulk-export');
    
    // Payment Form
    Route::get('/invoices/payment/form', [InvoiceController::class, 'showPaymentForm'])->name('invoices.payment.form');
    
    // Payment Confirmation
    Route::get('/payments/confirmation/{transactionId}', [PaymentController::class, 'showConfirmation'])->name('payments.confirmation');
    Route::post('/payments/failed', [PaymentController::class, 'handleFailedPayment'])->name('payments.failed');
    
    // API Routes (no parameters)
    Route::get('/outstanding-invoices', [InvoiceController::class, 'getOutstandingInvoices'])->name('outstanding-invoices');
    Route::get('/outstanding-summary', [InvoiceController::class, 'getLandlordOutstandingSummary'])->name('outstanding-summary');
    Route::get('/coverage-summary', [InvoiceController::class, 'getLandlordCoverageSummary'])->name('coverage-summary');
    Route::post('/payment-summary', [InvoiceController::class, 'getPaymentSummary'])->name('payment-summary');
    Route::get('/check-bulk-coverage', [InvoiceController::class, 'checkBulkCoverage'])->name('check-bulk-coverage');
    
    // ---------- BULK PAYMENT ROUTES ----------
    Route::post('/create-bulk-payment', [InvoiceController::class, 'createBulkPayment'])->name('create-bulk-payment');
    Route::post('/bulk-payments/create', [InvoiceController::class, 'createBulkPayment'])->name('bulk-payments.create');
    
    // ---------- PARAMETERIZED ROUTES (WITH {invoice} or {property}) ----------
    
    // Bulk payment options (property parameter)
    Route::get('/properties/{property}/bulk-payment-options', [InvoiceController::class, 'getBulkPaymentOptions'])->name('bulk-payment-options');
    Route::get('/properties/{property}/coverage-summary', [InvoiceController::class, 'getBulkCoverageSummary'])->name('coverage-summary');
    
    // Bulk payment processing (invoice parameter)
    Route::post('/bulk-payments/{bulkInvoice}/process', [InvoiceController::class, 'processBulkPayment'])->name('bulk-payments.process');
    
    // Payment processing (POST)
    Route::post('/payments/process', [InvoiceController::class, 'processPayment'])->name('payments.process');
    Route::post('/invoices/process-payment', [InvoiceController::class, 'processPayment'])->name('invoices.process-payment');
    
    // Invoice status check (invoice parameter)
    Route::get('/invoices/{invoice}/status', [InvoiceController::class, 'getLandlordInvoiceStatus'])->name('invoices.status');
    Route::get('/invoices/{invoice}/notification-status', [InvoiceController::class, 'getLandlordInvoiceNotificationStatus'])->name('invoices.notification-status');
    
    // Invoice view and print (invoice parameter)
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'showLandlordInvoice'])->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'printLandlordInvoice'])->name('invoices.print');
    
    // Single PDF Export for landlord
    Route::get('/invoices/{invoice}/export-pdf', [InvoiceController::class, 'landlordExportSinglePdf'])->name('invoices.export-pdf');
    
    // ---------- INDEX ROUTE (MUST BE LAST) ----------
    Route::get('/invoices', [InvoiceController::class, 'landlordInvoices'])->name('invoices');
});

// ==================== SHARED/GENERIC INVOICE ROUTES ====================
// Routes accessible by multiple user types (with authorization checks inside controllers)
Route::middleware(['auth'])->group(function () {
    
    // Payment summary API (accessible to all authenticated users)
    Route::post('/payment-summary', [InvoiceController::class, 'getPaymentSummary'])->name('payment-summary');
    
    // Outstanding invoices API (with middleware check inside controller)
    Route::get('/outstanding-invoices', [InvoiceController::class, 'getOutstandingInvoices'])->name('outstanding-invoices');
    
    // Bulk coverage check (accessible to both admin and landlord with property ownership check)
    Route::get('/check-bulk-coverage', [InvoiceController::class, 'checkBulkCoverage'])->name('check-bulk-coverage');
});

// ==================== WEBHOOK ROUTES (NO AUTHENTICATION) ====================
// Payment provider webhooks (no auth required)
Route::prefix('webhook')->name('webhook.')->group(function () {
    
    // =============================================
    // GENERIC WEBHOOK HANDLER (Supports all providers)
    // =============================================
    Route::post('/payment/{provider}', [PaymentWebhookController::class, 'handle'])->name('payment');
    
    // =============================================
    // PROVIDER-SPECIFIC WEBHOOK ROUTES
    // =============================================
    
    // Paystack Webhook
    Route::post('/paystack', [PaymentWebhookController::class, 'handlePaystack'])->name('paystack');
    
    // ExpressPay Webhook
    Route::post('/expresspay', [PaymentWebhookController::class, 'handleExpressPay'])->name('expresspay');
    
    // Hubtel Webhook
    Route::post('/hubtel', [PaymentWebhookController::class, 'handleHubtel'])->name('hubtel');
    
    // Flutterwave Webhook
    Route::post('/flutterwave', [PaymentWebhookController::class, 'handleFlutterwave'])->name('flutterwave');
});


// ==================== TENANT INVOICE ROUTES ====================
// Routes for Tenant (type: 3)
Route::middleware(['auth', 'multi.auth.user:3'])->prefix('tenant')->name('tenant.')->group(function () {
    
    // Tenant Invoice Management
    Route::get('/invoices', [TenantInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [TenantInvoiceController::class, 'print'])->name('invoices.print');
    Route::get('/invoices/{invoice}/export-pdf', [TenantInvoiceController::class, 'exportPdf'])->name('invoices.export-pdf');
    
    // =============================================
    // ✅ TENANT PAYMENT ROUTES - WITH ALL CALLBACKS
    // =============================================
    
    // Payment Form & Processing
    Route::get('/payments/create', [TenantPaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments/process', [TenantPaymentController::class, 'process'])->name('payments.process');
    Route::get('/payments/history', [TenantPaymentController::class, 'history'])->name('payments.history');
    
    // =============================================
    // ✅ CRITICAL: PAYMENT CALLBACK ROUTES - FIXES THE ERROR
    // =============================================
    
    // Generic callback route (supports all providers)
    Route::get('/payments/callback/{provider}', [TenantPaymentController::class, 'handleCallback'])->name('payments.callback');
    
    // Generic callback without provider (fallback)
    Route::get('/payments/callback', [TenantPaymentController::class, 'handleCallback'])->name('payments.callback.generic');
    
    // Provider-specific callback routes
    Route::get('/payments/callback/paystack', [TenantPaymentController::class, 'handlePaystackCallback'])->name('payments.callback.paystack');
    Route::get('/payments/callback/expresspay', [TenantPaymentController::class, 'handleExpressPayCallback'])->name('payments.callback.expresspay');
    Route::get('/payments/callback/hubtel', [TenantPaymentController::class, 'handleHubtelCallback'])->name('payments.callback.hubtel');
    Route::get('/payments/callback/flutterwave', [TenantPaymentController::class, 'handleFlutterwaveCallback'])->name('payments.callback.flutterwave');
    
    // =============================================
    // WEBHOOK ROUTES (POST - for payment providers)
    // =============================================
    
    Route::post('/payments/webhook/{provider}', [TenantPaymentController::class, 'handleWebhook'])->name('payments.webhook');
    
    // =============================================
    // PAYMENT CONFIRMATION & VERIFICATION
    // =============================================
    
    // Confirmation page
    Route::get('/payments/confirmation/{transactionId}', [TenantPaymentController::class, 'showConfirmation'])->name('payments.confirmation');
    
    // Verification form
    Route::get('/payments/verify/{transactionId}', [TenantPaymentController::class, 'showVerificationForm'])->name('payments.verify.form');
    Route::post('/payments/verify', [TenantPaymentController::class, 'verifyPayment'])->name('payments.verify.process');
    
    // =============================================
    // PAYMENT STATUS & UTILITY ROUTES
    // =============================================
    
    // Check payment status (AJAX)
    Route::get('/payments/status/{transactionId}', [TenantPaymentController::class, 'checkPaymentStatus'])->name('payments.status');
    
    // Cancel payment
    Route::post('/payments/cancel/{transactionId}', [TenantPaymentController::class, 'cancelPayment'])->name('payments.cancel');
    
    // Resend verification code
    Route::post('/payments/resend-verification/{transactionId}', [TenantPaymentController::class, 'resendVerificationCode'])->name('payments.resend-verification');
    
    // Export payments
    Route::get('/payments/export', [TenantPaymentController::class, 'exportPayments'])->name('payments.export');
    
    // Get payment details (AJAX)
    Route::get('/payments/details/{paymentId}', [TenantPaymentController::class, 'getPaymentDetails'])->name('payments.details');
    
    // =============================================
    // MOBILE MONEY SPECIFIC ROUTES
    // =============================================
    
    Route::get('/payments/mobile-money', [TenantPaymentController::class, 'mobileMoney'])->name('payments.mobile-money');
    Route::post('/payments/mobile-money/process', [TenantPaymentController::class, 'processMobileMoney'])->name('payments.mobile-money.process');
    
    // =============================================
    // BANK TRANSFER ROUTES
    // =============================================
    
    Route::get('/payments/bank-transfer', [TenantPaymentController::class, 'bankTransfer'])->name('payments.bank-transfer');
    Route::post('/payments/bank-transfer/process', [TenantPaymentController::class, 'processBankTransfer'])->name('payments.bank-transfer.process');
    
    // =============================================
    // CARD PAYMENT ROUTES
    // =============================================
    
    Route::get('/payments/card', [TenantPaymentController::class, 'card'])->name('payments.card');
    Route::post('/payments/card/process', [TenantPaymentController::class, 'processCard'])->name('payments.card.process');
    
    // =============================================
    // PAYMENT RECEIPT ROUTES
    // =============================================
    
    Route::get('/payments/{payment}/receipt', [TenantPaymentController::class, 'downloadReceipt'])->name('payments.receipt');
    
    // Tenant API Routes
    Route::get('/outstanding-invoices', [TenantInvoiceController::class, 'getOutstandingInvoices'])->name('outstanding-invoices');
    Route::get('/invoices/{invoice}/status', [TenantInvoiceController::class, 'getStatus'])->name('invoices.status');
});


// ==================== ADMIN TENANT INVOICE ROUTES ====================
// Only accessible by Super Admins (type 0) and Admins (type 1)
Route::middleware(['auth', 'multi.auth.user:0,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
        // Tenant Invoice Management
        Route::prefix('tenant-invoices')->name('tenant-invoices.')->group(function () {
            
            // ========== STATIC ROUTES (MUST COME BEFORE PARAMETER ROUTES) ==========
            
            // List and view routes
            Route::get('/', [TenantInvoiceController::class, 'index'])->name('index');
            Route::get('create', [TenantInvoiceController::class, 'create'])->name('create');
            Route::post('/', [TenantInvoiceController::class, 'store'])->name('store');
            
            // ========== GENERATION ROUTES (FIXED - ADDED BOTH GET AND POST) ==========
            // Generate monthly invoices - Support both GET and POST for flexibility
            Route::match(['get', 'post'], 'generate-monthly', [TenantInvoiceController::class, 'generateMonthlyInvoices'])
                ->name('generate-monthly');
            
            // Apply automatic penalties
            Route::match(['get', 'post'], 'apply-automatic-penalties', [TenantInvoiceController::class, 'applyAutomaticPenalties'])
                ->name('apply-automatic-penalties');
            
            // ========== PDF EXPORT ROUTES (MUST COME BEFORE PARAMETER ROUTES) ==========
            // Export current page as PDF
            Route::get('export-current-page', [TenantInvoiceController::class, 'exportCurrentPagePdf'])
                ->name('export-current-page');
            
            // Export all filtered invoices as PDF
            Route::get('export-all-filtered', [TenantInvoiceController::class, 'exportAllFilteredPdf'])
                ->name('export-all-filtered');
            
            // Bulk export as PDF (POST)
            Route::post('bulk-export', [TenantInvoiceController::class, 'bulkExportAdminPdf'])
                ->name('bulk-export');
            
            // Bulk print
            Route::get('bulk-print', [TenantInvoiceController::class, 'bulkPrintAdmin'])
                ->name('bulk-print');
            
            // ========== YEAR-END ARCHIVING ROUTES ==========
            Route::get('year-end-management', [TenantInvoiceController::class, 'yearEndManagement'])
                ->name('year-end-management');
            Route::get('year-end-statistics', [TenantInvoiceController::class, 'getYearEndStatistics'])
                ->name('year-end-statistics');
            Route::post('process-year-end', [TenantInvoiceController::class, 'processYearEndArchive'])
                ->name('process-year-end');
            Route::post('send-year-end-reminders', [TenantInvoiceController::class, 'sendYearEndReminders'])
                ->name('send-year-end-reminders');
            Route::get('unpaid-previous-years', [TenantInvoiceController::class, 'unpaidFromPreviousYears'])
                ->name('unpaid-previous-years');
            
            // ========== POST-PAYMENT ARCHIVING ROUTES ==========
            Route::post('process-post-payment', [TenantInvoiceController::class, 'processPostPaymentArchive'])
                ->name('process-post-payment');
            Route::get('preview-post-payment', [TenantInvoiceController::class, 'previewPostPaymentArchive'])
                ->name('preview-post-payment');
            
            // ========== BULK OPERATIONS ROUTES ==========
            // These must be defined before parameter routes
            Route::post('bulk-send-reminders', [TenantInvoiceController::class, 'bulkSendReminders'])
                ->name('bulk-send-reminders');
            Route::get('export-unpaid', [TenantInvoiceController::class, 'exportUnpaidInvoices'])
                ->name('export-unpaid');
            Route::get('export-selected', [TenantInvoiceController::class, 'exportSelectedInvoices'])
                ->name('export-selected');
            
            // ========== TRASH ROUTES ==========
            Route::get('trash', [TenantInvoiceController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [TenantInvoiceController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [TenantInvoiceController::class, 'forceDelete'])->name('force-delete');
            
            // ========== ARCHIVE ROUTES ==========
            Route::get('archives', [TenantInvoiceController::class, 'archives'])->name('archives');
            Route::get('archives/export-all', [TenantInvoiceController::class, 'exportAllArchives'])->name('export-all');
            Route::get('archives/cleanup', [TenantInvoiceController::class, 'archiveCleanup'])->name('archive-cleanup');
            Route::get('archives/preview-cleanup', [TenantInvoiceController::class, 'previewCleanup'])->name('preview-cleanup');
            Route::get('archives/export-cleanup', [TenantInvoiceController::class, 'exportArchivesForCleanup'])->name('export-cleanup');
            Route::post('archives/perform-cleanup', [TenantInvoiceController::class, 'performArchiveCleanup'])->name('perform-cleanup');
            Route::get('archives/cleanup-logs', [TenantInvoiceController::class, 'getCleanupLogs'])->name('cleanup-logs');
            Route::get('archives/get-all-records', [TenantInvoiceController::class, 'getAllRecordsForCleanup'])->name('get-all-records');
            Route::get('archives/preview-log-cleanup', [TenantInvoiceController::class, 'previewLogCleanup'])->name('preview-log-cleanup');
            Route::post('archives/cleanup-old-logs', [TenantInvoiceController::class, 'cleanupOldLogs'])->name('cleanup-old-logs');
            Route::post('archives/delete-selected-logs', [TenantInvoiceController::class, 'deleteSelectedLogs'])
                ->name('delete-selected-logs');
            Route::get('archives/{id}', [TenantInvoiceController::class, 'getArchiveDetails'])->name('archive-details');
            Route::get('archives/{id}/export-pdf', [TenantInvoiceController::class, 'exportArchivePDF'])->name('archive-export-pdf');
            Route::get('archives/{id}/export', [TenantInvoiceController::class, 'exportArchive'])->name('archive-export');
            
            // ========== PARAMETER ROUTES (MUST COME AFTER STATIC ROUTES) ==========
            // Single invoice PDF export (parameter route)
            Route::get('{tenantInvoice}/export-pdf', [TenantInvoiceController::class, 'exportAdminInvoicePdf'])
                ->name('export-pdf')
                ->where('tenantInvoice', '[0-9]+');
            
            Route::get('{tenantInvoice}', [TenantInvoiceController::class, 'show'])->name('show');
            Route::get('{tenantInvoice}/edit', [TenantInvoiceController::class, 'edit'])->name('edit');
            Route::put('{tenantInvoice}', [TenantInvoiceController::class, 'update'])->name('update');
            
            // Single invoice actions
            Route::prefix('{tenantInvoice}')->group(function () {
                Route::post('mark-paid', [TenantInvoiceController::class, 'markAsPaid'])->name('mark-paid');
                Route::post('apply-penalty', [TenantInvoiceController::class, 'applyPenalty'])->name('apply-penalty');
                Route::post('remove-penalty', [TenantInvoiceController::class, 'removePenalty'])->name('remove-penalty');
                Route::get('print', [TenantInvoiceController::class, 'print'])->name('print');
                Route::get('download', [TenantInvoiceController::class, 'download'])->name('download');
                Route::post('send-receipt', [TenantInvoiceController::class, 'sendReceipt'])->name('send-receipt');
                Route::post('send-reminder', [TenantInvoiceController::class, 'sendReminder'])->name('send-reminder');
                Route::delete('/', [TenantInvoiceController::class, 'destroy'])->name('destroy');
            });
        });
    });

// ==================== TENANT INVOICE ROUTES ====================
// Only accessible by Tenants (type 3)
Route::middleware(['auth', 'multi.auth.user:3'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {
        
        // Tenant Invoice Views
        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Main route - use single route with proper name
            Route::get('/', [TenantInvoiceController::class, 'myInvoices'])
                ->name('index');
            
            // Add alias route if needed (redirect instead of duplicate)
            Route::redirect('my-invoices', '/tenant/invoices')
                ->name('my-invoices');
            
            // ========== TENANT ARCHIVE APPROVAL ROUTES ==========
            Route::get('pending-archive', [TenantInvoiceController::class, 'pendingArchiveApproval'])
                ->name('pending-archive');
            Route::post('{tenantInvoice}/archive-approval', [TenantInvoiceController::class, 'processArchiveApproval'])
                ->name('archive-approval');
            
            // ========== PDF EXPORT ROUTES ==========
            // Single invoice PDF export
            Route::get('export-pdf/{tenantInvoice}', [TenantInvoiceController::class, 'exportInvoicePdf'])
                ->name('export-pdf')
                ->where('tenantInvoice', '[0-9]+');
            
            // Bulk PDF export (POST for selected invoices)
            Route::post('bulk-export-pdf', [TenantInvoiceController::class, 'bulkExportInvoicesPdf'])
                ->name('bulk-export-pdf');
            
            // Parameter routes (must come after static routes)
            Route::get('show/{tenantInvoice}', [TenantInvoiceController::class, 'showTenantInvoice'])
                ->name('show');
            Route::get('print/{tenantInvoice}', [TenantInvoiceController::class, 'printTenantInvoice'])
                ->name('print');
            Route::get('download/{tenantInvoice}', [TenantInvoiceController::class, 'downloadTenantInvoice'])
                ->name('download');
            Route::post('{tenantInvoice}/request-receipt', [TenantInvoiceController::class, 'requestReceipt'])
                ->name('request-receipt');
            Route::post('request-current', [TenantInvoiceController::class, 'requestCurrentMonth'])
                ->name('request-current');
        });

        // =============================================
        // ✅ TENANT PAYMENT ROUTES - COMPLETE WITH ALL CALLBACKS
        // =============================================
        
        Route::prefix('payments')->name('payments.')->group(function () {
            // History and creation
            Route::get('history', [TenantPaymentController::class, 'history'])->name('history');
            Route::get('make', [TenantPaymentController::class, 'make'])->name('make');
            
            // =============================================
            // ✅ CRITICAL: PAYMENT CALLBACK ROUTES - FIXES THE ERROR
            // =============================================
            
            // Generic callback route (supports all providers)
            Route::get('callback/{provider}', [TenantPaymentController::class, 'handleCallback'])->name('callback');
            
            // Generic callback without provider (fallback)
            Route::get('callback', [TenantPaymentController::class, 'handleCallback'])->name('callback.generic');
            
            // Provider-specific callback routes
            Route::get('callback/paystack', [TenantPaymentController::class, 'handlePaystackCallback'])->name('callback.paystack');
            Route::get('callback/expresspay', [TenantPaymentController::class, 'handleExpressPayCallback'])->name('callback.expresspay');
            Route::get('callback/hubtel', [TenantPaymentController::class, 'handleHubtelCallback'])->name('callback.hubtel');
            Route::get('callback/flutterwave', [TenantPaymentController::class, 'handleFlutterwaveCallback'])->name('callback.flutterwave');
            
            // =============================================
            // WEBHOOK ROUTES (POST - for payment providers)
            // =============================================
            
            Route::post('webhook/{provider}', [TenantPaymentController::class, 'handleWebhook'])->name('webhook');
            
            // =============================================
            // PAYMENT CONFIRMATION & VERIFICATION
            // =============================================
            
            // Confirmation page
            Route::get('confirmation/{transactionId}', [TenantPaymentController::class, 'showConfirmation'])->name('confirmation');
            
            // Verification form
            Route::get('verify/{transactionId}', [TenantPaymentController::class, 'showVerificationForm'])->name('verify.form');
            Route::post('verify', [TenantPaymentController::class, 'verifyPayment'])->name('verify.process');
            
            // =============================================
            // PAYMENT STATUS & UTILITY ROUTES
            // =============================================
            
            // Check payment status (AJAX)
            Route::get('status/{transactionId}', [TenantPaymentController::class, 'checkPaymentStatus'])->name('status');
            
            // Cancel payment
            Route::post('cancel/{transactionId}', [TenantPaymentController::class, 'cancelPayment'])->name('cancel');
            
            // Resend verification code
            Route::post('resend-verification/{transactionId}', [TenantPaymentController::class, 'resendVerificationCode'])->name('resend-verification');
            
            // Export payments
            Route::get('export', [TenantPaymentController::class, 'exportPayments'])->name('export');
            
            // Get payment details (AJAX)
            Route::get('details/{paymentId}', [TenantPaymentController::class, 'getPaymentDetails'])->name('details');
            
            // =============================================
            // MOBILE MONEY SPECIFIC ROUTES
            // =============================================
            
            Route::get('mobile-money', [TenantPaymentController::class, 'mobileMoney'])->name('mobile-money');
            Route::post('mobile-money/process', [TenantPaymentController::class, 'processMobileMoney'])->name('mobile-money.process');
            
            // =============================================
            // BANK TRANSFER ROUTES
            // =============================================
            
            Route::get('bank-transfer', [TenantPaymentController::class, 'bankTransfer'])->name('bank-transfer');
            Route::post('bank-transfer/process', [TenantPaymentController::class, 'processBankTransfer'])->name('bank-transfer.process');
            
            // =============================================
            // CARD PAYMENT ROUTES
            // =============================================
            
            Route::get('card', [TenantPaymentController::class, 'card'])->name('card');
            Route::post('card/process', [TenantPaymentController::class, 'processCard'])->name('card.process');
            
            // =============================================
            // PAYMENT RECEIPT ROUTES
            // =============================================
            
            Route::get('{payment}/receipt', [TenantPaymentController::class, 'downloadReceipt'])->name('receipt');
        });    
    });

// ==================== API ROUTES ====================
Route::prefix('api')->name('api.')->group(function () {
    
    // Tenant Invoice Statistics API - Admins (0,1) and Tenants (3) can access their own stats
    Route::middleware(['auth', 'multi.auth.user:0,1,3'])
        ->get('tenant-invoices/statistics', [TenantInvoiceController::class, 'getInvoiceStatistics'])
        ->name('tenant-invoices.statistics');
    
    // Get tenant details for invoice creation - Admins (0,1) only
    Route::middleware(['auth', 'multi.auth.user:0,1'])
        ->get('admin/tenants/{tenantId}/details', [TenantInvoiceController::class, 'getTenantDetails'])
        ->name('admin.tenants.details');
    
    // ========== YEAR-END ARCHIVE API ROUTES ==========
    
    // Get year-end archive statistics (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:0,1'])
        ->get('admin/tenant-invoices/year-end-stats/{year?}', [TenantInvoiceController::class, 'getYearEndStatistics'])
        ->name('admin.tenant-invoices.year-end-stats');
    
    // Get eligible invoices for year-end archive (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:0,1'])
        ->get('admin/tenant-invoices/eligible-for-archive/{year}', [TenantInvoiceController::class, 'getEligibleForYearEndArchive'])
        ->name('admin.tenant-invoices.eligible-for-archive');
    
    // Get invoices pending archive approval for tenant (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:3'])
        ->get('tenant/invoices/pending-approval', [TenantInvoiceController::class, 'getPendingArchiveApproval'])
        ->name('tenant.invoices.pending-approval');
    
    // Get archive info for a specific invoice (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:0,1,3'])
        ->get('tenant-invoices/{tenantInvoice}/archive-info', [TenantInvoiceController::class, 'getArchiveInfo'])
        ->name('tenant-invoices.archive-info')
        ->where('tenantInvoice', '[0-9]+');
    
    // Preview post-payment archiving (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:0,1'])
        ->get('admin/tenant-invoices/preview-post-payment', [TenantInvoiceController::class, 'previewPostPaymentArchive'])
        ->name('admin.tenant-invoices.preview-post-payment');
    
    // Get archive cleanup logs (API endpoint)
    Route::middleware(['auth', 'multi.auth.user:0,1'])
        ->get('admin/tenant-invoices/cleanup-logs', [TenantInvoiceController::class, 'getCleanupLogs'])
        ->name('admin.tenant-invoices.cleanup-logs');
    Route::put('tenant-invoices/{tenantInvoice}', [TenantInvoiceController::class, 'update'])
    ->name('admin.tenant-invoices.update');
    
    // =============================================
    // ✅ PAYMENT CALLBACK/WEBHOOK ROUTES - Public (for payment gateways)
    // =============================================
    
    Route::post('payments/mobile-money/callback', [TenantPaymentController::class, 'mobileMoneyCallback'])
        ->name('payments.mobile-money.callback');
    Route::post('payments/bank-transfer/callback', [TenantPaymentController::class, 'bankTransferCallback'])
        ->name('payments.bank-transfer.callback');
    Route::post('payments/card/callback', [TenantPaymentController::class, 'cardCallback'])
        ->name('payments.card.callback');
    
    // Payment verification routes
    Route::get('payments/{payment}/verify', [TenantPaymentController::class, 'verifyPayment'])
        ->name('payments.verify');
    
    // =============================================
    // ✅ PAYMENT STATUS CHECK ROUTE (AJAX)
    // =============================================
    
    Route::get('payments/status/{transactionId}', [TenantPaymentController::class, 'checkPaymentStatus'])
        ->name('payments.status');
    
    // =============================================
    // ✅ PAYMENT DETAILS ROUTE (AJAX)
    // =============================================
    
    Route::get('payments/details/{paymentId}', [TenantPaymentController::class, 'getPaymentDetails'])
        ->name('payments.details');
});

// ==================== PUBLIC ROUTES (No Authentication Required) ====================
Route::prefix('public')->name('public.')->group(function () {
    
    // Public invoice lookup (for tenants without login)
    Route::get('invoice/{invoice_number}', [TenantInvoiceController::class, 'publicLookup'])
        ->name('invoice.lookup');
    
    // Public payment page (for tenants without login)
    Route::get('pay/{invoice_number}', [TenantPaymentController::class, 'publicPaymentPage'])
        ->name('payment.public');
    
    // Public payment callback (for payment gateways)
    Route::post('payment-callback/{gateway}', [TenantPaymentController::class, 'publicPaymentCallback'])
        ->name('payment.callback');
    
    // =============================================
    // ✅ PUBLIC PAYMENT WEBHOOK (for external services)
    // =============================================
    
    Route::post('payment-webhook/{gateway}', [TenantPaymentController::class, 'webhookHandler'])
        ->name('payment.webhook')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});

// ==================== WEBHOOK ROUTES (For External Services) ====================
Route::prefix('webhook')->name('webhook.')->group(function () {
    
    // Payment gateway webhooks (no CSRF protection for webhooks)
    Route::post('payment/{gateway}', [TenantPaymentController::class, 'webhookHandler'])
        ->name('payment')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    
    // SMS delivery receipts
    Route::post('sms/delivery', [NotificationController::class, 'smsDeliveryReceipt'])
        ->name('sms.delivery')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    
    // Email bounce handling
    Route::post('email/bounce', [NotificationController::class, 'emailBounceHandler'])
        ->name('email.bounce')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});

/*
|--------------------------------------------------------------------------
| ✅ UPDATED: Registration Plans Routes with Split Controllers
|--------------------------------------------------------------------------
*/

// Admin Registration Plan Management Routes (Super Admin and Admin Only)
Route::middleware(['auth', 'multi.auth.user:0,1'])->group(function () {
    // Registration Plan resource routes (Core CRUD operations)
    Route::resource('registration-plans', RegistrationPlanController::class)->except(['show']);
    
    // Individual show route to handle trashed records
    Route::get('registration-plans/{id}', [RegistrationPlanController::class, 'show'])->name('registration-plans.show');
    
    // ✅ ADDED: Agent Management Routes
    Route::prefix('registration-plans/{id}')->name('registration-plans.')->group(function () {
        Route::post('add-agent', [RegistrationPlanAgentController::class, 'addAgent'])->name('add-agent');
        Route::delete('remove-agent/{agentId}', [RegistrationPlanAgentController::class, 'removeAgent'])->name('remove-agent');
        
        // ✅ ADDED: Missing route for agents.remove (to fix the error)
        Route::delete('agents/{agentId}/remove', [RegistrationPlanAgentController::class, 'removeAgent'])
            ->name('agents.remove');
            
        Route::post('reactivate-agent/{agentId}', [RegistrationPlanAgentController::class, 'reactivateAgent'])->name('reactivate-agent');
        Route::post('send-invitation', [RegistrationPlanAgentController::class, 'sendInvitation'])->name('send-invitation');
        Route::post('send-bulk-invitations', [RegistrationPlanAgentController::class, 'sendBulkInvitations'])->name('send-bulk-invitations');
        Route::get('assignments/{assignmentId}', [RegistrationPlanAgentController::class, 'getAssignmentDetails'])->name('assignment-details');
    });
    
    // ✅ ADDED: Status Management Routes
    Route::prefix('registration-plans/{id}')->name('registration-plans.')->group(function () {
        Route::post('mark-in-progress', [RegistrationPlanStatusController::class, 'markInProgress'])->name('mark-in-progress');
        Route::post('mark-completed', [RegistrationPlanStatusController::class, 'markCompleted'])->name('mark-completed');
        Route::post('cancel', [RegistrationPlanStatusController::class, 'cancel'])->name('cancel');
        Route::post('reactivate', [RegistrationPlanStatusController::class, 'reactivate'])->name('reactivate');
    });
    
    // ✅ ADDED: Notification routes (FIXED: Moved outside status management group)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('markAsRead');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
            ->name('markAllAsRead');
        Route::get('/', [NotificationController::class, 'index'])
            ->name('index');
    });
    
    // ✅ ADDED: Trash Management Routes
    Route::prefix('registration-plans')->name('registration-plans.')->group(function () {
        Route::get('trash/list', [RegistrationPlanTrashController::class, 'index'])->name('trash');
        Route::post('{id}/restore', [RegistrationPlanTrashController::class, 'restore'])->name('restore');
        Route::delete('{id}/force', [RegistrationPlanTrashController::class, 'forceDestroy'])->name('force-destroy');
        Route::delete('trash/empty', [RegistrationPlanTrashController::class, 'emptyTrash'])->name('empty-trash');
    });
    
    // ✅ ADDED: Pattern Management Routes
    Route::prefix('registration-plans')->name('registration-plans.')->group(function () {
        Route::post('check-global-sequence', [RegistrationPlanPatternController::class, 'checkGlobalSequence'])->name('check-global-sequence');
        Route::post('validate-pattern', [RegistrationPlanPatternController::class, 'validatePattern'])->name('validate-pattern');
        Route::post('get-next-pattern', [RegistrationPlanPatternController::class, 'getNextPattern'])->name('get-next-pattern');
        Route::get('{id}/next-pattern', [RegistrationPlanPatternController::class, 'getNextPatternForPlan'])->name('get-next-pattern-for-plan');
    });
    
    // ✅ ADDED: Export and Bulk Actions Routes
    Route::prefix('registration-plans')->name('registration-plans.')->group(function () {
        Route::post('export', [RegistrationPlanExportController::class, 'export'])->name('export');
        Route::post('bulk-actions', [RegistrationPlanExportController::class, 'bulkActions'])->name('bulk-actions');
    });
    
    // ✅ ADDED: Analytics and Statistics Routes
    Route::prefix('registration-plans')->name('registration-plans.')->group(function () {
        Route::get('analytics', [RegistrationPlanAnalyticsController::class, 'analytics'])->name('analytics');
        Route::get('statistics/data', [RegistrationPlanAnalyticsController::class, 'statistics'])->name('statistics');
    });
    
    // ✅ ADDED: Service Management Routes
    Route::prefix('registration-plans')->name('registration-plans.')->group(function () {
        Route::get('service-status', [RegistrationPlanServiceController::class, 'getServiceStatus'])->name('service-status');
        Route::post('test-channels', [RegistrationPlanServiceController::class, 'testChannels'])->name('test-channels');
    });
});

// Field Agent Registration Plan Access Routes
Route::middleware(['auth', 'multi.auth.user:4'])->prefix('field-agent')->name('field-agent.')->group(function () {
    // Field Agent registration plans
    Route::get('registration-plans', [RegistrationPlanController::class, 'index'])->name('registration-plans.index');
    Route::get('registration-plans/{id}', [RegistrationPlanController::class, 'show'])->name('registration-plans.show');
    
    // Field Agent property registration
    Route::get('registration/{planId}', [RegistrationPlanController::class, 'fieldAgentRegistration'])->name('registration');
    Route::post('registration/{planId}/register-property', [RegistrationPlanController::class, 'registerProperty'])->name('registration.register-property');
});



/*
|--------------------------------------------------------------------------
| User Management Routes - FULLY UPDATED WITH ALL INVITATION ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // ==================== USER TRASH MANAGEMENT ====================
    Route::get('users/trash', [UserManagementController::class, 'trash'])->name('users.trash');
    Route::post('users/{user}/restore', [UserManagementController::class, 'restore'])->name('users.restore');
    Route::delete('users/{user}/force-delete', [UserManagementController::class, 'forceDestroy'])->name('users.force-delete');
    Route::post('users/bulk-restore', [UserManagementController::class, 'bulkRestore'])->name('users.bulk-restore');
    Route::delete('users/bulk-permanent-delete', [UserManagementController::class, 'bulkPermanentDelete'])->name('users.bulk-permanent-delete');
    Route::post('users/empty-trash', [UserManagementController::class, 'emptyTrash'])->name('users.empty-trash');

    // ==================== ACCOUNT ARCHIVAL MANAGEMENT ====================
    Route::get('users/archived', [UserManagementController::class, 'archivedUsers'])->name('users.archived');
    Route::get('users/archived/{user}', [UserManagementController::class, 'archivedUserDetails'])->name('users.archived.details');
    Route::post('users/{user}/restore-archived', [UserManagementController::class, 'restoreArchived'])->name('users.restore-archived');
    Route::post('users/{user}/archive', [UserManagementController::class, 'archiveUser'])->name('users.archive');
    Route::post('users/bulk-archive', [UserManagementController::class, 'bulkArchive'])->name('users.bulk-archive');
    Route::post('users/bulk-restore-archived', [UserManagementController::class, 'bulkRestoreArchived'])->name('users.bulk-restore-archived');
    Route::delete('users/{user}/permanent-delete', [UserManagementController::class, 'permanentDelete'])->name('users.permanent-delete');
    Route::post('users/bulk-permanent-delete-archived', [UserManagementController::class, 'bulkPermanentDeleteArchived'])->name('users.bulk-permanent-delete-archived');
    Route::get('users/archival-stats', [UserManagementController::class, 'getArchivalStats'])->name('users.archival-stats');
    Route::get('users/{user}/archival-preview', [UserManagementController::class, 'archivalPreview'])->name('users.archival-preview');
    Route::post('users/{user}/cancel-archival-schedule', [UserManagementController::class, 'cancelArchivalSchedule'])->name('users.cancel-archival-schedule');
    Route::get('users/archived/export', [ExportController::class, 'exportArchived'])->name('users.archived.export');
    Route::get('users/archived/export/pdf', [ExportController::class, 'exportArchivedToPdf'])->name('users.archived.export.pdf');

    // ==================== EXPORT ROUTES ====================
    Route::get('users/export', [ExportController::class, 'export'])->name('users.export');
    Route::get('users/export/pdf', [ExportController::class, 'exportToPdf'])->name('users.export.pdf');

    // ==================== STANDARD USER RESOURCE ROUTES ====================
    // ✅ MOVED: Specific routes BEFORE the resource route to prevent conflicts
    Route::get('users/list-email', [\App\Http\Controllers\Admin\UserManagementController::class, 'getUserListForEmail'])->name('users.list-email');
    
    // ✅ MOVED: Test route for debugging
    Route::get('users/test', [\App\Http\Controllers\Admin\UserManagementController::class, 'testRoute'])->name('users.test');
    
    Route::resource('users', UserManagementController::class)->except(['destroy']);
    
    // Custom destroy route with correct parameter name
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    // ==================== USER STATUS MANAGEMENT ====================
    Route::post('users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');

    // ==================== USER INVITATION MANAGEMENT ====================
    // Send new invitation
    Route::post('users/{user}/send-invitation', [UserManagementController::class, 'sendInvitation'])->name('users.send-invitation');
    
    // Resend existing invitation
    Route::post('users/{user}/resend-invitation', [UserManagementController::class, 'resendInvitation'])->name('users.resend-invitation');
    
    // Get invitation info for resend modal
    Route::get('users/{user}/invitation-info', [UserManagementController::class, 'getInvitationInfo'])->name('users.invitation-info');
    
    // Get invitation history for user
    Route::get('users/{user}/invitation-history', [UserManagementController::class, 'getInvitationHistory'])->name('users.invitation-history');
    
    // Get available channels for user
    Route::get('users/{user}/available-channels', [UserManagementController::class, 'getUserChannels'])->name('users.available-channels');
    
    // Bulk invitation sending
    Route::post('users/bulk-invitation', [UserManagementController::class, 'bulkSendInvitation'])->name('users.bulk-invitation');

    // ==================== PHONE VERIFICATION MANAGEMENT ====================
    Route::post('users/{user}/verify-phone', [ProfileController::class, 'verifyPhone'])->name('users.verify-phone');
    Route::post('users/{user}/send-verification-code', [ProfileController::class, 'sendPhoneVerification'])->name('users.send-verification-code');

    // ==================== PASSWORD MANAGEMENT ====================
    Route::post('users/{user}/update-password', [UserManagementController::class, 'updatePassword'])->name('users.update-password');

    // ==================== PHOTO MANAGEMENT ====================
    Route::post('users/{user}/update-photo', [PhotoController::class, 'updatePhoto'])->name('users.update-photo');
    Route::post('users/{user}/remove-photo', [PhotoController::class, 'removePhoto'])->name('users.remove-photo');

    // ==================== BULK ACTIONS ====================
    Route::post('users/bulk-action', [UserManagementController::class, 'bulkAction'])->name('users.bulk-action');
    Route::post('users/bulk-role-action', [UserManagementController::class, 'bulkRoleAction'])->name('users.bulk-role-action');

    // ==================== ACTIVITY LOGS ====================
    Route::get('users/{user}/activity-log', [UserManagementController::class, 'activityLog'])->name('users.activity-log');

    // ==================== USER RELATIONS CHECK ====================
    Route::get('users/{user}/check-relations', [UserManagementController::class, 'checkRelations'])->name('users.check-relations');

    // ==================== DELETED USER DETAILS ====================
    Route::get('users/{user}/deleted-details', [UserManagementController::class, 'deletedDetails'])->name('users.deleted-details');
    Route::get('users/{user}/archived-details', [UserManagementController::class, 'getArchivedUserDetails'])->name('users.archived-details');

    // ==================== USER PERSONAL DATA ====================
    Route::get('users/{user}/download-data', [ProfileController::class, 'downloadPersonalData'])->name('users.download-data');

    // ==================== USER STATISTICS ====================
    Route::get('users/{user}/statistics', [ProfileController::class, 'getProfileStats'])->name('users.statistics');

    // ==================== MULTI-ROLE & LANDLORD MANAGEMENT ROUTES ====================
    Route::post('users/{user}/assign-landlord-role', [UserManagementController::class, 'assignLandlordRole'])->name('users.assign-landlord-role');
    Route::post('users/{user}/remove-landlord-role', [UserManagementController::class, 'removeLandlordRole'])->name('users.remove-landlord-role');
    Route::get('users/{user}/roles-data', [UserManagementController::class, 'getUserRolesData'])->name('users.roles-data');
    Route::put('users/{user}/update-roles', [UserManagementController::class, 'updateUserRoles'])->name('users.update-roles');

    // ==================== PROPERTY OWNER MANAGEMENT ROUTES ====================
    Route::get('property-owners', [UserManagementController::class, 'getPropertyOwners'])->name('users.property-owners');
    Route::get('multi-role-users', [UserManagementController::class, 'getMultiRoleUsers'])->name('users.multi-role-users');
    Route::post('users/{user}/transfer-properties', [UserManagementController::class, 'transferUserProperties'])->name('users.transfer-properties');

    // ==================== ROLE-BASED USER FILTERING ====================
    Route::get('users/by-role/{roleSlug}', [UserManagementController::class, 'getUsersByRole'])->name('users.by-role');
    Route::get('users/landlords', [UserManagementController::class, 'getLandlordUsers'])->name('users.landlords');
    Route::get('users/property-owners/details', [UserManagementController::class, 'getPropertyOwnersWithDetails'])->name('users.property-owners.details');

    // ==================== USER ROLE SYNC & MANAGEMENT ====================
    Route::post('users/bulk-assign-role', [UserManagementController::class, 'bulkAssignRole'])->name('users.bulk-assign-role');
    Route::post('users/bulk-remove-role', [UserManagementController::class, 'bulkRemoveRole'])->name('users.bulk-remove-role');
    Route::get('users/available-roles', [UserManagementController::class, 'getAvailableRoles'])->name('users.available-roles');

    // ==================== USER PERMISSION MANAGEMENT ====================
    Route::get('users/{user}/permissions', [UserManagementController::class, 'getUserPermissions'])->name('users.permissions');
    Route::post('users/{user}/override-permission', [UserManagementController::class, 'overrideUserPermission'])->name('users.override-permission');
    Route::delete('users/{user}/remove-permission-override', [UserManagementController::class, 'removePermissionOverride'])->name('users.remove-permission-override');

    // ==================== USER DASHBOARD STATISTICS API ====================
    Route::get('users/dashboard-stats', [UserManagementController::class, 'getDashboardStats'])->name('users.dashboard-stats');
    Route::get('users/role-distribution', [UserManagementController::class, 'getRoleDistribution'])->name('users.role-distribution');
    Route::get('users/registration-trends', [UserManagementController::class, 'getRegistrationTrends'])->name('users.registration-trends');

    // ==================== USER IMPERSONATION ROUTES ====================
    Route::post('users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->name('users.impersonate')->middleware('multi.auth.user:0');
    Route::post('users/stop-impersonation', [UserManagementController::class, 'stopImpersonation'])->name('users.stop-impersonation');

    // ==================== USER MERGE ROUTES ====================
    Route::post('users/merge', [UserManagementController::class, 'mergeUsers'])->name('users.merge')->middleware('multi.auth.user:0');
    Route::get('users/merge-preview/{sourceUser}/{targetUser}', [UserManagementController::class, 'previewMerge'])->name('users.merge-preview')->middleware('multi.auth.user:0');

    // ==================== USER NOTIFICATION MANAGEMENT ====================
    Route::post('users/bulk-notify', [UserManagementController::class, 'bulkNotify'])->name('users.bulk-notify');
    Route::get('users/{user}/notification-preferences', [UserManagementController::class, 'getNotificationPreferences'])->name('users.notification-preferences');
    Route::put('users/{user}/notification-preferences', [UserManagementController::class, 'updateNotificationPreferences'])->name('users.update-notification-preferences');

    // ==================== USER SESSION MANAGEMENT ====================
    Route::get('users/{user}/sessions', [UserManagementController::class, 'getUserSessions'])->name('users.sessions');
    Route::delete('users/{user}/sessions/{sessionId}', [UserManagementController::class, 'revokeSession'])->name('users.revoke-session');
    Route::post('users/{user}/revoke-all-sessions', [UserManagementController::class, 'revokeAllSessions'])->name('users.revoke-all-sessions');

    // ==================== USER LOGIN ACTIVITY ====================
    Route::get('users/{user}/login-history', [UserManagementController::class, 'getLoginHistory'])->name('users.login-history');
    Route::get('users/{user}/suspicious-logins', [UserManagementController::class, 'getSuspiciousLogins'])->name('users.suspicious-logins');

    // ==================== USER DATA CLEANUP & MAINTENANCE ====================
    Route::post('users/cleanup-invitations', [UserManagementController::class, 'cleanupInvitations'])->name('users.cleanup-invitations');
    Route::post('users/cleanup-activity-logs', [UserManagementController::class, 'cleanupActivityLogs'])->name('users.cleanup-activity-logs');
    Route::get('users/system-stats', [UserManagementController::class, 'getSystemUserStats'])->name('users.system-stats');

    // ==================== USER ACTIVITY REPORTING ====================
    Route::get('users/activity-report', [UserManagementController::class, 'generateActivityReport'])->name('users.activity-report');
    Route::get('users/activity-report/export', [UserManagementController::class, 'exportActivityReport'])->name('users.activity-report.export');

    // ==================== USER VALIDATION RULES MANAGEMENT ====================
    Route::get('users/validation-rules', [UserManagementController::class, 'getValidationRules'])->name('users.validation-rules');
    Route::get('users/check-unique', [UserManagementController::class, 'checkUniqueField'])->name('users.check-unique');

    // ==================== USER TAGS & CATEGORIES ====================
    Route::get('users/tags', [UserManagementController::class, 'getUserTags'])->name('users.tags');
    Route::post('users/{user}/add-tag', [UserManagementController::class, 'addUserTag'])->name('users.add-tag');
    Route::delete('users/{user}/remove-tag/{tag}', [UserManagementController::class, 'removeUserTag'])->name('users.remove-tag');
    Route::get('users/by-tag/{tag}', [UserManagementController::class, 'getUsersByTag'])->name('users.by-tag');

    // ==================== PROPERTY OWNERS EXPORT ====================
    Route::post('users/property-owners/export', [UserManagementController::class, 'exportPropertyOwners'])->name('users.property-owners.export');
    Route::get('users/property-owners/export/csv', [UserManagementController::class, 'exportPropertyOwnersCsv'])->name('users.property-owners.export.csv');
    Route::get('users/property-owners/export/pdf', [UserManagementController::class, 'exportPropertyOwnersPdf'])->name('users.property-owners.export.pdf');
});

// ==================== PUBLIC USER LOOKUP ROUTES ====================
Route::get('api/users/check-exists', [UserManagementController::class, 'checkUserExists'])->name('api.users.check-exists')->middleware('throttle:60,1');
Route::get('api/users/{user}/public-profile', [UserManagementController::class, 'getPublicProfile'])->name('api.users.public-profile')->middleware('throttle:60,1');
Route::get('referral/{code}', [UserManagementController::class, 'trackReferral'])->name('referral.track')->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| Property Management Routes 
|--------------------------------------------------------------------------
*/

// =============================================
// ADMIN-ONLY PROPERTY MANAGEMENT ROUTES
// =============================================

Route::middleware(['auth', 'multi.auth.user:0,1'])->group(function () {
    
    // =============================================
    // MAIN PROPERTY CRUD ROUTES
    // =============================================
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
    Route::put('/{property}/coordinates', [PropertyController::class, 'updateCoordinates'])
            ->name('coordinates.update');
    Route::get('/properties/geocode', [PropertyController::class, 'geocodeDigitalAddress'])
    ->name('properties.geocode');
    
    // =============================================
    // PROPERTY EXPORT & STATISTICS
    // =============================================
    Route::get('properties/export', [PropertyController::class, 'export'])->name('properties.export');
    Route::get('properties/type-stats', [PropertyController::class, 'getPropertyTypeStats'])->name('properties.type-stats');
    Route::get('properties/global-sequence-stats', [PropertyController::class, 'globalSequenceStats'])->name('properties.global-sequence-stats');
    
    // =============================================
    // PROPERTY FILTERING ROUTES
    // =============================================
    Route::get('properties/street/{streetName}', [PropertyController::class, 'getByStreet'])->name('properties.by-street');
    Route::get('properties/landlord/{landlordId}', [PropertyController::class, 'getByLandlord'])->name('properties.by-landlord');
    Route::get('properties/digital-address/{digitalAddress}', [PropertyController::class, 'getByDigitalAddress'])->name('properties.by-digital-address');
    Route::get('properties/zone/{zone}', [PropertyController::class, 'getByZone'])->name('properties.by-zone');
    Route::get('properties/registration-plan/{planId}', [PropertyController::class, 'getByRegistrationPlan'])->name('properties.by-registration-plan');
    Route::get('properties/with-digital-address', [PropertyController::class, 'getWithDigitalAddress'])->name('properties.with-digital-address');
    Route::get('properties/without-digital-address', [PropertyController::class, 'getWithoutDigitalAddress'])->name('properties.without-digital-address');
    Route::get('properties/type/{typeSlug}', [PropertyController::class, 'getByPropertyType'])->name('properties.by-type');
    
    // =============================================
    // PROPERTY TENANT MANAGEMENT
    // =============================================
    Route::prefix('properties/{property}/tenants')->name('properties.tenants.')->group(function () {
        Route::get('/', [PropertyController::class, 'getPropertyTenants'])->name('index');
        Route::post('/', [PropertyController::class, 'addTenant'])->name('store');
        Route::post('/bulk', [PropertyController::class, 'bulkAddTenants'])->name('bulk');
        Route::delete('/{tenant}', [PropertyController::class, 'removeTenant'])->name('destroy');
        Route::post('/{tenant}/resend-invitation', [PropertyController::class, 'resendTenantInvitation'])->name('resend-invitation');
    });

    // =============================================
    // DUPLICATE PREVENTION ROUTES
    // =============================================
    Route::post('properties/check-duplicate', [PropertyController::class, 'checkDuplicate'])
        ->name('properties.check-duplicate');
    
    Route::post('properties/suggest-similar', [PropertyController::class, 'suggestSimilarProperties'])
        ->name('properties.suggest-similar');
    
    // =============================================
    // PROPERTY PATTERN CHECK ROUTES (AJAX/API)
    // =============================================
    Route::prefix('properties')->name('properties.')->group(function () {
        Route::get('/check-pattern/{pattern}', [PropertyController::class, 'checkPattern'])->name('check-pattern');
        Route::post('/check-pattern', [PropertyController::class, 'checkPatternPost'])->name('check-pattern.post');
    });

    // =============================================
    // ✅ PROPERTY TRASH MANAGEMENT ROUTES
    // =============================================
    Route::get('properties/trash', [PropertyTrashController::class, 'index'])
        ->name('properties.trash.index');

    Route::get('properties/trash/count', [PropertyTrashController::class, 'getTrashCount'])
        ->name('properties.trash.count');

    Route::post('properties/{id}/restore', [PropertyTrashController::class, 'restore'])
        ->name('properties.trash.restore');
    Route::post('properties/trash/restore-all', [PropertyTrashController::class, 'restoreAll'])
        ->name('properties.trash.restore-all');

    Route::delete('properties/{id}/force-delete', [PropertyTrashController::class, 'forceDelete'])
        ->name('properties.trash.force-delete');
    Route::delete('properties/trash/empty', [PropertyTrashController::class, 'emptyTrash'])
        ->name('properties.trash.empty');
});

/*
|--------------------------------------------------------------------------
| Public Landlord Registration Completion Routes
|--------------------------------------------------------------------------
*/

Route::prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/complete-registration/{token}', [PropertyController::class, 'showLandlordRegistrationForm'])
        ->name('registration.complete');
    
    Route::post('/complete-registration/{token}', [PropertyController::class, 'completeLandlordRegistration'])
        ->name('registration.complete.submit');
});

/*
|--------------------------------------------------------------------------
| Landlord-specific Routes (MUST BE BEFORE SHARED ROUTES)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:2'])->group(function () {
    
    // =============================================
    // PROPERTY VIEWING ROUTES (READ ONLY)
    // =============================================
    Route::get('my-properties', [PropertyController::class, 'myProperties'])->name('properties.my-properties');
    Route::get('landlord/properties', [PropertyController::class, 'myProperties'])->name('landlord.properties.index');
    Route::get('landlord/properties/{property}', [PropertyController::class, 'show'])->name('landlord.properties.show');
    
    // =============================================
    // LANDLORD CONSTRUCTION DETAILS UPDATE ROUTE
    // =============================================
    Route::post('landlord/properties/update-construction', [PropertyController::class, 'updateConstruction'])
        ->name('properties.update-construction');
    Route::put('landlord/properties/{property}/construction', [PropertyController::class, 'updateConstructionWithProperty'])
        ->name('properties.update-construction-with-property');
    
    // =============================================
    // LANDLORD PHOTO MANAGEMENT ROUTES
    // =============================================
    Route::prefix('landlord/properties')->name('landlord.properties.')->group(function () {
        Route::post('{property}/upload-photos', [PropertyController::class, 'landlordUploadPhotos'])
            ->name('upload-photos');
        Route::delete('{property}/delete-photo/{photo}', [PropertyController::class, 'landlordDeletePhoto'])
            ->name('delete-photo');
        Route::put('{property}/set-primary-photo/{photo}', [PropertyController::class, 'landlordSetPrimaryPhoto'])
            ->name('set-primary-photo');
        Route::get('{property}/photo-gallery', [PropertyController::class, 'landlordGetPhotoGallery'])
            ->name('photo-gallery');
        Route::get('{property}/download-all-photos', [PropertyController::class, 'landlordDownloadAllPhotos'])
            ->name('download-all-photos');
    });

    Route::post('/properties/mark-active', [PropertyController::class, 'markActive'])->name('properties.mark-active');
    
    // =============================================
    // LANDLORD INVITATION MANAGEMENT ROUTES
    // =============================================
    Route::prefix('properties/{property}')->name('properties.')->group(function () {
        Route::get('/sms-invitation-stats', [PropertyInvitationController::class, 'getSmsInvitationStats'])
            ->name('sms-invitation-stats');
    });
});

/*
|--------------------------------------------------------------------------
| Shared Property View Routes (Multi-role access - READ ONLY)
| MUST BE AFTER LANDLORD ROUTES TO PREVENT CONFLICTS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1,2,4,5'])->group(function () {
    Route::get('properties/{property}', [PropertyController::class, 'show'])
        ->name('properties.show');
});
    
    
    // =============================================
    // ✅ NEW: Property Invitation Management Routes (Using PropertyInvitationController)
    // =============================================
    
    Route::prefix('properties/{property}')->name('properties.')->group(function () {
        // Resend landlord invitation
        Route::post('/resend-landlord-invitation', [PropertyInvitationController::class, 'resendLandlordInvitation'])
            ->name('resend-landlord-invitation');
        
        // Test SMS invitation
        Route::post('/test-sms-invitation', [PropertyInvitationController::class, 'testSmsInvitation'])
            ->name('test-sms-invitation');
        
        // Get SMS invitation statistics
        Route::get('/sms-invitation-stats', [PropertyInvitationController::class, 'getSmsInvitationStats'])
            ->name('sms-invitation-stats');
        
        // ✅ ADDED: Property invitation management
        Route::get('/invitations', [LandlordInvitationController::class, 'getPropertyInvitations'])
            ->name('invitations');
        Route::post('/send-invitation', [LandlordInvitationController::class, 'store'])
            ->name('send-invitation');
    });



// Field Agent-specific routes
Route::middleware(['auth', 'multi.auth.user:4'])->prefix('field-agent')->name('field-agent.')->group(function () {
    // Field Agent can resend invitations for properties they registered
    Route::prefix('properties/{property}')->name('properties.')->group(function () {
        Route::post('/resend-landlord-invitation', [PropertyInvitationController::class, 'resendLandlordInvitation'])
            ->name('resend-landlord-invitation')
            ->middleware('can:update,property');
        
        Route::get('/sms-invitation-stats', [PropertyInvitationController::class, 'getSmsInvitationStats'])
            ->name('sms-invitation-stats')
            ->middleware('can:view,property');
    });
});



/*
|--------------------------------------------------------------------------
| Payment Management Routes
|--------------------------------------------------------------------------
*/

// Landlord payment management
Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    // Payment form and processing
    Route::get('/properties/{propertyId}/pay', [PaymentController::class, 'showPaymentForm'])->name('payments.create');
    Route::post('/payments/process', [PaymentController::class, 'processPayment'])->name('payments.process');
    
    // ✅ ADDED: Payment callback route (required for Paystack and other providers)
    Route::get('/payments/callback/{provider}', [PaymentController::class, 'handleCallback'])->name('payments.callback');
    
    // Payment verification routes
    Route::get('/payments/verify/{transactionId}', [PaymentController::class, 'showVerificationForm'])->name('payments.verify.form');
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment'])->name('payments.verify');
    Route::post('/payments/resend-verification/{transactionId}', [PaymentController::class, 'resendVerification'])->name('payments.resend-verification');
    
    // Payment confirmation and history
    Route::get('/payments/confirmation/{transactionId}', [PaymentController::class, 'showConfirmation'])->name('payments.confirmation');
    Route::get('/payments/history', [PaymentController::class, 'paymentHistory'])->name('payments.history');
    
    // Payment operations
    Route::delete('/payments/{transactionId}/cancel', [PaymentController::class, 'cancelPayment'])->name('payments.cancel');
    
    // Payment information
    Route::get('/payments/status/{transactionId}', [PaymentController::class, 'checkPaymentStatus'])->name('payments.status');
    Route::get('/payments/details/{paymentId}', [PaymentController::class, 'getPaymentDetails'])->name('payments.details');
});


// ============================================================
// Admin Payment Management (Super Admin and Admin)
// ============================================================
Route::middleware(['auth', 'multi.auth.user:0,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // ---------- STATIC ROUTES (must come before /{id}) ----------

        // Payment listing
        Route::get('/payments', [PaymentController::class, 'index'])
            ->name('payments.index');

        // Export and statistics
        Route::get('/payments/export', [PaymentController::class, 'export'])
            ->name('payments.export');
        Route::get('/payments/statistics', [PaymentController::class, 'getStatistics'])
            ->name('payments.statistics');

        // Configuration and instructions
        Route::get('/payments/configuration', [PaymentController::class, 'checkConfiguration'])
            ->name('payments.configuration');
        Route::get('/payments/instructions/{method}', [PaymentController::class, 'getPaymentInstructions'])
            ->name('payments.instructions');

        // Payment confirmation actions (specific action segments)
        Route::post('/payments/bank-transfer/{transactionId}/confirm', [PaymentController::class, 'confirmBankTransfer'])
            ->name('payments.bank-transfer.confirm');
        Route::post('/payments/cash/{transactionId}/collect', [PaymentController::class, 'markCashCollected'])
            ->name('payments.cash.collect');

        // ---------- PARAMETERIZED ROUTES (specific first, broad last) ----------

        // Payment verification management
        Route::post('/payments/{transactionId}/resend-verification', [PaymentController::class, 'resendVerification'])
            ->name('payments.resend-verification');
        Route::post('/payments/{transactionId}/force-verify', [PaymentController::class, 'forceVerifyPayment'])
            ->name('payments.force-verify');

        // ✅ Status update — registered as PUT to match the Blade form's @method('PUT')
        Route::put('/payments/{id}/status', [PaymentController::class, 'updateStatus'])
            ->name('payments.updateStatus');

        // ---------- BROADEST ROUTE (must come LAST) ----------

        // Show a single payment — matches /payments/{id} and nothing else
        Route::get('/payments/{id}', [PaymentController::class, 'showAdmin'])
            ->name('payments.show');

    });


// Payment processing routes for all authenticated users (Generic routes)
Route::middleware(['auth'])->group(function () {
    // Core payment processing
    Route::post('/payments/process', [PaymentController::class, 'processPayment'])->name('payments.process');
    
    // Payment verification
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment'])->name('payments.verify');
    
    // Payment status and information
    Route::get('/payments/status/{transactionId}', [PaymentController::class, 'checkPaymentStatus'])->name('payments.status');
    Route::get('/payment-instructions/{method}', [PaymentController::class, 'getPaymentInstructions'])->name('payment-instructions');
    Route::get('/available-payment-methods', [PaymentController::class, 'getAvailablePaymentMethods'])->name('available-payment-methods');
    
    // Payment configuration and provider status
    Route::get('/payment-configuration', [PaymentController::class, 'checkConfiguration'])->name('payment-configuration');
});

// Payment callback and webhook routes (no auth required - called by payment providers)
Route::prefix('payments')->name('payments.')->group(function () {
    // Public callback routes (no auth - for provider redirects)
    Route::get('/callback/{provider}', [PaymentController::class, 'handleCallback'])->name('callback');
    Route::post('/callback/{provider}', [PaymentController::class, 'handleCallback'])->name('callback.post');
    Route::post('/webhook/{provider}', [PaymentController::class, 'handleWebhook'])->name('webhook');
    
    // Provider-specific webhook routes (direct URLs for payment providers)
    Route::post('/webhook/expresspay', [PaymentController::class, 'handleExpressPayWebhook'])->name('webhook.expresspay');
    Route::post('/webhook/hubtel', [PaymentController::class, 'handleHubtelWebhook'])->name('webhook.hubtel');
    Route::post('/webhook/paystack', [PaymentController::class, 'handlePaystackWebhook'])->name('webhook.paystack');
    Route::post('/webhook/flutterwave', [PaymentController::class, 'handleFlutterwaveWebhook'])->name('webhook.flutterwave');
});

/*
|--------------------------------------------------------------------------
| Developer Tools Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    // System diagnostics (Legacy routes - Redirect to new dashboard)
    Route::redirect('/diagnostics', '/developer/dashboard')->name('diagnostics');
    Route::redirect('/api-status', '/developer/health')->name('api-status');
    Route::redirect('/database-health', '/developer/database')->name('database-health');
    
    // Log management (Legacy routes - Redirect to new logs system)
    Route::redirect('/logs', '/developer/logs')->name('logs');
    Route::redirect('/logs/clear', '/developer/logs/clear/all')->name('logs.clear');
    Route::redirect('/logs/download', '/developer/logs/export')->name('logs.download');
    
    // Performance monitoring (Legacy routes - Redirect to new performance system)
    Route::redirect('/performance', '/developer/performance')->name('performance');
    Route::redirect('/cache-status', '/developer/cache')->name('cache-status');
    Route::redirect('/cache-clear', '/developer/cache/clear')->name('cache-clear');
});

/*
|--------------------------------------------------------------------------
| Test route to check middleware and payment system - UPDATED WITH NEW CONTROLLERS
|--------------------------------------------------------------------------
*/

Route::get('/test-middleware', function () {
    try {
        $paymentService = app(\App\Services\PaymentService::class);
        $status = $paymentService->checkPaymentMethodConfiguration();
        
        return response()->json([
            'success' => true,
            'message' => 'Middleware test successful',
            'middleware_loaded' => class_exists(\App\Http\Middleware\MultiAuthUser::class),
            'payment_service_loaded' => class_exists(\App\Services\PaymentService::class),
            'sms_service_loaded' => class_exists(\App\Services\SmsService::class),
            'payment_provider_controller_loaded' => class_exists(\App\Http\Controllers\Admin\PaymentProviderController::class),
            'sms_provider_controller_loaded' => class_exists(\App\Http\Controllers\Admin\SmsProviderController::class),
            'payment_controller_loaded' => class_exists(\App\Http\Controllers\PaymentController::class),
            'user_controller_loaded' => class_exists(\App\Http\Controllers\UserController::class),
            'super_admin_controller_loaded' => class_exists(\App\Http\Controllers\SuperAdminController::class),
            'user_invitation_controller_loaded' => class_exists(\App\Http\Controllers\UserInvitationController::class),
            'landlord_invitation_controller_loaded' => class_exists(\App\Http\Controllers\LandlordInvitationController::class),
            // ✅ ADDED: New split controllers
            'email_config_controller_loaded' => class_exists(\App\Http\Controllers\Admin\EmailConfigController::class),
            'whatsapp_config_controller_loaded' => class_exists(\App\Http\Controllers\Admin\WhatsAppConfigController::class),
            'payment_config_controller_loaded' => class_exists(\App\Http\Controllers\Admin\PaymentConfigController::class),
            'logo_controller_loaded' => class_exists(\App\Http\Controllers\Admin\LogoController::class),
            'system_info_controller_loaded' => class_exists(\App\Http\Controllers\Admin\SystemInfoController::class),
            // ✅ ADDED: New Registration Plan Split Controllers
            'registration_plan_agent_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanAgentController::class),
            'registration_plan_status_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanStatusController::class),
            'registration_plan_trash_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanTrashController::class),
            'registration_plan_pattern_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanPatternController::class),
            'registration_plan_export_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanExportController::class),
            'registration_plan_analytics_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanAnalyticsController::class),
            'registration_plan_service_controller_loaded' => class_exists(\App\Http\Controllers\RegistrationPlanServiceController::class),
            // ✅ ADDED: Tenant Invitation Controller
            'tenant_invitation_controller_loaded' => class_exists(\App\Http\Controllers\TenantInvitationController::class),
            // ✅ ADDED: Property Unit Controller
            'property_unit_controller_loaded' => class_exists(\App\Http\Controllers\PropertyUnitController::class),
            // ✅ ADDED: Developer Dashboard Controllers
            'developer_dashboard_controller_loaded' => class_exists(\App\Http\Controllers\Developer\DashboardController::class),
            'developer_log_controller_loaded' => class_exists(\App\Http\Controllers\Developer\LogController::class),
            'developer_system_health_controller_loaded' => class_exists(\App\Http\Controllers\Developer\SystemHealthController::class),
            'developer_maintenance_controller_loaded' => class_exists(\App\Http\Controllers\Developer\MaintenanceController::class),
            'developer_emergency_mode_controller_loaded' => class_exists(\App\Http\Controllers\Developer\EmergencyModeController::class),
            // ✅ ADDED: New Property Split Controllers
            'property_trash_controller_loaded' => class_exists(\App\Http\Controllers\PropertyTrashController::class),
            'property_invitation_controller_loaded' => class_exists(\App\Http\Controllers\PropertyInvitationController::class),
            // ✅ UPDATED: Enhanced developer dashboard routes check
            'developer_dashboard_routes_available' => [
                'index' => Route::has('developer.dashboard'),
                'metrics' => Route::has('developer.dashboard.metrics'),
                'data' => Route::has('developer.dashboard.data'),
                'errors' => Route::has('developer.dashboard.errors'),
                'maintenance_schedule' => Route::has('developer.dashboard.maintenance-schedule'),
                'impact_analysis' => Route::has('developer.dashboard.impact-analysis'),
                'alerts' => Route::has('developer.dashboard.alerts'),
                'trends' => Route::has('developer.dashboard.trends'),
                'run_diagnostics' => Route::has('developer.dashboard.run-diagnostics'),
                'run_command' => Route::has('developer.dashboard.run-command'),
                'generate_health_report' => Route::has('developer.dashboard.generate-health-report')
            ],
            'developer_log_routes_available' => [
                'index' => Route::has('developer.logs.index'),
                'overview' => Route::has('developer.logs.overview'),
                'statistics' => Route::has('developer.logs.statistics'),
                'errors' => Route::has('developer.logs.errors'),
                'warnings' => Route::has('developer.logs.warnings'),
                'critical' => Route::has('developer.logs.critical'),
                'debug' => Route::has('developer.logs.debug'),
                'info' => Route::has('developer.logs.info'),
                'search' => Route::has('developer.logs.search'),
                'filter' => Route::has('developer.logs.filter'),
                'by_date' => Route::has('developer.logs.by-date'),
                'by_level' => Route::has('developer.logs.by-level'),
                'by_source' => Route::has('developer.logs.by-source'),
                'by_user' => Route::has('developer.logs.by-user'),
                'show' => Route::has('developer.logs.show'),
                'update' => Route::has('developer.logs.update'),
                'destroy' => Route::has('developer.logs.destroy'),
                'resolve' => Route::has('developer.logs.resolve'),
                'unresolve' => Route::has('developer.logs.unresolve'),
                'bulk_resolve' => Route::has('developer.logs.bulk.resolve'),
                'bulk_delete' => Route::has('developer.logs.bulk.delete'),
                'bulk_archive' => Route::has('developer.logs.bulk.archive'),
                'clear_all' => Route::has('developer.logs.clear.all'),
                'clear_old' => Route::has('developer.logs.clear.old'),
                'analysis' => Route::has('developer.logs.analysis'),
                'trends' => Route::has('developer.logs.trends'),
                'generate_report' => Route::has('developer.logs.generate-report'),
                'report' => Route::has('developer.logs.report'),
                'report_download' => Route::has('developer.logs.report.download'),
                'export' => Route::has('developer.logs.export'),
                'export_csv' => Route::has('developer.logs.export.csv'),
                'export_json' => Route::has('developer.logs.export.json'),
                'export_pdf' => Route::has('developer.logs.export.pdf'),
                'stream' => Route::has('developer.logs.stream'),
                'live' => Route::has('developer.logs.live'),
                'realtime' => Route::has('developer.logs.realtime'),
                'settings' => Route::has('developer.logs.settings'),
                'settings_update' => Route::has('developer.logs.settings.update'),
                'settings_retention' => Route::has('developer.logs.settings.retention'),
                'settings_levels' => Route::has('developer.logs.settings.levels'),
                'archives' => Route::has('developer.logs.archives'),
                'archives_show' => Route::has('developer.logs.archives.show'),
                'archives_restore' => Route::has('developer.logs.archives.restore'),
                'archives_delete' => Route::has('developer.logs.archives.delete')
            ],
            'developer_health_routes_available' => [
                'index' => Route::has('developer.health.index'),
                'overall' => Route::has('developer.health.overall'),
                'status' => Route::has('developer.health.status'),
                'metrics' => Route::has('developer.health.metrics'),
                'live' => Route::has('developer.health.live'),
                'server' => Route::has('developer.health.server'),
                'database' => Route::has('developer.health.database'),
                'application' => Route::has('developer.health.application'),
                'services' => Route::has('developer.health.services'),
                'performance' => Route::has('developer.health.performance'),
                'security' => Route::has('developer.health.security'),
                'run_diagnostics' => Route::has('developer.health.run-diagnostics'),
                'diagnostics_result' => Route::has('developer.health.diagnostics.result'),
                'generate_report' => Route::has('developer.health.generate-report'),
                'report' => Route::has('developer.health.report'),
                'report_download' => Route::has('developer.health.report.download'),
                'history' => Route::has('developer.health.history'),
                'trends' => Route::has('developer.health.trends'),
                'alerts' => Route::has('developer.health.alerts'),
                'checks_custom' => Route::has('developer.health.checks.custom'),
                'checks_list' => Route::has('developer.health.checks.list'),
                'checks_show' => Route::has('developer.health.checks.show')
            ],
            'developer_maintenance_routes_available' => [
                'index' => Route::has('developer.maintenance.index'),
                'schedule' => Route::has('developer.maintenance.schedule'),
                'calendar' => Route::has('developer.maintenance.calendar'),
                'upcoming' => Route::has('developer.maintenance.upcoming'),
                'active' => Route::has('developer.maintenance.active'),
                'history' => Route::has('developer.maintenance.history'),
                'create' => Route::has('developer.maintenance.create'),
                'store' => Route::has('developer.maintenance.store'),
                'show' => Route::has('developer.maintenance.show'),
                'edit' => Route::has('developer.maintenance.edit'),
                'update' => Route::has('developer.maintenance.update'),
                'destroy' => Route::has('developer.maintenance.destroy'),
                'start' => Route::has('developer.maintenance.start'),
                'complete' => Route::has('developer.maintenance.complete'),
                'cancel' => Route::has('developer.maintenance.cancel'),
                'reschedule' => Route::has('developer.maintenance.reschedule'),
                'extend' => Route::has('developer.maintenance.extend'),
                'notify' => Route::has('developer.maintenance.notify'),
                'notify_users' => Route::has('developer.maintenance.notify-users'),
                'notify_admins' => Route::has('developer.maintenance.notify-admins'),
                'impact' => Route::has('developer.maintenance.impact'),
                'affected_users' => Route::has('developer.maintenance.affected-users'),
                'affected_modules' => Route::has('developer.maintenance.affected-modules'),
                'report' => Route::has('developer.maintenance.report'),
                'report_download' => Route::has('developer.maintenance.report.download'),
                'bulk_schedule' => Route::has('developer.maintenance.bulk.schedule'),
                'bulk_start' => Route::has('developer.maintenance.bulk.start'),
                'bulk_complete' => Route::has('developer.maintenance.bulk.complete'),
                'bulk_cancel' => Route::has('developer.maintenance.bulk.cancel'),
                'templates' => Route::has('developer.maintenance.templates'),
                'templates_show' => Route::has('developer.maintenance.templates.show'),
                'templates_store' => Route::has('developer.maintenance.templates.store'),
                'templates_update' => Route::has('developer.maintenance.templates.update'),
                'templates_destroy' => Route::has('developer.maintenance.templates.destroy'),
                'templates_use' => Route::has('developer.maintenance.templates.use'),
                'settings' => Route::has('developer.maintenance.settings'),
                'settings_update' => Route::has('developer.maintenance.settings.update'),
                'settings_notifications' => Route::has('developer.maintenance.settings.notifications'),
                'settings_notifications_update' => Route::has('developer.maintenance.settings.notifications.update')
            ],
            'developer_emergency_routes_available' => [
                'index' => Route::has('developer.emergency.index'),
                'status' => Route::has('developer.emergency.status'),
                'history' => Route::has('developer.emergency.history'),
                'settings' => Route::has('developer.emergency.settings'),
                'activate' => Route::has('developer.emergency.activate'),
                'deactivate' => Route::has('developer.emergency.deactivate'),
                'extend' => Route::has('developer.emergency.extend'),
                'update' => Route::has('developer.emergency.update'),
                'notify_users' => Route::has('developer.emergency.notify.users'),
                'notify_admins' => Route::has('developer.emergency.notify.admins'),
                'notify_all' => Route::has('developer.emergency.notify.all'),
                'access' => Route::has('developer.emergency.access'),
                'access_users' => Route::has('developer.emergency.access.users'),
                'access_roles' => Route::has('developer.emergency.access.roles'),
                'access_ip' => Route::has('developer.emergency.access.ip'),
                'reports' => Route::has('developer.emergency.reports'),
                'reports_show' => Route::has('developer.emergency.reports.show'),
                'reports_generate' => Route::has('developer.emergency.reports.generate'),
                'reports_download' => Route::has('developer.emergency.reports.download'),
                'settings_update' => Route::has('developer.emergency.settings.update'),
                'settings_triggers' => Route::has('developer.emergency.settings.triggers'),
                'settings_triggers_update' => Route::has('developer.emergency.settings.triggers.update'),
                'settings_notifications' => Route::has('developer.emergency.settings.notifications'),
                'settings_notifications_update' => Route::has('developer.emergency.settings.notifications.update'),
                'settings_access' => Route::has('developer.emergency.settings.access'),
                'settings_access_update' => Route::has('developer.emergency.settings.access.update')
            ],
            // ✅ UPDATED: Enhanced payment provider routes check
            'payment_provider_routes_available' => [
                'index' => Route::has('admin.payment-providers.index'),
                'configure' => Route::has('admin.payment-providers.configure'),
                'reset' => Route::has('admin.payment-providers.reset'),
                'status' => Route::has('admin.payment-providers.status'),
                'immediate_status' => Route::has('admin.payment-providers.immediate-status'),
                'update_status' => Route::has('admin.payment-providers.update-status'),
                'test_connection' => Route::has('admin.payment-providers.test-connection'),
                'verify_environment' => Route::has('admin.payment-providers.verify-environment'),
                'check_job_status' => Route::has('admin.payment-providers.check-job-status'),
                'retry_update' => Route::has('admin.payment-providers.retry-update'),
                'check_pending_updates' => Route::has('admin.payment-providers.check-pending-updates'),
                'clear_states' => Route::has('admin.payment-providers.clear-states'),
                'config' => Route::has('admin.payment-providers.config'),
                'webhook_urls' => Route::has('admin.payment-providers.webhook-urls'),
                'cleanup_backups' => Route::has('admin.payment-providers.cleanup-backups')
            ],
                // ✅ UPDATED: Property Unit Routes Check with streamlined routes
            'property_unit_routes_available' => [
                // ========== ADMIN ROUTES (View Only Access) ==========
                'admin_index' => Route::has('admin.property-units.index'),
                'admin_show' => Route::has('admin.property-units.show'),
                'admin_export' => Route::has('admin.property-units.export'),
                'admin_pending_approvals' => Route::has('admin.property-units.pending-approvals'),
                'admin_approve_tenant' => Route::has('admin.property-units.approve-tenant'),
                'admin_reject_tenant' => Route::has('admin.property-units.reject-tenant'),
                'admin_mark_vacated' => Route::has('admin.property-units.mark-vacated'),
                'admin_lease_management' => Route::has('admin.property-units.lease-management'),
                'admin_maintenance_requests' => Route::has('admin.property-units.maintenance-requests'),
                'admin_financials' => Route::has('admin.property-units.financials'),
                'admin_api_by_property' => Route::has('admin.property-units.api.by-property'),
                'admin_api_statistics' => Route::has('admin.property-units.api.statistics'),
                'admin_api_check_availability' => Route::has('admin.property-units.api.check-availability'),

                // ❌ REMOVED: Admin can no longer create/edit/update/destroy landlord units
                'admin_create' => false, // Route removed
                'admin_store' => false,  // Route removed
                'admin_edit' => false,   // Route removed
                'admin_update' => false, // Route removed
                'admin_destroy' => false, // Route removed
                'admin_assign_tenant_form' => false, // Admin cannot assign tenants directly
                'admin_assign_tenant' => false, // Admin cannot assign tenants directly
                'admin_api_by_property' => Route::has('api.property-units.by-property'), // Changed to shared API

                // ========== LANDLORD ROUTES (Full CRUD Access) ==========
                'landlord_index' => Route::has('landlord.property-units.index'),
                'landlord_create' => Route::has('landlord.property-units.create'),
                'landlord_store' => Route::has('landlord.property-units.store'),
                'landlord_show' => Route::has('landlord.property-units.show'),
                'landlord_edit' => Route::has('landlord.property-units.edit'),
                'landlord_update' => Route::has('landlord.property-units.update'),
                'landlord_destroy' => Route::has('landlord.property-units.destroy'),
                'landlord_export' => Route::has('landlord.property-units.export'),
                'landlord_assign_tenant_form' => Route::has('landlord.property-units.assign-tenant.form'),
                'landlord_assign_tenant' => Route::has('landlord.property-units.assign-tenant'),
                'landlord_mark_vacated' => Route::has('landlord.property-units.mark-vacated'),
                'landlord_lease_management' => Route::has('landlord.property-units.lease-management'),
                'landlord_maintenance_requests' => Route::has('landlord.property-units.maintenance-requests'),
                'landlord_financials' => Route::has('landlord.property-units.financials'),
                'landlord_pending_approvals' => Route::has('landlord.property-units.pending-approvals'),
                'landlord_api_by_property' => Route::has('api.property-units.by-property'), // Shared API
                'landlord_api_statistics' => Route::has('api.property-units.statistics'), // Shared API
                'landlord_api_check_availability' => Route::has('api.property-units.check-availability'), // Shared API

                // ========== TENANT ROUTES (View Own Unit Only) ==========
                'tenant_my_unit' => Route::has('tenant.property-units.my-unit'),
                'tenant_show' => Route::has('tenant.property-units.show'),

                // ========== DEVELOPER ROUTES (Analytics Only - No Unit Access) ==========
                'developer_index' => false, // Route removed - no unit access
                'developer_show' => false,  // Route removed - no unit access
                'developer_export' => false, // Route removed - no unit access
                'developer_api_by_property' => false, // Route removed - no unit access
                'developer_api_statistics' => false, // Route removed - no unit access
                'developer_analytics' => Route::has('developer.property-units.analytics'), // Only analytics access

                // ========== SHARED/API ROUTES ==========
                'shared_show' => Route::has('property-units.show'), // Generic show with authorization
                'shared_index' => Route::has('property-units.index'), // Admin/Landlord only
                'api_by_property' => Route::has('api.property-units.by-property'),
                'api_check_availability' => Route::has('api.property-units.check-availability'),
                'api_statistics' => Route::has('api.property-units.statistics'),
                'api_dashboard_stats' => Route::has('api.property-units.dashboard-stats'),

                // ========== GENERIC ROUTES (For Backward Compatibility) ==========
                'generic_create' => Route::has('property-units.create'),
                'generic_store' => Route::has('property-units.store'),
                'generic_edit' => Route::has('property-units.edit'),
                'generic_update' => Route::has('property-units.update'),
                'generic_destroy' => Route::has('property-units.destroy'),
                'generic_export' => Route::has('property-units.export'),
                'generic_assign_tenant_form' => Route::has('property-units.assign-tenant.form'),
                'generic_assign_tenant' => Route::has('property-units.assign-tenant'),
                'generic_approve_tenant' => Route::has('property-units.approve-tenant'),
                'generic_reject_tenant' => Route::has('property-units.reject-tenant'),
                'generic_mark_vacated' => Route::has('property-units.mark-vacated'),
                'generic_pending_approvals' => Route::has('property-units.pending-approvals'),
                'generic_lease_management' => Route::has('property-units.lease-management'),
                'generic_maintenance_requests' => Route::has('property-units.maintenance-requests'),
                'generic_financials' => Route::has('property-units.financials'),
                'generic_assignment_details' => Route::has('property-units.assignment-details'),

                // ========== NEW ROUTES ADDED ==========
                'generic_assignment_details' => Route::has('property-units.assignment-details'),
                'admin_assignment_details' => Route::has('admin.property-units.assignment-details'),
                'api_dashboard_stats' => Route::has('api.property-units.dashboard-stats'),
            ],
            'real_api_integration' => true,
            'payment_providers_supported' => [
                'mtn_momo' => 'MTN Mobile Money',
                'telecel_money' => 'Telecel Money', 
                'airteltigo_money' => 'AirtelTigo Money',
                'paystack' => 'Paystack'
            ],
            'sms_providers_supported' => [
                'arkesel' => 'Arkesel SMS',
                'twilio' => 'Twilio',
                'africastalking' => 'Africa\'s Talking',
                'hubtel' => 'Hubtel SMS',
                'nalosolutions' => 'Nalo Solutions'
            ],
            'user_management_features' => [
                'user_crud' => true,
                'bulk_actions' => true,
                'phone_verification' => true,
                'invitation_system' => true,
                'status_management' => true,
                'trash_management' => true,
                'export_functionality' => true,
                'pdf_export' => true,
                'security_features' => true,
                'phone_validation' => true,
                'personal_data_export' => true,
                'profile_completion' => true,
                'activity_logging' => true
            ],
            'user_routes_available' => [
                'index' => Route::has('admin.users.index'),
                'create' => Route::has('admin.users.create'),
                'store' => Route::has('admin.users.store'),
                'show' => Route::has('admin.users.show'),
                'edit' => Route::has('admin.users.edit'),
                'update' => Route::has('admin.users.update'),
                'destroy' => Route::has('admin.users.destroy'),
                'trash' => Route::has('admin.users.trash'),
                'restore' => Route::has('admin.users.restore'),
                'force_delete' => Route::has('admin.users.force-delete'),
                'send_invitation' => Route::has('admin.users.send-invitation'),
                'verify_phone' => Route::has('admin.users.verify-phone'),
                'bulk_action' => Route::has('admin.users.bulk-action'),
                'export' => Route::has('admin.users.export'),
                'export_pdf' => Route::has('admin.users.export.pdf'),
                'check_phone' => Route::has('admin.users.check-phone'),
                'user_activity' => Route::has('security.user-activity'),
                'suspicious_activity' => Route::has('security.suspicious-activity'),
                'block_user' => Route::has('security.user.block'),
                'unblock_user' => Route::has('security.user.unblock'),
                'download_data' => Route::has('admin.users.download-data'),
                'statistics' => Route::has('admin.users.statistics'),
                'check_relations' => Route::has('admin.users.check-relations'),
                'deleted_details' => Route::has('admin.users.deleted-details')
            ],
            'profile_routes_available' => [
                'edit' => Route::has('profile.edit'),
                'update' => Route::has('profile.update'),
                'password_update' => Route::has('profile.password.update'),
                'photo_update' => Route::has('profile.photo.update'),
                'photo_remove' => Route::has('profile.photo.remove'),
                'send_verification' => Route::has('profile.send-verification'),
                'verify_phone' => Route::has('profile.verify-phone'),
                'stats' => Route::has('profile.stats'),
                'download_data' => Route::has('profile.download-data'),
                'personal_info_update' => Route::has('profile.personal-info.update'),
                'contact_info_update' => Route::has('profile.contact-info.update'),
                'admin_edit' => Route::has('admin.profile.edit'),
                'admin_update' => Route::has('admin.profile.update'),
                'admin_password_update' => Route::has('admin.profile.password.update'),
                'admin_photo_update' => Route::has('admin.profile.photo.update'),
                'admin_photo_remove' => Route::has('admin.profile.photo.remove'),
                'admin_stats' => Route::has('admin.profile.stats'),
                'admin_download_data' => Route::has('admin.profile.download-data')
            ],
            'field_agent_routes_available' => [
                'dashboard' => Route::has('field-agent.dashboard'),
                'properties_index' => Route::has('field-agent.properties.index'),
                'properties_create' => Route::has('field-agent.properties.create'),
                'properties_store' => Route::has('field-agent.properties.store'),
                'properties_edit' => Route::has('field-agent.properties.edit'),
                'properties_update' => Route::has('field-agent.properties.update'),
                'properties_show' => Route::has('field-agent.properties.show'),
                'properties_my_properties' => Route::has('field-agent.properties.my-properties'),
                'properties_by_registration_plan' => Route::has('field-agent.properties.by-registration-plan'),
                'properties_type_stats' => Route::has('field-agent.properties.type-stats'),
                'properties_by_type' => Route::has('field-agent.properties.by-type'),
                'registration_plans' => Route::has('field-agent.registration-plans.index'),
                'registration_plans_show' => Route::has('field-agent.registration-plans.show'),
                'inspections' => Route::has('field-agent.inspections'),
                'collections' => Route::has('field-agent.collections'),
                'verifications' => Route::has('field-agent.verifications'),
                'profile' => Route::has('field-agent.profile'),
                'profile_update' => Route::has('field-agent.profile.update')
            ],
            'super_admin_routes_available' => [
                'dashboard' => Route::has('super-admin.dashboard'),
                'dashboard_data' => Route::has('super-admin.dashboard.data'),
                'dashboard_refresh' => Route::has('super-admin.dashboard.refresh'),
                'dashboard_export' => Route::has('super-admin.dashboard.export'),
                // ✅ ADDED: Super Admin API endpoints
                'api_user_statistics' => Route::has('super-admin.api.user-statistics'),
                'api_payment_statistics' => Route::has('super-admin.api.payment-statistics'),
                'api_property_statistics' => Route::has('super-admin.api.property-statistics'),
                'api_plan_statistics' => Route::has('super-admin.api.plan-statistics'),
                'api_invitation_statistics' => Route::has('super-admin.api.invitation-statistics'),
                'api_system_health' => Route::has('super-admin.api.system-health'),
                'api_revenue_chart_data' => Route::has('super-admin.api.revenue-chart-data'),
                'api_user_registration_chart_data' => Route::has('super-admin.api.user-registration-chart-data'),
                'api_property_registration_chart_data' => Route::has('super-admin.api.property-registration-chart-data'),
                'api_recent_payments' => Route::has('super-admin.api.recent-payments'),
                'api_recent_users' => Route::has('super-admin.api.recent-users'),
                'api_recent_properties' => Route::has('super-admin.api.recent-properties'),
                'api_recent_registration_plans' => Route::has('super-admin.api.recent-registration-plans'),
                'api_pending_actions' => Route::has('super-admin.api.pending-actions'),
                'api_system_alerts' => Route::has('super-admin.api.system-alerts'),
                'api_quick_stats' => Route::has('super-admin.api.quick-stats'),
                'api_payment_providers_status' => Route::has('super-admin.api.payment-providers-status'),
                'api_sms_providers_status' => Route::has('super-admin.api.sms-providers-status')
            ],
            'admin_routes_available' => [
                'dashboard' => Route::has('admin.dashboard'),
                'dashboard_data' => Route::has('admin.dashboard.data'),
                'dashboard_refresh' => Route::has('admin.dashboard.refresh'),
                'dashboard_export' => Route::has('admin.dashboard.export'),
                'dashboard_statistics' => Route::has('admin.dashboard.statistics'),
                'dashboard_analytics' => Route::has('admin.dashboard.analytics'),
                'dashboard_widgets_update' => Route::has('admin.dashboard.widgets.update'),
                'dashboard_widgets_reset' => Route::has('admin.dashboard.widgets.reset')
            ],
            'security_routes_available' => [
                'dashboard' => Route::has('security.dashboard'),
                'user_activity' => Route::has('security.user-activity'),
                'suspicious_activity' => Route::has('security.suspicious-activity'),
                'user_block' => Route::has('security.user.block'),
                'user_unblock' => Route::has('security.user.unblock'),
                'users_index' => Route::has('security.users.index'),
                'users_show' => Route::has('security.users.show'),
                'users_suspend' => Route::has('security.users.suspend'),
                'users_activate' => Route::has('security.users.activate')
            ],
            'user_types' => [
                'super_admin' => 0,
                'admin' => 1,
                'landlord' => 2,
                'tenant' => 3,
                'field_agent' => 4,
                'developer' => 5,
                'security-personnel' => 6
            ],
            'developer_routes_available' => Route::has('developer.dashboard'),
            'property_routes_available' => [
                'index' => Route::has('properties.index'),
                'create' => Route::has('properties.create'),
                'store' => Route::has('properties.store'),
                'show' => Route::has('properties.show'),
                'edit' => Route::has('properties.edit'),
                'update' => Route::has('properties.update'),
                'destroy' => Route::has('properties.destroy'),
                'trash' => Route::has('properties.trash.index'),
                'restore' => Route::has('properties.trash.restore'),
                'force_delete' => Route::has('properties.trash.force-delete'),
                'restore_all' => Route::has('properties.trash.restore-all'),
                'empty_trash' => Route::has('properties.trash.empty'),
                'export' => Route::has('properties.export'),
                'type_stats' => Route::has('properties.type-stats'),
                'global_sequence_stats' => Route::has('properties.global-sequence-stats'),
                'by_street' => Route::has('properties.by-street'),
                'by_landlord' => Route::has('properties.by-landlord'),
                'by_digital_address' => Route::has('properties.by-digital-address'),
                'by_zone' => Route::has('properties.by-zone'),
                'by_registration_plan' => Route::has('properties.by-registration-plan'),
                'with_digital_address' => Route::has('properties.with-digital-address'),
                'without_digital_address' => Route::has('properties.without-digital-address'),
                'by_type' => Route::has('properties.by-type'),
                'my_properties' => Route::has('properties.my-properties'),
                'properties_properties_show' => Route::has('properties.properties.show'),
                'properties_invitations' => Route::has('properties.invitations'),
                'properties_send_invitation' => Route::has('properties.send-invitation'),
                // ✅ ADDED: New Property Invitation Routes
                'resend_landlord_invitation' => Route::has('properties.resend-landlord-invitation'),
                'test_sms_invitation' => Route::has('properties.test-sms-invitation'),
                'sms_invitation_stats' => Route::has('properties.sms-invitation-stats')
            ],
            // ✅ UPDATED: Registration Plan Routes with Split Controllers
            'registration_plans_routes_available' => [
                'index' => Route::has('registration-plans.index'),
                'create' => Route::has('registration-plans.create'),
                'store' => Route::has('registration-plans.store'),
                'show' => Route::has('registration-plans.show'),
                'edit' => Route::has('registration-plans.edit'),
                'update' => Route::has('registration-plans.update'),
                'destroy' => Route::has('registration-plans.destroy'),
                'trash' => Route::has('registration-plans.trash'),
                'restore' => Route::has('registration-plans.restore'),
                'force_destroy' => Route::has('registration-plans.force-destroy'),
                'empty_trash' => Route::has('registration-plans.empty-trash'),
                // Agent Management
                'add_agent' => Route::has('registration-plans.add-agent'),
                'remove_agent' => Route::has('registration-plans.remove-agent'),
                'reactivate_agent' => Route::has('registration-plans.reactivate-agent'),
                'send_invitation' => Route::has('registration-plans.send-invitation'),
                'send_bulk_invitations' => Route::has('registration-plans.send-bulk-invitations'),
                'assignment_details' => Route::has('registration-plans.assignment-details'),
                // Status Management
                'mark_in_progress' => Route::has('registration-plans.mark-in-progress'),
                'mark_completed' => Route::has('registration-plans.mark-completed'),
                'cancel' => Route::has('registration-plans.cancel'),
                'reactivate' => Route::has('registration-plans.reactivate'),
                // Pattern Management
                'check_global_sequence' => Route::has('registration-plans.check-global-sequence'),
                'validate_pattern' => Route::has('registration-plans.validate-pattern'),
                'get_next_pattern' => Route::has('registration-plans.get-next-pattern'),
                'get_next_pattern_for_plan' => Route::has('registration-plans.get-next-pattern-for-plan'),
                // Export and Bulk Actions
                'export' => Route::has('registration-plans.export'),
                'bulk_actions' => Route::has('registration-plans.bulk-actions'),
                // Analytics and Statistics
                'analytics' => Route::has('registration-plans.analytics'),
                'statistics' => Route::has('registration-plans.statistics'),
                // Service Management
                'service_status' => Route::has('registration-plans.service-status'),
                'test_channels' => Route::has('registration-plans.test-channels')
            ],
            'system_settings_routes_available' => [
                'index' => Route::has('admin.system-settings.index'),
                'create' => Route::has('admin.system-settings.create'),
                'store' => Route::has('admin.system-settings.store'),
                'edit' => Route::has('admin.system-settings.edit'),
                'update' => Route::has('admin.system-settings.update'),
                'destroy' => Route::has('admin.system-settings.destroy'),
                'restore' => Route::has('admin.system-settings.restore'),
                'trash' => Route::has('admin.system-settings.trash'),
                'force_delete' => Route::has('admin.system-settings.force-delete'),
                'retry_env_update' => Route::has('admin.system-settings.retry-env-update'),
                'check_env_update' => Route::has('admin.system-settings.check-env-update'),
                'whatsapp_config_status' => Route::has('admin.system-settings.whatsapp-config-status'),
                'test_whatsapp_config' => Route::has('admin.system-settings.test-whatsapp-config'),
                'get_whatsapp_config' => Route::has('admin.system-settings.get-whatsapp-config'),
                'retry_whatsapp_update' => Route::has('admin.system-settings.retry-whatsapp-update'),
                'check_whatsapp_update' => Route::has('admin.system-settings.check-whatsapp-update'),
                'upload_logo' => Route::has('admin.system-settings.upload-logo'),
                'remove_logo' => Route::has('admin.system-settings.remove-logo'),
                'system_info' => Route::has('admin.system-settings.system-info'),
                'calculate_dues' => Route::has('admin.system-settings.calculate-dues'),
                'bulk_payment_enabled' => Route::has('admin.system-settings.bulk-payment-enabled'),
                'payment_configuration' => Route::has('admin.system-settings.payment-configuration'),
                'available_payment_methods' => Route::has('admin.system-settings.available-payment-methods'),
                'sync_payment_providers' => Route::has('admin.system-settings.sync-payment-providers'),
                'update_payment_recipient' => Route::has('admin.system-settings.update-payment-recipient'),
                'test_payment_recipient' => Route::has('admin.system-settings.test-payment-recipient'),
                'test_env_config' => Route::has('admin.system-settings.test-env-config'),
                'get_env_config' => Route::has('admin.system-settings.get-env-config'),
                'update_env_config' => Route::has('admin.system-settings.update-env-config'),
                'test_email' => Route::has('admin.system-settings.test-email'),
                'email_config_status' => Route::has('admin.system-settings.email-config-status'),
                'update_email_config' => Route::has('admin.system-settings.update-email-config'),
                'test_smtp' => Route::has('admin.system-settings.test-smtp')
            ],
            // ✅ ADDED: New split controller routes
            'email_config_routes_available' => [
                'status' => Route::has('admin.email-config.status'),
                'test_sending' => Route::has('admin.email-config.test-sending'),
                'update' => Route::has('admin.email-config.update'),
                'test_env' => Route::has('admin.email-config.test-env'),
                'env_config' => Route::has('admin.email-config.env-config'),
                'update_env' => Route::has('admin.email-config.update-env'),
                'test_smtp' => Route::has('admin.email-config.test-smtp'),
                'retry_env_update' => Route::has('admin.email-config.retry-env-update')
            ],
            'whatsapp_config_routes_available' => [
                'status' => Route::has('admin.whatsapp-config.status'),
                'test' => Route::has('admin.whatsapp-config.test'),
                'config' => Route::has('admin.whatsapp-config.config'),
                'retry_update' => Route::has('admin.whatsapp-config.retry-update')
            ],
            'payment_config_routes_available' => [
                'configuration' => Route::has('admin.payment-config.configuration'),
                'available_methods' => Route::has('admin.payment-config.available-methods'),
                'sync_providers' => Route::has('admin.payment-config.sync-providers'),
                'update_recipient' => Route::has('admin.payment-config.update-recipient')
            ],
            'logo_routes_available' => [
                'upload' => Route::has('admin.logo.upload'),
                'remove' => Route::has('admin.logo.remove')
            ],
            'system_info_routes_available' => [
                'info' => Route::has('admin.system-info.info'),
                'calculate_dues' => Route::has('admin.system-info.calculate-dues'),
                'bulk_payment_enabled' => Route::has('admin.system-info.bulk-payment-enabled')
            ],
            'landlord_channels_routes_available' => [
                'channels' => Route::has('landlord.channels'),
                'channels_create' => Route::has('landlord.channels.create'),
                'channels_store' => Route::has('landlord.channels.store'),
                'channels_show' => Route::has('landlord.channels.show'),
                'channels_edit' => Route::has('landlord.channels.edit'),
                'channels_update' => Route::has('landlord.channels.update'),
                'channels_destroy' => Route::has('landlord.channels.destroy'),
                'channels_subscribe' => Route::has('landlord.channels.subscribe'),
                'channels_analytics' => Route::has('landlord.channels.analytics'),
                'active_invitations' => Route::has('landlord.active-invitations')
            ],
            'user_invitation_routes_available' => [
                'public_accept' => Route::has('user.invitations.accept'),
                'public_process' => Route::has('user.invitations.process-acceptance'),
                'public_validate' => Route::has('user.invitations.validate'),
                'public_expired' => Route::has('user.invitations.expired'),
                'admin_resend' => Route::has('admin.user-invitations.resend'),
                'admin_status' => Route::has('admin.user-invitations.status'),
                'admin_validate' => Route::has('admin.user-invitations.validate'),
                'admin_statistics' => Route::has('admin.user-invitations.statistics')
            ],
            'landlord_invitation_routes_available' => [
                'public_accept' => Route::has('landlord.invitations.accept'),
                'public_process' => Route::has('landlord.invitations.process-acceptance'),
                'admin_send' => Route::has('admin.landlord-invitations.send'),
                'admin_resend' => Route::has('admin.landlord-invitations.resend'),
                'admin_cancel' => Route::has('admin.landlord-invitations.cancel'),
                'admin_regenerate_token' => Route::has('admin.landlord-invitations.regenerate-token'),
                'admin_property_invitations' => Route::has('admin.landlord-invitations.property'),
                'admin_statistics' => Route::has('admin.landlord-invitations.statistics')
            ],
            // ✅ ADDED: Tenant Invitation Routes
            'tenant_invitation_routes_available' => [
                'public_accept' => Route::has('tenant.invitation.accept'),
                'public_process_acceptance' => Route::has('tenant.invitation.process-acceptance'),
                'public_complete' => Route::has('tenant.invitation.complete'),
                'public_submit' => Route::has('tenant.invitation.submit'),
                'public_expired' => Route::has('tenant.invitation.expired'),
                'public_invalid' => Route::has('tenant.invitation.invalid'),
                'public_success' => Route::has('tenant.invitation.success'),
                'public_validate_token' => Route::has('tenant.invitation.validate-token'),
                'admin_send' => Route::has('admin.tenant-invitations.send'),
                'admin_resend' => Route::has('admin.tenant-invitations.resend'),
                'admin_cancel' => Route::has('admin.tenant-invitations.cancel'),
                'admin_property' => Route::has('admin.tenant-invitations.property'),
                'admin_user' => Route::has('admin.tenant-invitations.user'),
                'admin_statistics' => Route::has('admin.tenant-invitations.statistics'),
                'admin_show' => Route::has('admin.tenant-invitations.show'),
                'admin_test_delivery' => Route::has('admin.tenant-invitations.test-delivery'),
                'admin_index' => Route::has('admin.tenant-invitations.index'),
                'landlord_send' => Route::has('landlord.tenant-invitations.send'),
                'landlord_index' => Route::has('landlord.tenant-invitations.index'),
                'landlord_property' => Route::has('landlord.tenant-invitations.property'),
                'landlord_resend' => Route::has('landlord.tenant-invitations.resend'),
                'landlord_cancel' => Route::has('landlord.tenant-invitations.cancel')
            ],
            // ✅ ADDED: Photo management routes
            'photo_routes_available' => [
                'profile_photo_update' => Route::has('profile.photo.update'),
                'profile_photo_remove' => Route::has('profile.photo.remove'),
                'admin_profile_photo_update' => Route::has('admin.profile.photo.update'),
                'admin_profile_photo_remove' => Route::has('admin.profile.photo.remove'),
                'admin_user_photo_update' => Route::has('admin.users.photo.update'),
                'admin_user_photo_remove' => Route::has('admin.users.photo.remove'),
                'admin_user_photo_check_access' => Route::has('admin.users.photo.check-access')
            ],
            // ✅ ADDED: New property split controller routes
            'property_trash_routes_available' => [
                'index' => Route::has('properties.trash.index'),
                'restore' => Route::has('properties.trash.restore'),
                'force_delete' => Route::has('properties.trash.force-delete'),
                'restore_all' => Route::has('properties.trash.restore-all'),
                'empty' => Route::has('properties.trash.empty')
            ],
            'property_invitation_routes_available' => [
                'resend_landlord_invitation' => Route::has('properties.resend-landlord-invitation'),
                'test_sms_invitation' => Route::has('properties.test-sms-invitation'),
                'sms_invitation_stats' => Route::has('properties.sms-invitation-stats')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Middleware test failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->middleware('multi.auth.user:0,1,2,3,4,5,6'); // Allow all user types for testing


Route::get('/api/payments/debug/mtn', function() {
    $paymentService = app(PaymentService::class);
    
    return response()->json([
        'debug' => $paymentService->debugMTNCredentials(),
        'connection_test' => $paymentService->testMTNConnection(),
        'env_variables' => [
            'MTN_MOMO_ENVIRONMENT' => env('MTN_MOMO_ENVIRONMENT'),
            'MTN_MOMO_API_KEY_LENGTH' => strlen(env('MTN_MOMO_API_KEY')),
            'MTN_MOMO_SUBSCRIPTION_KEY_LENGTH' => strlen(env('MTN_MOMO_SUBSCRIPTION_KEY')),
            'MTN_MOMO_API_KEY_PREVIEW' => substr(env('MTN_MOMO_API_KEY'), 0, 8) . '...',
            'MTN_MOMO_SUBSCRIPTION_KEY_PREVIEW' => substr(env('MTN_MOMO_SUBSCRIPTION_KEY'), 0, 8) . '...',
            'APP_ENV' => env('APP_ENV'),
            'APP_DEBUG' => env('APP_DEBUG')
        ]
    ]);
});


/*
|--------------------------------------------------------------------------
| ✅ FIXED: Security Schedule Management Routes (Admin Only - Middleware 0,1)
|--------------------------------------------------------------------------
| CRITICAL FIX: Route order matters! Specific routes MUST come before 
| parameterized routes to prevent '/trash' being caught as '{schedule}'
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // =============================================
    // SECURITY SCHEDULE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('security-schedules')->name('security-schedules.')->group(function () {
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 1: SPECIFIC STATIC ROUTES (NO PARAMETERS)
        // ------------------------------------------------------------------
        // IMPORTANT: These must come BEFORE any route with {parameters}
        // ------------------------------------------------------------------
        
        // === 1.1 DASHBOARD & LIST VIEWS ===
        Route::get('/', [AdminSecurityScheduleController::class, 'index'])->name('index');
        
        // === 1.2 🗑️ TRASH MANAGEMENT - CRITICAL: MUST BE BEFORE WILDCARD ===
        Route::get('/trash', [AdminSecurityScheduleController::class, 'trash'])->name('trash');
        Route::post('/bulk-restore', [AdminSecurityScheduleController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-force-delete', [AdminSecurityScheduleController::class, 'bulkForceDelete'])->name('bulk-force-delete');
        Route::delete('/clear-old-trash', [AdminSecurityScheduleController::class, 'clearOldTrash'])->name('clear-old-trash');
        
        // === 1.3 CREATE & FORM ROUTES ===
        Route::get('/create', [AdminSecurityScheduleController::class, 'create'])->name('create');
        Route::post('/', [AdminSecurityScheduleController::class, 'store'])->name('store');
        
        // === 1.4 CALENDAR & EXPORT VIEWS ===
        Route::get('/calendar', [AdminSecurityScheduleController::class, 'calendar'])->name('calendar');
        Route::get('/date-details', [AdminSecurityScheduleController::class, 'getDateDetails'])->name('date-details');
        Route::get('/reports', [AdminSecurityScheduleController::class, 'reports'])->name('reports');
        Route::get('/analytics', [AdminSecurityScheduleController::class, 'analytics'])->name('analytics');
        Route::get('/export', [AdminSecurityScheduleController::class, 'export'])->name('export');
        Route::get('/export-schedules', [AdminSecurityScheduleController::class, 'exportSchedules'])->name('export-schedules');
        Route::get('/export-pdf', [AdminSecurityScheduleController::class, 'exportPDF'])->name('export-pdf');
        
        // === 1.5 QR CODE STATISTICS ===
        Route::get('/qr-code-statistics', [AdminSecurityScheduleController::class, 'getQrCodeStatistics'])->name('qr-code-statistics');
        
        // === 1.6 AVAILABILITY & CAPACITY CHECKS ===
        Route::get('/available-personnel', [AdminSecurityScheduleController::class, 'getAvailablePersonnel'])->name('available-personnel');
        Route::get('/post-capacity', [AdminSecurityScheduleController::class, 'getPostCapacity'])->name('post-capacity');
        Route::get('/handover-info', [AdminSecurityScheduleController::class, 'getHandoverInfo'])->name('handover-info');
        Route::get('/personnel-availability', [AdminSecurityScheduleController::class, 'getPersonnelAvailability'])->name('personnel-availability');
        Route::get('/shift-overlap-report', [AdminSecurityScheduleController::class, 'getShiftOverlapReport'])->name('shift-overlap-report');
        
        // === 1.7 STATISTICS & ANALYTICS ===
        Route::get('/statistics', [AdminSecurityScheduleController::class, 'getStatistics'])->name('statistics');
        Route::get('/schedule-statistics', [AdminSecurityScheduleController::class, 'getScheduleStatistics'])->name('schedule-statistics');
        Route::get('/schedule-analytics', [AdminSecurityScheduleController::class, 'getScheduleAnalytics'])->name('schedule-analytics');
        Route::get('/today-summary', [AdminSecurityScheduleController::class, 'getTodayScheduleSummary'])->name('today-summary');
        Route::get('/upcoming-handovers', [AdminSecurityScheduleController::class, 'getUpcomingHandovers'])->name('upcoming-handovers');
        Route::get('/personnel-performance', [AdminSecurityScheduleController::class, 'getPersonnelPerformance'])->name('personnel-performance');
        Route::get('/post-coverage', [AdminSecurityScheduleController::class, 'getPostCoverage'])->name('post-coverage');
        Route::get('/verification-statistics', [AdminSecurityScheduleController::class, 'getVerificationStatistics'])->name('verification-statistics');
        
        // === 1.8 TEMPLATE & PATTERN MANAGEMENT ===
        Route::get('/templates', [AdminSecurityScheduleController::class, 'getTemplates'])->name('templates.index');
        Route::get('/templates/list', [AdminSecurityScheduleController::class, 'getTemplatesList'])->name('templates.list');
        Route::post('/templates', [AdminSecurityScheduleController::class, 'createTemplate'])->name('templates.create');
        Route::post('/templates/{templateId}/generate', [AdminSecurityScheduleController::class, 'generateFromTemplate'])->name('templates.generate');
        Route::delete('/templates/{template}', [AdminSecurityScheduleController::class, 'deleteTemplate'])->name('templates.delete');
        
        // === 1.9 ROTATION PATTERNS ===
        Route::get('/rotation-patterns', [AdminSecurityScheduleController::class, 'getRotationPatterns'])->name('rotation-patterns');
        Route::post('/rotation-patterns', [AdminSecurityScheduleController::class, 'saveRotationPattern'])->name('rotation-patterns.save');
        Route::get('/rotation-dashboard', [AdminSecurityScheduleController::class, 'rotationDashboard'])->name('rotation-dashboard');
        
        // === 1.10 SHIFT GROUP ROTATION ===
        Route::post('/rotate-shift-groups', [AdminSecurityScheduleController::class, 'rotateShiftGroups'])->name('rotate-shift-groups');
        Route::get('/rotation-history/{scheduleId}', [AdminSecurityScheduleController::class, 'getRotationHistory'])->name('rotation-history');
        Route::post('/apply-rotation/{scheduleId}', [AdminSecurityScheduleController::class, 'applyRotation'])->name('apply-rotation');
        Route::post('/revert-rotation/{scheduleId}', [AdminSecurityScheduleController::class, 'revertRotation'])->name('revert-rotation');
        Route::get('/rotation-candidates', [AdminSecurityScheduleController::class, 'getRotationCandidates'])->name('rotation-candidates');
        Route::get('/rotation-summary', [AdminSecurityScheduleController::class, 'getRotationSummary'])->name('rotation-summary');
        
        // === 1.11 PREFERENCE SCORING ===
        Route::post('/calculate-preferences', [AdminSecurityScheduleController::class, 'calculatePreferenceScores'])->name('calculate-preferences');
        Route::get('/preference-scores', [AdminSecurityScheduleController::class, 'getPreferenceScores'])->name('preference-scores');
        
        // === 1.12 NOTIFICATION TEMPLATES ===
        Route::get('/notification-templates', [AdminSecurityScheduleController::class, 'getNotificationTemplates'])->name('notification-templates');
        
        // === 1.13 DASHBOARD WIDGETS & REPORTS ===
        Route::get('/dashboard-widgets', [AdminSecurityScheduleController::class, 'getDashboardWidgets'])->name('dashboard-widgets');
        Route::get('/attendance-report', [AdminSecurityScheduleController::class, 'getAttendanceReport'])->name('attendance-report');
        Route::get('/coverage-gaps', [AdminSecurityScheduleController::class, 'getCoverageGaps'])->name('coverage-gaps');
        Route::get('/audit-logs', [AdminSecurityScheduleController::class, 'getAuditLogs'])->name('audit-logs');
        
        // === 1.14 BULK ASSIGN PREVIEW ROUTE ===
        Route::post('/bulk-assign-preview', [AdminSecurityScheduleController::class, 'previewBulkAssign'])->name('bulk-assign-preview');
        
        // === 1.15 QUICK ROTATE ROUTE ===
        Route::post('/quick-rotate', [AdminSecurityScheduleController::class, 'quickRotate'])->name('quick-rotate');
        
        // === 1.16 ROTATION ELIGIBILITY CHECK ===
        Route::get('/rotation-eligible/{scheduleId}', [AdminSecurityScheduleController::class, 'isEligibleForRotation'])->name('rotation-eligible');
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 2: PARAMETERIZED ROUTES (WITH {PARAMETERS})
        // ------------------------------------------------------------------
        // IMPORTANT: These come AFTER all static routes to prevent conflicts
        // Order within this section also matters - more specific first
        // ------------------------------------------------------------------
        
        // === 2.1 PARAMETERIZED GET ROUTES ===
        Route::get('/change-history/{schedule}', [AdminSecurityScheduleController::class, 'getChangeHistory'])->name('change-history');
        Route::get('/{schedule}/edit', [AdminSecurityScheduleController::class, 'edit'])->name('edit');
        Route::get('/{schedule}', [AdminSecurityScheduleController::class, 'show'])->name('show');
        
        // === 2.2 PARAMETERIZED UPDATE/DELETE ROUTES ===
        Route::put('/{schedule}', [AdminSecurityScheduleController::class, 'update'])->name('update');
        Route::delete('/{schedule}', [AdminSecurityScheduleController::class, 'destroy'])->name('destroy');
        Route::patch('/{schedule}/restore', [AdminSecurityScheduleController::class, 'restore'])->name('restore');
        Route::delete('/{schedule}/force-delete', [AdminSecurityScheduleController::class, 'forceDelete'])->name('force-delete');
        
        // === 2.3 PARAMETERIZED STATUS ROUTES (Using scheduleId, not schedule) ===
        Route::put('/{scheduleId}/status', [AdminSecurityScheduleController::class, 'updateStatus'])->name('update-status');
        Route::put('/{scheduleId}/complete-handover', [AdminSecurityScheduleController::class, 'completeHandover'])->name('complete-handover');
        Route::post('/{scheduleId}/break', [AdminSecurityScheduleController::class, 'manageBreak'])->name('manage-break');
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 3: BULK OPERATIONS & ACTIONS
        // ------------------------------------------------------------------
        // These don't conflict with parameterized routes
        // ------------------------------------------------------------------
        
        // === 3.1 BULK OPERATIONS ===
        Route::post('/bulk-assign', [AdminSecurityScheduleController::class, 'bulkAssign'])->name('bulk-assign');
        Route::post('/bulk-update', [AdminSecurityScheduleController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/bulk-delete', [AdminSecurityScheduleController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/bulk-status', [AdminSecurityScheduleController::class, 'bulkStatus'])->name('bulk-status');
        
        // === 3.2 BULK ROTATION OPERATIONS ===
        Route::post('/bulk-rotate', [AdminSecurityScheduleController::class, 'bulkRotate'])->name('bulk-rotate');
        Route::post('/bulk-swap-groups', [AdminSecurityScheduleController::class, 'bulkSwapGroups'])->name('bulk-swap-groups');
        Route::post('/bulk-calculate-preferences', [AdminSecurityScheduleController::class, 'bulkCalculatePreferences'])->name('bulk-calculate-preferences');
        
        // === 3.3 SHIFT OPERATIONS ===
        Route::post('/swap', [AdminSecurityScheduleController::class, 'swapShifts'])->name('swap');
        Route::post('/quick-assign', [AdminSecurityScheduleController::class, 'quickAssign'])->name('quick-assign');
        Route::post('/send-notifications', [AdminSecurityScheduleController::class, 'sendNotifications'])->name('send-notifications');
        
        // === 3.4 SCHEDULE GENERATION ===
        Route::post('/generate-roster', [AdminSecurityScheduleController::class, 'generateRoster'])->name('generate-roster');
        Route::post('/generate-month', [AdminSecurityScheduleController::class, 'generateMonth'])->name('generate-month');
        Route::post('/copy-to-week', [AdminSecurityScheduleController::class, 'copyToWeek'])->name('copy-to-week');
        Route::post('/import', [AdminSecurityScheduleController::class, 'importSchedules'])->name('import');
        
        // === 3.5 VALIDATION ===
        Route::post('/validate-constraints', [AdminSecurityScheduleController::class, 'validateConstraints'])->name('validate-constraints');
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 4: API-STYLE ROUTES FOR AJAX (Web accessible)
        // ------------------------------------------------------------------
        // These are web routes with /api prefix for JavaScript calls
        // They remain accessible and don't conflict with parameterized routes
        // ------------------------------------------------------------------
        
        Route::prefix('api')->name('api.')->group(function () {
            
            // === 4.1 CAPACITY & AVAILABILITY ===
            Route::get('/available-slots', [AdminSecurityScheduleController::class, 'getAvailableSlots'])->name('available-slots');
            Route::get('/post-capacity', [AdminSecurityScheduleController::class, 'getPostCapacity'])->name('post-capacity');
            Route::get('/handover-info', [AdminSecurityScheduleController::class, 'getHandoverInfo'])->name('handover-info');
            Route::get('/personnel-availability', [AdminSecurityScheduleController::class, 'getPersonnelAvailability'])->name('personnel-availability');
            Route::get('/check-availability', [AdminSecurityScheduleController::class, 'checkAvailability'])->name('check-availability');
            
            // === 4.2 SHIFT & CONSTRAINT CHECKS ===
            Route::get('/shift-applicability', [AdminSecurityScheduleController::class, 'checkShiftApplicability'])->name('shift-applicability');
            Route::get('/personnel-constraints', [AdminSecurityScheduleController::class, 'checkPersonnelConstraints'])->name('personnel-constraints');
            Route::get('/constraint-check', [AdminSecurityScheduleController::class, 'checkAllConstraints'])->name('constraint-check');
            
            // === 4.3 ROTATION & SEQUENCES ===
            Route::get('/rotation-sequence', [AdminSecurityScheduleController::class, 'getRotationSequence'])->name('rotation-sequence');
            Route::get('/rotation-patterns', [AdminSecurityScheduleController::class, 'getRotationPatterns'])->name('rotation-patterns');
            Route::get('/rotation-candidates', [AdminSecurityScheduleController::class, 'getRotationCandidates'])->name('rotation-candidates');
            Route::get('/rotation-history/{scheduleId}', [AdminSecurityScheduleController::class, 'getRotationHistory'])->name('rotation-history');
            Route::get('/preference-scores', [AdminSecurityScheduleController::class, 'getPreferenceScores'])->name('preference-scores');
            Route::get('/rotation-summary', [AdminSecurityScheduleController::class, 'getRotationSummary'])->name('rotation-summary');
            
            // === 4.4 TEMPLATES ===
            Route::get('/templates/list', [AdminSecurityScheduleController::class, 'getTemplatesList'])->name('templates.list');
            Route::get('/notification-templates', [AdminSecurityScheduleController::class, 'getNotificationTemplates'])->name('notification-templates');
            
            // === 4.5 REPORTS & ANALYTICS ===
            Route::get('/reports/data', [AdminSecurityScheduleController::class, 'getReportsData'])->name('reports.data');
            Route::get('/analytics/data', [AdminSecurityScheduleController::class, 'getAnalyticsData'])->name('analytics.data');
            Route::get('/export/data', [AdminSecurityScheduleController::class, 'getExportData'])->name('export.data');
            Route::get('/schedule-analytics', [AdminSecurityScheduleController::class, 'getScheduleAnalytics'])->name('schedule-analytics');
            Route::get('/schedule-statistics', [AdminSecurityScheduleController::class, 'getScheduleStatistics'])->name('schedule-statistics');
            Route::get('/today-summary', [AdminSecurityScheduleController::class, 'getTodayScheduleSummary'])->name('today-summary');
            Route::get('/upcoming-handovers', [AdminSecurityScheduleController::class, 'getUpcomingHandovers'])->name('upcoming-handovers');
            Route::get('/personnel-performance', [AdminSecurityScheduleController::class, 'getPersonnelPerformance'])->name('personnel-performance');
            Route::get('/post-coverage', [AdminSecurityScheduleController::class, 'getPostCoverage'])->name('post-coverage');
            Route::get('/verification-statistics', [AdminSecurityScheduleController::class, 'getVerificationStatistics'])->name('verification-statistics');
            
            // === 4.6 DASHBOARD & REPORTS ===
            Route::get('/dashboard-widgets', [AdminSecurityScheduleController::class, 'getDashboardWidgets'])->name('dashboard-widgets');
            Route::get('/attendance-report', [AdminSecurityScheduleController::class, 'getAttendanceReport'])->name('attendance-report');
            Route::get('/coverage-gaps', [AdminSecurityScheduleController::class, 'getCoverageGaps'])->name('coverage-gaps');
            Route::get('/audit-logs', [AdminSecurityScheduleController::class, 'getAuditLogs'])->name('audit-logs');
            Route::get('/shift-overlap-report', [AdminSecurityScheduleController::class, 'getShiftOverlapReport'])->name('shift-overlap-report');
            Route::get('/qr-code-statistics', [AdminSecurityScheduleController::class, 'getQrCodeStatistics'])->name('qr-code-statistics');
            
            // === 4.7 PARAMETERIZED API ROUTES ===
            Route::get('/change-history/{schedule}', [AdminSecurityScheduleController::class, 'getChangeHistory'])->name('change-history');
            
            // === 4.8 API VERSIONS OF NEW ROUTES ===
            Route::post('/bulk-assign-preview', [AdminSecurityScheduleController::class, 'previewBulkAssign'])->name('bulk-assign-preview');
            Route::post('/quick-rotate', [AdminSecurityScheduleController::class, 'quickRotate'])->name('quick-rotate');
            Route::get('/rotation-eligible/{scheduleId}', [AdminSecurityScheduleController::class, 'isEligibleForRotation'])->name('rotation-eligible');
        });
    });
});

/*
|--------------------------------------------------------------------------
| ✅ API ROUTES (Separate prefix for dedicated AJAX calls)
|--------------------------------------------------------------------------
| These routes are for dedicated API endpoints with /api/admin prefix
| They complement the web routes and provide JSON responses
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('api/admin')->name('api.admin.')->group(function () {
    
    // ===== SECURITY SCHEDULE API ENDPOINTS =====
    Route::prefix('security-schedules')->name('security-schedules.')->group(function () {
        
        // === POST/PUT OPERATIONS ===
        Route::post('/bulk-assign', [AdminSecurityScheduleController::class, 'bulkAssign'])->name('bulk-assign');
        Route::put('/{scheduleId}/status', [AdminSecurityScheduleController::class, 'updateStatus'])->name('update-status');
        Route::put('/{scheduleId}/complete-handover', [AdminSecurityScheduleController::class, 'completeHandover'])->name('complete-handover');
        Route::post('/{scheduleId}/break', [AdminSecurityScheduleController::class, 'manageBreak'])->name('manage-break');
        Route::post('/swap', [AdminSecurityScheduleController::class, 'swapShifts'])->name('swap');
        Route::post('/generate-roster', [AdminSecurityScheduleController::class, 'generateRoster'])->name('generate-roster');
        Route::post('/templates/{templateId}/generate', [AdminSecurityScheduleController::class, 'generateFromTemplate'])->name('templates.generate');
        Route::post('/validate-constraints', [AdminSecurityScheduleController::class, 'validateConstraints'])->name('validate-constraints');
        
        // === NEW API ROUTES ===
        Route::post('/bulk-assign-preview', [AdminSecurityScheduleController::class, 'previewBulkAssign'])->name('bulk-assign-preview');
        Route::post('/quick-rotate', [AdminSecurityScheduleController::class, 'quickRotate'])->name('quick-rotate');
        Route::get('/rotation-eligible/{scheduleId}', [AdminSecurityScheduleController::class, 'isEligibleForRotation'])->name('rotation-eligible');
        
        // === SOFT DELETE & RESTORE API ROUTES ===
        Route::post('/bulk-restore', [AdminSecurityScheduleController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-force-delete', [AdminSecurityScheduleController::class, 'bulkForceDelete'])->name('bulk-force-delete');
        Route::delete('/clear-old-trash', [AdminSecurityScheduleController::class, 'clearOldTrash'])->name('clear-old-trash');
        
        // === ROTATION API ROUTES ===
        Route::post('/rotate-shift-groups', [AdminSecurityScheduleController::class, 'rotateShiftGroups'])->name('rotate-shift-groups');
        Route::post('/apply-rotation/{scheduleId}', [AdminSecurityScheduleController::class, 'applyRotation'])->name('apply-rotation');
        Route::post('/revert-rotation/{scheduleId}', [AdminSecurityScheduleController::class, 'revertRotation'])->name('revert-rotation');
        Route::post('/bulk-rotate', [AdminSecurityScheduleController::class, 'bulkRotate'])->name('bulk-rotate');
        Route::post('/bulk-swap-groups', [AdminSecurityScheduleController::class, 'bulkSwapGroups'])->name('bulk-swap-groups');
        Route::post('/calculate-preferences', [AdminSecurityScheduleController::class, 'calculatePreferenceScores'])->name('calculate-preferences');
        Route::post('/bulk-calculate-preferences', [AdminSecurityScheduleController::class, 'bulkCalculatePreferences'])->name('bulk-calculate-preferences');
        Route::post('/save-rotation-pattern', [AdminSecurityScheduleController::class, 'saveRotationPattern'])->name('save-rotation-pattern');
        
        // === GET OPERATIONS ===
        Route::get('/statistics', [AdminSecurityScheduleController::class, 'getStatistics'])->name('statistics');
        Route::get('/available-personnel', [AdminSecurityScheduleController::class, 'getAvailablePersonnel'])->name('available-personnel');
        
        // === CAPACITY & AVAILABILITY ===
        Route::get('/post-capacity', [AdminSecurityScheduleController::class, 'getPostCapacity'])->name('post-capacity');
        Route::get('/handover-info', [AdminSecurityScheduleController::class, 'getHandoverInfo'])->name('handover-info');
        Route::get('/check-availability', [AdminSecurityScheduleController::class, 'checkAvailability'])->name('check-availability');
        Route::get('/personnel-availability', [AdminSecurityScheduleController::class, 'getPersonnelAvailability'])->name('personnel-availability');
        Route::get('/available-slots', [AdminSecurityScheduleController::class, 'getAvailableSlots'])->name('available-slots');
        
        // === SHIFT & CONSTRAINT CHECKS ===
        Route::get('/shift-applicability', [AdminSecurityScheduleController::class, 'checkShiftApplicability'])->name('shift-applicability');
        Route::get('/personnel-constraints', [AdminSecurityScheduleController::class, 'checkPersonnelConstraints'])->name('personnel-constraints');
        Route::get('/constraint-check', [AdminSecurityScheduleController::class, 'checkAllConstraints'])->name('constraint-check');
        
        // === ROTATION & SEQUENCES ===
        Route::get('/rotation-sequence', [AdminSecurityScheduleController::class, 'getRotationSequence'])->name('rotation-sequence');
        Route::get('/rotation-patterns', [AdminSecurityScheduleController::class, 'getRotationPatterns'])->name('rotation-patterns');
        Route::get('/rotation-candidates', [AdminSecurityScheduleController::class, 'getRotationCandidates'])->name('rotation-candidates');
        Route::get('/rotation-history/{scheduleId}', [AdminSecurityScheduleController::class, 'getRotationHistory'])->name('rotation-history');
        Route::get('/preference-scores', [AdminSecurityScheduleController::class, 'getPreferenceScores'])->name('preference-scores');
        Route::get('/rotation-summary', [AdminSecurityScheduleController::class, 'getRotationSummary'])->name('rotation-summary');
        Route::get('/rotation-dashboard', [AdminSecurityScheduleController::class, 'getRotationDashboard'])->name('rotation-dashboard');
        
        // === REPORTS & ANALYTICS ===
        Route::get('/reports/data', [AdminSecurityScheduleController::class, 'getReportsData'])->name('reports.data');
        Route::get('/analytics/data', [AdminSecurityScheduleController::class, 'getAnalyticsData'])->name('analytics.data');
        Route::get('/export/data', [AdminSecurityScheduleController::class, 'getExportData'])->name('export.data');
        Route::get('/schedule-analytics', [AdminSecurityScheduleController::class, 'getScheduleAnalytics'])->name('schedule-analytics');
        Route::get('/schedule-statistics', [AdminSecurityScheduleController::class, 'getScheduleStatistics'])->name('schedule-statistics');
        Route::get('/shift-overlap-report', [AdminSecurityScheduleController::class, 'getShiftOverlapReport'])->name('shift-overlap-report');
        
        // === DASHBOARD & LIVE DATA ===
        Route::get('/today-summary', [AdminSecurityScheduleController::class, 'getTodayScheduleSummary'])->name('today-summary');
        Route::get('/upcoming-handovers', [AdminSecurityScheduleController::class, 'getUpcomingHandovers'])->name('upcoming-handovers');
        Route::get('/personnel-performance', [AdminSecurityScheduleController::class, 'getPersonnelPerformance'])->name('personnel-performance');
        Route::get('/post-coverage', [AdminSecurityScheduleController::class, 'getPostCoverage'])->name('post-coverage');
        Route::get('/dashboard-widgets', [AdminSecurityScheduleController::class, 'getDashboardWidgets'])->name('dashboard-widgets');
        Route::get('/attendance-report', [AdminSecurityScheduleController::class, 'getAttendanceReport'])->name('attendance-report');
        Route::get('/coverage-gaps', [AdminSecurityScheduleController::class, 'getCoverageGaps'])->name('coverage-gaps');
        Route::get('/audit-logs', [AdminSecurityScheduleController::class, 'getAuditLogs'])->name('audit-logs');
        Route::get('/verification-statistics', [AdminSecurityScheduleController::class, 'getVerificationStatistics'])->name('verification-statistics');
        Route::get('/qr-code-statistics', [AdminSecurityScheduleController::class, 'getQrCodeStatistics'])->name('qr-code-statistics');
        
        // === TEMPLATES ===
        Route::get('/templates/list', [AdminSecurityScheduleController::class, 'getTemplatesList'])->name('templates.list');
        Route::get('/notification-templates', [AdminSecurityScheduleController::class, 'getNotificationTemplates'])->name('notification-templates');
        
        // === PARAMETERIZED GET ROUTES ===
        Route::get('/change-history/{schedule}', [AdminSecurityScheduleController::class, 'getChangeHistory'])->name('change-history');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ SECURITY PERSONNEL SCHEDULE MANAGEMENT ROUTES (User Type 6)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    
    // =============================================
    // SECURITY SCHEDULE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('schedules')->name('schedules.')->group(function () {
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 1: SPECIFIC STATIC ROUTES (NO PARAMETERS)
        // ------------------------------------------------------------------
        // IMPORTANT: These must come BEFORE any route with {parameters}
        // ------------------------------------------------------------------
        
        // === 1.1 MAIN DASHBOARD / LIST VIEW ===
        Route::get('/', [SecurityScheduleController::class, 'mySchedules'])->name('index');
        
        // === 1.2 PREFERENCES MANAGEMENT ===
        Route::get('/preferences', [SecurityScheduleController::class, 'preferences'])->name('preferences');
        Route::post('/preferences', [SecurityScheduleController::class, 'updatePreferences'])->name('preferences.update');
        
        // === 1.3 AVAILABILITY MANAGEMENT ===
        Route::get('/availability', [SecurityScheduleController::class, 'availability'])->name('availability');
        Route::post('/availability', [SecurityScheduleController::class, 'updateAvailability'])->name('availability.update');
        Route::get('/availability/data', [SecurityScheduleController::class, 'getAvailability'])->name('availability.data');
        
        // === 1.4 STATISTICS & REPORTS ===
        Route::get('/statistics', [SecurityScheduleController::class, 'getStatistics'])->name('statistics');
        Route::get('/upcoming-handovers', [SecurityScheduleController::class, 'upcomingHandovers'])->name('upcoming-handovers');
        
        // === 1.5 ROTATION GROUPS ===
        Route::get('/rotation-groups', [SecurityScheduleController::class, 'rotationGroups'])->name('rotation-groups');
        
        // === 1.6 BREAK MANAGEMENT ===
        Route::get('/breaks/list', [SecurityScheduleController::class, 'listBreaks'])->name('breaks.list');
        
        // ------------------------------------------------------------------
        // 🚨 SECTION 2: PARAMETERIZED ROUTES (WITH {PARAMETERS})
        // ------------------------------------------------------------------
        // IMPORTANT: These come AFTER all static routes to prevent conflicts
        // Order within this section also matters - more specific first
        // ------------------------------------------------------------------
        
        // === 2.1 ROTATION GROUP DETAILS ===
        Route::get('/rotation-group/{groupId}', [SecurityScheduleController::class, 'rotationGroup'])->name('rotation-group');
        
        // === 2.2 CHECK-IN/OUT PAGES ===
        // These must come before other parameterized routes for proper matching
        Route::get('/{schedule}/checkin', [SecurityScheduleController::class, 'showCheckin'])->name('checkin');
        Route::get('/{schedule}/checkout', [SecurityScheduleController::class, 'showCheckout'])->name('checkout');
        
        // === 2.3 SMART CHECK-IN/OUT OPERATIONS (AJAX) ===
        Route::post('/{schedule}/smart-checkin', [SecurityScheduleController::class, 'smartCheckin'])->name('smart-checkin');
        Route::post('/{schedule}/smart-checkout', [SecurityScheduleController::class, 'smartCheckout'])->name('smart-checkout');
        
        // === 2.4 CALENDAR EXPORT ===
        Route::get('/{schedule}/calendar', [SecurityScheduleController::class, 'generateCalendar'])->name('calendar');
        
        // === 2.5 BREAK OPERATIONS ===
        Route::get('/{schedule}/breaks', [SecurityScheduleController::class, 'getBreaks'])->name('breaks');
        Route::post('/{schedule}/start-break', [SecurityScheduleController::class, 'startBreak'])->name('break.start');
        Route::post('/{schedule}/end-break', [SecurityScheduleController::class, 'endBreak'])->name('break.end');
        
        // === 2.6 HANDOVER MANAGEMENT ===
        Route::get('/{schedule}/handover', [SecurityScheduleController::class, 'handoverDetails'])->name('handover');
        Route::post('/{schedule}/handover/complete', [SecurityScheduleController::class, 'completeHandover'])->name('handover.complete');
        
        // === 2.7 CHECK-IN/OUT OPERATIONS (Legacy - Backward Compatibility) ===
        Route::post('/{schedule}/checkin', [SecurityScheduleController::class, 'checkin'])->name('checkin.legacy');
        Route::post('/{schedule}/checkout', [SecurityScheduleController::class, 'checkout'])->name('checkout.legacy');
        
        // === 2.8 SCHEDULE ACTIONS ===
        Route::post('/{schedule}/swap', [SecurityScheduleController::class, 'requestSwap'])->name('swap');
        Route::post('/{schedule}/acknowledge', [SecurityScheduleController::class, 'acknowledgeSchedule'])->name('acknowledge');
        Route::post('/{schedule}/report-issue', [SecurityScheduleController::class, 'reportIssue'])->name('report-issue');
        
        // === 2.9 SCHEDULE DETAILS (MUST BE LAST - MOST GENERIC) ===
        Route::get('/{schedule}', [SecurityScheduleController::class, 'showSchedule'])->name('show');
    });
    
    // =============================================
    // ✅ DIRECT ROUTES (Without /schedules prefix)
    // =============================================
    // These match the routes used in the Blade template
    // that don't include 'schedules' in the path
    
    // Preferences (direct route)
    Route::get('/preferences', [SecurityScheduleController::class, 'preferences'])->name('preferences');
    Route::post('/preferences', [SecurityScheduleController::class, 'updatePreferences'])->name('preferences.update');
    
    // Availability (direct route)
    Route::get('/availability', [SecurityScheduleController::class, 'availability'])->name('availability');
    Route::post('/availability', [SecurityScheduleController::class, 'updateAvailability'])->name('availability.update');
    
    // Rotation Group (direct route)
    Route::get('/rotation-group/{groupId}', [SecurityScheduleController::class, 'rotationGroup'])->name('rotation-group');
    
    // Schedule Show (direct route) - for backward compatibility
    Route::get('/schedule/{schedule}', [SecurityScheduleController::class, 'showSchedule'])->name('schedule.show');
    
    // Check-in direct route (for backward compatibility)
    Route::get('/schedule/{schedule}/checkin', [SecurityScheduleController::class, 'showCheckin'])->name('schedule.checkin');
    Route::get('/schedule/{schedule}/checkout', [SecurityScheduleController::class, 'showCheckout'])->name('schedule.checkout');
    
    // =============================================
    // ✅ API ROUTES FOR AJAX (Web accessible)
    // =============================================
    
    Route::prefix('api')->name('api.')->group(function () {
        
        // === AVAILABILITY API ===
        Route::get('/availability', [SecurityScheduleController::class, 'getAvailability'])->name('availability');
        Route::post('/availability', [SecurityScheduleController::class, 'updateAvailability'])->name('availability.update');
        
        // === STATISTICS API ===        Route::get('/statistics', [SecurityScheduleController::class, 'getStatistics'])->name('statistics');
        
        // === HANDOVER API ===
        Route::get('/handovers/upcoming', [SecurityScheduleController::class, 'upcomingHandovers'])->name('handovers.upcoming');
        
        // === CHECK-IN API ===
        Route::post('/schedule/{schedule}/smart-checkin', [SecurityScheduleController::class, 'smartCheckin'])->name('smart-checkin');
        Route::post('/schedule/{schedule}/smart-checkout', [SecurityScheduleController::class, 'smartCheckout'])->name('smart-checkout');
        
        // === BREAK API ===
        Route::post('/schedule/{schedule}/break/start', [SecurityScheduleController::class, 'startBreak'])->name('break.start');
        Route::post('/schedule/{schedule}/break/end', [SecurityScheduleController::class, 'endBreak'])->name('break.end');
        Route::get('/schedule/{schedule}/breaks', [SecurityScheduleController::class, 'getBreaks'])->name('breaks');
        
        // === HANDOVER API ===
        Route::get('/schedule/{schedule}/handover', [SecurityScheduleController::class, 'handoverDetails'])->name('handover');
        Route::post('/schedule/{schedule}/handover/complete', [SecurityScheduleController::class, 'completeHandover'])->name('handover.complete');
        
        // === SCHEDULE API ===
        Route::get('/schedule/{schedule}', [SecurityScheduleController::class, 'showSchedule'])->name('schedule.show');
        
        // === SWAP API ===
        Route::post('/schedule/{schedule}/swap', [SecurityScheduleController::class, 'requestSwap'])->name('swap');
        
        // === REPORT API ===
        Route::post('/schedule/{schedule}/report-issue', [SecurityScheduleController::class, 'reportIssue'])->name('report-issue');
        
        // === ACKNOWLEDGE API ===
        Route::post('/schedule/{schedule}/acknowledge', [SecurityScheduleController::class, 'acknowledgeSchedule'])->name('acknowledge');
    });
});

/*
|--------------------------------------------------------------------------
| ✅ DEBUG ROUTES (Optional - for development only)
|--------------------------------------------------------------------------
*/

// Route to list all admin security schedule routes (for debugging)
Route::middleware(['auth', 'multi.auth.user:0,1'])->get('/admin/security-schedules/routes-list', function() {
    $routes = [];
    foreach (Route::getRoutes() as $route) {
        if (str_contains($route->getName(), 'admin.security-schedules')) {
            $routes[] = [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $route->methods(),
            ];
        }
    }
    return response()->json($routes);
})->name('admin.security-schedules.routes-list');

// Route to list all security schedule routes (for debugging)
Route::middleware(['auth', 'multi.auth.user:6'])->get('/security/schedules/routes-list', function() {
    $routes = [];
    foreach (Route::getRoutes() as $route) {
        if (str_contains($route->getName(), 'security.schedules')) {
            $routes[] = [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $route->methods(),
            ];
        }
    }
    return response()->json($routes);
})->name('security.schedules.routes-list');

// =============================================
// ✅ ROTATION GROUPS ROUTES (Admin Only)
// =============================================

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('rotation-groups')->name('rotation-groups.')->group(function () {
        
        // =============================================
        // STATIC ROUTES (NO PARAMETERS) - MUST COME FIRST
        // =============================================
        
        // Main listing page
        Route::get('/', [RotationGroupController::class, 'index'])->name('index');
        
        // Create form and store
        Route::get('/create', [RotationGroupController::class, 'create'])->name('create');
        Route::post('/', [RotationGroupController::class, 'store'])->name('store');
        
        // Groups needing rotation (AJAX)
        Route::get('/needing-rotation', [RotationGroupController::class, 'getGroupsNeedingRotation'])->name('needing-rotation');
        
        // =============================================
        // PARAMETERIZED ROUTES (WITH {rotationGroup})
        // =============================================
        
        // View, edit, update, delete
        Route::get('/{rotationGroup}', [RotationGroupController::class, 'show'])->name('show');
        Route::get('/{rotationGroup}/edit', [RotationGroupController::class, 'edit'])->name('edit');
        Route::put('/{rotationGroup}', [RotationGroupController::class, 'update'])->name('update');
        Route::delete('/{rotationGroup}', [RotationGroupController::class, 'destroy'])->name('destroy');
        
        // Member management
        Route::post('/{rotationGroup}/assign-members', [RotationGroupController::class, 'assignMembers'])->name('assign-members');
        Route::delete('/{rotationGroup}/members/{member}', [RotationGroupController::class, 'removeMember'])->name('remove-member');
        
        // Rotation operations
        Route::post('/{rotationGroup}/execute-rotation', [RotationGroupController::class, 'executeRotation'])->name('execute-rotation');
        Route::get('/{rotationGroup}/preview-rotation', [RotationGroupController::class, 'previewRotation'])->name('preview-rotation');
        Route::get('/{rotationGroup}/rotation-history', [RotationGroupController::class, 'getRotationHistory'])->name('rotation-history');
        Route::get('/{rotationGroup}/statistics', [RotationGroupController::class, 'getStatistics'])->name('statistics');
    });
});

// =============================================
// ✅ ROTATION GROUPS API ROUTES (For AJAX calls)
// =============================================

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('api/admin')->name('api.admin.')->group(function () {
    
    Route::prefix('rotation-groups')->name('rotation-groups.')->group(function () {
        
        // === CRUD OPERATIONS (API) ===
        Route::get('/', [RotationGroupController::class, 'index'])->name('index');
        Route::post('/', [RotationGroupController::class, 'store'])->name('store');
        Route::get('/{rotationGroup}', [RotationGroupController::class, 'show'])->name('show');
        Route::put('/{rotationGroup}', [RotationGroupController::class, 'update'])->name('update');
        Route::delete('/{rotationGroup}', [RotationGroupController::class, 'destroy'])->name('destroy');
        
        // === MEMBER MANAGEMENT (API) ===
        Route::post('/{rotationGroup}/assign-members', [RotationGroupController::class, 'assignMembers'])->name('assign-members');
        Route::delete('/{rotationGroup}/members/{member}', [RotationGroupController::class, 'removeMember'])->name('remove-member');
        
        // === ROTATION OPERATIONS (API) ===
        Route::get('/{rotationGroup}/rotation-history', [RotationGroupController::class, 'getRotationHistory'])->name('rotation-history');
        Route::post('/{rotationGroup}/execute-rotation', [RotationGroupController::class, 'executeRotation'])->name('execute-rotation');
        Route::get('/{rotationGroup}/preview-rotation', [RotationGroupController::class, 'previewRotation'])->name('preview-rotation');
        Route::get('/{rotationGroup}/statistics', [RotationGroupController::class, 'getStatistics'])->name('statistics');
        Route::get('/needing-rotation', [RotationGroupController::class, 'getGroupsNeedingRotation'])->name('needing-rotation');
    });
});

// =============================================
// ✅ ROTATION HISTORY ROUTES (Admin Only)
// =============================================

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('rotation-history')->name('rotation-history.')->group(function () {
        
        // STATIC ROUTES - MUST COME FIRST
        Route::get('/', [RotationHistoryController::class, 'index'])->name('index');
        Route::get('/statistics', [RotationHistoryController::class, 'getStatistics'])->name('statistics');
        Route::get('/timeline', [RotationHistoryController::class, 'getTimeline'])->name('timeline');
        Route::get('/export', [RotationHistoryController::class, 'export'])->name('export');
        Route::get('/date-range', [RotationHistoryController::class, 'byDateRange'])->name('date-range');
        
        // PARAMETERIZED ROUTES - COMES LAST
        Route::get('/schedule/{scheduleId}', [RotationHistoryController::class, 'forSchedule'])->name('schedule');
        Route::get('/user/{userId}', [RotationHistoryController::class, 'forUser'])->name('user');
        Route::get('/group/{groupId}', [RotationHistoryController::class, 'forGroup'])->name('group');
        Route::get('/post/{postId}', [RotationHistoryController::class, 'forPost'])->name('post');
    });
});


    // =============================================
    // ✅ ROTATION GROUPS API ROUTES
    // =============================================
    
    Route::prefix('rotation-groups')->name('rotation-groups.')->group(function () {
        
        // === CRUD OPERATIONS ===
        Route::get('/', [RotationGroupController::class, 'index'])->name('index');
        Route::post('/', [RotationGroupController::class, 'store'])->name('store');
        Route::get('/{rotationGroup}', [RotationGroupController::class, 'show'])->name('show');
        Route::put('/{rotationGroup}', [RotationGroupController::class, 'update'])->name('update');
        Route::delete('/{rotationGroup}', [RotationGroupController::class, 'destroy'])->name('destroy');
        
        // === MEMBER MANAGEMENT ===
        Route::post('/{rotationGroup}/assign-members', [RotationGroupController::class, 'assignMembers'])->name('assign-members');
        Route::delete('/{rotationGroup}/members/{member}', [RotationGroupController::class, 'removeMember'])->name('remove-member');
        
        // === ROTATION OPERATIONS ===
        Route::get('/{rotationGroup}/rotation-history', [RotationGroupController::class, 'getRotationHistory'])->name('rotation-history');
        Route::post('/{rotationGroup}/execute-rotation', [RotationGroupController::class, 'executeRotation'])->name('execute-rotation');
        Route::get('/{rotationGroup}/preview-rotation', [RotationGroupController::class, 'previewRotation'])->name('preview-rotation');
        Route::get('/{rotationGroup}/statistics', [RotationGroupController::class, 'getStatistics'])->name('statistics');
        Route::get('/needing-rotation', [RotationGroupController::class, 'getGroupsNeedingRotation'])->name('needing-rotation');
    });

    

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // =============================================
    // SECURITY POST MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('security-posts')->name('security-posts.')->group(function () {
        // Main listing (includes option to show trashed)
        Route::get('/', [AdminSecurityPostController::class, 'index'])->name('index');
        
        // Trash management routes
        Route::prefix('trash')->name('trash.')->group(function () {
            // View trash (list of soft-deleted posts)
            Route::get('/', [AdminSecurityPostController::class, 'trash'])->name('index');
            
            // Restore a specific post from trash
            Route::post('/{id}/restore', [AdminSecurityPostController::class, 'restore'])->name('restore');
            
            // Force delete (permanently delete) a specific post from trash
            Route::delete('/{id}/force-delete', [AdminSecurityPostController::class, 'forceDelete'])->name('force-delete');
            
            // Bulk restore posts from trash
            Route::post('/bulk-restore', [AdminSecurityPostController::class, 'bulkRestore'])->name('bulk-restore');
            
            // Empty entire trash (permanently delete all trashed posts)
            Route::delete('/empty', [AdminSecurityPostController::class, 'emptyTrash'])->name('empty');
        });
        
        // Create post
        Route::get('/create', [AdminSecurityPostController::class, 'create'])->name('create');
        Route::post('/', [AdminSecurityPostController::class, 'store'])->name('store');
        
        // View post (will redirect to trash if post is deleted)
        Route::get('/{securityPost}', [AdminSecurityPostController::class, 'show'])->name('show');
        
        // Edit post (will redirect if post is deleted)
        Route::get('/{securityPost}/edit', [AdminSecurityPostController::class, 'edit'])->name('edit');
        Route::put('/{securityPost}', [AdminSecurityPostController::class, 'update'])->name('update');
        
        // Delete post (soft delete - moves to trash)
        Route::delete('/{securityPost}', [AdminSecurityPostController::class, 'destroy'])->name('destroy');
        
        // Toggle activation (AJAX) - won't work for trashed posts
        Route::post('/{securityPost}/toggle-activation', [AdminSecurityPostController::class, 'toggleActivation'])->name('toggle-activation');
        
        // Export posts (with option to include trashed)
        Route::get('/export', [AdminSecurityPostController::class, 'exportPosts'])->name('export');
    
        // =============================================
        // POST QR CODE MANAGEMENT ROUTES
        // =============================================
        
        Route::prefix('{securityPost}/qr-codes')->name('qr-codes.')->group(function () {
    // =============================================
    // STATIC ROUTES (NO PARAMETERS) - FIRST PRIORITY
    // =============================================
    
    // List all QR codes for a post
    Route::get('/', [PostQrCodeController::class, 'index'])->name('index');
    
    // Create new QR code
    Route::get('/create', [PostQrCodeController::class, 'create'])->name('create');
    Route::post('/', [PostQrCodeController::class, 'store'])->name('store');
    
    // Export QR codes as CSV
    Route::get('/export', [PostQrCodeController::class, 'export'])->name('export');
    
    // =============================================
    // TRASH ROUTES - SECOND PRIORITY (SPECIFIC PREFIX)
    // =============================================
    
    Route::prefix('trash')->name('trash.')->group(function () {
        // View trash - THIS MUST COME FIRST
        Route::get('/', [PostQrCodeController::class, 'trash'])->name('index');
        
        // Bulk restore
        Route::post('/bulk-restore', [PostQrCodeController::class, 'bulkRestore'])->name('bulk-restore');
        
        // Empty trash
        Route::delete('/empty', [PostQrCodeController::class, 'emptyTrash'])->name('empty');
        
        // Restore specific QR code
        Route::post('/{qrCode}/restore', [PostQrCodeController::class, 'restore'])->name('restore');
        
        // Force delete specific QR code
        Route::delete('/{qrCode}/force-delete', [PostQrCodeController::class, 'forceDelete'])->name('force-delete');
    });
    
    // =============================================
    // DYNAMIC ROUTES WITH {qrCode} PARAMETER - LAST PRIORITY
    // =============================================
    
    // View QR code details
    Route::get('/{qrCode}', [PostQrCodeController::class, 'show'])->name('show');
    
    // Download QR code image
    Route::get('/{qrCode}/download', [PostQrCodeController::class, 'download'])->name('download');
    
    // Update QR code status
    Route::post('/{qrCode}/update-status', [PostQrCodeController::class, 'updateStatus'])->name('update-status');
    
    // Regenerate QR code
    Route::post('/{qrCode}/regenerate', [PostQrCodeController::class, 'regenerate'])->name('regenerate');
    
    // Delete QR code (soft delete)
    Route::delete('/{qrCode}', [PostQrCodeController::class, 'destroy'])->name('destroy');
    
    // =============================================
    // API ROUTES
    // =============================================
    
    Route::prefix('api')->name('api.')->group(function () {
        // Static API routes first
        Route::get('/statistics', [PostQrCodeController::class, 'getStatistics'])->name('statistics');
        Route::get('/trash-statistics', [PostQrCodeController::class, 'getTrashStatistics'])->name('trash-statistics');
        Route::delete('/bulk-destroy', [PostQrCodeController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::post('/validate', [PostQrCodeController::class, 'validate'])->name('validate');
        
        // Dynamic API routes with parameters last
        Route::get('/{qrCode}/usage-history', [PostQrCodeController::class, 'getUsageHistory'])->name('usage-history');
    });
});
        
        // =============================================
        // API ROUTES (for AJAX/JSON responses)
        // =============================================
        
        Route::prefix('api')->name('api.')->group(function () {
            // Get posts for dropdown/autocomplete (excludes trashed)
            Route::get('/list', [AdminSecurityPostController::class, 'getPosts'])->name('list');
            
            // Get staffing statistics for dashboard (excludes trashed)
            Route::get('/staffing-statistics', [AdminSecurityPostController::class, 'getStaffingStatistics'])->name('staffing-statistics');
            
            // Get utilization statistics (excludes trashed)
            Route::get('/utilization-statistics', [AdminSecurityPostController::class, 'getUtilizationStatistics'])->name('utilization-statistics');
            
            // Get trash statistics
            Route::get('/trash-statistics', [AdminSecurityPostController::class, 'getTrashStatistics'])->name('trash-statistics');
            
            // Get post details with schedules (works for both active and trashed)
            Route::get('/{securityPost}/details', [AdminSecurityPostController::class, 'getPostDetails'])->name('details');
            
            // Bulk update posts (includes trash/restore/force-delete actions)
            Route::post('/bulk-update', [AdminSecurityPostController::class, 'bulkUpdate'])->name('bulk-update');
            
            // =============================================
            // GLOBAL QR CODE STATISTICS
            // =============================================
            
            // Get global QR code statistics across all posts
            Route::get('/qr-code-statistics', [AdminSecurityPostController::class, 'getQrCodeStatistics'])->name('qr-code-statistics');
        });
    });
});

// ============================================
// SECURITY PERSONNEL ROUTES (User Type: 6)
// ============================================

Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    
    Route::prefix('posts')->name('posts.')->group(function () {
        
        // ============================================
        // 🔴 SPECIFIC ROUTES FIRST (No parameters)
        // ============================================
        
        // List all posts (index)
        Route::get('/', [SecurityPostController::class, 'index'])->name('index');
        
        // My current post
        Route::get('/my-post', [SecurityPostController::class, 'myPost'])->name('my-post');
        
        // 📌 CREATE ROUTE - MUST BE BEFORE PARAMETERIZED ROUTES
        Route::get('/create', [SecurityPostController::class, 'create'])->name('create');
        Route::post('/', [SecurityPostController::class, 'store'])->name('store');
        
        // ============================================
        // 🗑️ TRASH ROUTES - Area Supervisor only
        // ============================================
        
        // View trashed posts
        Route::get('/trash', [SecurityPostController::class, 'trash'])->name('trash');
        
        // Empty entire trash
        Route::delete('/trash/empty', [SecurityPostController::class, 'emptyTrash'])->name('empty-trash');
        
        // ============================================
        // 📊 API/JSON ROUTES - For AJAX requests
        // ============================================
        
        // Get all posts with staffing status (JSON)
        Route::get('/staffing/all', [SecurityPostController::class, 'getPostsWithStaffing'])->name('staffing.all');
        
        // Get posts by type (JSON)
        Route::get('/by-type', [SecurityPostController::class, 'getPostsByType'])->name('by-type');
        
        // Get shift coverage report (JSON - Area Supervisor only)
        Route::get('/coverage-report', [SecurityPostController::class, 'getCoverageReport'])->name('coverage-report');
        
        // QR scan (uses code, not ID)
        Route::get('/scan/{code}', [SecurityPostController::class, 'getPostForScan'])->name('scan');
        
        // ============================================
        // 🟡 PARAMETERIZED ROUTES (Last!)
        // ============================================
        
        // View single post
        Route::get('/{securityPost}', [SecurityPostController::class, 'show'])->name('show');
        
        // Schedule
        Route::get('/{securityPost}/schedule', [SecurityPostController::class, 'getSchedule'])->name('schedule');
        
        // ✏️ EDIT ROUTES
        Route::get('/{securityPost}/edit', [SecurityPostController::class, 'edit'])->name('edit');
        Route::put('/{securityPost}', [SecurityPostController::class, 'update'])->name('update');
        
        // 🗑️ DELETE ROUTES
        Route::delete('/{securityPost}', [SecurityPostController::class, 'destroy'])->name('destroy');
        
        // ♻️ RESTORE AND FORCE DELETE (use ID, not model binding)
        Route::patch('/{id}/restore', [SecurityPostController::class, 'restore'])->name('restore');
        Route::delete('/{id}/force-delete', [SecurityPostController::class, 'forceDelete'])->name('force-delete');
        
        // ============================================
        // 📊 BULK OPERATIONS - Area Supervisor only
        // ============================================
        
        // Bulk restore multiple posts
        Route::post('/bulk-restore', [SecurityPostController::class, 'bulkRestore'])->name('bulk-restore');
        
        // Bulk permanently delete multiple posts
        Route::delete('/bulk-permanent-delete', [SecurityPostController::class, 'bulkPermanentDelete'])->name('bulk-permanent-delete');
        
        // ============================================
        // 📊 ADDITIONAL API ROUTES
        // ============================================
        
        // Get staffing status for a post (JSON)
        Route::get('/{securityPost}/staffing', [SecurityPostController::class, 'getStaffingStatus'])->name('staffing');
        
        // Get post availability for scheduling (JSON)
        Route::get('/{securityPost}/availability', [SecurityPostController::class, 'getAvailability'])->name('availability');
    });
});



// Security Shifts Routes
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('security-shifts')->name('security-shifts.')->group(function () {
        // Main CRUD operations
        Route::get('/', [SecurityShiftController::class, 'index'])->name('index');
        Route::get('/create', [SecurityShiftController::class, 'create'])->name('create');
        Route::post('/', [SecurityShiftController::class, 'store'])->name('store');
        
        // **TRASH ROUTES MUST COME BEFORE PARAMETERIZED ROUTES**
        Route::prefix('trash')->name('trash.')->group(function () {
            Route::get('/', [SecurityShiftController::class, 'trash'])->name('index');
            Route::post('/restore/{id}', [SecurityShiftController::class, 'restore'])->name('restore');
            Route::delete('/force-delete/{id}', [SecurityShiftController::class, 'forceDelete'])->name('force-delete');
        });
        
        // **BULK ACTION ROUTE**
        Route::post('/bulk-action', [SecurityShiftController::class, 'bulkAction'])->name('bulk-action');
        
        // **BULK OPERATIONS GROUP**
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::post('/action', [SecurityShiftController::class, 'bulkAction'])->name('action');
            Route::post('/restore', [SecurityShiftController::class, 'bulkRestore'])->name('restore');
            Route::post('/force-delete', [SecurityShiftController::class, 'bulkForceDelete'])->name('force-delete');
        });
        
        // **INDIVIDUAL SHIFT ROUTES - COMES LAST**
        Route::prefix('{securityShift}')->group(function () {
            Route::get('/', [SecurityShiftController::class, 'show'])->name('show');
            Route::get('/edit', [SecurityShiftController::class, 'edit'])->name('edit');
            Route::put('/', [SecurityShiftController::class, 'update'])->name('update');
            Route::delete('/', [SecurityShiftController::class, 'destroy'])->name('destroy');
            Route::post('/toggle-status', [SecurityShiftController::class, 'toggleStatus'])->name('toggle-status');
        });
        
        // Schedule & planning group
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('/for-schedule', [SecurityShiftController::class, 'getShiftsForSchedule'])->name('shifts');
            Route::get('/recommended', [SecurityShiftController::class, 'getRecommendedShifts'])->name('recommended');
            Route::post('/process-rotation', [SecurityShiftController::class, 'processRotation'])->name('process-rotation');
            Route::get('/calendar', [SecurityShiftController::class, 'shiftCalendar'])->name('calendar');
        });
        
        // Validation & checks
        Route::post('/check-in-use', [SecurityShiftController::class, 'checkShiftsInUse'])->name('check-in-use');
        
        // Export/Import/Print
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/export', [SecurityShiftController::class, 'exportSelected'])->name('export');
            Route::get('/print', [SecurityShiftController::class, 'print'])->name('print');
            Route::get('/', [SecurityShiftController::class, 'report'])->name('index');
        });
        
        // Import (if implemented)
        Route::post('/import', [SecurityShiftController::class, 'import'])->name('import');
    });
    
});


// In routes/web.php or a test route
Route::get('/test-global-sequence/{planId}', function($planId) {
    $plan = \App\Models\RegistrationPlan::find($planId);
    
    if (!$plan) {
        return response()->json(['error' => 'Plan not found'], 404);
    }
    
    $service = new \App\Services\PropertyRegistrationService(
        app(\App\Services\LandlordInvitationService::class),
        app(\App\Services\TenantInvitationService::class)
    );
    
    try {
        // Test pattern generation
        $pattern = $service->generateRegistrationPattern($plan);
        
        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'is_global_sequence' => $plan->is_global_sequence,
                'continues_from_plan_id' => $plan->continues_from_plan_id,
                'naming_pattern' => $plan->naming_pattern,
                'starting_point' => $plan->starting_point,
                'next_available_name' => $plan->next_available_name,
                'status' => $plan->status,
            ],
            'generated_pattern' => $pattern,
            'pattern_exists' => $pattern ? \App\Models\Property::where('registration_pattern', $pattern)->exists() : false,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// WhatsApp Provider Routes - Only accessible to users with role_id = 5 (Developer)
Route::middleware(['auth', 'multi.auth.user:5'])->prefix('admin')->name('admin.')->group(function () {
    
    // WhatsApp Providers Main Routes
    Route::prefix('whatsapp-providers')->name('whatsapp-providers.')->group(function () {
        
        // Dashboard/Index
        Route::get('/', [WhatsAppProviderController::class, 'index'])
            ->name('index');
        
        // Configuration
        Route::post('/configure', [WhatsAppProviderController::class, 'configureProvider'])
            ->name('configure');
        
        // Provider Actions
        Route::post('/toggle', [WhatsAppProviderController::class, 'toggleProvider'])
            ->name('toggle');
        
        Route::post('/test-connection', [WhatsAppProviderController::class, 'testConnection'])
            ->name('test-connection');
        
        Route::post('/send-test', [WhatsAppProviderController::class, 'sendTestMessage'])
            ->name('send-test');
        
        Route::post('/reset', [WhatsAppProviderController::class, 'resetProvider'])
            ->name('reset');
        
        // Status & Configuration
        Route::get('/status', [WhatsAppProviderController::class, 'getConfigurationStatus'])
            ->name('status');
        
        Route::get('/configuration-status', [WhatsAppProviderController::class, 'getConfigurationStatus'])
            ->name('configuration-status');
        
        // ✅ ADD THIS MISSING ROUTE:
        Route::post('/verify-environment', [WhatsAppProviderController::class, 'verifyEnvironment'])
            ->name('verify-environment');
        
        // Provider Configuration - FIXED: uses Request instead of URL parameter
        Route::get('/config', [WhatsAppProviderController::class, 'getProviderConfig'])
            ->name('get-config');
        
        // Pending Updates
        Route::get('/check-pending-updates', [WhatsAppProviderController::class, 'checkPendingWhatsAppUpdates'])
            ->name('check-pending-updates');
        
        Route::get('/retry-update', [WhatsAppProviderController::class, 'retryWhatsAppUpdate'])
            ->name('retry');
    });
    
    // WhatsApp System Settings (Legacy compatibility)
    Route::prefix('whatsapp-settings')->name('whatsapp-settings.')->group(function () {
        Route::get('/', [WhatsAppProviderController::class, 'index'])
            ->name('index');
        
        Route::post('/update', [WhatsAppProviderController::class, 'configureProvider'])
            ->name('update');
        
        Route::post('/test', [WhatsAppProviderController::class, 'testConnection'])
            ->name('test');
    });
    
    // ✅ ADD WEBHOOK ROUTE TO FIX PREVIOUS ERROR
    Route::prefix('webhook')->name('webhook.')->group(function () {
        Route::post('/whatsapp', function () {
            return response()->json(['status' => 'ok']);
        })->name('whatsapp');
    });
});

// Temporary WhatsApp webhook route (public)
Route::post('/webhook/whatsapp', function () {
    return response()->json(['status' => 'ok']);
})->name('webhook.whatsapp');

// ✅ ADD: Webhook verification route (for WhatsApp Business API)
Route::get('/webhook/whatsapp/verify', function (Illuminate\Http\Request $request) {
    $mode = $request->query('hub.mode');
    $token = $request->query('hub.verify_token');
    $challenge = $request->query('hub.challenge');
    
    $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'YOUR_VERIFY_TOKEN');
    
    if ($mode === 'subscribe' && $token === $verifyToken) {
        return response($challenge, 200);
    }
    
    return response()->json(['error' => 'Verification failed'], 403);
})->name('webhook.whatsapp.verify');

/*
|--------------------------------------------------------------------------
| ✅ FIXED: Quick Action Routes for Notifications
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'web'])->group(function () {
    // Quick mark all as read
    Route::post('/quick-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.quick-read');
    
    // Quick delete all
    Route::post('/quick-clear', [NotificationController::class, 'clearAll'])
        ->name('notifications.quick-clear');
    
    // ✅ FIXED: Notification action handler with role-based redirect
    Route::get('/notification-action/{id}', function ($id) {
        $user = auth()->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        
        // Mark as read
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }
        
        // Get action URL from notification data
        $actionUrl = $notification->data['action_url'] ?? null;
        
        if ($actionUrl) {
            return redirect($actionUrl);
        }
        
        // ✅ FIXED: Role-based fallback redirect
        $routeName = match(true) {
            $user->isLandlord() => 'landlord.notifications.index',
            $user->isAdmin() || $user->isSuperAdmin() => 'admin.notifications.index',
            $user->isDeveloper() => 'developer.notifications.index',
            $user->isFieldAgent() => 'field-agent.notifications.index',
            $user->isTenant() => 'tenant.notifications.index',
            $user->isSecurity() => 'security.notifications.index',
            default => 'notifications.index',
        };
        
        return redirect()->route($routeName)
            ->with('info', 'No specific action defined for this notification.');
    })->name('notification-action');
});

/*
|--------------------------------------------------------------------------
| ✅ FIXED: Core Notification Routes (Must come BEFORE role-specific routes)
|--------------------------------------------------------------------------
*/

// Web notification routes (page views) - These need to be accessible by all authenticated users
Route::middleware(['auth', 'web'])->group(function () {
    // Notification index page - This should use the proper controller method
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    
    // ✅ ADDED: Single notification view route
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])
        ->name('notifications.show');
    
    // Delete all notifications (web form)
    Route::delete('/notifications/clear', [NotificationController::class, 'clearAll'])
        ->name('notifications.clear');
    
    // Mark all notifications as seen (web)
    Route::post('/notifications/mark-seen', [NotificationController::class, 'markAllAsSeenApi'])
        ->name('notifications.mark-seen');
});


/*
|--------------------------------------------------------------------------
| Base notification routes (fallback for users without a role-specific group)
|--------------------------------------------------------------------------
| Needed because NotificationController::getNotificationsRouteForUser() falls
| back to `notifications.index` when no role matches.
*/
Route::middleware(['auth', 'web'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/',              [NotificationController::class, 'index'])->name('index');
    Route::get('/recent',        [NotificationController::class, 'recent'])->name('recent');
    Route::get('/unread-count',  [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::get('/categories',    [NotificationController::class, 'categories'])->name('categories');
    Route::get('/stats',         [NotificationController::class, 'stats'])->name('stats');
    Route::get('/export',        [NotificationController::class, 'export'])->name('export');
    Route::get('/preferences',   [NotificationController::class, 'preferences'])->name('preferences');
    Route::put('/preferences',   [NotificationController::class, 'updatePreferences'])->name('preferences.update');

    Route::post('/read-all',     [NotificationController::class, 'markAllAsRead'])->name('read-all');
    Route::post('/seen-all',     [NotificationController::class, 'markAllAsSeenApi'])->name('seen-all');
    Route::post('/test',         [NotificationController::class, 'test'])->name('test');

    Route::delete('/clear-all',  [NotificationController::class, 'clearAll'])->name('clear-all');
    Route::delete('/clear-read', [NotificationController::class, 'clearRead'])->name('clear-read');
    Route::post('/bulk-destroy', [NotificationController::class, 'bulkDestroy'])->name('bulk-destroy');

    Route::get('/category/{category}', [NotificationController::class, 'byCategory'])
        ->name('by-category')
        ->where('category', '[A-Za-z0-9_\-]+');

    Route::post('/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('mark-as-read')->whereUuid('id');
    Route::post('/{id}/unread', [NotificationController::class, 'markAsUnread'])
        ->name('mark-as-unread')->whereUuid('id');
    Route::delete('/{id}',      [NotificationController::class, 'destroy'])
        ->name('destroy')->whereUuid('id');

    Route::get('/{id}', [NotificationController::class, 'show'])
        ->name('show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| API notification routes (for AJAX calls from the bell)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {

    // ---------- List / lookup (literal paths FIRST) ----------
    Route::get('/notifications',            [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',     [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',      [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/categories', [NotificationController::class, 'categories'])->name('notifications.categories');
    Route::get('/notifications/stats',      [NotificationController::class, 'stats'])->name('notifications.stats');
    Route::get('/notifications/export',     [NotificationController::class, 'export'])->name('notifications.export');
    Route::get('/notifications/category/{category}', [NotificationController::class, 'byCategory'])
        ->name('notifications.by-category')
        ->where('category', '[A-Za-z0-9_\-]+');

    // ---------- Actions ----------
    Route::post('/notifications/read-all',  [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/seen-all',  [NotificationController::class, 'markAllAsSeenApi'])->name('notifications.seen-all');
    Route::post('/notifications/test',      [NotificationController::class, 'test'])->name('notifications.test');

    Route::delete('/notifications',         [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::delete('/notifications/read',    [NotificationController::class, 'clearRead'])->name('notifications.clear-read');

    // ---------- Parameterized (LAST) ----------
    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread'])
        ->name('notifications.mark-as-unread')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');

    Route::get('/notifications/{id}', [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:0,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');
    Route::get('/notifications/categories',   [NotificationController::class, 'categories'])->name('notifications.categories');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread'])
        ->name('notifications.mark-as-unread')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');

    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');

    Route::get('/users/{user}/notifications', [NotificationController::class, 'index'])
        ->name('users.notifications')
        ->middleware('can:view,user');
});

/*
|--------------------------------------------------------------------------
| Landlord
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:2'])
    ->prefix('landlord')
    ->name('landlord.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/categories',   [NotificationController::class, 'categories'])->name('notifications.categories');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread'])
        ->name('notifications.mark-as-unread')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');

    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| Tenant
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:3'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');
    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| Field Agent
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:4'])
    ->prefix('field-agent')
    ->name('field-agent.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');
    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| Developer
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:5'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');
    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web', 'multi.auth.user:6'])
    ->prefix('security')
    ->name('security.')
    ->group(function () {
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent',       [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/count',        [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::get('/notifications/stats',        [NotificationController::class, 'stats'])->name('notifications.stats');

    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');

    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')->whereUuid('id');
    Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')->whereUuid('id');
    Route::get('/notifications/{id}',         [NotificationController::class, 'show'])
        ->name('notifications.show')->whereUuid('id');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Command to List All Notification Routes (for debugging)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'web'])->get('/debug-notification-routes', function () {
    $routes = collect(Route::getRoutes())->filter(function ($route) {
        return str_contains($route->uri(), 'notification') || 
               str_contains($route->getName(), 'notification');
    })->map(function ($route) {
        return [
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'methods' => $route->methods(),
            'action' => $route->getActionName(),
        ];
    })->values();
    
    return response()->json($routes);
})->name('debug.notification-routes');

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Notification Broadcast Routes (for Real-time)
|--------------------------------------------------------------------------
*/

// Real-time notification broadcasting (if using Echo/Pusher)
Route::middleware(['auth', 'web'])->prefix('broadcasting')->group(function () {
    // Laravel Echo authentication for private channels
    Route::post('/auth', function () {
        // This route is for Laravel Echo authentication
        $broadcaster = new \Illuminate\Broadcasting\Broadcasters\PusherBroadcaster(
            app(\Illuminate\Broadcasting\BroadcastManager::class)->driver('pusher')->getPusher()
        );
        
        return $broadcaster->auth(request());
    });
    
    // Presence channel for online users
    Route::post('/presence-auth', function () {
        $broadcaster = new \Illuminate\Broadcasting\Broadcasters\PusherBroadcaster(
            app(\Illuminate\Broadcasting\BroadcastManager::class)->driver('pusher')->getPusher()
        );
        
        return $broadcaster->auth(request());
    });
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Notification Preferences Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'web'])->group(function () {
    // Notification preferences
    Route::get('/notification-preferences', [\App\Http\Controllers\UserController::class, 'notificationPreferences'])
        ->name('notification-preferences.index');
    
    Route::post('/notification-preferences', [\App\Http\Controllers\UserController::class, 'updateNotificationPreferences'])
        ->name('notification-preferences.update');
    
    Route::get('/notification-preferences/test', [\App\Http\Controllers\UserController::class, 'testNotificationPreferences'])
        ->name('notification-preferences.test');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: System-wide Notification Settings (Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'web', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    // System notification settings
    Route::get('/notification-settings', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'index'])
        ->name('notification-settings.index');
    
    Route::post('/notification-settings', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'update'])
        ->name('notification-settings.update');
    
    // Send broadcast notification to all users
    Route::get('/notification-settings/broadcast', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'broadcastForm'])
        ->name('notification-settings.broadcast.form');
    
    Route::post('/notification-settings/broadcast', [\App\Http\Controllers\Admin\NotificationSettingController::class, 'broadcast'])
        ->name('notification-settings.broadcast.send');
    
    // Notification templates
    Route::get('/notification-templates', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'index'])
        ->name('notification-templates.index');
    
    Route::get('/notification-templates/create', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'create'])
        ->name('notification-templates.create');
    
    Route::post('/notification-templates', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'store'])
        ->name('notification-templates.store');
    
    Route::get('/notification-templates/{template}/edit', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'edit'])
        ->name('notification-templates.edit');
    
    Route::put('/notification-templates/{template}', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'update'])
        ->name('notification-templates.update');
    
    Route::delete('/notification-templates/{template}', [\App\Http\Controllers\Admin\NotificationTemplateController::class, 'destroy'])
        ->name('notification-templates.destroy');
    
    // Notification logs
    Route::get('/notification-logs', [\App\Http\Controllers\Admin\NotificationLogController::class, 'index'])
        ->name('notification-logs.index');
    
    Route::get('/notification-logs/{log}', [\App\Http\Controllers\Admin\NotificationLogController::class, 'show'])
        ->name('notification-logs.show');
    
    Route::delete('/notification-logs/cleanup', [\App\Http\Controllers\Admin\NotificationLogController::class, 'cleanup'])
        ->name('notification-logs.cleanup');
});

/*
|--------------------------------------------------------------------------
| ✅ ADDED: Notification Webhook Endpoints (for external services)
|--------------------------------------------------------------------------
*/

// Webhook for external notification services (Push notifications, SMS, etc.)
Route::prefix('webhooks')->group(function () {
    // Pusher webhook for notification delivery status
    Route::post('/pusher', [\App\Http\Controllers\WebhookController::class, 'pusher'])
        ->name('webhooks.pusher');
    
    // Twilio webhook for SMS notifications
    Route::post('/twilio', [\App\Http\Controllers\WebhookController::class, 'twilio'])
        ->name('webhooks.twilio');
    
    // Firebase Cloud Messaging webhook
    Route::post('/fcm', [\App\Http\Controllers\WebhookController::class, 'fcm'])
        ->name('webhooks.fcm');
});


// =============================================
// PROPERTY OWNERSHIP TRANSFER ROUTES
// =============================================

Route::middleware(['auth'])->group(function () {
    
    // ========== LANDLORD ROUTES (Using LandlordTransferController) ==========
    Route::middleware(['multi.auth.user:2'])->prefix('properties/{property}')->name('properties.')->group(function () {
        
        // Show transfer request form (FULL PAGE)
        Route::get('/ownership-transfer/create', [LandlordTransferController::class, 'create'])
            ->name('ownership-transfers.create');
        
        // ✅ NEW: Get transfer form HTML via AJAX (PARTIAL)
        Route::get('/ownership-transfer/form', [LandlordTransferController::class, 'getForm'])
            ->name('ownership-transfers.form');
        
        // Submit transfer request (single or bulk)
        Route::post('/ownership-transfer', [LandlordTransferController::class, 'store'])
            ->name('ownership-transfers.store');
        
        // Bulk transfer endpoint (without property parameter)
        Route::post('/ownership-transfer/bulk', function() {
            $controller = app()->make(LandlordTransferController::class);
            return app()->call([$controller, 'store']);
        })->name('ownership-transfers.store.bulk');
        
        // View specific transfer request
        Route::get('/ownership-transfers/{transfer}', [LandlordTransferController::class, 'show'])
            ->name('ownership-transfers.show');
        
        // Cancel transfer request
        Route::post('/ownership-transfers/{transfer}/cancel', [LandlordTransferController::class, 'cancel'])
            ->name('ownership-transfers.cancel');
        
        // Download transfer document
        Route::get('/ownership-transfers/{transfer}/download', [LandlordTransferController::class, 'downloadDocument'])
            ->name('ownership-transfers.download');
        
        // Download transfer certificate
        Route::get('/ownership-transfers/{transfer}/certificate', [LandlordTransferController::class, 'downloadCertificate'])
            ->name('ownership-transfers.certificate');
        
        // Property ownership history (property-specific)
        Route::get('/ownership-history', [PropertyHistoryController::class, 'propertyOwnershipHistory'])
            ->name('ownership-history');
        
        // Verify digital signature
        Route::post('/ownership-transfers/{transfer}/verify-signature', [TransferWebhookController::class, 'verifySignature'])
            ->name('ownership-transfers.verify-signature');
        
        // ========== RESUBMIT REJECTED TRANSFER ROUTES ==========
        Route::get('/ownership-transfers/{transfer}/resubmit', [TransferResubmissionController::class, 'resubmit'])
            ->name('ownership-transfers.resubmit');
        
        // Process resubmission
        Route::post('/ownership-transfers/{transfer}/process-resubmit', [TransferResubmissionController::class, 'processResubmit'])
            ->name('ownership-transfers.process-resubmit');
        
        // ========== TRANSFER REVERSAL ROUTES (Landlord) ==========
        // Request reversal of a completed transfer
        Route::post('/ownership-transfers/{transfer}/request-reversal', [LandlordTransferController::class, 'requestReversal'])
            ->name('ownership-transfers.request-reversal');
        
        // Cancel a pending reversal request
        Route::delete('/ownership-transfers/{transfer}/cancel-reversal', [LandlordTransferController::class, 'cancelReversalRequest'])
            ->name('ownership-transfers.cancel-reversal');
    });

    
    // ========== ADMIN ROUTES (Using PropertyOwnershipTransferController) ==========
    Route::middleware(['multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
        
        // Dashboard
        Route::get('/ownership-transfers/dashboard', [PropertyOwnershipTransferController::class, 'dashboard'])
            ->name('ownership-transfers.dashboard');
        
        // =============================================
        // !!! CRITICAL: TRASH ROUTES MUST COME BEFORE WILDCARD ROUTES !!!
        // =============================================
        
        // ---------- UNIFIED TRASH MANAGEMENT ROUTES ----------
        // List trashed transfers (unified - regular + reversal)
        Route::get('/ownership-transfers/trash', [TransferTrashController::class, 'trash'])
            ->name('ownership-transfers.trash');
        
        // Get unified trash statistics (AJAX)
        Route::get('/ownership-transfers/trash/stats', [TransferTrashController::class, 'trashStats'])
            ->name('ownership-transfers.trash.stats');
        
        // Preview trash before emptying (AJAX)
        Route::get('/ownership-transfers/trash/preview', [TransferTrashController::class, 'previewTrash'])
            ->name('ownership-transfers.trash.preview');
        
        // Export trashed transfers (unified with type filter)
        Route::get('/ownership-transfers/trash/export/{format?}', [TransferTrashController::class, 'exportTrash'])
            ->name('ownership-transfers.trash.export');
        
        // View trash activity log (includes reversal actions)
        Route::get('/ownership-transfers/trash/activity', [TransferTrashController::class, 'trashActivityLog'])
            ->name('ownership-transfers.trash.activity');
        
        // Auto cleanup (can be called by scheduler)
        Route::post('/ownership-transfers/trash/cleanup', [TransferTrashController::class, 'autoCleanupTrash'])
            ->name('ownership-transfers.trash.cleanup');
        
        // Get fresh trashed count (bypass cache - returns regular and reversal counts)
        Route::get('/ownership-transfers/trash/fresh-count', [TransferTrashController::class, 'getFreshTrashedCount'])
            ->name('ownership-transfers.trash.fresh-count');
        
        // ========== SINGLE TRANSFER RESTORE ROUTE (Unified) ==========
        Route::post('/ownership-transfers/trash/restore/{transferId}', [TransferTrashController::class, 'restore'])
            ->name('ownership-transfers.trash.restore');
        
        // ========== BULK RESTORE ROUTE (Unified with originals option) ==========
        Route::post('/ownership-transfers/trash/bulk-restore', [TransferTrashController::class, 'bulkRestore'])
            ->name('ownership-transfers.trash.bulk-restore');
        
        // ========== SINGLE PERMANENT DELETE ROUTE (Unified) ==========
        Route::delete('/ownership-transfers/trash/force-delete/{transferId}', [TransferTrashController::class, 'forceDelete'])
            ->name('ownership-transfers.trash.force-delete');
        
        // ========== BULK PERMANENT DELETE ROUTE (Unified with originals option) ==========
        Route::delete('/ownership-transfers/trash/bulk-force-delete', [TransferTrashController::class, 'bulkForceDelete'])
            ->name('ownership-transfers.trash.bulk-force-delete');
        
        // ========== EMPTY TRASH ROUTE (Unified with type filters) ==========
        Route::delete('/ownership-transfers/trash/empty', [TransferTrashController::class, 'emptyTrash'])
            ->name('ownership-transfers.trash.empty');
        
        // ========== SOFT DELETE ROUTE (MOVE TO TRASH - Handles both regular and reversal) ==========
        Route::delete('/ownership-transfers/{transfer}', [PropertyOwnershipTransferController::class, 'destroy'])
            ->name('ownership-transfers.destroy');
        
        // ========== BULK SOFT DELETE ROUTE ==========
        Route::post('/ownership-transfers/bulk-soft-delete', [PropertyOwnershipTransferController::class, 'bulkSoftDelete'])
            ->name('ownership-transfers.bulk-soft-delete');
        
        // ========== REVERSAL TRASH DASHBOARD (Statistics view) ==========
        Route::get('/ownership-transfers/reversal-trash-dashboard', [TransferTrashController::class, 'reversalTrashDashboard'])
            ->name('ownership-transfers.reversal-trash-dashboard');
        
        // ========== REVERSAL TRASH BULK OPERATIONS ==========
        // Bulk restore reversal transfers
        Route::post('/ownership-transfers/trash/bulk-restore-reversals', [TransferTrashController::class, 'bulkRestoreReversals'])
            ->name('ownership-transfers.trash.bulk-restore-reversals');
        
        // Bulk permanently delete reversal transfers
        Route::delete('/ownership-transfers/trash/bulk-force-delete-reversals', [TransferTrashController::class, 'bulkForceDeleteReversals'])
            ->name('ownership-transfers.trash.bulk-force-delete-reversals');
        
        // ========== YEARLY ARCHIVE TRIGGER (Manual Admin Action) ==========
        // Manually trigger yearly archive process
        Route::post('/ownership-transfers/trigger-yearly-archive', [PropertyOwnershipTransferController::class, 'triggerYearlyArchive'])
            ->name('ownership-transfers.trigger-yearly-archive');
        
        // ========== ARCHIVE MANAGEMENT ROUTES ==========
        
        // ---------- ARCHIVE LISTING AND VIEWING ----------
        // List archived transfers
        Route::get('/ownership-transfers/archive', [TransferArchiveController::class, 'index'])
            ->name('ownership-transfers.archive');
        
        // Get archive statistics (AJAX)
        Route::get('/ownership-transfers/archive/stats', [TransferArchiveController::class, 'stats'])
            ->name('ownership-transfers.archive.stats');
        
        // View single archived transfer details
        Route::get('/ownership-transfers/archive/{archiveId}', [TransferArchiveController::class, 'show'])
            ->name('ownership-transfers.archive.show');
        
        // Preview archived transfer (AJAX)
        Route::get('/ownership-transfers/archive/preview/{archiveId}', [TransferArchiveController::class, 'preview'])
            ->name('ownership-transfers.archive.preview');
        
        // ---------- ARCHIVE EXPORT ROUTES ----------
        // Export archives to CSV
        Route::get('/ownership-transfers/archive/export/{format?}', [TransferArchiveController::class, 'exportCsv'])
            ->name('ownership-transfers.archive.export');
        
        // Export single archive
        Route::get('/ownership-transfers/archive/export/{archiveId}/single', [TransferArchiveController::class, 'exportSingleArchive'])
            ->name('ownership-transfers.archive.export-single');
        
        // Export archive by year
        Route::get('/ownership-transfers/archive/export-year/{year}', [TransferArchiveController::class, 'exportByYear'])
            ->name('ownership-transfers.archive.export-year');
        
        // ---------- ARCHIVE RESTORE ROUTES ----------
        // Restore single archive back to main table
        Route::post('/ownership-transfers/archive/restore', [TransferArchiveController::class, 'restore'])
            ->name('ownership-transfers.archive.restore');
        
        // Bulk restore archives
        Route::post('/ownership-transfers/archive/bulk-restore', [TransferArchiveController::class, 'bulkRestore'])
            ->name('ownership-transfers.archive.bulk-restore');
        
        // ---------- ARCHIVE PERMANENT DELETE ROUTES ----------
        // Permanently delete single archive
        Route::delete('/ownership-transfers/archive/delete/{archiveId}', [TransferArchiveController::class, 'destroy'])
            ->name('ownership-transfers.archive.delete');
        
        // Bulk permanently delete archives
        Route::delete('/ownership-transfers/archive/bulk-delete', [TransferArchiveController::class, 'bulkDestroy'])
            ->name('ownership-transfers.archive.bulk-delete');
        
        // Delete archives older than specified years
        Route::delete('/ownership-transfers/archive/delete-old/{years}', [TransferArchiveController::class, 'deleteOldArchives'])
            ->name('ownership-transfers.archive.delete-old');
        
        // Empty entire archive
        Route::delete('/ownership-transfers/archive/empty', [TransferArchiveController::class, 'emptyArchive'])
            ->name('ownership-transfers.archive.empty');
        
        // ---------- ARCHIVE SEARCH AND FILTERS ----------
        // Search within archives
        Route::get('/ownership-transfers/archive/search', [TransferArchiveController::class, 'search'])
            ->name('ownership-transfers.archive.search');
        
        // Get archive years filter options
        Route::get('/ownership-transfers/archive/years', [TransferArchiveController::class, 'getYears'])
            ->name('ownership-transfers.archive.years');
        
        // Get archive statistics by year
        Route::get('/ownership-transfers/archive/stats/year/{year}', [TransferArchiveController::class, 'yearStats'])
            ->name('ownership-transfers.archive.stats.year');
        
        // ---------- ARCHIVE ACTIVITY LOGS ----------
        // View archive activity logs
        Route::get('/ownership-transfers/archive/activity-logs', [TransferArchiveController::class, 'archiveActivityLogs'])
            ->name('ownership-transfers.archive.activity-logs');
        
        // View archive restore history
        Route::get('/ownership-transfers/archive/restore-history', [TransferArchiveController::class, 'archiveRestoreHistory'])
            ->name('ownership-transfers.archive.restore-history');
        
        // ---------- ARCHIVE MAINTENANCE ROUTES ----------
        // Run archive cleanup (remove old archives)
        Route::post('/ownership-transfers/archive/cleanup', [TransferArchiveController::class, 'runArchiveCleanup'])
            ->name('ownership-transfers.archive.cleanup');
        
        // Generate archive summary report
        Route::get('/ownership-transfers/archive/report/{year}', [TransferArchiveController::class, 'generateReport'])
            ->name('ownership-transfers.archive.report');
        
        // Download archive certificate
        Route::get('/ownership-transfers/archive/{archiveId}/certificate', [TransferArchiveController::class, 'downloadCertificate'])
            ->name('ownership-transfers.archive.certificate');
        
        // ========== ADMIN CERTIFICATE DOWNLOAD ROUTES ==========
        Route::get('/ownership-transfers/certificate/{transfer}', [PropertyOwnershipTransferController::class, 'downloadCertificate'])
            ->name('ownership-transfers.download-certificate');
        
        // Download document
        Route::get('/ownership-transfers/document/{transfer}', [PropertyOwnershipTransferController::class, 'downloadDocument'])
            ->name('ownership-transfers.download-document');
        
        // ========== ADVANCED SEARCH ==========
        Route::get('/ownership-transfers/search', [PropertyOwnershipTransferController::class, 'search'])
            ->name('ownership-transfers.search');
        
        // ========== EXPORT ROUTES ==========
        Route::get('/ownership-transfers/export/{format?}', [PropertyOwnershipTransferController::class, 'export'])
            ->name('ownership-transfers.export');
        
        // ========== BULK ACTION ROUTES ==========
        Route::post('/ownership-transfers/bulk-approve', [PropertyOwnershipTransferController::class, 'bulkApprove'])
            ->name('ownership-transfers.bulk-approve');
        
        Route::post('/ownership-transfers/bulk-reject', [PropertyOwnershipTransferController::class, 'bulkReject'])
            ->name('ownership-transfers.bulk-reject');
        
        // ========== TRANSFER REVERSAL MANAGEMENT ROUTES (Admin) ==========
        // List all pending reversal requests
        Route::get('/ownership-transfers/reversal-requests', [PropertyOwnershipTransferController::class, 'reversalRequests'])
            ->name('ownership-transfers.reversal-requests');
        
        // Approve a reversal request
        Route::post('/ownership-transfers/reversal/{transferId}/approve', [PropertyOwnershipTransferController::class, 'approveReversal'])
            ->name('ownership-transfers.reversal.approve');
        
        // Reject a reversal request
        Route::post('/ownership-transfers/reversal/{transferId}/reject', [PropertyOwnershipTransferController::class, 'rejectReversal'])
            ->name('ownership-transfers.reversal.reject');
        
        // Process reversal (execute the actual reversal)
        Route::post('/ownership-transfers/reversal/{transferId}/process', [PropertyOwnershipTransferController::class, 'processReversal'])
            ->name('ownership-transfers.reversal.process');
        
        // Check for expired reversal requests (can be called by scheduler)
        Route::get('/ownership-transfers/reversal/check-expired', [PropertyOwnershipTransferController::class, 'checkExpiredReversalRequests'])
            ->name('ownership-transfers.reversal.check-expired');
        
        // ========== LIST ALL TRANSFERS (INDEX) - WILDCARD ROUTES GO LAST! ==========
        Route::get('/ownership-transfers', [PropertyOwnershipTransferController::class, 'index'])
            ->name('ownership-transfers.index');
        
        // ========== VIEW SPECIFIC TRANSFER (WILDCARD) - ABSOLUTE LAST! ==========
        Route::get('/ownership-transfers/{transfer}', [PropertyOwnershipTransferController::class, 'show'])
            ->name('ownership-transfers.show');
        
        // ========== PROPERTY-SPECIFIC ADMIN ROUTES ==========
        Route::prefix('properties/{property}')->group(function () {
            
            // Approve transfer request (SINGLE APPROVAL)
            Route::post('/ownership-transfers/{transfer}/approve', [PropertyOwnershipTransferController::class, 'approve'])
                ->name('properties.ownership-transfers.approve');
            
            // Reject transfer request (SINGLE REJECTION)
            Route::post('/ownership-transfers/{transfer}/reject', [PropertyOwnershipTransferController::class, 'reject'])
                ->name('properties.ownership-transfers.reject');
            
            // Complete transfer (after new landlord accepts)
            Route::post('/ownership-transfers/{transfer}/complete', [PropertyOwnershipTransferController::class, 'complete'])
                ->name('properties.ownership-transfers.complete');
            
            // Get transfer readiness report
            Route::get('/ownership-transfers/{transfer}/readiness', [PropertyOwnershipTransferController::class, 'getTransferReadinessReport'])
                ->name('properties.ownership-transfers.readiness');
            
            // Mark transfer as verified (workflow step)
            Route::post('/ownership-transfers/{transfer}/verify', function(Property $property, PropertyOwnershipTransfer $transfer) {
                $transfer->update(['approval_workflow_step' => 'verification_completed']);
                return redirect()->back()->with('success', 'Transfer marked as verified');
            })->name('properties.ownership-transfers.verify');
            
            // Download document (admin access with property)
            Route::get('/ownership-transfers/{transfer}/download', [PropertyOwnershipTransferController::class, 'downloadDocument'])
                ->name('admin.properties.ownership-transfers.download');
            
            // Download certificate (admin access with property)
            Route::get('/ownership-transfers/{transfer}/certificate', [PropertyOwnershipTransferController::class, 'downloadCertificate'])
                ->name('admin.properties.ownership-transfers.certificate');
            
            // Webhook management
            Route::get('/ownership-transfers/{transfer}/webhooks', [TransferWebhookController::class, 'index'])
                ->name('properties.ownership-transfers.webhooks');
            
            Route::post('/ownership-transfers/{transfer}/webhooks/{webhookId}/retry', [TransferWebhookController::class, 'retry'])
                ->name('properties.ownership-transfers.webhooks.retry');
        });
        
        // Cleanup expired transfers (maintenance)
        Route::post('/ownership-transfers/cleanup', function() {
            $count = PropertyOwnershipTransfer::cleanupExpiredTransfers();
            return response()->json(['cleaned' => $count]);
        })->name('ownership-transfers.cleanup');
        
        // Get accurate counts for dashboard
        Route::get('/ownership-transfers/accurate-counts', [PropertyOwnershipTransferController::class, 'getAccurateCounts'])
            ->name('ownership-transfers.accurate-counts');
    });
    
    // ========== LANDLORD OVERVIEW ROUTES (Using LandlordTransferController) ==========
    Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
        // Dashboard
        Route::get('/ownership-transfers/dashboard', [LandlordTransferController::class, 'dashboard'])
            ->name('ownership-transfers.dashboard');
        
        // View all ownership transfers for landlord
        Route::get('/ownership-transfers', [LandlordTransferController::class, 'index'])
            ->name('ownership-transfers.index');
        
        // View ownership transfer history (landlord overview)
        Route::get('/ownership-history', [LandlordTransferController::class, 'history'])
            ->name('ownership-history');
        
        // View completed transfers
        Route::get('/ownership-transfers/completed', [LandlordTransferController::class, 'completed'])
            ->name('ownership-transfers.completed');
        
        // Export landlord's transfer history - handles CSV export
        Route::get('/ownership-transfers/export', [LandlordTransferController::class, 'history'])
            ->name('ownership-transfers.export');
        
        // Alternative: Direct CSV export route
        Route::get('/ownership-transfers/export-csv', [LandlordTransferController::class, 'exportHistory'])
            ->name('ownership-transfers.export-csv');
        
        // View archived transfers for landlord
        Route::get('/ownership-transfers/archive', [LandlordTransferController::class, 'archiveIndex'])
            ->name('ownership-transfers.archive');
        
        // Download archived certificate for landlord
        Route::get('/ownership-transfers/archive/{archiveId}/certificate', [LandlordTransferController::class, 'downloadArchiveCertificate'])
            ->name('ownership-transfers.archive.certificate');
        
        // Export landlord's archived transfers
        Route::get('/ownership-transfers/archive/export', [LandlordTransferController::class, 'exportLandlordArchive'])
            ->name('ownership-transfers.archive.export');
        
        // View reversal status for landlord
        Route::get('/ownership-transfers/reversal-status/{transfer}', [LandlordTransferController::class, 'getReversalStatus'])
            ->name('ownership-transfers.reversal-status');
    });
    
    // ========== SHARED DASHBOARD ROUTE ==========
    Route::get('/dashboard/ownership-transfers', function() {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.ownership-transfers.dashboard');
        }
        return redirect()->route('landlord.ownership-transfers.dashboard');
    })->name('dashboard.ownership-transfers');
    
    // ========== SHARED API ROUTES ==========
    Route::prefix('api')->name('api.')->group(function () {
        
        // Get ownership transfer status (includes reversal info)
        Route::get('/properties/{property}/ownership-transfers/{transfer}/status', function(Property $property, PropertyOwnershipTransfer $transfer) {
            return response()->json([
                'status' => $transfer->status,
                'status_label' => $transfer->status_label,
                'can_approve' => $transfer->canBeApproved(),
                'can_reject' => $transfer->canBeRejected(),
                'can_complete' => $transfer->canBeCompleted(),
                'can_cancel' => $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING,
                'can_resubmit' => $transfer->canBeResubmitted(),
                'workflow_step' => $transfer->approval_workflow_step,
                'next_workflow_step' => $transfer->next_workflow_step,
                'requires_digital_signature' => $transfer->requires_digital_signature,
                'digital_signature_verified' => $transfer->digital_signature_verified,
                'is_bulk_transfer' => $transfer->is_bulk_transfer,
                'processing_time_days' => $transfer->processing_time_days,
                'transfer_history' => $transfer->transfer_history,
                'is_trashed' => $transfer->trashed(),
                'can_be_restored' => $transfer->canBeRestored(),
                'can_be_permanently_deleted' => $transfer->canBePermanentlyDeleted(),
                'rejection_reason' => $transfer->rejection_reason,
                'resubmission_eligible' => $transfer->canBeResubmitted(),
                'resubmission_deadline' => $transfer->can_resubmit_after ? $transfer->can_resubmit_after->format('Y-m-d') : null,
                // Reversal info
                'reversal_status' => $transfer->reversal_status,
                'reversal_status_label' => $transfer->reversal_status_label,
                'reversal_requested_at' => $transfer->reversal_requested_at ? $transfer->reversal_requested_at->format('Y-m-d H:i:s') : null,
                'is_reversed' => $transfer->is_reversed,
                'is_reversal_record' => $transfer->isReversalRecord(),
                'can_request_reversal' => $transfer->canRequestReversal
            ]);
        })->name('properties.ownership-transfers.status');
        
        // Get transfer statistics for dashboard
        Route::get('/ownership-transfers/statistics', function() {
            return response()->json(PropertyOwnershipTransfer::getStatistics());
        })->name('ownership-transfers.statistics');
        
        // Get unified trash statistics (API)
        Route::get('/ownership-transfers/trash/unified-stats', [TransferTrashController::class, 'trashStats'])
            ->name('ownership-transfers.trash.unified-stats');
        
        // Get transfer statistics for specific landlord
        Route::get('/landlord/{landlordId}/ownership-transfers/statistics', function($landlordId) {
            $stats = [
                'total' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)->count(),
                'pending' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count(),
                'approved' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count(),
                'completed' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count(),
                'rejected' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count(),
                'cancelled' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count(),
                'total_value' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->sum('sale_amount'),
                'trashed' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('current_landlord_id', $landlordId)
                    ->count(),
                'archived' => \App\Models\PropertyOwnershipTransferArchive::where('current_landlord_id', $landlordId)->count(),
                'resubmittable' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)
                    ->where(function($q) {
                        $q->whereNull('can_resubmit_after')
                          ->orWhere('can_resubmit_after', '<=', now());
                    })
                    ->count(),
                // Reversal stats
                'reversal_requests' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('reversal_status', 'pending')
                    ->count(),
                'reversals_completed' => PropertyOwnershipTransfer::where('current_landlord_id', $landlordId)
                    ->where('is_reversed', true)
                    ->count(),
                'reversal_records_in_trash' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('current_landlord_id', $landlordId)
                    ->where('metadata->is_reversal', true)
                    ->count()
            ];
            return response()->json($stats);
        })->name('landlord.ownership-transfers.statistics');
        
        // Get trash statistics (API - unified)
        Route::get('/ownership-transfers/trash/stats', [TransferTrashController::class, 'trashStats'])
            ->name('ownership-transfers.trash.stats');
        
        // Get archive statistics (API)
        Route::get('/ownership-transfers/archive/stats', [TransferArchiveController::class, 'stats'])
            ->name('ownership-transfers.archive.stats');
        
        // Get monthly trend data
        Route::get('/ownership-transfers/monthly-trend', function() {
            $data = PropertyOwnershipTransfer::select(
                    \DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    \DB::raw('COUNT(*) as count'),
                    \DB::raw('SUM(sale_amount) as total_value')
                )
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            return response()->json($data);
        })->name('ownership-transfers.monthly-trend');
        
        // Get reversal monthly trend
        Route::get('/ownership-transfers/reversal-monthly-trend', function() {
            $data = PropertyOwnershipTransfer::select(
                    \DB::raw('DATE_FORMAT(reversal_requested_at, "%Y-%m") as month'),
                    \DB::raw('COUNT(*) as count'),
                    \DB::raw('SUM(CASE WHEN reversal_status = "completed" THEN 1 ELSE 0 END) as completed_count')
                )
                ->whereNotNull('reversal_requested_at')
                ->whereYear('reversal_requested_at', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            return response()->json($data);
        })->name('ownership-transfers.reversal-monthly-trend');
        
        // Get archive monthly trend
        Route::get('/ownership-transfers/archive/monthly-trend', function() {
            $data = PropertyOwnershipTransferArchive::select(
                    \DB::raw('archive_year as year'),
                    \DB::raw('MONTH(archived_at) as month'),
                    \DB::raw('COUNT(*) as count'),
                    \DB::raw('SUM(sale_amount) as total_value')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();
            
            return response()->json($data);
        })->name('ownership-transfers.archive.monthly-trend');
        
        // Get trashed transfers monthly trend (unified)
        Route::get('/ownership-transfers/trash/monthly-trend', function() {
            $data = PropertyOwnershipTransfer::onlyTrashed()
                ->select(
                    \DB::raw('DATE_FORMAT(deleted_at, "%Y-%m") as month'),
                    \DB::raw('COUNT(*) as count'),
                    \DB::raw('SUM(CASE WHEN metadata->is_reversal = true THEN 1 ELSE 0 END) as reversal_count'),
                    \DB::raw('SUM(sale_amount) as total_value')
                )
                ->whereYear('deleted_at', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            return response()->json($data);
        })->name('ownership-transfers.trash.monthly-trend');
        
        // Verify digital signature (API endpoint)
        Route::post('/properties/{property}/ownership-transfers/{transfer}/verify-signature', 
            [TransferWebhookController::class, 'verifySignature'])
            ->name('api.properties.ownership-transfers.verify-signature');
        
        // Webhook management (API)
        Route::get('/properties/{property}/ownership-transfers/{transfer}/webhooks', 
            [TransferWebhookController::class, 'index'])
            ->name('api.properties.ownership-transfers.webhooks');
        
        Route::post('/properties/{property}/ownership-transfers/{transfer}/webhooks/{webhookId}/retry', 
            [TransferWebhookController::class, 'retry'])
            ->name('api.properties.ownership-transfers.webhooks.retry');
        
        // Generate document reference
        Route::get('/ownership-transfers/generate-reference', function() {
            $date = now()->format('Ymd');
            $random = strtoupper(substr(md5(uniqid()), 0, 6));
            return response()->json(['reference' => "TRANS-{$date}-{$random}"]);
        })->name('ownership-transfers.generate-reference');
        
        // Check bulk transfer availability
        Route::get('/properties/{property}/bulk-transfer/availability', function(Property $property) {
            $user = auth()->user();
            $relatedProperties = $user->properties()
                ->where('id', '!=', $property->id)
                ->whereDoesntHave('currentOwnershipTransfer', function($query) {
                    $query->whereIn('status', [
                        PropertyOwnershipTransfer::STATUS_PENDING,
                        PropertyOwnershipTransfer::STATUS_APPROVED
                    ]);
                })
                ->select('id', 'property_name', 'registration_pattern', 'street_name', 'zone')
                ->get();
            
            return response()->json([
                'available' => $relatedProperties->isNotEmpty(),
                'count' => $relatedProperties->count(),
                'properties' => $relatedProperties
            ]);
        })->name('properties.bulk-transfer.availability');
        
        // Get list of users who have deleted transfers (for filter)
        Route::get('/ownership-transfers/trash/deleters', function() {
            $deleters = PropertyOwnershipTransfer::onlyTrashed()
                ->select('metadata->soft_deleted->deleted_by as id', 
                         'metadata->soft_deleted->deleted_by_name as name')
                ->whereNotNull('metadata->soft_deleted->deleted_by')
                ->distinct()
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name ?? 'Unknown User'
                    ];
                });
            
            return response()->json($deleters);
        })->name('ownership-transfers.trash.deleters');
        
        // Resend invitation API
        Route::post('/ownership-transfers/{transfer}/resend-invitation', function(PropertyOwnershipTransfer $transfer) {
            $controller = app()->make(PropertyOwnershipTransferController::class);
            $result = $controller->sendOwnershipTransferInvitation($transfer);
            return response()->json($result);
        })->name('ownership-transfers.resend-invitation');
        
        // ========== RESUBMISSION API ROUTES ==========
        Route::get('/ownership-transfers/{transfer}/resubmission-eligibility', [TransferResubmissionController::class, 'checkEligibility'])
            ->name('ownership-transfers.resubmission-eligibility');
        
        // Preview resubmission data
        Route::get('/ownership-transfers/{transfer}/preview-resubmit', [TransferResubmissionController::class, 'previewResubmit'])
            ->name('ownership-transfers.preview-resubmit');
        
        // ========== REVERSAL API ROUTES ==========
        // Check reversal eligibility
        Route::get('/ownership-transfers/{transfer}/reversal-eligibility', function(PropertyOwnershipTransfer $transfer) {
            return response()->json([
                'can_request_reversal' => $transfer->canRequestReversal,
                'reversal_status' => $transfer->reversal_status,
                'reversal_status_label' => $transfer->reversal_status_label,
                'is_reversed' => $transfer->is_reversed,
                'has_pending_request' => $transfer->reversal_status === 'pending',
                'reversal_deadline' => $transfer->reversal_deadline ? $transfer->reversal_deadline->format('Y-m-d') : null,
                'is_expired' => $transfer->isReversalExpired(),
                'reversal_details' => $transfer->reversal_details
            ]);
        })->name('ownership-transfers.reversal-eligibility');
        
        // Get reversal history for a transfer
        Route::get('/ownership-transfers/{transfer}/reversal-history', function(PropertyOwnershipTransfer $transfer) {
            $reversalRecords = PropertyOwnershipTransfer::where('original_transfer_id', $transfer->id)
                ->orWhere('reversal_transfer_id', $transfer->id)
                ->with(['reversalRequestedBy', 'reversalProcessedBy'])
                ->get();
            
            return response()->json([
                'transfer_id' => $transfer->id,
                'is_reversed' => $transfer->is_reversed,
                'reversal_status' => $transfer->reversal_status,
                'reversal_records' => $reversalRecords->map(function($record) {
                    return [
                        'id' => $record->id,
                        'status' => $record->reversal_status,
                        'requested_at' => $record->reversal_requested_at,
                        'requested_by' => $record->reversalRequestedBy?->name,
                        'processed_at' => $record->reversal_processed_at,
                        'processed_by' => $record->reversalProcessedBy?->name,
                        'reason' => $record->reversal_reason,
                        'is_reversal_record' => $record->isReversalRecord(),
                        'is_trashed' => $record->trashed()
                    ];
                })
            ]);
        })->name('ownership-transfers.reversal-history');
        
        // ========== ARCHIVE API ROUTES ==========
        
        // Search within archives
        Route::get('/ownership-transfers/archive/search', [TransferArchiveController::class, 'search'])
            ->name('ownership-transfers.archive.search');
        
        // Get archive by year
        Route::get('/ownership-transfers/archive/year/{year}', function($year) {
            $archives = PropertyOwnershipTransferArchive::where('archive_year', $year)
                ->with(['property', 'currentLandlord', 'newLandlord'])
                ->paginate(20);
            return response()->json($archives);
        })->name('ownership-transfers.archive.by-year');
        
        // Get archive summary by year range
        Route::get('/ownership-transfers/archive/summary/{startYear}/{endYear}', function($startYear, $endYear) {
            $summary = PropertyOwnershipTransferArchive::whereBetween('archive_year', [$startYear, $endYear])
                ->select(
                    'archive_year',
                    \DB::raw('COUNT(*) as count'),
                    \DB::raw('SUM(CASE WHEN status = "completed" THEN sale_amount ELSE 0 END) as total_value')
                )
                ->groupBy('archive_year')
                ->orderBy('archive_year')
                ->get();
            return response()->json($summary);
        })->name('ownership-transfers.archive.summary');
        
        // Compare archive statistics across years
        Route::get('/ownership-transfers/archive/compare/{years}', function($years) {
            $yearArray = explode(',', $years);
            $comparison = [];
            foreach ($yearArray as $year) {
                $comparison[$year] = [
                    'total' => PropertyOwnershipTransferArchive::where('archive_year', $year)->count(),
                    'completed' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                        ->where('status', 'completed')->count(),
                    'total_value' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                        ->where('status', 'completed')->sum('sale_amount')
                ];
            }
            return response()->json($comparison);
        })->name('ownership-transfers.archive.compare');
        
        // Get archive storage usage
        Route::get('/ownership-transfers/archive/storage-usage', [TransferArchiveController::class, 'getArchiveStorageUsage'])
            ->name('ownership-transfers.archive.storage-usage');
        
        // Get reversal storage usage
        Route::get('/ownership-transfers/reversal/storage-usage', function() {
            $reversals = PropertyOwnershipTransfer::where('metadata->is_reversal', true)->get();
            $totalBytes = 0;
            
            foreach ($reversals as $reversal) {
                if ($reversal->document_url) {
                    try {
                        $totalBytes += Storage::disk('public')->size($reversal->document_url);
                    } catch (\Exception $e) {}
                }
                if ($reversal->certificate_url) {
                    try {
                        $totalBytes += Storage::disk('public')->size($reversal->certificate_url);
                    } catch (\Exception $e) {}
                }
            }
            
            return response()->json([
                'storage_used_mb' => round($totalBytes / (1024 * 1024), 2),
                'reversal_count' => $reversals->count()
            ]);
        })->name('ownership-transfers.reversal.storage-usage');
        
        // Get reversal trash statistics
        Route::get('/ownership-transfers/trash/reversal-stats', function() {
            return response()->json([
                'total_reversals_in_trash' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->count(),
                'reversals_with_original' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->whereNotNull('metadata->original_transfer_id')
                    ->count(),
                'orphaned_reversals' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->whereNull('metadata->original_transfer_id')
                    ->count(),
                'storage_used_mb' => PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->get()
                    ->sum(function($reversal) {
                        $bytes = 0;
                        if ($reversal->document_url) {
                            try {
                                $bytes += Storage::disk('public')->size($reversal->document_url);
                            } catch (\Exception $e) {}
                        }
                        if ($reversal->certificate_url) {
                            try {
                                $bytes += Storage::disk('public')->size($reversal->certificate_url);
                            } catch (\Exception $e) {}
                        }
                        return $bytes;
                    }) / (1024 * 1024)
            ]);
        })->name('ownership-transfers.trash.reversal-stats');
    });
    
    // ========== WEBHOOK ROUTES (Public) ==========
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('/ownership-transfer/{token}', function($token) {
            \Log::info('Webhook received for ownership transfer', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('ownership-transfer');
        
        Route::post('/test', function() {
            \Log::info('Test webhook received');
            return response()->json(['status' => 'ok', 'message' => 'Webhook test successful']);
        })->name('test');
        
        Route::post('/transfer-restored/{token}', function($token) {
            \Log::info('Transfer restore webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-restored');
        
        Route::post('/transfer-permanently-deleted/{token}', function($token) {
            \Log::info('Transfer permanent deletion webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-permanently-deleted');
        
        Route::post('/transfer-resubmitted/{token}', function($token) {
            \Log::info('Transfer resubmission webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-resubmitted');
        
        Route::post('/transfer-archived/{token}', function($token) {
            \Log::info('Transfer archived webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-archived');
        
        Route::post('/transfer-restored-from-archive/{token}', function($token) {
            \Log::info('Transfer restored from archive webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-restored-from-archive');
        
        // Reversal webhooks
        Route::post('/transfer-reversal-requested/{token}', function($token) {
            \Log::info('Transfer reversal requested webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-reversal-requested');
        
        Route::post('/transfer-reversal-approved/{token}', function($token) {
            \Log::info('Transfer reversal approved webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-reversal-approved');
        
        Route::post('/transfer-reversal-rejected/{token}', function($token) {
            \Log::info('Transfer reversal rejected webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-reversal-rejected');
        
        Route::post('/transfer-reversal-completed/{token}', function($token) {
            \Log::info('Transfer reversal completed webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-reversal-completed');
        
        Route::post('/transfer-reversal-expired/{token}', function($token) {
            \Log::info('Transfer reversal expired webhook received', ['token' => $token]);
            return response()->json(['received' => true]);
        })->name('transfer-reversal-expired');
    });
});

// ========== PUBLIC ROUTES ==========
Route::get('/certificates/ownership-transfer/{transfer}/{token}', 
    function(PropertyOwnershipTransfer $transfer, $token) {
        if ($transfer->public_token !== $token) {
            abort(403, 'Invalid access token');
        }
        $property = $transfer->property;
        $controller = app()->make(LandlordTransferController::class);
        return app()->call([$controller, 'downloadCertificate'], ['property' => $property, 'transfer' => $transfer]);
    })->name('public.ownership-transfers.certificate');

Route::get('/certificates/ownership-transfer/archive/{archiveId}/{token}', 
    function($archiveId, $token) {
        $archive = PropertyOwnershipTransferArchive::findOrFail($archiveId);
        if ($archive->public_token !== $token) {
            abort(403, 'Invalid access token');
        }
        $controller = app()->make(TransferArchiveController::class);
        return app()->call([$controller, 'downloadCertificate'], ['archiveId' => $archiveId]);
    })->name('public.ownership-transfers.archive.certificate');

Route::get('/transfers/{reference}/status', function($reference) {
    $transfer = PropertyOwnershipTransfer::where('document_reference', $reference)->first();
    
    if (!$transfer) {
        return response()->json(['error' => 'Transfer not found'], 404);
    }
    
    return response()->json([
        'reference' => $transfer->document_reference,
        'status' => $transfer->status,
        'status_label' => $transfer->status_label,
        'property_name' => $transfer->property->property_name,
        'current_owner' => $transfer->currentLandlord->name,
        'new_owner' => $transfer->newLandlord->name ?? $transfer->new_owner_name,
        'transfer_date' => $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : null,
        'created_at' => $transfer->created_at->format('Y-m-d H:i:s'),
        'is_completed' => $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED,
        'is_trashed' => $transfer->trashed(),
        'is_archived' => false,
        'deleted_at' => $transfer->trashed() ? $transfer->deleted_at->format('Y-m-d H:i:s') : null,
        'can_resubmit' => $transfer->status === PropertyOwnershipTransfer::STATUS_REJECTED && $transfer->canBeResubmitted(),
        // Reversal info for public view
        'reversal_status' => $transfer->reversal_status,
        'reversal_status_label' => $transfer->reversal_status_label,
        'is_reversed' => $transfer->is_reversed,
        'reversal_requested_at' => $transfer->reversal_requested_at ? $transfer->reversal_requested_at->format('Y-m-d H:i:s') : null,
        'can_request_reversal' => $transfer->canRequestReversal,
        'is_reversal_record' => $transfer->isReversalRecord()
    ]);
})->name('public.transfers.status');

Route::get('/transfers/archive/{reference}/status', function($reference) {
    $archive = PropertyOwnershipTransferArchive::where('document_reference', $reference)->first();
    
    if (!$archive) {
        return response()->json(['error' => 'Archived transfer not found'], 404);
    }
    
    return response()->json([
        'reference' => $archive->document_reference,
        'status' => $archive->status,
        'status_label' => $archive->status_label,
        'property_name' => $archive->property->property_name ?? 'N/A',
        'current_owner' => $archive->currentLandlord->name ?? 'N/A',
        'new_owner' => $archive->new_owner_name,
        'transfer_date' => $archive->transfer_date ? $archive->transfer_date->format('Y-m-d') : null,
        'archived_at' => $archive->archived_at->format('Y-m-d H:i:s'),
        'archive_year' => $archive->archive_year,
        'is_archived' => true,
        'certificate_available' => !is_null($archive->certificate_url)
    ]);
})->name('public.transfers.archive.status');

Route::get('/transfers/{reference}/deletion-status', function($reference) {
    $transfer = PropertyOwnershipTransfer::withTrashed()
        ->where('document_reference', $reference)
        ->first();
    
    if (!$transfer) {
        return response()->json(['error' => 'Transfer not found'], 404);
    }
    
    return response()->json([
        'reference' => $transfer->document_reference,
        'is_deleted' => $transfer->trashed(),
        'deleted_at' => $transfer->trashed() ? $transfer->deleted_at->format('Y-m-d H:i:s') : null,
        'can_be_restored' => $transfer->canBeRestored(),
        'is_reversal_record' => $transfer->isReversalRecord(),
        'restoration_available_until' => $transfer->trashed() && $transfer->canBeRestored() 
            ? $transfer->deleted_at->addDays(config('ownership_transfer.trash_retention_days', 90))->format('Y-m-d')
            : null
    ]);
})->name('public.transfers.deletion-status');

Route::get('/transfers/{reference}/archive-info', function($reference) {
    $archive = PropertyOwnershipTransferArchive::where('document_reference', $reference)->first();
    
    if (!$archive) {
        return response()->json(['error' => 'Archived transfer not found'], 404);
    }
    
    return response()->json([
        'reference' => $archive->document_reference,
        'archive_year' => $archive->archive_year,
        'archived_at' => $archive->archived_at->format('Y-m-d H:i:s'),
        'archive_reason' => $archive->archive_reason,
        'archived_by' => $archive->archivedBy->name ?? 'System',
        'original_transfer_id' => $archive->original_transfer_id,
        'can_be_restored' => true,
        'restore_available' => true
    ]);
})->name('public.transfers.archive-info');

// ========== PUBLIC REVERSAL INFO ROUTES ==========
Route::get('/transfers/{reference}/reversal-status', function($reference) {
    $transfer = PropertyOwnershipTransfer::where('document_reference', $reference)->first();
    
    if (!$transfer) {
        return response()->json(['error' => 'Transfer not found'], 404);
    }
    
    return response()->json([
        'reference' => $transfer->document_reference,
        'reversal_status' => $transfer->reversal_status,
        'reversal_status_label' => $transfer->reversal_status_label,
        'reversal_requested_at' => $transfer->reversal_requested_at ? $transfer->reversal_requested_at->format('Y-m-d H:i:s') : null,
        'reversal_processed_at' => $transfer->reversal_processed_at ? $transfer->reversal_processed_at->format('Y-m-d H:i:s') : null,
        'is_reversed' => $transfer->is_reversed,
        'reversal_deadline' => $transfer->reversal_deadline ? $transfer->reversal_deadline->format('Y-m-d') : null,
        'reversal_reason' => $transfer->reversal_reason,
        'can_request_reversal' => $transfer->canRequestReversal,
        'is_reversal_record' => $transfer->isReversalRecord()
    ]);
})->name('public.transfers.reversal-status');

Route::get('/transfers/{reference}/reversal-history', function($reference) {
    $transfer = PropertyOwnershipTransfer::where('document_reference', $reference)->first();
    
    if (!$transfer) {
        return response()->json(['error' => 'Transfer not found'], 404);
    }
    
    $reversalRecords = PropertyOwnershipTransfer::where('original_transfer_id', $transfer->id)
        ->orWhere('reversal_transfer_id', $transfer->id)
        ->with(['reversalRequestedBy', 'reversalProcessedBy'])
        ->get();
    
    return response()->json([
        'transfer_id' => $transfer->id,
        'reference' => $transfer->document_reference,
        'is_reversed' => $transfer->is_reversed,
        'reversal_status' => $transfer->reversal_status,
        'reversal_records' => $reversalRecords->map(function($record) {
            return [
                'id' => $record->id,
                'reference' => $record->document_reference,
                'status' => $record->reversal_status,
                'status_label' => $record->reversal_status_label,
                'requested_at' => $record->reversal_requested_at ? $record->reversal_requested_at->format('Y-m-d H:i:s') : null,
                'requested_by' => $record->reversalRequestedBy?->name,
                'processed_at' => $record->reversal_processed_at ? $record->reversal_processed_at->format('Y-m-d H:i:s') : null,
                'processed_by' => $record->reversalProcessedBy?->name,
                'reason' => $record->reversal_reason,
                'is_reversal_record' => $record->isReversalRecord(),
                'is_trashed' => $record->trashed()
            ];
        })
    ]);
})->name('public.transfers.reversal-history');

// ========== FALLBACK/REDIRECT ROUTES ==========
Route::get('/properties/{property}/transfer-ownership', function(Property $property) {
    return redirect()->route('properties.ownership-transfers.create', $property);
})->name('properties.transfer-ownership.old');

Route::get('/landlord/properties/transfers', function() {
    return redirect()->route('landlord.ownership-transfers.index');
})->name('landlord.properties.transfers.old');

Route::get('/admin/property-transfers', function() {
    return redirect()->route('admin.ownership-transfers.index');
})->name('admin.property-transfers.old');

Route::get('/admin/property-transfers/trash', function() {
    return redirect()->route('admin.ownership-transfers.trash');
})->name('admin.property-transfers.trash.old');

Route::get('/admin/property-transfers/archive', function() {
    return redirect()->route('admin.ownership-transfers.archive');
})->name('admin.property-transfers.archive.old');

Route::get('/admin/property-transfers/reversal-requests', function() {
    return redirect()->route('admin.ownership-transfers.reversal-requests');
})->name('admin.property-transfers.reversal-requests.old');

// ========== SCHEDULER WEBHOOKS ==========
Route::post('/scheduler/cleanup-trashed-transfers', function() {
    $token = request()->input('token');
    $expectedToken = config('ownership_transfer.scheduler_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid scheduler token');
    }
    
    $daysToKeep = request()->input('days', config('ownership_transfer.trash_retention_days', 90));
    $deletedCount = PropertyOwnershipTransfer::cleanupOldTrashedRecords($daysToKeep);
    
    \Log::info('Scheduled trash cleanup completed', [
        'deleted_count' => $deletedCount,
        'retention_days' => $daysToKeep,
        'executed_at' => now()->toISOString()
    ]);
    
    return response()->json([
        'success' => true,
        'message' => "Scheduled cleanup completed. {$deletedCount} records permanently deleted.",
        'deleted_count' => $deletedCount,
        'retention_days' => $daysToKeep,
        'timestamp' => now()->toISOString()
    ]);
})->name('scheduler.cleanup-trashed-transfers');

Route::post('/scheduler/cleanup-expired-reversals', function() {
    $token = request()->input('token');
    $expectedToken = config('ownership_transfer.scheduler_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid scheduler token');
    }
    
    $controller = app()->make(PropertyOwnershipTransferController::class);
    $result = $controller->checkExpiredReversalRequests();
    
    return response()->json($result);
})->name('scheduler.cleanup-expired-reversals');

Route::post('/scheduler/cleanup-old-archives', function() {
    $token = request()->input('token');
    $expectedToken = config('ownership_transfer.scheduler_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid scheduler token');
    }
    
    $archiveService = app(\App\Services\TransferArchiveService::class);
    $retentionYears = request()->input('years', config('ownership_transfer.archive_retention_years', 7));
    $result = $archiveService->deleteOldArchives($retentionYears);
    
    \Log::info('Scheduled old archive cleanup completed', [
        'deleted_count' => $result['deleted_count'],
        'retention_years' => $retentionYears,
        'executed_at' => now()->toISOString()
    ]);
    
    return response()->json([
        'success' => true,
        'message' => "Scheduled archive cleanup completed. {$result['deleted_count']} archives permanently deleted.",
        'deleted_count' => $result['deleted_count'],
        'retention_years' => $retentionYears,
        'timestamp' => now()->toISOString()
    ]);
})->name('scheduler.cleanup-old-archives');

Route::post('/scheduler/archive-transfers', function() {
    $token = request()->input('token');
    $expectedToken = config('ownership_transfer.scheduler_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid scheduler token');
    }
    
    $year = request()->input('year', now()->subYear()->year);
    $archiveService = app(\App\Services\TransferArchiveService::class);
    $result = $archiveService->archiveYear($year, [
        'soft_delete_after_archive' => true,
        'archive_completed' => true,
        'archive_rejected' => true,
        'archive_cancelled' => true,
        'archive_reason' => 'scheduled_yearly_cleanup',
    ]);
    
    \Log::info('Scheduled transfer archiving completed', [
        'year' => $year,
        'archived_count' => $result['archived_count'],
        'failed_count' => $result['failed_count'],
        'executed_at' => now()->toISOString()
    ]);
    
    return response()->json($result);
})->name('scheduler.archive-transfers');

/*
|--------------------------------------------------------------------------
| DeveloperSuperadmin creation & Invitation route 
|--------------------------------------------------------------------------
*/

// ========== PUBLIC INVITATION ROUTES ==========
// These routes are public and don't require authentication
Route::middleware(['web'])->group(function () {
    // ✅ FIXED: Invitation acceptance - accessible without authentication
    Route::get('/invitation/accept/{token}', [App\Http\Controllers\InvitationController::class, 'accept'])
        ->name('invitation.accept');
    
    // ✅ FIXED: Password setup after invitation acceptance (NO auth middleware)
    Route::get('/invitation/setup-password', [App\Http\Controllers\InvitationController::class, 'showSetupPassword'])
        ->name('invitation.setup-password.form');
    
    // ✅ FIXED: Process password setup (NO auth middleware)
    Route::post('/invitation/setup-password', [App\Http\Controllers\InvitationController::class, 'setupPassword'])
        ->name('invitation.setup-password');
    
    // ✅ ADDED: Cancel invitation process
    Route::get('/invitation/cancel', [App\Http\Controllers\InvitationController::class, 'cancelInvitationProcess'])
        ->name('invitation.cancel');
});

// ========== SUPER ADMIN MANAGEMENT ROUTES ==========

// 1. Routes for Developers (type 5) - Full CRUD access
Route::middleware(['auth', 'multi.auth.user:5', 'web'])->prefix('developer')->name('developer.')->group(function () {
    
    // Super Admin Management (Developer only)
    Route::prefix('super-admins')->name('super-admins.')->group(function () {
        
        // ========== TRASH MANAGEMENT ROUTES (MUST come before /{id} routes) ==========
        
        // View trashed (soft-deleted) super admins
        Route::get('/trash', [DeveloperSuperAdminController::class, 'trash'])
            ->name('trash');
        
        // Bulk actions (no ID parameter)
        Route::post('/bulk-restore', [DeveloperSuperAdminController::class, 'bulkRestore'])
            ->name('bulk-restore');
        
        Route::delete('/bulk-permanent-delete', [DeveloperSuperAdminController::class, 'bulkPermanentDelete'])
            ->name('bulk-permanent-delete');
        
        Route::delete('/empty-trash', [DeveloperSuperAdminController::class, 'emptyTrash'])
            ->name('empty-trash');
        
        Route::post('/bulk-action', [DeveloperSuperAdminController::class, 'bulkAction'])
            ->name('bulk-action');
        
        Route::get('/dashboard-statistics', [DeveloperSuperAdminController::class, 'dashboardStatistics'])
            ->name('dashboard-statistics');
        
        Route::get('/export/csv', [DeveloperSuperAdminController::class, 'exportSuperAdmins'])
            ->name('export');
        
        Route::get('/invitation-details/{invitationId}', [DeveloperSuperAdminController::class, 'getInvitationDetails'])
            ->name('invitation-details');
        
        // ========== CRUD ROUTES ==========
        
        // Index - List all super admins created by developer
        Route::get('/', [DeveloperSuperAdminController::class, 'index'])
            ->name('index');
        
        // Create - Show form
        Route::get('/create', [DeveloperSuperAdminController::class, 'create'])
            ->name('create');
        
        // Store - Create new super admin
        Route::post('/', [DeveloperSuperAdminController::class, 'store'])
            ->name('store');
        
        // ========== ROUTES WITH ID PARAMETERS ==========
        
        // Show - View super admin details
        Route::get('/{id}', [DeveloperSuperAdminController::class, 'show'])
            ->name('show');
        
        // Edit - Edit super admin form
        Route::get('/{id}/edit', [DeveloperSuperAdminController::class, 'edit'])
            ->name('edit');
        
        // Update - Update super admin
        Route::put('/{id}', [DeveloperSuperAdminController::class, 'update'])
            ->name('update');
        
        // Delete - Soft delete super admin (moves to trash)
        Route::delete('/{id}', [DeveloperSuperAdminController::class, 'destroy'])
            ->name('destroy');
        
        // Restore a soft-deleted super admin
        Route::post('/{id}/restore', [DeveloperSuperAdminController::class, 'restore'])
            ->name('restore');
        
        // Permanently delete a soft-deleted super admin
        Route::delete('/{id}/force-delete', [DeveloperSuperAdminController::class, 'forceDelete'])
            ->name('force-delete');
        
        // ========== STATUS MANAGEMENT ROUTES ==========
        
        // Activate super admin
        Route::post('/{id}/activate', [DeveloperSuperAdminController::class, 'activate'])
            ->name('activate');
        
        // Suspend super admin
        Route::post('/{id}/suspend', [DeveloperSuperAdminController::class, 'suspend'])
            ->name('suspend');
        
        // Deactivate super admin
        Route::post('/{id}/deactivate', [DeveloperSuperAdminController::class, 'deactivate'])
            ->name('deactivate');
        
        // ========== VERIFICATION MANAGEMENT ROUTES ==========
        
        // Force verify phone
        Route::post('/{id}/force-verify-phone', [DeveloperSuperAdminController::class, 'forceVerifyPhone'])
            ->name('force-verify-phone');
        
        // Remove phone verification
        Route::post('/{id}/remove-phone-verification', [DeveloperSuperAdminController::class, 'removePhoneVerification'])
            ->name('remove-phone-verification');
        
        // Force verify email
        Route::post('/{id}/force-verify-email', [DeveloperSuperAdminController::class, 'forceVerifyEmail'])
            ->name('force-verify-email');
        
        // Remove email verification
        Route::post('/{id}/remove-email-verification', [DeveloperSuperAdminController::class, 'removeEmailVerification'])
            ->name('remove-email-verification');
        
        // ========== INVITATION MANAGEMENT ROUTES ==========
        
        // Send invitation (POST request)
        Route::post('/{id}/send-invitation', [DeveloperSuperAdminController::class, 'sendInvitation'])
            ->name('send-invitation');
        
        // Resend invitation (POST request)
        Route::post('/{id}/resend-invitation', [DeveloperSuperAdminController::class, 'resendInvitation'])
            ->name('resend-invitation');
        
        // Get invitation status (AJAX)
        Route::get('/{id}/invitation-status', [DeveloperSuperAdminController::class, 'getInvitationStatus'])
            ->name('invitation-status');
        
        // ========== INVITATION HISTORY ROUTES ==========
        
        // View invitation history
        Route::get('/{id}/invitation-history', [DeveloperSuperAdminController::class, 'invitationHistory'])
            ->name('invitation-history');
        
        // ========== ACTIVITY & AUDIT ROUTES ==========
        
        // View activities
        Route::get('/{id}/activities', [DeveloperSuperAdminController::class, 'activities'])
            ->name('activities');
        
        // Export activities to CSV
        Route::get('/{id}/activities/export', [DeveloperSuperAdminController::class, 'exportActivities'])
            ->name('activities.export');
        
        // ========== PASSWORD MANAGEMENT ==========
        
        // Change password form
        Route::get('/{id}/change-password', [DeveloperSuperAdminController::class, 'showChangePasswordForm'])
            ->name('change-password.form');
        
        // Change password
        Route::post('/{id}/change-password', [DeveloperSuperAdminController::class, 'changePassword'])
            ->name('change-password');
        
        // ========== API/UTILITY ROUTES ==========
        
        // Get available channels for user (AJAX)
        Route::get('/{id}/available-channels', [DeveloperSuperAdminController::class, 'getAvailableChannels'])
            ->name('available-channels');
        
        // Check user relations before deletion (AJAX)
        Route::get('/{id}/check-relations', [DeveloperSuperAdminController::class, 'checkRelations'])
            ->name('check-relations');
    });
});

// 2. Routes for Super Admins (type 0) - Read-only access
Route::middleware(['auth', 'multi.auth.user:0', 'web'])->prefix('super-admin')->name('super-admin.')->group(function () {
    
    // Super Admin Management (Read-only for Super Admins)
    Route::prefix('super-admins')->name('super-admins.')->group(function () {
        
        // Index - View all super admins in system (read-only)
        Route::get('/', [SuperAdminSuperAdminController::class, 'index'])
            ->name('index');
        
        // Show - View super admin details (read-only)
        Route::get('/{id}', [SuperAdminSuperAdminController::class, 'show'])
            ->name('show');
        
        // View activities (read-only)
        Route::get('/{id}/activities', [SuperAdminSuperAdminController::class, 'activities'])
            ->name('activities');
        
        // Export activities to CSV (read-only)
        Route::get('/{id}/export-activities', [SuperAdminSuperAdminController::class, 'exportActivities'])
            ->name('export-activities');
        
        // View invitation history (read-only)
        Route::get('/{id}/invitation-history', [SuperAdminSuperAdminController::class, 'invitationHistory'])
            ->name('invitation-history');
        
        // ========== API/UTILITY ROUTES (Read-only) ==========
        
        // Get invitation status (AJAX)
        Route::get('/{id}/invitation-status', [SuperAdminSuperAdminController::class, 'getInvitationStatus'])
            ->name('invitation-status');
        
        // Dashboard statistics (AJAX)
        Route::get('/dashboard-statistics', [SuperAdminSuperAdminController::class, 'dashboardStatistics'])
            ->name('dashboard-statistics');
        
        // Get invitation details API (JSON)
        Route::get('/invitation-details/{invitationId}', [SuperAdminSuperAdminController::class, 'getInvitationDetails'])
            ->name('invitation-details');
        
        // Export super admins to CSV (from index page)
        Route::get('/export/csv', [SuperAdminSuperAdminController::class, 'exportSuperAdmins'])
            ->name('export');
        
        // ========== ROLE MANAGEMENT ROUTES (Super Admin can manage their own roles) ==========
        
        // Update own roles (add/remove landlord, admin roles) - AJAX endpoint
        Route::put('/{id}/update-own-roles', [SuperAdminSuperAdminController::class, 'updateOwnRoles'])
            ->name('update-own-roles');
    });
});


/*
|--------------------------------------------------------------------------
| Developer System Settings Routes - FIXED VERSION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:5', 'web'])->prefix('developer')->name('developer.')->group(function () {
    
    // =============================================
    // DEVELOPER ROOT REDIRECT - FIXED: Redirect to actual dashboard
    // =============================================
    Route::get('/', function() {
        return redirect()->route('developer.dashboard');
    })->name('index');
    
    // =============================================
    // DEVELOPER DASHBOARD ROUTE - FIXED: Remove DashboardController reference
    // =============================================
    Route::get('/dashboard', function() {
        return view('developer.dashboard');
    })->name('dashboard');
    


// =============================================
// SETTINGS MANAGEMENT ROUTES (Developer only)
// =============================================
Route::prefix('settings')->name('settings.')->group(function () {
    Route::middleware(['multi.auth.user:5'])->group(function () {

        // Main settings page
        Route::get('/', [DeveloperSettingsController::class, 'index'])->name('index');

        // Update general / monitoring / analytics settings
        Route::put('/update', [DeveloperSettingsController::class, 'update'])->name('update');

        // ----- Billing -----
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::put('/update', [DeveloperSettingsController::class, 'updateBillingSettings'])
                ->name('update');

            Route::post('/change-primary', [DeveloperSettingsController::class, 'changePrimarySuperAdmin'])
                ->name('change-primary');

            Route::get('/invoices', [DeveloperSettingsController::class, 'billingInvoices'])
                ->name('invoices');
        });

        // Email configuration routes
        Route::prefix('email')->name('email.')->group(function () {
            Route::post('/test', [DeveloperSettingsController::class, 'testEmailConfiguration'])
                ->name('test');

            Route::post('/update', [DeveloperSettingsController::class, 'updateEmailConfiguration'])
                ->name('update');
        });

        // API & Security routes
        Route::post('/api/generate-keys', [DeveloperSettingsController::class, 'generateApiKey'])
            ->name('api.generate-keys');

        Route::get('/api/documentation', [DeveloperSettingsController::class, 'apiDocumentation'])
            ->name('api.documentation');

        Route::post('/security/update', [DeveloperSettingsController::class, 'updateSecuritySettings'])
            ->name('security.update');

        // Clear cache route
        Route::post('/clear-cache', [DeveloperSettingsController::class, 'clearCache'])
            ->name('clear-cache');
    });
});

// =============================================
// DEVELOPER SHORT-FORM ALIASES (Developer only)
// =============================================
// The Blade view references short-form names (no `settings.` segment):
//   developer.billing.invoices
//   developer.api.generate-keys
//   developer.api.documentation
//   developer.security.update
// These groups register those aliases so both naming conventions work.
Route::middleware(['multi.auth.user:5'])->group(function () {

    // ----- Billing aliases -----
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/invoices', [DeveloperSettingsController::class, 'billingInvoices'])
            ->name('invoices');
    });

    // ----- API aliases -----
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/generate-keys', [DeveloperSettingsController::class, 'generateApiKey'])
            ->name('generate-keys');

        Route::get('/documentation', [DeveloperSettingsController::class, 'apiDocumentation'])
            ->name('documentation');
    });

    // ----- Security aliases -----
    Route::prefix('security')->name('security.')->group(function () {
        Route::post('/update', [DeveloperSettingsController::class, 'updateSecuritySettings'])
            ->name('update');
    });
});

// =============================================
// SIMPLIFIED COMPATIBILITY ROUTES (Developer only)
// =============================================
Route::middleware(['multi.auth.user:5'])->group(function () {
    Route::post('/email/update', [DeveloperSettingsController::class, 'updateEmailConfiguration'])
        ->name('email.update');

    Route::post('/email/test', [DeveloperSettingsController::class, 'testEmailConfiguration'])
        ->name('email.test');
});

// =============================================
// DEVELOPER TOOLS ROUTES (Developer only)
// =============================================
Route::prefix('tools')->name('tools.')->group(function () {
    Route::middleware(['multi.auth.user:5'])->group(function () {

        Route::get('/dashboard', function () {
            return view('developer.tools.dashboard');
        })->name('dashboard');

        Route::post('/clear-cache', [DeveloperSettingsController::class, 'clearCache'])
            ->name('clear-cache');

        Route::post('/optimize', function () {
            Artisan::call('optimize:clear');
            return redirect()->route('developer.tools.dashboard')
                ->with('success', 'Application optimized successfully!');
        })->name('optimize');

        Route::post('/db-backup', function () {
            Artisan::call('backup:run');
            return redirect()->route('developer.tools.dashboard')
                ->with('success', 'Database backup created successfully!');
        })->name('db-backup');

        Route::post('/backup-database', function () {
            Artisan::call('backup:run');
            return redirect()->route('developer.tools.dashboard')
                ->with('success', 'Database backup created successfully!');
        })->name('backup-database');

        Route::get('/system-logs', function () {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $logs = file_get_contents($logFile);
                return view('developer.tools.logs', compact('logs'));
            }
            return redirect()->route('developer.tools.dashboard')
                ->with('error', 'Log file not found.');
        })->name('system-logs');

        Route::post('/clear-system-logs', function () {
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
            return redirect()->route('developer.tools.dashboard')
                ->with('success', 'System logs cleared successfully!');
        })->name('clear-system-logs');

        Route::post('/clear-all', [DeveloperSettingsController::class, 'clearCache'])
            ->name('clear-all');

        Route::post('/comprehensive-optimize', function () {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            return redirect()->route('developer.tools.dashboard')
                ->with('success', 'Comprehensive optimization completed successfully!');
        })->name('comprehensive-optimize');

        Route::get('/diagnostics', function () {
            $checks = [
                'php_version'      => PHP_VERSION,
                'laravel_version'  => app()->version(),
                'database'         => DB::connection()->getPdo() ? 'Connected' : 'Disconnected',
                'storage_writable' => is_writable(storage_path()) ? 'Writable' : 'Not Writable',
                'cache_writable'   => is_writable(storage_path('framework/cache')) ? 'Writable' : 'Not Writable',
            ];
            return view('developer.tools.diagnostics', compact('checks'));
        })->name('diagnostics');

        Route::get('/server-info', function () {
            $serverInfo = [
                'php_version'     => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'server_name'     => $_SERVER['SERVER_NAME'] ?? 'N/A',
                'server_addr'     => $_SERVER['SERVER_ADDR'] ?? 'N/A',
                'server_port'     => $_SERVER['SERVER_PORT'] ?? 'N/A',
                'document_root'   => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
                'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
                'remote_addr'     => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            ];
            return view('developer.tools.server-info', compact('serverInfo'));
        })->name('server-info');

        Route::get('/phpinfo', function () {
            phpinfo();
            exit;
        })->name('phpinfo');

        Route::post('/config-cache',   [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'configCache'])   ->name('config-cache');

        Route::post('/config-clear',   [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'configClear'])   ->name('config-clear');

        Route::post('/route-cache',    [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'routeCache'])    ->name('route-cache');

        Route::post('/route-clear',    [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'routeClear'])    ->name('route-clear');

        Route::post('/view-cache',     [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'viewCache'])     ->name('view-cache');

        Route::post('/view-clear',     [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'viewClear'])     ->name('view-clear');

        Route::post('/queue-restart',  [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'queueRestart'])  ->name('queue-restart');

        Route::post('/session-clean',  [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'sessionClean'])  ->name('session-clean');

        Route::post('/schedule-run',   [\App\Http\Controllers\Developer\DeveloperSettingsController::class, 'scheduleRun'])   ->name('schedule-run');
    });
});
    
    

// =============================================
// DEVELOPER BILLING ROUTES (Developer only - Type 5)
// =============================================
Route::prefix('billing')->name('billing.')->middleware(['multi.auth.user:5'])->group(function () {

    // =============================================
    // DASHBOARD
    // =============================================
    Route::get('/dashboard', [DeveloperBillingController::class, 'dashboard'])
        ->name('dashboard');

    Route::get('/developer-dashboard', [DeveloperBillingController::class, 'developerDashboard'])
        ->name('developer-dashboard');

    // =============================================
    // ✅ INVOICE LIST ROUTES
    // =============================================
    Route::get('/invoices', [DeveloperBillingController::class, 'billingInvoices'])
        ->name('invoices');

    Route::get('/view-invoices', [DeveloperBillingController::class, 'viewInvoices'])
        ->name('view-invoices');

    // =============================================
    // AGREEMENTS — LIST & DETAIL
    // =============================================
    Route::get('/agreements', [DeveloperBillingController::class, 'agreementsList'])
        ->name('agreements-list');

    Route::get('/view-agreements', [DeveloperBillingController::class, 'viewAgreements'])
        ->name('view-agreements');

    // Trashed agreements must come BEFORE the {agreementId} wildcard
    Route::get('/agreements/trashed', [DeveloperBillingController::class, 'trashedAgreements'])
        ->name('agreements-trashed');

    Route::get('/agreement/{agreementId}', [DeveloperBillingController::class, 'viewAgreement'])
        ->whereNumber('agreementId')
        ->name('view-agreement');

    // =============================================
    // TRASH ROUTES (Soft Delete Management)
    // =============================================
    Route::delete('/agreements/{agreementId}/delete', [DeveloperBillingController::class, 'deleteAgreement'])
        ->whereNumber('agreementId')
        ->name('delete-agreement');

    Route::delete('/agreements/{agreementId}/force-delete', [DeveloperBillingController::class, 'forceDeleteAgreement'])
        ->whereNumber('agreementId')
        ->name('force-delete-agreement');

    Route::post('/agreements/{agreementId}/restore', [DeveloperBillingController::class, 'restoreAgreement'])
        ->whereNumber('agreementId')
        ->name('restore-agreement');

    // =============================================
    // SIGNATURE ROUTES
    // =============================================
    Route::get('/agreement/{agreementId}/sign', [DeveloperBillingController::class, 'viewAgreementForSigning'])
        ->whereNumber('agreementId')
        ->name('view-agreement-for-signing');

    Route::get('/agreement/{agreementId}/view-signing', [DeveloperBillingController::class, 'viewAgreementForSigning'])
        ->whereNumber('agreementId')
        ->name('view-signing');

    Route::post('/agreement/{agreementId}/sign', [DeveloperBillingController::class, 'signAgreement'])
        ->whereNumber('agreementId')
        ->name('sign-agreement');

    Route::post('/agreement/{agreementId}/submit-signature', [DeveloperBillingController::class, 'submitSignature'])
        ->whereNumber('agreementId')
        ->name('submit-signature');

    Route::post('/agreement/{agreementId}/send-for-signing', [DeveloperBillingController::class, 'sendAgreementForSigning'])
        ->whereNumber('agreementId')
        ->name('send-for-signing');

    Route::post('/agreement/{agreementId}/send-signing', [DeveloperBillingController::class, 'sendAgreementForSigning'])
        ->whereNumber('agreementId')
        ->name('send-agreement-signing');

    Route::post('/agreement/{agreementId}/send-invitation', [DeveloperBillingController::class, 'sendInvitation'])
        ->whereNumber('agreementId')
        ->name('send-invitation');

    Route::post('/agreement/{agreementId}/invite', [DeveloperBillingController::class, 'sendInvitation'])
        ->whereNumber('agreementId')
        ->name('invite');

    Route::get('/agreement/{agreementId}/download-signed', [DeveloperBillingController::class, 'downloadSignedAgreement'])
        ->whereNumber('agreementId')
        ->name('download-signed-agreement');

    Route::post('/agreement/{agreementId}/revoke-signature', [DeveloperBillingController::class, 'revokeSignature'])
        ->whereNumber('agreementId')
        ->name('revoke-signature');

    Route::get('/agreement/{agreementId}/signature-audit', [DeveloperBillingController::class, 'viewSignatureAudit'])
        ->whereNumber('agreementId')
        ->name('signature-audit');

    // =============================================
    // AGREEMENT MANAGEMENT ROUTES
    // =============================================
    Route::post('/create-agreement', [DeveloperBillingController::class, 'createAgreement'])
        ->name('create-agreement');

    Route::put('/agreements/{agreementId}/update', [DeveloperBillingController::class, 'updateAgreement'])
        ->whereNumber('agreementId')
        ->name('update-agreement');

    Route::post('/agreements/{agreementId}/terminate', [DeveloperBillingController::class, 'terminateAgreement'])
        ->whereNumber('agreementId')
        ->name('terminate-agreement');

    Route::get('/agreement/{agreementId}/generate-pdf', [DeveloperBillingController::class, 'generateAgreementPdf'])
        ->whereNumber('agreementId')
        ->name('generate-agreement-pdf');

    Route::post('/agreement/{agreementId}/generate-pdf', [DeveloperBillingController::class, 'generateAgreementPdf'])
        ->whereNumber('agreementId')
        ->name('generate-agreement-pdf.post');

    Route::post('/agreement/{agreementId}/agree', [DeveloperBillingController::class, 'agreeToBillingTerms'])
        ->whereNumber('agreementId')
        ->name('agree-to-terms');

    Route::post('/agreement/{agreementId}/reject', [DeveloperBillingController::class, 'rejectBillingTerms'])
        ->whereNumber('agreementId')
        ->name('reject-terms');

    // =============================================
    // PRIMARY SUPER ADMIN ROUTES
    // =============================================
    Route::post('/change-primary-super-admin', [DeveloperBillingController::class, 'changePrimarySuperAdmin'])
        ->name('change-primary-super-admin');

    Route::get('/primary-super-admin-info', [DeveloperBillingController::class, 'getPrimarySuperAdminInfo'])
        ->name('primary-super-admin-info');

    Route::post('/agreements/{agreementId}/set-primary', [DeveloperBillingController::class, 'setAgreementAsPrimary'])
        ->whereNumber('agreementId')
        ->name('set-agreement-primary');

    // =============================================
    // PAYMENT ROUTES
    // =============================================
    Route::post('/confirm-payment', [DeveloperBillingController::class, 'confirmPayment'])
        ->name('confirm-payment');

    Route::post('/agreement/{agreementId}/record-payment', [DeveloperBillingController::class, 'recordPayment'])
        ->whereNumber('agreementId')
        ->name('record-payment');

    Route::get('/agreement/{agreementId}/record-payment-form', [DeveloperBillingController::class, 'showRecordPaymentForm'])
        ->whereNumber('agreementId')
        ->name('record-payment-form');

    Route::get('/payment/{paymentId}', [DeveloperBillingController::class, 'viewPaymentDetails'])
        ->whereNumber('paymentId')
        ->name('payment-details');

    // =============================================
    // ✅ SEND REMINDER ROUTES (FIXED)
    // =============================================
    // The blade POSTs to /developer/billing/agreements/{id}/send-reminder
    // (plural "agreements"). Previously only a singular /agreement/...
    // POST route existed, so the POST never resolved.
    //
    // Primary route used by the agreements-list blade's bell icon:
    Route::post('/agreements/{agreementId}/send-reminder', [DeveloperBillingController::class, 'sendReminder'])
        ->whereNumber('agreementId')
        ->name('agreements.send-reminder');

    // Backwards-compatible singular alias — some older views may still use it.
    Route::post('/agreement/{agreementId}/send-reminder', [DeveloperBillingController::class, 'sendReminder'])
        ->whereNumber('agreementId')
        ->name('send-reminder');

    // =============================================
    // SEND INVOICE ROUTES
    // =============================================
    Route::post('/agreement/{agreementId}/send-invoice', [DeveloperBillingController::class, 'sendInvoice'])
        ->whereNumber('agreementId')
        ->name('send-invoice');

    // =============================================
    // INVOICE DOWNLOAD ROUTES
    // =============================================
    Route::get('/agreement/{agreementId}/download-invoice', [DeveloperBillingController::class, 'downloadAgreementInvoice'])
        ->whereNumber('agreementId')
        ->name('download-agreement-invoice');

    Route::get('/agreement/{agreementId}/invoice', [DeveloperBillingController::class, 'downloadAgreementInvoice'])
        ->whereNumber('agreementId')
        ->name('download-invoice');

    // =============================================
    // BILLING SETTINGS ROUTES
    // =============================================
    Route::post('/update-billing', [DeveloperBillingController::class, 'updateBilling'])
        ->name('update-billing');

    Route::post('/update', [DeveloperBillingController::class, 'updateBillingSettings'])
        ->name('update');

    Route::post('/update-payment-settings', [DeveloperBillingController::class, 'updatePaymentSettings'])
        ->name('update-payment-settings');

    // =============================================
    // HISTORY & REPORTING ROUTES
    // =============================================
    Route::get('/history', [DeveloperBillingController::class, 'billingHistory'])
        ->name('history');

    Route::get('/payment-history', [DeveloperBillingController::class, 'paymentHistory'])
        ->name('payment-history');

    Route::get('/super-admin-payments', [DeveloperBillingController::class, 'superAdminPayments'])
        ->name('super-admin-payments');

    Route::get('/superadmin-payments', [DeveloperBillingController::class, 'superAdminPayments'])
        ->name('superadmin-payments');

    Route::get('/view-super-admin-payments', [DeveloperBillingController::class, 'viewSuperAdminPayments'])
        ->name('view-super-admin-payments');

    Route::post('/create-super-admin-request', [DeveloperBillingController::class, 'createSuperAdminPaymentRequest'])
        ->name('create-super-admin-request');

    Route::get('/create-super-admin-request-form', [DeveloperBillingController::class, 'showCreateSuperAdminRequestForm'])
        ->name('create-super-admin-request-form');

    Route::post('/create-sa-request', [DeveloperBillingController::class, 'createSuperAdminRequest'])
        ->name('create-sa-request');

    Route::put('/mark-sa-payment-received/{requestId}', [DeveloperBillingController::class, 'markSuperAdminPaymentReceived'])
        ->whereNumber('requestId')
        ->name('mark-sa-payment-received');

    Route::put('/mark-super-admin-payment-received/{requestId}', [DeveloperBillingController::class, 'markSaPaymentReceived'])
        ->whereNumber('requestId')
        ->name('mark-super-admin-payment-received');

    Route::post('/send-sa-reminder/{requestId}', [DeveloperBillingController::class, 'sendSuperAdminPaymentReminder'])
        ->whereNumber('requestId')
        ->name('send-sa-reminder');

    Route::post('/send-super-admin-reminder/{requestId}', [DeveloperBillingController::class, 'sendSuperAdminReminder'])
        ->whereNumber('requestId')
        ->name('send-super-admin-reminder');

    Route::delete('/cancel-sa-request/{requestId}', [DeveloperBillingController::class, 'cancelSuperAdminPaymentRequest'])
        ->whereNumber('requestId')
        ->name('cancel-sa-request');

    Route::delete('/cancel-super-admin-request/{requestId}', [DeveloperBillingController::class, 'cancelSuperAdminRequest'])
        ->whereNumber('requestId')
        ->name('cancel-super-admin-request');

    Route::get('/reports', [DeveloperBillingController::class, 'billingReports'])
        ->name('reports');

    Route::get('/export', [DeveloperBillingController::class, 'exportBillingData'])
        ->name('export');

    Route::get('/export-pdf', [DeveloperBillingController::class, 'exportBillingDataPdf'])
    ->name('export-pdf');

    Route::get('/export-history', [DeveloperBillingController::class, 'exportHistory'])
        ->name('export-history');

    Route::get('/export-reports', [DeveloperBillingController::class, 'exportReports'])
        ->name('export-reports');

    Route::post('/export-reports', [DeveloperBillingController::class, 'exportReports'])
        ->name('export-reports.csv');

    Route::get('/statistics', [DeveloperBillingController::class, 'viewStatistics'])
        ->name('statistics');

    // =============================================
    // RECURRING BILLING & INVOICE ROUTES
    // =============================================
    Route::post('/process-recurring', [DeveloperBillingController::class, 'processRecurringBilling'])
        ->name('process-recurring');

    Route::get('/process-recurring', [DeveloperBillingController::class, 'processRecurringBilling'])
        ->name('process-recurring.get');

    Route::post('/generate-custom-invoice', [DeveloperBillingController::class, 'generateCustomInvoice'])
        ->name('generate-custom-invoice');

    Route::post('/generate-monthly-invoice', [DeveloperBillingController::class, 'generateMonthlyInvoice'])
        ->name('generate-monthly-invoice');

    Route::delete('/cancel-proposal/{proposalId}', [DeveloperBillingController::class, 'cancelBillingProposal'])
        ->whereNumber('proposalId')
        ->name('cancel-proposal');

    // =============================================
    // SIGNATURE DOWNLOAD AND VERIFICATION ROUTES
    // =============================================
    Route::get('/signature/{signatureId}/download', [DeveloperBillingController::class, 'downloadSignature'])
        ->whereNumber('signatureId')
        ->name('download-signature');

    Route::get('/signatures/{signatureId}/verify', [DeveloperBillingController::class, 'verifySignature'])
        ->whereNumber('signatureId')
        ->name('verify-signature');

    Route::get('/signature/{signatureId}/certificate', [DeveloperBillingController::class, 'downloadSignatureCertificate'])
        ->whereNumber('signatureId')
        ->name('download-signature-certificate');

    Route::get('/agreement/{agreementId}/generate-final-pdf', [DeveloperBillingController::class, 'generateFinalAgreementPdf'])
        ->whereNumber('agreementId')
        ->name('generate-final-pdf');

    Route::post('/agreement/{agreementId}/send-test-email', [DeveloperBillingController::class, 'sendTestAgreementEmail'])
        ->whereNumber('agreementId')
        ->name('send-test-email');

    // =============================================
    // AJAX ENDPOINTS
    // =============================================
    Route::get('/agreements/{agreementId}/details', [DeveloperBillingController::class, 'getAgreementDetails'])
        ->whereNumber('agreementId')
        ->name('get-agreement-details');

    Route::get('/payments/{paymentId}/details', [DeveloperBillingController::class, 'getPaymentDetails'])
        ->whereNumber('paymentId')
        ->name('get-payment-details');

    Route::get('/payments/{paymentId}/api', [DeveloperBillingController::class, 'getPaymentDetailsApi'])
        ->whereNumber('paymentId')
        ->name('payment-details-api');

    Route::get('/agreement/{agreementId}/signature-status', [DeveloperBillingController::class, 'checkSignatureStatus'])
        ->whereNumber('agreementId')
        ->name('check-signature-status');

    Route::get('/agreement/{agreementId}/signing-status', [DeveloperBillingController::class, 'getSigningStatus'])
        ->whereNumber('agreementId')
        ->name('signing-status');

    Route::get('/notifications/unread-count', [DeveloperBillingController::class, 'getUnreadNotificationsCount'])
        ->name('unread-notifications-count');

    Route::get('/notifications/recent', [DeveloperBillingController::class, 'getRecentNotifications'])
        ->name('recent-notifications');

    // =============================================
    // BACKWARD COMPATIBILITY ALIASES
    // =============================================
    Route::get('/agreement/{agreementId}/download', [DeveloperBillingController::class, 'downloadAgreement'])
        ->whereNumber('agreementId')
        ->name('download-agreement');
});
    
    // =============================================
    // MONITORING & ANALYTICS ROUTES (Developer only)
    // =============================================
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::middleware(['multi.auth.user:5'])->group(function () {
            // Monitoring dashboard
            Route::get('/dashboard', [DeveloperMonitoringController::class, 'monitoringDashboard'])
                ->name('dashboard');
            
            // Generate system report
            Route::post('/report/generate', [DeveloperMonitoringController::class, 'generateReport'])
                ->name('report.generate');
            
            // Clear error logs
            Route::post('/clear-logs', [DeveloperMonitoringController::class, 'clearLogs'])
                ->name('clear-logs');
            
            // Error log view
            Route::get('/error-log', [DeveloperMonitoringController::class, 'errorLog'])
                ->name('error-log');
            
            // Get system health check
            Route::get('/health-check', [DeveloperMonitoringController::class, 'healthCheck'])
                ->name('health-check');
        });
    });
    
    // =============================================
    // BACKUP & RESTORE ROUTES (Developer only)
    // =============================================
    Route::prefix('backup')->name('backup.')->group(function () {
        Route::middleware(['multi.auth.user:5'])->group(function () {
            // Create manual backup
            Route::post('/create', [DeveloperBackupController::class, 'createBackup'])
                ->name('create');
            
            // List available backups (AJAX)
            Route::get('/list', [DeveloperBackupController::class, 'listBackups'])
                ->name('list');
            
            // Restore from backup
            Route::post('/restore', [DeveloperBackupController::class, 'restoreBackup'])
                ->name('restore');
            
            // Download backup file
            Route::get('/download', [DeveloperBackupController::class, 'downloadBackup'])
                ->name('download');
            
            // Delete backup file
            Route::delete('/delete', [DeveloperBackupController::class, 'deleteBackup'])
                ->name('delete');
            
            // Verify backup integrity
            Route::get('/verify', [DeveloperBackupController::class, 'verifyBackup'])
                ->name('verify');
            
            // Backup history page
            Route::get('/history', [DeveloperBackupController::class, 'backupHistory'])
                ->name('history');
        });
    });
    
    // =============================================
    // API DOCUMENTATION ROUTES (Developer only)
    // =============================================
    Route::prefix('api')->name('api.')->group(function () {
        Route::middleware(['multi.auth.user:5'])->group(function () {
            // API documentation page
            Route::get('/documentation', [DeveloperSettingsController::class, 'apiDocumentation'])
                ->name('documentation');
            
            // API test endpoint
            Route::get('/test', function() {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Developer API is working',
                    'timestamp' => now()->toISOString()
                ]);
            })->name('test');
        });
    });
    
    // =============================================
    // ADDITIONAL MISSING ROUTES - REMOVED REDIRECTS TO SETTINGS
    // =============================================
    
    // Developer monitoring dashboard (referenced in blade)
    Route::get('/monitoring/dashboard', [DeveloperMonitoringController::class, 'monitoringDashboard'])
        ->name('monitoring.dashboard');
    
    // Developer tools dashboard (referenced in blade)
    Route::get('/tools/dashboard', function() {
        return view('developer.tools.dashboard');
    })->name('tools.dashboard');
});

// =============================================
// SUPER ADMIN BILLING ROUTES (for Super Admins only - Type 0)
// =============================================
Route::middleware(['auth', 'web'])->prefix('superadmin')->name('superadmin.')->group(function () {
    // =============================================
    // SUPER ADMIN BILLING ROUTES WITH PROPER MIDDLEWARE
    // =============================================
    Route::prefix('billing')->name('billing.')->middleware(['multi.auth.user:0'])->group(function () {
        
        // =============================================
        // DASHBOARD & MAIN VIEWS
        // =============================================
        
        // Super admin billing dashboard
        Route::get('/dashboard', [SuperAdminBillingController::class, 'dashboard'])
            ->name('dashboard');
        
        // Alternative route for dashboard (for compatibility)
        Route::get('/', [SuperAdminBillingController::class, 'dashboard'])
            ->name('index');
        
        Route::get('/home', [SuperAdminBillingController::class, 'dashboard'])
            ->name('home');
        
        // =============================================
        // AGREEMENT ROUTES
        // =============================================
        
        // View super admin's agreements
        Route::get('/agreements', [SuperAdminBillingController::class, 'agreementsList'])
            ->name('agreements-list');
        
        // Alternative route for agreement list (for compatibility)
        Route::get('/view-agreements', [SuperAdminBillingController::class, 'agreementsList'])
            ->name('view-agreements');
        
        // View agreement details
        Route::get('/agreement/{agreementId}', [SuperAdminBillingController::class, 'viewAgreement'])
            ->whereNumber('agreementId')
            ->name('view-agreement');
        
        // Alternative route for agreement details (for compatibility)
        Route::get('/view-agreement/{agreementId}', [SuperAdminBillingController::class, 'viewAgreement'])
            ->whereNumber('agreementId')
            ->name('view-agreement-alt');
        
        // =============================================
        // SIGNATURE ROUTES
        // =============================================
        
        // View agreement for signing
        Route::middleware(['throttle:10,1'])->get('/agreement/{agreementId}/sign', [SuperAdminBillingController::class, 'viewAgreementForSigning'])
            ->whereNumber('agreementId')
            ->name('view-agreement-signing');
        
        // Alternative route for signing page (for compatibility)
        Route::get('/view-for-signing/{agreementId}', [SuperAdminBillingController::class, 'viewAgreementForSigning'])
            ->whereNumber('agreementId')
            ->name('view-for-signing');
        
        // Submit electronic signature - WITH RATE LIMITING
        Route::middleware(['throttle:5,1'])->post('/agreement/{agreementId}/sign', [SuperAdminBillingController::class, 'submitSignature'])
            ->whereNumber('agreementId')
            ->name('submit-signature');
        
        // Download signed agreement PDF
        Route::get('/agreement/{agreementId}/download-signed', [SuperAdminBillingController::class, 'downloadSignedAgreement'])
            ->whereNumber('agreementId')
            ->name('download-signed-agreement');
        
        // =============================================
        // INVOICE ROUTES
        // =============================================
        
        // View invoice details
        Route::get('/invoice/{invoiceId}', [SuperAdminBillingController::class, 'viewInvoice'])
            ->whereNumber('invoiceId')
            ->name('invoice-view');
        
        // Alternative route for invoice details (for compatibility)
        Route::get('/invoice/{invoiceId}/details', [SuperAdminBillingController::class, 'viewInvoice'])
            ->whereNumber('invoiceId')
            ->name('invoice-details');
        
        // Download invoice
        Route::get('/invoice/{invoiceId}/download', [SuperAdminBillingController::class, 'downloadInvoice'])
            ->whereNumber('invoiceId')
            ->name('invoice-download');
        
        // View all invoices
        Route::get('/invoices', [SuperAdminBillingController::class, 'invoiceList'])
            ->name('invoice-list');
        
        // =============================================
        // ✅ ADDED: PAYMENT STATUS ROUTE (for AJAX polling)
        // =============================================
        
        // Get payment status for a specific invoice (AJAX endpoint)
        Route::get('/invoice/{invoiceId}/payment-status', [SuperAdminBillingController::class, 'getPaymentStatus'])
            ->whereNumber('invoiceId')
            ->name('get-payment-status');
        
        // Alternative route name for payment status (for compatibility)
        Route::get('/payment-status/{invoiceId}', [SuperAdminBillingController::class, 'getPaymentStatus'])
            ->whereNumber('invoiceId')
            ->name('payment-status');
        
        // =============================================
        // PAYMENT ROUTES
        // =============================================
        
        // Agree to billing terms
        Route::post('/agree/{agreementId}', [SuperAdminBillingController::class, 'agreeToBillingTerms'])
            ->whereNumber('agreementId')
            ->name('agree');
        
        // Reject billing terms
        Route::post('/reject/{agreementId}', [SuperAdminBillingController::class, 'rejectBillingTerms'])
            ->whereNumber('agreementId')
            ->name('reject');
        
        // Record own payment - WITH RATE LIMITING
        Route::middleware(['throttle:10,1'])->post('/record-own-payment/{agreementId}', [SuperAdminBillingController::class, 'recordOwnPayment'])
            ->whereNumber('agreementId')
            ->name('record-own-payment');
        
        // Record payment (referenced in JavaScript)
        Route::post('/agreements/{agreementId}/record-payment', [SuperAdminBillingController::class, 'recordOwnPayment'])
            ->whereNumber('agreementId')
            ->name('record-payment');
        
        // Show record payment form
        Route::get('/agreements/{agreementId}/record-payment-form', [SuperAdminBillingController::class, 'showRecordPaymentForm'])
            ->whereNumber('agreementId')
            ->name('record-payment-form');
        
        // =============================================
        // ✅ PAYMENT CALLBACK & WEBHOOK ROUTES - ADD THIS
        // =============================================
        
        // Payment callback (for online payment redirects)
        // This is where users are redirected after paying on the provider's site
        Route::get('/payment-callback', [SuperAdminBillingController::class, 'paymentCallback'])
            ->name('payment-callback');
        
        // Webhook (for payment provider notifications)
        // This is called by the payment provider to confirm payment status
        Route::post('/webhook', [SuperAdminBillingController::class, 'webhook'])
            ->name('webhook');
        
        // View payment details
        Route::get('/payment/{paymentId}', [SuperAdminBillingController::class, 'viewPaymentDetails'])
            ->whereNumber('paymentId')
            ->name('payment-details');
        
        // View payment history
        Route::get('/payment-history', [SuperAdminBillingController::class, 'paymentHistory'])
            ->name('payment-history');
        
        // Alias for payment history (if referenced elsewhere)
        Route::get('/history', [SuperAdminBillingController::class, 'paymentHistory'])
            ->name('history');
        
        // =============================================
        // SHARED PAYMENT STATUS ROUTE
        // =============================================
        
        // View shared payment status (for primary super admin)
        Route::get('/shared-payment-status', [SuperAdminBillingController::class, 'sharedPaymentStatus'])
            ->name('shared-payment-status');
        
        // =============================================
        // SIGNATURE ROUTES
        // =============================================
        
        // Download signature
        Route::get('/signature/{signatureId}/download', [SuperAdminBillingController::class, 'downloadSignature'])
            ->whereNumber('signatureId')
            ->name('download-signature');
        
        // Verify signature (AJAX endpoint)
        Route::middleware(['throttle:30,1'])->get('/signatures/{signatureId}/verify', [SuperAdminBillingController::class, 'verifySignature'])
            ->whereNumber('signatureId')
            ->name('verify-signature');
        
        // Download signature certificate
        Route::get('/signature/{signatureId}/certificate', [SuperAdminBillingController::class, 'downloadSignatureCertificate'])
            ->whereNumber('signatureId')
            ->name('download-signature-certificate');
        
        // =============================================
        // REPORTING & EXPORT ROUTES
        // =============================================
        
        // View super admin reports
        Route::get('/reports', [SuperAdminBillingController::class, 'billingReports'])
            ->name('reports');
        
        // View agreement statistics
        Route::get('/statistics', [SuperAdminBillingController::class, 'viewStatistics'])
            ->name('statistics');
        
        // Export super admin billing data
        Route::get('/export', [SuperAdminBillingController::class, 'exportBillingData'])
            ->name('export');
        
        // Export with POST method for form submissions
        Route::post('/export', [SuperAdminBillingController::class, 'exportBillingData'])
            ->name('export.post');
        
        // GET route for export with parameters
        Route::get('/export-data', [SuperAdminBillingController::class, 'exportBillingData'])
            ->name('export-data');
        
        // Export reports (alias)
        Route::get('/export-reports', [SuperAdminBillingController::class, 'exportBillingData'])
            ->name('export-reports');
        
        // =============================================
        // ADDITIONAL BILLING ROUTES
        // =============================================
        
        // Download agreement invoice
        Route::get('/agreement/{agreementId}/download-invoice', [SuperAdminBillingController::class, 'downloadAgreementInvoice'])
            ->whereNumber('agreementId')
            ->name('download-invoice');
        
        // Generate final agreement PDF
        Route::get('/agreement/{agreementId}/generate-final-pdf', [SuperAdminBillingController::class, 'generateFinalAgreementPdf'])
            ->whereNumber('agreementId')
            ->name('generate-final-pdf');
        
        // Send test email
        Route::post('/agreement/{agreementId}/send-test-email', [SuperAdminBillingController::class, 'sendTestAgreementEmail'])
            ->whereNumber('agreementId')
            ->name('send-test-email');
        
        // =============================================
        // API HELPER ROUTES
        // =============================================
        
        // Get payment methods configuration (API endpoint)
        Route::get('/api/payment-methods', function() {
            try {
                $controller = new SuperAdminBillingController();
                $methods = $controller->getPaymentMethodsConfiguration();
                
                return response()->json([
                    'success' => true,
                    'payment_methods' => $methods,
                    'timestamp' => now()->toISOString()
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        })->name('api.payment-methods');
        
        // Get unread notifications count (AJAX)
        Route::get('/notifications/unread-count', [SuperAdminBillingController::class, 'getUnreadNotificationsCount'])
            ->name('unread-notifications-count');
        
        // Get recent notifications (AJAX)
        Route::get('/notifications/recent', [SuperAdminBillingController::class, 'getRecentNotifications'])
            ->name('recent-notifications');
        
        // Get agreement details for modal (AJAX)
        Route::get('/agreements/{agreementId}/details', [SuperAdminBillingController::class, 'getAgreementDetails'])
            ->whereNumber('agreementId')
            ->name('get-agreement-details');
        
        // Get payment details for modal (AJAX)
        Route::get('/payments/{paymentId}/details', [SuperAdminBillingController::class, 'getPaymentDetails'])
            ->whereNumber('paymentId')
            ->name('get-payment-details');
    
        
        // =============================================
        // API-LIKE ROUTES FOR AJAX CALLS
        // =============================================
        
        // Get agreement statistics via AJAX
        Route::middleware(['throttle:60,1'])->get('/api/stats', function() {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                $stats = [
                    'total_agreements' => \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)->count(),
                    'active_agreements' => \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)
                        ->where('status', 'active')->count(),
                    'pending_agreements' => \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)
                        ->where('status', 'pending')->count(),
                    'total_amount_agreed' => \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)
                        ->where('status', 'active')->sum('amount'),
                    'total_amount_paid' => \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)
                        ->where('status', 'active')->sum('amount_received'),
                ];
                
                return response()->json([
                    'success' => true,
                    'stats' => $stats,
                    'timestamp' => now()->toISOString()
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        })->name('api.stats');
        
        // Get monthly breakdown via AJAX
        Route::middleware(['throttle:30,1'])->get('/api/monthly-breakdown', function(Request $request) {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                $startDate = $request->get('start_date', now()->subMonths(6)->toDateString());
                $endDate = $request->get('end_date', now()->toDateString());
                
                // Call the helper method from controller
                $controller = new \App\Http\Controllers\Developer\SuperAdminBillingController();
                $breakdown = $controller->getMonthlyBreakdownForSuperAdmin($user->id, $startDate, $endDate);
                
                return response()->json([
                    'success' => true,
                    'breakdown' => $breakdown,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        })->name('api.monthly-breakdown');
        
        // Get payment method breakdown via AJAX
        Route::middleware(['throttle:30,1'])->get('/api/payment-method-breakdown', function(Request $request) {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                $startDate = $request->get('start_date', now()->subYear()->toDateString());
                $endDate = $request->get('end_date', now()->toDateString());
                
                // Call the helper method from controller
                $controller = new \App\Http\Controllers\Developer\SuperAdminBillingController();
                $breakdown = $controller->getPaymentMethodBreakdownForSuperAdmin($user->id, $startDate, $endDate);
                
                return response()->json([
                    'success' => true,
                    'breakdown' => $breakdown,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        })->name('api.payment-method-breakdown');
        
        // =============================================
        // FILE DOWNLOAD ROUTES
        // =============================================
        
        // Download any file from storage (protected)
        Route::get('/download-file/{filePath}', function($filePath) {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    abort(403, 'Unauthorized');
                }
                
                // Decode the file path
                $filePath = base64_decode($filePath);
                
                // Security check: ensure file is within storage/app directory
                if (!Storage::exists($filePath)) {
                    abort(404, 'File not found');
                }
                
                // Additional security: check if this is an agreement file for this super admin
                if (str_contains($filePath, 'agreements/')) {
                    // Extract agreement ID from path
                    preg_match('/agreements\/.*?\/(\d+)\//', $filePath, $matches);
                    if (isset($matches[1])) {
                        $agreementId = $matches[1];
                        $agreement = \App\Models\AdminBillingRecord::find($agreementId);
                        
                        if (!$agreement || $agreement->super_admin_id !== $user->id) {
                            abort(403, 'Unauthorized to access this file');
                        }
                    }
                }
                
                return response()->download(storage_path('app/' . $filePath));
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('File download error: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to download file: ' . $e->getMessage());
            }
        })->where('filePath', '.*')
          ->name('download-file');
        
        // =============================================
        // PDF GENERATION ROUTES
        // =============================================
        
        // Generate agreement PDF (super admin view)
        Route::get('/agreement/{agreementId}/generate-pdf', function($agreementId) {
            try {
                $agreement = \App\Models\AdminBillingRecord::findOrFail($agreementId);
                $user = auth()->user();
                
                if ($agreement->super_admin_id !== $user->id) {
                    abort(403, 'Unauthorized');
                }
                
                // Use controller method if it exists
                if (method_exists(SuperAdminBillingController::class, 'generateAgreementPdf')) {
                    return app(SuperAdminBillingController::class)->generateAgreementPdf($agreementId);
                }
                
                // Fallback PDF generation
                $controller = new \App\Http\Controllers\Developer\SuperAdminBillingController();
                $pdfData = $controller->prepareAgreementData($agreement);
                
                // Generate HTML
                $html = view('superadmin.billing.agreement-pdf', compact('pdfData', 'agreement'))->render();
                
                // Convert to PDF (you need dompdf or similar package)
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                
                return $pdf->download("Agreement-{$agreement->agreement_number}.pdf");
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('PDF generation error: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
            }
        })->whereNumber('agreementId')
          ->name('generate-pdf');
        
        // =============================================
        // BULK OPERATIONS
        // =============================================
        
        // Bulk export agreements
        Route::post('/bulk-export-agreements', function(Request $request) {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    abort(403, 'Unauthorized');
                }
                
                $agreementIds = $request->input('agreement_ids', []);
                
                if (empty($agreementIds)) {
                    return redirect()->back()->with('error', 'No agreements selected for export');
                }
                
                // Use the export method from controller
                return app(SuperAdminBillingController::class)->exportBillingData($request);
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Bulk export error: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to export agreements: ' . $e->getMessage());
            }
        })->name('bulk-export-agreements');
        
        // =============================================
        // SEARCH AND FILTER ROUTES
        // =============================================
        
        // Search agreements (AJAX)
        Route::middleware(['throttle:60,1'])->get('/search-agreements', function(Request $request) {
            try {
                $user = auth()->user();
                if ($user->type !== \App\Models\User::TYPE_SUPER_ADMIN) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                
                $searchTerm = $request->get('q', '');
                $limit = $request->get('limit', 10);
                
                $agreements = \App\Models\AdminBillingRecord::where('super_admin_id', $user->id)
                    ->where(function($query) use ($searchTerm) {
                        $query->where('agreement_number', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('amount', 'LIKE', "%{$searchTerm}%");
                    })
                    ->with('developerSetting')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function($agreement) {
                        return [
                            'id' => $agreement->id,
                            'agreement_number' => $agreement->agreement_number,
                            'description' => $agreement->description,
                            'amount' => number_format($agreement->amount, 2),
                            'status' => $agreement->status,
                            'developer_name' => $agreement->developerSetting->developer_name ?? 'Unknown',
                            'view_url' => route('superadmin.billing.view-agreement', $agreement->id)
                        ];
                    });
                
                return response()->json([
                    'success' => true,
                    'agreements' => $agreements,
                    'count' => $agreements->count()
                ]);
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        })->name('search-agreements');
    });
    
    // =============================================
    // ADDITIONAL SUPER ADMIN ROUTES REFERENCED IN BLADE
    // =============================================
    
    // Super admin main dashboard (redirect to billing dashboard)
    Route::get('/main-dashboard', function() {
        return redirect()->route('superadmin.billing.dashboard');
    })->name('main-dashboard');
});

// =============================================
// DEAL WITH THE MISSING ROUTE (developer.superadmin.billing.dashboard)
// =============================================
Route::middleware(['auth', 'web'])->get('/developer/superadmin/billing/dashboard', function() {
    if (auth()->check()) {
        $user = auth()->user();
        
        if ($user->type === 5) { // Developer
            return redirect()->route('developer.billing.dashboard');
        } elseif ($user->type === 0) { // Super Admin
            return redirect()->route('superadmin.billing.dashboard');
        }
    }
    
    // Default redirect if not logged in or unknown user type
    return redirect()->route('login');
})->name('developer.superadmin.billing.dashboard');

// =============================================
// API ROUTES (External API access - with developer check)
// =============================================
Route::prefix('api/developer')->middleware(['api', 'auth:api', 'multi.auth.user:5'])->name('api.developer.')->group(function () {
    // System status
    Route::get('/system/status', function() {
        return response()->json([
            'status' => 'online',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ]);
    })->name('system.status');
    
    // Billing overview
    Route::get('/billing/overview', function() {
        try {
            $billingService = app(\App\Services\DeveloperBillingService::class);
            $overview = $billingService->getBillingOverview();
            return response()->json([
                'success' => true,
                'data' => $overview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    })->name('billing.overview');
    
    // Developer settings API
    Route::get('/settings', function() {
        try {
            $settings = \App\Models\DeveloperSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Developer settings not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    })->name('settings.index');
    
    // Agreements API
    Route::get('/agreements', function() {
        try {
            $settings = \App\Models\DeveloperSetting::first();
            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Developer settings not found'
                ], 404);
            }
            
            $agreements = \App\Models\AdminBillingRecord::where('developer_setting_id', $settings->id)
                ->with('superAdmin')
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $agreements,
                'count' => $agreements->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    })->name('agreements.index');
    
    // Health check API
    Route::get('/health', function() {
        try {
            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
                    'cache' => 'ok',
                    'queue' => 'ok'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed: ' . $e->getMessage()
            ], 500);
        }
    })->name('health');
});

// =============================================
// PUBLIC ROUTES (No authentication required)
// =============================================

// Paystack webhook (no auth required for callback)
Route::middleware(['api'])->post('/paystack/webhook', function(Request $request) {
    // Verify Paystack signature
    $payload = $request->getContent();
    $signature = $request->header('x-paystack-signature');
    
    $expectedSignature = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));
    
    if ($signature !== $expectedSignature) {
        Log::warning('Invalid Paystack webhook signature', [
            'expected' => $expectedSignature,
            'received' => $signature
        ]);
        abort(401, 'Invalid signature');
    }
    
    $event = $request->input('event');
    $data = $request->input('data');
    
    Log::info('Paystack webhook received', [
        'event' => $event,
        'data' => $data
    ]);
    
    // Handle different Paystack events
    switch ($event) {
        case 'charge.success':
            // Handle successful payment
            $reference = $data['reference'];
            
            // Find payment attempt
            $paymentAttempt = DB::table('payment_attempts')
                ->where('payment_reference', $reference)
                ->first();
            
            if ($paymentAttempt) {
                // Update payment attempt
                DB::table('payment_attempts')
                    ->where('id', $paymentAttempt->id)
                    ->update([
                        'status' => 'verified',
                        'verified_at' => now(),
                        'updated_at' => now()
                    ]);
                
                // Update invoice
                $invoice = \App\Models\DeveloperBillingRecord::find($paymentAttempt->invoice_id);
                if ($invoice) {
                    $invoice->update([
                        'payment_status' => 'paid',
                        'amount_paid' => $invoice->amount,
                        'paid_date' => now(),
                        'payment_method' => 'paystack',
                        'payment_reference' => $reference,
                        'transaction_id' => $data['id'] ?? null
                    ]);
                    
                    Log::info('Paystack payment verified via webhook', [
                        'invoice_id' => $invoice->id,
                        'reference' => $reference,
                        'amount' => $invoice->amount
                    ]);
                }
            }
            break;
            
        default:
            Log::info('Unhandled Paystack webhook event', ['event' => $event]);
    }
    
    return response()->json(['status' => 'success']);
})->name('paystack.webhook');

// =============================================
// SCHEDULED TASK ROUTES (Internal use - no auth required)
// =============================================

// Route for Laravel scheduler to call (protected by cron token)
Route::get('/cron/process-recurring-billing', function() {
    // Verify cron token
    $token = request()->input('token');
    $expectedToken = config('app.cron_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid cron token');
    }
    
    try {
        // Use DeveloperBillingController instead
        $controller = app(DeveloperBillingController::class);
        $processed = $controller->processRecurringBilling();
        
        return response()->json([
            'success' => true,
            'message' => 'Recurring billing processed',
            'processed_count' => $processed
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to process recurring billing via cron: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
})->name('cron.process.billing');

// Route for Laravel scheduler to run system optimization
Route::get('/cron/system-optimize', function() {
    // Verify cron token
    $token = request()->input('token');
    $expectedToken = config('app.cron_token');
    
    if (!$expectedToken || $token !== $expectedToken) {
        abort(403, 'Invalid cron token');
    }
    
    try {
        // Run optimization commands
        \Artisan::call('cache:clear');
        \Artisan::call('view:clear');
        \Artisan::call('optimize:clear');
        
        return response()->json([
            'success' => true,
            'message' => 'System optimization completed',
            'timestamp' => now()->toISOString()
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to run system optimization via cron: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
})->name('cron.system.optimize');

// =============================================
// FALLBACK ROUTES (Error handling)
// =============================================

// Handle missing developer routes
Route::fallback(function() {
    if (request()->is('developer/*') && auth()->check()) {
        // If user is authenticated but not a developer
        if (auth()->user()->type !== 5) {
            abort(403, 'Access denied. Developer access only (User type 5 required).');
        }
        // If user is a developer but route doesn't exist
        return response()->view('errors.404-developer', [], 404);
    }
    
    if (request()->is('superadmin/*') && auth()->check()) {
        // If user is authenticated but not a super admin
        if (auth()->user()->type !== 0) {
            abort(403, 'Access denied. Super Admin access only (User type 0 required).');
        }
        // If user is a super admin but route doesn't exist
        return response()->view('errors.404-superadmin', [], 404);
    }
    
    // Default 404
    abort(404);
})->where('fallbackPlaceholder', '.*');

// Debug route - REMOVE AFTER TESTING
Route::post('/debug/signature-test/{agreementId}', function(Request $request, $agreementId) {
    Log::info('DEBUG POST received', [
        'agreement_id' => $agreementId,
        'all_data' => $request->all(),
    ]);
    
    return response()->json([
        'success' => true,
        'received' => $request->all(),
        'has_accept_terms' => $request->has('accept_terms'),
        'accept_terms_value' => $request->input('accept_terms'),
    ]);
})->name('debug.signature');


// =============================================
    //SECURITY REPORTS ROUTES
// =============================================
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('security-reports')->name('security-reports.')->group(function () {
        Route::get('/', [SecurityReportsController::class, 'index'])->name('index');
        Route::get('/create', [SecurityReportsController::class, 'create'])->name('create');
        Route::post('/', [SecurityReportsController::class, 'store'])->name('store');
        Route::get('/{securityReport}', [SecurityReportsController::class, 'show'])->name('show');
        Route::get('/{securityReport}/edit', [SecurityReportsController::class, 'edit'])->name('edit');
        Route::put('/{securityReport}', [SecurityReportsController::class, 'update'])->name('update');
        Route::delete('/{securityReport}', [SecurityReportsController::class, 'destroy'])->name('destroy');
        
        Route::post('/{securityReport}/assign', [SecurityReportsController::class, 'assign'])->name('assign');
        Route::post('/{securityReport}/verify', [SecurityReportsController::class, 'verify'])->name('verify');
        Route::post('/{securityReport}/resolve', [SecurityReportsController::class, 'resolve'])->name('resolve');
        Route::post('/{securityReport}/close', [SecurityReportsController::class, 'close'])->name('close');
        Route::post('/{securityReport}/cancel', [SecurityReportsController::class, 'cancel'])->name('cancel');
        
        Route::get('/export', [SecurityReportsController::class, 'export'])->name('export');
        Route::get('/dashboard-statistics', [SecurityReportsController::class, 'dashboardStatistics'])->name('dashboard-statistics');
        Route::post('/bulk-action', [SecurityReportsController::class, 'bulkAction'])->name('bulk-action');
    });
    
});



// Security Supervisor Assignments Routes - Admin Controller
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('supervisor-assignments')->name('supervisor-assignments.')->group(function () {
        
        // ============================================================
        // MAIN CRUD OPERATIONS
        // ============================================================
        Route::get('/', [AdminSupervisorAssignmentController::class, 'index'])->name('index');
        Route::get('/create', [AdminSupervisorAssignmentController::class, 'create'])->name('create');
        Route::post('/', [AdminSupervisorAssignmentController::class, 'store'])->name('store');

        // ============================================================
        // EXPORT ROUTES - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::get('/export', [AdminSupervisorAssignmentController::class, 'export'])->name('export');
        Route::get('/export-selected', [AdminSupervisorAssignmentController::class, 'exportSelected'])->name('export-selected');

        // ============================================================
        // ✅ FIXED: TRASH MANAGEMENT - MOVED BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('trash')->name('trash.')->group(function () {
            Route::get('/', [AdminSupervisorAssignmentController::class, 'trash'])->name('index');
            Route::post('/restore/{id}', [AdminSupervisorAssignmentController::class, 'restore'])->name('restore');
            Route::delete('/force-delete/{id}', [AdminSupervisorAssignmentController::class, 'forceDelete'])->name('force-delete');
        });

        // ============================================================
        // BULK OPERATIONS - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::post('/action', [AdminSupervisorAssignmentController::class, 'bulkAction'])->name('action');
            Route::post('/restore', [AdminSupervisorAssignmentController::class, 'bulkRestore'])->name('restore');
            Route::post('/force-delete', [AdminSupervisorAssignmentController::class, 'bulkForceDelete'])->name('force-delete');
        });

        // ============================================================
        // API ENDPOINTS FOR AJAX - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/eligible-supervisors', [AdminSupervisorAssignmentController::class, 'getEligibleSupervisors'])->name('eligible-supervisors');
            Route::get('/post-supervisors/{postId}', [AdminSupervisorAssignmentController::class, 'getPostSupervisors'])->name('post-supervisors');
            Route::get('/supervisor-posts/{userId}', [AdminSupervisorAssignmentController::class, 'getSupervisorPosts'])->name('supervisor-posts');
            Route::get('/assignment-details/{assignment}', [AdminSupervisorAssignmentController::class, 'getAssignmentDetails'])->name('assignment-details');
            Route::get('/hierarchy', [AdminSupervisorAssignmentController::class, 'getHierarchyTree'])->name('hierarchy');
            Route::post('/quick-assign', [AdminSupervisorAssignmentController::class, 'quickAssign'])->name('quick-assign');
        });

        // ============================================================
        // REPORTS & ANALYTICS - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [AdminSupervisorAssignmentController::class, 'reports'])->name('index');
            Route::get('/summary', [AdminSupervisorAssignmentController::class, 'summaryReport'])->name('summary');
            Route::get('/performance', [AdminSupervisorAssignmentController::class, 'performanceReport'])->name('performance');
            Route::get('/coverage', [AdminSupervisorAssignmentController::class, 'coverageReport'])->name('coverage');
            Route::get('/export-summary', [AdminSupervisorAssignmentController::class, 'exportSummaryReport'])->name('export-summary');
            Route::get('/print', [AdminSupervisorAssignmentController::class, 'printReport'])->name('print');
        });

        // ============================================================
        // STATISTICS DASHBOARD - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('stats')->name('stats.')->group(function () {
            Route::get('/dashboard', [AdminSupervisorAssignmentController::class, 'statsDashboard'])->name('dashboard');
            Route::get('/by-post', [AdminSupervisorAssignmentController::class, 'statsByPost'])->name('by-post');
            Route::get('/by-supervisor', [AdminSupervisorAssignmentController::class, 'statsBySupervisor'])->name('by-supervisor');
            Route::get('/by-type', [AdminSupervisorAssignmentController::class, 'statsByType'])->name('by-type');
            Route::get('/trends', [AdminSupervisorAssignmentController::class, 'statsTrends'])->name('trends');
            Route::get('/export', [AdminSupervisorAssignmentController::class, 'exportStats'])->name('export');
        });

        // ============================================================
        // PLANNING & SCHEDULING - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('planning')->name('planning.')->group(function () {
            Route::get('/calendar', [AdminSupervisorAssignmentController::class, 'calendar'])->name('calendar');
            Route::get('/post-coverage', [AdminSupervisorAssignmentController::class, 'getPostCoverage'])->name('post-coverage');
            Route::get('/availability', [AdminSupervisorAssignmentController::class, 'checkAvailability'])->name('availability');
            Route::post('/bulk-assign', [AdminSupervisorAssignmentController::class, 'bulkAssign'])->name('bulk-assign');
            Route::get('/gaps', [AdminSupervisorAssignmentController::class, 'findCoverageGaps'])->name('gaps');
        });

        // ============================================================
        // SUPERVISION & OVERSIGHT - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('supervision')->name('supervision.')->group(function () {
            Route::get('/pending-approvals', [AdminSupervisorAssignmentController::class, 'pendingApprovals'])->name('pending-approvals');
            Route::get('/active-supervisors', [AdminSupervisorAssignmentController::class, 'activeSupervisors'])->name('active-supervisors');
            Route::get('/history/{userId}', [AdminSupervisorAssignmentController::class, 'supervisorHistory'])->name('history');
            Route::get('/audit', [AdminSupervisorAssignmentController::class, 'auditTrail'])->name('audit');
        });

        // ============================================================
        // VALIDATION CHECKS - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('validate')->name('validate.')->group(function () {
            Route::post('/conflict', [AdminSupervisorAssignmentController::class, 'checkConflict'])->name('conflict');
            Route::post('/eligibility', [AdminSupervisorAssignmentController::class, 'checkEligibility'])->name('eligibility');
            Route::post('/availability', [AdminSupervisorAssignmentController::class, 'checkAvailability'])->name('availability');
            Route::post('/permissions', [AdminSupervisorAssignmentController::class, 'validatePermissions'])->name('permissions');
        });

        // ============================================================
        // IMPORT ROUTES - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/', [AdminSupervisorAssignmentController::class, 'importForm'])->name('form');
            Route::post('/', [AdminSupervisorAssignmentController::class, 'import'])->name('process');
            Route::get('/template', [AdminSupervisorAssignmentController::class, 'downloadTemplate'])->name('template');
            Route::post('/preview', [AdminSupervisorAssignmentController::class, 'previewImport'])->name('preview');
        });

        // ============================================================
        // ADMIN DASHBOARD - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::get('/dashboard', [AdminSupervisorAssignmentController::class, 'adminDashboard'])->name('dashboard');

        // ============================================================
        // ✅ FIXED: INDIVIDUAL ASSIGNMENT ROUTES - NOW AFTER ALL STATIC ROUTES
        // These must come LAST to avoid capturing static routes
        // ============================================================
        Route::prefix('{assignment}')->group(function () {
            Route::get('/', [AdminSupervisorAssignmentController::class, 'show'])->name('show');
            Route::get('/edit', [AdminSupervisorAssignmentController::class, 'edit'])->name('edit');
            Route::put('/', [AdminSupervisorAssignmentController::class, 'update'])->name('update');
            Route::delete('/', [AdminSupervisorAssignmentController::class, 'destroy'])->name('destroy');
            Route::post('/toggle-active', [AdminSupervisorAssignmentController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/extend', [AdminSupervisorAssignmentController::class, 'extend'])->name('extend');
            Route::post('/terminate', [AdminSupervisorAssignmentController::class, 'terminate'])->name('terminate');
            Route::post('/transfer', [AdminSupervisorAssignmentController::class, 'transfer'])->name('transfer');
            Route::post('/handover', [AdminSupervisorAssignmentController::class, 'handover'])->name('handover');
        });
    });
});



// Security Supervisor Assignments Routes - Security Personnel Controller
Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    
    Route::prefix('supervisor-assignments')->name('supervisor-assignments.')->group(function () {
        
        // ============================================================
        // MAIN CRUD OPERATIONS
        // ============================================================
        Route::get('/', [SupervisorAssignmentController::class, 'index'])->name('index');
        Route::get('/create', [SupervisorAssignmentController::class, 'create'])->name('create');
        Route::post('/', [SupervisorAssignmentController::class, 'store'])->name('store');
        
        // ============================================================
        // ✅ FIXED: EXPORT ROUTE - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::get('/export', [SupervisorAssignmentController::class, 'export'])->name('export');
        
        // ============================================================
        // BULK OPERATIONS (Limited - Activate/Deactivate only)
        // ============================================================
        Route::post('/bulk-action', [SupervisorAssignmentController::class, 'bulkAction'])->name('bulk-action');

        // ============================================================
        // API ENDPOINTS FOR AJAX - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/eligible-supervisors', [SupervisorAssignmentController::class, 'getEligibleSupervisors'])->name('eligible-supervisors');
            Route::get('/post-supervisors/{postId}', [SupervisorAssignmentController::class, 'getPostSupervisors'])->name('post-supervisors');
            Route::get('/assignment-details/{assignment}', [SupervisorAssignmentController::class, 'getAssignmentDetails'])->name('assignment-details');
        });

        // ============================================================
        // SUPERVISOR DASHBOARD & STATS - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::get('/dashboard', [SupervisorAssignmentController::class, 'supervisorDashboard'])->name('dashboard');
        Route::get('/stats', [SupervisorAssignmentController::class, 'getStats'])->name('stats');

        // ============================================================
        // VALIDATION CHECKS - MUST COME BEFORE PARAMETERIZED ROUTES
        // ============================================================
        Route::prefix('validate')->name('validate.')->group(function () {
            Route::post('/availability', [SupervisorAssignmentController::class, 'checkAvailability'])->name('availability');
            Route::post('/eligibility', [SupervisorAssignmentController::class, 'checkEligibility'])->name('eligibility');
        });

        // ============================================================
        // ✅ FIXED: INDIVIDUAL ASSIGNMENT ROUTES - MUST COME LAST
        // These must be AFTER all static routes to avoid conflicts
        // ============================================================
        Route::prefix('{assignment}')->group(function () {
            Route::get('/', [SupervisorAssignmentController::class, 'show'])->name('show');
            Route::get('/edit', [SupervisorAssignmentController::class, 'edit'])->name('edit');
            Route::put('/', [SupervisorAssignmentController::class, 'update'])->name('update');
            Route::delete('/', [SupervisorAssignmentController::class, 'destroy'])->name('destroy');
            Route::post('/toggle-active', [SupervisorAssignmentController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/extend', [SupervisorAssignmentController::class, 'extend'])->name('extend');
            Route::post('/terminate', [SupervisorAssignmentController::class, 'terminate'])->name('terminate');
        });
    });
});


/*
|--------------------------------------------------------------------------
| Security Personnel Management Routes - Area Supervisor
|--------------------------------------------------------------------------
*/

Route::prefix('security')
    ->middleware(['auth'])
    ->name('security.')
    ->group(function () {
        
        // ==================== Personnel Management ====================
        
        Route::prefix('personnel')
            ->name('personnel.')
            ->controller(SecurityPersonnelAssignmentController::class)
            ->group(function () {
                
                // ==================== CRUD Operations ====================
                
                // List all personnel
                Route::get('/', 'index')->name('index');
                
                // Create new personnel
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                
                // ==================== ⚠️ TRASH ROUTES MUST COME BEFORE {userId} ROUTES ====================
                
                // Display trashed personnel (must be BEFORE any {userId} routes)
                Route::get('/trash', 'trash')->name('trash');
                
                // Bulk restore from trash (must be BEFORE any {userId} routes)
                Route::post('/bulk/restore', 'bulkRestore')->name('bulk-restore');
                
                // Bulk permanent delete from trash (must be BEFORE any {userId} routes)
                Route::post('/bulk/permanent-delete', 'bulkPermanentDelete')->name('bulk-permanent-delete');
                
                // Empty entire trash (must be BEFORE any {userId} routes)
                Route::post('/empty-trash', 'emptyTrash')->name('empty-trash');
                
                // Bulk send invitations (must be BEFORE any {userId} routes)
                Route::post('/bulk/send-invitation', 'bulkSendInvitation')->name('bulk-send-invitation');
                
                // ==================== User-Specific Routes (with {userId} parameter) ====================
                // These must come AFTER the specific path routes above
                
                // View personnel details (AJAX)
                Route::get('/{userId}', 'show')->name('show');
                
                // Get personnel data for editing (AJAX)
                Route::get('/{userId}/edit-data', 'getEditData')->name('edit-data');
                
                // Edit personnel (full page)
                Route::get('/{userId}/edit', 'edit')->name('edit');
                
                // Update personnel (AJAX & Form)
                Route::put('/{userId}', 'update')->name('update');
                
                // Soft delete personnel
                Route::delete('/{userId}', 'destroy')->name('destroy');
                
                // Check critical relations before deletion (AJAX)
                Route::get('/{userId}/check-relations', 'checkRelations')->name('check-relations');
                
                // Restore personnel from trash
                Route::post('/{userId}/restore', 'restore')->name('restore');
                
                // Force delete personnel (permanent)
                Route::post('/{userId}/force-delete', 'forceDelete')->name('force-delete');
                Route::post('/{userId}/force-destroy', 'forceDestroy')->name('force-destroy');
                
                // ==================== Supervisor Management ====================
                
                Route::prefix('{userId}/supervisor')
                    ->name('supervisor.')
                    ->group(function () {
                        // Show assign supervisor form
                        Route::get('/assign', 'assignSupervisorForm')->name('assign');
                        
                        // Assign supervisor role
                        Route::post('/assign', 'assignSupervisor')->name('store');
                        
                        // Remove supervisor role
                        Route::delete('/remove', 'removeSupervisor')->name('remove');
                    });
                
                // ==================== Quick Actions (AJAX) ====================
                
                // Quick assign to post
                Route::post('/{userId}/quick-assign-post', 'quickAssignPost')->name('quick-assign-post');
                
                // Quick unassign from post
                Route::post('/{userId}/unassign-post', 'unassignPost')->name('unassign-post');
                
                // ==================== Invitation Management ====================
                
                // Send invitation to personnel
                Route::post('/{userId}/send-invitation', 'sendInvitation')->name('send-invitation');
                
                // Resend invitation to personnel
                Route::post('/{userId}/resend-invitation', 'resendInvitation')->name('resend-invitation');
                
                // Get invitation info for personnel (AJAX)
                Route::get('/{userId}/invitation-info', 'getInvitationInfo')->name('invitation-info');
                
                // Get available channels for personnel (AJAX)
                Route::get('/{userId}/channels', 'getUserChannels')->name('channels');
            });
        
        // ==================== Supervisor Assignments ====================
        
        Route::prefix('supervisor-assignments')
            ->name('supervisor-assignments.')
            ->controller(SupervisorAssignmentController::class)
            ->group(function () {
                // Main CRUD routes
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::get('/{id}/edit', 'edit')->name('edit');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                
                // Additional supervisor actions
                Route::post('/{id}/toggle-active', 'toggleActive')->name('toggle-active');
                Route::post('/{id}/extend', 'extend')->name('extend');
                Route::post('/{id}/terminate', 'terminate')->name('terminate');
                
                // API / AJAX routes
                Route::get('/eligible-supervisors', 'getEligibleSupervisors')->name('eligible-supervisors');
                Route::post('/bulk-action', 'bulkAction')->name('bulk-action');
                
                // Export routes
                Route::get('/export', 'export')->name('export');
            });
    });


// Security Supervisor Personal Routes (for supervisors to access their own dashboard)
Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    
    Route::prefix('supervisor')->name('supervisor.')->group(function () {
        // Supervisor's personal dashboard
        Route::get('/dashboard', [SupervisorController::class, 'dashboard'])->name('dashboard');
        
        // Supervisor's own assignments
        Route::prefix('my-assignments')->name('assignments.')->group(function () {
            Route::get('/', [SupervisorController::class, 'myAssignments'])->name('index');
            Route::get('/current', [SupervisorController::class, 'myCurrentAssignments'])->name('current');
            Route::get('/history', [SupervisorController::class, 'myAssignmentHistory'])->name('history');
            Route::get('/{id}', [SupervisorController::class, 'myAssignmentDetails'])->name('show');
        });
        
        // Supervisor actions
        Route::prefix('actions')->name('actions.')->group(function () {
            Route::get('/pending', [SupervisorController::class, 'myPendingApprovals'])->name('pending');
            Route::post('/approve-swap/{scheduleId}', [SupervisorController::class, 'approveSwap'])->name('approve-swap');
            // Note: approveOvertime, verifyCheckin, reviewIncident, requestBackup 
            // would need to be added to SupervisorController if needed
            // Route::post('/approve-overtime/{scheduleId}', [SupervisorController::class, 'approveOvertime'])->name('approve-overtime');
            // Route::post('/verify-checkin/{scheduleId}', [SupervisorController::class, 'verifyCheckin'])->name('verify-checkin');
            // Route::post('/review-incident/{incidentId}', [SupervisorController::class, 'reviewIncident'])->name('review-incident');
            // Route::post('/request-backup', [SupervisorController::class, 'requestBackup'])->name('request-backup');
        });
        
        // Team overview
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/today', [SupervisorController::class, 'teamToday'])->name('today');
            Route::get('/schedule', [SupervisorController::class, 'teamSchedule'])->name('schedule');
            Route::get('/attendance', [SupervisorController::class, 'teamAttendance'])->name('attendance');
            Route::get('/performance', [SupervisorController::class, 'teamPerformance'])->name('performance');
        });
        
        // Post overview
        Route::prefix('posts')->name('posts.')->group(function () {
            // Note: myPosts and postDetails would need to be added to SupervisorController
            // Route::get('/', [SupervisorController::class, 'myPosts'])->name('index');
            // Route::get('/{postId}', [SupervisorController::class, 'postDetails'])->name('show');
            Route::get('/{postId}/schedule', [SupervisorController::class, 'postSchedule'])->name('schedule');
            // Route::get('/{postId}/personnel', [SupervisorController::class, 'postPersonnel'])->name('personnel');
        });
    });
    
});


/*
|--------------------------------------------------------------------------
| Authenticated Admin Routes (Super Admin & Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    // ==================== CONSTRUCTION REGISTRATIONS MANAGEMENT ====================
    
    // List all construction registrations with filters
    Route::get('/construction-registrations', 
        [AdminConstructionRegistrationController::class, 'index'])
        ->name('construction-registrations.index');
    
    // ==================== ✅ IMPORTANT: SPECIFIC ROUTES MUST COME BEFORE WILDCARD ====================
    
    // Dashboard/stats for construction registrations
    Route::get('/construction-registrations/stats/overview', 
        [AdminConstructionRegistrationController::class, 'stats'])
        ->name('construction-registrations.stats');
    
    // Export registrations to CSV/Excel
    Route::get('/construction-registrations/export', 
        [AdminConstructionRegistrationController::class, 'export'])
        ->name('construction-registrations.export');
    
    // Export statistics report
    Route::get('/construction-registrations/export-stats', 
        [AdminConstructionRegistrationController::class, 'exportStats'])
        ->name('construction-registrations.export-stats');
    
    // Export archived registrations to CSV/Excel/PDF
    Route::get('/construction-registrations/export-archived', 
        [AdminConstructionRegistrationController::class, 'exportArchived'])
        ->name('construction-registrations.export-archived');
    
    // Bulk actions on multiple registrations (API endpoint)
    Route::post('/construction-registrations/api/bulk-action', 
        [AdminConstructionRegistrationController::class, 'bulkAction'])
        ->name('construction-registrations.api.bulk-action');
    
    // Bulk approve registrations
    Route::post('/construction-registrations/bulk-approve', 
        [AdminConstructionRegistrationController::class, 'bulkApprove'])
        ->name('construction-registrations.bulk-approve');
    
    // ==================== ✅ DUPLICATE MANAGEMENT ROUTES ====================
    
    // View duplicate registrations
    Route::get('/construction-registrations/duplicates', 
        [AdminConstructionRegistrationController::class, 'viewDuplicates'])
        ->name('construction-registrations.duplicates');
    
    // Merge duplicate registrations (API)
    Route::post('/construction-registrations/api/merge-duplicates', 
        [AdminConstructionRegistrationController::class, 'mergeDuplicates'])
        ->name('construction-registrations.api.merge-duplicates');
    
    // Check duplicate (API)
    Route::post('/construction-registrations/api/check-duplicate', 
        [AdminConstructionRegistrationController::class, 'checkDuplicate'])
        ->name('construction-registrations.api.check-duplicate');
    
    // Get duplicate details
    Route::get('/construction-registrations/{registration}/duplicates', 
        [AdminConstructionRegistrationController::class, 'getDuplicates'])
        ->name('construction-registrations.get-duplicates');
    
    // Mark duplicate status
    Route::patch('/construction-registrations/{registration}/duplicate-status', 
        [AdminConstructionRegistrationController::class, 'markDuplicateStatus'])
        ->name('construction-registrations.mark-duplicate-status');
    
    // ==================== ✅ ARCHIVE MANAGEMENT ROUTES (MUST COME BEFORE WILDCARD) ====================
    
    /**
     * View archived registrations (GET)
     * GET /admin/construction-registrations/archived
     */
    Route::get('/construction-registrations/archived', 
        [AdminConstructionRegistrationController::class, 'getArchivedRegistrations'])
        ->name('construction-registrations.archived');
    
    /**
     * Archive registrations (POST)
     * POST /admin/construction-registrations/archive
     */
    Route::post('/construction-registrations/archive', 
        [AdminConstructionRegistrationController::class, 'archiveRegistrations'])
        ->name('construction-registrations.archive');
    
    /**
     * Restore archived registrations (POST)
     * POST /admin/construction-registrations/restore-archived/{id?}
     */
    Route::post('/construction-registrations/restore-archived/{id?}', 
        [AdminConstructionRegistrationController::class, 'restoreArchived'])
        ->name('construction-registrations.restore-archived');
    
    /**
     * Get archive statistics (GET)
     * GET /admin/construction-registrations/archive-stats
     */
    Route::get('/construction-registrations/archive-stats', 
        [AdminConstructionRegistrationController::class, 'getArchiveStats'])
        ->name('construction-registrations.archive-stats');
    
    /**
     * Get eligible registrations for year-end archive (GET)
     * GET /admin/construction-registrations/eligible-for-archive/{year}
     */
    Route::get('/construction-registrations/eligible-for-archive/{year}', 
        [AdminConstructionRegistrationController::class, 'getEligibleForYearEndArchive'])
        ->name('construction-registrations.eligible-for-archive');
    
    /**
     * Process year-end archive (POST)
     * POST /admin/construction-registrations/process-year-end-archive
     */
    Route::post('/construction-registrations/process-year-end-archive', 
        [AdminConstructionRegistrationController::class, 'processYearEndArchive'])
        ->name('construction-registrations.process-year-end-archive');
    
    /**
     * Send year-end archive reminders (POST)
     * POST /admin/construction-registrations/send-year-end-reminders
     */
    Route::post('/construction-registrations/send-year-end-reminders', 
        [AdminConstructionRegistrationController::class, 'sendYearEndReminders'])
        ->name('construction-registrations.send-year-end-reminders');
    
    /**
     * Preview post-payment archive (GET)
     * GET /admin/construction-registrations/preview-post-payment-archive
     */
    Route::get('/construction-registrations/preview-post-payment-archive', 
        [AdminConstructionRegistrationController::class, 'previewPostPaymentArchive'])
        ->name('construction-registrations.preview-post-payment-archive');
    
    /**
     * Process post-payment archive (POST)
     * POST /admin/construction-registrations/process-post-payment-archive
     */
    Route::post('/construction-registrations/process-post-payment-archive', 
        [AdminConstructionRegistrationController::class, 'processPostPaymentArchive'])
        ->name('construction-registrations.process-post-payment-archive');
    
    /**
     * Permanently delete single archived registration (DELETE)
     * DELETE /admin/construction-registrations/permanent-delete/{id}
     */
    Route::delete('/construction-registrations/permanent-delete/{id}', 
        [AdminConstructionRegistrationController::class, 'permanentDelete'])
        ->name('construction-registrations.permanent-delete');
    
    /**
     * Permanently delete multiple archived registrations (DELETE - Bulk)
     * DELETE /admin/construction-registrations/permanent-delete-bulk
     */
    Route::delete('/construction-registrations/permanent-delete-bulk', 
        [AdminConstructionRegistrationController::class, 'permanentDeleteBulk'])
        ->name('construction-registrations.permanent-delete-bulk');
    
    // ==================== TRASHED CONSTRUCTION REGISTRATIONS (SPECIFIC PATHS) ====================
    
    // View trashed registrations
    Route::get('/construction-registrations/trash', 
        [AdminConstructionRegistrationController::class, 'trash'])
        ->name('construction-registrations.trash');
    
    // Restore all trashed
    Route::post('/construction-registrations/trash/restore-all', 
        [AdminConstructionRegistrationController::class, 'restoreAll'])
        ->name('construction-registrations.restore-all');
    
    // Empty trash
    Route::delete('/construction-registrations/trash/empty', 
        [AdminConstructionRegistrationController::class, 'emptyTrash'])
        ->name('construction-registrations.empty-trash');
    
    // Bulk action for trashed registrations
    Route::post('/construction-registrations/trash/bulk-action', 
        [AdminConstructionRegistrationController::class, 'bulkAction'])
        ->name('construction-registrations.trash.bulk-action');
    
    // ==================== WILDCARD ROUTES (MUST BE LAST) ====================
    
    // View single registration details
    Route::get('/construction-registrations/{registration}', 
        [AdminConstructionRegistrationController::class, 'show'])
        ->name('construction-registrations.show');
    
    // Update registration status
    Route::patch('/construction-registrations/{registration}/update-status', 
        [AdminConstructionRegistrationController::class, 'updateStatus'])
        ->name('construction-registrations.update-status');
    
    // Get available channels for landlord
    Route::get('/construction-registrations/{registration}/channels', 
        [AdminConstructionRegistrationController::class, 'getLandlordChannels'])
        ->name('construction-registrations.channels');
    
    // Assign registration to specific admin for review
    Route::post('/construction-registrations/{registration}/assign', 
        [AdminConstructionRegistrationController::class, 'assignToAdmin'])
        ->name('construction-registrations.assign');
    
    // ==================== NOTE ROUTES ====================
    
    // Add note to registration
    Route::post('/construction-registrations/{registration}/notes', 
        [AdminConstructionRegistrationController::class, 'addNote'])
        ->name('construction-registrations.add-note');
    
    // Delete note
    Route::delete('/construction-registrations/notes/{note}', 
        [AdminConstructionRegistrationController::class, 'deleteNote'])
        ->name('construction-registrations.delete-note');
    
    // ==================== DOCUMENT ROUTES ====================
    
    // Upload supporting documents
    Route::post('/construction-registrations/{registration}/documents', 
        [AdminConstructionRegistrationController::class, 'uploadDocument'])
        ->name('construction-registrations.upload-document');
    
    // Download document
    Route::get('/construction-registrations/{registration}/documents/{document}/download', 
        [AdminConstructionRegistrationController::class, 'downloadDocument'])
        ->name('construction-registrations.documents.download');
    
    // Delete document
    Route::delete('/construction-registrations/documents/{document}', 
        [AdminConstructionRegistrationController::class, 'deleteDocument'])
        ->name('construction-registrations.delete-document');
    
    // ==================== TENANT MANAGEMENT ROUTES ====================
    
    // Get tenant details for a specific tenant
    Route::get('/construction-registrations/tenants/{tenant}', 
        [AdminConstructionRegistrationController::class, 'getTenantDetails'])
        ->name('construction-registrations.tenants.details');
    
    // Approve a tenant
    Route::patch('/construction-registrations/tenants/{tenant}/approve', 
        [AdminConstructionRegistrationController::class, 'approveTenant'])
        ->name('construction-registrations.tenants.approve');
    
    // Reject a tenant
    Route::patch('/construction-registrations/tenants/{tenant}/reject', 
        [AdminConstructionRegistrationController::class, 'rejectTenant'])
        ->name('construction-registrations.tenants.reject');
    
    // Migrate legacy tenant data
    Route::post('/construction-registrations/{registration}/migrate-tenants', 
        [AdminConstructionRegistrationController::class, 'migrateTenants'])
        ->name('construction-registrations.migrate-tenants');
    
    // Bulk approve tenants
    Route::post('/construction-registrations/{registration}/tenants/bulk-approve', 
        [AdminConstructionRegistrationController::class, 'bulkApproveTenants'])
        ->name('construction-registrations.tenants.bulk-approve');
    
    // Get tenant invitation status
    Route::get('/construction-registrations/{registration}/tenant-invitations', 
        [AdminConstructionRegistrationController::class, 'getTenantInvitationStatus'])
        ->name('construction-registrations.tenant-invitations');
    
    // Resend tenant invitation
    Route::post('/construction-registrations/invitations/{invitation}/resend', 
        [AdminConstructionRegistrationController::class, 'resendTenantInvitation'])
        ->name('construction-registrations.invitations.resend');
    
    // Cancel tenant invitation
    Route::patch('/construction-registrations/invitations/{invitation}/cancel', 
        [AdminConstructionRegistrationController::class, 'cancelTenantInvitation'])
        ->name('construction-registrations.invitations.cancel');
    
    // Send invitation to a specific tenant
    Route::post('/construction-registrations/tenants/{tenant}/send-invitation', 
        [AdminConstructionRegistrationController::class, 'sendTenantInvitation'])
        ->name('construction-registrations.tenants.send-invitation');
    
    // Get tenant invitation status for a registration (alternative endpoint)
    Route::get('/construction-registrations/{registration}/tenants/invitations', 
        [AdminConstructionRegistrationController::class, 'getTenantInvitations'])
        ->name('construction-registrations.tenants.invitations');
    
    // ==================== SOFT DELETE ROUTES ====================
    
    // Restore from trash (by ID)
    Route::post('/construction-registrations/{id}/restore', 
        [AdminConstructionRegistrationController::class, 'restore'])
        ->name('construction-registrations.restore');
    
    // Permanently delete (by ID)
    Route::delete('/construction-registrations/{id}/force-delete', 
        [AdminConstructionRegistrationController::class, 'forceDelete'])
        ->name('construction-registrations.force-delete');
    
    // Soft delete registration (MUST BE LAST - catches any remaining {registration} patterns)
    Route::delete('/construction-registrations/{registration}', 
        [AdminConstructionRegistrationController::class, 'destroy'])
        ->name('construction-registrations.destroy');
});

/*
|--------------------------------------------------------------------------
| Additional Admin Routes (For other modules)
|--------------------------------------------------------------------------
*/

// Add other admin routes here if needed

// ==================== API ROUTES FOR CONSTRUCTION REGISTRATIONS ====================
// Note: These are separate from the web routes above
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('api/admin')->name('admin.api.')->group(function () {
    
    // Get registration stats for dashboard
    Route::get('/construction-registrations/stats', 
        [AdminConstructionRegistrationController::class, 'stats'])
        ->name('construction-registrations.stats');
    
    // Get registrations for datatable
    Route::get('/construction-registrations/datatable', 
        [AdminConstructionRegistrationController::class, 'datatable'])
        ->name('construction-registrations.datatable');
    
    // Export registrations to Excel
    Route::get('/construction-registrations/export', 
        [AdminConstructionRegistrationController::class, 'export'])
        ->name('construction-registrations.export');
    
    // Bulk action endpoint
    Route::post('/construction-registrations/bulk-action', 
        [AdminConstructionRegistrationController::class, 'bulkAction'])
        ->name('construction-registrations.bulk-action');
});

/*
|--------------------------------------------------------------------------
| Fallback Route for 404
|--------------------------------------------------------------------------
*/

Route::fallback(function () {
    return view('errors.404');
});


/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Public construction registration form submission
Route::post('/landlord/construction/register', 
    [LandlordConstructionRegistrationController::class, 'store'])
    ->name('landlord.construction.register');

// Optional: Public status check page (if you want landlords to check status without login)
Route::get('/construction/status/{token?}', 
    [LandlordConstructionRegistrationController::class, 'checkStatus'])
    ->name('construction.status.check');

/*
|--------------------------------------------------------------------------
| Authenticated Landlord Routes (For Landlords to Track Their Registrations)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    
    // View my construction registrations
    Route::get('/my-construction-registrations', 
        [LandlordConstructionRegistrationController::class, 'myRegistrations'])
        ->name('construction.my-registrations');
    
    // View specific registration status
    Route::get('/my-construction-registrations/{registration}', 
        [LandlordConstructionRegistrationController::class, 'showMyRegistration'])
        ->name('construction.my-registration');
    
    // Edit registration (if still pending and needs info)
    Route::get('/my-construction-registrations/{registration}/edit', 
        [LandlordConstructionRegistrationController::class, 'editMyRegistration'])
        ->name('construction.my-registration.edit');
    
    // Update registration
    Route::put('/my-construction-registrations/{registration}', 
        [LandlordConstructionRegistrationController::class, 'updateMyRegistration'])
        ->name('construction.my-registration.update');
    
    // Cancel registration
    Route::delete('/my-construction-registrations/{registration}', 
        [LandlordConstructionRegistrationController::class, 'cancelMyRegistration'])
        ->name('construction.my-registration.cancel');
});

Route::get('/admin/construction-registrations/export-stats', 
    [LandlordConstructionRegistrationController::class, 'exportStats']
)->name('admin.construction-registrations.export-stats')->middleware('auth');

Route::post('/admin/construction-registrations/bulk-action', 
    [LandlordConstructionRegistrationController::class, 'bulkAction']
)->name('admin.construction-registrations.bulk-action');

// ✅ FIXED: Public registration status API endpoint for frontend
Route::get('/api/registration-status', 
    [LandlordConstructionRegistrationController::class, 'checkRegistrationStatus'])
    ->name('api.registration.status');

// ✅ ADDED: Admin route for viewing duplicate registrations
Route::get('/admin/construction-registrations/duplicates', 
    [LandlordConstructionRegistrationController::class, 'viewDuplicates'])
    ->name('admin.construction-registrations.duplicates')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Webhook/API Routes (For external services - no CSRF)
|--------------------------------------------------------------------------
*/

Route::post('/api/construction-registration/webhook', 
    [LandlordConstructionRegistrationController::class, 'webhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('api.construction.webhook');

/*
|--------------------------------------------------------------------------
| Fallback route for authenticated users
|--------------------------------------------------------------------------
*/

// Public endpoint for system settings (no auth required)
Route::get('/api/system-settings', function() {
    try {
        $settings = \App\Models\SystemSetting::getSettings();
        return response()->json([
            'system_name' => $settings->system_name,
            'system_short_name' => $settings->system_short_name,
            'system_logo' => $settings->system_logo,
            'system_email' => $settings->system_email,
            'system_phone' => $settings->system_phone,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'system_name' => config('app.name', 'Hilltop Estate'),
        ]);
    }
})->name('api.system-settings');

// Admin endpoint (requires authentication)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/system-settings/api/settings', function() {
        $settings = \App\Models\SystemSetting::getSettings();
        return response()->json($settings);
    })->name('admin.system-settings.api');
});



/*
|--------------------------------------------------------------------------
| LANDLORD DASHBOARD ROUTES (User Type: 2)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    
    // =============================================
    // MAIN DASHBOARD ROUTES
    // =============================================
    
    // Main Dashboard View
    Route::get('/dashboard', [HomeController::class, 'landlordDashboard'])->name('dashboard');
    
    // Dashboard Statistics (AJAX)
    Route::get('/dashboard/stats', [HomeController::class, 'getLandlordStats'])->name('dashboard.stats');
    
    // Dashboard Charts Data (AJAX)
    Route::get('/dashboard/charts', [HomeController::class, 'getLandlordChartDataAjax'])->name('dashboard.charts');
    
    // Refresh Dashboard (AJAX)
    Route::post('/dashboard/refresh', [HomeController::class, 'refreshLandlordDashboard'])->name('dashboard.refresh');
    
    // Export Dashboard Data
    Route::get('/dashboard/export', [HomeController::class, 'exportLandlordDashboard'])->name('dashboard.export');
    
    // Dashboard Widgets Management
    Route::get('/dashboard/widgets', [HomeController::class, 'getLandlordWidgets'])->name('dashboard.widgets');
    Route::post('/dashboard/widgets/update', [HomeController::class, 'updateLandlordWidgets'])->name('dashboard.widgets.update');
    Route::post('/dashboard/widgets/reset', [HomeController::class, 'resetLandlordWidgets'])->name('dashboard.widgets.reset');
    
    // =============================================
    // FINANCIAL MANAGEMENT ROUTES
    // =============================================
    
    // Financial Summary
    Route::get('/financial-summary', [HomeController::class, 'getLandlordFinancialSummaryData'])->name('financial.summary');
    
    // Export Financial Report
    Route::get('/financial/export', [HomeController::class, 'exportLandlordFinancialReport'])->name('financial.export');
    
    // Unit Financial Details
    Route::get('/units/{unitId}/financials', [HomeController::class, 'getLandlordUnitFinancials'])->name('units.financials');
    
    // Generate Unit Invoice
    Route::post('/units/{unitId}/invoices/generate', [HomeController::class, 'generateLandlordUnitInvoice'])->name('units.invoices.generate');
    
    // Record Invoice Payment
    Route::post('/invoices/{invoiceId}/payments/record', [HomeController::class, 'recordLandlordInvoicePayment'])->name('invoices.payments.record');
    
    // =============================================
    // MAINTENANCE MANAGEMENT ROUTES
    // =============================================
    
    // Maintenance Summary (AJAX)
    Route::get('/maintenance/summary', [HomeController::class, 'getLandlordMaintenanceSummaryData'])->name('maintenance.summary');
    
    // Maintenance Analytics
    Route::get('/maintenance/analytics', [HomeController::class, 'landlordMaintenanceAnalytics'])->name('maintenance.analytics');
    
    // Unit Maintenance Requests
    Route::get('/units/{unitId}/maintenance', [HomeController::class, 'getLandlordUnitMaintenanceRequests'])->name('units.maintenance');
    
    // Update Maintenance Request Status
    Route::put('/maintenance/{requestId}/update', [HomeController::class, 'updateLandlordMaintenanceRequestStatus'])->name('maintenance.update');
    
    // Complete Unit Maintenance
    Route::post('/units/{unitId}/maintenance/complete', [HomeController::class, 'completeLandlordUnitMaintenance'])->name('units.maintenance.complete');
    
    // =============================================
    // CHANNELS MANAGEMENT ROUTES
    // =============================================
    
    // Channels Listing
    Route::get('/channels', [HomeController::class, 'landlordChannelsIndex'])->name('channels');
    
    // Create Channel
    Route::get('/channels/create', [HomeController::class, 'landlordChannelsCreate'])->name('channels.create');
    Route::post('/channels', [HomeController::class, 'landlordChannelsStore'])->name('channels.store');
    
    // Channel Details
    Route::get('/channels/{channel}', [HomeController::class, 'landlordChannelsShow'])->name('channels.show');
    
    // Edit Channel
    Route::get('/channels/{channel}/edit', [HomeController::class, 'landlordChannelsEdit'])->name('channels.edit');
    Route::put('/channels/{channel}', [HomeController::class, 'landlordChannelsUpdate'])->name('channels.update');
    Route::delete('/channels/{channel}', [HomeController::class, 'landlordChannelsDestroy'])->name('channels.destroy');
    
    // Channel Subscription
    Route::get('/channels/{channel}/subscribe', [HomeController::class, 'landlordChannelSubscribeForm'])->name('channels.subscribe');
    Route::post('/channels/{channel}/subscribe', [HomeController::class, 'processLandlordChannelSubscription'])->name('channels.subscribe.process');
    
    // Channel Analytics
    Route::get('/channels/{channel}/analytics', [HomeController::class, 'landlordChannelAnalytics'])->name('channels.analytics');
    
    // =============================================
    // INVITATION MANAGEMENT ROUTES
    // =============================================
    
    // Active Invitations
    Route::get('/active-invitations', [HomeController::class, 'landlordActiveInvitations'])->name('active-invitations');
    
    // Resend Invitation
    Route::post('/invitations/resend', [HomeController::class, 'landlordResendInvitation'])->name('invitations.resend');
    
    // Cancel Invitation
    Route::delete('/invitations/cancel', [HomeController::class, 'landlordCancelInvitation'])->name('invitations.cancel');
    
    // Send Tenant Invitation
    Route::get('/tenant-invitations/send', [TenantInvitationController::class, 'create'])->name('tenant-invitations.send');
    Route::post('/tenant-invitations', [TenantInvitationController::class, 'store'])->name('tenant-invitations.store');
    
    // =============================================
    // SETTINGS ROUTES
    // =============================================
    
    // Notification Settings
    Route::get('/notification-settings', [HomeController::class, 'landlordNotificationSettings'])->name('notification-settings');
    Route::put('/notification-settings', [HomeController::class, 'updateLandlordNotificationSettings'])->name('notification-settings.update');
    
    // =============================================
    // PERFORMANCE ANALYTICS ROUTES
    // =============================================
    
    // Properties Performance
    Route::get('/properties/performance', [HomeController::class, 'landlordPropertiesPerformance'])->name('properties.performance');
    
    // Tenants Performance
    Route::get('/tenants/performance', [HomeController::class, 'landlordTenantsPerformance'])->name('tenants.performance');
    
    // Lease Analytics
    Route::get('/leases/analytics', [HomeController::class, 'landlordLeaseAnalytics'])->name('leases.analytics');
    
    
    
    
    // =============================================
    // PAYMENTS HISTORY (External)
    // =============================================
    
    // Payments History (using PaymentController)
    Route::get('/payments/history', [PaymentController::class, 'history'])->name('payments.history');
    
    // =============================================
    // NOTIFICATIONS (External)
    // =============================================
    
    // Notifications Listing (using NotificationController)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear', [NotificationController::class, 'clearAll'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    
    // =============================================
    // SYSTEM HEALTH
    // =============================================
    
    // System Health Check
    Route::get('/system/health', [HomeController::class, 'landlordSystemHealth'])->name('system.health');
});


/*
|--------------------------------------------------------------------------
| TENANT DASHBOARD ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:3'])->prefix('tenant')->name('tenant.')->group(function () {
    
    // =============================================
    // MAIN DASHBOARD ROUTES
    // =============================================
    
    // Dashboard home page
    Route::get('/dashboard', [TenantDashboardController::class, 'index'])
        ->name('dashboard');
    
    // Dashboard statistics (AJAX)
    Route::get('/dashboard/stats', [TenantDashboardController::class, 'getStats'])
        ->name('dashboard.stats');
    
    // Dashboard charts data (AJAX)
    Route::get('/dashboard/charts', [TenantDashboardController::class, 'getChartDataAjax'])
        ->name('dashboard.charts');
    
    // Refresh dashboard data (AJAX)
    Route::post('/dashboard/refresh', [TenantDashboardController::class, 'refreshDashboard'])
        ->name('dashboard.refresh');
    
    // Export dashboard data
    Route::get('/dashboard/export', [TenantDashboardController::class, 'exportDashboard'])
        ->name('dashboard.export');
    
    // Widget management
    Route::get('/dashboard/widgets', [TenantDashboardController::class, 'getWidgets'])
        ->name('dashboard.widgets');
    
    Route::post('/dashboard/widgets/update', [TenantDashboardController::class, 'updateWidgets'])
        ->name('dashboard.widgets.update');
    
    Route::post('/dashboard/widgets/reset', [TenantDashboardController::class, 'resetWidgets'])
        ->name('dashboard.widgets.reset');
    
    // Financial summary (AJAX)
    Route::get('/financial-summary', [TenantDashboardController::class, 'getFinancialSummaryData'])
        ->name('financial.summary');
    
    // Maintenance summary (AJAX)
    Route::get('/maintenance-summary', [TenantDashboardController::class, 'getMaintenanceSummaryData'])
        ->name('maintenance.summary');
    
    // Lease status (AJAX)
    Route::get('/lease-status', [TenantDashboardController::class, 'getLeaseStatus'])
        ->name('lease.status');
});


/*
|--------------------------------------------------------------------------
| Field Agent Dashboard Routes (User Type 4)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:4'])->prefix('field-agent')->name('field-agent.')->group(function () {
    
    // ============================================
    // MAIN DASHBOARD
    // ============================================
    
    Route::get('/dashboard', [HomeController::class, 'fieldAgentDashboard'])->name('dashboard');
    
    // Dashboard API routes for AJAX calls
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/data', [HomeController::class, 'fieldAgentGetDashboardData'])->name('data');
        Route::post('/refresh', [HomeController::class, 'fieldAgentRefreshDashboard'])->name('refresh');
        Route::get('/chart-data', [HomeController::class, 'fieldAgentGetPeriodChartData'])->name('chart-data');
    });

    // ============================================
    // PROPERTY VERIFICATION ROUTES
    // ============================================
    
    // Main verifications page
    Route::get('/verifications', [HomeController::class, 'fieldAgentVerifications'])->name('verifications');
    
    // Verification API routes
    Route::prefix('verifications')->name('verifications.')->group(function () {
        // Bulk verification
        Route::post('/bulk', [HomeController::class, 'fieldAgentBulkVerify'])->name('bulk');
        
        // Single verification
        Route::post('/single', [HomeController::class, 'fieldAgentSingleVerify'])->name('single');
        
        // Refresh verification data
        Route::get('/refresh', [HomeController::class, 'fieldAgentRefreshVerifications'])->name('refresh');
        
        // Get verification details
        Route::get('/{propertyId}/details', [HomeController::class, 'fieldAgentGetVerificationDetails'])->name('details');
    });

    // ============================================
    // PROPERTY MANAGEMENT ROUTES - FIXED NAMESPACE
    // ============================================
    
    Route::prefix('properties')->name('properties.')->group(function () {
        // Properties listing (field agent's properties)
        Route::get('/', [FieldAgentPropertyController::class, 'index'])->name('index');
        
        // Field agent specific: my registered properties
        Route::get('/my-properties', [FieldAgentPropertyController::class, 'myProperties'])->name('my-properties');
        
        // Property creation
        Route::get('/create', [FieldAgentPropertyController::class, 'create'])->name('create');
        Route::post('/', [FieldAgentPropertyController::class, 'store'])->name('store');
        
        // Property view and edit
        Route::get('/{property}', [FieldAgentPropertyController::class, 'show'])->name('show');
        Route::get('/{property}/edit', [FieldAgentPropertyController::class, 'edit'])->name('edit');
        Route::put('/{property}', [FieldAgentPropertyController::class, 'update'])->name('update');
        Route::delete('/{property}', [FieldAgentPropertyController::class, 'destroy'])->name('destroy');
        
        // Properties by registration plan
        Route::get('/registration-plan/{planId}', [FieldAgentPropertyController::class, 'getByRegistrationPlan'])->name('by-registration-plan');
        
        // Property type statistics
        Route::get('/type-stats', [FieldAgentPropertyController::class, 'getPropertyTypeStats'])->name('type-stats');
        
        // Properties by property type
        Route::get('/type/{typeSlug}', [FieldAgentPropertyController::class, 'getByPropertyType'])->name('by-type');
    });

    // ============================================
    // REGISTRATION PLAN ROUTES
    // ============================================
    
    // Registration plans listing
    Route::get('/registration-plans', [HomeController::class, 'fieldAgentRegistrationPlans'])->name('registration-plans.index');
    
    // View specific plan
    Route::get('/registration-plans/{plan}', [HomeController::class, 'fieldAgentShowRegistrationPlan'])->name('registration-plans.show');
    
    // Plan progress and details
    Route::get('/registration-plans/{plan}/progress', [HomeController::class, 'fieldAgentPlanProgress'])->name('registration-plans.progress');
    
    // Plan assignments
    Route::get('/plan-assignments', [HomeController::class, 'fieldAgentPlanAssignments'])->name('plan-assignments.index');

    // ============================================
    // FIELD AGENT SETTINGS ROUTES
    // ============================================
    
    // Settings page
    Route::get('/settings', [HomeController::class, 'fieldAgentSettings'])->name('settings');
    
    // Update settings
    Route::put('/settings', [HomeController::class, 'fieldAgentUpdateSettings'])->name('settings.update');
    
    // Update profile
    Route::put('/profile', [HomeController::class, 'fieldAgentUpdateProfile'])->name('profile.update');
    
    // Change password
    Route::put('/change-password', [HomeController::class, 'fieldAgentChangePassword'])->name('change-password');

    // ============================================
    // NOTIFICATION ROUTES
    // ============================================
    
    // Mark notification as read
    Route::post('/notifications/{notification}/read', [HomeController::class, 'fieldAgentMarkNotificationRead'])->name('notifications.read');
    
    // Mark all notifications as read
    Route::post('/notifications/read-all', [HomeController::class, 'fieldAgentMarkAllNotificationsRead'])->name('notifications.read-all');
    
    // Get unread notifications count
    Route::get('/notifications/unread-count', [HomeController::class, 'fieldAgentUnreadNotificationsCount'])->name('notifications.unread-count');

    // ============================================
    // REPORTING ROUTES
    // ============================================
    
    // Reports dashboard
    Route::get('/reports', [HomeController::class, 'fieldAgentReports'])->name('reports');
    
    // Generate property registration report
    Route::get('/reports/properties', [HomeController::class, 'fieldAgentPropertyReport'])->name('reports.properties');
    
    // Generate performance report
    Route::get('/reports/performance', [HomeController::class, 'fieldAgentPerformanceReport'])->name('reports.performance');
    
    // Export report as PDF
    Route::post('/reports/export', [HomeController::class, 'fieldAgentExportReport'])->name('reports.export');
    
    // Download report
    Route::get('/reports/download/{filename}', [HomeController::class, 'fieldAgentDownloadReport'])->name('reports.download');

    // ============================================
    // DOCUMENT UPLOAD ROUTES
    // ============================================
    
    // Upload property document
    Route::post('/properties/{property}/documents', [HomeController::class, 'fieldAgentUploadDocument'])->name('properties.documents.upload');
    
    // Delete document
    Route::delete('/documents/{document}', [HomeController::class, 'fieldAgentDeleteDocument'])->name('documents.delete');
    
    // Download document
    Route::get('/documents/{document}/download', [HomeController::class, 'fieldAgentDownloadDocument'])->name('documents.download');

    // ============================================
    // TASK MANAGEMENT ROUTES
    // ============================================
    
    // Tasks listing
    Route::get('/tasks', [HomeController::class, 'fieldAgentTasks'])->name('tasks.index');
    
    // View specific task
    Route::get('/tasks/{task}', [HomeController::class, 'fieldAgentShowTask'])->name('tasks.show');
    
    // Update task status
    Route::put('/tasks/{task}/status', [HomeController::class, 'fieldAgentUpdateTaskStatus'])->name('tasks.update-status');
    
    // Complete task
    Route::post('/tasks/{task}/complete', [HomeController::class, 'fieldAgentCompleteTask'])->name('tasks.complete');

    // ============================================
    // CUSTOMER/CLIENT ROUTES
    // ============================================
    
    // Clients/landlords listing
    Route::get('/clients', [HomeController::class, 'fieldAgentClients'])->name('clients.index');
    
    // View client details
    Route::get('/clients/{client}', [HomeController::class, 'fieldAgentShowClient'])->name('clients.show');
    
    // Register new client
    Route::get('/clients/create', [HomeController::class, 'fieldAgentCreateClient'])->name('clients.create');
    Route::post('/clients', [HomeController::class, 'fieldAgentStoreClient'])->name('clients.store');
    
    // ============================================
    // LOGOUT ALL DEVICES
    // ============================================
    
    Route::post('/logout-all-devices', [HomeController::class, 'fieldAgentLogoutAllDevices'])->name('logout-all-devices');
});



/*
|--------------------------------------------------------------------------
| Developer Dashboard Routes (User Type: 5)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    
    // =============================================
    // MAIN DASHBOARD VIEW ROUTES
    // =============================================
    
    // Main Dashboard View
    Route::get('/dashboard', [DashboardController::class, 'developerDashboard'])
        ->name('dashboard');
    
    // Dashboard redirect (root path)
    Route::get('/', function() {
        return redirect()->route('developer.dashboard');
    })->name('index');
    
    // =============================================
    // DASHBOARD API ROUTES (AJAX Endpoints)
    // =============================================
    
    // Get all dashboard metrics (CPU, Memory, Disk, etc.)
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics'])
        ->name('dashboard.metrics');
    
    // Get combined dashboard data
    Route::get('/dashboard/data', [DashboardController::class, 'getDashboardData'])
        ->name('dashboard.data');
    
    // Get dashboard errors
    Route::get('/dashboard/errors', [DashboardController::class, 'getDashboardErrors'])
        ->name('dashboard.errors');
    
    // ✅ ADDED: Get error trends for chart
    Route::get('/dashboard/error-trends', [DashboardController::class, 'getErrorTrends'])
        ->name('dashboard.error-trends');
    
    // Get maintenance schedule
    Route::get('/dashboard/maintenance-schedule', [DashboardController::class, 'getMaintenanceSchedule'])
        ->name('dashboard.maintenance-schedule');
    
    // Get emergency modes
    Route::get('/dashboard/emergency-modes', [DashboardController::class, 'getEmergencyModes'])
        ->name('dashboard.emergency-modes');
    
    // Get impact analysis
    Route::get('/dashboard/impact-analysis', [DashboardController::class, 'getImpactAnalysis'])
        ->name('dashboard.impact-analysis');
    
    // Get performance insights
    Route::get('/dashboard/performance-insights', [DashboardController::class, 'getPerformanceInsights'])
        ->name('dashboard.performance-insights');
    
    // Get system alerts
    Route::get('/dashboard/alerts', [DashboardController::class, 'getSystemAlerts'])
        ->name('dashboard.alerts');
    
    // Get system trends
    Route::get('/dashboard/trends', [DashboardController::class, 'getSystemTrends'])
        ->name('dashboard.trends');
    
    // =============================================
    // DASHBOARD ACTION ROUTES (POST endpoints)
    // =============================================
    
    // Run system diagnostics
    Route::post('/dashboard/run-diagnostics', [DashboardController::class, 'runDiagnostics'])
        ->name('dashboard.run-diagnostics');
    
    // Run Artisan command
    Route::post('/dashboard/run-command', [DashboardController::class, 'runCommand'])
        ->name('dashboard.run-command');
    
    // Generate health report
    Route::post('/dashboard/generate-health-report', [DashboardController::class, 'generateHealthReport'])
        ->name('dashboard.generate-health-report');
    
    // Clear system alerts
    Route::post('/dashboard/clear-alerts', [DashboardController::class, 'clearAlerts'])
        ->name('dashboard.clear-alerts');
    
    // =============================================
    // QUICK ACTION ROUTES (For Blade Template)
    // =============================================
    
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        // Emergency Mode Toggle
        Route::post('/emergency/toggle', [DashboardController::class, 'toggleEmergencyMode'])
            ->name('emergency.toggle');
        
        // Mark Error as Resolved
        Route::post('/errors/{id}/mark-resolved', [DashboardController::class, 'markErrorResolved'])
            ->name('errors.mark-resolved');
        
        // Schedule Maintenance
        Route::post('/maintenance/schedule', [DashboardController::class, 'scheduleMaintenance'])
            ->name('maintenance.schedule');
        
        // Get Communication Status
        Route::get('/communication-status', [DashboardController::class, 'getCommunicationStatus'])
            ->name('communication-status');
        
        // Get Performance Metrics (API endpoint)
        Route::get('/performance-metrics', [DashboardController::class, 'getPerformanceMetricsApi'])
            ->name('performance-metrics');
        
        // Download Impact Report
        Route::get('/impact-report/download', [DashboardController::class, 'downloadImpactReport'])
            ->name('impact-report.download');
        
        // Download Health Report
        Route::get('/health-report/download', [DashboardController::class, 'downloadHealthReport'])
            ->name('health-report.download');
        
        // Handle Alert Action
        Route::post('/alerts/{id}/action', [DashboardController::class, 'handleAlertAction'])
            ->name('alerts.action');
    });
    
    // =============================================
    // ✅ DATABASE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('database')->name('database.')->group(function () {
        // Database Overview (View)
        Route::get('/', function () {
            return view('developer.database.index');
        })->name('index');
        
        // Database Status (API)
        Route::get('/status', [DashboardController::class, 'getDatabaseStatus'])
            ->name('status');
        
        // Database Statistics (API)
        Route::get('/statistics', [DashboardController::class, 'getDatabaseStatistics'])
            ->name('statistics');
        
        // Backups List (API)
        Route::get('/backups', [DashboardController::class, 'getBackups'])
            ->name('backups');
        
        // Create Backup (API)
        Route::post('/backups/create', [DashboardController::class, 'createBackup'])
            ->name('backups.create');
        
        // ✅ Delete Backup (API)
        Route::delete('/backups/delete', [DashboardController::class, 'deleteBackup'])
            ->name('backups.delete');
        
        // ✅ Download Backup (API)
        Route::get('/backups/download', [DashboardController::class, 'downloadBackup'])
            ->name('backups.download');
        
        // ✅ Cleanup Old Backups (API)
        Route::post('/backups/cleanup', [DashboardController::class, 'cleanupOldBackups'])
            ->name('backups.cleanup');
        
        // Optimize Table (API)
        Route::post('/maintenance/optimize', [DashboardController::class, 'optimizeTable'])
            ->name('maintenance.optimize');
    });
    
    // =============================================
    // ✅ PERFORMANCE MONITORING ROUTES
    // =============================================
    
    Route::prefix('performance')->name('performance.')->group(function () {
        // Performance Dashboard View
        Route::get('/dashboard', function () {
            return view('developer.performance.dashboard');
        })->name('dashboard');
        
        // Performance Metrics for Charts (Response Time, Throughput, Resources)
        Route::get('/metrics', [DashboardController::class, 'getPerformanceMetrics'])
            ->name('metrics');
        
        // Slow Queries Analysis
        Route::get('/analysis/slow-queries', [DashboardController::class, 'getSlowQueries'])
            ->name('analysis.slow-queries');
        
        // API Performance Analysis
        Route::get('/analysis/api-performance', [DashboardController::class, 'getApiPerformance'])
            ->name('analysis.api-performance');
        
        // Performance Export (if needed)
        Route::get('/export', function() {
            return response()->json(['message' => 'Export functionality coming soon']);
        })->name('export');
    });
    
    // =============================================
    // ✅ SYSTEM HEALTH ROUTES
    // =============================================
    
    Route::prefix('health')->name('health.')->group(function () {
        // Health Dashboard View
        Route::get('/', [App\Http\Controllers\Developer\SystemHealthController::class, 'index'])
            ->name('index');
        
        // Overall Health Status
        Route::get('/overall', [App\Http\Controllers\Developer\SystemHealthController::class, 'getOverallHealth'])
            ->name('overall');
        
        // Health Status
        Route::get('/status', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthStatus'])
            ->name('status');
        
        // System Metrics
        Route::get('/metrics', [App\Http\Controllers\Developer\SystemHealthController::class, 'getSystemMetrics'])
            ->name('metrics');
        
        // Live Metrics
        Route::get('/live', [App\Http\Controllers\Developer\SystemHealthController::class, 'getLiveMetrics'])
            ->name('live');
        
        // Server Health
        Route::get('/server', [App\Http\Controllers\Developer\SystemHealthController::class, 'getServerHealth'])
            ->name('server');
        
        // Database Health
        Route::get('/database', [App\Http\Controllers\Developer\SystemHealthController::class, 'getDatabaseHealth'])
            ->name('database');
        
        // Application Health
        Route::get('/application', [App\Http\Controllers\Developer\SystemHealthController::class, 'getApplicationHealth'])
            ->name('application');
        
        // Service Health
        Route::get('/services', [App\Http\Controllers\Developer\SystemHealthController::class, 'getServiceHealth'])
            ->name('services');
        
        // Performance Health
        Route::get('/performance', [App\Http\Controllers\Developer\SystemHealthController::class, 'getPerformanceHealth'])
            ->name('performance');
        
        // Security Health
        Route::get('/security', [App\Http\Controllers\Developer\SystemHealthController::class, 'getSecurityHealth'])
            ->name('security');
        
        // Run Diagnostics
        Route::post('/diagnostics', [App\Http\Controllers\Developer\SystemHealthController::class, 'runDiagnostics'])
            ->name('run-diagnostics');
        
        // Get Diagnostic Result
        Route::get('/diagnostics/{id}', [App\Http\Controllers\Developer\SystemHealthController::class, 'getDiagnosticResult'])
            ->name('diagnostics.result');
        
        // Generate Health Report
        Route::post('/reports/generate', [App\Http\Controllers\Developer\SystemHealthController::class, 'generateHealthReport'])
            ->name('generate-report');
        
        // Get Health Report
        Route::get('/reports/{id}', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthReport'])
            ->name('report');
        
        // Download Health Report
        Route::get('/reports/{id}/download', [App\Http\Controllers\Developer\SystemHealthController::class, 'downloadHealthReport'])
            ->name('report.download');
        
        // Health History
        Route::get('/history', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthHistory'])
            ->name('history');
        
        // Health Trends
        Route::get('/trends', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthTrends'])
            ->name('trends');
        
        // Health Alerts
        Route::get('/alerts', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthAlerts'])
            ->name('alerts');
        
        // Custom Health Check
        Route::post('/checks/custom', [App\Http\Controllers\Developer\SystemHealthController::class, 'runCustomCheck'])
            ->name('checks.custom');
        
        // List Health Checks
        Route::get('/checks', [App\Http\Controllers\Developer\SystemHealthController::class, 'listHealthChecks'])
            ->name('checks.list');
        
        // Get Specific Health Check
        Route::get('/checks/{check}', [App\Http\Controllers\Developer\SystemHealthController::class, 'getHealthCheck'])
            ->name('checks.show');
    });
    
    // =============================================
    // ✅ CACHE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('cache')->name('cache.')->group(function () {
        // Cache Dashboard View
        Route::get('/', function () {
            return view('developer.cache.index');
        })->name('index');
        
        // Cache Statistics (API)
        Route::get('/statistics', [DashboardController::class, 'getCacheStatistics'])
            ->name('statistics');
        
        // Clear Cache (API)
        Route::post('/clear', [DashboardController::class, 'clearCache'])
            ->name('clear');
        
        // Clear Specific Driver (API)
        Route::post('/clear/{driver}', [DashboardController::class, 'clearCacheDriver'])
            ->name('clear.driver');
    });
    
    // =============================================
    // ✅ QUEUE MANAGEMENT ROUTES
    // =============================================
    
    Route::prefix('queue')->name('queue.')->group(function () {
        // Queue Dashboard View
        Route::get('/', function () {
            return view('developer.queue.index');
        })->name('index');
        
        // Queue Monitoring (API)
        Route::get('/monitoring', [DashboardController::class, 'getQueueMonitoring'])
            ->name('monitoring');
        
        // Failed Jobs (API)
        Route::get('/failed', [DashboardController::class, 'getFailedJobs'])
            ->name('failed');
        
        // Retry Failed Job (API)
        Route::post('/failed/retry/{id}', [DashboardController::class, 'retryFailedJob'])
            ->name('failed.retry');
        
        // Retry All Failed Jobs (API)
        Route::post('/failed/retry-all', [DashboardController::class, 'retryAllFailedJobs'])
            ->name('failed.retry-all');
        
        // Delete Failed Job (API)
        Route::delete('/failed/{id}', [DashboardController::class, 'deleteFailedJob'])
            ->name('failed.forget');
        
        // Flush All Failed Jobs (API)
        Route::delete('/failed/flush', [DashboardController::class, 'flushFailedJobs'])
            ->name('failed.flush');
        
        // Restart Queue Workers (API)
        Route::post('/workers/restart', [DashboardController::class, 'restartQueueWorkers'])
            ->name('workers.restart');
    });
});


    // =============================================
    // ✅ SECURITY PERSONNEL DASHBOARD ROUTE
    // =============================================

Route::middleware(['auth', 'multi.auth.user:6'])->prefix('security')->name('security.')->group(function () {
    // Main dashboard
    Route::get('/dashboard', [SecurityDashboardController::class, 'index'])->name('dashboard');
    
    // Dashboard API routes
    Route::get('/dashboard/data', [SecurityDashboardController::class, 'getDashboardData'])->name('dashboard.data');
    Route::post('/dashboard/refresh', [SecurityDashboardController::class, 'refreshDashboard'])->name('dashboard.refresh');
    Route::get('/dashboard/export', [SecurityDashboardController::class, 'exportDashboard'])->name('dashboard.export');
    
    // Schedule management routes (as defined above)
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [SecurityScheduleController::class, 'mySchedules'])->name('index');
        Route::get('/preferences', [SecurityScheduleController::class, 'preferences'])->name('preferences');
        Route::post('/preferences', [SecurityScheduleController::class, 'updatePreferences'])->name('preferences.update');
        Route::get('/availability', [SecurityScheduleController::class, 'availability'])->name('availability');
        Route::post('/availability', [SecurityScheduleController::class, 'updateAvailability'])->name('availability.update');
        Route::get('/{schedule}/checkin', [SecurityScheduleController::class, 'showCheckin'])->name('checkin');
        Route::get('/{schedule}/checkout', [SecurityScheduleController::class, 'showCheckout'])->name('checkout');
        Route::post('/{schedule}/smart-checkin', [SecurityScheduleController::class, 'smartCheckin'])->name('smart-checkin');
        Route::post('/{schedule}/smart-checkout', [SecurityScheduleController::class, 'smartCheckout'])->name('smart-checkout');
        Route::post('/{schedule}/start-break', [SecurityScheduleController::class, 'startBreak'])->name('break.start');
        Route::post('/{schedule}/end-break', [SecurityScheduleController::class, 'endBreak'])->name('break.end');
        Route::get('/{schedule}/handover', [SecurityScheduleController::class, 'handoverDetails'])->name('handover');
        Route::post('/{schedule}/handover/complete', [SecurityScheduleController::class, 'completeHandover'])->name('handover.complete');
        Route::post('/{schedule}/swap', [SecurityScheduleController::class, 'requestSwap'])->name('swap');
        Route::post('/{schedule}/acknowledge', [SecurityScheduleController::class, 'acknowledgeSchedule'])->name('acknowledge');
        Route::post('/{schedule}/report-issue', [SecurityScheduleController::class, 'reportIssue'])->name('report-issue');
    });
    
    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    
    });


// ============================================
// PUBLIC API ROUTES (No authentication required)
// ============================================
Route::prefix('api')->name('api.')->group(function () {
    // Get approved testimonials (with optional limit)
    Route::get('/testimonials/{limit?}', [TestimonialController::class, 'getApprovedTestimonials'])
        ->name('testimonials.list')
        ->where('limit', '[0-9]+');
    
    // Submit testimonial from public homepage
    Route::post('/testimonials', [TestimonialController::class, 'store'])
        ->name('testimonials.submit');
});

// ============================================
// AUTHENTICATED USER DASHBOARD ROUTES
// ============================================
Route::middleware(['auth'])->prefix('dashboard')->name('dashboard.')->group(function () {
    
    Route::prefix('testimonials')->name('testimonials.')->group(function () {
        // Main user dashboard page
        Route::get('/', [TestimonialController::class, 'userTestimonials'])->name('index');
        
        // Submit new testimonial from dashboard
        Route::post('/', [TestimonialController::class, 'storeFromDashboard'])->name('store');
        
        // View specific testimonial
        Route::get('/{id}', [TestimonialController::class, 'showUserTestimonial'])->name('show');
        
        // Update testimonial (only if pending)
        Route::put('/{id}', [TestimonialController::class, 'updateUserTestimonial'])->name('update');
        
        // Delete testimonial (only if pending)
        Route::delete('/{id}', [TestimonialController::class, 'destroyUserTestimonial'])->name('destroy');
        
        // User statistics (AJAX)
        Route::get('/statistics/data', [TestimonialController::class, 'getUserStatistics'])->name('statistics');
        
        // Export user's own testimonials
        Route::get('/export/{format}', [TestimonialController::class, 'exportUserTestimonials'])->name('export');
    });
    
    // Simple alias for backward compatibility
    Route::get('/my-testimonials', [TestimonialController::class, 'userTestimonials'])->name('testimonials.my');
});

// ============================================
// ADMIN TESTIMONIAL ROUTES (Super Admin & Admin)
// ============================================
Route::middleware(['auth', 'multi.auth.user:0,1,5'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('testimonials')->name('testimonials.')->group(function () {
        
        // ========== VIEW ROUTES ==========
        Route::get('/', [TestimonialController::class, 'index'])->name('index');
        Route::get('/trashed', [TestimonialController::class, 'trashed'])->name('trashed');
        Route::get('/{id}', [TestimonialController::class, 'show'])->name('show');
        Route::get('/statistics/data', [TestimonialController::class, 'getStatistics'])->name('statistics');
        
        // ========== SINGLE ACTION ROUTES ==========
        Route::post('/{id}/approve', [TestimonialController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [TestimonialController::class, 'reject'])->name('reject');
        Route::post('/{id}/toggle-featured', [TestimonialController::class, 'toggleFeatured'])->name('toggle-featured');
        Route::delete('/{id}', [TestimonialController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [TestimonialController::class, 'restore'])->name('restore');
        
        // ========== BULK ACTION ROUTES ==========
        Route::post('/bulk-approve', [TestimonialController::class, 'bulkApprove'])->name('bulk-approve');
        Route::post('/bulk-reject', [TestimonialController::class, 'bulkReject'])->name('bulk-reject');
        Route::post('/bulk-feature', [TestimonialController::class, 'bulkFeature'])->name('bulk-feature');
        Route::post('/bulk-unfeature', [TestimonialController::class, 'bulkUnfeature'])->name('bulk-unfeature');
        Route::post('/bulk-soft-delete', [TestimonialController::class, 'bulkSoftDelete'])->name('bulk-soft-delete');
        Route::post('/bulk-restore', [TestimonialController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-permanent-delete', [TestimonialController::class, 'bulkPermanentDelete'])->name('bulk-permanent-delete');
        
        // ========== TRASH MANAGEMENT ==========
        Route::delete('/empty-trash', [TestimonialController::class, 'emptyTrash'])->name('empty-trash');
        
        // ========== UTILITY ROUTES ==========
        Route::post('/update-order', [TestimonialController::class, 'updateOrder'])->name('update-order');
        Route::get('/export/{format}', [TestimonialController::class, 'export'])->name('export');
        
        // Legacy bulk action (maintained for backward compatibility)
        Route::post('/bulk-action', [TestimonialController::class, 'bulkAction'])->name('bulk-action');
    });
});

// ============================================
// DEVELOPER TESTIMONIAL ROUTES (Extended access)
// ============================================
Route::middleware(['auth', 'multi.auth.user:5'])->prefix('developer')->name('developer.')->group(function () {
    
    Route::prefix('testimonials')->name('testimonials.')->group(function () {
        // All routes same as admin (reuse same controller)
        Route::get('/', [TestimonialController::class, 'index'])->name('index');
        Route::get('/trashed', [TestimonialController::class, 'trashed'])->name('trashed');
        Route::get('/{id}', [TestimonialController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [TestimonialController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [TestimonialController::class, 'reject'])->name('reject');
        Route::post('/{id}/toggle-featured', [TestimonialController::class, 'toggleFeatured'])->name('toggle-featured');
        Route::delete('/{id}', [TestimonialController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [TestimonialController::class, 'restore'])->name('restore');
        Route::post('/bulk-approve', [TestimonialController::class, 'bulkApprove'])->name('bulk-approve');
        Route::post('/bulk-reject', [TestimonialController::class, 'bulkReject'])->name('bulk-reject');
        Route::post('/bulk-feature', [TestimonialController::class, 'bulkFeature'])->name('bulk-feature');
        Route::post('/bulk-unfeature', [TestimonialController::class, 'bulkUnfeature'])->name('bulk-unfeature');
        Route::post('/bulk-soft-delete', [TestimonialController::class, 'bulkSoftDelete'])->name('bulk-soft-delete');
        Route::post('/bulk-restore', [TestimonialController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-permanent-delete', [TestimonialController::class, 'bulkPermanentDelete'])->name('bulk-permanent-delete');
        Route::delete('/empty-trash', [TestimonialController::class, 'emptyTrash'])->name('empty-trash');
        Route::post('/update-order', [TestimonialController::class, 'updateOrder'])->name('update-order');
        Route::get('/export/{format}', [TestimonialController::class, 'export'])->name('export');
        Route::post('/bulk-action', [TestimonialController::class, 'bulkAction'])->name('bulk-action');
        Route::get('/statistics/data', [TestimonialController::class, 'getStatistics'])->name('statistics');
    });
});

// ============================================
// SUPER ADMIN ADDITIONAL ROUTES (Extended permissions)
// ============================================
Route::middleware(['auth', 'multi.auth.user:0'])->prefix('super-admin')->name('super-admin.')->group(function () {
    
    Route::prefix('testimonials')->name('testimonials.')->group(function () {
        // Advanced management routes
        Route::get('/all', [TestimonialController::class, 'allTestimonials'])->name('all');
        Route::get('/audit-log', [TestimonialController::class, 'auditLog'])->name('audit-log');
        Route::get('/analytics', [TestimonialController::class, 'analytics'])->name('analytics');
        Route::post('/bulk-force-delete', [TestimonialController::class, 'bulkForceDelete'])->name('bulk-force-delete');
        Route::post('/restore-permanent/{id}', [TestimonialController::class, 'restorePermanent'])->name('restore-permanent');
        Route::get('/export-full/{format}', [TestimonialController::class, 'exportFull'])->name('export-full');
    });
});

// ============================================
// API ROUTES FOR MOBILE/SPA APPLICATIONS
// ============================================
Route::prefix('api')->name('api.')->group(function () {
    
    // Public endpoints (already defined at top)
    
    // Authenticated API endpoints for mobile apps
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user/testimonials', [TestimonialController::class, 'userTestimonials'])->name('user.testimonials');
        Route::post('/user/testimonials', [TestimonialController::class, 'storeFromDashboard'])->name('user.testimonials.store');
        Route::put('/user/testimonials/{id}', [TestimonialController::class, 'updateUserTestimonial'])->name('user.testimonials.update');
        Route::delete('/user/testimonials/{id}', [TestimonialController::class, 'destroyUserTestimonial'])->name('user.testimonials.destroy');
        Route::get('/user/testimonials/statistics', [TestimonialController::class, 'getUserStatistics'])->name('user.testimonials.statistics');
    });
    
    // Admin API endpoints
    Route::middleware(['auth:sanctum', 'multi.auth.user:0,1,5'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.list');
        Route::post('/testimonials/{id}/approve', [TestimonialController::class, 'approve'])->name('testimonials.approve');
        Route::delete('/testimonials/{id}', [TestimonialController::class, 'destroy'])->name('testimonials.destroy');
        Route::post('/testimonials/bulk-actions', [TestimonialController::class, 'bulkApprove'])->name('testimonials.bulk-actions');
        Route::get('/testimonials/statistics', [TestimonialController::class, 'getStatistics'])->name('testimonials.statistics');
    });
});

// ============================================
// WEBHOOK ROUTES (For external integrations)
// ============================================
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Submit testimonial via webhook
    Route::post('/testimonials', [TestimonialController::class, 'webhookStore'])->name('testimonials.store');
    
    // Moderation webhook
    Route::post('/testimonials/moderate', [TestimonialController::class, 'webhookModerate'])->name('testimonials.moderate');
});



// ==================== CONSTRUCTION CONTRACT ROUTES ====================

// Landlord routes for construction contracts
Route::middleware(['auth', 'multi.auth.user:2'])->prefix('landlord')->name('landlord.')->group(function () {
    
    // Construction Contracts
    Route::prefix('construction/contracts')->name('construction.contract.')->group(function () {
        Route::get('/', [LandlordConstructionContractController::class, 'index'])
            ->name('index');
        
        Route::get('/create', [LandlordConstructionContractController::class, 'create'])
            ->name('create');
        
        Route::post('/', [LandlordConstructionContractController::class, 'store'])
            ->name('store');
        
        Route::get('/{contract}', [LandlordConstructionContractController::class, 'show'])
            ->name('show');
        
        Route::get('/{contract}/edit', [LandlordConstructionContractController::class, 'edit'])
            ->name('edit');
        
        Route::put('/{contract}', [LandlordConstructionContractController::class, 'update'])
            ->name('update');
    });
});

// Admin routes for managing construction contracts
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::prefix('construction/contracts')->name('construction.contracts.')->group(function () {
        // Main CRUD
        Route::get('/', [AdminConstructionContractController::class, 'index'])
            ->name('index');
        
        Route::get('/{contract}', [AdminConstructionContractController::class, 'show'])
            ->name('show');
        
        // Status Management
        Route::patch('/{contract}/approve', [AdminConstructionContractController::class, 'approve'])
            ->name('approve');
        
        Route::patch('/{contract}/reject', [AdminConstructionContractController::class, 'reject'])
            ->name('reject');
        
        Route::patch('/{contract}/start', [AdminConstructionContractController::class, 'startWork'])
            ->name('start');
        
        Route::patch('/{contract}/pause', [AdminConstructionContractController::class, 'pauseWork'])
            ->name('pause');
        
        Route::patch('/{contract}/resume', [AdminConstructionContractController::class, 'resumeWork'])
            ->name('resume');
        
        Route::patch('/{contract}/complete', [AdminConstructionContractController::class, 'complete'])
            ->name('complete');
        
    });
});

// ============================================
// API ROUTES FOR CONSTRUCTION CONTRACTS
// ============================================
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    
    Route::prefix('construction/contracts')->name('construction.contracts.')->group(function () {
        
        // Admin APIs
        Route::middleware(['multi.auth.user:0,1'])->group(function () {
            Route::get('/status-counts', [AdminConstructionContractController::class, 'getStatusCounts'])
                ->name('status-counts');
        });
        
        // Landlord APIs
        Route::middleware(['multi.auth.user:2'])->group(function () {
            // Add any landlord-specific API endpoints here
        });
    });
});


// ============================================ //
// CONTRACTOR ROUTES                            //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:8'])->group(function () {
    
    // ============================================ //
    // DASHBOARD - Using dedicated ContractorController //
    // ============================================ //
    Route::get('/contractor/dashboard', [ContractorController::class, 'dashboard'])
        ->name('contractor.dashboard');
    
    // ============================================ //
    // PROFILE MANAGEMENT (via ProfileController)  //
    // ============================================ //
    Route::prefix('/contractor/profile')->name('contractor.profile.')->group(function () {
        
        // View Profile
        Route::get('/edit', [ProfileController::class, 'edit'])
            ->name('edit');
        
        // Personal Information
        Route::put('/personal', [ProfileController::class, 'updatePersonalInfo'])
            ->name('personal.update');
        
        // Contact Information
        Route::put('/contact', [ProfileController::class, 'updateDeveloperContact'])
            ->name('contact.update');
        
        // Password Update
        Route::put('/password', [ProfileController::class, 'updateDeveloperPassword'])
            ->name('password.update');
        
        // Profile Photo Management
        Route::post('/photo', [ProfileController::class, 'updateProfilePhoto'])
            ->name('photo.update');
        Route::post('/photo/remove', [ProfileController::class, 'removeProfilePhoto'])
            ->name('photo.remove');
        
        // Phone Verification
        Route::post('/phone/verify/send', [ProfileController::class, 'sendPhoneVerification'])
            ->name('phone.verify.send');
        Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])
            ->name('phone.verify');
        
        // Email Verification
        Route::post('/email/verify/send', [ProfileController::class, 'sendEmailVerification'])
            ->name('email.verify.send');
        Route::get('/email/verify/{token}', [ProfileController::class, 'verifyEmail'])
            ->name('email.verify');
        
        // Preferences (Contractor-specific)
        Route::put('/preferences', [ProfileController::class, 'updateDeveloperSettings'])
            ->name('preferences.update');
        
        // Contractor Credentials
        Route::put('/credentials', [ProfileController::class, 'updateDeveloperSettings'])
            ->name('credentials.update');
        
        // Statistics & Data
        Route::get('/stats', [ProfileController::class, 'getDeveloperStats'])
            ->name('stats');
        Route::get('/download-data', [ProfileController::class, 'downloadDeveloperData'])
            ->name('download-data');
    });
    
    // ============================================ //
    // CONTRACT MANAGEMENT ROUTES                  //
    // ============================================ //
    
    // Contract Listing & Details
    Route::get('/contractor/contracts', [ConstructionContractController::class, 'index'])
        ->name('contractor.contracts.index');
    Route::get('/contractor/contracts/{contract}', [ConstructionContractController::class, 'show'])
        ->name('contractor.contracts.show');
    
    // Progress Updates
    Route::get('/contractor/contracts/{contract}/progress', [ConstructionContractController::class, 'showProgressForm'])
        ->name('contractor.contracts.progress');
    Route::post('/contractor/contracts/{contract}/progress', [ConstructionContractController::class, 'updateProgress'])
        ->name('contractor.contracts.update-progress');
    
    // Milestone Management
    Route::get('/contractor/contracts/{contract}/milestones/{milestone}', [ConstructionContractController::class, 'showMilestoneForm'])
        ->name('contractor.milestones.submit-form');
    Route::post('/contractor/contracts/{contract}/milestones/{milestone}', [ConstructionContractController::class, 'submitMilestone'])
        ->name('contractor.milestones.submit');
    
    // ============================================ //
    // API ROUTES (AJAX Endpoints)                 //
    // ============================================ //
    
    // Status counts for dashboard widgets
    Route::get('/contractor/api/status-counts', [ContractorController::class, 'getStatusCounts'])
        ->name('contractor.api.status-counts');
    
    // Performance metrics for dashboard widgets
    Route::get('/contractor/api/performance-metrics', [ContractorController::class, 'getPerformanceMetrics'])
        ->name('contractor.api.performance-metrics');
    
    // Upcoming milestones for dashboard widgets
    Route::get('/contractor/api/upcoming-milestones', [ContractorController::class, 'getUpcomingMilestones'])
        ->name('contractor.api.upcoming-milestones');
    
    // Recent activity for dashboard widgets
    Route::get('/contractor/api/recent-activity', [ContractorController::class, 'getRecentActivityAjax'])
        ->name('contractor.api.recent-activity');
    
    // Chart data for dashboard widgets
    Route::get('/contractor/api/chart-data', [ContractorController::class, 'getChartDataAjax'])
        ->name('contractor.api.chart-data');
    
    // Quick stats for dashboard widgets
    Route::get('/contractor/api/quick-stats', [ContractorController::class, 'getQuickStats'])
        ->name('contractor.api.quick-stats');
    
    // Milestone statistics for specific contract
    Route::get('/contractor/api/contracts/{contract}/milestone-stats', [ConstructionContractController::class, 'getMilestoneStats'])
        ->name('contractor.api.milestone-stats');
    
    // ============================================ //
    // ADDITIONAL CONTRACTOR FEATURES              //
    // ============================================ //
    
    // Active Projects
    Route::get('/contractor/projects/active', [ConstructionContractController::class, 'activeProjects'])
        ->name('contractor.projects.active');
    
    // Completed Projects
    Route::get('/contractor/projects/completed', [ConstructionContractController::class, 'completedProjects'])
        ->name('contractor.projects.completed');
    
    // Project Calendar
    Route::get('/contractor/calendar', [CalendarController::class, 'index'])
        ->name('contractor.calendar');
    
    // Reports
    Route::get('/contractor/reports', [ReportController::class, 'index'])
        ->name('contractor.reports');
    
    // Export Reports (CSV)
    Route::get('/contractor/reports/export-csv', [ReportController::class, 'exportCsv'])
        ->name('contractor.reports.export-csv');
    
});

// ============================================ //
// WORKER MANAGEMENT ROUTES (Global)           //
// ============================================ //

// Global workers list (shows workers from all contracts)
Route::get('/contractor/workers', [ConstructionWorkerController::class, 'globalIndex'])
    ->name('contractor.workers.global');

// ✅ Toggle site assignment - Place this BEFORE the nested group
Route::post('/contractor/contracts/{contractId}/workers/{workerId}/toggle-site', 
    [ConstructionWorkerController::class, 'toggleSiteAssignment'])
    ->name('contractor.workers.toggle-site');

// Workers by contract (nested route)
Route::prefix('/contractor/contracts/{contract}/workers')
    ->name('contractor.workers.')
    ->group(function () {
        Route::get('/', [ConstructionWorkerController::class, 'index'])
            ->name('index');
        Route::get('/create', [ConstructionWorkerController::class, 'create'])
            ->name('create');
        Route::post('/', [ConstructionWorkerController::class, 'store'])
            ->name('store');
        Route::get('/{worker}', [ConstructionWorkerController::class, 'show'])
            ->name('show');
        Route::get('/{worker}/edit', [ConstructionWorkerController::class, 'edit'])
            ->name('edit');
        Route::put('/{worker}', [ConstructionWorkerController::class, 'update'])
            ->name('update');
        Route::delete('/{worker}', [ConstructionWorkerController::class, 'destroy'])
            ->name('destroy');
        Route::post('/{worker}/activate', [ConstructionWorkerController::class, 'activate'])
            ->name('activate');
        Route::post('/{worker}/terminate', [ConstructionWorkerController::class, 'terminate'])
            ->name('terminate');
        Route::post('/{worker}/complete', [ConstructionWorkerController::class, 'complete'])
            ->name('complete');
        Route::get('/stats', [ConstructionWorkerController::class, 'getStats'])
            ->name('stats');
        Route::get('/by-trade/{trade}', [ConstructionWorkerController::class, 'getByTrade'])
            ->name('by-trade');
        Route::post('/bulk-assign', [ConstructionWorkerController::class, 'bulkAssign'])
            ->name('bulk-assign');
    });

// Admin routes with multi.auth.user middleware
Route::middleware(['auth', 'multi.auth.user:0,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
        // ============================================================
        // WORKER MANAGEMENT ROUTES
        // ============================================================
        Route::prefix('workers')
            ->name('workers.')
            ->group(function () {
                
                // Main listing with filters
                Route::get('/', [AdminWorkerController::class, 'index'])
                    ->name('index');
                
                // View single worker details (AJAX)
                Route::get('/{id}', [AdminWorkerController::class, 'show'])
                    ->name('show');
                
                // Get workers by contractor
                Route::get('/by-contractor/{contractorId}', [AdminWorkerController::class, 'getByContractor'])
                    ->name('by-contractor');
                
                // Get site report (who's on site today)
                Route::get('/site-report', [AdminWorkerController::class, 'getTodaySiteReport'])
                    ->name('site-report');
                
                // Export workers data to CSV
                Route::get('/export', [AdminWorkerController::class, 'export'])
                    ->name('export');
                
                // Revoke worker badge (admin action)
                Route::post('/{id}/revoke-badge', [AdminWorkerController::class, 'revokeBadge'])
                    ->name('revoke-badge');
            });
    });


    // Routes for contractor
Route::prefix('contractor')->middleware(['auth', 'verified'])->group(function () {
    Route::prefix('contracts/{contract}/workers')->group(function () {
        Route::post('/{worker}/send-badge', [ConstructionWorkerController::class, 'sendBadge'])
            ->name('contractor.workers.send-badge');
        Route::post('/{worker}/resend-badge', [ConstructionWorkerController::class, 'resendBadge'])
            ->name('contractor.workers.resend-badge');
        Route::get('/{worker}/badge-status', [ConstructionWorkerController::class, 'getBadgeStatus'])
            ->name('contractor.workers.badge-status');
        Route::post('/bulk-send-badges', [ConstructionWorkerController::class, 'bulkSendBadges'])
            ->name('contractor.workers.bulk-send-badges');
    });
});

// Public routes for badge verification
Route::get('/verify-badge/{code}', [BadgeVerificationController::class, 'verify'])
    ->name('public.verify-badge');
Route::post('/api/verify-badge', [BadgeVerificationController::class, 'verifyBadge'])
    ->name('api.verify-badge');
Route::get('/api/badge-info/{code}', [BadgeVerificationController::class, 'getBadgeInfo'])
    ->name('api.badge-info');



// ============================================ //
// BASE ROUTES - All authenticated users        //
// ============================================ //
Route::middleware(['auth'])->prefix('email-accounts')->group(function () {

    // ========================================== //
    // 📧 STATIC ROUTES - MUST COME FIRST         //
    // ========================================== //
    Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
        ->name('email-accounts.inbox');
    Route::get('/sent', [UserEmailAccountController::class, 'sent'])
        ->name('email-accounts.sent');
    Route::get('/compose', [UserEmailAccountController::class, 'compose'])
        ->name('email-accounts.compose');
    Route::get('/create', [UserEmailAccountController::class, 'create'])
        ->name('email-accounts.create');

    // ========================================== //
    // 📨 POST ROUTES (No Parameters)             //
    // ========================================== //
    Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
        ->name('email-accounts.send');
    Route::post('/verify', [UserEmailAccountController::class, 'verify'])
        ->name('email-accounts.verify');

    // ========================================== //
    // 📋 INDEX & STORE (No Parameters)           //
    // ========================================== //
    Route::get('/', [UserEmailAccountController::class, 'index'])
        ->name('email-accounts.index');
    Route::post('/', [UserEmailAccountController::class, 'store'])
        ->name('email-accounts.store');

    // ========================================== //
    // 🔧 PARAMETERIZED ROUTES                    //
    // ⚠️ ORDER MATTERS: DELETE BEFORE GET        //
    // ========================================== //

    // ✅ DELETE - Must come before GET /{emailAccount}
    Route::delete('/{emailAccount}', [UserEmailAccountController::class, 'destroy'])
        ->name('email-accounts.destroy')
        ->where('emailAccount', '[0-9]+');

    // ✅ GET Show - Comes after DELETE
    Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
        ->name('email-accounts.show')
        ->where('emailAccount', '[0-9]+');

    // ✅ Edit
    Route::get('/{emailAccount}/edit', [UserEmailAccountController::class, 'edit'])
        ->name('email-accounts.edit')
        ->where('emailAccount', '[0-9]+');

    // ✅ Update
    Route::put('/{emailAccount}', [UserEmailAccountController::class, 'update'])
        ->name('email-accounts.update')
        ->where('emailAccount', '[0-9]+');

    // ========================================== //
    // ⚡ ACCOUNT ACTIONS                         //
    // ========================================== //
    Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
        ->name('email-accounts.sync')
        ->where('emailAccount', '[0-9]+');

    Route::post('/{emailAccount}/reverify', [UserEmailAccountController::class, 'reverify'])
        ->name('email-accounts.reverify')
        ->where('emailAccount', '[0-9]+');

    Route::post('/{emailAccount}/set-primary', [UserEmailAccountController::class, 'setPrimary'])
        ->name('email-accounts.set-primary')
        ->where('emailAccount', '[0-9]+');

    Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
        ->name('email-accounts.stats')
        ->where('emailAccount', '[0-9]+');

    // ========================================== //
    // 📨 EMAIL OPERATIONS                        //
    // ========================================== //
    Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
        ->name('email-accounts.view-email')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
        ->name('email-accounts.mark-read')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    // ✅ NEW: Mark as unread
    Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
        ->name('email-accounts.mark-unread')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
        ->name('email-accounts.reply')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
        ->name('email-accounts.forward')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    Route::delete('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'deleteEmail'])
        ->name('email-accounts.delete-email')
        ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

    // ========================================== //
    // 🗑️ SOFT DELETE OPERATIONS                  //
    // ========================================== //
    Route::post('/{id}/restore', [UserEmailAccountController::class, 'restore'])
        ->name('email-accounts.restore')
        ->where('id', '[0-9]+');

    Route::delete('/{id}/force-delete', [UserEmailAccountController::class, 'forceDelete'])
        ->name('email-accounts.force-delete')
        ->where('id', '[0-9]+');
});

// ============================================ //
// 🚀 SUPER ADMIN ROUTES                        //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:0,1'])->prefix('super-admin')->group(function () {

    Route::prefix('email-accounts')->group(function () {

        // ========================================== //
        // 📧 STATIC ROUTES                           //
        // ========================================== //
        Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
            ->name('super-admin.email-accounts.inbox');
        Route::get('/sent', [UserEmailAccountController::class, 'sent'])
            ->name('super-admin.email-accounts.sent');
        Route::get('/compose', [UserEmailAccountController::class, 'compose'])
            ->name('super-admin.email-accounts.compose');
        Route::get('/create', [UserEmailAccountController::class, 'create'])
            ->name('super-admin.email-accounts.create');

        // ========================================== //
        // 📨 POST ROUTES                             //
        // ========================================== //
        Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
            ->name('super-admin.email-accounts.send');
        Route::post('/verify', [UserEmailAccountController::class, 'verify'])
            ->name('super-admin.email-accounts.verify');

        // ========================================== //
        // 📋 INDEX & STORE                           //
        // ========================================== //
        Route::get('/', [UserEmailAccountController::class, 'index'])
            ->name('super-admin.email-accounts.index');
        Route::post('/', [UserEmailAccountController::class, 'store'])
            ->name('super-admin.email-accounts.store');

        // ========================================== //
        // ✅ CRUD ROUTES - DELETE MUST COME FIRST   //
        // ========================================== //
        Route::delete('/{emailAccount}', [UserEmailAccountController::class, 'destroy'])
            ->name('super-admin.email-accounts.destroy')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
            ->name('super-admin.email-accounts.show')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/edit', [UserEmailAccountController::class, 'edit'])
            ->name('super-admin.email-accounts.edit')
            ->where('emailAccount', '[0-9]+');

        Route::put('/{emailAccount}', [UserEmailAccountController::class, 'update'])
            ->name('super-admin.email-accounts.update')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // ⚡ ACCOUNT ACTIONS                         //
        // ========================================== //
        Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
            ->name('super-admin.email-accounts.sync')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/reverify', [UserEmailAccountController::class, 'reverify'])
            ->name('super-admin.email-accounts.reverify')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/set-primary', [UserEmailAccountController::class, 'setPrimary'])
            ->name('super-admin.email-accounts.set-primary')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
            ->name('super-admin.email-accounts.stats')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // 📨 EMAIL OPERATIONS                        //
        // ========================================== //
        Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
            ->name('super-admin.email-accounts.view-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
            ->name('super-admin.email-accounts.mark-read')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ✅ NEW: Mark as unread
        Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
            ->name('super-admin.email-accounts.mark-unread')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
            ->name('super-admin.email-accounts.reply')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
            ->name('super-admin.email-accounts.forward')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::delete('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'deleteEmail'])
            ->name('super-admin.email-accounts.delete-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ========================================== //
        // 🗑️ SOFT DELETE OPERATIONS                  //
        // ========================================== //
        Route::post('/{id}/restore', [UserEmailAccountController::class, 'restore'])
            ->name('super-admin.email-accounts.restore')
            ->where('id', '[0-9]+');

        Route::delete('/{id}/force-delete', [UserEmailAccountController::class, 'forceDelete'])
            ->name('super-admin.email-accounts.force-delete')
            ->where('id', '[0-9]+');
    });
});

// ============================================ //
// 👨‍💼 ADMIN ROUTES - Shows all email accounts  //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:1'])->prefix('admin')->group(function () {

    Route::prefix('email-accounts')->group(function () {

        // ========================================== //
        // 📧 STATIC ROUTES                           //
        // ========================================== //
        Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
            ->name('admin.email-accounts.inbox');
        Route::get('/sent', [UserEmailAccountController::class, 'sent'])
            ->name('admin.email-accounts.sent');
        Route::get('/compose', [UserEmailAccountController::class, 'compose'])
            ->name('admin.email-accounts.compose');

        // ❌ Admin CANNOT create/link email accounts

        // ========================================== //
        // 📨 POST ROUTES                             //
        // ========================================== //
        Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
            ->name('admin.email-accounts.send');

        // ❌ Admin CANNOT verify credentials

        // ========================================== //
        // 📋 INDEX - Shows ALL email accounts        //
        // ========================================== //
        Route::get('/', [UserEmailAccountController::class, 'index'])
            ->name('admin.email-accounts.index');

        // ❌ Admin CANNOT store email accounts

        // ========================================== //
        // ✅ CRUD ROUTES - View Only for Admin       //
        // ========================================== //
        Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
            ->name('admin.email-accounts.show')
            ->where('emailAccount', '[0-9]+');

        // ❌ Admin CANNOT edit, update, or delete accounts

        // ========================================== //
        // ⚡ ACCOUNT ACTIONS                         //
        // ========================================== //
        Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
            ->name('admin.email-accounts.sync')
            ->where('emailAccount', '[0-9]+');

        // ❌ Admin CANNOT reverify or set primary

        Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
            ->name('admin.email-accounts.stats')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // 📨 EMAIL OPERATIONS                        //
        // ========================================== //
        Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
            ->name('admin.email-accounts.view-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
            ->name('admin.email-accounts.mark-read')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ❌ Admin does NOT get a mark-unread route (kept consistent with their restricted feature set).
        // If you want Admins to be able to mark emails unread, uncomment the following block:
        //
        // Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
        //     ->name('admin.email-accounts.mark-unread')
        //     ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
            ->name('admin.email-accounts.reply')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
            ->name('admin.email-accounts.forward')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ❌ Admin CANNOT delete emails
    });
});

// ============================================ //
// 💻 DEVELOPER ROUTES                          //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:5'])
    ->prefix('developer')
    ->name('developer.')
    ->group(function () {

    Route::prefix('email-accounts')
        ->name('email-accounts.')
        ->group(function () {

        // ========================================== //
        // 📧 STATIC ROUTES                           //
        // ========================================== //
        Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
            ->name('inbox');

        Route::get('/sent', [UserEmailAccountController::class, 'sent'])
            ->name('sent');

        Route::get('/compose', [UserEmailAccountController::class, 'compose'])
            ->name('compose');

        Route::get('/create', [UserEmailAccountController::class, 'create'])
            ->name('create');

        // ========================================== //
        // 📨 POST ROUTES (Form Submissions)          //
        // ========================================== //
        Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
            ->name('send');

        Route::post('/verify', [UserEmailAccountController::class, 'verify'])
            ->name('verify');

        // ========================================== //
        // 📋 INDEX & STORE                           //
        // ========================================== //
        Route::get('/', [UserEmailAccountController::class, 'index'])
            ->name('index');

        Route::post('/', [UserEmailAccountController::class, 'store'])
            ->name('store');

        // ========================================== //
        // 🔧 PARAMETERIZED ROUTES                    //
        // ⚠️ ORDER MATTERS: DELETE BEFORE GET        //
        // ========================================== //
        Route::delete('/{emailAccount}', [UserEmailAccountController::class, 'destroy'])
            ->name('destroy')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
            ->name('show')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/edit', [UserEmailAccountController::class, 'edit'])
            ->name('edit')
            ->where('emailAccount', '[0-9]+');

        Route::put('/{emailAccount}', [UserEmailAccountController::class, 'update'])
            ->name('update')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // ⚡ ACCOUNT ACTIONS                         //
        // ========================================== //
        Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
            ->name('sync')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/reverify', [UserEmailAccountController::class, 'reverify'])
            ->name('reverify')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/set-primary', [UserEmailAccountController::class, 'setPrimary'])
            ->name('set-primary')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
            ->name('stats')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // 📨 EMAIL OPERATIONS                        //
        // ========================================== //
        Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
            ->name('view-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
            ->name('mark-read')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ✅ NEW: Mark as unread
        Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
            ->name('mark-unread')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
            ->name('reply')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
            ->name('forward')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::delete('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'deleteEmail'])
            ->name('delete-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ========================================== //
        // 🗑️ SOFT DELETE OPERATIONS                  //
        // ========================================== //
        Route::post('/{id}/restore', [UserEmailAccountController::class, 'restore'])
            ->name('restore')
            ->where('id', '[0-9]+');

        Route::delete('/{id}/force-delete', [UserEmailAccountController::class, 'forceDelete'])
            ->name('force-delete')
            ->where('id', '[0-9]+');
    });
});

// ============================================ //
// 💻 LANDLORD ROUTES                           //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:2'])
    ->prefix('landlord')
    ->name('landlord.')
    ->group(function () {

    Route::prefix('email-accounts')
        ->name('email-accounts.')
        ->group(function () {

        // ========================================== //
        // 📧 STATIC ROUTES                           //
        // ========================================== //
        Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
            ->name('inbox');

        Route::get('/sent', [UserEmailAccountController::class, 'sent'])
            ->name('sent');

        Route::get('/compose', [UserEmailAccountController::class, 'compose'])
            ->name('compose');

        Route::get('/create', [UserEmailAccountController::class, 'create'])
            ->name('create');

        // ========================================== //
        // 📨 POST ROUTES (Form Submissions)          //
        // ========================================== //
        Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
            ->name('send');

        Route::post('/verify', [UserEmailAccountController::class, 'verify'])
            ->name('verify');

        // ========================================== //
        // 📋 INDEX & STORE                           //
        // ========================================== //
        Route::get('/', [UserEmailAccountController::class, 'index'])
            ->name('index');

        Route::post('/', [UserEmailAccountController::class, 'store'])
            ->name('store');

        // ========================================== //
        // 🔧 PARAMETERIZED ROUTES                    //
        // ⚠️ ORDER MATTERS: DELETE BEFORE GET        //
        // ========================================== //
        Route::delete('/{emailAccount}', [UserEmailAccountController::class, 'destroy'])
            ->name('destroy')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
            ->name('show')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/edit', [UserEmailAccountController::class, 'edit'])
            ->name('edit')
            ->where('emailAccount', '[0-9]+');

        Route::put('/{emailAccount}', [UserEmailAccountController::class, 'update'])
            ->name('update')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // ⚡ ACCOUNT ACTIONS                         //
        // ========================================== //
        Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
            ->name('sync')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/reverify', [UserEmailAccountController::class, 'reverify'])
            ->name('reverify')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/set-primary', [UserEmailAccountController::class, 'setPrimary'])
            ->name('set-primary')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
            ->name('stats')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // 📨 EMAIL OPERATIONS                        //
        // ========================================== //
        Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
            ->name('view-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
            ->name('mark-read')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ✅ NEW: Mark as unread
        Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
            ->name('mark-unread')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
            ->name('reply')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
            ->name('forward')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::delete('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'deleteEmail'])
            ->name('delete-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ========================================== //
        // 🗑️ SOFT DELETE OPERATIONS                  //
        // ========================================== //
        Route::post('/{id}/restore', [UserEmailAccountController::class, 'restore'])
            ->name('restore')
            ->where('id', '[0-9]+');

        Route::delete('/{id}/force-delete', [UserEmailAccountController::class, 'forceDelete'])
            ->name('force-delete')
            ->where('id', '[0-9]+');
    });
});

// ============================================ //
// 💻 TENANT ROUTES                             //
// ============================================ //
Route::middleware(['auth', 'multi.auth.user:3'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {

    Route::prefix('email-accounts')
        ->name('email-accounts.')
        ->group(function () {

        // ========================================== //
        // 📧 STATIC ROUTES                           //
        // ========================================== //
        Route::get('/inbox', [UserEmailAccountController::class, 'inbox'])
            ->name('inbox');

        Route::get('/sent', [UserEmailAccountController::class, 'sent'])
            ->name('sent');

        Route::get('/compose', [UserEmailAccountController::class, 'compose'])
            ->name('compose');

        Route::get('/create', [UserEmailAccountController::class, 'create'])
            ->name('create');

        // ========================================== //
        // 📨 POST ROUTES (Form Submissions)          //
        // ========================================== //
        Route::post('/send', [UserEmailAccountController::class, 'sendEmail'])
            ->name('send');

        Route::post('/verify', [UserEmailAccountController::class, 'verify'])
            ->name('verify');

        // ========================================== //
        // 📋 INDEX & STORE                           //
        // ========================================== //
        Route::get('/', [UserEmailAccountController::class, 'index'])
            ->name('index');

        Route::post('/', [UserEmailAccountController::class, 'store'])
            ->name('store');

        // ========================================== //
        // 🔧 PARAMETERIZED ROUTES                    //
        // ⚠️ ORDER MATTERS: DELETE BEFORE GET        //
        // ========================================== //
        Route::delete('/{emailAccount}', [UserEmailAccountController::class, 'destroy'])
            ->name('destroy')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}', [UserEmailAccountController::class, 'show'])
            ->name('show')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/edit', [UserEmailAccountController::class, 'edit'])
            ->name('edit')
            ->where('emailAccount', '[0-9]+');

        Route::put('/{emailAccount}', [UserEmailAccountController::class, 'update'])
            ->name('update')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // ⚡ ACCOUNT ACTIONS                         //
        // ========================================== //
        Route::post('/{emailAccount}/sync', [UserEmailAccountController::class, 'sync'])
            ->name('sync')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/reverify', [UserEmailAccountController::class, 'reverify'])
            ->name('reverify')
            ->where('emailAccount', '[0-9]+');

        Route::post('/{emailAccount}/set-primary', [UserEmailAccountController::class, 'setPrimary'])
            ->name('set-primary')
            ->where('emailAccount', '[0-9]+');

        Route::get('/{emailAccount}/stats', [UserEmailAccountController::class, 'stats'])
            ->name('stats')
            ->where('emailAccount', '[0-9]+');

        // ========================================== //
        // 📨 EMAIL OPERATIONS                        //
        // ========================================== //
        Route::get('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'viewEmail'])
            ->name('view-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/mark-read', [UserEmailAccountController::class, 'markAsRead'])
            ->name('mark-read')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ✅ NEW: Mark as unread
        Route::post('/{emailAccount}/emails/{email}/mark-unread', [UserEmailAccountController::class, 'markAsUnread'])
            ->name('mark-unread')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/reply', [UserEmailAccountController::class, 'reply'])
            ->name('reply')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::post('/{emailAccount}/emails/{email}/forward', [UserEmailAccountController::class, 'forward'])
            ->name('forward')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        Route::delete('/{emailAccount}/emails/{email}', [UserEmailAccountController::class, 'deleteEmail'])
            ->name('delete-email')
            ->where(['emailAccount' => '[0-9]+', 'email' => '[0-9]+']);

        // ========================================== //
        // 🗑️ SOFT DELETE OPERATIONS                  //
        // ========================================== //
        Route::post('/{id}/restore', [UserEmailAccountController::class, 'restore'])
            ->name('restore')
            ->where('id', '[0-9]+');

        Route::delete('/{id}/force-delete', [UserEmailAccountController::class, 'forceDelete'])
            ->name('force-delete')
            ->where('id', '[0-9]+');
    });
});


// ============================================ //
// PUBLIC BADGE VERIFICATION ROUTES            //
// ============================================ //

Route::prefix('/verify')
    ->name('public.verify.')
    ->group(function () {
        // Public page for badge verification
        Route::get('/badge/{code}', [BadgeVerificationController::class, 'verify'])
            ->name('badge');
        
        // API endpoints for QR code scanning
        Route::post('/api/verify', [BadgeVerificationController::class, 'verifyBadge'])
            ->name('api');
        
        // Get badge info without verification (preview)
        Route::get('/api/info/{code}', [BadgeVerificationController::class, 'getBadgeInfo'])
            ->name('info');
        
        // Bulk verification (for security posts)
        Route::post('/api/bulk-verify', [BadgeVerificationController::class, 'bulkVerifyBadges'])
            ->name('bulk-verify');
    });


/*
|--------------------------------------------------------------------------
| SMS Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    // ========================================== //
    // 📱 SMS Management - Main Routes           //
    // ========================================== //
    Route::prefix('sms')->name('sms.')->group(function () {
        
        // Dashboard
        Route::get('/', [SmsController::class, 'index'])->name('index');
        
        // ========================================== //
        // 📨 COMPOSE & SEND - STATIC ROUTES FIRST   //
        // ========================================== //
        Route::get('/compose', [SmsController::class, 'compose'])->name('compose');
        Route::post('/send', [SmsController::class, 'send'])->name('send');
        
        // ========================================== //
        // 👥 USER LIST FOR SMS COMPOSER              //
        // ========================================== //
        Route::get('/users/list', [SmsController::class, 'getUserListForSms'])
            ->name('users.list');
        
        // ========================================== //
        // 👥 BULK SMS OPERATIONS                     //
        // ========================================== //
        Route::post('/bulk-send', [SmsController::class, 'bulkSend'])
            ->name('bulk-send');
        
        // ========================================== //
        // 📋 LOGS MANAGEMENT                        //
        // ========================================== //
        Route::get('/logs', [SmsController::class, 'logs'])->name('logs');
        Route::get('/logs/{log}', [SmsController::class, 'showLog'])->name('logs.show');
        Route::delete('/logs/{log}', [SmsController::class, 'deleteLog'])->name('logs.delete');
        Route::post('/logs/bulk-delete', [SmsController::class, 'bulkDeleteLogs'])->name('logs.bulk-delete');
        Route::get('/logs/export', [SmsController::class, 'exportLogs'])->name('logs.export');
        Route::post('/logs/cleanup', [SmsController::class, 'cleanup'])->name('logs.cleanup');
        
        // ========================================== //
        // 📊 STATISTICS                              //
        // ========================================== //
        Route::get('/statistics', [SmsController::class, 'statistics'])->name('statistics');
    });

    // ========================================== //
    // 🔌 API Routes for SMS (AJAX calls)        //
    // ========================================== //
    Route::prefix('api/sms')->name('api.sms.')->group(function () {
        Route::get('/status', [SmsController::class, 'status'])->name('status');
        Route::post('/status/refresh', [SmsController::class, 'refreshStatus'])->name('status.refresh');
        Route::post('/test', [SmsController::class, 'testConnection'])->name('test');
        Route::get('/statistics', [SmsController::class, 'statistics'])->name('statistics');
        
        // 👥 User list API endpoint (alternative to /sms/users/list)
        Route::get('/users', [SmsController::class, 'getUserListForSms'])
            ->name('users');
    });  
});

// =============================================
// ONLINE PAYMENT ROUTES
// =============================================

// Pay invoice page
Route::get('/pay-invoice/{invoiceId}', [DeveloperPaymentController::class, 'payInvoice'])
    ->name('payments.pay-invoice');

// Initialize payment
Route::post('/pay-invoice/{invoiceId}', [DeveloperPaymentController::class, 'initializeInvoicePayment'])
    ->name('payments.initialize-invoice-payment');

// Get available providers (AJAX)
Route::get('/payment-providers', [DeveloperPaymentController::class, 'getPaymentProviders'])
    ->name('payments.get-providers');

// =============================================
// WEBHOOK ROUTES (Public - No Auth)
// =============================================

Route::post('/webhook/paystack', [DeveloperPaymentController::class, 'handlePaystackWebhook'])
    ->name('payments.paystack-webhook');

Route::post('/webhook/flutterwave', [DeveloperPaymentController::class, 'handleFlutterwaveWebhook'])
    ->name('payments.flutterwave-webhook');

Route::post('/webhook/expresspay', [DeveloperPaymentController::class, 'handleExpressPayWebhook'])
    ->name('payments.expresspay-webhook');

Route::post('/webhook/hubtel', [DeveloperPaymentController::class, 'handleHubtelWebhook'])
    ->name('payments.hubtel-webhook');


// ============================================
// WHATSAPP WEB ROUTES - Developer
// ============================================

Route::prefix('developer')->name('developer.')->middleware(['auth', 'multi.auth.user:5'])->group(function () {
    
    // WhatsApp Management Routes
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        
        // ============================================
        // 💬 MESSAGE MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/messages', [WhatsAppMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/compose', [WhatsAppMessageController::class, 'compose'])->name('messages.compose');  // ✅ This is whatsapp.messages.compose
        Route::get('/messages/{id}', [WhatsAppMessageController::class, 'show'])->name('messages.show');
        
        // Web Actions
        Route::post('/messages/send', [WhatsAppMessageController::class, 'send'])->name('messages.send');
        Route::post('/messages/send-test', [WhatsAppMessageController::class, 'sendTest'])->name('messages.send-test');
        Route::delete('/messages/{id}', [WhatsAppMessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('/messages/{id}/resend', [WhatsAppMessageController::class, 'resend'])->name('messages.resend');
        
        // ============================================
        // 📝 TEMPLATE MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/templates', [WhatsAppTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [WhatsAppTemplateController::class, 'create'])->name('templates.create');
        Route::get('/templates/{id}', [WhatsAppTemplateController::class, 'show'])->name('templates.show');
        Route::get('/templates/{id}/edit', [WhatsAppTemplateController::class, 'edit'])->name('templates.edit');
        
        // Web Actions
        Route::post('/templates', [WhatsAppTemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{id}', [WhatsAppTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{id}', [WhatsAppTemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('/templates/{id}/sync', [WhatsAppTemplateController::class, 'sync'])->name('templates.sync');
        
        // ============================================
        // 📋 LOGS MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/logs', [WhatsAppLogController::class, 'index'])->name('logs.index');  // ✅ This is whatsapp.logs.index
        Route::get('/logs/{id}', [WhatsAppLogController::class, 'show'])->name('logs.show');
        
        // Web Actions
        Route::delete('/logs/clear', [WhatsAppLogController::class, 'clear'])->name('logs.clear');
        Route::get('/logs/export', [WhatsAppLogController::class, 'export'])->name('logs.export');
    });
});

// ============================================
// WHATSAPP WEB ROUTES - Admin
// ============================================

Route::prefix('admin')->name('admin.')->middleware(['auth', 'multi.auth.user:0,1'])->group(function () {
    
    // WhatsApp Management Routes
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        
        // ============================================
        // 💬 MESSAGE MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/messages', [WhatsAppMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/compose', [WhatsAppMessageController::class, 'compose'])->name('messages.compose');
        Route::get('/messages/{id}', [WhatsAppMessageController::class, 'show'])->name('messages.show');
        
        // Web Actions
        Route::post('/messages/send', [WhatsAppMessageController::class, 'send'])->name('messages.send');
        Route::post('/messages/send-test', [WhatsAppMessageController::class, 'sendTest'])->name('messages.send-test');
        Route::delete('/messages/{id}', [WhatsAppMessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('/messages/{id}/resend', [WhatsAppMessageController::class, 'resend'])->name('messages.resend');
        
        // ============================================
        // 📝 TEMPLATE MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/templates', [WhatsAppTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [WhatsAppTemplateController::class, 'create'])->name('templates.create');
        Route::get('/templates/{id}', [WhatsAppTemplateController::class, 'show'])->name('templates.show');
        Route::get('/templates/{id}/edit', [WhatsAppTemplateController::class, 'edit'])->name('templates.edit');
        
        // Web Actions
        Route::post('/templates', [WhatsAppTemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{id}', [WhatsAppTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{id}', [WhatsAppTemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('/templates/{id}/sync', [WhatsAppTemplateController::class, 'sync'])->name('templates.sync');
        
        // ============================================
        // 📋 LOGS MANAGEMENT
        // ============================================
        // Web Views
        Route::get('/logs', [WhatsAppLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{id}', [WhatsAppLogController::class, 'show'])->name('logs.show');
        
        // Web Actions
        Route::delete('/logs/clear', [WhatsAppLogController::class, 'clear'])->name('logs.clear');
        Route::get('/logs/export', [WhatsAppLogController::class, 'export'])->name('logs.export');
    });
});

// ============================================
// WHATSAPP WEBHOOK ROUTES (public - no auth)
// ============================================

Route::prefix('webhook')->name('webhook.')->group(function () {
    
    // WhatsApp Webhooks
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        
        // Provider webhooks
        Route::post('/twilio', [WhatsAppWebhookController::class, 'handleTwilio'])->name('twilio');
        Route::post('/vonage', [WhatsAppWebhookController::class, 'handleVonage'])->name('vonage');
        Route::post('/360dialog', [WhatsAppWebhookController::class, 'handle360Dialog'])->name('360dialog');
        Route::post('/wati', [WhatsAppWebhookController::class, 'handleWati'])->name('wati');
        Route::post('/custom', [WhatsAppWebhookController::class, 'handleCustom'])->name('custom');
        
        // Generic webhook handler
        Route::post('/{provider}', [WhatsAppWebhookController::class, 'handle'])->name('handle');
        
        // Webhook verification
        Route::get('/verify', [WhatsAppWebhookController::class, 'verify'])->name('verify');
        Route::get('/twilio/verify', [WhatsAppWebhookController::class, 'verifyTwilio'])->name('twilio.verify');
    });
});



// ==================== SANITATION PERSONNEL ROUTES ====================

Route::middleware(['auth', 'multi.auth.user:' . User::TYPE_SANITATION_PERSONNEL])
    ->prefix('sanitation')
    ->name('sanitation.')
    ->group(function () {

        // ============================================ //
        // 📊 DASHBOARD                                 //
        // ============================================ //
        Route::get('/dashboard', [SanitationController::class, 'dashboard'])->name('dashboard');

        // ============================================ //
        // 📊 STATISTICS & REPORTS                      //
        // ============================================ //
        Route::get('/statistics', [SanitationController::class, 'statistics'])->name('statistics');
        Route::get('/reports', [SanitationController::class, 'reports'])->name('reports');

        // ============================================ //
        // 📋 PROPERTY MANAGEMENT                       //
        // ============================================ //
        Route::prefix('properties')->name('properties.')->group(function () {

            // Static / specific routes MUST come before /{property}
            // Available properties (not yet linked)
            Route::get('/available', [SanitationController::class, 'availableProperties'])
                ->name('available');

            // Linked properties
            Route::get('/linked', [SanitationController::class, 'linkedProperties'])
                ->name('linked');

            // Link property form
            Route::get('/link/{property}', [SanitationController::class, 'linkPropertyForm'])
                ->name('link');

            // Store property link (POST) - with approval workflow
            Route::post('/link/{property}', [SanitationController::class, 'linkProperty'])
                ->name('link.store');

            // ✅ NEW: Bulk link selected properties from the available list
            Route::post('/bulk-link', [SanitationController::class, 'bulkLinkProperties'])
                ->name('bulk-link');

            // ✅ NEW: Bulk approve + link landlord-submitted service requests
            Route::post('/bulk-approve', [SanitationController::class, 'bulkApproveLandlordRequests'])
                ->name('bulk-approve');

            // Show property details (catch-all — must be last)
            Route::get('/{property}', [SanitationController::class, 'showProperty'])
                ->name('show');

            // Unlink property
            Route::delete('/{property}/unlink', [SanitationController::class, 'unlinkProperty'])
                ->name('unlink');

            // Update collection settings
            Route::put('/{property}/settings', [SanitationController::class, 'updateCollectionSettings'])
                ->name('settings');
        });

        // ============================================ //
        // ✅ APPROVALS                                 //
        // ============================================ //
        Route::prefix('approvals')->name('approvals.')->group(function () {

            // Pending approvals list
            Route::get('/pending', [SanitationController::class, 'pendingApprovals'])
                ->name('pending');

            // Landlord approval interface (accessed via unique token)
            Route::get('/landlord/{token}', [SanitationController::class, 'landlordApproval'])
                ->name('landlord');

            // Handle landlord approval response
            Route::post('/handle/{collectionRequest}', [SanitationController::class, 'handleLandlordApproval'])
                ->name('handle');

            // Thank you page after approval response
            Route::get('/thankyou/{collectionRequest}', [SanitationController::class, 'approvalThankYou'])
                ->name('thankyou');

            // Resend approval request to landlord
            Route::post('/resend/{collectionRequest}', [SanitationController::class, 'resendApproval'])
                ->name('resend');

            // Bulk approve pending approvals (Admin only)
            Route::post('/bulk-approve', [SanitationController::class, 'bulkApprove'])
                ->name('bulk');
        });

        // ============================================ //
        // 📋 REQUESTS                                  //
        // ============================================ //
        Route::prefix('requests')->name('requests.')->group(function () {

            // List all requests
            Route::get('/', [SanitationController::class, 'listRequests'])
                ->name('index');

            // Pending requests (approved by landlord, waiting for assignment)
            Route::get('/pending', [SanitationController::class, 'pendingRequests'])
                ->name('pending');

            // Assign request to personnel
            Route::post('/{collectionRequest}/assign', [SanitationController::class, 'assignRequest'])
                ->name('assign');

            // Update request status (en_route, arrived, in_progress, completed, cancelled)
            Route::post('/{collectionRequest}/status', [SanitationController::class, 'updateStatus'])
                ->name('status');

            // ✅ Show single request — catch-all MUST be last in this group
            Route::get('/{collectionRequest}', [SanitationController::class, 'showRequest'])
                ->name('show');
        });

        // ============================================ //
        // 🗺️ MAP                                       //
        // ============================================ //
        Route::get('/map/markers', [SanitationController::class, 'getMapMarkers'])
            ->name('map.markers');
    });
    

// ============================================ //
// 🧹 SANITATION PERSONNEL ROUTES               //
// (Matches blade naming convention)            //
// ============================================ //

Route::prefix('sanitation')->name('sanitation.')->group(function () {

    // ============================================ //
    // 👥 PERSONNEL & WORKERS                        //
    // ============================================ //

    Route::prefix('personnel')->name('personnel.')->group(function () {

        // -----------------------------------------------------------------
        // Personnel Management
        // -----------------------------------------------------------------
        Route::get('/', [PersonnelController::class, 'index'])
            ->name('index');

        Route::get('/create', [PersonnelController::class, 'create'])
            ->name('create');

        Route::post('/', [PersonnelController::class, 'store'])
            ->name('store');

        // -----------------------------------------------------------------
        // 🗑️ TRASH & BULK ENDPOINTS
        //
        // ⚠️ These MUST come before the `/{personnel}` wildcard routes
        //    below, otherwise `/personnel/trash` would be routed to
        //    `show($personnel = 'trash')` and 404.
        // -----------------------------------------------------------------
        Route::get('/trash', [PersonnelController::class, 'trash'])
            ->name('trash');

        Route::post('/bulk-restore', [PersonnelController::class, 'bulkRestore'])
            ->name('bulk-restore');

        Route::post('/bulk-force-delete', [PersonnelController::class, 'bulkForceDelete'])
            ->name('bulk-force-delete');

        Route::post('/{id}/restore', [PersonnelController::class, 'restore'])
            ->whereNumber('id')
            ->name('restore');

        Route::delete('/{id}/force-delete', [PersonnelController::class, 'forceDelete'])
            ->whereNumber('id')
            ->name('force-delete');

        // -----------------------------------------------------------------
        // AJAX — static endpoints (must also precede the wildcard)
        // -----------------------------------------------------------------
        Route::get('/available', [PersonnelController::class, 'getAvailable'])
            ->name('available');

        // -----------------------------------------------------------------
        // Wildcard personnel routes
        // -----------------------------------------------------------------
        Route::get('/{personnel}', [PersonnelController::class, 'show'])
            ->name('show');

        Route::get('/{personnel}/edit', [PersonnelController::class, 'edit'])
            ->name('edit');

        Route::put('/{personnel}', [PersonnelController::class, 'update'])
            ->name('update');

        Route::delete('/{personnel}', [PersonnelController::class, 'destroy'])
            ->name('destroy');

        // -----------------------------------------------------------------
        // Worker Management (under personnel)
        // -----------------------------------------------------------------
        Route::post('/{personnel}/workers/assign', [PersonnelController::class, 'assignWorker'])
            ->name('workers.assign');

        Route::delete('/{personnel}/workers/{worker}/unassign', [PersonnelController::class, 'unassignWorker'])
            ->name('workers.unassign');

        Route::get('/{personnel}/workers', [PersonnelController::class, 'getWorkers'])
            ->name('workers.index');

        // -----------------------------------------------------------------
        // Location Tracking
        // -----------------------------------------------------------------
        Route::post('/{personnel}/location', [PersonnelController::class, 'updateLocation'])
            ->name('location.update');
    });

    
    // ============================================ //
    // 👷 WORKERS                                   //
    // ============================================ //
    
    Route::prefix('workers')->name('workers.')->group(function () {
        
        Route::get('/', [WorkerController::class, 'index'])
            ->name('index');
        
        Route::get('/create', [WorkerController::class, 'create'])
            ->name('create');
        
        Route::post('/', [WorkerController::class, 'store'])
            ->name('store');
        
        Route::get('/{worker}', [WorkerController::class, 'show'])
            ->name('show');
        
        Route::get('/{worker}/edit', [WorkerController::class, 'edit'])
            ->name('edit');
        
        Route::put('/{worker}', [WorkerController::class, 'update'])
            ->name('update');
        
        Route::delete('/{worker}', [WorkerController::class, 'destroy'])
            ->name('destroy');
        
        // Location Tracking for workers
        Route::post('/{worker}/location', [WorkerController::class, 'updateLocation'])
            ->name('location.update');
        
        // Available Workers (AJAX)
        Route::get('/available', [WorkerController::class, 'getAvailable'])
            ->name('available');
    });
});

// ============================================ //
// 👥 PERSONNEL & WORKERS (Original)            //
// ============================================ //

Route::prefix('personnel')->name('personnel.')->group(function () {
    
    // Personnel Management
    Route::get('/', [PersonnelController::class, 'index'])
        ->name('index');
    
    Route::get('/create', [PersonnelController::class, 'create'])
        ->name('create');
    
    Route::post('/', [PersonnelController::class, 'store'])
        ->name('store');
    
    Route::get('/{personnel}', [PersonnelController::class, 'show'])
        ->name('show');
    
    Route::get('/{personnel}/edit', [PersonnelController::class, 'edit'])
        ->name('edit');
    
    Route::put('/{personnel}', [PersonnelController::class, 'update'])
        ->name('update');
    
    Route::delete('/{personnel}', [PersonnelController::class, 'destroy'])
        ->name('destroy');
    
    // Worker Management (under personnel)
    Route::post('/{personnel}/workers/assign', [PersonnelController::class, 'assignWorker'])
        ->name('workers.assign');
    
    Route::delete('/{personnel}/workers/{worker}/unassign', [PersonnelController::class, 'unassignWorker'])
        ->name('workers.unassign');
    
    Route::get('/{personnel}/workers', [PersonnelController::class, 'getWorkers'])
        ->name('workers.index');
    
    // Location Tracking
    Route::post('/{personnel}/location', [PersonnelController::class, 'updateLocation'])
        ->name('location.update');
    
    // Available Personnel (AJAX)
    Route::get('/available', [PersonnelController::class, 'getAvailable'])
        ->name('available');
    
    // Export
    Route::get('/export', [PersonnelController::class, 'export'])
        ->name('export');
});

// ============================================ //
// 👷 WORKERS (Separate - if needed)            //
// ============================================ //

Route::prefix('workers')->name('workers.')->group(function () {
    
    Route::get('/', [WorkerController::class, 'index'])
        ->name('index');
    
    Route::get('/create', [WorkerController::class, 'create'])
        ->name('create');
    
    Route::post('/', [WorkerController::class, 'store'])
        ->name('store');
    
    Route::get('/{worker}', [WorkerController::class, 'show'])
        ->name('show');
    
    Route::get('/{worker}/edit', [WorkerController::class, 'edit'])
        ->name('edit');
    
    Route::put('/{worker}', [WorkerController::class, 'update'])
        ->name('update');
    
    Route::delete('/{worker}', [WorkerController::class, 'destroy'])
        ->name('destroy');
    
    // Location Tracking for workers
    Route::post('/{worker}/location', [WorkerController::class, 'updateLocation'])
        ->name('location.update');
    
    // Available Workers (AJAX)
    Route::get('/available', [WorkerController::class, 'getAvailable'])
        ->name('available');
});

// ============================================ //
// 📍 LOCATION (General)                        //
// ============================================ //

// This route is for the current user's location update (mobile app)
Route::post('/location', [PersonnelController::class, 'updateLocation'])
    ->name('location');

// ==================== COLLECTION ZONES ROUTES ====================

Route::middleware(['auth', 'multi.auth.user:' . User::TYPE_SANITATION_PERSONNEL])
    ->prefix('sanitation/zones')
    ->name('sanitation.zones.')
    ->group(function () {

        // -----------------------------------------------------------------
        // 1. STATIC routes first — these MUST come before /{zone}
        // -----------------------------------------------------------------
        Route::get('/',        [CollectionZoneController::class, 'index'])->name('index');
        Route::get('/create',  [CollectionZoneController::class, 'create'])->name('create');
        Route::post('/',       [CollectionZoneController::class, 'store'])->name('store');

        // AJAX endpoints (static segments)
        Route::get('/available-personnel', [CollectionZoneController::class, 'getAvailablePersonnel'])->name('available-personnel');
        Route::get('/dropdown',            [CollectionZoneController::class, 'getZonesForDropdown'])->name('dropdown');

        // Export (static)
        Route::get('/export', [CollectionZoneController::class, 'export'])->name('export');

        // -----------------------------------------------------------------
        // 2. DYNAMIC routes — after all static ones
        // -----------------------------------------------------------------
        Route::get('/{zone}',      [CollectionZoneController::class, 'show'])->name('show');
        Route::get('/{zone}/edit', [CollectionZoneController::class, 'edit'])->name('edit');
        Route::put('/{zone}',      [CollectionZoneController::class, 'update'])->name('update');
        Route::delete('/{zone}',   [CollectionZoneController::class, 'destroy'])->name('destroy');

        // Additional actions
        Route::post('/{zone}/toggle-active',      [CollectionZoneController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{zone}/assign-personnel',   [CollectionZoneController::class, 'assignPersonnel'])->name('assign-personnel');
        Route::post('/{zone}/unassign-personnel', [CollectionZoneController::class, 'unassignPersonnel'])->name('unassign-personnel');
    });


// ============================================ //
// 🏢 SANITATION SETTINGS ROUTES                //
// ============================================ //
// ✅ Only accessible to:
//    - Admins & Super Admins (bypass via middleware)
//    - The ROOT sanitation supervisor (top of the personnel tree)
//    Everyone else gets a 403 before hitting the controller.

Route::middleware([
        'auth',
        'multi.auth.user:' . User::TYPE_SANITATION_PERSONNEL,
        // ✅ FIX: use class reference, not alias, because MultiAuthUser
        //         resolves middleware via app() and doesn't consult aliases.
        \App\Http\Middleware\EnsureRootSanitationAccess::class,
    ])
    ->prefix('sanitation/settings')
    ->name('sanitation.settings.')
    ->group(function () {

        // Main settings page
        Route::get('/', [SanitationSettingController::class, 'index'])->name('index');

        // Edit settings form
        Route::get('/edit', [SanitationSettingController::class, 'edit'])->name('edit');

        // Store/Update settings
        Route::post('/', [SanitationSettingController::class, 'store'])->name('store');
        Route::put('/', [SanitationSettingController::class, 'update'])->name('update');

        // Toggle active status — both aliases for compatibility
        Route::post('/toggle-active', [SanitationSettingController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/toggle', [SanitationSettingController::class, 'toggleActive'])->name('toggle');

        // Remove logo
        Route::delete('/remove-logo', [SanitationSettingController::class, 'removeLogo'])->name('remove-logo');

        // Export / Import settings
        Route::get('/export', [SanitationSettingController::class, 'export'])->name('export');
        Route::post('/import', [SanitationSettingController::class, 'import'])->name('import');

        // ============================================================ //
        // ✅ NEW: Environment-file (.env) writer for Google Maps /     //
        //         Ghana Post GPS credentials.                          //
        //                                                              //
        //   POST /sanitation/settings/environment                      //
        //   → SanitationSettingController::updateEnvironment()         //
        //                                                              //
        // Writes whitelisted keys to `.env`, clears config + option    //
        // caches, and returns to the settings index.                   //
        // ============================================================ //
        Route::post('/environment', [SanitationSettingController::class, 'updateEnvironment'])
            ->name('environment.update');

        // API endpoint
        Route::get('/api', [SanitationSettingController::class, 'apiSettings'])->name('api');
    });


Route::middleware(['auth', 'multi.auth.user:' . User::TYPE_SANITATION_PERSONNEL])
    ->prefix('sanitation')
    ->name('sanitation.')
    ->group(function () {
        
        // ============================================ //
        // 📋 PROFILE ROUTES                           //
        // ============================================ //
        
        Route::prefix('profile')->name('profile.')->group(function () {
            
            // Main profile page
            Route::get('/', [ProfileController::class, 'edit'])
                ->name('edit');
            
            // Personal Information
            Route::put('/personal', [ProfileController::class, 'updateSanitationPersonal'])
                ->name('personal.update');
            
            // Contact Information
            Route::put('/contact', [ProfileController::class, 'updateSanitationContact'])
                ->name('contact.update');
            
            // Sanitation Settings
            Route::put('/settings', [ProfileController::class, 'updateSanitationSettings'])
                ->name('settings.update');
            
            // Availability
            Route::put('/availability', [ProfileController::class, 'updateAvailability'])
                ->name('availability.update');
            
            // Profile Photo
            Route::post('/photo', [ProfileController::class, 'updateProfilePhoto'])
                ->name('photo.update');
            
            Route::delete('/photo', [ProfileController::class, 'removeProfilePhoto'])
                ->name('photo.remove');
            
            // Password
            Route::put('/password', [ProfileController::class, 'updatePassword'])
                ->name('password.update');
            
            // Phone Verification
            Route::post('/phone/send', [ProfileController::class, 'sendPhoneVerification'])
                ->name('phone.verify.send');
            
            Route::post('/phone/verify', [ProfileController::class, 'verifyPhone'])
                ->name('phone.verify.confirm');
            
            // Email Verification
            Route::post('/email/send', [ProfileController::class, 'sendEmailVerification'])
                ->name('email.verify.send');
            
            Route::get('/email/verify/{token}', [ProfileController::class, 'verifyEmail'])
                ->name('email.verify');
            
            // Statistics
            Route::get('/stats', [ProfileController::class, 'getProfileStats'])
                ->name('stats');
            
            // Data Export (GDPR)
            Route::get('/download', [ProfileController::class, 'downloadPersonalData'])
                ->name('data.download');
            
            // Session Management
            Route::post('/session/revoke', [ProfileController::class, 'revokeSession'])
                ->name('session.revoke');
        });
    });


// ============================================
// THEME MANAGEMENT ROUTES - FULL IMPLEMENTATION
// ============================================

Route::middleware(['web', 'auth'])->prefix('theme')->name('theme.')->group(function () {
    
    // ============================================
    // 1. CORE THEME ROUTES
    // ============================================
    Route::prefix('settings')->name('settings.')->group(function () {
        // Get user's theme settings
        Route::get('/', [ThemeController::class, 'getSettings'])
            ->name('get')
            ->middleware(['throttle:60,1']);
        
        // Update theme settings
        Route::post('/update', [ThemeController::class, 'update'])
            ->name('update')
            ->middleware(['throttle:30,1']);
        
        // Reset theme to default
        Route::post('/reset', [ThemeController::class, 'reset'])
            ->name('reset')
            ->middleware(['throttle:10,1']);
    });

    // ============================================
    // 2. THEME OPTIONS ROUTES
    // ============================================
    Route::prefix('options')->name('options.')->group(function () {
        // Get all available options
        Route::get('/', [ThemeController::class, 'getOptions'])
            ->name('all')
            ->middleware(['throttle:120,1', 'cache.headers:public;max_age=3600;etag']);
        
        // Get sidebar theme previews
        Route::get('/sidebar-previews', function () {
            return response()->json([
                'success' => true,
                'data' => app(ThemeController::class)->getSidebarThemePreviews()
            ]);
        })->name('sidebar-previews')
        ->middleware(['throttle:120,1', 'cache.headers:public;max_age=3600;etag']);
    });

    // ============================================
    // 3. COLOR PICKER ROUTES
    // ============================================
    Route::prefix('colors')->name('colors.')->group(function () {
        // Get custom colors
        Route::get('/', [ThemeController::class, 'getColors'])
            ->name('get')
            ->middleware(['throttle:60,1', 'cache.headers:private;max_age=3600']);
        
        // Update custom colors
        Route::post('/update', [ThemeController::class, 'updateColors'])
            ->name('update')
            ->middleware(['throttle:30,1']);
        
        // Reset colors to default
        Route::post('/reset', [ThemeController::class, 'resetColors'])
            ->name('reset')
            ->middleware(['throttle:10,1']);
        
        // Get default colors (public)
        Route::get('/default', function () {
            return response()->json([
                'success' => true,
                'data' => ThemeController::getDefaultColors()
            ]);
        })->name('default')
        ->middleware(['throttle:120,1', 'cache.headers:public;max_age=86400;etag;immutable']);
    });

    // ============================================
    // 4. THEME APPLICATION ROUTES
    // ============================================
    Route::prefix('apply')->name('apply.')->group(function () {
        // Apply theme to current session
        Route::post('/', [ThemeController::class, 'applyTheme'])
            ->name('current')
            ->middleware(['throttle:30,1']);
        
        // Apply theme to specific user (admin only)
        Route::post('/user/{user}', [ThemeController::class, 'applyThemeToUser'])
            ->name('user')
            ->middleware(['can:admin', 'throttle:10,1']);
    });

    // ============================================
    // 5. CSS GENERATION ROUTES
    // ============================================
    Route::prefix('css')->name('css.')->group(function () {
        // Get theme CSS
        Route::get('/', [ThemeController::class, 'getThemeCss'])
            ->name('get')
            ->middleware(['throttle:120,1', 'cache.headers:public;max_age=3600;etag;immutable']);
        
        // Get CSS with custom parameters
        Route::get('/preview', function (Request $request) {
            $settings = $request->validate([
                'appearance' => 'sometimes|in:light,dark,system',
                'sidebar_theme' => 'sometimes|in:default,dark,light,blue,green,custom',
                'density' => 'sometimes|in:comfortable,compact,spacious'
            ]);
            
            $user = auth()->user();
            $service = app(\App\Contracts\Theme\ThemeServiceInterface::class);
            
            // Merge with current settings
            $currentSettings = $service->getSettings($user);
            $mergedSettings = array_merge($currentSettings['settings'], $settings);
            
            $colors = $service->getColors($user);
            $css = $service->generateThemeCss(
                \App\DTOs\Theme\ThemeSettingsDTO::fromArray($mergedSettings),
                \App\DTOs\Theme\ThemeColorsDTO::fromArray($colors['colors'])
            );
            
            return response($css, 200)
                ->header('Content-Type', 'text/css');
        })->name('preview')
        ->middleware(['throttle:60,1', 'cache.headers:public;max_age=300;must_revalidate']);
    });

    // ============================================
    // 6. PREVIEW ROUTES
    // ============================================
    Route::prefix('preview')->name('preview.')->group(function () {
        // Get sidebar theme preview
        Route::get('/sidebar/{theme}', [ThemePreviewController::class, 'sidebarPreview'])
            ->name('sidebar')
            ->where('theme', 'default|dark|light|blue|green|custom')
            ->middleware(['throttle:60,1', 'cache.headers:public;max_age=3600;etag']);
        
        // Get all sidebar previews
        Route::get('/sidebar/all', [ThemePreviewController::class, 'allSidebarPreviews'])
            ->name('sidebar-all')
            ->middleware(['throttle:60,1', 'cache.headers:public;max_age=3600;etag']);
        
        // Get color preview
        Route::get('/colors', [ThemePreviewController::class, 'colorPreview'])
            ->name('colors')
            ->middleware(['throttle:60,1', 'cache.headers:public;max_age=3600;etag']);
        
        // Get live preview with current theme
        Route::get('/live', [ThemePreviewController::class, 'livePreview'])
            ->name('live')
            ->middleware(['throttle:30,1', 'cache.headers:private;max_age=300;must_revalidate']);
    });

    // ============================================
    // 7. ADMIN ROUTES
    // ============================================
    Route::prefix('admin')->name('admin.')->middleware(['can:admin'])->group(function () {
        // System-wide theme settings
        Route::get('/system', [ThemeController::class, 'getSystemSettings'])
            ->name('system.get')
            ->middleware(['cache.headers:private;max_age=3600']);
        
        Route::post('/system', [ThemeController::class, 'updateSystemSettings'])
            ->name('system.update');
        
        Route::post('/system/reset', [ThemeController::class, 'resetSystemSettings'])
            ->name('system.reset');
        
        // User theme management
        Route::get('/users', [ThemeController::class, 'getUserThemes'])
            ->name('users.list')
            ->middleware(['cache.headers:private;max_age=3600']);
        
        Route::get('/user/{user}', [ThemeController::class, 'getUserTheme'])
            ->name('user.get')
            ->middleware(['cache.headers:private;max_age=3600']);
        
        Route::post('/user/{user}/reset', [ThemeController::class, 'resetUserTheme'])
            ->name('user.reset');
        
        // Theme statistics
        Route::get('/statistics', [ThemeController::class, 'getThemeStatistics'])
            ->name('statistics')
            ->middleware(['throttle:30,1', 'cache.headers:private;max_age=3600']);
    });

    // ============================================
    // 8. API COMPATIBILITY ROUTES
    // ============================================
    Route::prefix('api')->name('api.')->group(function () {
        // Backward compatibility for API routes
        Route::get('/settings', [ThemeController::class, 'getSettings'])
            ->name('settings')
            ->middleware(['throttle:60,1', 'cache.headers:private;max_age=3600']);
        
        Route::post('/settings/update', [ThemeController::class, 'update'])
            ->name('settings.update')
            ->middleware(['throttle:30,1']);
        
        Route::post('/settings/reset', [ThemeController::class, 'reset'])
            ->name('settings.reset')
            ->middleware(['throttle:10,1']);
        
        Route::get('/colors', [ThemeController::class, 'getColors'])
            ->name('colors')
            ->middleware(['throttle:60,1', 'cache.headers:private;max_age=3600']);
        
        Route::post('/colors/update', [ThemeController::class, 'updateColors'])
            ->name('colors.update')
            ->middleware(['throttle:30,1']);
        
        Route::post('/colors/reset', [ThemeController::class, 'resetColors'])
            ->name('colors.reset')
            ->middleware(['throttle:10,1']);
        
        Route::get('/css', [ThemeController::class, 'getThemeCss'])
            ->name('css')
            ->middleware(['throttle:120,1', 'cache.headers:public;max_age=3600;etag;immutable']);
    });
});

// ============================================
// 9. PUBLIC ROUTES (No Authentication Required)
// ============================================
Route::prefix('theme')->name('theme.')->group(function () {
    // Public CSS (with caching)
    Route::get('/public/css', function () {
        $css = Cache::remember('theme_public_css', 3600, function () {
            $service = app(\App\Contracts\Theme\ThemeServiceInterface::class);
            $settings = \App\DTOs\Theme\ThemeSettingsDTO::fromArray([
                'appearance' => 'system',
                'sidebar_theme' => 'default',
                'font_size' => 'medium',
                'layout' => 'comfortable',
                'animations' => 'enabled',
                'sidebar_position' => 'left',
                'header_style' => 'default',
                'density' => 'comfortable'
            ]);
            $colors = \App\DTOs\Theme\ThemeColorsDTO::default();
            
            return $service->generateThemeCss($settings, $colors);
        });
        
        return response($css, 200)
            ->header('Content-Type', 'text/css')
            ->header('Cache-Control', 'public, max-age=86400, immutable');
    })->name('public.css')
    ->middleware(['throttle:120,1']);
    
    // Public theme options
    Route::get('/public/options', function () {
        $options = Cache::remember('theme_public_options', 86400, function () {
            return [
                'appearance' => ['light', 'dark', 'system'],
                'sidebar_themes' => ['default', 'dark', 'light', 'blue', 'green', 'custom'],
                'font_sizes' => ['small', 'medium', 'large'],
                'layout' => ['compact', 'comfortable'],
                'animations' => ['enabled', 'disabled'],
                'sidebar_position' => ['left', 'right'],
                'header_style' => ['default', 'glass', 'solid'],
                'density' => ['comfortable', 'compact', 'spacious']
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $options
        ])->header('Cache-Control', 'public, max-age=86400, immutable');
    })->name('public.options')
    ->middleware(['throttle:120,1']);
});

// ============================================
// 10. WEBHOOKS AND CALLBACKS
// ============================================
Route::prefix('theme/webhooks')->name('theme.webhooks.')->group(function () {
    // Theme changed webhook
    Route::post('/theme-changed', function (Request $request) {
        // Handle theme change webhook
        // This can be used by external services
        return response()->json(['success' => true]);
    })->name('theme-changed');
    
    // Colors changed webhook
    Route::post('/colors-changed', function (Request $request) {
        // Handle colors change webhook
        return response()->json(['success' => true]);
    })->name('colors-changed');
});

// Add this route for developers
Route::get('/developer/users/list-email', [App\Http\Controllers\Admin\UserManagementController::class, 'getUserListForEmail'])
    ->name('developer.users.list-email')
    ->middleware('auth');



// ============================================ //
// ♻️ LANDLORD WASTE COLLECTION ROUTES          //
// ============================================ //

Route::middleware(['auth', 'verified'])
    ->prefix('landlord')
    ->name('landlord.')
    ->group(function () {

        // Waste Collection Routes (Landlord View)
        Route::prefix('waste')
            ->name('waste.')
            ->group(function () {

                // --------------------------------------------------------- //
                // 📋 LISTING                                               //
                // --------------------------------------------------------- //

                // Pending Approvals — landlord approves/rejects waste collection requests
                Route::get('/approvals', [WasteCollectionController::class, 'pendingApprovals'])
                    ->name('approvals');

                // All collection requests for landlord's properties
                Route::get('/requests', [WasteCollectionController::class, 'myRequests'])
                    ->name('requests');

                // Collection History (completed only)
                Route::get('/history', [WasteCollectionController::class, 'history'])
                    ->name('history');

                // --------------------------------------------------------- //
                // 🔍 SINGLE REQUEST                                        //
                // --------------------------------------------------------- //

                // View a specific request
                // ✅ Constrained to numeric IDs so it never collides with other routes
                Route::get('/request/{wasteCollectionRequest}', [WasteCollectionController::class, 'showRequest'])
                    ->whereNumber('wasteCollectionRequest')
                    ->name('request.show');

                // --------------------------------------------------------- //
                // ✅ APPROVE / ❌ REJECT                                   //
                // --------------------------------------------------------- //

                // Approve a request (POST) — throttled to prevent spam
                Route::post('/request/{wasteCollectionRequest}/approve', [WasteCollectionController::class, 'approveRequest'])
                    ->whereNumber('wasteCollectionRequest')
                    ->middleware('throttle:30,1')     // 30 per minute
                    ->name('request.approve');

                // Reject a request (POST) — throttled
                Route::post('/request/{wasteCollectionRequest}/reject', [WasteCollectionController::class, 'rejectRequest'])
                    ->whereNumber('wasteCollectionRequest')
                    ->middleware('throttle:30,1')
                    ->name('request.reject');

                // --------------------------------------------------------- //
                // ⚡ BULK ACTIONS                                          //
                // --------------------------------------------------------- //

                // Bulk Approve — throttled harder since it touches many records
                Route::post('/bulk-approve', [WasteCollectionController::class, 'bulkApprove'])
                    ->middleware('throttle:10,1')     // 10 per minute
                    ->name('bulk.approve');

                // (Optional future) Bulk Reject
                // Route::post('/bulk-reject', [WasteCollectionController::class, 'bulkReject'])
                //     ->middleware('throttle:10,1')
                //     ->name('bulk.reject');

                // --------------------------------------------------------- //
                // 🗑️ BIN FULL                                              //
                // --------------------------------------------------------- //

                Route::post('/bin-full', [WasteCollectionController::class, 'reportBinFull'])
                    ->middleware('throttle:5,1')      // 5 per minute
                    ->name('bin.full');

                // --------------------------------------------------------- //
                // 🆕 REQUEST SANITATION SERVICE                            //
                // --------------------------------------------------------- //
                // Landlord asks a sanitation supervisor (created by admin)
                // to link one of their unlinked properties for collection.

                // Submit a service request — throttled to stop dropdown spam
                Route::post('/request-service', [WasteCollectionController::class, 'requestSanitationService'])
                    ->middleware('throttle:10,1')     // 10 per minute
                    ->name('request.service');

                // Cancel a pending service request for a specific property
                Route::post('/properties/{property}/cancel-service-request', [WasteCollectionController::class, 'cancelSanitationServiceRequest'])
                    ->whereNumber('property')
                    ->middleware('throttle:20,1')     // 20 per minute
                    ->name('cancel.service');
            });
    });

// Sanitation Personnel
Route::middleware(['auth', 'web', 'multi.auth.user:9'])->prefix('sanitation')->name('sanitation.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])
        ->name('notifications.show');
    
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')
        ->where('id', '[0-9a-f-]+');
    
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')
        ->where('id', '[0-9a-f-]+');
});

// Contractor
Route::middleware(['auth', 'web', 'multi.auth.user:8'])->prefix('contractor')->name('contractor.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])
        ->name('notifications.show');
    
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read')
        ->where('id', '[0-9a-f-]+');
    
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')
        ->where('id', '[0-9a-f-]+');
});

/*
|--------------------------------------------------------------------------
| PUBLIC STORAGE FILE SERVING (no auth)
|--------------------------------------------------------------------------
|
| Serves files from `storage/app/public/*` through Laravel so the CORS
| middleware in `config/cors.php` applies. This is required for Flutter
| Web clients, which load images via the browser — and the browser
| enforces CORS on every cross-origin request, including images.
|
| Why /files instead of /storage:
|   Laravel's `FilesystemServiceProvider` reserves `/storage/{path}` for
|   its internal signed-temporary-URL mechanism. Registering our own
|   `GET /storage/{path}` route collides with the framework's route and
|   the framework's version wins (it returns 403 for unsigned requests).
|   Using `/files/{path}` avoids the collision entirely.
|
| Why this route is at the TOP of routes/web.php:
|   All other routes in this file sit inside an auth-protected group.
|   Browsers loading <img src="..."> don't send auth headers, so the
|   route MUST be outside any auth middleware or it will 302-redirect
|   to /login before it can serve the file.
|
| ⚠️  IMPORTANT — DO NOT run `php artisan storage:link` in this project.
|     The `public/storage` symlink (or Windows junction) shadows this
|     route. When it exists, the web server serves the file directly,
|     bypassing CORS middleware, and Flutter Web images break with a
|     CORS error. Keep the symlink removed.
|
| The corresponding client-side constant is `AppConstants.storageBaseUrl`
| in `lib/utils/constants.dart`, which must be:
|     'http://127.0.0.1:8000/files'      (dev)
|     'https://your-domain.com/files'    (prod)
|
*/

Route::get('/files/{path}', function (string $path) {
    // Path traversal guard — reject anything with `..` in it.
    if (str_contains($path, '..')) {
        abort(400);
    }

    $disk = Storage::disk('public');

    // 404 if the file doesn't exist on disk.
    if (!$disk->exists($path)) {
        abort(404);
    }

    // Stream the file with the correct MIME type. Laravel's
    // `HandleCors` middleware (see config/cors.php) will add the
    // `Access-Control-Allow-Origin` header automatically because the
    // path matches the `files/*` entry in that config.
    return response()->file(
        $disk->path($path),
        [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ],
    );
})
    ->where('path', '.*')          // allow slashes in the path (nested folders)
    ->name('files.serve');         // named for route('files.serve', ...) usage



Route::middleware('auth')->get('/{any}', function () {
    return redirect()->route('dashboard');
})->where('any', '.*');
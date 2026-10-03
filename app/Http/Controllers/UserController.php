<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SystemSetting;
use App\Models\UserInvitation;
use App\Models\PropertyUnit;
use App\Models\Property;
use App\Models\MaintenanceRequest;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Services\EnvironmentConfigService;
use App\Services\MultiChannelInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    protected $smsService;
    protected $emailService;
    protected $whatsappService;
    protected $environmentService;
    protected $multiChannelService;

    public function __construct(
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService,
        EnvironmentConfigService $environmentService,
        MultiChannelInvitationService $multiChannelService
    ) {
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
        $this->environmentService = $environmentService;
        $this->multiChannelService = $multiChannelService;
    }

    // ==================== CORE USER FUNCTIONALITY ====================

    /**
     * Display user dashboard based on user type
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        // Check if user needs to complete invitation
        if ($user->needsPasswordSetup()) {
            $invitation = $user->pendingInvitation;
            if ($invitation) {
                return redirect()->route('user.invitations.accept', ['token' => $invitation->token])
                    ->with('info', 'Please complete your account setup to access the dashboard.')
                    ->with('setup_required', true);
            }
        }

        // Get comprehensive communication service status
        $systemSettings = SystemSetting::getSettings();
        $communicationStatus = $this->getCommunicationServiceStatus();

        // Get user-specific dashboard data
        $dashboardData = $this->getUserDashboardData($user);

        // Determine dashboard view based on user type
        $view = $this->getDashboardViewByType($user->type);

        return view($view, compact(
            'user',
            'dashboardData',
            'communicationStatus',
            'systemSettings'
        ));
    }

    /**
     * Get comprehensive communication service status
     */
    private function getCommunicationServiceStatus(): array
    {
        $systemSettings = SystemSetting::getSettings();
        
        return [
            'sms' => $this->smsService->getSystemStatus(),
            'email' => $this->getEnhancedEmailStatus($systemSettings),
            'whatsapp' => $this->getEnhancedWhatsAppStatus($systemSettings),
            'multi_channel' => $this->multiChannelService->getSystemStatus(),
            'overall_health' => $this->calculateOverallCommunicationHealth($systemSettings),
        ];
    }

    /**
     * Calculate overall communication health
     */
    private function calculateOverallCommunicationHealth($systemSettings): string
    {
        try {
            $emailStatus = $this->getEnhancedEmailStatus($systemSettings);
            $whatsappStatus = $this->getEnhancedWhatsAppStatus($systemSettings);
            $smsStatus = $this->smsService->getSystemStatus();

            $healthyServices = 0;
            $totalServices = 0;

            // Check email service with safe array access
            if (($emailStatus['can_send_emails'] ?? false)) {
                $healthyServices++;
            }
            $totalServices++;

            // Check WhatsApp service with safe array access
            if (($whatsappStatus['can_send_messages'] ?? false)) {
                $healthyServices++;
            }
            $totalServices++;

            // Check SMS service with safe array access
            if (($smsStatus['enabled'] ?? false)) {
                $healthyServices++;
            }
            $totalServices++;

            if ($healthyServices === $totalServices) return 'excellent';
            if ($healthyServices >= 2) return 'good';
            if ($healthyServices >= 1) return 'fair';
            return 'poor';

        } catch (\Exception $e) {
            Log::error('Error calculating communication health: ' . $e->getMessage(), [
                'error' => $e->getTraceAsString()
            ]);
            return 'fair';
        }
    }

    /**
     * Get user-specific dashboard data
     */
    private function getUserDashboardData(User $user): array
    {
        $data = [
            'welcome_message' => $this->getWelcomeMessage($user),
            'quick_actions' => $this->getQuickActions($user),
            'recent_activity' => $this->getRecentActivity($user),
            'stats' => $this->getDashboardStats($user),
        ];

        // Add type-specific data
        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $data['property_stats'] = $this->getLandlordPropertyStats($user);
                $data['tenant_stats'] = $this->getLandlordTenantStats($user);
                break;
                
            case User::TYPE_FIELD_AGENT:
                $data['agent_stats'] = $this->getFieldAgentStats($user);
                break;
                
            case User::TYPE_TENANT:
                $data['tenant_stats'] = $this->getTenantStats($user);
                break;
                
            case User::TYPE_ADMIN:
            case User::TYPE_SUPER_ADMIN:
                $data['admin_stats'] = $this->getAdminStats($user);
                break;
        }

        return $data;
    }

    /**
     * Get personalized welcome message
     */
    private function getWelcomeMessage(User $user): string
    {
        $timeOfDay = $this->getTimeOfDay();
        $messages = [
            'morning' => "Good morning",
            'afternoon' => "Good afternoon", 
            'evening' => "Good evening"
        ];

        $greeting = $messages[$timeOfDay] ?? 'Welcome';
        
        if ($user->needsPasswordSetup()) {
            return "{$greeting}, {$user->name}! Please complete your account setup to get started.";
        }
        
        if ($user->status === User::STATUS_PENDING) {
            return "{$greeting}, {$user->name}! Your account is pending activation.";
        }
        
        return "{$greeting}, {$user->name}!";
    }

    /**
     * Get time of day for greeting
     */
    private function getTimeOfDay(): string
    {
        $hour = now()->hour;
        
        if ($hour < 12) return 'morning';
        if ($hour < 17) return 'afternoon';
        return 'evening';
    }

    /**
     * Get quick actions based on user type and status
     */
    private function getQuickActions(User $user): array
    {
        // If user needs to complete invitation, show only setup action
        if ($user->needsPasswordSetup()) {
            $invitation = $user->pendingInvitation;
            return [
                [
                    'title' => 'Complete Account Setup',
                    'description' => 'Set your password to activate your account and access all features',
                    'icon' => 'user-lock',
                    'url' => $invitation ? route('user.invitations.accept', ['token' => $invitation->token]) : '#',
                    'color' => 'primary',
                    'priority' => 1,
                    'badge' => 'Required'
                ]
            ];
        }

        $baseActions = [
            [
                'title' => 'Update Profile',
                'description' => 'Manage your personal information and preferences',
                'icon' => 'user',
                'url' => \Route::has('profile.edit') ? route('profile.edit') : '#',
                'color' => 'primary',
                'priority' => 2
            ]
        ];

        // Add resend invitation action for pending users
        if ($user->status === User::STATUS_PENDING && $user->canReceiveInvitation()) {
            $baseActions[] = [
                'title' => 'Resend Invitation',
                'description' => 'Send new invitation link to your registered email and phone',
                'icon' => 'envelope',
                'url' => '#',
                'color' => 'warning',
                'priority' => 1,
                'action' => 'resendInvitation',
                'requires_confirmation' => true,
                'confirmation_message' => 'This will send a new invitation link to your registered contact methods. Continue?'
            ];
        }

        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $baseActions[] = [
                    'title' => 'Add Property',
                    'description' => 'List a new property for rent or sale',
                    'icon' => 'home',
                    'url' => \Route::has('properties.create') ? route('properties.create') : '#',
                    'color' => 'success',
                    'priority' => 3
                ];
                $baseActions[] = [
                    'title' => 'My Properties',
                    'description' => 'View and manage your property portfolio',
                    'icon' => 'list',
                    'url' => \Route::has('properties.my-properties') ? route('properties.my-properties') : '#',
                    'color' => 'info',
                    'priority' => 4
                ];
                $baseActions[] = [
                    'title' => 'My Tenants',
                    'description' => 'Manage tenants in your properties',
                    'icon' => 'users',
                    'url' => \Route::has('landlord.tenants.index') ? route('landlord.tenants.index') : '#',
                    'color' => 'secondary',
                    'priority' => 5
                ];
                break;
                
            case User::TYPE_FIELD_AGENT:
                $baseActions[] = [
                    'title' => 'View Assignments',
                    'description' => 'Check your current property assignments and tasks',
                    'icon' => 'tasks',
                    'url' => \Route::has('agent.assignments') ? route('agent.assignments') : '#',
                    'color' => 'warning',
                    'priority' => 3
                ];
                $baseActions[] = [
                    'title' => 'Submit Report',
                    'description' => 'Submit field inspection or progress report',
                    'icon' => 'file-alt',
                    'url' => \Route::has('agent.reports.create') ? route('agent.reports.create') : '#',
                    'color' => 'success',
                    'priority' => 4
                ];
                break;
                
            case User::TYPE_TENANT:
                $baseActions[] = [
                    'title' => 'My Rentals',
                    'description' => 'View your rental agreements and history',
                    'icon' => 'file-contract',
                    'url' => \Route::has('tenant.rentals') ? route('tenant.rentals') : '#',
                    'color' => 'info',
                    'priority' => 3
                ];
                $baseActions[] = [
                    'title' => 'Make Payment',
                    'description' => 'Pay your rent, utilities, or other bills',
                    'icon' => 'credit-card',
                    'url' => \Route::has('tenant.payments.create') ? route('tenant.payments.create') : '#',
                    'color' => 'success',
                    'priority' => 4
                ];
                break;
                
            case User::TYPE_ADMIN:
                $baseActions[] = [
                    'title' => 'Manage Users',
                    'description' => 'View and manage system users and permissions',
                    'icon' => 'users',
                    'url' => \Route::has('admin.users.index') ? route('admin.users.index') : '#',
                    'color' => 'danger',
                    'priority' => 3
                ];
                // Regular admin doesn't get system settings
                break;
                
            case User::TYPE_SUPER_ADMIN:
                $baseActions[] = [
                    'title' => 'Manage Users',
                    'description' => 'View and manage system users and permissions',
                    'icon' => 'users',
                    'url' => \Route::has('admin.users.index') ? route('admin.users.index') : '#',
                    'color' => 'danger',
                    'priority' => 3
                ];
                $baseActions[] = [
                    'title' => 'System Settings',
                    'description' => 'Configure system preferences and communication settings',
                    'icon' => 'cog',
                    'url' => \Route::has('super-admin.settings') ? route('super-admin.settings') : '#',
                    'color' => 'secondary',
                    'priority' => 4
                ];
                break;
        }

        // Sort by priority
        usort($baseActions, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $baseActions;
    }

    /**
     * Get recent user activity
     */
    private function getRecentActivity(User $user): array
    {
        $activities = [];

        // Add invitation activity
        if ($user->invitations()->exists()) {
            $latestInvitation = $user->invitations()->latest()->first();
            $activities[] = [
                'type' => 'invitation_sent',
                'message' => 'Account invitation sent via ' . implode(', ', $latestInvitation->channels ?? ['email']),
                'timestamp' => $latestInvitation->sent_at,
                'icon' => 'envelope',
                'color' => 'info'
            ];
        }

        // Add login activity
        if ($user->last_login_at) {
            $activities[] = [
                'type' => 'login',
                'message' => 'Last successful login',
                'timestamp' => $user->last_login_at,
                'icon' => 'sign-in-alt',
                'color' => 'success'
            ];
        }

        // Add profile update activity
        $lastProfileUpdate = $user->metadata['last_profile_update'] ?? null;
        if ($lastProfileUpdate) {
            $activities[] = [
                'type' => 'profile_update',
                'message' => 'Profile information updated',
                'timestamp' => $lastProfileUpdate,
                'icon' => 'user-edit',
                'color' => 'primary'
            ];
        }

        // Add invitation acceptance activity
        if ($user->invitation_accepted_at) {
            $activities[] = [
                'type' => 'invitation_accepted',
                'message' => 'Account activated and setup completed',
                'timestamp' => $user->invitation_accepted_at,
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }

        // Add type-specific activities
        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $latestProperty = $user->properties()->latest()->first();
                if ($latestProperty) {
                    $activities[] = [
                        'type' => 'property_added',
                        'message' => 'Property added: ' . Str::limit($latestProperty->property_name, 30),
                        'timestamp' => $latestProperty->created_at,
                        'icon' => 'home',
                        'color' => 'success'
                    ];
                }
                break;
                
            case User::TYPE_FIELD_AGENT:
                $latestAssignment = $user->assignedPlans()->latest()->first();
                if ($latestAssignment) {
                    $activities[] = [
                        'type' => 'assignment_received',
                        'message' => 'New assignment received: ' . Str::limit($latestAssignment->title, 30),
                        'timestamp' => $latestAssignment->created_at,
                        'icon' => 'tasks',
                        'color' => 'info'
                    ];
                }
                break;
        }

        // Add communication-related activities
        $lastCommunication = $user->metadata['last_communication'] ?? null;
        if ($lastCommunication) {
            $activities[] = [
                'type' => 'communication_sent',
                'message' => 'Notification sent via ' . ($lastCommunication['channel'] ?? 'email'),
                'timestamp' => $lastCommunication['timestamp'] ?? now(),
                'icon' => 'comment',
                'color' => 'secondary'
            ];
        }

        // Sort by timestamp (newest first) and limit to 5
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, 5);
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(User $user): array
    {
        $stats = [
            'profile_completion' => $this->calculateProfileCompletion($user),
            'account_age_days' => $user->created_at->diffInDays(),
            'last_activity' => $user->last_activity_at?->diffForHumans() ?? 'Never',
            'communication_channels' => $this->getUserCommunicationChannels($user),
        ];

        // Add invitation status for pending users
        if ($user->status === User::STATUS_PENDING) {
            $stats['invitation_status'] = $user->latest_invitation_status;
            $stats['can_resend_invitation'] = $user->canReceiveInvitation();
            $stats['has_valid_invitation'] = $user->has_valid_invitation;
        }

        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $stats['total_properties'] = $user->properties()->count();
                $stats['active_properties'] = $user->properties()->where('status', 'active')->count();
                $stats['pending_properties'] = $user->properties()->where('status', 'pending')->count();
                break;
                
            case User::TYPE_FIELD_AGENT:
                $stats['total_assignments'] = $user->assignedPlans()->count();
                $stats['active_assignments'] = $user->assignedPlans()->where('status', 'active')->count();
                $stats['completed_assignments'] = $user->assignedPlans()->where('status', 'completed')->count();
                break;
                
            case User::TYPE_TENANT:
                $stats['active_rentals'] = $user->rentals()->where('status', 'active')->count();
                $stats['total_payments'] = $user->payments()->count();
                $stats['pending_payments'] = $user->payments()->where('status', 'pending')->count();
                break;
                
            case User::TYPE_ADMIN:
            case User::TYPE_SUPER_ADMIN:
                $stats['total_users'] = User::count();
                $stats['users_today'] = User::whereDate('created_at', today())->count();
                $stats['pending_invitations'] = User::withValidInvitations()->count();
                $stats['active_users'] = User::where('status', User::STATUS_ACTIVE)->count();
                break;
        }

        return $stats;
    }

    /**
     * Get user communication channels status
     */
    private function getUserCommunicationChannels(User $user): array
    {
        $channels = [];

        if (!empty($user->email)) {
            $channels['email'] = [
                'enabled' => true,
                'verified' => !is_null($user->email_verified_at),
                'value' => $user->email
            ];
        }

        if (!empty($user->phone)) {
            $channels['sms'] = [
                'enabled' => true,
                'verified' => !is_null($user->phone_verified_at),
                'value' => $user->phone
            ];
            
            $channels['whatsapp'] = [
                'enabled' => true,
                'verified' => !is_null($user->phone_verified_at),
                'value' => $user->phone
            ];
        }

        return $channels;
    }

    // ==================== INVITATION ACCEPTANCE METHODS ====================

    /**
     * Display invitation acceptance form
     */
    public function showInvitationAcceptForm($token)
    {
        try {
            $invitation = UserInvitation::where('token', $token)->firstOrFail();
            $user = $invitation->user;

            // Check if invitation is valid
            if (!$invitation->isValid()) {
                return view('auth.invitation-expired', [
                    'message' => 'This invitation link has expired or is no longer valid.',
                    'user' => $user,
                    'can_resend' => $user->canReceiveInvitation()
                ]);
            }

            // Check if user already completed setup
            if (!is_null($user->invitation_accepted_at)) {
                return redirect()->route('login')
                    ->with('info', 'Your account is already active. Please log in with your password.');
            }

            return view('auth.accept-invitation', compact('user', 'token', 'invitation'));

        } catch (\Exception $e) {
            Log::error('Error loading invitation form: ' . $e->getMessage(), [
                'token' => $token
            ]);

            return view('auth.invitation-expired', [
                'message' => 'Invalid invitation link. Please contact support if you believe this is an error.',
                'can_resend' => false
            ]);
        }
    }

    /**
     * Process invitation acceptance and password setup
     */
    public function processInvitationAcceptance(Request $request, $token)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'agree_terms' => 'required|accepted',
            'agree_privacy' => 'required|accepted',
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
            'agree_terms.accepted' => 'You must accept the terms and conditions to continue.',
            'agree_privacy.accepted' => 'You must accept the privacy policy to continue.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('validation_errors', true);
        }

        DB::beginTransaction();

        try {
            $invitation = UserInvitation::where('token', $token)->firstOrFail();
            $user = $invitation->user;

            // Validate invitation
            if (!$invitation->isValid()) {
                return redirect()->route('login')
                    ->with('error', 'This invitation link has expired. Please contact support for a new one.');
            }

            // Complete the invitation process
            $user->completeInvitation($request->password);

            // Log the user in automatically
            Auth::login($user);

            // Log the successful invitation acceptance
            Log::info('User completed invitation process successfully', [
                'user_id' => $user->id,
                'user_type' => $user->type,
                'user_email' => $user->email,
                'accepted_at' => now()->toDateTimeString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            // Redirect based on user type
            return $this->redirectAfterInvitationAcceptance($user)
                ->with('success', 'Welcome to ' . config('app.name') . '! Your account has been activated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error processing invitation acceptance: ' . $e->getMessage(), [
                'token' => $token,
                'error' => $e->getTraceAsString(),
                'ip_address' => $request->ip()
            ]);

            return redirect()->route('login')
                ->with('error', 'We encountered an error while processing your invitation. Please contact support for assistance.');
        }
    }

    /**
     * Redirect user after successful invitation acceptance
     */
    private function redirectAfterInvitationAcceptance(User $user)
    {
        $routeMap = [
            User::TYPE_SUPER_ADMIN => 'superadmin.dashboard',
            User::TYPE_ADMIN => 'admin.dashboard',
            User::TYPE_LANDLORD => 'landlord.dashboard',
            User::TYPE_TENANT => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
            User::TYPE_SECURITY_PERSONNEL => 'security-personnel.dashboard',
        ];

        $route = $routeMap[$user->type] ?? 'dashboard';

        // Add onboarding parameter for first-time users
        return redirect()->route($route, ['onboarding' => 'true']);
    }

    /**
     * Resend invitation for pending users
     */
    public function resendInvitation(Request $request)
    {
        $user = Auth::user();

        // Check if user can receive invitation
        if (!$user->canReceiveInvitation()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot receive invitations at this time. Your account may already be active or the invitation period has expired.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Create new invitation
            $invitation = $user->createInvitation();

            if ($invitation) {
                DB::commit();

                Log::info('Invitation resent successfully', [
                    'user_id' => $user->id,
                    'channels' => $invitation->channels,
                    'invitation_id' => $invitation->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Invitation resent successfully! Check your registered contact methods for the new link.',
                    'invitation_url' => $invitation->getInvitationUrl(),
                    'channels_used' => $invitation->channels
                ]);
            } else {
                DB::rollBack();

                Log::error('Failed to create invitation', [
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create invitation. Please contact support.',
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error resending invitation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resending the invitation. Please try again or contact support.'
            ], 500);
        }
    }

    // ==================== SERVICE STATUS METHODS ====================

    /**
     * Get enhanced email status with system settings integration
     */
    protected function getEnhancedEmailStatus($systemSettings): array
    {
        $basicStatus = $this->emailService->getSystemStatus();
        $envConfig = $this->environmentService->getMailConfiguration();
        
        $envMatchesSystem = ($envConfig['username'] ?? '') === ($systemSettings->system_email ?? '');
        
        return array_merge($basicStatus, [
            'system_email_configured' => !empty($systemSettings->system_email),
            'env_matches_system' => $envMatchesSystem,
            'can_send_emails' => ($basicStatus['enabled'] ?? false) && $envMatchesSystem && !empty($systemSettings->system_email),
            'system_email' => $systemSettings->system_email ?? null,
            'env_email' => $envConfig['username'] ?? null,
        ]);
    }

    /**
     * Get enhanced WhatsApp status with system settings integration
     */
    protected function getEnhancedWhatsAppStatus($systemSettings): array
    {
        $basicStatus = $this->whatsappService->getSystemStatus();
        $envConfig = $this->environmentService->getWhatsAppConfiguration();
        
        $whatsappMatch = $this->checkWhatsAppConfigurationMatch($systemSettings, $envConfig);
        
        return array_merge($basicStatus, [
            'system_configured' => $systemSettings->isWhatsAppConfigured(),
            'env_matches_system' => $whatsappMatch['matches'],
            'configuration_health' => $whatsappMatch['health'],
            'can_send_messages' => ($basicStatus['enabled'] ?? false) && $whatsappMatch['matches'] && $whatsappMatch['health'] === 'healthy',
            'match_details' => $whatsappMatch,
        ]);
    }

    /**
     * Check WhatsApp configuration match between system settings and .env
     */
    protected function checkWhatsAppConfigurationMatch($systemSettings, $envConfig): array
    {
        try {
            if (!$systemSettings->isWhatsAppConfigured()) {
                return [
                    'matches' => false,
                    'reason' => 'WhatsApp not configured in system settings',
                    'health' => 'unconfigured'
                ];
            }

            $systemProvider = $systemSettings->whatsapp_provider;
            $envProvider = $envConfig['provider'] ?? 'none';

            if ($systemProvider !== $envProvider) {
                return [
                    'matches' => false,
                    'reason' => "Provider mismatch: System uses {$systemProvider}, .env uses {$envProvider}",
                    'health' => 'misconfigured'
                ];
            }

            $credentialsValid = false;
            switch ($systemProvider) {
                case 'twilio':
                    $systemSid = $systemSettings->twilio_sid ?? '';
                    $envSid = $envConfig['twilio_sid'] ?? '';
                    $systemFrom = $systemSettings->twilio_whatsapp_from ?? '';
                    $envFrom = $envConfig['twilio_whatsapp_from'] ?? '';
                    
                    $credentialsValid = !empty($systemSid) && 
                                       !empty($envSid) && 
                                       $systemSid === $envSid &&
                                       $systemFrom === $envFrom;
                    break;

                case 'vonage':
                    $systemKey = $systemSettings->vonage_key ?? '';
                    $envKey = $envConfig['vonage_key'] ?? '';
                    $systemFrom = $systemSettings->vonage_whatsapp_from ?? '';
                    $envFrom = $envConfig['vonage_whatsapp_from'] ?? '';
                    
                    $credentialsValid = !empty($systemKey) && 
                                       !empty($envKey) && 
                                       $systemKey === $envKey &&
                                       $systemFrom === $envFrom;
                    break;

                default:
                    $credentialsValid = false;
                    break;
            }

            if (!$credentialsValid) {
                return [
                    'matches' => false,
                    'reason' => 'Credentials mismatch between system settings and .env',
                    'health' => 'misconfigured'
                ];
            }

            return [
                'matches' => true,
                'reason' => 'Configuration matches and connection successful',
                'health' => 'healthy'
            ];

        } catch (\Exception $e) {
            Log::warning('Error checking WhatsApp configuration match: ' . $e->getMessage());
            return [
                'matches' => false,
                'reason' => 'Error checking configuration: ' . $e->getMessage(),
                'health' => 'error'
            ];
        }
    }

    // ==================== TYPE-SPECIFIC STATISTICS ====================

    /**
     * Get landlord property statistics
     */
    private function getLandlordPropertyStats(User $user): array
    {
        return [
            'total_properties' => $user->properties()->count(),
            'active_properties' => $user->properties()->where('status', 'active')->count(),
            'pending_properties' => $user->properties()->where('status', 'pending')->count(),
            'occupied_properties' => $user->properties()->whereHas('units', function($q) {
                $q->where('status', PropertyUnit::STATUS_OCCUPIED);
            })->count(),
            'total_rent' => $user->properties()->sum('monthly_rent'),
            'properties_added_this_month' => $user->properties()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Get landlord tenant statistics
     */
    private function getLandlordTenantStats(User $user): array
    {
        return [
            'total_tenants' => User::where('type', User::TYPE_TENANT)
                ->whereHas('propertyUnits.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->count(),
            'active_tenants' => User::where('type', User::TYPE_TENANT)
                ->whereHas('propertyUnits', function($q) {
                    $q->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED);
                })
                ->whereHas('propertyUnits.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->count(),
            'pending_approvals' => PropertyUnit::pendingTenantApproval()
                ->whereHas('property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->count(),
        ];
    }

    /**
 * Get field agent statistics
 */
private function getFieldAgentStats(User $user): array
{
    return array_merge($user->field_agent_stats ?? [], [
        'total_assignments' => $user->assignedPlans()->count(),
        'completed_assignments' => $user->assignedPlans()->where('status', 'completed')->count(),
        'active_assignments' => $user->assignedPlans()->where('status', 'active')->count(),
        'pending_reports' => $user->reports()->where('status', 'pending')->count(),
        'rating' => $user->metadata['average_rating'] ?? 'Not rated',
    ]);
}

    /**
     * Get tenant statistics
     */
    private function getTenantStats(User $user): array
    {
        return [
            'active_rentals' => $user->propertyUnits()->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)->count(),
            'total_rent_paid' => $user->invoices()->where('status', 'paid')->sum('amount'),
            'outstanding_balance' => $user->invoices()->where('status', '!=', 'paid')->sum('amount'),
        ];
    }

    /**
     * Get admin statistics
     */
    private function getAdminStats(User $user): array
    {
        $today = now()->today();
        
        return [
            'total_users' => User::count(),
            'users_today' => User::whereDate('created_at', $today)->count(),
            'pending_approvals' => User::where('status', User::STATUS_PENDING)->count(),
            'active_users' => User::where('status', User::STATUS_ACTIVE)->count(),
            'total_properties' => Property::count(),
            'active_properties' => Property::where('status', 'active')->count(),
            'total_income' => \App\Models\Payment::where('status', 'completed')->sum('amount'),
            'income_today' => \App\Models\Payment::where('status', 'completed')
                ->whereDate('created_at', $today)
                ->sum('amount'),
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get appropriate dashboard view based on user type
     */
    private function getDashboardViewByType($userType): string
    {
        $viewMap = [
            User::TYPE_SUPER_ADMIN => 'super-admin.dashboard',
            User::TYPE_ADMIN => 'admin.dashboard',
            User::TYPE_LANDLORD => 'landlord.dashboard',
            User::TYPE_TENANT => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
            User::TYPE_SECURITY_PERSONNEL => 'security-personnel.dashboard',
        ];

        // FIX: Check if user type exists in map, otherwise use a safe fallback
        if (!array_key_exists($userType, $viewMap)) {
            Log::warning('Unknown user type for dashboard view', [
                'user_type' => $userType,
                'user_id' => Auth::id(),
                'fallback' => 'user.default-dashboard'
            ]);
            
            // Check if we have a generic dashboard view
            if (view()->exists('user.default-dashboard')) {
                return 'user.default-dashboard';
            }
            
            // Check if we have any of the specific dashboard views as fallback
            foreach ($viewMap as $view) {
                if (view()->exists($view)) {
                    return $view;
                }
            }
            
            // Ultimate fallback - redirect to login with error
            throw new \Exception('No valid dashboard view found for user type: ' . $userType);
        }

        $view = $viewMap[$userType];
        
        // Double-check that the view exists
        if (!view()->exists($view)) {
            Log::error('Dashboard view not found: ' . $view, [
                'user_type' => $userType,
                'user_id' => Auth::id()
            ]);
            
            // Try to find an alternative view
            if (view()->exists('user.default-dashboard')) {
                return 'user.default-dashboard';
            }
            
            throw new \Exception('Dashboard view not found: ' . $view);
        }

        return $view;
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(User $user): int
    {
        $details = $this->calculateProfileCompletionDetails($user);
        return $details['total_weight'] > 0 
            ? min(100, round(($details['completed_weight'] / $details['total_weight']) * 100))
            : 0;
    }

    /**
     * Calculate detailed profile completion
     */
    private function calculateProfileCompletionDetails(User $user): array
    {
        $fields = [
            'name' => ['weight' => 10, 'completed' => !empty($user->name)],
            'email' => ['weight' => 15, 'completed' => !empty($user->email) && !is_null($user->email_verified_at)],
            'phone' => ['weight' => 15, 'completed' => !empty($user->phone) && !is_null($user->phone_verified_at)],
            'digital_address' => ['weight' => 10, 'completed' => !empty($user->digital_address)],
            'region' => ['weight' => 10, 'completed' => !empty($user->region)],
            'location' => ['weight' => 10, 'completed' => !empty($user->location)],
            'gender' => ['weight' => 5, 'completed' => !empty($user->gender)],
            'dob' => ['weight' => 5, 'completed' => !empty($user->dob)],
            'photo' => ['weight' => 10, 'completed' => !empty($user->photo)],
            'username' => ['weight' => 10, 'completed' => !empty($user->username)],
        ];

        $details = [];
        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($fields as $field => $data) {
            $details[$field] = [
                'completed' => $data['completed'],
                'weight' => $data['weight'],
                'label' => $this->getFieldLabel($field)
            ];

            $totalWeight += $data['weight'];
            if ($data['completed']) {
                $completedWeight += $data['weight'];
            }
        }

        $details['total_weight'] = $totalWeight;
        $details['completed_weight'] = $completedWeight;

        return $details;
    }

    /**
     * Get field label for display
     */
    private function getFieldLabel(string $field): string
    {
        $labels = [
            'name' => 'Full Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'digital_address' => 'Digital Address',
            'region' => 'Region',
            'location' => 'Location',
            'gender' => 'Gender',
            'dob' => 'Date of Birth',
            'photo' => 'Profile Photo',
            'username' => 'Username',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Get user notifications
     */
    public function getNotifications()
    {
        $user = Auth::user();
        
        $notifications = [
            [
                'id' => 1,
                'type' => 'system',
                'title' => 'Welcome to ' . config('app.name'),
                'message' => 'Thank you for joining our platform! Explore your dashboard to get started.',
                'timestamp' => $user->created_at->diffForHumans(),
                'read' => true,
            ]
        ];

        // Add invitation-related notifications
        if ($user->needsPasswordSetup()) {
            $invitation = $user->pendingInvitation;
            $notifications[] = [
                'id' => 2,
                'type' => 'invitation',
                'title' => 'Complete Account Setup',
                'message' => 'Please set your password to activate your account and access all features',
                'timestamp' => $invitation->sent_at?->diffForHumans() ?? 'Recently',
                'read' => false,
                'action_url' => $invitation ? route('user.invitations.accept', ['token' => $invitation->token]) : '#',
                'priority' => 'high',
                'icon' => 'user-lock'
            ];
        }

        // Add type-specific notifications
        switch ($user->type) {
            case User::TYPE_LANDLORD:
                $pendingProperties = $user->properties()->where('status', 'pending')->count();
                if ($pendingProperties > 0) {
                    $notifications[] = [
                        'id' => 3,
                        'type' => 'property',
                        'title' => 'Pending Properties',
                        'message' => "You have {$pendingProperties} properties awaiting approval",
                        'timestamp' => now()->diffForHumans(),
                        'read' => false,
                        'icon' => 'home'
                    ];
                }
                
                $pendingApprovals = PropertyUnit::pendingTenantApproval()
                    ->whereHas('property', function($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    })->count();
                if ($pendingApprovals > 0) {
                    $notifications[] = [
                        'id' => 4,
                        'type' => 'tenant',
                        'title' => 'Pending Tenant Approvals',
                        'message' => "You have {$pendingApprovals} tenant assignments awaiting admin approval",
                        'timestamp' => now()->diffForHumans(),
                        'read' => false,
                        'icon' => 'user-clock'
                    ];
                }
                break;
                
            case User::TYPE_FIELD_AGENT:
                $newAssignments = $user->assignedPlans()->where('status', 'active')->count();
                if ($newAssignments > 0) {
                    $notifications[] = [
                        'id' => 3,
                        'type' => 'assignment',
                        'title' => 'New Assignments',
                        'message' => "You have {$newAssignments} new property assignments",
                        'timestamp' => now()->diffForHumans(),
                        'read' => false,
                        'icon' => 'tasks'
                    ];
                }
                break;
        }

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => count(array_filter($notifications, fn($n) => !$n['read']))
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notificationId)
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Clear all notifications
     */
    public function clearAllNotifications()
    {
        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared'
        ]);
    }

    // ==================== USER PROFILE METHODS ====================

    /**
     * Display user profile
     */
    public function showProfile()
    {
        $user = Auth::user();
        
        // Prepare user data for display
        $userData = $this->prepareUserForDisplay($user);

        // Get user statistics
        $userStats = $this->getUserStatistics($user);

        // Get communication status
        $communicationStatus = $this->getCommunicationServiceStatus();

        return view('user.profile.show', compact(
            'user',
            'userData',
            'userStats',
            'communicationStatus'
        ));
    }

    /**
     * Prepare user data for display
     */
    private function prepareUserForDisplay(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $user->username,
            'type' => $user->type,
            'status' => $user->status,
            'gender' => $user->gender,
            'dob' => $user->dob,
            'digital_address' => $user->digital_address,
            'region' => $user->region,
            'location' => $user->location,
            'photo' => $user->photo,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'invitation_accepted_at' => $user->invitation_accepted_at,
            'last_login_at' => $user->last_login_at,
            'last_activity_at' => $user->last_activity_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            
            // Display properties
            'type_display' => $user->type_name,
            'status_display' => $user->status_with_color['label'],
            'has_photo' => $user->has_photo,
            'photo_url' => $user->photo_url,
            'avatar_url' => $user->avatar_url,
            'initials' => $user->initials,
            'local_phone' => $user->local_phone,
            'is_phone_verified' => $user->is_phone_verified,
            'has_accepted_invitation' => $user->has_accepted_invitation,
            'is_invitation_pending' => $user->is_invitation_pending,
            
            // Type checks
            'is_super_admin' => $user->isSuperAdmin(),
            'is_admin' => $user->isAdmin(),
            'is_landlord' => $user->isLandlord(),
            'is_tenant' => $user->isTenant(),
            'is_field_agent' => $user->isFieldAgent(),
            'is_security_personnel' => $user->isSecurityPersonnel(),
        ];
    }

    /**
     * Get user statistics for display
     */
    private function getUserStatistics(User $user): array
    {
        $stats = [];

        switch ($user->type) {
            case User::TYPE_FIELD_AGENT:
                $stats = $user->field_agent_stats;
                break;

            case User::TYPE_LANDLORD:
                $stats = [
                    'total_properties' => $user->properties()->count(),
                    'active_properties' => $user->properties()->where('status', 'active')->count(),
                    'total_units' => $user->properties()->withCount('units')->get()->sum('units_count'),
                    'occupied_units' => PropertyUnit::whereHas('property', function($q) use ($user) {
                        $q->where('landlord_id', $user->id);
                    })->where('status', PropertyUnit::STATUS_OCCUPIED)->count(),
                    'total_tenants' => User::where('type', User::TYPE_TENANT)
                        ->whereHas('propertyUnits.property', function($q) use ($user) {
                            $q->where('landlord_id', $user->id);
                        })->count(),
                ];
                break;

            case User::TYPE_TENANT:
                $stats = [
                    'active_units' => $user->propertyUnits()->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)->count(),
                    'total_rent_paid' => $user->invoices()->where('status', 'paid')->sum('amount'),
                    'outstanding_balance' => $user->invoices()->where('status', '!=', 'paid')->sum('amount'),
                    'member_since' => $user->created_at->diffForHumans(),
                    'last_active' => $user->last_activity_at?->diffForHumans() ?? 'Never',
                ];
                break;

            case User::TYPE_ADMIN:
            case User::TYPE_SUPER_ADMIN:
                $stats = [
                    'users_created' => User::where('created_by', $user->id)->count(),
                    'last_active' => $user->last_activity_at?->diffForHumans() ?? 'Never',
                    'total_logins' => $user->metadata['total_logins'] ?? 0,
                ];
                break;
        }

        return $stats;
    }

    // ==================== LANDLORD TENANT MANAGEMENT ====================

    /**
 * Display tenants for landlord
 */
public function landlordTenants(Request $request)
{
    $user = auth()->user();
    
    if (!$user->isLandlord()) {
        abort(403, 'Unauthorized access.');
    }

    // Get landlord's properties for filter
    $properties = $user->properties()->orderBy('property_name')->get();

    // Tenant status options
    $tenantStatusOptions = PropertyUnit::getTenantStatusOptions();

    // Build query for tenants assigned to landlord's properties
    $query = User::where('type', User::TYPE_TENANT)
        ->where(function($q) use ($user) {
            // Tenants who are assigned to landlord's properties OR
            // Tenants who have pending assignments to landlord's properties
            $q->whereHas('propertyUnits.property', function($propertyQuery) use ($user) {
                $propertyQuery->where('landlord_id', $user->id);
            });
        })
        ->with(['propertyUnits' => function($q) use ($user) {
            $q->whereHas('property', function($query) use ($user) {
                $query->where('landlord_id', $user->id);
            })->with('property');
        }])
        ->orderBy('name');

    // Search functionality
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('digital_address', 'like', "%{$search}%");
        });
    }

    // Filter by property
    if ($request->filled('property_id')) {
        $query->whereHas('propertyUnits', function($q) use ($request) {
            $q->where('property_id', $request->property_id);
        });
    }

    // Filter by tenant status
    if ($request->filled('tenant_status')) {
        $query->whereHas('propertyUnits', function($q) use ($request) {
            $q->where('tenant_status', $request->tenant_status);
        });
    }

    // Filter by phone
    if ($request->filled('phone')) {
        $query->where('phone', 'like', "%{$request->phone}%");
    }

    // Filter by email
    if ($request->filled('email')) {
        $query->where('email', 'like', "%{$request->email}%");
    }

    // Filter by date range
    if ($request->filled('start_date') || $request->filled('end_date')) {
        $query->whereDate('created_at', '>=', $request->filled('start_date') ? $request->start_date : '1900-01-01')
              ->whereDate('created_at', '<=', $request->filled('end_date') ? $request->end_date : now()->format('Y-m-d'));
    }

    $tenants = $query->paginate(20)->withQueryString();

    // ========== FIXED STATISTICS CALCULATION ==========
    
    // Get all tenant IDs for this landlord's properties
    $landlordTenantIds = User::where('type', User::TYPE_TENANT)
        ->whereHas('propertyUnits.property', function($q) use ($user) {
            $q->where('landlord_id', $user->id);
        })
        ->pluck('id');
    
    // Get property units for this landlord
    $landlordUnitsQuery = PropertyUnit::whereHas('property', function($q) use ($user) {
        $q->where('landlord_id', $user->id);
    });
    
    // Calculate statistics
    $stats = [
        // Total unique tenants across all properties
        'total_tenants' => $landlordTenantIds->count(),
        
        // Approved tenants (actively occupying units)
        'approved_tenants' => $landlordUnitsQuery->clone()
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->count(),
        
        // Pending approvals (awaiting admin approval)
        'pending_approvals' => $landlordUnitsQuery->clone()
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_PENDING_APPROVAL)
            ->count(),
        
        // Vacated tenants (moved out)
        'vacated_tenants' => $landlordUnitsQuery->clone()
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_VACATED)
            ->count(),
        
        // Rejected tenants (applications rejected)
        'rejected_tenants' => $landlordUnitsQuery->clone()
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_REJECTED)
            ->count(),
        
        // Terminated tenants (lease terminated)
        'terminated_tenants' => $landlordUnitsQuery->clone()
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_TERMINATED)
            ->count(),
        
        // Filtered count (for display in UI)
        'filtered_tenants' => $tenants->total(),
    ];

    return view('landlord.tenants.index', compact(
        'tenants', 
        'properties', 
        'tenantStatusOptions', 
        'stats', 
        'request'
    ));
}

    /**
     * Display tenants for landlord (alias for landlordTenantsIndex route)
     */
    public function landlordTenantsIndex(Request $request)
    {
        return $this->landlordTenants($request);
    }

    /**
     * Show tenant details for landlord
     */
    public function landlordShowTenant($id)
    {
        $user = auth()->user();
        
        if (!$user->isLandlord()) {
            abort(403, 'Unauthorized access.');
        }

        $tenant = User::where('type', User::TYPE_TENANT)
            ->whereHas('propertyUnits.property', function($q) use ($user) {
                $q->where('landlord_id', $user->id);
            })
            ->with([
                'propertyUnits' => function($q) use ($user) {
                    $q->whereHas('property', function($query) use ($user) {
                        $query->where('landlord_id', $user->id);
                    })->with(['property', 'currentLease']);
                },
                'invoices' => function($q) use ($user) {
                    $q->whereHas('unit.property', function($query) use ($user) {
                        $query->where('landlord_id', $user->id);
                    })->orderBy('due_date', 'desc')->limit(10);
                },
                'maintenanceRequests' => function($q) use ($user) {
                    $q->whereHas('unit.property', function($query) use ($user) {
                        $query->where('landlord_id', $user->id);
                    })->orderBy('created_at', 'desc')->limit(10);
                }
            ])
            ->findOrFail($id);

        // Calculate tenant statistics
        $stats = [
            'total_units' => $tenant->propertyUnits->count(),
            'total_rent_paid' => $tenant->invoices()->where('status', 'paid')->sum('amount'),
            'outstanding_balance' => $tenant->invoices()->where('status', '!=', 'paid')->sum('amount'),
            'maintenance_requests' => $tenant->maintenanceRequests()->count(),
            'active_maintenance' => $tenant->maintenanceRequests()->whereIn('status', ['pending', 'in_progress'])->count(),
            'payment_history' => $tenant->invoices()
                ->whereHas('unit.property', function($query) use ($user) {
                    $query->where('landlord_id', $user->id);
                })
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(amount) as total')
                ->where('status', 'paid')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get(),
        ];

        // Get tenant's active lease if any
        $activeLease = $tenant->propertyUnits->first()?->currentLease;

        return view('landlord.tenants.show', compact('tenant', 'stats', 'activeLease'));
    }

    /**
     * Get available tenants for assignment (used in PropertyUnitController)
     */
    public function getAvailableTenants(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isLandlord()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Get tenants not currently assigned to any unit (approved or pending)
        $availableTenants = User::where('type', User::TYPE_TENANT)
            ->whereDoesntHave('propertyUnits', function ($query) {
                $query->whereIn('tenant_status', [
                    PropertyUnit::TENANT_STATUS_APPROVED,
                    PropertyUnit::TENANT_STATUS_PENDING_APPROVAL
                ]);
            })
            ->select('id', 'name', 'email', 'phone', 'digital_address', 'gender')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'tenants' => $availableTenants
        ]);
    }

    /**
     * Export tenants list for landlord
     */
    public function exportTenants(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isLandlord()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $query = User::where('type', User::TYPE_TENANT)
            ->whereHas('propertyUnits.property', function($q) use ($user) {
                $q->where('landlord_id', $user->id);
            })
            ->with(['propertyUnits.property']);

        // Apply filters
        if ($request->filled('property_id')) {
            $query->whereHas('propertyUnits', function($q) use ($request) {
                $q->where('property_id', $request->property_id);
            });
        }

        if ($request->filled('tenant_status')) {
            $query->whereHas('propertyUnits', function($q) use ($request) {
                $q->where('tenant_status', $request->tenant_status);
            });
        }

        $tenants = $query->get();

        $fileName = 'tenants_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($tenants) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, [
                'Tenant ID',
                'Name',
                'Email',
                'Phone',
                'Digital Address',
                'Gender',
                'Status',
                'Properties Assigned',
                'Units Assigned',
                'Move In Date',
                'Current Rent',
                'Total Rent Paid',
                'Outstanding Balance',
                'Maintenance Requests',
                'Created At'
            ]);

            // Data rows
            foreach ($tenants as $tenant) {
                $units = $tenant->propertyUnits;
                $properties = $units->pluck('property.property_name')->unique()->implode(', ');
                $unitNumbers = $units->pluck('unit_number')->implode(', ');
                
                $moveInDate = $units->first()?->tenant_move_in_date?->format('Y-m-d') ?? 'N/A';
                $currentRent = $units->sum('current_rent_amount');
                $totalRentPaid = $tenant->invoices()->where('status', 'paid')->sum('amount');
                $outstandingBalance = $tenant->invoices()->where('status', '!=', 'paid')->sum('amount');
                $maintenanceCount = $tenant->maintenanceRequests()->count();

                fputcsv($file, [
                    $tenant->id,
                    $tenant->name,
                    $tenant->email,
                    $tenant->phone,
                    $tenant->digital_address ?? 'N/A',
                    $tenant->gender ?? 'N/A',
                    $this->getTenantStatusLabel($tenant),
                    $properties,
                    $unitNumbers,
                    $moveInDate,
                    $currentRent,
                    $totalRentPaid,
                    $outstandingBalance,
                    $maintenanceCount,
                    $tenant->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get tenant status label
     */
    private function getTenantStatusLabel(User $tenant): string
    {
        $unit = $tenant->propertyUnits()->first();
        if (!$unit) {
            return 'Not Assigned';
        }

        $statusLabels = [
            PropertyUnit::TENANT_STATUS_PENDING_APPROVAL => 'Pending Approval',
            PropertyUnit::TENANT_STATUS_APPROVED => 'Active',
            PropertyUnit::TENANT_STATUS_REJECTED => 'Rejected',
            PropertyUnit::TENANT_STATUS_VACATED => 'Vacated',
        ];

        return $statusLabels[$unit->tenant_status] ?? 'Unknown';
    }

    /**
     * Get tenant statistics API endpoint
     */
    public function getTenantStatistics($id)
    {
        $user = auth()->user();
        
        if (!$user->isLandlord()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tenant = User::where('type', User::TYPE_TENANT)
            ->whereHas('propertyUnits.property', function($q) use ($user) {
                $q->where('landlord_id', $user->id);
            })
            ->findOrFail($id);

        $stats = [
            'total_units' => $tenant->propertyUnits()->count(),
            'total_rent_paid' => $tenant->invoices()->where('status', 'paid')->sum('amount'),
            'outstanding_balance' => $tenant->invoices()->where('status', '!=', 'paid')->sum('amount'),
            'maintenance_requests' => $tenant->maintenanceRequests()->count(),
            'active_maintenance' => $tenant->maintenanceRequests()->whereIn('status', ['pending', 'in_progress'])->count(),
            'payment_history' => [
                'last_30_days' => $tenant->invoices()
                    ->where('status', 'paid')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('amount'),
                'last_90_days' => $tenant->invoices()
                    ->where('status', 'paid')
                    ->where('created_at', '>=', now()->subDays(90))
                    ->sum('amount'),
                'this_year' => $tenant->invoices()
                    ->where('status', 'paid')
                    ->whereYear('created_at', now()->year)
                    ->sum('amount'),
            ],
        ];

        return response()->json([
            'success' => true,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
            ],
            'statistics' => $stats
        ]);
    }

    // ==================== TENANT PROFILE METHODS ====================

    /**
     * Show tenant profile (for tenant's own view)
     */
    public function tenantProfile()
    {
        $user = auth()->user();
        
        if (!$user->isTenant()) {
            abort(403, 'Unauthorized access.');
        }

        // Get tenant's assigned units
        $units = $user->propertyUnits()
            ->with(['property', 'currentLease'])
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->get();

        // Get recent invoices
        $recentInvoices = $user->invoices()
            ->orderBy('due_date', 'desc')
            ->limit(5)
            ->get();

        // Get recent maintenance requests
        $recentMaintenance = $user->maintenanceRequests()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Calculate statistics
        $stats = [
            'active_units' => $units->count(),
            'outstanding_balance' => $user->invoices()->where('status', '!=', 'paid')->sum('amount'),
            'pending_maintenance' => $user->maintenanceRequests()->whereIn('status', ['pending', 'in_progress'])->count(),
            'next_payment_due' => $user->invoices()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->first()?->due_date,
        ];

        return view('tenant.profile.index', compact('user', 'units', 'recentInvoices', 'recentMaintenance', 'stats'));
    }

    // ==================== USER SEARCH API ====================

    /**
     * Search users by name, email, or phone
     */
    public function searchUsers(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isLandlord() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $search = $request->input('search', '');
        
        if (empty($search) || strlen($search) < 2) {
            return response()->json(['success' => false, 'message' => 'Search term must be at least 2 characters'], 400);
        }

        $query = User::where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });

        // For landlords, only show tenants
        if ($user->isLandlord()) {
            $query->where('type', User::TYPE_TENANT);
        }

        $users = $query->select('id', 'name', 'email', 'phone', 'type', 'created_at')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type_name,
                    'created_at' => $user->created_at->format('Y-m-d'),
                ];
            })
        ]);
    }
}
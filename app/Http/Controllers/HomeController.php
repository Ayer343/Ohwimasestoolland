<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\SystemSetting;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\Role;
use App\Models\PropertyUnit;
use App\Models\RentalAgreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\MaintenanceRequest;
use App\Models\TenantInvitation;
use App\Models\AgentInvitation;
use App\Models\Notification;
use App\Models\Channel;
use App\Models\PropertyOwnershipTransfer;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Models\UserInvitation;
use App\Models\LandlordInvitation;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use App\Models\SecurityReport;
use App\Models\SecurityIncident;
use App\Models\SecurityAttendance;
use App\Models\LandlordConstructionRegistration;
use App\Models\Testimonial;
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use App\Services\InvoiceService;
use App\Services\AgentInvitationService;
use App\Services\PropertyRegistrationService;
use App\Services\MultiChannelInvitationService;
use Carbon\Carbon;


class HomeController extends Controller
{
    protected $paymentService;
    protected $smsService;
    protected $invoiceService;
    protected $invitationService;

    public function __construct(
        PaymentService $paymentService = null,
        SmsService $smsService = null,
        InvoiceService $invoiceService = null,
        AgentInvitationService $invitationService = null
    ) {
        $this->paymentService = $paymentService;
        $this->smsService = $smsService;
        $this->invoiceService = $invoiceService;
        $this->invitationService = $invitationService;
    }

   /**
     * Display the application homepage or redirect to appropriate dashboard.
     */
    public function index()
    {
        if (Auth::check()) {
            return $this->redirectToDashboard();
        }
        
        $systemSettings = SystemSetting::getSettings();
        
        $stats = [
            'total_properties' => Property::count(),
            'under_construction' => Property::where('status', 'under_construction')->count(),
            'total_landlords' => User::where(function($query) {
                $query->where('type', User::TYPE_LANDLORD)
                      ->orWhereHas('roles', function($q) {
                          $q->where('slug', 'landlord');
                      });
            })->count(),
            'total_tenants' => User::where(function($query) {
                $query->where('type', User::TYPE_TENANT)
                      ->orWhereHas('roles', function($q) {
                          $q->where('slug', 'tenant');
                      });
            })->count(),
        ];
        
        return view('welcome', compact('systemSettings', 'stats'));
    }


    /**
     * Redirect authenticated users to their appropriate dashboard based on PRIMARY role.
     */
    public function redirectToDashboard()
    {
        $user = Auth::user();
        $selectedRole = session('selected_role');
        
        if ($selectedRole) {
            return $this->redirectToRoleDashboard($selectedRole);
        }
        
        $primaryRole = $user->getPrimaryRoleAttribute();
        
        if ($primaryRole) {
            return $this->redirectToRoleDashboard($primaryRole->slug);
        }
        
        return $this->redirectByLegacyType($user);
    }

    /**
     * Redirect to specific role dashboard
     */
    private function redirectToRoleDashboard(string $roleSlug)
    {
        $routeMap = [
            'super-admin' => 'super-admin.dashboard',
            'admin' => 'admin.dashboard',
            'landlord' => 'landlord.dashboard',
            'field-agent' => 'field-agent.dashboard',
            'security-personnel' => 'security.dashboard',
            'tenant' => 'tenant.dashboard',
        ];
        
        if (!isset($routeMap[$roleSlug])) {
            Log::warning('Invalid role slug for redirect: ' . $roleSlug);
            session()->forget('selected_role');
            return $this->redirectByLegacyType(Auth::user());
        }
        
        $routeName = $routeMap[$roleSlug];
        
        if (!\Route::has($routeName)) {
            Log::error('Dashboard route not found: ' . $routeName);
            return $this->redirectByLegacyType(Auth::user());
        }
        
        return redirect()->route($routeName);
    }

    /**
     * Redirect based on legacy user type (backward compatibility)
     */
    private function redirectByLegacyType(User $user)
    {
        $userType = $user->getRawOriginal('type');
        
        $routeMap = [
            User::TYPE_SUPER_ADMIN => 'super-admin.dashboard',
            User::TYPE_ADMIN => 'admin.dashboard',
            User::TYPE_LANDLORD => 'landlord.dashboard',
            User::TYPE_TENANT => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
            User::TYPE_SECURITY_PERSONNEL => 'security.dashboard',
        ];
        
        if (isset($routeMap[$userType]) && \Route::has($routeMap[$userType])) {
            return redirect()->route($routeMap[$userType]);
        }
        
        Log::error('No valid dashboard found for user: ' . $user->id . ', type: ' . $userType);
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect('/')->with('error', 'Invalid user role. Please contact support.');
    }


    /**
     * Verify and redirect if session role doesn't match current dashboard
     */
    private function verifyDashboardRole(string $expectedRole)
    {
        $user = Auth::user();
        $selectedRole = session('selected_role');
        
        if (!$selectedRole) {
            return null;
        }
        
        if ($selectedRole === $expectedRole) {
            return null;
        }
        
        // ==================== DUAL MODE ROLE CHECK ====================
        // 1. Check role-based roles
        $hasRoleBasedRole = $user->hasRole($selectedRole);
        
        // 2. Check legacy type field
        $legacyTypeMap = [
            0 => 'super-admin',
            1 => 'admin',
            2 => 'landlord',
            3 => 'tenant',
            4 => 'field-agent',
            5 => 'developer',
            6 => 'security-personnel',
        ];
        $legacyRole = $legacyTypeMap[$user->type] ?? null;
        $hasLegacyRole = ($legacyRole === $selectedRole);
        
        // 3. User has the role if EITHER check passes
        $hasRole = $hasRoleBasedRole || $hasLegacyRole;
        
        if (!$hasRole) {
            Log::warning('verifyDashboardRole - User does NOT have session role, clearing it', [
                'user_id' => $user->id,
                'selected_role' => $selectedRole,
                'expected_role' => $expectedRole,
                'user_roles' => $user->roles->pluck('slug')->toArray(),
                'legacy_role' => $legacyRole
            ]);
            session()->forget('selected_role');
            return null;
        }
        
        $routes = [
            'super-admin' => 'super-admin.dashboard',
            'admin' => 'admin.dashboard',
            'landlord' => 'landlord.dashboard',
            'field-agent' => 'field-agent.dashboard',
            'security-personnel' => 'security.dashboard',
            'tenant' => 'tenant.dashboard',
        ];
        
        if (isset($routes[$selectedRole])) {
            $routeName = $routes[$selectedRole];
            
            if (\Route::has($routeName)) {
                Log::info('verifyDashboardRole - Redirecting to correct dashboard', [
                    'from_role' => $expectedRole,
                    'to_role' => $selectedRole,
                    'user_id' => $user->id
                ]);
                $redirectAttemptKey = 'redirect_attempt_' . $expectedRole . '_to_' . $selectedRole;
                if (session()->has($redirectAttemptKey)) {
                    session()->forget($redirectAttemptKey);
                }
                return redirect()->route($routeName);
            } else {
                Log::error('Route not found for role', ['role' => $selectedRole, 'route' => $routeName]);
                session()->forget('selected_role');
                return null;
            }
        }
        
        session()->forget('selected_role');
        return null;
    }

    /**
     * Super Admin Dashboard with comprehensive statistics and search functionality
     */
    public function superadminDashboard(Request $request)
    {
        $redirect = $this->verifyDashboardRole('super-admin');
        if ($redirect) return $redirect;
        
        try {
            $searchTerm = $request->get('search');
            $category = $request->get('category', 'all');
            
            $systemSettings = SystemSetting::getSettings();
            $settings = SystemSetting::first() ?? new SystemSetting();
            
            $filteredData = $this->getFilteredDashboardData($searchTerm, $category);
            
            $userStats = $this->getUserStatistics();
            $paymentStats = $this->getPaymentStatistics($settings);
            $propertyStats = $this->getPropertyStatistics();
            $planStats = $this->getRegistrationPlanStatistics();
            
            $recentPayments = $this->getRecentPayments($settings);
            $recentPlans = $this->getRecentRegistrationPlans();
            $recentUsers = $this->getRecentUsers();
            $recentProperties = $this->getRecentProperties();
            $recentTestimonials = Testimonial::approved()
                ->with(['user', 'approver'])
                ->latest()
                ->take(5)
                ->get();
            
            $paymentProviders = $this->getPaymentProvidersStatus();
            $smsProviders = $this->getSmsProvidersStatus();
            
            $invitationStats = $this->getInvitationStatistics();
            $landlordInvitationStats = $this->getLandlordInvitationStatistics();
            $userInvitationStats = $this->getUserInvitationStatistics();
            
            $systemHealth = $this->getSystemHealthStatus($paymentProviders, $smsProviders, $invitationStats);
            
            $revenueData = $this->getRevenueChartData($settings);
            $userRegistrationData = $this->getUserRegistrationChartData();
            $propertyRegistrationData = $this->getPropertyRegistrationChartData();
            
            $pendingActions = $this->getPendingActions();
            $systemAlerts = $this->getSystemAlerts();
            
            $quickStats = $this->getQuickStats($settings);
            
            $user = Auth::user();
            $userRoles = $user->getRoleSlugsAttribute();
            $isMultiRoleUser = count($userRoles) > 1;
            
            return view('super-admin.dashboard', compact(
                'systemSettings',
                'settings',
                'searchTerm',
                'category',
                'filteredData',
                'userStats',
                'paymentStats',
                'propertyStats',
                'planStats',
                'recentPayments',
                'recentPlans',
                'recentUsers',
                'recentProperties',
                'recentTestimonials',
                'paymentProviders',
                'smsProviders',
                'invitationStats',
                'landlordInvitationStats',
                'userInvitationStats',
                'systemHealth',
                'revenueData',
                'userRegistrationData',
                'propertyRegistrationData',
                'pendingActions',
                'systemAlerts',
                'quickStats',
                'userRoles',
                'isMultiRoleUser'
            ));
            
        } catch (\Exception $e) {
            Log::error('Super Admin Dashboard Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return $this->getFallbackDashboardData($e->getMessage());
        }
    }

    /**
     * Admin Dashboard with multi-role support
     */
    public function adminDashboard()
    {
        $redirect = $this->verifyDashboardRole('admin');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $systemSettings = SystemSetting::getSettings();
        $settings = SystemSetting::first() ?? new SystemSetting();
        
        $userStats = [
            'total_users' => User::whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
            'active_users' => User::where('status', 'active')->whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
            'pending_users' => User::where('status', 'pending')->whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
            'suspended_users' => User::where('status', 'suspended')->whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
            'super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)->count(),
            'admins' => User::where('type', User::TYPE_ADMIN)->count(),
            'landlords' => User::where(function($query) {
                $query->where('type', User::TYPE_LANDLORD)
                      ->orWhereHas('roles', function($q) {
                          $q->where('slug', 'landlord');
                      });
            })->count(),
            'tenants' => User::where('type', User::TYPE_TENANT)->count(),
            'field_agents' => User::where('type', User::TYPE_FIELD_AGENT)->count(),
            'developers' => User::where('type', User::TYPE_DEVELOPER)->count(),
            'security_checkpoints' => User::where('type', User::TYPE_SECURITY_PERSONNEL)->count(),
            'new_users_today' => User::whereDate('created_at', today())->whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
            'new_users_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])->count(),
        ];
        
        $totalRevenue = Payment::where('status', 'completed')->sum('amount') ?? 0;
        $todayRevenue = Payment::where('status', 'completed')->whereDate('created_at', today())->sum('amount') ?? 0;
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount') ?? 0;
        $weeklyRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount') ?? 0;
        
        $paymentStats = [
            'total_revenue' => $totalRevenue,
            'formatted_total_revenue' => $systemSettings->formatAmount($totalRevenue),
            'today_revenue' => $todayRevenue,
            'formatted_today_revenue' => $systemSettings->formatAmount($todayRevenue),
            'monthly_revenue' => $monthlyRevenue,
            'formatted_monthly_revenue' => $systemSettings->formatAmount($monthlyRevenue),
            'weekly_revenue' => $weeklyRevenue,
            'formatted_weekly_revenue' => $systemSettings->formatAmount($weeklyRevenue),
            'total_payments' => Payment::count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'failed_payments' => Payment::where('status', 'failed')->count(),
            'completed_payments' => Payment::where('status', 'completed')->count(),
        ];
        
        $propertyStats = [
            'total_properties' => Property::count(),
            'active_properties' => Property::where('status', 'active')->count(),
            'inactive_properties' => Property::where('status', 'inactive')->count(),
            'pending_properties' => Property::where('status', 'pending')->count(),
            'with_digital_address' => Property::whereNotNull('digital_address')->count(),
            'without_digital_address' => Property::whereNull('digital_address')->count(),
            'properties_with_dues' => Property::has('invoices')->count(),
            'properties_registered_today' => Property::whereDate('created_at', today())->count(),
            'properties_registered_this_week' => Property::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'properties_registered_this_month' => Property::whereMonth('created_at', now()->month)->count(),
        ];
        
        $planStats = [
            'total_plans' => RegistrationPlan::count(),
            'active_plans' => RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_plans' => RegistrationPlan::where('status', 'completed')->count(),
            'draft_plans' => RegistrationPlan::where('status', 'draft')->count(),
            'cancelled_plans' => RegistrationPlan::where('status', 'cancelled')->count(),
            'unassigned_plans' => RegistrationPlan::unassigned()->count(),
            'overdue_plans' => RegistrationPlan::overdue()->count(),
            'multi_agent_plans' => RegistrationPlan::multipleAssignment()->count(),
            'single_assignment_plans' => RegistrationPlan::singleAssignment()->count(),
            'total_agent_assignments' => PlanAgentAssignment::where('is_active', true)->count(),
            'active_agent_assignments' => PlanAgentAssignment::active()->count(),
            'properties_through_plans' => Property::whereHas('registrationPlan')->count(),
        ];
        
        $recentPayments = Payment::with(['property', 'landlord'])
            ->whereIn('status', ['completed', 'pending', 'failed'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        $recentPayments->each(function ($payment) use ($systemSettings) {
            $payment->formatted_amount = $systemSettings->formatAmount($payment->amount);
        });
        
        $recentUsers = User::whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $recentProperties = Property::with(['landlord', 'registrationPlan'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $recentPlans = RegistrationPlan::with(['activeAgents.agent', 'properties'])
            ->withCount(['properties as registered_properties_count'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $recentTestimonials = collect();
        if (class_exists('\App\Models\Testimonial')) {
            try {
                $recentTestimonials = Testimonial::approved()
                    ->with(['user', 'approver'])
                    ->latest()
                    ->take(5)
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Testimonial model error: ' . $e->getMessage());
            }
        }
        
        $currentYear = now()->year;
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month');
        
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[] = $monthlyRevenue[$month] ?? 0;
        }
        
        $revenueData = [
            'monthly' => $monthlyData,
            'daily' => array_fill(0, now()->daysInMonth, 0),
            'monthly_labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'daily_labels' => range(1, now()->daysInMonth),
            'current_year' => $currentYear,
            'current_month' => now()->monthName,
        ];
        
        $labels = [];
        $allData = [];
        $landlordData = [];
        $agentData = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;
            $labels[] = $date->format('M Y');
            
            $allData[] = User::whereNotIn('type', [User::TYPE_SUPER_ADMIN, User::TYPE_DEVELOPER])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
            
            $landlordData[] = User::where(function($query) {
                    $query->where('type', User::TYPE_LANDLORD)
                          ->orWhereHas('roles', function($q) {
                              $q->where('slug', 'landlord');
                          });
                })
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
            
            $agentData[] = User::where('type', User::TYPE_FIELD_AGENT)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
        }
        
        $userRegistrationData = [
            'all' => $allData,
            'landlords' => $landlordData,
            'agents' => $agentData,
            'labels' => $labels,
        ];
        
        $propertyLabels = [];
        $propertyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $propertyLabels[] = $date->format('M Y');
            $propertyData[] = Property::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }
        
        $propertyRegistrationData = [
            'properties' => $propertyData,
            'with_digital_address' => array_fill(0, 6, 0),
            'without_digital_address' => array_fill(0, 6, 0),
            'labels' => $propertyLabels,
        ];
        
        $paymentProviders = $this->getPaymentProvidersStatus();
        $smsProviders = $this->getSmsProvidersStatus();
        $invitationStats = $this->getInvitationStatistics();
        $landlordInvitationStats = $this->getLandlordInvitationStatistics();
        $userInvitationStats = $this->getUserInvitationStatistics();
        $systemHealth = $this->getSystemHealthStatus($paymentProviders, $smsProviders, $invitationStats);
        $pendingActions = $this->getPendingActions();
        $systemAlerts = $this->getSystemAlerts();
        $quickStats = $this->getQuickStats($settings);
        
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('admin.dashboard', compact(
            'systemSettings',
            'userStats',
            'paymentStats',
            'propertyStats',
            'planStats',
            'recentPayments',
            'recentUsers',
            'recentProperties',
            'recentPlans',
            'recentTestimonials',
            'revenueData',
            'recentPayments',      
            'recentUsers',        
            'recentProperties',    
            'recentPlans',         
            'userRegistrationData',
            'propertyRegistrationData',
            'paymentProviders',
            'smsProviders',
            'invitationStats',
            'landlordInvitationStats',
            'userInvitationStats',
            'systemHealth',
            'pendingActions',
            'systemAlerts',
            'quickStats',
            'userRoles',
            'isMultiRoleUser'
        ));
    }

    /**
     * Landlord Dashboard with multi-role support
     */
    public function landlordDashboard()
    {
        $redirect = $this->verifyDashboardRole('landlord');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $systemSettings = SystemSetting::getSettings();
        
        $stats = $this->getLandlordDashboardStats($user->id);
        $recentProperties = $this->getLandlordRecentProperties($user->id);
        $recentPayments = $this->getLandlordRecentPayments($user->id);
        $upcomingLeaseExpirations = $this->getLandlordUpcomingLeaseExpirations($user->id);
        $recentMaintenanceRequests = $this->getLandlordRecentMaintenanceRequests($user->id);
        $financialSummary = $this->getLandlordFinancialSummary($user->id);
        $maintenanceSummary = $this->getLandlordMaintenanceSummary($user->id);
        $chartData = $this->getLandlordChartData($user->id);
        $pendingActions = $this->getLandlordPendingActions($user->id);
        $notifications = $user->notifications()->latest()->limit(10)->get();
        
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        $otherRoles = array_diff($userRoles, ['landlord']);
        $hasAdminAccess = $user->isAdmin() || $user->isSuperAdmin();
        
        return view('landlord.dashboard', compact(
            'systemSettings',
            'stats',
            'recentProperties',
            'recentPayments',
            'upcomingLeaseExpirations',
            'recentMaintenanceRequests',
            'financialSummary',
            'maintenanceSummary',
            'chartData',
            'pendingActions',
            'notifications',
            'userRoles',
            'isMultiRoleUser',
            'otherRoles',
            'hasAdminAccess'
        ));
    }

    /**
     * Get landlord financial summary data (AJAX endpoint)
     */
    public function getLandlordFinancialSummaryData()
    {
        try {
            $user = Auth::user();
            $financialSummary = $this->getLandlordFinancialSummary($user->id);
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $financialSummary
                ]);
            }
            
            return $financialSummary;
        } catch (\Exception $e) {
            Log::error('Error getting landlord financial summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlord maintenance summary data (AJAX endpoint)
     */
    public function getLandlordMaintenanceSummaryData()
    {
        try {
            $user = Auth::user();
            $maintenanceSummary = $this->getLandlordMaintenanceSummary($user->id);
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $maintenanceSummary
                ]);
            }
            
            return $maintenanceSummary;
        } catch (\Exception $e) {
            Log::error('Error getting landlord maintenance summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlord lease analytics (AJAX endpoint)
     */
    public function landlordLeaseAnalytics()
    {
        try {
            $user = Auth::user();
            
            // Get lease statistics for landlord
            $leaseStats = [
                'total_active_leases' => RentalAgreement::whereHas('unit.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->where('status', 'active')->count(),
                
                'expiring_this_month' => RentalAgreement::whereHas('unit.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->where('status', 'active')
                  ->where('end_date', '<=', now()->endOfMonth())
                  ->where('end_date', '>=', now())
                  ->count(),
                
                'expiring_next_month' => RentalAgreement::whereHas('unit.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->where('status', 'active')
                  ->where('end_date', '<=', now()->addMonth()->endOfMonth())
                  ->where('end_date', '>=', now()->addMonth()->startOfMonth())
                  ->count(),
                
                'expired_leases' => RentalAgreement::whereHas('unit.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->where('status', 'expired')->count(),
                
                'pending_approvals' => RentalAgreement::whereHas('unit.property', function($q) use ($user) {
                    $q->where('landlord_id', $user->id);
                })->where('status', 'pending_approval')->count(),
            ];
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $leaseStats
                ]);
            }
            
            return $leaseStats;
        } catch (\Exception $e) {
            Log::error('Error getting landlord lease analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlord financial summary data for export
     */
    public function getLandlordFinancialSummaryDataExport()
    {
        try {
            $user = Auth::user();
            $financialSummary = $this->getLandlordFinancialSummary($user->id);
            
            // Add additional data for export
            $financialSummary['generated_at'] = now()->toDateTimeString();
            $financialSummary['generated_by'] = $user->name;
            
            return response()->json($financialSummary);
        } catch (\Exception $e) {
            Log::error('Error exporting landlord financial summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tenant Dashboard with multi-role support
     */
    public function tenantDashboard()
    {
        $redirect = $this->verifyDashboardRole('tenant');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $systemSettings = SystemSetting::getSettings();
        
        $tenantStats = [
            'current_rental' => $this->getCurrentRental($user),
            'payment_history' => $this->getTenantPaymentCount($user),
            'pending_payments' => $this->getTenantPendingPayments($user),
            'maintenance_requests' => $this->getMaintenanceRequestCount($user),
            'pending_maintenance' => $this->getPendingMaintenanceCount($user),
        ];
        
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('tenant.dashboard', compact('systemSettings', 'tenantStats', 'userRoles', 'isMultiRoleUser'));
    }

    /**
     * Field Agent Dashboard with comprehensive statistics
     */
    public function fieldAgentDashboard()
    {
        $redirect = $this->verifyDashboardRole('field-agent');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $settings = SystemSetting::first() ?? new SystemSetting();
        
        // Get comprehensive field agent statistics
        $stats = $this->getFieldAgentDashboardStats($user);
        
        // Get assigned registration plans
        $assignedPlans = $this->getFieldAgentAssignedPlans($user);
        
        // Get recently registered properties
        $recentProperties = $this->getFieldAgentRecentProperties($user);
        
        // Get pending tasks
        $pendingTasks = $this->getFieldAgentPendingTasks($user);
        
        // Get performance metrics
        $performanceMetrics = $this->getFieldAgentPerformanceMetrics($user);
        
        // Get recent activities
        $recentActivities = $this->getFieldAgentRecentActivities($user);
        
        // Get chart data
        $chartData = $this->getFieldAgentChartData($user);
        
        // Get notifications
        $notifications = $user->notifications()->latest()->limit(10)->get();
        $unreadCount = $user->unreadNotifications()->count();
        
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('field-agent.dashboard', compact(
            'user',
            'settings',
            'stats',
            'assignedPlans',
            'recentProperties',
            'pendingTasks',
            'performanceMetrics',
            'recentActivities',
            'notifications',
            'unreadCount',
            'chartData',
            'userRoles',
            'isMultiRoleUser'
        ));
    }

    /**
     * Get dashboard statistics for field agent
     */
    protected function getFieldAgentDashboardStats($user)
    {
        try {
            $hasVerificationStatus = Schema::hasColumn('properties', 'verification_status');
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');

            // Properties registered by this agent
            $query = Property::query();
            if ($hasRegisteredBy) {
                $query->where('registered_by', $user->id);
            }
            $totalPropertiesRegistered = $query->count();
            
            // Properties registered this month
            $queryMonth = Property::query();
            if ($hasRegisteredBy) {
                $queryMonth->where('registered_by', $user->id);
            }
            $propertiesThisMonth = $queryMonth
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            
            // Properties registered this week
            $queryWeek = Property::query();
            if ($hasRegisteredBy) {
                $queryWeek->where('registered_by', $user->id);
            }
            $propertiesThisWeek = $queryWeek
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();
            
            // Properties registered today
            $queryToday = Property::query();
            if ($hasRegisteredBy) {
                $queryToday->where('registered_by', $user->id);
            }
            $propertiesToday = $queryToday
                ->whereDate('created_at', today())
                ->count();
            
            // Active registration plans assigned
            $activePlansCount = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->count();
            
            // Properties pending verification
            $pendingVerifications = 0;
            $completedVerifications = 0;
            if ($hasVerificationStatus && $hasRegisteredBy) {
                $pendingVerifications = Property::where('registered_by', $user->id)
                    ->where('verification_status', 'pending')
                    ->count();
                
                $completedVerifications = Property::where('registered_by', $user->id)
                    ->where('verification_status', 'verified')
                    ->count();
            }
            
            // Success rate
            $successRate = $totalPropertiesRegistered > 0 ? 100 : 0;
            
            // Registration plan progress
            $planProgress = [];
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            if (!empty($planIds)) {
                $plans = RegistrationPlan::whereIn('id', $planIds)->get();
                foreach ($plans as $plan) {
                    $registeredCount = Property::where('registration_plan_id', $plan->id)->count();
                    $planProgress[] = [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                        'total_properties' => $plan->estimated_houses ?? 0,
                        'registered_properties' => $registeredCount,
                        'completion_percentage' => ($plan->estimated_houses ?? 0) > 0 
                            ? round(($registeredCount / ($plan->estimated_houses ?? 1)) * 100, 1)
                            : 0,
                        'deadline' => $plan->end_date,
                        'is_overdue' => $plan->end_date && $plan->end_date < now(),
                    ];
                }
            }

            return [
                'total_properties_registered' => $totalPropertiesRegistered,
                'properties_this_month' => $propertiesThisMonth,
                'properties_this_week' => $propertiesThisWeek,
                'properties_today' => $propertiesToday,
                'active_plans_count' => $activePlansCount,
                'pending_verifications' => $pendingVerifications,
                'completed_verifications' => $completedVerifications,
                'success_rate' => $successRate,
                'plan_progress' => $planProgress,
                'has_active_plans' => $activePlansCount > 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting field agent dashboard stats: ' . $e->getMessage());
            return $this->getEmptyFieldAgentStats();
        }
    }

    /**
     * Get assigned registration plans for field agent
     */
    protected function getFieldAgentAssignedPlans($user)
    {
        try {
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            if (empty($planIds)) {
                return collect();
            }
            
            $plans = RegistrationPlan::whereIn('id', $planIds)
                ->whereNull('deleted_at')
                ->get();
            
            foreach ($plans as $plan) {
                $registeredCount = Property::where('registration_plan_id', $plan->id)->count();
                $plan->assigned_properties = 0;
                $plan->registered_properties = $registeredCount;
                $plan->completion_percentage = ($plan->estimated_houses ?? 0) > 0 
                    ? round(($registeredCount / ($plan->estimated_houses ?? 1)) * 100, 1)
                    : 0;
            }
            
            return $plans;
        } catch (\Exception $e) {
            Log::error('Error getting assigned registration plans: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get recently registered properties by field agent
     */
    protected function getFieldAgentRecentProperties($user, $limit = 10)
    {
        try {
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');
            
            $query = Property::query();
            if ($hasRegisteredBy) {
                $query->where('registered_by', $user->id);
            }
            
            return $query->with(['landlord', 'registrationPlan', 'propertyType'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($property) {
                    $property->formatted_created_at = $property->created_at->diffForHumans();
                    return $property;
                });
        } catch (\Exception $e) {
            Log::error('Error getting recently registered properties: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get pending tasks for field agent
     */
    protected function getFieldAgentPendingTasks($user)
    {
        try {
            $tasks = [];
            $hasVerificationStatus = Schema::hasColumn('properties', 'verification_status');
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');

            // Properties pending verification
            if ($hasVerificationStatus && $hasRegisteredBy) {
                $pendingVerifications = Property::where('registered_by', $user->id)
                    ->where('verification_status', 'pending')
                    ->count();
                
                if ($pendingVerifications > 0) {
                    $tasks[] = [
                        'type' => 'verification',
                        'title' => 'Properties Pending Verification',
                        'count' => $pendingVerifications,
                        'icon' => 'clipboard-check',
                        'color' => 'warning',
                        'route' => route('field-agent.verifications'),
                    ];
                }
            }

            // Registration plans with pending registrations
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            if (!empty($planIds)) {
                $plans = RegistrationPlan::whereIn('id', $planIds)->get();
                foreach ($plans as $plan) {
                    $registeredCount = Property::where('registration_plan_id', $plan->id)->count();
                    $remaining = ($plan->estimated_houses ?? 0) - $registeredCount;
                    if ($remaining > 0) {
                        $tasks[] = [
                            'type' => 'registration',
                            'title' => $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                            'subtitle' => "{$remaining} properties remaining to register",
                            'count' => $remaining,
                            'icon' => 'building',
                            'color' => 'primary',
                            'route' => route('field-agent.registration-plans.show', $plan->id),
                            'progress' => ($plan->estimated_houses ?? 0) > 0 
                                ? round(($registeredCount / ($plan->estimated_houses ?? 1)) * 100, 1)
                                : 0,
                        ];
                    }
                }
            }

            return $tasks;
        } catch (\Exception $e) {
            Log::error('Error getting pending tasks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get performance metrics for field agent
     */
    protected function getFieldAgentPerformanceMetrics($user)
    {
        try {
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');

            // Get monthly registration counts for the last 6 months
            $monthlyRegistrations = [];
            $months = [];
            
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $months[] = $date->format('M Y');
                
                $query = Property::query();
                if ($hasRegisteredBy) {
                    $query->where('registered_by', $user->id);
                }
                $count = $query
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();
                
                $monthlyRegistrations[] = $count;
            }

            // Daily registrations for current week
            $dailyRegistrations = [];
            $days = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $days[] = $date->format('D');
                
                $query = Property::query();
                if ($hasRegisteredBy) {
                    $query->where('registered_by', $user->id);
                }
                $count = $query
                    ->whereDate('created_at', $date->toDateString())
                    ->count();
                
                $dailyRegistrations[] = $count;
            }

            // Total assigned properties
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            $totalAssignedProperties = 0;
            if (!empty($planIds)) {
                $totalAssignedProperties = RegistrationPlan::whereIn('id', $planIds)->sum('estimated_houses');
            }

            // Monthly target (20% of total assigned properties, minimum 5)
            $monthlyTarget = max(5, round($totalAssignedProperties * 0.2));
            
            $queryActual = Property::query();
            if ($hasRegisteredBy) {
                $queryActual->where('registered_by', $user->id);
            }
            $monthlyActual = $queryActual
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            
            $targetAchievement = $monthlyTarget > 0 
                ? round(($monthlyActual / $monthlyTarget) * 100, 1)
                : 0;

            // Quarterly target data
            $quarterlyTarget = $monthlyTarget * 3;
            
            $queryQuarterlyActual = Property::query();
            if ($hasRegisteredBy) {
                $queryQuarterlyActual->where('registered_by', $user->id);
            }
            $quarterlyActual = $queryQuarterlyActual
                ->whereBetween('created_at', [now()->startOfQuarter(), now()->endOfQuarter()])
                ->count();
            
            $quarterlyAchievement = $quarterlyTarget > 0 
                ? round(($quarterlyActual / $quarterlyTarget) * 100, 1)
                : 0;

            // Yearly target data
            $yearlyTarget = $monthlyTarget * 12;
            
            $queryYearlyActual = Property::query();
            if ($hasRegisteredBy) {
                $queryYearlyActual->where('registered_by', $user->id);
            }
            $yearlyActual = $queryYearlyActual
                ->whereYear('created_at', now()->year)
                ->count();
            
            $yearlyAchievement = $yearlyTarget > 0 
                ? round(($yearlyActual / $yearlyTarget) * 100, 1)
                : 0;

            // Plan completion rates
            $planCompletionRates = collect();
            $totalPlansCompleted = 0;
            
            if (!empty($planIds)) {
                $plans = RegistrationPlan::whereIn('id', $planIds)->get();
                foreach ($plans as $plan) {
                    $registeredCount = Property::where('registration_plan_id', $plan->id)->count();
                    $totalProperties = $plan->estimated_houses ?? 0;
                    $completionPercentage = $totalProperties > 0 
                        ? round(($registeredCount / $totalProperties) * 100, 1)
                        : 0;
                    
                    $planCompletionRates->push([
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                        'completed' => $registeredCount,
                        'total' => $totalProperties,
                        'percentage' => $completionPercentage,
                        'is_completed' => $registeredCount >= $totalProperties && $totalProperties > 0,
                    ]);
                    
                    if ($registeredCount >= $totalProperties && $totalProperties > 0) {
                        $totalPlansCompleted++;
                    }
                }
            }

            // Average registration time
            $averageRegistrationTime = null;
            $recentPropertiesQuery = Property::query();
            if ($hasRegisteredBy) {
                $recentPropertiesQuery->where('registered_by', $user->id);
            }
            $recentProperties = $recentPropertiesQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            if ($recentProperties->count() > 1) {
                $totalTimeDiff = 0;
                $previousProperty = null;
                foreach ($recentProperties as $property) {
                    if ($previousProperty) {
                        $diffInMinutes = $previousProperty->created_at->diffInMinutes($property->created_at);
                        $totalTimeDiff += $diffInMinutes;
                    }
                    $previousProperty = $property;
                }
                $averageRegistrationTime = round($totalTimeDiff / ($recentProperties->count() - 1));
            }

            // Best performing day
            $bestPerformingDay = null;
            $dayPerformance = [];
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            foreach ($daysOfWeek as $day) {
                $query = Property::query();
                if ($hasRegisteredBy) {
                    $query->where('registered_by', $user->id);
                }
                $count = $query
                    ->whereRaw('DAYOFWEEK(created_at) = ?', [array_search($day, $daysOfWeek) + 1])
                    ->count();
                $dayPerformance[$day] = $count;
            }
            
            if (!empty($dayPerformance)) {
                $bestPerformingDay = array_keys($dayPerformance, max($dayPerformance))[0] ?? null;
            }

            return [
                'monthly_registrations' => $monthlyRegistrations,
                'month_labels' => $months,
                'daily_registrations' => $dailyRegistrations,
                'day_labels' => $days,
                'monthly_target' => $monthlyTarget,
                'monthly_actual' => $monthlyActual,
                'target_achievement' => $targetAchievement,
                'quarterly_target' => $quarterlyTarget,
                'quarterly_actual' => $quarterlyActual,
                'quarterly_achievement' => $quarterlyAchievement,
                'yearly_target' => $yearlyTarget,
                'yearly_actual' => $yearlyActual,
                'yearly_achievement' => $yearlyAchievement,
                'plan_completion_rates' => $planCompletionRates,
                'total_plans_completed' => $totalPlansCompleted,
                'average_registration_time' => $averageRegistrationTime,
                'best_performing_day' => $bestPerformingDay,
                'total_assigned_properties' => $totalAssignedProperties,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting performance metrics: ' . $e->getMessage());
            return [
                'monthly_registrations' => array_fill(0, 6, 0),
                'month_labels' => [],
                'daily_registrations' => array_fill(0, 7, 0),
                'day_labels' => [],
                'average_registration_time' => null,
                'plan_completion_rates' => collect(),
                'monthly_target' => 0,
                'monthly_actual' => 0,
                'target_achievement' => 0,
                'best_performing_day' => null,
                'total_plans_completed' => 0,
                'quarterly_target' => 0,
                'quarterly_actual' => 0,
                'quarterly_achievement' => 0,
                'yearly_target' => 0,
                'yearly_actual' => 0,
                'yearly_achievement' => 0,
                'total_assigned_properties' => 0,
            ];
        }
    }

    /**
     * Get recent activities for field agent
     */
    protected function getFieldAgentRecentActivities($user, $limit = 20)
    {
        try {
            $activities = [];
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');

            // Property registrations
            $query = Property::query();
            if ($hasRegisteredBy) {
                $query->where('registered_by', $user->id);
            }
            $properties = $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            foreach ($properties as $property) {
                $activities[] = [
                    'type' => 'property_registered',
                    'title' => 'Property Registered',
                    'description' => "Registered property: {$property->property_name}",
                    'icon' => 'building',
                    'color' => 'success',
                    'timestamp' => $property->created_at,
                    'formatted_time' => $property->created_at->diffForHumans(),
                    'link' => route('properties.show', $property->id),
                ];
            }

            // Plan assignments
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            if (!empty($planIds)) {
                $plans = RegistrationPlan::whereIn('id', $planIds)->get();
                foreach ($plans as $plan) {
                    $activities[] = [
                        'type' => 'plan_assigned',
                        'title' => 'Registration Plan Assigned',
                        'description' => "Assigned to plan: {$plan->zone} - {$plan->section}",
                        'icon' => 'clipboard-list',
                        'color' => 'info',
                        'timestamp' => $plan->created_at,
                        'formatted_time' => $plan->created_at->diffForHumans(),
                        'link' => route('field-agent.registration-plans.show', $plan->id),
                    ];
                }
            }

            // Sort by timestamp and limit
            $activities = collect($activities)
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values()
                ->toArray();

            return $activities;
        } catch (\Exception $e) {
            Log::error('Error getting recent activities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get chart data for field agent dashboard
     */
    protected function getFieldAgentChartData($user)
    {
        try {
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');

            // Registration trend for last 12 months
            $registrationTrend = [];
            $trendLabels = [];
            
            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $trendLabels[] = $date->format('M Y');
                
                $query = Property::query();
                if ($hasRegisteredBy) {
                    $query->where('registered_by', $user->id);
                }
                $count = $query
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();
                
                $registrationTrend[] = $count;
            }
            
            // Weekly performance
            $weeklyPerformance = [];
            $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $currentWeekStart = now()->startOfWeek();
            
            foreach ($weekDays as $index => $day) {
                $date = $currentWeekStart->copy()->addDays($index);
                $query = Property::query();
                if ($hasRegisteredBy) {
                    $query->where('registered_by', $user->id);
                }
                $count = $query
                    ->whereDate('created_at', $date->toDateString())
                    ->count();
                
                $weeklyPerformance[] = $count;
            }

            // Plan completion status
            $planIds = DB::table('plan_agent_assignments')
                ->where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();
            
            $planStatus = collect();
            
            if (!empty($planIds)) {
                $plans = RegistrationPlan::whereIn('id', $planIds)
                    ->whereNull('deleted_at')
                    ->get();
                
                foreach ($plans as $plan) {
                    $registeredCount = Property::where('registration_plan_id', $plan->id)->count();
                    $totalProperties = $plan->estimated_houses ?? 0;
                    
                    $planStatus->push([
                        'name' => $plan->zone . ($plan->section ? ' - ' . $plan->section : ''),
                        'completed' => $registeredCount,
                        'remaining' => max(0, $totalProperties - $registeredCount),
                        'total' => $totalProperties,
                    ]);
                }
            }

            return [
                'registration_trend' => $registrationTrend,
                'trend_labels' => $trendLabels,
                'weekly_performance' => $weeklyPerformance,
                'week_labels' => $weekDays,
                'plan_status' => $planStatus,
                'has_real_data' => array_sum($registrationTrend) > 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting chart data: ' . $e->getMessage());
            
            $defaultLabels = [];
            for ($i = 11; $i >= 0; $i--) {
                $defaultLabels[] = now()->subMonths($i)->format('M Y');
            }
            
            return [
                'registration_trend' => array_fill(0, 12, 0),
                'trend_labels' => $defaultLabels,
                'weekly_performance' => array_fill(0, 7, 0),
                'week_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'plan_status' => collect(),
                'has_real_data' => false,
            ];
        }
    }

    /**
     * Get empty stats array for error cases
     */
    protected function getEmptyFieldAgentStats()
    {
        return [
            'total_properties_registered' => 0,
            'properties_this_month' => 0,
            'properties_this_week' => 0,
            'properties_today' => 0,
            'active_plans_count' => 0,
            'pending_verifications' => 0,
            'completed_verifications' => 0,
            'success_rate' => 0,
            'plan_progress' => [],
            'has_active_plans' => false,
        ];
    }

    // ==================== FIELD AGENT VERIFICATION METHODS ====================

    /**
     * Display verifications page for field agent
     */
    public function fieldAgentVerifications()
    {
        $redirect = $this->verifyDashboardRole('field-agent');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');
        
        try {
            $query = Property::where('verification_status', 'pending');
            
            if ($hasRegisteredBy) {
                $query->where('registered_by', $user->id);
            }
            
            $pendingProperties = $query->with(['landlord', 'propertyType', 'registrationPlan'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            $weeklyPending = Property::where('verification_status', 'pending')
                ->where('registered_by', $user->id)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();
            
            $urgentPending = Property::where('verification_status', 'pending')
                ->where('registered_by', $user->id)
                ->where('created_at', '<', now()->subDays(7))
                ->count();
            
            $totalVerified = Property::where('verification_status', 'verified')
                ->where('registered_by', $user->id)
                ->count();
            
            $totalSubmitted = Property::where('registered_by', $user->id)->count();
            $verificationRate = $totalSubmitted > 0 
                ? round(($totalVerified / $totalSubmitted) * 100, 1)
                : 0;
            
            $userRoles = $user->getRoleSlugsAttribute();
            $isMultiRoleUser = count($userRoles) > 1;
            
            return view('field-agent.verifications.index', compact(
                'pendingProperties',
                'weeklyPending',
                'urgentPending',
                'verificationRate',
                'userRoles',
                'isMultiRoleUser'
            ));
            
        } catch (\Exception $e) {
            Log::error('Verifications page error: ' . $e->getMessage());
            
            $pendingProperties = collect([]);
            $weeklyPending = 0;
            $urgentPending = 0;
            $verificationRate = 0;
            $userRoles = $user->getRoleSlugsAttribute();
            $isMultiRoleUser = count($userRoles) > 1;
            
            return view('field-agent.verifications.index', compact(
                'pendingProperties',
                'weeklyPending',
                'urgentPending',
                'verificationRate',
                'userRoles',
                'isMultiRoleUser'
            ));
        }
    }

    /**
     * Bulk verify properties (AJAX)
     */
    public function fieldAgentBulkVerify(Request $request)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            $request->validate([
                'property_ids' => 'required|array',
                'property_ids.*' => 'exists:properties,id',
                'verification_status' => 'required|in:verified,rejected'
            ]);
            
            $user = Auth::user();
            $hasRegisteredBy = Schema::hasColumn('properties', 'registered_by');
            
            $query = Property::whereIn('id', $request->property_ids);
            if ($hasRegisteredBy) {
                $query->where('registered_by', $user->id);
            }
            
            $updateData = [
                'verification_status' => $request->verification_status,
                'updated_at' => now(),
            ];
            
            if (Schema::hasColumn('properties', 'verified_at')) {
                $updateData['verified_at'] = now();
            }
            
            if (Schema::hasColumn('properties', 'verified_by')) {
                $updateData['verified_by'] = $user->id;
            }
            
            $updated = $query->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => "{$updated} properties have been verified.",
                'count' => $updated
            ]);
            
        } catch (\Exception $e) {
            Log::error('Bulk verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk verification.'
            ], 500);
        }
    }

    /**
     * Single property verification
     */
    public function fieldAgentSingleVerify(Request $request)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'verification_status' => 'required|in:verified,rejected,pending_review'
            ]);
            
            $property = Property::where('id', $request->property_id)
                ->where('registered_by', Auth::id())
                ->first();
            
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found or you do not have permission to verify it'
                ], 404);
            }
            
            $updateData = [
                'verification_status' => $request->verification_status,
                'updated_at' => now(),
            ];
            
            if (Schema::hasColumn('properties', 'verified_at')) {
                $updateData['verified_at'] = now();
            }
            
            if (Schema::hasColumn('properties', 'verified_by')) {
                $updateData['verified_by'] = Auth::id();
            }
            
            if (Schema::hasColumn('properties', 'verification_notes') && $request->verification_notes) {
                $updateData['verification_notes'] = $request->verification_notes;
            }
            
            if (Schema::hasColumn('properties', 'rejection_reason') && $request->rejection_reason) {
                $updateData['rejection_reason'] = $request->rejection_reason;
            }
            
            $updated = DB::table('properties')
                ->where('id', $property->id)
                ->update($updateData);
            
            if ($updated) {
                cache()->forget('field_agent_dashboard_' . Auth::id());
                
                return response()->json([
                    'success' => true,
                    'message' => 'Property verified successfully!',
                    'data' => [
                        'id' => $property->id,
                        'verification_status' => $request->verification_status
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update property status. No rows were affected.'
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh verification data (AJAX)
     */
    public function fieldAgentRefreshVerifications(Request $request)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            $user = Auth::user();
            
            $total = Property::where('registered_by', $user->id)
                ->where('verification_status', 'pending')
                ->count();
            
            $weekly = Property::where('registered_by', $user->id)
                ->where('verification_status', 'pending')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();
            
            $urgent = Property::where('registered_by', $user->id)
                ->where('verification_status', 'pending')
                ->where('created_at', '<', now()->subDays(7))
                ->count();
            
            $totalVerified = Property::where('registered_by', $user->id)
                ->where('verification_status', 'verified')
                ->count();
            
            $totalSubmitted = Property::where('registered_by', $user->id)->count();
            $rate = $totalSubmitted > 0 ? round(($totalVerified / $totalSubmitted) * 100, 1) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'weekly' => $weekly,
                    'urgent' => $urgent,
                    'rate' => $rate
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification details for a property
     */
    public function fieldAgentGetVerificationDetails($propertyId)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            $property = Property::where('id', $propertyId)
                ->where('registered_by', Auth::id())
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $property->id,
                    'name' => $property->property_name,
                    'address' => $property->street_name,
                    'status' => $property->verification_status,
                    'registered_at' => $property->created_at->toIso8601String(),
                    'days_pending' => $property->created_at->diffInDays(now())
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }
    }

    // ==================== FIELD AGENT REGISTRATION PLAN METHODS ====================

    /**
     * Display registration plans for field agent
     */
    public function fieldAgentRegistrationPlans()
    {
        $redirect = $this->verifyDashboardRole('field-agent');
        if ($redirect) return $redirect;
        
        $planIds = DB::table('plan_agent_assignments')
            ->where('agent_id', Auth::id())
            ->where('is_active', true)
            ->pluck('plan_id')
            ->toArray();
        
        $assignments = collect();
        if (!empty($planIds)) {
            $plans = RegistrationPlan::whereIn('id', $planIds)->get();
            foreach ($plans as $plan) {
                $assignments->push((object)[
                    'registrationPlan' => $plan,
                    'id' => null,
                    'created_at' => $plan->created_at,
                ]);
            }
        }
        
        $completedPlans = collect();
        $userRoles = Auth::user()->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('field-agent.registration-plans.index', compact('assignments', 'completedPlans', 'userRoles', 'isMultiRoleUser'));
    }

    /**
     * Show registration plan details
     */
    public function fieldAgentShowRegistrationPlan($id)
    {
        $redirect = $this->verifyDashboardRole('field-agent');
        if ($redirect) return $redirect;
        
        $plan = RegistrationPlan::findOrFail($id);
        
        $registeredProperties = Property::where('registered_by', Auth::id())
            ->where('registration_plan_id', $id)
            ->with('landlord')
            ->paginate(15);
        
        $progress = [
            'total' => $plan->estimated_houses ?? 0,
            'registered' => $registeredProperties->total(),
            'remaining' => ($plan->estimated_houses ?? 0) - $registeredProperties->total(),
            'percentage' => ($plan->estimated_houses ?? 0) > 0 
                ? round(($registeredProperties->total() / ($plan->estimated_houses ?? 1)) * 100, 1)
                : 0
        ];
        
        $userRoles = Auth::user()->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('field-agent.registration-plans.show', compact('plan', 'registeredProperties', 'progress', 'userRoles', 'isMultiRoleUser'));
    }

    /**
     * Plan progress (alias for showRegistrationPlan)
     */
    public function fieldAgentPlanProgress($id)
    {
        return $this->fieldAgentShowRegistrationPlan($id);
    }

    // ==================== FIELD AGENT API METHODS ====================

    /**
     * API endpoint to get dashboard data via AJAX
     */
    public function fieldAgentGetDashboardData(Request $request)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            $user = Auth::user();
            $dataType = $request->get('type', 'overview');
            
            switch ($dataType) {
                case 'stats':
                    $data = $this->getFieldAgentDashboardStats($user);
                    break;
                case 'plans':
                    $data = $this->getFieldAgentAssignedPlans($user);
                    break;
                case 'properties':
                    $data = $this->getFieldAgentRecentProperties($user, $request->get('limit', 10));
                    break;
                case 'tasks':
                    $data = $this->getFieldAgentPendingTasks($user);
                    break;
                case 'performance':
                    $data = $this->getFieldAgentPerformanceMetrics($user);
                    break;
                case 'activities':
                    $data = $this->getFieldAgentRecentActivities($user, $request->get('limit', 20));
                    break;
                case 'chart_data':
                    $data = $this->getFieldAgentChartData($user);
                    break;
                default:
                    $data = [
                        'stats' => $this->getFieldAgentDashboardStats($user),
                        'plans' => $this->getFieldAgentAssignedPlans($user),
                        'properties' => $this->getFieldAgentRecentProperties($user, 5),
                        'tasks' => $this->getFieldAgentPendingTasks($user),
                        'performance' => $this->getFieldAgentPerformanceMetrics($user),
                        'activities' => $this->getFieldAgentRecentActivities($user, 10),
                        'chart_data' => $this->getFieldAgentChartData($user),
                    ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Field Agent Dashboard API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh field agent dashboard data
     */
    public function fieldAgentRefreshDashboard(Request $request)
    {
        try {
            $redirect = $this->verifyDashboardRole('field-agent');
            if ($redirect) return $redirect;
            
            cache()->forget('field_agent_dashboard_' . Auth::id());
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data refreshed successfully',
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Field Agent Dashboard Refresh Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh dashboard data'
            ], 500);
        }
    }

    /**
     * Security Personnel Dashboard with multi-role support
     */
    public function securityPersonnelDashboard()
    {
        $redirect = $this->verifyDashboardRole('security-personnel');
        if ($redirect) return $redirect;
        
        $user = Auth::user();
        $systemSettings = SystemSetting::getSettings();
        
        $securityStats = [
            'active_patrols' => 0,
            'reported_incidents' => 0,
            'access_logs_today' => 0,
            'pending_handovers' => 0,
        ];
        
        try {
            if (class_exists('\App\Models\SecuritySchedule')) {
                $securityStats['active_patrols'] = \App\Models\SecuritySchedule::where('status', 'active')->count();
                $securityStats['pending_handovers'] = \App\Models\SecuritySchedule::where('status', 'pending_handover')->count();
            }
        } catch (\Exception $e) {
            Log::warning('SecuritySchedule model not available: ' . $e->getMessage());
        }
        
        try {
            if (class_exists('\App\Models\SecurityReport')) {
                $securityStats['reported_incidents'] = \App\Models\SecurityReport::whereDate('created_at', today())->count();
            }
        } catch (\Exception $e) {
            Log::warning('SecurityReport model not available: ' . $e->getMessage());
        }
        
        try {
            if (class_exists('\App\Models\AccessLog')) {
                $securityStats['access_logs_today'] = \App\Models\AccessLog::whereDate('created_at', today())->count();
            }
        } catch (\Exception $e) {
            Log::warning('AccessLog model not available: ' . $e->getMessage());
        }
        
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        $isSupervisor = false;
        $supervisorLevel = null;
        
        if (method_exists($user, 'isSecuritySupervisor')) {
            $isSupervisor = $user->isSecuritySupervisor();
            $supervisorLevel = $user->supervisor_level ?? null;
        }
        
        $isPropertyOwner = $user->isPropertyOwner();
        $ownedPropertiesCount = $user->ownedProperties()->count();
        
        return view('security.dashboard', compact(
            'systemSettings', 
            'securityStats', 
            'userRoles', 
            'isMultiRoleUser',
            'isSupervisor',
            'supervisorLevel',
            'isPropertyOwner',
            'ownedPropertiesCount'
        ));
    }

    // ==================== SUPER ADMIN HELPER METHODS ====================

    protected function getFilteredDashboardData($searchTerm, $category)
    {
        if (!$searchTerm || strlen($searchTerm) < 2) {
            return null;
        }
        
        $results = [];
        
        switch ($category) {
            case 'users':
                $results['users'] = User::where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->orWhere('phone', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'properties':
                $results['properties'] = Property::where('property_name', 'like', "%{$searchTerm}%")
                    ->orWhere('registration_pattern', 'like', "%{$searchTerm}%")
                    ->orWhere('digital_address', 'like', "%{$searchTerm}%")
                    ->orWhere('street_name', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'property-units':
                $results['property_units'] = PropertyUnit::where('unit_number', 'like', "%{$searchTerm}%")
                    ->orWhere('unit_type', 'like', "%{$searchTerm}%")
                    ->orWhere('registration_pattern', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'payments':
                $results['payments'] = Payment::where('transaction_id', 'like', "%{$searchTerm}%")
                    ->orWhere('reference_number', 'like', "%{$searchTerm}%")
                    ->orWhere('amount', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'invoices':
                $results['invoices'] = Invoice::where('invoice_number', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('amount', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'registration-plans':
                $results['registration_plans'] = RegistrationPlan::where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('naming_pattern', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'construction-registrations':
                if (class_exists('App\Models\LandlordConstructionRegistration')) {
                    $results['construction_registrations'] = LandlordConstructionRegistration::where('property_address', 'like', "%{$searchTerm}%")
                        ->orWhere('developer_name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->limit(20)
                        ->get();
                }
                break;
                
            case 'security-posts':
                if (class_exists('App\Models\SecurityPost')) {
                    $results['security_posts'] = SecurityPost::where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('location', 'like', "%{$searchTerm}%")
                        ->limit(20)
                        ->get();
                }
                break;
                
            case 'security-reports':
                if (class_exists('App\Models\SecurityReport')) {
                    $results['security_reports'] = SecurityReport::where('title', 'like', "%{$searchTerm}%")
                        ->orWhere('report_type', 'like', "%{$searchTerm}%")
                        ->limit(20)
                        ->get();
                }
                break;
                
            case 'ownership-transfers':
                if (class_exists('App\Models\PropertyOwnershipTransfer')) {
                    $results['ownership_transfers'] = PropertyOwnershipTransfer::whereHas('property', function($q) use ($searchTerm) {
                        $q->where('property_name', 'like', "%{$searchTerm}%");
                    })->orWhere('transfer_reason', 'like', "%{$searchTerm}%")
                        ->limit(20)
                        ->get();
                }
                break;
                
            default:
                $results['users'] = User::where('name', 'like', "%{$searchTerm}%")->limit(5)->get();
                $results['properties'] = Property::where('property_name', 'like', "%{$searchTerm}%")->limit(5)->get();
                $results['payments'] = Payment::where('transaction_id', 'like', "%{$searchTerm}%")->limit(5)->get();
                $results['invoices'] = Invoice::where('invoice_number', 'like', "%{$searchTerm}%")->limit(5)->get();
                $results['registration_plans'] = RegistrationPlan::where('name', 'like', "%{$searchTerm}%")->limit(5)->get();
                break;
        }
        
        return $results;
    }

    /**
     * Get comprehensive user statistics (excludes developers)
     * FIXED: Landlords now counted by ROLE (not just legacy type)
     */
    public function getUserStatistics()
    {
        try {
            return [
                'total_users' => User::where('type', '!=', User::TYPE_DEVELOPER)->count(),
                'active_users' => User::where('type', '!=', User::TYPE_DEVELOPER)->where('status', 'active')->count(),
                'pending_users' => User::where('type', '!=', User::TYPE_DEVELOPER)->where('status', 'pending')->count(),
                'suspended_users' => User::where('type', '!=', User::TYPE_DEVELOPER)->where('status', 'suspended')->count(),
                'super_admins' => User::where('type', User::TYPE_SUPER_ADMIN)->count(),
                'admins' => User::where('type', User::TYPE_ADMIN)->count(),
                'landlords' => User::where(function($query) {
                    $query->where('type', User::TYPE_LANDLORD)
                          ->orWhereHas('roles', function($q) {
                              $q->where('slug', 'landlord');
                          });
                })->count(),
                'tenants' => User::where('type', User::TYPE_TENANT)->count(),
                'field_agents' => User::where('type', User::TYPE_FIELD_AGENT)->count(),
                'developers' => User::where('type', User::TYPE_DEVELOPER)->count(),
                'security_checkpoints' => User::where('type', User::TYPE_SECURITY_PERSONNEL)->count(),
                'new_users_today' => User::where('type', '!=', User::TYPE_DEVELOPER)->whereDate('created_at', today())->count(),
                'new_users_week' => User::where('type', '!=', User::TYPE_DEVELOPER)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user statistics: ' . $e->getMessage());
            return $this->getEmptyUserStatistics();
        }
    }

    protected function getEmptyUserStatistics()
    {
        return [
            'total_users' => 0,
            'active_users' => 0,
            'pending_users' => 0,
            'suspended_users' => 0,
            'super_admins' => 0,
            'admins' => 0,
            'landlords' => 0,
            'tenants' => 0,
            'field_agents' => 0,
            'developers' => 0,
            'security_checkpoints' => 0,
            'new_users_today' => 0,
            'new_users_week' => 0,
        ];
    }

    public function getPaymentStatistics($settings = null)
    {
        try {
            if (!$settings) {
                $settings = SystemSetting::first() ?? new SystemSetting();
            }

            $totalRevenue = Payment::where('status', 'completed')->sum('amount') ?? 0;
            $todayRevenue = Payment::where('status', 'completed')->whereDate('created_at', today())->sum('amount') ?? 0;
            $monthlyRevenue = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount') ?? 0;
            $weeklyRevenue = Payment::where('status', 'completed')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount') ?? 0;

            $formattedTotal = $settings->formatAmount($totalRevenue);
            $formattedToday = $settings->formatAmount($todayRevenue);
            $formattedMonthly = $settings->formatAmount($monthlyRevenue);
            $formattedWeekly = $settings->formatAmount($weeklyRevenue);

            $paymentMethods = Payment::where('status', 'completed')
                ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->payment_method => [
                        'count' => $item->count,
                        'total' => $item->total
                    ]];
                });

            return [
                'total_revenue' => $totalRevenue,
                'formatted_total_revenue' => $formattedTotal,
                'today_revenue' => $todayRevenue,
                'formatted_today_revenue' => $formattedToday,
                'monthly_revenue' => $monthlyRevenue,
                'formatted_monthly_revenue' => $formattedMonthly,
                'weekly_revenue' => $weeklyRevenue,
                'formatted_weekly_revenue' => $formattedWeekly,
                'total_payments' => Payment::count(),
                'pending_payments' => Payment::where('status', 'pending')->count(),
                'failed_payments' => Payment::where('status', 'failed')->count(),
                'completed_payments' => Payment::where('status', 'completed')->count(),
                'payment_methods' => $paymentMethods,
                'currency_info' => $settings->getCurrencyInfo() ?? [
                    'symbol' => '$',
                    'code' => 'USD',
                    'position' => 'left'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting payment statistics: ' . $e->getMessage());
            $fallbackSymbol = $settings->currency_symbol ?? '$';
            return [
                'total_revenue' => 0,
                'formatted_total_revenue' => $fallbackSymbol . '0.00',
                'today_revenue' => 0,
                'formatted_today_revenue' => $fallbackSymbol . '0.00',
                'monthly_revenue' => 0,
                'formatted_monthly_revenue' => $fallbackSymbol . '0.00',
                'weekly_revenue' => 0,
                'formatted_weekly_revenue' => $fallbackSymbol . '0.00',
                'total_payments' => 0,
                'pending_payments' => 0,
                'failed_payments' => 0,
                'completed_payments' => 0,
                'payment_methods' => [],
                'currency_info' => [
                    'symbol' => $fallbackSymbol,
                    'code' => $settings->currency_code ?? 'USD',
                    'position' => $settings->currency_position ?? 'left'
                ]
            ];
        }
    }

    public function getPropertyStatistics()
    {
        try {
            return [
                'total_properties' => Property::count(),
                'active_properties' => Property::where('status', 'active')->count(),
                'inactive_properties' => Property::where('status', 'inactive')->count(),
                'pending_properties' => Property::where('status', 'pending')->count(),
                'with_digital_address' => Property::whereNotNull('digital_address')->count(),
                'without_digital_address' => Property::whereNull('digital_address')->count(),
                'properties_with_dues' => Property::has('invoices')->count(),
                'properties_registered_today' => Property::whereDate('created_at', today())->count(),
                'properties_registered_this_week' => Property::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'properties_registered_this_month' => Property::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting property statistics: ' . $e->getMessage());
            return [
                'total_properties' => 0,
                'active_properties' => 0,
                'inactive_properties' => 0,
                'pending_properties' => 0,
                'with_digital_address' => 0,
                'without_digital_address' => 0,
                'properties_with_dues' => 0,
                'properties_registered_today' => 0,
                'properties_registered_this_week' => 0,
                'properties_registered_this_month' => 0,
            ];
        }
    }

    public function getRegistrationPlanStatistics()
    {
        try {
            $totalPlans = RegistrationPlan::count();
            $activePlans = RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])->count();
            $completedPlans = RegistrationPlan::where('status', 'completed')->count();
            $draftPlans = RegistrationPlan::where('status', 'draft')->count();
            $cancelledPlans = RegistrationPlan::where('status', 'cancelled')->count();
            $unassignedPlans = RegistrationPlan::unassigned()->count();
            $overduePlans = RegistrationPlan::overdue()->count();
            
            $multiAgentPlans = RegistrationPlan::multipleAssignment()->count();
            $singleAgentPlans = RegistrationPlan::singleAssignment()->count();
            
            $totalAgentAssignments = PlanAgentAssignment::where('is_active', true)->count();
            $activeAgentAssignments = PlanAgentAssignment::active()->count();
            $propertiesThroughPlans = Property::whereHas('registrationPlan')->count();

            return [
                'total_plans' => $totalPlans,
                'active_plans' => $activePlans,
                'completed_plans' => $completedPlans,
                'draft_plans' => $draftPlans,
                'cancelled_plans' => $cancelledPlans,
                'unassigned_plans' => $unassignedPlans,
                'overdue_plans' => $overduePlans,
                'multi_agent_plans' => $multiAgentPlans,
                'single_assignment_plans' => $singleAgentPlans,
                'total_agent_assignments' => $totalAgentAssignments,
                'active_agent_assignments' => $activeAgentAssignments,
                'properties_through_plans' => $propertiesThroughPlans,
                'status_labels' => ['Draft', 'Assigned', 'In Progress', 'Completed', 'Cancelled'],
                'status_data' => [
                    $draftPlans,
                    RegistrationPlan::where('status', 'assigned')->count(),
                    RegistrationPlan::where('status', 'in_progress')->count(),
                    $completedPlans,
                    $cancelledPlans
                ],
                'assignment_labels' => ['Single Agent', 'Multiple Agents'],
                'assignment_data' => [$singleAgentPlans, $multiAgentPlans],
            ];
        } catch (\Exception $e) {
            Log::error('Error getting registration plan statistics: ' . $e->getMessage());
            return [
                'total_plans' => 0,
                'active_plans' => 0,
                'completed_plans' => 0,
                'draft_plans' => 0,
                'cancelled_plans' => 0,
                'unassigned_plans' => 0,
                'overdue_plans' => 0,
                'multi_agent_plans' => 0,
                'single_assignment_plans' => 0,
                'total_agent_assignments' => 0,
                'active_agent_assignments' => 0,
                'properties_through_plans' => 0,
                'status_labels' => ['Draft', 'Assigned', 'In Progress', 'Completed', 'Cancelled'],
                'status_data' => [0, 0, 0, 0, 0],
                'assignment_labels' => ['Single Agent', 'Multiple Agents'],
                'assignment_data' => [0, 0],
            ];
        }
    }

    public function getInvitationStatistics()
    {
        try {
            $totalAgentInvitations = AgentInvitation::count();
            $pendingAgentInvitations = AgentInvitation::where('status', 'pending')->count();
            $acceptedAgentInvitations = AgentInvitation::where('status', 'accepted')->count();
            $expiredAgentInvitations = AgentInvitation::where('status', 'expired')->count();
            
            $successRate = $totalAgentInvitations > 0 
                ? round(($acceptedAgentInvitations / $totalAgentInvitations) * 100, 2)
                : 0;

            return [
                'agent_invitations' => [
                    'total' => $totalAgentInvitations,
                    'pending' => $pendingAgentInvitations,
                    'accepted' => $acceptedAgentInvitations,
                    'expired' => $expiredAgentInvitations,
                    'success_rate' => $successRate,
                ],
                'overall_status' => $pendingAgentInvitations > 0 ? 'active' : 'idle',
            ];
        } catch (\Exception $e) {
            Log::error('Error getting invitation statistics: ' . $e->getMessage());
            return [
                'agent_invitations' => [
                    'total' => 0,
                    'pending' => 0,
                    'accepted' => 0,
                    'expired' => 0,
                    'success_rate' => 0,
                ],
                'overall_status' => 'error',
            ];
        }
    }

    public function getLandlordInvitationStatistics()
    {
        try {
            $total = LandlordInvitation::count();
            $pending = LandlordInvitation::where('status', 'pending')->count();
            $accepted = LandlordInvitation::where('status', 'accepted')->count();
            $expired = LandlordInvitation::where('status', 'expired')->count();
            
            $successRate = $total > 0 ? round(($accepted / $total) * 100, 2) : 0;

            return [
                'total' => $total,
                'pending' => $pending,
                'accepted' => $accepted,
                'expired' => $expired,
                'success_rate' => $successRate,
                'status' => $pending > 0 ? 'active' : 'idle',
            ];
        } catch (\Exception $e) {
            Log::error('Error getting landlord invitation statistics: ' . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'accepted' => 0,
                'expired' => 0,
                'success_rate' => 0,
                'status' => 'error',
            ];
        }
    }

    public function getUserInvitationStatistics()
    {
        try {
            $total = UserInvitation::count();
            $pending = UserInvitation::where('status', 'pending')->count();
            $accepted = UserInvitation::where('status', 'accepted')->count();
            $expired = UserInvitation::where('status', 'expired')->count();
            
            $successRate = $total > 0 ? round(($accepted / $total) * 100, 2) : 0;

            return [
                'total' => $total,
                'pending' => $pending,
                'accepted' => $accepted,
                'expired' => $expired,
                'success_rate' => $successRate,
                'status' => $pending > 0 ? 'active' : 'idle',
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user invitation statistics: ' . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'accepted' => 0,
                'expired' => 0,
                'success_rate' => 0,
                'status' => 'error',
            ];
        }
    }

    public function getRecentPayments($settings = null, $limit = 5)
    {
        try {
            if (!$settings) {
                $settings = SystemSetting::first() ?? new SystemSetting();
            }

            $payments = Payment::with(['property', 'landlord'])
                ->whereIn('status', ['completed', 'pending', 'failed'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $payments->each(function ($payment) use ($settings) {
                $payment->formatted_amount = $settings->formatAmount($payment->amount);
                return $payment;
            });

            return $payments;
        } catch (\Exception $e) {
            Log::error('Error getting recent payments: ' . $e->getMessage());
            return collect();
        }
    }

    public function getPaymentProvidersStatus()
    {
        try {
            if ($this->paymentService && method_exists($this->paymentService, 'checkPaymentMethodConfiguration')) {
                return $this->paymentService->checkPaymentMethodConfiguration();
            }
            
            $settings = SystemSetting::first();
            if ($settings) {
                return [
                    'mtn_momo' => [
                        'enabled' => (bool) $settings->enable_mtn_momo,
                        'configured' => !empty($settings->mtn_momo_api_key) && !empty($settings->mtn_momo_subscription_key),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                    'telecel_money' => [
                        'enabled' => (bool) $settings->enable_telecel_money,
                        'configured' => !empty($settings->telecel_api_key),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                    'airteltigo_money' => [
                        'enabled' => (bool) $settings->enable_airteltigo_money,
                        'configured' => !empty($settings->airteltigo_api_key),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                    'paystack' => [
                        'enabled' => (bool) $settings->enable_paystack,
                        'configured' => !empty($settings->paystack_secret_key) && !empty($settings->paystack_public_key),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                ];
            }
            
            return [
                'mtn_momo' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'telecel_money' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'airteltigo_money' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'paystack' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
            ];
        } catch (\Exception $e) {
            Log::error('Error getting payment providers status: ' . $e->getMessage());
            return [
                'mtn_momo' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'telecel_money' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'airteltigo_money' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'paystack' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
            ];
        }
    }

    public function getSmsProvidersStatus()
    {
        try {
            $settings = SystemSetting::first();
            if ($settings) {
                return [
                    'arkesel' => [
                        'enabled' => (bool) $settings->enable_arkesel_sms,
                        'configured' => !empty($settings->arkesel_api_key),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                    'twilio' => [
                        'enabled' => (bool) $settings->enable_twilio_sms,
                        'configured' => !empty($settings->twilio_sid) && !empty($settings->twilio_token),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                    'africastalking' => [
                        'enabled' => (bool) $settings->enable_africastalking_sms,
                        'configured' => !empty($settings->africastalking_api_key) && !empty($settings->africastalking_username),
                        'can_configure' => true,
                        'readonly' => false
                    ],
                ];
            }
            
            return [
                'arkesel' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'twilio' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'africastalking' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
            ];
        } catch (\Exception $e) {
            Log::error('Error getting SMS providers status: ' . $e->getMessage());
            return [
                'arkesel' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'twilio' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
                'africastalking' => ['enabled' => false, 'configured' => false, 'can_configure' => true, 'readonly' => false],
            ];
        }
    }

    public function getSystemHealthStatus($paymentProviders = null, $smsProviders = null, $invitationStats = null)
    {
        try {
            if (!$paymentProviders) {
                $paymentProviders = $this->getPaymentProvidersStatus();
            }
            
            if (!$smsProviders) {
                $smsProviders = $this->getSmsProvidersStatus();
            }
            
            if (!$invitationStats) {
                $invitationStats = $this->getInvitationStatistics();
            }

            $activePaymentProviders = collect($paymentProviders)->filter(function ($status) {
                return $status['enabled'] && $status['configured'];
            })->count();

            $activeSmsProviders = collect($smsProviders)->filter(function ($status) {
                return $status['enabled'] && $status['configured'];
            })->count();

            $invitationSystemHealthy = $invitationStats ? ($invitationStats['overall_status'] === 'active') : false;

            $healthScore = ($activePaymentProviders > 0 ? 2 : 0) + 
                          ($activeSmsProviders > 0 ? 2 : 0) + 
                          ($invitationSystemHealthy ? 1 : 0);

            $databaseCheck = $this->checkDatabaseHealth();
            if ($databaseCheck) $healthScore += 2;

            $storageCheck = $this->checkStorageHealth();
            if ($storageCheck) $healthScore += 1;

            if ($healthScore >= 6) {
                $status = 'healthy';
                $color = 'success';
            } elseif ($healthScore >= 4) {
                $status = 'degraded';
                $color = 'warning';
            } else {
                $status = 'unhealthy';
                $color = 'danger';
            }

            return [
                'overall' => $status,
                'color' => $color,
                'score' => $healthScore,
                'active_payment_providers' => $activePaymentProviders,
                'active_sms_providers' => $activeSmsProviders,
                'invitation_system_healthy' => $invitationSystemHealthy,
                'database_healthy' => $databaseCheck,
                'storage_healthy' => $storageCheck,
                'total_payment_providers' => count($paymentProviders),
                'total_sms_providers' => count($smsProviders),
                'checks' => [
                    'payment_providers' => $activePaymentProviders > 0,
                    'sms_providers' => $activeSmsProviders > 0,
                    'invitations' => $invitationSystemHealthy,
                    'database' => $databaseCheck,
                    'storage' => $storageCheck,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting system health status: ' . $e->getMessage());
            return [
                'overall' => 'unhealthy',
                'color' => 'danger',
                'score' => 0,
                'active_payment_providers' => 0,
                'active_sms_providers' => 0,
                'invitation_system_healthy' => false,
                'database_healthy' => false,
                'storage_healthy' => false,
                'total_payment_providers' => 0,
                'total_sms_providers' => 0,
                'checks' => [
                    'payment_providers' => false,
                    'sms_providers' => false,
                    'invitations' => false,
                    'database' => false,
                    'storage' => false,
                ]
            ];
        }
    }

    public function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkStorageHealth()
    {
        try {
            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $totalSpace = disk_total_space($storagePath);
            
            $freePercentage = ($freeSpace / $totalSpace) * 100;
            return $freePercentage > 10;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRevenueChartData($settings = null)
    {
        try {
            if (!$settings) {
                $settings = SystemSetting::first() ?? new SystemSetting();
            }

            $currentYear = now()->year;
            
            $monthlyRevenue = Payment::where('status', 'completed')
                ->whereYear('created_at', $currentYear)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->pluck('total', 'month');

            $dailyRevenue = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('DAY(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->pluck('total', 'day');

            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData[] = $monthlyRevenue[$month] ?? 0;
            }

            $daysInMonth = now()->daysInMonth;
            $dailyData = [];
            $dailyLabels = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dailyData[] = $dailyRevenue[$day] ?? 0;
                $dailyLabels[] = $day;
            }

            return [
                'monthly' => $monthlyData,
                'daily' => $dailyData,
                'monthly_labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'daily_labels' => $dailyLabels,
                'current_year' => $currentYear,
                'current_month' => now()->monthName,
                'currency' => $settings->getCurrencyInfo() ?? [
                    'symbol' => '$',
                    'code' => 'USD',
                    'position' => 'left'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting revenue chart data: ' . $e->getMessage());
            return [
                'monthly' => array_fill(0, 12, 0),
                'daily' => array_fill(0, 30, 0),
                'monthly_labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'daily_labels' => range(1, 30),
                'current_year' => now()->year,
                'current_month' => now()->monthName,
                'currency' => [
                    'symbol' => '$',
                    'code' => 'USD',
                    'position' => 'left'
                ]
            ];
        }
    }

    /**
     * Get user registration chart data (excludes developers)
     * FIXED: Landlords now counted by ROLE (not just legacy type)
     */
    public function getUserRegistrationChartData()
    {
        try {
            $labels = [];
            $allData = [];
            $landlordData = [];
            $agentData = [];

            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $month = $date->month;
                $year = $date->year;
                
                $labels[] = $date->format('M Y');
                
                $allData[] = User::where('type', '!=', User::TYPE_DEVELOPER)
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();
                
                $landlordData[] = User::where(function($query) {
                        $query->where('type', User::TYPE_LANDLORD)
                              ->orWhereHas('roles', function($q) {
                                  $q->where('slug', 'landlord');
                              });
                    })
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();
                
                $agentData[] = User::where('type', User::TYPE_FIELD_AGENT)
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();
            }

            return [
                'all' => $allData,
                'landlords' => $landlordData,
                'agents' => $agentData,
                'labels' => $labels,
                'time_period' => 'last_12_months',
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user registration chart data: ' . $e->getMessage());
            return [
                'all' => array_fill(0, 12, 0),
                'landlords' => array_fill(0, 12, 0),
                'agents' => array_fill(0, 12, 0),
                'labels' => [],
                'time_period' => 'last_12_months',
            ];
        }
    }

    public function getPropertyRegistrationChartData()
    {
        try {
            $labels = [];
            $propertyData = [];
            $withDigitalAddress = [];
            $withoutDigitalAddress = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $month = $date->month;
                $year = $date->year;
                
                $labels[] = $date->format('M Y');
                
                $propertyData[] = Property::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();
                
                $withDigitalAddress[] = Property::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->whereNotNull('digital_address')
                    ->count();
                
                $withoutDigitalAddress[] = Property::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->whereNull('digital_address')
                    ->count();
            }

            return [
                'properties' => $propertyData,
                'with_digital_address' => $withDigitalAddress,
                'without_digital_address' => $withoutDigitalAddress,
                'labels' => $labels,
                'time_period' => 'last_6_months',
            ];
        } catch (\Exception $e) {
            Log::error('Error getting property registration chart data: ' . $e->getMessage());
            return [
                'properties' => array_fill(0, 6, 0),
                'with_digital_address' => array_fill(0, 6, 0),
                'without_digital_address' => array_fill(0, 6, 0),
                'labels' => [],
                'time_period' => 'last_6_months',
            ];
        }
    }

    public function getRecentUsers($limit = 5)
    {
        try {
            return User::where('type', '!=', User::TYPE_DEVELOPER)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->each(function ($user) {
                    $user->type_name = $this->getUserTypeName($user->type);
                    return $user;
                });
        } catch (\Exception $e) {
            Log::error('Error getting recent users: ' . $e->getMessage());
            return collect();
        }
    }

    public function getRecentProperties($limit = 5)
    {
        try {
            return Property::with(['landlord', 'registrationPlan'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting recent properties: ' . $e->getMessage());
            return collect();
        }
    }

    public function getRecentRegistrationPlans($limit = 5)
    {
        try {
            return RegistrationPlan::with(['activeAgents.agent', 'properties'])
                ->withCount(['properties as registered_properties_count'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->each(function ($plan) {
                    $plan->assigned_agents_count = $plan->activeAgents->count();
                    $plan->primary_agent = $plan->activeAgents->first()->agent ?? null;
                    $plan->completion_percentage = $plan->total_properties > 0 
                        ? round(($plan->registered_properties_count / $plan->total_properties) * 100, 1)
                        : 0;
                    return $plan;
                });
        } catch (\Exception $e) {
            Log::error('Error getting recent registration plans: ' . $e->getMessage());
            return collect();
        }
    }

    public function getUserTypeName($type)
    {
        $types = [
            User::TYPE_SUPER_ADMIN => 'Super Admin',
            User::TYPE_ADMIN => 'Admin',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_DEVELOPER => 'Developer',
            User::TYPE_SECURITY_PERSONNEL => 'Security Checkpoint',
        ];
        
        return $types[$type] ?? 'Unknown';
    }

    public function getPendingActions()
    {
        try {
            return [
                'users' => User::where('status', 'pending')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'payments' => Payment::where('status', 'pending')
                    ->where('created_at', '<=', now()->subHours(2))
                    ->count(),
                'invoices' => Invoice::where('status', 'overdue')->count(),
                'unassigned_plans' => RegistrationPlan::unassigned()->count(),
                'pending_agent_invitations' => AgentInvitation::where('status', 'pending')->count(),
                'pending_user_invitations' => UserInvitation::where('status', 'pending')->count(),
                'pending_landlord_invitations' => LandlordInvitation::where('status', 'pending')->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting pending actions: ' . $e->getMessage());
            return [
                'users' => collect(),
                'payments' => 0,
                'invoices' => 0,
                'unassigned_plans' => 0,
                'pending_agent_invitations' => 0,
                'pending_user_invitations' => 0,
                'pending_landlord_invitations' => 0,
            ];
        }
    }

    public function getQuickStats($settings = null)
    {
        try {
            if (!$settings) {
                $settings = SystemSetting::first() ?? new SystemSetting();
            }

            return [
                'total_revenue' => [
                    'value' => $settings->formatAmount(Payment::where('status', 'completed')->sum('amount') ?? 0),
                    'icon' => 'dollar-sign',
                    'color' => 'success',
                    'trend' => $this->getRevenueTrend(),
                ],
                'total_users' => [
                    'value' => User::where('type', '!=', User::TYPE_DEVELOPER)->count(),
                    'icon' => 'users',
                    'color' => 'primary',
                    'trend' => $this->getUserTrend(),
                ],
                'total_properties' => [
                    'value' => Property::count(),
                    'icon' => 'building',
                    'color' => 'info',
                    'trend' => $this->getPropertyTrend(),
                ],
                'active_plans' => [
                    'value' => RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])->count(),
                    'icon' => 'clipboard-check',
                    'color' => 'warning',
                    'trend' => $this->getPlanTrend(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error getting quick stats: ' . $e->getMessage());
            return [
                'total_revenue' => ['value' => '$0.00', 'icon' => 'dollar-sign', 'color' => 'success', 'trend' => 0],
                'total_users' => ['value' => 0, 'icon' => 'users', 'color' => 'primary', 'trend' => 0],
                'total_properties' => ['value' => 0, 'icon' => 'building', 'color' => 'info', 'trend' => 0],
                'active_plans' => ['value' => 0, 'icon' => 'clipboard-check', 'color' => 'warning', 'trend' => 0],
            ];
        }
    }

    public function getRevenueTrend()
    {
        try {
            $currentMonth = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            $previousMonth = Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount');

            if ($previousMonth == 0) {
                return $currentMonth > 0 ? 100 : 0;
            }

            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getUserTrend()
    {
        try {
            $currentMonth = User::where('type', '!=', User::TYPE_DEVELOPER)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $previousMonth = User::where('type', '!=', User::TYPE_DEVELOPER)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();

            if ($previousMonth == 0) {
                return $currentMonth > 0 ? 100 : 0;
            }

            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getPropertyTrend()
    {
        try {
            $currentMonth = Property::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $previousMonth = Property::whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();

            if ($previousMonth == 0) {
                return $currentMonth > 0 ? 100 : 0;
            }

            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getPlanTrend()
    {
        try {
            $currentMonth = RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $previousMonth = RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();

            if ($previousMonth == 0) {
                return $currentMonth > 0 ? 100 : 0;
            }

            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getSystemAlerts()
    {
        try {
            $alerts = [];

            $paymentProviders = $this->getPaymentProvidersStatus();
            $activePaymentProviders = collect($paymentProviders)->filter(function ($status) {
                return $status['enabled'] && $status['configured'];
            })->count();

            if ($activePaymentProviders === 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'credit-card',
                    'title' => 'No Active Payment Providers',
                    'message' => 'All payment providers are disabled or misconfigured. Payments cannot be processed.',
                    'link' => route('admin.payment-providers.index'),
                    'link_text' => 'Configure Payment Providers',
                    'requires_super_admin' => false
                ];
            } elseif ($activePaymentProviders === 1) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'credit-card',
                    'title' => 'Limited Payment Options',
                    'message' => 'Only one payment provider is active. Consider enabling additional providers.',
                    'link' => route('admin.payment-providers.index'),
                    'link_text' => 'View Payment Providers',
                    'requires_super_admin' => false
                ];
            }

            $smsProviders = $this->getSmsProvidersStatus();
            $activeSmsProviders = collect($smsProviders)->filter(function ($status) {
                return $status['enabled'] && $status['configured'];
            })->count();

            if ($activeSmsProviders === 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'sms',
                    'title' => 'No Active SMS Providers',
                    'message' => 'All SMS providers are disabled or misconfigured. Notifications cannot be sent.',
                    'link' => route('admin.sms-providers.index'),
                    'link_text' => 'Configure SMS Providers',
                    'requires_super_admin' => false
                ];
            }

            $overdueInvoices = Invoice::where('status', 'overdue')->count();
            if ($overdueInvoices > 50) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'exclamation-triangle',
                    'title' => 'High Number of Overdue Invoices',
                    'message' => "There are {$overdueInvoices} overdue invoices requiring attention.",
                    'link' => route('invoices.index'),
                    'link_text' => 'View Invoices',
                    'requires_super_admin' => false
                ];
            } elseif ($overdueInvoices > 20) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'exclamation-circle',
                    'title' => 'Overdue Invoices',
                    'message' => "There are {$overdueInvoices} overdue invoices.",
                    'link' => route('invoices.index'),
                    'link_text' => 'View Invoices',
                    'requires_super_admin' => false
                ];
            }

            $unassignedPlans = RegistrationPlan::unassigned()->count();
            if ($unassignedPlans > 5) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'clipboard-list',
                    'title' => 'Unassigned Registration Plans',
                    'message' => "{$unassignedPlans} registration plans are waiting for agent assignment.",
                    'link' => route('registration-plans.index'),
                    'link_text' => 'View Registration Plans',
                    'requires_super_admin' => false
                ];
            }

            $overduePlans = RegistrationPlan::overdue()->count();
            if ($overduePlans > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'calendar-times',
                    'title' => 'Overdue Registration Plans',
                    'message' => "{$overduePlans} registration plans are past their end date.",
                    'link' => route('registration-plans.index'),
                    'link_text' => 'View Registration Plans',
                    'requires_super_admin' => false
                ];
            }

            $settings = SystemSetting::first();
            if (!$settings) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'cogs',
                    'title' => 'System Settings Not Configured',
                    'message' => 'Please configure system settings before using the application.',
                    'link' => route('admin.system-settings.index'),
                    'link_text' => 'Configure System Settings',
                    'requires_super_admin' => false
                ];
            }

            if (!$this->checkDatabaseHealth()) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'database',
                    'title' => 'Database Connection Issue',
                    'message' => 'Unable to connect to the database. Some features may not work properly.',
                    'link' => null,
                    'link_text' => null,
                    'requires_super_admin' => false
                ];
            }

            if (!$this->checkStorageHealth()) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'hard-drive',
                    'title' => 'Low Storage Space',
                    'message' => 'Storage space is running low. Consider cleaning up old files.',
                    'link' => null,
                    'link_text' => null,
                    'requires_super_admin' => false
                ];
            }

            return $alerts;
        } catch (\Exception $e) {
            Log::error('Error getting system alerts: ' . $e->getMessage());
            return [];
        }
    }

    public function getFallbackDashboardData($errorMessage = null)
    {
        $settings = SystemSetting::first() ?? new SystemSetting();
        
        $recentTestimonials = collect();
        try {
            $recentTestimonials = Testimonial::approved()
                ->with(['user', 'approver'])
                ->latest()
                ->take(5)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error loading testimonials in fallback: ' . $e->getMessage());
        }
        
        $user = Auth::user();
        $userRoles = $user->getRoleSlugsAttribute();
        $isMultiRoleUser = count($userRoles) > 1;
        
        return view('super-admin.dashboard', [
            'systemSettings' => $settings,
            'settings' => $settings,
            'searchTerm' => null,
            'category' => 'all',
            'filteredData' => null,
            'userStats' => $this->getUserStatistics(),
            'paymentStats' => $this->getPaymentStatistics($settings),
            'propertyStats' => $this->getPropertyStatistics(),
            'planStats' => $this->getRegistrationPlanStatistics(),
            'recentPayments' => collect(),
            'recentPlans' => collect(),
            'recentUsers' => collect(),
            'recentProperties' => collect(),
            'recentTestimonials' => $recentTestimonials,
            'paymentProviders' => $this->getPaymentProvidersStatus(),
            'smsProviders' => $this->getSmsProvidersStatus(),
            'invitationStats' => $this->getInvitationStatistics(),
            'landlordInvitationStats' => $this->getLandlordInvitationStatistics(),
            'userInvitationStats' => $this->getUserInvitationStatistics(),
            'systemHealth' => $this->getSystemHealthStatus(),
            'revenueData' => $this->getRevenueChartData($settings),
            'userRegistrationData' => $this->getUserRegistrationChartData(),
            'propertyRegistrationData' => $this->getPropertyRegistrationChartData(),
            'pendingActions' => $this->getPendingActions(),
            'systemAlerts' => [],
            'quickStats' => $this->getQuickStats($settings),
            'userRoles' => $userRoles,
            'isMultiRoleUser' => $isMultiRoleUser,
            'error' => $errorMessage,
        ]);
    }

    // ==================== LANDLORD DASHBOARD HELPER METHODS ====================

    private function getLandlordDashboardStats($landlordId)
    {
        try {
            $totalProperties = Property::where('landlord_id', $landlordId)->count();
            $totalUnits = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->count();
            
            $occupiedUnits = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->whereHas('rentalAgreements', function($q) {
                $q->where('status', 'active')
                  ->where('start_date', '<=', now())
                  ->where('end_date', '>=', now());
            })->count();
            
            $vacantUnits = $totalUnits - $occupiedUnits;
            $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;
            
            $monthlyRevenue = Payment::whereHas('invoices.unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->whereMonth('payment_date', now()->month)
              ->whereYear('payment_date', now()->year)
              ->where('status', Payment::STATUS_COMPLETED)
              ->sum('amount');
            
            $pendingPayments = $this->calculateLandlordPendingPayments($landlordId);
            
            $pendingMaintenance = 0;
            if (class_exists('App\Models\MaintenanceRequest')) {
                $pendingMaintenance = MaintenanceRequest::whereHas('unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })->whereIn('status', ['pending', 'assigned', 'in_progress'])->count();
            }
            
            $activeLeases = RentalAgreement::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'active')->count();
            
            $totalTenants = User::where('type', User::TYPE_TENANT)
                ->whereHas('propertyUnits', function($q) use ($landlordId) {
                    $q->whereHas('property', function($prop) use ($landlordId) {
                        $prop->where('landlord_id', $landlordId);
                    });
                })->distinct()->count();
            
            $tenantApprovals = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('tenant_status', 'pending')->count();
            
            $pendingLeaseSignatures = RentalAgreement::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'pending_signature')
              ->whereNull('tenant_signed_at')
              ->count();
            
            return [
                'total_properties' => $totalProperties,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => $occupancyRate,
                'monthly_revenue' => (float) $monthlyRevenue,
                'pending_payments' => (float) $pendingPayments,
                'pending_maintenance' => $pendingMaintenance,
                'active_leases' => $activeLeases,
                'total_tenants' => $totalTenants,
                'tenant_approvals' => $tenantApprovals,
                'pending_lease_signatures' => $pendingLeaseSignatures,
                'unread_messages' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting landlord dashboard stats: ' . $e->getMessage());
            return [
                'total_properties' => 0,
                'total_units' => 0,
                'occupied_units' => 0,
                'vacant_units' => 0,
                'occupancy_rate' => 0,
                'monthly_revenue' => 0,
                'pending_payments' => 0,
                'pending_maintenance' => 0,
                'active_leases' => 0,
                'total_tenants' => 0,
                'tenant_approvals' => 0,
                'pending_lease_signatures' => 0,
                'unread_messages' => 0,
            ];
        }
    }

    private function calculateLandlordPendingPayments($landlordId)
    {
        try {
            $invoices = Invoice::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', '!=', 'paid')->get();
            
            $totalPending = 0;
            
            foreach ($invoices as $invoice) {
                $paidAmount = Payment::whereHas('invoices', function($q) use ($invoice) {
                    $q->where('invoice_id', $invoice->id);
                })->where('status', Payment::STATUS_COMPLETED)->sum('amount');
                
                $invoiceTotal = $invoice->total_amount ?? $invoice->amount ?? 0;
                $pending = $invoiceTotal - $paidAmount;
                if ($pending > 0) {
                    $totalPending += $pending;
                }
            }
            
            return $totalPending;
        } catch (\Exception $e) {
            Log::error('Error calculating pending payments: ' . $e->getMessage());
            return 0;
        }
    }

    private function getLandlordRecentProperties($landlordId, $limit = 5)
    {
        try {
            return Property::where('landlord_id', $landlordId)
                ->with(['propertyType', 'units'])
                ->latest()
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting recent properties: ' . $e->getMessage());
            return collect();
        }
    }

    private function getLandlordRecentPayments($landlordId, $limit = 10)
    {
        try {
            return Payment::with(['invoices.unit.property', 'invoices.tenant'])
                ->whereHas('invoices.unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })
                ->latest()
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting recent payments: ' . $e->getMessage());
            return collect();
        }
    }

    private function getLandlordUpcomingLeaseExpirations($landlordId, $limit = 10)
    {
        try {
            return RentalAgreement::with(['unit.property', 'tenant'])
                ->whereHas('unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })
                ->where('status', 'active')
                ->where('end_date', '<=', now()->addDays(30))
                ->orderBy('end_date')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting upcoming lease expirations: ' . $e->getMessage());
            return collect();
        }
    }

    private function getLandlordRecentMaintenanceRequests($landlordId, $limit = 5)
    {
        try {
            if (!class_exists('App\Models\MaintenanceRequest')) {
                return collect();
            }
            
            return MaintenanceRequest::with(['unit.property', 'tenant'])
                ->whereHas('unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })
                ->latest()
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting recent maintenance requests: ' . $e->getMessage());
            return collect();
        }
    }

    private function getLandlordFinancialSummary($landlordId)
    {
        try {
            $totalRevenue = Payment::whereHas('invoices.unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', Payment::STATUS_COMPLETED)->sum('amount');
            
            $totalOutstanding = Invoice::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', '!=', 'paid')->sum('amount');
            
            $monthlyRevenue = Payment::whereHas('invoices.unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->whereMonth('payment_date', now()->month)
              ->whereYear('payment_date', now()->year)
              ->where('status', Payment::STATUS_COMPLETED)
              ->sum('amount');
            
            $totalInvoices = Invoice::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->count();
            
            $paidInvoices = Invoice::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'paid')->count();
            
            return [
                'total_revenue' => (float) $totalRevenue,
                'total_outstanding' => (float) $totalOutstanding,
                'monthly_revenue' => (float) $monthlyRevenue,
                'total_invoices' => $totalInvoices,
                'paid_invoices' => $paidInvoices,
                'collection_rate' => $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting financial summary: ' . $e->getMessage());
            return [
                'total_revenue' => 0,
                'total_outstanding' => 0,
                'monthly_revenue' => 0,
                'total_invoices' => 0,
                'paid_invoices' => 0,
                'collection_rate' => 0,
            ];
        }
    }

    private function getLandlordMaintenanceSummary($landlordId)
    {
        try {
            if (!class_exists('App\Models\MaintenanceRequest')) {
                return [
                    'total_requests' => 0,
                    'pending_requests' => 0,
                    'completed_requests' => 0,
                    'urgent_requests' => 0,
                    'total_cost' => 0,
                    'avg_resolution_days' => 0,
                    'completion_rate' => 0,
                ];
            }
            
            $totalRequests = MaintenanceRequest::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->count();
            
            $pendingRequests = MaintenanceRequest::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->whereIn('status', ['pending', 'assigned', 'in_progress'])->count();
            
            $completedRequests = MaintenanceRequest::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'completed')->count();
            
            $totalCost = MaintenanceRequest::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'completed')->sum('actual_cost');
            
            return [
                'total_requests' => $totalRequests,
                'pending_requests' => $pendingRequests,
                'completed_requests' => $completedRequests,
                'urgent_requests' => 0,
                'total_cost' => (float) $totalCost,
                'avg_resolution_days' => 0,
                'completion_rate' => $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting maintenance summary: ' . $e->getMessage());
            return [
                'total_requests' => 0,
                'pending_requests' => 0,
                'completed_requests' => 0,
                'urgent_requests' => 0,
                'total_cost' => 0,
                'avg_resolution_days' => 0,
                'completion_rate' => 0,
            ];
        }
    }

    private function getLandlordChartData($landlordId)
    {
        try {
            // Revenue data
            $months = collect(range(1, 12))->map(function($month) {
                return Carbon::create()->month($month)->format('M');
            });
            
            $revenue = collect(range(1, 12))->map(function($month) use ($landlordId) {
                return (float) Payment::whereHas('invoices.unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })->whereMonth('payment_date', $month)
                  ->whereYear('payment_date', now()->year)
                  ->where('status', Payment::STATUS_COMPLETED)
                  ->sum('amount');
            });
            
            // Occupancy data - use the same logic
            $unitIds = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->pluck('id')->toArray();
            
            $totalUnits = count($unitIds);
            $occupancyData = [];
            
            foreach (range(1, 12) as $month) {
                $occupiedCount = 0;
                foreach ($unitIds as $unitId) {
                    $hasActiveLease = RentalAgreement::where('unit_id', $unitId)
                        ->where('status', 'active')
                        ->where(function($q) use ($month) {
                            $q->whereMonth('start_date', '<=', $month)
                              ->whereMonth('end_date', '>=', $month);
                        })
                        ->exists();
                    
                    if ($hasActiveLease) {
                        $occupiedCount++;
                    }
                }
                $occupancyData[] = $totalUnits > 0 ? round(($occupiedCount / $totalUnits) * 100) : 0;
            }
            
            return [
                'revenue' => ['labels' => $months, 'data' => $revenue],
                'occupancy' => ['labels' => $months, 'data' => $occupancyData]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting chart data: ' . $e->getMessage());
            return [
                'revenue' => ['labels' => [], 'data' => []],
                'occupancy' => ['labels' => [], 'data' => []]
            ];
        }
    }

    private function getLandlordPendingActions($landlordId)
    {
        try {
            $pendingTenantApprovals = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('tenant_status', 'pending')->count();
            
            $pendingLeaseSignatures = RentalAgreement::whereHas('unit.property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->where('status', 'pending_signature')
              ->whereNull('tenant_signed_at')
              ->count();
            
            return [
                'tenant_approvals' => $pendingTenantApprovals,
                'maintenance_approvals' => 0,
                'pending_lease_signatures' => $pendingLeaseSignatures,
                'unread_messages' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting pending actions: ' . $e->getMessage());
            return [
                'tenant_approvals' => 0,
                'maintenance_approvals' => 0,
                'pending_lease_signatures' => 0,
                'unread_messages' => 0,
            ];
        }
    }

    public function getLandlordStats()
    {
        try {
            $user = Auth::user();
            $stats = $this->getLandlordDashboardStats($user->id);
            
            $stats['pending_tenant_approvals'] = $stats['tenant_approvals'] ?? 0;
            $stats['pending_maintenance_approvals'] = 0;
            $stats['unread_messages'] = 0;
            $stats['pending_invoices'] = $stats['pending_payments'] ?? 0;
            $stats['pending_lease_signatures'] = $stats['pending_lease_signatures'] ?? 0;
            
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in getLandlordStats: ' . $e->getMessage());
            return response()->json([
                'total_properties' => 0,
                'total_units' => 0,
                'occupied_units' => 0,
                'vacant_units' => 0,
                'occupancy_rate' => 0,
                'monthly_revenue' => 0,
                'pending_payments' => 0,
                'pending_maintenance' => 0,
                'active_leases' => 0,
                'total_tenants' => 0,
                'tenant_approvals' => 0,
                'pending_lease_signatures' => 0,
                'unread_messages' => 0,
            ]);
        }
    }

    public function getLandlordChartDataAjax(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->get('type', 'revenue');
            $period = $request->get('period', 'monthly');
            $year = $request->get('year', Carbon::now()->year);
            
            if ($type === 'revenue') {
                $data = $this->getLandlordRevenueChartData($user->id, $year);
            } else if ($type === 'occupancy') {
                $data = $this->getLandlordOccupancyChartData($user->id, $period);
            } else {
                $data = ['labels' => [], 'data' => []];
            }
            
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in getLandlordChartDataAjax: ' . $e->getMessage());
            return response()->json([
                'labels' => [],
                'data' => []
            ]);
        }
    }

    public function refreshLandlordDashboard(Request $request)
    {
        try {
            $user = Auth::user();
            
            $data = [
                'stats' => $this->getLandlordDashboardStats($user->id),
                'recent_properties' => $this->getLandlordRecentProperties($user->id),
                'recent_payments' => $this->getLandlordRecentPayments($user->id),
                'financial_summary' => $this->getLandlordFinancialSummary($user->id),
                'maintenance_summary' => $this->getLandlordMaintenanceSummary($user->id),
                'pending_actions' => $this->getLandlordPendingActions($user->id),
                'refreshed_at' => Carbon::now()->toDateTimeString()
            ];
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            }
            
            return redirect()->back()->with('success', 'Dashboard refreshed successfully.');
        } catch (\Exception $e) {
            Log::error('Error refreshing landlord dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh dashboard'
            ], 500);
        }
    }

    public function exportLandlordDashboard(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->get('type', 'summary');
            
            $data = [
                'summary' => $this->getLandlordDashboardStats($user->id),
                'financial_summary' => $this->getLandlordFinancialSummary($user->id),
                'maintenance_summary' => $this->getLandlordMaintenanceSummary($user->id),
                'generated_at' => Carbon::now()->toDateTimeString(),
                'generated_by' => $user->name
            ];
            
            if ($request->wantsJson()) {
                return response()->json($data);
            }
            
            return redirect()->back()->with('success', 'Dashboard data exported successfully.');
        } catch (\Exception $e) {
            Log::error('Error exporting landlord dashboard: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export dashboard data');
        }
    }

    private function getLandlordRevenueChartData($landlordId, $year)
    {
        try {
            $months = collect(range(1, 12))->map(function($month) {
                return Carbon::create()->month($month)->format('M');
            });
            
            $data = collect(range(1, 12))->map(function($month) use ($landlordId, $year) {
                return (float) Payment::whereHas('invoices.unit.property', function($q) use ($landlordId) {
                    $q->where('landlord_id', $landlordId);
                })->whereMonth('payment_date', $month)
                  ->whereYear('payment_date', $year)
                  ->where('status', Payment::STATUS_COMPLETED)
                  ->sum('amount');
            });
            
            return [
                'labels' => $months,
                'data' => $data
            ];
        } catch (\Exception $e) {
            Log::error('Error getting revenue chart data: ' . $e->getMessage());
            return [
                'labels' => [],
                'data' => []
            ];
        }
    }

    private function getLandlordOccupancyChartData($landlordId, $period = 'monthly')
    {
        try {
            // Get all property units for this landlord
            $unitIds = PropertyUnit::whereHas('property', function($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            })->pluck('id')->toArray();
            
            $totalUnits = count($unitIds);
            
            if ($totalUnits === 0) {
                return [
                    'labels' => collect(range(1, 12))->map(fn($m) => Carbon::create()->month($m)->format('M'))->toArray(),
                    'data' => array_fill(0, 12, 0)
                ];
            }
            
            if ($period === 'quarterly') {
                // Quarterly data - 4 quarters
                $labels = ['Q1', 'Q2', 'Q3', 'Q4'];
                $data = [];
                
                for ($quarter = 1; $quarter <= 4; $quarter++) {
                    $startMonth = ($quarter - 1) * 3 + 1;
                    $endMonth = $quarter * 3;
                    
                    // Count units that had active leases during this quarter
                    $occupiedCount = 0;
                    foreach ($unitIds as $unitId) {
                        $hasActiveLease = RentalAgreement::where('unit_id', $unitId)
                            ->where('status', 'active')
                            ->where(function($q) use ($startMonth, $endMonth) {
                                // Check if agreement overlaps with this quarter
                                $q->where(function($sub) use ($startMonth, $endMonth) {
                                    $sub->whereMonth('start_date', '<=', $endMonth)
                                        ->whereMonth('end_date', '>=', $startMonth);
                                });
                            })
                            ->exists();
                        
                        if ($hasActiveLease) {
                            $occupiedCount++;
                        }
                    }
                    
                    $data[] = $totalUnits > 0 ? round(($occupiedCount / $totalUnits) * 100) : 0;
                }
                
                return [
                    'labels' => $labels,
                    'data' => $data
                ];
            }
            
            // Monthly data - 12 months
            $labels = collect(range(1, 12))->map(function($month) {
                return Carbon::create()->month($month)->format('M');
            })->toArray();
            
            $data = [];
            
            foreach (range(1, 12) as $month) {
                $occupiedCount = 0;
                
                foreach ($unitIds as $unitId) {
                    // Check if this unit had an active rental agreement during this month
                    $hasActiveLease = RentalAgreement::where('unit_id', $unitId)
                        ->where('status', 'active')
                        ->where(function($q) use ($month) {
                            $q->whereMonth('start_date', '<=', $month)
                              ->whereMonth('end_date', '>=', $month);
                        })
                        ->exists();
                    
                    if ($hasActiveLease) {
                        $occupiedCount++;
                    }
                }
                
                $data[] = $totalUnits > 0 ? round(($occupiedCount / $totalUnits) * 100) : 0;
            }
            
            return [
                'labels' => $labels,
                'data' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting occupancy chart data: ' . $e->getMessage());
            return [
                'labels' => [],
                'data' => []
            ];
        }
    }

    // ==================== HELPER METHODS FOR ALL DASHBOARDS ====================

/**
 * Switch dashboard role for multi-role users
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function switchDashboard(Request $request)
{
    // Clear any output buffers that might contain BOM or extra output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        $rateLimitKey = 'dashboard-switch:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => "Too many switch attempts. Please try again in {$seconds} seconds."
            ], 429);
        }
        
        RateLimiter::hit($rateLimitKey, 60);
        
        if (!Auth::check()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $user = Auth::user();
        $roleSlug = $request->input('role');
        
        if (!$roleSlug) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Role parameter required'
            ], 400);
        }
        
        $validRoles = ['super-admin', 'admin', 'landlord', 'field-agent', 'security-personnel', 'tenant'];
        if (!in_array($roleSlug, $validRoles)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Invalid role specified'
            ], 400);
        }
        
        // ==================== DUAL MODE ROLE CHECK ====================
        // 1. Check role-based roles
        $hasRoleBasedRole = $user->hasRole($roleSlug);
        
        // 2. Check legacy type field
        $legacyTypeMap = [
            0 => 'super-admin',
            1 => 'admin',
            2 => 'landlord',
            3 => 'tenant',
            4 => 'field-agent',
            5 => 'developer',
            6 => 'security-personnel',
        ];
        $legacyRole = $legacyTypeMap[$user->type] ?? null;
        $hasLegacyRole = ($legacyRole === $roleSlug);
        
        // 3. User has the role if EITHER check passes
        $hasRole = $hasRoleBasedRole || $hasLegacyRole;
        
        if (!$hasRole) {
            $userRoles = $user->roles->pluck('slug')->toArray();
            if ($legacyRole) {
                $userRoles[] = $legacyRole . ' (legacy)';
            }
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => "You do not have the '{$roleSlug}' role. Your roles: " . implode(', ', $userRoles)
            ], 403);
        }
        
        // Clear any redirect attempt session keys
        session()->forget('selected_role');
        
        foreach (session()->all() as $key => $value) {
            if (str_starts_with($key, 'redirect_attempt_')) {
                session()->forget($key);
            }
        }
        
        // Set the new role in session and cookie
        session(['selected_role' => $roleSlug]);
        session()->save();
        cookie()->queue('selected_role', $roleSlug, 120);
        
        $routeMap = [
            'super-admin' => 'super-admin.dashboard',
            'admin' => 'admin.dashboard',
            'landlord' => 'landlord.dashboard',
            'field-agent' => 'field-agent.dashboard',
            'security-personnel' => 'security.dashboard',
            'tenant' => 'tenant.dashboard',
        ];
        
        $routeName = $routeMap[$roleSlug];
        
        if (!\Route::has($routeName)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => "Route '{$routeName}' does not exist."
            ], 500);
        }
        
        // Return clean JSON response with proper headers
        return $this->cleanJsonResponse([
            'success' => true,
            'redirect_url' => route($routeName),
            'message' => "Switched to " . ucfirst(str_replace('-', ' ', $roleSlug)) . " dashboard"
        ]);
        
    } catch (\Exception $e) {
        Log::error('Dashboard switch exception: ' . $e->getMessage(), [
            'user_id' => Auth::id(),
            'role' => $request->input('role'),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Clear buffers on error too
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Helper method to return clean JSON response
 * This prevents BOM and extra output from corrupting the JSON
 * 
 * @param array $data
 * @param int $status
 * @return \Illuminate\Http\JsonResponse
 */
protected function cleanJsonResponse($data, $status = 200)
{
    // Clear any output buffers that might contain BOM or extra output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers to prevent BOM issues and ensure clean JSON
    $response = response()->json($data, $status)
        ->header('Content-Type', 'application/json')
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('Cache-Control', 'no-cache, must-revalidate')
        ->header('Pragma', 'no-cache');
    
    // Remove any BOM from the response content
    $content = $response->getContent();
    if ($content && str_starts_with($content, "\xEF\xBB\xBF")) {
        $response->setContent(substr($content, 3));
    }
    
    return $response;
}

    public function getUserRoles()
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated'
                ], 401);
            }
            
            $user = Auth::user();
            $roles = $user->roles()->select('slug', 'display_name', 'priority')->get();
            
            return response()->json([
                'success' => true,
                'roles' => $roles,
                'primary_role' => $user->getPrimaryRoleAttribute()?->slug,
                'is_multi_role' => $roles->count() > 1,
            ]);
        } catch (\Exception $e) {
            Log::error('Get user roles error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user roles'
            ], 500);
        }
    }

    public function clearSelectedRole()
    {
        try {
            session()->forget('selected_role');
            
            foreach (session()->all() as $key => $value) {
                if (str_starts_with($key, 'redirect_attempt_')) {
                    session()->forget($key);
                }
            }
            
            session()->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard selection cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Clear selected role error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear dashboard selection'
            ], 500);
        }
    }

    public function getDashboardData(Request $request)
    {
        try {
            $settings = SystemSetting::first() ?? new SystemSetting();
            $dataType = $request->get('type', 'overview');
            
            switch ($dataType) {
                case 'payments':
                    $data = $this->getPaymentStatistics($settings);
                    break;
                case 'users':
                    $data = $this->getUserStatistics();
                    break;
                case 'properties':
                    $data = $this->getPropertyStatistics();
                    break;
                case 'plans':
                    $data = $this->getRegistrationPlanStatistics();
                    break;
                case 'invitations':
                    $data = [
                        'agent_invitations' => $this->getInvitationStatistics(),
                        'landlord_invitations' => $this->getLandlordInvitationStatistics(),
                        'user_invitations' => $this->getUserInvitationStatistics(),
                    ];
                    break;
                case 'revenue_chart':
                    $data = $this->getRevenueChartData($settings);
                    break;
                case 'user_chart':
                    $data = $this->getUserRegistrationChartData();
                    break;
                case 'property_chart':
                    $data = $this->getPropertyRegistrationChartData();
                    break;
                case 'system_health':
                    $data = $this->getSystemHealthStatus();
                    break;
                case 'alerts':
                    $data = $this->getSystemAlerts();
                    break;
                case 'recent_payments':
                    $data = $this->getRecentPayments($settings, 10);
                    break;
                case 'recent_users':
                    $data = $this->getRecentUsers(10);
                    break;
                case 'recent_properties':
                    $data = $this->getRecentProperties(10);
                    break;
                case 'recent_plans':
                    $data = $this->getRecentRegistrationPlans(10);
                    break;
                case 'quick_stats':
                    $data = $this->getQuickStats($settings);
                    break;
                default:
                    $data = [
                        'user_stats' => $this->getUserStatistics(),
                        'payment_stats' => $this->getPaymentStatistics($settings),
                        'property_stats' => $this->getPropertyStatistics(),
                        'plan_stats' => $this->getRegistrationPlanStatistics(),
                        'invitation_stats' => [
                            'agent' => $this->getInvitationStatistics(),
                            'landlord' => $this->getLandlordInvitationStatistics(),
                            'user' => $this->getUserInvitationStatistics(),
                        ],
                        'system_health' => $this->getSystemHealthStatus(),
                        'currency_settings' => $settings->getCurrencyInfo(),
                        'quick_stats' => $this->getQuickStats($settings),
                    ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'currency_settings' => $settings->getCurrencyInfo()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Super Admin Dashboard API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refreshDashboard(Request $request)
    {
        try {
            cache()->forget('super_admin_dashboard_stats');
            cache()->forget('super_admin_quick_stats');

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data refreshed successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Super Admin Dashboard Refresh Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh dashboard data'
            ], 500);
        }
    }

    public function apiRecentTestimonials()
    {
        try {
            $testimonials = Testimonial::approved()
                ->with(['user', 'approver'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function($testimonial) {
                    return [
                        'id' => $testimonial->id,
                        'name' => $testimonial->name,
                        'email' => $testimonial->email,
                        'role' => $testimonial->role,
                        'content' => $testimonial->content,
                        'rating' => $testimonial->rating,
                        'avatar_url' => $testimonial->avatar_url,
                        'property_location' => $testimonial->property_location,
                        'is_featured' => $testimonial->is_featured,
                        'is_approved' => $testimonial->is_approved,
                        'created_at' => $testimonial->created_at->toISOString(),
                        'created_at_formatted' => $testimonial->created_at->format('M j, Y'),
                        'time_ago' => $testimonial->created_at->diffForHumans(),
                        'user' => $testimonial->user ? [
                            'id' => $testimonial->user->id,
                            'name' => $testimonial->user->name,
                            'email' => $testimonial->user->email,
                            'type_name' => $testimonial->user->getTypeName(),
                        ] : null,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $testimonials,
                'total' => Testimonial::approved()->count(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting recent testimonials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load testimonials',
                'data' => []
            ], 500);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function getPendingInvitationsCount()
    {
        try {
            if (class_exists('\App\Models\UserInvitation')) {
                return UserInvitation::where('status', 'pending')->count();
            }
        } catch (\Exception $e) {
            Log::warning('UserInvitation model not available');
        }
        return 0;
    }

    private function getTotalPaymentsSum()
    {
        try {
            if (class_exists('\App\Models\Payment')) {
                return Payment::sum('amount');
            }
        } catch (\Exception $e) {
            Log::warning('Payment model not available');
        }
        return 0;
    }

    private function getLandlordMonthlyIncome($user)
    {
        try {
            if (class_exists('\App\Models\Payment')) {
                return $user->payments()->whereMonth('created_at', now()->month)->sum('amount');
            }
        } catch (\Exception $e) {
            Log::warning('Payment model not available for landlord income');
        }
        return 0;
    }

    private function getCurrentRental($user)
    {
        try {
            if (method_exists($user, 'currentRental')) {
                return $user->currentRental();
            }
        } catch (\Exception $e) {
            Log::warning('currentRental method not available');
        }
        return null;
    }

    private function getTenantPaymentCount($user)
    {
        try {
            if (class_exists('\App\Models\Payment') && method_exists($user, 'payments')) {
                return $user->payments()->count();
            }
        } catch (\Exception $e) {
            Log::warning('Payment model not available for tenant');
        }
        return 0;
    }

    private function getTenantPendingPayments($user)
    {
        try {
            if (class_exists('\App\Models\Payment') && method_exists($user, 'payments')) {
                return $user->payments()->where('status', 'pending')->count();
            }
        } catch (\Exception $e) {
            Log::warning('Payment model not available for pending payments');
        }
        return 0;
    }

    private function getMaintenanceRequestCount($user)
    {
        try {
            if (method_exists($user, 'maintenanceRequests')) {
                return $user->maintenanceRequests()->count();
            }
        } catch (\Exception $e) {
            Log::warning('maintenanceRequests method not available');
        }
        return 0;
    }

    private function getPendingMaintenanceCount($user)
    {
        try {
            if (method_exists($user, 'maintenanceRequests')) {
                return $user->maintenanceRequests()->where('status', 'pending')->count();
            }
        } catch (\Exception $e) {
            Log::warning('maintenanceRequests method not available for pending');
        }
        return 0;
    }

    private function getRegisteredPropertiesCount($user)
    {
        try {
            if (method_exists($user, 'registeredProperties')) {
                return $user->registeredProperties()->count();
            }
        } catch (\Exception $e) {
            Log::warning('registeredProperties method not available');
        }
        return 0;
    }

    private function getPendingVerificationsCount($user)
    {
        try {
            return Property::where('registered_by', $user->id)
                ->where('verification_status', 'pending')
                ->count();
        } catch (\Exception $e) {
            Log::warning('Error getting pending verifications: ' . $e->getMessage());
        }
        return 0;
    }

    private function getCompletedVerificationsCount($user)
    {
        try {
            return Property::where('registered_by', $user->id)
                ->where('verification_status', 'verified')
                ->count();
        } catch (\Exception $e) {
            Log::warning('Error getting completed verifications: ' . $e->getMessage());
        }
        return 0;
    }

    private function getActiveAssignmentsCount($user)
    {
        try {
            if (method_exists($user, 'activePlanAssignments')) {
                return $user->activePlanAssignments()->count();
            }
        } catch (\Exception $e) {
            Log::warning('activePlanAssignments method not available');
        }
        return 0;
    }

    private function getTotalAssignmentsCount($user)
    {
        try {
            if (method_exists($user, 'planAssignments')) {
                return $user->planAssignments()->count();
            }
        } catch (\Exception $e) {
            Log::warning('planAssignments method not available');
        }
        return 0;
    }

    private function calculateFieldAgentCompletionRate($user)
    {
        try {
            if (method_exists($user, 'calculatePerformanceScore')) {
                return $user->calculatePerformanceScore();
            }
        } catch (\Exception $e) {
            Log::warning('calculatePerformanceScore method not available');
        }
        return 0;
    }
}
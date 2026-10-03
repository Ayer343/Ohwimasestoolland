<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\TenantInvoice;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Models\AgentInvitation;
use App\Models\UserInvitation;
use App\Models\LandlordInvitation;
use App\Models\SystemSetting;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use App\Models\SecurityReport;
use App\Models\PropertyOwnershipTransfer;
use App\Models\LandlordConstructionRegistration;
use App\Models\Testimonial;  // ✅ ADDED: Import Testimonial model
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Services\InvoiceService;
use App\Services\AgentInvitationService;
use Illuminate\Support\Facades\Auth;  // ✅ ADD THIS LINE
use Illuminate\Support\Facades\Log;    // ✅ ADD THIS LINE

class SuperAdminController extends Controller
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
 * Super Admin Dashboard - Enhanced with search functionality
 */
public function dashboard(Request $request)
{
    try {
        // ✅ Authorization check
        $this->authorizeSuperAdmin();

        // ✅ Get search parameters
        $searchTerm = $request->get('search');
        $category = $request->get('category', 'all');
        
        // ✅ FIX: Define systemSettings variable (this was missing!)
        $systemSettings = SystemSetting::getSettings();
        $settings = SystemSetting::first() ?? new SystemSetting();

        // ✅ Get filtered data based on search
        $filteredData = $this->getFilteredDashboardData($searchTerm, $category);

        // User 
        $userStats = $this->getUserStatistics();
        
        // Payment Statistics
        $paymentStats = $this->getPaymentStatistics($settings);
        
        // Property Statistics
        $propertyStats = $this->getPropertyStatistics();
        
        // Registration Plan Statistics
        $planStats = $this->getRegistrationPlanStatistics();
        
        // Recent Payments
        $recentPayments = $this->getRecentPayments($settings);
        
        // Payment Providers Status
        $paymentProviders = $this->getPaymentProvidersStatus();
        
        // SMS Providers Status
        $smsProviders = $this->getSmsProvidersStatus();
        
        // Invitation Statistics
        $invitationStats = $this->getInvitationStatistics();
        
        // Landlord Invitation Statistics
        $landlordInvitationStats = $this->getLandlordInvitationStatistics();
        
        // User Invitation Statistics
        $userInvitationStats = $this->getUserInvitationStatistics();
        
        // System Health
        $systemHealth = $this->getSystemHealthStatus($paymentProviders, $smsProviders, $invitationStats);
        
        // Revenue Data for Charts
        $revenueData = $this->getRevenueChartData($settings);
        
        // User Registration Data for Charts
        $userRegistrationData = $this->getUserRegistrationChartData();
        
        // Property Registration Data for Charts
        $propertyRegistrationData = $this->getPropertyRegistrationChartData();
        
        // Pending Actions
        $pendingActions = $this->getPendingActions();
        
        // Recent Registration Plans
        $recentPlans = $this->getRecentRegistrationPlans();
        
        // Recent Users
        $recentUsers = $this->getRecentUsers();
        
        // Recent Properties
        $recentProperties = $this->getRecentProperties();
        
        // ✅ ADDED: Recent Testimonials (Approved only)
        $recentTestimonials = Testimonial::approved()
            ->with(['user', 'approver'])
            ->latest()
            ->take(5)
            ->get();
        
        // System Alerts
        $systemAlerts = $this->getSystemAlerts();
        
        // Quick Stats for Widgets
        $quickStats = $this->getQuickStats($settings);
        
        // ✅ FIX: Get user roles for dashboard switcher
        $user = Auth::user();
        $userRoles = $user->roles->pluck('slug')->toArray();
        $isMultiRoleUser = count($userRoles) > 1;
        
        // ✅ FIX: Get current role for switcher display
        $currentRole = session('selected_role', 'super-admin');
        $roleIcons = [
            'super-admin' => 'crown',
            'admin' => 'shield-alt',
            'landlord' => 'home',
            'tenant' => 'user',
            'field-agent' => 'clipboard-list',
            'security-personnel' => 'shield-alt',
            'developer' => 'code',
        ];
        $currentIcon = $roleIcons[$currentRole] ?? 'tachometer-alt';

        // ✅ FIX: Include ALL variables in compact
        return view('super-admin.dashboard', compact(
            'systemSettings',      // ✅ ADDED - This was missing!
            'settings',
            'searchTerm',
            'category',
            'filteredData',
            'userStats',
            'paymentStats',
            'propertyStats',
            'planStats',
            'recentPayments',
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
            'recentPlans',
            'recentUsers',
            'recentProperties',
            'recentTestimonials',
            'systemAlerts',
            'quickStats',
            'userRoles',           // ✅ ADDED - For dashboard switcher
            'isMultiRoleUser',     // ✅ ADDED - For dashboard switcher
            'currentRole',         // ✅ ADDED - For dashboard switcher
            'currentIcon',         // ✅ ADDED - For dashboard switcher
            'roleIcons'            // ✅ ADDED - For dashboard switcher
        ));

    } catch (\Exception $e) {
        \Log::error('Super Admin Dashboard Error: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return $this->getFallbackDashboardData($e->getMessage());
    }
}

    /**
     * Get filtered dashboard data based on search term and category
     */
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
                $results['construction_registrations'] = LandlordConstructionRegistration::where('property_address', 'like', "%{$searchTerm}%")
                    ->orWhere('developer_name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'security-posts':
                $results['security_posts'] = SecurityPost::where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('location', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'security-shifts':
                $results['security_shifts'] = SecurityShift::where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'security-schedules':
                $results['security_schedules'] = SecuritySchedule::whereHas('securityPersonnel', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%");
                })->orWhereHas('post', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%");
                })->limit(20)->get();
                break;
                
            case 'security-supervisor-assignments':
                $results['supervisor_assignments'] = SecuritySupervisorAssignment::whereHas('supervisor', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%");
                })->orWhereHas('post', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%");
                })->limit(20)->get();
                break;
                
            case 'security-reports':
                $results['security_reports'] = SecurityReport::where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('report_type', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            case 'ownership-transfers':
                $results['ownership_transfers'] = PropertyOwnershipTransfer::whereHas('property', function($q) use ($searchTerm) {
                    $q->where('property_name', 'like', "%{$searchTerm}%");
                })->orWhere('transfer_reason', 'like', "%{$searchTerm}%")
                    ->limit(20)
                    ->get();
                break;
                
            default:
                // Search all categories
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
 * Get comprehensive user statistics
 */
public function getUserStatistics()
{
    try {
        if (request()->expectsJson()) {
            $this->authorizeSuperAdmin();
        }

        // ✅ Exclude developer users (type 5) from all user counts
        return [
            'total_users' => User::where('type', '!=', 5)->count(),
            'active_users' => User::where('type', '!=', 5)->where('status', 'active')->count(),
            'pending_users' => User::where('type', '!=', 5)->where('status', 'pending')->count(),
            'suspended_users' => User::where('type', '!=', 5)->where('status', 'suspended')->count(),
            'super_admins' => User::where('type', 0)->count(),
            'admins' => User::where('type', 1)->count(),
            'landlords' => User::where('type', 2)->count(),
            'tenants' => User::where('type', 3)->count(),
            'field_agents' => User::where('type', 4)->count(),
            'developers' => User::where('type', 5)->count(),
            'security_checkpoints' => User::where('type', 6)->count(),
            'new_users_today' => User::where('type', '!=', 5)->whereDate('created_at', today())->count(),
            'new_users_week' => User::where('type', '!=', 5)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    } catch (\Exception $e) {
        \Log::error('Error getting user statistics: ' . $e->getMessage());
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

    /**
     * Get payment statistics with currency formatting
     */
    public function getPaymentStatistics($settings = null)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting payment statistics: ' . $e->getMessage());
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

    /**
     * Get property statistics
     */
    public function getPropertyStatistics()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting property statistics: ' . $e->getMessage());
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

    /**
     * Get registration plan statistics
     */
    public function getRegistrationPlanStatistics()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting registration plan statistics: ' . $e->getMessage());
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

    /**
     * Get invitation statistics
     */
    public function getInvitationStatistics()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting invitation statistics: ' . $e->getMessage());
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

    /**
     * Get landlord invitation statistics
     */
    public function getLandlordInvitationStatistics()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting landlord invitation statistics: ' . $e->getMessage());
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

    /**
     * Get user invitation statistics
     */
    public function getUserInvitationStatistics()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting user invitation statistics: ' . $e->getMessage());
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

    /**
     * Get recent payments
     */
    public function getRecentPayments($settings = null, $limit = 5)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting recent payments: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get payment providers status
     */
    public function getPaymentProvidersStatus()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

            if ($this->paymentService && method_exists($this->paymentService, 'checkPaymentMethodConfiguration')) {
                return $this->paymentService->checkPaymentMethodConfiguration();
            }
            
            $settings = SystemSetting::first();
            if ($settings) {
                return [
                    'mtn_momo' => [
                        'enabled' => (bool) $settings->enable_mtn_momo,
                        'configured' => !empty($settings->mtn_momo_api_key) && !empty($settings->mtn_momo_subscription_key)
                    ],
                    'telecel_money' => [
                        'enabled' => (bool) $settings->enable_telecel_money,
                        'configured' => !empty($settings->telecel_api_key)
                    ],
                    'airteltigo_money' => [
                        'enabled' => (bool) $settings->enable_airteltigo_money,
                        'configured' => !empty($settings->airteltigo_api_key)
                    ],
                    'paystack' => [
                        'enabled' => (bool) $settings->enable_paystack,
                        'configured' => !empty($settings->paystack_secret_key) && !empty($settings->paystack_public_key)
                    ],
                ];
            }
            
            return [
                'mtn_momo' => ['enabled' => false, 'configured' => false],
                'telecel_money' => ['enabled' => false, 'configured' => false],
                'airteltigo_money' => ['enabled' => false, 'configured' => false],
                'paystack' => ['enabled' => false, 'configured' => false],
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting payment providers status: ' . $e->getMessage());
            return [
                'mtn_momo' => ['enabled' => false, 'configured' => false],
                'telecel_money' => ['enabled' => false, 'configured' => false],
                'airteltigo_money' => ['enabled' => false, 'configured' => false],
                'paystack' => ['enabled' => false, 'configured' => false],
            ];
        }
    }

    /**
     * Get SMS providers status
     */
    public function getSmsProvidersStatus()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

            $settings = SystemSetting::first();
            if ($settings) {
                return [
                    'arkesel' => [
                        'enabled' => (bool) $settings->enable_arkesel_sms,
                        'configured' => !empty($settings->arkesel_api_key)
                    ],
                    'twilio' => [
                        'enabled' => (bool) $settings->enable_twilio_sms,
                        'configured' => !empty($settings->twilio_sid) && !empty($settings->twilio_token)
                    ],
                    'africastalking' => [
                        'enabled' => (bool) $settings->enable_africastalking_sms,
                        'configured' => !empty($settings->africastalking_api_key) && !empty($settings->africastalking_username)
                    ],
                ];
            }
            
            return [
                'arkesel' => ['enabled' => false, 'configured' => false],
                'twilio' => ['enabled' => false, 'configured' => false],
                'africastalking' => ['enabled' => false, 'configured' => false],
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting SMS providers status: ' . $e->getMessage());
            return [
                'arkesel' => ['enabled' => false, 'configured' => false],
                'twilio' => ['enabled' => false, 'configured' => false],
                'africastalking' => ['enabled' => false, 'configured' => false],
            ];
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealthStatus($paymentProviders = null, $smsProviders = null, $invitationStats = null)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting system health status: ' . $e->getMessage());
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

    /**
     * Check database health
     */
    public function checkDatabaseHealth()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check storage health
     */
    public function checkStorageHealth()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $totalSpace = disk_total_space($storagePath);
            
            $freePercentage = ($freeSpace / $totalSpace) * 100;
            return $freePercentage > 10;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChartData($settings = null)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting revenue chart data: ' . $e->getMessage());
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
 * Get user registration chart data (excluding developers)
 */
public function getUserRegistrationChartData()
{
    try {
        if (request()->expectsJson()) {
            $this->authorizeSuperAdmin();
        }

        $labels = [];
        $allData = [];
        $landlordData = [];
        $agentData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;
            
            $labels[] = $date->format('M Y');
            
            // ✅ Exclude developers from "All Users" chart
            $allData[] = User::where('type', '!=', 5)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
            
            $landlordData[] = User::where('type', 2)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
            
            $agentData[] = User::where('type', 4)
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
        \Log::error('Error getting user registration chart data: ' . $e->getMessage());
        return [
            'all' => array_fill(0, 12, 0),
            'landlords' => array_fill(0, 12, 0),
            'agents' => array_fill(0, 12, 0),
            'labels' => [],
            'time_period' => 'last_12_months',
        ];
    }
}

    /**
     * Get property registration chart data
     */
    public function getPropertyRegistrationChartData()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting property registration chart data: ' . $e->getMessage());
            return [
                'properties' => array_fill(0, 6, 0),
                'with_digital_address' => array_fill(0, 6, 0),
                'without_digital_address' => array_fill(0, 6, 0),
                'labels' => [],
                'time_period' => 'last_6_months',
            ];
        }
    }

    /**
 * Get recent users (excluding developers)
 */
public function getRecentUsers($limit = 5)
{
    try {
        if (request()->expectsJson()) {
            $this->authorizeSuperAdmin();
        }

        return User::where('type', '!=', 5)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->each(function ($user) {
                $user->type_name = $this->getUserTypeName($user->type);
                return $user;
            });
    } catch (\Exception $e) {
        \Log::error('Error getting recent users: ' . $e->getMessage());
        return collect();
    }
}

    /**
     * Get recent properties
     */
    public function getRecentProperties($limit = 5)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

            return Property::with(['landlord', 'registrationPlan'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            \Log::error('Error getting recent properties: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get user type name
     */
    public function getUserTypeName($type)
    {
        $types = [
            0 => 'Super Admin',
            1 => 'Admin',
            2 => 'Landlord',
            3 => 'Tenant',
            4 => 'Field Agent',
            5 => 'Developer',
            6 => 'Security Checkpoint',
        ];
        
        return $types[$type] ?? 'Unknown';
    }

    /**
     * Get pending actions
     */
    public function getPendingActions()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting pending actions: ' . $e->getMessage());
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

    /**
     * Get recent registration plans
     */
    public function getRecentRegistrationPlans($limit = 5)
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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
            \Log::error('Error getting recent registration plans: ' . $e->getMessage());
            return collect();
        }
    }

    /**
 * Get quick stats (excluding developers from total_users)
 */
public function getQuickStats($settings = null)
{
    try {
        if (request()->expectsJson()) {
            $this->authorizeSuperAdmin();
        }

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
                // ✅ Exclude developers from total user count
                'value' => User::where('type', '!=', 5)->count(),
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
        \Log::error('Error getting quick stats: ' . $e->getMessage());
        return [
            'total_revenue' => ['value' => '$0.00', 'icon' => 'dollar-sign', 'color' => 'success', 'trend' => 0],
            'total_users' => ['value' => 0, 'icon' => 'users', 'color' => 'primary', 'trend' => 0],
            'total_properties' => ['value' => 0, 'icon' => 'building', 'color' => 'info', 'trend' => 0],
            'active_plans' => ['value' => 0, 'icon' => 'clipboard-check', 'color' => 'warning', 'trend' => 0],
        ];
    }
}

    /**
     * Get revenue trend
     */
    public function getRevenueTrend()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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

    /**
 * Get user trend (excluding developers)
 */
public function getUserTrend()
{
    try {
        if (request()->expectsJson()) {
            $this->authorizeSuperAdmin();
        }

        $currentMonth = User::where('type', '!=', 5)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $previousMonth = User::where('type', '!=', 5)
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

    /**
     * Get property trend
     */
    public function getPropertyTrend()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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

    /**
     * Get plan trend
     */
    public function getPlanTrend()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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

    /**
     * Get system alerts
     */
    public function getSystemAlerts()
    {
        try {
            if (request()->expectsJson()) {
                $this->authorizeSuperAdmin();
            }

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

            $pendingPayments = Payment::where('status', 'pending')
                ->where('created_at', '<=', now()->subHours(4))
                ->count();
            
            if ($pendingPayments > 10) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'clock',
                    'title' => 'Stuck Payments',
                    'message' => "{$pendingPayments} payments have been pending for more than 4 hours.",
                    'link' => route('admin.payments.index'),
                    'link_text' => 'View Payments',
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
            \Log::error('Error getting system alerts: ' . $e->getMessage());
            return [];
        }
    }

   /**
     * Fallback dashboard data with recent testimonials
     */
    public function getFallbackDashboardData($errorMessage = null)
    {
        $this->authorizeSuperAdmin();

        $settings = SystemSetting::first() ?? new SystemSetting();
        
        // ✅ ADDED: Recent Testimonials for fallback
        $recentTestimonials = collect();
        try {
            $recentTestimonials = Testimonial::approved()
                ->with(['user', 'approver'])
                ->latest()
                ->take(5)
                ->get();
        } catch (\Exception $e) {
            \Log::error('Error loading testimonials in fallback: ' . $e->getMessage());
        }
        
        return view('super-admin.dashboard', [
            'settings' => $settings,
            'searchTerm' => null,
            'category' => 'all',
            'filteredData' => null,
            'userStats' => $this->getUserStatistics(),
            'paymentStats' => $this->getPaymentStatistics($settings),
            'propertyStats' => $this->getPropertyStatistics(),
            'planStats' => $this->getRegistrationPlanStatistics(),
            'recentPayments' => collect(),
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
            'recentPlans' => collect(),
            'recentUsers' => collect(),
            'recentProperties' => collect(),
            'recentTestimonials' => $recentTestimonials,  // ✅ ADDED: Pass to view
            'systemAlerts' => [],
            'quickStats' => $this->getQuickStats($settings),
            'error' => $errorMessage,
        ]);
    }

    // =============================================
    // ✅ ADDED: API endpoint for recent testimonials
    // =============================================

    /**
     * API endpoint for recent testimonials
     */
    public function apiRecentTestimonials()
    {
        try {
            $this->authorizeSuperAdmin();
            
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
            \Log::error('Error getting recent testimonials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load testimonials',
                'data' => []
            ], 500);
        }
    }

    /**
     * API endpoint for testimonial statistics
     */
    public function apiTestimonialStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => Testimonial::count(),
                    'approved' => Testimonial::approved()->count(),
                    'pending' => Testimonial::pending()->count(),
                    'featured' => Testimonial::featured()->count(),
                    'trashed' => Testimonial::onlyTrashed()->count(),
                    'average_rating' => round(Testimonial::approved()->avg('rating') ?? 0, 1),
                    'rating_distribution' => [
                        5 => Testimonial::where('rating', 5)->count(),
                        4 => Testimonial::where('rating', 4)->count(),
                        3 => Testimonial::where('rating', 3)->count(),
                        2 => Testimonial::where('rating', 2)->count(),
                        1 => Testimonial::where('rating', 1)->count(),
                    ],
                    'authenticated_vs_guest' => [
                        'authenticated' => Testimonial::whereNotNull('user_id')->count(),
                        'guest' => Testimonial::whereNull('user_id')->count(),
                    ],
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting testimonial statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load testimonial statistics',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get dashboard data via API (for AJAX updates)
     */
    public function getDashboardData(Request $request)
    {
        try {
            $this->authorizeSuperAdmin();

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
            \Log::error('Super Admin Dashboard API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear dashboard cache and refresh data
     */
    public function refreshDashboard(Request $request)
    {
        try {
            $this->authorizeSuperAdmin();

            cache()->forget('super_admin_dashboard_stats');
            cache()->forget('super_admin_quick_stats');

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data refreshed successfully',
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Super Admin Dashboard Refresh Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh dashboard data'
            ], 500);
        }
    }

    /**
     * Export dashboard data
     */
    public function exportDashboardData(Request $request)
    {
        try {
            $this->authorizeSuperAdmin();

            $settings = SystemSetting::first() ?? new SystemSetting();
            $exportType = $request->get('type', 'overview');
            
            $data = [
                'exported_at' => now()->toISOString(),
                'exported_by' => auth()->user()->name,
                'time_period' => 'all_time',
                'currency_settings' => $settings->getCurrencyInfo()
            ];
            
            switch ($exportType) {
                case 'financial':
                    $data['payment_stats'] = $this->getPaymentStatistics($settings);
                    $data['revenue_data'] = $this->getRevenueChartData($settings);
                    break;
                    
                case 'user_analytics':
                    $data['user_stats'] = $this->getUserStatistics();
                    $data['user_registration_data'] = $this->getUserRegistrationChartData();
                    break;
                    
                case 'property_analytics':
                    $data['property_stats'] = $this->getPropertyStatistics();
                    $data['property_registration_data'] = $this->getPropertyRegistrationChartData();
                    break;
                    
                case 'registration_plans':
                    $data['plan_stats'] = $this->getRegistrationPlanStatistics();
                    $data['recent_plans'] = $this->getRecentRegistrationPlans(20);
                    break;
                    
                case 'system_status':
                    $data['system_health'] = $this->getSystemHealthStatus();
                    $data['payment_providers'] = $this->getPaymentProvidersStatus();
                    $data['sms_providers'] = $this->getSmsProvidersStatus();
                    $data['invitation_stats'] = [
                        'agent' => $this->getInvitationStatistics(),
                        'landlord' => $this->getLandlordInvitationStatistics(),
                        'user' => $this->getUserInvitationStatistics(),
                    ];
                    break;
                    
                default:
                    $data = array_merge($data, [
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
                        'revenue_data' => $this->getRevenueChartData($settings),
                        'user_registration_data' => $this->getUserRegistrationChartData(),
                        'property_registration_data' => $this->getPropertyRegistrationChartData(),
                        'quick_stats' => $this->getQuickStats($settings),
                    ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Dashboard data exported successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Super Admin Dashboard Export Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export dashboard data'
            ], 500);
        }
    }

    // =============================================
    // ✅ ADDED: API Routes for Individual Data Types
    // =============================================

    /**
     * API endpoint for user statistics
     */
    public function apiUserStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getUserStatistics(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for payment statistics
     */
    public function apiPaymentStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            $settings = SystemSetting::first() ?? new SystemSetting();
            return response()->json([
                'success' => true,
                'data' => $this->getPaymentStatistics($settings),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for property statistics
     */
    public function apiPropertyStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getPropertyStatistics(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for registration plan statistics
     */
    public function apiPlanStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getRegistrationPlanStatistics(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for invitation statistics
     */
    public function apiInvitationStatistics()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => [
                    'agent' => $this->getInvitationStatistics(),
                    'landlord' => $this->getLandlordInvitationStatistics(),
                    'user' => $this->getUserInvitationStatistics(),
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for system health
     */
    public function apiSystemHealth()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getSystemHealthStatus(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for revenue chart data
     */
    public function apiRevenueChartData()
    {
        try {
            $this->authorizeSuperAdmin();
            $settings = SystemSetting::first() ?? new SystemSetting();
            return response()->json([
                'success' => true,
                'data' => $this->getRevenueChartData($settings),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for user registration chart data
     */
    public function apiUserRegistrationChartData()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getUserRegistrationChartData(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for property registration chart data
     */
    public function apiPropertyRegistrationChartData()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getPropertyRegistrationChartData(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for recent payments
     */
    public function apiRecentPayments()
    {
        try {
            $this->authorizeSuperAdmin();
            $settings = SystemSetting::first() ?? new SystemSetting();
            return response()->json([
                'success' => true,
                'data' => $this->getRecentPayments($settings, 10),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for recent users
     */
    public function apiRecentUsers()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getRecentUsers(10),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for recent properties
     */
    public function apiRecentProperties()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getRecentProperties(10),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for recent registration plans
     */
    public function apiRecentRegistrationPlans()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getRecentRegistrationPlans(10),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for pending actions
     */
    public function apiPendingActions()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getPendingActions(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for system alerts
     */
    public function apiSystemAlerts()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getSystemAlerts(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for quick stats
     */
    public function apiQuickStats()
    {
        try {
            $this->authorizeSuperAdmin();
            $settings = SystemSetting::first() ?? new SystemSetting();
            return response()->json([
                'success' => true,
                'data' => $this->getQuickStats($settings),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for payment providers status
     */
    public function apiPaymentProvidersStatus()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getPaymentProvidersStatus(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for SMS providers status
     */
    public function apiSmsProvidersStatus()
    {
        try {
            $this->authorizeSuperAdmin();
            return response()->json([
                'success' => true,
                'data' => $this->getSmsProvidersStatus(),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Authorize super admin
     */
    private function authorizeSuperAdmin()
    {
        if (!auth()->check()) {
            abort(401, 'Unauthenticated.');
        }

        if (auth()->user()->type != 0) {
            abort(403, 'Unauthorized access. Super Admin access required.');
        }
    }
}
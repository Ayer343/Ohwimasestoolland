<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\PlanAgentAssignment;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationPlanAnalyticsController extends Controller
{
    protected $smsService;
    protected $whatsappService;
    protected $emailService;

    public function __construct(
        SmsService $smsService,
        WhatsAppService $whatsappService,
        EmailService $emailService
    ) {
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
    }

    /**
     * Get progress statistics for dashboard.
     */
    public function statistics()
    {
        $stats = [
            'total_plans' => RegistrationPlan::count(),
            'active_plans' => RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_plans' => RegistrationPlan::where('status', 'completed')->count(),
            'cancelled_plans' => RegistrationPlan::where('status', 'cancelled')->count(),
            'trashed_plans' => RegistrationPlan::onlyTrashed()->count(),
            'total_houses_target' => RegistrationPlan::sum('estimated_houses'),
            'total_houses_registered' => RegistrationPlan::withCount('properties')->get()->sum('properties_count'),
            'unassigned_plans' => RegistrationPlan::whereDoesntHave('assignedAgents')->count(),
            'multi_agent_plans' => RegistrationPlan::where('agent_assignment_type', 'multiple')->count(),
            'plans_with_patterns' => RegistrationPlan::whereNotNull('naming_pattern')->count(),
            'most_used_pattern' => RegistrationPlan::groupBy('naming_pattern')
                ->select('naming_pattern', DB::raw('count(*) as count'))
                ->orderBy('count', 'desc')
                ->first()?->naming_pattern ?? 'None',
            'global_sequence_plans' => RegistrationPlan::where('is_global_sequence', true)->count(),
            'total_agent_assignments' => PlanAgentAssignment::where('is_active', true)->count(),
            // Enhanced statistics using model methods
            'active_assignments' => PlanAgentAssignment::active()->count(),
            'assignments_needing_attention' => count(PlanAgentAssignment::needsAttention()),
            'total_properties_registered_by_agents' => PlanAgentAssignment::sum('properties_registered'),
        ];

        $recentPlans = RegistrationPlan::with(['assignedAgents.agent'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $overduePlans = RegistrationPlan::where('registration_end_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['assignedAgents.agent'])
            ->get();

        // Get service statuses
        $smsStatus = $this->smsService->getSystemStatus();
        $whatsappStatus = $this->whatsappService->getSystemStatus();
        $emailStatus = $this->emailService->getSystemStatus();

        return view('admin.dashboard', compact('stats', 'recentPlans', 'overduePlans', 'smsStatus', 'whatsappStatus', 'emailStatus'));
    }

    /**
     * Get registration plan analytics
     */
    public function analytics()
    {
        $analytics = [
            'plans_by_status' => RegistrationPlan::groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->pluck('count', 'status'),

            'plans_by_zone' => RegistrationPlan::groupBy('zone')
                ->select('zone', DB::raw('count(*) as count'))
                ->orderBy('count', 'desc')
                ->get(),

            'monthly_registration' => RegistrationPlan::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as plans_count'),
                    DB::raw('SUM(estimated_houses) as target_houses')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->take(12)
                ->get(),

            'agent_performance' => User::where('type', User::TYPE_FIELD_AGENT)
                ->withCount(['assignedPlans as completed_plans_count' => function($query) {
                    $query->where('status', 'completed');
                }])
                ->withCount(['assignedPlans as total_plans_count'])
                ->withSum(['assignedPlans as total_houses_target' => function($query) {
                    $query->where('status', 'completed');
                }], 'estimated_houses')
                ->having('total_plans_count', '>', 0)
                ->orderBy('completed_plans_count', 'desc')
                ->get(),

            'pattern_usage' => RegistrationPlan::groupBy('naming_pattern')
                ->select('naming_pattern', DB::raw('count(*) as count'))
                ->orderBy('count', 'desc')
                ->get(),

            'assignment_types' => RegistrationPlan::groupBy('agent_assignment_type')
                ->select('agent_assignment_type', DB::raw('count(*) as count'))
                ->get(),

            // Enhanced analytics using PlanAgentAssignment model
            'assignment_performance' => PlanAgentAssignment::with(['agent', 'plan'])
                ->select('agent_id', DB::raw('SUM(properties_registered) as total_properties'))
                ->groupBy('agent_id')
                ->orderBy('total_properties', 'desc')
                ->take(10)
                ->get(),

            'active_assignments_by_zone' => PlanAgentAssignment::active()
                ->with('plan')
                ->get()
                ->groupBy('plan.zone')
                ->map(function($assignments) {
                    return [
                        'count' => $assignments->count(),
                        'total_properties' => $assignments->sum('properties_registered')
                    ];
                }),
        ];

        return view('admin.registration-plans.analytics', compact('analytics'));
    }

    /**
     * Get service status for dashboard
     */
    public function getServiceStatus()
    {
        return response()->json([
            'sms' => $this->smsService->getSystemStatus(),
            'whatsapp' => $this->whatsappService->getSystemStatus(),
            'email' => $this->emailService->getSystemStatus(),
        ]);
    }
}
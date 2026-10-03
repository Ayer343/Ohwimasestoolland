<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\Property;
use App\Models\PlanAgentAssignment;
use App\Services\AgentInvitationService;
use App\Services\SmsTemplateService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use App\Services\SystemSetting;
use App\Notifications\PlanAssignedNotification;
use App\Rules\FieldAgentExists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RegistrationPlansExport;

class RegistrationPlanController extends Controller
{
    protected $agentInvitationService;
    protected $smsTemplateService;
    protected $smsService;
    protected $whatsappService;
    protected $emailService;

    public function __construct(
        AgentInvitationService $agentInvitationService,
        SmsTemplateService $smsTemplateService,
        SmsService $smsService,
        WhatsAppService $whatsappService,
        EmailService $emailService
    ) {
        $this->agentInvitationService = $agentInvitationService;
        $this->smsTemplateService = $smsTemplateService;
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
    }

    // ================================================================
    //  CHANNEL STATUS RESOLUTION
    // ================================================================

    protected function resolveChannelStatuses(): array
    {
        // ---------- SMS ----------
        $smsStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'SMS service unavailable'];
        try {
            if ($this->smsService) {
                $resolved = $this->smsService->getSystemStatus();
                if (is_array($resolved)) {
                    $smsStatus = $resolved;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve SMS status: ' . $e->getMessage());
        }

        $smsReady = (bool) (
            $smsStatus['system_ready']
            ?? $smsStatus['can_send_sms']
            ?? $smsStatus['ready']
            ?? false
        );

        $smsStatus['system_ready'] = $smsReady;
        $smsStatus['can_send']     = $smsStatus['can_send'] ?? $smsReady;
        if (!isset($smsStatus['health'])) {
            $smsStatus['health'] = $smsReady ? 'healthy' : 'degraded';
        }
        if (!isset($smsStatus['message'])) {
            $smsStatus['message'] = $smsReady
                ? 'SMS service is operational'
                : 'SMS service is not configured or not ready';
        }

        // ---------- WhatsApp ----------
        $whatsappStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'WhatsApp service unavailable'];
        try {
            if ($this->whatsappService) {
                if (method_exists($this->whatsappService, 'getSystemStatus')) {
                    $resolved = $this->whatsappService->getSystemStatus();
                    if (is_array($resolved)) {
                        $whatsappStatus = $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve WhatsApp status: ' . $e->getMessage());
        }

        $whatsappReady = (bool) (
            $whatsappStatus['system_ready']
            ?? $whatsappStatus['can_send']
            ?? $whatsappStatus['configured']
            ?? false
        );

        $whatsappStatus['system_ready'] = $whatsappReady;
        $whatsappStatus['can_send']     = $whatsappStatus['can_send'] ?? $whatsappReady;
        if (!isset($whatsappStatus['health'])) {
            $whatsappStatus['health'] = $whatsappReady ? 'healthy' : 'not_configured';
        }
        if (!isset($whatsappStatus['message'])) {
            $whatsappStatus['message'] = $whatsappReady
                ? 'WhatsApp service is operational'
                : 'WhatsApp service is not configured';
        }

        // ---------- Email ----------
        $emailStatus = ['system_ready' => false, 'health' => 'unknown', 'message' => 'Email service unavailable'];
        try {
            if ($this->emailService && method_exists($this->emailService, 'getSystemStatus')) {
                $resolved = $this->emailService->getSystemStatus();
                if (is_array($resolved)) {
                    $emailStatus = $resolved;
                }
            } elseif (class_exists(SystemSetting::class)) {
                $settings = SystemSetting::getSettings();
                if ($settings && method_exists($settings, 'getEmailConfigurationStatus')) {
                    $resolved = $settings->getEmailConfigurationStatus();
                    if (is_array($resolved)) {
                        $emailStatus = $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve Email status: ' . $e->getMessage());
        }

        $emailReady = (bool) (
            $emailStatus['system_ready']
            ?? $emailStatus['can_send']
            ?? $emailStatus['configured']
            ?? false
        );

        $emailStatus['system_ready'] = $emailReady;
        $emailStatus['can_send']     = $emailStatus['can_send'] ?? $emailReady;
        $emailStatus['configured']   = $emailStatus['configured'] ?? $emailReady;
        if (!isset($emailStatus['health'])) {
            $emailStatus['health'] = $emailReady ? 'healthy' : 'degraded';
        }
        if (!isset($emailStatus['message'])) {
            $emailStatus['message'] = $emailReady
                ? 'Email service is operational'
                : 'Email service is not configured or not ready';
        }
        if (!isset($emailStatus['provider'])) {
            $emailStatus['provider'] = $emailStatus['system_email'] ?? 'N/A';
        }

        return [
            'smsStatus'      => $smsStatus,
            'whatsappStatus' => $whatsappStatus,
            'emailStatus'    => $emailStatus,
        ];
    }

    protected function isChannelReady(array $status): bool
    {
        return (bool) (
            $status['system_ready']
            ?? $status['can_send']
            ?? $status['can_send_sms']
            ?? $status['ready']
            ?? $status['configured']
            ?? false
        );
    }

    // ================================================================
    //  EXISTING-AGENT DETECTION
    // ================================================================

    protected function isExistingActiveAgent($userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) return false;

            $isFieldAgent = method_exists($user, 'isFieldAgent')
                ? $user->isFieldAgent()
                : ($user->type === User::TYPE_FIELD_AGENT
                    || $user->hasRole('field-agent'));

            if (!$isFieldAgent) return false;

            if (!empty($user->phone_verified_at))       return true;
            if (!empty($user->invitation_accepted_at))  return true;
            if (($user->status ?? null) === 'active')   return true;

            return false;
        } catch (\Throwable $e) {
            Log::warning('isExistingActiveAgent check failed: ' . $e->getMessage());
            return false;
        }
    }

    // ================================================================
    //  NOTIFY ASSIGNED AGENT (IN-APP)
    // ================================================================

    protected function notifyAgentOfPlanAssignment(RegistrationPlan $plan, $agentId, ?string $instructions = null): void
    {
        try {
            $agent = User::find($agentId);
            if (!$agent) {
                Log::warning("Cannot notify missing agent {$agentId} about plan {$plan->id}");
                return;
            }

            $agent->notify(new PlanAssignedNotification(
                planId:          $plan->id,
                zone:            $plan->zone,
                section:         $plan->section,
                startDate:       $plan->registration_start_date?->format('M j, Y') ?? 'today',
                endDate:         $plan->registration_end_date?->format('M j, Y') ?? 'open-ended',
                namingPattern:   $plan->naming_pattern,
                estimatedHouses: $plan->estimated_houses,
                assignedByName:  Auth::user()?->name ?? 'System',
                instructions:    $instructions,
            ));

            Log::info("Plan-assignment notification sent to agent {$agentId}", [
                'plan_id' => $plan->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to notify agent {$agentId} about plan {$plan->id}: " . $e->getMessage());
        }
    }

    // ================================================================
    //  CHANNEL COLLECTION
    // ================================================================

    protected function collectInvitationChannels(Request $request, $agentIndex = null): array
    {
        if ($agentIndex !== null && $request->has("agent_invitation_channels.{$agentIndex}")) {
            $channels = (array) $request->input("agent_invitation_channels.{$agentIndex}");
        } elseif ($request->has('invitation_channels')) {
            $channels = (array) $request->input('invitation_channels');
        } else {
            $channels = [];
        }

        if (empty($channels)) {
            $legacy = null;
            if ($agentIndex !== null) {
                $legacy = $request->input("invitation_methods.{$agentIndex}")
                    ?? $request->input('invitation_methods.' . $agentIndex);
            }
            $legacy = $legacy ?? $request->input('invitation_method');

            if ($legacy === 'all_channels') {
                $channels = ['sms', 'whatsapp', 'email'];
            } elseif (!empty($legacy)) {
                $channels = [$legacy];
            }
        }

        $channelStatuses = $this->resolveChannelStatuses();
        $channels = array_values(array_filter($channels, function ($channel) use ($channelStatuses) {
            switch ($channel) {
                case 'sms':      return $this->isChannelReady($channelStatuses['smsStatus']);
                case 'whatsapp': return $this->isChannelReady($channelStatuses['whatsappStatus']);
                case 'email':    return $this->isChannelReady($channelStatuses['emailStatus']);
                default:         return false;
            }
        }));

        return array_values(array_unique($channels));
    }

    // ================================================================
    //  STATUS RESOLUTION HELPER
    // ================================================================

    protected function resolveFinalStatus(
        string $requestedStatus,
        bool $hasAgentsToAssign,
        bool $hasRequiredFields
    ): ?string {
        if (in_array($requestedStatus, ['assigned', 'in_progress'], true) && !$hasRequiredFields) {
            return null;
        }
        if ($requestedStatus === 'in_progress' && !$hasAgentsToAssign) {
            return null;
        }
        if ($requestedStatus === 'in_progress' && $hasAgentsToAssign && $hasRequiredFields) {
            return 'in_progress';
        }
        if ($hasAgentsToAssign && $hasRequiredFields && $requestedStatus === 'draft') {
            return 'assigned';
        }
        if (!$hasAgentsToAssign && in_array($requestedStatus, ['assigned', 'in_progress'], true)) {
            return 'draft';
        }
        return $requestedStatus;
    }

    // ================================================================
    //  INDEX
    // ================================================================

    public function index(Request $request)
    {
        $query = RegistrationPlan::with(['creator', 'assignedAgents.agent'])
            ->withCount('properties')
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('zone') && !empty($request->zone)) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }

        if ($request->has('agent_id') && !empty($request->agent_id)) {
            if ($request->agent_id === 'unassigned') {
                $query->whereDoesntHave('assignedAgents');
            } else {
                $query->whereHas('assignedAgents', function ($q) use ($request) {
                    $q->where('agent_id', $request->agent_id);
                });
            }
        }

        if ($request->has('section') && !empty($request->section)) {
            $query->where('section', 'like', '%' . $request->section . '%');
        }

        $plans = $query->paginate(20);

        $agents = User::where('type', User::TYPE_FIELD_AGENT)->get();
        $statuses = ['draft', 'assigned', 'in_progress', 'completed', 'cancelled'];

        $trashedCount = RegistrationPlan::onlyTrashed()->count();

        $overduePlansCount = RegistrationPlan::where('registration_end_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        return view('admin.registration-plans.index', compact(
            'plans', 'agents', 'statuses', 'trashedCount',
            'overduePlansCount', 'smsStatus', 'whatsappStatus', 'emailStatus'
        ));
    }

    // ================================================================
    //  CREATE
    // ================================================================

    public function create()
    {
        $fieldAgents = User::where(function ($query) {
                $query->where('type', User::TYPE_FIELD_AGENT)
                      ->orWhereHas('roles', function ($q) {
                          $q->where('slug', 'field-agent');
                      });
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $legacyCount = User::where('type', User::TYPE_FIELD_AGENT)->count();
        $roleBasedCount = User::whereHas('roles', function ($q) {
            $q->where('slug', 'field-agent');
        })->where('type', '!=', User::TYPE_FIELD_AGENT)->count();

        Log::info('Field agents loaded for plan creation', [
            'total' => $fieldAgents->count(),
            'legacy_field_agents' => $legacyCount,
            'role_based_field_agents' => $roleBasedCount
        ]);

        $zones = RegistrationPlan::distinct()->pluck('zone')->filter();
        $sections = RegistrationPlan::distinct()->pluck('section')->filter();

        $namingPatterns = [
            '{letter}{number}' => 'Letter + Number (A1, A2, B1, etc.)',
            '{number}'         => 'Number Only (1, 2, 3, etc.)',
            '{letter}'         => 'Letter Only (A, B, C, etc.)',
            '{number}{letter}' => 'Number + Letter (1A, 2A, 1B, etc.)',
            'custom'           => 'Custom Pattern'
        ];

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        return view('admin.registration-plans.create', compact(
            'fieldAgents',
            'zones',
            'sections',
            'namingPatterns',
            'smsStatus',
            'whatsappStatus',
            'emailStatus'
        ));
    }

    // ================================================================
    //  STORE
    // ================================================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assigned_agent_id'   => 'nullable|exists:users,id',
            'agent_name'          => 'nullable|string|max:255',
            'agent_phone'         => 'nullable|string|max:20',
            'agent_email'         => 'nullable|email',
            'invitation_method'   => 'nullable|in:sms,whatsapp,email,all_channels',

            'invitation_channels'   => 'nullable|array',
            'invitation_channels.*' => 'string|in:sms,whatsapp,email',

            'agent_types'             => 'nullable|array',
            'agent_types.*'           => 'in:existing,new',
            'assigned_agent_ids'      => 'nullable|array',
            'assigned_agent_ids.*'    => ['nullable', new FieldAgentExists],
            'agent_phones'            => 'nullable|array',
            'agent_phones.*'          => 'nullable|string|max:20',
            'agent_names'             => 'nullable|array',
            'agent_names.*'           => 'nullable|string|max:255',
            'agent_emails'            => 'nullable|array',
            'agent_emails.*'          => 'nullable|email',
            'invitation_methods'      => 'nullable|array',
            'invitation_methods.*'    => 'nullable|in:sms,whatsapp,email,all_channels',

            'agent_invitation_channels'   => 'nullable|array',

            'zone'                      => 'required|string|max:100',
            'section'                   => 'nullable|string|max:100',
            'naming_pattern'            => 'required|string|max:100',
            'custom_pattern'            => 'nullable|string|max:100',
            'starting_point'            => 'required|string|max:50',
            'estimated_houses'          => 'required|integer|min:1|max:1000',
            'sequence_type'             => 'required|in:sequential,even_only,odd_only',
            'registration_start_date'   => 'nullable|date',
            'registration_end_date'     => 'nullable|date|after_or_equal:registration_start_date',
            'instructions'              => 'nullable|string',
            'boundaries_description'    => 'nullable|string',
            'agent_assignment_type'     => 'required|in:single,multiple',
        ]);

        if ($validated['naming_pattern'] === 'custom' && !empty($validated['custom_pattern'])) {
            $validated['naming_pattern'] = $validated['custom_pattern'];
        }

        $patternValidation = $this->validateNamingPattern(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['sequence_type']
        );
        if (!$patternValidation['valid']) {
            return back()->with('error', $patternValidation['message'])->withInput();
        }

        DB::beginTransaction();

        try {
            $conflictCheck = $this->checkNamingPatternConflicts(
                $validated['naming_pattern'],
                $validated['starting_point'],
                null,
                $validated['zone'],
                $validated['section']
            );

            if ($conflictCheck['has_conflict']) {
                return back()->with('error', $conflictCheck['message'])->withInput();
            }

            $nextAvailableName = $validated['starting_point'];

            $assignedAgentIds  = [];
            $invitationsSent   = [];
            $newAgentsCreated  = [];
            $skippedExisting   = [];

            if ($validated['agent_assignment_type'] === 'single') {
                if (!empty($validated['assigned_agent_id'])) {
                    $assignedUser = User::find($validated['assigned_agent_id']);
                    if (!$assignedUser || !$assignedUser->isFieldAgent()) {
                        return back()->with('error', 'The assigned user must be a field agent.')->withInput();
                    }
                    $assignedAgentIds[] = $validated['assigned_agent_id'];
                } elseif (!empty($validated['agent_phone']) && !empty($validated['agent_name'])) {
                    $agentResult = $this->agentInvitationService->findOrCreateAgent([
                        'phone' => $validated['agent_phone'],
                        'name'  => $validated['agent_name'],
                        'email' => $validated['agent_email'] ?? null,
                    ]);

                    if (!$agentResult['success']) {
                        return back()->with('error', $agentResult['message'])->withInput();
                    }

                    $assignedAgentIds[] = $agentResult['agent_id'];
                    $newAgentsCreated[$agentResult['agent_id']] = [
                        'phone' => $validated['agent_phone'],
                        'name'  => $validated['agent_name'],
                        'email' => $validated['agent_email'] ?? null,
                    ];
                }
            } else {
                if (!empty($validated['agent_types'])) {
                    foreach ($validated['agent_types'] as $index => $agentType) {
                        if ($agentType === 'existing' && !empty($validated['assigned_agent_ids'][$index])) {
                            $agentId = $validated['assigned_agent_ids'][$index];
                            $assignedUser = User::find($agentId);
                            if (!$assignedUser || !$assignedUser->isFieldAgent()) {
                                return back()->with('error', 'The assigned user must be a field agent.')->withInput();
                            }
                            $assignedAgentIds[] = $agentId;
                        } elseif ($agentType === 'new' && !empty($validated['agent_phones'][$index]) && !empty($validated['agent_names'][$index])) {
                            $agentResult = $this->agentInvitationService->findOrCreateAgent([
                                'phone' => $validated['agent_phones'][$index],
                                'name'  => $validated['agent_names'][$index],
                                'email' => $validated['agent_emails'][$index] ?? null,
                            ]);

                            if (!$agentResult['success']) {
                                return back()->with('error', $agentResult['message'])->withInput();
                            }

                            $assignedAgentIds[] = $agentResult['agent_id'];
                            $newAgentsCreated[$agentResult['agent_id']] = [
                                'phone' => $validated['agent_phones'][$index],
                                'name'  => $validated['agent_names'][$index],
                                'email' => $validated['agent_emails'][$index] ?? null,
                            ];
                        }
                    }
                }
            }

            if (!empty($assignedAgentIds)) {
                if (empty($validated['registration_start_date']) || empty($validated['registration_end_date'])) {
                    return back()->with('error', 'Start date and end date are required when assigning field agents.')->withInput();
                }
            } else {
                $validated['registration_start_date'] = null;
                $validated['registration_end_date'] = null;
                $validated['instructions'] = null;
            }

            $plan = RegistrationPlan::create([
                'created_by'                => Auth::id(),
                'zone'                      => $validated['zone'],
                'section'                   => $validated['section'],
                'naming_pattern'            => $validated['naming_pattern'],
                'starting_point'            => $validated['starting_point'],
                'next_available_name'       => $nextAvailableName,
                'estimated_houses'          => $validated['estimated_houses'],
                'sequence_type'             => $validated['sequence_type'],
                'registration_start_date'   => $validated['registration_start_date'],
                'registration_end_date'     => $validated['registration_end_date'],
                'instructions'              => $validated['instructions'],
                'boundaries_description'    => $validated['boundaries_description'],
                'status'                    => !empty($assignedAgentIds) ? 'assigned' : 'draft',
                'agent_assignment_type'     => $validated['agent_assignment_type'],
            ]);

            foreach ($assignedAgentIds as $index => $agentId) {
                PlanAgentAssignment::create([
                    'plan_id'     => $plan->id,
                    'agent_id'    => $agentId,
                    'assigned_by' => Auth::id(),
                    'is_active'   => true,
                ]);
            }

            foreach ($assignedAgentIds as $index => $agentId) {
                $this->notifyAgentOfPlanAssignment(
                    $plan,
                    $agentId,
                    $validated['instructions'] ?? null
                );

                if ($this->isExistingActiveAgent($agentId)) {
                    $skippedExisting[] = $agentId;
                    Log::info("Skipping channel invitation for existing active agent {$agentId} on plan {$plan->id}");
                    continue;
                }

                if ($validated['agent_assignment_type'] === 'single') {
                    $channels = $this->collectInvitationChannels($request);
                } else {
                    $channels = $this->collectInvitationChannels($request, $agentId);
                }

                if (empty($channels)) {
                    Log::info("No invitation channels selected for agent {$agentId} in plan {$plan->id}");
                    continue;
                }

                $invitationResult = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
                    $plan->id,
                    $agentId,
                    $channels,
                    $validated['instructions'] ?? null
                );

                if (!empty($invitationResult['success'])) {
                    $invitationsSent[] = [
                        'agent_id' => $agentId,
                        'channels' => $invitationResult['channels_successful'] ?? $channels,
                    ];
                }
            }

            DB::commit();

            $successMessage = '✅ Registration plan created successfully!';

            if (!empty($assignedAgentIds)) {
                $agentCount      = count($assignedAgentIds);
                $invitationCount = count($invitationsSent);

                $successMessage .= "\n\n📋 Plan Details:\n";
                $successMessage .= "• Plan ID: #{$plan->id}\n";
                $successMessage .= "• Zone: {$plan->zone}\n";
                if ($plan->section) {
                    $successMessage .= "• Section: {$plan->section}\n";
                }
                $successMessage .= "• Status: " . ucfirst($plan->status) . "\n";
                $successMessage .= "• Estimated Houses: {$plan->estimated_houses}\n";
                $successMessage .= "• Naming Pattern: {$plan->naming_pattern}\n";
                $successMessage .= "• Starting Point: {$plan->starting_point}\n";

                $successMessage .= "\n👥 Agent Assignment:\n";
                $successMessage .= "• Total Agents: {$agentCount}\n";
                $successMessage .= "• In-app notifications: {$agentCount} (all agents notified)\n";

                if ($invitationCount > 0) {
                    $successMessage .= "• Invitations Sent: {$invitationCount}\n";
                    $allChannels = [];
                    foreach ($invitationsSent as $inv) {
                        $allChannels = array_merge($allChannels, (array) ($inv['channels'] ?? []));
                    }
                    $allChannels = array_unique($allChannels);
                    $successMessage .= "• Channels Used: " . implode(', ', array_map('strtoupper', $allChannels)) . "\n";
                } else {
                    $successMessage .= "• No channel invitations sent (no channels selected, or agents already active)\n";
                }

                if (!empty($skippedExisting)) {
                    $successMessage .= "• Skipped channel invitation (already active): " . count($skippedExisting) . " agent(s)\n";
                }

                if (!empty($newAgentsCreated)) {
                    $successMessage .= "\n🆕 New Agents Created:\n";
                    foreach ($newAgentsCreated as $agentId => $agentData) {
                        $successMessage .= "• {$agentData['name']} - {$agentData['phone']}\n";
                    }
                }
            } else {
                $successMessage .= "\n\n📋 Plan saved as DRAFT (no agents assigned).\n";
                $successMessage .= "You can edit this plan later to assign field agents.";
            }

            $successMessage .= "\n\n💡 What would you like to do next?\n";
            $successMessage .= "• Create another plan using this form\n";
            $successMessage .= "• View the plan you just created (click the button below)";

            session()->flash('last_created_plan_id', $plan->id);
            session()->flash('last_created_plan_details', [
                'id'               => $plan->id,
                'zone'             => $plan->zone,
                'section'          => $plan->section,
                'status'           => $plan->status,
                'agent_count'      => count($assignedAgentIds),
                'invitation_count' => count($invitationsSent),
            ]);

            return redirect()->route('registration-plans.create')
                ->with('success', $successMessage)
                ->with('plan_created', true)
                ->with('created_plan_id', $plan->id)
                ->with('created_plan_zone', $plan->zone)
                ->with('created_plan_section', $plan->section)
                ->with('created_plan_status', $plan->status)
                ->with('agent_count', count($assignedAgentIds))
                ->with('invitation_count', count($invitationsSent))
                ->with('new_agents_created', $newAgentsCreated);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create registration plan: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return back()->with('error', 'Failed to create registration plan: ' . $e->getMessage())->withInput();
        }
    }

    // ================================================================
    //  SHOW
    // ================================================================

    public function show($id)
    {
        $registrationPlan = RegistrationPlan::withTrashed()->with([
            'creator',
            'assignedAgents.agent',
            'assignedAgents.assigner',
            'properties.landlord',
            'invitations' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        $progressPercentage = $registrationPlan->progress_percentage;

        $sequencePreview = $this->generateSequencePreview($registrationPlan);

        $recentProperties = $registrationPlan->properties()
            ->with('landlord')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $invitationStats = [
            'total'    => $registrationPlan->invitations->count(),
            'pending'  => $registrationPlan->invitations->where('status', 'sent')->count(),
            'accepted' => $registrationPlan->invitations->where('status', 'accepted')->count(),
            'expired'  => $registrationPlan->invitations->where('status', 'expired')->count(),
        ];

        $agentPerformance = [];
        foreach ($registrationPlan->assignedAgents as $assignment) {
            $agent = $assignment->agent;

            $agentPerformance[] = [
                'agent'                  => $agent,
                'assignment'             => $assignment,
                'properties_registered'  => $assignment->properties_registered,
                'assignment_date'        => $assignment->assigned_at,
                'is_active'              => $assignment->isActive(),
                'performance_metrics'    => $assignment->getPerformanceMetrics(),
                'assignment_summary'     => $assignment->getAssignmentSummary(),
            ];
        }

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        return view('admin.registration-plans.show', compact(
            'registrationPlan',
            'progressPercentage',
            'sequencePreview',
            'recentProperties',
            'invitationStats',
            'agentPerformance',
            'smsStatus',
            'whatsappStatus',
            'emailStatus'
        ));
    }

    // ================================================================
    //  EDIT
    // ================================================================

    public function edit($id)
    {
        $registrationPlan = RegistrationPlan::withTrashed()->with(['assignedAgents.agent'])->findOrFail($id);

        if (in_array($registrationPlan->status, ['completed', 'cancelled'])) {
            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('warning', 'Cannot edit a completed or cancelled plan.');
        }

        if ($registrationPlan->trashed()) {
            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('warning', 'Cannot edit a deleted plan. Restore it first.');
        }

        $fieldAgents = User::where(function ($query) {
                $query->where('type', User::TYPE_FIELD_AGENT)
                      ->orWhereHas('roles', function ($q) {
                          $q->where('slug', 'field-agent');
                      });
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $zones = RegistrationPlan::distinct()->pluck('zone')->filter();
        $sections = RegistrationPlan::distinct()->pluck('section')->filter();

        $namingPatterns = [
            '{letter}{number}' => 'Letter + Number (A1, A2, B1, etc.)',
            '{number}'         => 'Number Only (1, 2, 3, etc.)',
            '{letter}'         => 'Letter Only (A, B, C, etc.)',
            '{number}{letter}' => 'Number + Letter (1A, 2A, 1B, etc.)',
            'custom'           => 'Custom Pattern'
        ];

        $channelStatuses = $this->resolveChannelStatuses();
        $smsStatus      = $channelStatuses['smsStatus'];
        $whatsappStatus = $channelStatuses['whatsappStatus'];
        $emailStatus    = $channelStatuses['emailStatus'];

        return view('admin.registration-plans.edit', compact(
            'registrationPlan',
            'fieldAgents',
            'zones',
            'sections',
            'namingPatterns',
            'smsStatus',
            'whatsappStatus',
            'emailStatus'
        ));
    }

    // ================================================================
    //  UPDATE  ← THE FIX LIVES HERE
    // ================================================================
    //
    //  ORDER OF OPERATIONS (critical):
    //
    //    1. Validate
    //    2. Resolve $finalStatus
    //    3. Resolve the desired agent set (into $assignedAgentIds)
    //    4. Diff + sync the pivot (add / remove agents)  ← MUST BE BEFORE 5
    //    5. Fill + save the plan (status + fields)       ← model hook now sees correct agent count
    //    6. Send notifications + invitations
    //    7. Commit
    //
    //  Reversing 4 and 5 causes the model's `updating` hook to see
    //  zero active agents (because the pivot hasn't been written yet)
    //  and silently revert `assigned` back to `draft`.
    //
    public function update(Request $request, $id)
    {
        $registrationPlan = RegistrationPlan::withTrashed()->with(['assignedAgents'])->findOrFail($id);

        $validated = $request->validate([
            'assigned_agent_id'   => 'nullable|exists:users,id',
            'agent_name'          => 'nullable|string|max:255',
            'agent_phone'         => 'nullable|string|max:20',
            'agent_email'         => 'nullable|email',
            'invitation_method'   => 'nullable|in:sms,whatsapp,email,all_channels',

            'invitation_channels'   => 'nullable|array',
            'invitation_channels.*' => 'string|in:sms,whatsapp,email',

            'agent_types'             => 'nullable|array',
            'agent_types.*'           => 'in:existing,new',
            'assigned_agent_ids'      => 'nullable|array',
            'assigned_agent_ids.*'    => ['nullable', new FieldAgentExists],
            'agent_phones'            => 'nullable|array',
            'agent_phones.*'          => 'nullable|string|max:20',
            'agent_names'             => 'nullable|array',
            'agent_names.*'           => 'nullable|string|max:255',
            'agent_emails'            => 'nullable|array',
            'agent_emails.*'          => 'nullable|email',
            'invitation_methods'      => 'nullable|array',
            'invitation_methods.*'    => 'nullable|in:sms,whatsapp,email,all_channels',

            'agent_invitation_channels'   => 'nullable|array',

            'zone'                      => 'required|string|max:100',
            'section'                   => 'nullable|string|max:100',
            'naming_pattern'            => 'required|string|max:100',
            'custom_pattern'            => 'nullable|string|max:100',
            'starting_point'            => 'required|string|max:50',
            'estimated_houses'          => 'required|integer|min:1|max:1000',
            'sequence_type'             => 'required|in:sequential,even_only,odd_only',
            'registration_start_date'   => 'nullable|date',
            'registration_end_date'     => 'nullable|date|after_or_equal:registration_start_date',
            'instructions'              => 'nullable|string',
            'boundaries_description'    => 'nullable|string',
            'status'                    => 'required|in:draft,assigned,in_progress',
            'agent_assignment_type'     => 'required|in:single,multiple',
        ]);

        // ------------------------------------------------------------------
        // Resolve custom naming pattern
        // ------------------------------------------------------------------
        if ($validated['naming_pattern'] === 'custom' && !empty($validated['custom_pattern'])) {
            $validated['naming_pattern'] = $validated['custom_pattern'];
        }

        $patternValidation = $this->validateNamingPattern(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['sequence_type']
        );
        if (!$patternValidation['valid']) {
            return back()->with('error', $patternValidation['message'])->withInput();
        }

        $conflictCheck = $this->checkNamingPatternConflicts(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $registrationPlan->id,
            $validated['zone'],
            $validated['section']
        );

        if ($conflictCheck['has_conflict']) {
            return back()->with('error', $conflictCheck['message'])->withInput();
        }

        DB::beginTransaction();

        try {
            // ==============================================================
            // STEP 1: Determine whether agents are being assigned
            // ==============================================================
            $hasAgentsToAssign = false;
            if ($validated['agent_assignment_type'] === 'single') {
                $hasAgentsToAssign = !empty($validated['assigned_agent_id'])
                    || (!empty($validated['agent_phone']) && !empty($validated['agent_name']));
            } else {
                $hasAgentsToAssign = !empty($validated['agent_types']);
            }

            $hasRequiredFields = !empty($validated['naming_pattern'])
                && !empty($validated['starting_point'])
                && !empty($validated['sequence_type'])
                && !empty($validated['estimated_houses']);

            // ==============================================================
            // STEP 2: Resolve final status
            // ==============================================================
            $finalStatus = $this->resolveFinalStatus(
                $validated['status'],
                $hasAgentsToAssign,
                $hasRequiredFields
            );

            if ($finalStatus === null) {
                DB::rollBack();

                $msg = "Cannot set plan status to '{$validated['status']}' because the required fields or agents are missing.";
                if (in_array($validated['status'], ['assigned', 'in_progress'], true) && !$hasRequiredFields) {
                    $msg = 'Cannot set plan status to "' . $validated['status'] . '" because required fields (naming pattern, starting point, sequence type, or estimated houses) are missing.';
                } elseif ($validated['status'] === 'in_progress' && !$hasAgentsToAssign) {
                    $msg = 'Cannot set plan status to "in_progress" because no agents are assigned to the plan.';
                }

                return back()->with('error', $msg)->withInput();
            }

            if ($finalStatus !== $validated['status']) {
                Log::info('Plan status auto-adjusted during update', [
                    'plan_id'          => $registrationPlan->id,
                    'requested_status' => $validated['status'],
                    'final_status'     => $finalStatus,
                    'has_agents'       => $hasAgentsToAssign,
                    'has_required'     => $hasRequiredFields,
                ]);
            }

            // ==============================================================
            // STEP 3: Resolve desired agent set (DO NOT touch DB yet)
            // ==============================================================
            $assignedAgentIds  = [];
            $invitationsSent   = [];
            $newAgentsCreated  = [];
            $skippedExisting   = [];

            if ($validated['agent_assignment_type'] === 'single') {
                if (!empty($validated['assigned_agent_id'])) {
                    $assignedUser = User::find($validated['assigned_agent_id']);
                    if (!$assignedUser || !$assignedUser->isFieldAgent()) {
                        throw new \Exception('The assigned user must be a field agent.');
                    }
                    $assignedAgentIds[] = $validated['assigned_agent_id'];
                } elseif (!empty($validated['agent_phone']) && !empty($validated['agent_name'])) {
                    $agentResult = $this->agentInvitationService->findOrCreateAgent([
                        'phone' => $validated['agent_phone'],
                        'name'  => $validated['agent_name'],
                        'email' => $validated['agent_email'] ?? null,
                    ]);

                    if (!$agentResult['success']) {
                        throw new \Exception($agentResult['message']);
                    }

                    $assignedAgentIds[] = $agentResult['agent_id'];
                    $newAgentsCreated[$agentResult['agent_id']] = [
                        'phone' => $validated['agent_phone'],
                        'name'  => $validated['agent_name'],
                        'email' => $validated['agent_email'] ?? null,
                    ];
                }
            } else {
                if (!empty($validated['agent_types'])) {
                    foreach ($validated['agent_types'] as $index => $agentType) {
                        if ($agentType === 'existing' && !empty($validated['assigned_agent_ids'][$index])) {
                            $agentId = $validated['assigned_agent_ids'][$index];
                            $assignedUser = User::find($agentId);
                            if (!$assignedUser || !$assignedUser->isFieldAgent()) {
                                throw new \Exception('The assigned user must be a field agent.');
                            }
                            $assignedAgentIds[] = $agentId;
                        } elseif ($agentType === 'new' && !empty($validated['agent_phones'][$index]) && !empty($validated['agent_names'][$index])) {
                            $agentResult = $this->agentInvitationService->findOrCreateAgent([
                                'phone' => $validated['agent_phones'][$index],
                                'name'  => $validated['agent_names'][$index],
                                'email' => $validated['agent_emails'][$index] ?? null,
                            ]);

                            if (!$agentResult['success']) {
                                throw new \Exception($agentResult['message']);
                            }

                            $assignedAgentIds[] = $agentResult['agent_id'];
                            $newAgentsCreated[$agentResult['agent_id']] = [
                                'phone' => $validated['agent_phones'][$index],
                                'name'  => $validated['agent_names'][$index],
                                'email' => $validated['agent_emails'][$index] ?? null,
                            ];
                        }
                    }
                }
            }

            // ==============================================================
            // STEP 4: Sync the pivot BEFORE saving the plan
            //         (this is what makes the model's auto-status hook
            //          see the correct agent count)
            // ==============================================================
            $currentAgentIds = $registrationPlan->assignedAgents()
                ->where('is_active', true)
                ->pluck('agent_id')
                ->toArray();

            $newAgentIds     = array_values(array_diff($assignedAgentIds, $currentAgentIds));
            $removedAgentIds = array_values(array_diff($currentAgentIds, $assignedAgentIds));

            // Deactivate removed agents
            foreach ($removedAgentIds as $agentId) {
                $assignment = PlanAgentAssignment::where('plan_id', $registrationPlan->id)
                    ->where('agent_id', $agentId)
                    ->where('is_active', true)
                    ->first();

                if ($assignment) {
                    $assignment->markAsInactive('Removed during plan update');
                }
            }

            // Create new agent assignments
            foreach ($newAgentIds as $agentId) {
                PlanAgentAssignment::create([
                    'plan_id'     => $registrationPlan->id,
                    'agent_id'    => $agentId,
                    'assigned_by' => Auth::id(),
                    'is_active'   => true,
                ]);
            }

            // Bust the relation caches so the model hook queries fresh data
            $registrationPlan->unsetRelation('assignedAgents');
            $registrationPlan->unsetRelation('planAssignments');
            $registrationPlan->unsetRelation('activeAgents');

            // ==============================================================
            // STEP 5: NOW save the plan (pivot is already correct)
            // ==============================================================
            $updateData = [
                'zone'                      => $validated['zone'],
                'section'                   => $validated['section'] ?? null,
                'naming_pattern'            => $validated['naming_pattern'],
                'starting_point'            => $validated['starting_point'],
                'estimated_houses'          => $validated['estimated_houses'],
                'sequence_type'             => $validated['sequence_type'],
                'registration_start_date'   => $validated['registration_start_date'] ?? null,
                'registration_end_date'     => $validated['registration_end_date'] ?? null,
                'instructions'              => $validated['instructions'] ?? null,
                'boundaries_description'    => $validated['boundaries_description'] ?? null,
                'agent_assignment_type'     => $validated['agent_assignment_type'],
                'status'                    => $finalStatus,
            ];

            $registrationPlan->fill($updateData);
            $registrationPlan->save();

            // Diagnostic log (keep in debug mode)
            if (config('app.debug')) {
                $activeCount = $registrationPlan->assignedAgents()
                    ->where('is_active', true)
                    ->count();

                Log::info('[UPDATE] after save', [
                    'plan_id'        => $registrationPlan->id,
                    'status_in_mem'  => $registrationPlan->status,
                    'status_in_db'   => RegistrationPlan::find($registrationPlan->id)->status,
                    'requested'      => $validated['status'],
                    'final'          => $finalStatus,
                    'new_agents'     => $newAgentIds,
                    'removed_agents' => $removedAgentIds,
                    'active_count'   => $activeCount,
                ]);
            }

            // ==============================================================
            // STEP 6: Notify + invite NEW agents
            // ==============================================================
            foreach ($newAgentIds as $agentId) {
                $this->notifyAgentOfPlanAssignment(
                    $registrationPlan,
                    $agentId,
                    $validated['instructions'] ?? null
                );

                if ($this->isExistingActiveAgent($agentId)) {
                    $skippedExisting[] = $agentId;
                    Log::info("Skipping channel invitation for existing active agent {$agentId} on plan update {$registrationPlan->id}");
                    continue;
                }

                if ($validated['agent_assignment_type'] === 'single') {
                    $channels = $this->collectInvitationChannels($request);
                } else {
                    $channels = $this->collectInvitationChannels($request, $agentId);
                }

                if (empty($channels)) {
                    Log::info("No invitation channels selected for agent {$agentId} in plan update {$registrationPlan->id}");
                    continue;
                }

                $invitationResult = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
                    $registrationPlan->id,
                    $agentId,
                    $channels,
                    $validated['instructions'] ?? null
                );

                if (!empty($invitationResult['success'])) {
                    $invitationsSent[] = [
                        'agent_id' => $agentId,
                        'channels' => $invitationResult['channels_successful'] ?? $channels,
                    ];
                }
            }

            DB::commit();

            // Refresh so the success message reflects what's actually in the DB
            $registrationPlan->refresh();

            // ==============================================================
            // Success message
            // ==============================================================
            $successMessage = 'Registration plan updated successfully!';

            if ($finalStatus !== $validated['status']) {
                $successMessage .= " Plan status was automatically changed from '{$validated['status']}' to '{$finalStatus}'.";
            }

            if (!empty($newAgentIds)) {
                $invitationCount = count($invitationsSent);
                $successMessage .= ' ' . count($newAgentIds) . ' new agent(s) added.';
                $successMessage .= ' ' . count($newAgentIds) . ' in-app notification(s) sent.';
                if ($invitationCount > 0) {
                    $successMessage .= " {$invitationCount} channel invitation(s) sent.";
                } else {
                    $successMessage .= ' No channel invitations sent (no channels selected, or agents were already active).';
                }
            }

            if (!empty($skippedExisting)) {
                $successMessage .= ' Skipped channel invitation for ' . count($skippedExisting) . ' already-active agent(s).';
            }

            if (!empty($removedAgentIds)) {
                $successMessage .= ' ' . count($removedAgentIds) . ' agent(s) removed.';
            }

            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update registration plan: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return back()->with('error', 'Failed to update registration plan: ' . $e->getMessage())->withInput();
        }
    }

    // ================================================================
    //  DESTROY
    // ================================================================

    public function destroy($id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        if ($registrationPlan->properties()->exists()) {
            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('error', 'Cannot delete a plan that has registered properties. Cancel the plan instead.');
        }

        if (in_array($registrationPlan->status, ['assigned', 'in_progress'])) {
            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('error', 'Cannot delete an active plan. Cancel the plan first.');
        }

        DB::beginTransaction();

        try {
            $registrationPlan->delete();
            DB::commit();

            return redirect()->route('registration-plans.index')
                ->with('success', 'Registration plan moved to trash successfully!');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete registration plan. Please try again.');
        }
    }

    // ================================================================
    //  HELPERS
    // ================================================================

    private function checkNamingPatternConflicts($namingPattern, $startingPoint, $planId = null, $zone, $section = null)
    {
        $query = Property::whereHas('registrationPlan', function ($q) use ($zone, $section) {
                $q->where('zone', $zone);
                if ($section) {
                    $q->where('section', $section);
                }
            })
            ->where('registration_pattern', $startingPoint);

        if ($planId) {
            $query->whereHas('registrationPlan', function ($q) use ($planId) {
                $q->where('id', '!=', $planId);
            });
        }

        $existingProperty = $query->first();

        if ($existingProperty) {
            $conflictingPlan = $existingProperty->registrationPlan;

            return [
                'has_conflict' => true,
                'message'      => "Pattern '{$startingPoint}' already exists in plan: " .
                                   "{$conflictingPlan->zone}" .
                                   ($conflictingPlan->section ? " - {$conflictingPlan->section}" : "") .
                                   ". Please use a different pattern."
            ];
        }

        return ['has_conflict' => false];
    }

    private function validateNamingPattern($pattern, $startingPoint, $sequenceType)
    {
        if (!preg_match('/\{([a-zA-Z_]+)\}/', $pattern)) {
            return [
                'valid'   => false,
                'message' => 'Naming pattern must contain at least one placeholder like {letter}, {number}, etc.'
            ];
        }

        $isNumberThenLetter = strpos($pattern, '{number}') !== false &&
                              strpos($pattern, '{letter}') !== false &&
                              strpos($pattern, '{number}') < strpos($pattern, '{letter}');

        $isLetterThenNumber = strpos($pattern, '{letter}') !== false &&
                              strpos($pattern, '{number}') !== false &&
                              strpos($pattern, '{letter}') < strpos($pattern, '{number}');

        if ($isNumberThenLetter) {
            if (!preg_match('/^\d+[A-Za-z]+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be in format like 1A, 2B, etc. for number+letter patterns.'];
            }

            $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'For even-only sequences, starting point must have an even number (2A, 4B, etc.).'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'For odd-only sequences, starting point must have an odd number (1A, 3B, etc.).'];
            }
        } elseif ($isLetterThenNumber) {
            if (!preg_match('/^[A-Za-z]+\d+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be in format like A1, B2, etc. for letter+number patterns.'];
            }

            $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'For even-only sequences, starting point must have an even number (A2, B4, etc.).'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'For odd-only sequences, starting point must have an odd number (A1, B3, etc.).'];
            }
        } elseif (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            if (preg_match('/^[A-Za-z]+\d+$/', $startingPoint)) {
                $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
                if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                    return ['valid' => false, 'message' => 'For even-only sequences, starting point must have an even number (A2, B4, etc.).'];
                }
                if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                    return ['valid' => false, 'message' => 'For odd-only sequences, starting point must have an odd number (A1, B3, etc.).'];
                }
                return ['valid' => true, 'message' => 'Pattern is valid'];
            } elseif (preg_match('/^\d+[A-Za-z]+$/', $startingPoint)) {
                $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
                if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                    return ['valid' => false, 'message' => 'For even-only sequences, starting point must have an even number (2A, 4B, etc.).'];
                }
                if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                    return ['valid' => false, 'message' => 'For odd-only sequences, starting point must have an odd number (1A, 3B, etc.).'];
                }
                return ['valid' => true, 'message' => 'Pattern is valid'];
            } else {
                return ['valid' => false, 'message' => 'Starting point must be in format like A1, B2 (letter+number) OR 1A, 2B (number+letter).'];
            }
        } elseif (strpos($pattern, '{letter}') !== false) {
            if (!preg_match('/^[A-Za-z]+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must contain only letters for letter-only patterns.'];
            }
        } elseif (strpos($pattern, '{number}') !== false) {
            if (!is_numeric($startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be a number for number-only patterns.'];
            }

            $number = intval($startingPoint);
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'For even-only sequences, starting point must be an even number (2, 4, 6, etc.).'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'For odd-only sequences, starting point must be an odd number (1, 3, 5, etc.).'];
            }
        }

        return ['valid' => true, 'message' => 'Pattern is valid'];
    }

    private function generateSequencePreview(RegistrationPlan $plan, $count = 5)
    {
        $preview = [];
        $currentName = $plan->next_available_name ?? $plan->starting_point;

        for ($i = 0; $i < $count; $i++) {
            $preview[] = $currentName;
            $currentName = $this->generateNextName($currentName, $plan->naming_pattern, $plan->sequence_type);
        }

        return $preview;
    }

    private function generateNextName($currentName, $pattern, $sequenceType)
    {
        if (empty($currentName)) {
            return $this->getStartingName($pattern, $sequenceType);
        }

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $this->generateCombinedNextName($currentName, $sequenceType);
        } elseif (strpos($pattern, '{letter}') !== false) {
            return $this->generateLetterNextName($currentName);
        } elseif (strpos($pattern, '{number}') !== false) {
            return $this->generateNumberNextName($currentName, $sequenceType);
        }

        return $this->generateSimpleNextName($currentName);
    }

    private function getStartingName($pattern, $sequenceType)
    {
        $isNumberThenLetter = strpos($pattern, '{number}') !== false &&
                              strpos($pattern, '{letter}') !== false &&
                              strpos($pattern, '{number}') < strpos($pattern, '{letter}');

        if ($isNumberThenLetter) {
            return $sequenceType === 'even_only' ? '2A' : ($sequenceType === 'odd_only' ? '1A' : '1A');
        } elseif (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $sequenceType === 'even_only' ? 'A2' : ($sequenceType === 'odd_only' ? 'A1' : 'A1');
        } elseif (strpos($pattern, '{letter}') !== false) {
            return 'A';
        } elseif (strpos($pattern, '{number}') !== false) {
            return $sequenceType === 'even_only' ? '2' : '1';
        }

        return '001';
    }

    private function generateCombinedNextName($currentName, $sequenceType)
    {
        if (preg_match('/(\d+)([A-Za-z]+)/', $currentName, $matches)) {
            $number = (int) $matches[1];
            $letter = $matches[2];

            $number += 1;

            switch ($sequenceType) {
                case 'even_only':
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case 'odd_only':
                    if ($number % 2 === 0) $number += 1;
                    break;
            }

            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
            }

            return $number . $letter;
        } elseif (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
            $letter = $matches[1];
            $number = (int) $matches[2];

            $number += 1;

            switch ($sequenceType) {
                case 'even_only':
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case 'odd_only':
                    if ($number % 2 === 0) $number += 1;
                    break;
            }

            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
            }

            return $letter . $number;
        }

        return $this->generateSimpleNextName($currentName);
    }

    private function incrementLetters($letters)
    {
        $length = strlen($letters);
        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return $letters;
            }
            $letters[$i] = 'A';
        }
        return 'A' . $letters;
    }

    private function generateLetterNextName($currentName)
    {
        return $this->incrementLetters($currentName);
    }

    private function generateNumberNextName($currentName, $sequenceType)
    {
        $number = (int) $currentName;

        switch ($sequenceType) {
            case 'even_only':
                return $number % 2 === 0 ? $number + 2 : $number + 1;
            case 'odd_only':
                return $number % 2 === 1 ? $number + 2 : $number + 1;
            default:
                return $number + 1;
        }
    }

    private function generateSimpleNextName($currentName)
    {
        if (preg_match('/(.*?)(\d+)$/', $currentName, $matches)) {
            $prefix = $matches[1];
            $number = (int) $matches[2];
            return $prefix . ($number + 1);
        }

        return $currentName . '-1';
    }
}
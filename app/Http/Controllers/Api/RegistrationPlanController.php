<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRegistrationPlanRequest;
use App\Http\Requests\Api\UpdateRegistrationPlanRequest;
use App\Http\Resources\RegistrationPlanResource;
use App\Models\PlanAgentAssignment;
use App\Models\Property;
use App\Models\RegistrationPlan;
use App\Models\User;
use App\Services\AgentInvitationService;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationPlanController extends Controller
{
    public function __construct(
        protected AgentInvitationService $agentInvitationService,
        protected SmsService $smsService,
        protected WhatsAppService $whatsappService,
        protected EmailService $emailService,
    ) {}

    /**
     * GET /api/registration-plans
     */
    public function index(Request $request): JsonResponse
    {
        $query = RegistrationPlan::with(['creator', 'assignedAgents.agent'])
            ->withCount('properties')
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('zone')) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }
        if ($request->filled('section')) {
            $query->where('section', 'like', '%' . $request->section . '%');
        }
        if ($request->filled('agent_id')) {
            if ($request->agent_id === 'unassigned') {
                $query->whereDoesntHave('assignedAgents');
            } else {
                $query->whereHas('assignedAgents', fn($q) => $q->where('agent_id', $request->agent_id));
            }
        }

        $plans = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => RegistrationPlanResource::collection($plans->items()),
            'meta' => [
                'current_page'  => $plans->currentPage(),
                'last_page'     => $plans->lastPage(),
                'per_page'      => $plans->perPage(),
                'total'         => $plans->total(),
                'trashed_count' => RegistrationPlan::onlyTrashed()->count(),
                'overdue_count' => RegistrationPlan::where('registration_end_date', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->count(),
            ],
            'services' => [
                'sms'      => $this->smsService->getSystemStatus(),
                'whatsapp' => $this->whatsappService->getSystemStatus(),
                'email'    => $this->emailService->getSystemStatus(),
            ],
        ]);
    }

    /**
     * GET /api/registration-plans/{id}
     */
    public function show(int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->with([
            'creator',
            'assignedAgents.agent',
            'assignedAgents.assigner',
            'properties.landlord',
            'invitations' => fn($q) => $q->orderByDesc('created_at'),
        ])->findOrFail($id);

        // CHANGED: mirror web controller's `recentProperties` slice
        $recentProperties = $plan->properties()
            ->with('landlord')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $sequencePreview = $this->generateSequencePreview($plan);

        return response()->json([
            'data'                => new RegistrationPlanResource($plan),
            'progress_percentage' => $plan->progress_percentage,
            'sequence_preview'    => $sequencePreview,
            'recent_properties'   => $recentProperties,
            'invitation_stats'    => [
                'total'    => $plan->invitations->count(),
                'pending'  => $plan->invitations->where('status', 'sent')->count(),
                'accepted' => $plan->invitations->where('status', 'accepted')->count(),
                'expired'  => $plan->invitations->where('status', 'expired')->count(),
            ],
            'agent_performance' => $plan->assignedAgents->map(fn($a) => [
                'agent_id'              => $a->agent_id,
                'agent'                 => $a->agent,
                'properties_registered' => $a->properties_registered,
                'assignment_date'       => $a->assigned_at,
                'is_active'             => $a->isActive(),
                'performance_metrics'   => $a->getPerformanceMetrics(),
                'assignment_summary'    => $a->getAssignmentSummary(),
            ]),
        ]);
    }

    /**
     * POST /api/registration-plans
     */
    public function store(StoreRegistrationPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['naming_pattern'] ?? null) === 'custom' && !empty($validated['custom_pattern'])) {
            $validated['naming_pattern'] = $validated['custom_pattern'];
        }

        $patternValidation = $this->validateNamingPattern(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['sequence_type']
        );
        if (!$patternValidation['valid']) {
            return response()->json(['message' => $patternValidation['message']], 422);
        }

        $conflict = $this->checkNamingPatternConflicts(
            $validated['naming_pattern'],
            $validated['starting_point'],
            null,
            $validated['zone'],
            $validated['section'] ?? null
        );
        if ($conflict['has_conflict']) {
            return response()->json(['message' => $conflict['message']], 409);
        }

        DB::beginTransaction();

        try {
            // CHANGED: resolveAgents now returns agent_ids keyed against the
            // ORIGINAL request row index so invitations map correctly.
            $assignment = $this->resolveAgents($validated);

            if (!empty($assignment['errors'])) {
                DB::rollBack();
                return response()->json(['message' => $assignment['errors'][0]], 422);
            }

            // CHANGED: agent_ids is now an associative array [requestIndex => agentId].
            // For 'single' mode the key is 0; for 'multiple' it's the original $i.
            $agentIndexMap    = $assignment['agent_ids'];
            $assignedAgentIds = array_values($agentIndexMap);
            $newAgentsCreated = $assignment['new_agents'];

            // Date enforcement (identical to web)
            if (!empty($assignedAgentIds)) {
                if (empty($validated['registration_start_date']) || empty($validated['registration_end_date'])) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Start date and end date are required when assigning field agents.',
                    ], 422);
                }
            } else {
                $validated['registration_start_date'] = null;
                $validated['registration_end_date']   = null;
                $validated['instructions']            = null;
            }

            $plan = RegistrationPlan::create([
                'created_by'              => Auth::id(),
                'zone'                    => $validated['zone'],
                'section'                 => $validated['section'] ?? null,
                'naming_pattern'          => $validated['naming_pattern'],
                'starting_point'          => $validated['starting_point'],
                'next_available_name'     => $validated['starting_point'],
                'estimated_houses'        => $validated['estimated_houses'],
                'sequence_type'           => $validated['sequence_type'],
                'registration_start_date' => $validated['registration_start_date'],
                'registration_end_date'   => $validated['registration_end_date'],
                'instructions'            => $validated['instructions'] ?? null,
                'boundaries_description'  => $validated['boundaries_description'] ?? null,
                'status'                  => !empty($assignedAgentIds) ? 'assigned' : 'draft',
                'agent_assignment_type'   => $validated['agent_assignment_type'],
            ]);

            $invitationsSent = $this->persistAssignmentsAndInvite(
                $plan,
                $agentIndexMap,
                $validated
            );

            DB::commit();

            return response()->json([
                'message' => 'Registration plan created successfully.',
                'data'    => new RegistrationPlanResource($plan->fresh(['creator', 'assignedAgents.agent'])),
                'meta'    => [
                    'agent_count'        => count($assignedAgentIds),
                    'invitation_count'   => count($invitationsSent),
                    'invitations_sent'   => $invitationsSent,
                    'new_agents_created' => $newAgentsCreated,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API plan create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to create registration plan.'], 500);
        }
    }

    /**
     * PUT/PATCH /api/registration-plans/{id}
     */
    public function update(UpdateRegistrationPlanRequest $request, int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->with('assignedAgents')->findOrFail($id);

        if ($plan->trashed()) {
            return response()->json(['message' => 'Cannot edit a deleted plan. Restore it first.'], 409);
        }
        if (in_array($plan->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Cannot edit a completed or cancelled plan.'], 409);
        }

        $validated = $request->validated();

        if (($validated['naming_pattern'] ?? null) === 'custom' && !empty($validated['custom_pattern'])) {
            $validated['naming_pattern'] = $validated['custom_pattern'];
        }

        $patternValidation = $this->validateNamingPattern(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['sequence_type']
        );
        if (!$patternValidation['valid']) {
            return response()->json(['message' => $patternValidation['message']], 422);
        }

        $conflict = $this->checkNamingPatternConflicts(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $plan->id,
            $validated['zone'],
            $validated['section'] ?? null
        );
        if ($conflict['has_conflict']) {
            return response()->json(['message' => $conflict['message']], 409);
        }

        // ---- Resolve agents FIRST so status logic is based on what will actually be assigned ----
        $assignment = $this->resolveAgents($validated);
        if (!empty($assignment['errors'])) {
            return response()->json(['message' => $assignment['errors'][0]], 422);
        }
        $agentIndexMap    = $assignment['agent_ids'];
        $assignedAgentIds = array_values($agentIndexMap);
        $newAgentsCreated = $assignment['new_agents'];

        // ---- Status auto-correction (mirrors web controller) ----
        $hasAgentsToAssign = !empty($assignedAgentIds);
        $hasRequiredFields = !empty($validated['naming_pattern'])
            && !empty($validated['starting_point'])
            && !empty($validated['sequence_type'])
            && !empty($validated['estimated_houses']);

        $finalStatus = $validated['status'];

        if ($hasAgentsToAssign && $plan->status === 'draft' && $validated['status'] === 'draft') {
            $finalStatus = 'assigned';
        }
        if (!$hasAgentsToAssign && in_array($finalStatus, ['assigned', 'in_progress'], true)) {
            $finalStatus = 'draft';
        }
        if (in_array($finalStatus, ['assigned', 'in_progress'], true) && !$hasRequiredFields) {
            return response()->json([
                'message' => "Cannot set status to '{$finalStatus}' because required fields are missing.",
            ], 422);
        }

        // CHANGED: date enforcement — identical to web controller.
        if (!empty($assignedAgentIds)) {
            if (empty($validated['registration_start_date']) || empty($validated['registration_end_date'])) {
                return response()->json([
                    'message' => 'Start date and end date are required when assigning field agents.',
                ], 422);
            }
        } else {
            $validated['registration_start_date'] = null;
            $validated['registration_end_date']   = null;
            $validated['instructions']            = null;
        }

        DB::beginTransaction();

        try {
            $updateData = $validated;
            unset($updateData['status']);
            $updateData['status'] = $finalStatus;

            $plan->update($updateData);

            $currentAgentIds = $plan->assignedAgents->pluck('agent_id')->toArray();
            $newAgentIds     = array_values(array_diff($assignedAgentIds, $currentAgentIds));
            $removedAgentIds = array_values(array_diff($currentAgentIds, $assignedAgentIds));

            // Deactivate removed agents
            if (!empty($removedAgentIds)) {
                PlanAgentAssignment::where('plan_id', $plan->id)
                    ->whereIn('agent_id', $removedAgentIds)
                    ->where('is_active', true)
                    ->get()
                    ->each(fn($a) => $a->markAsInactive('Removed during plan update'));
            }

            // CHANGED: filter the index map down to only truly-new agents so
            // invitation methods still map to the correct request row.
            $newAgentIndexMap = array_filter(
                $agentIndexMap,
                fn($agentId) => in_array($agentId, $newAgentIds, true)
            );

            $invitationsSent = $this->persistAssignmentsAndInvite(
                $plan,
                $newAgentIndexMap,
                $validated
            );

            DB::commit();

            $message = 'Registration plan updated successfully.';
            if ($finalStatus !== $validated['status']) {
                $message .= " Status auto-changed to '{$finalStatus}'.";
            }

            return response()->json([
                'message' => $message,
                'data'    => new RegistrationPlanResource($plan->fresh(['creator', 'assignedAgents.agent'])),
                'meta'    => [
                    'status'             => $finalStatus,
                    'added_agents'       => $newAgentIds,
                    'removed_agents'     => $removedAgentIds,
                    'invitation_count'   => count($invitationsSent),
                    'invitations_sent'   => $invitationsSent,
                    'new_agents_created' => $newAgentsCreated,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API plan update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update registration plan.'], 500);
        }
    }

    /**
     * DELETE /api/registration-plans/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = RegistrationPlan::findOrFail($id);

        if ($plan->properties()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan with registered properties. Cancel it instead.',
            ], 409);
        }
        if (in_array($plan->status, ['assigned', 'in_progress'], true)) {
            return response()->json([
                'message' => 'Cannot delete an active plan. Cancel it first.',
            ], 409);
        }

        // CHANGED: wrap in a transaction to mirror the web controller.
        DB::beginTransaction();
        try {
            $plan->delete();
            DB::commit();
            return response()->json(['message' => 'Registration plan moved to trash.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API plan delete failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete registration plan.'], 500);
        }
    }

    /**
     * POST /api/registration-plans/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        $plan = RegistrationPlan::onlyTrashed()->findOrFail($id);
        $plan->restore();

        return response()->json([
            'message' => 'Registration plan restored.',
            'data'    => new RegistrationPlanResource(
                $plan->fresh(['creator', 'assignedAgents.agent'])
            ),
        ]);
    }

    // =====================================================================
    // STATUS TRANSITIONS
    // =====================================================================

    public function markInProgress(int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->findOrFail($id);

        if ($plan->trashed()) {
            return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
        }
        if (!in_array($plan->status, ['draft', 'assigned'], true)) {
            return response()->json([
                'message' => "Cannot mark a '{$plan->status}' plan as in progress.",
            ], 409);
        }
        if (!$plan->hasActiveAgents()) {
            return response()->json([
                'message' => 'Assign at least one field agent before activating this plan.',
            ], 422);
        }

        $plan->update(['status' => 'in_progress']);

        return response()->json([
            'message' => 'Plan marked as in progress.',
            'data'    => new RegistrationPlanResource(
                $plan->fresh(['creator', 'assignedAgents.agent'])
            ),
        ]);
    }

    public function markCompleted(int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->findOrFail($id);

        if ($plan->trashed()) {
            return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
        }
        if (!in_array($plan->status, ['assigned', 'in_progress'], true)) {
            return response()->json([
                'message' => "Cannot complete a '{$plan->status}' plan.",
            ], 409);
        }

        $plan->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Plan marked as completed.',
            'data'    => new RegistrationPlanResource(
                $plan->fresh(['creator', 'assignedAgents.agent'])
            ),
        ]);
    }

    public function reactivate(int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->findOrFail($id);

        if ($plan->trashed()) {
            return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
        }
        if ($plan->status !== 'cancelled') {
            return response()->json([
                'message' => "Only cancelled plans can be reactivated. Current status: '{$plan->status}'.",
            ], 409);
        }

        $newStatus = $plan->hasActiveAgents() ? 'assigned' : 'draft';
        $plan->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Plan reactivated.',
            'data'    => new RegistrationPlanResource(
                $plan->fresh(['creator', 'assignedAgents.agent'])
            ),
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $plan = RegistrationPlan::withTrashed()->findOrFail($id);

        if ($plan->trashed()) {
            return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
        }
        if (in_array($plan->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => "Cannot cancel a '{$plan->status}' plan.",
            ], 409);
        }

        $reason = $request->input('reason');
        if ($reason) {
            Log::info("Plan #{$plan->id} cancelled by user " . Auth::id() . ": {$reason}");
        }

        $plan->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Plan cancelled.',
            'data'    => new RegistrationPlanResource(
                $plan->fresh(['creator', 'assignedAgents.agent'])
            ),
        ]);
    }

    // =====================================================================
    // FIELD AGENTS
    // =====================================================================

    public function fieldAgents(): JsonResponse
{
    $agents = User::query()
        ->where(function ($q) {
            $q->where('type', User::TYPE_FIELD_AGENT)
              ->orWhereHas('roles', fn($r) => $r->where('slug', 'field-agent'));
        })
        ->whereNull('deleted_at')
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'phone', 'photo', 'phone_verified_at'])
        ->map(fn(User $u) => [
            'id'                => $u->id,
            'name'              => $u->name,
            'email'             => $u->email,
            'phone'             => $u->phone,
            'photo_url'         => $u->photo_url,
            'avatar_url'        => $u->avatar_url,
            'has_photo'         => $u->has_photo,
            'is_phone_verified' => $u->is_phone_verified,
        ]);

    return response()->json([
        'data' => $agents,
        'meta' => ['total' => $agents->count()],
    ]);
}

    // =====================================================================
    // METADATA
    // =====================================================================

    public function zones(): JsonResponse
    {
        $zones = RegistrationPlan::query()
            ->whereNotNull('zone')->where('zone', '!=', '')
            ->distinct()->orderBy('zone')->pluck('zone')->values();

        return response()->json(['data' => $zones]);
    }

    public function sections(): JsonResponse
    {
        $sections = RegistrationPlan::query()
            ->whereNotNull('section')->where('section', '!=', '')
            ->distinct()->orderBy('section')->pluck('section')->values();

        return response()->json(['data' => $sections]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total'           => RegistrationPlan::count(),
            'active_count'    => RegistrationPlan::whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_count' => RegistrationPlan::where('status', 'completed')->count(),
            'cancelled_count' => RegistrationPlan::where('status', 'cancelled')->count(),
            'trashed_count'   => RegistrationPlan::onlyTrashed()->count(),
            'overdue_count'   => RegistrationPlan::where('registration_end_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Resolve agents from request payload (existing or new).
     *
     * CHANGED: returns `agent_ids` as an ASSOCIATIVE array keyed by the
     * original request row index, so the caller can map invitation methods
     * back to the correct row. Example:
     *   ['agent_ids' => [0 => 12, 2 => 34], 'new_agents' => [...], 'errors' => []]
     * For 'single' mode, the key is always 0.
     *
     * @return array{agent_ids: array<int,int>, new_agents: array, errors: array}
     */
    private function resolveAgents(array $v): array
    {
        $agentIndexMap = [];   // requestIndex => agentId
        $newAgents     = [];
        $errors        = [];

        if (($v['agent_assignment_type'] ?? null) === 'single') {
            if (!empty($v['assigned_agent_id'])) {
                $user = User::find($v['assigned_agent_id']);
                if (!$user || !$user->isFieldAgent()) {
                    $errors[] = 'The assigned user must be a field agent.';
                } else {
                    $agentIndexMap[0] = $user->id;
                }
            } elseif (!empty($v['agent_phone']) && !empty($v['agent_name'])) {
                $result = $this->agentInvitationService->findOrCreateAgent([
                    'phone' => $v['agent_phone'],
                    'name'  => $v['agent_name'],
                    'email' => $v['agent_email'] ?? null,
                ]);
                if (!$result['success']) {
                    $errors[] = $result['message'];
                } else {
                    $agentIndexMap[0] = $result['agent_id'];
                    $newAgents[$result['agent_id']] = [
                        'phone' => $v['agent_phone'],
                        'name'  => $v['agent_name'],
                        'email' => $v['agent_email'] ?? null,
                    ];
                }
            }
        } else {
            foreach ($v['agent_types'] ?? [] as $i => $type) {
                if ($type === 'existing' && !empty($v['assigned_agent_ids'][$i])) {
                    $user = User::find($v['assigned_agent_ids'][$i]);
                    if (!$user || !$user->isFieldAgent()) {
                        $errors[] = 'The assigned user must be a field agent.';
                    } else {
                        $agentIndexMap[$i] = $user->id;
                    }
                } elseif ($type === 'new'
                    && !empty($v['agent_phones'][$i])
                    && !empty($v['agent_names'][$i])
                ) {
                    $result = $this->agentInvitationService->findOrCreateAgent([
                        'phone' => $v['agent_phones'][$i],
                        'name'  => $v['agent_names'][$i],
                        'email' => $v['agent_emails'][$i] ?? null,
                    ]);
                    if (!$result['success']) {
                        $errors[] = $result['message'];
                    } else {
                        $agentIndexMap[$i] = $result['agent_id'];
                        $newAgents[$result['agent_id']] = [
                            'phone' => $v['agent_phones'][$i],
                            'name'  => $v['agent_names'][$i],
                            'email' => $v['agent_emails'][$i] ?? null,
                        ];
                    }
                }
            }
        }

        // Deduplicate while preserving the first requestIndex each agent appeared at.
        $seen = [];
        $deduped = [];
        foreach ($agentIndexMap as $index => $agentId) {
            if (isset($seen[$agentId])) {
                continue;
            }
            $seen[$agentId] = true;
            $deduped[$index] = $agentId;
        }

        return [
            'agent_ids'  => $deduped,
            'new_agents' => $newAgents,
            'errors'     => $errors,
        ];
    }

    /**
     * Create assignments & send invitations.
     *
     * CHANGED: $agentIndexMap is [requestIndex => agentId]. The invitation
     * method for each agent is looked up using the ORIGINAL request index,
     * which is what the web controller intends but gets wrong.
     *
     * @param  array<int,int> $agentIndexMap
     * @return array<int,array>
     */
    private function persistAssignmentsAndInvite(RegistrationPlan $plan, array $agentIndexMap, array $v): array
    {
        $invitationsSent = [];
        $isSingle = ($v['agent_assignment_type'] ?? null) === 'single';

        foreach ($agentIndexMap as $requestIndex => $agentId) {
            PlanAgentAssignment::create([
                'plan_id'     => $plan->id,
                'agent_id'    => $agentId,
                'assigned_by' => Auth::id(),
            ]);

            // CHANGED: use the ORIGINAL request index, not a re-indexed one.
            $method = $isSingle
                ? ($v['invitation_method'] ?? null)
                : ($v['invitation_methods'][$requestIndex] ?? null);

            if (!$method) {
                Log::info("No invitation method selected for agent {$agentId} in plan {$plan->id}");
                continue;
            }

            $result = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
                $plan->id,
                $agentId,
                [$method],
                $v['instructions'] ?? null
            );

            if (!empty($result['success'])) {
                $invitationsSent[] = [
                    'agent_id' => $agentId,
                    'method'   => $method,
                    'channels' => $result['channels_successful'] ?? [],
                ];
            }
        }

        return $invitationsSent;
    }

    private function requestHasAgents(array $v): bool
    {
        if (($v['agent_assignment_type'] ?? null) === 'single') {
            return !empty($v['assigned_agent_id'])
                || (!empty($v['agent_phone']) && !empty($v['agent_name']));
        }
        return !empty($v['agent_types']);
    }

    // ---------------------------------------------------------------------
    // Naming pattern helpers — unchanged (already aligned with web)
    // ---------------------------------------------------------------------

    private function validateNamingPattern($pattern, $startingPoint, $sequenceType): array
    {
        if (!preg_match('/\{([a-zA-Z_]+)\}/', $pattern)) {
            return ['valid' => false, 'message' => 'Naming pattern must contain at least one placeholder.'];
        }

        $isNumberThenLetter = str_contains($pattern, '{number}')
            && str_contains($pattern, '{letter}')
            && strpos($pattern, '{number}') < strpos($pattern, '{letter}');
        $isLetterThenNumber = str_contains($pattern, '{letter}')
            && str_contains($pattern, '{number}')
            && strpos($pattern, '{letter}') < strpos($pattern, '{number}');

        if ($isNumberThenLetter) {
            if (!preg_match('/^\d+[A-Za-z]+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be like 1A, 2B.'];
            }
            $number = (int)preg_replace('/[^0-9]/', '', $startingPoint);
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'Even-only sequence requires even starting number.'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'Odd-only sequence requires odd starting number.'];
            }
        } elseif ($isLetterThenNumber) {
            if (!preg_match('/^[A-Za-z]+\d+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be like A1, B2.'];
            }
            $number = (int)preg_replace('/[^0-9]/', '', $startingPoint);
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'Even-only sequence requires even starting number.'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'Odd-only sequence requires odd starting number.'];
            }
        } elseif (str_contains($pattern, '{letter}') && str_contains($pattern, '{number}')) {
            if (!preg_match('/^([A-Za-z]+\d+|\d+[A-Za-z]+)$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be like A1 or 1A.'];
            }
        } elseif (str_contains($pattern, '{letter}')) {
            if (!preg_match('/^[A-Za-z]+$/', $startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must contain only letters.'];
            }
        } elseif (str_contains($pattern, '{number}')) {
            if (!is_numeric($startingPoint)) {
                return ['valid' => false, 'message' => 'Starting point must be numeric.'];
            }
            $number = (int)$startingPoint;
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return ['valid' => false, 'message' => 'Even-only sequence requires even starting number.'];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return ['valid' => false, 'message' => 'Odd-only sequence requires odd starting number.'];
            }
        }

        return ['valid' => true, 'message' => 'Pattern is valid'];
    }

    private function checkNamingPatternConflicts(
        string $namingPattern,
        string $startingPoint,
        ?int $planId,
        string $zone,
        ?string $section
    ): array {
        $query = Property::whereHas('registrationPlan', function ($q) use ($zone, $section) {
            $q->where('zone', $zone);
            if ($section) {
                $q->where('section', $section);
            }
        })->where('registration_pattern', $startingPoint);

        if ($planId) {
            $query->whereHas('registrationPlan', fn($q) => $q->where('id', '!=', $planId));
        }

        if ($existing = $query->first()) {
            $conflicting = $existing->registrationPlan;
            return [
                'has_conflict' => true,
                'message' => "Pattern '{$startingPoint}' already exists in plan: "
                    . $conflicting->zone
                    . ($conflicting->section ? " - {$conflicting->section}" : "")
                    . ". Please use a different pattern.",
            ];
        }

        return ['has_conflict' => false];
    }

    private function generateSequencePreview(RegistrationPlan $plan, int $count = 5): array
    {
        $preview = [];
        $current = $plan->next_available_name ?? $plan->starting_point;
        for ($i = 0; $i < $count; $i++) {
            $preview[] = $current;
            $current = $this->generateNextName($current, $plan->naming_pattern, $plan->sequence_type);
        }
        return $preview;
    }

    private function generateNextName(string $current, string $pattern, string $sequenceType): string
    {
        if (str_contains($pattern, '{letter}') && str_contains($pattern, '{number}')) {
            return $this->generateCombinedNextName($current, $sequenceType);
        }
        if (str_contains($pattern, '{letter}')) {
            return $this->incrementLetters($current);
        }
        if (str_contains($pattern, '{number}')) {
            return $this->generateNumberNextName($current, $sequenceType);
        }
        return $this->generateSimpleNextName($current);
    }

    private function generateCombinedNextName(string $current, string $sequenceType): string
    {
        if (preg_match('/(\d+)([A-Za-z]+)/', $current, $m)) {
            $num = (int)$m[1];
            $letter = $m[2];
            $num = $this->advanceNumber($num, $sequenceType);
            if ($num > 99) {
                $letter = $this->incrementLetters($letter);
                $num = $sequenceType === 'even_only' ? 2 : 1;
            }
            return $num . $letter;
        }
        if (preg_match('/([A-Za-z]+)(\d+)/', $current, $m)) {
            $letter = $m[1];
            $num = (int)$m[2];
            $num = $this->advanceNumber($num, $sequenceType);
            if ($num > 99) {
                $letter = $this->incrementLetters($letter);
                $num = $sequenceType === 'even_only' ? 2 : 1;
            }
            return $letter . $num;
        }
        return $this->generateSimpleNextName($current);
    }

    private function advanceNumber(int $num, string $seq): int
    {
        $num++;
        if ($seq === 'even_only' && $num % 2 !== 0) $num++;
        if ($seq === 'odd_only' && $num % 2 === 0) $num++;
        return $num;
    }

    private function incrementLetters(string $letters): string
    {
        $len = strlen($letters);
        for ($i = $len - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return $letters;
            }
            $letters[$i] = 'A';
        }
        return 'A' . $letters;
    }

    private function generateNumberNextName(string $current, string $seq): string
    {
        $n = (int)$current;
        return match ($seq) {
            'even_only' => $n % 2 === 0 ? $n + 2 : $n + 1,
            'odd_only'  => $n % 2 === 1 ? $n + 2 : $n + 1,
            default     => $n + 1,
        };
    }

    private function generateSimpleNextName(string $current): string
    {
        if (preg_match('/(.*?)(\d+)$/', $current, $m)) {
            return $m[1] . ((int)$m[2] + 1);
        }
        return $current . '-1';
    }

    /**
 * GET /api/admin/registration-plans/trashed
 */
public function trashed(Request $request): JsonResponse
{
    $plans = RegistrationPlan::onlyTrashed()
        ->with(['creator', 'assignedAgents.agent'])
        ->withCount('properties')
        ->orderByDesc('deleted_at')
        ->paginate($request->integer('per_page', 20));

    return response()->json([
        'data' => RegistrationPlanResource::collection($plans->items()),
        'meta' => [
            'current_page' => $plans->currentPage(),
            'last_page'    => $plans->lastPage(),
            'per_page'     => $plans->perPage(),
            'total'        => $plans->total(),
        ],
    ]);
}

/**
 * POST /api/admin/registration-plans/validate-pattern
 * Dry-run the pattern validator without creating a plan.
 */
public function validatePattern(Request $request): JsonResponse
{
    $data = $request->validate([
        'naming_pattern' => 'required|string|max:100',
        'starting_point' => 'required|string|max:50',
        'sequence_type'  => 'required|in:sequential,even_only,odd_only',
        'zone'           => 'nullable|string|max:100',
        'section'        => 'nullable|string|max:100',
        'plan_id'        => 'nullable|integer|exists:registration_plans,id',
    ]);

    $result = $this->validateNamingPattern(
        $data['naming_pattern'],
        $data['starting_point'],
        $data['sequence_type']
    );

    if (!$result['valid']) {
        return response()->json([
            'valid'   => false,
            'message' => $result['message'],
        ], 422);
    }

    // Optional conflict check when zone is provided
    if (!empty($data['zone'])) {
        $conflict = $this->checkNamingPatternConflicts(
            $data['naming_pattern'],
            $data['starting_point'],
            $data['plan_id'] ?? null,
            $data['zone'],
            $data['section'] ?? null
        );
        if ($conflict['has_conflict']) {
            return response()->json([
                'valid'   => false,
                'message' => $conflict['message'],
            ], 409);
        }
    }

    return response()->json([
        'valid'   => true,
        'message' => 'Pattern is valid.',
    ]);
}

/**
 * DELETE /api/admin/registration-plans/{id}/force-delete
 */
public function forceDelete(int $id): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->findOrFail($id);

    if (!$plan->trashed()) {
        return response()->json([
            'message' => 'Plan must be soft-deleted before permanent deletion.',
        ], 409);
    }
    if ($plan->properties()->exists()) {
        return response()->json([
            'message' => 'Cannot permanently delete a plan that has registered properties.',
        ], 409);
    }

    $plan->forceDelete();

    return response()->json(['message' => 'Registration plan permanently deleted.']);
}

/**
 * GET /api/admin/registration-plans/{id}/sequence-preview
 */
public function sequencePreview(int $id, Request $request): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->findOrFail($id);
    $count = max(1, min(50, $request->integer('count', 5)));

    return response()->json([
        'data' => [
            'current' => $plan->next_available_name ?? $plan->starting_point,
            'preview' => $this->generateSequencePreview($plan, $count),
        ],
    ]);
}

/**
 * GET /api/admin/registration-plans/{id}/agents
 */
public function agents(int $id): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->with(['assignedAgents.agent'])->findOrFail($id);

    return response()->json([
        'data' => $plan->assignedAgents->map(fn($a) => [
            'assignment_id'        => $a->id,
            'agent_id'             => $a->agent_id,
            'agent'                => $a->agent,
            'assigned_by'          => $a->assigned_by,
            'assigned_at'          => $a->assigned_at,
            'is_active'            => $a->isActive(),
            'properties_registered'=> $a->properties_registered,
            'performance_metrics'  => $a->getPerformanceMetrics(),
            'assignment_summary'   => $a->getAssignmentSummary(),
        ]),
    ]);
}

/**
 * POST /api/admin/registration-plans/{id}/agents
 * Assign additional agents (or replace the set) using the same payload shape
 * as store()/update() — but without touching plan configuration fields.
 */
public function assignAgents(Request $request, int $id): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->with('assignedAgents')->findOrFail($id);

    if ($plan->trashed()) {
        return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
    }
    if (in_array($plan->status, ['completed', 'cancelled'], true)) {
        return response()->json([
            'message' => "Cannot modify a '{$plan->status}' plan.",
        ], 409);
    }

    $validated = $request->validate([
        'agent_assignment_type' => 'required|in:single,multiple',
        'assigned_agent_id'     => 'nullable|exists:users,id',
        'agent_name'            => 'nullable|string|max:255',
        'agent_phone'           => 'nullable|string|max:20',
        'agent_email'           => 'nullable|email',
        'invitation_method'     => 'nullable|in:sms,whatsapp,email,all_channels',
        'agent_types'           => 'nullable|array',
        'agent_types.*'         => 'in:existing,new',
        'assigned_agent_ids'    => 'nullable|array',
        'assigned_agent_ids.*'  => 'nullable|integer|exists:users,id',
        'agent_phones'          => 'nullable|array',
        'agent_phones.*'        => 'nullable|string|max:20',
        'agent_names'           => 'nullable|array',
        'agent_names.*'         => 'nullable|string|max:255',
        'agent_emails'          => 'nullable|array',
        'agent_emails.*'        => 'nullable|email',
        'invitation_methods'    => 'nullable|array',
        'invitation_methods.*'  => 'nullable|in:sms,whatsapp,email,all_channels',
        'instructions'          => 'nullable|string',
    ]);

    $assignment = $this->resolveAgents($validated);
    if (!empty($assignment['errors'])) {
        return response()->json(['message' => $assignment['errors'][0]], 422);
    }

    $agentIndexMap    = $assignment['agent_ids'];
    $assignedAgentIds = array_values($agentIndexMap);

    if (empty($assignedAgentIds)) {
        return response()->json(['message' => 'No agents supplied.'], 422);
    }

    DB::beginTransaction();
    try {
        $currentAgentIds = $plan->assignedAgents->pluck('agent_id')->toArray();
        $newAgentIds     = array_values(array_diff($assignedAgentIds, $currentAgentIds));

        $newAgentIndexMap = array_filter(
            $agentIndexMap,
            fn($agentId) => in_array($agentId, $newAgentIds, true)
        );

        $invitationsSent = $this->persistAssignmentsAndInvite($plan, $newAgentIndexMap, $validated);

        // Auto-promote draft → assigned if we now have agents and required fields exist
        if ($plan->status === 'draft' && $plan->hasActiveAgents()) {
            $plan->update(['status' => 'assigned']);
        }

        DB::commit();

        return response()->json([
            'message' => 'Agents assigned.',
            'data'    => new RegistrationPlanResource($plan->fresh(['creator', 'assignedAgents.agent'])),
            'meta'    => [
                'added_agents'       => $newAgentIds,
                'invitation_count'   => count($invitationsSent),
                'invitations_sent'   => $invitationsSent,
                'new_agents_created' => $assignment['new_agents'],
            ],
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('API plan assignAgents failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['message' => 'Failed to assign agents.'], 500);
    }
}

/**
 * DELETE /api/admin/registration-plans/{id}/agents/{agentId}
 */
public function removeAgent(int $id, int $agentId): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->findOrFail($id);

    if ($plan->trashed()) {
        return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
    }

    $assignment = PlanAgentAssignment::where('plan_id', $plan->id)
        ->where('agent_id', $agentId)
        ->where('is_active', true)
        ->first();

    if (!$assignment) {
        return response()->json(['message' => 'Active assignment not found.'], 404);
    }

    $assignment->markAsInactive('Removed via API');

    // If no active agents remain, drop plan back to draft (unless completed/cancelled)
    if (!$plan->fresh()->hasActiveAgents()
        && !in_array($plan->status, ['completed', 'cancelled'], true)) {
        $plan->update(['status' => 'draft']);
    }

    return response()->json([
        'message' => 'Agent removed from plan.',
        'data'    => new RegistrationPlanResource($plan->fresh(['creator', 'assignedAgents.agent'])),
    ]);
}

/**
 * POST /api/admin/registration-plans/{id}/agents/{agentId}/resend-invitation
 */
public function resendInvitation(Request $request, int $id, int $agentId): JsonResponse
{
    $plan = RegistrationPlan::withTrashed()->findOrFail($id);

    if ($plan->trashed()) {
        return response()->json(['message' => 'Cannot modify a deleted plan.'], 409);
    }
    if (in_array($plan->status, ['completed', 'cancelled'], true)) {
        return response()->json([
            'message' => "Cannot resend invitations for a '{$plan->status}' plan.",
        ], 409);
    }

    $assignment = PlanAgentAssignment::where('plan_id', $plan->id)
        ->where('agent_id', $agentId)
        ->where('is_active', true)
        ->first();

    if (!$assignment) {
        return response()->json(['message' => 'Active assignment not found.'], 404);
    }

    $method = $request->input('invitation_method')
        ?? $request->input('method')
        ?? 'all_channels';

    if (!in_array($method, ['sms', 'whatsapp', 'email', 'all_channels'], true)) {
        return response()->json(['message' => 'Invalid invitation method.'], 422);
    }

    $result = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
        $plan->id,
        $agentId,
        [$method],
        $plan->instructions
    );

    if (empty($result['success'])) {
        return response()->json([
            'message' => $result['message'] ?? 'Failed to resend invitation.',
        ], 500);
    }

    return response()->json([
        'message'  => 'Invitation resent.',
        'channels' => $result['channels_successful'] ?? [],
    ]);
}

public function analytics(): JsonResponse
{
    // Status breakdown
    $plansByStatus = RegistrationPlan::query()
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->pluck('count', 'status');

    // Zone breakdown
    $plansByZone = RegistrationPlan::query()
        ->selectRaw('COALESCE(zone, "") as zone, COUNT(*) as count')
        ->groupBy('zone')
        ->orderByDesc('count')
        ->get();

    // Assignment types
    $assignmentTypes = RegistrationPlan::query()
        ->selectRaw('agent_assignment_type, COUNT(*) as count')
        ->groupBy('agent_assignment_type')
        ->get();

    // Pattern usage
    $patternUsage = RegistrationPlan::query()
        ->selectRaw('naming_pattern, COUNT(*) as count')
        ->groupBy('naming_pattern')
        ->orderByDesc('count')
        ->get();

    // Monthly rollup
    $monthly = RegistrationPlan::query()
        ->selectRaw('
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            COUNT(*) as plans_count,
            SUM(estimated_houses) as target_houses
        ')
        ->groupBy('year', 'month')
        ->orderByDesc('year')
        ->orderByDesc('month')
        ->limit(12)
        ->get();

    // Agent performance
    $agentPerf = \DB::table('plan_agent_assignments')
        ->join('users', 'users.id', '=', 'plan_agent_assignments.agent_id')
        ->selectRaw('
            users.id as agent_id,
            users.name,
            SUM(plan_agent_assignments.properties_registered) as total_properties,
            SUM(CASE WHEN plan_agent_assignments.is_active = 1 THEN 1 ELSE 0 END) as active_assignments
        ')
        ->groupBy('users.id', 'users.name')
        ->orderByDesc('total_properties')
        ->limit(20)
        ->get()
        ->map(fn($row) => [
            'agent' => ['id' => $row->agent_id, 'name' => $row->name],
            'total_properties' => (int) $row->total_properties,
            'active_assignments' => (int) $row->active_assignments,
        ]);

    // Active assignments by zone
    $zoneStats = RegistrationPlan::query()
        ->whereIn('status', ['assigned', 'in_progress'])
        ->withSum('assignedAgents as total_properties', 'properties_registered')
        ->withCount('assignedAgents')
        ->get()
        ->groupBy(fn($p) => $p->zone ?? '')
        ->map(fn($group) => [
            'count' => $group->count(),
            'total_properties' => $group->sum('total_properties'),
        ]);

    return response()->json([
        'analytics' => [
            'plans_by_status' => $plansByStatus,
            'plans_by_zone' => $plansByZone,
            'assignment_types' => $assignmentTypes,
            'pattern_usage' => $patternUsage,
            'monthly_registration' => $monthly,
            'assignment_performance' => $agentPerf,
            'active_assignments_by_zone' => $zoneStats,
        ],
    ]);
}

/**
 * GET /api/v1/admin/registration-plans/property-counts?ids=1,2,3
 *
 * Returns per-plan property counts for the given plan IDs.
 * Response shape:
 *   { "data": { "1": { "total": 42, "registered_by_me": 7 }, "2": {...} } }
 */
public function propertyCounts(Request $request): JsonResponse
{
    $ids = collect(explode(',', (string) $request->input('ids', '')))
        ->map(fn($v) => (int) trim($v))
        ->filter()
        ->unique()
        ->take(200)   // cap to keep the query bounded
        ->values();

    if ($ids->isEmpty()) {
        return response()->json(['data' => (object) []]);
    }

    $userId = Auth::id();

    $totals = Property::whereIn('registration_plan_id', $ids)
        ->select('registration_plan_id', DB::raw('count(*) as total'))
        ->groupBy('registration_plan_id')
        ->pluck('total', 'registration_plan_id');

    $mine = Property::whereIn('registration_plan_id', $ids)
        ->where('registered_by', $userId)
        ->select('registration_plan_id', DB::raw('count(*) as total'))
        ->groupBy('registration_plan_id')
        ->pluck('total', 'registration_plan_id');

    $out = [];
    foreach ($ids as $id) {
        $out[(string) $id] = [
            'total'            => (int) ($totals[$id] ?? 0),
            'registered_by_me' => (int) ($mine[$id] ?? 0),
        ];
    }

    return response()->json(['data' => $out]);
}

}
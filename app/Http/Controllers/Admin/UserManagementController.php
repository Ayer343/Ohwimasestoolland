<?php
// app/Http/Controllers/Admin/UserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\Role;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Repositories\UserRepository;
use App\Services\UserInvitationService;
use App\Services\UserDisplayService;
use App\Services\MultiChannelInvitationService;
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Events\UserDeleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends Controller
{
    protected UserRepository $userRepository;
    protected UserInvitationService $invitationService;
    protected UserDisplayService $displayService;
    protected MultiChannelInvitationService $multiChannelService;

    // Configurable pagination settings
    protected const DEFAULT_PER_PAGE = 15;
    protected const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    public function __construct(
        UserRepository $userRepository,
        UserInvitationService $invitationService,
        UserDisplayService $displayService,
        MultiChannelInvitationService $multiChannelService
    ) {
        $this->userRepository = $userRepository;
        $this->invitationService = $invitationService;
        $this->displayService = $displayService;
        $this->multiChannelService = $multiChannelService;
    }

    /**
 * Display a listing of users
 *
 * Supports an optional `queue=pending_personnel` filter that isolates
 * security-personnel submissions awaiting admin approval.
 */
public function index(Request $request)
{
    $currentUser = auth()->user();

    $perPage = $this->getPerPage($request->get('per_page'));

    // If the admin opened the pending-personnel queue, build the query
    // directly — the repository's paginator doesn't know about the
    // metadata flag, so we bypass it for this one view.
    if ($request->get('queue') === 'pending_personnel') {
        $users = User::query()
            ->where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('metadata->awaiting_approval', true)
            ->when(! $currentUser->isSuperAdmin(), function ($q) {
                $q->where('type', '!=', User::TYPE_SUPER_ADMIN);
            })
            ->orderBy('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $users->getCollection()->transform(function ($user) {
            $metadata = $user->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }

            // Resolve the submitting head for display.
            $headId = $metadata['created_by_head_id'] ?? $user->created_by;
            $head   = $headId ? User::find($headId) : null;

            $user->setAttribute('submitted_by_head_id', $headId);
            $user->setAttribute('submitted_by_head_name',
                $metadata['created_by_head_name'] ?? ($head?->name ?? 'Unknown')
            );
            $user->setAttribute('submitted_at',
                $metadata['submitted_at'] ?? $user->created_at?->toISOString()
            );

            return $user;
        });

        $stats = [
            'pending_personnel_total' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('metadata->awaiting_approval', true)
                ->count(),
            'pending_personnel_today' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('metadata->awaiting_approval', true)
                ->whereDate('created_at', today())
                ->count(),
            'pending_personnel_this_week' => User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('metadata->awaiting_approval', true)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
        ];

        $userTypes = $this->getFilteredUserTypes($currentUser);
        $statuses  = User::getUserStatuses();

        $availableRoles = Role::where('slug', '!=', 'developer')
            ->orderBy('priority')
            ->get();

        $communicationStatus = $this->getCommunicationServiceStatus();
        $perPageOptions      = self::PER_PAGE_OPTIONS;
        $queue               = 'pending_personnel';

        return view('admin.users.index', compact(
            'users', 'userTypes', 'statuses', 'stats',
            'communicationStatus', 'availableRoles', 'perPage',
            'perPageOptions', 'queue'
        ));
    }

    // ---- Normal listing path (unchanged) ----
    $users = $this->userRepository->getPaginatedUsers($request->all(), $currentUser, $perPage);

    $users->getCollection()->each(function ($user) {
        $user->loadCount([
            'ownedProperties as properties_count',
            'approvedFamilyLinks as linked_properties_count',
        ]);
    });

    $stats = $this->userRepository->getDashboardStats($currentUser, $request->all());

    $users->getCollection()->transform(function ($user) {
        return $this->displayService->prepareForDisplay($user);
    });

    $userTypes = $this->getFilteredUserTypes($currentUser);
    $statuses  = User::getUserStatuses();
    $availableRoles = Role::where('slug', '!=', 'developer')
        ->orderBy('priority')
        ->get();

    $communicationStatus = $this->getCommunicationServiceStatus();
    $perPageOptions      = self::PER_PAGE_OPTIONS;

    return view('admin.users.index', compact(
        'users', 'userTypes', 'statuses', 'stats',
        'communicationStatus', 'availableRoles', 'perPage', 'perPageOptions'
    ));
}

    /**
     * Show form for creating new user
     */
    public function create()
{
    $currentUser = auth()->user();

    $userTypes = $this->getFilteredUserTypes($currentUser);

    if (isset($userTypes[\App\Models\User::TYPE_FORMER_LANDLORD])) {
        unset($userTypes[\App\Models\User::TYPE_FORMER_LANDLORD]);
    }
    if (isset($userTypes['archived'])) {
        unset($userTypes['archived']);
    }

    $statuses = User::getUserStatuses();
    $communicationStatus = $this->getCommunicationServiceStatus();

    $smsStatus      = $communicationStatus['sms'] ?? [];
    $whatsappStatus = $communicationStatus['whatsapp'] ?? [];
    $emailStatus    = $communicationStatus['email'] ?? [];
    $multiChannelStatus = $communicationStatus['multi_channel'] ?? [];

    $smsStatus['system_ready']      = $smsStatus['system_ready']      ?? ($smsStatus['enabled'] ?? false);
    $whatsappStatus['system_ready'] = $whatsappStatus['system_ready'] ?? ($whatsappStatus['enabled'] ?? false);
    $emailStatus['system_ready']    = $emailStatus['system_ready']    ?? ($emailStatus['enabled'] ?? false);

    $defaultSupervisorSettings = ['can_be_supervisor' => false];

    $availableRoles = auth()->user()->isSuperAdmin()
        ? Role::where('slug', '!=', 'developer')->orderBy('priority')->get()
        : collect();

    $sanitationSupervisors = $this->getAvailableSanitationSupervisors();

    return view('admin.users.create', compact(
        'userTypes', 'statuses', 'communicationStatus',
        'smsStatus', 'whatsappStatus', 'emailStatus', 'multiChannelStatus',
        'defaultSupervisorSettings', 'availableRoles', 'sanitationSupervisors'
    ));
}

/**
 * Store a newly created user
 */
public function store(StoreUserRequest $request)
{
    $currentUser = auth()->user();

    DB::beginTransaction();

    try {
        $userData = $request->validated();

        // ================================================================
        // SANITATION
        // ================================================================
        $isSanitationPersonnel = (int) $request->input('type') === User::TYPE_SANITATION_PERSONNEL;
        $isSanitationEnabled   = $request->boolean('sanitation_can_be_supervisor', false);

        if ($isSanitationPersonnel && $isSanitationEnabled) {
            $userData['sanitation_can_be_supervisor'] = true;
            $userData['sanitation_personnel_role']    = $request->input('sanitation_personnel_role', 'worker');
            $userData['sanitation_personnel_status']  = $request->input('sanitation_personnel_status', 'active');
            $userData['sanitation_vehicle_number']    = $request->input('sanitation_vehicle_number');
            $userData['sanitation_vehicle_type']      = $request->input('sanitation_vehicle_type');
            $userData['sanitation_emergency_contact'] = $request->input('sanitation_emergency_contact');
            $userData['sanitation_hire_date']         = $request->input('sanitation_hire_date');
            $userData['sanitation_address']           = $request->input('address');
            $userData['sanitation_supervisor_id']     = $request->input('sanitation_supervisor_id');

            Log::info('Sanitation personnel data prepared for creation', [
                'user_type'     => $userData['type'] ?? null,
                'role'          => $userData['sanitation_personnel_role'],
                'status'        => $userData['sanitation_personnel_status'],
                'supervisor_id' => $userData['sanitation_supervisor_id'],
            ]);
        } else {
            $userData['sanitation_can_be_supervisor'] = false;
        }

        // ================================================================
        // SECURITY
        // ================================================================
        // Eligibility is a single boolean. No rank, no level, no score.
        // Certifications are optional free-text (record-keeping only).
        $isSecurityPersonnel = (int) $request->input('type') === User::TYPE_SECURITY_PERSONNEL;
        $isSecurityEligible  = $isSecurityPersonnel
            && $request->boolean('security_can_be_supervisor', false);

        $userData['can_be_supervisor'] = $isSecurityEligible;

        $userData['security_supervisor_certifications'] = $isSecurityEligible
            ? $request->input('security_supervisor_certifications')
            : null;

        Log::info('Security personnel data prepared for creation', [
            'user_type'          => $userData['type'] ?? null,
            'can_be_supervisor'  => $isSecurityEligible,
            'has_certifications' => !empty($userData['security_supervisor_certifications']),
        ]);

        // ================================================================
        // CREATE USER
        // ================================================================
        $user = $this->userRepository->createUser($userData, $currentUser);

        // ================================================================
        // ASSIGN ROLES
        // ================================================================
        // `roles[]` may contain EITHER numeric role IDs (legacy checkbox
        // form) OR slug strings (auto-role hidden input from create blade).
        // role_user.role_id is an INTEGER FK, so we MUST resolve slugs to
        // numeric IDs before calling sync().
        $skipSpatieRoles = in_array((int) $request->input('type'), [
            User::TYPE_SANITATION_PERSONNEL,
            User::TYPE_SECURITY_PERSONNEL,
        ]);

        if (!$skipSpatieRoles && $currentUser->isSuperAdmin() && $request->has('roles')) {
            $roleIds = $this->resolveRoleIdentifiers((array) $request->input('roles', []));

            if (!empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }
        }

        // ================================================================
        // SEND INVITATION
        // ================================================================
        $invitationResult = null;
        if ($request->boolean('send_invitation')) {
            $invitationData = $request->validated();
            $invitationData['invitation_type'] = $request->input('invitation_type', 'welcome');

            $requestedChannels = $request->input('invitation_channels');

            if (is_array($requestedChannels) && !empty($requestedChannels)) {
                $invitationData['invitation_channels'] = $requestedChannels;
            } else {
                $available = $this->invitationService->getAvailableChannels($user);

                $invitationData['invitation_channels'] = !empty($available)
                    ? array_keys(array_filter($available))
                    : ['sms'];
            }

            $invitationResult = $this->invitationService->sendInvitation($user, $invitationData);

            if ($invitationResult['success']) {
                $user->update(['invitation_sent_at' => now()]);
            }
        }

        DB::commit();

        event(new UserCreated($user, $currentUser, $request->validated()));

        $successMessage = $this->buildSuccessMessage($user, $invitationResult);

        session()->flash('last_created_user', [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'type'            => $user->type,
            'success_message' => $successMessage,
        ]);

        return redirect()->route('admin.users.create')
            ->with('success', $successMessage . ' You can continue adding more users.')
            ->with('invitation_result', $invitationResult);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to create user: ' . $e->getMessage(), [
            'user_data'   => $request->validated(),
            'error_trace' => $e->getTraceAsString(),
            'user_id'     => auth()->id(),
        ]);

        return back()->with('error', 'Failed to create user: ' . $e->getMessage())
            ->withInput();
    }
}

    /**
     * Display the specified user
     */
    public function show($id)
    {
        $currentUser = auth()->user();

        $user = User::with([
            'creator',
            'properties',
            'invitations',
            'roles',
            'approvedFamilyLinks.property',
        ])
        ->withCount([
            'ownedProperties as properties_count',
            'approvedFamilyLinks as linked_properties_count',
        ])
        ->findOrFail($id);

        $this->authorizeUserView($user, $currentUser);

        $this->loadUserRelationships($user);

        $userData = $this->displayService->prepareForDisplay($user);
        $userStats = $this->userRepository->getUserStatistics($user);
        $communicationStatus = $this->getCommunicationServiceStatus();
        $stats = $this->userRepository->getDashboardStats($currentUser);

        $userTypes = User::getUserTypes();
        $statuses = User::getUserStatuses();

        $perPage = $this->getPerPage(request()->get('per_page', 10));

        $users = User::where('id', '!=', $user->id)
            ->where('type', '!=', User::TYPE_DEVELOPER)
            ->when(!$currentUser->isSuperAdmin(), fn($q) => $q->where('type', '!=', User::TYPE_SUPER_ADMIN))
            ->when($currentUser->isSuperAdmin(), fn($q) => $q->where('type', '!=', User::TYPE_SUPER_ADMIN)->orWhere('id', $currentUser->id))
            ->orderBy('name')
            ->paginate($perPage);

        return view('admin.users.show', compact(
            'user', 'userData', 'userStats', 'communicationStatus',
            'stats', 'userTypes', 'statuses', 'users', 'perPage'
        ));
    }

    /**
     * Show form for editing user
     */
    public function edit($id)
    {
        $currentUser = auth()->user();
        $user = User::with('roles')->findOrFail($id);

        $this->authorizeUserEdit($user, $currentUser);

        $userData = $this->displayService->prepareForDisplay($user);
        $userTypes = $this->getFilteredUserTypes($currentUser, $user);
        $statuses = User::getUserStatuses();
        $communicationStatus = $this->getCommunicationServiceStatus();

        $availableRoles = $currentUser->isSuperAdmin()
            ? Role::where('slug', '!=', 'developer')->orderBy('priority')->get()
            : collect();

        $sanitationSupervisors = $this->getAvailableSanitationSupervisors();

        $currentSupervisorId = $user->sanitationPersonnel?->supervisor_id;

        return view('admin.users.edit', compact(
            'user', 'userData', 'userTypes', 'statuses', 'communicationStatus',
            'availableRoles', 'sanitationSupervisors', 'currentSupervisorId'
        ));
    }

    /**
     * Update the specified user
     */
    /**
 * Update the specified user
 */
public function update(UpdateUserRequest $request, $id)
{
    $currentUser = auth()->user();
    $user        = User::findOrFail($id);

    DB::beginTransaction();

    try {
        $oldData  = $user->toArray();
        $oldRoles = $user->roles->pluck('id')->toArray();

        $updateData = $request->validated();

        // ================================================================
        // SANITATION
        // ================================================================
        $isSanitationPersonnel = $user->type === User::TYPE_SANITATION_PERSONNEL;

        if ($isSanitationPersonnel) {
            $updateData['sanitation_can_be_supervisor'] = $request->boolean('sanitation_can_be_supervisor', false);
            $updateData['sanitation_personnel_role']    = $request->input('sanitation_personnel_role', 'worker');
            $updateData['sanitation_personnel_status']  = $request->input('sanitation_personnel_status', 'active');
            $updateData['sanitation_vehicle_number']    = $request->input('sanitation_vehicle_number');
            $updateData['sanitation_vehicle_type']      = $request->input('sanitation_vehicle_type');
            $updateData['sanitation_emergency_contact'] = $request->input('sanitation_emergency_contact');
            $updateData['sanitation_hire_date']         = $request->input('sanitation_hire_date');
            $updateData['sanitation_address']           = $request->input('address');
            $updateData['sanitation_supervisor_id']     = $request->input('sanitation_supervisor_id');
        }

        // ================================================================
        // SECURITY
        // ================================================================
        // Eligibility is a single boolean. Certifications are optional
        // free-text. No rank, no level, no score.
        $isSecurityPersonnel = $user->type === User::TYPE_SECURITY_PERSONNEL;
        $isSecurityEligible  = $isSecurityPersonnel
            && $request->boolean('security_can_be_supervisor', false);

        $updateData['can_be_supervisor'] = $isSecurityEligible;

        if ($isSecurityPersonnel) {
            $updateData['security_supervisor_certifications'] =
                $request->input('security_supervisor_certifications');
        }

        // ================================================================
        // PERSIST
        // ================================================================
        $updated = $this->userRepository->updateUser($user, $updateData, $currentUser);

        if (!$updated) {
            throw new \Exception('Failed to update user');
        }

        // ================================================================
        // CASCADE: revoke eligibility → deactivate active assignment
        // ================================================================
        $eligibilityWasRevoked = $isSecurityPersonnel
            && !$isSecurityEligible
            && !empty($oldData['can_be_supervisor']);

        if ($eligibilityWasRevoked) {
            $activeAssignment = \App\Models\SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($activeAssignment) {
                $activeAssignment->update([
                    'is_active' => false,
                    'end_date'  => now()->subDay(),
                    'notes'     => trim(($activeAssignment->notes ?? '') .
                        "\nDeactivated because supervisor eligibility was revoked on " . now()->toDateString() . '.'),
                ]);

                Log::warning('Active Head-of-Security assignment deactivated on eligibility revocation', [
                    'user_id'       => $user->id,
                    'assignment_id' => $activeAssignment->id,
                    'revoked_by'    => $currentUser->id,
                ]);
            }
        }

        // ================================================================
        // UPDATE ROLES
        // ================================================================
        // Same slug-or-ID resolver as store().
        $skipSpatieRoles = in_array($user->type, [
            User::TYPE_SANITATION_PERSONNEL,
            User::TYPE_SECURITY_PERSONNEL,
        ]);

        if (!$skipSpatieRoles && $currentUser->isSuperAdmin() && $request->has('roles')) {
            $roleIds = $this->resolveRoleIdentifiers((array) $request->input('roles', []));
            $user->roles()->sync($roleIds);
        }

        DB::commit();

        $changes = $this->getChanges($oldData, $user->toArray());
        $changes['roles'] = [
            'old' => $oldRoles,
            'new' => $user->roles->pluck('id')->toArray(),
        ];
        event(new UserUpdated($user, $currentUser, $changes));

        $successMessage = $this->buildUpdateSuccessMessage($user, $request->validated());

        return redirect()->route('admin.users.show', $user->id)
            ->with('success', $successMessage);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to update user: ' . $e->getMessage(), [
            'user_id'     => $user->id,
            'error_trace' => $e->getTraceAsString(),
            'updated_by'  => $currentUser->id,
        ]);

        return back()->with('error', 'Failed to update user: ' . $e->getMessage())
            ->withInput();
    }
}

    // ================================================================ //
    // ========== NEW HELPER: resolve slugs OR IDs → numeric IDs ========== //
    // ================================================================ //
    /**
     * Normalize the incoming `roles[]` payload into an array of numeric
     * role IDs suitable for `$user->roles()->sync()`.
     *
     * Accepts a mixed array where each element may be:
     *   • a numeric role ID (legacy checkbox form)
     *   • a role slug string (auto-role input from create blade)
     *
     * Unknown slugs are silently dropped.
     */
    protected function resolveRoleIdentifiers(array $raw): array
    {
        $numeric = [];
        $slugs   = [];

        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $numeric[] = (int) $value;
            } else {
                $slugs[] = (string) $value;
            }
        }

        if (!empty($slugs)) {
            $slugIds = Role::whereIn('slug', $slugs)->pluck('id')->all();
            $numeric = array_merge($numeric, array_map('intval', $slugIds));
        }

        return array_values(array_unique(array_filter($numeric)));
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        $this->authorizePasswordChange($user, $currentUser);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'notify_user' => 'sometimes|boolean',
            'notification_channels' => 'sometimes|array|in:sms,email,whatsapp',
        ]);

        DB::beginTransaction();

        try {
            $user->update(['password' => bcrypt($validated['password'])]);

            if ($request->boolean('notify_user')) {
                $channels = $request->input('notification_channels', ['email']);

                if (empty($user->email)) {
                    $channels = array_values(array_diff($channels, ['email']));
                }
                if (empty($user->phone)) {
                    $channels = array_values(array_diff($channels, ['sms', 'whatsapp']));
                }

                if (!empty($channels)) {
                    $this->sendPasswordChangeNotification($user, $channels);
                } else {
                    Log::info('Password change notification skipped — no contact channels available', [
                        'user_id'    => $user->id,
                        'has_email'  => !empty($user->email),
                        'has_phone'  => !empty($user->phone),
                    ]);
                }
            }

            DB::commit();

            Log::info('Password updated', [
                'user_id' => $user->id,
                'updated_by' => $currentUser->id,
                'notification_sent' => $request->boolean('notify_user')
            ]);

            return back()->with('success', 'Password updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update password: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to update password: ' . $e->getMessage());
        }
    }

    /**
     * Send invitation to user
     */
    public function sendInvitation(Request $request, $id)
    {
        try {
            if (ob_get_level()) {
                ob_clean();
            }

            $currentUser = auth()->user();
            $user = User::findOrFail($id);

            $this->authorizeInvitationSend($user, $currentUser);

            $validated = $request->validate([
                'invitation_channels' => 'required|array',
                'invitation_channels.*' => 'in:sms,email,whatsapp',
                'invitation_type' => 'required|in:welcome,registration,account_setup,password_setup',
                'custom_message' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
                'template' => 'nullable|string|max:100',
            ]);

            if (empty($validated['expires_in_days'])) {
                $validated['expires_in_days'] = config('invitation.default_expiry_days', 7);
            }

            $result = $this->invitationService->sendInvitation($user, $validated);

            if ($request->expectsJson() || $request->ajax()) {
                if ($result['success']) {
                    return $this->cleanJsonResponse([
                        'success' => true,
                        'message' => 'Invitation sent successfully!',
                        'invitation_url' => $result['invitation_url'] ?? null,
                        'channels_successful' => $result['channels_successful'] ?? [],
                        'failed_channels' => $result['failed_channels'] ?? []
                    ]);
                }

                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send invitation'
                ], 422);
            }

            if ($result['success']) {
                $channels = implode(', ', $result['channels_successful']);
                return back()->with('success', "Invitation sent successfully via {$channels}");
            }

            return back()->with('error', 'Failed to send invitation: ' . ($result['message'] ?? 'Unknown error'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;

        } catch (\Exception $e) {
            Log::error('Failed to send invitation: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to send invitation: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to send invitation: ' . $e->getMessage());
        }
    }

    /**
     * Resend invitation
     */
    public function resendInvitation(Request $request, $id)
    {
        try {
            if (ob_get_level()) {
                ob_clean();
            }

            $currentUser = auth()->user();
            $user = User::findOrFail($id);

            $this->authorizeInvitationSend($user, $currentUser);

            $validated = $request->validate([
                'channels' => 'sometimes|array',
                'channels.*' => 'in:sms,email,whatsapp',
                'custom_message' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
                'resend_type' => 'nullable|in:same_channels,available_channels,selected_channels',
                'invitation_type' => 'nullable|in:welcome,registration,account_setup,password_setup'
            ]);

            $result = $this->invitationService->resendInvitation($user, $validated);

            if ($request->expectsJson() || $request->ajax()) {
                if ($result['success']) {
                    return $this->cleanJsonResponse([
                        'success' => true,
                        'message' => 'Invitation resent successfully!',
                        'invitation_url' => $result['invitation_url'] ?? null,
                        'channels_successful' => $result['channels_successful'] ?? [],
                        'failed_channels' => $result['failed_channels'] ?? []
                    ]);
                }

                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to resend invitation'
                ], 422);
            }

            if ($result['success']) {
                $channels = implode(', ', $result['channels_successful']);
                return back()->with('success', "Invitation resent successfully via {$channels}");
            }

            return back()->with('error', 'Failed to resend invitation: ' . ($result['message'] ?? 'Unknown error'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;

        } catch (\Exception $e) {
            Log::error('Failed to resend invitation: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to resend invitation: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to resend invitation: ' . $e->getMessage());
        }
    }

    /**
     * Get invitation information for a user (for resend modal)
     */
    public function getInvitationInfo($id)
    {
        try {
            if (ob_get_level()) {
                ob_clean();
            }

            $user = User::with(['invitations' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])->findOrFail($id);

            $lastInvitation = $user->invitations->first();

            if ($lastInvitation) {
                $channels = $lastInvitation->channels;

                if (is_string($channels)) {
                    $channels = json_decode($channels, true) ?? [];
                } elseif (!is_array($channels)) {
                    $channels = [];
                }

                return $this->cleanJsonResponse([
                    'success' => true,
                    'invitation' => [
                        'sent_at' => $lastInvitation->created_at,
                        'expires_at' => $lastInvitation->expires_at,
                        'channels' => $channels,
                        'type' => $lastInvitation->type ?? 'welcome',
                    ]
                ]);
            }

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'No previous invitation found'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation info: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve invitation information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate user account
     */
    public function activate($id)
    {
        $user = $this->findUserAndAuthorizeAction($id, 'activate');

        if ($user->update(['status' => User::STATUS_ACTIVE])) {
            Log::info('User account activated', [
                'user_id' => $user->id,
                'activated_by' => auth()->id()
            ]);

            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'User account activated successfully!'
                ]);
            }

            return back()->with('success', 'User account activated successfully!');
        }

        if (request()->expectsJson() || request()->ajax()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to activate user account.'
            ], 500);
        }

        return back()->with('error', 'Failed to activate user account.');
    }

    /**
     * Suspend user account
     */
    public function suspend(Request $request, $id)
    {
        $user = $this->findUserAndAuthorizeAction($id, 'suspend');

        if ($user->id === auth()->id()) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You cannot suspend your own account.'
                ], 422);
            }
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $reason = $request->input('reason', 'No reason provided');

        if ($user->update([
            'status' => User::STATUS_SUSPENDED,
            'suspension_reason' => $reason,
            'suspended_at' => now(),
            'suspended_by' => auth()->id()
        ])) {
            Log::info('User account suspended', [
                'user_id' => $user->id,
                'suspended_by' => auth()->id(),
                'reason' => $reason
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'User account suspended successfully!'
                ]);
            }

            return back()->with('success', 'User account suspended successfully!');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to suspend user account.'
            ], 500);
        }

        return back()->with('error', 'Failed to suspend user account.');
    }

    /**
     * Deactivate user account
     */
    public function deactivate(Request $request, $id)
    {
        $user = $this->findUserAndAuthorizeAction($id, 'deactivate');

        if ($user->id === auth()->id()) {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.'
                ], 422);
            }
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $reason = $request->input('reason', 'Account deactivated by administrator');

        if ($user->update([
            'status' => User::STATUS_INACTIVE,
            'deactivation_reason' => $reason,
            'deactivated_at' => now(),
            'deactivated_by' => auth()->id()
        ])) {
            Log::info('User account deactivated', [
                'user_id' => $user->id,
                'deactivated_by' => auth()->id(),
                'reason' => $reason
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'User account deactivated successfully!'
                ]);
            }

            return back()->with('success', 'User account deactivated successfully!');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to deactivate user account.'
            ], 500);
        }

        return back()->with('error', 'Failed to deactivate user account.');
    }

    /**
     * Soft delete user
     */
    public function destroy(Request $request, $id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        $this->authorizeUserDeletion($user, $currentUser);

        if ($user->id === auth()->id()) {
            return $this->deletionResponse($request, false, 'You cannot delete your own account.');
        }

        DB::beginTransaction();

        try {
            $criticalRelations = $this->checkCriticalRelations($user);

            if (!empty($criticalRelations)) {
                $message = "Cannot delete user. Has: " . implode(', ', $criticalRelations);
                return $this->deletionResponse($request, false, $message);
            }

            $deletionReason = $request->input('deletion_reason', 'No reason provided');
            $result = $this->userRepository->softDeleteUser($user, $currentUser, $deletionReason);

            if ($result) {
                DB::commit();
                event(new UserDeleted($user, $currentUser, $deletionReason));

                Log::info('User soft deleted', [
                    'user_id' => $user->id,
                    'deleted_by' => $currentUser->id,
                    'deletion_reason' => $deletionReason
                ]);

                return $this->deletionResponse($request, true, 'User account deleted successfully!', route('admin.users.index'));
            }

            throw new \Exception('Failed to delete user');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString(),
                'deleted_by' => $currentUser->id
            ]);

            return $this->deletionResponse($request, false, 'Failed to delete user account: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted user (from trash).
     */
    public function restore($id)
    {
        $currentUser = auth()->user();

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            $this->authorizeUserRestore($user, $currentUser);

            DB::beginTransaction();

            $user->restore();

            $user->update([
                'status'          => User::STATUS_PENDING,
                'deleted_at'      => null,
                'deleted_by'      => null,
                'deletion_reason' => null,
            ]);

            $metadata = $user->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }
            if (is_array($metadata) && isset($metadata['deletion_info'])) {
                unset($metadata['deletion_info']);
                $user->update(['metadata' => $metadata]);
            }

            DB::commit();

            Log::info('User restored from trash', [
                'user_id'        => $user->id,
                'user_name'      => $user->name,
                'user_email'     => $user->email,
                'restored_by'    => $currentUser->id,
                'restored_by_name' => $currentUser->name,
            ]);

            return $this->cleanJsonResponse([
                'success'      => true,
                'message'      => 'User restored successfully!',
                'redirect_url' => route('admin.users.show', $user->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to restore user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk actions for users
     */
    public function bulkAction(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:activate,suspend,deactivate,delete,send_invitation',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'invitation_channels' => 'nullable|required_if:action,send_invitation|array',
            'invitation_type' => 'nullable|required_if:action,send_invitation|in:welcome,registration,account_setup,password_setup',
            'reason' => 'nullable|string|max:500'
        ]);

        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        foreach ($validated['user_ids'] as $userId) {
            $result = $this->processBulkAction($userId, $validated['action'], $validated, $currentUser);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = $result;
        }

        $message = "Bulk action completed: {$results['success']} successful, {$results['failed']} failed";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $results['success'] > 0,
                'results' => $results,
                'message' => $message
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Get available channels for user
     */
    public function getUserChannels(User $user, Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $availableChannels = $this->invitationService->getAvailableChannels($user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'available_channels' => $availableChannels,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'has_phone' => !empty($user->phone),
                    'has_email' => !empty($user->email),
                ]
            ]);
        }

        return $availableChannels;
    }

    // ==================== MULTI-ROLE MANAGEMENT METHODS ====================

    /**
     * Get user roles data for AJAX modal
     */
    public function getUserRolesData($id)
    {
        if (ob_get_level()) {
            ob_clean();
        }

        if (!auth()->user()->isSuperAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can manage roles.'
            ], 403);
        }

        try {
            $user = User::with('roles')
                ->withCount([
                    'ownedProperties as properties_count',
                    'approvedFamilyLinks as linked_properties_count',
                ])
                ->findOrFail($id);

            if ($user->type === User::TYPE_DEVELOPER) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Cannot manage roles for developer accounts.'
                ], 403);
            }

            $availableRoles = Role::where('slug', '!=', 'developer')
                ->orderBy('priority')
                ->get();

            $ownedCount  = (int) ($user->properties_count ?? 0);
            $linkedCount = (int) ($user->linked_properties_count ?? 0);

            $responseData = [
                'success' => true,
                'user' => [
                    'id'      => $user->id,
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'type'    => $user->type,
                    'current_roles' => $user->roles->map(function ($role) {
                        return [
                            'id'           => $role->id,
                            'name'         => $role->name,
                            'slug'         => $role->slug,
                            'display_name' => $role->display_name ?? $role->name,
                            'description'  => $role->description,
                        ];
                    }),

                    'is_property_owner'      => $ownedCount > 0,
                    'has_property_access'    => ($ownedCount + $linkedCount) > 0,
                    'property_count'         => $ownedCount,
                    'linked_property_count'  => $linkedCount,
                    'accessible_property_count' => $ownedCount + $linkedCount,
                ],
                'available_roles' => $availableRoles->map(function ($role) {
                    return [
                        'id'           => $role->id,
                        'name'         => $role->name,
                        'slug'         => $role->slug,
                        'display_name' => $role->display_name ?? $role->name,
                        'description'  => $role->description,
                        'priority'     => $role->priority,
                    ];
                }),
            ];

            return $this->cleanJsonResponse($responseData);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'User not found.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get user roles data: ' . $e->getMessage(), [
                'user_id' => $id
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to load user roles data.'
            ], 500);
        }
    }

    /**
     * Helper method to return clean JSON response
     */
    protected function cleanJsonResponse($data, $status = 200)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $jsonString = json_encode($data);

        if (substr($jsonString, 0, 3) === "\xEF\xBB\xBF") {
            $jsonString = substr($jsonString, 3);
            $data = json_decode($jsonString, true);
            $jsonString = json_encode($data);
        }

        return response($jsonString, $status)
            ->header('Content-Type', 'application/json')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    /**
     * Update user roles (sync multiple roles at once)
     */
    public function updateUserRoles(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can assign roles.'
            ], 403);
        }

        $validated = $request->validate([
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            if ($user->type === User::TYPE_DEVELOPER) {
                throw new \Exception('Cannot modify roles for developer accounts.');
            }

            $roleIds = $validated['roles'] ?? [];
            $roles = Role::whereIn('id', $roleIds)->get();

            $oldRoles = $user->roles->pluck('slug')->toArray();

            $user->roles()->sync($roleIds);

            $hasLandlordRole = $roles->contains('slug', 'landlord');
            if ($hasLandlordRole && $user->type !== User::TYPE_LANDLORD) {
                Log::info('Landlord role assigned to user', [
                    'user_id' => $user->id,
                    'user_type' => $user->type,
                    'by_user' => auth()->id()
                ]);
            }

            DB::commit();

            $newRoles = $user->fresh()->roles->pluck('slug')->toArray();

            Log::info('User roles updated', [
                'user_id' => $user->id,
                'old_roles' => $oldRoles,
                'new_roles' => $newRoles,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User roles updated successfully!',
                'user_roles' => $newRoles,
                'role_details' => $user->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user roles: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign landlord role to a user
     */
    public function assignLandlordRole(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can assign landlord role.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            if ($user->type === User::TYPE_DEVELOPER) {
                throw new \Exception('Cannot assign landlord role to developer accounts.');
            }

            $landlordRole = Role::where('slug', 'landlord')->first();

            if (!$landlordRole) {
                throw new \Exception('Landlord role not found. Please run the role seeder.');
            }

            if (!$user->hasRole('landlord')) {
                $user->roles()->attach($landlordRole);

                Log::info('Landlord role assigned', [
                    'user_id' => $user->id,
                    'assigned_by' => auth()->id()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Landlord role assigned successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign landlord role: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign landlord role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove landlord role from a user
     */
    public function removeLandlordRole(Request $request, $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can remove landlord role.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            $propertyCount = $user->properties()->count();

            if ($propertyCount > 0) {
                $validated = $request->validate([
                    'force_remove' => 'sometimes|boolean',
                    'transfer_to' => 'nullable|exists:users,id'
                ]);

                if (!$request->boolean('force_remove')) {
                    return response()->json([
                        'success' => false,
                        'message' => "User owns {$propertyCount} property(s). You must either transfer properties or use force_remove flag.",
                        'property_count' => $propertyCount,
                        'requires_transfer' => true
                    ], 422);
                }

                if ($request->has('transfer_to') && $request->filled('transfer_to')) {
                    $newOwner = User::find($request->transfer_to);
                    if ($newOwner) {
                        $transferred = Property::where('landlord_id', $user->id)->update(['landlord_id' => $newOwner->id]);
                        Log::info('Properties transferred during role removal', [
                            'from_user' => $user->id,
                            'to_user' => $newOwner->id,
                            'property_count' => $transferred
                        ]);
                    }
                }
            }

            $landlordRole = Role::where('slug', 'landlord')->first();

            if ($landlordRole && $user->hasRole('landlord')) {
                $user->roles()->detach($landlordRole->id);

                Log::info('Landlord role removed', [
                    'user_id' => $user->id,
                    'removed_by' => auth()->id(),
                    'force_remove' => $request->boolean('force_remove'),
                    'properties_transferred' => $request->has('transfer_to')
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Landlord role removed successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove landlord role: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove landlord role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk role action for the index blade
     */
    public function bulkRoleAction(Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can perform bulk role assignments.'
            ], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:assign_landlord,remove_landlord',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'force_remove' => 'sometimes|boolean',
            'transfer_to' => 'nullable|exists:users,id',
            'skip_property_owners' => 'sometimes|boolean'
        ]);

        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];
        $landlordRole = Role::where('slug', 'landlord')->first();

        if (!$landlordRole) {
            return response()->json([
                'success' => false,
                'message' => 'Landlord role not found. Please run the role seeder.'
            ], 500);
        }

        foreach ($validated['user_ids'] as $userId) {
            try {
                $user = User::find($userId);

                if (!$user) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'User not found'];
                    continue;
                }

                if ($user->type === User::TYPE_DEVELOPER) {
                    $results['failed']++;
                    $results['details'][] = ['id' => $userId, 'success' => false, 'message' => 'Cannot modify developer accounts'];
                    continue;
                }

                if ($validated['action'] === 'assign_landlord') {
                    if (!$user->hasRole('landlord')) {
                        $user->roles()->attach($landlordRole);
                        $results['success']++;
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Landlord role assigned'];
                    } else {
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Already has landlord role'];
                        $results['success']++;
                    }
                } elseif ($validated['action'] === 'remove_landlord') {
                    $propertyCount = $user->properties()->count();

                    if ($propertyCount > 0 && !($validated['force_remove'] ?? false)) {
                        if ($validated['skip_property_owners'] ?? false) {
                            $results['skipped']++;
                            $results['details'][] = [
                                'id' => $userId,
                                'success' => true,
                                'message' => "Skipped - User owns {$propertyCount} property(s)"
                            ];
                            continue;
                        }

                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => "User owns {$propertyCount} property(s). Use force_remove to override."
                        ];
                        continue;
                    }

                    if ($user->hasRole('landlord')) {
                        $user->roles()->detach($landlordRole->id);
                        $results['success']++;
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Landlord role removed'];
                    } else {
                        $results['details'][] = ['id' => $userId, 'success' => true, 'message' => 'Does not have landlord role'];
                        $results['success']++;
                    }
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
                Log::error("Bulk role action failed for user {$userId}: " . $e->getMessage());
            }
        }

        $message = "Bulk role action completed: {$results['success']} successful, {$results['failed']} failed";
        if ($results['skipped'] > 0) {
            $message .= ", {$results['skipped']} skipped";
        }

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => $message
        ]);
    }

    /**
     * Get available roles (for dropdowns)
     */
    public function getAvailableRoles()
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $roles = Role::where('slug', '!=', 'developer')
            ->orderBy('priority')
            ->get()
            ->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'display_name' => $role->display_name ?? $role->name,
                    'description' => $role->description,
                    'priority' => $role->priority,
                ];
            });

        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }

    /**
     * Check if user has critical relations (for deletion validation)
     */
    public function checkRelations($id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $criticalRelations = $this->checkCriticalRelations($user);

        return $this->cleanJsonResponse([
            'success' => true,
            'has_critical_relations' => !empty($criticalRelations),
            'critical_relations' => $criticalRelations,
            'user_id' => $user->id,
            'user_name' => $user->name
        ]);
    }

    // ==================== TRASH & ARCHIVE MANAGEMENT ====================

    /**
     * Display trash (deleted users)
     */
    public function trash(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized to view trashed users.');
        }

        $perPage = $this->getPerPage($request->get('per_page'));

        $users = User::onlyTrashed()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type') && $request->type !== 'all', function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('deleted_by'), function ($query) use ($request) {
                $query->where('deleted_by', $request->deleted_by);
            })
            ->when(!$currentUser->isSuperAdmin(), function ($query) {
                $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $metadata = $user->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $deletionInfo = $metadata['deletion_info'] ?? [];

            $deletedByName = $deletionInfo['deleted_by_name'] ?? null;

            if (!$deletedByName) {
                $deleterId = $deletionInfo['deleted_by'] ?? $user->deleted_by;
                if ($deleterId) {
                    $deleter = User::withTrashed()->find($deleterId);
                    $deletedByName = $deleter?->name;
                }
            }

            $deletedByName = $deletedByName ?: 'System';

            $user->setAttribute('resolved_deleted_by_name', $deletedByName);
            $user->setAttribute('resolved_deletion_reason',
                $deletionInfo['deletion_reason'] ?? $user->deletion_reason
            );
            $user->setAttribute('resolved_days_ago',
                $user->deleted_at ? (int) $user->deleted_at->diffInDays(now()) : 0
            );

            return $user;
        });

        $userTypes = $this->getFilteredUserTypes($currentUser);

        $stats = [
            'total_trashed'      => User::onlyTrashed()->count(),
            'trashed_this_week'  => User::onlyTrashed()->where('deleted_at', '>=', now()->subWeek())->count(),
            'trashed_this_month' => User::onlyTrashed()->where('deleted_at', '>=', now()->subMonth())->count(),
        ];

        $statuses = User::getUserStatuses();
        $perPageOptions = self::PER_PAGE_OPTIONS;

        return view('admin.users.trash', compact(
            'users', 'userTypes', 'stats', 'statuses', 'perPage', 'perPageOptions'
        ));
    }

    /**
     * Display archived users with filtering and stats
     */
    public function archivedUsers(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized to view archived users.');
        }

        $perPage = $this->getPerPage($request->get('per_page'));

        $users = User::onlyTrashed()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type') && $request->type !== 'all', function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('archival_period') && $request->archival_period !== 'all', function ($query) use ($request) {
                switch ($request->archival_period) {
                    case 'today':
                        $query->whereDate('deleted_at', today());
                        break;
                    case 'week':
                        $query->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'month':
                        $query->whereMonth('deleted_at', now()->month);
                        break;
                    case 'year':
                        $query->whereYear('deleted_at', now()->year);
                        break;
                }
            })
            ->when($request->filled('deletion_status') && $request->deletion_status !== 'all', function ($query) use ($request) {
                if ($request->deletion_status === 'scheduled') {
                    $query->whereNotNull('deletion_scheduled_at');
                } else {
                    $query->whereNull('deletion_scheduled_at');
                }
            })
            ->when(!$currentUser->isSuperAdmin(), function ($query) {
                $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $userData = $this->displayService->prepareForDisplay($user);

            $metadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $metadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $metadata = $user->metadata;
                }
            }

            $rawArchivalInfo = $metadata['archival_info']
                ?? $metadata['archived']
                ?? [];

            $archivedByName = $rawArchivalInfo['archived_by_name'] ?? null;

            if (!$archivedByName) {
                $deleterId = $rawArchivalInfo['archived_by']
                    ?? $user->deleted_by
                    ?? null;

                if ($deleterId) {
                    $deleter = User::withTrashed()->find($deleterId);
                    $archivedByName = $deleter?->name;
                }
            }

            $archivedByName = $archivedByName ?: 'System';

            $userData['archival_info'] = [
                'archived_at'       => $rawArchivalInfo['archived_at']
                    ?? $user->deleted_at?->toISOString(),
                'archived_reason'   => $rawArchivalInfo['archived_reason']
                    ?? $rawArchivalInfo['reason']
                    ?? $user->deletion_reason
                    ?? 'Account archived',
                'archived_by'       => $rawArchivalInfo['archived_by']
                    ?? $user->deleted_by,
                'archived_by_name'  => $archivedByName,
                'original_email'    => $rawArchivalInfo['original_email']
                    ?? $metadata['archived']['original_email']
                    ?? null,
                'original_phone'    => $rawArchivalInfo['original_phone']
                    ?? $metadata['archived']['original_phone']
                    ?? null,
                'original_username' => $rawArchivalInfo['original_username']
                    ?? $metadata['archived']['original_username']
                    ?? null,
                'user_type_at_archival' => $rawArchivalInfo['user_type_at_archival']
                    ?? $metadata['archived']['user_type']
                    ?? $user->type,
                'archival_type'     => $rawArchivalInfo['archival_type']
                    ?? 'manual_admin',
                'days_since_archival' => $user->deleted_at
                    ? (int) $user->deleted_at->diffInDays(now())
                    : 0,
            ];

            $userData['archived_at'] = $user->deleted_at;

            $deletionScheduledAt = $user->deletion_scheduled_at ?? null;
            if ($deletionScheduledAt && !($deletionScheduledAt instanceof \Carbon\Carbon)) {
                try {
                    $deletionScheduledAt = \Carbon\Carbon::parse($deletionScheduledAt);
                } catch (\Throwable $e) {
                    $deletionScheduledAt = null;
                }
            }

            $userData['deletion_scheduled_at'] = $deletionScheduledAt;
            $userData['days_until_deletion'] = $deletionScheduledAt
                ? max(0, (int) now()->diffInDays($deletionScheduledAt, false))
                : null;

            return $userData;
        });

        $stats = [
            'total_archived' => User::onlyTrashed()->count(),

            'archived_today' => User::onlyTrashed()
                ->whereDate('deleted_at', today())
                ->count(),

            'archived_this_week' => User::onlyTrashed()
                ->where('deleted_at', '>=', now()->startOfWeek())
                ->count(),

            'archived_this_month' => User::onlyTrashed()
                ->whereMonth('deleted_at', now()->month)
                ->count(),

            'scheduled_for_deletion' => User::onlyTrashed()
                ->whereNotNull('deletion_scheduled_at')
                ->where('deletion_scheduled_at', '>', now())
                ->count(),

            'restored_this_month' => User::query()
                ->whereNotNull('metadata->restored->restored_at')
                ->where('metadata->restored->restored_at', '>=', now()->startOfMonth()->toISOString())
                ->count(),

            'restored_count' => User::query()
                ->whereNotNull('metadata->restored->restored_at')
                ->count(),

            'pending_restore_requests' => 0,
        ];

        $userTypes = $this->getFilteredUserTypes($currentUser);
        $perPageOptions = self::PER_PAGE_OPTIONS;

        return view('admin.users.archived', compact('users', 'userTypes', 'stats', 'perPage', 'perPageOptions'));
    }

    /**
     * Restore an archived user
     */
    public function restoreArchived($id)
    {
        $currentUser = auth()->user();
        $user = User::onlyTrashed()->findOrFail($id);

        $this->authorizeUserRestore($user, $currentUser);

        DB::beginTransaction();

        try {
            $metadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $metadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $metadata = $user->metadata;
                }
            }

            $originalEmail = $metadata['archival_info']['original_email'] ??
                            $metadata['archived']['original_email'] ??
                            null;
            $originalUsername = $metadata['archival_info']['original_username'] ??
                               $metadata['archived']['original_username'] ??
                               null;
            $originalPhone = $metadata['archival_info']['original_phone'] ??
                            $metadata['archived']['original_phone'] ??
                            null;

            $user->restore();

            unset($metadata['archival_info']);
            unset($metadata['deletion_info']);
            unset($metadata['archived']);

            $restoreData = [
                'metadata' => !empty($metadata) ? $metadata : null,
                'deletion_scheduled_at' => null,
                'deleted_by' => null,
                'status' => User::STATUS_ACTIVE,
            ];

            if ($originalEmail && !User::where('email', $originalEmail)->where('id', '!=', $user->id)->exists()) {
                $restoreData['email'] = $originalEmail;
            }

            if ($originalUsername && !User::where('username', $originalUsername)->where('id', '!=', $user->id)->exists()) {
                $restoreData['username'] = $originalUsername;
            }

            if ($originalPhone && !User::where('phone', $originalPhone)->where('id', '!=', $user->id)->exists()) {
                $restoreData['phone'] = $originalPhone;
            }

            $user->update($restoreData);

            $currentMetadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $currentMetadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $currentMetadata = $user->metadata;
                }
            }

            $currentMetadata['restored'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => $currentUser->id,
                'restored_by_name' => $currentUser->name,
                'previous_email' => $user->getOriginal('email'),
                'previous_username' => $user->getOriginal('username')
            ];

            $user->update(['metadata' => $currentMetadata]);

            DB::commit();

            Log::info('Archived user restored', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'restored_email' => $user->email,
                'restored_by' => $currentUser->id
            ]);

            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'User account restored successfully!',
                    'redirect_url' => route('admin.users.show', $user->id)
                ]);
            }

            return redirect()->route('admin.users.show', $user->id)
                ->with('success', 'User account restored successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore archived user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            if (request()->expectsJson() || request()->ajax()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to restore user: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to restore user: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore archived users
     */
    public function bulkRestoreArchived(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found'
                        ];
                        continue;
                    }

                    $user->restore();
                    $results['success']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => true,
                        'message' => 'User restored'
                    ];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk restore failed for user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            $message = "Bulk restore completed: {$results['success']} successful, {$results['failed']} failed";

            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'restored_count' => $results['success'],
                'failed_count' => $results['failed'],
                'message' => $message,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage(), [
                'user_ids' => $validated['user_ids']
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get archived user details for AJAX modal
     */
    public function getArchivedUserDetails($id)
    {
        $currentUser = auth()->user();

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $metadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $metadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $metadata = $user->metadata;
                }
            }

            $archivalInfo = $metadata['archival_info'] ?? [];
            $deletionInfo = $metadata['deletion_info'] ?? [];

            $userData = $this->displayService->prepareForDisplay($user);
            $userData['deleted_at'] = $user->deleted_at?->format('Y-m-d H:i:s');

            $deletedByName = 'System';
            if ($user->deleted_by) {
                $deletedByUser = User::withTrashed()->find($user->deleted_by);
                $deletedByName = $deletedByUser ? $deletedByUser->name : 'System';
            }
            $userData['deleted_by_name'] = $deletedByName;

            return response()->json([
                'success' => true,
                'user' => $userData,
                'archival_info' => $archivalInfo,
                'deletion_info' => $deletionInfo,
                'properties_count' => $user->properties()->count(),
                'related_records' => [
                    'invitations' => $user->invitations()->count(),
                    'activities' => 0,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get archived user details: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deleted user details for AJAX modal
     */
    public function getDeletedUserDetails($id)
    {
        $currentUser = auth()->user();

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Unauthorized to view this user details.'
                ], 403);
            }

            $deletedBy = null;
            if ($user->deleted_by) {
                $deletedBy = User::withTrashed()->find($user->deleted_by);
            }

            $metadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $metadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $metadata = $user->metadata;
                }
            }

            $deletionInfo = $metadata['deletion_info'] ?? [
                'reason' => $metadata['archival_info']['archived_reason'] ?? 'No reason provided',
                'deleted_at' => $user->deleted_at,
                'deleted_by' => $user->deleted_by
            ];

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'deleted_at' => $user->deleted_at?->format('Y-m-d H:i:s'),
                'deleted_by_name' => optional($deletedBy)->name ?? 'System',
                'deletion_reason' => $deletionInfo['reason'] ?? 'Not specified',
                'can_restore' => $this->canRestoreUser($user)
            ];

            $html = view('admin.users.partials.deleted-user-details', compact('user', 'deletedBy', 'deletionInfo'))->render();

            return $this->cleanJsonResponse([
                'success' => true,
                'user' => $userData,
                'html' => $html
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get deleted user details: ' . $e->getMessage(), [
                'user_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to load user details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a single user (force delete).
     */
    public function forceDelete(Request $request, $id)
    {
        $currentUser = auth()->user();

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            if ($user->type === User::TYPE_DEVELOPER) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete developer accounts.',
                ], 403);
            }

            if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Unauthorized to delete Super Admin accounts.',
                ], 403);
            }

            if ($currentUser->isSuperAdmin()
                && $user->type === User::TYPE_SUPER_ADMIN
                && $user->id !== $currentUser->id) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'You cannot delete other Super Admin accounts.',
                ], 403);
            }

            DB::beginTransaction();

            $userInfo = [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'type'   => $user->type,
                'photo'  => $user->photo,
            ];

            $user->forceFill([
                'name'              => 'Deleted User',
                'email'             => "deleted_{$user->id}@deleted.example",
                'phone'             => null,
                'username'          => "deleted_{$user->id}",
                'digital_address'   => null,
                'location'          => null,
                'photo'             => null,
                'phone_verified_at' => null,
                'email_verified_at' => null,
                'last_login_at'     => null,
                'last_activity_at'  => null,
            ])->saveQuietly();

            if (!empty($userInfo['photo'])) {
                $photoPath = 'users/photos/' . $userInfo['photo'];
                if (Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
            }

            $user->roles()->detach();

            $user->forceDelete();

            DB::commit();

            Log::warning('User permanently deleted (PII scrubbed)', [
                'user_id'          => $userInfo['id'],
                'user_name_before' => $userInfo['name'],
                'user_email_before'=> $userInfo['email'],
                'user_type'        => $userInfo['type'],
                'deleted_by'       => $currentUser->id,
                'deleted_by_name'  => $currentUser->name,
                'deletion_reason'  => $request->input('deletion_reason'),
            ]);

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'User permanently deleted successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to permanently delete user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk restore multiple users (for trash)
     */
    public function bulkRestore(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $results = ['success' => 0, 'failed' => 0, 'restored_count' => 0, 'details' => []];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found'
                        ];
                        continue;
                    }

                    $user->restore();
                    $user->update([
                        'status' => User::STATUS_PENDING,
                        'deleted_at' => null,
                        'deleted_by' => null
                    ]);

                    $results['success']++;
                    $results['restored_count']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => true,
                        'message' => 'User restored'
                    ];

                    Log::info('User restored via bulk action', [
                        'user_id' => $userId,
                        'restored_by' => $currentUser->id
                    ]);

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk restore failed for user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            $message = "Bulk restore completed: {$results['success']} successful, {$results['failed']} failed";

            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'restored_count' => $results['restored_count'],
                'failed_count' => $results['failed'],
                'message' => $message,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage(), [
                'user_ids' => $validated['user_ids']
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk permanently delete multiple users
     */
    public function bulkPermanentDelete(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'deletion_reason' => 'nullable|string|max:500'
        ]);

        if (in_array($currentUser->id, $validated['user_ids'])) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'You cannot permanently delete your own account.'
            ], 422);
        }

        $results = ['success' => 0, 'failed' => 0, 'deleted_count' => 0, 'details' => []];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                try {
                    $user = User::onlyTrashed()->find($userId);

                    if (!$user) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'User not found or not trashed'
                        ];
                        continue;
                    }

                    if ($user->type === User::TYPE_DEVELOPER) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'Cannot delete developer accounts'
                        ];
                        continue;
                    }

                    if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $userId,
                            'success' => false,
                            'message' => 'Unauthorized to delete Super Admin accounts'
                        ];
                        continue;
                    }

                    $user->roles()->detach();

                    if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                        Storage::disk('public')->delete('users/photos/' . $user->photo);
                    }

                    $user->forceDelete();

                    $results['success']++;
                    $results['deleted_count']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => true,
                        'message' => 'User permanently deleted'
                    ];

                    Log::info('User permanently deleted via bulk action', [
                        'user_id' => $userId,
                        'deleted_by' => $currentUser->id,
                        'deletion_reason' => $validated['deletion_reason'] ?? null
                    ]);

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $userId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    Log::error("Bulk permanent delete failed for user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            $message = "Bulk permanent delete completed: {$results['success']} successful, {$results['failed']} failed";

            return $this->cleanJsonResponse([
                'success' => $results['success'] > 0,
                'deleted_count' => $results['deleted_count'],
                'failed_count' => $results['failed'],
                'message' => $message,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk permanent delete failed: ' . $e->getMessage(), [
                'user_ids' => $validated['user_ids'],
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Bulk permanent delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Empty entire trash
     */
    public function emptyTrash(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can empty trash.'
            ], 403);
        }

        $validated = $request->validate([
            'confirmation' => 'required|string|in:empty_all_trash'
        ]);

        DB::beginTransaction();

        try {
            $trashedUsers = User::onlyTrashed()
                ->where('type', '!=', User::TYPE_DEVELOPER)
                ->get();

            $count = $trashedUsers->count();
            $deletedCount = 0;
            $failedUsers = [];

            foreach ($trashedUsers as $user) {
                try {
                    if ($user->id === $currentUser->id) {
                        $failedUsers[] = "Cannot delete your own account";
                        continue;
                    }

                    $user->roles()->detach();

                    if ($user->photo && Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                        Storage::disk('public')->delete('users/photos/' . $user->photo);
                    }

                    $user->forceDelete();
                    $deletedCount++;

                } catch (\Exception $e) {
                    $failedUsers[] = "{$user->name} (ID: {$user->id}): " . $e->getMessage();
                    Log::error("Failed to permanently delete user {$user->id} during empty trash: " . $e->getMessage());
                }
            }

            DB::commit();

            Log::info('Trash emptied', [
                'total_users' => $count,
                'deleted_count' => $deletedCount,
                'failed_count' => count($failedUsers),
                'deleted_by' => $currentUser->id
            ]);

            $message = "Trash emptied successfully! {$deletedCount} out of {$count} users were permanently deleted.";

            if (!empty($failedUsers)) {
                $message .= " Failed to delete: " . implode(', ', array_slice($failedUsers, 0, 3));
                if (count($failedUsers) > 3) {
                    $message .= " and " . (count($failedUsers) - 3) . " more";
                }
            }

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deletedCount,
                'total_count' => $count,
                'failed_users' => $failedUsers
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage(), [
                'error_trace' => $e->getTraceAsString()
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to empty trash: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias for forceDelete
     */
    public function forceDestroy(Request $request, $id)
    {
        return $this->forceDelete($request, $id);
    }

    // ==================== PROPERTY OWNER MANAGEMENT ====================

    /**
     * Display property owners with multi-role detection.
     */
    public function getPropertyOwners(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized to view property owners.');
        }

        $perPage = $this->getPerPage($request->get('per_page'));

        $ownersOnly = $request->boolean('owners_only', false);

        $baseQuery = User::query()
            ->when($ownersOnly, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereHas('roles', fn ($r) => $r->where('slug', 'landlord'))
                       ->orWhere('type', User::TYPE_LANDLORD);
                })
                ->whereHas('properties', fn ($p) => $p->whereNull('deleted_at'));
            }, function ($q) {
                $q->withPropertyAccess();
            });

        $propertyOwners = (clone $baseQuery)
            ->with([
                'properties' => function ($q) {
                    $q->select('id', 'landlord_id', 'property_name', 'digital_address', 'status', 'created_at');
                },
                'roles',
                'linkedProperties:id,property_name,digital_address,status',
            ])
            ->withCount([
                'ownedProperties as properties_count',
                'approvedFamilyLinks as linked_properties_count',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('multi_role'), function ($query) use ($request) {
                if ($request->multi_role === 'yes') {
                    $query->has('roles', '>', 1);
                } elseif ($request->multi_role === 'no') {
                    $query->has('roles', '=', 1);
                }
            })
            ->orderBy('name')
            ->paginate($perPage);

        $propertyOwners->getCollection()->transform(function ($user) {
            $user->property_count        = (int) ($user->properties_count ?? 0);
            $user->active_property_count = $user->properties
                ? $user->properties->where('status', 'active')->count()
                : 0;

            $user->linked_property_count = (int) ($user->linked_properties_count ?? 0);

            $user->accessible_property_count = $user->property_count + $user->linked_property_count;

            $user->is_linked_only        = $user->property_count === 0 && $user->linked_property_count > 0;
            $user->is_multi_role         = $user->roles->count() > 1;
            $user->is_admin_and_landlord = $user->hasRole('admin') && $user->hasRole('landlord');
            $user->role_names            = $user->roles->pluck('display_name', 'slug')->implode(', ');

            return $user;
        });

        $statsBase = (clone $baseQuery);

        $stats = [
            'total_property_owners' => (clone $statsBase)->count(),

            'total_properties' => Property::count(),

            'multi_role_property_owners' => (clone $statsBase)
                ->has('roles', '>', 1)
                ->count(),

            'linked_only_members' => User::query()
                ->withPropertyAccess()
                ->whereDoesntHave('ownedProperties')
                ->whereHas('approvedFamilyLinks')
                ->count(),

            'multi_role_breakdown' => [
                'admin_landlords' => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('roles', fn($q) => $q->where('slug', 'admin'))
                    ->whereHas('properties')->count(),
                'super_admin_landlords' => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('roles', fn($q) => $q->where('slug', 'super-admin'))
                    ->whereHas('properties')->count(),
                'field_agent_landlords' => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('roles', fn($q) => $q->where('slug', 'field-agent'))
                    ->whereHas('properties')->count(),
                'security_landlords' => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('roles', fn($q) => $q->where('slug', 'security-personnel'))
                    ->whereHas('properties')->count(),
                'tenant_landlords' => User::whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                    ->whereHas('roles', fn($q) => $q->where('slug', 'tenant'))
                    ->whereHas('properties')->count(),
            ],

            'property_owners_without_phone' => (clone $statsBase)->whereNull('phone')->count(),
            'property_owners_without_email' => (clone $statsBase)->whereNull('email')->count(),

            'avg_properties_per_owner' => round(
                (float) (clone $statsBase)
                    ->withCount(['ownedProperties', 'approvedFamilyLinks'])
                    ->get()
                    ->avg(fn ($u) => ($u->owned_properties_count ?? 0) + ($u->approved_family_links_count ?? 0))
                ?? 0,
                1
            ),
        ];

        $perPageOptions = self::PER_PAGE_OPTIONS;

        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'property_owners' => $propertyOwners,
                'stats'           => $stats,
                'pagination' => [
                    'current_page' => $propertyOwners->currentPage(),
                    'last_page'    => $propertyOwners->lastPage(),
                    'per_page'     => $propertyOwners->perPage(),
                    'total'        => $propertyOwners->total(),
                ],
            ]);
        }

        return view('admin.users.property-owners', compact(
            'propertyOwners', 'stats', 'perPage', 'perPageOptions'
        ));
    }

    /**
     * Export property owners
     */
    public function exportPropertyOwners(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized to export property owners.');
        }

        $format = $request->get('export_format', 'csv');
        $includePhotos = $request->boolean('include_photos', false);

        $propertyOwners = User::where(function($query) {
            $query->whereHas('roles', fn($q) => $q->where('slug', 'landlord'))
                  ->orWhere('type', User::TYPE_LANDLORD);
        })
        ->whereHas('properties')
        ->with(['properties' => function($q) {
            $q->select('id', 'landlord_id', 'property_name', 'digital_address', 'status', 'property_type', 'city');
        }, 'roles', 'creator'])
        ->get();

        if ($format === 'csv') {
            $fileName = 'property-owners-' . date('Y-m-d-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            $callback = function() use ($propertyOwners) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'Name', 'Email', 'Phone', 'Property Count', 'Active Properties',
                    'Has Landlord Role', 'Multi-Role', 'Other Roles', 'Joined Date',
                    'Last Login', 'Status', 'Properties List'
                ]);

                foreach ($propertyOwners as $owner) {
                    $propertyNames = $owner->properties->take(5)->pluck('property_name')->implode('; ');
                    $totalProperties = $owner->properties->count();
                    if ($totalProperties > 5) {
                        $propertyNames .= " (+" . ($totalProperties - 5) . " more)";
                    }

                    $otherRoles = $owner->roles->where('slug', '!=', 'landlord')->pluck('display_name')->implode(', ');

                    fputcsv($file, [
                        $owner->name,
                        $owner->email,
                        $owner->phone,
                        $totalProperties,
                        $owner->properties->where('status', 'active')->count(),
                        $owner->hasRole('landlord') ? 'Yes' : 'No',
                        $owner->roles->count() > 1 ? 'Yes' : 'No',
                        $otherRoles ?: 'None',
                        $owner->created_at->format('Y-m-d'),
                        $owner->last_login_at?->format('Y-m-d H:i') ?? 'Never',
                        ucfirst($owner->status),
                        $propertyNames,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        if ($format === 'pdf') {
            $totalUsers = $propertyOwners->count();
            $exportDate = date('F j, Y, g:i A');
            $statistics = $this->calculateExportStatistics($propertyOwners);

            $systemName = config('app.name', 'Property Management System');
            $systemShortName = config('app.short_name', 'PMS');
            $systemTagline = config('app.tagline', 'Smart Property Management');

            $systemLogo = null;
            if ($includePhotos && $logoPath = config('app.logo_path')) {
                $systemLogo = $this->convertImageToBase64($logoPath);
            }

            if ($includePhotos) {
                foreach ($propertyOwners as $user) {
                    $user->photo_base64 = $this->getUserPhotoBase64($user);
                }
            }

            $data = [
                'users' => $propertyOwners,
                'totalUsers' => $totalUsers,
                'exportDate' => $exportDate,
                'exportedBy' => $currentUser->name,
                'systemName' => $systemName,
                'systemShortName' => $systemShortName,
                'systemTagline' => $systemTagline,
                'systemLogo' => $systemLogo,
                'includeStatistics' => $request->boolean('include_statistics', true),
                'includePhotos' => $includePhotos,
                'filters' => $request->all(),
                'statistics' => $statistics,
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.users.exports.property-owners-pdf', $data);

            $pdf->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
                'defaultPaperSize' => 'a4',
                'margin_top' => 10,
                'margin_bottom' => 15,
                'margin_left' => 10,
                'margin_right' => 10,
            ]);

            $fileName = 'property-owners-' . date('Y-m-d-His') . '.pdf';

            return $pdf->download($fileName);
        }

        return back()->with('error', 'Unsupported export format.');
    }

    /**
     * Calculate export statistics for users
     */
    private function calculateExportStatistics($users): array
    {
        $total = $users->count();

        $typeBreakdown = [];
        foreach ($users->groupBy('type') as $type => $group) {
            $typeBreakdown[$type] = [
                'count' => $group->count(),
                'percentage' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0,
            ];
        }

        $statusBreakdown = [];
        foreach ($users->groupBy('status') as $status => $group) {
            $statusBreakdown[$status] = [
                'count' => $group->count(),
                'percentage' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0,
            ];
        }

        $verifiedPhones = $users->filter(fn($u) => !is_null($u->phone_verified_at))->count();
        $verifiedEmails = $users->filter(fn($u) => !is_null($u->email_verified_at))->count();
        $withPhotos = $users->filter(fn($u) => !empty($u->photo))->count();
        $propertyOwners = $users->filter(fn($u) => $u->is_property_owner)->count();

        $completionRate = [
            'phone_verification' => $total > 0 ? round(($verifiedPhones / $total) * 100, 1) : 0,
            'email_verification' => $total > 0 ? round(($verifiedEmails / $total) * 100, 1) : 0,
            'profile_photos' => $total > 0 ? round(($withPhotos / $total) * 100, 1) : 0,
        ];

        $multiRoleCount = $users->filter(fn($u) => $u->roles->count() > 1)->count();
        $landlordRoleCount = $users->filter(fn($u) => $u->hasRole('landlord'))->count();

        return [
            'type_breakdown' => $typeBreakdown,
            'status_breakdown' => $statusBreakdown,
            'verified_phones' => $verifiedPhones,
            'verified_emails' => $verifiedEmails,
            'with_photos' => $withPhotos,
            'property_owners' => $propertyOwners,
            'multi_role_users' => $multiRoleCount,
            'landlord_role_count' => $landlordRoleCount,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Convert image to base64 for PDF embedding
     */
    private function convertImageToBase64($imagePath): ?string
    {
        try {
            if (!file_exists($imagePath)) {
                return null;
            }

            $imageContent = file_get_contents($imagePath);
            if ($imageContent === false) {
                return null;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageContent);
            finfo_close($finfo);

            return 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);

        } catch (\Exception $e) {
            \Log::warning('Failed to convert image to base64: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user photo as base64 for PDF
     */
    private function getUserPhotoBase64(User $user): ?string
    {
        if (empty($user->photo)) {
            return null;
        }

        try {
            $photoContent = null;

            if (Storage::disk('public')->exists('users/photos/' . $user->photo)) {
                $photoContent = Storage::disk('public')->get('users/photos/' . $user->photo);
            } elseif (Storage::disk('public')->exists($user->photo)) {
                $photoContent = Storage::disk('public')->get($user->photo);
            } elseif (file_exists(public_path('storage/users/photos/' . $user->photo))) {
                $photoContent = file_get_contents(public_path('storage/users/photos/' . $user->photo));
            } elseif (file_exists(public_path($user->photo))) {
                $photoContent = file_get_contents(public_path($user->photo));
            } elseif (filter_var($user->photo, FILTER_VALIDATE_URL)) {
                $photoContent = @file_get_contents($user->photo);
            }

            if (empty($photoContent)) {
                return null;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $photoContent);
            finfo_close($finfo);

            return 'data:' . $mimeType . ';base64,' . base64_encode($photoContent);

        } catch (\Exception $e) {
            \Log::warning('Failed to get user photo base64: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
            return null;
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Get pagination per page value
     */
    private function getPerPage($requestedPerPage): int
    {
        $perPage = (int) $requestedPerPage;

        if (!in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return $perPage;
    }

    /**
     * Get filtered user types based on permissions
     */
    private function getFilteredUserTypes(User $currentUser, ?User $targetUser = null): array
    {
        $userTypes = User::getUserTypes();

        unset($userTypes[User::TYPE_DEVELOPER]);
        unset($userTypes[User::TYPE_SUPER_ADMIN]);

        if (!$currentUser->isSuperAdmin()) {
            unset($userTypes[User::TYPE_ADMIN]);
        }

        return $userTypes;
    }

    /**
     * Authorize user view
     */
    private function authorizeUserView(User $user, User $currentUser): void
    {
        if ($user->type === User::TYPE_DEVELOPER) {
            abort(403, 'Cannot view developer accounts.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to view this user.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'You can only view your own Super Admin account.');
        }
    }

    /**
     * Authorize user edit
     */
    private function authorizeUserEdit(User $user, User $currentUser): void
    {
        if ($user->type === User::TYPE_DEVELOPER) {
            abort(403, 'Cannot edit developer accounts.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to edit Super Admin users.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'You can only edit your own Super Admin account.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'Unauthorized to edit other Admin users.');
        }
    }

    /**
     * Authorize password change
     */
    private function authorizePasswordChange(User $user, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to change Super Admin passwords.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'You can only change your own Super Admin password.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'Unauthorized to change other Admin passwords.');
        }
    }

    /**
     * Authorize invitation send
     */
    private function authorizeInvitationSend(User $user, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to send invitations to Super Admin users.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, 'You can only send invitations for your own Super Admin account.');
        }
    }

    /**
     * Authorize user deletion
     */
    private function authorizeUserDeletion(User $user, User $currentUser): void
    {
        if ($user->type === User::TYPE_DEVELOPER) {
            abort(403, 'Cannot delete developer accounts.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to delete Super Admin accounts.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'You cannot delete other Super Admin accounts.');
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_ADMIN) {
            abort(403, 'Unauthorized to delete Admin accounts.');
        }
    }

    /**
     * Authorize user restore
     */
    private function authorizeUserRestore(User $user, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to restore Super Admin accounts.');
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, 'You cannot restore other Super Admin accounts.');
        }
    }

    /**
     * Find user and authorize action
     */
    private function findUserAndAuthorizeAction($id, string $action): User
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        if ($user->type === User::TYPE_DEVELOPER) {
            abort(403, "Cannot {$action} developer accounts.");
        }

        if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
            abort(403, "Unauthorized to {$action} Super Admin accounts.");
        }

        if ($currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN && $user->id !== $currentUser->id) {
            abort(403, "You cannot {$action} other Super Admin accounts.");
        }

        return $user;
    }

    /**
     * Null-safe restore check.
     */
    private function canRestoreUser(User $user): bool
    {
        $exists = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($user) {
                $hasCondition = false;

                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                    $hasCondition = true;
                }

                if (!empty($user->phone)) {
                    $q->orWhere('phone', $user->phone);
                    $hasCondition = true;
                }

                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->exists();

        return !$exists;
    }

    /**
     * Check critical relations for deletion.
     */
    private function checkCriticalRelations(User $user): array
    {
        $criticalRelations = [];

        if ($user->type === User::TYPE_LANDLORD || $user->hasRole('landlord')) {
            $propertyCount = $user->properties()->count();
            if ($propertyCount > 0) {
                $criticalRelations[] = "{$propertyCount} properties";
            }
        }

        if ($user->type === User::TYPE_TENANT) {
            $approvedUnitCount = \App\Models\PropertyUnit::where('tenant_id', $user->id)
                ->where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                ->count();

            if ($approvedUnitCount > 0) {
                $criticalRelations[] = "{$approvedUnitCount} assigned unit(s)";
            }
        }

        if ($user->type === User::TYPE_FIELD_AGENT) {
            if ($user->assignedPlans()->where('status', 'active')->exists()) {
                $criticalRelations[] = 'active assignments';
            }
        }

        if (in_array($user->type, [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])) {
            $createdUsers = User::where('created_by', $user->id)->count();
            if ($createdUsers > 0) {
                $criticalRelations[] = "{$createdUsers} created users";
            }
        }

        return $criticalRelations;
    }

    /**
     * Process single bulk action
     */
    private function processBulkAction($userId, string $action, array $data, User $currentUser): array
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return ['id' => $userId, 'success' => false, 'message' => 'User not found'];
            }

            if (!$currentUser->isSuperAdmin() && $user->type === User::TYPE_SUPER_ADMIN) {
                return ['id' => $userId, 'success' => false, 'message' => 'Unauthorized action on Super Admin'];
            }

            if ($user->id === auth()->id() && in_array($action, ['suspend', 'deactivate', 'delete'])) {
                return ['id' => $userId, 'success' => false, 'message' => 'Cannot perform action on your own account'];
            }

            switch ($action) {
                case 'activate':
                    $user->update(['status' => User::STATUS_ACTIVE]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account activated'];

                case 'suspend':
                    $user->update([
                        'status' => User::STATUS_SUSPENDED,
                        'suspension_reason' => $data['reason'] ?? 'Bulk action suspension'
                    ]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account suspended'];

                case 'deactivate':
                    $user->update([
                        'status' => User::STATUS_INACTIVE,
                        'deactivation_reason' => $data['reason'] ?? 'Bulk action deactivation'
                    ]);
                    return ['id' => $userId, 'success' => true, 'message' => 'Account deactivated'];

                case 'delete':
                    $criticalRelations = $this->checkCriticalRelations($user);
                    if (!empty($criticalRelations)) {
                        return ['id' => $userId, 'success' => false, 'message' => 'Has critical relations: ' . implode(', ', $criticalRelations)];
                    }

                    $this->userRepository->softDeleteUser($user, $currentUser, $data['reason'] ?? 'Bulk action deletion');
                    return ['id' => $userId, 'success' => true, 'message' => 'Account deleted'];

                case 'send_invitation':
                    $requestedChannels = $data['invitation_channels'] ?? null;

                    if (empty($requestedChannels)) {
                        $available = $this->invitationService->getAvailableChannels($user);

                        $requestedChannels = !empty($available)
                            ? array_keys(array_filter($available))
                            : ['sms'];
                    }

                    $invitationData = [
                        'invitation_channels' => $requestedChannels,
                        'invitation_type'     => $data['invitation_type'] ?? 'welcome',
                        'custom_message'      => $data['custom_message'] ?? null,
                        'expires_in_days'     => $data['expires_in_days'] ?? 7,
                    ];

                    $result = $this->invitationService->sendInvitation($user, $invitationData);

                    if ($result['success']) {
                        return ['id' => $userId, 'success' => true, 'message' => 'Invitation sent'];
                    }
                    return ['id' => $userId, 'success' => false, 'message' => $result['message']];

                default:
                    return ['id' => $userId, 'success' => false, 'message' => 'Unknown action'];
            }

        } catch (\Exception $e) {
            Log::error("Bulk action failed for user {$userId}: " . $e->getMessage());
            return ['id' => $userId, 'success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Load user relationships safely
     */
    private function loadUserRelationships(User $user): void
    {
        try {
            $user->load(['creator']);
        } catch (\Exception $e) {
            Log::warning('Failed to load creator: ' . $e->getMessage());
            $user->setRelation('creator', null);
        }

        try {
            $user->load(['properties' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            }]);
        } catch (\Exception $e) {
            Log::warning('Failed to load properties: ' . $e->getMessage());
            $user->setRelation('properties', collect());
        }

        try {
            $user->load(['invitations' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(5);
            }]);
        } catch (\Exception $e) {
            $user->setRelation('invitations', collect());
        }

        if ($user->isFieldAgent()) {
            try {
                $user->load(['assignedPlans' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10)]);
            } catch (\Exception $e) {
                $user->setRelation('assignedPlans', collect());
            }
        }
    }

    /**
     * Build success message for user creation
     */
    private function buildSuccessMessage(User $user, ?array $invitationResult): string
    {
        $message = 'User created successfully!';

        if ($user->type === User::TYPE_LANDLORD) {
            $message .= ' Landlord role has been automatically assigned.';
        }

        if ($user->type === User::TYPE_SANITATION_PERSONNEL && $user->sanitationPersonnel) {
            $role = $user->sanitationPersonnel->role ?? 'worker';
            $message .= " Sanitation personnel created with role: " . ucfirst($role) . ".";
        }

        if ($invitationResult && $invitationResult['success']) {
            $successful = $invitationResult['channels_successful'] ?? [];

            if (!empty($successful)) {
                $message .= " Invitation sent via " . implode(', ', $successful) . ".";
            } else {
                $message .= " Invitation processed.";
            }

            if (!empty($invitationResult['failed_channels'])) {
                $message .= " Failed channels: " . implode(', ', $invitationResult['failed_channels']) . ".";
            }
        }

        return $message;
    }

    /**
     * Build success message for user update
     */
    private function buildUpdateSuccessMessage(User $user, array $data): string
    {
        $message = 'User updated successfully!';

        if (($data['type'] ?? null) === User::TYPE_LANDLORD && !$user->hasRole('landlord')) {
            $message .= ' Landlord role has been automatically assigned.';
        }

        return $message;
    }

    /**
     * Get changes between old and new data
     */
    private function getChanges(array $oldData, array $newData): array
    {
        $changes = [];
        $excludedFields = ['updated_at', 'last_login_at', 'remember_token'];

        foreach ($newData as $key => $value) {
            if (in_array($key, $excludedFields)) {
                continue;
            }

            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Send password change notification via multiple channels
     */
    private function sendPasswordChangeNotification(User $user, array $channels = ['email']): void
    {
        try {
            $message = "🔐 Password Update Notification\n\n";
            $message .= "Hello {$user->name}!\n\n";
            $message .= "Your password has been updated by an administrator.\n";
            $message .= "If you did not request this change, please contact support immediately.\n\n";
            $message .= "Thank you for helping us keep your account secure.";

            $result = $this->multiChannelService->sendMessage(
                $user,
                $message,
                'password_change_notification',
                [
                    'changed_by' => auth()->id(),
                    'changed_at' => now()->toIso8601String(),
                    'channels' => $channels
                ],
                $channels
            );

            if (!$result['success']) {
                Log::warning('Password change notification partially failed', [
                    'user_id' => $user->id,
                    'failed_channels' => $result['failed_channels'] ?? []
                ]);
            }

        } catch (\Exception $e) {
            Log::warning("Failed to send password change notification: " . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /**
 * Consistent response for approve/reject actions.
 */
private function personnelApprovalResponse(
    Request $request,
    bool $success,
    string $message,
    int $status = 200
) {
    if ($request->expectsJson() || $request->ajax()) {
        return $this->cleanJsonResponse([
            'success' => $success,
            'message' => $message,
        ], $status);
    }

    return back()->with($success ? 'success' : 'error', $message);
}

    /**
     * Get communication service status with caching
     */
    private function getCommunicationServiceStatus(): array
    {
        try {
            return cache()->remember('communication_service_status', 300, function () {
                return [
                    'sms' => app(\App\Services\SmsService::class)->getSystemStatus() ?? [],
                    'email' => app(\App\Services\EmailService::class)->getSystemStatus() ?? [],
                    'whatsapp' => app(\App\Services\WhatsAppService::class)->getSystemStatus() ?? [],
                    'multi_channel' => $this->multiChannelService->getSystemStatus() ?? [],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get communication service status: ' . $e->getMessage());

            return [
                'sms' => ['enabled' => false, 'status' => 'error', 'message' => 'Service unavailable', 'can_send' => false],
                'email' => ['enabled' => false, 'status' => 'error', 'message' => 'Service unavailable', 'can_send' => false],
                'whatsapp' => ['enabled' => false, 'status' => 'error', 'message' => 'Service unavailable', 'can_send' => false],
                'multi_channel' => ['enabled' => false, 'status' => 'error', 'message' => 'Service unavailable', 'can_send' => false],
            ];
        }
    }

    /**
     * Handle deletion response
     */
    private function deletionResponse(Request $request, bool $success, string $message, ?string $redirectUrl = null)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->cleanJsonResponse([
                'success' => $success,
                'message' => $message,
                'redirect_url' => $redirectUrl,
            ], $success ? 200 : 422);
        }

        if ($success && $redirectUrl) {
            return redirect($redirectUrl)->with('success', $message);
        }

        return back()->with('error', $message);
    }

    /**
     * Export users to CSV or PDF
     */
    public function export(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized to export users.');
        }

        $format = $request->get('export_format', 'csv');
        $includePhotos = $request->boolean('include_photos', false);
        $includeStatistics = $request->boolean('include_statistics', true);

        $query = User::query();

        $userTypes = $request->input('user_types', ['all']);
        if (!in_array('all', $userTypes) && !empty($userTypes)) {
            $query->whereIn('type', $userTypes);
        }

        if ($request->boolean('current_filters')) {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('role') && $request->role !== 'all') {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('slug', $request->role);
                });
            }

            if ($request->filled('multi_role') && $request->multi_role === 'yes') {
                $query->has('roles', '>', 1);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->where('type', '!=', User::TYPE_DEVELOPER);
        $query->with(['roles', 'creator']);

        $users = $query->get();

        if ($format === 'csv') {
            return $this->exportToCsv($users, $request);
        }

        if ($format === 'pdf') {
            return $this->exportToPdf($users, $request, $includePhotos, $includeStatistics);
        }

        return back()->with('error', 'Unsupported export format.');
    }

    /**
     * Export users to CSV
     */
    private function exportToCsv($users, $request)
    {
        $fileName = 'users-export-' . date('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'ID', 'Name', 'Email', 'Phone', 'Username', 'Type', 'Roles',
                'Status', 'Email Verified', 'Phone Verified', 'Has Photo',
                'Property Owner', 'Property Count', 'Created At', 'Created By',
                'Last Login', 'Registration IP'
            ]);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->username,
                    ucfirst(str_replace('_', ' ', $user->type)),
                    $user->roles->pluck('display_name')->implode(', ') ?: 'None',
                    ucfirst($user->status),
                    $user->email_verified_at ? 'Yes' : 'No',
                    $user->phone_verified_at ? 'Yes' : 'No',
                    !empty($user->photo) ? 'Yes' : 'No',
                    $user->is_property_owner ? 'Yes' : 'No',
                    $user->property_count ?? $user->properties()->count(),
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->creator->name ?? 'System',
                    $user->last_login_at?->format('Y-m-d H:i:s') ?? 'Never',
                    $user->registration_ip ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export users to PDF
     */
    private function exportToPdf($users, $request, $includePhotos, $includeStatistics)
    {
        $obLevelBefore = ob_get_level();
        $obDumpBefore  = [];

        if ($obLevelBefore > 0) {
            for ($i = 0; $i < $obLevelBefore; $i++) {
                $contents = ob_get_contents();
                $obDumpBefore[] = [
                    'level'    => $i,
                    'length'   => strlen($contents ?: ''),
                    'preview'  => substr($contents ?: '', 0, 200),
                ];
            }
        }

        Log::info('PDF Export Started', [
            'user_count'          => $users->count(),
            'include_photos'      => $includePhotos,
            'include_statistics'  => $includeStatistics,
            'exported_by'         => auth()->id(),
            'ob_level_at_start'   => $obLevelBefore,
            'ob_dump_at_start'    => $obDumpBefore,
            'memory_usage'        => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory'         => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'user_agent'          => $request->userAgent(),
            'accept_header'       => $request->header('Accept'),
            'is_ajax'             => $request->ajax(),
            'wants_json'          => $request->wantsJson(),
        ]);

        $cleared = 0;
        while (ob_get_level() > 0) {
            ob_end_clean();
            $cleared++;
        }

        Log::info('PDF Export — Output buffers cleared', [
            'buffers_cleared'   => $cleared,
            'ob_level_now'      => ob_get_level(),
        ]);

        $totalUsers      = $users->count();
        $exportDate      = date('F j, Y, g:i A');
        $statistics      = $includeStatistics ? $this->calculateExportStatistics($users) : [];

        $systemName      = config('app.name', 'Property Management System');
        $systemShortName = config('app.short_name', 'PMS');
        $systemTagline   = config('app.tagline', 'Smart Property Management');
        $systemEmail     = config('app.email', '');
        $systemPhone     = config('app.phone', '');

        $systemLogo = null;
        $logoPath   = config('app.logo_path');
        if ($logoPath && file_exists(public_path($logoPath))) {
            $systemLogo = $this->convertImageToBase64(public_path($logoPath));
        }

        Log::debug('PDF Export — Logo & branding resolved', [
            'logo_path'        => $logoPath,
            'logo_exists'      => $logoPath ? file_exists(public_path($logoPath)) : false,
            'logo_base64_len'  => $systemLogo ? strlen($systemLogo) : 0,
            'system_name'      => $systemName,
            'system_short'     => $systemShortName,
        ]);

        if ($includePhotos) {
            $photosLoaded = 0;
            foreach ($users as $user) {
                $user->photo_base64 = $this->getUserPhotoBase64($user);
                if (!empty($user->photo_base64)) {
                    $photosLoaded++;
                }
            }
            Log::debug('PDF Export — User photos loaded', [
                'photos_requested' => $users->count(),
                'photos_loaded'    => $photosLoaded,
            ]);
        }

        $data = [
            'systemName'        => $systemName,
            'systemShortName'   => $systemShortName,
            'systemLogo'        => $systemLogo,
            'systemTagline'     => $systemTagline,
            'systemEmail'       => $systemEmail,
            'systemPhone'       => $systemPhone,
            'users'             => $users,
            'includePhotos'     => $includePhotos,
            'includeStatistics' => $includeStatistics,
            'exportDate'        => $exportDate,
            'exportedBy'        => auth()->user()->name,
            'totalUsers'        => $totalUsers,
            'filters'           => $request->all(),
            'userTypes'         => $users->groupBy('type')->map->count(),
            'statistics'        => $statistics,
        ];

        Log::debug('PDF Data Prepared', [
            'users_count'   => $users->count(),
            'has_users'     => $users->isNotEmpty(),
            'system_name'   => $systemName,
            'has_logo'      => !empty($systemLogo),
            'data_keys'     => array_keys($data),
            'memory_usage'  => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ]);

        $viewStartTime = microtime(true);

        try {
            $html = view('admin.users.exports.users-pdf', $data)->render();
        } catch (\Throwable $e) {
            Log::error('PDF Export — Blade render FAILED', [
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        $viewDuration = round((microtime(true) - $viewStartTime) * 1000, 2);

        Log::info('PDF Export — HTML view rendered', [
            'html_length'    => strlen($html),
            'render_time_ms' => $viewDuration,
            'html_preview'   => substr($html, 0, 300),
            'html_has_body'  => str_contains($html, '<body'),
            'html_has_table' => str_contains($html, 'table-container'),
            'html_ends_with' => substr($html, -100),
        ]);

        $pdfStartTime = microtime(true);

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

            $pdf->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled'         => false,
                'dpi'                  => 150,
                'defaultPaperSize'     => 'a4',
                'margin_top'           => 10,
                'margin_bottom'        => 15,
                'margin_left'          => 10,
                'margin_right'         => 10,
            ]);

            $output = $pdf->output();
        } catch (\Throwable $e) {
            Log::error('PDF Export — DomPDF render FAILED', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        $pdfDuration = round((microtime(true) - $pdfStartTime) * 1000, 2);
        $outputSize  = strlen($output);
        $pdfHeader   = bin2hex(substr($output, 0, 8));
        $pdfTrailer  = substr($output, -20);

        $fileName = 'users_export_' . date('Y-m-d_His') . '.pdf';

        Log::info('PDF Export Completed Successfully', [
            'file_name'         => $fileName,
            'user_count'        => $totalUsers,
            'system_name'       => $systemName,
            'pdf_size_bytes'    => $outputSize,
            'pdf_size_human'    => round($outputSize / 1024, 2) . ' KB',
            'pdf_header_hex'    => $pdfHeader,
            'pdf_header_ascii'  => substr($output, 0, 8),
            'pdf_trailer'       => $pdfTrailer,
            'is_valid_pdf'      => str_starts_with($output, '%PDF-'),
            'render_time_ms'    => $pdfDuration,
            'peak_memory'       => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'ob_level_at_end'   => ob_get_level(),
            'ob_contents_at_end' => ob_get_level() > 0 ? substr(ob_get_contents() ?: '', 0, 200) : null,
        ]);

        if ($outputSize === 0) {
            Log::error('PDF Export — Refusing to send empty PDF', [
                'html_length' => strlen($html),
                'user_count'  => $totalUsers,
            ]);
            abort(500, 'PDF generation produced empty output.');
        }

        if (!str_starts_with($output, '%PDF-')) {
            Log::error('PDF Export — Refusing to send malformed PDF', [
                'header_hex'  => $pdfHeader,
                'first_200'   => substr($output, 0, 200),
            ]);
            abort(500, 'PDF generation produced malformed output.');
        }

        Log::info('PDF Export — Dispatching HTTP response', [
            'file_name'      => $fileName,
            'content_length' => $outputSize,
            'content_type'   => 'application/pdf',
        ]);

        return response($output, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Length', (string) $outputSize)
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Export-Debug', 'size=' . $outputSize);
    }

    /**
     * Archive a landlord OR an unassigned tenant account.
     */
    public function archiveUser(Request $request, $id)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can archive users.'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            $isLandlord = $user->type === User::TYPE_LANDLORD;
            $isTenant   = $user->type === User::TYPE_TENANT;

            if (!$isLandlord && !$isTenant) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Only landlord or tenant accounts can be archived.'
                ], 422);
            }

            if ($user->trashed()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'This user is already archived.'
                ], 422);
            }

            if ($isLandlord) {
                $propertyCount = $user->properties()->count();
                if ($propertyCount > 0) {
                    return $this->cleanJsonResponse([
                        'success' => false,
                        'message' => "Cannot archive landlord with {$propertyCount} active property(s). Transfer all properties first.",
                        'property_count' => $propertyCount
                    ], 422);
                }
            }

            $approvedUnitCount = 0;
            if ($isTenant) {
                $approvedUnitCount = \App\Models\PropertyUnit::where('tenant_id', $user->id)
                    ->where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
                    ->count();

                if ($approvedUnitCount > 0) {
                    return $this->cleanJsonResponse([
                        'success' => false,
                        'message' => "Cannot archive tenant with {$approvedUnitCount} active property unit assignment(s). Vacate the unit first.",
                        'unit_count' => $approvedUnitCount
                    ], 422);
                }
            }

            $settings = SystemSetting::getSettings();
            $permanentDeletionDays = $settings->account_archival['permanent_deletion_days'] ?? 365;

            $defaultReason = $isTenant
                ? 'Tenant has not been assigned to any property unit - manual archival by admin'
                : 'Landlord has no properties - manual archival by admin';

            $archivalReason = $request->input('reason', $defaultReason);

            $originalEmail    = $user->email;
            $originalUsername = $user->username;
            $originalPhone    = $user->phone;

            $existingMetadata = [];
            if ($user->metadata) {
                if (is_string($user->metadata)) {
                    $existingMetadata = json_decode($user->metadata, true) ?? [];
                } elseif (is_array($user->metadata)) {
                    $existingMetadata = $user->metadata;
                }
            }

            $archivalInfo = [
                'archived_at'                   => now()->toISOString(),
                'archived_reason'               => $archivalReason,
                'archived_by'                   => $currentUser->id,
                'archived_by_name'              => $currentUser->name,
                'original_email'                => $originalEmail,
                'original_phone'                => $originalPhone,
                'original_username'             => $originalUsername,
                'archival_type'                 => 'manual_admin',
                'user_type_at_archival'         => $user->type,
                'property_count_at_archival'    => $isLandlord ? 0 : null,
                'unassigned_days_at_archival'   => $isTenant && $user->created_at
                    ? (int) $user->created_at->diffInDays(now())
                    : null,
                'days_until_permanent_deletion' => $permanentDeletionDays,
            ];

            $existingMetadata['archival_info'] = $archivalInfo;
            $existingMetadata['archived'] = [
                'archived_at'       => now()->toISOString(),
                'archived_by'       => $currentUser->id,
                'archived_by_name'  => $currentUser->name,
                'reason'            => $archivalReason,
                'original_email'    => $originalEmail,
                'original_username' => $originalUsername,
                'original_phone'    => $originalPhone,
                'user_type'         => $user->type,
            ];

            $deletionScheduledAt = now()->addDays($permanentDeletionDays);
            $existingMetadata['deletion_info'] = [
                'scheduled_at' => $deletionScheduledAt->toISOString(),
                'reason'       => 'Auto-deletion after archival period',
                'notified'     => false,
            ];

            $archivedEmail    = $this->getArchivedEmail($originalEmail, $user->id);
            $archivedUsername = $this->getArchivedUsername($originalUsername, $user->id);

            $user->update([
                'status'                => 'archived',
                'metadata'              => $existingMetadata,
                'deletion_scheduled_at' => $deletionScheduledAt,
                'deleted_by'            => $currentUser->id,
                'email'                 => $archivedEmail,
                'username'              => $archivedUsername,
            ]);

            $user->delete();

            Log::info('User account archived', [
                'user_id'                => $user->id,
                'user_name'              => $user->name,
                'user_type'              => $user->type,
                'original_email'         => $originalEmail,
                'archived_email'         => $archivedEmail,
                'archived_by'            => $currentUser->id,
                'archived_by_name'       => $currentUser->name,
                'reason'                 => $archivalReason,
                'deleted_at'             => $user->deleted_at,
                'deletion_scheduled_at'  => $deletionScheduledAt->toISOString(),
            ]);

            $this->sendArchivalNotification($user, $archivalReason, $deletionScheduledAt);

            $typeLabel = $isTenant ? 'Tenant' : 'Landlord';

            return $this->cleanJsonResponse([
                'success'  => true,
                'message'  => "{$typeLabel} account archived successfully! The account will be permanently deleted after {$permanentDeletionDays} days.",
                'deletion_scheduled_at' => $deletionScheduledAt->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to archive user: ' . $e->getMessage(), [
                'user_id'     => $id,
                'error_trace' => $e->getTraceAsString(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to archive user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get archived email
     */
    private function getArchivedEmail($originalEmail, $userId)
    {
        if (empty($originalEmail)) {
            return null;
        }

        if (strpos($originalEmail, '.archived.') !== false) {
            return $originalEmail;
        }

        $parts = explode('@', $originalEmail);
        $username = $parts[0];
        $domain = $parts[1] ?? 'example.com';

        return $username . '.archived.' . $userId . '@' . $domain;
    }

    /**
     * Get archived username
     */
    private function getArchivedUsername($originalUsername, $userId)
    {
        if (empty($originalUsername)) {
            return null;
        }

        if (strpos($originalUsername, '.archived.') !== false) {
            return $originalUsername;
        }

        return $originalUsername . '.archived.' . $userId;
    }

    /**
     * Send archival notification to user.
     */
    private function sendArchivalNotification(User $user, $reason, $deletionScheduledAt)
    {
        try {
            $originalEmail = $user->metadata['archival_info']['original_email'] ?? $user->email;

            if (!$originalEmail || !filter_var($originalEmail, FILTER_VALIDATE_EMAIL)) {
                Log::info('Archival notification skipped — no valid email on file', [
                    'user_id' => $user->id,
                    'user_type' => $user->type,
                ]);
                return;
            }

            $userType = $user->type;
            $isTenant = $userType === User::TYPE_TENANT;

            $subject = $isTenant
                ? 'Your tenant account has been archived'
                : 'Your landlord account has been archived';

            $body = $isTenant
                ? "Your tenant account has been archived. Reason: {$reason}\n\n" .
                  "If you are assigned to a property unit in the future, please contact support."
                : "Your landlord account has been archived. Reason: {$reason}\n\n" .
                  "To restore your account, contact support.";

            Log::info('Archival notification prepared', [
                'user_id'          => $user->id,
                'user_type'        => $userType,
                'email'            => $originalEmail,
                'subject'          => $subject,
                'reason'           => $reason,
                'deletion_date'    => $deletionScheduledAt->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to send archival notification: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Get user list for email composer.
     */
    public function getUserListForEmail(Request $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators and developers can access user lists.'
            ], 403);
        }

        try {
            $query = User::query()
                ->with(['emailAccounts' => function ($q) {
                    $q->orderByDesc('is_primary')
                      ->orderByRaw("CASE WHEN status = 'verified' THEN 1 ELSE 0 END DESC")
                      ->orderBy('id');
                }])
                ->where('type', '!=', User::TYPE_DEVELOPER)
                ->where(function ($q) {
                    $q->whereNotNull('email')
                      ->where('email', '!=', '')
                      ->orWhereHas('emailAccounts');
                });

            if ($currentUser->isAdmin() && !$currentUser->isSuperAdmin()) {
                $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
            }

            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $limit = min((int) $request->input('limit', 100), 500);

            $users = $query->select('id', 'name', 'email', 'type', 'photo', 'status')
                ->orderBy('name')
                ->limit($limit)
                ->get();

            $responseData = [
                'success' => true,
                'users' => $users->map(function ($user) {
                    $accounts = $user->emailAccounts ?? collect();

                    $sortedAccounts = $accounts->sortByDesc(function ($a) {
                        $primary  = (int) $a->getRawOriginal('is_primary');
                        $verified = $a->getRawOriginal('status') === 'verified' ? 1 : 0;
                        return ($primary * 100) + ($verified * 10);
                    })->values();

                    $firstAccount = $sortedAccounts->first();

                    $recipientEmail = null;
                    $recipientSource = null;

                    if ($firstAccount) {
                        $rawEmail = $firstAccount->getRawOriginal('email') ?: $firstAccount->email;
                        if (!empty($rawEmail)) {
                            $recipientEmail = $rawEmail;
                            $recipientSource = 'linked';
                        }
                    }

                    if (empty($recipientEmail) && !empty($user->email)) {
                        $recipientEmail = $user->email;
                        $recipientSource = 'profile';
                    }

                    if (empty($recipientEmail)) {
                        return null;
                    }

                    $accountMeta = $sortedAccounts->map(function ($a) {
                        return [
                            'email'      => $a->getRawOriginal('email') ?: $a->email,
                            'status'     => $a->getRawOriginal('status') ?: $a->status,
                            'is_primary' => (bool) $a->getRawOriginal('is_primary'),
                            'provider'   => $a->getRawOriginal('provider'),
                        ];
                    })->all();

                    return [
                        'id'               => $user->id,
                        'name'             => $user->name ?? 'Unknown User',
                        'email'            => $user->email,
                        'type'             => $user->type,
                        'type_label'       => $this->getUserTypeLabel($user->type),
                        'is_landlord'      => $user->type === User::TYPE_LANDLORD,
                        'has_photo'        => !empty($user->photo),
                        'photo_url'        => $user->photo_url ?? null,
                        'status'           => $user->status,
                        'status_label'     => $this->getStatusLabel($user->status),

                        'recipient_email'  => $recipientEmail,
                        'recipient_source' => $recipientSource,
                        'email_accounts'   => $accountMeta,
                    ];
                })
                ->filter()
                ->values(),
            ];

            $jsonResponse = response()->json($responseData);

            $content = $jsonResponse->getContent();
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
                $jsonResponse->setContent($content);
            }

            return $jsonResponse;

        } catch (\Exception $e) {
            \Log::error('Error loading users for email composer: ' . $e->getMessage(), [
                'user_id' => $currentUser->id,
                'error'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to get user type label
     */
    private function getUserTypeLabel(string $type): string
    {
        $labels = [
            User::TYPE_SUPER_ADMIN => 'Super Admin',
            User::TYPE_ADMIN => 'Admin',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
            User::TYPE_CONTRACTOR => 'Contractor',
            User::TYPE_DEVELOPER => 'Developer',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Helper method to get status label
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_PENDING => 'Pending',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE => 'Inactive',
            'archived' => 'Archived',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get supervisors that can be assigned to sanitation personnel.
     */
    private function getAvailableSanitationSupervisors(): array
    {
        try {
            return \App\Models\SanitationPersonnel::query()
                ->where(function ($q) {
                    $q->whereIn('role', \App\Models\SanitationPersonnel::SUPERVISOR_ROLES)
                      ->orWhere('can_be_supervisor', true);
                })
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(function ($personnel) {
                    $label = trim($personnel->full_name)
                        . ' (' . ucfirst($personnel->role) . ')'
                        . ($personnel->employee_id ? ' · ' . $personnel->employee_id : '');

                    return [$personnel->id => $label];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to load sanitation supervisors list', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
   /**
 * Approve a security-personnel submission from the Head of Security.
 *
 * - Clears the `awaiting_approval` flag
 * - Sets status to ACTIVE
 * - Records the approving admin in metadata
 * - If the head queued an invitation, sends it now (best-effort)
 * - Notifies the submitting head that the submission was approved
 */
public function approvePersonnel(Request $request, $id)
{
    $currentUser = auth()->user();

    if (! $currentUser->isSuperAdmin() && ! $currentUser->isAdmin()) {
        return $this->personnelApprovalResponse($request, false,
            'Unauthorized. Only administrators can approve personnel.', 403);
    }

    $user = User::find($id);

    if (! $user) {
        return $this->personnelApprovalResponse($request, false,
            'User not found.', 404);
    }

    if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
        return $this->personnelApprovalResponse($request, false,
            'This record is not security personnel.', 422);
    }

    $metadata = $user->metadata ?? [];
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true) ?? [];
    }

    if (empty($metadata['awaiting_approval'])) {
        return $this->personnelApprovalResponse($request, false,
            'This submission has already been reviewed.', 422);
    }

    DB::beginTransaction();

    try {
        $queuedInvitation    = $metadata['queued_invitation'] ?? null;
        $submittingHeadId    = $metadata['created_by_head_id'] ?? $user->created_by;
        $invitationDispatched = false;

        // Clear the pending flags and record approval.
        $metadata['awaiting_approval'] = false;
        $metadata['approved_by']       = $currentUser->id;
        $metadata['approved_by_name']  = $currentUser->name;
        $metadata['approved_at']       = now()->toISOString();

        $user->update([
            'status'   => User::STATUS_ACTIVE,
            'metadata' => $metadata,
        ]);

        // If the head pre-queued an invitation, trigger it now.
        if (is_array($queuedInvitation) && ! empty($queuedInvitation['channels'])) {
            try {
                $result = $this->invitationService->sendInvitation($user, [
                    'invitation_channels' => $queuedInvitation['channels'],
                    'invitation_type'     => $queuedInvitation['type'] ?? 'security_welcome',
                    'custom_message'      => $queuedInvitation['message'] ?? null,
                    'expires_in_days'     => (int) ($queuedInvitation['expires_in_days'] ?? 7),
                ]);

                if ($result['success']) {
                    $invitationDispatched = true;
                    $user->update(['invitation_sent_at' => now()]);

                    $metadata = $user->fresh()->metadata ?? [];
                    if (is_string($metadata)) {
                        $metadata = json_decode($metadata, true) ?? [];
                    }
                    $metadata['queued_invitation']['sent_at'] = now()->toISOString();
                    $metadata['queued_invitation']['channels_successful'] =
                        $result['channels_successful'] ?? [];
                    $user->update(['metadata' => $metadata]);

                    Log::info('Queued invitation dispatched on approval', [
                        'user_id'  => $user->id,
                        'channels' => $result['channels_successful'] ?? [],
                    ]);
                } else {
                    Log::warning('Queued invitation failed on approval', [
                        'user_id' => $user->id,
                        'message' => $result['message'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Queued invitation threw on approval', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        DB::commit();

        Log::info('Security personnel approved', [
            'user_id'              => $user->id,
            'user_name'            => $user->name,
            'approved_by'          => $currentUser->id,
            'approved_by_name'     => $currentUser->name,
            'submitted_by_head_id' => $submittingHeadId,
            'invitation_sent'      => $invitationDispatched,
        ]);

        // ------------------------------------------------------------
        // 🔧 NEW: notify the submitting Head of Security.
        //
        // Runs AFTER commit so a mail failure can't roll back the
        // approval. Wrapped in try/catch for the same reason: the
        // approval stands even if notification dispatch fails.
        // Skipped when the head is the same user as the approver
        // (self-approval — no point telling them they approved it).
        // ------------------------------------------------------------
        if ($submittingHeadId && (int) $submittingHeadId !== (int) $currentUser->id) {
            try {
                $head = User::find($submittingHeadId);

                if ($head) {
                    $head->notify(new \App\Notifications\SecurityPersonnelApprovedNotification(
                        personnel:            $user->fresh() ?? $user,
                        approvedBy:           $currentUser,
                        invitationDispatched: $invitationDispatched,
                    ));

                    Log::info('Approval notification dispatched to submitting head', [
                        'user_id' => $user->id,
                        'head_id' => $head->id,
                    ]);
                } else {
                    Log::warning('Submitting head not found — approval notification skipped', [
                        'user_id' => $user->id,
                        'head_id' => $submittingHeadId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Approval notification failed to dispatch', [
                    'user_id' => $user->id,
                    'head_id' => $submittingHeadId,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }
        }

        return $this->personnelApprovalResponse($request, true,
            "{$user->name} has been approved and activated.");
    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to approve security personnel: ' . $e->getMessage(), [
            'user_id'     => $id,
            'error_trace' => $e->getTraceAsString(),
        ]);

        return $this->personnelApprovalResponse($request, false,
            'Failed to approve: ' . $e->getMessage(), 500);
    }
}

/**
 * Reject a security-personnel submission.
 *
 * - Requires a reason
 * - Clears the `awaiting_approval` flag
 * - Records rejection details in metadata
 * - Soft-deletes the user so it disappears from active lists
 *   (the head sees it in their "pending" tab, still visible for context)
 * - Notifies the submitting head that the submission was rejected
 */
public function rejectPersonnel(Request $request, $id)
{
    $currentUser = auth()->user();

    if (! $currentUser->isSuperAdmin() && ! $currentUser->isAdmin()) {
        return $this->personnelApprovalResponse($request, false,
            'Unauthorized. Only administrators can reject personnel.', 403);
    }

    $validated = $request->validate([
        'reason' => ['required', 'string', 'max:500'],
    ]);

    $user = User::find($id);

    if (! $user) {
        return $this->personnelApprovalResponse($request, false,
            'User not found.', 404);
    }

    if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
        return $this->personnelApprovalResponse($request, false,
            'This record is not security personnel.', 422);
    }

    $metadata = $user->metadata ?? [];
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true) ?? [];
    }

    if (empty($metadata['awaiting_approval'])) {
        return $this->personnelApprovalResponse($request, false,
            'This submission has already been reviewed.', 422);
    }

    DB::beginTransaction();

    try {
        $submittingHeadId = $metadata['created_by_head_id'] ?? $user->created_by;

        $metadata['awaiting_approval'] = false;
        $metadata['rejected_by']       = $currentUser->id;
        $metadata['rejected_by_name']  = $currentUser->name;
        $metadata['rejected_at']       = now()->toISOString();
        $metadata['rejection_reason']  = $validated['reason'];

        // Preserve any prior deletion_info for the trash view
        $metadata['deletion_info'] = array_merge(
            $metadata['deletion_info'] ?? [],
            [
                'deleted_at'       => now()->toISOString(),
                'deleted_by'       => $currentUser->id,
                'deleted_by_name'  => $currentUser->name,
                'deletion_reason'  => 'Submission rejected: ' . $validated['reason'],
                'deletion_type'    => 'approval_rejection',
            ]
        );

        $user->update([
            'status'      => 'rejected',
            'metadata'    => $metadata,
            'deleted_by'  => $currentUser->id,
        ]);

        $user->delete(); // soft delete

        DB::commit();

        Log::info('Security personnel submission rejected', [
            'user_id'              => $user->id,
            'user_name'            => $user->name,
            'rejected_by'          => $currentUser->id,
            'rejected_by_name'     => $currentUser->name,
            'reason'               => $validated['reason'],
            'submitted_by_head_id' => $submittingHeadId,
        ]);

        // ------------------------------------------------------------
        // 🔧 NEW: notify the submitting Head of Security.
        //
        // Runs AFTER commit — rejection is already persisted, so a
        // notification failure must not change that. Skipped for
        // self-rejection.
        // ------------------------------------------------------------
        if ($submittingHeadId && (int) $submittingHeadId !== (int) $currentUser->id) {
            try {
                $head = User::find($submittingHeadId);

                if ($head) {
                    // Soft-deleted user — use withTrashed() so the
                    // notification carries correct data even though the
                    // record is now hidden from normal queries.
                    $personnel = User::withTrashed()->find($user->id) ?? $user;

                    $head->notify(new \App\Notifications\SecurityPersonnelRejectedNotification(
                        personnel:  $personnel,
                        rejectedBy: $currentUser,
                        reason:     $validated['reason'],
                    ));

                    Log::info('Rejection notification dispatched to submitting head', [
                        'user_id' => $user->id,
                        'head_id' => $head->id,
                    ]);
                } else {
                    Log::warning('Submitting head not found — rejection notification skipped', [
                        'user_id' => $user->id,
                        'head_id' => $submittingHeadId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Rejection notification failed to dispatch', [
                    'user_id' => $user->id,
                    'head_id' => $submittingHeadId,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }
        }

        return $this->personnelApprovalResponse($request, true,
            "{$user->name}'s submission was rejected.");
    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to reject security personnel: ' . $e->getMessage(), [
            'user_id'     => $id,
            'error_trace' => $e->getTraceAsString(),
        ]);

        return $this->personnelApprovalResponse($request, false,
            'Failed to reject: ' . $e->getMessage(), 500);
    }
}


}